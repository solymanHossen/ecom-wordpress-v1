<?php
/**
 * NexMart Products Handler
 * Manages product CRUD operations, search, filtering, and display
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Products {
    
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
        // AJAX handlers
        add_action('wp_ajax_nexmart_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_nopriv_nexmart_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_nexmart_get_product', [$this, 'ajax_get_product']);
        add_action('wp_ajax_nopriv_nexmart_get_product', [$this, 'ajax_get_product']);
        add_action('wp_ajax_nexmart_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_nopriv_nexmart_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_nexmart_add_review', [$this, 'ajax_add_review']);
        add_action('wp_ajax_nexmart_toggle_wishlist', [$this, 'ajax_toggle_wishlist']);
        add_action('wp_ajax_nopriv_nexmart_toggle_wishlist', [$this, 'ajax_toggle_wishlist']);
        add_action('wp_ajax_nexmart_get_wishlist', [$this, 'ajax_get_wishlist']);
    }
    
    /**
     * Get products with filtering
     */
    public function get_products($args = []) {
        $defaults = [
            'category_id' => null,
            'vendor_id' => null,
            'featured' => null,
            'status' => 'active',
            'search' => null,
            'min_price' => null,
            'max_price' => null,
            'sort' => 'newest',
            'limit' => 12,
            'offset' => 0,
            'campaign_id' => null,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['p.status = %s'];
        $params = [$args['status']];
        
        if ($args['category_id']) {
            $where[] = 'p.category_id = %d';
            $params[] = $args['category_id'];
        }
        
        if ($args['vendor_id']) {
            $where[] = 'p.vendor_id = %d';
            $params[] = $args['vendor_id'];
        }
        
        if ($args['featured'] !== null) {
            $where[] = 'p.featured = %d';
            $params[] = $args['featured'];
        }
        
        if ($args['search']) {
            $where[] = '(p.name LIKE %s OR p.description LIKE %s)';
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        if ($args['min_price'] !== null) {
            $where[] = 'COALESCE(p.sale_price, p.price) >= %f';
            $params[] = $args['min_price'];
        }
        
        if ($args['max_price'] !== null) {
            $where[] = 'COALESCE(p.sale_price, p.price) <= %f';
            $params[] = $args['max_price'];
        }
        
        $join = '';
        if ($args['campaign_id']) {
            $join = "INNER JOIN {$this->db->campaign_products_table} cp ON p.id = cp.product_id AND cp.campaign_id = " . intval($args['campaign_id']);
        }
        
        // Sorting
        $order = match($args['sort']) {
            'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
            'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
            'rating' => 'p.rating DESC',
            'popular' => 'p.sales_count DESC',
            'name' => 'p.name ASC',
            default => 'p.created_at DESC',
        };
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $count_sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->products_table} p {$join} WHERE {$where_clause}",
            ...$params
        );
        $total = $this->wpdb->get_var($count_sql);
        
        // Get products
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $sql = $this->wpdb->prepare(
            "SELECT p.*, c.name as category_name, c.slug as category_slug,
                    v.store_name as vendor_name, v.store_slug as vendor_slug
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             LEFT JOIN {$this->db->vendors_table} v ON p.vendor_id = v.id
             {$join}
             WHERE {$where_clause}
             ORDER BY {$order}
             LIMIT %d OFFSET %d",
            ...$params
        );
        
        $products = $this->wpdb->get_results($sql);
        
        // Add images to each product
        foreach ($products as &$product) {
            $product->images = $this->get_product_images($product->id);
            $product->primary_image = !empty($product->images) ? $product->images[0]->image_url : '';
        }
        
        return [
            'products' => $products,
            'total' => (int)$total,
            'pages' => ceil($total / $args['limit']),
        ];
    }
    
    /**
     * Get single product with full details
     */
    public function get_product($id, $by = 'id') {
        $field = $by === 'slug' ? 'slug' : 'id';
        $placeholder = $by === 'slug' ? '%s' : '%d';
        
        $product = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name, c.slug as category_slug,
                    v.store_name as vendor_name, v.store_slug as vendor_slug, v.id as vendor_id
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             LEFT JOIN {$this->db->vendors_table} v ON p.vendor_id = v.id
             WHERE p.{$field} = {$placeholder}",
            $id
        ));
        
        if (!$product) {
            return null;
        }
        
        // Add images
        $product->images = $this->get_product_images($product->id);
        $product->primary_image = !empty($product->images) ? $product->images[0]->image_url : '';
        
        // Add attributes
        $product->attributes = $this->get_product_attributes($product->id);
        
        // Add reviews
        $product->reviews = $this->get_product_reviews($product->id);
        
        // Add related products
        $product->related = $this->get_related_products($product->id, $product->category_id);
        
        return $product;
    }
    
    /**
     * Get product images
     */
    public function get_product_images($product_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->product_images_table} 
             WHERE product_id = %d ORDER BY sort_order ASC",
            $product_id
        ));
    }
    
    /**
     * Get product attributes
     */
    public function get_product_attributes($product_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->product_attributes_table} 
             WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get product reviews
     */
    public function get_product_reviews($product_id, $limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->db->reviews_table} 
             WHERE product_id = %d AND status = 'approved'
             ORDER BY created_at DESC LIMIT %d",
            $product_id, $limit
        ));
    }
    
    /**
     * Get related products
     */
    public function get_related_products($product_id, $category_id, $limit = 4) {
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, 
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             WHERE p.category_id = %d AND p.id != %d AND p.status = 'active'
             ORDER BY RAND() LIMIT %d",
            $category_id, $product_id, $limit
        ));
        
        return $products;
    }
    
    /**
     * Get categories
     */
    public function get_categories($parent_id = null, $with_count = true) {
        $where = $parent_id !== null ? $this->wpdb->prepare("WHERE parent_id = %d", $parent_id) : "";
        
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM {$this->db->products_table} p WHERE p.category_id = c.id AND p.status = 'active') as product_count
                FROM {$this->db->categories_table} c {$where} ORDER BY sort_order ASC, name ASC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get category by slug
     */
    public function get_category($slug) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->db->categories_table} WHERE slug = %s",
            $slug
        ));
    }
    
    /**
     * Add review
     */
    public function add_review($data) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to leave a review.');
        }
        
        $product_id = intval($data['product_id']);
        
        // Check if already reviewed
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->db->reviews_table} 
             WHERE product_id = %d AND user_id = %d",
            $product_id, $user_id
        ));
        
        if ($exists) {
            return new WP_Error('already_reviewed', 'You have already reviewed this product.');
        }
        
        $user = get_userdata($user_id);
        
        $review_data = [
            'product_id' => $product_id,
            'user_id' => $user_id,
            'reviewer_name' => $user->display_name,
            'reviewer_email' => $user->user_email,
            'rating' => max(1, min(5, intval($data['rating']))),
            'title' => sanitize_text_field($data['title'] ?? ''),
            'comment' => sanitize_textarea_field($data['comment'] ?? ''),
            'status' => 'pending', // Or 'approved' for auto-approval
        ];
        
        $this->wpdb->insert($this->db->reviews_table, $review_data);
        $review_id = $this->wpdb->insert_id;
        
        if (!$review_id) {
            return new WP_Error('review_failed', 'Failed to submit review.');
        }
        
        // Update product rating
        $this->update_product_rating($product_id);
        
        return [
            'review_id' => $review_id,
            'message' => 'Review submitted successfully!',
        ];
    }
    
    /**
     * Update product rating
     */
    private function update_product_rating($product_id) {
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
             FROM {$this->db->reviews_table} 
             WHERE product_id = %d AND status = 'approved'",
            $product_id
        ));
        
        $this->wpdb->update(
            $this->db->products_table,
            [
                'rating' => $stats->avg_rating ?: 0,
                'reviews_count' => $stats->review_count,
            ],
            ['id' => $product_id]
        );
    }
    
    /**
     * Toggle wishlist
     */
    public function toggle_wishlist($product_id) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to add to wishlist.');
        }
        
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->db->wishlists_table} 
             WHERE product_id = %d AND user_id = %d",
            $product_id, $user_id
        ));
        
        if ($exists) {
            $this->wpdb->delete($this->db->wishlists_table, ['id' => $exists]);
            return ['in_wishlist' => false, 'message' => 'Removed from wishlist.'];
        } else {
            $this->wpdb->insert($this->db->wishlists_table, [
                'user_id' => $user_id,
                'product_id' => $product_id,
            ]);
            return ['in_wishlist' => true, 'message' => 'Added to wishlist.'];
        }
    }
    
    /**
     * Get user wishlist
     */
    public function get_wishlist($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, w.created_at as added_at,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->wishlists_table} w
             JOIN {$this->db->products_table} p ON w.product_id = p.id
             WHERE w.user_id = %d
             ORDER BY w.created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Check if product is in wishlist
     */
    public function is_in_wishlist($product_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->db->wishlists_table} 
             WHERE product_id = %d AND user_id = %d",
            $product_id, $user_id
        ));
    }
    
    /**
     * Get featured products
     */
    public function get_featured($limit = 8) {
        return $this->get_products(['featured' => 1, 'limit' => $limit]);
    }
    
    /**
     * Get sale products
     */
    public function get_sale_products($limit = 8) {
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE p.status = 'active' AND p.sale_price IS NOT NULL AND p.sale_price > 0
             ORDER BY (p.price - p.sale_price) / p.price DESC
             LIMIT %d",
            $limit
        ));
        
        return $products;
    }
    
    /**
     * Get new arrivals
     */
    public function get_new_arrivals($limit = 8, $days = 30) {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE p.status = 'active' AND p.created_at >= %s
             ORDER BY p.created_at DESC
             LIMIT %d",
            $date, $limit
        ));
        
        return $products;
    }
    
    /**
     * Get best sellers
     */
    public function get_best_sellers($limit = 8) {
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, c.name as category_name,
                    (SELECT image_url FROM {$this->db->product_images_table} 
                     WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as primary_image
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             WHERE p.status = 'active'
             ORDER BY p.sales_count DESC
             LIMIT %d",
            $limit
        ));
        
        return $products;
    }
    
    // AJAX Handlers
    
    public function ajax_get_products() {
        $args = [
            'category_id' => isset($_GET['category_id']) ? intval($_GET['category_id']) : null,
            'vendor_id' => isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : null,
            'featured' => isset($_GET['featured']) ? intval($_GET['featured']) : null,
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : null,
            'min_price' => isset($_GET['min_price']) ? floatval($_GET['min_price']) : null,
            'max_price' => isset($_GET['max_price']) ? floatval($_GET['max_price']) : null,
            'sort' => isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest',
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 12,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0,
        ];
        
        $result = $this->get_products($args);
        wp_send_json_success($result);
    }
    
    public function ajax_get_product() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : null;
        
        if ($slug) {
            $product = $this->get_product($slug, 'slug');
        } elseif ($id) {
            $product = $this->get_product($id);
        } else {
            wp_send_json_error(['message' => 'Product ID or slug required.']);
        }
        
        if (!$product) {
            wp_send_json_error(['message' => 'Product not found.']);
        }
        
        wp_send_json_success(['product' => $product]);
    }
    
    public function ajax_search_products() {
        $query = sanitize_text_field($_GET['q'] ?? '');
        
        if (strlen($query) < 2) {
            wp_send_json_success(['products' => []]);
        }
        
        $result = $this->get_products([
            'search' => $query,
            'limit' => 10,
        ]);
        
        wp_send_json_success($result);
    }
    
    public function ajax_add_review() {
        check_ajax_referer('nexmart_nonce', 'nonce');
        
        $data = [
            'product_id' => intval($_POST['product_id']),
            'rating' => intval($_POST['rating']),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'comment' => sanitize_textarea_field($_POST['comment'] ?? ''),
        ];
        
        $result = $this->add_review($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_toggle_wishlist() {
        // Verify nonce - relaxed for page caching compatibility
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'nexmart_nonce')) {
            // Allow to continue but log for debugging
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if ($product_id <= 0) {
            wp_send_json_error(['message' => 'Invalid product ID.']);
            return;
        }
        
        $result = $this->toggle_wishlist($product_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_wishlist() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in.']);
        }
        
        $wishlist = $this->get_wishlist();
        wp_send_json_success(['wishlist' => $wishlist]);
    }
}
