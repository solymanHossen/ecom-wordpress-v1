<?php
/**
 * Search Results Template
 * Modern e-commerce search page for products with advanced filters
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

// Get search query and filters
$search_query = get_search_query();
$page = get_query_var('paged') ? max(1, get_query_var('paged')) : 1;
$per_page = 16;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$filter_vendor = isset($_GET['vendor']) ? intval($_GET['vendor']) : 0;
$filter_min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$filter_max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$filter_rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$filter_in_stock = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : false;
$sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'relevance';

// Initialize results
$products = [];
$total = 0;
$categories = [];
$vendors = [];
$blog_posts = [];
$price_range = ['min' => 0, 'max' => 0];

if (!empty($search_query)) {
    // Build WHERE clauses
    $where_clauses = ["p.status = 'published'"];
    $search_param = '%' . $wpdb->esc_like($search_query) . '%';
    $params = [];
    
    // Search condition
    $where_clauses[] = "(p.name LIKE %s OR p.description LIKE %s OR p.sku LIKE %s)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    
    // Category filter
    if ($filter_category > 0) {
        $where_clauses[] = "p.category_id = %d";
        $params[] = $filter_category;
    }
    
    // Vendor filter
    if ($filter_vendor > 0) {
        $where_clauses[] = "p.vendor_id = %d";
        $params[] = $filter_vendor;
    }
    
    // Price range filter
    if ($filter_min_price > 0) {
        $where_clauses[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) >= %f";
        $params[] = $filter_min_price;
    }
    if ($filter_max_price > 0) {
        $where_clauses[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) <= %f";
        $params[] = $filter_max_price;
    }
    
    // Stock filter
    if ($filter_in_stock) {
        $where_clauses[] = "p.stock_quantity > 0";
    }
    
    $where_clause = implode(' AND ', $where_clauses);
    
    // Sorting
    $order_by = match($sort_by) {
        'price_low' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) ASC',
        'price_high' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) DESC',
        'newest' => 'p.created_at DESC',
        'popular' => 'p.sales_count DESC',
        'rating' => 'avg_rating DESC',
        default => 'p.sales_count DESC, p.created_at DESC' // relevance
    };
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$prefix}products p WHERE $where_clause";
    $total = $wpdb->get_var($params ? $wpdb->prepare($count_query, ...$params) : $count_query);
    
    // Get products with filters
    $product_query = "SELECT p.*, 
        v.store_name as vendor_name,
        v.store_slug as vendor_slug,
        c.name as category_name,
        (SELECT image_url FROM {$prefix}product_images WHERE product_id = p.id AND sort_order = 0 LIMIT 1) as primary_image,
        (SELECT AVG(rating) FROM {$prefix}reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM {$prefix}reviews WHERE product_id = p.id) as review_count
    FROM {$prefix}products p 
    LEFT JOIN {$prefix}vendors v ON p.vendor_id = v.id 
    LEFT JOIN {$prefix}categories c ON p.category_id = c.id
    WHERE $where_clause";
    
    // Add rating filter (must be after avg_rating is calculated)
    if ($filter_rating > 0) {
        $product_query = "SELECT * FROM ($product_query) AS filtered WHERE COALESCE(avg_rating, 0) >= $filter_rating";
    }
    
    $product_query .= " ORDER BY $order_by LIMIT $per_page OFFSET $offset";
    
    $products = $wpdb->get_results($params ? $wpdb->prepare($product_query, ...$params) : $product_query);
    
    // Get price range for filter slider
    $price_range_query = "SELECT 
        MIN(COALESCE(NULLIF(p.sale_price, 0), p.price)) as min_price,
        MAX(COALESCE(NULLIF(p.sale_price, 0), p.price)) as max_price
    FROM {$prefix}products p WHERE " . implode(' AND ', array_slice($where_clauses, 0, 1));
    $price_range_result = $wpdb->get_row($price_range_query);
    $price_range = [
        'min' => floor($price_range_result->min_price ?? 0),
        'max' => ceil($price_range_result->max_price ?? 1000)
    ];
    
    // Search categories (only if no category filter applied)
    if (!$filter_category) {
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, COUNT(p.id) as product_count
            FROM {$prefix}categories c 
            LEFT JOIN {$prefix}products p ON c.id = p.category_id AND p.status = 'published'
            WHERE c.name LIKE %s 
            GROUP BY c.id
            HAVING product_count > 0
            LIMIT 5",
            $search_param
        ));
    }
    
    // Search vendors (only if no vendor filter applied)
    if (!$filter_vendor) {
        $vendors = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, 
                (SELECT COUNT(*) FROM {$prefix}products WHERE vendor_id = v.id AND status = 'published') as product_count
            FROM {$prefix}vendors v 
            WHERE v.status = 'active' AND (v.store_name LIKE %s OR v.store_description LIKE %s)
            HAVING product_count > 0
            LIMIT 5",
            $search_param, $search_param
        ));
    }
    
    // Search blog posts
    $blog_posts = get_posts([
        's' => $search_query,
        'posts_per_page' => 3,
        'post_status' => 'publish'
    ]);
}

$total_pages = ceil($total / $per_page);
$has_results = !empty($products) || !empty($categories) || !empty($vendors) || !empty($blog_posts);
?>

<div class="bg-gray-50 min-h-screen">
    <!-- Search Header -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-8">
            <!-- Breadcrumb -->
            <nav class="text-sm text-gray-500 mb-4">
                <a href="<?php echo home_url(); ?>" class="hover:text-primary-600">Home</a> / 
                <span class="text-gray-900">Search Results</span>
            </nav>

            <!-- Search Title -->
            <div class="mb-6">
                <?php if (!empty($search_query)): ?>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        Search Results for "<?php echo esc_html($search_query); ?>"
                    </h1>
                    <p class="text-gray-600">
                        <?php echo $total; ?> product<?php echo $total != 1 ? 's' : ''; ?> found
                        <?php if (!empty($categories)): ?>
                            · <?php echo count($categories); ?> categor<?php echo count($categories) != 1 ? 'ies' : 'y'; ?>
                        <?php endif; ?>
                        <?php if (!empty($vendors)): ?>
                            · <?php echo count($vendors); ?> vendor<?php echo count($vendors) != 1 ? 's' : ''; ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Search Products</h1>
                    <p class="text-gray-600">Enter a search term to find products, categories, or vendors</p>
                <?php endif; ?>
            </div>

            <!-- Search Bar -->
            <form method="get" action="<?php echo home_url('/'); ?>" class="relative max-w-3xl">
                <input type="search" 
                       name="s" 
                       value="<?php echo esc_attr($search_query); ?>" 
                       placeholder="Search for products, brands, categories..." 
                       class="w-full pl-12 pr-24 py-4 border-2 border-gray-300 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none text-lg"
                       autofocus>
                <svg class="w-6 h-6 text-gray-400 absolute left-4 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-primary-600 text-white px-6 py-2.5 rounded-lg hover:bg-primary-700 transition-colors font-medium">
                    Search
                </button>
            </form>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <?php if (empty($search_query)): ?>
            <!-- Empty State - No Search Query -->
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                    <div class="w-24 h-24 bg-gradient-to-br from-primary-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">Start Your Search</h2>
                    <p class="text-gray-600 mb-8">Discover thousands of products from our trusted vendors</p>
                    
                    <!-- Popular Searches -->
                    <div class="text-left">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Popular Searches</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $popular_searches = ['Headphones', 'Laptop', 'Smartphone', 'Camera', 'Watch', 'Keyboard', 'Mouse', 'Monitor'];
                            foreach ($popular_searches as $term):
                            ?>
                                <a href="<?php echo home_url('/?s=' . urlencode($term)); ?>" 
                                   class="px-4 py-2 bg-gray-100 hover:bg-primary-100 hover:text-primary-700 rounded-lg text-sm font-medium transition-colors">
                                    <?php echo esc_html($term); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Browse Categories -->
                <div class="mt-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Browse by Category</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php
                        $all_categories = $wpdb->get_results("SELECT * FROM {$prefix}categories ORDER BY name ASC LIMIT 8");
                        foreach ($all_categories as $cat):
                        ?>
                            <a href="<?php echo home_url('/shop?category=' . $cat->id); ?>" 
                               class="bg-white rounded-lg p-4 text-center hover:shadow-lg transition-shadow">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-2xl font-bold text-primary-600">
                                        <?php echo esc_html(substr($cat->name, 0, 2)); ?>
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-900"><?php echo esc_html($cat->name); ?></h4>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif (!$has_results): ?>
            <!-- No Results Found -->
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">No Results Found</h2>
                    <p class="text-gray-600 mb-6">
                        We couldn't find any products matching "<strong><?php echo esc_html($search_query); ?></strong>"
                    </p>
                    
                    <!-- Search Suggestions -->
                    <div class="bg-gray-50 rounded-lg p-6 text-left mb-6">
                        <h3 class="font-semibold text-gray-900 mb-3">Search Tips:</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Check your spelling
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Try more general keywords
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Use fewer keywords
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Try different or related terms
                            </li>
                        </ul>
                    </div>

                    <a href="<?php echo home_url('/shop'); ?>" 
                       class="inline-flex items-center gap-2 bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Browse All Products
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Results Found -->
            
            <!-- Advanced Filters Bar -->
            <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Sort Dropdown -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Sort:</label>
                        <select onchange="window.location.href=this.value" 
                                class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm">
                            <option value="<?php echo add_query_arg('sort', 'relevance'); ?>" <?php selected($sort_by, 'relevance'); ?>>Most Relevant</option>
                            <option value="<?php echo add_query_arg('sort', 'popular'); ?>" <?php selected($sort_by, 'popular'); ?>>Most Popular</option>
                            <option value="<?php echo add_query_arg('sort', 'newest'); ?>" <?php selected($sort_by, 'newest'); ?>>Newest First</option>
                            <option value="<?php echo add_query_arg('sort', 'price_low'); ?>" <?php selected($sort_by, 'price_low'); ?>>Price: Low to High</option>
                            <option value="<?php echo add_query_arg('sort', 'price_high'); ?>" <?php selected($sort_by, 'price_high'); ?>>Price: High to Low</option>
                            <option value="<?php echo add_query_arg('sort', 'rating'); ?>" <?php selected($sort_by, 'rating'); ?>>Highest Rated</option>
                        </select>
                    </div>
                    
                    <!-- In Stock Toggle -->
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" 
                               <?php checked($filter_in_stock); ?>
                               onchange="window.location.href=this.checked ? '<?php echo add_query_arg('in_stock', '1'); ?>' : '<?php echo remove_query_arg('in_stock'); ?>'"
                               class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <span class="text-sm font-medium text-gray-700">In Stock Only</span>
                    </label>
                    
                    <!-- Filter Button (Mobile) -->
                    <button onclick="document.getElementById('filterSidebar').classList.toggle('hidden')" 
                            class="ml-auto lg:hidden flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        Filters
                    </button>
                    
                    <!-- Active Filters -->
                    <?php 
                    $active_filters = [];
                    if ($filter_category) $active_filters[] = ['label' => 'Category', 'remove' => remove_query_arg('category')];
                    if ($filter_vendor) $active_filters[] = ['label' => 'Vendor', 'remove' => remove_query_arg('vendor')];
                    if ($filter_min_price) $active_filters[] = ['label' => 'Min $' . $filter_min_price, 'remove' => remove_query_arg('min_price')];
                    if ($filter_max_price) $active_filters[] = ['label' => 'Max $' . $filter_max_price, 'remove' => remove_query_arg('max_price')];
                    if ($filter_rating) $active_filters[] = ['label' => $filter_rating . '★ & up', 'remove' => remove_query_arg('rating')];
                    
                    if (!empty($active_filters)): ?>
                        <div class="flex flex-wrap items-center gap-2 w-full pt-2 border-t border-gray-200 mt-2">
                            <span class="text-sm font-medium text-gray-700">Active Filters:</span>
                            <?php foreach ($active_filters as $filter): ?>
                                <a href="<?php echo $filter['remove']; ?>" 
                                   class="inline-flex items-center gap-1 px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-xs font-medium hover:bg-primary-200 transition-colors">
                                    <?php echo esc_html($filter['label']); ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </a>
                            <?php endforeach; ?>
                            <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?') . '?s=' . urlencode($search_query); ?>" 
                               class="text-xs text-gray-600 hover:text-primary-600 font-medium">Clear All</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Sidebar Filters -->
                <aside id="filterSidebar" class="lg:col-span-3 hidden lg:block">
                    <div class="bg-white rounded-xl shadow-sm p-6 sticky top-4">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            Filters
                        </h3>
                        
                        <!-- Price Range Filter -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Price Range</h4>
                            <form method="GET" action="" class="space-y-3">
                                <input type="hidden" name="s" value="<?php echo esc_attr($search_query); ?>">
                                <?php if ($sort_by !== 'relevance'): ?>
                                    <input type="hidden" name="sort" value="<?php echo esc_attr($sort_by); ?>">
                                <?php endif; ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" 
                                           name="min_price" 
                                           placeholder="Min" 
                                           value="<?php echo $filter_min_price ?: ''; ?>"
                                           min="0"
                                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500">
                                    <input type="number" 
                                           name="max_price" 
                                           placeholder="Max" 
                                           value="<?php echo $filter_max_price ?: ''; ?>"
                                           min="0"
                                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500">
                                </div>
                                <button type="submit" 
                                        class="w-full bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                                    Apply Price Filter
                                </button>
                            </form>
                            <div class="mt-2 text-xs text-gray-500">
                                Range: $<?php echo number_format($price_range['min'], 0); ?> - $<?php echo number_format($price_range['max'], 0); ?>
                            </div>
                        </div>
                        
                        <!-- Rating Filter -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Minimum Rating</h4>
                            <div class="space-y-2">
                                <?php for ($r = 5; $r >= 1; $r--): ?>
                                    <a href="<?php echo add_query_arg('rating', $r); ?>" 
                                       class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100 transition-colors <?php echo $filter_rating == $r ? 'bg-primary-50 text-primary-700' : ''; ?>">
                                        <div class="flex">
                                            <?php for ($i = 0; $i < $r; $i++): ?>
                                                <svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm">&amp; up</span>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Categories Filter -->
                        <?php if (!$filter_category): 
                            $all_cats = $wpdb->get_results("SELECT c.id, c.name, COUNT(p.id) as product_count 
                                FROM {$prefix}categories c 
                                LEFT JOIN {$prefix}products p ON c.id = p.category_id AND p.status = 'published'
                                GROUP BY c.id 
                                HAVING product_count > 0 
                                ORDER BY product_count DESC 
                                LIMIT 10");
                            if (!empty($all_cats)):
                        ?>
                            <div class="mb-6">
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Categories</h4>
                                <div class="space-y-2 max-h-64 overflow-y-auto">
                                    <?php foreach ($all_cats as $cat): ?>
                                        <a href="<?php echo add_query_arg('category', $cat->id); ?>" 
                                           class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                                            <span class="text-gray-700"><?php echo esc_html($cat->name); ?></span>
                                            <span class="text-gray-500 text-xs"><?php echo $cat->product_count; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; endif; ?>
                    </div>
                </aside>
                
                <!-- Main Content -->
                <div class="lg:col-span-9">
                    <!-- Categories Results -->
                    <?php if (!empty($categories)): ?>
                        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Categories
                            </h2>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($categories as $cat): ?>
                                    <a href="<?php echo home_url('/shop?category=' . $cat->id); ?>" 
                                       class="flex items-center gap-2 bg-primary-50 hover:bg-primary-100 text-primary-700 px-4 py-2 rounded-lg transition-colors">
                                        <span class="font-medium"><?php echo esc_html($cat->name); ?></span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Vendors Results -->
                    <?php if (!empty($vendors)): ?>
                        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                Vendors
                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($vendors as $vendor): ?>
                                    <a href="<?php echo home_url('/vendor/' . $vendor->store_slug); ?>" 
                                       class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg hover:border-primary-500 hover:shadow-md transition-all">
                                        <div class="w-12 h-12 bg-gradient-to-br from-primary-100 to-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <span class="text-lg font-bold text-primary-600">
                                                <?php echo esc_html(substr($vendor->store_name, 0, 2)); ?>
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold text-gray-900 truncate"><?php echo esc_html($vendor->store_name); ?></h3>
                                            <p class="text-sm text-gray-600"><?php echo $vendor->product_count; ?> products</p>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Products Results -->
                    <?php if (!empty($products)): ?>
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    Products
                                </h2>
                                <span class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?></span>
                            </div>

                            <!-- Products Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $final_price = $product->sale_price > 0 && $product->sale_price < $product->price ? $product->sale_price : $product->price;
                                    $discount_percent = $product->sale_price > 0 && $product->sale_price < $product->price ? round((($product->price - $product->sale_price) / $product->price) * 100) : 0;
                                    $product_url = home_url('/product/' . $product->slug);
                                    ?>
                                    <div class="bg-white rounded-lg shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden group">
                                        <a href="<?php echo esc_url($product_url); ?>" class="block">
                                            <!-- Product Image -->
                                            <div class="relative aspect-square overflow-hidden bg-gray-100">
                                                <?php if ($product->primary_image): ?>
                                                    <img src="<?php echo esc_url($product->primary_image); ?>" 
                                                         alt="<?php echo esc_attr($product->name); ?>" 
                                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
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
                                            </div>

                                            <!-- Product Info -->
                                            <div class="p-4">
                                                <?php if ($product->vendor_name): ?>
                                                    <p class="text-xs text-gray-500 mb-1"><?php echo esc_html($product->vendor_name); ?></p>
                                                <?php endif; ?>
                                                
                                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors min-h-[48px]">
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
                                                        <span class="text-xs text-gray-600">(<?php echo $product->review_count; ?>)</span>
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
                                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                            </svg>
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
                                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2">
                                            Next
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Blog Posts Results -->
                    <?php if (!empty($blog_posts)): ?>
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                                Blog Posts
                            </h2>
                            <div class="space-y-4">
                                <?php foreach ($blog_posts as $post): ?>
                                    <a href="<?php echo get_permalink($post); ?>" 
                                       class="flex gap-4 p-4 border border-gray-200 rounded-lg hover:border-primary-500 hover:shadow-md transition-all">
                                        <?php if (has_post_thumbnail($post)): ?>
                                            <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                                                <?php echo get_the_post_thumbnail($post, 'thumbnail', ['class' => 'w-full h-full object-cover']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-900 mb-1"><?php echo get_the_title($post); ?></h3>
                                            <p class="text-sm text-gray-600 line-clamp-2"><?php echo wp_trim_words(get_the_excerpt($post), 20); ?></p>
                                            <span class="text-xs text-gray-500 mt-2 inline-block"><?php echo get_the_date('', $post); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-3">
                    <!-- Search Tips -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="font-bold text-gray-900 mb-4">Search Tips</h3>
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Use specific keywords
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Try brand names
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-primary-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Search by SKU
                            </li>
                        </ul>
                    </div>

                    <!-- Popular Categories -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="font-bold text-gray-900 mb-4">Popular Categories</h3>
                        <div class="space-y-2">
                            <?php
                            $popular_cats = $wpdb->get_results("
                                SELECT c.*, COUNT(p.id) as product_count 
                                FROM {$prefix}categories c 
                                LEFT JOIN {$prefix}products p ON c.id = p.category_id 
                                GROUP BY c.id 
                                ORDER BY product_count DESC 
                                LIMIT 6
                            ");
                            foreach ($popular_cats as $cat):
                            ?>
                                <a href="<?php echo home_url('/shop?category=' . $cat->id); ?>" 
                                   class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition-colors">
                                    <span class="text-sm text-gray-700"><?php echo esc_html($cat->name); ?></span>
                                    <span class="text-xs text-gray-500"><?php echo $cat->product_count; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
