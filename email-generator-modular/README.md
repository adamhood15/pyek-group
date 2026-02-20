# Modular Email Generator

**Next-generation email system with complete section control and ordering flexibility.**

Build emails by arranging modular components in any order. Each section is independently configurable, giving you complete creative control while maintaining email best practices.

## Quick Start

```bash
# Install dependencies
cd email-generator-modular
npm install

# Build an email
node build-email.js tta-email.json

# Build all emails at once
./build-all.sh

# Output will be in /dist/{BRAND}/2026/ folder
```

## Key Features

✅ **Modular Components** - Mix and match sections in any order
✅ **JSON-Driven** - Control everything from one configuration file
✅ **Brand-Aware** - Automatic brand configurations (CBV, TTA, TTH)
✅ **Email-Safe** - Table-based HTML with CSS inlining
✅ **Outlook Compatible** - VML fallbacks for buttons
✅ **Dark Mode Support** - Optimized for dark mode email clients
✅ **Responsive** - Mobile-friendly layouts
✅ **Reusable** - DRY component architecture

## Project Structure

```
email-generator-modular/
├── build-email.js           # Build script with partial registration
├── build-all.sh             # Build all campaigns
├── brand-config.js          # Brand configurations (CBV, TTA, TTH)
├── package.json             # Dependencies
├── templates/
│   ├── modular-template.hbs # Main email wrapper
│   └── components/          # Reusable component partials
│       ├── heading.hbs
│       ├── image.hbs
│       ├── single-column.hbs
│       ├── two-column.hbs
│       ├── three-column.hbs
│       ├── cta-button.hbs
│       ├── cta-dual.hbs
│       ├── spacer.hbs
│       └── divider.hbs
├── campaigns/               # Campaign JSON files
│   ├── cbv-email.json
│   ├── tta-email.json
│   └── tth-email.json
└── dist/                    # Generated HTML output
    ├── CBV/2026/
    ├── TTA/2026/
    └── TTH/2026/
```

## JSON Structure

```json
{
  "brand": "tta",
  "meta": {
    "title": "Email Subject",
    "preheader": "Preview text (optional)"
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
    {
      "type": "heading",
      "content": "Your Heading",
      "fontSize": "42px"
    },
    {
      "type": "image",
      "src": "https://cdn.com/image.png",
      "alt": "Image description",
      "link": "https://destination.com"
    },
    {
      "type": "cta-button",
      "text": "CLICK HERE",
      "url": "https://destination.com"
    }
  ]
}
```

## Available Components

### 1. Heading

Large text heading, ideal for section titles.

```json
{
  "type": "heading",
  "content": "Your Heading Text",
  "fontSize": "42px",
  "lineHeight": "48px",
  "fontWeight": "700",
  "color": "#ffffff",
  "textAlign": "center",
  "padding": "30px 50px 0 50px"
}
```

**Properties:**
- `content` - Heading text (supports HTML)
- `fontSize` - Text size (default: "48px")
- `lineHeight` - Line height (default: "52px")
- `fontWeight` - Font weight (default: "700")
- `color` - Text color (default: theme.textColor)
- `textAlign` - Alignment (default: "center")
- `padding` - CSS padding (default: "25px 50px 0 50px")

---

### 2. Image

Full-width image with optional link.

```json
{
  "type": "image",
  "src": "https://cdn.com/image.png",
  "alt": "Image description",
  "link": "https://destination.com",
  "padding": "0"
}
```

**Properties:**
- `src` - Image URL (required)
- `alt` - Alt text for accessibility (required)
- `link` - Destination URL (optional - wraps image in anchor)
- `padding` - CSS padding (default: "0")

---

### 3. Single Column

Text content in a single centered column.

```json
{
  "type": "single-column",
  "content": "Your paragraph text with <strong>bold</strong> and <span style=\"color:#ff0000;\">colored</span> text",
  "fontSize": "24px",
  "lineHeight": "34px",
  "fontWeight": "400",
  "color": "#ffffff",
  "textAlign": "center",
  "padding": "35px 50px"
}
```

