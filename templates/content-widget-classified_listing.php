<li <?php classified_listing_class(); ?>>
	<a href="<?php the_classified_permalink(); ?>">
		<div class="classified-title">
			<h3><?php the_title(); ?></h3>
		</div>
		<ul class="meta">
			<li class="classified-location"><?php the_classified_location( false ); ?></li>
			<li class="classified-price"><?php the_classified_price(); ?></li>
			<li class="classified-type <?php echo get_the_classified_type() ? sanitize_title( get_the_classified_type()->slug ) : ''; ?>"><?php the_classified_type(); ?></li>
		</ul>
	</a>
</li>