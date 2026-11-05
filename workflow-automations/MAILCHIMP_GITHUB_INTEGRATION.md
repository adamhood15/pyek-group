# GitHub → Mailchimp: Draft-Only Email Deployment

This guide describes how to deploy version-controlled HTML emails from a GitHub repository into **Mailchimp draft campaigns**, with optional two-stage test emails (internal first, then the approval group). **The deployment must never schedule or send a live campaign.**

## 1. Architecture

```text
GitHub repository (HTML + campaign metadata)
  ├── Local development: .env (ignored by Git)
  └── GitHub Actions: encrypted Actions secret
          ↓
    Node.js deployment script
          ↓
    Mailchimp Marketing API
          ↓
    Create or update an UNSENT draft → optional test email
```

Use the HTML files as the source of truth. Store each campaign's configuration alongside its HTML. Keep the Mailchimp campaign ID in a durable mapping so subsequent deployments update the same draft instead of creating duplicates.

## 2. Recommended repository layout

```text
pyek-group/
├── .github/
│   └── workflows/
│       └── build-mailchimp-campaign.yml
├── 2026/
│   ├── CBV/
│   │   └── CBV-26-078-Costume-Contest/
│   │       ├── winner/
│   │       │   ├── index.html
│   │       │   └── campaign.json
│   │       └── loser/
│   │           ├── index.html
│   │           └── campaign.json
│   ├── TTA/              # Houston/Austin share the TT audience; interest_ids differ
│   ├── TTH/
│   └── ...
├── workflow-automations/
│   ├── scripts/
│   │   ├── deploy-mailchimp.mjs
│   │   ├── test-recipients.mjs
│   │   └── tt-park-interests.mjs
│   └── email-on-acid/
├── .env                  # local only; NEVER commit
├── .env.example          # safe template; commit
├── .gitignore
└── package.json
```

Folder pattern: `{YEAR}/{BRAND}/{BRAND}-{YY}-{###}-{Campaign-Name}/{variant}/index.html`. Each campaign gets one folder, and each variant (for example `winner` and `loser`) gets a subfolder holding its `index.html` and `campaign.json`. Emails that are currently flat files (for example `2026/CBV/CBV-26-078-Costume-Contest-Results-Winner-Oct26.html`) must be moved into this structure before they can be deployed.

The script and workflow below are an implementation starting point. **Validate Mailchimp segment condition syntax and behavior in your account before production use.** Adjust paths and configuration to match your repository.

## 3. Mailchimp prerequisites


1. Confirm the intended recipient **segment** before any eventual live send. Selecting an audience alone does not implement a “non-season-pass holders” exclusion.

