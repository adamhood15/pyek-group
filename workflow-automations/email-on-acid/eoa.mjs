#!/usr/bin/env node
// Email on Acid test runner. No dependencies, requires Node 18+.
// Usage:
//   node workflow-automations/email-on-acid/eoa.mjs auth
//   node workflow-automations/email-on-acid/eoa.mjs clients
//   node workflow-automations/email-on-acid/eoa.mjs test <email.html> [--clients gmail,apple,outlook,yahoo|ids] [--image-blocking] [--sandbox] [--keep]
// Default clients: all Gmail, Apple, Outlook and Yahoo clients. Credentials come from the environment or a .env file (EOA_API_KEY, EOA_PASSWORD, optional EOA_CLIENTS).

import { readFileSync, existsSync, mkdirSync, writeFileSync } from "node:fs";
import { dirname, join, basename, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, "..", "..");
const BASE = "https://api.emailonacid.com/v5";
const POLL_MS = 10000;
const TIMEOUT_MS = 10 * 60 * 1000;

function loadEnv() {
  for (const dir of [here, repoRoot]) {
    const file = join(dir, ".env");
    if (!existsSync(file)) continue;
    for (const line of readFileSync(file, "utf8").split("\n")) {
      const m = line.match(/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/);
      if (m && !(m[1] in process.env)) process.env[m[1]] = m[2].replace(/^["']|["']$/g, "");
    }
  }
}

function parseArgs(argv) {
  const flags = {};
  const positional = [];
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--clients") flags.clients = argv[++i];
    else if (a === "--name") flags.name = argv[++i];
    else if (a.startsWith("--")) flags[a.slice(2)] = true;
    else positional.push(a);
  }
  return { flags, positional };
}

function authHeader(sandbox) {
  const key = sandbox ? "sandbox" : process.env.EOA_API_KEY;
  const pass = sandbox ? "sandbox" : process.env.EOA_PASSWORD;
  if (!key || !pass) {
    console.error("Missing EOA_API_KEY / EOA_PASSWORD. Copy .env.example to .env and fill them in.");
    process.exit(1);
  }
  return "Basic " + Buffer.from(`${key}:${pass}`).toString("base64");
}

async function api(method, path, auth, body) {
  const res = await fetch(BASE + path, {
    method,
    headers: { Authorization: auth, Accept: "application/json", "Content-Type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = { raw: text }; }
  if (!res.ok) {
    const e = data.error || {};
    throw new Error(`${method} ${path} -> ${res.status} ${e.name || ""} ${e.message || text}`.trim());
  }
  return data;
}

// Client groups, matched against the account's client IDs. Default run covers every group.
const GROUPS = {
  gmail: /^(gmailcom|android\d+_gmailapp)/,
  apple: /^(applemail|iphone(?!\d+ol_)|ipad|macmail)/,
  outlook: /^(outlook|m365|o365|iphone\d+ol_)/,
  yahoo: /^yahoo/,
};

async function resolveClients(auth, spec) {
  const tokens = (spec || Object.keys(GROUPS).join(",")).split(",").map((s) => s.trim()).filter(Boolean);
  const { clients } = await api("GET", "/email/clients", auth);
  const ids = Object.keys(clients);
  const out = new Set();
  for (const t of tokens) {
    if (GROUPS[t]) ids.filter((id) => GROUPS[t].test(id)).forEach((id) => out.add(id));
    else out.add(t);
  }
  return [...out];
}

// "CBV-26-078-Costume-Contest-Results-Winner-Oct26.html" -> "Costume Contest Results - CBV - Winner"
const VARIANTS = ["Winner", "Loser", "Control", "Variant", "A", "B"];
function testName(file) {
  if (basename(file) === "index.html") {
    const dir = dirname(resolve(file));
    const meta = join(dir, "campaign.json");
    if (existsSync(meta)) {
      const name = JSON.parse(readFileSync(meta, "utf8")).test_name;
      if (name) return name;
    }
    const campaign = basename(dirname(dir));
    const variant = basename(dir);
    return `${testName(campaign + ".html")} - ${variant[0].toUpperCase()}${variant.slice(1)}`;
  }
  const parts = basename(file, ".html").split("-");
  const brand = parts[0];
  let words = parts.slice(1);
  while (words.length && /^\d+$/.test(words[0])) words.shift();
  if (words.length && /^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\d{2}$/i.test(words[words.length - 1])) words.pop();
  const variant = words.length > 1 && VARIANTS.includes(words[words.length - 1]) ? words.pop() : "";
  return [words.join(" "), brand, variant].filter(Boolean).join(" - ");
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function download(url, auth, dest) {
  const res = await fetch(url, { headers: { Authorization: auth } });
  if (!res.ok) return false;
  writeFileSync(dest, Buffer.from(await res.arrayBuffer()));
  return true;
}

async function cmdAuth(auth) {
  const r = await api("GET", "/auth", auth);
  console.log(r.success ? "Credentials OK" : "Credentials rejected");
}

async function cmdClients(auth) {
  const { clients } = await api("GET", "/email/clients", auth);
  const rows = Object.values(clients).sort((a, b) => a.category.localeCompare(b.category) || a.client.localeCompare(b.client));
  for (const c of rows) console.log(`${c.id.padEnd(24)} ${c.category.padEnd(12)} ${c.client} (${c.os})${c.default ? "  [default]" : ""}`);
}

async function cmdTest(auth, file, flags) {
  if (!file) { console.error("Pass the path of an HTML email."); process.exit(1); }
  const html = readFileSync(file, "utf8");
  const subject = flags.name || testName(file);
  const clientList = flags.sandbox ? [] : await resolveClients(auth, flags.clients || process.env.EOA_CLIENTS);

  const body = { subject, html, charset: "utf-8", image_blocking: Boolean(flags["image-blocking"]) };
  if (clientList.length) body.clients = clientList;
  if (flags.sandbox) body.sandbox = true;

  const created = await api("POST", "/email/tests", auth, body);
  const id = created.id;
  console.log(`Created test ${id} for ${subject} (${clientList.length || "sandbox"} clients)`);

  const start = Date.now();
  let info;
  while (true) {
    info = await api("GET", `/email/tests/${id}`, auth);
    const pending = (info.processing || []).length;
    console.log(`  ${(info.completed || []).length} complete, ${pending} processing, ${(info.bounced || []).length} bounced`);
    if (!pending) break;
    if (Date.now() - start > TIMEOUT_MS) { console.error("Timed out waiting for screenshots."); break; }
    await sleep(POLL_MS);
  }

  const results = await api("GET", `/email/tests/${id}/results`, auth);
  const slug = basename(file) === "index.html" ? subject.replace(/[^A-Za-z0-9]+/g, "-").replace(/^-|-$/g, "") : basename(file, ".html");
  const outDir = join(repoRoot, ".eoa-results", slug);
  mkdirSync(outDir, { recursive: true });

  const lines = [`# Email on Acid: ${basename(file)}`, "", `Test ID: ${id}`, `Subject: ${subject}`, ""];
  for (const r of Object.values(results)) {
    const shot = r.screenshots?.default;
    let saved = "";
    if (r.status === "Complete" && shot) {
      const dest = join(outDir, `${r.id}.png`);
      if (await download(shot, auth, dest)) saved = dest;
    }
    const note = r.status === "Bounced" ? ` (${r.status_details?.bounce_message || "bounced"})` : "";
    lines.push(`- ${r.display_name || r.id} [${r.category}]: ${r.status}${note}${saved ? ` -> ${saved}` : ""}`);
    console.log(`${r.status.padEnd(10)} ${r.display_name || r.id}${note}`);
  }
  writeFileSync(join(outDir, "report.md"), lines.join("\n") + "\n");
  console.log(`\nReport and screenshots: ${outDir}`);

  if (!flags.keep) {
    await api("DELETE", `/email/tests/${id}`, auth);
    console.log("Test deleted from Email on Acid (screenshots kept locally). Use --keep to retain it online.");
  } else {
    console.log(`Test kept online: ${id}`);
  }
  if ((info.bounced || []).length) process.exitCode = 2;
}

loadEnv();
const { flags, positional } = parseArgs(process.argv.slice(2));
const [cmd, arg] = positional;
const auth = authHeader(flags.sandbox);

try {
  if (cmd === "auth") await cmdAuth(auth);
  else if (cmd === "clients") await cmdClients(auth);
  else if (cmd === "test") await cmdTest(auth, arg, flags);
  else { console.error("Commands: auth | clients | test <email.html> [--clients a,b] [--image-blocking] [--sandbox] [--keep]"); process.exit(1); }
} catch (e) {
  console.error(e.message);
  process.exit(1);
}