**Properties:**
- `content` - Text content (supports HTML)
- `fontSize` - Text size (default: "24px")
- `lineHeight` - Line height (default: "34px")
- `fontWeight` - Font weight (default: "400")
- `color` - Text color (default: theme.textColor)
- `textAlign` - Alignment (default: "center")
- `padding` - CSS padding (default: "35px 50px")

---

### 4. Two Column

Side-by-side layout with images, titles, descriptions, and CTAs.

```json
{
  "type": "two-column",
  "padding": "25px 25px",
  "columnWidth": "300",
  "columnPadding": "15px",
  "imageWidth": "250",
  "textAlign": "left",
  "columns": [
    {
      "image": "https://cdn.com/image1.png",
      "alt": "Image 1",
      "link": "https://destination1.com",
      "title": "Column 1 Title",
      "description": "Column 1 description text",
      "ctaText": "Learn More",
      "ctaLink": "https://destination1.com"
    },
    {
      "image": "https://cdn.com/image2.png",
      "alt": "Image 2",
      "link": "https://destination2.com",
      "title": "Column 2 Title",
      "description": "Column 2 description text",
      "ctaText": "Learn More",
      "ctaLink": "https://destination2.com"
    }
  ]
}
```

**Properties:**
- `padding` - Padding around entire section (default: "25px 0")
- `columnWidth` - Width of each column (default: "300")
- `columnPadding` - Padding inside columns (default: "10px")
- `imageWidth` - Image width (default: "280")
- `textAlign` - Text alignment (default: "left")
- `titleFontSize` - Title font size (default: "20px")
- `titleLineHeight` - Title line height (default: "24px")
- `titleColor` - Title color (default: theme.textColor)
- `descriptionFontSize` - Description font size (default: "16px")
- `descriptionLineHeight` - Description line height (default: "22px")
- `descriptionColor` - Description color (default: theme.textColor)
- `ctaColor` - CTA link color (default: "#ffe00c")
- `ctaFontSize` - CTA font size (default: "16px")

**Column Properties:**
- `image` - Image URL (optional)
- `alt` - Image alt text
- `link` - Image link (optional)
- `title` - Column title (optional)
- `description` - Column description (optional)
- `ctaText` - CTA link text (optional)
- `ctaLink` - CTA destination URL

---

### 5. Three Column

Three-column layout, ideal for features/icons.

```json
{
  "type": "three-column",
  "padding": "25px 25px",
  "columnWidth": "200",
  "columnPadding": "10px",
  "iconWidth": "60",
  "columns": [
    {
      "icon": "https://cdn.com/icon1.png",
      "alt": "Icon 1",
      "title": "Feature 1",
      "text": "Feature description"
    },
    {
      "icon": "https://cdn.com/icon2.png",
      "alt": "Icon 2",
      "title": "Feature 2",
      "text": "Feature description"
    },
    {
      "icon": "https://cdn.com/icon3.png",
      "alt": "Icon 3",
      "title": "Feature 3",
      "text": "Feature description"
    }
  ]
}
```

**Properties:**
- `padding` - Padding around entire section (default: "25px 0")
- `columnWidth` - Width of each column (default: "200")
- `columnPadding` - Padding inside columns (default: "10px")
- `iconWidth` - Icon width (default: "60")
- `titleFontSize` - Title font size (default: "18px")
- `titleColor` - Title color (default: theme.textColor)
- `textFontSize` - Text font size (default: "14px")
- `textColor` - Text color (default: theme.textColor)

**Column Properties:**
- `icon` - Icon/image URL (optional)
- `alt` - Icon alt text
- `title` - Feature title (optional)
- `text` - Feature description (optional)

---

### 6. CTA Button

Single call-to-action button with full styling control.

