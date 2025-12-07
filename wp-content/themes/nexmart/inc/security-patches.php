<?php
/**
 * NexMart Critical Security Fixes
 * 
 * This file contains immediate security patches that should be applied
 * to the NexMart e-commerce platform.
 * 
 * Usage: Include this file in functions.php before other includes
 * require_once get_template_directory() . '/inc/security-patches.php';
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================
 * PATCH #1: Rate Limiting for AJAX Endpoints
 * =========================================
 */
class NexMart_Rate_Limiter {
    
    private static $limits = [
        'add_to_cart' => ['limit' => 30, 'window' => 60],
        'checkout' => ['limit' => 10, 'window' => 60],
        'login' => ['limit' => 5, 'window' => 300],
        'register' => ['limit' => 3, 'window' => 300],
        'search' => ['limit' => 60, 'window' => 60],
        'default' => ['limit' => 100, 'window' => 60],
    ];
    
    /**
     * Check rate limit for an action
     * 
     * @param string $action The action being rate limited
     * @param string $identifier Optional custom identifier (default: IP)
     * @return bool True if allowed, false if rate limited
     */
    public static function check($action, $identifier = null) {
        $identifier = $identifier ?: self::get_client_ip();
        $key = 'nexmart_rate_' . $action . '_' . md5($identifier);
        
        $config = self::$limits[$action] ?? self::$limits['default'];
        $count = get_transient($key);
        
        if ($count === false) {
            set_transient($key, 1, $config['window']);
            return true;
        }
        
        if ($count >= $config['limit']) {
            return false;
        }
        
        set_transient($key, $count + 1, $config['window']);
        return true;
    }
    
    /**
     * Enforce rate limit (sends JSON error if exceeded)
     */
    public static function enforce($action) {
        if (!self::check($action)) {
            wp_send_json_error([
                'message' => 'Too many requests. Please wait and try again.',
                'code' => 'rate_limited'
            ], 429);
            exit;
        }
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }
}

/**
 * =========================================
 * PATCH #2: Strict Nonce Verification Wrapper
 * =========================================
 */
class NexMart_Security {
    
    /**
     * Verify nonce strictly - dies on failure
     */
    public static function verify_nonce($nonce_value = null, $nonce_action = 'nexmart_nonce') {
        $nonce = $nonce_value ?: ($_POST['nonce'] ?? $_GET['nonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error([
                'message' => 'Security verification failed. Please refresh the page.',
                'code' => 'nonce_failed'
            ], 403);
            exit;
        }
        
        return true;
    }
    
    /**
     * Sanitize ORDER BY clause - whitelist approach
     */
    public static function sanitize_order_by($input, $allowed = null) {
        $default_allowed = [
            'newest' => 'created_at DESC',
            'oldest' => 'created_at ASC',
            'price_low' => 'price ASC',
            'price_high' => 'price DESC',
            'name' => 'name ASC',
            'name_desc' => 'name DESC',
            'rating' => 'rating DESC',
            'popular' => 'sales_count DESC',
            'stock' => 'stock_quantity ASC',
        ];
        
        $allowed = $allowed ?: $default_allowed;
        return $allowed[$input] ?? $allowed['newest'];
    }
    
    /**
     * Validate product ID
     */
    public static function validate_product_id($id) {
        $id = intval($id);
        
        if ($id <= 0) {
            wp_send_json_error([
                'message' => 'Invalid product ID.',
                'code' => 'invalid_product'
            ], 400);
            exit;
        }
        
        return $id;
    }
    
    /**
     * Sanitize search query
     */
    public static function sanitize_search($query, $max_length = 100) {
        $query = sanitize_text_field($query);
        $query = substr($query, 0, $max_length);
        $query = preg_replace('/[^\w\s\-]/', '', $query); // Only alphanumeric, spaces, hyphens
        return $query;
    }
    
    /**
     * Validate email with additional checks
     */
    public static function validate_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            return false;
        }
        
        // Block disposable email domains (basic list)
        $disposable_domains = [
            'tempmail.com', 'throwaway.email', 'mailinator.com',
            'guerrillamail.com', '10minutemail.com'
        ];
        
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (in_array($domain, $disposable_domains)) {
            return false;
        }
        
        return $email;
    }
}

/**
 * =========================================
 * PATCH #3: Database Transaction Wrapper
 * =========================================
 */
class NexMart_Transaction {
    
    private static $active = false;
    
    /**
     * Start a database transaction
     */
    public static function start() {
        global $wpdb;
        
        if (self::$active) {
            return false;
        }
        
        $wpdb->query('START TRANSACTION');
        self::$active = true;
        return true;
    }
    
    /**
     * Commit the transaction
     */
    public static function commit() {
        global $wpdb;
        
        if (!self::$active) {
            return false;
        }
        
        $wpdb->query('COMMIT');
        self::$active = false;
        return true;
    }
    
    /**
     * Rollback the transaction
     */
    public static function rollback() {
        global $wpdb;
        
        if (!self::$active) {
            return false;
        }
        
        $wpdb->query('ROLLBACK');
        self::$active = false;
        return true;
    }
    
    /**
     * Execute callback within transaction
     */
    public static function run(callable $callback) {
        self::start();
        
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }
    }
}

