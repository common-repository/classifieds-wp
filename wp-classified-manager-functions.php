<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_filter( 'icl_current_language', 'classified_manager_set_ajax_language' );
add_filter( 'upload_dir', 'classified_manager_upload_dir' );


if ( ! function_exists( 'get_classified_listings' ) ) :
/**
 * Queries classified listings with certain criteria and returns them
 *
 * @access public
 * @return void
 */
function get_classified_listings( $args = array() ) {
	global $wpdb, $classified_manager_keyword;

	$args = wp_parse_args( $args, array(
		'search_location'        => '',
		'search_keywords'        => '',
		'search_categories'      => array(),
		'classified_types'       => array(),
		'offset'                 => 0,
		'posts_per_page'         => 20,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'featured'               => null,
		'classified_unavailable' => null,
		'fields'                 => 'all'
	) );

	$query_args = array(
		'post_type'              => 'classified_listing',
		'post_status'            => 'publish',
		'ignore_sticky_posts'    => 1,
		'offset'                 => absint( $args['offset'] ),
		'posts_per_page'         => intval( $args['posts_per_page'] ),
		'orderby'                => $args['orderby'],
		'order'                  => $args['order'],
		'tax_query'              => array(),
		'meta_query'             => array(),
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'cache_results'          => false,
		'fields'                 => $args['fields'],
	);

	if ( ! empty( $args['post__in'] ) ) {
		$query_args['post__in'] = $args['post__in'];
	}

	if ( $args['posts_per_page'] < 0 ) {
		$query_args['no_found_rows'] = true;
	}

	if ( ! empty( $args['search_location'] ) ) {
		$location_meta_keys = array( 'geolocation_formatted_address', '_classified_location', 'geolocation_state_long' );
		$location_search    = array( 'relation' => 'OR' );
		foreach ( $location_meta_keys as $meta_key ) {
			$location_search[] = array(
				'key'     => $meta_key,
				'value'   => $args['search_location'],
				'compare' => 'like'
			);
		}
		$query_args['meta_query'][] = $location_search;
	}

	if ( ! is_null( $args['featured'] ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_featured',
			'value'   => '1',
			'compare' => $args['featured'] ? '=' : '!='
		);
	}

	if ( ! is_null( $args['classified_unavailable'] ) || 1 === absint( get_option( 'classified_manager_hide_unavaliable_classifieds' ) ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_classified_unavailable',
			'value'   => '1',
			'compare' => $args['classified_unavailable'] ? '=' : '!='
		);
	}

	if ( ! empty( $args['classified_types'] ) ) {
		$query_args['tax_query'][] = array(
			'taxonomy' => 'classified_listing_type',
			'field'    => 'slug',
			'terms'    => $args['classified_types']
		);
	}

	if ( ! empty( $args['search_categories'] ) ) {
		$field    = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';
		$operator = 'all' === get_option( 'classified_manager_category_filter_type', 'all' ) && sizeof( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
		$query_args['tax_query'][] = array(
			'taxonomy'         => 'classified_listing_category',
			'field'            => $field,
			'terms'            => array_values( $args['search_categories'] ),
			'include_children' => $operator !== 'AND' ,
			'operator'         => $operator
		);
	}

	if ( 'featured' === $args['orderby'] ) {
		$query_args['orderby'] = array(
			'menu_order' => 'ASC',
			'date'       => 'DESC'
		);
	}

	$classified_manager_keyword = sanitize_text_field( $args['search_keywords'] );

	if ( ! empty( $classified_manager_keyword ) && strlen( $classified_manager_keyword ) >= apply_filters( 'classified_manager_get_listings_keyword_length_threshold', 2 ) ) {
		$query_args['_keyword'] = $classified_manager_keyword; // Does nothing but needed for unique hash
		add_filter( 'posts_clauses', 'get_classified_listings_keyword_search' );
	}

	$query_args = apply_filters( 'classified_manager_get_listings', $query_args, $args );

	if ( empty( $query_args['meta_query'] ) ) {
		unset( $query_args['meta_query'] );
	}

	if ( empty( $query_args['tax_query'] ) ) {
		unset( $query_args['tax_query'] );
	}

	// Polylang LANG arg
	if ( function_exists( 'pll_current_language' ) ) {
		$query_args['lang'] = pll_current_language();
	}

	// Filter args
	$query_args = apply_filters( 'get_classified_listings_query_args', $query_args, $args );

	// Generate hash
	$to_hash         = json_encode( $query_args ) . apply_filters( 'wpml_current_language', '' );
	$query_args_hash = 'jm_' . md5( $to_hash ) . WP_Classified_Manager_Cache_Helper::get_transient_version( 'get_classified_listings' );

	do_action( 'before_get_classified_listings', $query_args, $args );

	if ( false === ( $result = get_transient( $query_args_hash ) ) ) {
		$result = new WP_Query( $query_args );
		set_transient( $query_args_hash, $result, DAY_IN_SECONDS * 30 );
	}

	do_action( 'after_get_classified_listings', $query_args, $args );

	remove_filter( 'posts_clauses', 'get_classified_listings_keyword_search' );

	return $result;
}
endif;

if ( ! function_exists( 'get_classified_listings_keyword_search' ) ) :
	/**
	 * Join and where query for keywords
	 *
	 * @param array $args
	 * @return array
	 */
	function get_classified_listings_keyword_search( $args ) {
		global $wpdb, $classified_manager_keyword;

		$conditions   = array();
		$conditions[] = "{$wpdb->posts}.post_title LIKE '%" . esc_sql( $classified_manager_keyword ) . "%'";
		$conditions[] = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%" . esc_sql( $classified_manager_keyword ) . "%' )";
		$conditions[] = "{$wpdb->posts}.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} AS tr LEFT JOIN {$wpdb->terms} AS t ON tr.term_taxonomy_id = t.term_id WHERE t.name LIKE '%" . esc_sql( $classified_manager_keyword ) . "%' )";

		if ( ctype_alnum( $classified_manager_keyword ) ) {
			$conditions[] = "{$wpdb->posts}.post_content RLIKE '[[:<:]]" . esc_sql( $classified_manager_keyword ) . "[[:>:]]'";
		} else {
			$conditions[] = "{$wpdb->posts}.post_content LIKE '%" . esc_sql( $classified_manager_keyword ) . "%'";
		}

		$args['where'] .= " AND ( " . implode( ' OR ', $conditions ) . " ) ";

		return $args;
	}
endif;

if ( ! function_exists( 'order_featured_classified_listing' ) ) :
	/**
	 * Was used for sorting.
	 *
	 * @deprecated 1.22.4
	 * @param array $args
	 * @return array
	 */
	function order_featured_classified_listing( $args ) {
		global $wpdb;
		$args['orderby'] = "$wpdb->posts.menu_order ASC, $wpdb->posts.post_date DESC";
		return $args;
	}
endif;

if ( ! function_exists( 'get_classified_listing_post_statuses' ) ) :
/**
 * Get post statuses used for classifieds
 *
 * @access public
 * @return array
 */
function get_classified_listing_post_statuses() {
	return apply_filters( 'classified_listing_post_statuses', array(
		'draft'           => _x( 'Draft', 'post status', 'classifieds-wp' ),
		'expired'         => _x( 'Expired', 'post status', 'classifieds-wp' ),
		'preview'         => _x( 'Preview', 'post status', 'classifieds-wp' ),
		'pending'         => _x( 'Pending approval', 'post status', 'classifieds-wp' ),
		'pending_payment' => _x( 'Pending payment', 'post status', 'classifieds-wp' ),
		'publish'         => _x( 'Active', 'post status', 'classifieds-wp' ),
	) );
}
endif;

if ( ! function_exists( 'get_featured_classified_ids' ) ) :
/**
 * Gets the ids of featured classifieds.
 *
 * @access public
 * @return array
 */
function get_featured_classified_ids() {
	return get_posts( array(
		'posts_per_page' => -1,
		'post_type'      => 'classified_listing',
		'post_status'    => 'publish',
		'meta_key'       => '_featured',
		'meta_value'     => '1',
		'fields'         => 'ids'
	) );
}
endif;

if ( ! function_exists( 'get_classified_listing_types' ) ) :
/**
 * Get classified listing types
 *
 * @access public
 * @return array
 */
function get_classified_listing_types( $fields = 'all' ) {
	return get_terms( "classified_listing_type", array(
		'orderby'    => 'name',
		'order'      => 'ASC',
		'hide_empty' => false,
		'fields'     => $fields
	) );
}
endif;

if ( ! function_exists( 'get_classified_listing_categories' ) ) :
/**
 * Get classified categories
 *
 * @access public
 * @return array
 */
function get_classified_listing_categories() {
	if ( ! get_option( 'classified_manager_enable_categories' ) ) {
		return array();
	}

	return get_terms( "classified_listing_category", array(
		'orderby'       => 'name',
	    'order'         => 'ASC',
	    'hide_empty'    => false,
	) );
}
endif;

if ( ! function_exists( 'classified_manager_get_filtered_links' ) ) :
/**
 * Shows links after filtering classifieds
 */
function classified_manager_get_filtered_links( $args = array() ) {
	$classified_categories = array();
	$types          = get_classified_listing_types();

	// Convert to slugs
	if ( $args['search_categories'] ) {
		foreach ( $args['search_categories'] as $category ) {
			if ( is_numeric( $category ) ) {
				$category_object = get_term_by( 'id', $category, 'classified_listing_category' );
				if ( ! is_wp_error( $category_object ) ) {
					$classified_categories[] = $category_object->slug;
				}
			} else {
				$classified_categories[] = $category;
			}
		}
	}

	$links = apply_filters( 'classified_manager_classified_filters_showing_classifieds_links', array(
		'reset' => array(
			'name' => __( 'Reset', 'classifieds-wp' ),
			'url'  => '#'
		),
		'rss_link' => array(
			'name' => __( 'RSS', 'classifieds-wp' ),
			'url'  => get_classified_listing_rss_link( apply_filters( 'classified_manager_get_listings_custom_filter_rss_args', array(
				'classified_types'       => isset( $args['filter_classified_types'] ) ? implode( ',', $args['filter_classified_types'] ) : '',
				'search_location' => $args['search_location'],
				'classified_categories'  => implode( ',', $classified_categories ),
				'search_keywords' => $args['search_keywords'],
			) ) )
		)
	), $args );

	if ( sizeof( $args['filter_classified_types'] ) === sizeof( $types ) && ! $args['search_keywords'] && ! $args['search_location'] && ! $args['search_categories'] && ! apply_filters( 'classified_manager_get_listings_custom_filter', false ) ) {
		unset( $links['reset'] );
	}

	$return = '';

	foreach ( $links as $key => $link ) {
		$return .= '<a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '">' . $link['name'] . '</a>';
	}

	return $return;
}
endif;

if ( ! function_exists( 'get_classified_listing_rss_link' ) ) :
/**
 * Get the Classified Listing RSS link
 *
 * @return string
 */
function get_classified_listing_rss_link( $args = array() ) {
	$rss_link = add_query_arg( urlencode_deep( array_merge( array( 'feed' => 'classified_feed' ), $args ) ), home_url() );
	return $rss_link;
}
endif;

if ( ! function_exists( 'wp_classified_manager_notify_new_user' ) ) :
	/**
	 * Handle account creation.
	 *
	 * @param  int $user_id
	 * @param  string $password
	 */
	function wp_classified_manager_notify_new_user( $user_id, $password ) {
		global $wp_version;

		if ( version_compare( $wp_version, '4.3.1', '<' ) ) {
			wp_new_user_notification( $user_id, $password );
		} else {
			wp_new_user_notification( $user_id, null, 'both' );
		}
	}
endif;

if ( ! function_exists( 'classified_manager_create_account' ) ) :
/**
 * Handle account creation.
 *
 * @param  array $args containing username, email, role
 * @param  string $deprecated role string
 * @return WP_error | bool was an account created?
 */
function wp_classified_manager_create_account( $args, $deprecated = '' ) {
	global $current_user;

	// Soft Deprecated in 1.20.0
	if ( ! is_array( $args ) ) {
		$username = '';
		$password = wp_generate_password();
		$email    = $args;
		$role     = $deprecated;
	} else {
		$defaults = array(
			'username' => '',
			'email'    => '',
			'password' => wp_generate_password(),
			'role'     => get_option( 'default_role' )
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args );
	}

	$username = sanitize_user( $username );
	$email    = apply_filters( 'user_registration_email', sanitize_email( $email ) );

	if ( empty( $email ) ) {
		return new WP_Error( 'validation-error', __( 'Invalid email address.', 'classifieds-wp' ) );
	}

	if ( empty( $username ) ) {
		$username = sanitize_user( current( explode( '@', $email ) ) );
	}

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'classifieds-wp' ) );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'classifieds-wp' ) );
	}

	// Ensure username is unique
	$append     = 1;
	$o_username = $username;

	while ( username_exists( $username ) ) {
		$username = $o_username . $append;
		$append ++;
	}

	// Final error checking
	$reg_errors = new WP_Error();
	$reg_errors = apply_filters( 'classified_manager_registration_errors', $reg_errors, $username, $email );

	do_action( 'classified_manager_register_post', $username, $email, $reg_errors );

	if ( $reg_errors->get_error_code() ) {
		return $reg_errors;
	}

	// Create account
	$new_user = array(
		'user_login' => $username,
		'user_pass'  => $password,
		'user_email' => $email,
		'role'       => $role
    );

    $user_id = wp_insert_user( apply_filters( 'classified_manager_create_account_data', $new_user ) );

    if ( is_wp_error( $user_id ) ) {
    	return $user_id;
    }

    // Notify
    wp_classified_manager_notify_new_user( $user_id, $password, $new_user );
    // Login
    wp_set_auth_cookie( $user_id, true, is_ssl() );
    $current_user = get_user_by( 'id', $user_id );

    return true;
}
endif;

