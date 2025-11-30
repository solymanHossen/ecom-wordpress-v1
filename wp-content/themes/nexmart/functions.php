<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Early URL routing for custom pages
 */
add_action('parse_request', function($wp) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'nexmart_';
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Handle product pages
    if (preg_match('#/product/([^/]+)/?$#', $request_uri, $matches)) {
        $slug = sanitize_title($matches[1]);
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}products WHERE slug = %s", $slug
        ));
        if ($product) {
            $wp->query_vars['nexmart_product'] = $slug;
        }
    }
    
    // Handle vendor pages
    if (preg_match('#/vendor/([^/]+)/?$#', $request_uri, $matches)) {
        $slug = sanitize_title($matches[1]);
        $wp->query_vars['nexmart_vendor'] = $slug;
    }
}, 1);

/**
 * NexMart Theme Constants
 */
define('NEXMART_VERSION', '1.0.0');
define('NEXMART_DIR', get_template_directory());
define('NEXMART_URI', get_template_directory_uri());
define('NEXMART_INC_DIR', NEXMART_DIR . '/inc');

/**
 * Start PHP Session early for cart functionality
 */
function nexmart_start_session() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'nexmart_start_session', 1);

/**
 * Include Core Classes
 */
require_once NEXMART_INC_DIR . '/class-nexmart-database.php';
require_once NEXMART_INC_DIR . '/class-nexmart-auth.php';
require_once NEXMART_INC_DIR . '/class-nexmart-seeder.php';
require_once NEXMART_INC_DIR . '/class-nexmart-products.php';
require_once NEXMART_INC_DIR . '/class-nexmart-cart.php';
require_once NEXMART_INC_DIR . '/class-nexmart-orders.php';
require_once NEXMART_INC_DIR . '/class-nexmart-campaigns.php';
require_once NEXMART_INC_DIR . '/class-nexmart-vendor-dashboard.php';

/**
 * Initialize NexMart Classes
 */
function nexmart_init_classes() {
    // Initialize database first
    NexMart_Database::get_instance();
    
    // Initialize other classes
    NexMart_Auth::get_instance();
    NexMart_Seeder::get_instance();
    NexMart_Products::get_instance();
    NexMart_Cart::get_instance();
    NexMart_Orders::get_instance();
    NexMart_Campaigns::get_instance();
    NexMart_Vendor_Dashboard::get_instance();
}
add_action('init', 'nexmart_init_classes', 5);

/**
 * Create database tables on theme activation
 */
function nexmart_activate_theme() {
    $db = NexMart_Database::get_instance();
    $db->create_tables();
}
add_action('after_switch_theme', 'nexmart_activate_theme');

/**
 * NexMart Theme Setup
 */
function nexmart_setup() {
    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title.
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support( 'post-thumbnails' );
    
    // Add custom image sizes
    add_image_size('nexmart-product', 400, 400, true);
    add_image_size('nexmart-product-large', 800, 800, true);
    add_image_size('nexmart-banner', 1920, 600, true);

    // Register Navigation Menus
    register_nav_menus( array(
        'primary' => esc_html__( 'Primary Menu', 'nexmart' ),
        'mobile'  => esc_html__( 'Mobile Menu', 'nexmart' ),
        'footer'  => esc_html__( 'Footer Menu', 'nexmart' ),
    ) );

    // Add WooCommerce Support
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
    
    // HTML5 support
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));
}
add_action( 'after_setup_theme', 'nexmart_setup' );

/**
 * Enqueue Scripts and Styles
 */
function nexmart_scripts() {
    // Load Tailwind CSS (CDN for Development/Demo purposes)
    wp_enqueue_script( 'tailwind', 'https://cdn.tailwindcss.com', array(), '3.3.0', false );
    
    // Load Lucide Icons
    wp_enqueue_script( 'lucide', 'https://unpkg.com/lucide@latest', array(), '0.263.1', false );

    // Load Main Theme Script
    wp_enqueue_script( 'nexmart-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), time(), true );
    
    // Load Advanced Search Script
    wp_enqueue_script( 'nexmart-advanced-search', get_template_directory_uri() . '/assets/js/advanced-search.js', array(), time(), true );

    // Pass PHP variables to JS
    wp_localize_script( 'nexmart-main', 'nexmartObj', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'siteUrl' => home_url('/'),
        'nonce' => wp_create_nonce('nexmart_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userId' => get_current_user_id(),
        'cartCount' => NexMart_Cart::get_instance()->get_cart_count(),
    ));
}
add_action( 'wp_enqueue_scripts', 'nexmart_scripts' );

/**
 * Custom Configuration for Tailwind via script tag
 */
function nexmart_tailwind_config() {
    ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        },
                        indigo: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        },
                        rose: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            500: '#f43f5e',
                            600: '#e11d48',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <?php
}
add_action( 'wp_head', 'nexmart_tailwind_config' );

