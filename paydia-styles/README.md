# Paydia Styles - Modular CSS

Converted from SCSS to modular, maintainable CSS files.

## 📁 File Structure

```
paydia-styles/
├── paydia-styles.css          # Master file (imports all modules)
├── 01-variables.css           # CSS Custom Properties (colors, spacing, etc.)
├── 02-base-layout.css         # Base page structure and typography
├── 03-tabs-navigation.css     # Tab controls and navigation
├── 04-product-cards.css       # Product/ticket card components
├── 05-sale-summary.css        # Sticky bottom summary bar
├── 06-utilities.css           # Utilities and miscellaneous styles
├── 07-reservation-wizard.css  # Calendar and reservation system
├── 08-modal-base.css          # Modal overlay and structure
├── 09-cart-items.css          # Cart item display and controls
├── 10-cart-upsells.css        # Upsell items section
├── 11-cart-checkout.css       # Checkout flow and order summary
├── 12-guest-list.css          # Guest list management
├── 13-checkout-forms.css      # Payment and billing forms
├── 14-error-states.css        # Error messages and loading states
├── example.html               # Usage example
├── README.md                  # This file
└── CONVERSION-STATUS.md       # Conversion progress tracking
```

## 🚀 Quick Start

### Option 1: Import All Styles (Simplest)

```html
<link rel="stylesheet" href="paydia-styles/paydia-styles.css">
```

### Option 2: Import Individual Modules (Best Performance)

Import only what you need:

```html
<link rel="stylesheet" href="paydia-styles/01-variables.css">
<link rel="stylesheet" href="paydia-styles/02-base-layout.css">
<link rel="stylesheet" href="paydia-styles/04-product-cards.css">
<!-- etc. -->
```

### Option 3: Combine for Production

Concatenate all CSS files into one for production:

```bash
cat 01-variables.css 02-base-layout.css 03-tabs-navigation.css 04-product-cards.css 05-sale-summary.css 06-utilities.css 07-reservation-wizard.css 08-modal-base.css 09-cart-items.css 10-cart-upsells.css 11-cart-checkout.css 12-guest-list.css 13-checkout-forms.css 14-error-states.css > paydia-styles-combined.css
```

Then minify using your preferred tool (cssnano, clean-css, etc.)

## 📝 Module Descriptions

### 01-variables.css
**CSS Custom Properties (Variables)**
- Brand colors (blue, red, etc.)
- Spacing scales
- Border radius values
- Shadow definitions
- Typography settings

**Action Required:** Update the color values to match your brand:
```css
:root {
  --blue: #003399;        /* Change to your primary color */
  --blue-light: #4d79ff;  /* Change to your accent color */
  --red: #ff0000;         /* Change to your error/danger color */
}
```

### 02-base-layout.css
**Page Structure & Typography**
- Base page styles for Paydia pages
- Main container layouts
- Heading styles (h1, h2, etc.)
- Responsive typography

**Applies to:** All Paydia storefront pages

### 03-tabs-navigation.css
**Tab Controls**
- Desktop tab navigation
- Mobile tab dropdown
- Tab toggle buttons
- Active/hover states

**Components:**
- `.tabs-wrapper`
- `.nav-tabs`
- `.tabs-toggle`
- `.mobile-tabs-wrapper`

### 04-product-cards.css
**Product/Ticket Cards**
- Card layout (image, title, description, price)
- Feature lists with checkmarks
- Quantity selectors (plus/minus buttons)
- Add to cart / Remove buttons
- Responsive grid (3-column → 2-column → 1-column)

**Components:**
- `.row.mt-3` (card container)
- Card images, titles, descriptions
- Feature lists (`<ul>` with checkmark icons)
- Quantity controls
- Action buttons

### 05-sale-summary.css
**Sticky Summary Bar**
- Bottom sticky bar with total and checkout
- Promo code input and validation
- Error/success message styling
- Responsive layout

