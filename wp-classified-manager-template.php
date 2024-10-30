<?php
/**
 * Template Functions
 *
 * Template functions specifically created for classified listings
 *
 * @author 		Classifieds WP
 * @category 	Core
 * @package 	Classifieds WP/Template
 * @version     1.0
 */

add_action( 'single_classified_listing_start', 'classified_listing_header_display', 20 );
add_action( 'single_classified_listing_start', 'classified_listing_meta_display', 30 );
add_action( 'single_classified_listing_header_end', 'classified_manager_images_gallery_display', 15 );
add_filter( 'body_class', 'classified_manager_body_class' );


/**
 * Get and include template files.
 *
 * @param mixed $template_name
 * @param array $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return void
 */
function get_classified_manager_template( $template_name, $args = array(), $template_path = 'classifieds-wp', $default_path = '' ) {
	if ( $args && is_array( $args ) ) {
		extract( $args );
	}
	include( locate_classified_manager_template( $template_name, $template_path, $default_path ) );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *		yourtheme		/	$template_path	/	$template_name
 *		yourtheme		/	$template_name
 *		$default_path	/	$template_name
 *
 * @param string $template_name
 * @param string $template_path (default: 'classified_manager')
 * @param string|bool $default_path (default: '') False to not load a default
 * @return string
 */
function locate_classified_manager_template( $template_name, $template_path = 'classifieds-wp', $default_path = '' ) {

	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			// backwards compat.
			trailingslashit( 'classified_manager' ) . $template_name,
			$template_name
		)
	);

	// Get default template
	if ( ! $template && $default_path !== false ) {
		$default_path = $default_path ? $default_path : WP_CLASSIFIED_MANAGER_PLUGIN_DIR . '/templates/';
		if ( file_exists( trailingslashit( $default_path ) . $template_name ) ) {
			$template = trailingslashit( $default_path ) . $template_name;
		}
	}

	// Return what we found
	return apply_filters( 'classified_manager_locate_template', $template, $template_name, $template_path );
}

/**
 * Get template part (for templates in loops).
 *
 * @param string $slug
 * @param string $name (default: '')
 * @param string $template_path (default: 'classified_manager')
 * @param string|bool $default_path (default: '') False to not load a default
 */
function get_classified_manager_template_part( $slug, $name = '', $template_path = 'classifieds-wp', $default_path = '' ) {
	$template = '';

	if ( $name ) {
		$template = locate_classified_manager_template( "{$slug}-{$name}.php", $template_path, $default_path );
	}

	// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/classified_manager/slug.php
	if ( ! $template ) {
		$template = locate_classified_manager_template( "{$slug}.php", $template_path, $default_path );
	}

	if ( $template ) {
		load_template( $template, false );
	}
}

/**
 * Custom 'get_sidebar()' function to include the plugin sidebars.
 *
 * @since 1.2
 */
function get_classified_manager_sidebar( $name = null ) {
	/**
	 * Fires before the sidebar template file is loaded.
	 *
	 * The hook allows a specific sidebar template file to be used in place of the
	 * default sidebar template file. If your file is called sidebar-new.php,
	 * you would specify the filename in the hook as get_sidebar( 'new' ).
	 *
	 * @param string $name Name of the specific sidebar file to use.
	 */
	do_action( 'get_classified_manager_sidebar', $name );

	$sidebar = 'sidebar-classified_listing.php';

	$name = (string) $name;
	if ( '' !== $name ) {
		$sidebar = "sidebar-{$name}.php";
	}

	get_classified_manager_template( $sidebar );
}

/**
 * Add custom body classes
 * @param  array $classes
 * @return array
 */
function classified_manager_body_class( $classes ) {
	$classes   = (array) $classes;
	$classes[] = sanitize_title( wp_get_theme() );

	return array_unique( $classes );
}

/**
 * Get classifieds pagination for [classifieds] shortcode
 * @return [type] [description]
 */
function get_classified_listing_pagination( $max_num_pages, $current_page = 1 ) {
	ob_start();
	get_classified_manager_template( 'classified-pagination.php', array( 'max_num_pages' => $max_num_pages, 'current_page' => absint( $current_page ) ) );
	return ob_get_clean();
}

/**
 * Outputs the classifieds status
 *
 * @return void
 */
function the_classified_status( $post = null ) {
	echo get_the_classified_status( $post );
}

/**
 * Gets the classifieds status
 *
 * @return string
 */
function get_the_classified_status( $post = null ) {
	$post     = get_post( $post );
	$status   = $post->post_status;
	$statuses = get_classified_listing_post_statuses();

	if ( isset( $statuses[ $status ] ) ) {
		$status = $statuses[ $status ];
	} else {
		$status = __( 'Inactive', 'classifieds-wp' );
	}

	return apply_filters( 'the_classified_status', $status, $post );
}

/**
 * Return whether or not the position has been marked as unavailable
 *
 * @param  object $post
 * @return boolean
 */
