/**
 * Cloudflare Workers Implementation
 * High-performance edge computing for cart abandonment tracking
 *
 * Deploy with: wrangler publish
 *
 * Benefits:
 * - Global edge network (low latency)
 * - Automatic scaling
 * - Pay-per-request pricing
 * - Built-in DDoS protection
 * - No cold starts
 */

// Environment variables are accessed via env parameter

export default {
  async fetch(request, env, ctx) {
    // Handle CORS preflight
    if (request.method === 'OPTIONS') {
      return handleCORS();
    }

    // Only accept POST requests
    if (request.method !== 'POST') {
      return jsonResponse({ error: 'Method not allowed' }, 405);
    }

    // Rate limiting using Durable Objects (optional, requires setup)
    // const rateLimitOk = await checkRateLimit(request, env);
    // if (!rateLimitOk) {
    //   return jsonResponse({ error: 'Rate limit exceeded' }, 429);
    // }

    try {
      const payload = await request.json();

      // Validate request
      const validation = validateRequest(payload);
      if (!validation.valid) {
        return jsonResponse({ error: validation.error }, 400);
      }

      // Process based on event type
      const result = await processEvent(payload, env);

      return jsonResponse(result, 200);
    } catch (error) {
      console.error('Error processing request:', error);
      return jsonResponse({ error: 'Internal server error' }, 500);
    }
  }
};

/**
 * Validate incoming request
 */
function validateRequest(payload) {
  const { eventType, email, sessionId, cart } = payload;

  if (!eventType) {
    return { valid: false, error: 'Missing eventType' };
  }

  if (!sessionId) {
    return { valid: false, error: 'Missing sessionId' };
  }

  const validEvents = ['cart_abandoned', 'email_updated', 'purchase_completed', 'manual_trigger'];
  if (!validEvents.includes(eventType)) {
    return { valid: false, error: 'Invalid eventType' };
  }

  if (eventType !== 'purchase_completed' && !email) {
    return { valid: false, error: 'Missing email' };
  }

  if (!isValidEmail(email) && eventType !== 'purchase_completed') {
    return { valid: false, error: 'Invalid email format' };
  }

  if (eventType === 'cart_abandoned' && (!cart || !cart.items || cart.items.length === 0)) {
    return { valid: false, error: 'Cart data missing or empty' };
  }

  return { valid: true };
}

/**
 * Process event based on type
 */
async function processEvent(payload, env) {
  const { eventType, email, cart, sessionId, cartUrl, orderId, orderTotal } = payload;

  switch (eventType) {
    case 'cart_abandoned':
      await createOrUpdateContact(email, env);
      await ensureProductsExist(cart, env);
      await createOrUpdateCart(email, cart, sessionId, cartUrl, env);
      await trackEvent(email, 'cart_abandoned', {
        cart_total: cart.total,
        item_count: cart.items.length
      }, env);

      return {
        success: true,
        message: 'Cart abandonment tracked'
      };

    case 'email_updated':
      await createOrUpdateContact(email, env);

      if (cart && cart.items && cart.items.length > 0) {
        await ensureProductsExist(cart, env);
        await createOrUpdateCart(email, cart, sessionId, cartUrl, env);
      }

      return {
        success: true,
        message: 'Email updated'
      };

    case 'purchase_completed':
      if (email && sessionId) {
        await deleteCart(email, sessionId, env);
        await trackEvent(email, 'purchase_completed', {
          order_id: orderId,
          order_total: orderTotal
        }, env);
      }

      return {
        success: true,
        message: 'Purchase recorded, cart removed'
      };

    case 'manual_trigger':
      if (cart && cart.items && cart.items.length > 0 && email) {
        await createOrUpdateContact(email, env);
        await ensureProductsExist(cart, env);
        await createOrUpdateCart(email, cart, sessionId, cartUrl, env);
      }

      return {
        success: true,
        message: 'Manual sync completed'
      };

    default:
      throw new Error('Unknown event type');
  }
}

/**
 * Mailchimp API request helper
 */
async function mailchimpRequest(endpoint, method, data, env) {
  const apiKey = env.MAILCHIMP_API_KEY;
  const serverPrefix = env.MAILCHIMP_SERVER_PREFIX || 'us1';

  const url = `https://${serverPrefix}.api.mailchimp.com/3.0/${endpoint}`;

  const options = {
    method,
    headers: {
      'Authorization': 'Basic ' + btoa(`anystring:${apiKey}`),
      'Content-Type': 'application/json'
    }
  };

  if (data) {
    options.body = JSON.stringify(data);
  }

  const response = await fetch(url, options);
  const responseData = await response.json();

  if (!response.ok) {
    console.error('Mailchimp API error:', responseData);
    throw new Error(responseData.detail || 'Mailchimp API error');
  }

  return responseData;
}

/**
 * Create or update contact
 */