**Components:**
- `#sale-summary`
- Promo code form
- Total price display
- Checkout button

**Features:**
- Sticks to bottom on desktop
- Full-width background effect
- Icon-based error messages

### 06-utilities.css
**Utilities & Overrides**
- Hidden elements (newsletter, Instagram sections)
- Chat widget positioning
- Z-index overrides
- Miscellaneous utility styles

### 07-reservation-wizard.css
**Reservation Wizard & Calendar**
- Complete calendar/date picker system
- Month navigation (previous/next)
- Available/unavailable date styling
- Selected date highlighting
- Room/time selection dropdowns
- Guest input forms (3-column responsive grid)
- Capacity warnings
- Wizard navigation buttons

**Components:**
- `#paydia-reservation-wizard`
- Calendar table with interactive dates
- Guest list input forms
- Room/time selection interface

### 08-modal-base.css
**Modal Overlay & Structure**
- Full-screen modal overlay
- Modal container with responsive sizing
- Modal header with title
- Close button (X) with hover states
- Modal body and footer sections
- Empty cart message styling

**Components:**
- `body > #paydia-cart`
- `#paydia-modal-content`

### 09-cart-items.css
**Cart Item Display**
- Cart items list layout
- Item images, titles, descriptions
- Item metadata (date, time, guests)
- Item pricing and subtotals
- Quantity controls (plus/minus buttons)
- Remove item buttons
- Guest names display (for party bookings)

**Components:**
- `.cart-items-list`
- `.cart-item`
- Quantity controls
- Remove buttons

### 10-cart-upsells.css
**Upsell Items Section**
- Responsive grid of upsell items
- Upsell item cards with images
- Item titles, descriptions, prices
- Add to cart buttons
- Badge support (Popular, Recommended)
- Added-to-cart state styling

**Components:**
- `.upsells-section`
- `.upsell-item`
- Responsive grid layout

### 11-cart-checkout.css
**Checkout Flow & Summary**
- Promo code input and validation
- Success/error message styling
- Order summary with line items
- Subtotal, discount, and total display
- Checkout button
- Continue shopping button
- Clear cart functionality
- Loading overlay

**Components:**
- `.cart-summary`
- Promo code section
- Order totals display
- Checkout button

### 12-guest-list.css
**Guest List Management**
- Individual guest item cards
- Guest name input forms (first/last)
- Reservation item selection per guest
- Guest-specific pricing
- Remove guest button
- Guest total calculation
- Responsive 2-column layout

**Components:**
- `.paydia-guest-list-items`
- `.guest-item`
- Reservation item cards

### 13-checkout-forms.css
**Payment & Billing Forms**
- Checkout section layout
- Form grid (2-column responsive)
- Text inputs, dropdowns, and labels
- Payment method selection
- Credit card fields
- Order review section
- Order items list
- Order totals summary
- Submit order button
- Terms and conditions checkbox
- Field validation styling

**Components:**
- `#paydia-checkout-container`
- `.checkout-section`
- Form fields and inputs
- Payment methods

### 14-error-states.css
**Error Messages & Loading States**
- Error containers (global and inline)
- Success messages
- Warning and info messages
- API error display with error codes
- Loading spinners (small, medium, large)
- Loading overlays
- Button loading states
- Empty state styling
- Toast notifications
- Validation summary
- Network error display

**Components:**
- `.error-container`
- `.success-container`
- `.loading-spinner`
- `.toast-notification`
- Validation states

## 🎨 Customization Guide

### 1. Update Brand Colors

Edit `01-variables.css`:

```css
:root {
  --blue: #YOUR_PRIMARY_COLOR;
  --blue-light: #YOUR_ACCENT_COLOR;
  --red: #YOUR_ERROR_COLOR;
}
```

### 2. Adjust Spacing

Edit `01-variables.css`:

```css
:root {
  --spacing-sm: 16px;  /* Adjust to your scale */
  --spacing-md: 20px;
  --spacing-lg: 30px;
}
```

