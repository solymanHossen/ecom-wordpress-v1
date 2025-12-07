<?php
/**
 * Template Name: Cart Page
 */
get_header();
$is_logged_in = is_user_logged_in();
$user_id = get_current_user_id();
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold mb-8">Shopping Cart</h1>
        
        <div id="cart-container" class="grid lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div id="cart-items" class="space-y-4">
                    <div class="bg-white rounded-2xl p-8 text-center">
                        <i data-lucide="loader-2" class="w-12 h-12 text-gray-300 mx-auto mb-4 animate-spin"></i>
                        <p class="text-gray-500">Loading your cart...</p>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div>
                <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-24">
                    <h2 class="text-xl font-bold mb-6">Order Summary</h2>
                    
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span id="cart-subtotal" class="font-semibold">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span id="cart-shipping" class="font-semibold">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount</span>
                            <span id="cart-discount" class="font-semibold text-green-600">-$0.00</span>
                        </div>
                        <div class="border-t pt-4 flex justify-between">
                            <span class="text-lg font-bold">Total</span>
                            <span id="cart-total" class="text-lg font-bold text-primary-600">$0.00</span>
                        </div>
                    </div>
                    
                    <!-- Coupon -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Coupon Code</label>
                        <div class="flex gap-2">
                            <input type="text" id="coupon-code" placeholder="Enter code" class="flex-1 border rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <button id="apply-coupon" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">Apply</button>
                        </div>
                        <p id="coupon-message" class="text-sm mt-2 hidden"></p>
                    </div>
                    
                    <?php if ($is_logged_in): ?>
                    <a href="<?php echo home_url('/checkout/'); ?>" id="checkout-btn" class="block w-full bg-primary-600 text-white text-center py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                        Proceed to Checkout
                    </a>
                    <?php else: ?>
                    <a href="<?php echo wp_login_url(home_url('/checkout/')); ?>" class="block w-full bg-primary-600 text-white text-center py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                        Login to Checkout
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo home_url('/shop/'); ?>" class="block w-full text-center py-3 mt-4 text-primary-600 hover:underline">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Empty Cart State -->
        <div id="empty-cart" class="hidden bg-white rounded-2xl p-12 text-center">
            <i data-lucide="shopping-cart" class="w-24 h-24 text-gray-300 mx-auto mb-6"></i>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Your cart is empty</h2>
            <p class="text-gray-500 mb-8">Looks like you haven't added anything to your cart yet.</p>
            <a href="<?php echo home_url('/shop/'); ?>" class="inline-block bg-primary-600 text-white px-8 py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                Start Shopping
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    
    document.getElementById('apply-coupon').addEventListener('click', applyCoupon);
});

function loadCart() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=nexmart_get_cart')
        .then(response => response.json())
        .then(data => {
            console.log('Cart data:', data);
            const cart = data.success ? data.data.cart : null;
            if (cart && cart.items && cart.items.length > 0) {
                renderCartItems(cart);
                updateSummary(cart);
                document.getElementById('cart-container').classList.remove('hidden');
                document.getElementById('empty-cart').classList.add('hidden');
            } else {
                document.getElementById('cart-container').classList.add('hidden');
                document.getElementById('empty-cart').classList.remove('hidden');
            }
        });
}

