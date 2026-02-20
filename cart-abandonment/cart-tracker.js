/**
 * Cart Abandonment Tracker
 * Listens to Paydia events and manages cart abandonment tracking
 *
 * Usage:
 * CartTracker.init({
 *   backendUrl: 'https://your-api.com/abandoned-cart',
 *   abandonmentDelay: 30, // minutes
 *   storeId: 'your-store-id',
 *   debug: false
 * });
 */

(function(window) {
  'use strict';

  const CartTracker = {
    config: {
      backendUrl: '',
      abandonmentDelay: 30, // minutes
      storeId: '',
      storageKey: 'paydia_cart_state',
      emailKey: 'paydia_customer_email',
      sessionKey: 'paydia_session_id',
      debug: false
    },

    state: {
      cart: null,
      abandonmentTimer: null,
      email: null,
      sessionId: null,
      lastActivity: null,
      checkoutStarted: false,
      purchaseCompleted: false
    },

    /**
     * Initialize the tracker
     */
    init(options = {}) {
      this.config = { ...this.config, ...options };

      // Generate or retrieve session ID
      this.state.sessionId = this.getOrCreateSessionId();

      // Load saved state
      this.loadState();

      // Set up event listeners
      this.setupEventListeners();

      // Set up email capture
      this.setupEmailCapture();

      // Resume abandonment timer if cart exists
      if (this.state.cart && this.state.cart.items.length > 0 && !this.state.purchaseCompleted) {
        this.resetAbandonmentTimer();
      }

      this.log('Cart Tracker initialized', this.state);
    },

    /**
     * Set up Paydia event listeners
     */
    setupEventListeners() {
      // Cart updated (items added/removed/quantity changed)
      window.addEventListener('paydia:cart_updated', (event) => {
        this.handleCartUpdated(event.detail);
      });

      // Checkout started
      window.addEventListener('paydia:checkout_started', (event) => {
        this.handleCheckoutStarted(event.detail);
      });

      // Purchase completed
      window.addEventListener('paydia:purchase_completed', (event) => {
        this.handlePurchaseCompleted(event.detail);
      });

      // Listen for email capture from other scripts
      window.addEventListener('customer_email_captured', (event) => {
        this.handleEmailCaptured(event.detail.email);
      });

      // Listen for localStorage changes in other tabs
      window.addEventListener('storage', (event) => {
        if (event.key === this.config.storageKey) {
          this.loadState();
        }
      });

      // Before unload - save final state
      window.addEventListener('beforeunload', () => {
        this.saveState();
      });
    },

    /**
     * Handle cart updated event
     */
    handleCartUpdated(cartData) {
      this.log('Cart updated', cartData);

      // Update state
      this.state.cart = this.normalizeCartData(cartData);
      this.state.lastActivity = Date.now();
      this.state.checkoutStarted = false; // Reset if user goes back to cart

      // Save state
      this.saveState();

      // Reset abandonment timer
      if (this.state.cart.items.length > 0) {
        this.resetAbandonmentTimer();
      } else {
        // Cart is empty, clear timer
        this.clearAbandonmentTimer();
      }
    },

    /**
     * Handle checkout started event
     */
    handleCheckoutStarted(checkoutData) {
      this.log('Checkout started', checkoutData);

      this.state.checkoutStarted = true;
      this.state.lastActivity = Date.now();

      // Update cart with checkout data if available
      if (checkoutData.cart) {
        this.state.cart = this.normalizeCartData(checkoutData.cart);
      }

      this.saveState();

      // Extend abandonment timer (user is engaged)
      this.resetAbandonmentTimer();

      // Try to capture email from checkout form
      this.captureCheckoutEmail();
    },

    /**
     * Handle purchase completed event
     */
    handlePurchaseCompleted(orderData) {
      this.log('Purchase completed', orderData);

      this.state.purchaseCompleted = true;
      this.state.lastActivity = Date.now();

      // Clear abandonment timer
      this.clearAbandonmentTimer();

      // Notify backend to delete cart and cancel any pending emails
      this.notifyPurchaseCompleted(orderData);

      // Clear cart state after successful notification
      this.clearState();
    },

    /**
     * Handle email captured
     */
    handleEmailCaptured(email) {
      if (!this.isValidEmail(email)) {
        return;
      }

      this.log('Email captured', email);

      const previousEmail = this.state.email;
      this.state.email = email;

      // Save email separately for persistence
      localStorage.setItem(this.config.emailKey, email);

      this.saveState();

      // If cart exists and this is a new email, send to backend
      if (this.state.cart && this.state.cart.items.length > 0) {
        // If email changed, update backend immediately
        if (previousEmail !== email) {
          this.sendToBackend('email_updated');
        }
      }
    },

    /**
     * Set up email capture mechanisms
     */
    setupEmailCapture() {
      // Try to load saved email
      const savedEmail = localStorage.getItem(this.config.emailKey);
      if (savedEmail && this.isValidEmail(savedEmail)) {
        this.state.email = savedEmail;
      }

      // Capture from checkout form email field
      this.watchForEmailInput();
    },

    /**
     * Watch for email input in forms
     */
    watchForEmailInput() {
      // Debounce function
      let emailDebounce = null;

      const checkEmailInput = (event) => {
        const input = event.target;

        if (input.type === 'email' || input.name === 'email' || input.id.includes('email')) {
          clearTimeout(emailDebounce);
          emailDebounce = setTimeout(() => {
            const email = input.value.trim();
            if (this.isValidEmail(email)) {
              this.handleEmailCaptured(email);
            }
          }, 1000);
        }
      };

      // Listen for input events (works on all forms)
      document.addEventListener('input', checkEmailInput, true);

      // Also check on blur
      document.addEventListener('blur', (event) => {
        const input = event.target;
        if (input.type === 'email' || input.name === 'email') {
          const email = input.value.trim();
          if (this.isValidEmail(email)) {
            this.handleEmailCaptured(email);
          }
        }
      }, true);
    },

    /**
     * Try to capture email from checkout form
     */
    captureCheckoutEmail() {
      // Look for email input in common selectors
      const selectors = [
        'input[type="email"]',
        'input[name="email"]',
        'input[id*="email"]',
        '#email',
        '.email-input'
      ];

      for (const selector of selectors) {
        const input = document.querySelector(selector);
        if (input && input.value) {
          const email = input.value.trim();
          if (this.isValidEmail(email)) {
            this.handleEmailCaptured(email);
            return;
          }
        }
      }
    },

    /**
     * Reset abandonment timer
     */
    resetAbandonmentTimer() {
      this.clearAbandonmentTimer();

      const delayMs = this.config.abandonmentDelay * 60 * 1000;

      this.state.abandonmentTimer = setTimeout(() => {
        this.handleCartAbandoned();
      }, delayMs);

      this.log(`Abandonment timer set for ${this.config.abandonmentDelay} minutes`);
    },

    /**
     * Clear abandonment timer
     */
    clearAbandonmentTimer() {
      if (this.state.abandonmentTimer) {
        clearTimeout(this.state.abandonmentTimer);
        this.state.abandonmentTimer = null;
        this.log('Abandonment timer cleared');
      }
    },

    /**
     * Handle cart abandoned
     */
    handleCartAbandoned() {
      this.log('Cart abandoned');

      // Don't send if cart is empty or purchase was completed
      if (!this.state.cart ||
          this.state.cart.items.length === 0 ||
          this.state.purchaseCompleted) {
        return;
      }

      // Send to backend
      this.sendToBackend('cart_abandoned');
    },

    /**
     * Send cart data to backend
     */
    async sendToBackend(eventType) {
      if (!this.config.backendUrl) {
        this.log('Backend URL not configured', 'warn');
        return;
      }

      // Can't send without email (most Mailchimp operations require it)
      if (!this.state.email) {
        this.log('Email not captured yet, skipping backend sync', 'warn');
        return;
      }

      const payload = {
        eventType,
        sessionId: this.state.sessionId,
        email: this.state.email,
        cart: this.state.cart,
        checkoutStarted: this.state.checkoutStarted,
        timestamp: Date.now(),
        cartUrl: window.location.href
      };

      this.log('Sending to backend', payload);

      try {
        const response = await fetch(this.config.backendUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload)
        });

        if (!response.ok) {
          throw new Error(`Backend returned ${response.status}`);
        }

        const result = await response.json();
        this.log('Backend response', result);

        return result;
      } catch (error) {
        this.log('Failed to send to backend: ' + error.message, 'error');

        // Retry logic with exponential backoff
        this.scheduleRetry(payload);
      }
    },

    /**
     * Notify backend that purchase was completed
     */
    async notifyPurchaseCompleted(orderData) {
      const payload = {
        eventType: 'purchase_completed',
        sessionId: this.state.sessionId,
        email: this.state.email,
        orderId: orderData.orderId || orderData.id,
        orderTotal: orderData.total,
        timestamp: Date.now()
      };

      try {
        await fetch(this.config.backendUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload)
        });
      } catch (error) {
        this.log('Failed to notify purchase completion: ' + error.message, 'error');
      }
    },

    /**
     * Schedule retry with exponential backoff
     */
    scheduleRetry(payload, attempt = 1) {
      if (attempt > 3) {
        this.log('Max retry attempts reached', 'error');
        return;
      }

      const delay = Math.pow(2, attempt) * 1000; // 2s, 4s, 8s

      this.log(`Scheduling retry ${attempt} in ${delay}ms`);

      setTimeout(async () => {
        try {
          const response = await fetch(this.config.backendUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
          });

          if (!response.ok) {
            throw new Error(`Backend returned ${response.status}`);
          }

          this.log('Retry successful');
        } catch (error) {
          this.log(`Retry ${attempt} failed: ${error.message}`, 'error');
          this.scheduleRetry(payload, attempt + 1);
        }
      }, delay);
    },

    /**
     * Normalize cart data from Paydia event
     */
    normalizeCartData(rawCart) {
      return {
        id: rawCart.id || this.state.sessionId,
        items: (rawCart.items || []).map(item => ({
          id: item.id || item.productId,
          productId: item.productId || item.id,
          variantId: item.variantId || item.variant_id,
          name: item.name || item.title,
          description: item.description || '',
          quantity: parseInt(item.quantity) || 1,
          price: parseFloat(item.price) || 0,
          imageUrl: item.imageUrl || item.image_url || item.image || '',
          url: item.url || item.product_url || window.location.href
        })),
        currency: rawCart.currency || 'USD',
        subtotal: parseFloat(rawCart.subtotal) || this.calculateSubtotal(rawCart.items),
        total: parseFloat(rawCart.total) || parseFloat(rawCart.subtotal) || this.calculateSubtotal(rawCart.items),
        itemCount: rawCart.items ? rawCart.items.length : 0
      };
    },

    /**
     * Calculate cart subtotal
     */
    calculateSubtotal(items) {
      if (!items || items.length === 0) return 0;

      return items.reduce((sum, item) => {
        return sum + (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 1);
      }, 0);
    },

    /**
     * Get or create session ID
     */
    getOrCreateSessionId() {
      let sessionId = sessionStorage.getItem(this.config.sessionKey);

      if (!sessionId) {
        sessionId = this.generateSessionId();
        sessionStorage.setItem(this.config.sessionKey, sessionId);
      }

      return sessionId;
    },

    /**
     * Generate unique session ID
     */
    generateSessionId() {
      return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    },

    /**
     * Validate email
     */
    isValidEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    },

    /**
     * Save state to localStorage
     */
    saveState() {
      try {
        const stateToSave = {
          cart: this.state.cart,
          email: this.state.email,
          sessionId: this.state.sessionId,
          lastActivity: this.state.lastActivity,
          checkoutStarted: this.state.checkoutStarted,
          purchaseCompleted: this.state.purchaseCompleted
        };

        localStorage.setItem(this.config.storageKey, JSON.stringify(stateToSave));
      } catch (error) {
        this.log('Failed to save state: ' + error.message, 'error');
      }
    },

    /**
     * Load state from localStorage
     */
    loadState() {
      try {
        const savedState = localStorage.getItem(this.config.storageKey);
        if (savedState) {
          const parsed = JSON.parse(savedState);

          this.state.cart = parsed.cart || null;
          this.state.email = parsed.email || localStorage.getItem(this.config.emailKey) || null;
          this.state.sessionId = parsed.sessionId || this.state.sessionId;
          this.state.lastActivity = parsed.lastActivity || null;
          this.state.checkoutStarted = parsed.checkoutStarted || false;
          this.state.purchaseCompleted = parsed.purchaseCompleted || false;

          this.log('State loaded', this.state);
        }
      } catch (error) {
        this.log('Failed to load state: ' + error.message, 'error');
      }
    },

    /**
     * Clear state (after purchase)
     */
    clearState() {
      this.state.cart = null;
      this.state.checkoutStarted = false;
      this.state.purchaseCompleted = false;

      try {
        localStorage.removeItem(this.config.storageKey);
      } catch (error) {
        this.log('Failed to clear state: ' + error.message, 'error');
      }
    },

    /**
     * Debug logging
     */
    log(message, level = 'info') {
      if (!this.config.debug && level !== 'error') return;

      const timestamp = new Date().toISOString();
      const prefix = `[CartTracker ${timestamp}]`;

      switch (level) {
        case 'error':
          console.error(prefix, message);
          break;
        case 'warn':
          console.warn(prefix, message);
          break;
        default:
          console.log(prefix, message);
      }
    },

    /**
     * Public API: Manually set email
     */
    setEmail(email) {
      if (this.isValidEmail(email)) {
        this.handleEmailCaptured(email);
        return true;
      }
      return false;
    },

    /**
     * Public API: Get current cart state
     */
    getCart() {
      return this.state.cart;
    },

    /**
     * Public API: Force send to backend
     */
    forceSend() {
      return this.sendToBackend('manual_trigger');
    }
  };

  // Expose to window
  window.CartTracker = CartTracker;

  // Auto-initialize if config is present
  if (window.CART_TRACKER_CONFIG) {
    CartTracker.init(window.CART_TRACKER_CONFIG);
  }

})(window);
