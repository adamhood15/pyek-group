# Testing Guide

Comprehensive testing guide for cart abandonment tracking system.

## Testing Strategy

### Testing Pyramid

```
        /\
       /  \      E2E Tests (5%)
      /____\     - Full user flows
     /      \    - Email delivery
    /________\   Integration Tests (25%)
   /          \  - API endpoints
  /____________\ - Mailchimp integration
 /______________\
      Unit Tests (70%)
      - Validation functions
      - Data transformations
      - Utility functions
```

## Unit Tests

### Backend Validation Tests

```javascript
// tests/validation.test.js
const { validateRequest, isValidEmail, generateCartId } = require('./utils');

describe('Email Validation', () => {
  test('validates correct email', () => {
    expect(isValidEmail('user@example.com')).toBe(true);
  });

  test('rejects invalid email', () => {
    expect(isValidEmail('invalid-email')).toBe(false);
    expect(isValidEmail('user@')).toBe(false);
    expect(isValidEmail('@example.com')).toBe(false);
  });

  test('rejects empty email', () => {
    expect(isValidEmail('')).toBe(false);
    expect(isValidEmail(null)).toBe(false);
  });
});

describe('Cart ID Generation', () => {
  test('generates consistent ID for same inputs', () => {
    const id1 = generateCartId('session_123', 'user@example.com');
    const id2 = generateCartId('session_123', 'user@example.com');
    expect(id1).toBe(id2);
  });

  test('generates different ID for different inputs', () => {
    const id1 = generateCartId('session_123', 'user1@example.com');
    const id2 = generateCartId('session_123', 'user2@example.com');
    expect(id1).not.toBe(id2);
  });
});

describe('Request Validation', () => {
  test('accepts valid cart_abandoned request', () => {
    const request = {
      eventType: 'cart_abandoned',
      email: 'user@example.com',
      sessionId: 'session_123',
      cart: {
        items: [{ productId: '1', price: 10, quantity: 1 }],
        total: 10
      }
    };

    expect(validateRequest(request)).toEqual({ valid: true });
  });

  test('rejects request without email', () => {
    const request = {
      eventType: 'cart_abandoned',
      sessionId: 'session_123',
      cart: { items: [], total: 0 }
    };

    expect(validateRequest(request).valid).toBe(false);
  });

  test('rejects request with empty cart', () => {
    const request = {
      eventType: 'cart_abandoned',
      email: 'user@example.com',
      sessionId: 'session_123',
      cart: { items: [], total: 0 }
    };

    expect(validateRequest(request).valid).toBe(false);
  });
});
```

### Frontend Tests

```javascript
// tests/cart-tracker.test.js
/**
 * @jest-environment jsdom
 */

describe('CartTracker', () => {
  beforeEach(() => {
    // Clear localStorage
    localStorage.clear();
    sessionStorage.clear();

    // Load CartTracker
    require('../cart-tracker.js');

    // Initialize
    window.CartTracker.init({
      backendUrl: 'http://localhost:3000/test',
      abandonmentDelay: 0.1, // 0.1 minutes for testing
      storeId: 'test-store',
      debug: true
    });
  });

  test('initializes with config', () => {
    expect(window.CartTracker.config.storeId).toBe('test-store');
  });

  test('generates session ID', () => {
    const sessionId = window.CartTracker.state.sessionId;
    expect(sessionId).toBeTruthy();
    expect(sessionId).toMatch(/^session_/);
  });

  test('validates email correctly', () => {
    expect(window.CartTracker.isValidEmail('user@example.com')).toBe(true);
    expect(window.CartTracker.isValidEmail('invalid')).toBe(false);
  });

  test('handles cart update event', () => {
    const cartData = {
      items: [
        { productId: '1', name: 'Product 1', price: 10, quantity: 1 }
      ],
      total: 10
    };

    window.dispatchEvent(new CustomEvent('paydia:cart_updated', {
      detail: cartData
    }));

    expect(window.CartTracker.state.cart).toBeTruthy();
    expect(window.CartTracker.state.cart.items.length).toBe(1);
  });

  test('saves state to localStorage', () => {
    const cartData = {
      items: [{ productId: '1', price: 10, quantity: 1 }],
      total: 10
    };

    window.CartTracker.handleCartUpdated(cartData);

    const saved = localStorage.getItem('paydia_cart_state');
    expect(saved).toBeTruthy();

    const parsed = JSON.parse(saved);
    expect(parsed.cart).toBeTruthy();
  });

  test('loads state from localStorage', () => {
    const state = {
      cart: { items: [{ productId: '1', price: 10, quantity: 1 }], total: 10 },
      email: 'user@example.com',
      sessionId: 'test_session'
    };

    localStorage.setItem('paydia_cart_state', JSON.stringify(state));

    window.CartTracker.loadState();

    expect(window.CartTracker.state.cart).toBeTruthy();
    expect(window.CartTracker.state.email).toBe('user@example.com');
  });

  test('captures email correctly', () => {
    window.CartTracker.setEmail('user@example.com');
    expect(window.CartTracker.state.email).toBe('user@example.com');
  });

  test('clears state after purchase', () => {
    window.CartTracker.state.cart = { items: [], total: 0 };

    window.dispatchEvent(new CustomEvent('paydia:purchase_completed', {
      detail: { orderId: '123' }
    }));

    expect(window.CartTracker.state.purchaseCompleted).toBe(true);
  });
});
```

