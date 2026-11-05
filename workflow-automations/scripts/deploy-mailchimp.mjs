import "dotenv/config";
import { readFile } from "node:fs/promises";
import { resolve } from "node:path";
import { PARK_INTERESTS } from "./tt-park-interests.mjs";
import { TEST_RECIPIENTS } from "./test-recipients.mjs";

const [campaignDirArg, ...flags] = process.argv.slice(2);
if (!campaignDirArg) {
  throw new Error("Usage: node scripts/deploy-mailchimp.mjs <campaign-directory> [--test | --test-approved]");
}

const sendInternalTest = flags.includes("--test");
const sendApprovedTest = flags.includes("--test-approved");
if (sendInternalTest && sendApprovedTest) {
  throw new Error("Use either --test (internal group) or --test-approved (approval group), not both");
}
const dir = resolve(campaignDirArg);
const config = JSON.parse(await readFile(resolve(dir, "campaign.json"), "utf8"));
const html = await readFile(resolve(dir, "index.html"), "utf8");

const requiredFields = ["campaign_name", "subject", "preview_text", "from_name", "from_email", "to_name"];
const missingFields = requiredFields.filter(key => typeof config[key] !== "string" || !config[key].trim());
if (missingFields.length) {
  throw new Error(`campaign.json is missing required fields: ${missingFields.join(", ")}`);
}

const senders = {
  "Cowabunga Vegas": "donotreply@cowabungavegas.com",
  "Typhoon Texas Austin": "doublet@typhoontexas.com",
  "Typhoon Texas Houston": "doublet@typhoontexas.com",
  "Daytona Lagoon": "lagoonclub@updates.daytonalagoon.com"
};
if (senders[config.from_name] !== config.from_email) {
  throw new Error(`from_name/from_email must be one of: ${Object.entries(senders).map(([name, email]) => `${name} <${email}>`).join(", ")}`);
}

const apiKey = process.env.MAILCHIMP_API_KEY;
const audienceEnvNames = {
  CBV: "MAILCHIMP_AUDIENCE_CBV",
  TT: "MAILCHIMP_AUDIENCE_TT",
  DTL: "MAILCHIMP_AUDIENCE_DTL"
};
const audienceEnvName = audienceEnvNames[config.audience];
if (!apiKey || !audienceEnvName) {
  throw new Error("Missing API key or invalid campaign audience (CBV, TT, DTL)");
}
const dc = apiKey.match(/-([a-z]{2}\d+)$/i)?.[1];
if (!dc) throw new Error("Cannot derive Mailchimp data center from API key suffix");
const audienceId = process.env[audienceEnvName];
if (!audienceId) throw new Error(`Missing GitHub variable ${audienceEnvName}`);

// Fail closed: targeting must be explicit. TT campaigns take their interest from the verified
// park mapping in tt-park-interests.mjs; other audiences may list interest_ids with
// interest_category_id. A rejected or missing segment never falls back to the full audience.
let targeting = null;
if (config.audience === "TT") {
  if (!["houston", "austin"].includes(config.park)) {
    throw new Error("TT campaigns require park houston or austin");
  }
  const mapped = PARK_INTERESTS[config.park];
  if (!mapped?.category_id || !mapped?.interest_id) {
    throw new Error(`No verified interest mapping for park "${config.park}" in tt-park-interests.mjs`);
  }
  if ((config.interest_ids !== undefined &&
       JSON.stringify(config.interest_ids) !== JSON.stringify([mapped.interest_id])) ||
      (config.interest_category_id !== undefined && config.interest_category_id !== mapped.category_id)) {
    throw new Error(`campaign.json interest settings do not match the verified mapping for park "${config.park}"`);
  }
  targeting = { categoryId: mapped.category_id, interestIds: [mapped.interest_id] };
} else if (config.interest_ids !== undefined) {
  if (!Array.isArray(config.interest_ids) || config.interest_ids.length === 0 ||
      config.interest_ids.some(id => typeof id !== "string" || !id.trim()) ||
      typeof config.interest_category_id !== "string" || !config.interest_category_id.trim()) {
    throw new Error("interest_ids must be nonempty Mailchimp interest IDs and require interest_category_id");
  }
  targeting = { categoryId: config.interest_category_id, interestIds: config.interest_ids };
}
if (!html.trim()) throw new Error("HTML is empty");

