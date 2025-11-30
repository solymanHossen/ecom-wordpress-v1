/**
 * NexMart Main JavaScript
 * E-commerce functionality and UI interactions
 */

// Global NexMart object
window.NexMart = {
    cart: null,
    wishlist: [],
    
    // Initialize
    init: function() {
        this.initIcons();
        this.initMobileMenu();
        this.initSearch();
        this.initCart();
        this.initWishlist();
        this.initProductGallery();
        this.initQuantityControls();
        this.initNotifications();
        this.initCheckout();
    },
    
    // Initialize Lucide Icons
    initIcons: function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    },
    
    // Mobile Menu
    initMobileMenu: function() {
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    },
    
    // Search functionality
    initSearch: function() {
        const searchInput = document.querySelector('.search-input');
        const searchResults = document.querySelector('.search-results');
        
        if (searchInput) {
            let debounceTimer;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    if (searchResults) searchResults.classList.add('hidden');
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    NexMart.searchProducts(query);
                }, 300);
            });
        }
    },
    
    // Search products
    searchProducts: function(query) {
        fetch(`${nexmartObj.ajaxurl}?action=nexmart_search_products&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.products.length > 0) {
                    this.renderSearchResults(data.data.products);
                }
            });
    },
    
    // Render search results
    renderSearchResults: function(products) {
        const container = document.querySelector('.search-results');
        if (!container) return;
        
        let html = '<div class="divide-y">';
        products.forEach(product => {
            html += `
                <a href="${nexmartObj.siteUrl}product/${product.slug}" class="flex items-center gap-4 p-4 hover:bg-gray-50">
                    <img src="${product.featured_image || 'https://via.placeholder.com/50'}" 
                         alt="${product.name}" 
                         class="w-12 h-12 object-cover rounded-lg">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900">${product.name}</h4>
                        <p class="text-sm text-indigo-600 font-bold">$${product.current_price.toFixed(2)}</p>
                    </div>
                </a>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
        container.classList.remove('hidden');
    },
    
    // Initialize cart
    initCart: function() {
        console.log('NexMart cart initializing...');
        console.log('nexmartObj available:', typeof nexmartObj !== 'undefined');
        if (typeof nexmartObj !== 'undefined') {
            console.log('Ajax URL:', nexmartObj.ajaxurl);
            console.log('Initial cart count:', nexmartObj.cartCount);
        }
        
        // Add to cart buttons
        const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
        console.log('Found add-to-cart buttons:', addToCartBtns.length);
        
        addToCartBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.dataset.productId;
                let quantity = this.dataset.quantity || 1;
                
                console.log('Add to cart clicked - Product ID:', productId, 'Button:', this);
                
                if (!productId) {
                    console.error('No product ID found on button');
                    NexMart.showNotification('Error: No product ID', 'error');
                    return;
                }
                
                // Check for quantity input on single product page
                const qtyInput = document.getElementById('qty');
                if (qtyInput && this.id === 'add-to-cart-btn') {
                    quantity = parseInt(qtyInput.value) || 1;
                }
                
                console.log('Calling addToCart with:', productId, quantity);
                NexMart.addToCart(productId, quantity);
            });
        });
        
        // Cart drawer toggle
        const cartBtn = document.querySelectorAll('.cart-btn');
        const cartDrawer = document.getElementById('cart-drawer');
        const cartOverlay = document.getElementById('cart-overlay');
        const closeCart = document.getElementById('close-cart');
        
        console.log('Cart buttons found:', cartBtn.length);
        console.log('Cart drawer:', cartDrawer ? 'found' : 'NOT FOUND');
        
        if (cartBtn.length > 0 && cartDrawer) {
            cartBtn.forEach(btn => {
                btn.addEventListener('click', () => {
                    console.log('Cart button clicked');
                    NexMart.openCartDrawer();
                });
            });
            
            if (closeCart) {
                closeCart.addEventListener('click', () => {
                    NexMart.closeCartDrawer();
                });
            }
            
            if (cartOverlay) {
                cartOverlay.addEventListener('click', () => {
                    NexMart.closeCartDrawer();
                });
            }
        }
        
        // Load cart on init
        this.loadCart();
    },
    
    // Add to cart
    addToCart: function(productId, quantity = 1, attributes = {}) {
        console.log('Adding to cart:', { productId, quantity, attributes });
        
        // Check if nexmartObj is available
        if (typeof nexmartObj === 'undefined') {
            console.error('nexmartObj is not defined');
            this.showNotification('Configuration error. Please refresh the page.', 'error');
            return Promise.reject('Configuration error');
        }
        
        const formData = new FormData();
        formData.append('action', 'nexmart_add_to_cart');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        formData.append('attributes', JSON.stringify(attributes));
        formData.append('nonce', nexmartObj.nonce);
        
        return fetch(nexmartObj.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            console.log('Add to cart response:', data);
            
            if (data.success) {
                this.cart = data.data.cart;
                this.updateCartUI();
                this.showNotification('Product added to cart!', 'success');
                
                // Auto-open cart drawer after adding
                setTimeout(() => {
                    this.openCartDrawer();
                }, 300);
            } else {
                throw new Error(data.data?.message || 'Failed to add to cart');
            }
            
            return data;
        })
        .catch(err => {
            console.error('Add to cart error:', err);
            this.showNotification(err.message || 'Failed to add to cart', 'error');
            throw err;
        });
    },
    
    // Load cart
    loadCart: function() {
        if (typeof nexmartObj === 'undefined') {
            console.error('nexmartObj is not defined');
            return;
        }
        
        // Initialize cart count from server-side value first
        if (nexmartObj.cartCount) {
            document.querySelectorAll('.cart-count').forEach(badge => {
                badge.textContent = nexmartObj.cartCount;
                if (nexmartObj.cartCount > 0) {
                    badge.classList.remove('hidden');
                }
            });
        }
        
        // Fetch fresh cart data from server
        fetch(`${nexmartObj.ajaxurl}?action=nexmart_get_cart&nonce=${nexmartObj.nonce}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Cache-Control': 'no-cache'
            }
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.json();
            })
            .then(data => {
                console.log('Cart loaded:', data);
                if (data.success && data.data && data.data.cart) {
                    this.cart = data.data.cart;
                    this.updateCartUI();
                } else {
                    console.warn('Cart response structure unexpected:', data);
                }
            })
            .catch(err => {
                console.error('Load cart error:', err);
                // Still try to update UI with existing data
                if (this.cart) {
                    this.updateCartUI();
                }
            });
    },
    
    // Update cart UI
    updateCartUI: function() {
        const itemCount = this.cart?.item_count || 0;
        const items = this.cart?.items || [];
        
        console.log('Updating cart UI, item count:', itemCount, 'items:', items.length);
        
        // Update cart count badges
        document.querySelectorAll('.cart-count').forEach(badge => {
            badge.textContent = itemCount;
            if (itemCount > 0) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
        
        // Update cart drawer if exists
        const cartItems = document.getElementById('cart-items');
        if (cartItems) {
            this.renderCartDrawer();
        }
    },
    
    // Render cart drawer
    renderCartDrawer: function() {
        const container = document.getElementById('cart-items');
        const subtotalEl = document.getElementById('cart-drawer-subtotal');
        
        if (!container) {
            console.warn('Cart items container not found');
            return;
        }
        
        // Check if cart and items exist
        if (!this.cart || !this.cart.items || this.cart.items.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                    <i data-lucide="shopping-bag" class="w-16 h-16 mb-4"></i>
                    <p>Your cart is empty</p>
                </div>
            `;
            if (subtotalEl) {
                subtotalEl.textContent = '$0.00';
            }
            lucide.createIcons();
            return;
        }
        
        let html = '';
        this.cart.items.forEach(item => {
            const itemImage = item.image || item.featured_image || 'https://via.placeholder.com/80';
            const itemPrice = parseFloat(item.current_price || item.unit_price || item.price || 0);
            const itemQty = parseInt(item.quantity || 1);
            const lineTotal = itemPrice * itemQty;
            
            html += `
                <div class="flex gap-4 p-4 border-b" data-cart-id="${item.cart_id || item.id}">
                    <img src="${itemImage}" 
                         alt="${item.name || 'Product'}" 
                         class="w-20 h-20 object-cover rounded-lg">
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-gray-900 truncate">${item.name || 'Product'}</h4>
                        <p class="text-sm text-gray-500">$${itemPrice.toFixed(2)} × ${itemQty}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <button class="cart-qty-btn w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100" data-action="decrease" data-cart-id="${item.cart_id || item.id}">
                                <i data-lucide="minus" class="w-4 h-4"></i>
                            </button>
                            <span class="w-8 text-center">${itemQty}</span>
                            <button class="cart-qty-btn w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100" data-action="increase" data-cart-id="${item.cart_id || item.id}">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">$${lineTotal.toFixed(2)}</p>
                        <button class="text-red-500 hover:text-red-700 mt-2 cart-remove-btn" data-cart-id="${item.cart_id || item.id}">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Update subtotal
        if (subtotalEl && this.cart.subtotal !== undefined) {
            const subtotal = parseFloat(this.cart.subtotal || 0);
            subtotalEl.textContent = '$' + subtotal.toFixed(2);
        }
        
        lucide.createIcons();
        
        // Update subtotal
        if (subtotalEl) {
            subtotalEl.textContent = '$' + this.cart.subtotal.toFixed(2);
        }
        
        // Bind events
        container.querySelectorAll('.cart-qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const cartId = btn.dataset.cartId;
                const action = btn.dataset.action;
                const item = this.cart.items.find(i => i.cart_id == cartId);
                
                if (item) {
                    let newQty = action === 'increase' ? item.quantity + 1 : item.quantity - 1;
                    if (newQty < 1) {
                        this.removeFromCart(cartId);
                    } else {
                        this.updateCartItem(cartId, newQty);
                    }
                }
            });
        });
        
        container.querySelectorAll('.cart-remove-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.removeFromCart(btn.dataset.cartId);
            });
        });
    },
    
    // Update cart item
    updateCartItem: function(cartId, quantity) {
        const formData = new FormData();
        formData.append('action', 'nexmart_update_cart');
        formData.append('cart_item_id', cartId);
        formData.append('quantity', quantity);
        formData.append('nonce', nexmartObj.nonce);
        
        fetch(nexmartObj.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.cart = data.data.cart;
                this.updateCartUI();
            }
        });
    },
    
    // Remove from cart
    removeFromCart: function(cartId) {
        const formData = new FormData();
        formData.append('action', 'nexmart_remove_from_cart');
        formData.append('cart_item_id', cartId);
        formData.append('nonce', nexmartObj.nonce);
        
        fetch(nexmartObj.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.cart = data.data.cart;
                this.updateCartUI();
                this.showNotification('Item removed from cart', 'success');
            }
        });
    },
    
    // Open cart drawer
    openCartDrawer: function() {
        const drawer = document.getElementById('cart-drawer');
        const overlay = document.getElementById('cart-overlay');
        
        if (drawer) {
            drawer.classList.remove('translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    },
    
    // Close cart drawer
    closeCartDrawer: function() {
        const drawer = document.getElementById('cart-drawer');
        const overlay = document.getElementById('cart-overlay');
        
        if (drawer) {
            drawer.classList.add('translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    },
    
    // Wishlist
    initWishlist: function() {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = btn.dataset.productId;
                this.toggleWishlist(productId, btn);
            });
        });
    },
    
    toggleWishlist: function(productId, button) {
        if (typeof nexmartObj === 'undefined') {
            console.error('nexmartObj is not defined');
            this.showNotification('Configuration error. Please refresh the page.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'nexmart_toggle_wishlist');
        formData.append('product_id', productId);
        formData.append('nonce', nexmartObj.nonce);
        
        fetch(nexmartObj.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                button.classList.toggle('text-red-500', data.data.in_wishlist);
                button.classList.toggle('fill-red-500', data.data.in_wishlist);
                this.showNotification(data.data.message, 'success');
            } else {
                this.showNotification(data.data?.message || 'Please log in to add to wishlist', 'error');
            }
        })
        .catch(err => {
            console.error('Wishlist toggle error:', err);
            this.showNotification('An error occurred', 'error');
        });
    },
    
    // Product gallery
    initProductGallery: function() {
        const mainImage = document.getElementById('main-product-image');
        const thumbnails = document.querySelectorAll('.product-thumbnail');
        
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.dataset.fullImage;
                thumbnails.forEach(t => t.classList.remove('ring-2', 'ring-indigo-500'));
                this.classList.add('ring-2', 'ring-indigo-500');
            });
        });
    },
    
    // Quantity controls on product pages
    initQuantityControls: function() {
        const qtyBtns = document.querySelectorAll('.qty-btn');
        console.log('Initializing quantity controls, buttons found:', qtyBtns.length);
        
        qtyBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const input = this.closest('.flex').querySelector('.qty-input') || 
                              this.parentElement.querySelector('.qty-input') ||
                              document.getElementById('qty');
                
                if (!input) {
                    console.error('Quantity input not found for button:', this);
                    return;
                }
                
                let value = parseInt(input.value) || 1;
                const action = this.dataset.action;
                const max = parseInt(input.max) || 999;
                
                console.log('Quantity control clicked:', action, 'current value:', value);
                
                if (action === 'increase' && value < max) {
                    value++;
                } else if (action === 'decrease' && value > 1) {
                    value--;
                }
                
                input.value = value;
                console.log('New quantity value:', value);
            });
        });
    },
    
    // Notifications
    initNotifications: function() {
        // Create notification container
        if (!document.getElementById('notification-container')) {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'fixed bottom-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }
    },
    
    showNotification: function(message, type = 'success') {
        const container = document.getElementById('notification-container');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `px-6 py-3 rounded-xl shadow-lg transform translate-x-full transition-transform duration-300 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-indigo-500'
        } text-white font-semibold flex items-center gap-2`;
        
        const icon = type === 'success' ? 'check-circle' : 
                     type === 'error' ? 'x-circle' : 
                     type === 'warning' ? 'alert-triangle' : 'info';
        
        notification.innerHTML = `<i data-lucide="${icon}" class="w-5 h-5"></i><span>${message}</span>`;
        container.appendChild(notification);
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 10);
        
        // Remove after delay
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },
    
    // Checkout
    initCheckout: function() {
        const checkoutForm = document.getElementById('checkout-form');
        if (!checkoutForm) return;
        
        console.log('Checkout form found, initializing...');
        
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(checkoutForm);
            formData.append('action', 'nexmart_create_order');
            formData.append('nonce', nexmartObj.nonce);
            
            const submitBtn = checkoutForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Processing...';
            lucide.createIcons();
            
            fetch(nexmartObj.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                console.log('Checkout response:', data);
                
                if (data.success) {
                    NexMart.showNotification('Order placed successfully!', 'success');
                    
                    // Redirect to order confirmation
                    setTimeout(() => {
                        window.location.href = nexmartObj.siteUrl + 'order-confirmation/?order=' + data.data.order_number;
                    }, 1000);
                } else {
                    NexMart.showNotification(data.data?.message || 'Failed to place order', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error('Checkout error:', err);
                NexMart.showNotification('An error occurred', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('%c🚀 NexMart Initializing...', 'color: blue; font-size: 16px; font-weight: bold');
    console.log('nexmartObj:', window.nexmartObj);
    console.log('jQuery:', typeof jQuery !== 'undefined' ? 'Loaded' : 'NOT LOADED');
    console.log('lucide:', typeof lucide !== 'undefined' ? 'Loaded' : 'NOT LOADED');
    
    NexMart.init();
    
    console.log('%c✅ NexMart Initialized', 'color: green; font-size: 16px; font-weight: bold');
});
