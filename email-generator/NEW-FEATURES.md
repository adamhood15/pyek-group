# New Features Guide

## Heading Section

Add a prominent heading above body paragraphs (between hero images and body text).

### Basic Usage

```json
"heading": {
  "enabled": true,
  "content": "LAST CHANCE TO SAVE!",
  "fontSize": "36px",
  "lineHeight": "44px",
  "fontWeight": "700",
  "color": "#ffffff",
  "textAlign": "center",
  "padding": "40px 50px 20px 50px"
}
```

### Options

| Property | Description | Example |
|----------|-------------|---------|
| `enabled` | Show/hide heading | `true` or `false` |
| `content` | Heading text (supports HTML) | `"LAST CHANCE!"` |
| `fontSize` | Font size | `"36px"`, `"42px"` |
| `lineHeight` | Line height | `"44px"`, `"50px"` |
| `fontWeight` | Font weight | `"400"` (normal), `"700"` (bold), `"900"` (black) |
| `color` | Text color | `"#ffffff"`, `"#000000"`, `"#ffee30"` |
| `textAlign` | Alignment | `"center"`, `"left"`, `"right"` |
| `padding` | Spacing (top right bottom left) | `"40px 50px 20px 50px"` |

### Examples

**Bold Announcement:**
```json
"heading": {
  "enabled": true,
  "content": "🎉 FLASH SALE ALERT! 🎉",
  "fontSize": "42px",
  "lineHeight": "50px",
  "fontWeight": "900",
  "color": "#ffee30",
  "textAlign": "center",
  "padding": "50px 30px 30px 30px"
}
```

**Subtle Heading:**
```json
"heading": {
  "enabled": true,
  "content": "Exclusive Member Offer",
  "fontSize": "28px",
  "lineHeight": "36px",
  "fontWeight": "400",
  "color": "#ffffff",
  "textAlign": "center",
  "padding": "30px 50px 15px 50px"
}
```

**Disable Heading:**
```json
"heading": {
  "enabled": false
}
```

---

## Body Paragraph Color Control

Control text color per paragraph (overrides theme color).

### Basic Usage

```json
"bodyParagraphs": [
  {
    "content": "This text will be white",
    "fontSize": "24px",
    "lineHeight": "34px",
    "padding": "25px 50px 30px 50px",
    "color": "#ffffff"
  },
  {
    "content": "This text will be yellow",
    "fontSize": "20px",
    "lineHeight": "30px",
    "padding": "0 50px 30px 50px",
    "color": "#ffee30"
  }
]
```

### How It Works

- **With `color` property**: Uses specified color
- **Without `color` property**: Uses `theme.textColor` (default)

### Examples

**Mixed Colors:**
```json
"bodyParagraphs": [
  {
    "content": "Get <span style=\"font-weight:700\">$10 off</span> your pass!",
    "fontSize": "24px",
    "lineHeight": "34px",
    "padding": "25px 50px 20px 50px",
    "color": "#ffffff"
  },
  {
    "content": "Limited time only!",
    "fontSize": "18px",
    "lineHeight": "26px",
    "padding": "0 50px 30px 50px",
    "color": "#ff1469"
  },
  {
    "content": "Terms and conditions apply.",
    "fontSize": "14px",
    "lineHeight": "20px",
    "padding": "0 50px 30px 50px",
    "color": "#cccccc"
  }
]
```

**Using Theme Color (no color specified):**
```json
"bodyParagraphs": [
  {
    "content": "This uses theme.textColor automatically",
    "fontSize": "20px",
    "lineHeight": "30px",
    "padding": "25px 50px 30px 50px"
  }
]
```

---

## Complete Example

Here's a full campaign using both new features:

```json
{
  "meta": {
    "title": "Flash Sale",
    "preheader": "Last chance to save big!"
  },
  "theme": {
    "backgroundColor": "#0285c5",
    "textColor": "#ffffff"
  },
  "utm": {
    "enabled": true,
    "params": "?utm_source=Email&utm_medium=Mailchimp&utm_campaign=Flash-Sale"
  },
  "heroImages": [
    {
      "src": "https://example.com/hero.png",
      "alt": "Flash Sale",
      "link": "https://example.com/"
    }
  ],
  "heading": {
    "enabled": true,
    "content": "⚡ FLASH SALE ENDS TONIGHT ⚡",
    "fontSize": "38px",
    "lineHeight": "46px",
    "fontWeight": "900",
    "color": "#ffee30",
    "textAlign": "center",
    "padding": "40px 30px 25px 30px"
  },
  "bodyParagraphs": [
    {
      "content": "Save <span style=\"font-weight:700\">50%</span> on all Season Passes!",
      "fontSize": "24px",
      "lineHeight": "34px",
      "padding": "0 50px 20px 50px",
      "color": "#ffffff"
    },
    {
      "content": "Hurry! Only 3 hours left.",
      "fontSize": "20px",
      "lineHeight": "30px",
      "padding": "0 50px 30px 50px",
      "color": "#ff1469"
    }
  ],
  "ctaSection": {
    "enabled": true,
    "showBay": true,
    "showCanyon": false,
    "padding": "25px 0 0 0",
    "bayUrl": "https://example.com/flash-sale/",
    "bayText": "GRAB THE DEAL"
  },
  "closingModule": {
    "enabled": false
  }
}
```

---

## Tips

1. **Heading for urgency**: Use bold, bright colors for time-sensitive campaigns
2. **Heading for branding**: Use your brand color with medium weight for elegance
3. **Color hierarchy**: Use brightest color for most important text
4. **Contrast**: Always ensure text color contrasts with background for readability
5. **Test colors**: View in email client to ensure proper rendering

## Common Color Combinations

**Blue Background:**
- White text: `#ffffff`
- Yellow accent: `#ffee30`
- Pink accent: `#ff1469`

**Dark Background:**
- White text: `#ffffff`
- Light gray: `#cccccc`
- Cyan accent: `#00ffff`

**Light Background:**
- Black text: `#000000`
- Dark blue: `#234ea2`
- Dark gray: `#333333`
