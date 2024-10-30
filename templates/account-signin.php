<?php if ( is_user_logged_in() ) : ?>

	<fieldset>
		<label><?php _e( 'Your account', 'classifieds-wp' ); ?></label>
		<div class="field account-sign-in">
			<?php
				$user = wp_get_current_user();
				printf( __( 'You are currently signed in as <strong>%s</strong>.', 'classifieds-wp' ), $user->user_login );
			?>

			<a class="button" href="<?php echo apply_filters( 'submit_classified_form_logout_url', wp_logout_url( get_permalink() ) ); ?>"><?php _e( 'Sign out', 'classifieds-wp' ); ?></a>
		</div>
	</fieldset>

<?php else :

	$account_required             = classified_manager_user_requires_account();
	$registration_enabled         = classified_manager_enable_registration();
	$generate_username_from_email = classified_manager_generate_username_from_email();
	?>
	<fieldset>
		<label><?php _e( 'Have an account?', 'classifieds-wp' ); ?></label>
		<div class="field account-sign-in">
			<a class="button" href="<?php echo apply_filters( 'submit_classified_form_login_url', wp_login_url( get_permalink() ) ); ?>"><?php _e( 'Sign in', 'classifieds-wp' ); ?></a>

			<?php if ( $registration_enabled ) : ?>

				<?php printf( __( 'If you don&rsquo;t have an account you can %screate one below by entering your email address/username. Your account details will be confirmed via email.', 'classifieds-wp' ), $account_required ? '' : __( 'optionally', 'classifieds-wp' ) . ' ' ); ?>

			<?php elseif ( $account_required ) : ?>

				<?php echo apply_filters( 'submit_classified_form_login_required_message',  __('You must sign in to create a new listing.', 'classifieds-wp' ) ); ?>

			<?php endif; ?>
		</div>
	</fieldset>
	<?php if ( $registration_enabled ) : ?>
		<?php if ( ! $generate_username_from_email ) : ?>
			<fieldset>
				<label><?php _e( 'Username', 'classifieds-wp' ); ?> <?php echo apply_filters( 'submit_classified_form_required_label', ( ! $account_required ) ? ' <small>' . __( '(optional)', 'classifieds-wp' ) . '</small>' : '' ); ?></label>
				<div class="field">
					<input type="text" class="input-text" name="create_account_username" id="account_username" value="<?php echo empty( $_POST['create_account_username'] ) ? '' : esc_attr( sanitize_text_field( stripslashes( $_POST['create_account_username'] ) ) ); ?>" />
				</div>
			</fieldset>
		<?php endif; ?>
		<fieldset>
			<label><?php _e( 'Your email', 'classifieds-wp' ); ?> <?php echo apply_filters( 'submit_classified_form_required_label', ( ! $account_required ) ? ' <small>' . __( '(optional)', 'classifieds-wp' ) . '</small>' : '' ); ?></label>
			<div class="field">
				<input type="email" class="input-text" name="create_account_email" id="account_email" placeholder="<?php esc_attr_e( 'you@yourdomain.com', 'classifieds-wp' ); ?>" value="<?php echo empty( $_POST['create_account_email'] ) ? '' : esc_attr( sanitize_text_field( stripslashes( $_POST['create_account_email'] ) ) ); ?>" />
			</div>
		</fieldset>
		<?php do_action( 'classified_manager_register_form' ); ?>
	<?php endif; ?>

<?php endif; ?>
