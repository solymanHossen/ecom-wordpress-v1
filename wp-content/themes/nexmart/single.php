<?php get_header(); ?>

<div class="container mx-auto px-4 py-8 animate-fade-in">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    
    <a href="<?php echo home_url(); ?>" class="mb-6 flex items-center text-gray-500 hover:text-indigo-600 transition-colors w-fit">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Home
    </a>

    <div class="grid md:grid-cols-2 gap-12">
        <!-- Gallery Section -->
        <div class="space-y-4">
            <div class="aspect-square bg-gray-100 rounded-2xl overflow-hidden border border-gray-200 relative group">
                <?php if ( has_post_thumbnail() ) {
                    the_post_thumbnail('full', array('class' => 'w-full h-full object-cover'));
                } else {
                    echo '<div class="w-full h-full flex items-center justify-center text-gray-400"><i data-lucide="image" class="w-16 h-16"></i></div>';
                } ?>
                <div class="absolute top-4 right-4 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="p-3 bg-white rounded-full shadow-lg hover:text-indigo-600">
                        <i data-lucide="share-2" class="w-5 h-5"></i>
                    </button>
                    <button class="p-3 bg-white rounded-full shadow-lg hover:text-rose-600">
                        <i data-lucide="heart" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <!-- Thumbnails would go here dynamically -->
        </div>

        <!-- Product Info -->
        <div>
            <div class="flex items-center gap-2 mb-4">
                <?php 
                $cats = get_the_category();
                if($cats) {
                    echo '<span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">' . esc_html($cats[0]->name) . '</span>';
                }
                ?>
            </div>
            
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"><?php the_title(); ?></h1>
            
            <div class="flex items-center gap-4 mb-6">
                <div class="flex text-yellow-400">
                    <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                    <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                    <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                    <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                    <i data-lucide="star" class="w-4 h-4"></i>
                </div>
                <span class="text-gray-500">(120 Reviews)</span>
                <span class="text-gray-300">|</span>
                <span class="text-green-600 text-sm font-medium flex items-center gap-1">
                    <i data-lucide="package" class="w-4 h-4"></i> In Stock
                </span>
            </div>

            <div class="prose text-gray-600 mb-8">
                <?php the_excerpt(); ?>
            </div>

            <!-- Add to Cart Area (Simulated or Real WC) -->
            <div class="space-y-6 border-t border-b border-gray-100 py-6 mb-8">
                <?php if(class_exists('WooCommerce')) { 
                    woocommerce_template_single_add_to_cart();
                } else { 
                    // Get product from custom nexmart_products table if exists
                    global $wpdb;
                    $prefix = $wpdb->prefix . 'nexmart_';
                    $post_id = get_the_ID();
                    
                    // Try to find matching product by post title/slug
                    $product = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$prefix}products WHERE slug = %s OR name = %s LIMIT 1",
                        get_post_field('post_name', $post_id),
                        get_the_title()
                    ));
                    
                    if ($product) {
                        $product_id = $product->id;
                        $stock = $product->stock_quantity;
                        $price = $product->sale_price ?: $product->price;
                ?>
                    <div class="text-3xl font-bold text-indigo-600 mb-4">
                        $<?php echo number_format($price, 2); ?>
                        <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                        <span class="text-lg text-gray-400 line-through ml-2">$<?php echo number_format($product->price, 2); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="text-gray-600">Quantity:</span>
                        <div class="flex items-center border rounded-lg">
                            <button type="button" class="qty-btn w-10 h-10 flex items-center justify-center hover:bg-gray-100" data-action="decrease">-</button>
                            <input type="number" id="qty" value="1" min="1" max="<?php echo $stock; ?>" class="qty-input w-16 text-center border-x">
                            <button type="button" class="qty-btn w-10 h-10 flex items-center justify-center hover:bg-gray-100" data-action="increase">+</button>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo $stock; ?> in stock</span>
                    </div>
                    <div class="flex gap-4">
                        <button id="add-to-cart-btn" data-product-id="<?php echo $product_id; ?>" class="add-to-cart-btn flex-1 bg-indigo-600 text-white py-4 rounded-xl font-bold shadow-lg hover:bg-indigo-700 hover:shadow-indigo-200 transition-all flex justify-center items-center gap-2">
                           <i data-lucide="shopping-cart" class="w-5 h-5"></i> Add to Cart
                        </button>
                        <button class="wishlist-btn w-14 h-14 border-2 border-gray-200 rounded-xl flex items-center justify-center hover:border-red-500 hover:text-red-500" data-product-id="<?php echo $product_id; ?>">
                            <i data-lucide="heart" class="w-6 h-6"></i>
                        </button>
                    </div>
                <?php } else { ?>
                    <div class="bg-gray-100 p-6 rounded-xl text-center">
                        <p class="text-gray-600 mb-4">This is a blog post. Visit our shop to purchase products.</p>
                        <a href="<?php echo home_url('/shop/'); ?>" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
                            Browse Products
                        </a>
                    </div>
                <?php } ?>
                <?php } ?>
            </div>

            <!-- Features -->
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <i data-lucide="truck" class="text-indigo-600 w-6 h-6"></i>
                    <div>
                        <p class="font-bold text-sm">Free Delivery</p>
                        <p class="text-xs text-gray-500">Enter your Postal code for Delivery Availability</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <i data-lucide="shield" class="text-indigo-600 w-6 h-6"></i>
                    <div>
                        <p class="font-bold text-sm">Return Delivery</p>
                        <p class="text-xs text-gray-500">Free 30 Days Delivery Returns.</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8">
                 <?php the_content(); ?>
            </div>
        </div>
    </div>

    <?php endwhile; endif; ?>
</div>

<?php get_footer(); ?>
