<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'font-sans antialiased text-gray-600 bg-white' ); ?>>
<?php wp_body_open(); ?>

<!-- Top Bar -->
<div class="bg-indigo-900 text-white text-xs py-2 px-4 hidden md:flex justify-between items-center">
    <div class="flex gap-4">
        <span>📞 +1 (800) 123-4567</span>
        <span>📧 support@market.ai</span>
    </div>
    <div class="flex gap-4">
        <a href="<?php echo home_url('/vendor-dashboard'); ?>" class="hover:text-indigo-200 transition">Sell on Marketplace</a>
        <span>|</span>
        <a href="#" class="hover:text-indigo-200 transition">Track Order</a>
    </div>
</div>

<!-- Main Header -->
<header id="main-header" class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100 transition-all">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4 md:gap-8 justify-between">
            
            <!-- Logo -->
            <a href="<?php echo home_url('/'); ?>" class="flex items-center gap-2 group">
                <div class="bg-indigo-600 p-2 rounded-lg group-hover:scale-110 transition-transform">
                    <i data-lucide="zap" class="text-white w-6 h-6"></i>
                </div>
                <span class="text-2xl font-bold tracking-tight text-gray-900">Nex<span class="text-indigo-600">Mart</span></span>
            </a>

            <!-- AI Search Bar -->
            <div class="hidden md:flex flex-1 max-w-2xl relative group">
                <form role="search" method="get" class="w-full flex relative" action="<?php echo home_url( '/' ); ?>">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" class="text-gray-400 w-5 h-5"></i>
                    </div>
                    <input 
                        type="search" 
                        name="s" 
                        class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent block pl-10 p-3 transition-shadow shadow-sm group-hover:shadow-md outline-none" 
                        placeholder="Ask AI to find products (e.g., 'Best headphones for gym')..." 
                        value="<?php echo get_search_query(); ?>"
                    />
                    <div class="absolute inset-y-0 right-2 flex items-center">
                        <button type="submit" class="bg-indigo-100 text-indigo-600 p-1.5 rounded-lg hover:bg-indigo-200 transition">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 md:gap-4">
                <button class="p-2 hover:bg-gray-100 rounded-full relative transition">
                    <i data-lucide="bell" class="text-gray-600 w-6 h-6"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
                </button>
                
                <button class="cart-btn p-2 hover:bg-gray-100 rounded-full relative transition group">
                    <i data-lucide="shopping-cart" class="text-gray-600 w-6 h-6 group-hover:text-indigo-600"></i>
                    <span class="cart-count absolute top-0 right-0 bg-indigo-600 text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full font-bold hidden">0</span>
                </button>

                <button id="mobile-menu-btn" class="p-2 hover:bg-gray-100 rounded-full md:hidden">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>

                <?php if ( is_user_logged_in() ) : ?>
                    <a href="<?php echo home_url('/my-account'); ?>" class="hidden md:flex items-center gap-2 bg-gray-100 text-gray-900 px-4 py-2 rounded-xl text-sm font-bold hover:bg-gray-200 transition-colors">
                        <i data-lucide="user" class="w-4 h-4"></i> My Account
                    </a>
                <?php else : ?>
                    <a href="<?php echo wp_login_url(); ?>" class="hidden md:flex items-center gap-2 bg-gray-900 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-gray-800 transition-colors">
                        <i data-lucide="user" class="w-4 h-4"></i> Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Search -->
        <div class="md:hidden mt-4 pb-2">
            <form role="search" method="get" class="relative" action="<?php echo home_url( '/' ); ?>">
                <input type="search" name="s" placeholder="Search..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 p-3 outline-none" />
                <div class="absolute left-3 top-3.5 text-gray-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Mega Menu -->
    <div class="border-t border-gray-100 bg-white hidden md:block">
        <div class="container mx-auto px-4">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'flex items-center gap-8 text-sm font-medium text-gray-600 overflow-x-auto py-3 no-scrollbar',
                'fallback_cb'    => false, // Fallback functionality disabled for cleaner code
            ) );
            ?>
        </div>
    </div>
</header>