function is_classified_unavailable( $post = null ) {
	$post = get_post( $post );
	return $post->_classified_unavailable ? true : false;
}

/**
 * Return whether or not the position has been featured
 *
 * @param  object $post
 * @return boolean
 */
function is_listing_featured( $post = null ) {
	$post = get_post( $post );
	return $post->_featured ? true : false;
}

/**
 * Return whether or not contacts are allowed
 *
 * @param  object $post
 * @return boolean
 */
function users_can_contact( $post = null ) {
	$post = get_post( $post );
	return apply_filters( 'classified_manager_users_can_contact', ( ! is_classified_unavailable() && ! in_array( $post->post_status, array( 'preview', 'expired' ) ) ), $post );
}

/**
 * the_classified_permalink function.
 *
 * @access public
 * @return void
 */
function the_classified_permalink( $post = null ) {
	echo get_the_classified_permalink( $post );
}

/**
 * get_the_classified_permalink function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return string
 */
function get_the_classified_permalink( $post = null ) {
	$post = get_post( $post );
	$link = get_permalink( $post );

	return apply_filters( 'the_classified_permalink', $link, $post );
}

/**
 * get_the_classified_contact_method function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return object
 */
function get_the_classified_contact_method( $post = null ) {
	$post = get_post( $post );

	if ( $post && $post->post_type !== 'classified_listing' ) {
		return;
	}

	$method = new stdClass();
	$contact  = $post->_classified_contact;

	if ( empty( $contact ) )
		return false;

	if ( strstr( $contact, '@' ) && is_email( $contact ) ) {
		$method->type      = 'email';
		$method->raw_email = $contact;
		$method->email     = antispambot( $contact );
		$method->subject   = apply_filters( 'classified_manager_contact_email_subject', sprintf( __( 'RE: "%s" listing on %s', 'classifieds-wp' ), $post->post_title, home_url() ), $post );
	} else {
		$method->type = 'phone';
		$method->url  = $contact;
	}

	return apply_filters( 'the_classified_contact_method', $method, $post );
}
/**
 * the_classified_type function.
 *
 * @access public
 * @return void
 */
function the_classified_type( $post = null ) {
	if ( $classified_type = get_the_classified_type( $post ) ) {
		echo $classified_type->name;
	}
}

/**
 * get_the_classified_type function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_the_classified_type( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'classified_listing' ) {
		return;
	}

	$types = wp_get_post_terms( $post->ID, 'classified_listing_type' );

	if ( $types ) {
		$type = current( $types );
	} else {
		$type = false;
	}

	return apply_filters( 'the_classified_type', $type, $post );
}


/**
 * the_classified_location function.
 * @param  boolean $map_link whether or not to link to google maps
 * @return [type]
 */
function the_classified_location( $map_link = true, $post = null ) {
	$location = get_the_classified_location( $post );

	if ( $location ) {
		if ( $map_link ) {
			// If linking to google maps, we don't want anything but text here
			echo apply_filters( 'the_classified_location_map_link', '<a class="google_map_link" href="' . esc_url( 'http://maps.google.com/maps?q=' . urlencode( strip_tags( $location ) ) . '&zoom=14&size=512x512&maptype=roadmap&sensor=false' ) . '" target="_blank">' . esc_html( strip_tags( $location ) ) . '</a>', $location, $post );
		} else {
			echo wp_kses_post( $location );
		}
	} else {
		echo wp_kses_post( apply_filters( 'the_classified_location_anywhere_text', __( 'Anywhere', 'classifieds-wp' ) ) );
	}
}

/**
 * get_the_classified_location function.
 *
 * @access public
 * @param mixed $post (default: null)
 * @return void
 */
function get_the_classified_location( $post = null ) {
	$post = get_post( $post );
	if ( $post->post_type !== 'classified_listing' ) {
		return;
	}

	return apply_filters( 'the_classified_location', $post->_classified_location, $post );
}

/**
 * Outputs the featured image.
 *
 * @param string $size    (default: 'full')
 * @param string $as_link (default: 'true')
 * @param mixed $default  (default: null)
 * @return void
 */
function the_classified_featured_image( $size = 'full', $as_link = true, $default = null, $post = null ) {
	$id = get_the_classified_featured_image( $post, $size );

	if ( $id ) {

		$image = wp_get_attachment_image( $id, $size, false, array( 'class' => 'classified_featured_image' ) );

		if ( $as_link ) {

			$atts = array(
				'href' => esc_url( wp_get_attachment_image_url( $id, 'full' ) ),
				'rel'  => 'lightbox[classified-gallery]',
			);
			echo html( 'a', $atts, $image );

		} else {
			echo $image;
		}

	} elseif ( $default ) {
		echo '<img class="classified_featured_image" src="' . esc_attr( $default ) . '" />';
	} else {
		echo '<img class="classified_featured_image" src="' . esc_attr( apply_filters( 'classified_manager_default_classified_featured_image', WP_CLASSIFIED_MANAGER_PLUGIN_URL . '/assets/images/placeholder.png' ) ) . '" />';
	}

}