function renderCartItems(cart) {
    const container = document.getElementById('cart-items');
    
    // Group by vendor
    const vendors = {};
    cart.items.forEach(item => {
        const vendorId = item.vendor_id || 0;
        if (!vendors[vendorId]) {
            vendors[vendorId] = {
                name: item.vendor_name || 'NexMart',
                items: []
            };
        }
        vendors[vendorId].items.push(item);
    });
    
    let html = '';
    Object.keys(vendors).forEach(vendorId => {
        const vendor = vendors[vendorId];
        html += `
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b flex items-center gap-2">
                    <i data-lucide="store" class="w-5 h-5 text-primary-600"></i>
                    <span class="font-semibold">${vendor.name}</span>
                </div>
                <div class="divide-y">
        `;
        
        vendor.items.forEach(item => {
            const displayPrice = item.current_price || item.sale_price || item.price;
            const cartId = item.cart_id || item.id;
            const stockQty = parseInt(item.stock_quantity || 9999);
            const isMaxStock = item.quantity >= stockQty;
            
            html += `
                <div class="p-6 flex gap-4">
                    <div class="w-24 h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                        <img src="${item.image || 'https://placehold.co/100x100/e2e8f0/64748b?text=Product'}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900 mb-1">${item.name}</h3>
                        <p class="text-primary-600 font-bold">$${parseFloat(displayPrice).toFixed(2)}</p>
                        ${isMaxStock ? '<p class="text-xs text-red-500 mt-1">Max stock reached</p>' : ''}
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="updateCartItem('${cartId}', ${item.quantity - 1})" class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100">-</button>
                        <span class="w-8 text-center">${item.quantity}</span>
                        <button onclick="updateCartItem('${cartId}', ${item.quantity + 1})" 
                                class="w-8 h-8 border rounded flex items-center justify-center hover:bg-gray-100 ${isMaxStock ? 'opacity-50 cursor-not-allowed' : ''}"
                                ${isMaxStock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">$${(parseFloat(displayPrice) * item.quantity).toFixed(2)}</p>
                        <button onclick="removeFromCart('${cartId}')" class="text-red-500 text-sm hover:underline mt-1">Remove</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div></div>';
    });
    
    container.innerHTML = html;
    lucide.createIcons();
}

function updateSummary(cart) {
    document.getElementById('cart-subtotal').textContent = '$' + parseFloat(cart.subtotal || 0).toFixed(2);
    document.getElementById('cart-shipping').textContent = cart.subtotal >= 50 ? 'FREE' : '$5.99';
    document.getElementById('cart-discount').textContent = '-$' + parseFloat(cart.discount || 0).toFixed(2);
    
    let total = parseFloat(cart.subtotal || 0) - parseFloat(cart.discount || 0);
    if (cart.subtotal < 50) total += 5.99;
    document.getElementById('cart-total').textContent = '$' + total.toFixed(2);
}

function updateCartItem(cartItemId, quantity) {
    if (quantity < 1) {
        removeFromCart(cartItemId);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'nexmart_update_cart');
    formData.append('cart_item_id', cartItemId);
    formData.append('quantity', quantity);
    formData.append('nonce', '<?php echo wp_create_nonce('nexmart_nonce'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCart();
            updateCartCount();
        } else {
            alert(data.data.message || 'Error updating cart');
        }
    });
}

function removeFromCart(cartItemId) {
    const formData = new FormData();
    formData.append('action', 'nexmart_remove_from_cart');
    formData.append('cart_item_id', cartItemId);
    formData.append('nonce', '<?php echo wp_create_nonce('nexmart_nonce'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCart();
            updateCartCount();
        }
    });
}

function applyCoupon() {
    const code = document.getElementById('coupon-code').value;
    const msg = document.getElementById('coupon-message');
    
    if (!code) {
        msg.textContent = 'Please enter a coupon code';
        msg.className = 'text-sm mt-2 text-red-600';
        msg.classList.remove('hidden');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'nexmart_apply_coupon');
    formData.append('code', code);
    formData.append('nonce', '<?php echo wp_create_nonce('nexmart_nonce'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            msg.textContent = data.data.message || 'Coupon applied successfully!';
            msg.className = 'text-sm mt-2 text-green-600';
            loadCart();
        } else {
            msg.textContent = (data.data && data.data.message) ? data.data.message : 'Invalid coupon code';
            msg.className = 'text-sm mt-2 text-red-600';
        }
        msg.classList.remove('hidden');
    });
}

function updateCartCount() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=nexmart_get_cart_count')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.cart-count');
            if (badge) {
                badge.textContent = data.data || 0;
                badge.style.display = (data.data || 0) > 0 ? 'flex' : 'none';
            }
        });
}
</script>

<?php get_footer(); ?>
