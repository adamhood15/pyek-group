# Security Considerations

## Critical Security Rules

### Never Expose API Keys Client-Side

❌ **NEVER DO THIS:**
```javascript
// DON'T expose Mailchimp API key in frontend code
const MAILCHIMP_API_KEY = 'abc123-us1'; // NEVER!

fetch('https://us1.api.mailchimp.com/3.0/lists', {
  headers: {
    'Authorization': 'Basic ' + btoa('anystring:' + MAILCHIMP_API_KEY) // NEVER!
  }
});
```

✅ **ALWAYS DO THIS:**
```javascript
// Frontend only sends to your backend
fetch('https://your-api.com/abandoned-cart', {
  method: 'POST',
  body: JSON.stringify({ email, cart })
});

// Backend handles Mailchimp API (Node.js example)
// API key is in environment variable
mailchimp.setConfig({
  apiKey: process.env.MAILCHIMP_API_KEY // Secure!
});
```

## API Key Management

### Best Practices

1. **Environment Variables**
   ```bash
   # .env file (never commit to git)
   MAILCHIMP_API_KEY=your_key_here-us1
   MAILCHIMP_LIST_ID=abc123
   MAILCHIMP_STORE_ID=store_123
   ```

2. **Git Ignore**
   ```
   # .gitignore
   .env
   .env.local
   .env.production
   config/secrets.json
   ```

3. **Key Rotation Schedule**
   - Rotate API keys every 90 days
   - Immediately rotate if compromised
   - Use Mailchimp's key management dashboard

4. **Separate Keys by Environment**
   ```bash
   # Development
   MAILCHIMP_API_KEY_DEV=dev_key-us1

   # Staging
   MAILCHIMP_API_KEY_STAGING=staging_key-us1

   # Production
   MAILCHIMP_API_KEY_PROD=prod_key-us1
   ```

## Input Validation

### Backend Validation (Required)

Always validate on the backend, never trust client input:

```javascript
function validateRequest(req, res, next) {
  const { email, cart, sessionId } = req.body;

  // Validate email format
  if (!isValidEmail(email)) {
    return res.status(400).json({ error: 'Invalid email' });
  }

  // Validate session ID format
  if (!sessionId || sessionId.length > 100) {
    return res.status(400).json({ error: 'Invalid session ID' });
  }

  // Validate cart structure
  if (!cart || !Array.isArray(cart.items)) {
    return res.status(400).json({ error: 'Invalid cart structure' });
  }

  // Validate cart items
  for (const item of cart.items) {
    if (!item.productId || typeof item.price !== 'number' || item.price < 0) {
      return res.status(400).json({ error: 'Invalid cart item' });
    }

    // Prevent integer overflow
    if (item.quantity > 10000) {
      return res.status(400).json({ error: 'Quantity too large' });
    }
  }

  // Validate cart total (prevent manipulation)
  const calculatedTotal = cart.items.reduce((sum, item) => {
    return sum + (item.price * item.quantity);
  }, 0);

  if (Math.abs(calculatedTotal - cart.total) > 0.01) {
    return res.status(400).json({ error: 'Cart total mismatch' });
  }

  next();
}
```

### Sanitize User Input

```javascript
function sanitizeString(str, maxLength = 255) {
  if (typeof str !== 'string') return '';

  return str
    .trim()
    .slice(0, maxLength)
    .replace(/[<>]/g, ''); // Remove potential HTML tags
}

function sanitizeCart(cart) {
  return {
    ...cart,
    items: cart.items.map(item => ({
      productId: sanitizeString(item.productId, 100),
      name: sanitizeString(item.name, 255),
      description: sanitizeString(item.description, 1000),
      quantity: Math.max(1, Math.min(10000, parseInt(item.quantity) || 1)),
      price: Math.max(0, parseFloat(item.price) || 0)
    }))
  };
}
```

## Rate Limiting

### Implementation Examples

#### Express (Node.js)
```javascript
const rateLimit = require('express-rate-limit');

const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // Limit each IP to 100 requests per windowMs
  message: 'Too many requests, please try again later',
  standardHeaders: true,
  legacyHeaders: false,
});

app.use('/abandoned-cart', limiter);
```

#### WordPress
```php
function check_rate_limit($identifier) {
    $transient_key = 'rate_limit_' . md5($identifier);
    $count = get_transient($transient_key);

    if ($count === false) {
        set_transient($transient_key, 1, 15 * MINUTE_IN_SECONDS);
        return true;
    }

    if ($count >= 100) {
        return false;
    }

    set_transient($transient_key, $count + 1, 15 * MINUTE_IN_SECONDS);
    return true;
}
```

#### Redis (Production)
```javascript
const Redis = require('ioredis');
const redis = new Redis(process.env.REDIS_URL);

async function checkRateLimit(identifier) {
  const key = `rate_limit:${identifier}`;
  const count = await redis.incr(key);

  if (count === 1) {
    await redis.expire(key, 900); // 15 minutes
  }

  return count <= 100;
}
```

## CORS Configuration

### Strict CORS (Production)

```javascript
const cors = require('cors');

app.use(cors({
  origin: (origin, callback) => {
    const allowedOrigins = [
      'https://yourdomain.com',
      'https://www.yourdomain.com'
    ];

    if (!origin || allowedOrigins.includes(origin)) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  },
  methods: ['POST'],
  credentials: false,
  maxAge: 86400 // 24 hours
}));
```

### Development CORS