/**
 * True if an the user can post a classified. If accounts are required, and reg is enabled, users can post (they signup at the same time).
 *
 * @return bool
 */
function classified_manager_user_can_post_classified() {
	$can_post = true;

	if ( ! is_user_logged_in() ) {
		if ( classified_manager_user_requires_account() && ! classified_manager_enable_registration() ) {
			$can_post = false;
		}
	}

	return apply_filters( 'classified_manager_user_can_post_classified', $can_post );
}

/**
 * True if an the user can edit a classified.
 *
 * @return bool
 */
function classified_manager_user_can_edit_classified( $classified_id ) {
	$can_edit = true;

	if ( ! is_user_logged_in() || ! $classified_id ) {
		$can_edit = false;
	} else {
		$classified      = get_post( $classified_id );

		if ( ! $classified || ( absint( $classified->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_post', $classified_id ) ) ) {
			$can_edit = false;
		}
	}

	return apply_filters( 'classified_manager_user_can_edit_classified', $can_edit, $classified_id );
}

/**
 * True if registration is enabled.
 *
 * @return bool
 */
function classified_manager_enable_registration() {
	return apply_filters( 'classified_manager_enable_registration', get_option( 'classified_manager_enable_registration' ) == 1 ? true : false );
}

/**
 * True if usernames are generated from email addresses.
 *
 * @return bool
 */
function classified_manager_generate_username_from_email() {
	return apply_filters( 'classified_manager_generate_username_from_email', get_option( 'classified_manager_generate_username_from_email' ) == 1 ? true : false );
}

/**
 * True if an account is required to post a classified.
 *
 * @return bool
 */
function classified_manager_user_requires_account() {
	return apply_filters( 'classified_manager_user_requires_account', get_option( 'classified_manager_user_requires_account' ) == 1 ? true : false );
}

/**
 * True if users are allowed to edit submissions that are pending approval.
 *
 * @return bool
 */
function classified_manager_user_can_edit_pending_submissions() {
	return apply_filters( 'classified_manager_user_can_edit_pending_submissions', get_option( 'classified_manager_user_can_edit_pending_submissions' ) == 1 ? true : false );
}

/**
 * Based on wp_dropdown_categories, with the exception of supporting multiple selected categories.
 * @see  wp_dropdown_categories
 */
function classified_manager_dropdown_categories( $args = '' ) {
	$defaults = array(
		'orderby'         => 'id',
		'order'           => 'ASC',
		'show_count'      => 0,
		'hide_empty'      => 1,
		'child_of'        => 0,
		'exclude'         => '',
		'echo'            => 1,
		'selected'        => 0,
		'hierarchical'    => 0,
		'name'            => 'cat',
		'id'              => '',
		'class'           => 'classified-manager-category-dropdown ' . ( is_rtl() ? 'chosen-rtl' : '' ),
		'depth'           => 0,
		'taxonomy'        => 'classified_listing_category',
		'value'           => 'id',
		'multiple'        => true,
		'show_option_all' => false,
		'placeholder'     => __( 'Choose a category&hellip;', 'classifieds-wp' ),
		'no_results_text' => __( 'No results match', 'classifieds-wp' ),
		'multiple_text'   => __( 'Select Some Options', 'classifieds-wp' ),
		'required'        => false,
	);

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	extract( $r );

	// Store in a transient to help sites with many cats
	$categories_hash = 'jm_cats_' . md5( json_encode( $r ) . WP_Classified_Manager_Cache_Helper::get_transient_version( 'jm_get_' . $r['taxonomy'] ) );
	$categories      = get_transient( $categories_hash );

	if ( empty( $categories ) ) {
		$categories = get_terms( $taxonomy, array(
			'orderby'         => $r['orderby'],
			'order'           => $r['order'],
			'hide_empty'      => $r['hide_empty'],
			'child_of'        => $r['child_of'],
			'exclude'         => $r['exclude'],
			'hierarchical'    => $r['hierarchical']
		) );
		set_transient( $categories_hash, $categories, DAY_IN_SECONDS * 30 );
	}

	$name       = esc_attr( $name );
	$class      = esc_attr( $class );
	$id         = $id ? esc_attr( $id ) : $name;

	$output = "<select name='" . esc_attr( $name ) . "[]' id='" . esc_attr( $id ) . "' class='" . esc_attr( $class ) . "' " . ( $multiple ? "multiple='multiple'" : '' ) . " data-placeholder='" . esc_attr( $placeholder ) . "' data-no_results_text='" . esc_attr( $no_results_text ) . "' data-multiple_text='" . esc_attr( $multiple_text ) . "'" . ( $required ? 'required' : '' ) . ">\n";

	if ( $show_option_all ) {
		$output .= '<option value="">' . esc_html( $show_option_all ) . '</option>';
	}

	if ( ! empty( $categories ) ) {
		include_once( WP_CLASSIFIED_MANAGER_PLUGIN_DIR . '/includes/class-wp-classified-manager-category-walker.php' );

		$walker = new WP_Classified_Manager_Category_Walker;

		if ( $hierarchical ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}

		$output .= $walker->walk( $categories, $depth, $r );
	}

	$output .= "</select>\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Get the page ID of a page if set, with PolyLang compat.
 * @param  string $page e.g. classified_dashboard, submit_classified_form, classifieds
 * @return int
 */
function classified_manager_get_page_id( $page ) {
	$page_id = get_option( 'classified_manager_' . $page . '_page_id', false );
	if ( $page_id ) {
		return absint( function_exists( 'pll_get_post' ) ? pll_get_post( $page_id ) : $page_id );
	} else {
		return 0;
	}
}

/**
 * Get the permalink of a page if set
 * @param  string $page e.g. classified_dashboard, submit_classified_form, classifieds
 * @return string|bool
 */
function classified_manager_get_permalink( $page ) {
	if ( $page_id = classified_manager_get_page_id( $page ) ) {
		return get_permalink( $page_id );
	} else {
		return false;
	}
}

/**
 * Filters the upload dir when $classified_manager_upload is true
 * @param  array $pathdata
 * @return array
 */
function classified_manager_upload_dir( $pathdata ) {
	global $classified_manager_upload, $classified_manager_uploading_file;

	if ( ! empty( $classified_manager_upload ) ) {
		$dir = apply_filters( 'classified_manager_upload_dir', 'classified-manager-uploads/' . sanitize_key( $classified_manager_uploading_file ), sanitize_key( $classified_manager_uploading_file ) );

		if ( empty( $pathdata['subdir'] ) ) {
			$pathdata['path']   = $pathdata['path'] . '/' . $dir;
			$pathdata['url']    = $pathdata['url'] . '/' . $dir;
			$pathdata['subdir'] = '/' . $dir;
		} else {
			$new_subdir         = '/' . $dir . $pathdata['subdir'];
			$pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
			$pathdata['subdir'] = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['subdir'] );
		}
	}

	return $pathdata;
}


/**
 * Prepare files for upload by standardizing them into an array. This adds support for multiple file upload fields.
 * @param  array $file_data
 * @return array
 */
function classified_manager_prepare_uploaded_files( $file_data ) {
	$files_to_upload = array();

	if ( is_array( $file_data['name'] ) ) {
		foreach( $file_data['name'] as $file_data_key => $file_data_value ) {
			if ( $file_data['name'][ $file_data_key ] ) {
				$type              = wp_check_filetype( $file_data['name'][ $file_data_key ] ); // Map mime type to one WordPress recognises
				$files_to_upload[] = array(
					'name'     => $file_data['name'][ $file_data_key ],
					'type'     => $type['type'],
					'tmp_name' => $file_data['tmp_name'][ $file_data_key ],
					'error'    => $file_data['error'][ $file_data_key ],
					'size'     => $file_data['size'][ $file_data_key ]
				);
			}
		}
	} else {
		$type              = wp_check_filetype( $file_data['name'] ); // Map mime type to one WordPress recognises
		$file_data['type'] = $type['type'];
		$files_to_upload[] = $file_data;
	}

	return $files_to_upload;
}

/**
 * Upload a file using WordPress file API.
 * @param  array $file_data Array of $_FILE data to upload.
 * @param  array $args Optional arguments
 * @return array|WP_Error Array of objects containing either file information or an error
 */
function classified_manager_upload_file( $file, $args = array() ) {
	global $classified_manager_upload, $classified_manager_uploading_file;

	include_once( ABSPATH . 'wp-admin/includes/file.php' );
	include_once( ABSPATH . 'wp-admin/includes/media.php' );

	$args = wp_parse_args( $args, array(
		'file_key'           => '',
		'file_label'         => '',
		'allowed_mime_types' => get_allowed_mime_types()
	) );

	$classified_manager_upload         = true;
	$classified_manager_uploading_file = $args['file_key'];
	$uploaded_file              = new stdClass();

	if ( ! in_array( $file['type'], $args['allowed_mime_types'] ) ) {
		if ( $args['file_label'] ) {
			return new WP_Error( 'upload', sprintf( __( '"%s" (filetype %s) needs to be one of the following file types: %s', 'classifieds-wp' ), $args['file_label'], $file['type'], implode( ', ', array_keys( $args['allowed_mime_types'] ) ) ) );
		} else {
			return new WP_Error( 'upload', sprintf( __( 'Uploaded files need to be one of the following file types: %s', 'classifieds-wp' ), implode( ', ', array_keys( $args['allowed_mime_types'] ) ) ) );
		}
	} else {
		$upload = wp_handle_upload( $file, apply_filters( 'submit_classified_wp_handle_upload_overrides', array( 'test_form' => false ) ) );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload', $upload['error'] );
		} else {
			$uploaded_file->url       = $upload['url'];
			$uploaded_file->file      = $upload['file'];
			$uploaded_file->name      = basename( $upload['file'] );
			$uploaded_file->type      = $upload['type'];
			$uploaded_file->size      = $file['size'];
			$uploaded_file->extension = substr( strrchr( $uploaded_file->name, '.' ), 1 );
		}
	}

	$classified_manager_upload         = false;
	$classified_manager_uploading_file = '';

	return $uploaded_file;
}

/**
 * Calculate and return the classified expiry date
 * @param  int $classified_id
 * @return string
 */
function calculate_classified_expiry( $classified_id ) {
	// Get duration from the product if set...
	$duration = get_post_meta( $classified_id, '_classified_duration', true );

	// ...otherwise use the global option
	if ( ! $duration ) {
		$duration = absint( get_option( 'classified_manager_submission_duration' ) );
	}

	if ( $duration ) {
		return date( 'Y-m-d', strtotime( "+{$duration} days", current_time( 'timestamp' ) ) );
	}

	return '';
}

/**
 * Set the current language of the ajax request
 * @param  string $lang
 * @return string
 */
function classified_manager_set_ajax_language( $lang ) {
    if ( ( strstr( $_SERVER['REQUEST_URI'], '/jm-ajax/' ) || ! empty( $_GET['jm-ajax'] ) ) && isset( $_POST['lang'] ) ) {
		$lang = sanitize_text_field( $_POST['lang'] );
	}
    return $lang;
}

/**
 * Enables/disabled the media viewer.
 *
 * @since 1.1.
 */
function classified_manager_enable_media_viewer() {

	if ( ! is_user_logged_in() )  {
		return false;
	}

	$num_images = (int) get_option('classified_manager_num_images');

	return apply_filters( 'classified_manager_enable_media_viewer', $num_images > 1 );
}


if ( ! function_exists('html') ) {
/**
 * Generate an HTML tag. Attributes are escaped. Content is NOT escaped.
 *
 * @since 1.1.
 *
 * @param string $tag
 *
 * @return string
 */
function html( $tag ) {
	static $SELF_CLOSING_TAGS = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta' );

	$args = func_get_args();

	$tag = array_shift( $args );

	if ( is_array( $args[0] ) ) {
		$closing = $tag;
		$attributes = array_shift( $args );
		foreach ( $attributes as $key => $value ) {
			if ( false === $value ) {
				continue;
			}

			if ( true === $value ) {
				$value = $key;
			}

			$tag .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
	} else {
		list( $closing ) = explode( ' ', $tag, 2 );
	}

	if ( in_array( $closing, $SELF_CLOSING_TAGS ) ) {
		return "<{$tag} />";
	}

	$content = implode( '', $args );

	return "<{$tag}>{$content}</{$closing}>";
}
}