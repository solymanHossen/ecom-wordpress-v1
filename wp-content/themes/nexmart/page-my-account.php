<?php
/**
 * Template Name: My Account
 * Customer Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();
global $wpdb;
$db = NexMart_Database::get_instance();
$user_id = get_current_user_id();
$user = wp_get_current_user();

$page = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

// Get customer data
$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$db->orders_table} WHERE user_id = %d ORDER BY created_at DESC",
    $user_id
));

$wishlist = NexMart_Products::get_instance()->get_wishlist($user_id);

$order_stats = [
    'total' => count($orders),
    'pending' => count(array_filter($orders, fn($o) => $o->status === 'pending')),
    'completed' => count(array_filter($orders, fn($o) => $o->status === 'completed')),
];
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <aside class="lg:w-64 shrink-0">
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden sticky top-24">
                    <div class="p-6 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mb-3">
                            <span class="text-2xl font-bold"><?php echo strtoupper(substr($user->display_name, 0, 1)); ?></span>
                        </div>
                        <h2 class="font-bold text-lg"><?php echo esc_html($user->display_name); ?></h2>
                        <p class="text-indigo-100 text-sm"><?php echo esc_html($user->user_email); ?></p>
                    </div>
                    
                    <nav class="p-4 space-y-1">
                        <a href="?tab=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $page === 'dashboard' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                        </a>
                        <a href="?tab=orders" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $page === 'orders' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <i data-lucide="shopping-bag" class="w-5 h-5"></i> My Orders
                            <?php if ($order_stats['pending'] > 0): ?>
                            <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full"><?php echo $order_stats['pending']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=wishlist" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $page === 'wishlist' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <i data-lucide="heart" class="w-5 h-5"></i> Wishlist
                            <?php if (count($wishlist) > 0): ?>
                            <span class="ml-auto bg-red-100 text-red-600 text-xs px-2 py-0.5 rounded-full"><?php echo count($wishlist); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=addresses" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $page === 'addresses' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <i data-lucide="map-pin" class="w-5 h-5"></i> Addresses
                        </a>
                        <a href="?tab=settings" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo $page === 'settings' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50'; ?>">
                            <i data-lucide="settings" class="w-5 h-5"></i> Settings
                        </a>
                        
                        <div class="pt-4 mt-4 border-t">
                            <a href="<?php echo wp_logout_url(home_url()); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50">
                                <i data-lucide="log-out" class="w-5 h-5"></i> Logout
                            </a>
                        </div>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1">
                <?php if ($page === 'dashboard'): ?>
                <!-- Dashboard Overview -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo esc_html($user->first_name ?: $user->display_name); ?>!</h1>
                    <p class="text-gray-500">Here's what's happening with your account.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <i data-lucide="package" class="w-6 h-6 text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $order_stats['total']; ?></p>
                                <p class="text-sm text-gray-500">Total Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $order_stats['pending']; ?></p>
                                <p class="text-sm text-gray-500">Pending Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                                <i data-lucide="heart" class="w-6 h-6 text-red-600"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($wishlist); ?></p>
                                <p class="text-sm text-gray-500">Wishlist Items</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b flex justify-between items-center">
                        <h2 class="font-bold text-lg text-gray-900">Recent Orders</h2>
                        <a href="?tab=orders" class="text-indigo-600 text-sm hover:underline">View All</a>
                    </div>
                    <?php if ($orders): ?>
                    <div class="divide-y">
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                        <div class="p-6 flex items-center justify-between hover:bg-gray-50">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <i data-lucide="package" class="w-6 h-6 text-gray-500"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Order #<?php echo $order->id; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($order->created_at)); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">$<?php echo number_format($order->total, 2); ?></p>
                                <span class="inline-block px-2 py-1 text-xs font-medium rounded-full 
                                    <?php echo $order->status === 'completed' ? 'bg-green-100 text-green-700' : 
                                          ($order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                          ($order->status === 'shipped' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')); ?>">
                                    <?php echo ucfirst($order->status); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center">
                        <i data-lucide="package" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                        <p class="text-gray-500 mb-4">You haven't placed any orders yet.</p>
                        <a href="<?php echo home_url('/shop'); ?>" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-indigo-700">Start Shopping</a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php elseif ($page === 'orders'): ?>
                <!-- All Orders -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">My Orders</h1>
                    <p class="text-gray-500">Track and manage your orders.</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <?php if ($orders): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-6 py-4 text-left">Order</th>
                                    <th class="px-6 py-4 text-left">Date</th>
                                    <th class="px-6 py-4 text-left">Status</th>
                                    <th class="px-6 py-4 text-left">Total</th>
                                    <th class="px-6 py-4 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">#<?php echo $order->id; ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo date('M j, Y', strtotime($order->created_at)); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 text-xs font-medium rounded-full 
                                            <?php echo $order->status === 'completed' ? 'bg-green-100 text-green-700' : 
                                                  ($order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                                  ($order->status === 'shipped' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')); ?>">
                                            <?php echo ucfirst($order->status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900">$<?php echo number_format($order->total, 2); ?></td>
                                    <td class="px-6 py-4">
                                        <button class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">View Details</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center">
                        <i data-lucide="package" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                        <p class="text-gray-500 mb-4">No orders found.</p>
                        <a href="<?php echo home_url('/shop'); ?>" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-indigo-700">Start Shopping</a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php elseif ($page === 'wishlist'): ?>
                <!-- Wishlist -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">My Wishlist</h1>
                    <p class="text-gray-500">Products you've saved for later.</p>
                </div>

                <?php if ($wishlist): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($wishlist as $product): 
                        $image = $product->primary_image ?: 'https://placehold.co/400x400/e2e8f0/64748b?text=Product';
                        $display_price = $product->sale_price ?: $product->price;
                    ?>
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100 group">
                        <div class="relative">
                            <a href="<?php echo home_url('/product/' . $product->slug); ?>">
                                <div class="aspect-square bg-gray-100">
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product->name); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                            </a>
                            <button class="wishlist-btn absolute top-3 right-3 w-10 h-10 bg-white rounded-full shadow flex items-center justify-center text-red-500" data-product-id="<?php echo $product->id; ?>">
                                <i data-lucide="heart" class="w-5 h-5 fill-current"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                                <a href="<?php echo home_url('/product/' . $product->slug); ?>" class="hover:text-indigo-600"><?php echo esc_html($product->name); ?></a>
                            </h3>
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-bold text-indigo-600">$<?php echo number_format($display_price, 2); ?></span>
                                <button class="add-to-cart-btn bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700" data-product-id="<?php echo $product->id; ?>">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                    <i data-lucide="heart" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                    <p class="text-gray-500 mb-4">Your wishlist is empty.</p>
                    <a href="<?php echo home_url('/shop'); ?>" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-indigo-700">Browse Products</a>
                </div>
                <?php endif; ?>

                <?php elseif ($page === 'settings'): ?>
                <!-- Account Settings -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Account Settings</h1>
                    <p class="text-gray-500">Update your profile and preferences.</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <form id="profile-form" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        
                        <div class="pt-6 border-t">
                            <h3 class="font-bold text-gray-900 mb-4">Change Password</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                    <input type="password" name="current_password" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" name="new_password" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-medium hover:bg-indigo-700 transition">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <?php else: ?>
                <!-- Addresses (Placeholder) -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">My Addresses</h1>
                    <p class="text-gray-500">Manage your shipping and billing addresses.</p>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
                    <i data-lucide="map-pin" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                    <p class="text-gray-500 mb-4">No addresses saved yet.</p>
                    <button class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-indigo-700">Add New Address</button>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php get_footer(); ?>