Reference: [Mailchimp Marketing API](https://mailchimp.com/developer/marketing/api/).

## 4. Store credentials safely

Create `.gitignore`:

```gitignore
.env
.env.*
!.env.example
node_modules/
```

Commit `.env.example`:

```dotenv
MAILCHIMP_API_KEY=
MAILCHIMP_AUDIENCE_CBV=
MAILCHIMP_AUDIENCE_TT=
MAILCHIMP_AUDIENCE_DTL=
```

Create your **untracked** `.env` locally with the actual values. Never commit API keys or paste them into campaign metadata. If a key is exposed, revoke/rotate it; deleting the file from the latest commit is not enough because Git history may retain it.

**Already configured in GitHub:** the `MAILCHIMP_API_KEY` Actions secret and the `MAILCHIMP_AUDIENCE_CBV`, `MAILCHIMP_AUDIENCE_TT`, and `MAILCHIMP_AUDIENCE_DTL` Actions variables (Settings → Secrets and variables → Actions). No setup is needed there; confirm the names match exactly. The API key suffix supplies the data center automatically. A GitHub Actions runner will not automatically have your local `.env` file.

GitHub Secrets are encrypted at rest and are made available to authorized workflows at runtime. They are **not** a complete protection against malicious workflows or compromised dependencies: restrict who can edit workflows, protect branches, review third-party actions, and avoid exposing secrets to untrusted pull requests.

## 5. Campaign configuration

Example `2026/CBV/CBV-26-078-Costume-Contest/winner/campaign.json`:

```json
{
  "campaign_key": "CBV-26-078-halloween-winner",
  "campaign_name": "CBV-26-078 Costume Contest Results Winner",
  "subject": "🎃 You Picked the Winner! Here's Your Treat!",
  "preview_text": "Your vote paid off! Enjoy $5 off your 2027 Season Pass. 🍬",
  "from_name": "Cowabunga Vegas",
  "from_email": "donotreply@cowabungavegas.com",
  "to_name": "*|FNAME|* *|LNAME|*",
  "audience": "CBV",
  "mailchimp_campaign_id": null
}
```

Example `2026/CBV/CBV-26-078-Costume-Contest/loser/campaign.json`:

```json
{
  "campaign_key": "CBV-26-078-halloween-loser",
  "campaign_name": "CBV-26-078 Costume Contest Results Loser",
  "subject": "Bunga's Halloween Costume Has Been Decided! 👀",
  "preview_text": "You voted, we counted! See which costume Bunga will be rocking.",
  "from_name": "Cowabunga Vegas",
  "from_email": "donotreply@cowabungavegas.com",
  "to_name": "*|FNAME|* *|LNAME|*",
  "audience": "CBV",
  "mailchimp_campaign_id": null
}
```

**Important:** `to_name` uses Mailchimp merge tags, which require the corresponding audience fields to exist. Verify how your audience handles missing first or last names. `preview_text` maps to the API's `preview_text` campaign setting.

`from_name` and `from_email` must be exactly one of these pairs, and the script rejects anything else: Cowabunga Vegas <donotreply@cowabungavegas.com>, Typhoon Texas Austin <doublet@typhoontexas.com>, Typhoon Texas Houston <doublet@typhoontexas.com>, Daytona Lagoon <lagoonclub@updates.daytonalagoon.com>. Use the pair that matches the brand.

### How campaign IDs are handled

After the **first** deployment, the script prints the newly created Mailchimp campaign ID. Set `mailchimp_campaign_id` in that campaign's JSON file and commit the change **before deploying it again**. Later deployments update the existing draft. This manual registration is intentional: it avoids silent duplicate creation or brittle name-based matching.

For a more advanced implementation, maintain a dedicated campaign-ID registry in a database or protected deployment artifact, with idempotency and concurrency controls. **Do not** assume an ephemeral GitHub Actions runner can retain a local mapping between runs.

### Typhoon Texas: Houston and Austin share one audience

Both parks use `MAILCHIMP_AUDIENCE_TT`. **Do not** create separate Houston and Austin audience variables. Instead, specify the park and the Mailchimp **interest IDs** associated with its group selections. Find the IDs via Mailchimp's interest categories/interests API (or inspect the group's IDs in your Mailchimp setup); do not assume the display name itself is a usable API ID.

All of `campaign_name`, `subject`, `preview_text`, `from_name`, `from_email` and `to_name` are required; the script fails before any API call if one is missing. Example Houston `campaign.json` (other sender/subject fields follow the CBV examples):

```json
{
  "campaign_key": "TTH-example",
  "campaign_name": "Typhoon Texas Houston Example",
  "audience": "TT",
  "park": "houston",
  "subject": "Example subject",
  "preview_text": "Example preview",
  "from_name": "Typhoon Texas Houston",
  "from_email": "doublet@typhoontexas.com",
  "to_name": "*|FNAME|* *|LNAME|*",
  "mailchimp_campaign_id": null
}
```

