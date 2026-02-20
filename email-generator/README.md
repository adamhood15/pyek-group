# Email Generator

Multi-brand promotional email generation system for table-based HTML marketing emails.

## Quick Start

```bash
# Install dependencies
npm install

# Build an email
node build-email.js cbv-email.json
node build-email.js tta-email.json
node build-email.js tth-email.json

# Build all emails at once
./build-all.sh

# Output will be in /dist/{BRAND}/2026/ folder
```

## Project Structure

```
email-generator/
├── build-email.js          # CLI build script
├── brand-config.js         # Brand-specific configurations
├── package.json            # Dependencies
├── templates/
│   └── promo-template.hbs  # Handlebars template
├── campaigns/
│   ├── cbv-email.json      # Cowabunga Bay & Canyon (reusable)
│   ├── tta-email.json      # Typhoon Texas Austin (reusable)
│   └── tth-email.json      # Typhoon Texas Houston (reusable)
└── dist/                   # Generated HTML files (output)
    ├── CBV/2026/
    ├── TTA/2026/
    └── TTH/2026/
```

## Multi-Brand System

The system supports three brands with automatic configuration:

- **CBV** - Cowabunga Bay & Canyon (dual CTA buttons)
- **TTA** - Typhoon Texas Austin (single CTA button)
- **TTH** - Typhoon Texas Houston (single CTA button)

Each brand has its own:
- Footer logo and contact info
- CTA button configuration
- Brand colors and styling

## Dependencies

- **handlebars** (^4.7.8) - Template compilation
- **juice** (^10.0.0) - CSS inlining for email clients
- **html-minifier** (^4.0.0) - HTML optimization

## Campaign JSON Structure

### Required Fields

```json
{
  "brand": "tta",
  "meta": {
    "title": "Email Title",
    "preheader": "Preview text (optional)"
  },
  "theme": {
    "backgroundColor": "#ffffff",
    "textColor": "#003955"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=Campaign-Name"
  },
  "heroImages": [
    {
      "src": "https://example.com/image.png",
      "alt": "Image description",
      "link": "https://destination-url.com"
    }
  ]
}
```

### Optional Fields

**Note on Hero Images:** The `link` property is optional. If provided, the image will be wrapped in an anchor tag. If omitted, the image displays without a link.

#### Heading Section
```json
"heading": {
  "enabled": true,
  "content": "Your Heading Text",
  "fontSize": "48px",
  "lineHeight": "52px",
  "fontWeight": "700",
  "textAlign": "center",
  "padding": "25px 50px 0 50px",
  "color": "#003955"
}
```

#### Body Paragraphs
```json
"bodyParagraphs": [
  {
    "content": "Text with <strong>bold</strong> and <span style=\"color:#ff1469\">colored text</span>",
    "fontSize": "24px",
    "lineHeight": "34px",
    "padding": "35px 50px 0 50px",
    "textColor": "#ffffff"
  },
  {
    "content": "Second paragraph text",
    "fontSize": "24px",
    "lineHeight": "34px",
    "padding": "0 50px 50px 50px",
    "textColor": "#003955"
  }
]
```

**Properties:**
- `content` - Paragraph text (supports HTML: `<strong>`, `<span>`, etc.)
- `fontSize` - Text size (e.g., "24px")
- `lineHeight` - Line height (e.g., "34px")
- `padding` - CSS padding (e.g., "35px 50px 0 50px")
- `textColor` (optional) - Text color (e.g., "#ffffff"). Can also use `color` instead.

#### Content Image
Displays a full-width image between body paragraphs and CTA section. Useful for image-based CTAs or additional graphics.

```json
"contentImage": {
  "enabled": true,
  "src": "https://example.com/cta-image.png",
  "alt": "Save our Contact",
  "link": "https://typhoontexas.com/",
  "padding": "0 0 0 0"
}
```

**Properties:**
- `enabled` - Show or hide this section
- `src` - Image URL
- `alt` - Alt text for accessibility
- `link` (optional) - Wraps image in anchor tag if provided
- `padding` - CSS padding around the image

#### CTA Section - Single Button (TTA/TTH)
```json
"ctaSection": {
  "enabled": true,
  "ctaUrl": "https://typhoontexas.com/events/",
  "ctaText": "LEARN MORE",
  "ctaColor": "#ffe00c",
  "ctaTextColor": "#003955",
  "ctaBorderColor": "#ffe00c",
  "ctaFontSize": "30px",
  "ctaFontWeight": "900",
  "ctaWidth": "425px",
  "ctaHeight": "75px",
  "ctaPadding": "16px 30px",
  "padding": "0 0 50px 0"
}
```

