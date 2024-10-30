<?php

/**
 * WooCommerce API Manager API Key Class.
 *
 * Modified by 'Classifieds WP Manager'.
 *
 * @package   Update API Manager/Key Handler
 * @author    Todd Lahman LLC
 * @copyright Copyright (c) Todd Lahman LLC
 * @since     1.3
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WP_Classified_Manager_WOO_API_Manager {

    /**
     * The single instance of the class.
     *
     * @var object
     */
    protected static $_instance = null;

    /**
     * The license data.
     *
     * @var array
     */
    private $license;

    public function __construct( $license ) {
        $this->license = $license;
    }

    public static function instance( $license ) {

        if ( empty( self::$_instance[ $license->id ] ) ) {
        	self::$_instance[ $license->id ] = new self( $license );
        }

        return self::$_instance[ $license->id ];
    }

	public function activate( $args ) {

		$defaults = array(
			'request'          => 'activation',
			'product_id'       => $this->license->id,
			'instance'         => $this->license->instance,
			'platform'         => $this->license->domain,
			'software_version' => $this->license->version
		);

		$args = wp_parse_args( $defaults, $args );

		$target_url = esc_url_raw( $this->create_software_api_url( $args ) );
		$request    = wp_safe_remote_get( $target_url );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            // Request failed
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	public function deactivate( $args ) {

		$defaults = array(
			'request'    => 'deactivation',
			'product_id' => $this->license->id,
			'instance'   => $this->license->instance,
			'platform'   => $this->license->domain
		);

		$args = wp_parse_args( $defaults, $args );

		$target_url = esc_url_raw( $this->create_software_api_url( $args ) );
		$request    = wp_safe_remote_get( $target_url );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            // Request failed
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	/**
	 * Checks if the add-on is activated or deactivated.
	 */
	public function status( $args ) {

		$defaults = array(
			'request'    => 'status',
			'product_id' => $this->license->id,
			'instance'   => $this->license->instance,
			'platform'   => $this->license->domain
		);

		$args = wp_parse_args( $defaults, $args );

		$target_url = esc_url_raw( $this->create_software_api_url( $args ) );
		$request    = wp_safe_remote_get( $target_url );

		// $request = wp_remote_post( wpcm_license_manager()->upgrade_url . 'wc-api/am-software-api/', array( 'body' => $args ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            // Request failed
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	/**
	 * API Key URL.
	 */
	public function create_software_api_url( $args ) {
		$api_url = add_query_arg( 'wc-api', 'am-software-api', $this->license->upgrade_url );
		return $api_url . '&' . http_build_query( $args );
	}

}

// Class is instantiated as an object by other classes on-demand