/**
 * AJAX endpoint to run seeders (admin only)
 */
function nexmart_run_seeder() {
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Admin access required']);
    }
    
    check_ajax_referer('nexmart_nonce', 'nonce');
    
    $seeder = NexMart_Seeder::get_instance();
    $result = $seeder->run_all_seeders();
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success([
        'message' => 'Seeders completed successfully',
        'data' => $result
    ]);
}
add_action('wp_ajax_nexmart_run_seeder', 'nexmart_run_seeder');

/**
 * Add body classes
 */
function nexmart_body_class($classes) {
    if (is_page_template('vendor-dashboard.php')) {
        $classes[] = 'vendor-dashboard';
    }
    if (is_user_logged_in()) {
        $classes[] = 'logged-in';
        if (current_user_can('vendor')) {
            $classes[] = 'is-vendor';
        }
    }
    return $classes;
}
add_filter('body_class', 'nexmart_body_class');

/**
 * Register custom post types (for products if not using WooCommerce)
 */
function nexmart_register_post_types() {
    // Products are managed via custom tables, but we can add a CPT for SEO purposes
    register_post_type('nexmart_product', array(
        'labels' => array(
            'name' => 'Products',
            'singular_name' => 'Product',
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'rewrite' => false,
    ));
}
add_action('init', 'nexmart_register_post_types');

/**
 * Custom rewrite rules for product URLs
 */
function nexmart_rewrite_rules() {
    add_rewrite_rule(
        '^product/([^/]+)/?$',
        'index.php?nexmart_product=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^shop/category/([^/]+)/?$',
        'index.php?pagename=shop&category_slug=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^vendor/([^/]+)/?$',
        'index.php?nexmart_vendor=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^my-account/?$',
        'index.php?pagename=my-account',
        'top'
    );
    add_rewrite_rule(
        '^cart/?$',
        'index.php?pagename=cart',
        'top'
    );
    add_rewrite_rule(
        '^checkout/?$',
        'index.php?pagename=checkout',
        'top'
    );
    add_rewrite_rule(
        '^order-confirmation/?$',
        'index.php?pagename=order-confirmation',
        'top'
    );
    add_rewrite_rule(
        '^admin-dashboard/?$',
        'index.php?pagename=admin-dashboard',
        'top'
    );
}
add_action('init', 'nexmart_rewrite_rules');

/**
 * Register query vars
 */
function nexmart_query_vars($vars) {
    $vars[] = 'product_slug';
    $vars[] = 'category_slug';
    $vars[] = 'vendor_slug';
    $vars[] = 'nexmart_product';
    $vars[] = 'nexmart_vendor';
    return $vars;
}
add_filter('query_vars', 'nexmart_query_vars');

/**
 * Template redirect for custom URLs
 */
function nexmart_template_redirect() {
    global $wpdb;
    $prefix = $wpdb->prefix . 'nexmart_';
    
    // Handle product pages via query var
    $product_slug = get_query_var('nexmart_product');
    if ($product_slug) {
        set_query_var('product_slug', $product_slug);
        include get_template_directory() . '/single-product.php';
        exit;
    }
    
    // Handle product pages via URL pattern
    $request_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#/product/([^/]+)/?$#', $request_uri, $matches)) {
        $slug = sanitize_title($matches[1]);
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}products WHERE slug = %s", $slug
        ));
        if ($product) {
            set_query_var('product_slug', $slug);
            include get_template_directory() . '/single-product.php';
            exit;
        }
    }
    
    // Handle vendor pages  
    $vendor_slug = get_query_var('nexmart_vendor');
    if ($vendor_slug) {
        set_query_var('vendor_slug', $vendor_slug);
        include get_template_directory() . '/single-vendor.php';
        exit;
    }
}
add_action('template_redirect', 'nexmart_template_redirect', 1);

/**
 * Template include filter for page templates
 */
