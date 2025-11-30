<?php
/**
 * Template Name: Admin Dashboard
 * Modern Admin Dashboard for NexMart
 */

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

global $wpdb;
$db = NexMart_Database::get_instance();

// Get dashboard statistics
$stats = [
    'total_orders' => $wpdb->get_var("SELECT COUNT(*) FROM {$db->orders_table}"),
    'pending_orders' => $wpdb->get_var("SELECT COUNT(*) FROM {$db->orders_table} WHERE status = 'pending'"),
    'total_revenue' => $wpdb->get_var("SELECT SUM(total) FROM {$db->orders_table} WHERE status IN ('completed', 'processing', 'shipped')") ?: 0,
    'total_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$db->products_table}"),
    'total_vendors' => $wpdb->get_var("SELECT COUNT(*) FROM {$db->vendors_table}"),
    'total_customers' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$db->orders_table} WHERE user_id IS NOT NULL"),
    'today_orders' => $wpdb->get_var("SELECT COUNT(*) FROM {$db->orders_table} WHERE DATE(created_at) = CURDATE()"),
    'today_revenue' => $wpdb->get_var("SELECT SUM(total) FROM {$db->orders_table} WHERE DATE(created_at) = CURDATE() AND status IN ('completed', 'processing', 'shipped')") ?: 0,
];

