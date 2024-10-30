<?php
/**
 * Provides a frontend media viewer using the native WordPress media browser.
 *
 * Based on the work by the good folks at AppThemes (http://www.appthemes.com).
 */

define( 'CLASSIFIED_MANAGER_VIEWER_VERSION', '1.0' );
define( 'CLASSIFIED_MANAGER_ATTACHMENT_FILE', 'file' );		// DEFAULT - meta type assigned to attachments
define( 'CLASSIFIED_MANAGER_ATTACHMENT_GALLERY', 'gallery' );  // suggested meta type for image attachments that are displayed as gallery images
define( 'CLASSIFIED_MANAGER_ATTACHMENT_EMBED', 'embed' );		// suggested meta type for embeds


/**
 * Outputs the media manager UI.
 */
function wp_classified_manager_ui( $post_id, $atts = array(), $filters = array() ) {

	$defaults = array(
		'id' => '_classified-images',
		'attachment_params' => array(
			'rel' => 'lightbox[classified-gallery]',
		),
	);
	$atts = wp_parse_args( $atts, $defaults );

	$defaults = array(
		'file_limit'  => (int) get_option( 'classified_manager_num_images' ),
		'file_size'   => (int) get_option( 'classified_manager_max_image_size' ) * 1024,
	);
	$filters = wp_parse_args( $filters, $defaults );

	// Temporary fix for media viewer "bug" that causes the upload button to stop
	// working on themes that specify 'border-collapse' differently.
	?><style type="text/css">table { border-collapse: separate; }</style><?php

	classified_manager_media_viewer( $post_id, $atts, $filters );
}


// __Main Class.

/**
 * The core media viewer class.
 */
class WP_Classified_Manager_Media_Viewer {

	/**
	 * The template used for displaying the media manager UI.
	 * @var string
	 */
	protected static $template;

	/**
	 * The plugin dir.
	 * @var string
	 */
	protected static $plugin_dir;

	/**
	 * The plugin URL.
	 * @var string
	 */
	protected static $plugin_uri;

	/**
	 * The media manager input name for storing id's.
	 * @var string
	 */
	protected static $attach_ids_inputs = '_classified_manager_attach_ids_fields';

	/**
	 * The media manager input name for storing URL's.
	 * @var string
	 */
	protected static $embed_url_inputs  = '_classified_manager_embed_urls_fields';

