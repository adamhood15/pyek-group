<?php
/**
 * WordPress Backend for Cart Abandonment Tracking
 * Handles requests from frontend and communicates with Mailchimp
 *
 * Installation:
 * 1. Add this file to your theme's functions.php or create as a plugin
 * 2. Configure constants in wp-config.php or use Options API
 * 3. Ensure WordPress REST API is enabled
 *
 * Required constants in wp-config.php:
 * define('MAILCHIMP_API_KEY', 'your-api-key');
 * define('MAILCHIMP_SERVER_PREFIX', 'us1');
 * define('MAILCHIMP_LIST_ID', 'your-list-id');
 * define('MAILCHIMP_STORE_ID', 'your-store-id');
 */

// Register REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('cart-abandonment/v1', '/track', array(
        'methods' => 'POST',
        'callback' => 'handle_cart_abandonment',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => array(
            'eventType' => array(
                'required' => true,
                'type' => 'string',
                'validate_callback' => function($param) {
                    return in_array($param, ['cart_abandoned', 'email_updated', 'purchase_completed', 'manual_trigger']);
                }
            ),
            'email' => array(
                'required' => false,
                'type' => 'string',
                'validate_callback' => function($param) {
                    return empty($param) || is_email($param);
                }
            ),
            'sessionId' => array(
                'required' => true,
                'type' => 'string'
            ),
            'cart' => array(
                'required' => false,
                'type' => 'object'
            )
        )
    ));
});

/**
 * Main handler function
 */
