<?php if ( ! is_tax( 'classified_listing_type' ) && empty( $classified_types ) ) : ?>
	<ul class="classified_types">
		<?php foreach ( get_classified_listing_types() as $type ) : ?>
			<li><label for="classified_type_<?php echo $type->slug; ?>" class="<?php echo sanitize_title( $type->name ); ?>"><input type="checkbox" name="filter_classified_type[]" value="<?php echo $type->slug; ?>" <?php checked( in_array( $type->slug, $selected_classified_types ), true ); ?> id="classified_type_<?php echo $type->slug; ?>" /> <?php echo $type->name; ?></label></li>
		<?php endforeach; ?>
	</ul>
	<input type="hidden" name="filter_classified_type[]" value="" />
<?php elseif ( $classified_types ) : ?>
	<?php foreach ( $classified_types as $classified_type ) : ?>
		<input type="hidden" name="filter_classified_type[]" value="<?php echo sanitize_title( $classified_type ); ?>" />
	<?php endforeach; ?>
<?php endif; ?>