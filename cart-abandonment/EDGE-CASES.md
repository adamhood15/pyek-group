# Edge Cases & Troubleshooting

## Common Edge Cases

### 1. Multiple Browser Tabs

**Problem:** User has cart open in multiple tabs, events fire multiple times.

**Solution:**
```javascript
// Use localStorage events to sync across tabs
window.addEventListener('storage', (event) => {
  if (event.key === 'paydia_cart_state') {
    // Another tab updated the cart
    CartTracker.loadState();

    // Cancel our timer, other tab will handle it
    if (CartTracker.state.abandonmentTimer) {
      CartTracker.clearAbandonmentTimer();
    }
  }
});

// Designate one tab as "leader" using Leader Election
let isLeader = false;

function electLeader() {
  const leaderId = sessionStorage.getItem('cart_leader');
  const myId = sessionStorage.getItem('paydia_session_id');

  if (!leaderId || leaderId === myId) {
    sessionStorage.setItem('cart_leader', myId);
    isLeader = true;
  }
}

// Only leader sends abandonment notifications
if (isLeader) {
  CartTracker.resetAbandonmentTimer();
}
```

### 2. Private/Incognito Mode

**Problem:** localStorage may not be available or cleared immediately.

**Solution:**
```javascript
function getStorage() {
  try {
    // Test localStorage availability
    localStorage.setItem('test', 'test');
    localStorage.removeItem('test');
    return localStorage;
  } catch (e) {
    // Fallback to sessionStorage
    console.warn('localStorage unavailable, using sessionStorage');
    return sessionStorage;
  }
}

const storage = getStorage();
```

### 3. Ad Blockers

**Problem:** Ad blockers may block tracking scripts or API requests.

**Solution:**
```javascript
// Use first-party domain for API
// Instead of: https://external-api.com/track
// Use: https://yourdomain.com/api/cart-track

// Retry with exponential backoff
async function robustFetch(url, options, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.ok) return response;
    } catch (error) {
      if (i === retries - 1) throw error;
      await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
    }
  }
}
```

### 4. Email Changes

**Problem:** User changes email during session (e.g., logs in with different account).

**Solution:**
```javascript
handleEmailCaptured(newEmail) {
  const previousEmail = this.state.email;

  if (previousEmail && previousEmail !== newEmail) {
    // Email changed! Handle carefully

    // 1. Delete old cart in Mailchimp
    this.sendToBackend('email_changed', {
      oldEmail: previousEmail,
      newEmail: newEmail
    });

    // 2. Update state
    this.state.email = newEmail;

    // 3. Create new cart with new email
    if (this.state.cart && this.state.cart.items.length > 0) {
      this.sendToBackend('cart_abandoned');
    }
  }
}
```

**Backend handling:**
```javascript
case 'email_changed':
  // Delete cart for old email
  await deleteCart(req.body.oldEmail, sessionId);

  // Create cart for new email
  await createOrUpdateContact(req.body.newEmail);
  await createOrUpdateCart(req.body.newEmail, cart, sessionId, cartUrl);
  break;
```

### 5. Rapid Cart Updates

**Problem:** User rapidly adds/removes items, causing API flood.

**Solution:**
```javascript
// Debounce cart updates
let cartUpdateTimeout = null;

handleCartUpdated(cartData) {
  this.state.cart = this.normalizeCartData(cartData);
  this.saveState();

  // Clear existing timeout
  clearTimeout(cartUpdateTimeout);

  // Set new timeout
  cartUpdateTimeout = setTimeout(() => {
    // Only send to backend if email is captured
    if (this.state.email && this.state.cart.items.length > 0) {
      this.sendToBackend('cart_updated');
    }
  }, 2000); // Wait 2 seconds after last change
}
```

### 6. Purchase After Abandonment Email Sent

**Problem:** User purchases after email is already sent.

