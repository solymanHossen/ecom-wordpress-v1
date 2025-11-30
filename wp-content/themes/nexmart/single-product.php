<?php
/**
 * Single Product Template
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

$product_slug = get_query_var('product_slug');
$product = $wpdb->get_row($wpdb->prepare(
    "SELECT p.*, c.name as category_name, c.slug as category_slug, v.store_name as vendor_name 
     FROM {$prefix}products p 
     LEFT JOIN {$prefix}categories c ON p.category_id = c.id 
     LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id 
     WHERE p.slug = %s", $product_slug
));

if (!$product) {
    get_template_part('404');
    exit;
}

$images = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$prefix}product_images WHERE product_id = %d ORDER BY sort_order ASC", $product->id));
$reviews = $wpdb->get_results($wpdb->prepare("SELECT r.*, u.display_name FROM {$prefix}reviews r JOIN {$wpdb->users} u ON r.user_id = u.ID WHERE r.product_id = %d ORDER BY r.created_at DESC", $product->id));
$avg_rating = $wpdb->get_var($wpdb->prepare("SELECT AVG(rating) FROM {$prefix}reviews WHERE product_id = %d", $product->id));
$related = $wpdb->get_results($wpdb->prepare("SELECT p.*, (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id AND sort_order = 0 LIMIT 1) as primary_image FROM {$prefix}products p WHERE p.category_id = %d AND p.id != %d AND p.stock_quantity > 0 LIMIT 4", $product->category_id, $product->id));
$display_price = $product->sale_price ?: $product->price;
$has_discount = $product->sale_price && $product->sale_price < $product->price;
$main_image = !empty($images) ? $images[0]->image_url : 'https://placehold.co/600x600/e2e8f0/64748b?text=Product';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="<?php echo home_url(); ?>">Home</a> / 
            <a href="<?php echo home_url('/shop/'); ?>">Shop</a> / 
            <?php if ($product->category_name): ?>
            <a href="<?php echo home_url('/shop/?category=' . $product->category_id); ?>"><?php echo esc_html($product->category_name); ?></a> / 
            <?php endif; ?>
            <span class="text-gray-900"><?php echo esc_html($product->name); ?></span>
        </nav>
        
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="grid lg:grid-cols-2 gap-8 p-8">
                <!-- Images -->
                <div>
                    <div class="aspect-square bg-gray-100 rounded-2xl overflow-hidden mb-4">
                        <img id="main-image" src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($product->name); ?>" class="w-full h-full object-contain">
                    </div>
                    <?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-5 gap-2">
                        <?php foreach ($images as $img): ?>
                        <button onclick="document.getElementById('main-image').src='<?php echo esc_url($img->image_url); ?>'" class="aspect-square bg-gray-100 rounded-lg overflow-hidden border-2 hover:border-primary-500">
                            <img src="<?php echo esc_url($img->image_url); ?>" class="w-full h-full object-cover">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Details -->
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="bg-primary-100 text-primary-700 text-xs px-3 py-1 rounded-full"><?php echo esc_html($product->category_name ?: 'General'); ?></span>
                        <?php if ($product->featured): ?>
                        <span class="bg-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded-full">Featured</span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo esc_html($product->name); ?></h1>
                    
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i data-lucide="star" class="w-5 h-5 <?php echo $i <= round($avg_rating ?: 0) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'; ?>"></i>
                            <?php endfor; ?>
                            <span class="ml-2 text-gray-600">(<?php echo count($reviews); ?> reviews)</span>
                        </div>
                        <?php if ($product->vendor_name): ?>
                        <span class="text-gray-500">by <a href="#" class="text-primary-600"><?php echo esc_html($product->vendor_name); ?></a></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-primary-600">$<?php echo number_format($display_price, 2); ?></span>
                        <?php if ($has_discount): ?>
                        <span class="text-xl text-gray-400 line-through ml-3">$<?php echo number_format($product->price, 2); ?></span>
                        <span class="ml-2 bg-red-500 text-white text-sm px-2 py-1 rounded">Save <?php echo round((($product->price - $display_price) / $product->price) * 100); ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="prose prose-gray mb-8 max-w-none">
                        <?php echo wpautop(esc_html($product->description)); ?>
                    </div>
                    
                    <div class="flex items-center gap-4 mb-6">
                        <span class="text-gray-600">Quantity:</span>
                        <div class="flex items-center border rounded-lg">
                            <button type="button" class="qty-btn w-10 h-10 flex items-center justify-center hover:bg-gray-100" data-action="decrease">-</button>
                            <input type="number" id="qty" value="1" min="1" max="<?php echo $product->stock_quantity; ?>" class="qty-input w-16 text-center border-x">
                            <button type="button" class="qty-btn w-10 h-10 flex items-center justify-center hover:bg-gray-100" data-action="increase">+</button>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo $product->stock_quantity; ?> available</span>
                    </div>
                    
                    <div class="flex gap-4">
                        <button id="add-to-cart-btn" data-product-id="<?php echo $product->id; ?>" class="add-to-cart-btn flex-1 bg-primary-600 text-white py-4 px-8 rounded-xl font-semibold hover:bg-primary-700 transition flex items-center justify-center gap-2">
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            Add to Cart
                        </button>
                        <button class="wishlist-btn w-14 h-14 border-2 border-gray-200 rounded-xl flex items-center justify-center hover:border-red-500 hover:text-red-500" data-product-id="<?php echo $product->id; ?>">
                            <i data-lucide="heart" class="w-6 h-6"></i>
                        </button>
                    </div>
                    
                    <div class="mt-8 pt-8 border-t">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="flex flex-col items-center">
                                <i data-lucide="truck" class="w-8 h-8 text-primary-600 mb-2"></i>
                                <span class="text-sm font-medium">Free Shipping</span>
                                <span class="text-xs text-gray-500">On orders over $50</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i data-lucide="refresh-cw" class="w-8 h-8 text-primary-600 mb-2"></i>
                                <span class="text-sm font-medium">Easy Returns</span>
                                <span class="text-xs text-gray-500">30-day return policy</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i data-lucide="shield-check" class="w-8 h-8 text-primary-600 mb-2"></i>
                                <span class="text-sm font-medium">Secure Payment</span>
                                <span class="text-xs text-gray-500">100% protected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reviews -->
        <div class="bg-white rounded-2xl shadow-sm mt-8 p-8">
            <h2 class="text-2xl font-bold mb-6">Customer Reviews (<?php echo count($reviews); ?>)</h2>
            <?php if ($reviews): ?>
            <div class="space-y-6">
                <?php foreach ($reviews as $review): ?>
                <div class="border-b pb-6">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center font-bold text-primary-600">
                            <?php echo strtoupper(substr($review->display_name, 0, 1)); ?>
                        </div>
                        <div>
                            <p class="font-semibold"><?php echo esc_html($review->display_name); ?></p>
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i data-lucide="star" class="w-4 h-4 <?php echo $i <= $review->rating ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ml-2 text-xs text-gray-500"><?php echo date('M j, Y', strtotime($review->created_at)); ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600"><?php echo esc_html($review->comment); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500">No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </div>
        
        <!-- Related Products -->
        <?php if ($related): ?>
        <div class="mt-12">
            <h2 class="text-2xl font-bold mb-6">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($related as $rel): ?>
                <div class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-gray-100">
                    <a href="<?php echo home_url('/product/' . $rel->slug); ?>">
                        <div class="aspect-square bg-gray-100">
                            <img src="<?php echo esc_url($rel->primary_image ?: 'https://placehold.co/400x400/e2e8f0/64748b?text=Product'); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        </div>
                    </a>
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2"><a href="<?php echo home_url('/product/' . $rel->slug); ?>"><?php echo esc_html($rel->name); ?></a></h3>
                        <span class="text-lg font-bold text-primary-600">$<?php echo number_format($rel->sale_price ?: $rel->price, 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateQty(delta) {
    const input = document.getElementById('qty');
    const newVal = Math.max(1, Math.min(<?php echo $product->stock_quantity; ?>, parseInt(input.value) + delta));
    input.value = newVal;
}
</script>

<?php get_footer(); ?>
