# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

Email marketing assets and website components for PYEK Group's water park brands. The primary workflow is updating existing Mailchimp HTML email files using the prompt and JSON system described below.

---

## Email Update Workflow

The core working pattern is defined in `.claude/claude-email-prompt.md`. When updating emails:

1. **Reference file** — an existing HTML email (`{BRAND}-{YY}-{NUM}-{Name}.html`) used as the structural template
2. **`.claude/email.json`** — source of truth for all content replacements (images, copy, buttons, UTM params, new filename)
3. **Output** — fully updated production-ready HTML placed in the correct year/brand folder

**Critical rule:** Update only what the JSON specifies. Do not redesign, restructure, or refactor. The existing HTML is already production-ready.

---

# Email Image Export

When I upload an email PSD and ask for images, slice and export them as follows.

## 1. Identify the images
- **Hero:** the area above the fold, from the top of the artboard down.
- **Body images:** any photos, graphics, or illustrated sections inside the email body.
- Leave out anything that will be live text or coded in HTML (copy, buttons, footer, solid color blocks) unless I say otherwise.

## 2. Slice
- A hero or body section can be split into **multiple slices** when that makes sense: at natural breaks (for example a logo strip above a photo), to keep files under the size limit, or where coded content will sit between parts.
- Slices must stack seamlessly with no gaps or overlaps.
- If a section transitions into the body with a shaped divider (a wave, curve, angled edge, banner, torn edge, etc.), the slice ends at the **lowest point of that divider**. Crop flush to it: the final pixel row of the slice must contain the lowest visible pixel of the divider, with no empty transparent rows below it. Verify by measuring the lowest row where alpha is above 0 and confirming it is the last row of the exported file.

## 3. Handle dividers and transparency
- Hide the body color layer and any body content overlapping the slice.
- Make everything **below or outside the divider's edge transparent**, meaning any area where the body color would normally show. Keep the divider itself and the image.
- Never export a background image showing through where the body color belongs. We set that color in code.

## 4. Export
- Export as PNG with transparency preserved.
- **Maximum width is 600 px.** Scale wider images down proportionally, and resize before compressing.
- Name the files in top-to-bottom order:
  - Hero slices: `1-hero.png`, `2-hero.png`, and so on
  - Body images: `1-image.png`, `2-image.png`, and so on
- If the PSD has multiple emails (artboards), put each email's files in a folder named after its artboard.

## 5. Compress
- Each file must be **under 250 KB**.
- Use pngquant with **no dithering** (`--nofs`). Lower the color count step by step (256 → 128 → 112 → 96) until the file fits, then run oxipng for lossless savings.
- Use the highest color count that fits. Check skies and gradients for banding. If a file still won't fit cleanly, split it into more slices instead of degrading quality.

## 6. Deliver
- Zip everything into one downloadable file.
- Give a short summary listing each file with its dimensions, file size, and color count.
- Flag any issues, such as identical images across artboards, an unclear divider or slice point, or layers that didn't render correctly.

## Email Creation Cadence

Follow these steps, in order, for every new email. Do not skip ahead or run a later step before the user approves the previous one.

1. **Build** the email in HTML.
2. **Test in Email on Acid** (`./eoa {name-fragment}`) and visually review the screenshots (Gmail, Yahoo, Apple, Outlook, light and dark mode). Verify there are no issues.
3. **Retest if needed**, up to 3 times per email. If issues remain after 3 tests, stop and flag the user to investigate.
4. **After the user approves the Email on Acid tests**, create the Mailchimp campaign in draft mode and send a test to the **Development team** (`--test`: the adamhood email addresses).
5. **After the user approves the Development team test**, send a test to the **Brand Team** (`--test-approved`: Connor and Natalia).
6. **After the Brand Team approves**, the user schedules the campaign in Mailchimp. Never schedule or send a live campaign.

---

## Email on Acid Testing

Before an email is deployed, run `./eoa {name-fragment}` from the repo root (e.g. `./eoa 078` tests every email whose filename contains "078"; a full file path also works). Other commands: `./eoa auth` (check credentials), `./eoa clients` (list client IDs). Extra flags pass through: `--image-blocking`, `--keep`, `--clients gmail,outlook` (groups or IDs), `--sandbox`. By default each run covers all Gmail, Apple, Outlook and Yahoo clients. The wrapper calls `workflow-automations/email-on-acid/eoa.mjs`; credentials live in the git-ignored `workflow-automations/email-on-acid/.env`. Results (report.md plus per-client PNGs) land in `.eoa-results/{email-name}/`; read those to review rendering. Tests are auto-named "{Campaign} - {BRAND} - {Variant}" from the filename (override with `--name`). Run at most 3 tests per email before flagging the user for review; batch fixes between runs. Once the user approves an email's Email on Acid screenshots, delete that email's results folder by its exact name with `rm -rf .eoa-results/{email-name}` (e.g. `rm -rf .eoa-results/Bay-Closure-Announcement-CBV`). Never use a wildcard such as `.eoa-results/*/`; Claude Code's safety check blocks it. Do not leave approved screenshots in the repo. See `workflow-automations/email-on-acid/README.md`.

---

## Mailchimp Draft Deployment

