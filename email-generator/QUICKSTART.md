# Quick Start Guide

## Installation

```bash
cd email-generator
npm install
```

## Build Your First Email

Choose your brand and build:

```bash
# Build TTA email
node build-email.js tta-email.json

# Build TTH email
node build-email.js tth-email.json

# Build CBV email
node build-email.js cbv-email.json

# Or build all at once
./build-all.sh
```

Your HTML files will be in `/dist/BRAND/2026/` (e.g., `/dist/TTA/2026/tta-email.html`)

## Create a New Campaign

The system uses 3 reusable JSON templates - one per brand:

1. Open the JSON file for your brand:
   - `cbv-email.json` - Cowabunga Bay & Canyon
   - `tta-email.json` - Typhoon Texas Austin
   - `tth-email.json` - Typhoon Texas Houston

2. Update the content (hero images, text, CTAs, etc.)

3. Build it:
   ```bash
   node build-email.js tta-email.json
   ```

4. The generated HTML replaces the previous version in `dist/TTA/2026/`

## Essential JSON Fields

```json
{
  "brand": "tta",
  "meta": {
    "title": "Your Email Title",
    "preheader": "Preview text"
  },
  "theme": {
    "backgroundColor": "#0285c5",
    "textColor": "#ffffff"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=Campaign-Name"
  },
  "heroImages": [
    {
      "src": "https://your-cdn.com/image.png",
      "alt": "Description",
      "link": "https://destination-url.com"
    }
  ],
  "bodyParagraphs": [
    {
      "content": "Your paragraph text with <strong>bold</strong>",
      "fontSize": "24px",
      "lineHeight": "34px",
      "padding": "35px 50px 0 50px",
      "textColor": "#ffffff"
    }
  ],
  "contentImage": {
    "enabled": true,
    "src": "https://your-cdn.com/cta-image.png",
    "alt": "Call to action",
    "link": "https://destination-url.com",
    "padding": "0"
  },
  "ctaSection": {
    "enabled": true,
    "ctaUrl": "https://typhoontexas.com/",
    "ctaText": "LEARN MORE",
    "ctaColor": "#ffe00c",
    "ctaPadding": "16px 30px"
  }
}
```

## Common Scenarios

### Image-Only Email (No Text)
Set `bodyParagraphs: []`, `ctaSection.enabled: false`, `closingModule.enabled: false`

### Email with CTA Button
Set `ctaSection.enabled: true` and configure button properties

### Email with Image CTA (No Button)
Set `ctaSection.enabled: false` and use `contentImage` with a link

### CBV Dual Buttons
Set `ctaSection.showBay: true` and `ctaSection.showCanyon: true`

### Text Closing vs Image Closing
Set `closingModule.isImage: false` for text or `true` for image

## Build Commands

```bash
# Build all brands at once
./build-all.sh

# Build individual brands
node build-email.js cbv-email.json
node build-email.js tta-email.json
node build-email.js tth-email.json
```

## Helpful Commands

```bash
# Make build-all.sh executable (one-time setup)
chmod +x build-all.sh

# View generated HTML in browser
open dist/TTA/2026/tta-email.html
open dist/TTH/2026/tth-email.html
open dist/CBV/2026/cbv-email.html

# View campaign templates
cat cbv-email.json
cat tta-email.json
cat tth-email.json
```

## Upload to ESP

1. Open the generated HTML file in `/dist`
2. Copy entire contents
3. Paste into your ESP's HTML editor
4. Send test email
5. Deploy campaign

## Getting Help

- Full documentation: [README.md](README.md)
- Example campaigns: `/campaigns` directory
- Template file: `/templates/promo-template.hbs`