	/**
	 * The media manager default filters.
	 * @var string
	 */
	protected static $default_filters;

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'media_capabilities' ), 15, 4 );
		add_action( 'classified_manager_media_viewer', array( __CLASS__, 'output_hidden_inputs' ), 10, 5 );
		add_action( 'ajax_query_attachments_args', array( __CLASS__, 'restrict_media_library' ), 5 );
		add_action( 'wp_ajax_classified_manager_mv_manage_files', array( __CLASS__ , 'ajax_refresh_attachments' ) );
		add_action( 'wp_ajax_nopriv_classified_manager_mv_manage_files', array( __CLASS__ , 'ajax_refresh_attachments' ) );
		add_action( 'wp_ajax_classified_manager_mv_get_options', array( __CLASS__, 'ajax_set_media_viewer_session' ) );
		add_action( 'wp_ajax_nopriv_classified_manager_mv_get_options', array( __CLASS__, 'ajax_set_media_viewer_session' ) );
		add_action( 'wp_ajax_classified_manager_delete_media_viewer_transients', array( __CLASS__, 'ajax_delete_transients' ) );
		add_action( 'wp_ajax_nopriv_classified_manager_delete_media_viewer_transients', array( __CLASS__, 'ajax_delete_transients' ) );
		add_action( 'add_attachment', array( __CLASS__, 'set_attachment_mv_id' ) );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'validate_upload_restrictions' ) );
	}

	function __construct() {
		$this->init_hooks();

		self::$plugin_dir  = WP_CLASSIFIED_MANAGER_PLUGIN_DIR;
		self::$plugin_uri  = WP_CLASSIFIED_MANAGER_PLUGIN_URL;

		$template = WP_CLASSIFIED_MANAGER_PLUGIN_DIR . '/templates/form-fields/wp-media-viewer-field.php';
		self::$template = apply_filters( 'classified_manager_locate_template', $template, 'classified_manager' );

		$params = classified_manager_mv_get_args();

		extract( $params );

		self::$default_filters = $params;
	}

	/**
	 * Enqueues the JS scripts that output WP's media viewer.
	 */
	static function enqueue_media_viewer( $ids, $localization = array() ) {

		$ext = ( ! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG ? '.min' : '' )  . '.js';

		wp_register_script(
			'classified_manager-media-viewer',
			self::$plugin_uri . '/assets/js/media-viewer' . $ext,
			array( 'jquery' ),
			CLASSIFIED_MANAGER_VIEWER_VERSION,
			true
		);

		$options = array();

		if ( ! empty( $ids ) ) {

			foreach( $ids as $id ) {
				$options[ $id ] = classified_manager_get_media_viewer_options( $id );
			}

		}

		$defaults = array(
			'post_id'                     => 0,
			'post_id_field'               => '',
			'ajaxurl'                     => admin_url( 'admin-ajax.php', 'relative' ),
			'ajax_nonce'                  => wp_create_nonce( 'classified_manager-media-viewer' ),
			'files_limit_text'            => __( 'Allowed images', 'classifieds-wp' ),
			'files_type_text'             => __( 'Allowed file types', 'classifieds-wp' ),
			'insert_media_title'          => __( 'Insert Media', 'classifieds-wp' ),
			'embed_media_title'           => __( 'Insert from URL', 'classifieds-wp' ),
			'file_size_text'              => __( 'Maximum upload image size', 'classifieds-wp' ),
			'embed_limit_text'            => __( 'Allowed embeds', 'classifieds-wp' ),
			'clear_embeds_text'           => __( 'Clear Embeds (clears any previously added embeds)', 'classifieds-wp' ),
			'allowed_embeds_reached_text' => __( 'No more embeds allowed!', 'classifieds-wp' ),
			'embeds_not_allowed_text'     => __( 'Embeds are not allowed!', 'classifieds-wp' ),
			'files_limit_reached_text'    => __( "The number of images exceed the allowed limit.\n\nThe remaining images will be ignored.", 'classifieds-wp' ),
			'embed_limit_reached_text'    => __( "The number of embeds exceed the allowed limit.\n\nThe remaining mebds will be ignored.", 'classifieds-wp' ),
			'options'                     => json_encode( $options ),
			'spinner'                     => esc_url( admin_url( 'images/spinner-2x.gif' ) ),
		);
		$localization = wp_parse_args( $localization, $defaults );

		wp_localize_script( 'classified_manager-media-viewer', 'classified_manager_viewer_i18n', $localization );

		wp_enqueue_script( 'classified_manager-media-viewer' );

		wp_enqueue_media();
	}

	/**
	 * Outputs the media manager HTML markup.
	 *
	 * @uses do_action() Calls 'classified_manager_media_viewer'
	 *
	 */
	static function output_media_manager( $object_id = 0, $atts = array(), $filters = array() ) {

		// Make sure we have a unique ID for each outputted file manager.
		if ( empty( $atts['id'] ) ) {
			$attach_field_id = uniqid('id');
		} else {
			$attach_field_id = $atts['id'];
		}

		// Parse the custom filters for the outputted media manager.
		$filters = wp_parse_args( apply_filters( 'classified_media_manager_filters', $filters ), self::$default_filters );

		// Allow using 'meta_type' or 'file_meta_type' as filter name.
		if ( ! empty( $filters['meta_type'] ) ) {
			$filters['file_meta_type'] = $filters['meta_type'];
		}

		// Media manager fieldset attributes.
		$defaults = array(
			'id'                => $attach_field_id,
			'object'            => 'post',
			'class'             => 'files',
			'title'             => '',
			'upload_text'       => __( 'Add Media', 'classifieds-wp' ),
			'manage_text'       => __( 'Manage Media', 'classifieds-wp' ),
			'no_media_text'     => __( 'No media added yet', 'classifieds-wp' ),
			'attachment_ids'    => '',
			'embeds_attach_ids' => '',
			'exclude_ids'       => '',
			'embed_urls'        => '',
			'attachment_params' => array(),
			'embed_params'      => array(),
		);
		$atts = wp_parse_args( apply_filters( 'classified_media_manager_atts', $atts ), $defaults );

		if ( ! empty( $filters['mime_types'] ) ) {

			// Extract, correct and flatten the mime types.
			if ( ! is_array( $filters['mime_types'] ) ) {

				// Keep the original required mime types to display to the user.
				$filters['file_types'] = $filters['mime_types'];

				$mime_types = explode( ',', $filters['mime_types'] );
			} else {
				$mime_types = $filters['mime_types'];

				// Keep the original required mime types to display to the user.
				$filters['file_types'] = implode( ',', $filters['mime_types'] );
			}
			$mime_types = classified_manager_mv_get_mime_types_for( $mime_types );
			$filters['mime_types'] = implode( ',', $mime_types );
		}

		if ( empty( $atts['attachment_ids'] ) && $object_id ) {

			if ( 'post' === $atts['object'] ) {
				$attachment_ids = get_post_meta( $object_id, $attach_field_id, true );

				if ( ! empty( $attachment_ids ) ) {

					// Check if the attachments stored in meta are still valid by querying the DB to retrieve all the valid ID's.
					$args = array(
						'fields'   => 'ids',
						'post__in' => $attachment_ids,
						'orderby'  => 'post__in'
					);
					$atts['attachment_ids'] = self::get_post_attachments( $object_id, $args );

					// Refresh the post meta.
					if ( ! empty( $atts['attachment_ids'] ) ) {
						update_post_meta( $object_id, $attach_field_id, $atts['attachment_ids'] );
					}

				}

			} else {
				$atts['attachment_ids'] = get_user_meta( $object_id, $attach_field_id, true );
			}

			if ( ! empty( $atts['attachment_ids'] ) && ! empty( $atts['exclude_ids'] ) ) {
				$atts['attachment_ids'] = array_diff( $atts['attachment_ids'], (array) $atts['exclude_ids'] );
			}

		}

		// Get all the embeds for the current post ID, if editing a post.
		if ( empty( $atts['embed_urls'] ) && $object_id && 'post' == $atts['object'] ) {

			if ( 'post' == $atts['object'] ) {
				$embeds_attach_ids = get_post_meta( $object_id, $attach_field_id .'_embeds', true );

				if ( ! empty( $embeds_attach_ids ) ) {

					// Check if the attachments stored in meta are still valid by querying the DB to retrieve all the valid ID's.
					$args = array(
						'meta_value' => classified_manager_mv_get_allowed_meta_types('embed'),
						'post__in'   => $embeds_attach_ids,
					);

					$curr_embed_attachments = self::get_post_attachments( $object_id, $args );

					if ( ! empty( $curr_embed_attachments ) ) {
						$atts['embed_urls'] = wp_list_pluck( $curr_embed_attachments, 'guid' );
						$embeds_attach_ids  = wp_list_pluck( $curr_embed_attachments, 'ID' );

						// refresh the post meta
						update_post_meta( $object_id,  $attach_field_id .'_embeds', array_keys( $embeds_attach_ids ) );
					}

				}

			} else {
				$embeds_attach_ids = get_user_meta( $object_id, $attach_field_id .'_embeds', true );
			}

			if ( ! empty( $embeds_attach_ids ) ) {
				$atts['embeds_attach_ids'] = $embeds_attach_ids;
			}

		}

		$atts['button_text'] = ( ! empty( $atts['attachment_ids'] ) ? $atts['manage_text'] : $atts['upload_text']  );

		$no_media = ! ( (int) $atts['attachment_ids'] + (int) $atts['embeds_attach_ids'] );

		if ( self::$template ) {
			require self::$template;
		} else {

			$located = locate_template( 'wp-media-viewer-field.php' );

			if ( ! $located ) {
				trigger_error('Could not locate template for the media viewer');
				return;
			}

		}

		$options = array(
			'attributes' => $atts,
			'filters'    => $filters
		);

		update_option( "classified_manager_media_viewer_{$attach_field_id}", $options );

		do_action( 'classified_manager_media_viewer', $attach_field_id, $atts['attachment_ids'], $atts['embeds_attach_ids'], $atts['embed_urls'], $filters );
	}

	/**
	 * Process all posted inputs that contain attachment ID's that need to be assigned to a post or user.
	 */
	static function handle_media_upload( $object_id, $type = 'post', $fields = array(), $duplicate = false ) {

		$attach_ids_inputs = self::$attach_ids_inputs;
		$embed_url_inputs  = self::$embed_url_inputs;

		if ( ! $fields ) {
			if ( isset( $_POST[ $attach_ids_inputs ] ) ) {
				$fields['attachs'] = $_POST[ $attach_ids_inputs ];
			}

			if ( isset( $_POST[ $embed_url_inputs ] ) ) {
				$fields['embeds'] = $_POST[ $embed_url_inputs ];
			}
		}

		if ( empty( $fields ) ) {
			return;
		}

		$attachs = array();

		// Handle normal attachments.
		foreach( (array) $fields['attachs'] as $field ) {
			$media = self::handle_media_field( $object_id, $field, $type, $duplicate );
			if ( ! empty( $media ) ) {
				$attachs = array_merge( $media, $attachs );
			}
		}

		// Handle embed attachments.
		foreach( (array) $fields['embeds'] as $field ) {
			$media = self::handle_embed_field( $object_id, $field, $type );
			if ( ! empty( $media ) ) {
				$attachs = array_merge( $media, $attachs );
			}
		}

		// Clear previous attachments by checking if they are present on the updated attachments list.
		if ( 'post' == $type ) {
			self::maybe_clear_old_attachments( $object_id, $attachs );
		}

	}

	/**
	 * Handles embedded media related posted data and retrieves an updated list of all the embed attachments for the current object.
	 *
	 * @uses do_action() Calls 'classified_manager_mv_handle_embed_field'
	 *
	 */
	private static function handle_embed_field( $object_id, $field, $type = 'post' ) {

		// User cleared the embeds.
		if ( empty( $_POST[ $field ] ) ) {

			// Delete the embed url's from the user/post meta.
			delete_metadata( $type, $object_id, $field );
			$media = array();

		} else {

			$embeds = explode( ',', wp_strip_all_tags( $_POST[ $field ] ) );

			foreach( $embeds as $embed ) {

				$embed = trim( $embed );

				// Try to get all the meta data from the embed URL to populate the attachment 'post_mime_type'.
				// The 'post_mime_type' is stored in the following format: <mime type>/<provider-name>-iframe-embed ( e.g: video/youtube-iframe-embed, video/vimeo-iframe-embed, etc ).
				// If the provider is not recognized by WordPress the 'post_mime_type' will default to <mime type>/iframe-embed ( e.g: video/iframe-embed ).
				$oembed = self::get_oembed_object( $embed );

				$iframe_type = ( ! empty( $oembed->provider_name ) ? strtolower( $oembed->provider_name ) . '-' : '' ) . 'iframe-embed';
				$type = ( ! empty( $oembed->type ) ? $oembed->type : 'unknown' );
				$title = ( ! empty( $oembed->title ) ? $oembed->title : __( 'Unknown', 'classifieds-wp' ) );

				$attachment = array(
					'post_title'     => $title,
					'post_content'   => $embed,
					'post_parent'    => $object_id, // treating WP bug https://core.trac.wordpress.org/ticket/29646
					'guid'           => $embed,
					'post_mime_type' => sprintf( '%1s/%2s', $type, $iframe_type ),
				);

				// Assign the embed URL to the object as a normal file attachment.
				$attach_id = wp_insert_attachment( $attachment, '', $object_id );

				if ( is_wp_error( $attach_id ) ) {
					continue;
				}

				$media[] = (int) $attach_id;

				if ( isset( $_POST[ $field . '_meta_type' ] ) && in_array( $_POST[ $field .'_meta_type' ], classified_manager_mv_get_allowed_meta_types('embed') ) ) {
					$meta_type = $_POST[ $field .'_meta_type' ];
				} else {
					$meta_type = CLASSIFIED_MANAGER_ATTACHMENT_EMBED;
				}

				update_post_meta( $attach_id, '_classified_manager_attachment_type', $meta_type );
			}

			// Store the embed url's on the user/post meta.
			if ( 'user' === $type ) {
				update_user_meta( $object_id, $field, $media );
			} else {
				update_post_meta( $object_id, $field, $media );
			}

		}

		do_action( 'classified_manager_mv_handle_embed_field', $object_id, $field, $type );

		return $media;
	}

	/**
	 * Handles media related posted data and retrieves an updated list of all the attachments for the current object.
	 *
	 * @uses do_action() Calls 'classified_manager_mv_handle_media_field'
	 *
	 * @todo: maybe set '$duplicate' param to 'true' by default
	 */
	private static function handle_media_field( $object_id, $field, $type = 'post', $duplicate = false ) {

		// User cleared the attachments.
		if ( empty( $_POST[ $field ] ) ) {

			// Delete the attachments from the user/post meta.
			delete_metadata( $type, $object_id, $field );
			$media = array();

		} else {

			$attachments = explode( ',', wp_strip_all_tags( $_POST[ $field ] ) );

			foreach( $attachments as $attachment_id ) {

				$attachment = get_post( $attachment_id );

				if ( $attachment->post_parent != $object_id && 'post' == $type ) {

					$attachment->post_date = '';
					$attachment->post_date_gmt = '';

					$filename = get_attached_file( $attachment_id );
					$generate_meta = false;

					// If '$duplicate' is set to TRUE and the attachment already has a parent, clone it and assign it to the post.
					// Otherwise, the attachment will not change and will simply change parents.
					if ( $duplicate && $attachment->post_parent ) {
						$attachment->ID = 0;
						$generate_meta = true;
					}

					// Treating WP bug https://core.trac.wordpress.org/ticket/29646.
					$attachment->post_parent = $object_id;

					// Update the attachment.
					$attach_id = wp_insert_attachment( $attachment, $filename, $object_id );
					if ( is_wp_error( $attach_id ) ) {
						continue;
					}

					if ( $generate_meta ) {
						// Include the 'wp_generate_attachment_metadata()' dependency file.
						require_once( ABSPATH . 'wp-admin/includes/image.php' );

						// Generate the metadata for the cloned attachment, and update the database record.
						wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $filename ) );
					}


				} else {
					$attach_id = $attachment_id;
				}

				if ( isset( $_POST[ $field .'_meta_type' ] ) && in_array( $_POST[ $field .'_meta_type' ], classified_manager_mv_get_allowed_meta_types('file') ) ) {
					$meta_type = $_POST[ $field .'_meta_type' ];
				} else {
					$meta_type = CLASSIFIED_MANAGER_ATTACHMENT_FILE;
				}

				$media[] = (int) $attach_id;

				update_post_meta( $attach_id, '_classified_manager_attachment_type', $meta_type );
			}

			// Store the attachments on the user/post meta.
			if ( 'user' == $type ) {
				update_user_meta( $object_id, $field, $media );
			} else {
				update_post_meta( $object_id, $field, $media );
			}

		}

		do_action( 'classified_manager_mv_handle_media_field', $object_id, $field, $type );

		return $media;
	}

	/**
	 * Outputs the hidden inputs that act as helpers for the media manager JS.
	 */
	static function output_hidden_inputs( $attach_field_id, $attachment_ids, $attachment_embed_ids, $embed_urls, $filters ) {

		$embeds_input        = $attach_field_id . '_embeds';
		$attach_embeds_input = $attach_field_id . '_attach_embeds';

		echo '<div class="classified-manager-media-viewer-helper">';

		// Input for the media manager unique nonce.
		wp_nonce_field( "classified_manager_mv_nonce_{$attach_field_id}", "classified_manager_mv_nonce_{$attach_field_id}" );

		// Input for the attachment ID's selected by the user in the media manager.
		echo html( 'input', array( 'name' => $attach_field_id, 'type' => 'hidden', 'value' => implode( ',', (array) $attachment_ids ) ) );

		// Input with all the field names that contain attachment ID's.
		echo html( 'input', array( 'name' => self::$attach_ids_inputs.'[]','type' => 'hidden', 'value' => $attach_field_id ) );

		// Input for the embeds attachment ID's selected by the user in the media manager.
		echo html( 'input', array( 'name' => $attach_embeds_input, 'type' => 'hidden', 'value' => implode( ',', (array) $attachment_embed_ids ) ) );

		// Input for the embed URL's selected by the user in the media manager.
		echo html( 'input', array( 'name' => $embeds_input, 'type' => 'hidden', 'value' => implode( ',', (array) $embed_urls ) ) );

		// Input with all the field names that contain embed URL's.
		echo html( 'input', array( 'name' => self::$embed_url_inputs.'[]','type' => 'hidden', 'value' => $embeds_input ) );

		// Input for normal attachments meta type.
		if ( ! empty( $filters['file_meta_type'] ) ) {
			echo html( 'input', array( 'class' => $attach_field_id,	'type' => 'hidden',	'name' => $attach_field_id . '_meta_type', 'value' => $filters['file_meta_type'] ) );
		}

		// Input for embed attachments meta type.
		if ( ! empty( $filters['embed_meta_type'] ) ) {
			echo html( 'input', array( 'class' => $attach_field_id,	'type' => 'hidden',	'name' => $embeds_input . '_meta_type', 'value' => $filters['embed_meta_type'] ) );
		}

		echo '</div>';
	}

	/**
	 * Refreshes the attachments/embed list based on the user selection.
	 */
	static function ajax_refresh_attachments() {

		if ( ! check_ajax_referer( 'classified_manager_mv_nonce_' . $_POST['cm_mv_id'], 'cm_mv_nonce' ) ) {
			die();
		}

		extract( $_POST );

		$attachment_ids = $embed_attach_ids = $attach_embed_urls = $embed_urls = array();

		$posted_embed_urls = $embeds = $attachments = '';

		// Retrieve the options for the current media manager.
		$media_manager_options = classified_manager_get_media_viewer_options( $cm_mv_id );

		if ( isset( $_POST['attachments'] ) ) {
			$attachment_ids = array_merge( $attachment_ids, $_POST['attachments'] );
			$attachment_ids = array_map( 'intval', $attachment_ids );
			$attachment_ids = array_unique( $attachment_ids );
		}

		if ( ! empty( $_POST['embed_urls'] ) ) {
			$posted_embed_urls = sanitize_text_field( $_POST['embed_urls'] );
			$embed_urls = explode( ',', $posted_embed_urls );

			$new_embed_urls = array();

			foreach( $embed_urls as $embed ) {
				$check = wp_check_filetype( $embed );

				// Check if the embed is an image that can be attached.
				$id = self::get_embed_as_attachment( $embed );

				if ( $id && ! is_wp_error( $id ) ) {
					array_push( $attachment_ids, $id );
				} else {
					$new_embed_urls = $embed;
				}

				// Updated the embeds list.
				$embed_urls = $new_embed_urls;
			}

		}

		if ( isset( $_POST['attach_embeds'] ) ) {
			$embed_attach_ids = array_merge( $embed_attach_ids, $_POST['attach_embeds'] );
			$embed_attach_ids = array_map( 'intval', $embed_attach_ids );
			$embed_attach_ids = array_unique( $embed_attach_ids );

			// Get URL's from attached embeds.
			foreach( $embed_attach_ids as $id ) {
				$file = classified_manager_mv_get_attachment_meta( $id );

				if ( ! empty( $file['url'] ) ) {
					$attach_embed_urls[] = $file['url'];
				}

			}

		}

		if ( ! empty( $attach_embed_urls ) ) {
			$embed_urls         = array_merge( $embed_urls, $attach_embed_urls );
			$posted_embed_urls .= implode( ',', $embed_urls );
		}

		if ( ! empty( $attachment_ids ) ) {
			$attachments = classified_manager_mv_output_attachments( $attachment_ids, $size = 'thumbnail', $media_manager_options['attributes']['attachment_params'], $echo = false );
		}

		if ( ! empty( $embed_urls ) ) {
			$embeds = classified_manager_mv_output_embed_urls( $embed_urls, $media_manager_options['attributes']['embed_params'], $echo = false );
		}

		$output = array(
			'attach_ids' => $attachment_ids,
			'files'      => array( 'html' => $attachments ),
			'embeds'     => array( 'url' => $posted_embed_urls, 'html' => $embeds ),
		);

		exit( json_encode( array( 'output' => $output ) ) );
	}

	/**
	 * Check if an embed is an image that can be attached.
	 */
	static function get_embed_as_attachment( $embed ) {

		if ( false !== strpos( $check['type'], 'image' ) ) {
			return false;
		}

		$tmp = download_url( $embed );

		unset( $embed_urls[ $embed ] );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array();

		// Set variables for storage. Fix file filename for query strings.
		preg_match( '/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $embed, $matches );

		$file_array['name']     = basename( $matches[0] );
		$file_array['tmp_name'] = $tmp;

		// If error storing temporarily, unlink.
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
		}

		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, $post_id );

		// If error storing permanently, unlink.
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $id;
		}

		// Store the image embed as a normal attachment.
		return $id;
	}

	/**
	 * Restrict media library to files uploaded by the current user with
	 * no parent or whose parent is the current post ID.
	 */
	static function restrict_media_library( $query ) {
		global $current_user;

		// Make sure we're restricting the library only on the frontend media manager.
		if ( ! classified_manager_mv_get_active_media_viewer() ) {
			return $query;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
		   $query['author'] = $current_user->ID;

		   if ( empty( $_REQUEST['post_id'] ) ) {
			   $query['post_parent'] = 0;
		   } else {
			   $query['post_parent'] = $_REQUEST['post_id'];
		   }

		}

		return $query;
	}

	/**
	 * Validates the files the current user is trying to upload by checking their mime types
	 * and the preset file limit.
	 */
	static function validate_upload_restrictions( $file ) {

		if ( empty( $_POST['classified_manager_mv_mime_types'] ) && empty( $_POST['classified_manager_mv_file_size'] ) && empty( $_POST['classified_manager_mv_file_limit'] ) ) {
			return $file;
		}

		$cm_mv_id = sanitize_text_field( $_POST['classified_manager_mv_id'] );

		$options = classified_manager_get_media_viewer_options( $cm_mv_id );

		// Secure mime types.
		if ( ! empty( $_POST['classified_manager_mv_mime_types'] ) ) {

			// Check if the mime types limit where hacked.
			if ( empty( $options['filters'] ) || $_POST['classified_manager_mv_mime_types'] != $options['filters']['mime_types'] ) {
				$file['error'] = __( 'Sorry, allowed mime types do not seem to be valid.', 'classifieds-wp' );
				return $file;
			}

			// Can be 'mime_type/extension', 'extension' or 'mime_type'.
			$allowed = explode( ',', $_POST['classified_manager_mv_mime_types'] );

			$file_type = wp_check_filetype( $file['name'] );
			$mime_type = explode( '/', $file_type['type'] );

			$not_allowed = true;

			// Check if extension and mime type are allowed.
			if ( in_array( $mime_type[0], $allowed ) || in_array( $file_type['type'], $allowed ) || in_array( $file_type['ext'], $allowed ) ) {
				$not_allowed = false;
			}

			if ( $not_allowed ) {

				$allowed_mime_types = get_allowed_mime_types();

				// First pass to check if the mime type is allowed.
				if ( ! in_array( $file['type'], $allowed_mime_types ) ) {

					// Double check if the extension is invalid by looking at the allowed extensions keys.
					foreach ( $allowed_mime_types as $ext_preg => $mime_match ) {
						$ext_preg = '!^(' . $ext_preg . ')$!i';
						if ( preg_match( $ext_preg, $file_type['ext'] ) ) {
							$not_allowed = false;
							break;
						}
					}

				}

				if ( $not_allowed ) {
					$file['error'] = __( 'Sorry, you cannot upload this file type for this field.', 'classifieds-wp' );
					return $file;
				}

			}

		}

		// Secure file size.
		if ( ! empty( $_POST['classified_manager_mv_file_size'] ) ) {

			// Check if the file size limit was hacked.
			if ( empty( $options['filters'] ) || $_POST['classified_manager_mv_file_size'] != $options['filters']['file_size'] ) {
				$file['error'] = __( 'Sorry, the allowed file size does not seem to be valid.', 'classifieds-wp' );
				return $file;
			}

			$file_size = sanitize_text_field( $_POST['classified_manager_mv_file_size'] );

			if ( $file['size'] > $file_size ) {
				$file['error'] = __( 'Sorry, you cannot upload this file as it exceeds the size limitations for this field.', 'classifieds-wp' );
				return $file;
			}

		}

		// Secure file limit.
		if ( ! empty( $_POST['classified_manager_mv_file_limit'] ) ) {

			$args = array(
				'post_type'   => 'attachment',
				'author'      => get_current_user_id(),
				'post_parent' => ! empty( $_POST['post_id'] ) ? $_POST['post_id'] : 0,
				'nopaging'    => true,
				'post_status' => 'any',
				// Limit files considering the media manager parent ID since each available media manager on a form can have it's own file limits.
				'meta_key'    => '_classified_manager_media_manager_parent',
				'meta_value'  => $cm_mv_id,
			);

			$attachments = new WP_Query( $args );

			if ( $attachments->found_posts && $attachments->found_posts >= $_POST['classified_manager_mv_file_limit'] && '-1' != $_POST['classified_manager_mv_file_limit'] ) {
				$file['error'] = __( 'Sorry, you\'ve reached the file upload limit for this field.', 'classifieds-wp' );
				return $file;
			}

		}

		return $file;
	}

	/**
	 * Get the attachments for a given object.
	 */
	static function get_post_attachments( $object_id, $args = array() ) {

		// Get the current attached embeds.
		$defaults = array(
			'post_parent' => $object_id,
			'meta_key'    => '_classified_manager_attachment_type',
			'meta_value'  =>  classified_manager_mv_get_allowed_meta_types('file'),
			'orderby'     => 'menu_order',
			'order'       => 'asc',
		);
		$args = wp_parse_args( $args, $defaults );

		$curr_attachments = get_children( $args );

		return $curr_attachments;
	}

	/**
	 * Unassigns or deletes any previous attachments that are not present on the current attachment enqueue list.
	 */
	static function maybe_clear_old_attachments( $object_id, $attachments = array(), $delete = false ) {

		$args = array(
			'meta_value' => classified_manager_mv_get_allowed_meta_types(),
		);

		if ( ! empty( $attachments ) ) {
			$args['post__not_in'] = $attachments;
		}

		$old_attachments = self::get_post_attachments( $object_id, $args );

		// Unattach or delete.
		foreach( $old_attachments as $old_attachment ) {

			$type = get_post_meta( $old_attachment->ID, '_classified_manager_attachment_type', true );

			// Delete embeds by default since they cannot be re-attached again.
			if ( in_array( $type, classified_manager_mv_get_allowed_meta_types('embed') ) || $delete ) {
				wp_delete_attachment( $old_attachment->ID );
			} else {
				// Unattach normal attachments to allow re-attaching them later.
				$old_attachment->post_parent = 0;
				wp_insert_attachment( $old_attachment );
			}
		}

	}

   /**
    * Attempts to fetch an oembed object with metadata for a provided URL using oEmbed.
    */
   static function get_oembed_object( $url ) {
		require_once( ABSPATH . WPINC . '/class-oembed.php' );
		$oembed = _wp_oembed_get_object();

		$oembed_provider_url = $oembed->discover( $url );
		$oembed_object = $oembed->fetch( $oembed_provider_url, $url );

		return empty( $oembed_object ) ? false : $oembed_object;
   }

	/**
	 * Ajax callback to initialize a new media manager ID session.
	 */
	static function ajax_set_media_viewer_session() {

		if ( empty( $_POST['cm_mv_id'] ) ) {
		   die();
		}

		if ( ! check_ajax_referer( 'classified_manager_mv_nonce_' . $_POST['cm_mv_id'], 'cm_mv_nonce' ) ) {
		   die();
		}

		$cm_mv_id = $_POST['cm_mv_id'];

		// Set a transient for the opened media manager ID/user ID to help identify the current media viewer when there's multiple mm's on same form.
		$result = set_transient( 'classified_manager_media_viewer_id_' . get_current_user_id(), $cm_mv_id, 60 * 60 * 5 ); // keep transient for 5 minutes

		die();
	}

	/**
	 * Delete any stored transients when media manager UI is closed.
	 */
	static function ajax_delete_transients() {
		$user_id = get_current_user_id();
		delete_transient( 'classified_manager_media_viewer_id_'.$user_id );
		die();
	}

   /**
	* Assign a meta key containing the media manager parent ID AND a default attach type to each new media attachment added through the media manager.
	*/
   static function set_attachment_mv_id( $attach_id ) {

	   // Get the active media manager ID for the current user.
	   $cm_mv_id = classified_manager_mv_get_active_media_viewer();

	   if ( $cm_mv_id ) {
		   update_post_meta( $attach_id, '_classified_manager_media_manager_parent', $cm_mv_id );
		   update_post_meta( $attach_id, '_classified_manager_attachment_type', CLASSIFIED_MANAGER_ATTACHMENT_FILE );
	   }

   }

	/**
	 * Meta capabilities for uploading files.
	 *
	 * Users need the 'upload_media' cap to be able to upload files.
	 * Users need the 'delete_post' cap to be able to delete files.
	 */
	static function media_capabilities( $caps, $cap, $user_id, $args ) {

		// Check for an active media manager for the current user - skip otherwise.
		if ( ! classified_manager_mv_get_active_media_viewer() ) {
			return $caps;
		}

		if ( ! apply_filters( 'classified_manager_media_viewer_allow_upload_files', true, $user_id ) ) {
			return $caps;
		}

		switch( $cap ) {

			case 'upload_files':

				if ( ( user_can( $user_id, 'upload_media' ) ) ) {
					$caps = array( 'exist' );
				}
				break;

			case 'delete_post':
			case 'edit_post':

				$post = get_post( $args[0] );

				// Allow users to delete their uploaded files.
				if ( user_can( $user_id, 'upload_media' ) && $user_id == $post->post_author) {
					$cm_mv_id = classified_manager_mv_get_active_media_viewer();

					if ( $cm_mv_id ) {

						if ( 'delete_post' === $cap && 'attachment' === $post->post_type ) {

							$mm_options = classified_manager_get_media_viewer_options( $cm_mv_id );

							// Check if the active media manage allows deleting uploaded files (only own uploaded files can be deleted).
							if ( isset( $mm_options['filters']['delete_media'] ) ) {

								if ( $mm_options['filters']['delete_media'] && 'caps' !== $mm_options['filters']['delete_media'] ) {
									$caps = array( 'exist' );
								} elseif( false === $mm_options['filters']['delete_media'] ) {
									$caps[] = 'do_not_allow';
								}

							}

						} elseif( 'edit_post' === $cap && 'attachment' !== $post->post_type ) {

							// Allow users with the 'upload_media' cap to edit their own posts.
							$caps = array( 'exist' );
						}

					}

				}
				break;

		}
		return $caps;
	}

}


