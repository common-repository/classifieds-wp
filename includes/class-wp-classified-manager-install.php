<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Classified_Manager_Install
 */
class WP_Classified_Manager_Install {

	/**
	 * Install Classifieds WP
	 */
	public static function install() {
		global $wpdb;

		self::init_user_roles();
		self::default_terms();
		self::schedule_cron();

		// Redirect to setup screen for new installs
		if ( ! get_option( 'wp_classified_manager_version' ) ) {
			set_transient( '_classified_manager_activation_redirect', 1, HOUR_IN_SECONDS );
		}

		// Update featured posts ordering
		if ( version_compare( get_option( 'wp_classified_manager_version', WP_CLASSIFIED_MANAGER_VERSION ), '1.22.0', '<' ) ) {
			$wpdb->query( "UPDATE {$wpdb->posts} p SET p.menu_order = 0 WHERE p.post_type='classified_listing';" );
			$wpdb->query( "UPDATE {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id SET p.menu_order = -1 WHERE pm.meta_key = '_featured' AND pm.meta_value='1' AND p.post_type='classified_listing';" );
		}

		// Update legacy options
		if ( false === get_option( 'classified_manager_submit_classified_form_page_id', false ) && get_option( 'classified_manager_submit_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'classified_manager_submit_page_slug' ) )->ID;
			update_option( 'classified_manager_submit_classified_form_page_id', $page_id );
		}

		if ( false === get_option( 'classified_manager_classified_dashboard_page_id', false ) && get_option( 'classified_manager_classified_dashboard_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'classified_manager_classified_dashboard_page_slug' ) )->ID;
			update_option( 'classified_manager_classified_dashboard_page_id', $page_id );
		}

		self::update_legacy_features();

		delete_transient( 'wp_classified_manager_addons_html' );
		update_option( 'wp_classified_manager_version', WP_CLASSIFIED_MANAGER_VERSION );
	}

	/**
	 * Init user roles
	 */
	private static function init_user_roles() {
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		if ( is_object( $wp_roles ) ) {
			add_role( 'advertiser', __( 'Advertiser', 'classifieds-wp' ), array(
				'read'         => true,
				'edit_posts'   => false,
				'delete_posts' => false,
				'upload_media' => true
			) );

			if ( apply_filters( 'classified_manager_enable_media_viewer', true ) ) {

				// Add special upload capabilities to roles with the 'edit_posts' cap.
				foreach( $wp_roles->roles as $role => $details ) {
					if ( ! empty( $details['capabilities']['edit_posts'] ) || 'advertiser' === $role ) {
						$wp_roles->add_cap( $role, 'upload_media' );
					}
				}

			}

			$capabilities = self::get_core_capabilities();

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}

		}
	}

	/**
	 * Get capabilities
	 * @return array
	 */
	private static function get_core_capabilities() {
		return array(
			'core' => array(
				'manage_classified_listings'
			),
			'classified_listing' => array(
				"edit_classified_listing",
				"read_classified_listing",
				"delete_classified_listing",
				"edit_classified_listings",
				"edit_others_classified_listings",
				"publish_classified_listings",
				"read_private_classified_listings",
				"delete_classified_listings",
				"delete_private_classified_listings",
				"delete_published_classified_listings",
				"delete_others_classified_listings",
				"edit_private_classified_listings",
				"edit_published_classified_listings",
				"manage_classified_listing_terms",
				"edit_classified_listing_terms",
				"delete_classified_listing_terms",
				"assign_classified_listing_terms"
			)
		);
	}

	/**
	 * default_terms function.
	 */
	private static function default_terms() {
		if ( get_option( 'classified_manager_installed_terms' ) == 1 ) {
			return;
		}

		$taxonomies = array(
			'classified_listing_type' => array(
				'New',
				'Used'
			)
		);

		foreach ( $taxonomies as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! get_term_by( 'slug', sanitize_title( $term ), $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}

		update_option( 'classified_manager_installed_terms', 1 );
	}

	/**
	 * Updated legacy features.
	 */
	protected static function update_legacy_features() {
		self::update_legacy_featured_images();
	}

	/**
	 * Updated legacy featured images to proper WP featured images.
	 */
	protected static function update_legacy_featured_images() {

		$args = array(
			'post_type'    => 'classified_listing',
			'nopaging'     => true,
			'meta_key'     => '_classified_featured_image',
			'meta_value'   => '',
			'meta_compare' => '!='
		);
		$query = new WP_Query( $args );

		foreach( $query->posts as $post ) {
			$url = get_post_meta( $post->ID, '_classified_featured_image', true );

			if ( $url ) {
				$attachment_id = wp_classified_manager_get_attachment_id_by_url( $url );
				$updated       = set_post_thumbnail( $post->ID, $attachment_id );
			}

			if ( ! empty( $updated ) || ! $url ) {
				delete_post_meta( $post->ID, '_classified_featured_image' );
			}
		}

	}

	/**
	 * Setup cron classifieds
	 */
	private static function schedule_cron() {
		wp_clear_scheduled_hook( 'classified_manager_check_for_expired_classifieds' );
		wp_clear_scheduled_hook( 'classified_manager_delete_old_previews' );
		wp_clear_scheduled_hook( 'classified_manager_clear_expired_transients' );
		wp_schedule_event( time(), 'hourly', 'classified_manager_check_for_expired_classifieds' );
		wp_schedule_event( time(), 'daily', 'classified_manager_delete_old_previews' );
		wp_schedule_event( time(), 'twicedaily', 'classified_manager_clear_expired_transients' );
	}
}