### 3. Change Border Radius

Edit `01-variables.css`:

```css
:root {
  --radius-sm: 8px;
  --radius-md: 16px;
  --radius-lg: 20px;
  --radius-pill: 100px;  /* For pill-shaped buttons */
}
```

### 4. Update Breakpoints

Media queries are currently:
- **Mobile:** `max-width: 767px`
- **Tablet:** `768px - 991px`
- **Laptop:** `992px - 1440px`
- **Desktop:** `1441px+`

To change, find and replace media queries in individual files.

## 🔧 Browser Support

### Supported:
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

### Not Supported:
- Internet Explorer 11 (CSS custom properties not supported)

### Fallbacks Needed For:
- **aspect-ratio** (for older browsers)
- **gap** in flexbox (for Safari < 14.1)

## 📱 Responsive Behavior

### Product Cards
- **Desktop (> 992px):** 3 columns
- **Tablet (768px - 991px):** 2 columns
- **Mobile (< 768px):** 1 column

### Sale Summary
- **Desktop:** Fixed to bottom with full-width background
- **Mobile:** Static position, stacked layout

### Tabs
- **Desktop:** Horizontal tabs in background container
- **Mobile:** Dropdown with toggle button

## 🐛 Known Issues & Limitations

### 1. Long Selectors
Due to SCSS nesting conversion, selectors can be very long. This is intentional for specificity but could be refactored.

### 2. Image Paths
Assumes images are at `../../resources/assets/images/`. Update paths if different:
```css
/* Find and replace */
../../resources/assets/images/ → /your/path/
```

### 3. Font Loading
Assumes "Neue Kabel" font is already loaded. Ensure this in your HTML:
```html
<link rel="preload" href="/fonts/NeueKabel.woff2" as="font" type="font/woff2" crossorigin>
```

## 🔄 Migration from SCSS

This CSS was converted from SCSS. Key changes:

| SCSS Feature | CSS Equivalent |
|--------------|----------------|
| `$blue` variable | `var(--blue)` custom property |
| `@include phone` | `@media (max-width: 767px)` |
| `&:hover` nesting | Full selector with `:hover` |
| `@mixin fontsize()` | Manual responsive font-size |

## ✅ Complete Conversion

All sections from the original SCSS have been successfully converted to modular CSS files! This includes:

- ✅ Base styles and variables
- ✅ Navigation and product cards
- ✅ Reservation wizard with calendar
- ✅ Full cart modal system
- ✅ Guest list management
- ✅ Checkout forms
- ✅ Error states and loading indicators

See [CONVERSION-STATUS.md](CONVERSION-STATUS.md) for detailed conversion statistics.

## 🤝 Contributing

### Adding New Modules

1. Create new file: `XX-module-name.css`
2. Add descriptive header comment
3. Import in `paydia-styles.css`
4. Update this README

### Naming Conventions

- **Files:** Numbered prefix + kebab-case (`01-variables.css`)
- **Classes:** Use existing Paydia class names
- **Custom Properties:** Double-dash prefix (`--blue`)

## 💡 Tips

### Performance
- Combine & minify for production
- Remove unused modules
- Use critical CSS for above-the-fold content

### Maintenance
- Keep variables in `01-variables.css`
- One component per module
- Document complex selectors

### Testing
Test across:
- Different page types (tickets, season-pass, parties)
- Breakpoints (mobile, tablet, desktop)
- Browsers (Chrome, Firefox, Safari)

## 🆘 Support

### Common Issues

**Q: Colors not showing correctly**
A: Check that `01-variables.css` is loaded first and values are updated

**Q: Styles not applying**
A: Check selector specificity and page class names

**Q: Images not loading**
A: Verify image paths match your file structure

**Q: Responsive layout broken**
A: Check container widths and media queries

## 📄 License

Same as original project.

---

**Last Updated:** 2025
**Converted By:** Claude Code
**Original Format:** SCSS
**Current Format:** Modular CSS
