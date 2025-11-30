<?php
/**
 * Template Name: Vendor Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

global $wpdb;
$db = NexMart_Database::get_instance();
$user_id = get_current_user_id();
$vendor = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$db->vendors_table} WHERE user_id = %d",
    $user_id
));

if (!$vendor) {
    wp_die('You are not a registered vendor. <a href="' . home_url('/vendor-registration') . '">Register here</a>');
}

$dashboard = NexMart_Vendor_Dashboard::get_instance();
$stats = $dashboard->get_vendor_stats($vendor->id);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - NexMart</title>
    <?php wp_head(); ?>
</head>
<body class="bg-slate-50 min-h-screen flex font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col fixed h-full z-20">
        <div class="p-6 border-b border-slate-100">
           <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
             <i data-lucide="layout-dashboard" class="text-indigo-600 w-6 h-6"></i> VendorHub
           </h2>
        </div>
        <nav class="flex-1 p-4 space-y-1">
             <a href="?page=dashboard" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl bg-indigo-50 text-indigo-600 transition-colors">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
             </a>
             <a href="?page=products" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-50 transition-colors">
                <i data-lucide="package" class="w-5 h-5"></i> Products
             </a>
             <a href="?page=orders" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-50 transition-colors">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i> Orders
             </a>
             <a href="?page=analytics" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-50 transition-colors">
                <i data-lucide="trending-up" class="w-5 h-5"></i> Analytics
             </a>
             <a href="?page=settings" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-50 transition-colors">
                <i data-lucide="settings" class="w-5 h-5"></i> Settings
             </a>
        </nav>
        <div class="p-4 border-t border-slate-100">
           <a href="<?php echo wp_logout_url(home_url()); ?>" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-xl transition-colors">
             <i data-lucide="log-out" class="w-5 h-5"></i> Logout
           </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 md:ml-64">
        <div class="flex justify-between items-center mb-8">
           <div>
             <h1 class="text-2xl font-bold text-slate-900">Dashboard Overview</h1>
             <p class="text-slate-500">Welcome back, <?php echo esc_html($vendor->store_name); ?></p>
           </div>
           <div class="flex gap-3">
                <a href="<?php echo home_url('/store/' . $vendor->slug); ?>" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium flex items-center gap-2 hover:bg-gray-50">
                    Visit Store
                </a>
                <button onclick="document.getElementById('add-product-modal').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 hover:bg-indigo-700 transition">
                    <i data-lucide="plus" class="w-5 h-5"></i> Add Product
                </button>
           </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
           <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                  <div class="p-3 rounded-xl bg-green-100 text-green-600">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                  </div>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Total Revenue</h3>
                <p class="text-2xl font-bold text-slate-900">$<?php echo number_format($stats['revenue'], 2); ?></p>
           </div>
           
           <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                  <div class="p-3 rounded-xl bg-blue-100 text-blue-600">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                  </div>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Total Orders</h3>
                <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['orders']->total_orders ?? 0); ?></p>
           </div>

           <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                  <div class="p-3 rounded-xl bg-purple-100 text-purple-600">
                    <i data-lucide="package" class="w-5 h-5"></i>
                  </div>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Products</h3>
                <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['products']->total_products); ?></p>
           </div>

           <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                  <div class="p-3 rounded-xl bg-orange-100 text-orange-600">
                    <i data-lucide="star" class="w-5 h-5"></i>
                  </div>
                </div>
                <h3 class="text-slate-500 text-sm font-medium">Rating</h3>
                <p class="text-2xl font-bold text-slate-900">4.8</p>
           </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
           <div class="p-6 border-b border-slate-100 flex justify-between items-center">
              <h3 class="font-bold text-slate-800">Recent Orders</h3>
              <a href="?page=orders" class="text-indigo-600 text-sm font-medium hover:underline">View All</a>
           </div>
           <div class="overflow-x-auto">
               <table class="w-full text-left">
                   <thead class="bg-slate-50 text-slate-500 text-sm">
                       <tr>
                           <th class="p-4 font-medium">Order ID</th>
                           <th class="p-4 font-medium">Customer</th>
                           <th class="p-4 font-medium">Date</th>
                           <th class="p-4 font-medium">Status</th>
                           <th class="p-4 font-medium">Total</th>
                           <th class="p-4 font-medium">Action</th>
                       </tr>
                   </thead>
                   <tbody class="divide-y divide-slate-100">
                       <?php if ($stats['recent_orders']): foreach ($stats['recent_orders'] as $order): ?>
                       <tr class="hover:bg-slate-50 transition-colors">
                           <td class="p-4 font-medium text-slate-900">#<?php echo $order->id; ?></td>
                           <td class="p-4 text-slate-600"><?php echo esc_html($order->customer_name ?: 'Guest'); ?></td>
                           <td class="p-4 text-slate-600"><?php echo date('M j, Y', strtotime($order->created_at)); ?></td>
                           <td class="p-4">
                               <span class="px-3 py-1 rounded-full text-xs font-medium 
                                   <?php echo $order->status === 'completed' ? 'bg-green-100 text-green-700' : 
                                         ($order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'); ?>">
                                   <?php echo ucfirst($order->status); ?>
                               </span>
                           </td>
                           <td class="p-4 font-medium text-slate-900">$<?php echo number_format($order->total, 2); ?></td>
                           <td class="p-4">
                               <button class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">Details</button>
                           </td>
                       </tr>
                       <?php endforeach; else: ?>
                       <tr>
                           <td colspan="6" class="p-8 text-center text-slate-500">No orders found.</td>
                       </tr>
                       <?php endif; ?>
                   </tbody>
               </table>
           </div>
        </div>
        
        <!-- Top Products -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
           <div class="p-6 border-b border-slate-100 flex justify-between items-center">
              <h3 class="font-bold text-slate-800">Top Products</h3>
              <a href="?page=products" class="text-indigo-600 text-sm font-medium hover:underline">View All</a>
           </div>
           <div class="overflow-x-auto">
               <table class="w-full text-left">
                   <thead class="bg-slate-50 text-slate-500 text-sm">
                       <tr>
                           <th class="p-4 font-medium">Product</th>
                           <th class="p-4 font-medium">Price</th>
                           <th class="p-4 font-medium">Sales</th>
                           <th class="p-4 font-medium">Stock</th>
                           <th class="p-4 font-medium">Status</th>
                       </tr>
                   </thead>
                   <tbody class="divide-y divide-slate-100">
                       <?php if ($stats['top_products']): foreach ($stats['top_products'] as $product): 
                           $image = $product->primary_image ?: 'https://placehold.co/100x100/e2e8f0/64748b?text=Product';
                       ?>
                       <tr class="hover:bg-slate-50 transition-colors">
                           <td class="p-4">
                               <div class="flex items-center gap-3">
                                   <img src="<?php echo esc_url($image); ?>" class="w-10 h-10 rounded-lg object-cover bg-gray-100">
                                   <span class="font-medium text-slate-900"><?php echo esc_html($product->name); ?></span>
                               </div>
                           </td>
                           <td class="p-4 text-slate-600">$<?php echo number_format($product->price, 2); ?></td>
                           <td class="p-4 text-slate-600"><?php echo number_format($product->sales_count); ?></td>
                           <td class="p-4 text-slate-600"><?php echo number_format($product->stock_quantity); ?></td>
                           <td class="p-4">
                               <span class="px-3 py-1 rounded-full text-xs font-medium 
                                   <?php echo $product->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                   <?php echo ucfirst($product->status); ?>
                               </span>
                           </td>
                       </tr>
                       <?php endforeach; else: ?>
                       <tr>
                           <td colspan="5" class="p-8 text-center text-slate-500">No products found.</td>
                       </tr>
                       <?php endif; ?>
                   </tbody>
               </table>
           </div>
        </div>
    </main>
    
    <!-- Add Product Modal (Placeholder) -->
    <div id="add-product-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Add New Product</h2>
                <button onclick="document.getElementById('add-product-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="add-product-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="name" class="w-full border rounded-lg px-4 py-2" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price ($)</label>
                        <input type="number" name="price" step="0.01" class="w-full border rounded-lg px-4 py-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                        <input type="number" name="stock_quantity" class="w-full border rounded-lg px-4 py-2" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="w-full border rounded-lg px-4 py-2"></textarea>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="document.getElementById('add-product-modal').classList.add('hidden')" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Add Product Form Handler
        const addProductForm = document.getElementById('add-product-form');
        if (addProductForm) {
            addProductForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'nexmart_vendor_add_product');
                formData.append('nonce', '<?php echo wp_create_nonce('nexmart_nonce'); ?>');
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Product added successfully!');
                        location.reload();
                    } else {
                        alert(data.data.message || 'Error adding product');
                    }
                })
                .catch(err => {
                    alert('An error occurred. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        }
    </script>

<?php wp_footer(); ?>
</body>
</html>