**CTA Properties:**
- `enabled` - Show or hide the CTA button
- `ctaUrl` - Button destination URL
- `ctaText` - Button text
- `ctaColor` - Button background color (default: brand primary color)
- `ctaTextColor` (optional) - Button text color (default: "#ffffff")
- `ctaBorderColor` (optional) - Button border color (default: matches ctaColor)
- `ctaFontSize` (optional) - Button text size (default: from template)
- `ctaFontWeight` (optional) - Button text weight (default: from template)
- `ctaWidth` (optional) - Outlook VML button width (default: "240px")
- `ctaHeight` (optional) - Outlook VML button height (default: "44px")
- `ctaPadding` (optional) - Button padding (default: "10px 20px")
- `padding` - Spacing around the button container

**Dynamic Hover Effect:** The hover style automatically uses the `ctaColor` and `ctaTextColor` values, inverting the colors on hover.

#### CTA Section - Dual Buttons (CBV Only)
```json
"ctaSection": {
  "enabled": true,
  "showBay": true,
  "showCanyon": true,
  "padding": "25px 0 0 0",
  "bayUrl": "https://cowabungavegas.com/bay/",
  "bayText": "LEARN MORE",
  "canyonUrl": "https://cowabungavegas.com/canyon/",
  "canyonText": "LEARN MORE"
}
```

#### Closing Module - Image
```json
"closingModule": {
  "enabled": true,
  "isImage": true,
  "imageSrc": "https://example.com/closing.png",
  "imageAlt": "Closing message",
  "padding": "50px"
}
```

#### Closing Module - Text
```json
"closingModule": {
  "enabled": true,
  "isImage": false,
  "padding": "50px",
  "fontSize": "100px",
  "lineHeight": "100px",
  "fontWeight": "700",
  "textContent": "<p style='font: 700 100px/100px Arial Black, sans-serif; text-align: center; padding: 50px 0 0 0; color: #003955; margin: 0;'>THANK YOU</p>"
}
```

## Usage Examples

### TTA Heroes Weekend Email
```json
{
  "brand": "tta",
  "meta": {
    "title": "TTA Heroes Weekend",
    "preheader": ""
  },
  "theme": {
    "backgroundColor": "#ffffff",
    "textColor": "#003955"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=PYEK-TTA-25-041-Heroes-Weekend"
  },
  "heroImages": [
    {
      "src": "https://typhoon-texas.s3.us-east-1.amazonaws.com/austin/2025/heroes-weekend.png",
      "alt": "Heroes Weekend - Get in Free!"
    }
  ],
  "heading": {
    "enabled": false
  },
  "bodyParagraphs": [
    {
      "content": "Join us for Heroes Weekend, a celebration <strong>honoring those who serve</strong>.",
      "fontSize": "24px",
      "lineHeight": "34px",
      "padding": "35px 50px 0 50px"
    }
  ],
  "ctaSection": {
    "enabled": true,
    "ctaUrl": "https://typhoontexas.com/events/heroes-weekend",
    "ctaText": "LEARN MORE!",
    "ctaColor": "#ffe00c",
    "ctaPadding": "16px 30px",
    "padding": "0 0 50px 0"
  },
  "closingModule": {
    "enabled": true,
    "isImage": false,
    "padding": "50px",
    "fontSize": "100px",
    "lineHeight": "100px",
    "fontWeight": "700",
    "textContent": "<p style='font: 700 100px/100px Arial Black, sans-serif; text-align: center; color: #003955;'>THANK YOU HEROES!</p>"
  }
}
```

### CBV Dual-Park Email
```json
{
  "brand": "cbv",
  "meta": {
    "title": "CBV Summer Sale",
    "preheader": "Save big on season passes!"
  },
  "theme": {
    "backgroundColor": "#ffffff",
    "textColor": "#000000"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=CBV-Summer-Sale"
  },
  "heroImages": [
    {
      "src": "https://cowabunga-vegas.s3.us-east-1.amazonaws.com/summer-sale.png",
      "alt": "Summer Sale - Both Parks"
    }
  ],
  "heading": {
    "enabled": false
  },
  "bodyParagraphs": [
    {
      "content": "Save on season passes at both parks!",
      "fontSize": "22px",
      "lineHeight": "31px",
      "padding": "0 25px 25px 25px"
    }
  ],
  "ctaSection": {
    "enabled": true,
    "showBay": true,
    "showCanyon": true,
    "padding": "25px 0 0 0",
    "bayUrl": "https://cowabungavegas.com/bay/tickets",
    "bayText": "BAY TICKETS",
    "canyonUrl": "https://cowabungavegas.com/canyon/tickets",
    "canyonText": "CANYON TICKETS"
  },
  "closingModule": {
    "enabled": false
  }
}
```

## Build Features

The build script automatically:
1. ✓ Validates campaign JSON structure
2. ✓ Applies brand-specific configuration
3. ✓ Compiles Handlebars template
4. ✓ Generates dynamic CTA hover styles
5. ✓ Inlines CSS for email client compatibility
6. ✓ Minifies HTML for production
7. ✓ Outputs to brand-specific folders
8. ✓ Generates build stats report