function handle_cart_abandonment(WP_REST_Request $request) {
    $event_type = $request->get_param('eventType');
    $email = $request->get_param('email');
    $session_id = $request->get_param('sessionId');
    $cart = $request->get_param('cart');
    $cart_url = $request->get_param('cartUrl');
    $order_id = $request->get_param('orderId');
    $order_total = $request->get_param('orderTotal');

    // Validate configuration
    if (!defined('MAILCHIMP_API_KEY') || !defined('MAILCHIMP_LIST_ID') || !defined('MAILCHIMP_STORE_ID')) {
        return new WP_Error(
            'missing_config',
            'Mailchimp configuration missing',
            array('status' => 500)
        );
    }

    // Rate limiting
    if (!check_rate_limit($email ?: $session_id)) {
        return new WP_Error(
            'rate_limit',
            'Rate limit exceeded',
            array('status' => 429)
        );
    }

    // Log the event
    error_log(sprintf('[Cart Abandonment] %s event for %s', $event_type, $email ?: 'unknown'));

    try {
        switch ($event_type) {
            case 'cart_abandoned':
                if (empty($email)) {
                    return new WP_Error('missing_email', 'Email required', array('status' => 400));
                }
                if (empty($cart) || empty($cart['items'])) {
                    return new WP_Error('missing_cart', 'Cart data required', array('status' => 400));
                }

                // Create or update contact
                $contact_result = mailchimp_create_or_update_contact($email);
                if (is_wp_error($contact_result)) {
                    throw new Exception($contact_result->get_error_message());
                }

                // Ensure products exist
                mailchimp_ensure_products_exist($cart);

                // Create or update cart
                $cart_result = mailchimp_create_or_update_cart($email, $cart, $session_id, $cart_url);
                if (is_wp_error($cart_result)) {
                    throw new Exception($cart_result->get_error_message());
                }

                // Track event
                mailchimp_track_event($email, 'cart_abandoned', array(
                    'cart_total' => $cart['total'],
                    'item_count' => count($cart['items'])
                ));

                return array(
                    'success' => true,
                    'message' => 'Cart abandonment tracked'
                );

            case 'email_updated':
                if (empty($email)) {
                    return new WP_Error('missing_email', 'Email required', array('status' => 400));
                }

                $contact_result = mailchimp_create_or_update_contact($email);
                if (is_wp_error($contact_result)) {
                    throw new Exception($contact_result->get_error_message());
                }

                if (!empty($cart) && !empty($cart['items'])) {
                    mailchimp_ensure_products_exist($cart);
                    mailchimp_create_or_update_cart($email, $cart, $session_id, $cart_url);
                }

                return array(
                    'success' => true,
                    'message' => 'Email updated'
                );

            case 'purchase_completed':
                if (!empty($email) && !empty($session_id)) {
                    $delete_result = mailchimp_delete_cart($email, $session_id);

                    mailchimp_track_event($email, 'purchase_completed', array(
                        'order_id' => $order_id,
                        'order_total' => $order_total
                    ));

                    error_log(sprintf('[Cart Abandonment] Cart deleted after purchase for %s', $email));
                }

                return array(
                    'success' => true,
                    'message' => 'Purchase recorded, cart removed'
                );

            case 'manual_trigger':
                if (!empty($cart) && !empty($cart['items']) && !empty($email)) {
                    mailchimp_create_or_update_contact($email);
                    mailchimp_ensure_products_exist($cart);
                    mailchimp_create_or_update_cart($email, $cart, $session_id, $cart_url);
                }

                return array(
                    'success' => true,
                    'message' => 'Manual sync completed'
                );

            default:
                return new WP_Error('unknown_event', 'Unknown event type', array('status' => 400));
        }
    } catch (Exception $e) {
        error_log('[Cart Abandonment] Error: ' . $e->getMessage());

        return new WP_Error(
            'processing_error',
            'Error processing request: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}

/**
 * Simple rate limiting using transients
 */
function check_rate_limit($identifier) {
    $transient_key = 'cart_rate_limit_' . md5($identifier);
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

/**
 * Generate subscriber hash for Mailchimp
 */
function mailchimp_generate_subscriber_hash($email) {
    return md5(strtolower($email));
}

/**
 * Generate cart ID
 */
function mailchimp_generate_cart_id($session_id, $email) {
    return md5($session_id . '_' . $email);
}

/**
 * Make Mailchimp API request
 */
function mailchimp_api_request($endpoint, $method = 'GET', $data = null) {
    $api_key = defined('MAILCHIMP_API_KEY') ? MAILCHIMP_API_KEY : '';
    $server_prefix = defined('MAILCHIMP_SERVER_PREFIX') ? MAILCHIMP_SERVER_PREFIX : 'us1';

    $url = "https://{$server_prefix}.api.mailchimp.com/3.0/{$endpoint}";

    $args = array(
        'method' => $method,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );

    if ($data !== null) {
        $args['body'] = json_encode($data);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Handle errors
    if ($status_code >= 400) {
        error_log('[Mailchimp API] Error: ' . print_r($body, true));

        return new WP_Error(
            'mailchimp_error',
            isset($body['detail']) ? $body['detail'] : 'Mailchimp API error',
            array('status' => $status_code)
        );
    }

    return $body;
}

/**
 * Create or update contact in Mailchimp
 */
function mailchimp_create_or_update_contact($email, $first_name = '', $last_name = '') {
    $list_id = defined('MAILCHIMP_LIST_ID') ? MAILCHIMP_LIST_ID : '';
    $subscriber_hash = mailchimp_generate_subscriber_hash($email);

    // Try to get existing member
    $member = mailchimp_api_request("lists/{$list_id}/members/{$subscriber_hash}", 'GET');

    $member_data = array(
        'email_address' => $email,
        'status_if_new' => 'subscribed'
    );

    if (!empty($first_name) || !empty($last_name)) {
        $member_data['merge_fields'] = array(
            'FNAME' => $first_name,
            'LNAME' => $last_name
        );
    }

    if (is_wp_error($member)) {
        // Member doesn't exist, create
        if ($member->get_error_code() === 'mailchimp_error') {
            $member_data['status'] = 'subscribed';
            return mailchimp_api_request("lists/{$list_id}/members", 'POST', $member_data);
        }
        return $member;
    }

    // Member exists, update
    return mailchimp_api_request("lists/{$list_id}/members/{$subscriber_hash}", 'PATCH', $member_data);
}

/**
 * Create or update cart in Mailchimp
 */
function mailchimp_create_or_update_cart($email, $cart, $session_id, $checkout_url) {
    $store_id = defined('MAILCHIMP_STORE_ID') ? MAILCHIMP_STORE_ID : '';
    $cart_id = mailchimp_generate_cart_id($session_id, $email);
    $subscriber_hash = mailchimp_generate_subscriber_hash($email);

    $lines = array();
    foreach ($cart['items'] as $index => $item) {
        $lines[] = array(
            'id' => $cart_id . '_line_' . $index,
            'product_id' => (string) $item['productId'],
            'product_variant_id' => (string) ($item['variantId'] ?: $item['productId']),
            'quantity' => (int) $item['quantity'],
            'price' => (float) $item['price']
        );
    }

    $cart_data = array(
        'id' => $cart_id,
        'customer' => array(
            'id' => $subscriber_hash,
            'email_address' => $email,
            'opt_in_status' => true
        ),
        'currency_code' => isset($cart['currency']) ? $cart['currency'] : 'USD',
        'order_total' => (float) $cart['total'],
        'lines' => $lines
    );

    if (!empty($checkout_url)) {
        $cart_data['checkout_url'] = $checkout_url;
    }

    // Try to update existing cart
    $result = mailchimp_api_request("ecommerce/stores/{$store_id}/carts/{$cart_id}", 'PATCH', $cart_data);

    if (is_wp_error($result) && strpos($result->get_error_message(), 'not found') !== false) {
        // Cart doesn't exist, create
        return mailchimp_api_request("ecommerce/stores/{$store_id}/carts", 'POST', $cart_data);
    }

    return $result;
}

/**
 * Delete cart from Mailchimp
 */
function mailchimp_delete_cart($email, $session_id) {
    $store_id = defined('MAILCHIMP_STORE_ID') ? MAILCHIMP_STORE_ID : '';
    $cart_id = mailchimp_generate_cart_id($session_id, $email);

    $result = mailchimp_api_request("ecommerce/stores/{$store_id}/carts/{$cart_id}", 'DELETE');

    // If cart doesn't exist, that's fine
    if (is_wp_error($result) && strpos($result->get_error_message(), 'not found') !== false) {
        return array('deleted' => true);
    }

    return $result;
}

/**
 * Track event in Mailchimp
 */
function mailchimp_track_event($email, $event_name, $properties = array()) {
    $list_id = defined('MAILCHIMP_LIST_ID') ? MAILCHIMP_LIST_ID : '';
    $subscriber_hash = mailchimp_generate_subscriber_hash($email);

    $event_data = array(
        'name' => $event_name,
        'properties' => $properties
    );

    // Don't fail the request if event tracking fails
    $result = mailchimp_api_request("lists/{$list_id}/members/{$subscriber_hash}/events", 'POST', $event_data);

    if (is_wp_error($result)) {
        error_log('[Mailchimp] Failed to track event: ' . $result->get_error_message());
    }

    return $result;
}

/**
 * Ensure products exist in Mailchimp store
 */
function mailchimp_ensure_products_exist($cart) {
    if (empty($cart['items'])) {
        return;
    }

    $store_id = defined('MAILCHIMP_STORE_ID') ? MAILCHIMP_STORE_ID : '';

    foreach ($cart['items'] as $item) {
        $product_id = (string) $item['productId'];

        // Try to get product
        $product = mailchimp_api_request("ecommerce/stores/{$store_id}/products/{$product_id}", 'GET');

        if (is_wp_error($product) && strpos($product->get_error_message(), 'not found') !== false) {
            // Product doesn't exist, create it
            $product_data = array(
                'id' => $product_id,
                'title' => $item['name'],
                'description' => isset($item['description']) ? $item['description'] : $item['name'],
                'url' => isset($item['url']) ? $item['url'] : '',
                'variants' => array(
                    array(
                        'id' => (string) ($item['variantId'] ?: $item['productId']),
                        'title' => $item['name'],
                        'price' => (float) $item['price'],
                        'image_url' => isset($item['imageUrl']) ? $item['imageUrl'] : ''
                    )
                )
            );

            $create_result = mailchimp_api_request("ecommerce/stores/{$store_id}/products", 'POST', $product_data);

            if (is_wp_error($create_result)) {
                error_log('[Mailchimp] Failed to create product: ' . $create_result->get_error_message());
            }
        }
    }
}

/**
 * Add CORS headers for cross-origin requests
 */
add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
    if (strpos($request->get_route(), 'cart-abandonment') !== false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    return $served;
}, 10, 4);

/**
 * Handle OPTIONS preflight requests
 */
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        if ($request->get_method() === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
            exit;
        }
        return $served;
    }, 15, 4);
});