// __API.

/**
 * Outputs the media manager HTML markup.
 *
 * @param int $object_id (optional) The post ID/user ID that the media relates to
 * @param array $atts (optional) Input attributes to be passed to the media manager:
 * 			'id'			   => the input ID - name used as meta key to store the media data
 *			'object'		   => the object to assign the attachments: 'post'(default)|'user'
 *			'class'			   => the input CSS class
 *			'title'			   => the input title
 *			'upload_text'	   => the text to be displayed on the upload button when there are no uploads yet
 *			'manage_text'	   => the text to be displayed on the upload button when uploads already exist
 *			'no_media_text'	   => the placeholder text to be displayed while there are no uploads
 *			'attachment_ids'   => default attachment ID's to be listed (int|array),
 *			'embed_urls'	   => default embed URL's to be listed (string|array),
 *			'attachment_params => the parameters to pass to the function that outputs the attachments (array)
 * 			'embed_params      => the parameters to pass to the function that outputs the embeds (array)
 * @param array $filters (optional) Filters to be passed to the media manager:
 *			'file_limit'	 => file limit - 0 = disable, -1 = no limit (default)
 *			'file_size'		 => file size (in bytes) - default = 1048577 (~1MB)
  *			'file_meta_type' => CLASSIFIED_MANAGER_ATTACHMENT_FILE (default), CLASSIFIED_MANAGER_ATTACHMENT_GALLERY - hook into 'classified_manager_mv_allowed_file_meta_types()' to add others
 *			'embed_limit'	 => embed limit - 0 = disable, -1 = no limit (default)
 *			'embed_meta_type'=> CLASSIFIED_MANAGER_ATTACHMENT_EMBED (default) - hook into 'classified_manager_mv_allowed_embed_meta_types()' to add others
 *			'mime_types'	 => the mime types accepted (default is empty - accepts any mime type) (string|array)
 *			'delete_media'   => allow deleting own uploaded files (caps = use WP caps for the user role, false = do not allow, true = allow)
 */