**Solution:**
```javascript
// Backend: Always delete cart on purchase
case 'purchase_completed':
  // This will suppress future emails from the automation
  await deleteCart(email, sessionId);

  // Track purchase event to prevent re-sending
  await trackEvent(email, 'purchase_completed', {
    order_id: orderId,
    recovered: checkIfRecovered(email) // Check if clicked email link
  });
  break;

// In Mailchimp automation:
// Set condition: "Only send if cart still exists"
```

### 7. Back Button Navigation

**Problem:** User goes back after checkout, cart state is stale.

**Solution:**
```javascript
// Detect page navigation
window.addEventListener('pageshow', (event) => {
  if (event.persisted) {
    // Page loaded from cache (back button)
    // Reload cart state
    CartTracker.loadState();

    // Re-sync with Paydia
    triggerCartRefresh();
  }
});

function triggerCartRefresh() {
  // Dispatch event to force Paydia to send current cart state
  const event = new CustomEvent('paydia:request_cart_update');
  window.dispatchEvent(event);
}
```

### 8. Network Failures

**Problem:** API request fails due to network issues.

**Solution:**
```javascript
// Queue failed requests in localStorage
class RequestQueue {
  constructor() {
    this.queueKey = 'failed_requests_queue';
  }

  add(request) {
    const queue = this.getQueue();
    queue.push({
      ...request,
      timestamp: Date.now(),
      retries: 0
    });
    localStorage.setItem(this.queueKey, JSON.stringify(queue));
  }

  getQueue() {
    try {
      return JSON.parse(localStorage.getItem(this.queueKey) || '[]');
    } catch {
      return [];
    }
  }

  async processQueue() {
    const queue = this.getQueue();

    if (queue.length === 0) return;

    const processed = [];

    for (const request of queue) {
      try {
        await fetch(request.url, request.options);
        processed.push(request);
      } catch (error) {
        request.retries++;

        if (request.retries >= 5) {
          // Give up after 5 retries
          processed.push(request);
        }
      }
    }

    // Remove processed requests
    const remaining = queue.filter(r => !processed.includes(r));
    localStorage.setItem(this.queueKey, JSON.stringify(remaining));
  }
}

// Process queue when online
window.addEventListener('online', () => {
  requestQueue.processQueue();
});
```

### 9. Session Expiry

**Problem:** User session expires, but cart still exists.

**Solution:**
```javascript
// Store session expiry time
function checkSessionValidity() {
  const sessionExpiry = sessionStorage.getItem('session_expiry');

  if (sessionExpiry && Date.now() > parseInt(sessionExpiry)) {
    // Session expired, generate new session ID
    CartTracker.state.sessionId = CartTracker.generateSessionId();
    sessionStorage.setItem(CartTracker.config.sessionKey, CartTracker.state.sessionId);

    // Set new expiry (24 hours)
    const newExpiry = Date.now() + (24 * 60 * 60 * 1000);
    sessionStorage.setItem('session_expiry', newExpiry.toString());
  }
}
```

### 10. Duplicate Carts

**Problem:** User creates multiple carts with same email.

**Solution:**
```javascript
// Use deterministic cart ID based on session + email
function generateCartId(sessionId, email) {
  // This ensures same session + email = same cart ID
  return crypto
    .createHash('md5')
    .update(`${sessionId}_${email}`)
    .digest('hex');
}

// Mailchimp will update existing cart instead of creating new one
```

### 11. Bot Traffic

**Problem:** Bots trigger cart events, wasting resources.

**Solution:**
```javascript
// Simple bot detection
function isLikelyBot() {
  const userAgent = navigator.userAgent.toLowerCase();
  const botPatterns = [
    'bot', 'crawler', 'spider', 'scraper',
    'headless', 'phantom', 'selenium'
  ];

  return botPatterns.some(pattern => userAgent.includes(pattern));
}

// Don't track bots
if (!isLikelyBot()) {
  CartTracker.init(config);
}

// Backend: Additional validation
function validateUserAgent(req) {
  const ua = req.headers['user-agent'];

  if (!ua || ua.length < 10) {
    return false; // Suspicious
  }

  // Check for common bot patterns
  const botPatterns = /bot|crawler|spider|scraper/i;
  if (botPatterns.test(ua)) {
    return false;
  }

  return true;
}
```