function nexmart_template_include($template) {
    // Handle product URLs
    if (get_query_var('product_slug')) {
        return get_template_directory() . '/single-product.php';
    }
    
    // Handle shop URLs
    $request_uri = $_SERVER['REQUEST_URI'];
    if (strpos($request_uri, '/shop') !== false && !is_page('shop')) {
        return get_template_directory() . '/page-shop.php';
    }
    
    // Handle custom page URLs
    $page_mappings = [
        '/cart' => '/page-cart.php',
        '/checkout' => '/page-checkout.php',
        '/my-account' => '/page-my-account.php',
        '/order-confirmation' => '/page-order-confirmation.php',
        '/admin-dashboard' => '/template-admin-dashboard.php',
        '/vendor-dashboard' => '/template-vendor-dashboard.php',
    ];
    
    foreach ($page_mappings as $url => $template_file) {
        if (strpos($request_uri, $url) === 0) {
            $template_path = get_template_directory() . $template_file;
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
    }
    
    return $template;
}
add_filter('template_include', 'nexmart_template_include', 99);

/**
 * Flush rewrite rules on theme activation
 */
function nexmart_flush_rewrite() {
    nexmart_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'nexmart_flush_rewrite');

/**
 * Helper function to get product URL
 */
function nexmart_get_product_url($slug) {
    return home_url('/product/' . $slug . '/');
}

/**
 * Helper function to get category URL
 */
function nexmart_get_category_url($slug) {
    return home_url('/shop/category/' . $slug . '/');
}

/**
 * Helper function to format price
 */
function nexmart_format_price($price, $decimals = 2) {
    return '$' . number_format((float) $price, $decimals);
}

/**
 * Helper function to get product rating stars HTML
 */
function nexmart_get_rating_stars($rating, $show_count = false, $count = 0) {
    $output = '<div class="flex items-center gap-1">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        } else {
            $output .= '<svg class="w-4 h-4 text-gray-300 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        }
    }
    if ($show_count) {
        $output .= '<span class="text-sm text-gray-500">(' . $count . ')</span>';
    }
    $output .= '</div>';
    return $output;
}

/**
 * Get discount badge HTML
 */
function nexmart_get_discount_badge($regular_price, $sale_price) {
    if (!$sale_price || $sale_price >= $regular_price) {
        return '';
    }
    $discount = round((($regular_price - $sale_price) / $regular_price) * 100);
    return '<span class="absolute top-3 left-3 bg-rose-500 text-white text-xs font-bold px-2 py-1 rounded-full">' . $discount . '% OFF</span>';
}

/**
 * AJAX Live Search Handler
 * Modern implementation with multi-type search results
 */
function nexmart_ajax_live_search() {
    // Verify nonce
    check_ajax_referer('nexmart_nonce', 'nonce');
    
    global $wpdb;
    $prefix = $wpdb->prefix . 'nexmart_';
    
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    
    if (empty($query) || strlen($query) < 2) {
        wp_send_json_error(['message' => 'Query too short']);
        return;
    }
    
    $search_param = '%' . $wpdb->esc_like($query) . '%';
    
    // Search Products (limit 5 for dropdown)
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.stock_quantity,
            v.store_name as vendor_name,
            (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id AND sort_order = 0 LIMIT 1) as primary_image
        FROM {$prefix}products p 
        LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id 
        WHERE p.status = 'published' 
            AND (p.name LIKE %s OR p.description LIKE %s OR p.sku LIKE %s)
        ORDER BY p.sales_count DESC, p.created_at DESC 
        LIMIT 5",
        $search_param, $search_param, $search_param
    ));
    
    // Search Categories (limit 3)
    $categories = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.name, c.slug,
            (SELECT COUNT(*) FROM {$prefix}products WHERE category_id = c.id AND status = 'published') as product_count
        FROM {$prefix}categories c 
        WHERE c.name LIKE %s 
        LIMIT 3",
        $search_param
    ));
    
    // Search Vendors (limit 3)
    $vendors = $wpdb->get_results($wpdb->prepare(
        "SELECT v.id, v.store_name, v.store_slug,
            (SELECT COUNT(*) FROM {$prefix}products WHERE vendor_id = v.id AND status = 'published') as product_count
        FROM {$prefix}vendors v 
        WHERE v.status = 'active' 
            AND (v.store_name LIKE %s OR v.store_description LIKE %s)
        LIMIT 3",
        $search_param, $search_param
    ));
    
    wp_send_json_success([
        'products' => $products,
        'categories' => $categories,
        'vendors' => $vendors,
        'query' => $query
    ]);
}
add_action('wp_ajax_nexmart_live_search', 'nexmart_ajax_live_search');
add_action('wp_ajax_nopriv_nexmart_live_search', 'nexmart_ajax_live_search');