```javascript
// Only use in development!
if (process.env.NODE_ENV === 'development') {
  app.use(cors({ origin: '*' }));
}
```

## Request Signing (Advanced)

For critical applications, implement HMAC request signing:

### Frontend
```javascript
async function signRequest(payload, secret) {
  const encoder = new TextEncoder();
  const data = encoder.encode(JSON.stringify(payload));

  const key = await crypto.subtle.importKey(
    'raw',
    encoder.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );

  const signature = await crypto.subtle.sign('HMAC', key, data);

  return Array.from(new Uint8Array(signature))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');
}

// Send with request
const payload = { email, cart };
const signature = await signRequest(payload, 'shared-secret');

fetch(url, {
  headers: {
    'X-Signature': signature
  },
  body: JSON.stringify(payload)
});
```

### Backend Verification
```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const hmac = crypto.createHmac('sha256', secret);
  hmac.update(JSON.stringify(payload));
  const calculated = hmac.digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(calculated)
  );
}

// Middleware
function requireSignature(req, res, next) {
  const signature = req.headers['x-signature'];

  if (!signature || !verifySignature(req.body, signature, process.env.SIGNING_SECRET)) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  next();
}
```

## HTTPS Only

### Force HTTPS in Production

```javascript
// Express middleware
function requireHTTPS(req, res, next) {
  if (process.env.NODE_ENV === 'production' && !req.secure) {
    return res.redirect('https://' + req.headers.host + req.url);
  }
  next();
}

app.use(requireHTTPS);
```

### Strict Transport Security
```javascript
app.use((req, res, next) => {
  res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  next();
});
```

## Data Protection

### PII Handling

```javascript
// Never log sensitive data
function safeLog(data) {
  const safe = { ...data };

  // Redact email
  if (safe.email) {
    safe.email = safe.email.replace(/(.{3}).*(@.*)/, '$1***$2');
  }

  // Redact session ID
  if (safe.sessionId) {
    safe.sessionId = safe.sessionId.slice(0, 8) + '...';
  }

  console.log(safe);
}
```

### Data Retention

```javascript
// Automatically clean old cart data
async function cleanupOldCarts() {
  const cutoffDate = Date.now() - (7 * 24 * 60 * 60 * 1000); // 7 days

  // Remove from localStorage if older than 7 days
  const state = JSON.parse(localStorage.getItem('cart_state'));

  if (state && state.lastActivity < cutoffDate) {
    localStorage.removeItem('cart_state');
  }
}

// Run on page load
cleanupOldCarts();
```

## SQL Injection Prevention

If storing cart data in database:

```javascript
// ❌ NEVER: String concatenation
const query = `SELECT * FROM carts WHERE email = '${email}'`; // VULNERABLE!

// ✅ ALWAYS: Parameterized queries
const query = 'SELECT * FROM carts WHERE email = ?';
db.execute(query, [email]);
```

## XSS Prevention

```javascript
// Sanitize before displaying user data
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, m => map[m]);
}

// Use when displaying product names, descriptions, etc.
element.textContent = escapeHtml(productName);
```

## Security Headers

```javascript
const helmet = require('helmet');

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", 'data:', 'https:'],
      connectSrc: ["'self'", 'https://your-api.com']
    }
  },
  hsts: {
    maxAge: 31536000,
    includeSubDomains: true,
    preload: true
  }
}));
```

## Monitoring & Alerts

### Log Security Events

```javascript
function logSecurityEvent(type, details) {
  const event = {
    timestamp: new Date().toISOString(),
    type,
    details,
    ip: req.ip,
    userAgent: req.headers['user-agent']
  };

  // Send to logging service (e.g., Datadog, Sentry)
  console.error('[SECURITY]', JSON.stringify(event));

  // Alert if critical
  if (type === 'rate_limit_exceeded' || type === 'invalid_signature') {
    // Send alert notification
  }
}
```

### Failed Request Monitoring

```javascript
let failedAttempts = 0;

function trackFailedAttempt() {
  failedAttempts++;

  if (failedAttempts > 100) {
    // Alert: Possible attack
    notifySecurityTeam('High number of failed requests');
  }
}
```

## Compliance

### GDPR Considerations

```javascript
// Provide data deletion endpoint
app.delete('/abandoned-cart/user/:email', async (req, res) => {
  const { email } = req.params;

  // Verify user owns this email (implement auth)
  if (!verifyOwnership(req, email)) {
    return res.status(403).json({ error: 'Forbidden' });
  }

  // Delete from Mailchimp
  await deleteMailchimpData(email);

  // Delete from local storage (user must do client-side)

  res.json({ success: true });
});
```

### Data Processing Agreement

Ensure your Mailchimp account has:
- Data Processing Agreement (DPA) in place
- Appropriate data retention settings
- GDPR compliance enabled

## Security Checklist

- [ ] API keys in environment variables, never in code
- [ ] API keys never exposed client-side
- [ ] All requests validated on backend
- [ ] Rate limiting implemented
- [ ] CORS properly configured
- [ ] HTTPS enforced in production
- [ ] Security headers configured
- [ ] Input sanitization implemented
- [ ] SQL injection prevention (if using database)
- [ ] XSS prevention in place
- [ ] Logging configured (without sensitive data)
- [ ] Error messages don't leak system info
- [ ] Request signing (for high-security needs)
- [ ] Data retention policy implemented
- [ ] GDPR compliance measures
- [ ] Regular security audits scheduled
- [ ] Dependency updates automated
