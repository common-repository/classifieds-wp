<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main license class.
 */
final class WP_Classified_Manager_License {

    /**
     * Base URL to the remote upgrade API Manager server. If not set then the Author URI is used.
     *
     * @var string
     */
    private $upgrade_url;

    /**
     * Base URL to the license owner remote account page.
     *
     * @var string
     */
    private $renew_url;

    /**
     * Main option key for storing license information.
     *
     * @var string
     */
    private $option_key;

    /**
     * Populated only when an update is available.
     *
     * @var object
     */
    private $update;

    /**
     * The license data keys for each license.
     *
     * @var array
     */
    private $data = array(
        'id'         => '',
        'api_key'    => '',
        'email'      => '',
        'instance'   => '', // Instance ID (unique to each blog activation).
        'plugin'     => '', // Plugin file path.
        'version'    => '',
        'domain'     => '',
        'status'     => 'inactive',
        'deactivate' => false,
        'extra'      => '',
    );

    /**
     * Cloning is forbidden.
     */
    public function __clone() {}

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup() {}

    /**
     * Initialize the license.
     */
    public function __construct( $addon, $plugin, $upgrade_url, $renew_url ) {

        add_action( 'wp_classified_manager_license_activated', array( $this, 'activate_plugin' ) );
        add_action( 'wp_classified_manager_license_deactivated', array( $this, 'deactivate_plugin' ) );

        // Remove any parent plugin name prefix from the add-on name.
        $addon = WP_Classified_Manager_Addons::unprefixed_name( $addon );

        $this->upgrade_url = $upgrade_url;
        $this->renew_url   = $renew_url;

        $this->set_license_data( $addon, $plugin );

        register_deactivation_hook( $plugin['plugin'], array( $this, 'deactivate_license' ) );
    }

    /**
     * Prepare and set all the license data for an add-on.
     */
    private function set_license_data( $addon, $plugin ) {

        // Get the base options key where licenses are stored.
        $options_key = WP_Classified_Manager_License_Manager::get_options_key();

        // Get the licenses from the DB.
        $licenses = get_option( $options_key );

        if ( ! empty( $licenses[ $addon ] ) )  {

            // Get an updated status from the server.
            $this->data           = $licenses[ $addon ];
            $this->data['status'] = $this->server_status();

            $this->update_check();

        } else {

            $data = array(
                'id'       => $addon,
                'instance' => wp_generate_password( 12, false ),
                'domain'   => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
            );

            // Get additional data from the '$plugin' parameter.
            $data = array_merge( $data, $plugin );

            $this->data = wp_parse_args( $data, $this->data );

            $data = $this->data;

            // Save the license data.
            update_option( $options_key, $data );

        }

        // Set the single option key for the add-on license.
        $this->option_key = $options_key . "[$addon]";
    }


    /**
     * Public methods.
     */


    /**
     * Deactivates a license when a add-on/plugin with an active license is deactivated.
     */
    public function deactivate_license() {

        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $file = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$file}" );

        // Avoid a deactivation loop.
       remove_action( 'wp_classified_manager_license_deactivated', array( $this, 'deactivate_plugin' ) );

        $this->deactivate();
    }

    /**
     * Deactivates a add-on/plugin if the license is not active.
     */
    public function deactivate_plugin() {
        deactivate_plugins( $this->plugin, $silent = true );
    }

    /**
     * Activates an add-on/plugin if license is successfully activated.
     */
    public function activate_plugin() {
        activate_plugin( $this->plugin );
    }

    /**
     * Check for updates.
     */
    public function update_check() {
        WP_Classified_Manager_WOO_Update_API_Check::instance( $this->upgrade_url, $this->renew_url, $this->data );

        $cache_key = md5( 'wpcm_plugin_' . sanitize_key( $this->plugin ) . '_update_info' );
        $response  = get_transient( $cache_key );

        if ( $response && is_object( $response ) ) {
            $this->update = $response;
        }

    }

    /**
     * Outputs the download update link if available.
     */
    public function download_update_link() {

        if ( empty( $this->update->new_version ) || 'active' !== $this->status ) {
            return;
        }

        $new = version_compare( $this->version, $this->update->new_version, '<' );

        if ( $new ) {
          return sprintf( __( '<a class="update-addon-link" href="%1$s">Download v.%2$s</a>', 'classifieds-wp' ), esc_url( $this->update->package ), $this->update->new_version );
        }

    }


    /**
     * Wrapper methods for the Woo API Manager class actions.
     */


    /**
     * Wrapper method for activating a license.
     */
    public function activate( $data ) {
        $result = WP_Classified_Manager_WOO_API_Manager::instance( $this )->activate( $data );
        $result = json_decode( $result, true );

        if ( ! empty( $result['activated'] ) ) {
            do_action( 'wp_classified_manager_license_activated', $this->data );
        }

        return $result;
    }

    /**
     * Wrapper method for deactivating a license.
     */
    public function deactivate( $data = array() ) {

        if ( empty( $data ) ) {
            $data['email']       = $this->email;
            $data['licence_key'] = $this->api_key;
        }

        $result = WP_Classified_Manager_WOO_API_Manager::instance( $this )->deactivate( $data );
        $result = json_decode( $result, true );

        if ( ! empty( $result['deactivated'] ) ) {
            do_action( 'wp_classified_manager_license_deactivated', $this->data );
        }

        return $result;
    }

    /**
     * Wrapper method for getting a license status.
     */
    public function status( $data ) {
        $result = WP_Classified_Manager_WOO_API_Manager::instance( $this )->status( $data );
        return json_decode( $result, true );
    }

    /**
     * Returns the API License Key status from the WooCommerce API Manager on the server.
     */
    public function server_status() {
        $activation_status = $this->status;

        $data = array(
            'email'       => $this->email,
            'licence_key' => $this->api_key,
        );

        $status = $this->status( $data );

        return ! empty( $status['status_check'] ) ? $status['status_check'] : $status['activated'] ;
    }

    /**
     * Getter.
     */
    public function __get( $property ) {

        if ( property_exists( $this, $property ) ) {
          return $this->$property;
        } elseif( isset( $this->data[ $property ] ) ) {
            return $this->data[ $property ];
        }

    }

}
