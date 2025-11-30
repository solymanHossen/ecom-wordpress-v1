<?php
/**
 * NexMart Database Handler
 * Creates and manages all custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Database {
    
    private static $instance = null;
    private $wpdb;
    private $charset_collate;
    
    // Table names
    public $vendors_table;
    public $categories_table;
    public $products_table;
    public $product_images_table;
    public $product_attributes_table;
    public $reviews_table;
    public $coupons_table;
    public $orders_table;
    public $order_items_table;
    public $cart_table;
    public $wishlists_table;
    public $campaigns_table;
    public $campaign_products_table;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->set_table_names();
    }
    
    private function set_table_names() {
        $prefix = $this->wpdb->prefix . 'nexmart_';
        
        $this->vendors_table = $prefix . 'vendors';
        $this->categories_table = $prefix . 'categories';
        $this->products_table = $prefix . 'products';
        $this->product_images_table = $prefix . 'product_images';
        $this->product_attributes_table = $prefix . 'product_attributes';
        $this->reviews_table = $prefix . 'reviews';
        $this->coupons_table = $prefix . 'coupons';
        $this->orders_table = $prefix . 'orders';
        $this->order_items_table = $prefix . 'order_items';
        $this->cart_table = $prefix . 'cart';
        $this->wishlists_table = $prefix . 'wishlists';
        $this->campaigns_table = $prefix . 'campaigns';
        $this->campaign_products_table = $prefix . 'campaign_products';
    }
    
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Categories Table
        $sql = "CREATE TABLE {$this->categories_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            icon varchar(100),
            image varchar(500),
            parent_id bigint(20) UNSIGNED DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Vendors Table
        $sql = "CREATE TABLE {$this->vendors_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            store_name varchar(255) NOT NULL,
            store_slug varchar(255) NOT NULL,
            store_description text,
            logo_url varchar(500),
            banner_url varchar(500),
            phone varchar(50),
            address text,
            city varchar(100),
            state varchar(100),
            country varchar(100),
            postcode varchar(20),
            commission_rate decimal(5,2) DEFAULT 10.00,
            total_sales decimal(15,2) DEFAULT 0.00,
            featured tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY store_slug (store_slug),
            KEY user_id (user_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Products Table
        $sql = "CREATE TABLE {$this->products_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) UNSIGNED NOT NULL,
            category_id bigint(20) UNSIGNED,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            short_description text,
            sku varchar(100),
            price decimal(12,2) NOT NULL,
            sale_price decimal(12,2),
            stock_quantity int(11),
            weight decimal(8,2),
            length decimal(8,2),
            width decimal(8,2),
            height decimal(8,2),
            featured tinyint(1) DEFAULT 0,
            sales_count int(11) DEFAULT 0,
            rating decimal(3,2) DEFAULT 0.00,
            reviews_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY vendor_id (vendor_id),
            KEY category_id (category_id),
            KEY status (status)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Product Images Table
        $sql = "CREATE TABLE {$this->product_images_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            image_url varchar(500) NOT NULL,
            alt_text varchar(255),
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Product Attributes Table
        $sql = "CREATE TABLE {$this->product_attributes_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            attribute_name varchar(100) NOT NULL,
            attribute_value text NOT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Reviews Table
        $sql = "CREATE TABLE {$this->reviews_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED,
            reviewer_name varchar(100),
            reviewer_email varchar(100),
            rating tinyint(1) NOT NULL,
            title varchar(255),
            comment text,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Coupons Table
        $sql = "CREATE TABLE {$this->coupons_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            description text,
            discount_type varchar(20) NOT NULL,
            discount_value decimal(12,2) NOT NULL,
            minimum_order decimal(12,2) DEFAULT 0,
            max_uses int(11),
            times_used int(11) DEFAULT 0,
            start_date date,
            end_date date,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Orders Table
        $sql = "CREATE TABLE {$this->orders_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED,
            status varchar(20) DEFAULT 'pending',
            payment_status varchar(20) DEFAULT 'pending',
            payment_method varchar(50),
            subtotal decimal(12,2) NOT NULL,
            discount decimal(12,2) DEFAULT 0,
            shipping_cost decimal(12,2) DEFAULT 0,
            tax decimal(12,2) DEFAULT 0,
            total decimal(12,2) NOT NULL,
            coupon_code varchar(50),
            currency varchar(10) DEFAULT 'USD',
            shipping_first_name varchar(100),
            shipping_last_name varchar(100),
            shipping_email varchar(255),
            shipping_phone varchar(50),
            shipping_address varchar(255),
            shipping_address_2 varchar(255),
            shipping_city varchar(100),
            shipping_state varchar(100),
            shipping_postcode varchar(20),
            shipping_country varchar(100),
            billing_first_name varchar(100),
            billing_last_name varchar(100),
            billing_email varchar(255),
            billing_phone varchar(50),
            billing_address varchar(255),
            billing_address_2 varchar(255),
            billing_city varchar(100),
            billing_state varchar(100),
            billing_postcode varchar(20),
            billing_country varchar(100),
            notes text,
            shipped_at datetime,
            delivered_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY user_id (user_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Order Items Table
        $sql = "CREATE TABLE {$this->order_items_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            vendor_id bigint(20) UNSIGNED,
            product_name varchar(255) NOT NULL,
            product_sku varchar(100),
            product_image varchar(500),
            quantity int(11) NOT NULL,
            unit_price decimal(12,2) NOT NULL,
            total decimal(12,2) NOT NULL,
            attributes text,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY vendor_id (vendor_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Cart Table
        $sql = "CREATE TABLE {$this->cart_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) UNSIGNED,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            attributes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Wishlists Table
        $sql = "CREATE TABLE {$this->wishlists_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_product (user_id, product_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Campaigns Table
        $sql = "CREATE TABLE {$this->campaigns_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            banner_image varchar(500),
            discount_type varchar(20) NOT NULL,
            discount_value decimal(12,2) NOT NULL,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            featured tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'inactive',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        // Campaign Products Table
        $sql = "CREATE TABLE {$this->campaign_products_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_product (campaign_id, product_id)
        ) {$this->charset_collate};";
        dbDelta($sql);
        
        return true;
    }
    
    public function drop_tables() {
        $tables = [
            $this->campaign_products_table,
            $this->campaigns_table,
            $this->wishlists_table,
            $this->cart_table,
            $this->order_items_table,
            $this->orders_table,
            $this->coupons_table,
            $this->reviews_table,
            $this->product_attributes_table,
            $this->product_images_table,
            $this->products_table,
            $this->vendors_table,
            $this->categories_table,
        ];
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        return true;
    }
}