Emails are deployed to Mailchimp as unsent drafts with `node workflow-automations/scripts/deploy-mailchimp.mjs {campaign-dir}` (or the "Build Mailchimp Campaign" GitHub workflow). Campaign folders follow `{YEAR}/{BRAND}/{BRAND}-{YY}-{###}-{Campaign}/{variant}/` with `index.html` and `campaign.json`. Never schedule or send a live campaign. The sender is always one of these `from_name` / `from_email` pairs (the script enforces this): Cowabunga Vegas / donotreply@cowabungavegas.com, Typhoon Texas Austin / doublet@typhoontexas.com, Typhoon Texas Houston / doublet@typhoontexas.com, Daytona Lagoon / lagoonclub@updates.daytonalagoon.com. Test sends are always two-stage and only when the user explicitly asks: `--test` goes to the internal group, the Development team (adamhood3700@gmail.com, adamhood3700@yahoo.com, adam.hood@pyek.com); `--test-approved` goes to the approval group, the Brand Team (connor.arndt@pyek.com, connor.pyek@gmail.com, connor.pyek@yahoo.com, natalia.castro@pyek.com) and only after the internal round is approved. Recipient lists live in `workflow-automations/scripts/test-recipients.mjs`. Typhoon Texas campaigns (audience `TT`, shared by Houston and Austin) set `park` in `campaign.json`; the group targeting comes from the verified park mapping in `workflow-automations/scripts/tt-park-interests.mjs`, which must be filled in (category and interest IDs from Mailchimp) before any TT deployment will run. Email on Acid tests are named from each `campaign.json` `test_name`. See `workflow-automations/MAILCHIMP_GITHUB_INTEGRATION.md`.

---

## File Organization

```
{YEAR}/
  {BRAND}/
    {BRAND}-{YY}-{###}-{Campaign-Name}-{Mon}{YY}.html
```

**Active brands:**
| Code | Property |
|------|----------|
| CBV | Cowabunga Bay & Canyon (Las Vegas) |
| TTA | Typhoon Texas Austin |
| TTH | Typhoon Texas Houston |
| CBC | Cowabunga Bay Canyon (separate) |
| CBB | Cowabunga Bay Bay (separate) |
| SWI | Swimply (or similar) |

**S3 image buckets:**
- TTA/TTH: `typhoon-texas.s3.us-east-1.amazonaws.com/austin/` or `/houston/`
- CBV: `cowabunga-vegas.s3.us-east-1.amazonaws.com/bay-and-canyon/`
- Per-campaign image folders: `{bucket path}/2026/{BRAND}-{YY}-{###}-Offseason-Emails/{Theme}/{Email-Folder}/`. Examples: CBV winner `.../CBV-26-078-Offseason-Emails/Halloween/Costume-Contest-Winner/` and loser `.../Costume-Contest-Results/`; TTA winner `austin/2026/TTA-26-072-Offseason-Emails/Halloween/Costume-Contest-Results-Winner/` and loser `.../Costume-Contest-Results/`. Images are named `1-hero`, `2-hero`, `3-text` (plus `bat.png` where an inline emoji image is needed). Always confirm the folder name with the user and check the image URLs return 200 before testing.
- Live links: CBV `https://cowabungavegas.com/canyon/buy-tickets/`; TTA `https://typhoontexas.com/austin/buy-tickets/` (append `?promo=CODE` for promo emails).

---

## email.json Structure

Each `Email_N` object contains:
- `email_name` — campaign slug
- `email_folder` — destination path (e.g. `/2026/TTH/`)
- `html_file_name` — output filename
- `reference_file` — source HTML to copy structure from
- `html_title_tag` — `<title>` content
- `images` — `image_1..N` with `src` and `alt`
- `button` or `button_1`/`button_2` — `text`, `url`, `utm_campaign`, optional color overrides
- `copy` — `paragraph_1`, `paragraph_2`, etc.

CBV emails use dual buttons (`button_1` / `button_2`); TTA/TTH use a single `button`.

---

## HTML Email Rules

- Table-based layout — do not convert to div/flex/grid
- All CSS must be inline — do not add external stylesheets
- No JavaScript
- VML fallback buttons for Outlook must be preserved
- Always capitalize the word "Summer" in all email copy, subject lines, preview text, and alt text (e.g. "best Summer ever", "Get the most out of Summer")
- Always capitalize "Season Pass" (both words) in all email copy, subject lines, preview text, and alt text (e.g. "Lock in a Season Pass today")
- Live text copy: do not force line breaks (`<br>`) unless necessary. Text does not need to wrap on the same lines as the reference images; copy must reflow responsively across screen sizes
- Mailchimp merge tags (`*|MC_PREVIEW_TEXT|*`, `*|EMAIL|*`, etc.) must be preserved
- UTM params: always append `utm_source=mailchimp&utm_medium=email&utm_campaign={value}`; use `?` if no existing params, `&` if params already present. Per Google Analytics 4 conventions, `utm_source` is the platform sending the traffic (`mailchimp`) and `utm_medium` is the channel (`email`, which GA4 groups into its Email channel); never use `email` as the source. Keep all UTM values lowercase except the `utm_campaign` job-code name, which stays consistent across every link in the email

---

## Website-Components/

Standalone components for WordPress/Oxygen/ACF sites (countdown timers, events carousel, promotions carousel, Daytona calendar, etc.). Each component lives in its own subfolder. See project memory for carousel system specifics on typhoontexas.com.

---

## What to Ignore

`email-generator/` and `email-generator-modular/` are sandbox/testing projects and not part of active production work.