function classified_manager_media_viewer( $object_id = 0, $atts = array(), $filters = array() ) {
	WP_Classified_Manager_Media_Viewer::output_media_manager( $object_id, $atts, $filters );
}

/**
 * Enqueues the JS scripts that output WP's media manager.
 *
 * @param array $ids           The media manager instance ID's.
 * @param array $localization (optional) The localization params to be passed to wp_localize_script()
 * 		'post_id'			=> the existing post ID, if editing a post, or 0 for new posts (required for edits if 'post_id_field' is empty)
 *		'post_id_field'		=> an input field name containing the current post ID (required for edits if 'post_id' is empty)
 *		'ajaxurl'			=> admin_url( 'admin-ajax.php', 'relative' ),
 *		'ajax_nonce'		=> wp_create_nonce('classified_manager-media-viewer'),
 *		'files_limit_text'	=> the files limit text to be displayed on the upload view
 *		'files_type_text'	=> the allowed file types to be displayed on the upload view
 *		'insert_media_title'=> the insert media title to be displayed on the upload view
 *		'embed_media_title'	=> the embed media title to be displayed on the embed view
 *		'embed_limit_text'	=> the embed limit to be displayed on the embed view
 *		'clear_embeds_text' => the text for clearing the embeds to be displayed on the embed view
 *		'allowed_embeds_reached_text' => the allowed embeds warning to be displayed when users reach the max embeds allowed
 */
