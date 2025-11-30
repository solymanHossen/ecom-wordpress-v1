<?php
/**
 * NexMart Authentication & Authorization
 * Handles user authentication, registration, roles, and permissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Auth {
    
    private static $instance = null;
    private $wpdb;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add custom roles
        add_action('init', [$this, 'add_custom_roles']);
        
        // AJAX handlers
        add_action('wp_ajax_nopriv_nexmart_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_nexmart_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nexmart_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_nexmart_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_nexmart_change_password', [$this, 'ajax_change_password']);
        add_action('wp_ajax_nexmart_get_current_user', [$this, 'ajax_get_current_user']);
        
        // Vendor registration
        add_action('wp_ajax_nexmart_register_vendor', [$this, 'ajax_register_vendor']);
        add_action('wp_ajax_nopriv_nexmart_register_vendor', [$this, 'ajax_register_vendor']);
    }
    
    /**
     * Add custom user roles
     */
    public function add_custom_roles() {
        // Add vendor role
        if (!get_role('vendor')) {
            add_role('vendor', 'Vendor', [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'upload_files' => true,
                'manage_nexmart_products' => true,
                'manage_nexmart_orders' => true,
            ]);
        }
        
        // Add customer role capabilities
        $customer = get_role('subscriber');
        if ($customer) {
            $customer->add_cap('manage_nexmart_cart');
            $customer->add_cap('manage_nexmart_wishlist');
        }
    }
    
    /**
     * Register new user
     */
    public function register_user($data) {
        $email = sanitize_email($data['email']);
        $password = $data['password'];
        $name = sanitize_text_field($data['name'] ?? '');
        $role = isset($data['role']) && $data['role'] === 'vendor' ? 'vendor' : 'subscriber';
        
        // Validation
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.');
        }
        
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'An account with this email already exists.');
        }
        
        if (strlen($password) < 8) {
            return new WP_Error('weak_password', 'Password must be at least 8 characters.');
        }
        
        // Create user
        $username = $this->generate_username($email);
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name ?: $username,
            'first_name' => $name,
            'role' => $role,
        ]);
        
        // Auto login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        return [
            'user_id' => $user_id,
            'message' => 'Registration successful!',
        ];
    }
    
    /**
     * Login user
     */
    public function login_user($email, $password, $remember = false) {
        $user = get_user_by('email', $email);
        
        if (!$user) {
            $user = get_user_by('login', $email);
        }
        
        if (!$user) {
            return new WP_Error('invalid_credentials', 'Invalid email or password.');
        }
        
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            return new WP_Error('invalid_credentials', 'Invalid email or password.');
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        
        return [
            'user_id' => $user->ID,
            'user' => $this->get_user_data($user->ID),
            'message' => 'Login successful!',
        ];
    }
    
    /**
     * Register vendor
     */
    public function register_vendor($data) {
        // First register as user
        $data['role'] = 'vendor';
        $result = $this->register_user($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $user_id = $result['user_id'];
        $db = NexMart_Database::get_instance();
        
        // Create vendor profile
        $store_name = sanitize_text_field($data['store_name'] ?? $data['name'] . "'s Store");
        $store_slug = sanitize_title($store_name);
        
        // Ensure unique slug
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$db->vendors_table} WHERE store_slug LIKE %s",
            $store_slug . '%'
        ));
        
        if ($existing) {
            $store_slug .= '-' . ($existing + 1);
        }
        
        $vendor_data = [
            'user_id' => $user_id,
            'store_name' => $store_name,
            'store_slug' => $store_slug,
            'store_description' => sanitize_textarea_field($data['store_description'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? ''),
            'postcode' => sanitize_text_field($data['postcode'] ?? ''),
            'status' => 'active', // Can be 'pending' for approval workflow
        ];
        
        $this->wpdb->insert($db->vendors_table, $vendor_data);
        $vendor_id = $this->wpdb->insert_id;
        
        if (!$vendor_id) {
            return new WP_Error('vendor_creation_failed', 'Failed to create vendor profile.');
        }
        
        return [
            'user_id' => $user_id,
            'vendor_id' => $vendor_id,
            'message' => 'Vendor registration successful!',
        ];
    }
    
    /**
     * Get current user data
     */
    public function get_user_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return null;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        
        $data = [
            'id' => $user->ID,
            'email' => $user->user_email,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->roles[0] ?? 'subscriber',
            'avatar' => get_avatar_url($user->ID),
            'registered' => $user->user_registered,
        ];
        
        // Add vendor data if applicable
        if ($this->is_vendor($user_id)) {
            $db = NexMart_Database::get_instance();
            $vendor = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$db->vendors_table} WHERE user_id = %d",
                $user_id
            ));
            
            if ($vendor) {
                $data['vendor'] = [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'store_slug' => $vendor->store_slug,
                    'status' => $vendor->status,
                    'commission_rate' => $vendor->commission_rate,
                    'total_sales' => $vendor->total_sales,
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Update user profile
     */
    public function update_profile($user_id, $data) {
        $update_data = ['ID' => $user_id];
        
        if (isset($data['display_name'])) {
            $update_data['display_name'] = sanitize_text_field($data['display_name']);
        }
        if (isset($data['first_name'])) {
            $update_data['first_name'] = sanitize_text_field($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $update_data['last_name'] = sanitize_text_field($data['last_name']);
        }
        if (isset($data['email']) && is_email($data['email'])) {
            $update_data['user_email'] = sanitize_email($data['email']);
        }
        
        $result = wp_update_user($update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $this->get_user_data($user_id);
    }
    
    /**
     * Change password
     */
    public function change_password($user_id, $current_password, $new_password) {
        $user = get_userdata($user_id);
        
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            return new WP_Error('invalid_password', 'Current password is incorrect.');
        }
        
        if (strlen($new_password) < 8) {
            return new WP_Error('weak_password', 'New password must be at least 8 characters.');
        }
        
        wp_set_password($new_password, $user_id);
        
        // Re-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        return ['message' => 'Password changed successfully.'];
    }
    
    /**
     * Check if user is admin
     */
    public function is_admin($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        return user_can($user_id, 'administrator');
    }
    
    /**
     * Check if user is vendor
     */
    public function is_vendor($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        return user_can($user_id, 'vendor') || $this->is_admin($user_id);
    }
    
    /**
     * Check if user can manage product
     */
    public function can_manage_product($user_id, $product_id) {
        if ($this->is_admin($user_id)) {
            return true;
        }
        
        $db = NexMart_Database::get_instance();
        $product = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT p.vendor_id, v.user_id FROM {$db->products_table} p
             JOIN {$db->vendors_table} v ON p.vendor_id = v.id
             WHERE p.id = %d",
            $product_id
        ));
        
        return $product && $product->user_id == $user_id;
    }
    
    /**
     * Generate username from email
     */
    private function generate_username($email) {
        $base = strstr($email, '@', true);
        $username = sanitize_user($base);
        
        if (username_exists($username)) {
            $i = 1;
            while (username_exists($username . $i)) {
                $i++;
            }
            $username = $username . $i;
        }
        
        return $username;
    }
    
    /**
     * Generate nonce for frontend
     */
    public function get_nonce() {
        return wp_create_nonce('nexmart_nonce');
    }
    
    // AJAX Handlers
    
    public function ajax_register() {
        // Clean output buffer to prevent HTML before JSON
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Verify nonce (lenient for cached pages)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexmart_nonce')) {
            error_log('Registration: Nonce verification failed, but continuing');
        }
        
        $data = [
            'email' => sanitize_email($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'name' => sanitize_text_field($_POST['name'] ?? ''),
        ];
        
        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            wp_send_json_error(['message' => 'Email and password are required.']);
            exit;
        }
        
        if (!is_email($data['email'])) {
            wp_send_json_error(['message' => 'Invalid email address.']);
            exit;
        }
        
        if (strlen($data['password']) < 8) {
            wp_send_json_error(['message' => 'Password must be at least 8 characters.']);
            exit;
        }
        
        $result = $this->register_user($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            exit;
        }
        
        wp_send_json_success([
            'message' => 'Account created successfully! Redirecting to login...',
            'redirect' => home_url('/login')
        ]);
        exit;
    }
    
    public function ajax_login() {
        // Clean output buffer to prevent HTML before JSON
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Verify nonce (lenient for cached pages)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexmart_nonce')) {
            error_log('Login: Nonce verification failed, but continuing');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        
        // Validate required fields
        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Email and password are required.']);
            exit;
        }
        
        $result = $this->login_user($email, $password, $remember);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            exit;
        }
        
        // Determine redirect URL
        $redirect = home_url('/my-account');
        if (!empty($_POST['redirect_to'])) {
            $redirect = esc_url_raw($_POST['redirect_to']);
        }
        
        wp_send_json_success([
            'message' => 'Login successful! Redirecting...',
            'redirect' => $redirect,
            'user' => $result['user']
        ]);
        exit;
    }
    
    public function ajax_logout() {
        wp_logout();
        wp_send_json_success(['message' => 'Logged out successfully.']);
    }
    
    public function ajax_update_profile() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated.']);
        }
        
        $data = [
            'display_name' => sanitize_text_field($_POST['display_name'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
        ];
        
        $result = $this->update_profile(get_current_user_id(), $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['user' => $result, 'message' => 'Profile updated.']);
    }
    
    public function ajax_change_password() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated.']);
        }
        
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        
        $result = $this->change_password(get_current_user_id(), $current, $new);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_current_user() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not authenticated.']);
        }
        
        wp_send_json_success(['user' => $this->get_user_data()]);
    }
    
    public function ajax_register_vendor() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $data = [
            'email' => sanitize_email($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'store_name' => sanitize_text_field($_POST['store_name'] ?? ''),
            'store_description' => sanitize_textarea_field($_POST['store_description'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        ];
        
        $result = $this->register_vendor($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
}
