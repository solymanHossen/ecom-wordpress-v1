<?php
/**
 * Template Name: Order Confirmation
 */
get_header();
global $wpdb;
$prefix = $wpdb->prefix . 'nexmart_';

$order_id = isset($_GET['order']) ? intval($_GET['order']) : 0;
$order = null;
$order_items = [];

if ($order_id) {
    // Get order - allow viewing for logged in users OR guests via order_id
    if (is_user_logged_in()) {
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}orders WHERE id = %d AND user_id = %d",
            $order_id, get_current_user_id()
        ));
    }
    
    // Fallback for guest users or if user's order not found
    if (!$order) {
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}orders WHERE id = %d AND user_id IS NULL",
            $order_id
        ));
    }
    
    if ($order) {
        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}order_items WHERE order_id = %d",
            $order_id
        ));
    }
}
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="container mx-auto px-4 max-w-3xl">
        <?php if ($order): ?>
        <!-- Success State -->
        <div class="bg-white rounded-2xl shadow-sm p-8 text-center mb-8">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="check" class="w-10 h-10 text-green-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Confirmed!</h1>
            <p class="text-gray-600 mb-4">Thank you for your purchase. Your order has been received.</p>
            <p class="text-primary-600 font-semibold text-lg">Order #<?php echo esc_html($order->order_number); ?></p>
        </div>
        
        <!-- Order Details -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">Order Details</h2>
            </div>
            <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Order Information</h3>
                        <div class="space-y-1 text-sm text-gray-600">
                            <p><span class="font-medium">Date:</span> <?php echo date('F j, Y', strtotime($order->created_at)); ?></p>
                            <p><span class="font-medium">Status:</span> <span class="inline-block bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded text-xs"><?php echo ucfirst($order->status); ?></span></p>
                            <p><span class="font-medium">Payment:</span> <?php echo ucfirst(str_replace('_', ' ', $order->payment_method)); ?></p>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Shipping Address</h3>
                        <div class="text-sm text-gray-600">
                            <p><?php echo esc_html($order->shipping_first_name . ' ' . $order->shipping_last_name); ?></p>
                            <p><?php echo esc_html($order->shipping_address); ?></p>
                            <?php if ($order->shipping_address_2): ?>
                            <p><?php echo esc_html($order->shipping_address_2); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html($order->shipping_city . ', ' . $order->shipping_state . ' ' . $order->shipping_postcode); ?></p>
                            <p><?php echo esc_html($order->shipping_country); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="border-t pt-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Items Ordered</h3>
                    <div class="space-y-4">
                        <?php foreach ($order_items as $item): ?>
                        <div class="flex gap-4 py-3 border-b last:border-0">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                <img src="<?php echo esc_url($item->product_image ?: 'https://placehold.co/80x80/e2e8f0/64748b?text=P'); ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900"><?php echo esc_html($item->product_name); ?></h4>
                                <p class="text-sm text-gray-500">Qty: <?php echo $item->quantity; ?> × $<?php echo number_format($item->unit_price, 2); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">$<?php echo number_format($item->total, 2); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Order Totals -->
                <div class="border-t mt-6 pt-6">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">$<?php echo number_format($order->subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium"><?php echo $order->shipping_cost > 0 ? '$' . number_format($order->shipping_cost, 2) : 'FREE'; ?></span>
                        </div>
                        <?php if ($order->discount > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount</span>
                            <span class="font-medium text-green-600">-$<?php echo number_format($order->discount, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax</span>
                            <span class="font-medium">$<?php echo number_format($order->tax, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-lg pt-2 border-t">
                            <span class="font-bold">Total</span>
                            <span class="font-bold text-primary-600">$<?php echo number_format($order->total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Confirmation -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-8">
            <div class="flex gap-4">
                <i data-lucide="mail" class="w-6 h-6 text-blue-600 flex-shrink-0"></i>
                <div>
                    <h3 class="font-semibold text-blue-900 mb-1">Confirmation Email Sent</h3>
                    <p class="text-blue-700 text-sm">We've sent a confirmation email to your registered email address with your order details.</p>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?php echo home_url('/shop/'); ?>" class="inline-flex items-center justify-center gap-2 bg-primary-600 text-white px-8 py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                Continue Shopping
            </a>
            <a href="<?php echo home_url('/my-account/orders/'); ?>" class="inline-flex items-center justify-center gap-2 bg-white border-2 border-gray-200 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:border-gray-300 transition">
                <i data-lucide="package" class="w-5 h-5"></i>
                View All Orders
            </a>
        </div>
        
        <?php else: ?>
        <!-- No Order Found -->
        <div class="bg-white rounded-2xl shadow-sm p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="package-x" class="w-10 h-10 text-gray-400"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Order Not Found</h1>
            <p class="text-gray-600 mb-8">We couldn't find the order you're looking for. It may have been removed or you may not have permission to view it.</p>
            <a href="<?php echo home_url('/shop/'); ?>" class="inline-block bg-primary-600 text-white px-8 py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                Go to Shop
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