### 12. Cart Recovery Link Conflicts

**Problem:** User clicks recovery link but has different items in cart now.

**Solution:**
```javascript
// Detect recovery link parameter
const urlParams = new URLSearchParams(window.location.search);
const isRecovery = urlParams.has('recover_cart');
const recoveryCartId = urlParams.get('cart_id');

if (isRecovery) {
  // Compare with current cart
  const currentCart = CartTracker.getCart();

  if (currentCart && currentCart.items.length > 0) {
    // Ask user which cart to keep
    if (confirm('You have items in your cart. Replace with saved cart?')) {
      // Load recovered cart
      loadRecoveredCart(recoveryCartId);
    }
  } else {
    // No current cart, load recovered cart
    loadRecoveredCart(recoveryCartId);
  }
}
```

### 13. Currency Mismatches

**Problem:** User changes currency/region, prices become invalid.

**Solution:**
```javascript
// Track currency with cart
handleCartUpdated(cartData) {
  const newCurrency = cartData.currency;
  const oldCurrency = this.state.cart?.currency;

  if (oldCurrency && newCurrency !== oldCurrency) {
    // Currency changed - treat as new cart
    this.state.cart = this.normalizeCartData(cartData);

    // Update in Mailchimp
    if (this.state.email) {
      this.sendToBackend('cart_updated');
    }
  }
}
```

### 14. Product Out of Stock

**Problem:** Product becomes unavailable after cart abandonment.

**Solution:**
```javascript
// Backend: Check availability before sending email
async function validateCartAvailability(cart) {
  const availabilityChecks = cart.items.map(async (item) => {
    const available = await checkProductAvailability(item.productId);
    return { ...item, available };
  });

  const results = await Promise.all(availabilityChecks);

  // Filter to only available items
  return results.filter(item => item.available);
}

// In Mailchimp automation:
// Add merge field: |AVAILABLE_ITEMS|
// Show conditional content if items unavailable
```

### 15. Cross-Device Continuity

**Problem:** User starts on mobile, continues on desktop.

**Solution:**
```javascript
// Use email as primary identifier
// When email is captured, associate with both sessions

// Backend: Track multiple sessions per email
async function associateSession(email, sessionId) {
  await redis.sadd(`user_sessions:${email}`, sessionId);

  // Set expiry
  await redis.expire(`user_sessions:${email}`, 7 * 24 * 60 * 60); // 7 days
}

// When user logs in on new device
async function syncCartAcrossDevices(email) {
  const sessions = await redis.smembers(`user_sessions:${email}`);

  // Get most recent cart
  const carts = await Promise.all(
    sessions.map(sid => getCartBySession(sid))
  );

  const latestCart = carts
    .filter(c => c !== null)
    .sort((a, b) => b.timestamp - a.timestamp)[0];

  return latestCart;
}
```

## Troubleshooting Guide

### Issue: Emails Not Sending

**Diagnosis:**
```javascript
// Check Mailchimp cart was created
const cartId = generateCartId(sessionId, email);

const cart = await mailchimp.ecommerce.getStoreCart(
  MAILCHIMP_STORE_ID,
  cartId
);

console.log('Cart exists:', cart);
```

**Common Causes:**
1. Automation not enabled in Mailchimp
2. Cart deleted before email sent
3. Trigger conditions not met
4. Contact opted out

**Fix:**
- Verify automation is active
- Check Mailchimp automation logs
- Ensure cart exists for required time
- Verify contact subscription status

### Issue: Cart Total Mismatch

**Diagnosis:**
```javascript
// Log calculated vs reported total
const calculated = cart.items.reduce((sum, item) => {
  return sum + (item.price * item.quantity);
}, 0);

console.log('Calculated:', calculated, 'Reported:', cart.total);
```