// Recent orders
$recent_orders = $wpdb->get_results("
    SELECT o.*, u.display_name as customer_name 
    FROM {$db->orders_table} o 
    LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
    ORDER BY o.created_at DESC LIMIT 10
");

// Top products
$top_products = $wpdb->get_results("
    SELECT p.*, 
           (SELECT image_url FROM {$db->product_images_table} WHERE product_id = p.id ORDER BY sort_order LIMIT 1) as image
    FROM {$db->products_table} p 
    ORDER BY p.sales_count DESC LIMIT 5
");

// Recent vendors
$recent_vendors = $wpdb->get_results("
    SELECT v.*, 
           (SELECT COUNT(*) FROM {$db->products_table} WHERE vendor_id = v.id) as product_count
    FROM {$db->vendors_table} v 
    ORDER BY v.created_at DESC LIMIT 5
");

// Sales by day (last 7 days)
$sales_chart = $wpdb->get_results("
    SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders
    FROM {$db->orders_table}
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND status IN ('completed', 'processing', 'shipped')
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Categories with product counts
$categories = $wpdb->get_results("
    SELECT c.*, COUNT(p.id) as product_count
    FROM {$db->categories_table} c
    LEFT JOIN {$db->products_table} p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY product_count DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NexMart</title>
    <?php wp_head(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: #eef2ff;
            color: #4f46e5;
        }
        .sidebar-link.active {
            background: #4f46e5;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 fixed h-full z-20 flex flex-col">
        <div class="p-6 border-b">
            <a href="<?php echo home_url(); ?>" class="flex items-center gap-2">
                <div class="bg-indigo-600 p-2 rounded-lg">
                    <i data-lucide="zap" class="w-5 h-5 text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-900">NexMart</span>
            </a>
        </div>
        
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <a href="?page=dashboard" class="sidebar-link active">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="?page=orders" class="sidebar-link">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i> Orders
                <?php if ($stats['pending_orders'] > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=products" class="sidebar-link">
                <i data-lucide="package" class="w-5 h-5"></i> Products
            </a>
            <a href="?page=categories" class="sidebar-link">
                <i data-lucide="folder" class="w-5 h-5"></i> Categories
            </a>
            <a href="?page=vendors" class="sidebar-link">
                <i data-lucide="store" class="w-5 h-5"></i> Vendors
            </a>
            <a href="?page=customers" class="sidebar-link">
                <i data-lucide="users" class="w-5 h-5"></i> Customers
            </a>
            <a href="?page=campaigns" class="sidebar-link">
                <i data-lucide="megaphone" class="w-5 h-5"></i> Campaigns
            </a>
            <a href="?page=coupons" class="sidebar-link">
                <i data-lucide="ticket" class="w-5 h-5"></i> Coupons
            </a>
            <a href="?page=reviews" class="sidebar-link">
                <i data-lucide="star" class="w-5 h-5"></i> Reviews
            </a>
            <a href="?page=analytics" class="sidebar-link">
                <i data-lucide="bar-chart-2" class="w-5 h-5"></i> Analytics
            </a>
            
            <div class="pt-4 mt-4 border-t">
                <a href="?page=settings" class="sidebar-link">
                    <i data-lucide="settings" class="w-5 h-5"></i> Settings
                </a>
            </div>
        </nav>
        
        <div class="p-4 border-t">
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="sidebar-link text-red-600 hover:bg-red-50">
                <i data-lucide="log-out" class="w-5 h-5"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 ml-64">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="flex items-center justify-between px-8 py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-500">Welcome back, <?php echo wp_get_current_user()->display_name; ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="<?php echo home_url(); ?>" class="text-sm text-gray-600 hover:text-indigo-600 flex items-center gap-1">
                        <i data-lucide="external-link" class="w-4 h-4"></i> View Store
                    </a>
                    <button class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                            <span class="text-indigo-600 font-bold"><?php echo strtoupper(substr(wp_get_current_user()->display_name, 0, 1)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card stat-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-green-600"></i>
                        </div>
                        <span class="text-xs font-medium text-green-600 bg-green-100 px-2 py-1 rounded-full">
                            +<?php echo number_format($stats['today_revenue'], 0); ?> today
                        </span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Revenue</h3>
                    <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
                </div>

                <div class="dashboard-card stat-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="shopping-cart" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <span class="text-xs font-medium text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                            +<?php echo $stats['today_orders']; ?> today
                        </span>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Orders</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_orders']); ?></p>
                </div>

                <div class="dashboard-card stat-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="package" class="w-6 h-6 text-purple-600"></i>
                        </div>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Products</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_products']); ?></p>
                </div>

                <div class="dashboard-card stat-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="users" class="w-6 h-6 text-orange-600"></i>
                        </div>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Total Customers</h3>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_customers']); ?></p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-2 dashboard-card p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Sales Overview (Last 7 Days)</h3>
                    <canvas id="salesChart" height="100"></canvas>
                </div>
                
                <div class="dashboard-card p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Top Categories</h3>
                    <div class="space-y-4">
                        <?php foreach ($categories as $cat): 
                            $percentage = $stats['total_products'] > 0 ? ($cat->product_count / $stats['total_products']) * 100 : 0;
                        ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700"><?php echo esc_html($cat->name); ?></span>
                                <span class="text-gray-500"><?php echo $cat->product_count; ?> products</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Orders -->
                <div class="dashboard-card overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-bold text-gray-900">Recent Orders</h3>
                        <a href="?page=orders" class="text-sm text-indigo-600 hover:underline">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-6 py-3 text-left">Order</th>
                                    <th class="px-6 py-3 text-left">Customer</th>
                                    <th class="px-6 py-3 text-left">Status</th>
                                    <th class="px-6 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($recent_orders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">#<?php echo $order->id; ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo esc_html($order->customer_name ?: 'Guest'); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php echo $order->status === 'completed' ? 'bg-green-100 text-green-700' : 
                                                  ($order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                                  ($order->status === 'processing' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')); ?>">
                                            <?php echo ucfirst($order->status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-gray-900">$<?php echo number_format($order->total, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="dashboard-card overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-bold text-gray-900">Top Products</h3>
                        <a href="?page=products" class="text-sm text-indigo-600 hover:underline">View All</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($top_products as $product): 
                            $image = $product->image ?: 'https://placehold.co/60x60/e2e8f0/64748b?text=P';
                        ?>
                        <div class="p-4 flex items-center gap-4 hover:bg-gray-50">
                            <img src="<?php echo esc_url($image); ?>" alt="" class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 truncate"><?php echo esc_html($product->name); ?></h4>
                                <p class="text-sm text-gray-500"><?php echo $product->sales_count; ?> sold</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">$<?php echo number_format($product->sale_price ?: $product->price, 2); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $product->stock_quantity; ?> in stock</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Vendors Section -->
            <div class="mt-8">
                <div class="dashboard-card overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-bold text-gray-900">Recent Vendors</h3>
                        <a href="?page=vendors" class="text-sm text-indigo-600 hover:underline">View All</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 p-6">
                        <?php foreach ($recent_vendors as $vendor): ?>
                        <div class="text-center p-4 border border-gray-100 rounded-xl hover:shadow-md transition">
                            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <?php if ($vendor->logo_url): ?>
                                <img src="<?php echo esc_url($vendor->logo_url); ?>" alt="" class="w-14 h-14 rounded-full object-cover">
                                <?php else: ?>
                                <i data-lucide="store" class="w-8 h-8 text-indigo-600"></i>
                                <?php endif; ?>
                            </div>
                            <h4 class="font-medium text-gray-900 truncate"><?php echo esc_html($vendor->store_name); ?></h4>
                            <p class="text-sm text-gray-500"><?php echo $vendor->product_count; ?> products</p>
                            <span class="inline-block mt-2 px-2 py-1 text-xs font-medium rounded-full 
                                <?php echo $vendor->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                <?php echo ucfirst($vendor->status); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Sales Chart
    const salesData = <?php echo json_encode($sales_chart); ?>;
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.map(d => {
                const date = new Date(d.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
            datasets: [{
                label: 'Revenue',
                data: salesData.map(d => parseFloat(d.revenue) || 0),
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => '$' + value.toLocaleString()
                    }
                }
            }
        }
    });
</script>

<?php wp_footer(); ?>
</body>
</html>
