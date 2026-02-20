# Multi-Brand Email System

## Overview

The email generator now supports three brands:
- **CBV** - Cowabunga Bay & Canyon (Vegas)
- **TTA** - Typhoon Texas Austin
- **TTH** - Typhoon Texas Houston

## Campaign Folder Structure

Campaigns are organized by brand and year:
```
campaigns/
├── CBV/
│   └── 2026/
│       ├── cbv-example.json
│       ├── test-last-chance.json
│       └── valentines-sale.json
├── TTA/
│   └── 2026/
│       └── tta-example.json
└── TTH/
    └── 2026/
        └── tth-example.json
```

## Brand Configuration

Brand settings are in [brand-config.js](brand-config.js) and include:
- Logo URLs
- Footer addresses
- CTA button colors
- CTA type (single or dual)

## Usage

### 1. Add Brand to Campaign JSON

Add a `brand` field at the top level:

```json
{
  "brand": "cbv",  // or "tta" or "tth"
  "meta": { ... },
  "theme": { ... }
}
```

### 2. CTA Sections by Brand

**CBV (Dual CTA):**
```json
"ctaSection": {
  "enabled": true,
  "showBay": true,
  "showCanyon": true,
  "padding": "25px 0 0 0",
  "bayUrl": "https://cowabungavegas.com/bay/",
  "bayText": "GRAB TICKETS",
  "canyonUrl": "https://cowabungavegas.com/canyon/",
  "canyonText": "GRAB TICKETS"
}
```

**TTA/TTH (Single CTA):**
```json
"ctaSection": {
  "enabled": true,
  "ctaUrl": "https://typhoontexas.com/austin/",
  "ctaText": "GET SEASON PASS",
  "ctaColor": "#ffe00c"
}
```

### 3. Build Command

Use the brand folder path:
```bash
node build-email.js CBV/2026/cbv-example.json
node build-email.js TTA/2026/tta-example.json
node build-email.js TTH/2026/tth-example.json
```

## Brand Differences

| Feature | CBV | TTA | TTH |
|---------|-----|-----|-----|
| **CTA Type** | Dual (Bay + Canyon) | Single | Single |
| **Primary Color** | Pink (#ff1469) | Yellow (#ffe00c) | Yellow (#ffe00c) |
| **Secondary Color** | Green (#04868b) | Pink (#ff1469) | Pink (#ff1469) |
| **Logo** | Cowabunga | Typhoon Texas | Typhoon Texas |
| **Address** | Henderson, NV | Pflugerville, TX | Katy, TX |

## Examples

See campaign examples:
- `campaigns/CBV/2026/cbv-example.json`
- `campaigns/TTA/2026/tta-example.json`
- `campaigns/TTH/2026/tth-example.json`

## ✅ System Complete

All multi-brand features are fully implemented:

✅ Brand configuration system
✅ Build script brand merging
✅ Example campaigns for all brands
✅ Brand validation
✅ Single CTA button rendering (TTA/TTH)
✅ Dual CTA button rendering (CBV)
✅ Brand-specific footer content
✅ Dynamic CTA button colors
✅ Organized folder structure by brand and year

## Creating New Campaigns

1. Navigate to the appropriate brand/year folder: `campaigns/[BRAND]/2026/`
2. Create a new JSON file with your campaign name (e.g., `spring-sale.json`)
3. Copy structure from an existing example for that brand
4. Update content, images, URLs, and UTM parameters
5. Build: `node build-email.js [BRAND]/2026/your-campaign.json`

The template automatically applies the correct branding based on the `brand` field in your JSON!
