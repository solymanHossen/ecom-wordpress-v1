<footer class="bg-white border-t border-gray-200 pt-16 pb-8 text-gray-600 text-sm mt-12">
    <div class="container mx-auto px-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 mb-12">
        <div class="col-span-2 lg:col-span-2">
            <div class="flex items-center gap-2 mb-4">
                <div class="bg-indigo-600 p-1.5 rounded-lg">
                    <i data-lucide="zap" class="text-white w-5 h-5"></i>
                </div>
                <span class="text-xl font-bold text-gray-900">Nex<span class="text-indigo-600">Mart</span></span>
            </div>
            <p class="mb-6 max-w-sm text-gray-500">
                The world's most advanced AI-powered marketplace. Experience shopping like never before.
            </p>
            <div class="flex gap-4">
                 <!-- Socials (Visual only) -->
                 <a href="#" class="w-10 h-10 bg-gray-100 rounded-full hover:bg-indigo-600 hover:text-white transition-colors flex items-center justify-center"><i data-lucide="facebook" class="w-4 h-4"></i></a>
                 <a href="#" class="w-10 h-10 bg-gray-100 rounded-full hover:bg-indigo-600 hover:text-white transition-colors flex items-center justify-center"><i data-lucide="twitter" class="w-4 h-4"></i></a>
                 <a href="#" class="w-10 h-10 bg-gray-100 rounded-full hover:bg-indigo-600 hover:text-white transition-colors flex items-center justify-center"><i data-lucide="instagram" class="w-4 h-4"></i></a>
            </div>
        </div>
        
        <div>
            <h4 class="font-bold text-gray-900 mb-4">Shopping</h4>
            <ul class="space-y-2">
                <li><a href="#" class="hover:text-indigo-600">Trending Now</a></li>
                <li><a href="#" class="hover:text-indigo-600">Best Sellers</a></li>
                <li><a href="#" class="hover:text-indigo-600">Flash Deals</a></li>
            </ul>
        </div>
        
        <div>
            <h4 class="font-bold text-gray-900 mb-4">Customer Care</h4>
            <ul class="space-y-2">
                <li><a href="#" class="hover:text-indigo-600">Help Center</a></li>
                <li><a href="#" class="hover:text-indigo-600">Returns & Refunds</a></li>
                <li><a href="#" class="hover:text-indigo-600">Contact Us</a></li>
            </ul>
        </div>

        <div>
            <h4 class="font-bold text-gray-900 mb-4">Sell</h4>
            <ul class="space-y-2">
                <li><a href="<?php echo home_url('/vendor-dashboard'); ?>" class="hover:text-indigo-600">Vendor Hub</a></li>
                <li><a href="#" class="hover:text-indigo-600">Start Selling</a></li>
            </ul>
        </div>
    </div>
    
    <div class="container mx-auto px-4 pt-8 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
        <p>&copy; <?php echo date('Y'); ?> NexMart Inc. All rights reserved.</p>
        <div class="flex gap-2">
             <div class="bg-gray-100 px-3 py-1 rounded text-xs font-bold text-gray-500">Visa</div>
             <div class="bg-gray-100 px-3 py-1 rounded text-xs font-bold text-gray-500">Mastercard</div>
             <div class="bg-gray-100 px-3 py-1 rounded text-xs font-bold text-gray-500">PayPal</div>
        </div>
    </div>
</footer>

<!-- Sticky Mobile Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden flex justify-around p-3 z-50 shadow-lg pb-safe">
    <a href="<?php echo home_url(); ?>" class="flex flex-col items-center gap-1 text-indigo-600">
        <i data-lucide="home" class="w-5 h-5"></i> <span class="text-[10px]">Home</span>
    </a>
    <a href="<?php echo home_url('/shop'); ?>" class="flex flex-col items-center gap-1 text-gray-400">
        <i data-lucide="search" class="w-5 h-5"></i> <span class="text-[10px]">Shop</span>
    </a>
    <button class="cart-btn flex flex-col items-center gap-1 text-gray-400 relative">
        <span class="cart-count absolute -top-1 right-2 bg-rose-500 text-white text-[8px] w-4 h-4 rounded-full flex items-center justify-center hidden">0</span>
        <i data-lucide="shopping-cart" class="w-5 h-5"></i> <span class="text-[10px]">Cart</span>
    </button>
    <a href="<?php echo is_user_logged_in() ? home_url('/my-account') : wp_login_url(); ?>" class="flex flex-col items-center gap-1 text-gray-400">
        <i data-lucide="user" class="w-5 h-5"></i> <span class="text-[10px]">Account</span>
    </a>
</div>

<!-- Cart Drawer -->
<div id="cart-overlay" class="fixed inset-0 bg-black/50 z-50 hidden"></div>
<div id="cart-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">
    <div class="p-6 border-b flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Shopping Cart</h2>
        <button id="close-cart" class="p-2 hover:bg-gray-100 rounded-full transition">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
    </div>
    <div id="cart-items" class="flex-1 overflow-y-auto">
        <div class="flex flex-col items-center justify-center h-64 text-gray-400">
            <i data-lucide="shopping-bag" class="w-16 h-16 mb-4"></i>
            <p>Your cart is empty</p>
        </div>
    </div>
    <div class="p-6 border-t bg-gray-50">
        <div class="flex justify-between items-center mb-4">
            <span class="text-gray-600">Subtotal:</span>
            <span id="cart-drawer-subtotal" class="text-xl font-bold text-gray-900">$0.00</span>
        </div>
        <a href="<?php echo home_url('/cart'); ?>" class="block w-full bg-gray-200 text-gray-800 py-3 rounded-xl text-center font-semibold hover:bg-gray-300 transition mb-2">
            View Cart
        </a>
        <a href="<?php echo home_url('/checkout'); ?>" class="block w-full bg-indigo-600 text-white py-3 rounded-xl text-center font-semibold hover:bg-indigo-700 transition">
            Checkout
        </a>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
