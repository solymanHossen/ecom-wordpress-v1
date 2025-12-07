<?php
/**
 * NexMart Performance Optimization Patches
 * 
 * This file contains performance optimizations for the NexMart platform
 * 
 * Usage: Include this file in functions.php after security patches
 * require_once get_template_directory() . '/inc/performance-patches.php';
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================
 * PATCH #1: Transient-based Caching System
 * =========================================
 */
class NexMart_Cache {
    
    // Default cache times in seconds
    const CACHE_SHORT = 300;      // 5 minutes
    const CACHE_MEDIUM = 1800;    // 30 minutes
    const CACHE_LONG = 3600;      // 1 hour
    const CACHE_DAY = 86400;      // 24 hours
    
    /**
     * Get cached value
     */
    public static function get($key) {
        $full_key = 'nexmart_' . $key;
        return get_transient($full_key);
    }
    
    /**
     * Set cached value
     */
    public static function set($key, $value, $expiration = self::CACHE_MEDIUM) {
        $full_key = 'nexmart_' . $key;
        return set_transient($full_key, $value, $expiration);
    }
    
    /**
     * Delete cached value
     */
    public static function delete($key) {
        $full_key = 'nexmart_' . $key;
        return delete_transient($full_key);
    }
    
    /**
     * Delete all caches matching a pattern
     */
    public static function delete_pattern($pattern) {
        global $wpdb;
        
        $full_pattern = '_transient_nexmart_' . $pattern;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $full_pattern . '%',
            '_transient_timeout_nexmart_' . $pattern . '%'
        ));
    }
    
    /**
     * Remember - get from cache or execute callback
     */
    public static function remember($key, $callback, $expiration = self::CACHE_MEDIUM) {
        $cached = self::get($key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $value = $callback();
        self::set($key, $value, $expiration);
        
        return $value;
    }
    
    /**
     * Clear all NexMart caches
     */
    public static function flush() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_nexmart_%' 
             OR option_name LIKE '_transient_timeout_nexmart_%'"
        );
    }
}

/**
 * =========================================
 * PATCH #2: Optimized Product Queries
 * =========================================
 */
class NexMart_Product_Query {
    
    private $wpdb;
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db = NexMart_Database::get_instance();
    }
    
    /**
     * Get products with batch-loaded images (fixes N+1 problem)
     */
    public function get_products_optimized($args = []) {
        $cache_key = 'products_' . md5(serialize($args));
        
        return NexMart_Cache::remember($cache_key, function() use ($args) {
            return $this->fetch_products($args);
        }, NexMart_Cache::CACHE_SHORT);
    }
    
    private function fetch_products($args) {
        $defaults = [
            'category_id' => null,
            'vendor_id' => null,
            'featured' => null,
            'status' => 'published',
            'search' => null,
            'min_price' => null,
            'max_price' => null,
            'sort' => 'newest',
            'limit' => 12,
            'offset' => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = ['p.status = %s'];
        $params = [$args['status']];
        
        if ($args['category_id']) {
            $where[] = 'p.category_id = %d';
            $params[] = intval($args['category_id']);
        }
        
        if ($args['vendor_id']) {
            $where[] = 'p.vendor_id = %d';
            $params[] = intval($args['vendor_id']);
        }
        
        if ($args['featured'] !== null) {
            $where[] = 'p.featured = %d';
            $params[] = intval($args['featured']);
        }
        
        if ($args['search']) {
            $where[] = 'MATCH(p.name, p.description) AGAINST(%s IN BOOLEAN MODE)';
            $params[] = $args['search'];
        }
        
        if ($args['min_price'] !== null) {
            $where[] = 'COALESCE(p.sale_price, p.price) >= %f';
            $params[] = floatval($args['min_price']);
        }
        
        if ($args['max_price'] !== null) {
            $where[] = 'COALESCE(p.sale_price, p.price) <= %f';
            $params[] = floatval($args['max_price']);
        }
        
        // Use whitelist for ORDER BY
        $order = NexMart_Security::sanitize_order_by($args['sort']);
        $where_clause = implode(' AND ', $where);
        
        // Count query
        $count_sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->db->products_table} p WHERE {$where_clause}",
            ...$params
        );
        $total = (int) $this->wpdb->get_var($count_sql);
        
        // Main query - NO SUBQUERIES
        $params[] = intval($args['limit']);
        $params[] = intval($args['offset']);
        
        $products = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                p.id, p.vendor_id, p.category_id, p.name, p.slug,
                p.short_description, p.price, p.sale_price, 
                p.stock_quantity, p.featured, p.rating, p.reviews_count,
                p.created_at,
                c.name as category_name, c.slug as category_slug,
                v.store_name as vendor_name, v.store_slug as vendor_slug
             FROM {$this->db->products_table} p
             LEFT JOIN {$this->db->categories_table} c ON p.category_id = c.id
             LEFT JOIN {$this->db->vendors_table} v ON p.vendor_id = v.id
             WHERE {$where_clause}
             ORDER BY p.{$order}
             LIMIT %d OFFSET %d",
            ...$params
        ));
        
        if (empty($products)) {
            return [
                'products' => [],
                'total' => 0,
                'pages' => 0,
            ];
        }
        
        // Batch load images - ONE QUERY for all products
        $product_ids = array_column($products, 'id');
        $products = $this->attach_images($products, $product_ids);
        
        return [
            'products' => $products,
            'total' => $total,
            'pages' => ceil($total / $args['limit']),
        ];
    }
    
    /**
     * Batch load product images (fixes N+1)
     */
    private function attach_images($products, $product_ids) {
        if (empty($product_ids)) {
            return $products;
        }
        
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        
        $images = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT product_id, image_url 
             FROM {$this->db->product_images_table} 
             WHERE product_id IN ($placeholders) AND sort_order = 0",
            ...$product_ids
        ));
        
        // Index by product_id
        $image_map = [];
        foreach ($images as $img) {
            $image_map[$img->product_id] = $img->image_url;
        }
        
        // Attach to products
        foreach ($products as &$product) {
            $product->primary_image = $image_map[$product->id] ?? '';
        }
        
        return $products;
    }
    
    /**
     * Get single product with caching
     */
    public function get_product_cached($id_or_slug, $by = 'id') {
        $cache_key = "product_{$by}_{$id_or_slug}";
        
        return NexMart_Cache::remember($cache_key, function() use ($id_or_slug, $by) {
            return NexMart_Products::get_instance()->get_product($id_or_slug, $by);
        }, NexMart_Cache::CACHE_SHORT);
    }
}