function classified_manager_mv_enqueue_media_viewer( $ids, $localization = array() ) {
	WP_Classified_Manager_Media_Viewer::enqueue_media_viewer( $ids, $localization );
}

/**
 * Handles media related post data
 *
 * @param int $post_id The post ID to which the attachments will be assigned
 * @param array $fields (optional) The media fields that should be handled -
 * Expects the fields index type: 'attachs' or 'embeds' (e.g: $fields = array( 'attach' => array( 'field1', 'field2' ), 'embeds' => array( 'field1', 'field2' ) )
 * @param bool $duplicate (optional) Should the media files be duplicated, thus keeping the original file unattached
 * @return null|bool False if no media was processed, null otherwise
 */
function classified_manager_mv_handle_media_upload( $post_id, $fields = array(), $duplicate = false ) {
	WP_Classified_Manager_Media_Viewer::handle_media_upload( $post_id, 'post', $fields, $duplicate );
}

/**
 * Handles media related user data
 *
 * @param int $user_id The user ID to which the attachments will be assigned
 * @param array $fields (optional) The media fields that should be handled
 * @return null|bool False if no media was processed, null otherwise
 */
function classified_manager_mv_handle_user_media_upload( $user_id, $fields = array() ) {
	WP_Classified_Manager_Media_Viewer::handle_media_upload( $user_id, 'user', $fields );
}