## Output Structure

Generated files are organized by brand and year:
```
dist/
├── CBV/2026/cbv-email.html
├── TTA/2026/tta-email.html
└── TTH/2026/tth-email.html
```

Generated HTML includes:
- Table-based layout for email client compatibility
- Inline styles for maximum deliverability
- Outlook VML buttons for compatibility
- Dark mode support
- Brand-specific footers
- MailChimp merge tags

## CTA Customization

### Dynamic Colors
Change the button colors in your JSON:
```json
"ctaColor": "#ff1469",         // Pink background
"ctaTextColor": "#ffffff",     // White text
"ctaBorderColor": "#ff1469"    // Pink border
```

The hover effect automatically inverts:
- **Default:** Colored background, white/colored text
- **Hover:** Inverted colors for visual feedback

### Dynamic Sizing
Control button size with multiple properties:
```json
"ctaPadding": "16px 30px",     // Button padding (affects clickable area)
"ctaFontSize": "30px",         // Text size
"ctaFontWeight": "900",        // Text weight (bold)
"ctaWidth": "425px",           // Outlook VML width
"ctaHeight": "75px"            // Outlook VML height
```

**Examples:**
```json
// Large prominent button
"ctaPadding": "20px 40px",
"ctaFontSize": "32px",
"ctaFontWeight": "900"

// Compact button
"ctaPadding": "10px 20px",
"ctaFontSize": "18px",
"ctaFontWeight": "700"
```

## Brand Configuration

Each brand is configured in `brand-config.js`:

```javascript
{
  cbv: {
    name: 'Cowabunga Bay & Canyon',
    shortName: 'CBV',
    cta: {
      type: 'dual',  // Shows Bay + Canyon buttons
      bayColor: '#ff1469',
      canyonColor: '#04868b'
    },
    footer: {
      address: '7055 S Fort Apache Rd.',
      city: 'Las Vegas, NV 89148',
      companyName: 'Cowabunga Vegas'
    },
    logo: {
      src: 'https://cowabunga-logo.png',
      alt: 'Cowabunga Waterpark',
      width: '96'
    }
  }
}
```

## Workflow

### Creating a New Email

1. **Update JSON template** for your brand (cbv/tta/tth-email.json)
2. **Change content:**
   - Hero image URLs, alt text, and links (optional)
   - Body paragraphs with text color control
   - Content image (optional)
   - CTA URL, text, colors, and sizing
   - UTM parameters
   - Closing module
3. **Build:** `node build-email.js tta-email.json` or `./build-all.sh`
4. **Output:** Check `dist/TTA/2026/tta-email.html`
5. **Upload to ESP**

### Reusable Templates

The system uses 3 reusable JSON files that you modify for each campaign:
- `cbv-email.json` - Update this for all CBV emails
- `tta-email.json` - Update this for all TTA emails
- `tth-email.json` - Update this for all TTH emails

This approach keeps your campaign structure simple and consistent.

## MailChimp Merge Tags

The template includes:
- `*|MC_PREVIEW_TEXT|*` - Preheader text
- `*|EMAIL|*` - Recipient email
- `*|FORWARD|*` - Forward to friend link
- `*|ARCHIVE|*` - View online link
- `*|UPDATE_PROFILE|*` - Update preferences link
- `*|UNSUB|*` - Unsubscribe link

## Validation

The build script validates:
- Brand field (must be cbv, tta, or tth)
- Required meta fields (title)
- Theme configuration
- Hero images array (at least 1 required)
- Image properties (src and alt)

Missing or invalid fields will cause the build to fail with helpful error messages.

## Troubleshooting

### Build fails with "Campaign file not found"
Include the `.json` extension: `node build-email.js tta-email.json`

### CTA color not changing
Make sure you:
1. Updated the `ctaColor` in the JSON
2. Saved the JSON file
3. Re-ran the build script
4. Hard-refreshed your browser (Ctrl+Shift+R)

### Body paragraphs not appearing
Body paragraphs must use the object format:
```json
{
  "content": "Your text here",
  "fontSize": "24px",
  "lineHeight": "34px",
  "padding": "25px 50px"
}
```

### Hover effect not working in testing
Hover effects only work:
- In browsers (not in most email clients)
- Some modern email clients (Apple Mail, Outlook.com)
- Not in Gmail or Outlook desktop

## ESP Upload

1. Build your email: `node build-email.js tta-email.json`
2. Open the generated HTML: `dist/TTA/2026/tta-email.html`
3. Copy all HTML content
4. Paste into your ESP (MailChimp, Klaviyo, etc.)
5. Test send before deploying
6. Check rendering in Litmus or Email on Acid

## License

Internal use only.
