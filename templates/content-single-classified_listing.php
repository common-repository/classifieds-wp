<?php global $post; ?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h3 class="entry-title"><?php the_title(); ?></h3>
	</header><!-- .entry-header -->

	<div class="single_classified_listing" itemscope itemtype="http://schema.org/ClassifiedPosting">
		<meta itemprop="title" content="<?php echo esc_attr( $post->post_title ); ?>" />

		<?php if ( get_option( 'classified_manager_hide_expired_content', 1 ) && 'expired' === $post->post_status ) : ?>
			<div class="classified-manager-info"><?php _e( 'This listing has expired.', 'classifieds-wp' ); ?></div>
		<?php else : ?>
			<?php
				/**
				 * single_classified_listing_start hook
				 *
				 * @hooked classified_listing_header_display - 20
				 * @hooked classified_listing_meta_display - 30
				 */
				do_action( 'single_classified_listing_start' );
			?>

			<div class="classified_description" itemprop="description">
				<?php echo apply_filters( 'the_classified_description', get_the_content() ); ?>
			</div>

			<?php if ( users_can_contact() ) : ?>
				<?php get_classified_manager_template( 'classified-contact.php' ); ?>
			<?php endif; ?>

			<?php
				/**
				 * single_classified_listing_end hook
				 */
				do_action( 'single_classified_listing_end' );
			?>
		<?php endif; ?>
	</div>
</article>