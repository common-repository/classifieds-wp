<?php
/**
 * Single view Company information box
 *
 * Hooked into single_classified_listing_start priority 30
 */

global $post;
?>

<?php do_action( 'single_classified_listing_header_start' ); ?>

<?php if ( is_classified_unavailable() ) : ?>
	<div class="classified-manager-error">
		<ul>
			<li class="listing-sold"><?php _e( 'This listing is no longer available.', 'classifieds-wp' ); ?></li>
		</ul>
	</div>
<?php elseif ( ! users_can_contact() && 'preview' !== $post->post_status ) : ?>
	<div class="classified-manager-error">
		<ul>
			<li class="listing-expired"><?php _e( 'This is listing has expired.', 'classifieds-wp' ); ?></li>
		</ul>
	</div>
<?php endif; ?>

<?php do_action( 'single_classified_listing_header_end' ); ?>