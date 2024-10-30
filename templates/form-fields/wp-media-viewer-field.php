<div id="<?php echo esc_attr( $atts['id'] ); ?>" class="classified-manager-media-viewer <?php echo esc_attr( $atts['class'] ); ?>">
	<?php if ( $atts['title'] ): ?>
		<legend><?php echo $atts['title']; ?></legend>
	<?php endif; ?>

	<div id="<?php echo esc_attr( $atts['id'] ); ?>" class="media-placeholder">

		<div class="no-media" style="display: <?php echo $no_media ? 'block' : 'none'; ?>">
			<?php echo $atts['no_media_text']; ?>
		</div>

		<?php do_action( 'classified_listing_before_media', $atts ); ?>

		<div class="media-attachments classified_images_gallery">
			<?php classified_manager_mv_output_attachments( $atts['attachment_ids'], 'thumbnail', $atts['attachment_params'] ); ?>
		</div>

		<?php do_action( 'classified_manager_after_media', $atts ); ?>

	</div>

	<div class="classified-manager-media-viewer-actions">
		<span class="classified-manager-media-viewer-spinner"></span><input type="button" class="button small upload_button" data-group-id="<?php echo esc_attr( $atts['id'] ); ?>" data-upload-text="<?php echo esc_attr( $atts['upload_text'] ); ?>" data-manage-text="<?php echo esc_attr( $atts['manage_text'] ); ?>" value="<?php echo esc_attr( $atts['button_text'] ); ?>">
		<div class="classified-manager-media-viewer-info">
			<?php $size = get_option( 'classified_manager_max_image_size' ); ?>
			<small><?php echo sprintf( __( 'Max: %1$s / Size: %2$s', 'classifieds-wp' ), get_option( 'classified_manager_num_images' ), size_format( $size * 1024 ) ); ?></small>
		</div>
	</div>
</div>