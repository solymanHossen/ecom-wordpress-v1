<?php
/**
 * NexMart Vendor Dashboard Handler
 * Manages vendor product management, orders, and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Vendor_Dashboard {
    
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
        // Product management
        add_action('wp_ajax_nexmart_vendor_add_product', [$this, 'ajax_add_product']);
        add_action('wp_ajax_nexmart_vendor_update_product', [$this, 'ajax_update_product']);
        add_action('wp_ajax_nexmart_vendor_delete_product', [$this, 'ajax_delete_product']);
        add_action('wp_ajax_nexmart_vendor_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_nexmart_vendor_upload_image', [$this, 'ajax_upload_image']);
        
        // Order management
        add_action('wp_ajax_nexmart_vendor_get_orders', [$this, 'ajax_get_orders']);
        add_action('wp_ajax_nexmart_vendor_update_order', [$this, 'ajax_update_order']);
        
        // Dashboard stats
        add_action('wp_ajax_nexmart_vendor_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_nexmart_vendor_get_analytics', [$this, 'ajax_get_analytics']);
        
        // Profile management
        add_action('wp_ajax_nexmart_vendor_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_nexmart_vendor_get_profile', [$this, 'ajax_get_profile']);
    }
    
    /**
     * Get current vendor ID
     */
    private function get_current_vendor_id() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }
        
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->db->vendors_table} WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get vendor profile
     */
    public function get_vendor_profile($vendor_id = null) {
        if (!$vendor_id) {
            $vendor_id = $this->get_current_vendor_id();
        }
        
        if (!$vendor_id) {
            return null;
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT v.*, u.user_email, u.display_name
             FROM {$this->db->vendors_table} v
             JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE v.id = %d",
            $vendor_id
        ));
    }
    
    /**
     * Update vendor profile
     */
    public function update_vendor_profile($vendor_id, $data) {
        $update = [];
        
        $allowed_fields = [
            'store_name', 'store_description', 'logo_url', 'banner_url',
            'phone', 'address', 'city', 'state', 'country', 'postcode',
            'social_facebook', 'social_twitter', 'social_instagram'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        if (empty($update)) {
            return new WP_Error('no_changes', 'No changes to update.');
        }
        
        $this->wpdb->update(
            $this->db->vendors_table,
            $update,
            ['id' => $vendor_id]
        );
        
        return $this->get_vendor_profile($vendor_id);
    }
    
    /**
     * Get vendor products
     */
    public function get_vendor_products($vendor_id, $args = []) {
        $defaults = [
            'status' => null,
            'search' => null,
            'category_id' => null,
            'sort' => 'newest',
            'limit' => 20,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['p.vendor_id = %d'];
        $params = [$vendor_id];
        
        if ($args['status']) {
            $where[] = 'p.status = %s';
            $params[] = $args['status'];
        }
        
        if ($args['search']) {
            $where[] = '(p.name LIKE %s OR p.sku LIKE %s)';
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        if ($args['category_id']) {
            $where[] = 'p.category_id = %d';
            $params[] = $args['category_id'];
        }
        
        $order = match($args['sort']) {
            'oldest' => 'p.created_at ASC',
            'name' => 'p.name ASC',
            'price_low' => 'p.price ASC',
            'price_high' => 'p.price DESC',
            'stock' => 'p.stock_quantity ASC',
            default => 'p.created_at DESC',
        };
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $count_sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->products_table} p WHERE {$where_clause}",
            ...$params
        );
        $total = $this->wpdb->get_var($count_sql);
        
        // Get products
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE {$where_clause}
             ORDER BY {$order}
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        return [
            'products' => $products,
            'total' => (int)$total,
            'pages' => ceil($total / $args['limit']),
        ];
    }
    
    /**
     * Add new product
     */
    public function add_product($vendor_id, $data) {
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', 'Product name is required.');
        }
        
        if (empty($data['price']) || $data['price'] <= 0) {
            return new WP_Error('invalid_price', 'Valid price is required.');
        }
        
        $slug = sanitize_title($data['name']);
        
        // Ensure unique slug
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->products_table} WHERE slug LIKE %s",
            $slug . '%'
        ));
        
        if ($existing) {
            $slug .= '-' . ($existing + 1);
        }
        
        $product_data = [
            'vendor_id' => $vendor_id,
            'category_id' => intval($data['category_id'] ?? 0),
            'name' => sanitize_text_field($data['name']),
            'slug' => $slug,
            'description' => wp_kses_post($data['description'] ?? ''),
            'short_description' => sanitize_textarea_field($data['short_description'] ?? ''),
            'price' => floatval($data['price']),
            'sale_price' => !empty($data['sale_price']) ? floatval($data['sale_price']) : null,
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'stock_quantity' => isset($data['stock_quantity']) ? intval($data['stock_quantity']) : null,
            'weight' => !empty($data['weight']) ? floatval($data['weight']) : null,
            'length' => !empty($data['length']) ? floatval($data['length']) : null,
            'width' => !empty($data['width']) ? floatval($data['width']) : null,
            'height' => !empty($data['height']) ? floatval($data['height']) : null,
            'status' => in_array($data['status'] ?? '', ['active', 'draft', 'inactive']) ? $data['status'] : 'draft',
            'featured' => !empty($data['featured']) ? 1 : 0,
        ];
        
        $this->wpdb->insert($this->db->products_table, $product_data);
        $product_id = $this->wpdb->insert_id;
        
        if (!$product_id) {
            return new WP_Error('create_failed', 'Failed to create product.');
        }
        
        // Add images
        if (!empty($data['images'])) {
            foreach ($data['images'] as $i => $image_url) {
                $this->wpdb->insert($this->db->product_images_table, [
                    'product_id' => $product_id,
                    'image_url' => esc_url_raw($image_url),
                    'sort_order' => $i,
                ]);
            }
        }
        
        // Add attributes
        if (!empty($data['attributes'])) {
            foreach ($data['attributes'] as $attr) {
                $this->wpdb->insert($this->db->product_attributes_table, [
                    'product_id' => $product_id,
                    'attribute_name' => sanitize_text_field($attr['name']),
                    'attribute_value' => sanitize_text_field($attr['value']),
                ]);
            }
        }
        
        return $this->get_product($product_id);
    }
    
    /**
     * Update product
     */
    public function update_product($vendor_id, $product_id, $data) {
        // Verify ownership
        $product = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->products_table} WHERE id = %d AND vendor_id = %d",
            $product_id, $vendor_id
        ));
        
        if (!$product) {
            return new WP_Error('not_found', 'Product not found or access denied.');
        }
        
        $update = [];
        
        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['category_id'])) {
            $update['category_id'] = intval($data['category_id']);
        }
        if (isset($data['description'])) {
            $update['description'] = wp_kses_post($data['description']);
        }
        if (isset($data['short_description'])) {
            $update['short_description'] = sanitize_textarea_field($data['short_description']);
        }
        if (isset($data['price'])) {
            $update['price'] = floatval($data['price']);
        }
        if (isset($data['sale_price'])) {
            $update['sale_price'] = !empty($data['sale_price']) ? floatval($data['sale_price']) : null;
        }
        if (isset($data['sku'])) {
            $update['sku'] = sanitize_text_field($data['sku']);
        }
        if (isset($data['stock_quantity'])) {
            $update['stock_quantity'] = $data['stock_quantity'] !== '' ? intval($data['stock_quantity']) : null;
        }
        if (isset($data['status'])) {
            $update['status'] = in_array($data['status'], ['active', 'draft', 'inactive']) ? $data['status'] : $product->status;
        }
        if (isset($data['featured'])) {
            $update['featured'] = !empty($data['featured']) ? 1 : 0;
        }
        
        if (!empty($update)) {
            $this->wpdb->update(
                $this->db->products_table,
                $update,
                ['id' => $product_id]
            );
        }
        
        // Update images
        if (isset($data['images'])) {
            // Delete existing images
            $this->wpdb->delete($this->db->product_images_table, ['product_id' => $product_id]);
            
            // Add new images
            foreach ($data['images'] as $i => $image_url) {
                $this->wpdb->insert($this->db->product_images_table, [
                    'product_id' => $product_id,
                    'image_url' => esc_url_raw($image_url),
                    'sort_order' => $i,
                ]);
            }
        }
        
        // Update attributes
        if (isset($data['attributes'])) {
            // Delete existing attributes
            $this->wpdb->delete($this->db->product_attributes_table, ['product_id' => $product_id]);
            
            // Add new attributes
            foreach ($data['attributes'] as $attr) {
                if (!empty($attr['name']) && !empty($attr['value'])) {
                    $this->wpdb->insert($this->db->product_attributes_table, [
                        'product_id' => $product_id,
                        'attribute_name' => sanitize_text_field($attr['name']),
                        'attribute_value' => sanitize_text_field($attr['value']),
                    ]);
                }
            }
        }
        
        return $this->get_product($product_id);
    }
    
    /**
     * Delete product
     */
    public function delete_product($vendor_id, $product_id) {
        // Verify ownership
        $product = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->products_table} WHERE id = %d AND vendor_id = %d",
            $product_id, $vendor_id
        ));
        
        if (!$product) {
            return new WP_Error('not_found', 'Product not found or access denied.');
        }
        
        // Delete related data
        $this->wpdb->delete($this->db->product_images_table, ['product_id' => $product_id]);
        $this->wpdb->delete($this->db->product_attributes_table, ['product_id' => $product_id]);
        $this->wpdb->delete($this->db->reviews_table, ['product_id' => $product_id]);
        $this->wpdb->delete($this->db->wishlists_table, ['product_id' => $product_id]);
        $this->wpdb->delete($this->db->cart_table, ['product_id' => $product_id]);
        $this->wpdb->delete($this->db->campaign_products_table, ['product_id' => $product_id]);
        
        // Delete product
        $this->wpdb->delete($this->db->products_table, ['id' => $product_id]);
        
        return true;
    }
    
    /**
     * Get product for editing
     */
    public function get_product($product_id) {
        $product = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE p.id = %d",
            $product_id
        ));
        
        if ($product) {
            $product->images = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->db->product_images_table} 
                 WHERE product_id = %d ORDER BY sort_order",
                $product_id
            ));
            
            $product->attributes = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->db->product_attributes_table} 
                 WHERE product_id = %d",
                $product_id
            ));
        }
        
        return $product;
    }
    
    /**
     * Get vendor orders
     */
    public function get_vendor_orders($vendor_id, $args = []) {
        $defaults = [
            'status' => null,
            'limit' => 20,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $orders = NexMart_Orders::get_instance()->get_vendor_orders(
            $vendor_id,
            $args['status'],
            $args['limit'],
            $args['offset']
        );
        
        return $orders;
    }
    
    /**
     * Get vendor dashboard stats
     */
    public function get_vendor_stats($vendor_id) {
        // Product stats
        $product_stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                SUM(CASE WHEN stock_quantity IS NOT NULL AND stock_quantity <= 5 THEN 1 ELSE 0 END) as low_stock_products
             FROM {$this->db->products_table} WHERE vendor_id = %d",
            $vendor_id
        ));
        
        // Order stats
        $order_stats = NexMart_Orders::get_instance()->get_order_stats($vendor_id);
        
        // Recent orders
        $recent_orders = $this->get_vendor_orders($vendor_id, ['limit' => 5]);
        
        // Top products
        $top_products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, 
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             WHERE p.vendor_id = %d AND p.status = 'active'
             ORDER BY p.sales_count DESC
             LIMIT 5",
            $vendor_id
        ));
        
        return [
            'products' => $product_stats,
            'orders' => $order_stats['orders'],
            'revenue' => $order_stats['revenue'],
            'recent_orders' => $recent_orders,
            'top_products' => $top_products,
        ];
    }
    
    /**
     * Get vendor analytics
     */
    public function get_vendor_analytics($vendor_id, $period = 30) {
        // Sales by day
        $sales_by_day = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(o.created_at) as date, SUM(oi.total) as revenue, COUNT(DISTINCT o.id) as orders
             FROM {$this->db->order_items_table} oi
             JOIN {$this->db->orders_table} o ON oi.order_id = o.id
             WHERE oi.vendor_id = %d 
             AND o.status NOT IN ('cancelled', 'refunded')
             AND o.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(o.created_at)
             ORDER BY date ASC",
            $vendor_id, $period
        ));
        
        // Sales by category
        $sales_by_category = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.name as category, SUM(oi.total) as revenue, SUM(oi.quantity) as units
             FROM {$this->db->order_items_table} oi
             JOIN {$this->db->products_table} p ON oi.product_id = p.id
             JOIN {$this->db->categories_table} c ON p.category_id = c.id
             JOIN {$this->db->orders_table} o ON oi.order_id = o.id
             WHERE oi.vendor_id = %d 
             AND o.status NOT IN ('cancelled', 'refunded')
             AND o.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY c.id
             ORDER BY revenue DESC
             LIMIT 5",
            $vendor_id, $period
        ));
        
        // Top selling products
        $top_products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.name, SUM(oi.quantity) as units_sold, SUM(oi.total) as revenue
             FROM {$this->db->order_items_table} oi
             JOIN {$this->db->products_table} p ON oi.product_id = p.id
             JOIN {$this->db->orders_table} o ON oi.order_id = o.id
             WHERE oi.vendor_id = %d 
             AND o.status NOT IN ('cancelled', 'refunded')
             AND o.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY p.id
             ORDER BY units_sold DESC
             LIMIT 10",
            $vendor_id, $period
        ));
        
        return [
            'sales_by_day' => $sales_by_day,
            'sales_by_category' => $sales_by_category,
            'top_products' => $top_products,
        ];
    }
    
    /**
     * Handle image upload
     */
    public function upload_image() {
        if (empty($_FILES['image'])) {
            return new WP_Error('no_file', 'No file uploaded.');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('image', 0);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        $url = wp_get_attachment_url($attachment_id);
        
        return [
            'attachment_id' => $attachment_id,
            'url' => $url,
        ];
    }
    
    // AJAX Handlers
    
    public function ajax_add_product() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'category_id' => intval($_POST['category_id'] ?? 0),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'short_description' => sanitize_textarea_field($_POST['short_description'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'sale_price' => floatval($_POST['sale_price'] ?? 0),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'stock_quantity' => isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? intval($_POST['stock_quantity']) : null,
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
            'featured' => !empty($_POST['featured']),
            'images' => isset($_POST['images']) ? array_map('esc_url_raw', (array) $_POST['images']) : [],
            'attributes' => isset($_POST['attributes']) ? (array) $_POST['attributes'] : [],
        ];
        
        $result = $this->add_product($vendor_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['product' => $result, 'message' => 'Product created successfully!']);
    }
    
    public function ajax_update_product() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Product ID required.']);
        }
        
        $data = [];
        
        if (isset($_POST['name'])) $data['name'] = $_POST['name'];
        if (isset($_POST['category_id'])) $data['category_id'] = $_POST['category_id'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['short_description'])) $data['short_description'] = $_POST['short_description'];
        if (isset($_POST['price'])) $data['price'] = $_POST['price'];
        if (isset($_POST['sale_price'])) $data['sale_price'] = $_POST['sale_price'];
        if (isset($_POST['sku'])) $data['sku'] = $_POST['sku'];
        if (isset($_POST['stock_quantity'])) $data['stock_quantity'] = $_POST['stock_quantity'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['featured'])) $data['featured'] = $_POST['featured'];
        if (isset($_POST['images'])) $data['images'] = (array) $_POST['images'];
        if (isset($_POST['attributes'])) $data['attributes'] = (array) $_POST['attributes'];
        
        $result = $this->update_product($vendor_id, $product_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['product' => $result, 'message' => 'Product updated successfully!']);
    }
    
    public function ajax_delete_product() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Product ID required.']);
        }
        
        $result = $this->delete_product($vendor_id, $product_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Product deleted successfully!']);
    }
    
    public function ajax_get_products() {
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $args = [
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null,
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : null,
            'category_id' => isset($_GET['category_id']) ? intval($_GET['category_id']) : null,
            'sort' => isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest',
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 20,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0,
        ];
        
        $result = $this->get_vendor_products($vendor_id, $args);
        wp_send_json_success($result);
    }
    
    public function ajax_upload_image() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $result = $this->upload_image();
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_orders() {
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $args = [
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null,
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 20,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0,
        ];
        
        $orders = $this->get_vendor_orders($vendor_id, $args);
        wp_send_json_success(['orders' => $orders]);
    }
    
    public function ajax_update_order() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        // Verify this order has items from this vendor
        $has_items = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->order_items_table} 
             WHERE order_id = %d AND vendor_id = %d",
            $order_id, $vendor_id
        ));
        
        if (!$has_items) {
            wp_send_json_error(['message' => 'Order not found.']);
        }
        
        $result = NexMart_Orders::get_instance()->update_order_status($order_id, $status);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['order' => $result, 'message' => 'Order updated successfully!']);
    }
    
    public function ajax_get_stats() {
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $stats = $this->get_vendor_stats($vendor_id);
        wp_send_json_success(['stats' => $stats]);
    }
    
    public function ajax_get_analytics() {
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
        $analytics = $this->get_vendor_analytics($vendor_id, $period);
        wp_send_json_success(['analytics' => $analytics]);
    }
    
    public function ajax_update_profile() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $data = [];
        $allowed = ['store_name', 'store_description', 'logo_url', 'banner_url', 
                    'phone', 'address', 'city', 'state', 'country', 'postcode'];
        
        foreach ($allowed as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }
        
        $result = $this->update_vendor_profile($vendor_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['vendor' => $result, 'message' => 'Profile updated successfully!']);
    }
    
    public function ajax_get_profile() {
        $vendor_id = $this->get_current_vendor_id();
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor account required.']);
        }
        
        $profile = $this->get_vendor_profile($vendor_id);
        wp_send_json_success(['vendor' => $profile]);
    }
}
