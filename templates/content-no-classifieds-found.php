<?php if ( defined( 'DOING_AJAX' ) ) : ?>
	<li class="no_classified_listings_found"><?php _e( 'There are no listings matching your search.', 'classifieds-wp' ); ?></li>
<?php else : ?>
	<p class="no_classified_listings_found"><?php _e( 'There are currently no vacancies.', 'classifieds-wp' ); ?></p>
<?php endif; ?>