```json
{
  "type": "cta-button",
  "text": "GET YOUR TICKETS",
  "url": "https://typhoontexas.com/tickets/",
  "color": "#ffe00c",
  "textColor": "#003955",
  "borderColor": "#ffe00c",
  "fontSize": "22px",
  "fontWeight": "900",
  "buttonPadding": "16px 40px",
  "width": "350px",
  "height": "65px",
  "padding": "25px 0 50px 0"
}
```

**Properties:**
- `text` - Button text (required)
- `url` - Destination URL (required)
- `color` - Button background color (default: brand primary color)
- `textColor` - Button text color (default: "#ffffff")
- `borderColor` - Button border color (default: matches color)
- `fontSize` - Text font size (default: "18px")
- `fontWeight` - Text font weight (default: "900")
- `buttonPadding` - Padding inside button (default: "10px 20px")
- `width` - Outlook VML width (default: "240px")
- `height` - Outlook VML height (default: "60px")
- `padding` - Padding around button container (default: "25px 0 50px 0")

**Hover Effect:** Automatically inverts colors on hover (background ↔ text color)

---

### 7. CTA Dual

Dual buttons with brand logos (CBV only).

```json
{
  "type": "cta-dual",
  "showBay": true,
  "showCanyon": true,
  "bayUrl": "https://cowabungavegas.com/bay/",
  "bayText": "BAY TICKETS",
  "canyonUrl": "https://cowabungavegas.com/canyon/",
  "canyonText": "CANYON TICKETS",
  "padding": "25px 0 0 0"
}
```

**Properties:**
- `showBay` - Show Bay button (default: true)
- `showCanyon` - Show Canyon button (default: true)
- `bayUrl` - Bay destination URL
- `bayText` - Bay button text
- `canyonUrl` - Canyon destination URL
- `canyonText` - Canyon button text
- `padding` - Padding around entire section (default: "25px 0 0 0")

---

### 8. Spacer

Empty vertical spacing.

```json
{
  "type": "spacer",
  "height": "30px"
}
```

**Properties:**
- `height` - Vertical height (default: "20px")

---

### 9. Divider

Horizontal line separator.

```json
{
  "type": "divider",
  "color": "#ffe00c",
  "thickness": "2px",
  "style": "solid",
  "padding": "30px 50px"
}
```

**Properties:**
- `color` - Line color (default: "#cccccc")
- `thickness` - Line thickness (default: "1px")
- `style` - Line style (default: "solid", options: "solid", "dashed", "dotted")
- `padding` - Padding around divider (default: "20px 50px")

---

## Build Process

The build script automatically:

1. ✅ Registers all component partials from `/templates/components/`
2. ✅ Validates campaign JSON structure
3. ✅ Applies brand-specific configuration
4. ✅ Compiles Handlebars template with sections
5. ✅ Generates dynamic CTA hover styles
6. ✅ Inlines CSS for email client compatibility
7. ✅ Minifies HTML for production
8. ✅ Outputs to brand-specific folders
9. ✅ Displays build statistics

## Build Examples

```bash
# Single email
node build-email.js tta-email.json

# All emails
./build-all.sh
```

**Output:**
```
📦 Registering component partials...
   ✓ Registered: heading
   ✓ Registered: image
   ✓ Registered: single-column
   ✓ Registered: two-column
   ✓ Registered: three-column
   ✓ Registered: cta-button
   ✓ Registered: cta-dual
   ✓ Registered: spacer
   ✓ Registered: divider

🚀 Building email from: tta-email.json
   Brand: Typhoon Texas Austin (TTA)
   Sections: 11 components

🔨 Compiling template...
🎨 Inlining CSS...
📦 Minifying HTML...

✅ Build complete!

📊 Build Stats:
   File size: 45.23 KB
   Sections: 11
   Section types: image, heading, single-column, two-column, spacer, divider, three-column, cta-button

📁 Output: dist/TTA/2026/tta-email.json

✨ Ready to upload to ESP!
```

