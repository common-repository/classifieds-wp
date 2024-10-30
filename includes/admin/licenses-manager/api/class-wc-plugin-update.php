<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Todd Lahman LLC Updater - Single Updater Class
 *
 * Modified by 'Classifieds WP Manager' to work in multi-site.
 *
 * References: https://gist.github.com/pippinsplugins/392d34955505b73e08e6
 *             https://easydigitaldownloads.com/forums/topic/automatic-update-to-extentions-not-working-in-multisite
 *
 * @package   Update API Manager/Update Handler
 * @author    Todd Lahman LLC
 * @copyright Copyright (c) Todd Lahman LLC
 *
 */

class WP_Classified_Manager_WOO_Update_API_Check {

	/**
	 * API data.
	 */

	private $upgrade_url;       // URL to access the Update API Manager.
	private $plugin_name;
	private $product_id;        // Software Title
	private $api_key;           // API License Key
	private $activation_email;  // License Email
	private $renew_license_url; // URL to renew a license
	private $instance;          // Instance ID (unique to each blog activation)
	private $domain;            // blog domain name
	private $software_version;
	private $plugin_or_theme;   // 'theme' or 'plugin'
	private $extra;             // Used to send any extra information.

	private $update_cache_key;  // Key where update information is cached.

	/**
	 * @var The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Ensures only one instance is loaded or can be loaded.
	 */
	public static function instance( $upgrade_url, $renew_url, $data, $plugin_or_theme = 'plugin' ) {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $upgrade_url, $renew_url, $data, $plugin_or_theme );
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct( $upgrade_url, $renew_url, $data, $plugin_or_theme = 'plugin' ) {

		extract( $data );

		if ( 'active' !== $status ) {
			return;
		}

		// API data.
		$this->upgrade_url       = $upgrade_url;
		$this->plugin_name       = $plugin;
		$this->product_id        = $id;
		$this->api_key           = $api_key;
		$this->activation_email  = $email;
		$this->renew_license_url = $renew_url;
		$this->instance          = $instance;
		$this->domain            = $domain;
		$this->software_version  = $version;
		$this->extra             = $extra;

		$this->update_cache_key = md5( 'wpcm_plugin_' . sanitize_key( $this->plugin_name ) . '_update_info' );

		// Slug should be the same as the plugin/theme directory name.
		if (  strpos( $this->plugin_name, '.php' ) !== 0 ) {
			$this->slug = dirname( $this->plugin_name );
		} else {
			$this->slug = $this->plugin_name;
		}

		/**
		 * Flag for plugin or theme updates.
		 */
		$this->plugin_or_theme = $plugin_or_theme; // 'theme' or 'plugin'

		// Uses the flag above to determine if this is a plugin or a theme update request.
		if ( $this->plugin_or_theme === 'plugin' ) {

			/**
			 * Plugin Updates
			 */

			// Check For Plugin Updates.
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_check' ) );

			// Check For Plugin Information to display on the update details page.
			add_filter( 'plugins_api', array( $this, 'request' ), 10, 3 );

			if ( is_multisite() && ! is_main_site() ) {

				// Multi-site Only - Displays the update notification on the plugin row.
				add_action( 'after_plugin_row_' . $this->plugin_name, array( $this, 'show_update_notification' ), 10, 2 );

				// Force the update API to trigger the 'plugins_api' filter to display the version details info.
	 			add_action( 'admin_init', array( $this, 'show_info' ) );

				// Displays the notification count icon on the plugins menu.
	 			add_action( 'admin_menu', array( $this, 'menu_count' ) );
			}

		} else if ( $this->plugin_or_theme == 'theme' ) {

			/**
			 * Theme Updates
			 */

			// Check For Theme Updates.
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'update_check' ) );

