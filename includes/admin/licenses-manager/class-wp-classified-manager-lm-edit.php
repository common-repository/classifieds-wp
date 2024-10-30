<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The license manager UI class.
 */
class WP_Classified_Manager_License_Manager_Edit {

	/**
	 * The name for the Add-on being edited.
	 *
	 * @var string
	 */
	private $addon;

	/**
	 * The Add-on license.
	 *
	 * @var object
	 */
	private $license;

	/**
	 * Initialize the license manager UI.
	 */
	public function __construct() {

		// Check if we get an Add-on to edit.
		if ( ! empty( $_REQUEST['addon'] ) ) {
			$addon = wp_strip_all_tags( $_REQUEST['addon'] );

			$this->license = wpcm_license_manager()->get_license_for( $addon );
			$this->addon   = $addon;
		}

		// Register the licenses edit page.
		$this->register_settings_form();
	}

	/**
	 * Register the license edit page.
	 */
	private function register_settings_form() {

		$page        = WP_Classified_Manager_License_Manager_Menu::get_page( 'edit' );
		$options_key = WP_Classified_Manager_License_Manager::get_options_key();

		register_setting( $options_key, $options_key, array( $this, 'validate_options' ) );

		// API Key settings.
		add_settings_section( 'api_key', sprintf( '<em>%s</em>', $this->addon ), array( $this, 'wc_am_api_key_text' ), $page );
		add_settings_field( 'status', __( 'License Status', 'classifieds-wp' ), array( $this, 'wc_am_api_key_status_field' ), $page, 'api_key' );
		add_settings_field( 'version', __( 'Version', 'classifieds-wp' ), array( $this, 'wc_am_api_key_version_field' ), $page, 'api_key' );
		add_settings_field( 'domain', __( 'Domain', 'classifieds-wp' ), array( $this, 'wc_am_api_key_domain_field' ), $page, 'api_key' );
		add_settings_field( 'api_key', __( 'License Key', 'classifieds-wp' ), array( $this, 'wc_am_api_key_field' ), $page, 'api_key' );
		add_settings_field( 'email', __( 'License email', 'classifieds-wp' ), array( $this, 'wc_am_api_email_field' ), $page, 'api_key' );
	}

	/**
	 * Display the edit license page.
	 */
	public function edit_license() {

		settings_errors();

		$settings_tabs = array( 'wpcm-tab-activation' => __( 'Details', 'classifieds-wp' )  );
		$current_tab   = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'wpcm-tab-activation';
		$tab           = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'wpcm-tab-activation';

		?><div class='wrap'>

			<?php screen_icon(); ?>

			<h2><?php _e( 'Manage Add-on License', 'classifieds-wp' ); ?></h2>

			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $settings_tabs as $tab_page => $tab_name ) {
					$active_tab = $current_tab == $tab_page ? 'nav-tab-active' : '';
					echo '<a class="nav-tab ' . $active_tab . '" href="' . esc_url( add_query_arg( 'tab', $tab_page ) ) . '">' . $tab_name . '</a>';
				}
				?>
			</h2>
			<form action='options.php' method='post'>
				<div class="main">
				<?php
					settings_fields( WP_Classified_Manager_License_Manager::get_options_key() );

					do_settings_sections( WP_Classified_Manager_License_Manager_Menu::get_page( 'edit' ) );

					if ( 'active' === $this->license->status ) {
						$action = __( 'Deactivate', 'classifieds-wp' );

						echo "<input type='hidden' name='deactivate' value='deactivate'>";
					} else {
						$action = __( 'Activate', 'classifieds-wp' );
					}