/**
 * Output the listing images. Excludes the featured image from the images list by default.
 */
function the_classified_images( $post_id = 0, $size = 'thumbnail', $exclude_featured = true, $atts = array() ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$featured_id = get_post_thumbnail_id( $post_id );

	$args           = apply_filters( 'classified_manager_image_gallery_query_args', array( 'post__not_in' => array( $featured_id ) ) );
	$attachment_ids = classified_manager_mv_get_post_attachments( $post_id, $args );

	$defaults = array(
		'show_description' => false,
		'rel'              => 'lightbox[classified-gallery]'
	);
	$atts = wp_parse_args( $atts, $defaults );

	classified_manager_mv_output_attachments( $attachment_ids, $size, $atts );
}

/**
 * Get the featured image for a classified listing.
 *
 * @param mixed $post (default: null)
 * @param string $size
 * @return int
 */
function get_the_classified_featured_image( $post = null, $size = 'full' ) {

	$post = get_post( $post );

	if ( $post->post_type !== 'classified_listing' ) {
		return;
	}

	$image = get_post_thumbnail_id( $post->ID );

	return apply_filters( 'the_classified_featured_image', $image, $post );
}

/**
 * get_the_classified_website function.
 *
 * @access public
 * @param int $post (default: null)
 * @return void
 */
function get_the_classified_website( $post = null ) {
	$post = get_post( $post );

	if ( $post->post_type !== 'classified_listing' )
		return;

	$website = $post->_classified_website;

	if ( $website && ! strstr( $website, 'http:' ) && ! strstr( $website, 'https:' ) ) {
		$website = 'http://' . $website;
	}

	return apply_filters( 'the_classified_website', $website, $post );
}

/**
 * Display or retrieve the current classified price with optional content.
 *
 * @access public
 * @param mixed $id (default: null)
 * @return void
 */
function the_classified_price( $before = '', $after = '', $echo = true, $post = null ) {
	$classified_price = get_the_classified_price( $post );
	$classified_currency = get_option( 'classified_manager_listing_currency' );

	if ( strlen( $classified_price ) == 0 )
		return;

	$classified_price = esc_attr( strip_tags( $classified_price ) );
	$classified_price = $before . $classified_currency . ' ' . $classified_price . $after;

	if ( $echo )
		echo $classified_price;
	else
		return $classified_price;
}

/**
 * get_the_classified_price function.
 *
 * @access public
 * @param int $post (default: 0)
 * @return void
 */
function get_the_classified_price( $post = null ) {
	$post = get_post( $post );

	if ( $post->post_type !== 'classified_listing' )
		return;

	return apply_filters( 'the_classified_price', $post->_classified_price, $post );
}

/**
 * classified_listing_class function.
 *
 * @access public
 * @param string $class (default: '')
 * @param mixed $post_id (default: null)
 * @return void
 */
function classified_listing_class( $class = '', $post_id = null ) {
	// Separates classes with a single space, collates classes for post DIV
	echo 'class="' . join( ' ', get_classified_listing_class( $class, $post_id ) ) . '"';
}

/**
 * get_classified_listing_class function.
 *
 * @access public
 * @return array
 */
function get_classified_listing_class( $class = '', $post_id = null ) {
	$post = get_post( $post_id );

	if ( $post->post_type !== 'classified_listing' ) {
		return array();
	}

	$classes = array();

	if ( empty( $post ) ) {
		return $classes;
	}

	$classes[] = 'classified_listing';
	if ( $classified_type = get_the_classified_type() ) {
		$classes[] = 'classified-type-' . sanitize_title( $classified_type->name );
	}

	if ( is_classified_unavailable( $post ) ) {
		$classes[] = 'classified_classified_unavailable';
	}

	if ( is_listing_featured( $post ) ) {
		$classes[] = 'classified_listing_featured';
	}

	if ( ! empty( $class ) ) {
		if ( ! is_array( $class ) ) {
			$class = preg_split( '#\s+#', $class );
		}
		$classes = array_merge( $classes, $class );
	}

	return get_post_class( $classes, $post->ID );
}

/**
 * Displays listing header data on the single classifieds page.
 */
function classified_listing_header_display() {
	get_classified_manager_template( 'content-single-classified_listing-header.php', array() );
}


/**
 * Displays classified meta data on the single classified page
 */
function classified_listing_meta_display() {
	get_classified_manager_template( 'content-single-classified_listing-meta.php', array() );
}

/**
 *  Displays the image gallery template.
 *
 * @since 1.1
 */
function classified_manager_images_gallery_display() {
	global $classified_preview;

	get_classified_manager_template( 'content-single-classified_listing-gallery.php', array( 'classified_preview' => $classified_preview ) );
}
