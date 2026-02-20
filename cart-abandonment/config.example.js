/**
 * Configuration Examples
 * Copy and customize for your environment
 */

// ============================================
// FRONTEND CONFIGURATION
// ============================================

// Option 1: Inline configuration (simple sites)
window.CART_TRACKER_CONFIG = {
  backendUrl: 'https://your-api.com/abandoned-cart',
  abandonmentDelay: 30, // minutes
  storeId: 'your-mailchimp-store-id',
  debug: false // Set to true for development
};

// Option 2: Manual initialization (more control)
document.addEventListener('DOMContentLoaded', function() {
  window.CartTracker.init({
    backendUrl: 'https://your-api.com/abandoned-cart',
    abandonmentDelay: 30,
    storeId: 'your-mailchimp-store-id',
    debug: process.env.NODE_ENV === 'development'
  });
});

// Option 3: WordPress integration
window.CartTracker.init({
  backendUrl: '<?php echo rest_url("cart-abandonment/v1/track"); ?>',
  abandonmentDelay: 30,
  storeId: 'your-mailchimp-store-id',
  debug: false
});

// ============================================
// BACKEND CONFIGURATION - Node.js
// ============================================

// .env file for Node.js
/*
PORT=3000
MAILCHIMP_API_KEY=your_api_key_here-us1
MAILCHIMP_SERVER_PREFIX=us1
MAILCHIMP_LIST_ID=abc123def4
MAILCHIMP_STORE_ID=store_123
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
NODE_ENV=production
*/

// ============================================
// BACKEND CONFIGURATION - WordPress
// ============================================

// wp-config.php
/*
// Mailchimp Configuration
define('MAILCHIMP_API_KEY', 'your_api_key_here-us1');
define('MAILCHIMP_SERVER_PREFIX', 'us1');
define('MAILCHIMP_LIST_ID', 'abc123def4');
define('MAILCHIMP_STORE_ID', 'store_123');
*/

// ============================================
// SERVERLESS CONFIGURATION - AWS Lambda
// ============================================

// serverless.yml
/*
service: cart-abandonment-api

provider:
  name: aws
  runtime: nodejs18.x
  region: us-east-1
  environment:
    MAILCHIMP_API_KEY: ${env:MAILCHIMP_API_KEY}
    MAILCHIMP_SERVER_PREFIX: ${env:MAILCHIMP_SERVER_PREFIX}
    MAILCHIMP_LIST_ID: ${env:MAILCHIMP_LIST_ID}
    MAILCHIMP_STORE_ID: ${env:MAILCHIMP_STORE_ID}

functions:
  cartAbandonment:
    handler: handler.cartAbandonment
    events:
      - http:
          path: abandoned-cart
          method: post
          cors: true
*/

// ============================================
// SERVERLESS CONFIGURATION - Cloudflare Workers
// ============================================

// wrangler.toml
/*
name = "cart-abandonment"
main = "src/index.js"
compatibility_date = "2024-01-01"

[vars]
MAILCHIMP_SERVER_PREFIX = "us1"
MAILCHIMP_LIST_ID = "abc123def4"
MAILCHIMP_STORE_ID = "store_123"

[secrets]
MAILCHIMP_API_KEY = "your_api_key_here"
*/

// ============================================
// ENVIRONMENT-SPECIFIC SETTINGS
// ============================================

// Development
const devConfig = {
  backendUrl: 'http://localhost:3000/abandoned-cart',
  abandonmentDelay: 1, // 1 minute for testing
  storeId: 'test-store',
  debug: true
};

// Staging
const stagingConfig = {
  backendUrl: 'https://staging-api.yourdomain.com/abandoned-cart',
  abandonmentDelay: 15, // 15 minutes
  storeId: 'staging-store',
  debug: true
};

// Production
const productionConfig = {
  backendUrl: 'https://api.yourdomain.com/abandoned-cart',
  abandonmentDelay: 30, // 30 minutes
  storeId: 'production-store',
  debug: false
};

// Auto-select based on hostname
function getConfig() {
  const hostname = window.location.hostname;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return devConfig;
  } else if (hostname.includes('staging')) {
    return stagingConfig;
  } else {
    return productionConfig;
  }
}

// Initialize with environment-aware config
window.CartTracker.init(getConfig());

// ============================================
// CUSTOM ABANDONMENT DELAYS
// ============================================

// Quick abandonment (aggressive recovery)
const aggressiveConfig = {
  abandonmentDelay: 15 // 15 minutes
};

// Standard abandonment
const standardConfig = {
  abandonmentDelay: 30 // 30 minutes
};

// Relaxed abandonment (less pushy)
const relaxedConfig = {
  abandonmentDelay: 60 // 1 hour
};

// Multi-stage abandonment (requires custom implementation)
const multiStageConfig = {
  stages: [
    { delay: 30, template: 'reminder' },     // 30 min: gentle reminder
    { delay: 1440, template: 'incentive' },  // 24 hours: discount offer
    { delay: 4320, template: 'last-chance' } // 72 hours: final notice
  ]
};

// ============================================
// INTEGRATION WITH EMAIL CAPTURE POPUPS
// ============================================

// Example: Integrate with common popup libraries

// OptinMonster
document.addEventListener('om.Campaign.afterSuccess', function(event) {
  const email = event.detail.email;
  window.CartTracker.setEmail(email);
});

// Mailchimp Popup
document.addEventListener('mailchimp-form-submit', function(event) {
  const email = event.detail.email;
  window.CartTracker.setEmail(email);
});

// Custom popup
function onPopupSubmit(email) {
  if (window.CartTracker) {
    window.CartTracker.setEmail(email);
  }
}

// ============================================
// ADVANCED: CUSTOM EVENT DISPATCHING
// ============================================

// If you need to manually trigger Paydia events (for testing)
function simulateCartUpdate(cartData) {
  const event = new CustomEvent('paydia:cart_updated', {
    detail: cartData
  });
  window.dispatchEvent(event);
}

// Example cart data structure
const exampleCart = {
  id: 'cart_123',
  items: [
    {
      id: 'item_1',
      productId: 'prod_123',
      variantId: 'var_456',
      name: 'Example Product',
      description: 'Product description',
      quantity: 2,
      price: 29.99,
      imageUrl: 'https://example.com/image.jpg',
      url: 'https://example.com/product'
    }
  ],
  currency: 'USD',
  subtotal: 59.98,
  total: 59.98
};

// Trigger test event
// simulateCartUpdate(exampleCart);

// ============================================
// SECURITY: REQUEST SIGNING (Optional)
// ============================================

// For enhanced security, sign requests with HMAC
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

// Use in fetch request
async function sendSecureRequest(url, payload, secret) {
  const signature = await signRequest(payload, secret);

  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Signature': signature
    },
    body: JSON.stringify(payload)
  });
}