			// Check For Theme Information to display on the update details page.
			add_filter( 'themes_api', array( $this, 'request' ), 10, 3 );

		}

	}

	/**
	 * Upgrade API URL.
	 */
	private function create_upgrade_api_url( $args ) {
		$upgrade_url = add_query_arg( 'wc-api', 'upgrade-api', $this->upgrade_url );
		return $upgrade_url . '&' . http_build_query( $args );
	}

	/**
	 * Check for updates against the remote server.
	 */
	public function update_check( $transient ) {
		global $pagenow;

        if ( 'plugins.php' === $pagenow && is_multisite() ) {
            return $transient;
        }

		if ( ! is_object( $transient ) ) {
			$transient = new stdClass;
		}

		if ( ! empty( $transient->response ) && ! empty( $transient->response[ $this->plugin_name ] ) ) {
			return $transient;
		}

		// Check for a plugin update.
		$response = $this->plugin_information( 'pluginupdatecheck' );

		// Displays an admin error message in the WordPress dashboard.
		$this->check_response_for_errors( $response );

		// Set version variables.
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {

			// New plugin version from the API.
			$new_ver = (string) $response->new_version;

			// Current installed plugin version.
			$curr_ver = (string) $this->software_version;

		}

		// If there is a new version, modify the transient to reflect an update is available.
		if ( isset( $new_ver ) && isset( $curr_ver ) ) {

			if ( $response !== false && version_compare( $new_ver, $curr_ver, '>' ) ) {

				if ( $this->plugin_or_theme === 'plugin' ) {

					$transient->response[ $this->plugin_name ] = $response;

				} else if ( $this->plugin_or_theme === 'theme' ) {

					$transient->response[ $this->plugin_name ]['new_version'] = $response->new_version;
					$transient->response[ $this->plugin_name ]['url']         = $response->url;
					$transient->response[ $this->plugin_name ]['package']     = $response->package;

				}

                $transient->last_checked = time();
                $transient->checked[ $this->plugin_name ] = $this->software_version;

				// Cache update information for 5 minutes (min expiry time for download link).
				set_transient( $this->update_cache_key, $response, 60 * 5 );
			}

		}
		return $transient;
	}

	/**
	 * Sends and receives data to and from the server API.
	 */
	public function plugin_information( $request, $args = array()) {

		$defaults = array(
			'plugin_name'      => $this->plugin_name,
			'version'          => $this->software_version,
			'product_id'       => $this->product_id,
			'api_key'          => $this->api_key,
			'activation_email' => $this->activation_email,
			'instance'         => $this->instance,
			'domain'           => $this->domain,
			'software_version' => $this->software_version,
			'extra'            => $this->extra,
			'disable_download' => is_multisite() && ! is_main_site(),
		);
		$args = wp_parse_args( $args, $defaults );

		$args['request'] = $request;

		$target_url = esc_url_raw( $this->create_upgrade_api_url( $args ) );
		$request    = wp_safe_remote_get( $target_url );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			return false;
		}

		$response = unserialize( wp_remote_retrieve_body( $request ) );

		/**
		 * For debugging errors from the API
		 * For errors like: unserialize(): Error at offset 0 of 170 bytes
		 * Comment out $response above first
		 */
		 // $response = wp_remote_retrieve_body( $request );
		 //print_r($response); exit;

		if ( is_object( $response ) ) {

			// Disable WP updates for the plugin since they will usually fail on multi-site.
			if ( ! empty( $args['disable_download'] ) && isset( $response->download_link ) ) {
				unset( $response->download_link );
			}

			return $response;
		} else {
			return false;
		}

	}

	/**
	 * Generic request helper.
	 */
	public function request( $false, $action, $args ) {

		// Is this a plugin or a theme?
		if ( $this->plugin_or_theme === 'plugin' ) {

			$version = get_site_transient( 'update_plugins' );

		} else if ( $this->plugin_or_theme == 'theme' ) {

			$version = get_site_transient( 'update_themes' );

		}

		// Check if this plugins API is about this plugin.
		if ( isset( $args->slug ) ) {

			if ( $args->slug !== $this->slug ) {
				return $false;
			}

		} else {
			return $false;
		}

		$response = $this->plugin_information( 'plugininformation' );

		// If everything is okay return the $response
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			return $response;
		}

	}

	/**
	 * Show update notification row.
	 * Needed for multi-site sub-sites, because WP won't tell you otherwise!
	 */
	public function show_update_notification( $file, $plugin ) {

       if ( ! current_user_can( 'update_plugins' ) || ! is_multisite() ) {
            return;
        }

		if ( $this->plugin_name !== $file ) {
			return;
		}

		// Cache API call, to prevent requesting it over and over.
		$update_cache = get_site_transient( 'update_plugins' );

   		if ( ! is_object( $update_cache ) || empty( $update_cache->response ) || empty( $update_cache->response[ $this->plugin_name ] ) ) {

			$cache_key = md5( 'wpcm_plugin_' .sanitize_key( $this->plugin_name ) . '_version_info' );
			$response  = get_transient( $cache_key );

            if ( false === $response ) {

				// Check for a plugin update.
				$response = $this->plugin_information( 'pluginupdatecheck', array( 'disable_download' => false ) );

                set_transient( $cache_key, $response, 3600 );
            }

            if ( ! is_object( $response ) ) {
                return;
            }

            if ( version_compare( $this->software_version, $response->new_version, '<' ) ) {

                $update_cache->response[ $this->plugin_name ] = $response;

            }

        } else {

            $response = $update_cache->response[ $this->plugin_name ];

        }

		if ( ! empty( $update_cache->response[ $this->plugin_name ] ) && version_compare( $this->software_version, $response->new_version, '<' ) ) {

			 // Build a plugin list row, with update notification.
            $wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

			$active_class = is_plugin_active( $file ) ? ' active' : '';

			echo '<tr class="plugin-update-tr' . $active_class . '" id="' . esc_attr( $response->slug . '-update' ) . '" data-slug="' . esc_attr( $response->slug ) . '" data-plugin="' . esc_attr( $file ) . '"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange"><div class="update-message">';

            $details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&wpcm_action=view_info&section=changelog&plugin=' . $this->slug . '&slug=' . $this->slug . '&TB_iframe=true' );

            if ( empty( $response->package ) ) {

                printf(
                    __( 'There is a new version of <em>%1$s</em> available. <a target="_blank" class="thickbox" href="%2$s">View version %3$s details</a>.', 'edd' ),
                    esc_html( $this->product_id ),
                    esc_url( $details_url ),
                    esc_html( $response->version )
                );

            } else {

				printf( __( 'There is a new version of <em>%1$s</em> available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>. <em>Automatic update is unavailable for this plugin.</em> Please <a href="%5$s">download</a> and install new version manually.', 'classifieds-wp' ),
					$this->product_id,
					esc_url( $details_url ),
					esc_attr( $this->product_id ),
					$response->new_version,
					esc_url( $response->package )
				);

            }

          	echo '</div></td></tr>';

			// Cache update information for 5 minutes (min expiry time for download link).
			set_transient( $this->update_cache_key, $response, 60 * 5 );

			// Add the missing CSS for the plugin update.

			?><script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.plugin-update-tr.active').prev().addClass('update');
				});
			</script><?php
		}

	}

	/**
	 * Displays the plugin version details information.
	 */
    public function show_info() {

        if ( empty( $_REQUEST['wpcm_action'] ) || 'view_info' !== $_REQUEST['wpcm_action'] ) {
            return;
        }

        if ( empty( $_REQUEST['plugin'] ) || empty( $_REQUEST['slug'] ) ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'You do not have permission to install plugin updates', 'classifieds-wp' ), __( 'Error', 'edd' ), array( 'response' => 403 ) );
        }

        if ( isset( $_REQUEST['tab'] ) && 'plugin-information' == $_REQUEST['tab'] ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // Need for install_plugin_information().

            wp_enqueue_style( 'plugin-install' );

            global $tab, $body_id;
            $body_id = $tab = 'plugin-information';

			set_current_screen();

            install_plugin_information();

            exit;
        }
    }

	/**
	 * Outputs the update count icon information on the plugins menu.
	 */
    public function menu_count() {
		global $menu;

		if ( ! is_multisite() || ! current_user_can('update_plugins') || ! get_transient( $this->update_cache_key ) ) {
			return;
		}

		$update_data = wp_get_update_data();
		$update_data['counts']['plugins'] = apply_filters( 'classified_manager_counts_plugins', 1 );

		if ( ! $update_data['counts']['plugins'] ) {
			return;
		}

		$count = "<span class='update-plugins count-{$update_data['counts']['plugins']}'><span class='plugin-count'>" . number_format_i18n($update_data['counts']['plugins']) . "</span></span>";

		$menu[65] = array( sprintf( __('Plugins %s'), $count ), 'activate_plugins', 'plugins.php', '', 'menu-top menu-icon-plugins', 'menu-plugins', 'dashicons-admin-plugins' );
    }

	/**
	 * Displays an admin error message in the WordPress dashboard.
	 */
	public function check_response_for_errors( $response ) {

		$admin_notices = array();

		if ( ! empty( $response ) ) {

			if ( isset( $response->errors['no_key'] ) && $response->errors['no_key'] == 'no_key' && isset( $response->errors['no_subscription'] ) && $response->errors['no_subscription'] == 'no_subscription' ) {

				$admin_notices[] = 'no_key_error_notice';
				$admin_notices[] = 'no_subscription_error_notice';

			} else if ( isset( $response->errors['exp_license'] ) && $response->errors['exp_license'] == 'exp_license' ) {

				$admin_notices[] = 'expired_license_error_notice';

			}  else if ( isset( $response->errors['hold_subscription'] ) && $response->errors['hold_subscription'] == 'hold_subscription' ) {

				$admin_notices[] = 'on_hold_subscription_error_notice';

			} else if ( isset( $response->errors['cancelled_subscription'] ) && $response->errors['cancelled_subscription'] == 'cancelled_subscription' ) {

				$admin_notices[] = 'canceled_subscription_error_notice';

			} else if ( isset( $response->errors['exp_subscription'] ) && $response->errors['exp_subscription'] == 'exp_subscription' ) {

				$admin_notices[] = 'expired_subscription_error_notice';

			} else if ( isset( $response->errors['suspended_subscription'] ) && $response->errors['suspended_subscription'] == 'suspended_subscription' ) {

				$admin_notices[] = 'suspended_subscription_error_notice';

			} else if ( isset( $response->errors['pending_subscription'] ) && $response->errors['pending_subscription'] == 'pending_subscription' ) {

				$admin_notices[] = 'pending_subscription_error_notice';

			} else if ( isset( $response->errors['trash_subscription'] ) && $response->errors['trash_subscription'] == 'trash_subscription' ) {

				$admin_notices[] = 'trash_subscription_error_notice';

			} else if ( isset( $response->errors['no_subscription'] ) && $response->errors['no_subscription'] == 'no_subscription' ) {

				$admin_notices[] = 'no_subscription_error_notice';

			} else if ( isset( $response->errors['no_activation'] ) && $response->errors['no_activation'] == 'no_activation' ) {

				$admin_notices[] = 'no_activation_error_notice';

			} else if ( isset( $response->errors['no_key'] ) && $response->errors['no_key'] == 'no_key' ) {

				$admin_notices[] = 'no_key_error_notice';

			} else if ( isset( $response->errors['download_revoked'] ) && $response->errors['download_revoked'] == 'download_revoked' ) {

				$admin_notices[] = 'download_revoked_error_notice';

			} else if ( isset( $response->errors['switched_subscription'] ) && $response->errors['switched_subscription'] == 'switched_subscription' ) {

				$admin_notices[] = 'switched_subscription_error_notice';

			}

			foreach( $admin_notices as $admin_notice ) {
				add_action( 'admin_notices', array( $this, $admin_notice ) );
			}

			// Clear update information if there's something wrong with the license.
			if ( ! empty( $admin_notices ) ) {
				delete_transient( $this->update_cache_key );
			}

		}

	}

	/**
	 * Display license expired error notice.
	 */
	public function expired_license_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The license key for <em>%s</em> has expired. You can reactivate or purchase a license key from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription on-hold error notice.
	 */
	public function on_hold_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> is on-hold. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription canceled error notice.
	 */
	public function canceled_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> has been canceled. You can renew the subscription from your account <a href="%s" target="_blank">dashboard</a>. A new license key will be emailed to you after your order has been completed.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription expired error notice.
	 */
	public function expired_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> has expired. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription expired error notice.
	 */
	public function suspended_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> has been suspended. You can reactivate the subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription expired error notice.
	 */
	public function pending_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> is still pending. You can check on the status of the subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription expired error notice.
	 */
	public function trash_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'The subscription for <em>%s</em> has been placed in the trash and will be deleted soon. You can purchase a new subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display subscription expired error notice.
	 */
	public function no_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'A subscription for <em>%s</em> could not be found. You can purchase a subscription from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display missing key error notice.
	 */
	public function no_key_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'A license key for <em>%s</em> could not be found. Maybe you forgot to enter a license key when setting up <em>%s</em>, or the key was deactivated in your account. You can reactivate or purchase a license key from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display missing download permission revoked error notice.
	 */
	public function download_revoked_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'Download permission for <em>%s</em> has been revoked possibly due to a license key or subscription expiring. You can reactivate or purchase a license key from your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

	/**
	 * Display no activation error notice.
	 */
	public function no_activation_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( '<em>%s</em> has not been activated. Please enter the license key and email to activate <em>%s</em>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->product_id ) ;
	}

	/**
	 * Display switched activation error notice.
	 */
	public function switched_subscription_error_notice( $message ){
		echo sprintf( '<div id="message" class="error"><p>' . __( 'You changed the subscription for <em>%s</em>, so you will need to enter your new API License Key in the settings page. The License Key should have arrived in your email inbox, if not you can get it by logging into your account <a href="%s" target="_blank">dashboard</a>.', 'classifieds-wp' ) . '</p></div>', $this->product_id, $this->renew_license_url ) ;
	}

} // End of class
