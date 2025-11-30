<?php
/**
 * NexMart Data Seeder
 * Seeds database with sample data for development and testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexMart_Seeder {
    
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
        
        add_action('wp_ajax_nexmart_run_seeder', [$this, 'ajax_run_seeder']);
        add_action('wp_ajax_nexmart_clear_data', [$this, 'ajax_clear_data']);
    }
    
    /**
     * Run all seeders
     */
    public function run_all() {
        $results = [];
        
        $results['categories'] = $this->seed_categories();
        $results['vendors'] = $this->seed_vendors();
        $results['products'] = $this->seed_products();
        $results['campaigns'] = $this->seed_campaigns();
        $results['coupons'] = $this->seed_coupons();
        $results['reviews'] = $this->seed_reviews();
        $results['orders'] = $this->seed_orders();
        
        return $results;
    }
    
    /**
     * Seed categories
     */
    public function seed_categories() {
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'icon' => 'smartphone', 'description' => 'Latest electronic gadgets and devices'],
            ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => 'shirt', 'description' => 'Trendy clothing and accessories'],
            ['name' => 'Home & Garden', 'slug' => 'home-garden', 'icon' => 'home', 'description' => 'Home decor and garden supplies'],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'dribbble', 'description' => 'Sports equipment and activewear'],
            ['name' => 'Beauty', 'slug' => 'beauty', 'icon' => 'sparkles', 'description' => 'Beauty and personal care products'],
            ['name' => 'Books', 'slug' => 'books', 'icon' => 'book-open', 'description' => 'Books, ebooks and audiobooks'],
            ['name' => 'Toys & Games', 'slug' => 'toys-games', 'icon' => 'gamepad-2', 'description' => 'Toys, games and entertainment'],
            ['name' => 'Automotive', 'slug' => 'automotive', 'icon' => 'car', 'description' => 'Car accessories and parts'],
        ];
        
        $count = 0;
        foreach ($categories as $cat) {
            // Check if exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->db->categories_table} WHERE slug = %s",
                $cat['slug']
            ));
            
            if (!$exists) {
                $this->wpdb->insert($this->db->categories_table, $cat);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Seed vendors
     */
    public function seed_vendors() {
        $vendors_data = [
            [
                'user' => ['email' => 'techzone@nexmart.com', 'password' => 'vendor123', 'name' => 'TechZone Admin'],
                'store' => [
                    'store_name' => 'TechZone Electronics',
                    'store_slug' => 'techzone',
                    'store_description' => 'Premium electronics and gadgets at competitive prices',
                    'phone' => '+1 234 567 8901',
                    'city' => 'San Francisco',
                    'country' => 'USA',
                    'status' => 'active',
                    'featured' => 1,
                ],
            ],
            [
                'user' => ['email' => 'fashionhub@nexmart.com', 'password' => 'vendor123', 'name' => 'FashionHub Admin'],
                'store' => [
                    'store_name' => 'Fashion Hub',
                    'store_slug' => 'fashion-hub',
                    'store_description' => 'Latest trends in clothing and accessories',
                    'phone' => '+1 234 567 8902',
                    'city' => 'New York',
                    'country' => 'USA',
                    'status' => 'active',
                    'featured' => 1,
                ],
            ],
            [
                'user' => ['email' => 'homestyle@nexmart.com', 'password' => 'vendor123', 'name' => 'HomeStyle Admin'],
                'store' => [
                    'store_name' => 'HomeStyle Living',
                    'store_slug' => 'homestyle',
                    'store_description' => 'Beautiful home decor and furniture',
                    'phone' => '+1 234 567 8903',
                    'city' => 'Los Angeles',
                    'country' => 'USA',
                    'status' => 'active',
                ],
            ],
            [
                'user' => ['email' => 'sportsmax@nexmart.com', 'password' => 'vendor123', 'name' => 'SportsMax Admin'],
                'store' => [
                    'store_name' => 'SportsMax Pro',
                    'store_slug' => 'sportsmax',
                    'store_description' => 'Professional sports equipment and gear',
                    'phone' => '+1 234 567 8904',
                    'city' => 'Chicago',
                    'country' => 'USA',
                    'status' => 'active',
                ],
            ],
        ];
        
        $count = 0;
        foreach ($vendors_data as $vendor) {
            // Check if vendor email exists
            if (email_exists($vendor['user']['email'])) {
                continue;
            }
            
            // Create user
            $username = sanitize_user(strstr($vendor['user']['email'], '@', true));
            $user_id = wp_create_user($username, $vendor['user']['password'], $vendor['user']['email']);
            
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $vendor['user']['name'],
                    'role' => 'vendor',
                ]);
                
                // Create vendor profile
                $vendor['store']['user_id'] = $user_id;
                $this->wpdb->insert($this->db->vendors_table, $vendor['store']);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Seed products
     */
    public function seed_products() {
        // Get categories and vendors
        $categories = $this->wpdb->get_results("SELECT id, slug FROM {$this->db->categories_table}", OBJECT_K);
        $vendors = $this->wpdb->get_results("SELECT id, store_slug FROM {$this->db->vendors_table}", OBJECT_K);
        
        if (empty($categories) || empty($vendors)) {
            return 0;
        }
        
        $products = [
            // Electronics
            [
                'name' => 'iPhone 15 Pro Max',
                'slug' => 'iphone-15-pro-max',
                'description' => 'The most advanced iPhone ever with A17 Pro chip, titanium design, and 48MP camera system.',
                'short_description' => 'Latest Apple flagship smartphone',
                'price' => 1199.00,
                'sale_price' => 1099.00,
                'sku' => 'IPHONE15PM-256',
                'stock_quantity' => 50,
                'category' => 'electronics',
                'vendor' => 'techzone',
                'featured' => 1,
                'images' => ['https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=600'],
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'description' => 'Galaxy AI is here with the most powerful Galaxy S experience yet.',
                'short_description' => 'Premium Android flagship',
                'price' => 1299.00,
                'sale_price' => 1199.00,
                'sku' => 'SAMS24U-256',
                'stock_quantity' => 45,
                'category' => 'electronics',
                'vendor' => 'techzone',
                'featured' => 1,
                'images' => ['https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=600'],
            ],
            [
                'name' => 'MacBook Pro 16" M3',
                'slug' => 'macbook-pro-16-m3',
                'description' => 'The most advanced laptop for professionals. M3 Pro or M3 Max chip.',
                'short_description' => 'Professional laptop with M3 chip',
                'price' => 2499.00,
                'sku' => 'MBP16M3-512',
                'stock_quantity' => 30,
                'category' => 'electronics',
                'vendor' => 'techzone',
                'images' => ['https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600'],
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'slug' => 'sony-wh-1000xm5',
                'description' => 'Industry-leading noise canceling headphones with exceptional sound quality.',
                'short_description' => 'Premium noise-canceling headphones',
                'price' => 399.00,
                'sale_price' => 349.00,
                'sku' => 'SONYWH1000XM5',
                'stock_quantity' => 100,
                'category' => 'electronics',
                'vendor' => 'techzone',
                'images' => ['https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600'],
            ],
            [
                'name' => 'iPad Pro 12.9"',
                'slug' => 'ipad-pro-12-9',
                'description' => 'Supercharged by M2 with an astonishing display.',
                'short_description' => 'Professional tablet with M2 chip',
                'price' => 1099.00,
                'sku' => 'IPADPRO12-256',
                'stock_quantity' => 40,
                'category' => 'electronics',
                'vendor' => 'techzone',
                'images' => ['https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600'],
            ],
            
            // Fashion
            [
                'name' => 'Premium Cotton T-Shirt',
                'slug' => 'premium-cotton-tshirt',
                'description' => 'Ultra-soft 100% organic cotton t-shirt. Perfect for everyday wear.',
                'short_description' => 'Comfortable organic cotton tee',
                'price' => 39.99,
                'sale_price' => 29.99,
                'sku' => 'TSHIRT-ORG-M',
                'stock_quantity' => 200,
                'category' => 'fashion',
                'vendor' => 'fashion-hub',
                'images' => ['https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600'],
            ],
            [
                'name' => 'Designer Denim Jacket',
                'slug' => 'designer-denim-jacket',
                'description' => 'Classic denim jacket with modern styling. Premium quality construction.',
                'short_description' => 'Stylish premium denim jacket',
                'price' => 129.00,
                'sale_price' => 99.00,
                'sku' => 'DENIM-JKT-L',
                'stock_quantity' => 75,
                'category' => 'fashion',
                'vendor' => 'fashion-hub',
                'featured' => 1,
                'images' => ['https://images.unsplash.com/photo-1543076447-215ad9ba6923?w=600'],
            ],
            [
                'name' => 'Leather Sneakers',
                'slug' => 'leather-sneakers',
                'description' => 'Premium leather sneakers with cushioned sole. Italian craftsmanship.',
                'short_description' => 'Handcrafted leather sneakers',
                'price' => 199.00,
                'sku' => 'SNKR-LTH-42',
                'stock_quantity' => 60,
                'category' => 'fashion',
                'vendor' => 'fashion-hub',
                'images' => ['https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600'],
            ],
            [
                'name' => 'Classic Aviator Sunglasses',
                'slug' => 'classic-aviator-sunglasses',
                'description' => 'Timeless aviator design with polarized lenses and metal frame.',
                'short_description' => 'Polarized aviator sunglasses',
                'price' => 159.00,
                'sale_price' => 129.00,
                'sku' => 'SUNGL-AVI-01',
                'stock_quantity' => 150,
                'category' => 'fashion',
                'vendor' => 'fashion-hub',
                'images' => ['https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=600'],
            ],
            
            // Home & Garden
            [
                'name' => 'Modern Floor Lamp',
                'slug' => 'modern-floor-lamp',
                'description' => 'Minimalist floor lamp with adjustable brightness. Perfect for any room.',
                'short_description' => 'Adjustable modern floor lamp',
                'price' => 189.00,
                'sale_price' => 149.00,
                'sku' => 'LAMP-FLR-01',
                'stock_quantity' => 40,
                'category' => 'home-garden',
                'vendor' => 'homestyle',
                'images' => ['https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=600'],
            ],
            [
                'name' => 'Velvet Accent Chair',
                'slug' => 'velvet-accent-chair',
                'description' => 'Luxurious velvet chair with gold legs. Statement piece for your home.',
                'short_description' => 'Luxury velvet accent chair',
                'price' => 449.00,
                'sku' => 'CHAIR-VLV-GLD',
                'stock_quantity' => 25,
                'category' => 'home-garden',
                'vendor' => 'homestyle',
                'featured' => 1,
                'images' => ['https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=600'],
            ],
            [
                'name' => 'Ceramic Plant Pot Set',
                'slug' => 'ceramic-plant-pot-set',
                'description' => 'Set of 3 handcrafted ceramic pots in varying sizes. Drainage holes included.',
                'short_description' => 'Handcrafted ceramic pot set',
                'price' => 79.00,
                'sale_price' => 59.00,
                'sku' => 'POT-CRM-SET3',
                'stock_quantity' => 80,
                'category' => 'home-garden',
                'vendor' => 'homestyle',
                'images' => ['https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=600'],
            ],
            
            // Sports
            [
                'name' => 'Professional Yoga Mat',
                'slug' => 'professional-yoga-mat',
                'description' => 'Extra thick eco-friendly yoga mat with alignment lines. Non-slip surface.',
                'short_description' => 'Eco-friendly premium yoga mat',
                'price' => 89.00,
                'sale_price' => 69.00,
                'sku' => 'YOGA-MAT-PRO',
                'stock_quantity' => 100,
                'category' => 'sports',
                'vendor' => 'sportsmax',
                'images' => ['https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=600'],
            ],
            [
                'name' => 'Adjustable Dumbbell Set',
                'slug' => 'adjustable-dumbbell-set',
                'description' => '5-50 lbs adjustable dumbbells. Space-saving home gym essential.',
                'short_description' => 'Adjustable weight dumbbells',
                'price' => 349.00,
                'sku' => 'DBELL-ADJ-50',
                'stock_quantity' => 35,
                'category' => 'sports',
                'vendor' => 'sportsmax',
                'featured' => 1,
                'images' => ['https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=600'],
            ],
            [
                'name' => 'Running Shoes Pro',
                'slug' => 'running-shoes-pro',
                'description' => 'Lightweight running shoes with carbon fiber plate. Maximum energy return.',
                'short_description' => 'Professional running shoes',
                'price' => 199.00,
                'sale_price' => 169.00,
                'sku' => 'RUN-SHOE-PRO',
                'stock_quantity' => 70,
                'category' => 'sports',
                'vendor' => 'sportsmax',
                'images' => ['https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600'],
            ],
            [
                'name' => 'Smart Fitness Watch',
                'slug' => 'smart-fitness-watch',
                'description' => 'Advanced fitness tracking with GPS, heart rate, and sleep monitoring.',
                'short_description' => 'Multi-sport fitness tracker',
                'price' => 299.00,
                'sale_price' => 249.00,
                'sku' => 'WATCH-FIT-01',
                'stock_quantity' => 85,
                'category' => 'sports',
                'vendor' => 'sportsmax',
                'images' => ['https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600'],
            ],
        ];
        
        $count = 0;
        $category_map = [];
        $vendor_map = [];
        
        foreach ($categories as $cat) {
            $category_map[$cat->slug] = $cat->id;
        }
        foreach ($vendors as $v) {
            $vendor_map[$v->store_slug] = $v->id;
        }
        
        foreach ($products as $product) {
            // Check if exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->db->products_table} WHERE slug = %s",
                $product['slug']
            ));
            
            if ($exists) {
                continue;
            }
            
            $category_id = $category_map[$product['category']] ?? 0;
            $vendor_id = $vendor_map[$product['vendor']] ?? 0;
            
            if (!$category_id || !$vendor_id) {
                continue;
            }
            
            $images = $product['images'] ?? [];
            unset($product['images'], $product['category'], $product['vendor']);
            
            $product['category_id'] = $category_id;
            $product['vendor_id'] = $vendor_id;
            $product['status'] = 'active';
            
            $this->wpdb->insert($this->db->products_table, $product);
            $product_id = $this->wpdb->insert_id;
            
            if ($product_id) {
                // Add product images
                foreach ($images as $i => $url) {
                    $this->wpdb->insert($this->db->product_images_table, [
                        'product_id' => $product_id,
                        'image_url' => $url,
                        'sort_order' => $i,
                    ]);
                }
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Seed campaigns
     */
    public function seed_campaigns() {
        $campaigns = [
            [
                'name' => 'Flash Sale Weekend',
                'slug' => 'flash-sale-weekend',
                'description' => 'Biggest sale of the month! Up to 50% off on selected items.',
                'discount_type' => 'percentage',
                'discount_value' => 25.00,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
                'status' => 'active',
                'featured' => 1,
            ],
            [
                'name' => 'New Year Sale',
                'slug' => 'new-year-sale',
                'description' => 'Start the year with amazing deals!',
                'discount_type' => 'percentage',
                'discount_value' => 30.00,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'status' => 'active',
            ],
            [
                'name' => 'Electronics Week',
                'slug' => 'electronics-week',
                'description' => 'Special discounts on all electronics!',
                'discount_type' => 'percentage',
                'discount_value' => 15.00,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
                'status' => 'active',
            ],
        ];
        
        $count = 0;
        foreach ($campaigns as $campaign) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->db->campaigns_table} WHERE slug = %s",
                $campaign['slug']
            ));
            
            if (!$exists) {
                $this->wpdb->insert($this->db->campaigns_table, $campaign);
                $campaign_id = $this->wpdb->insert_id;
                
                // Add random products to campaign
                if ($campaign_id) {
                    $products = $this->wpdb->get_col("SELECT id FROM {$this->db->products_table} ORDER BY RAND() LIMIT 5");
                    foreach ($products as $product_id) {
                        $this->wpdb->insert($this->db->campaign_products_table, [
                            'campaign_id' => $campaign_id,
                            'product_id' => $product_id,
                        ]);
                    }
                }
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Seed coupons
     */
    public function seed_coupons() {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'description' => 'Welcome discount - 10% off your first order',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'minimum_order' => 50.00,
                'max_uses' => 1000,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 year')),
                'status' => 'active',
            ],
            [
                'code' => 'SAVE20',
                'description' => 'Save $20 on orders over $100',
                'discount_type' => 'fixed',
                'discount_value' => 20.00,
                'minimum_order' => 100.00,
                'max_uses' => 500,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+6 months')),
                'status' => 'active',
            ],
            [
                'code' => 'FREESHIP',
                'description' => 'Free shipping on all orders',
                'discount_type' => 'shipping',
                'discount_value' => 100.00,
                'minimum_order' => 0.00,
                'max_uses' => 200,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 month')),
                'status' => 'active',
            ],
            [
                'code' => 'VIP25',
                'description' => 'VIP exclusive - 25% off',
                'discount_type' => 'percentage',
                'discount_value' => 25.00,
                'minimum_order' => 200.00,
                'max_uses' => 100,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+3 months')),
                'status' => 'active',
            ],
        ];
        
        $count = 0;
        foreach ($coupons as $coupon) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->db->coupons_table} WHERE code = %s",
                $coupon['code']
            ));
            
            if (!$exists) {
                $this->wpdb->insert($this->db->coupons_table, $coupon);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Seed reviews
     */
    public function seed_reviews() {
        $products = $this->wpdb->get_col("SELECT id FROM {$this->db->products_table}");
        
        if (empty($products)) {
            return 0;
        }
        
        $reviews = [
            ['rating' => 5, 'title' => 'Excellent product!', 'comment' => 'Absolutely love this product. Exceeded my expectations in every way. Highly recommended!'],
            ['rating' => 5, 'title' => 'Best purchase ever', 'comment' => 'The quality is outstanding and the delivery was super fast. Will definitely buy again.'],
            ['rating' => 4, 'title' => 'Great value', 'comment' => 'Very good product for the price. Minor issues but overall very satisfied.'],
            ['rating' => 4, 'title' => 'Good quality', 'comment' => 'Product works as described. Shipping was quick. Would recommend.'],
            ['rating' => 5, 'title' => 'Perfect!', 'comment' => 'Exactly what I was looking for. Excellent craftsmanship and attention to detail.'],
            ['rating' => 3, 'title' => 'Decent product', 'comment' => 'It\'s okay for the price. Could be better but serves its purpose.'],
            ['rating' => 4, 'title' => 'Happy with purchase', 'comment' => 'Nice quality and fast shipping. Would buy from this seller again.'],
        ];
        
        $names = ['John D.', 'Sarah M.', 'Michael R.', 'Emily K.', 'David L.', 'Jessica W.', 'Robert B.', 'Amanda S.'];
        
        $count = 0;
        foreach ($products as $product_id) {
            // Check if product already has reviews
            $has_reviews = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->db->reviews_table} WHERE product_id = %d",
                $product_id
            ));
            
            if ($has_reviews) {
                continue;
            }
            
            // Add 2-4 random reviews per product
            $num_reviews = rand(2, 4);
            $used_indices = [];
            
            for ($i = 0; $i < $num_reviews; $i++) {
                do {
                    $index = array_rand($reviews);
                } while (in_array($index, $used_indices) && count($used_indices) < count($reviews));
                
                $used_indices[] = $index;
                $review = $reviews[$index];
                
                $this->wpdb->insert($this->db->reviews_table, [
                    'product_id' => $product_id,
                    'user_id' => 0,
                    'reviewer_name' => $names[array_rand($names)],
                    'rating' => $review['rating'],
                    'title' => $review['title'],
                    'comment' => $review['comment'],
                    'status' => 'approved',
                ]);
                $count++;
            }
        }
        
        // Update product ratings
        $this->update_product_ratings();
        
        return $count;
    }
    
    /**
     * Update product average ratings
     */
    private function update_product_ratings() {
        $products = $this->wpdb->get_col("SELECT id FROM {$this->db->products_table}");
        
        foreach ($products as $product_id) {
            $avg = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT AVG(rating) FROM {$this->db->reviews_table} 
                 WHERE product_id = %d AND status = 'approved'",
                $product_id
            ));
            
            $count = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->db->reviews_table} 
                 WHERE product_id = %d AND status = 'approved'",
                $product_id
            ));
            
            $this->wpdb->update(
                $this->db->products_table,
                [
                    'rating' => $avg ?: 0,
                    'reviews_count' => $count,
                ],
                ['id' => $product_id]
            );
        }
    }
    
    /**
     * Seed sample orders for testing
     */
    public function seed_orders() {
        // Get first user or create a test user
        $user = get_users(['number' => 1, 'role' => 'customer']);
        if (empty($user)) {
            $user_id = wp_create_user('testcustomer', 'password123', 'customer@example.com');
            wp_update_user([
                'ID' => $user_id,
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'role' => 'customer'
            ]);
        } else {
            $user_id = $user[0]->ID;
        }
        
        $products = $this->wpdb->get_results(
            "SELECT id, name, price, sale_price FROM {$this->db->products_table} LIMIT 10"
        );
        
        if (empty($products)) {
            return 0;
        }
        
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $payment_statuses = ['pending', 'paid', 'failed'];
        $payment_methods = ['card', 'paypal', 'cod'];
        
        $addresses = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1-555-0101',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10001',
                'country' => 'US'
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '+1-555-0102',
                'address' => '456 Oak Avenue',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postcode' => '90001',
                'country' => 'US'
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'Johnson',
                'email' => 'mike.j@example.com',
                'phone' => '+1-555-0103',
                'address' => '789 Pine Road',
                'city' => 'Chicago',
                'state' => 'IL',
                'postcode' => '60601',
                'country' => 'US'
            ]
        ];
        
        $count = 0;
        
        // Create 5 sample orders
        for ($i = 0; $i < 5; $i++) {
            $address = $addresses[array_rand($addresses)];
            $status = $statuses[array_rand($statuses)];
            $payment_status = $status === 'delivered' ? 'paid' : $payment_statuses[array_rand($payment_statuses)];
            
            // Select 1-3 random products
            $num_items = rand(1, 3);
            $order_items = [];
            $subtotal = 0;
            
            for ($j = 0; $j < $num_items; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 2);
                $price = $product->sale_price ?: $product->price;
                $total = $price * $quantity;
                
                $order_items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total
                ];
                
                $subtotal += $total;
            }
            
            $shipping = $subtotal >= 100 ? 0 : 9.99;
            $tax = $subtotal * 0.10;
            $total = $subtotal + $shipping + $tax;
            
            // Create order
            $order_number = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 10));
            
            $this->wpdb->insert($this->db->orders_table, [
                'order_number' => $order_number,
                'user_id' => $user_id,
                'status' => $status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_methods[array_rand($payment_methods)],
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_cost' => $shipping,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'USD',
                'shipping_first_name' => $address['first_name'],
                'shipping_last_name' => $address['last_name'],
                'shipping_email' => $address['email'],
                'shipping_phone' => $address['phone'],
                'shipping_address' => $address['address'],
                'shipping_city' => $address['city'],
                'shipping_state' => $address['state'],
                'shipping_postcode' => $address['postcode'],
                'shipping_country' => $address['country'],
                'billing_first_name' => $address['first_name'],
                'billing_last_name' => $address['last_name'],
                'billing_email' => $address['email'],
                'billing_phone' => $address['phone'],
                'billing_address' => $address['address'],
                'billing_city' => $address['city'],
                'billing_state' => $address['state'],
                'billing_postcode' => $address['postcode'],
                'billing_country' => $address['country'],
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days"))
            ]);
            
            $order_id = $this->wpdb->insert_id;
            
            // Create order items
            foreach ($order_items as $item) {
                // Get product to fetch vendor_id and other details
                $product = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT vendor_id, sku FROM {$this->db->products_table} WHERE id = %d",
                    $item['product_id']
                ));
                
                // Get first product image (ordered by sort_order)
                $product_image = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT image_url FROM {$this->db->product_images_table} WHERE product_id = %d ORDER BY sort_order, id LIMIT 1",
                    $item['product_id']
                ));
                
                $this->wpdb->insert($this->db->order_items_table, [
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'vendor_id' => $product->vendor_id,
                    'product_name' => $item['product_name'],
                    'product_sku' => $product->sku,
                    'product_image' => $product_image ?: '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total' => $item['total']
                ]);
            }
            
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Clear all seeded data
     */
    public function clear_all() {
        $tables = [
            $this->db->reviews_table,
            $this->db->campaign_products_table,
            $this->db->coupons_table,
            $this->db->campaigns_table,
            $this->db->order_items_table,
            $this->db->orders_table,
            $this->db->cart_table,
            $this->db->wishlists_table,
            $this->db->product_attributes_table,
            $this->db->product_images_table,
            $this->db->products_table,
            $this->db->categories_table,
        ];
        
        foreach ($tables as $table) {
            $this->wpdb->query("TRUNCATE TABLE $table");
        }
        
        // Delete vendor users
        $vendor_users = $this->wpdb->get_col("SELECT user_id FROM {$this->db->vendors_table}");
        foreach ($vendor_users as $user_id) {
            wp_delete_user($user_id);
        }
        
        $this->wpdb->query("TRUNCATE TABLE {$this->db->vendors_table}");
        
        return true;
    }
    
    // AJAX Handlers
    
    public function ajax_run_seeder() {
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $results = $this->run_all();
        wp_send_json_success([
            'message' => 'Seeding complete!',
            'results' => $results,
        ]);
    }
    
    public function ajax_clear_data() {
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $this->clear_all();
        wp_send_json_success(['message' => 'All data cleared.']);
    }
}