## Integration Tests

### API Endpoint Tests

```javascript
// tests/api.test.js
const request = require('supertest');
const app = require('../node-backend');

describe('POST /abandoned-cart', () => {
  test('accepts valid cart_abandoned request', async () => {
    const response = await request(app)
      .post('/abandoned-cart')
      .send({
        eventType: 'cart_abandoned',
        email: 'test@example.com',
        sessionId: 'test_session_123',
        cart: {
          items: [
            {
              productId: '1',
              name: 'Test Product',
              price: 10,
              quantity: 1
            }
          ],
          total: 10
        }
      });

    expect(response.status).toBe(200);
    expect(response.body.success).toBe(true);
  });

  test('rejects request without email', async () => {
    const response = await request(app)
      .post('/abandoned-cart')
      .send({
        eventType: 'cart_abandoned',
        sessionId: 'test_session_123',
        cart: { items: [], total: 0 }
      });

    expect(response.status).toBe(400);
  });

  test('rejects request with invalid email', async () => {
    const response = await request(app)
      .post('/abandoned-cart')
      .send({
        eventType: 'cart_abandoned',
        email: 'invalid-email',
        sessionId: 'test_session_123',
        cart: { items: [], total: 0 }
      });

    expect(response.status).toBe(400);
  });

  test('handles purchase_completed event', async () => {
    const response = await request(app)
      .post('/abandoned-cart')
      .send({
        eventType: 'purchase_completed',
        email: 'test@example.com',
        sessionId: 'test_session_123',
        orderId: 'order_123',
        orderTotal: 100
      });

    expect(response.status).toBe(200);
    expect(response.body.success).toBe(true);
  });

  test('enforces rate limiting', async () => {
    // Send 101 requests rapidly
    const promises = [];
    for (let i = 0; i < 101; i++) {
      promises.push(
        request(app)
          .post('/abandoned-cart')
          .send({
            eventType: 'cart_abandoned',
            email: 'test@example.com',
            sessionId: 'test_session_123',
            cart: { items: [{ productId: '1', price: 10, quantity: 1 }], total: 10 }
          })
      );
    }

    const responses = await Promise.all(promises);
    const rateLimited = responses.filter(r => r.status === 429);

    expect(rateLimited.length).toBeGreaterThan(0);
  });
});

describe('GET /health', () => {
  test('returns ok status', async () => {
    const response = await request(app).get('/health');

    expect(response.status).toBe(200);
    expect(response.body.status).toBe('ok');
  });
});
```

### Mailchimp Integration Tests

```javascript
// tests/mailchimp.test.js
const mailchimp = require('@mailchimp/mailchimp_marketing');

// Use Mailchimp test credentials
mailchimp.setConfig({
  apiKey: process.env.TEST_MAILCHIMP_API_KEY,
  server: process.env.TEST_MAILCHIMP_SERVER
});

describe('Mailchimp Integration', () => {
  const testEmail = `test+${Date.now()}@example.com`;
  const testCartId = `test_cart_${Date.now()}`;

  afterAll(async () => {
    // Cleanup: delete test cart
    try {
      await mailchimp.ecommerce.deleteStoreCart(
        process.env.TEST_MAILCHIMP_STORE_ID,
        testCartId
      );
    } catch (error) {
      // Cart may not exist
    }
  });

  test('creates contact', async () => {
    const response = await mailchimp.lists.addListMember(
      process.env.TEST_MAILCHIMP_LIST_ID,
      {
        email_address: testEmail,
        status: 'subscribed'
      }
    );

    expect(response.email_address).toBe(testEmail);
    expect(response.status).toBe('subscribed');
  });

  test('creates abandoned cart', async () => {
    const subscriberHash = require('crypto')
      .createHash('md5')
      .update(testEmail.toLowerCase())
      .digest('hex');

    const response = await mailchimp.ecommerce.addStoreCart(
      process.env.TEST_MAILCHIMP_STORE_ID,
      {
        id: testCartId,
        customer: {
          id: subscriberHash,
          email_address: testEmail,
          opt_in_status: true
        },
        currency_code: 'USD',
        order_total: 10,
        lines: [
          {
            id: `${testCartId}_line_1`,
            product_id: 'test_product',
            product_variant_id: 'test_variant',
            quantity: 1,
            price: 10
          }
        ]
      }
    );

    expect(response.id).toBe(testCartId);
    expect(response.order_total).toBe(10);
  });

  test('retrieves cart', async () => {
    const response = await mailchimp.ecommerce.getStoreCart(
      process.env.TEST_MAILCHIMP_STORE_ID,
      testCartId
    );

    expect(response.id).toBe(testCartId);
  });

  test('deletes cart', async () => {
    await mailchimp.ecommerce.deleteStoreCart(
      process.env.TEST_MAILCHIMP_STORE_ID,
      testCartId
    );

    // Verify deletion
    try {
      await mailchimp.ecommerce.getStoreCart(
        process.env.TEST_MAILCHIMP_STORE_ID,
        testCartId
      );
      fail('Cart should have been deleted');
    } catch (error) {
      expect(error.status).toBe(404);
    }
  });
});
```

