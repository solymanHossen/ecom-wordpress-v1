<?php
/**
 * Template for Single Vendor Store Page
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

// Get vendor slug from query var
$vendor_slug = get_query_var('vendor_slug', '');

if (empty($vendor_slug)) {
    // Redirect to shop if no vendor slug
    wp_redirect(home_url('/shop'));
    exit;
}

// Get vendor information
$vendor = $wpdb->get_row($wpdb->prepare(
    "SELECT v.*, u.user_email, u.display_name 
    FROM {$prefix}vendors v 
    JOIN {$wpdb->prefix}users u ON v.user_id = u.ID 
    WHERE v.store_slug = %s AND v.status = 'active'",
    $vendor_slug
));

if (!$vendor) {
    // Vendor not found
    get_template_part('404');
    get_footer();
    exit;
}

// Get vendor products with pagination
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';

// Build query
$where = ["p.vendor_id = %d", "p.status = 'published'"];
$params = [$vendor->id];

if ($search) {
    $where[] = '(p.name LIKE %s OR p.description LIKE %s)';
    $params[] = '%' . $wpdb->esc_like($search) . '%';
    $params[] = '%' . $wpdb->esc_like($search) . '%';
}

$where_clause = implode(' AND ', $where);
$order_by = $sort === 'price_low' ? 'p.price ASC' : ($sort === 'price_high' ? 'p.price DESC' : 'p.created_at DESC');

// Get total products count
$count_query = "SELECT COUNT(*) FROM {$prefix}products p WHERE $where_clause";
$total = $wpdb->get_var($wpdb->prepare($count_query, ...$params));
$total_pages = ceil($total / $per_page);

// Get products
$product_query = "SELECT p.*, 
    (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id AND sort_order = 0 LIMIT 1) as primary_image,
    (SELECT AVG(rating) FROM {$prefix}reviews WHERE product_id = p.id) as avg_rating,
    (SELECT COUNT(*) FROM {$prefix}reviews WHERE product_id = p.id) as review_count
    FROM {$prefix}products p 
    WHERE $where_clause 
    ORDER BY $order_by 
    LIMIT $per_page OFFSET $offset";
$products = $wpdb->get_results($wpdb->prepare($product_query, ...$params));

// Calculate vendor rating
$vendor_rating = $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(r.rating) 
    FROM {$prefix}reviews r 
    JOIN {$prefix}products p ON r.product_id = p.id 
    WHERE p.vendor_id = %d",
    $vendor->id
));

$total_reviews = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT r.id) 
    FROM {$prefix}reviews r 
    JOIN {$prefix}products p ON r.product_id = p.id 
    WHERE p.vendor_id = %d",
    $vendor->id
));
?>

<div class="bg-gray-50 min-h-screen">
    <!-- Vendor Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <div class="container mx-auto px-4 py-12">
            <div class="flex items-start gap-8">
                <!-- Store Logo/Avatar -->
                <div class="flex-shrink-0">
                    <div class="w-32 h-32 bg-white rounded-lg shadow-lg flex items-center justify-center">
                        <span class="text-4xl font-bold text-blue-600">
                            <?php echo esc_html(substr($vendor->store_name, 0, 2)); ?>
                        </span>
                    </div>
                </div>

                <!-- Store Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-4xl font-bold"><?php echo esc_html($vendor->store_name); ?></h1>
                        <?php if ($vendor_rating): ?>
                            <div class="flex items-center bg-white/20 rounded-lg px-3 py-1">
                                <svg class="w-5 h-5 text-yellow-300 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <span class="font-semibold"><?php echo number_format($vendor_rating, 1); ?></span>
                                <span class="text-white/80 ml-1">(<?php echo $total_reviews; ?>)</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($vendor->store_description): ?>
                        <p class="text-white/90 text-lg mb-4 max-w-3xl"><?php echo esc_html($vendor->store_description); ?></p>
                    <?php endif; ?>

                    <!-- Store Stats -->
                    <div class="flex gap-6 mb-4">
                        <div>
                            <div class="text-2xl font-bold"><?php echo number_format($total); ?></div>
                            <div class="text-white/80 text-sm">Products</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold">$<?php echo number_format($vendor->total_sales, 2); ?></div>
                            <div class="text-white/80 text-sm">Total Sales</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold"><?php echo date('Y', strtotime($vendor->created_at)); ?></div>
                            <div class="text-white/80 text-sm">Member Since</div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <?php if ($vendor->phone || $vendor->address): ?>
                        <div class="flex gap-4 text-sm text-white/80">
                            <?php if ($vendor->phone): ?>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    <?php echo esc_html($vendor->phone); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($vendor->address): ?>
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <?php echo esc_html($vendor->city ? $vendor->city . ', ' . $vendor->state : $vendor->address); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <a href="<?php echo home_url(); ?>" class="hover:text-primary-600">Home</a> / 
            <a href="<?php echo home_url('/shop'); ?>" class="hover:text-primary-600">Shop</a> / 
            <span class="text-gray-900"><?php echo esc_html($vendor->store_name); ?></span>
        </nav>

        <!-- Filters & Search -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                <!-- Search -->
                <form method="GET" class="flex-1 max-w-md">
                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               value="<?php echo esc_attr($search); ?>" 
                               placeholder="Search in this store..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </form>

                <!-- Sort -->
                <div class="flex items-center gap-4">
                    <span class="text-gray-600 text-sm"><?php echo count($products); ?> of <?php echo $total; ?> products</span>
                    <select name="sort" 
                            onchange="window.location.href='<?php echo add_query_arg('sort', '', $_SERVER['REQUEST_URI']); ?>' + this.value" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="newest" <?php selected($sort, 'newest'); ?>>Newest</option>
                        <option value="price_low" <?php selected($sort, 'price_low'); ?>>Price: Low to High</option>
                        <option value="price_high" <?php selected($sort, 'price_high'); ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <!-- No Products -->
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Products Found</h3>
                <p class="text-gray-600">This store doesn't have any products yet<?php echo $search ? ' matching your search' : ''; ?>.</p>
                <?php if ($search): ?>
                    <a href="<?php echo remove_query_arg('search'); ?>" class="inline-block mt-4 text-primary-600 hover:text-primary-700 font-medium">
                        Clear Search
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Products Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                    <?php
                    $final_price = $product->sale_price > 0 && $product->sale_price < $product->price ? $product->sale_price : $product->price;
                    $discount_percent = $product->sale_price > 0 && $product->sale_price < $product->price ? round((($product->price - $product->sale_price) / $product->price) * 100) : 0;
                    $product_url = home_url('/product/' . $product->slug);
                    ?>
                    <div class="bg-white rounded-lg shadow-sm hover:shadow-lg transition-shadow duration-300 overflow-hidden group">
                        <a href="<?php echo esc_url($product_url); ?>" class="block">
                            <!-- Product Image -->
                            <div class="relative aspect-square overflow-hidden bg-gray-100">
                                <?php if ($product->primary_image): ?>
                                    <img src="<?php echo esc_url($product->primary_image); ?>" 
                                         alt="<?php echo esc_attr($product->name); ?>" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($discount_percent > 0): ?>
                                    <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded-lg text-sm font-bold">
                                        -<?php echo $discount_percent; ?>%
                                    </div>
                                <?php endif; ?>

                                <?php if ($product->stock_quantity <= 0): ?>
                                    <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                        <span class="bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold">Out of Stock</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors">
                                    <?php echo esc_html($product->name); ?>
                                </h3>

                                <!-- Rating -->
                                <?php if ($product->avg_rating): ?>
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="flex items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <svg class="w-4 h-4 <?php echo $i <= round($product->avg_rating) ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm text-gray-600">(<?php echo $product->review_count; ?>)</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Price -->
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-xl font-bold text-primary-600">
                                        $<?php echo number_format($final_price, 2); ?>
                                    </span>
                                    <?php if ($discount_percent > 0): ?>
                                        <span class="text-sm text-gray-500 line-through">
                                            $<?php echo number_format($product->price, 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Stock Info -->
                                <?php if ($product->stock_quantity > 0 && $product->stock_quantity <= 10): ?>
                                    <p class="text-sm text-orange-600 mb-3">Only <?php echo $product->stock_quantity; ?> left in stock</p>
                                <?php endif; ?>
                            </div>
                        </a>

                        <!-- Add to Cart Button -->
                        <div class="px-4 pb-4">
                            <?php if ($product->stock_quantity > 0): ?>
                                <button onclick="addToCart(<?php echo $product->id; ?>, event)" 
                                        class="w-full bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700 transition-colors font-medium">
                                    Add to Cart
                                </button>
                            <?php else: ?>
                                <button disabled class="w-full bg-gray-300 text-gray-500 py-2 rounded-lg cursor-not-allowed font-medium">
                                    Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center gap-2 mt-8">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo add_query_arg('paged', $page - 1); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="<?php echo add_query_arg('paged', $i); ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo add_query_arg('paged', $page + 1); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
