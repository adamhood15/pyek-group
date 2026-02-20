# Cart Abandonment Tracking System
## Paydia + Mailchimp Integration

## Architecture Overview

### System Flow

```
┌─────────────────┐
│  Paydia Store   │ Emits custom events
└────────┬────────┘
         │ paydia:cart_updated
         │ paydia:checkout_started
         │ paydia:purchase_completed
         ▼
┌─────────────────┐
│  Frontend JS    │ Event listeners + localStorage
└────────┬────────┘
         │ REST API calls
         ▼
┌─────────────────┐
│  Backend API    │ Validates & processes
└────────┬────────┘
         │ Mailchimp API
         ▼
┌─────────────────┐
│   Mailchimp     │ E-commerce API + Events
└─────────────────┘
         │
         ▼
  Automated Email Campaign
```

### Key Components

1. **Frontend Layer** (`cart-tracker.js`)
   - Event listeners for Paydia events
   - Cart state management (localStorage)
   - Abandonment timer logic
   - Email capture strategy
   - Backend communication

2. **Backend Layer** (Node.js or WordPress PHP)
   - Secure API endpoints
   - Request validation
   - Mailchimp API integration
   - Rate limiting
   - Error handling

3. **Mailchimp Integration**
   - **E-commerce API** (Primary): Structured cart storage
   - **Events API**: Behavioral tracking
   - **Merge Fields**: Supplementary contact data

### Data Flow

1. **Cart Update** → Store locally → Set abandonment timer
2. **Timer Expires** → Send to backend → Create/update Mailchimp cart
3. **Checkout Started** → Update cart status → Extend timer
4. **Purchase Completed** → Delete Mailchimp cart → Cancel automation
5. **Email Sent** → User clicks recovery link → Pre-populate cart

## Mailchimp Strategy

### Recommended Approach: E-commerce API

**Why E-commerce API?**
- Native abandoned cart support
- Structured product/cart schema
- Built-in automation triggers
- Product recommendations
- Revenue tracking

**API Endpoints Used:**
- `POST /ecommerce/stores/{store_id}/carts` - Create cart
- `PATCH /ecommerce/stores/{store_id}/carts/{cart_id}` - Update cart
- `DELETE /ecommerce/stores/{store_id}/carts/{cart_id}` - Remove on purchase
- `POST /lists/{list_id}/members` - Add/update contact

### Alternative: Events API

Use for supplementary tracking:
- `POST /lists/{list_id}/members/{subscriber_hash}/events`
- Track: `viewed_product`, `added_to_cart`, `started_checkout`

## Email Capture Strategy

### Progressive Capture

1. **Anonymous tracking** → localStorage with session ID
2. **Email popup** → Trigger after 30s or before exit intent
3. **Checkout form** → Capture email field on blur
4. **Associate data** → Link session to email once captured

### Implementation Priority

1. Checkout email field (highest conversion)
2. Newsletter popup with incentive
3. Exit intent modal
4. Guest checkout option

## Security Considerations

### Never Expose

- ❌ Mailchimp API keys
- ❌ Store credentials
- ❌ Backend endpoints without validation

### Always Implement

- ✅ HTTPS only
- ✅ CORS restrictions
- ✅ Rate limiting
- ✅ Input validation
- ✅ API key rotation schedule
- ✅ Request signing (optional but recommended)
- ✅ Environment variables for secrets

## Edge Cases Handled

1. **Multiple tabs** → Use localStorage events to sync
2. **Returning users** → Check for existing carts, merge or replace
3. **Email change** → Update all records, don't duplicate
4. **Rapid cart changes** → Debounce API calls
5. **Network failures** → Retry with exponential backoff
6. **Purchase after email sent** → Delete cart, suppress future emails
7. **Browser privacy mode** → Fallback to session storage
8. **Cart recovery conflicts** → Timestamp-based resolution

## Scalability Recommendations

### Serverless vs Traditional Backend

**Serverless (Recommended)**
- **Pros**: Auto-scaling, pay-per-use, no server maintenance
- **Cons**: Cold starts, vendor lock-in
- **Best for**: Variable traffic, rapid scaling needs

**Traditional Backend**
- **Pros**: Predictable latency, full control, cheaper at high volume
- **Cons**: Requires maintenance, manual scaling
- **Best for**: Consistent high traffic, existing infrastructure

### Recommended: Hybrid Approach

```
Light traffic → Cloudflare Workers (edge computing)
Medium traffic → AWS Lambda + API Gateway
High traffic → Dedicated Node.js server + Redis cache
WordPress → WP REST API + Transients cache
```

## Implementation Files

- `cart-tracker.js` - Frontend event listener and state management
- `node-backend.js` - Node.js/Express backend
- `wordpress-backend.php` - WordPress REST API handler
- `mailchimp-client.js` - Mailchimp API integration
- `config.example.js` - Configuration template

## Setup Instructions

### 1. Mailchimp Setup

1. Create a Store in E-commerce settings
2. Note your Store ID
3. Generate API key (Account → Extras → API Keys)
4. Create an Abandoned Cart automation
5. Set automation trigger: "Cart is abandoned for X hours"

### 2. Backend Setup

**Node.js:**
```bash
npm install express cors dotenv @mailchimp/mailchimp_marketing
```

**WordPress:**
Install as plugin or add to theme's functions.php

### 3. Frontend Integration

Add to your storefront:
```html
<script src="cart-tracker.js"></script>
<script>
  window.CartTracker.init({
    backendUrl: 'https://your-api.com/abandoned-cart',
    abandonmentDelay: 30, // minutes
    storeId: 'your-store-id'
  });
</script>
```

### 4. Environment Variables

```env
MAILCHIMP_API_KEY=your_api_key_here
MAILCHIMP_SERVER_PREFIX=us1
MAILCHIMP_LIST_ID=your_list_id
MAILCHIMP_STORE_ID=your_store_id
BACKEND_SECRET=random_string_for_validation
```

## Monitoring & Analytics

Track these metrics:
- Cart abandonment rate
- Email open rate
- Recovery conversion rate
- Average cart value recovered
- Time to abandonment
- Most abandoned products

## Next Steps

1. Review code examples in each file
2. Test with Mailchimp sandbox
3. Implement email capture strategy
4. Design cart recovery landing page
5. Set up monitoring and alerts
6. A/B test abandonment timing
7. Optimize email content and timing
