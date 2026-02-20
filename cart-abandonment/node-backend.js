/**
 * Node.js Backend for Cart Abandonment Tracking
 * Handles requests from frontend and communicates with Mailchimp
 *
 * Dependencies:
 * npm install express cors dotenv @mailchimp/mailchimp_marketing crypto
 */

require('dotenv').config();
const express = require('express');
const cors = require('cors');
const crypto = require('crypto');
const mailchimp = require('@mailchimp/mailchimp_marketing');

const app = express();

// Configuration
const CONFIG = {
  PORT: process.env.PORT || 3000,
  MAILCHIMP_API_KEY: process.env.MAILCHIMP_API_KEY,
  MAILCHIMP_SERVER_PREFIX: process.env.MAILCHIMP_SERVER_PREFIX || 'us1',
  MAILCHIMP_LIST_ID: process.env.MAILCHIMP_LIST_ID,
  MAILCHIMP_STORE_ID: process.env.MAILCHIMP_STORE_ID,
  ALLOWED_ORIGINS: process.env.ALLOWED_ORIGINS?.split(',') || ['http://localhost:3000'],
  RATE_LIMIT_WINDOW: 15 * 60 * 1000, // 15 minutes
  RATE_LIMIT_MAX: 100 // Max requests per window
};

// Validate required environment variables
const requiredEnvVars = [
  'MAILCHIMP_API_KEY',
  'MAILCHIMP_LIST_ID',
  'MAILCHIMP_STORE_ID'
];

for (const envVar of requiredEnvVars) {
  if (!process.env[envVar]) {
    console.error(`Missing required environment variable: ${envVar}`);
    process.exit(1);
  }
}

// Configure Mailchimp
mailchimp.setConfig({
  apiKey: CONFIG.MAILCHIMP_API_KEY,
  server: CONFIG.MAILCHIMP_SERVER_PREFIX
});

// Middleware
app.use(cors({
  origin: (origin, callback) => {
    if (!origin || CONFIG.ALLOWED_ORIGINS.includes(origin)) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  }
}));

app.use(express.json({ limit: '10mb' }));

// Simple in-memory rate limiting (use Redis in production)
const rateLimitStore = new Map();

function rateLimit(req, res, next) {
  const identifier = req.ip + req.body.email;
  const now = Date.now();

  if (!rateLimitStore.has(identifier)) {
    rateLimitStore.set(identifier, { count: 1, resetTime: now + CONFIG.RATE_LIMIT_WINDOW });
    return next();
  }

  const limit = rateLimitStore.get(identifier);

  if (now > limit.resetTime) {
    limit.count = 1;
    limit.resetTime = now + CONFIG.RATE_LIMIT_WINDOW;
    return next();
  }

  if (limit.count >= CONFIG.RATE_LIMIT_MAX) {
    return res.status(429).json({
      success: false,
      error: 'Rate limit exceeded'
    });
  }

  limit.count++;
  next();
}

// Clean up rate limit store periodically
setInterval(() => {
  const now = Date.now();
  for (const [key, value] of rateLimitStore.entries()) {
    if (now > value.resetTime) {
      rateLimitStore.delete(key);
    }
  }
}, 60000); // Clean every minute

// Request validation middleware
function validateRequest(req, res, next) {
  const { eventType, email, cart, sessionId } = req.body;

  if (!eventType) {
    return res.status(400).json({
      success: false,
      error: 'Missing eventType'
    });
  }

  if (!sessionId) {
    return res.status(400).json({
      success: false,
      error: 'Missing sessionId'
    });
  }

  if (eventType !== 'purchase_completed' && !email) {
    return res.status(400).json({
      success: false,
      error: 'Missing email'
    });
  }

  if (!isValidEmail(email) && eventType !== 'purchase_completed') {
    return res.status(400).json({
      success: false,
      error: 'Invalid email format'
    });
  }

  if (eventType === 'cart_abandoned' && (!cart || !cart.items || cart.items.length === 0)) {
    return res.status(400).json({
      success: false,
      error: 'Cart data missing or empty'
    });
  }

  next();
}

// Utility functions
function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function generateSubscriberHash(email) {
  return crypto
    .createHash('md5')
    .update(email.toLowerCase())
    .digest('hex');
}

function generateCartId(sessionId, email) {
  return crypto
    .createHash('md5')
    .update(`${sessionId}_${email}`)
    .digest('hex');
}

// Mailchimp API functions
async function createOrUpdateContact(email, firstName = '', lastName = '') {
  const subscriberHash = generateSubscriberHash(email);

  try {
    // Try to get existing member
    await mailchimp.lists.getListMember(
      CONFIG.MAILCHIMP_LIST_ID,
      subscriberHash
    );

    // Member exists, update
    return await mailchimp.lists.updateListMember(
      CONFIG.MAILCHIMP_LIST_ID,
      subscriberHash,
      {
        email_address: email,
        status_if_new: 'subscribed',
        merge_fields: {
          FNAME: firstName,
          LNAME: lastName
        }
      }
    );
  } catch (error) {
    if (error.status === 404) {
      // Member doesn't exist, create
      return await mailchimp.lists.addListMember(
        CONFIG.MAILCHIMP_LIST_ID,
        {
          email_address: email,
          status: 'subscribed',
          merge_fields: {
            FNAME: firstName,
            LNAME: lastName
          }
        }
      );
    }
    throw error;
  }
}

