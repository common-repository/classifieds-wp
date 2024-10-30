<?php global $classified_manager; ?>

<a href="<?php the_permalink(); ?>">
	<?php if ( has_post_thumbnail() ) : ?>
		<?php the_classified_featured_image(); ?>
	<?php endif; ?>

	<div class="classified_summary_content">

		<h3><?php the_title(); ?></h3>
		<?php the_classified_price('<span class="classified-price">', '</span>'); ?>
		<div class="classified-listing-meta">
			<p class="meta"><?php the_classified_location( false ); ?> &mdash; <?php the_classified_type() ?></p>
		</div>
	</div>
</a>