/**
 * Outputs the HTML markup for a list of attachment ID's.
 *
 * @param array $attachment_ids The list of attachment ID's to output
 * @param array $atts (optional) See 'wp_get_attachment_image' 'atts' param.
 *		        Additionally accepts the param 'show_description' => displays the attachment description (default is FALSE),
 * @param bool $echo Should the attachments be echoed or returned (default is TRUE)
 */
function classified_manager_mv_output_attachments( $attachment_ids, $size = 'thumbnail', $atts = array(), $echo = true ) {

	if ( empty( $attachment_ids ) ) {
		return;
	}

	$attachments = '';

	if ( ! $echo ) {
		ob_start();
	}

	foreach( (array) $attachment_ids as $attachment_id ) {
		classified_manager_mv_output_attachment( $attachment_id, $size, $atts );
	}

	if ( ! $echo ) {
		$attachments .= ob_get_clean();
	}

	if ( ! empty( $attachments ) ) {
		return $attachments;
	}

}

/**
 * Outputs the HTML markup for a specific attachment ID.
 *
 * @param int $attachment_id The attachment ID
 * @param array $size (optional) See 'wp_get_attachment_image' 'size' param.
 * @param array $atts (optional) See 'wp_get_attachment_image' 'atts' param.
 *        Special 'atts':
 *                 'show_description' - displays the media title + description of the attachment
 *                 'add_meta_atts'    - adds caption + description as data attributes to the attachment
 * @return string The HTML markup
 */