For Austin, use `"park": "austin"`. TT campaigns take their group targeting from the verified park-to-interest mapping in `workflow-automations/scripts/tt-park-interests.mjs`, not from `campaign.json`. Fill in each park's `category_id` and `interest_id` from Mailchimp (`GET /lists/{TT list id}/interest-categories`, then `.../interest-categories/{category_id}/interests`). Until both are set for a park, deployment fails. If `campaign.json` also lists `interest_ids` or `interest_category_id`, they must match the mapping or deployment fails. The script confirms the interest exists in Mailchimp, then targets it with the `interestcontainsall` operator on the `interests-<category_id>` field. Interest IDs are *not* audience IDs. If a campaign also needs to exclude season passholders or apply other conditions, extend `segment_opts` and verify the full recipient count in Mailchimp; the group restriction alone does not perform that exclusion. **Fail deployment if TT group targeting is missing, invalid, or rejected**—never silently create a full-audience TT campaign.

## 6. Node.js deployment script

Use Node.js 20 or newer. The example uses native `fetch` and `dotenv` for local development.

```bash
npm init -y
npm install dotenv
```

Create `workflow-automations/scripts/deploy-mailchimp.mjs`:

```javascript
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
```

**Security and behavior notes:**

- This script only creates/updates draft campaigns, uploads HTML, and optionally requests test sends. It **does not call** `/actions/send` or `/actions/schedule`.
- Mailchimp API keys may still authorize live sending elsewhere. Removing those calls from this script is a workflow safeguard, **not an API permission boundary**.
- The draft-status check blocks updates to already-sent or scheduled campaigns. Check the API response and test in a nonproduction account or disposable draft before relying on it.
- The sender's email address is set via Mailchimp's `reply_to` setting; Mailchimp's campaign settings model uses `from_name` and `reply_to` for these fields. Verify the displayed From address in the Mailchimp UI before approval.
- Test sends are always two-stage, and the recipient lists live in `workflow-automations/scripts/test-recipients.mjs` (change them only through review). `--test` sends to the **internal** group, the Development team (adamhood3700@gmail.com, adamhood3700@yahoo.com, adam.hood@pyek.com). `--test-approved` sends to the **approval** group, the Brand Team (connor.arndt@pyek.com, connor.pyek@gmail.com, connor.pyek@yahoo.com, natalia.castro@pyek.com) and is only used after the internal round is approved. The two flags cannot be combined.
- Never print the API key, Authorization header, or full environment to logs.

## 7. Run locally

```bash
# Creates a draft, or updates it when campaign ID is registered:
node workflow-automations/scripts/deploy-mailchimp.mjs 2026/CBV/CBV-26-078-Costume-Contest/winner

# Only after reviewing the draft, send the internal test round:
node workflow-automations/scripts/deploy-mailchimp.mjs 2026/CBV/CBV-26-078-Costume-Contest/winner --test

# Only after the internal round is approved, send to the approval group:
node workflow-automations/scripts/deploy-mailchimp.mjs 2026/CBV/CBV-26-078-Costume-Contest/winner --test-approved
```

**After first run:** Record the new ID in `campaign.json` and commit it. Otherwise the next run will create another draft. Repeat for the loser campaign.

## 8. GitHub Actions workflow

Create `.github/workflows/build-mailchimp-campaign.yml`:

```yaml
name: Build Mailchimp Campaign

on:
  workflow_dispatch:
    inputs:
      campaign:
        description: "Campaign to build"
        required: true
        type: choice
        options:
          - 2026/CBV/CBV-26-078-Costume-Contest/winner
          - 2026/CBV/CBV-26-078-Costume-Contest/loser
          - 2026/TTA/TTA-26-072-Costume-Contest/winner
          - 2026/TTA/TTA-26-072-Costume-Contest/loser
          - 2026/TTH/TTH-26-080-Costume-Contest/winner
          - 2026/TTH/TTH-26-080-Costume-Contest/loser
          - 2026/DTL/DTL-26-033-Costume-Contest/winner
          - 2026/DTL/DTL-26-033-Costume-Contest/loser
      test_send:
        description: "Test emails after updating draft: none, internal (first round), or approved (only after internal is approved)"
        required: true
        type: choice
        options:
          - none
          - internal
          - approved
        default: none

permissions:
  contents: read

concurrency:
  group: mailchimp-draft-${{ inputs.campaign }}
  cancel-in-progress: false

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: npm
      - run: npm ci
      - name: Create or update draft
        env:
          MAILCHIMP_API_KEY: ${{ secrets.MAILCHIMP_API_KEY }}
          MAILCHIMP_AUDIENCE_CBV: ${{ vars.MAILCHIMP_AUDIENCE_CBV }}
          MAILCHIMP_AUDIENCE_TT: ${{ vars.MAILCHIMP_AUDIENCE_TT }}
          MAILCHIMP_AUDIENCE_DTL: ${{ vars.MAILCHIMP_AUDIENCE_DTL }}
          CAMPAIGN_DIR: ${{ inputs.campaign }}
          TEST_SEND: ${{ inputs.test_send }}
        run: |
          case "$TEST_SEND" in
            internal) node workflow-automations/scripts/deploy-mailchimp.mjs "$CAMPAIGN_DIR" --test ;;
            approved) node workflow-automations/scripts/deploy-mailchimp.mjs "$CAMPAIGN_DIR" --test-approved ;;
            *) node workflow-automations/scripts/deploy-mailchimp.mjs "$CAMPAIGN_DIR" ;;
          esac
```