## E2E Tests

### Playwright E2E Tests

```javascript
// tests/e2e/cart-abandonment.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Cart Abandonment Flow', () => {
  test('tracks abandoned cart', async ({ page }) => {
    // Navigate to store
    await page.goto('https://your-store.com');

    // Add item to cart
    await page.click('[data-testid="product-1"]');
    await page.click('[data-testid="add-to-cart"]');

    // Verify cart tracker initialized
    const trackerInitialized = await page.evaluate(() => {
      return window.CartTracker && window.CartTracker.state.cart !== null;
    });
    expect(trackerInitialized).toBe(true);

    // Enter email in checkout
    await page.click('[data-testid="checkout-button"]');
    await page.fill('input[type="email"]', 'test@example.com');
    await page.blur('input[type="email"]');

    // Wait for email capture
    await page.waitForTimeout(1000);

    // Verify email was captured
    const emailCaptured = await page.evaluate(() => {
      return window.CartTracker.state.email === 'test@example.com';
    });
    expect(emailCaptured).toBe(true);

    // Leave page (simulate abandonment)
    // Wait for abandonment timer (set to 1 minute for testing)
    await page.waitForTimeout(61000);

    // Check that API was called
    const requests = await page.evaluate(() => {
      return window._testRequests || [];
    });

    const abandonmentRequest = requests.find(
      r => r.url.includes('/abandoned-cart') && r.body.eventType === 'cart_abandoned'
    );
    expect(abandonmentRequest).toBeTruthy();
  });

  test('cancels abandonment on purchase', async ({ page }) => {
    // Add item to cart
    await page.goto('https://your-store.com');
    await page.click('[data-testid="product-1"]');
    await page.click('[data-testid="add-to-cart"]');

    // Checkout
    await page.click('[data-testid="checkout-button"]');
    await page.fill('input[type="email"]', 'test@example.com');

    // Complete purchase
    await page.fill('input[name="card-number"]', '4242424242424242');
    await page.fill('input[name="card-exp"]', '12/25');
    await page.fill('input[name="card-cvc"]', '123');
    await page.click('[data-testid="complete-purchase"]');

    // Wait for purchase event
    await page.waitForSelector('[data-testid="order-confirmation"]');

    // Verify purchase event was sent
    const requests = await page.evaluate(() => {
      return window._testRequests || [];
    });

    const purchaseRequest = requests.find(
      r => r.url.includes('/abandoned-cart') && r.body.eventType === 'purchase_completed'
    );
    expect(purchaseRequest).toBeTruthy();
  });
});
```

## Manual Testing Checklist

### Functional Tests

- [ ] **Cart Update**
  - Add item to cart
  - Verify cart tracker state updated
  - Check localStorage persisted

- [ ] **Email Capture**
  - Enter email in checkout form
  - Verify email captured in tracker state
  - Check localStorage saved email

- [ ] **Abandonment Timer**
  - Add items to cart
  - Wait for timer duration
  - Verify API request sent
  - Check Mailchimp cart created

- [ ] **Purchase Completion**
  - Complete a purchase
  - Verify cart deleted from Mailchimp
  - Check abandonment email NOT sent

- [ ] **Multiple Tabs**
  - Open cart in 2 tabs
  - Add items in tab 1
  - Verify tab 2 syncs

- [ ] **Cart Recovery**
  - Click recovery link from email
  - Verify cart restored
  - Complete purchase

### Cross-Browser Testing

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

