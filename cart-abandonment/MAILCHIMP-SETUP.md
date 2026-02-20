# Mailchimp Setup Guide

Complete guide to configuring Mailchimp for cart abandonment tracking.

## Prerequisites

- Active Mailchimp account (Standard plan or higher recommended)
- E-commerce features enabled
- Basic understanding of Mailchimp automations

## Step 1: Generate API Key

1. Log into Mailchimp
2. Navigate to **Account → Extras → API keys**
3. Click **Create A Key**
4. Copy the API key (format: `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us1`)
5. Save securely (you won't be able to see it again)

**API Key Format:**
```
abc123def456ghi789jkl012mno345-us1
└─────────────┬────────────────────┘ └┬┘
         API Key                      Server Prefix
```

**Important:**
- Never share or commit API keys to version control
- Store in environment variables
- Rotate every 90 days for security

## Step 2: Get Your List ID

1. Go to **Audience → All contacts**
2. Click **Settings → Audience name and defaults**
3. Look for **Audience ID** (format: `a1b2c3d4e5`)
4. Copy this ID

**Alternative Method:**
```bash
# Using API to list all audiences
curl -X GET \
  "https://us1.api.mailchimp.com/3.0/lists" \
  -u "anystring:YOUR_API_KEY"
```

## Step 3: Create E-commerce Store

### Via Mailchimp Dashboard

1. Navigate to **Audience → All contacts**
2. Click **Manage Audience → Settings**
3. Select **E-commerce Stores**
4. Click **Create Store**
5. Fill in details:
   - **Store Name:** Your store name
   - **Currency Code:** USD (or your currency)
   - **Timezone:** Your timezone

6. Note your **Store ID**

### Via API

```javascript
const mailchimp = require('@mailchimp/mailchimp_marketing');

mailchimp.setConfig({
  apiKey: 'YOUR_API_KEY',
  server: 'us1'
});

async function createStore() {
  const response = await mailchimp.ecommerce.addStore({
    id: 'my_store_id', // Unique identifier
    list_id: 'YOUR_LIST_ID',
    name: 'My Store',
    currency_code: 'USD',
    platform: 'Custom' // or 'WordPress', 'Shopify', etc.
  });

  console.log('Store created:', response.id);
}

createStore();
```

**Store ID Format:**
- Can be anything: `store_123`, `mystore`, etc.
- Must be unique across your Mailchimp account
- Cannot be changed later

## Step 4: Configure Abandoned Cart Automation

### Create Automation

1. Go to **Campaigns → All campaigns**
2. Click **Create Campaign → Email → Automated**
3. Select **Abandoned Cart**
4. Click **Begin**

### Automation Settings

**Trigger Settings:**
```
Trigger: Cart is abandoned
Delay: 1-24 hours (recommend starting with 1-2 hours)
Conditions:
  ✓ Cart has not been purchased
  ✓ Cart is not empty
  ✓ Total is greater than $0.00
```

**Recipient Settings:**
```
To: Customers who abandoned their cart
From: your-email@yourdomain.com
Subject: You left something behind!
```

### Email Content

**Merge Tags Available:**
- `|FNAME|` - First name
- `|LNAME|` - Last name
- `|EMAIL|` - Email address

**E-commerce Merge Tags:**
```
*|ABANDONED_CART|*
```

This creates a complete cart block with:
- Product images
- Product names
- Quantities
- Prices
- Total
- Checkout button

**Example Email Template:**
```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Don't forget your items!</title>
</head>
<body>
  <h1>Hi *|FNAME:there|*!</h1>

  <p>You left some items in your cart. Complete your purchase now!</p>

  <!-- This renders the full cart -->
  *|ABANDONED_CART|*

  <p>
    <a href="*|CHECKOUT_URL|*" style="padding: 15px 30px; background: #007bff; color: white; text-decoration: none;">
      Complete Purchase
    </a>
  </p>

  <p>Questions? Just reply to this email.</p>

  <p>Thanks,<br>Your Store Team</p>
</body>
</html>
```

### Advanced: Multi-Stage Automation

**Stage 1: Reminder (1 hour)**
```
Subject: Did you forget something?
Content: Friendly reminder with cart contents
CTA: "Complete Your Order"
```

**Stage 2: Incentive (24 hours)**
```
Subject: Here's 10% off to complete your order!
Content: Cart + discount code
CTA: "Claim Your Discount"
Condition: Cart still not purchased
```

**Stage 3: Last Chance (72 hours)**
```
Subject: Last chance - items may sell out!
Content: Cart + urgency message
CTA: "Don't Miss Out"
Condition: Cart still not purchased
```

### Automation Workflow

```
Cart Abandoned
     │
     ├─ Wait 1 hour
     │
     ├─ Send Reminder Email
     │      │
     │      ├─ Purchased? → END
     │      └─ Not Purchased → Continue
     │
     ├─ Wait 23 hours (24 total)
     │
     ├─ Send Incentive Email
     │      │
     │      ├─ Purchased? → END
     │      └─ Not Purchased → Continue
     │
     ├─ Wait 48 hours (72 total)
     │
     └─ Send Final Email
            │
            └─ END
```

## Step 5: Test the Integration

### Test Cart Creation

```javascript
const testCart = {
  id: 'test_cart_123',
  customer: {
    id: 'test_customer',
    email_address: 'test@example.com',
    opt_in_status: true
  },
  currency_code: 'USD',
  order_total: 99.99,
  lines: [
    {
      id: 'line_1',
      product_id: 'prod_123',
      product_variant_id: 'var_456',
      quantity: 1,
      price: 99.99
    }
  ],
  checkout_url: 'https://yourstore.com/checkout'
};

const response = await mailchimp.ecommerce.addStoreCart(
  'YOUR_STORE_ID',
  testCart
);

console.log('Test cart created:', response);
```

### Verify in Mailchimp

1. Go to **Audience → All contacts**
2. Search for test email
3. View contact profile
4. Check **E-commerce** tab
5. Verify cart appears

### Test Automation

1. Create test cart (as above)
2. Wait for automation delay (1 hour)
3. Check contact's email history
4. Verify email was sent

**Quick Test (Skip Wait):**
1. Temporarily set automation delay to 1 minute
2. Create test cart
3. Wait 1 minute
4. Check email
5. Reset automation delay to production value

## Step 6: Product Synchronization

### Option 1: Auto-Create Products

Products are created automatically when carts are added (handled by our backend code).

**Pros:**
- No manual setup
- Works immediately
- Simple implementation

**Cons:**
- Limited product data
- No product collections
- Basic images only

### Option 2: Bulk Import Products

For better product data:

```javascript
async function syncProducts(products) {
  for (const product of products) {
    await mailchimp.ecommerce.addStoreProduct(
      'YOUR_STORE_ID',
      {
        id: product.id,
        title: product.name,
        description: product.description,
        url: product.url,
        variants: product.variants.map(v => ({
          id: v.id,
          title: v.name,
          price: v.price,
          sku: v.sku,
          inventory_quantity: v.stock,
          image_url: v.image
        })),
        images: product.images.map(img => ({
          id: img.id,
          url: img.url
        }))
      }
    );
  }
}
```

### Option 3: Feed/Sync Integration

For Shopify, WooCommerce, etc.:
1. Use native Mailchimp integration
2. Products sync automatically
3. Better product recommendations
4. Automatic stock updates

## Step 7: Configure Recovery URL

### Basic Recovery URL

```javascript
const cartUrl = `https://yourstore.com/cart?recover=${cartId}`;
```

### Advanced: Pre-populate Cart

```javascript
// Backend endpoint
app.get('/cart/recover/:cartId', async (req, res) => {
  const { cartId } = req.params;

  // Get cart from Mailchimp
  const cart = await mailchimp.ecommerce.getStoreCart(
    MAILCHIMP_STORE_ID,
    cartId
  );

  // Redirect to Paydia with cart data
  const itemParams = cart.lines.map(line =>
    `item=${line.product_id}&qty=${line.quantity}`
  ).join('&');

  res.redirect(`https://yourstore.com/cart?${itemParams}`);
});
```

## Step 8: Tracking & Analytics

### View Abandoned Cart Reports

1. Go to **Reports → E-commerce**
2. Click **Abandoned Cart**
3. View metrics:
   - Total abandoned carts
   - Abandonment rate
   - Recovery rate
   - Revenue recovered

### Key Metrics to Track

```
Abandonment Rate = (Abandoned Carts / Total Carts) × 100

Recovery Rate = (Recovered Sales / Abandoned Carts) × 100

Average Cart Value = Total Revenue / Number of Carts

Revenue Recovered = Recovered Sales × Average Cart Value
```

### Custom Reporting via API

```javascript
async function getAbandonedCartStats() {
  const response = await mailchimp.ecommerce.getStoreCartLines(
    MAILCHIMP_STORE_ID
  );

  const total = response.carts.length;
  const recovered = response.carts.filter(c => c.recovered).length;
  const rate = (recovered / total) * 100;

  console.log(`Recovery Rate: ${rate.toFixed(2)}%`);
}
```

## Step 9: Advanced Configuration

### Segmentation

Create segments for targeted recovery:

**High-Value Carts:**
```
Conditions:
  Cart Total > $100
```

**Frequent Abandoners:**
```
Conditions:
  Abandoned Cart Count > 3
  Time Since Last Purchase > 30 days
```

**First-Time Visitors:**
```
Conditions:
  Total Orders = 0
  Abandoned Cart Count = 1
```

### Personalization

**Dynamic Content Blocks:**
```html
*|IF:CART_TOTAL > 100|*
  <p>🎁 Free shipping on orders over $100!</p>
*|END:IF|*

*|IF:CART_ITEM_COUNT > 3|*
  <p>💰 10% off orders with 3+ items!</p>
*|END:IF|*
```

**Product Recommendations:**
```html
*|PRODUCT_RECOMMENDATIONS|*
```

### A/B Testing

Test different approaches:

**Subject Lines:**
- "You left something behind"
- "Complete your order & save 10%"
- "Your cart expires soon!"

**Send Times:**
- 1 hour vs 4 hours vs 24 hours

**Incentives:**
- No discount
- 10% off
- Free shipping

## Troubleshooting

### Issue: Carts Not Appearing

**Check:**
1. Store ID is correct
2. List ID is correct
3. API key has e-commerce permissions
4. Customer exists in audience

**Debug:**
```javascript
// Test cart creation
const cart = await mailchimp.ecommerce.addStoreCart(STORE_ID, testCart);
console.log('Cart created:', cart.id);

// Verify cart exists
const verify = await mailchimp.ecommerce.getStoreCart(STORE_ID, cart.id);
console.log('Cart verified:', verify);
```

### Issue: Emails Not Sending

**Check:**
1. Automation is enabled
2. Trigger conditions are met
3. Contact is subscribed (not unsubscribed)
4. Cart hasn't been deleted
5. Required time delay has passed

**Debug:**
```javascript
// Check contact status
const contact = await mailchimp.lists.getListMember(
  LIST_ID,
  generateSubscriberHash(email)
);

console.log('Status:', contact.status); // Should be 'subscribed'
```

### Issue: Products Missing Images

**Solution:**
```javascript
// Ensure image URLs are publicly accessible
await mailchimp.ecommerce.updateStoreProduct(STORE_ID, productId, {
  variants: [{
    id: variantId,
    image_url: 'https://cdn.yourstore.com/products/image.jpg' // Must be HTTPS
  }]
});
```

## Best Practices

### ✅ Do

- Use descriptive product names
- Include high-quality images
- Set reasonable automation delays (1-4 hours)
- Segment your audience
- A/B test subject lines
- Monitor recovery rates
- Clean up old carts (7-30 days)
- Use HTTPS for all URLs
- Test before launching

### ❌ Don't

- Send too many emails (max 3 per cart)
- Use generic "No-Reply" sender
- Include out-of-stock items
- Forget to delete carts after purchase
- Expose sensitive customer data
- Use low-quality images
- Set delays too short (<30 min)
- Forget to test email rendering

## Resources

- [Mailchimp API Documentation](https://mailchimp.com/developer/marketing/api/)
- [E-commerce API Reference](https://mailchimp.com/developer/marketing/api/ecommerce/)
- [Abandoned Cart Guide](https://mailchimp.com/help/create-an-abandoned-cart-email/)
- [Merge Tags Reference](https://mailchimp.com/help/all-the-merge-tags-cheat-sheet/)

## Support

If you encounter issues:

1. Check [Mailchimp Status](https://status.mailchimp.com/)
2. Review API logs in Mailchimp dashboard
3. Test with Mailchimp's API Playground
4. Contact Mailchimp support (Standard+ plans)