## Workflow

### Creating a New Email

1. **Open JSON file** for your brand (cbv/tta/tth-email.json)

2. **Define sections array** in the order you want them to appear:
   ```json
   "sections": [
     { "type": "image", ... },
     { "type": "heading", ... },
     { "type": "single-column", ... },
     { "type": "cta-button", ... }
   ]
   ```

3. **Configure each section** with its properties

4. **Build:** `node build-email.js tta-email.json`

5. **Output:** Check `dist/TTA/2026/tta-email.html`

6. **Upload to ESP**

### Reordering Sections

Simply rearrange objects in the `sections` array:

```json
// Before: Image first, then heading
"sections": [
  { "type": "image", ... },
  { "type": "heading", ... }
]

// After: Heading first, then image
"sections": [
  { "type": "heading", ... },
  { "type": "image", ... }
]
```

### Adding New Sections

Insert a new section object at any position:

```json
"sections": [
  { "type": "image", ... },
  { "type": "spacer", "height": "30px" },  // NEW
  { "type": "heading", ... }
]
```

### Removing Sections

Delete the section object from the array.

## Brand Configuration

Three brands are pre-configured in `brand-config.js`:

- **CBV** - Cowabunga Bay & Canyon (dual CTA buttons)
- **TTA** - Typhoon Texas Austin (single CTA button)
- **TTH** - Typhoon Texas Houston (single CTA button)

Each brand has:
- Footer logo and contact info
- CTA button configuration
- Brand colors and styling

## Email Best Practices

✅ **Table-Based Layout** - Maximum email client compatibility
✅ **Inline CSS** - Automatic CSS inlining with Juice
✅ **VML Buttons** - Outlook compatibility with Vector Markup Language
✅ **Alt Text** - All images have descriptive alt text
✅ **Responsive** - Mobile-friendly with media queries
✅ **Dark Mode** - Optimized for dark mode email clients
✅ **Web Fonts** - Custom fonts with fallbacks
✅ **Minified** - Optimized HTML file size

## ESP Upload

1. Build your email: `node build-email.js tta-email.json`
2. Open generated HTML: `dist/TTA/2026/tta-email.html`
3. Copy all HTML content
4. Paste into your ESP (MailChimp, Klaviyo, etc.)
5. Test send before deploying
6. Check rendering in Litmus or Email on Acid

## Creating Custom Components

To add a new component type:

1. **Create partial** in `templates/components/your-component.hbs`
2. **Use table-based HTML** for email compatibility
3. **Add to template** in `modular-template.hbs`:
   ```handlebars
   {{#if (eq this.type "your-component")}}
     {{> your-component}}
   {{/if}}
   ```
4. **Build will auto-register** the partial

## Troubleshooting

### Build fails with "Campaign must have a sections array"
Your JSON needs a `sections` array. Add:
```json
"sections": []
```

### Section not appearing
Check section type spelling matches exactly (case-sensitive):
- ✅ `"type": "cta-button"`
- ❌ `"type": "CTA-Button"`
- ❌ `"type": "cta_button"`

### Colors not working
Ensure color values include `#`:
- ✅ `"color": "#ffe00c"`
- ❌ `"color": "ffe00c"`

### Images not loading
Use full HTTPS URLs:
- ✅ `"src": "https://cdn.com/image.png"`
- ❌ `"src": "/images/image.png"`

## vs. Original System

| Feature | Original | Modular |
|---------|----------|---------|
| Section Ordering | Fixed | Completely flexible |
| Add New Sections | Edit template | Add to JSON array |
| Reorder Sections | Edit template | Reorder JSON array |
| Component Reuse | Limited | Full reusability |
| Learning Curve | Low | Medium |
| Flexibility | Medium | Very High |
| Maintenance | Single template | Component partials |

## License

Internal use only.