const baseUrl = `https://${dc}.api.mailchimp.com/3.0`;
const auth = "Basic " + Buffer.from(`anystring:${apiKey}`).toString("base64");

async function mailchimp(path, method = "GET", payload) {
  const response = await fetch(`${baseUrl}${path}`, {
    method,
    headers: {
      Authorization: auth,
      Accept: "application/json",
      ...(payload === undefined ? {} : { "Content-Type": "application/json" })
    },
    ...(payload === undefined ? {} : { body: JSON.stringify(payload) })
  });
  const body = await response.text();
  let data;
  try { data = body ? JSON.parse(body) : {}; } catch { data = { raw: body }; }
  if (!response.ok) {
    throw new Error(`Mailchimp ${method} ${path}: HTTP ${response.status}: ${JSON.stringify(data)}`);
  }
  return data;
}

// Confirm each interest exists in the stated category before touching any campaign.
if (targeting) {
  for (const interestId of targeting.interestIds) {
    const interest = await mailchimp(
      `/lists/${encodeURIComponent(audienceId)}/interest-categories/${encodeURIComponent(targeting.categoryId)}/interests/${encodeURIComponent(interestId)}`
    );
    console.log(`Verified interest ${interestId}: ${interest.name}`);
  }
}

// interestcontainsall requires a subscriber to belong to every listed interest.
const recipients = {
  list_id: audienceId,
  ...(targeting
    ? { segment_opts: { match: "all", conditions: [{
        condition_type: "Interests",
        field: `interests-${targeting.categoryId}`,
        op: "interestcontainsall",
        value: targeting.interestIds
      }] } }
    : {})
};

const settings = {
  title: config.campaign_name,
  subject_line: config.subject,
  preview_text: config.preview_text,
  from_name: config.from_name,
  reply_to: config.from_email,
  to_name: config.to_name
};

let id = config.mailchimp_campaign_id;
if (id) {
  const existing = await mailchimp(`/campaigns/${encodeURIComponent(id)}`);
  if (existing.status !== "save") {
    throw new Error(`Refusing to modify campaign ${id}: status is ${existing.status}, not draft/save`);
  }
  if (existing.recipients?.list_id !== audienceId) {
    throw new Error(`Audience mismatch on campaign ${id}; refusing to update`);
  }
  await mailchimp(`/campaigns/${encodeURIComponent(id)}`, "PATCH", { settings, recipients });
  console.log(`Updated draft settings: ${id}`);
} else {
  const created = await mailchimp("/campaigns", "POST", {
    type: "regular",
    recipients,
    settings
  });
  id = created.id;
  console.log(`CREATED DRAFT: ${id}`);
  console.log(`ACTION REQUIRED: set "mailchimp_campaign_id": "${id}" in ${resolve(dir, "campaign.json")} and commit it before another deployment.`);
}

await mailchimp(`/campaigns/${encodeURIComponent(id)}/content`, "PUT", { html });
console.log(`Uploaded HTML to draft ${id}`);

if (sendInternalTest || sendApprovedTest) {
  const stage = sendApprovedTest ? "approved" : "internal";
  const testEmails = TEST_RECIPIENTS[stage];
  await mailchimp(`/campaigns/${encodeURIComponent(id)}/actions/test`, "POST", {
    test_emails: testEmails,
    send_type: "html"
  });
  console.log(`Requested HTML test send (${stage} group) to: ${testEmails.join(", ")}`);
}

console.log("Deployment finished. No live send or schedule action was invoked.");
