<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The base license manager class.
 */
final class WP_Classified_Manager_License_Manager {

    /**
     * Base URL to the remote upgrade API Manager server. If not set then the Author URI is used.
     *
     * @var string
     */
    private $upgrade_url = 'http://classifiedswp.com/';

    /**
     * Base URL to the remote account page.
     *
     * @var string
     */
    private $renew_url = 'http://classifiedswp.com/account/';

    /**
     * Main option key for storing licenses information.
     */
    private static $options_key = 'wpcm_addons_licenses';

    /**
     * The licenses list.
     *
     * @var array
     */
    private $licenses;

    /**
     * @var The single instance of the class.
     */
    protected static $_instance = null;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone() {}

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup() {}

    /**
     * [__constructor]
     */
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // Check for external connection blocking.
        add_action( 'admin_notices', array( $this, 'check_external_blocking' ) );
    }

    /**
     * Initialize the license manager after all plugins have been loaded.
     */
    public function init() {

        // Get the installed add-ons list.
        $addons = WP_Classified_Manager_Addons::installed_addons_list();

        // Return earlier if there are no add-ons installed.
        if ( ! $addons ) {
            return false;
        }

        // Include the license class file.
        include_once( 'class-wp-classified-manager-lm-license.php' );

        // Include the menu and UI files.
        include_once( 'class-wp-classified-manager-lm-menu.php' );
        include_once( 'class-wp-classified-manager-lm-list.php' );
        include_once( 'class-wp-classified-manager-lm-edit.php' );

        // Include WooCommerce Licenses API Manager.
        include_once( 'api/class-wc-key-api.php' );
        include_once( 'api/class-wc-plugin-update.php' );

        // Generate default licenses for each new add-on.
        $this->create_licenses( $addons );
    }


    /**
     * Private methods.
     */


    /**
     * Create licenses for new add-ons.
     */
    private function create_licenses( $addons ) {

        $display_notice = false;

        foreach( $addons as $addon => $plugin ) {

            $this->licenses[ $addon ] = new WP_Classified_Manager_License( $addon, $plugin, $this->upgrade_url, $this->renew_url );

            if ( 'active' !== $this->licenses[ $addon ]->status ) {
                $display_notice = true;

                if ( is_plugin_active( $plugin['plugin'] ) ) {
                    $this->licenses[ $addon ]->deactivate_plugin();
                }

            } elseif( is_plugin_inactive( $plugin['plugin'] ) ) {
                $this->licenses[ $addon ]->deactivate();
            }

        }

        if ( $display_notice ) {
            add_action( 'admin_notices', array( $this, 'inactive_licenses_notice' ) );
        }

    }


    /**
     * Public methods.
     */


    /**
     * Retrieves the list of licenses objects for the current site.
     *
     * @return array
     */
    public function get_licenses() {
        return $this->licenses;
    }

    /**
     * Retrieves a list of licenses data.
     *
     * @return array
     */
    public function get_licenses_options() {
        return wp_list_pluck( $this->licenses, 'data' );
    }

    /**
     * Retrieves the existing license for a given add-on.
     *
     * @return array
     */
    public function get_license_for( $addon ) {

        if ( empty( $this->licenses[ $addon ] ) ) {
            return false;
        }
        return $this->licenses[ $addon ];
    }

    public static function get_options_key() {
        return self::$options_key;
    }

    /**
     * Getter.
     */
    public function __get( $property ) {

        if ( property_exists( $this, $property ) ) {
          return $this->$property;
        }

    }


    /**
     * Callbacks.
     */


    /**
     * Check for external blocking constant.
     */
    public function check_external_blocking() {

        // Show notice if external requests are blocked through the WP_HTTP_BLOCK_EXTERNAL constant.
        if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL === true ) {

            // cCeck if our API endpoint is in the allowed hosts.
            $host = parse_url( $this->upgrade_url, PHP_URL_HOST );

            if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || stristr( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
                ?><div class="error">
                    <p><?php printf( __( '<b>Warning!</b> You\'re blocking external requests which means you won\'t be able to get <em>%1$s</em> Add-ons updates. Please add <code>%2$s</code> to your <code>%3$s</code> constant.', 'classifieds-wp' ), 'Classified WP', $host, 'WP_ACCESSIBLE_HOSTS' ); ?></p>
                </div><?php
            }

        }

    }

    /**
     * Displays an inactive notice if there are inactive add-ons.
     */
    public static function inactive_licenses_notice() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['page'] ) && in_array( $_GET['page'], WP_Classified_Manager_License_Manager_Menu::get_page('all') ) ) {
            return;
        }

        ?><div id="message" class="error">
            <p><?php echo sprintf( __( 'You have inactive Add-ons for <em>%1$s</em>! Click <a href="%2$s">here</a> to manage your licenses and activate Add-ons.', 'classifieds-wp' ), 'Classifieds WP Manager', esc_url( WP_Classified_Manager_License_Manager_Menu::get_page( 'manage' ,'url' ) ) ); ?></p>
        </div><?php
    }

}

function wpcm_license_manager() {
    return WP_Classified_Manager_License_Manager::instance();
}

wpcm_license_manager();
