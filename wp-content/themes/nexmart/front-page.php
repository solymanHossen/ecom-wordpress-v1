<?php
/**
 * NexMart Homepage Template
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

// Get Flash Sales Campaign
$flash_campaign = $wpdb->get_row("SELECT * FROM {$prefix}campaigns WHERE status = 'active' AND end_date > NOW() ORDER BY end_date ASC LIMIT 1");

// Get Categories
$categories = $wpdb->get_results("SELECT * FROM {$prefix}categories ORDER BY name ASC LIMIT 8");

// Get Flash Sale Products
$flash_products = [];
if ($flash_campaign) {
    $flash_products = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, v.store_name as vendor_name, 
         (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) as primary_image
         FROM {$prefix}products p 
         JOIN {$prefix}campaign_products cp ON p.id = cp.product_id 
         LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id 
         WHERE cp.campaign_id = %d AND p.stock_quantity > 0 
         ORDER BY RAND() LIMIT 8", 
        $flash_campaign->id
    ));
}

// Get Featured Products
$featured_products = $wpdb->get_results(
    "SELECT p.*, v.store_name as vendor_name, 
     (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1) as primary_image
     FROM {$prefix}products p 
     LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id 
     WHERE p.featured = 1 AND p.stock_quantity > 0 
     ORDER BY p.created_at DESC LIMIT 8"
);

// Get Top Vendors
$vendors = $wpdb->get_results(
    "SELECT v.*, (SELECT COUNT(*) FROM {$prefix}products WHERE vendor_id = v.id) as product_count 
     FROM {$prefix}vendors v 
     WHERE v.status = 'active' 
     ORDER BY product_count DESC LIMIT 4"
);
?>

<!-- Hero Section -->
<section class="relative bg-gradient-to-r from-indigo-600 to-indigo-800 text-white overflow-hidden">
    <div class="container mx-auto px-4 py-16 lg:py-24 relative z-10">
        <div class="grid lg:grid-cols-2 gap-8 items-center">
            <div class="space-y-6">
                <span class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-medium">🔥 Special Offer - Limited Time</span>
                <h1 class="text-4xl lg:text-6xl font-bold leading-tight">Discover Amazing<br><span class="text-yellow-300">Products</span> at Best Prices</h1>
                <p class="text-lg text-white/80 max-w-lg">Shop from thousands of vendors with exclusive deals, fast delivery, and secure payments.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="<?php echo home_url('/shop/'); ?>" class="inline-flex items-center gap-2 bg-white text-indigo-600 px-8 py-4 rounded-full font-semibold hover:bg-yellow-300 transition-all duration-300 shadow-lg hover:shadow-xl">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i> Shop Now
                    </a>
                </div>
            </div>
            <div class="hidden lg:block">
                <div class="relative bg-white/10 backdrop-blur rounded-3xl p-8 border border-white/20">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/20 rounded-2xl p-6 text-center">
                            <i data-lucide="package" class="w-8 h-8 mx-auto mb-2"></i>
                            <div class="text-2xl font-bold">16+</div>
                            <div class="text-sm opacity-80">Products</div>
                        </div>
                        <div class="bg-white/20 rounded-2xl p-6 text-center">
                            <i data-lucide="store" class="w-8 h-8 mx-auto mb-2"></i>
                            <div class="text-2xl font-bold">4+</div>
                            <div class="text-sm opacity-80">Vendors</div>
                        </div>
                        <div class="bg-white/20 rounded-2xl p-6 text-center">
                            <i data-lucide="truck" class="w-8 h-8 mx-auto mb-2"></i>
                            <div class="text-2xl font-bold">Free</div>
                            <div class="text-sm opacity-80">Delivery</div>
                        </div>
                        <div class="bg-white/20 rounded-2xl p-6 text-center">
                            <i data-lucide="shield-check" class="w-8 h-8 mx-auto mb-2"></i>
                            <div class="text-2xl font-bold">100%</div>
                            <div class="text-sm opacity-80">Secure</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Shop by Category</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php if ($categories): foreach ($categories as $cat): $icon = $cat->icon ?: 'package'; ?>
            <a href="<?php echo home_url('/shop/?category=' . $cat->id); ?>" class="group bg-white rounded-2xl p-6 text-center shadow-sm hover:shadow-xl transition-all border border-gray-100 hover:-translate-y-1">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-indigo-500">
                    <i data-lucide="<?php echo esc_attr($icon); ?>" class="w-8 h-8 text-indigo-600 group-hover:text-white"></i>
                </div>
                <h3 class="font-semibold text-gray-900"><?php echo esc_html($cat->name); ?></h3>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- Flash Deals -->
<?php if ($flash_campaign && !empty($flash_products)): ?>
<section class="py-16 bg-gradient-to-r from-red-500 to-orange-500">
    <div class="container mx-auto px-4">
        <div class="text-white text-center mb-8">
            <h2 class="text-3xl font-bold flex items-center justify-center gap-3">
                <i data-lucide="zap" class="w-8 h-8 text-yellow-300"></i>
                <?php echo esc_html($flash_campaign->name); ?>
            </h2>
            <p class="text-white/80 mt-2">Ends: <?php echo date('M j, Y H:i', strtotime($flash_campaign->end_date)); ?></p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($flash_products as $product): 
                $image = $product->primary_image ?: 'https://placehold.co/400x400/e2e8f0/64748b?text=Product';
                $discount = $flash_campaign->discount_value;
                $discount_price = $flash_campaign->discount_type === 'percentage' 
                    ? $product->price * (1 - $discount/100) 
                    : $product->price - $discount;
            ?>
            <div class="group bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all">
                <div class="relative">
                    <a href="<?php echo home_url('/product/' . $product->slug); ?>">
                        <div class="aspect-square bg-gray-100">
                            <img src="<?php echo esc_url($image); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        </div>
                    </a>
                    <span class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                        -<?php echo $flash_campaign->discount_type === 'percentage' ? round($discount) . '%' : '$' . $discount; ?>
                    </span>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 line-clamp-2 mb-2"><a href="<?php echo home_url('/product/' . $product->slug); ?>"><?php echo esc_html($product->name); ?></a></h3>
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-bold text-red-600">$<?php echo number_format($discount_price, 2); ?></span>
                        <span class="text-sm text-gray-400 line-through">$<?php echo number_format($product->price, 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<section class="py-16">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Featured Products</h2>
            <a href="<?php echo home_url('/shop/?featured=1'); ?>" class="text-indigo-600 hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php if ($featured_products): foreach ($featured_products as $product): 
                $image = $product->primary_image ?: 'https://placehold.co/400x400/e2e8f0/64748b?text=Product';
                $display_price = $product->sale_price ?: $product->price;
            ?>
            <div class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-gray-100 hover:-translate-y-1">
                <div class="relative">
                    <a href="<?php echo home_url('/product/' . $product->slug); ?>">
                        <div class="aspect-square bg-gray-100">
                            <img src="<?php echo esc_url($image); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        </div>
                    </a>
                    <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                    <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">SALE</span>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <p class="text-xs text-gray-500 mb-1"><?php echo esc_html($product->vendor_name ?: 'NexMart'); ?></p>
                    <h3 class="font-semibold text-gray-900 line-clamp-2 mb-2"><a href="<?php echo home_url('/product/' . $product->slug); ?>"><?php echo esc_html($product->name); ?></a></h3>
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-bold text-indigo-600">$<?php echo number_format($display_price, 2); ?></span>
                        <div class="flex gap-2">
                            <button class="wishlist-btn w-10 h-10 border border-gray-200 text-gray-500 rounded-full flex items-center justify-center hover:border-red-500 hover:text-red-500" data-product-id="<?php echo $product->id; ?>">
                                <i data-lucide="heart" class="w-5 h-5"></i>
                            </button>
                            <button class="add-to-cart-btn w-10 h-10 bg-indigo-500 text-white rounded-full flex items-center justify-center hover:bg-indigo-600" data-product-id="<?php echo $product->id; ?>">
                                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="col-span-4 text-center py-8 text-gray-500">No featured products available.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Top Vendors -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900">Top Vendors</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php if ($vendors): foreach ($vendors as $vendor): ?>
            <div class="bg-white rounded-2xl p-6 text-center shadow-sm hover:shadow-xl transition-all border border-gray-100">
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <?php if ($vendor->logo_url): ?>
                    <img src="<?php echo esc_url($vendor->logo_url); ?>" class="w-16 h-16 rounded-full object-cover">
                    <?php else: ?>
                    <i data-lucide="store" class="w-10 h-10 text-indigo-600"></i>
                    <?php endif; ?>
                </div>
                <h3 class="font-semibold text-gray-900"><?php echo esc_html($vendor->store_name); ?></h3>
                <p class="text-sm text-gray-500"><?php echo intval($vendor->product_count); ?> Products</p>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="py-16 bg-indigo-600 text-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Subscribe to Our Newsletter</h2>
        <p class="text-indigo-100 mb-8 max-w-lg mx-auto">Get exclusive deals, new arrivals, and special offers delivered to your inbox.</p>
        <form class="max-w-md mx-auto flex gap-2">
            <input type="email" placeholder="Enter your email" class="flex-1 px-6 py-4 rounded-full text-gray-900 focus:outline-none">
            <button type="submit" class="bg-yellow-400 text-gray-900 px-8 py-4 rounded-full font-semibold hover:bg-yellow-300 transition">Subscribe</button>
        </form>
    </div>
</section>

<!-- Features -->
<section class="py-16">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="truck" class="w-8 h-8 text-indigo-600"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Free Shipping</h3>
                <p class="text-sm text-gray-500">On orders over $50</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="refresh-cw" class="w-8 h-8 text-indigo-600"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Easy Returns</h3>
                <p class="text-sm text-gray-500">30-day return policy</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="shield-check" class="w-8 h-8 text-indigo-600"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Secure Payment</h3>
                <p class="text-sm text-gray-500">100% protected</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="headphones" class="w-8 h-8 text-indigo-600"></i>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">24/7 Support</h3>
                <p class="text-sm text-gray-500">Always here to help</p>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
