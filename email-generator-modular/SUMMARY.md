# Modular Email Generator - System Summary

## ✅ System Complete!

Your new modular email generation system is ready to use. Build emails with complete section control and ordering flexibility.

## 📁 Location

```
/Users/Adam.Hood/Documents/pyek-group/email-generator-modular/
```

## 🎯 Key Features

✅ **9 Reusable Components**
- heading, image, single-column, two-column, three-column
- cta-button, cta-dual, spacer, divider

✅ **Complete Section Control**
- Order sections however you want in JSON
- Add, remove, or reorder sections instantly
- No template editing required

✅ **Email Best Practices**
- Table-based HTML for maximum compatibility
- Inline CSS with Juice
- VML buttons for Outlook
- Dark mode support
- Mobile responsive

✅ **Multi-Brand System**
- CBV, TTA, TTH pre-configured
- Automatic brand colors and CTAs
- Brand-specific footers

## 🚀 Quick Start

```bash
cd email-generator-modular

# Build single email
node build-email.js tta-email.json

# Build all emails
./build-all.sh
```

## 📊 Build Results

All three test emails built successfully:

- **TTA**: 10 sections, 22.45 KB
- **TTH**: 6 sections, 15.42 KB
- **CBV**: 11 sections, 24.10 KB

Output location: `dist/BRAND/2026/`

## 📚 Documentation

- **[README.md](README.md)** - Complete documentation
- **[QUICKSTART.md](QUICKSTART.md)** - Quick reference guide

## 🔧 Component Examples

### Order Matters!

```json
"sections": [
  { "type": "image", ... },        // Shows first
  { "type": "heading", ... },      // Shows second
  { "type": "single-column", ... },// Shows third
  { "type": "cta-button", ... }    // Shows last
]
```

### Flexibility Demo

**Before:**
```json
"sections": [
  { "type": "heading" },
  { "type": "image" },
  { "type": "cta-button" }
]
```

**After (reorder):**
```json
"sections": [
  { "type": "image" },
  { "type": "spacer", "height": "30px" },
  { "type": "heading" },
  { "type": "cta-button" }
]
```

Just rebuild - that's it!

## 📦 What's Included

### Templates
- `modular-template.hbs` - Main wrapper
- `components/*.hbs` - 9 reusable components

### Campaigns
- `cbv-email.json` - Example CBV email
- `tta-email.json` - Example TTA email
- `tth-email.json` - Example TTH email

### Build System
- `build-email.js` - Automatic partial registration
- `build-all.sh` - Batch build script
- `brand-config.js` - Brand configurations

## 🎨 Available Components

| Component | Purpose | Customizable |
|-----------|---------|--------------|
| heading | Large section heading | Size, color, weight, alignment, padding |
| image | Full-width image | Link, padding |
| single-column | Text paragraph | Size, color, alignment, padding |
| two-column | Side-by-side content | Images, titles, descriptions, CTAs |
| three-column | Feature/icon grid | Icons, titles, descriptions |
| cta-button | Single button | Colors, size, text, padding |
| cta-dual | Dual buttons (CBV) | URLs, text for both buttons |
| spacer | Vertical spacing | Height |
| divider | Horizontal line | Color, thickness, style |

## 🆚 vs. Original System

| Feature | Original | Modular |
|---------|----------|---------|
| **Section Ordering** | Fixed in template | JSON array order |
| **Add Sections** | Edit template | Add to JSON |
| **Remove Sections** | Edit template | Remove from JSON |
| **Reorder Sections** | Edit template | Reorder JSON |
| **Component Reuse** | Copy/paste code | Use type property |
| **Flexibility** | Medium | Very High |

## 🔄 Migration Path

1. **Keep original system** for existing campaigns
2. **Use modular system** for new campaigns requiring flexibility
3. **No breaking changes** - both systems coexist

Original: `/email-generator/`
Modular: `/email-generator-modular/`

## 📝 Next Steps

### For Your Next Email

1. Open JSON file: `campaigns/tta-email.json`
2. Update meta data (title, preheader, UTM)
3. Define sections array in desired order
4. Configure each section properties
5. Build: `node build-email.js tta-email.json`
6. Open: `dist/TTA/2026/tta-email.html`
7. Upload to ESP!

### Example: Simple Welcome Email

```json
{
  "brand": "tta",
  "meta": {
    "title": "Welcome!",
    "preheader": "Thanks for joining us!"
  },
  "sections": [
    {
      "type": "image",
      "src": "https://cdn.com/welcome-hero.png",
      "alt": "Welcome!"
    },
    {
      "type": "heading",
      "content": "Welcome to the Team!"
    },
    {
      "type": "single-column",
      "content": "We're excited to have you!"
    },
    {
      "type": "cta-button",
      "text": "GET STARTED",
      "url": "https://typhoontexas.com/"
    }
  ]
}
```

## 🎓 Learn By Example

Check out the three example emails in `/campaigns/`:
- **TTA**: Multi-section with two-column, three-column
- **TTH**: Image-heavy welcome series
- **CBV**: Dual-brand with dual CTA

## 💡 Pro Tips

1. **Start Simple** - Begin with 3-4 sections, then add more
2. **Use Spacers** - Control vertical rhythm
3. **Test Build Often** - Catch errors early
4. **Preview in Browser** - Use `open dist/...` command
5. **Reuse Patterns** - Copy section configs between emails

## ✨ System Architecture

```
JSON Campaign → Build Script → Handlebars Compilation
                                       ↓
                           Register Partials (9 components)
                                       ↓
                           Inject Brand Config
                                       ↓
                           Iterate Sections Array
                                       ↓
                           Render Matching Component
                                       ↓
                           Inline CSS (Juice)
                                       ↓
                           Minify HTML
                                       ↓
                           Output to dist/BRAND/2026/
```

## 🐛 Troubleshooting

**"Cannot find module 'handlebars'"**
→ Run: `npm install`

**Section not appearing**
→ Check type spelling (case-sensitive)

**Colors not working**
→ Use `#` prefix: `"color": "#ffe00c"`

**Images not loading**
→ Use full HTTPS URLs

## 📧 Support

- Documentation: See [README.md](README.md)
- Examples: Check `/campaigns/` folder
- Template Components: See `/templates/components/`

---

**System Status:** ✅ Ready for Production

**Next Command:** `node build-email.js [your-campaign].json`
