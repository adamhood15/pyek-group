# Deployment Guide

Complete guide to deploying the cart abandonment tracking system across different platforms.

## Table of Contents

1. [Node.js Backend Deployment](#nodejs-backend)
2. [WordPress Deployment](#wordpress)
3. [Serverless Deployment](#serverless)
4. [Frontend Integration](#frontend)
5. [Production Checklist](#production-checklist)

---

## Node.js Backend

### Option 1: Traditional Server (VPS, EC2, etc.)

#### Prerequisites
- Server with Node.js 18+ installed
- Domain name configured
- SSL certificate (Let's Encrypt recommended)

#### Steps

1. **Clone/Upload Files**
```bash
# SSH into your server
ssh user@your-server.com

# Create directory
mkdir -p /var/www/cart-abandonment
cd /var/www/cart-abandonment

# Upload files via git or scp
git clone your-repo-url .
# OR
scp -r ./cart-abandonment user@your-server.com:/var/www/
```

2. **Install Dependencies**
```bash
npm install --production
```

3. **Configure Environment**
```bash
cp .env.example .env
nano .env
```

Edit with your values:
```env
MAILCHIMP_API_KEY=abc123-us1
MAILCHIMP_SERVER_PREFIX=us1
MAILCHIMP_LIST_ID=def456
MAILCHIMP_STORE_ID=store_789
PORT=3000
NODE_ENV=production
ALLOWED_ORIGINS=https://yourdomain.com
```

4. **Setup Process Manager (PM2)**
```bash
# Install PM2 globally
npm install -g pm2

# Start application
pm2 start node-backend.js --name cart-abandonment

# Save PM2 configuration
pm2 save

# Setup auto-restart on reboot
pm2 startup
```

5. **Configure Nginx Reverse Proxy**
```bash
sudo nano /etc/nginx/sites-available/cart-abandonment
```

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;

    # SSL certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;

        # CORS headers
        add_header Access-Control-Allow-Origin "https://yourdomain.com" always;
        add_header Access-Control-Allow-Methods "POST, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Content-Type" always;

        # Handle preflight
        if ($request_method = 'OPTIONS') {
            return 204;
        }
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/cart-abandonment /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

6. **Setup SSL with Let's Encrypt**
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d api.yourdomain.com
```

7. **Monitor Logs**
```bash
# PM2 logs
pm2 logs cart-abandonment

# Nginx logs
tail -f /var/log/nginx/error.log
```

### Option 2: Heroku

1. **Create Heroku App**
```bash
heroku create your-cart-api
```

2. **Set Environment Variables**
```bash
heroku config:set MAILCHIMP_API_KEY=abc123-us1
heroku config:set MAILCHIMP_SERVER_PREFIX=us1
heroku config:set MAILCHIMP_LIST_ID=def456
heroku config:set MAILCHIMP_STORE_ID=store_789
heroku config:set ALLOWED_ORIGINS=https://yourdomain.com
```

3. **Create Procfile**
```
web: node node-backend.js
```

4. **Deploy**
```bash
git add .
git commit -m "Initial deployment"
git push heroku main
```

5. **Scale**
```bash
heroku ps:scale web=1
```

### Option 3: DigitalOcean App Platform

1. **Connect Repository**
- Go to DigitalOcean dashboard
- Create new App
- Connect GitHub/GitLab repo

2. **Configure App**
```yaml
name: cart-abandonment
services:
  - name: api
    github:
      repo: your-username/your-repo
      branch: main
    run_command: node node-backend.js
    environment_slug: node-js
    instance_count: 1
    instance_size_slug: basic-xxs
    envs:
      - key: MAILCHIMP_API_KEY
        value: ${MAILCHIMP_API_KEY}
      - key: MAILCHIMP_SERVER_PREFIX
        value: us1
      - key: MAILCHIMP_LIST_ID
        value: ${MAILCHIMP_LIST_ID}
      - key: MAILCHIMP_STORE_ID
        value: ${MAILCHIMP_STORE_ID}
      - key: NODE_ENV
        value: production
```

3. **Deploy**
- Click "Deploy"
- App will auto-deploy on git push

---

## WordPress

### Installation

#### Option 1: As Plugin

1. **Create Plugin File Structure**
```
wp-content/
  plugins/
    cart-abandonment/
      cart-abandonment.php
      wordpress-backend.php
      readme.txt
```

2. **Main Plugin File** (`cart-abandonment.php`)
```php
<?php
/**
 * Plugin Name: Cart Abandonment Tracker
 * Description: Mailchimp cart abandonment integration
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Include backend handler
require_once plugin_dir_path(__FILE__) . 'wordpress-backend.php';
```

3. **Activate Plugin**
- Go to **Plugins → Installed Plugins**
- Find "Cart Abandonment Tracker"
- Click "Activate"

#### Option 2: In Theme

Add to `functions.php`:
```php
// Include cart abandonment backend
require_once get_template_directory() . '/cart-abandonment/wordpress-backend.php';
```

### Configuration

Add to `wp-config.php`:
```php
// Mailchimp Configuration
define('MAILCHIMP_API_KEY', 'abc123-us1');
define('MAILCHIMP_SERVER_PREFIX', 'us1');
define('MAILCHIMP_LIST_ID', 'def456');
define('MAILCHIMP_STORE_ID', 'store_789');
```

### Frontend Integration

Add to theme's `header.php` or via plugin:
```php
<script src="<?php echo get_template_directory_uri(); ?>/js/cart-tracker.js"></script>
<script>
window.CartTracker.init({
  backendUrl: '<?php echo rest_url('cart-abandonment/v1/track'); ?>',
  abandonmentDelay: 30,
  storeId: '<?php echo MAILCHIMP_STORE_ID; ?>',
  debug: false
});
</script>
```

---

## Serverless

### AWS Lambda + API Gateway

1. **Create Lambda Function**
```bash
# Package dependencies
npm install --production
zip -r function.zip node_modules/ node-backend.js
```

2. **Create `handler.js` Wrapper**
```javascript
const serverless = require('serverless-http');
const app = require('./node-backend');

module.exports.handler = serverless(app);
```

3. **Deploy with Serverless Framework**

`serverless.yml`:
```yaml
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
  api:
    handler: handler.handler
    events:
      - http:
          path: /abandoned-cart
          method: post
          cors: true
      - http:
          path: /abandoned-cart
          method: options
          cors: true

plugins:
  - serverless-offline
```

Deploy:
```bash
npm install -g serverless
serverless deploy
```

### Cloudflare Workers

1. **Install Wrangler**
```bash
npm install -g wrangler
wrangler login
```

2. **Configure `wrangler.toml`**
```toml
name = "cart-abandonment"
main = "cloudflare-worker.js"
compatibility_date = "2024-01-01"

[env.production]
vars = { MAILCHIMP_SERVER_PREFIX = "us1" }
```

3. **Set Secrets**
```bash
wrangler secret put MAILCHIMP_API_KEY
wrangler secret put MAILCHIMP_LIST_ID
wrangler secret put MAILCHIMP_STORE_ID
```

4. **Deploy**
```bash
wrangler publish
```

### Vercel

1. **Create `vercel.json`**
```json
{
  "version": 2,
  "builds": [
    {
      "src": "node-backend.js",
      "use": "@vercel/node"
    }
  ],
  "routes": [
    {
      "src": "/abandoned-cart",
      "dest": "node-backend.js"
    }
  ]
}
```

2. **Deploy**
```bash
npm install -g vercel
vercel login
vercel --prod
```

3. **Set Environment Variables**
```bash
vercel env add MAILCHIMP_API_KEY
vercel env add MAILCHIMP_LIST_ID
vercel env add MAILCHIMP_STORE_ID
```

---

## Frontend

### Static Sites (HTML/JS)

1. **Upload Files**
Upload `cart-tracker.js` to your server/CDN.

2. **Include Script**
```html
<script src="/js/cart-tracker.js"></script>
<script>
window.CartTracker.init({
  backendUrl: 'https://api.yourdomain.com/abandoned-cart',
  abandonmentDelay: 30,
  storeId: 'your-store-id',
  debug: false
});
</script>
```

### WordPress

Add to theme or via plugin:
```php
function enqueue_cart_tracker() {
    wp_enqueue_script(
        'cart-tracker',
        get_template_directory_uri() . '/js/cart-tracker.js',
        array(),
        '1.0.0',
        true
    );

    wp_localize_script('cart-tracker', 'CART_TRACKER_CONFIG', array(
        'backendUrl' => rest_url('cart-abandonment/v1/track'),
        'abandonmentDelay' => 30,
        'storeId' => MAILCHIMP_STORE_ID,
        'debug' => false
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_cart_tracker');
```

### React/Next.js

```javascript
// components/CartTracker.jsx
import { useEffect } from 'react';

export default function CartTracker() {
  useEffect(() => {
    // Load script
    const script = document.createElement('script');
    script.src = '/js/cart-tracker.js';
    script.async = true;
    script.onload = () => {
      window.CartTracker.init({
        backendUrl: process.env.NEXT_PUBLIC_API_URL + '/abandoned-cart',
        abandonmentDelay: 30,
        storeId: process.env.NEXT_PUBLIC_MAILCHIMP_STORE_ID,
        debug: process.env.NODE_ENV === 'development'
      });
    };
    document.body.appendChild(script);

    return () => {
      document.body.removeChild(script);
    };
  }, []);

  return null;
}
```

### Vue/Nuxt

```javascript
// plugins/cart-tracker.client.js
export default defineNuxtPlugin(() => {
  if (process.client) {
    const script = document.createElement('script');
    script.src = '/js/cart-tracker.js';
    script.onload = () => {
      window.CartTracker.init({
        backendUrl: import.meta.env.VITE_API_URL + '/abandoned-cart',
        abandonmentDelay: 30,
        storeId: import.meta.env.VITE_MAILCHIMP_STORE_ID,
        debug: import.meta.env.DEV
      });
    };
    document.body.appendChild(script);
  }
});
```

---

## Production Checklist

### Security

- [ ] API keys in environment variables (not in code)
- [ ] HTTPS enabled and enforced
- [ ] CORS properly configured (specific origins, not *)
- [ ] Rate limiting enabled
- [ ] Input validation implemented
- [ ] Security headers configured
- [ ] Error messages don't leak sensitive info
- [ ] Dependencies updated and audited

### Performance

- [ ] CDN configured for static assets
- [ ] Response caching where appropriate
- [ ] Database connection pooling (if applicable)
- [ ] Graceful shutdown handling
- [ ] Health check endpoint working

### Monitoring

- [ ] Error tracking configured (Sentry, etc.)
- [ ] Logging configured (structured logs)
- [ ] Uptime monitoring (Pingdom, UptimeRobot)
- [ ] Performance monitoring (New Relic, DataDog)
- [ ] Mailchimp automation tested
- [ ] Alert notifications configured

### Backup & Recovery

- [ ] Database backups scheduled (if applicable)
- [ ] Configuration backed up
- [ ] Disaster recovery plan documented
- [ ] Rollback procedure tested

### Testing

- [ ] Integration tests passing
- [ ] Load testing completed
- [ ] Email delivery tested
- [ ] Cross-browser testing done
- [ ] Mobile testing done
- [ ] Edge cases tested

### Documentation

- [ ] API documentation complete
- [ ] Configuration guide written
- [ ] Troubleshooting guide available
- [ ] Team trained on system

### Legal

- [ ] Privacy policy updated
- [ ] Terms of service updated
- [ ] GDPR compliance verified (if applicable)
- [ ] Data retention policy implemented

---

## Platform Comparison

| Platform | Complexity | Cost | Scalability | Maintenance |
|----------|-----------|------|-------------|-------------|
| **VPS/EC2** | High | $5-50/mo | Manual | High |
| **Heroku** | Low | $7-25/mo | Auto | Low |
| **DigitalOcean** | Medium | $5-20/mo | Auto | Low |
| **AWS Lambda** | Medium | Pay-per-use | Auto | Medium |
| **Cloudflare Workers** | Low | Free-$5/mo | Auto | Low |
| **Vercel** | Low | Free-$20/mo | Auto | Very Low |
| **WordPress** | Low | Hosting cost | N/A | Medium |

### Recommendations

**For Small Sites (< 1000 carts/month):**
- Cloudflare Workers (best value)
- Vercel Free Tier
- WordPress plugin

**For Medium Sites (1000-10000 carts/month):**
- Heroku
- DigitalOcean App Platform
- AWS Lambda

**For Large Sites (> 10000 carts/month):**
- VPS with PM2
- AWS Lambda with provisioned concurrency
- Cloudflare Workers (paid plan)

**For WordPress Sites:**
- WordPress plugin (if already on WordPress)
- Otherwise, Cloudflare Workers for API

---

## Rollout Strategy

### Phase 1: Testing (Week 1)
- Deploy to staging environment
- Test with small user subset (5%)
- Monitor error rates
- Verify Mailchimp integration

### Phase 2: Beta (Week 2-3)
- Increase to 25% of users
- A/B test email timing
- Collect feedback
- Optimize abandonment delay

### Phase 3: Full Release (Week 4)
- Roll out to 100% of users
- Monitor recovery rate
- Adjust based on metrics

### Rollback Plan

If issues arise:
```bash
# Node.js
pm2 stop cart-abandonment
pm2 delete cart-abandonment
# Restore previous version
git checkout previous-version
npm install
pm2 start node-backend.js

# Heroku
heroku rollback

# Vercel
vercel rollback
```

---

## Support

For deployment issues:
1. Check logs first
2. Verify environment variables
3. Test Mailchimp connectivity
4. Review security settings
5. Consult platform documentation