### Edge Case Testing

- [ ] Private/Incognito mode
- [ ] Ad blocker enabled
- [ ] Slow network (3G)
- [ ] Network disconnected
- [ ] Email change mid-session
- [ ] Rapid cart updates
- [ ] Empty cart
- [ ] Very large cart (50+ items)
- [ ] Special characters in product names
- [ ] Currency symbols

## Performance Testing

### Load Testing with Artillery

```yaml
# load-test.yml
config:
  target: 'https://your-api.com'
  phases:
    - duration: 60
      arrivalRate: 10
      name: 'Warm up'
    - duration: 120
      arrivalRate: 50
      name: 'Sustained load'
    - duration: 60
      arrivalRate: 100
      name: 'Peak load'
  variables:
    email:
      - 'user1@example.com'
      - 'user2@example.com'

scenarios:
  - name: 'Cart Abandonment'
    flow:
      - post:
          url: '/abandoned-cart'
          json:
            eventType: 'cart_abandoned'
            email: '{{ email }}'
            sessionId: 'session_{{ $randomNumber() }}'
            cart:
              items:
                - productId: '{{ $randomNumber() }}'
                  price: 10
                  quantity: 1
              total: 10
```

Run:
```bash
npm install -g artillery
artillery run load-test.yml
```

### Performance Benchmarks

Target metrics:
- **Response time:** < 200ms (p95)
- **Throughput:** > 100 req/s
- **Error rate:** < 0.1%
- **Memory usage:** < 512MB
- **CPU usage:** < 70%

## Monitoring Tests

### Health Check Test

```bash
#!/bin/bash
# health-check.sh

ENDPOINT="https://your-api.com/health"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $ENDPOINT)

if [ $RESPONSE -eq 200 ]; then
  echo "✓ Health check passed"
  exit 0
else
  echo "✗ Health check failed (HTTP $RESPONSE)"
  exit 1
fi
```

### Email Delivery Test

```javascript
// tests/email-delivery.test.js
const mailchimp = require('@mailchimp/mailchimp_marketing');

test('abandoned cart email is sent', async () => {
  // Create test cart
  const testEmail = 'test@example.com';
  const cartId = 'test_cart_123';

  await createTestCart(cartId, testEmail);

  // Wait for automation delay (e.g., 1 hour)
  // In testing, configure a 1-minute automation
  await new Promise(resolve => setTimeout(resolve, 65000));

  // Check if email was sent
  const campaigns = await mailchimp.campaigns.list({
    status: 'sent',
    since_send_time: new Date(Date.now() - 120000).toISOString()
  });

  const abandonmentEmail = campaigns.campaigns.find(
    c => c.type === 'automation' && c.recipients.list_id === TEST_LIST_ID
  );

  expect(abandonmentEmail).toBeTruthy();
});
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Test Suite

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm ci

      - name: Run linter
        run: npm run lint

      - name: Run unit tests
        run: npm test

      - name: Run integration tests
        env:
          MAILCHIMP_API_KEY: ${{ secrets.TEST_MAILCHIMP_API_KEY }}
          MAILCHIMP_LIST_ID: ${{ secrets.TEST_MAILCHIMP_LIST_ID }}
          MAILCHIMP_STORE_ID: ${{ secrets.TEST_MAILCHIMP_STORE_ID }}
        run: npm run test:integration

      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

## Test Data Cleanup

```javascript
// tests/cleanup.js
async function cleanupTestData() {
  // Delete test carts
  const carts = await mailchimp.ecommerce.getAllStoreCarts(TEST_STORE_ID);

  for (const cart of carts.carts) {
    if (cart.id.startsWith('test_')) {
      await mailchimp.ecommerce.deleteStoreCart(TEST_STORE_ID, cart.id);
    }
  }

  // Delete test contacts
  const members = await mailchimp.lists.getListMembersInfo(TEST_LIST_ID);

  for (const member of members.members) {
    if (member.email_address.includes('+test')) {
      await mailchimp.lists.deleteListMember(
        TEST_LIST_ID,
        generateSubscriberHash(member.email_address)
      );
    }
  }

  console.log('Test data cleanup complete');
}

// Run after tests
afterAll(cleanupTestData);
```

## Testing Best Practices

1. **Use test mode/sandbox** where available
2. **Clean up test data** after each run
3. **Use unique identifiers** for test data (timestamps, UUIDs)
4. **Mock external APIs** in unit tests
5. **Test real APIs** in integration tests
6. **Automate tests** in CI/CD pipeline
7. **Monitor test coverage** (aim for >80%)
8. **Test edge cases** thoroughly
9. **Performance test** before production
10. **Document test scenarios**