async function createOrUpdateContact(email, env) {
  const listId = env.MAILCHIMP_LIST_ID;
  const subscriberHash = generateHash(email);

  try {
    // Try to update existing member
    return await mailchimpRequest(
      `lists/${listId}/members/${subscriberHash}`,
      'PATCH',
      {
        email_address: email,
        status_if_new: 'subscribed'
      },
      env
    );
  } catch (error) {
    // If member doesn't exist, create
    return await mailchimpRequest(
      `lists/${listId}/members`,
      'POST',
      {
        email_address: email,
        status: 'subscribed'
      },
      env
    );
  }
}

/**
 * Create or update cart
 */
async function createOrUpdateCart(email, cart, sessionId, checkoutUrl, env) {
  const storeId = env.MAILCHIMP_STORE_ID;
  const cartId = generateCartId(sessionId, email);
  const subscriberHash = generateHash(email);

  const lines = cart.items.map((item, index) => ({
    id: `${cartId}_line_${index}`,
    product_id: String(item.productId),
    product_variant_id: String(item.variantId || item.productId),
    quantity: item.quantity,
    price: item.price
  }));

  const cartData = {
    id: cartId,
    customer: {
      id: subscriberHash,
      email_address: email,
      opt_in_status: true
    },
    currency_code: cart.currency || 'USD',
    order_total: cart.total,
    lines,
    checkout_url: checkoutUrl
  };

  try {
    // Try to update existing cart
    return await mailchimpRequest(
      `ecommerce/stores/${storeId}/carts/${cartId}`,
      'PATCH',
      cartData,
      env
    );
  } catch (error) {
    // Cart doesn't exist, create
    return await mailchimpRequest(
      `ecommerce/stores/${storeId}/carts`,
      'POST',
      cartData,
      env
    );
  }
}

/**
 * Delete cart
 */
async function deleteCart(email, sessionId, env) {
  const storeId = env.MAILCHIMP_STORE_ID;
  const cartId = generateCartId(sessionId, email);

  try {
    return await mailchimpRequest(
      `ecommerce/stores/${storeId}/carts/${cartId}`,
      'DELETE',
      null,
      env
    );
  } catch (error) {
    // If cart doesn't exist, that's fine
    return { deleted: true };
  }
}

/**
 * Track event
 */
async function trackEvent(email, eventName, properties, env) {
  const listId = env.MAILCHIMP_LIST_ID;
  const subscriberHash = generateHash(email);

  try {
    return await mailchimpRequest(
      `lists/${listId}/members/${subscriberHash}/events`,
      'POST',
      {
        name: eventName,
        properties
      },
      env
    );
  } catch (error) {
    console.error('Failed to track event:', error);
    // Don't fail the request
    return null;
  }
}

/**
 * Ensure products exist
 */
async function ensureProductsExist(cart, env) {
  const storeId = env.MAILCHIMP_STORE_ID;

  const promises = cart.items.map(async (item) => {
    const productId = String(item.productId);

    try {
      // Check if product exists
      await mailchimpRequest(
        `ecommerce/stores/${storeId}/products/${productId}`,
        'GET',
        null,
        env
      );
    } catch (error) {
      // Product doesn't exist, create it
      try {
        await mailchimpRequest(
          `ecommerce/stores/${storeId}/products`,
          'POST',
          {
            id: productId,
            title: item.name,
            description: item.description || item.name,
            url: item.url || '',
            variants: [
              {
                id: String(item.variantId || item.productId),
                title: item.name,
                price: item.price,
                image_url: item.imageUrl || ''
              }
            ]
          },
          env
        );
      } catch (createError) {
        console.error('Failed to create product:', createError);
      }
    }
  });

  await Promise.allSettled(promises);
}

/**
 * Utility functions
 */
function generateHash(email) {
  return md5(email.toLowerCase());
}

function generateCartId(sessionId, email) {
  return md5(`${sessionId}_${email}`);
}

function isValidEmail(email) {
  return email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function handleCORS() {
  return new Response(null, {
    status: 204,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
      'Access-Control-Max-Age': '86400'
    }
  });
}

function jsonResponse(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'Access-Control-Allow-Origin': '*'
    }
  });
}

/**
 * Simple MD5 implementation for Workers
 * (Cloudflare Workers doesn't have crypto.createHash)
 */
async function md5(text) {
  const encoder = new TextEncoder();
  const data = encoder.encode(text);
  const hashBuffer = await crypto.subtle.digest('MD5', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Rate limiting with Durable Objects (advanced, requires setup)
 */
/*
async function checkRateLimit(request, env) {
  const ip = request.headers.get('CF-Connecting-IP');
  const id = env.RATE_LIMITER.idFromName(ip);
  const limiter = env.RATE_LIMITER.get(id);

  const response = await limiter.fetch(request);
  return response.ok;
}

// Durable Object class (in separate file)
export class RateLimiter {
  constructor(state, env) {
    this.state = state;
  }

  async fetch(request) {
    const count = (await this.state.storage.get('count')) || 0;

    if (count >= 100) {
      return new Response('Rate limit exceeded', { status: 429 });
    }

    await this.state.storage.put('count', count + 1);

    // Reset after 15 minutes
    this.state.storage.setAlarm(Date.now() + 15 * 60 * 1000);

    return new Response('OK');
  }

  async alarm() {
    await this.state.storage.delete('count');
  }
}
*/