function classified_manager_mv_output_attachment( $attachment_id, $size = 'thumbnail', $atts = array() ) {

	$file = classified_manager_mv_get_attachment_meta( $attachment_id );

	$mime_type = explode( '/', $file['mime_type'] );

	$defaults = array(
		// Special temporary attributes.
		'show_description' => false,
		'add_meta_atts'    => false,

		// Regular HTML attributes.
		'href'   => $file['url'],
		'title'  => $file['title'],
		'alt'    => $file['alt'],
		'target' => '_blank',
	);
	$atts = wp_parse_args( $atts, $defaults );

	extract( $atts );

	unset( $atts['show_description'] );
	unset( $atts['add_meta_atts'] );

	$image = '';

	if ( 'image' == $mime_type[0] && $size ) {
		$image = wp_get_attachment_image( $attachment_id, $size, $icon = false, $atts );
	}

	$title = $show_description ? $file['title'] : '';

	$link = html( 'a', $atts, $image . $title );

	if ( $show_description || $add_meta_atts ) {
		$attachment = get_post( $attachment_id );

		// In case the attachment was deleted somehow return earlier.
		if ( empty( $attachment ) ) {
			return;
		}

		if ( $show_description ) {

			$file = array_merge( $file, array(
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
			) );
			$link .= html( 'p', array( 'class' =>  'file-description' ), $file['description'] );

		} elseif( $add_meta_atts ) {

			$atts = array_merge( $atts, array(
				'data-caption'     => $attachment->post_excerpt,
				'data-description' => $attachment->post_content,
			) );
			$link = html( 'a', $atts, $image );
		}

	}

	if ( ! empty( $image ) ) {
		echo $link;
		return;
	}

	// Echo as icon.
	echo wp_get_attachment_image( $attachment_id, $size, $icon = true, $atts );
}

/**
 * Queries the database for media manager attachments.
 * Uses the meta key '_classified_manager_attachment_type' to filter the available attachment types: gallery | file | embed
 *
 * @param int $post_id	The listing ID
 * @param array $filters (optional) Params to be used to filter the attachments query
 */
function classified_manager_mv_get_post_attachments( $post_id, $params = array(), $filters = array() ) {

	if ( ! $post_id ) {
		return array();
	}

	$defaults = array(
		'file_limit' => -1,
		'meta_type'  => CLASSIFIED_MANAGER_ATTACHMENT_FILE,
		'mime_types' => '',
	);
	$filters = wp_parse_args( $filters, $defaults );

	extract( $filters );

	$defaults = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_parent'    => $post_id,
		'posts_per_page' => $file_limit,
		'post_mime_type' => $mime_types,
		'orderby'        => 'menu_order',
		'order'          => 'asc',
		'meta_key'       => '_classified_manager_attachment_type',
		'meta_value'     => $meta_type,
		'fields'         => 'ids',
	);
	$params = wp_parse_args( $params, $defaults );

	return get_posts( $params );
}