/**
 * Admin settings page (optional)
 */
add_action('admin_menu', function() {
    add_options_page(
        'Cart Abandonment Settings',
        'Cart Abandonment',
        'manage_options',
        'cart-abandonment-settings',
        'cart_abandonment_settings_page'
    );
});

function cart_abandonment_settings_page() {
    ?>
    <div class="wrap">
        <h1>Cart Abandonment Settings</h1>

        <h2>Configuration Status</h2>
        <table class="form-table">
            <tr>
                <th>Mailchimp API Key</th>
                <td><?php echo defined('MAILCHIMP_API_KEY') && !empty(MAILCHIMP_API_KEY) ? '✓ Configured' : '✗ Not configured'; ?></td>
            </tr>
            <tr>
                <th>List ID</th>
                <td><?php echo defined('MAILCHIMP_LIST_ID') && !empty(MAILCHIMP_LIST_ID) ? MAILCHIMP_LIST_ID : '✗ Not configured'; ?></td>
            </tr>
            <tr>
                <th>Store ID</th>
                <td><?php echo defined('MAILCHIMP_STORE_ID') && !empty(MAILCHIMP_STORE_ID) ? MAILCHIMP_STORE_ID : '✗ Not configured'; ?></td>
            </tr>
            <tr>
                <th>API Endpoint</th>
                <td><code><?php echo rest_url('cart-abandonment/v1/track'); ?></code></td>
            </tr>
        </table>

        <h2>Instructions</h2>
        <p>Add these constants to your <code>wp-config.php</code> file:</p>
        <pre>define('MAILCHIMP_API_KEY', 'your-api-key-here');
define('MAILCHIMP_SERVER_PREFIX', 'us1');
define('MAILCHIMP_LIST_ID', 'your-list-id');
define('MAILCHIMP_STORE_ID', 'your-store-id');</pre>

        <h2>Frontend Integration</h2>
        <p>Add this JavaScript to your storefront:</p>
        <pre>&lt;script&gt;
window.CartTracker.init({
    backendUrl: '<?php echo rest_url('cart-abandonment/v1/track'); ?>',
    abandonmentDelay: 30, // minutes
    storeId: '<?php echo defined('MAILCHIMP_STORE_ID') ? MAILCHIMP_STORE_ID : 'your-store-id'; ?>',
    debug: false
});
&lt;/script&gt;</pre>
    </div>
    <?php
}