Commit `package-lock.json` so `npm ci` works. The workflow reads the repository-level `MAILCHIMP_API_KEY` secret and audience variables, so no GitHub Environment is used.

This workflow is **manual-trigger only** (`workflow_dispatch`) by design. After testing, you may add a push trigger, but only after implementing durable ID registration and branch protections. Otherwise pushes could create duplicate drafts.

## 9. Validation and approval checklist

Before running a deployment:

- [ ] Confirm the intended campaign directory and JSON metadata.
- [ ] Validate HTML links, hosted image URLs, and mobile layout.
- [ ] Confirm the selected audience matches CBV, TT, or DTL; for TT, verify Houston/Austin interest targeting.
- [ ] Confirm the sender name and email, subject, and preview text.
- [ ] Check personalization merge tags and fallbacks.
- [ ] Check that an existing campaign ID points to the correct **unsent draft**.
- [ ] Verify that no live-send or schedule API endpoint appears in the deployment script.

After deploying:

- [ ] Save the campaign ID in Git if newly created.
- [ ] Review the campaign in Mailchimp, including its audience and any required segment/exclusions.
- [ ] Confirm the From address is displayed correctly.
- [ ] Run the internal test round (`--test`) only when explicitly requested.
- [ ] After the internal round is approved, run the approval round (`--test-approved`), again only when explicitly requested.
- [ ] Test rendering and links using Email on Acid if appropriate.
- [ ] Leave the campaign **unscheduled and unsent**. A human handles final scheduling and live sending in Mailchimp.

## 10. Suggested enhancements

1. **HTML validation:** Add an HTML linter and link checker before uploading.
2. **Asset publishing:** Publish email images to a stable HTTPS asset host before deployment.
3. **Safer idempotency:** Use a durable, protected campaign-ID registry and fail if a key resolves to multiple campaigns.
4. **Audience segmentation:** Store an explicit Mailchimp segment or saved-segment ID in campaign metadata where applicable; validate before any eventual live send.
5. **Deployment environments:** Separate test and production Mailchimp accounts/audiences where possible.
6. **Auditability:** Record the Git commit SHA and Mailchimp campaign ID in the deployment log, without logging secrets.
7. **Optional tests:** Test emails are gated behind the explicit `test_send` workflow input (none, internal, approved) rather than sending on every code push.

## 11. API references

- [Mailchimp Marketing API overview](https://mailchimp.com/developer/marketing/api/)
- [Campaigns API](https://mailchimp.com/developer/marketing/api/campaigns/)
- [Campaign content API](https://mailchimp.com/developer/marketing/api/campaign-content/)
- [GitHub Actions secrets](https://docs.github.com/en/actions/security-for-github-actions/security-guides/using-secrets-in-github-actions)
- [GitHub Actions workflow_dispatch](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow)

> **Operational rule:** The repository-to-Mailchimp integration is **draft-only**. The person responsible for the campaign must review and initiate any eventual live send separately in Mailchimp.