/**
 * Collects and returns the meta info for a specific attachment ID.
 *
 * Meta retrieved: title, alt, url, mime type, file size
 *
 * @param int $attachment_id  The attachment ID
 * @return array Retrieves the attachment meta
 */
function classified_manager_mv_get_attachment_meta( $attachment_id ) {
	$filename = wp_get_attachment_url( $attachment_id );

	$title    = trim( strip_tags( get_the_title( $attachment_id ) ) );
	$size     = size_format( filesize( get_attached_file( $attachment_id ) ), 2 );
	$basename = basename( $filename );

	$meta = array (
		'title'     => ( ! $title ? $basename : $title ),
		'alt'       => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'url'       => $filename,
		'mime_type' => get_post_mime_type( $attachment_id ),
		'size'      => $size,
	);
	return $meta;
}

/**
 * Compares full/partial mime types or file extensions and tries to retrieve a list of related mime types.
 *
 * examples:
 * 'image'	=> 'image/png', 'image/gif', etc
 * 'pdf'	=> 'application/pdf'
 *
 * @param mixed $mime_types_ext The full/partial mime type or file extension to search
 * @return array The list of mime types if found, or an empty array
 */
function classified_manager_mv_get_mime_types_for( $mime_types_ext ) {

	$normalized_mime_types = array();

	$all_mime_types = wp_get_mime_types();

	// Sanitize the file extensions/mime types.
	$mime_types_ext = array_map( 'trim', (array) $mime_types_ext );
	$mime_types_ext = preg_replace( "/[^a-z\/]/i", '', $mime_types_ext );

	foreach( $mime_types_ext as $mime_type_ext ) {

		if ( isset( $all_mime_types[ $mime_type_ext ] ) ) {
			$normalized_mime_types[] = $all_mime_types[ $mime_type_ext ];
		} elseif( in_array( $mime_type_ext, $all_mime_types ) ) {
			$normalized_mime_types[] = $mime_type_ext;
		} else {

			// Try to get the full mime type from extension (e.g.: png, .jpg, etc ) or mime type parts (e.g.: image, application).
			foreach ( $all_mime_types as $exts => $mime ) {
				$mime_parts = explode( '/', $mime );

				if ( preg_match( "!({$exts})$|({$mime_parts[0]})!i", $mime_type_ext ) ) {
					$normalized_mime_types[] = $mime;
				}
			}
		}
	}
	return $normalized_mime_types;
}

/**
 * Retrieves all the attributes and filters set for a specific media manager ID.
 *
 * @param string $cm_mv_id The media manager ID to retrieve the options from.
 * @return array An associative array with all the options for the media manager.
 */
function classified_manager_get_media_viewer_options( $cm_mv_id ) {
	return get_option( "classified_manager_media_viewer_{$cm_mv_id}" );
}

/**
 * Retrieves the currently active (opened) media manager ID.
 *
 * @return string The media manager ID.
 */
function classified_manager_mv_get_active_media_viewer( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return get_transient('classified_manager_media_viewer_id_'.$user_id);
}

/**
 * Retrieves allowed attachments meta types.
 *
 * @uses apply_filters() Calls 'classified_manager_mv_allowed_meta_types'
 * @uses apply_filters() Calls 'classified_manager_mv_allowed_file_meta_types'
 * @uses apply_filters() Calls 'classified_manager_mv_allowed_embed_meta_types'
 *
 * @param string $type The attachment type: 'file' or 'embed', or all types, if empty
 */
function classified_manager_mv_get_allowed_meta_types( $type = '' ) {

	$meta_types = array(
		'file'  => array( CLASSIFIED_MANAGER_ATTACHMENT_FILE, CLASSIFIED_MANAGER_ATTACHMENT_GALLERY ),
		'embed' => array( CLASSIFIED_MANAGER_ATTACHMENT_EMBED ),
	);

	if ( empty( $type ) ) {
		$meta_types = array_merge( $meta_types['file'], $meta_types['embed'] );
	} elseif ( empty( $meta_types[ $type ] ) ) {
		$meta_types = $meta_types['file'];
	} else {
		$meta_types = $meta_types[ $type ];
		$type = '_' . $type;
	}

	return apply_filters( "classified_manager_mv_allowed{$type}_meta_types", $meta_types );
}


### Hooks Callbacks


/**
 * Retrieve the 'get_theme_support()' args.
 */
function classified_manager_mv_get_args( $option = '' ) {

	static $args = array();

	if ( empty( $args ) ) {

		$defaults = array(
			'file_limit'   => 3,       // 0 = disable, -1 = no limit
			'embed_limit'  => 0,       // 0 = disable, -1 = no limit
			'file_size'    => -1,      // -1 = use WP default, or use any other number (in bytes)
			'mime_types'   => '',      // blank = any (accepts 'image', 'image/png', 'png, jpg', etc) (string|array)
			'delete_media' => 'caps',  // allow deleting own uploaded files (caps = use WP caps for the user role, false = do not allow, true = allow)
		);
		$args = wp_parse_args( $args, $defaults );

	}

	if ( empty( $option ) ) {
		return $args;
	} else if ( isset( $args[ $option ] ) ) {
		return $args[ $option ];
	} else {
		return false;
	}

}


/**
 * Get an attachment ID given a URL.
 *
 * @param string $url
 *
 * @return int Attachment ID on success, 0 on failure
 */
function wp_classified_manager_get_attachment_id_by_url( $url ) {

	$attachment_id = 0;

	$dir = wp_upload_dir();

	if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?

		$file = basename( $url );

		$query_args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'value'   => $file,
					'compare' => 'LIKE',
					'key'     => '_wp_attachment_metadata',
				),
			)
		);

		$query = new WP_Query( $query_args );

		if ( $query->have_posts() ) {

			foreach ( $query->posts as $post_id ) {

				$meta = wp_get_attachment_metadata( $post_id );

				$original_file       = basename( $meta['file'] );
				$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );

				if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
					$attachment_id = $post_id;
					break;
				}

			}

		}

	}
	return $attachment_id;
}

new WP_Classified_Manager_Media_Viewer;