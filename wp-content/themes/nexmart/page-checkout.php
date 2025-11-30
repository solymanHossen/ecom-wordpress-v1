<?php
/**
 * Template Name: Checkout Page
 */
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/checkout/')));
    exit;
}
get_header();
$user_id = get_current_user_id();
$user = wp_get_current_user();
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold mb-8">Checkout</h1>
        
        <form id="checkout-form" class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <!-- Shipping Address -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-primary-600"></i>
                        Shipping Address
                    </h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                            <input type="tel" name="phone" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                            <input type="text" name="address" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Street address">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                            <input type="text" name="city" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">State/Province *</label>
                            <input type="text" name="state" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ZIP Code *</label>
                            <input type="text" name="postcode" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                            <select name="country" required class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="GB">United Kingdom</option>
                                <option value="AU">Australia</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-primary-600"></i>
                        Payment Method
                    </h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-4 p-4 border rounded-xl cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input type="radio" name="payment_method" value="card" checked class="w-5 h-5 text-primary-600">
                            <i data-lucide="credit-card" class="w-6 h-6 text-gray-400"></i>
                            <span class="font-medium">Credit/Debit Card</span>
                        </label>
                        <label class="flex items-center gap-4 p-4 border rounded-xl cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input type="radio" name="payment_method" value="paypal" class="w-5 h-5 text-primary-600">
                            <span class="font-bold text-blue-600">PayPal</span>
                        </label>
                        <label class="flex items-center gap-4 p-4 border rounded-xl cursor-pointer hover:bg-gray-50 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input type="radio" name="payment_method" value="cod" class="w-5 h-5 text-primary-600">
                            <i data-lucide="banknote" class="w-6 h-6 text-gray-400"></i>
                            <span class="font-medium">Cash on Delivery</span>
                        </label>
                    </div>
                    
                    <div id="card-details" class="mt-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                            <input type="text" id="card_number" placeholder="1234 5678 9012 3456" class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                <input type="text" id="card_expiry" placeholder="MM/YY" class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                <input type="text" id="card_cvv" placeholder="123" class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Notes -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-4">Order Notes (Optional)</h2>
                    <textarea name="notes" rows="3" placeholder="Special instructions for your order..." class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div>
                <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-24">
                    <h2 class="text-xl font-bold mb-6">Order Summary</h2>
                    
                    <div id="checkout-items" class="space-y-4 mb-6 max-h-64 overflow-y-auto">
                        <div class="text-center py-4">
                            <i data-lucide="loader-2" class="w-8 h-8 text-gray-300 mx-auto animate-spin"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-3 border-t pt-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span id="checkout-subtotal" class="font-semibold">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span id="checkout-shipping" class="font-semibold">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount</span>
                            <span id="checkout-discount" class="font-semibold text-green-600">-$0.00</span>
                        </div>
                        <div class="border-t pt-4 flex justify-between">
                            <span class="text-lg font-bold">Total</span>
                            <span id="checkout-total" class="text-xl font-bold text-primary-600">$0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" id="place-order-btn" class="w-full bg-primary-600 text-white py-4 rounded-xl font-semibold hover:bg-primary-700 transition mt-6 flex items-center justify-center gap-2">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                        Place Order
                    </button>
                    
                    <p class="text-xs text-gray-500 text-center mt-4">
                        By placing this order, you agree to our Terms of Service and Privacy Policy.
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let cartData = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCheckoutCart();
    
    document.querySelectorAll('input[name="payment_method"]').forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('card-details').style.display = this.value === 'card' ? 'block' : 'none';
        });
    });
    
    document.getElementById('checkout-form').addEventListener('submit', placeOrder);
});

function loadCheckoutCart() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=nexmart_get_cart')
        .then(response => response.json())
        .then(data => {
            console.log('Checkout cart data:', data);
            const cart = data.success ? data.data.cart : null;
            if (cart && cart.items && cart.items.length > 0) {
                cartData = cart;
                renderCheckoutItems(cart);
                updateCheckoutSummary(cart);
            } else {
                window.location.href = '<?php echo home_url('/cart/'); ?>';
            }
        });
}

function renderCheckoutItems(cart) {
    const container = document.getElementById('checkout-items');
    let html = '';
    
    cart.items.forEach(item => {
        const price = item.current_price || item.sale_price || item.price;
        html += `
            <div class="flex gap-3 py-2">
                <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                    <img src="${item.image || 'https://placehold.co/80x80/e2e8f0/64748b?text=P'}" class="w-full h-full object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-sm text-gray-900 truncate">${item.name}</h4>
                    <p class="text-sm text-gray-500">Qty: ${item.quantity}</p>
                </div>
                <div class="text-right">
                    <p class="font-semibold">$${(parseFloat(price) * item.quantity).toFixed(2)}</p>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateCheckoutSummary(cart) {
    const subtotal = parseFloat(cart.subtotal || 0);
    const discount = parseFloat(cart.discount || 0);
    const shipping = subtotal >= 50 ? 0 : 5.99;
    const total = subtotal - discount + shipping;
    
    document.getElementById('checkout-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('checkout-shipping').textContent = shipping === 0 ? 'FREE' : '$' + shipping.toFixed(2);
    document.getElementById('checkout-discount').textContent = '-$' + discount.toFixed(2);
    document.getElementById('checkout-total').textContent = '$' + total.toFixed(2);
}

function placeOrder(e) {
    e.preventDefault();
    
    const btn = document.getElementById('place-order-btn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processing...';
    lucide.createIcons();
    
    const form = document.getElementById('checkout-form');
    const formData = new FormData(form);
    formData.append('action', 'nexmart_create_order');
    formData.append('nonce', '<?php echo wp_create_nonce('nexmart_nonce'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo home_url('/order-confirmation/'); ?>?order=' + data.data.order_id;
        } else {
            alert(data.data || 'Failed to place order. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="lock" class="w-5 h-5"></i> Place Order';
            lucide.createIcons();
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="lock" class="w-5 h-5"></i> Place Order';
        lucide.createIcons();
    });
}
</script>

<?php get_footer(); ?>
