# Quick Start - Modular Email Generator

## Installation

```bash
cd email-generator-modular
npm install
```

## Build Your First Email

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

## JSON Structure

```json
{
  "brand": "tta",
  "meta": {
    "title": "Email Title",
    "preheader": "Preview text"
  },
  "theme": {
    "backgroundColor": "#0285c5",
    "textColor": "#ffffff"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=Name"
  },
  "sections": [
    { "type": "image", "src": "...", "alt": "..." },
    { "type": "heading", "content": "..." },
    { "type": "single-column", "content": "..." },
    { "type": "cta-button", "text": "...", "url": "..." }
  ]
}
```

## Available Section Types

| Type | Purpose |
|------|---------|
| `heading` | Large text heading |
| `image` | Full-width image with optional link |
| `single-column` | Text paragraph |
| `two-column` | Side-by-side content |
| `three-column` | Three-column features/icons |
| `cta-button` | Single call-to-action button |
| `cta-dual` | Dual buttons (CBV only) |
| `spacer` | Vertical spacing |
| `divider` | Horizontal line |

## Component Examples

### Image

```json
{
  "type": "image",
  "src": "https://cdn.com/hero.png",
  "alt": "Hero image",
  "link": "https://destination.com",
  "padding": "0"
}
```

### Heading

```json
{
  "type": "heading",
  "content": "Your Heading Text",
  "fontSize": "42px",
  "lineHeight": "48px",
  "color": "#ffffff",
  "padding": "30px 50px 0 50px"
}
```

### Single Column Text

```json
{
  "type": "single-column",
  "content": "Your paragraph with <strong>bold</strong> text",
  "fontSize": "24px",
  "lineHeight": "34px",
  "color": "#ffffff",
  "padding": "20px 50px"
}
```

### Two Column

```json
{
  "type": "two-column",
  "padding": "25px 25px",
  "columns": [
    {
      "image": "https://cdn.com/img1.png",
      "alt": "Image 1",
      "title": "Feature 1",
      "description": "Description text",
      "ctaText": "Learn More",
      "ctaLink": "https://destination.com"
    },
    {
      "image": "https://cdn.com/img2.png",
      "alt": "Image 2",
      "title": "Feature 2",
      "description": "Description text",
      "ctaText": "Learn More",
      "ctaLink": "https://destination.com"
    }
  ]
}
```

### Three Column

```json
{
  "type": "three-column",
  "padding": "25px 25px",
  "columns": [
    {
      "icon": "https://cdn.com/icon1.png",
      "alt": "Icon 1",
      "title": "Feature 1",
      "text": "Description"
    },
    {
      "icon": "https://cdn.com/icon2.png",
      "alt": "Icon 2",
      "title": "Feature 2",
      "text": "Description"
    },
    {
      "icon": "https://cdn.com/icon3.png",
      "alt": "Icon 3",
      "title": "Feature 3",
      "text": "Description"
    }
  ]
}
```

### CTA Button

```json
{
  "type": "cta-button",
  "text": "GET TICKETS",
  "url": "https://typhoontexas.com/tickets/",
  "color": "#ffe00c",
  "textColor": "#003955",
  "fontSize": "22px",
  "buttonPadding": "16px 40px",
  "padding": "25px 0 50px 0"
}
```

### Spacer

```json
{
  "type": "spacer",
  "height": "30px"
}
```

### Divider

```json
{
  "type": "divider",
  "color": "#ffe00c",
  "thickness": "2px",
  "padding": "30px 50px"
}
```

## Common Workflows

### Create Image-Only Email

```json
"sections": [
  { "type": "image", "src": "https://cdn.com/promo.png", "alt": "Promotion" }
]
```

### Create Email with CTA

```json
"sections": [
  { "type": "image", "src": "...", "alt": "..." },
  { "type": "heading", "content": "Summer Sale!" },
  { "type": "single-column", "content": "Save 50% on season passes!" },
  { "type": "cta-button", "text": "SHOP NOW", "url": "..." }
]
```

### Reorder Sections

Simply rearrange objects in the `sections` array - the order in JSON = the order in email!

### Add Spacing

Insert spacer sections between components:

```json
"sections": [
  { "type": "image", ... },
  { "type": "spacer", "height": "30px" },
  { "type": "heading", ... }
]
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
cat campaigns/tta-email.json
```

## Upload to ESP

1. Build your email
2. Open the generated HTML file in `/dist/BRAND/2026/`
3. Copy entire contents
4. Paste into your ESP's HTML editor
5. Send test email
6. Deploy campaign

## Getting Help

- Full documentation: [README.md](README.md)
- Component reference: See README.md "Available Components" section
- Examples: Check the JSON files in `/campaigns` directory
