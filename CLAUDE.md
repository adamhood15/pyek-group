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
- Mailchimp merge tags (`*|MC_PREVIEW_TEXT|*`, `*|EMAIL|*`, etc.) must be preserved
- UTM params: always append `utm_source=email&utm_medium=email&utm_campaign={value}`; use `?` if no existing params, `&` if params already present

---

## Website-Components/

Standalone components for WordPress/Oxygen/ACF sites (countdown timers, events carousel, promotions carousel, Daytona calendar, etc.). Each component lives in its own subfolder. See project memory for carousel system specifics on typhoontexas.com.

---

## What to Ignore

`email-generator/` and `email-generator-modular/` are sandbox/testing projects and not part of active production work.
