<?php
/**
 * Single view Classified meta box
 *
 * Hooked into single_classified_listing_start priority 20
 */
global $post;
?>

<div class="classified-listing-meta" itemscope itemtype="http://data-vocabulary.org/Organization">

<?php do_action( 'single_classified_listing_meta_before' ); ?>

<ul class="meta">
	<?php do_action( 'single_classified_listing_meta_start' ); ?>

	<li class="classified-type <?php echo get_the_classified_type() ? sanitize_title( get_the_classified_type()->slug ) : ''; ?>" itemprop="classifiedType"><?php the_classified_type(); ?></li>

	<li class="location" itemprop="classifiedLocation"><?php the_classified_location(); ?></li>

	<li class="date-posted" itemprop="datePosted"><date><?php printf( human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) ); ?></date></li>

	<?php if ( $website = get_the_classified_website() ) : ?>
		<li class="classified-website" itemprop="classifiedWebsite"><a class="website" href="<?php echo esc_url( $website ); ?>" itemprop="url" target="_blank" rel="nofollow"><?php _e( 'Website', 'classifieds-wp' ); ?></a>
	<?php endif; ?>

	<?php do_action( 'single_classified_listing_meta_end' ); ?>
</ul>

<?php do_action( 'single_classified_listing_meta_after' ); ?>

</div>