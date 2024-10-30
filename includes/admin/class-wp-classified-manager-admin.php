<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WP_Classified_Manager_Admin class.
 */
class WP_Classified_Manager_Admin {

	/**
	 * __construct function.
	 */
	public function __construct() {
		include_once( 'class-wp-classified-manager-cpt.php' );
		include_once( 'class-wp-classified-manager-settings.php' );
		include_once( 'class-wp-classified-manager-writepanels.php' );
		include_once( 'class-wp-classified-manager-setup.php' );
		include_once( 'class-wp-classified-manager-addons.php' );

		$this->settings_page = new WP_Classified_Manager_Settings();

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		global $wp_scripts;

		$screen = get_current_screen();

		if ( in_array( $screen->id, apply_filters( 'classified_manager_admin_screen_ids', array( 'edit-classified_listing', 'classified_listing', 'classified_listing_page_classified-manager-settings', 'classified_listing_page_classified-manager-addons' ) ) ) ) {
			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//code.jquery.com/ui/' . $jquery_version . '/themes/smoothness/jquery-ui.css', array(), $jquery_version );
			wp_enqueue_style( 'classified_manager_admin_css', WP_CLASSIFIED_MANAGER_PLUGIN_URL . '/assets/css/admin.css' );
			wp_register_script( 'jquery-tiptip', WP_CLASSIFIED_MANAGER_PLUGIN_URL. '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), WP_CLASSIFIED_MANAGER_VERSION, true );
			wp_enqueue_script( 'classified_manager_admin_js', WP_CLASSIFIED_MANAGER_PLUGIN_URL. '/assets/js/admin.min.js', array( 'jquery', 'jquery-tiptip', 'jquery-ui-datepicker' ), WP_CLASSIFIED_MANAGER_VERSION, true );
		}

		wp_enqueue_style( 'classified_manager_admin_menu_css', WP_CLASSIFIED_MANAGER_PLUGIN_URL . '/assets/css/menu.css' );
	}

	/**
	 * admin_menu function.
	 */
	public function admin_menu() {
		add_submenu_page( 'edit.php?post_type=classified_listing', __( 'Settings', 'classifieds-wp' ), __( 'Settings', 'classifieds-wp' ), 'manage_options', 'classified-manager-settings', array( $this->settings_page, 'output' ) );

		if ( apply_filters( 'classified_manager_show_addons_page', true ) ) {
			add_submenu_page(  'edit.php?post_type=classified_listing', __( 'Classifieds WP Add-ons', 'classifieds-wp' ),  __( 'Add-ons', 'classifieds-wp' ) , 'manage_options', 'classified-manager-addons', array( $this, 'addons_page' ) );
		}

	}

	/**
	 * Output addons page.
	 */
	public function addons_page() {
		WP_Classified_Manager_Addons::output();
	}

}

new WP_Classified_Manager_Admin();