/**
 * =========================================
 * PATCH #4: Session Security Enhancement
 * =========================================
 */
function nexmart_secure_session_start() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', is_ssl() ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        
        session_start();
        
        // Session regeneration on login
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $session_user = $_SESSION['nexmart_user_id'] ?? null;
            
            if ($session_user !== $user_id) {
                session_regenerate_id(true);
                $_SESSION['nexmart_user_id'] = $user_id;
            }
        }
    }
}

// Replace original session start
remove_action('init', 'nexmart_start_session', 1);
add_action('init', 'nexmart_secure_session_start', 1);

/**
 * =========================================
 * PATCH #5: Input Sanitization Helpers
 * =========================================
 */
class NexMart_Input {
    
    /**
     * Get and sanitize POST value
     */
    public static function post($key, $type = 'string', $default = null) {
        if (!isset($_POST[$key])) {
            return $default;
        }
        
        return self::sanitize($_POST[$key], $type);
    }
    
    /**
     * Get and sanitize GET value
     */
    public static function get($key, $type = 'string', $default = null) {
        if (!isset($_GET[$key])) {
            return $default;
        }
        
        return self::sanitize($_GET[$key], $type);
    }
    
    /**
     * Sanitize value based on type
     */
    public static function sanitize($value, $type) {
        switch ($type) {
            case 'int':
            case 'integer':
                return intval($value);
                
            case 'float':
            case 'decimal':
                return floatval($value);
                
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'html':
                return wp_kses_post($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'slug':
                return sanitize_title($value);
                
            case 'array':
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];
                
            case 'json':
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                
            case 'string':
            default:
                return sanitize_text_field($value);
        }
    }
}

/**
 * =========================================
 * PATCH #6: Logging for Security Events
 * =========================================
 */
class NexMart_Security_Log {
    
    private static $log_file = null;
    
    private static function get_log_file() {
        if (self::$log_file === null) {
            $log_dir = WP_CONTENT_DIR . '/nexmart-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
            }
            self::$log_file = $log_dir . '/security-' . date('Y-m-d') . '.log';
        }
        return self::$log_file;
    }
    
    /**
     * Log a security event
     */
    public static function log($event, $data = [], $severity = 'info') {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => strtoupper($severity),
            'event' => $event,
            'ip' => NexMart_Rate_Limiter::get_client_ip(),
            'user_id' => get_current_user_id() ?: 'guest',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => $data,
        ];
        
        $log_line = json_encode($log_entry) . "\n";
        file_put_contents(self::get_log_file(), $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log failed login attempt
     */
    public static function failed_login($username) {
        self::log('failed_login', ['username' => $username], 'warning');
    }
    
    /**
     * Log rate limit hit
     */
    public static function rate_limited($action) {
        self::log('rate_limited', ['action' => $action], 'warning');
    }
    
    /**
     * Log suspicious activity
     */
    public static function suspicious($description, $data = []) {
        self::log('suspicious', array_merge(['description' => $description], $data), 'alert');
    }
}

/**
 * =========================================
 * Apply Rate Limiting to Existing Hooks
 * =========================================
 */
function nexmart_apply_rate_limits() {
    // Add rate limiting checks before AJAX handlers
    add_action('wp_ajax_nopriv_nexmart_add_to_cart', function() {
        NexMart_Rate_Limiter::enforce('add_to_cart');
    }, 1);
    
    add_action('wp_ajax_nexmart_add_to_cart', function() {
        NexMart_Rate_Limiter::enforce('add_to_cart');
    }, 1);
    
    add_action('wp_ajax_nopriv_nexmart_login', function() {
        NexMart_Rate_Limiter::enforce('login');
    }, 1);
    
    add_action('wp_ajax_nopriv_nexmart_register', function() {
        NexMart_Rate_Limiter::enforce('register');
    }, 1);
    
    add_action('wp_ajax_nexmart_create_order', function() {
        NexMart_Rate_Limiter::enforce('checkout');
    }, 1);
    
    add_action('wp_ajax_nopriv_nexmart_live_search', function() {
        NexMart_Rate_Limiter::enforce('search');
    }, 1);
}
add_action('init', 'nexmart_apply_rate_limits', 2);

/**
 * Log failed login attempts
 */
add_action('wp_login_failed', function($username) {
    NexMart_Security_Log::failed_login($username);
});

/**
 * =========================================
 * USAGE EXAMPLES
 * =========================================
 * 
 * // In AJAX handlers:
 * public function ajax_toggle_wishlist() {
 *     NexMart_Security::verify_nonce(); // Strict verification
 *     $product_id = NexMart_Security::validate_product_id($_POST['product_id']);
 *     // ... rest of logic
 * }
 * 
 * // In order creation:
 * public function create_order($data) {
 *     return NexMart_Transaction::run(function() use ($data) {
 *         // All database operations here
 *         // Auto-rollback on exception
 *         return $order_id;
 *     });
 * }
 * 
 * // Getting sanitized input:
 * $product_id = NexMart_Input::post('product_id', 'int', 0);
 * $email = NexMart_Input::post('email', 'email');
 * $description = NexMart_Input::post('description', 'html');
 */