async function createOrUpdateCart(email, cart, sessionId, checkoutUrl) {
  const cartId = generateCartId(sessionId, email);

  const cartData = {
    id: cartId,
    customer: {
      id: generateSubscriberHash(email),
      email_address: email,
      opt_in_status: true
    },
    currency_code: cart.currency || 'USD',
    order_total: cart.total,
    lines: cart.items.map((item, index) => ({
      id: `${cartId}_line_${index}`,
      product_id: String(item.productId),
      product_variant_id: String(item.variantId || item.productId),
      quantity: item.quantity,
      price: item.price
    })),
    checkout_url: checkoutUrl
  };

  try {
    // Try to update existing cart
    return await mailchimp.ecommerce.updateStoreCart(
      CONFIG.MAILCHIMP_STORE_ID,
      cartId,
      cartData
    );
  } catch (error) {
    if (error.status === 404) {
      // Cart doesn't exist, create
      return await mailchimp.ecommerce.addStoreCart(
        CONFIG.MAILCHIMP_STORE_ID,
        cartData
      );
    }
    throw error;
  }
}

async function deleteCart(email, sessionId) {
  const cartId = generateCartId(sessionId, email);

  try {
    return await mailchimp.ecommerce.deleteStoreCart(
      CONFIG.MAILCHIMP_STORE_ID,
      cartId
    );
  } catch (error) {
    // If cart doesn't exist, that's fine
    if (error.status === 404) {
      return { deleted: true };
    }
    throw error;
  }
}

async function trackEvent(email, eventName, properties = {}) {
  const subscriberHash = generateSubscriberHash(email);

  try {
    return await mailchimp.lists.createListMemberEvent(
      CONFIG.MAILCHIMP_LIST_ID,
      subscriberHash,
      {
        name: eventName,
        properties
      }
    );
  } catch (error) {
    console.error('Failed to track event:', error);
    // Don't fail the request if event tracking fails
    return null;
  }
}

async function ensureProductsExist(cart) {
  // In production, you should have a system to sync products to Mailchimp
  // This is a simplified version that creates products on-the-fly

  const productPromises = cart.items.map(async (item) => {
    try {
      // Try to get product
      await mailchimp.ecommerce.getStoreProduct(
        CONFIG.MAILCHIMP_STORE_ID,
        String(item.productId)
      );
    } catch (error) {
      if (error.status === 404) {
        // Product doesn't exist, create it
        try {
          await mailchimp.ecommerce.addStoreProduct(
            CONFIG.MAILCHIMP_STORE_ID,
            {
              id: String(item.productId),
              title: item.name,
              description: item.description || item.name,
              url: item.url,
              variants: [
                {
                  id: String(item.variantId || item.productId),
                  title: item.name,
                  price: item.price,
                  image_url: item.imageUrl
                }
              ]
            }
          );
        } catch (createError) {
          console.error('Failed to create product:', createError);
        }
      }
    }
  });

  await Promise.allSettled(productPromises);
}

// Main endpoint
app.post('/abandoned-cart', rateLimit, validateRequest, async (req, res) => {
  const { eventType, email, cart, sessionId, checkoutStarted, cartUrl, orderId } = req.body;

  console.log(`[${new Date().toISOString()}] Received ${eventType} event for ${email || 'unknown'}`);

  try {
    switch (eventType) {
      case 'cart_abandoned':
        // 1. Create or update contact in Mailchimp
        await createOrUpdateContact(email);

        // 2. Ensure products exist in Mailchimp store
        await ensureProductsExist(cart);

        // 3. Create or update abandoned cart
        await createOrUpdateCart(email, cart, sessionId, cartUrl);

        // 4. Track abandonment event
        await trackEvent(email, 'cart_abandoned', {
          cart_total: cart.total,
          item_count: cart.items.length
        });

        console.log(`Cart abandoned notification sent for ${email}`);

        return res.json({
          success: true,
          message: 'Cart abandonment tracked'
        });

      case 'email_updated':
        // Update contact and cart with new email
        await createOrUpdateContact(email);

        if (cart && cart.items.length > 0) {
          await ensureProductsExist(cart);
          await createOrUpdateCart(email, cart, sessionId, cartUrl);
        }

        return res.json({
          success: true,
          message: 'Email updated'
        });

      case 'purchase_completed':
        // Delete the cart to prevent abandoned cart email
        if (email && sessionId) {
          await deleteCart(email, sessionId);

          // Track purchase event
          await trackEvent(email, 'purchase_completed', {
            order_id: orderId,
            order_total: req.body.orderTotal
          });

          console.log(`Cart deleted after purchase for ${email}`);
        }

        return res.json({
          success: true,
          message: 'Purchase recorded, cart removed'
        });

      case 'manual_trigger':
        // Manual trigger from frontend
        if (cart && cart.items.length > 0) {
          await createOrUpdateContact(email);
          await ensureProductsExist(cart);
          await createOrUpdateCart(email, cart, sessionId, cartUrl);
        }

        return res.json({
          success: true,
          message: 'Manual sync completed'
        });

      default:
        return res.status(400).json({
          success: false,
          error: 'Unknown event type'
        });
    }
  } catch (error) {
    console.error('Error processing request:', error);

    // Check for specific Mailchimp errors
    if (error.response) {
      console.error('Mailchimp error response:', error.response.body);

      return res.status(500).json({
        success: false,
        error: 'Mailchimp API error',
        details: error.response.body?.detail || error.message
      });
    }

    return res.status(500).json({
      success: false,
      error: 'Internal server error',
      details: error.message
    });
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString()
  });
});

// Error handling
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);

  res.status(500).json({
    success: false,
    error: 'Internal server error'
  });
});

// Start server
app.listen(CONFIG.PORT, () => {
  console.log(`Cart abandonment backend running on port ${CONFIG.PORT}`);
  console.log(`Mailchimp Store ID: ${CONFIG.MAILCHIMP_STORE_ID}`);
  console.log(`Mailchimp List ID: ${CONFIG.MAILCHIMP_LIST_ID}`);
});

module.exports = app;