					submit_button( $action );
				?>
				</div>
			</form>
		</div><?php
	}

	/**
	 * Provides text for api key section.
	 */
	public function wc_am_api_key_text() {}

	/**
	 * Returns the API License Key status.
	 *
	 * @todo: maybe provide more info like number of activations left.
	 */
	public function wc_am_api_key_status_field() {

		self::add_inline_css();

		echo 'active' === $this->license->status ? '<div class="license-status active"></div>' . __( 'Activated', 'classifieds-wp' ) : '<div class="license-status deactivated"></div>' . __( 'Deactivated', 'classifieds-wp' );
	}

	/**
	 * Returns the API License Key version.
	 *
	 * @todo: maybe provide more info like number of activations left.
	 */
	public function wc_am_api_key_version_field() {
		echo $this->license->version . $this->license->download_update_link();
	}


	/**
	 * Returns the API License Key domain.
	 */
	public function wc_am_api_key_domain_field() {
		echo $this->license->domain && 'active' === $this->license->status ? sprintf( '<a href="%1$s">%1$s</a>', $this->license->domain ) : '-';
	}

	/**
	 * Returns API License text field.
	 */
	public function wc_am_api_key_field() {

		$option_key = $this->license->option_key . "[api_key]";

		echo "<input id='api_key' name='" . esc_attr( $option_key ) . "' size='25' type='text' value='" . esc_attr( $this->license->api_key ) . "' class='regular-text' />";

		if ( $this->license->api_key ) {
			echo "<span class='dashicons dashicons-yes' style='color: #66ab03;'></span>";
		} else {
			echo "<span class='dashicons dashicons-no' style='color: #ca336c;'></span>";
		}
	}

	/**
	 * Returns API License email text field.
	 */
	public function wc_am_api_email_field() {

		$option_key = $this->license->option_key . "[email]";

		echo "<input id='activation_email' name='" . esc_attr( $option_key ) . "' size='25' type='text' value='" . esc_attr( $this->license->email ) . "' class='regular-text' />";

		if ( $this->license->email ) {
			echo "<span class='dashicons dashicons-yes' style='color: #66ab03;'></span>";
		} else {
			echo "<span class='dashicons dashicons-no' style='color: #ca336c;'></span>";
		}
	}

	/**
	 * Sanitizes and validates all input and output for Dashboard.
	 */
	public function validate_options( $input ) {

		$addon = sanitize_text_field( key( $input ) );

		$api_key   = sanitize_text_field( $input[ $addon ]['api_key'] );
		$api_email = sanitize_text_field( $input[ $addon ]['email'] );

		$options       = wpcm_license_manager()->get_licenses_options();
		$this->license = wpcm_license_manager()->get_license_for( $addon );

		if ( ! $api_key || ! $api_email ) {
			add_settings_error( 'api_key_check_text', 'api_key_check_error', __( 'Please fill in all the required fields.', 'classifieds-wp' ), 'error' );
			return $options;
		}

		if ( empty( $options[ $addon ] ) ) {
			add_settings_error( 'api_key_check_text', 'api_email_error', __( 'Add-on not found.', 'classifieds-wp' ), 'error' );
			return $options;
		}

		$deactivate = ! empty( $_REQUEST['deactivate'] );

		$current_status    = $options[ $addon ]['status'];
		$current_api_key   = $options[ $addon ]['api_key'];
		$current_api_email = $options[ $addon ]['email'];

		if ( ! $deactivate && $current_status === 'active' && $current_api_key === $api_key && $api_email === $current_api_email  ) {
			add_settings_error( 'api_key_check_text', 'api_key_check_error', __( 'License is already activated.', 'classifieds-wp' ), 'updated' );
			return $options;
		}

		$options[ $addon ]['api_key'] = $api_key;
		$options[ $addon ]['email']   = $api_email;
		$options[ $addon ]['status']  = 'inactive';

		$args = array(
			'email'       => $api_email,
			'licence_key' => $api_key,
		);

		if ( $deactivate ) {

			// Deactivates license key activation.
			$activate_results = $this->license->deactivate( $args );

		} else {

			/**
			 * If this is a new key, and an existing key already exists in the database,
			 * deactivate the existing key before activating the new key.
			 */
			if ( $current_api_key && $current_api_key !== $api_key ) {
				$this->replace_license_key( $current_api_key );
			}

			$activate_results = $this->license->activate( $args );

		}

		if ( empty( $activate_results ) || isset( $activate_results['code'] ) ) {

			if ( empty( $activate_results )  ) {

				add_settings_error( 'api_key_check_text', 'api_key_check_error', __( 'Connection failed to the License Key API server. Try again later.', 'classifieds-wp' ), 'error' );

			} else {

				$defaults = array(
					'additional info' => '',
					'code'            => '-1',
					'error'           => '',
				);
				$activate_results = wp_parse_args( $activate_results, $defaults );

				switch ( $activate_results['code'] ) {

					case '100':
						add_settings_error( 'api_email_text', 'api_email_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '101':
						add_settings_error( 'api_key_text', 'api_key_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '102':
						add_settings_error( 'api_key_purchase_incomplete_text', 'api_key_purchase_incomplete_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '103':
						add_settings_error( 'api_key_exceeded_text', 'api_key_exceeded_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '104':
						add_settings_error( 'api_key_not_activated_text', 'api_key_not_activated_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '105':
						add_settings_error( 'api_key_invalid_text', 'api_key_invalid_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					case '106':
						add_settings_error( 'sub_not_active_text', 'sub_not_active_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
						break;

					default:
						add_settings_error( 'unknown_text', 'unknown_error', 'There was an unknown error. Please try again.', 'error' );

				}

			}


		} else {

			if ( ! empty( $activate_results['deactivated'] ) ) {

				add_settings_error( 'wc_am_deactivate_text', 'deactivate_msg', __( 'Plugin license deactivated. ', 'classifieds-wp' ) . "{$activate_results['activations_remaining']}.", 'updated' );

			} else {

				$options[ $addon ]['status'] = 'active';

				add_settings_error( 'activate_text', 'activate_msg', __( 'Add-on Activated! ', 'classifieds-wp' ) . "{$activate_results['message']}.", 'updated' );

			}

		}

		return $options;
	}

	/**
	 * Add some simple inline CSS.
	 */
	public static function add_inline_css() {
?>
		<style type="text/css">
			.license-status {
				width: 10px;
			    height: 10px;
			    display: inline-block;
			    margin-right: 5px;
			    border-radius: 11px;
		    }
		    .license-status.active {
			    background-color: #16D013;
		    }
		    .license-status.deactivated {
			    background-color: #CA3333;
		    }
		</style>
<?php
	}


	/**
	 * Helpers.
	 */


	/**
	 * Deactivate the current license key before activating the new license key.
	 */
	public function replace_license_key( $current_api_key ) {

		$args = array(
			'email'       => $this->license->email,
			'licence_key' => $current_api_key,
		);

		$reset = $this->license->deactivate( $args );

		if ( $reset ) {
			return true;
		}

		return add_settings_error( 'not_deactivated_text', 'not_deactivated_error', __( 'The license could not be deactivated. Use the License Deactivation tab to manually deactivate the license before activating a new license.', 'classifieds-wp' ), 'updated' );
	}

}
