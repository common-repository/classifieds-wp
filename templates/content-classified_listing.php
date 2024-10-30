<?php global $post, $wpcm_wrap; ?>

<?php // Wrap every n number of classifieds. ?>

<?php if ( ! empty( $per_row ) && 0 === ( $wpcm_wrap % $per_row ) ): ?>

	<?php if ( $wpcm_wrap ): ?>

		</div>

		<?php $wpcm_wrap = 0; ?>

	<?php endif; ?>

	<div class="classified-columns-<?php echo esc_attr( $per_row ); ?>">

<?php endif; ?>

<article <?php classified_listing_class(); ?> data-longitude="<?php echo esc_attr( $post->geolocation_lat ); ?>" data-latitude="<?php echo esc_attr( $post->geolocation_long ); ?>">
	<a href="<?php esc_url( the_classified_permalink() ); ?>">
		<?php the_classified_featured_image( $size = 'full', $link = false ); ?>
		<div class="classified-title">
			<h3><?php the_title(); ?></h3>
		</div>
		<div class="classified-location">
			<?php the_classified_location( false ); ?>
		</div>
		<div class="classified-listing-meta">
			<ul class="meta">
				<?php do_action( 'classified_listing_meta_start' ); ?>

				<li class="classified-type <?php echo get_the_classified_type() ? sanitize_title( get_the_classified_type()->slug ) : ''; ?>"><?php the_classified_type(); ?></li>
				<li class="date"><date><?php printf( __( '%s ago', 'classifieds-wp' ), human_time_diff( get_post_time( 'U' ), current_time( 'timestamp' ) ) ); ?></date></li>

				<?php do_action( 'classified_listing_meta_end' ); ?>
			</ul>
		</div>
	</a>
</article>

<?php $wpcm_wrap++; ?>
