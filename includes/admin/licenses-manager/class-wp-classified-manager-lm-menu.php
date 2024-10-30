<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * The licenses manager menu class.
 */
class WP_Classified_Manager_License_Manager_Menu {

	/**
	 * The used page slugs.
	 *
	 * @var array
	 */
	private static $pages = array(
		'manage' => 'classified-manager-licenses-manager',
		'edit'   => 'classified-manager-licenses-manager-edit',
	);

	/**
	 * The base URL for the menu.
	 *
	 * @var string
	 */
	private static $base_url = 'edit.php?post_type=classified_listing';

	/**
	 * Initialize the menu.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 15  );
	}

	/**
	 * Creates the license manager menu and pages.
	 */
	public function add_menu() {
		$edit = new WP_Classified_Manager_License_Manager_Edit;
		$list = new WP_Classified_Manager_License_Manager_List;

		$page_list = add_submenu_page( self::$base_url, __( 'Licenses Manager', 'classifieds-wp' ),  __( 'Licenses Manager', 'classifieds-wp' ) , 'manage_options', 'classified-manager-licenses-manager', array( $list, 'list_licenses' ) );
		$page_edit = add_submenu_page( null, __( 'Edit Add-on License', 'classifieds-wp' ),  __( 'Edit Add-on License', 'classifieds-wp' ) , 'manage_options', 'classified-manager-licenses-manager-edit', array( $edit, 'edit_license' ) );

		add_action( 'admin_head-' . $page_list, array( $this, 'inline_css' ) );
		add_action( 'admin_head-' . $page_edit, array( $this, 'inline_css' ) );
	}

	/**
	 * Custom CSS for the license manager pages.
	 */
	public function inline_css() {
		?><style type="text/css">
			a.update-addon-link { margin-left: 10px; text-decoration: underline; }
		</style><?php
	}


    /**
     * Helpers.
     */


	/**
	 * Retrieves the manage/edit pages URL or slug.
	 */
	public static function get_page( $page = 'manage', $part = 'slug' ) {

		if ( 'slug' !== $part ) {
			return add_query_arg( 'page', self::$pages[ $page ], self::$base_url );
		} elseif ( 'all' === $page ) {
			return self::$pages;
		}
		return self::$pages[ $page ];
	}

}

new WP_Classified_Manager_License_Manager_Menu;