**Fix:**
```javascript
// Always recalculate on backend
function recalculateTotal(cart) {
  const subtotal = cart.items.reduce((sum, item) => {
    return sum + (parseFloat(item.price) * parseInt(item.quantity));
  }, 0);

  // Add tax, shipping if applicable
  return {
    ...cart,
    subtotal,
    total: subtotal // Or add tax/shipping
  };
}
```

### Issue: Events Not Firing

**Diagnosis:**
```javascript
// Test Paydia event listeners
window.addEventListener('paydia:cart_updated', (e) => {
  console.log('✓ paydia:cart_updated fired', e.detail);
});

// Manually trigger test event
const testCart = { items: [{ name: 'Test', price: 10 }] };
window.dispatchEvent(new CustomEvent('paydia:cart_updated', {
  detail: testCart
}));
```

**Common Causes:**
1. Paydia script not loaded
2. Event listener registered too late
3. Event name typo
4. Paydia version mismatch

**Fix:**
- Ensure cart-tracker.js loads after Paydia
- Use `DOMContentLoaded` or defer attribute
- Verify event names with Paydia documentation

### Issue: High API Error Rate

**Diagnosis:**
```bash
# Check Mailchimp API logs
curl -X GET \
  "https://us1.api.mailchimp.com/3.0/reports" \
  -u "anystring:$MAILCHIMP_API_KEY"
```

**Common Causes:**
1. Rate limiting
2. Invalid store configuration
3. Product doesn't exist
4. Malformed requests

**Fix:**
```javascript
// Add detailed error logging
try {
  await mailchimpRequest(endpoint, data);
} catch (error) {
  console.error('Mailchimp Error:', {
    endpoint,
    status: error.status,
    message: error.message,
    body: error.response?.body
  });

  // Alert if repeated errors
  if (errorCount++ > 10) {
    notifyAdmins('High Mailchimp error rate');
  }
}
```

### Issue: Memory Leaks

**Diagnosis:**
```javascript
// Monitor localStorage size
function checkStorageSize() {
  let total = 0;
  for (let key in localStorage) {
    if (localStorage.hasOwnProperty(key)) {
      total += localStorage[key].length + key.length;
    }
  }
  console.log('localStorage size:', (total / 1024).toFixed(2), 'KB');
}
```

**Fix:**
```javascript
// Implement cleanup
function cleanupOldData() {
  const keys = Object.keys(localStorage);

  keys.forEach(key => {
    if (key.startsWith('paydia_')) {
      try {
        const data = JSON.parse(localStorage.getItem(key));

        // Remove if older than 7 days
        if (data.timestamp < Date.now() - (7 * 24 * 60 * 60 * 1000)) {
          localStorage.removeItem(key);
        }
      } catch (e) {
        // Invalid data, remove it
        localStorage.removeItem(key);
      }
    }
  });
}

// Run on init
cleanupOldData();
```

### Issue: CORS Errors

**Diagnosis:**
```javascript
// Check CORS headers
fetch(backendUrl, { method: 'OPTIONS' })
  .then(r => {
    console.log('CORS headers:', {
      origin: r.headers.get('Access-Control-Allow-Origin'),
      methods: r.headers.get('Access-Control-Allow-Methods'),
      headers: r.headers.get('Access-Control-Allow-Headers')
    });
  });
```

**Fix:**
```javascript
// Backend: Ensure proper CORS
app.use(cors({
  origin: process.env.ALLOWED_ORIGINS.split(','),
  methods: ['POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type']
}));

// Handle preflight
app.options('*', cors());
```

## Testing Checklist

- [ ] Normal cart flow works
- [ ] Multiple tabs sync correctly
- [ ] Private mode works (with degraded features)
- [ ] Email change handled properly
- [ ] Rapid updates debounced
- [ ] Purchase cancels abandonment
- [ ] Back button navigation works
- [ ] Network failure recovery
- [ ] Bot traffic filtered
- [ ] Recovery link works
- [ ] Currency changes handled
- [ ] Cross-device sync (if implemented)
- [ ] Mailchimp automation triggers
- [ ] Error logging works
- [ ] Cleanup runs properly
