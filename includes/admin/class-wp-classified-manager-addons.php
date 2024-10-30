<?php
/**
 * Addons Page
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WP_Classified_Manager_Addons' ) ) :

/**
 * WP_Classified_Manager_Addons Class
 */
class WP_Classified_Manager_Addons {

	/**
	 * Handles output of the reports page in admin.
	 */
	public static function output() {

		if ( false === ( $addons = get_transient( 'wp_classified_manager_addons_html' ) ) ) {

			$raw_addons = wp_remote_get(
				'http://classifiedswp.com/add-ons/',
				array(
					'timeout'     => 10,
					'redirection' => 5,
					'sslverify'   => false
				)
			);

			if ( ! is_wp_error( $raw_addons ) ) {

				$raw_addons = wp_remote_retrieve_body( $raw_addons );

				// Get Products
				$dom = new DOMDocument();
				libxml_use_internal_errors(true);
				$dom->loadHTML( $raw_addons );

				$xpath  = new DOMXPath( $dom );
				$tags   = $xpath->query('//div[@class="entry-content-wrapper"]');

				foreach ( $tags as $tag ) {
					$addons = $tag->ownerDocument->saveXML( $tag );
					break;
				}

				$addons = wp_kses_post( $addons );

				if ( $addons ) {
					set_transient( 'wp_classified_manager_addons_html', $addons, 60*60*24*7 ); // Cached for a week
				}
			}
		}

		?>
		<div class="wrap wp_classified_manager wp_classified_manager_addons_wrap">
			<h2><?php _e( 'Classifieds WP Add-ons', 'classifieds-wp' ); ?></h2>

			<div id="classified-manager-addons-banner" class="notice updated below-h2"><strong><?php _e( 'Do you need multiple add-ons?', 'classifieds-wp' ); ?></strong> <a href="http://classifiedswp.com/add-ons/core-add-on-bundle/" class="button" target="_blank"><?php _e( 'Check out the core add-on bundle &rarr;', 'classifieds-wp' ); ?></a></div>

			<?php echo $addons; ?>
		</div>
		<?php
	}

	public static function addons_list() {

		if ( false === ( $addons = get_transient( 'wp_classified_manager_addons' ) ) ) {

	    	$raw_addons = wp_remote_get(
	            'http://classifiedswp.com/add-ons/',
	            array(
	                'timeout'     => 10,
	                'redirection' => 5,
	                'sslverify'   => false
	            )
	        );

	        if ( ! is_wp_error( $raw_addons ) ) {

	            $raw_addons = wp_remote_retrieve_body( $raw_addons );

	            // Get Products
	            $dom = new DOMDocument();
	            libxml_use_internal_errors(true);
	            $dom->loadHTML( $raw_addons );

				$xpath = new DOMXPath( $dom );
				$tags  = $xpath->query('//div[@class="inner_product_header"]');
			}

	        foreach ( $tags as $tag ) {

	            $addon = explode( PHP_EOL, $tag->textContent );

	            if ( ! empty( $addon[0] ) ) {
		            $addons[] = $addon[0];
		            // Make sure add-ons can also be found when prefixed with 'Classifieds WP -'.
		            $addons[] = "Classifieds WP - {$addon[0]}";
		        }

        	}

			if ( $addons ) {
				set_transient( 'wp_classified_manager_addons', $addons, 60*60*24*7 ); // Cached for a week
			}

        }
        return $addons;
	}

	public static function installed_addons_list() {

		$installed_addons = array();
		$valid_addons     = self::addons_list();

		if ( empty( $valid_addons ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins      = get_plugins();
		$plugin_names = wp_list_pluck( $plugins, 'Name' );
		$found_addons = array_intersect( $plugin_names, $valid_addons );

		foreach( $found_addons as $file => $name ) {

			$installed_addons[ self::unprefixed_name( $name ) ] = array(
				'plugin'  => $file,
				'version' => $plugins[ $file ]['Version'],
			);

		}
		return $installed_addons;
	}

	/**
	 * Outputs the add-on name prefixed with the parent plugin name.
	 */
	public static function prefixed_name( $name ) {
		return "Classifieds WP - {$name}";
	}

	/**
	 * Outputs the add-on name prefixed with the parent plugin name.
	 */
	public static function unprefixed_name( $name ) {
		return trim( str_replace( 'Classifieds WP -', '', $name ) );
	}

}

endif;
