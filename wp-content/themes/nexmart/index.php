<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 *
 * @package NexMart
 */

get_header();
?>

<div class="container mx-auto px-4 py-8">
    
    <?php if ( is_home() && ! is_front_page() ) : ?>
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900"><?php single_post_title(); ?></h1>
        </header>
    <?php endif; ?>

    <?php if ( have_posts() ) : ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ( have_posts() ) : the_post(); ?>
                <article <?php post_class( 'bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-xl transition-all duration-300' ); ?>>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a href="<?php the_permalink(); ?>" class="block aspect-video overflow-hidden bg-gray-100">
                            <?php the_post_thumbnail( 'medium_large', array( 'class' => 'w-full h-full object-cover hover:scale-105 transition-transform duration-500' ) ); ?>
                        </a>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <?php 
                            $categories = get_the_category();
                            if ( $categories ) : ?>
                                <span class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full text-xs font-bold">
                                    <?php echo esc_html( $categories[0]->name ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="text-gray-400 text-xs"><?php echo get_the_date(); ?></span>
                        </div>
                        
                        <h2 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 hover:text-indigo-600 transition-colors">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        
                        <p class="text-gray-500 text-sm line-clamp-3 mb-4">
                            <?php echo wp_trim_words( get_the_excerpt(), 20 ); ?>
                        </p>
                        
                        <a href="<?php the_permalink(); ?>" class="inline-flex items-center gap-2 text-indigo-600 font-medium text-sm hover:gap-3 transition-all">
                            Read More <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="mt-12 flex justify-center">
            <?php
            the_posts_pagination( array(
                'mid_size'  => 2,
                'prev_text' => '<i data-lucide="chevron-left" class="w-4 h-4"></i>',
                'next_text' => '<i data-lucide="chevron-right" class="w-4 h-4"></i>',
                'class'     => 'flex items-center gap-2',
            ) );
            ?>
        </div>

    <?php else : ?>
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="file-search" class="w-12 h-12 text-gray-400"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">No Posts Found</h2>
            <p class="text-gray-500 mb-6">It looks like there's nothing here yet.</p>
            <a href="<?php echo home_url(); ?>" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition-colors">
                <i data-lucide="home" class="w-4 h-4"></i> Back to Home
            </a>
        </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
