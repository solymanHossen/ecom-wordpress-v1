<?php
/**
 * NexMart Campaigns Handler
 * Manages flash sales, promotions, and marketing campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Campaigns {
    
    private static $instance = null;
    private $wpdb;
    private $db;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db = NexMart_Database::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_nexmart_get_campaigns', [$this, 'ajax_get_campaigns']);
        add_action('wp_ajax_nopriv_nexmart_get_campaigns', [$this, 'ajax_get_campaigns']);
        add_action('wp_ajax_nexmart_get_campaign', [$this, 'ajax_get_campaign']);
        add_action('wp_ajax_nopriv_nexmart_get_campaign', [$this, 'ajax_get_campaign']);
        add_action('wp_ajax_nexmart_create_campaign', [$this, 'ajax_create_campaign']);
        add_action('wp_ajax_nexmart_update_campaign', [$this, 'ajax_update_campaign']);
        add_action('wp_ajax_nexmart_delete_campaign', [$this, 'ajax_delete_campaign']);
        add_action('wp_ajax_nexmart_add_campaign_products', [$this, 'ajax_add_campaign_products']);
        add_action('wp_ajax_nexmart_remove_campaign_product', [$this, 'ajax_remove_campaign_product']);
    }
    
    /**
     * Get active campaigns
     */
    public function get_active_campaigns($limit = 10) {
        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->campaigns_table} 
             WHERE status = 'active' 
             AND start_date <= NOW() 
             AND end_date >= NOW()
             ORDER BY featured DESC, created_at DESC
             LIMIT %d",
            $limit
        ));
        
        foreach ($campaigns as &$campaign) {
            $campaign->products = $this->get_campaign_products($campaign->id, 4);
            $campaign->product_count = $this->get_campaign_product_count($campaign->id);
            $campaign->time_remaining = $this->get_time_remaining($campaign->end_date);
        }
        
        return $campaigns;
    }
    
    /**
     * Get all campaigns (for admin)
     */
    public function get_all_campaigns($status = null, $limit = 20, $offset = 0) {
        $where = [];
        $params = [];
        
        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params[] = $limit;
        $params[] = $offset;
        
        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->campaigns_table} 
             {$where_clause}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        foreach ($campaigns as &$campaign) {
            $campaign->product_count = $this->get_campaign_product_count($campaign->id);
        }
        
        return $campaigns;
    }
    
    /**
     * Get single campaign
     */
    public function get_campaign($id, $by = 'id') {
        $field = $by === 'slug' ? 'slug' : 'id';
        $placeholder = $by === 'slug' ? '%s' : '%d';
        
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->campaigns_table} WHERE {$field} = {$placeholder}",
            $id
        ));
        
        if ($campaign) {
            $campaign->products = $this->get_campaign_products($campaign->id);
            $campaign->product_count = count($campaign->products);
            $campaign->time_remaining = $this->get_time_remaining($campaign->end_date);
        }
        
        return $campaign;
    }
    
    /**
     * Get featured campaign (for homepage banner)
     */
    public function get_featured_campaign() {
        $campaign = $this->wpdb->get_row(
            "SELECT * FROM {$this->db->campaigns_table} 
             WHERE status = 'active' 
             AND featured = 1
             AND start_date <= NOW() 
             AND end_date >= NOW()
             ORDER BY created_at DESC
             LIMIT 1"
        );
        
        if ($campaign) {
            $campaign->products = $this->get_campaign_products($campaign->id, 8);
            $campaign->time_remaining = $this->get_time_remaining($campaign->end_date);
        }
        
        return $campaign;
    }
    
    /**
     * Get campaign products
     */
    public function get_campaign_products($campaign_id, $limit = null) {
        $limit_clause = $limit ? $this->wpdb->prepare("LIMIT %d", $limit) : '';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, cp.id as campaign_product_id, c.name as category_name,
                    cam.discount_type, cam.discount_value,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->campaign_products_table} cp
             JOIN {$this->db->products_table} p ON cp.product_id = p.id
             JOIN {$this->db->campaigns_table} cam ON cp.campaign_id = cam.id
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE cp.campaign_id = %d AND p.status = 'active'
             ORDER BY cp.created_at DESC {$limit_clause}",
            $campaign_id
        ));
    }
    
    /**
     * Get campaign product count
     */
    public function get_campaign_product_count($campaign_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->campaign_products_table} cp
             JOIN {$this->db->products_table} p ON cp.product_id = p.id
             WHERE cp.campaign_id = %d AND p.status = 'active'",
            $campaign_id
        ));
    }
    
    /**
     * Calculate discounted price for campaign product
     */
    public function get_campaign_price($product, $campaign = null) {
        if (!$campaign) {
            // Check if product is in any active campaign
            $campaign = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT c.* FROM {$this->db->campaigns_table} c
                 JOIN {$this->db->campaign_products_table} cp ON c.id = cp.campaign_id
                 WHERE cp.product_id = %d 
                 AND c.status = 'active'
                 AND c.start_date <= NOW() 
                 AND c.end_date >= NOW()
                 LIMIT 1",
                $product->id
            ));
        }
        
        if (!$campaign) {
            return $product->sale_price ?: $product->price;
        }
        
        $base_price = $product->sale_price ?: $product->price;
        
        switch ($campaign->discount_type) {
            case 'percentage':
                $discount = $base_price * ($campaign->discount_value / 100);
                return max(0, $base_price - $discount);
            case 'fixed':
                return max(0, $base_price - $campaign->discount_value);
            default:
                return $base_price;
        }
    }
    
    /**
     * Get time remaining for campaign
     */
    public function get_time_remaining($end_date) {
        $now = time();
        $end = strtotime($end_date);
        $diff = $end - $now;
        
        if ($diff <= 0) {
            return null;
        }
        
        return [
            'total_seconds' => $diff,
            'days' => floor($diff / 86400),
            'hours' => floor(($diff % 86400) / 3600),
            'minutes' => floor(($diff % 3600) / 60),
            'seconds' => $diff % 60,
        ];
    }
    
    /**
     * Create campaign
     */
    public function create_campaign($data) {
        $campaign_data = [
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'banner_image' => esc_url_raw($data['banner_image'] ?? ''),
            'discount_type' => in_array($data['discount_type'], ['percentage', 'fixed']) ? $data['discount_type'] : 'percentage',
            'discount_value' => floatval($data['discount_value'] ?? 0),
            'start_date' => sanitize_text_field($data['start_date']),
            'end_date' => sanitize_text_field($data['end_date']),
            'status' => in_array($data['status'], ['active', 'inactive', 'scheduled']) ? $data['status'] : 'inactive',
            'featured' => !empty($data['featured']) ? 1 : 0,
        ];
        
        // Ensure unique slug
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->campaigns_table} WHERE slug LIKE %s",
            $campaign_data['slug'] . '%'
        ));
        
        if ($existing) {
            $campaign_data['slug'] .= '-' . ($existing + 1);
        }
        
        $this->wpdb->insert($this->db->campaigns_table, $campaign_data);
        $campaign_id = $this->wpdb->insert_id;
        
        if (!$campaign_id) {
            return new WP_Error('create_failed', 'Failed to create campaign.');
        }
        
        // Add products if provided
        if (!empty($data['product_ids'])) {
            foreach ($data['product_ids'] as $product_id) {
                $this->add_product_to_campaign($campaign_id, intval($product_id));
            }
        }
        
        return $this->get_campaign($campaign_id);
    }
    
    /**
     * Update campaign
     */
    public function update_campaign($campaign_id, $data) {
        $update = [];
        
        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['banner_image'])) {
            $update['banner_image'] = esc_url_raw($data['banner_image']);
        }
        if (isset($data['discount_type'])) {
            $update['discount_type'] = in_array($data['discount_type'], ['percentage', 'fixed']) ? $data['discount_type'] : 'percentage';
        }
        if (isset($data['discount_value'])) {
            $update['discount_value'] = floatval($data['discount_value']);
        }
        if (isset($data['start_date'])) {
            $update['start_date'] = sanitize_text_field($data['start_date']);
        }
        if (isset($data['end_date'])) {
            $update['end_date'] = sanitize_text_field($data['end_date']);
        }
        if (isset($data['status'])) {
            $update['status'] = in_array($data['status'], ['active', 'inactive', 'scheduled']) ? $data['status'] : 'inactive';
        }
        if (isset($data['featured'])) {
            $update['featured'] = !empty($data['featured']) ? 1 : 0;
        }
        
        if (empty($update)) {
            return new WP_Error('no_changes', 'No changes to update.');
        }
        
        $this->wpdb->update(
            $this->db->campaigns_table,
            $update,
            ['id' => $campaign_id]
        );
        
        return $this->get_campaign($campaign_id);
    }
    
    /**
     * Delete campaign
     */
    public function delete_campaign($campaign_id) {
        // Delete campaign products first
        $this->wpdb->delete(
            $this->db->campaign_products_table,
            ['campaign_id' => $campaign_id]
        );
        
        // Delete campaign
        $this->wpdb->delete(
            $this->db->campaigns_table,
            ['id' => $campaign_id]
        );
        
        return true;
    }
    
    /**
     * Add product to campaign
     */
    public function add_product_to_campaign($campaign_id, $product_id) {
        // Check if already exists
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->db->campaign_products_table} 
             WHERE campaign_id = %d AND product_id = %d",
            $campaign_id, $product_id
        ));
        
        if ($exists) {
            return true;
        }
        
        $this->wpdb->insert($this->db->campaign_products_table, [
            'campaign_id' => $campaign_id,
            'product_id' => $product_id,
        ]);
        
        return $this->wpdb->insert_id > 0;
    }
    
    /**
     * Remove product from campaign
     */
    public function remove_product_from_campaign($campaign_id, $product_id) {
        $this->wpdb->delete(
            $this->db->campaign_products_table,
            [
                'campaign_id' => $campaign_id,
                'product_id' => $product_id,
            ]
        );
        
        return true;
    }
    
    /**
     * Check if product is in active campaign
     */
    public function is_product_in_campaign($product_id) {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT c.id FROM {$this->db->campaigns_table} c
             JOIN {$this->db->campaign_products_table} cp ON c.id = cp.campaign_id
             WHERE cp.product_id = %d 
             AND c.status = 'active'
             AND c.start_date <= NOW() 
             AND c.end_date >= NOW()
             LIMIT 1",
            $product_id
        ));
    }
    
    // AJAX Handlers
    
    public function ajax_get_campaigns() {
        $active_only = isset($_GET['active']) && $_GET['active'] === 'true';
        
        if ($active_only) {
            $campaigns = $this->get_active_campaigns();
        } else {
            $campaigns = $this->get_all_campaigns();
        }
        
        wp_send_json_success(['campaigns' => $campaigns]);
    }
    
    public function ajax_get_campaign() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : null;
        
        if ($slug) {
            $campaign = $this->get_campaign($slug, 'slug');
        } elseif ($id) {
            $campaign = $this->get_campaign($id);
        } else {
            wp_send_json_error(['message' => 'Campaign ID or slug required.']);
        }
        
        if (!$campaign) {
            wp_send_json_error(['message' => 'Campaign not found.']);
        }
        
        wp_send_json_success(['campaign' => $campaign]);
    }
    
    public function ajax_create_campaign() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'banner_image' => esc_url_raw($_POST['banner_image'] ?? ''),
            'discount_type' => sanitize_text_field($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => floatval($_POST['discount_value'] ?? 0),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'inactive'),
            'featured' => !empty($_POST['featured']),
            'product_ids' => isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [],
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Campaign name is required.']);
        }
        
        $result = $this->create_campaign($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['campaign' => $result, 'message' => 'Campaign created successfully!']);
    }
    
    public function ajax_update_campaign() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if (!$campaign_id) {
            wp_send_json_error(['message' => 'Campaign ID required.']);
        }
        
        $data = [];
        
        if (isset($_POST['name'])) $data['name'] = $_POST['name'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['banner_image'])) $data['banner_image'] = $_POST['banner_image'];
        if (isset($_POST['discount_type'])) $data['discount_type'] = $_POST['discount_type'];
        if (isset($_POST['discount_value'])) $data['discount_value'] = $_POST['discount_value'];
        if (isset($_POST['start_date'])) $data['start_date'] = $_POST['start_date'];
        if (isset($_POST['end_date'])) $data['end_date'] = $_POST['end_date'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['featured'])) $data['featured'] = $_POST['featured'];
        
        $result = $this->update_campaign($campaign_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['campaign' => $result, 'message' => 'Campaign updated successfully!']);
    }
    
    public function ajax_delete_campaign() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if (!$campaign_id) {
            wp_send_json_error(['message' => 'Campaign ID required.']);
        }
        
        $this->delete_campaign($campaign_id);
        wp_send_json_success(['message' => 'Campaign deleted successfully!']);
    }
    
    public function ajax_add_campaign_products() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [];
        
        if (!$campaign_id || empty($product_ids)) {
            wp_send_json_error(['message' => 'Campaign ID and product IDs required.']);
        }
        
        foreach ($product_ids as $product_id) {
            $this->add_product_to_campaign($campaign_id, $product_id);
        }
        
        $campaign = $this->get_campaign($campaign_id);
        wp_send_json_success(['campaign' => $campaign, 'message' => 'Products added to campaign!']);
    }
    
    public function ajax_remove_campaign_product() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$campaign_id || !$product_id) {
            wp_send_json_error(['message' => 'Campaign ID and product ID required.']);
        }
        
        $this->remove_product_from_campaign($campaign_id, $product_id);
        
        $campaign = $this->get_campaign($campaign_id);
        wp_send_json_success(['campaign' => $campaign, 'message' => 'Product removed from campaign!']);
    }
}
