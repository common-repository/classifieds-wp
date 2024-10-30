<?php if ( $contact = get_the_classified_contact_method() ) :
	wp_enqueue_script( 'wp-classified-manager-classified-contact' );
	?>
	<div class="classified_contact contact">
		<?php do_action( 'classified_contact_start', $contact ); ?>

		<input type="button" class="contact_button button" value="<?php esc_attr_e( 'Contact Information', 'classifieds-wp' ); ?>" />

		<div class="contact_details">
			<?php
				/**
				 * classified_manager_contact_details_email or classified_manager_contact_details_url hook
				 */
				do_action( 'classified_manager_contact_details_' . $contact->type, $contact );
			?>
		</div>
		<?php do_action( 'classified_contact_end', $contact ); ?>
	</div>
<?php endif; ?>