/**
 * =========================================
 * PATCH #3: Categories Caching
 * =========================================
 */
function nexmart_get_categories_cached() {
    return NexMart_Cache::remember('all_categories', function() {
        global $wpdb;
        $db = NexMart_Database::get_instance();
        
        return $wpdb->get_results(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM {$db->products_table} p 
                     WHERE p.category_id = c.id AND p.status = 'published') as product_count
             FROM {$db->categories_table} c 
             ORDER BY c.sort_order ASC, c.name ASC"
        );
    }, NexMart_Cache::CACHE_LONG);
}

/**
 * =========================================
 * PATCH #4: Featured Vendors Caching
 * =========================================
 */
function nexmart_get_featured_vendors_cached($limit = 6) {
    return NexMart_Cache::remember("featured_vendors_{$limit}", function() use ($limit) {
        global $wpdb;
        $db = NexMart_Database::get_instance();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, 
                    (SELECT COUNT(*) FROM {$db->products_table} p 
                     WHERE p.vendor_id = v.id AND p.status = 'published') as product_count
             FROM {$db->vendors_table} v 
             WHERE v.status = 'active' AND v.featured = 1
             ORDER BY v.total_sales DESC
             LIMIT %d",
            $limit
        ));
    }, NexMart_Cache::CACHE_MEDIUM);
}

/**
 * =========================================
 * PATCH #5: Cache Invalidation Hooks
 * =========================================
 */

// Clear product cache on product changes
function nexmart_clear_product_cache($product_id) {
    NexMart_Cache::delete_pattern('products_');
    NexMart_Cache::delete("product_id_{$product_id}");
    
    // Get slug for slug-based cache
    global $wpdb;
    $db = NexMart_Database::get_instance();
    $slug = $wpdb->get_var($wpdb->prepare(
        "SELECT slug FROM {$db->products_table} WHERE id = %d",
        $product_id
    ));
    if ($slug) {
        NexMart_Cache::delete("product_slug_{$slug}");
    }
}

// Clear category cache on changes
function nexmart_clear_category_cache() {
    NexMart_Cache::delete('all_categories');
}

// Clear vendor cache on changes
function nexmart_clear_vendor_cache() {
    NexMart_Cache::delete_pattern('featured_vendors_');
}

/**
 * =========================================
 * PATCH #6: Lazy Loading for Images
 * =========================================
 */
function nexmart_lazy_load_images($content) {
    if (is_admin()) {
        return $content;
    }
    
    // Add loading="lazy" to images
    $content = preg_replace(
        '/<img(?![^>]*loading=)([^>]*?)>/i',
        '<img loading="lazy"$1>',
        $content
    );
    
    return $content;
}
add_filter('the_content', 'nexmart_lazy_load_images');

/**
 * =========================================
 * PATCH #7: Cart Cleanup Cron Job
 * =========================================
 */
