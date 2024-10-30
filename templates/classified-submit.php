<?php
/**
 * Classified Submission Form
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $classified_manager;
?>
<form action="<?php echo esc_url( $action ); ?>" method="post" id="submit-classified-form" class="classified-manager-form" enctype="multipart/form-data">

	<?php if ( apply_filters( 'submit_classified_form_show_signin', true ) ) : ?>

		<?php get_classified_manager_template( 'account-signin.php' ); ?>

	<?php endif; ?>

	<?php if ( classified_manager_user_can_post_classified() || classified_manager_user_can_edit_classified( $classified_id ) ) : ?>

		<!-- Classified Information Fields -->
		<?php do_action( 'submit_classified_form_classified_fields_start' ); ?>

		<?php foreach ( $classified_fields as $key => $field ): ?>
			<fieldset class="fieldset-<?php esc_attr_e( $key ); ?>">
				<label for="<?php esc_attr_e( $key ); ?>"><?php echo $field['label'] . apply_filters( 'submit_classified_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'classifieds-wp' ) . '</small>', $field ); ?></label>
				<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
					<?php if ( 'wp-media-viewer' === $field['type'] ): ?>
						<?php wp_classified_manager_ui( $classified_id ); ?>
					<?php else: ?>
						<?php get_classified_manager_template( 'form-fields/' . $field['type'] . '-field.php', array( 'key' => $key, 'field' => $field ) ); ?>
					<?php endif; ?>

				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php do_action( 'submit_classified_form_classified_fields_end' ); ?>

		<p>
			<input type="hidden" name="classified_manager_form" value="<?php echo esc_attr( $form ); ?>" />
			<input type="hidden" name="classified_id" value="<?php echo esc_attr( $classified_id ); ?>" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
			<input type="submit" name="submit_classified" class="button" value="<?php esc_attr_e( $submit_button_text ); ?>" />
		</p>

	<?php else : ?>

		<?php do_action( 'submit_classified_form_disabled' ); ?>

	<?php endif; ?>
</form>
