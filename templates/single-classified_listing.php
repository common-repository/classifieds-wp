<?php get_header(); ?>

<div id="classified-manager-single" <?php classified_listing_class('classified-manager-single'); ?>">
	<main id="classified-manager-main" class="classified-manager-main">
		<?php
		// Start the loop.
		while ( have_posts() ) : the_post();

			// Include the single post content template.
			get_classified_manager_template_part( 'content-single', 'classified_listing' );

			// End of the loop.
		endwhile;
		?>
	</main>

	<?php get_classified_manager_sidebar(); ?>

</div><!-- .content-area -->

<?php get_footer(); ?>
