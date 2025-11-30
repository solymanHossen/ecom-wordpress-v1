<?php
/**
 * Template Name: Shop Page
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$campaign_id = isset($_GET['campaign']) ? intval($_GET['campaign']) : 0;
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';
$featured = isset($_GET['featured']) ? 1 : 0;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = ['p.stock_quantity > 0'];
$params = [];
if ($category_id) { $where[] = 'p.category_id = %d'; $params[] = $category_id; }
if ($search) { $where[] = '(p.name LIKE %s OR p.description LIKE %s)'; $params[] = '%' . $wpdb->esc_like($search) . '%'; $params[] = '%' . $wpdb->esc_like($search) . '%'; }
if ($featured) { $where[] = 'p.featured = 1'; }
$where_clause = implode(' AND ', $where);
$order_by = $sort === 'price_low' ? 'p.price ASC' : ($sort === 'price_high' ? 'p.price DESC' : 'p.created_at DESC');
$join_clause = $campaign_id ? "JOIN {$prefix}campaign_products cp ON p.id = cp.product_id AND cp.campaign_id = " . intval($campaign_id) : '';

$count_query = "SELECT COUNT(DISTINCT p.id) FROM {$prefix}products p $join_clause WHERE $where_clause";
$total = $wpdb->get_var($params ? $wpdb->prepare($count_query, ...$params) : $count_query);
$total_pages = ceil($total / $per_page);

$product_query = "SELECT p.*, v.store_name as vendor_name, (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id AND sort_order = 0 LIMIT 1) as primary_image, (SELECT AVG(rating) FROM {$prefix}reviews WHERE product_id = p.id) as avg_rating" . ($campaign_id ? ", cp.discount_price" : "") . " FROM {$prefix}products p LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id $join_clause WHERE $where_clause ORDER BY $order_by LIMIT $per_page OFFSET $offset";
$products = $wpdb->get_results($params ? $wpdb->prepare($product_query, ...$params) : $product_query);
$categories = $wpdb->get_results("SELECT * FROM {$prefix}categories ORDER BY name ASC");
?>

<div class="bg-gray-50 min-h-screen">
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-8">
            <nav class="text-sm text-gray-500 mb-4">
                <a href="<?php echo home_url(); ?>">Home</a> / <span class="text-gray-900">Shop</span>
            </nav>
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900"><?php echo $search ? 'Search: "' . esc_html($search) . '"' : 'All Products'; ?></h1>
                <form class="flex gap-2" method="GET">
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search..." class="px-4 py-2 border rounded-lg">
                    <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg">Search</button>
                </form>
            </div>
            <p class="text-gray-600 mt-2"><?php echo count($products); ?> of <?php echo $total; ?> products</p>
        </div>
    </div>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex gap-8">
            <!-- Sidebar -->
            <aside class="w-64 hidden lg:block">
                <div class="bg-white rounded-xl p-6 shadow-sm sticky top-24">
                    <h3 class="font-bold text-lg mb-4">Categories</h3>
                    <div class="space-y-2">
                        <a href="<?php echo home_url('/shop/'); ?>" class="block py-2 px-3 rounded-lg <?php echo !$category_id ? 'bg-primary-50 text-primary-600' : 'hover:bg-gray-50'; ?>">All Categories</a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo $cat->id; ?>" class="block py-2 px-3 rounded-lg <?php echo $category_id == $cat->id ? 'bg-primary-50 text-primary-600' : 'hover:bg-gray-50'; ?>"><?php echo esc_html($cat->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <h3 class="font-bold text-lg mt-6 mb-4">Sort By</h3>
                    <select onchange="window.location.href=this.value" class="w-full border rounded-lg px-3 py-2">
                        <option value="?sort=newest" <?php selected($sort, 'newest'); ?>>Newest</option>
                        <option value="?sort=price_low" <?php selected($sort, 'price_low'); ?>>Price: Low to High</option>
                        <option value="?sort=price_high" <?php selected($sort, 'price_high'); ?>>Price: High to Low</option>
                    </select>
                </div>
            </aside>
            
            <!-- Products Grid -->
            <main class="flex-1">
                <?php if ($products): ?>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): 
                        $image = $product->primary_image ?: 'https://placehold.co/400x400/e2e8f0/64748b?text=Product';
                        $display_price = isset($product->discount_price) ? $product->discount_price : ($product->sale_price ?: $product->price);
                        $has_discount = $display_price < $product->price;
                    ?>
                    <div class="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-gray-100 hover:-translate-y-1">
                        <div class="relative">
                            <a href="<?php echo home_url('/product/' . $product->slug); ?>">
                                <div class="aspect-square bg-gray-100">
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product->name); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                </div>
                            </a>
                            <?php if ($has_discount): $discount = round((($product->price - $display_price) / $product->price) * 100); ?>
                            <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1"><?php echo esc_html($product->vendor_name ?: 'NexMart'); ?></p>
                            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary-600">
                                <a href="<?php echo home_url('/product/' . $product->slug); ?>"><?php echo esc_html($product->name); ?></a>
                            </h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-lg font-bold text-primary-600">$<?php echo number_format($display_price, 2); ?></span>
                                    <?php if ($has_discount): ?>
                                    <span class="text-sm text-gray-400 line-through ml-1">$<?php echo number_format($product->price, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2">
                                    <button class="wishlist-btn w-10 h-10 border border-gray-200 text-gray-500 rounded-full flex items-center justify-center hover:border-red-500 hover:text-red-500" data-product-id="<?php echo $product->id; ?>">
                                        <i data-lucide="heart" class="w-5 h-5"></i>
                                    </button>
                                    <button class="add-to-cart-btn w-10 h-10 bg-primary-500 text-white rounded-full flex items-center justify-center hover:bg-primary-600" data-product-id="<?php echo $product->id; ?>">
                                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-12 flex justify-center">
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?paged=<?php echo $i; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                           class="px-4 py-2 rounded-lg <?php echo $i == $page ? 'bg-primary-600 text-white' : 'bg-white border hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="bg-white rounded-2xl p-12 text-center">
                    <i data-lucide="package-x" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No Products Found</h3>
                    <a href="<?php echo home_url('/shop/'); ?>" class="inline-block mt-4 bg-primary-600 text-white px-6 py-3 rounded-xl">Browse All Products</a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>
<?php get_footer(); ?>