function nexmart_schedule_cleanup() {
    if (!wp_next_scheduled('nexmart_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'nexmart_daily_cleanup');
    }
}
add_action('init', 'nexmart_schedule_cleanup');

function nexmart_run_cleanup() {
    global $wpdb;
    $db = NexMart_Database::get_instance();
    
    // Clean abandoned carts older than 30 days
    $wpdb->query(
        "DELETE FROM {$db->cart_table} 
         WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    // Clean expired transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_timeout_%' 
         AND option_value < UNIX_TIMESTAMP()"
    );
    
    // Log cleanup
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('NexMart cleanup completed: ' . date('Y-m-d H:i:s'));
    }
}
add_action('nexmart_daily_cleanup', 'nexmart_run_cleanup');

/**
 * =========================================
 * PATCH #8: Database Query Optimization
 * =========================================
 */
function nexmart_optimize_queries() {
    // Only run once
    if (get_option('nexmart_queries_optimized')) {
        return;
    }
    
    global $wpdb;
    $db = NexMart_Database::get_instance();
    
    // Add composite indexes
    $indexes = [
        // Products table
        "ALTER TABLE {$db->products_table} ADD INDEX IF NOT EXISTS idx_status_category (status, category_id, created_at)",
        "ALTER TABLE {$db->products_table} ADD INDEX IF NOT EXISTS idx_price_range (price, sale_price)",
        "ALTER TABLE {$db->products_table} ADD INDEX IF NOT EXISTS idx_vendor_status (vendor_id, status)",
        
        // Orders table
        "ALTER TABLE {$db->orders_table} ADD INDEX IF NOT EXISTS idx_status_date (status, created_at)",
        "ALTER TABLE {$db->orders_table} ADD INDEX IF NOT EXISTS idx_user_status (user_id, status)",
        
        // Order items table
        "ALTER TABLE {$db->order_items_table} ADD INDEX IF NOT EXISTS idx_vendor_order (vendor_id, order_id)",
        
        // Cart table
        "ALTER TABLE {$db->cart_table} ADD INDEX IF NOT EXISTS idx_cleanup (updated_at)",
        
        // Reviews table
        "ALTER TABLE {$db->reviews_table} ADD INDEX IF NOT EXISTS idx_product_status (product_id, status, rating)",
    ];
    
    foreach ($indexes as $sql) {
        $wpdb->query($sql);
    }
    
    // Add fulltext index for search (if not exists)
    $wpdb->query(
        "ALTER TABLE {$db->products_table} 
         ADD FULLTEXT INDEX IF NOT EXISTS ft_search (name, description, short_description)"
    );
    
    update_option('nexmart_queries_optimized', true);
}
add_action('admin_init', 'nexmart_optimize_queries');

/**
 * =========================================
 * PATCH #9: Asset Optimization Hints
 * =========================================
 */
function nexmart_add_preload_hints() {
    // Preload critical assets
    echo '<link rel="preload" href="' . get_template_directory_uri() . '/assets/js/main.js" as="script">';
    
    // Preconnect to CDNs
    echo '<link rel="preconnect" href="https://cdn.tailwindcss.com">';
    echo '<link rel="preconnect" href="https://unpkg.com">';
    
    // DNS prefetch for images
    echo '<link rel="dns-prefetch" href="https://images.unsplash.com">';
}
add_action('wp_head', 'nexmart_add_preload_hints', 1);

/**
 * =========================================
 * PATCH #10: Homepage Widget Caching
 * =========================================
 */
function nexmart_get_homepage_data_cached() {
    return NexMart_Cache::remember('homepage_data', function() {
        $products = NexMart_Products::get_instance();
        
        return [
            'featured' => $products->get_featured(8),
            'new_arrivals' => $products->get_new_arrivals(8, 30),
            'best_sellers' => $products->get_best_sellers(8),
            'sale_products' => $products->get_sale_products(8),
            'categories' => nexmart_get_categories_cached(),
            'vendors' => nexmart_get_featured_vendors_cached(6),
        ];
    }, NexMart_Cache::CACHE_MEDIUM);
}

/**
 * =========================================
 * USAGE EXAMPLES
 * =========================================
 * 
 * // Cached product list:
 * $query = new NexMart_Product_Query();
 * $products = $query->get_products_optimized([
 *     'category_id' => 1,
 *     'limit' => 12,
 * ]);
 * 
 * // Manual caching:
 * $result = NexMart_Cache::remember('my_key', function() {
 *     return expensive_operation();
 * }, NexMart_Cache::CACHE_LONG);
 * 
 * // Clear caches:
 * nexmart_clear_product_cache($product_id);
 * NexMart_Cache::flush(); // Clear all
 */
