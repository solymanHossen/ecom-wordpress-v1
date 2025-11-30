<?php
/**
 * 404 Error Page Template
 */
get_header();
?>

<div class="bg-gray-50 min-h-screen flex items-center justify-center py-12">
    <div class="container mx-auto px-4 text-center">
        <div class="max-w-lg mx-auto">
            <div class="text-9xl font-bold text-primary-600 mb-4">404</div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Page Not Found</h1>
            <p class="text-gray-600 mb-8">Sorry, the page you're looking for doesn't exist or has been moved.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?php echo home_url(); ?>" class="inline-flex items-center justify-center gap-2 bg-primary-600 text-white px-8 py-4 rounded-xl font-semibold hover:bg-primary-700 transition">
                    <i data-lucide="home" class="w-5 h-5"></i>
                    Go Home
                </a>
                <a href="<?php echo home_url('/shop/'); ?>" class="inline-flex items-center justify-center gap-2 bg-white border-2 border-gray-200 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:border-gray-300 transition">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    Browse Shop
                </a>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
