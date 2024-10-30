<?php
/**
 * WP_Classified_Manager_Content class.
 */
class WP_Classified_Manager_Post_Types {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ), 0 );
		add_filter( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'classified_manager_check_for_expired_classifieds', array( $this, 'check_for_expired_classifieds' ) );
		add_action( 'classified_manager_delete_old_previews', array( $this, 'delete_old_previews' ) );

		add_action( 'pending_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'preview_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'set_expiry' ) );
		add_action( 'expired_to_publish', array( $this, 'set_expiry' ) );

		add_filter( 'the_classified_description', 'wptexturize'        );
		add_filter( 'the_classified_description', 'convert_smilies'    );
		add_filter( 'the_classified_description', 'convert_chars'      );
		add_filter( 'the_classified_description', 'wpautop'            );
		add_filter( 'the_classified_description', 'shortcode_unautop'  );
		add_filter( 'the_classified_description', 'prepend_attachment' );

		add_action( 'classified_manager_contact_details_email', array( $this, 'contact_details_email' ) );
		add_action( 'classified_manager_contact_details_phone', array( $this, 'contact_details_url' ) );

		add_filter( 'wp_insert_post_data', array( $this, 'fix_post_name' ), 10, 2 );
		add_action( 'add_post_meta', array( $this, 'maybe_add_geolocation_data' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'update_post_meta' ), 10, 4 );
		add_action( 'wp_insert_post', array( $this, 'maybe_add_default_meta_data' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'before_delete_classified' ) );

		// Templates.
		add_filter( 'single_template', array( $this, 'get_custom_post_type_template' ) );

		// WP ALL Import
		add_action( 'pmxi_saved_post', array( $this, 'pmxi_saved_post' ), 10, 1 );

		// RP4WP
		add_filter( 'rp4wp_get_template', array( $this, 'rp4wp_template' ), 10, 3 );
		add_filter( 'rp4wp_related_meta_fields', array( $this, 'rp4wp_related_meta_fields' ), 10, 3 );
		add_filter( 'rp4wp_related_meta_fields_weight', array( $this, 'rp4wp_related_meta_fields_weight' ), 10, 3 );

		// Single classified content
		$this->classified_content_filter( true );
	}

	/**
	 * register_post_types function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_types() {
		if ( post_type_exists( "classified_listing" ) )
			return;

		$admin_capability = 'manage_classified_listings';

		/**
		 * Taxonomies
		 */
		if ( get_option( 'classified_manager_enable_categories' ) ) {
			$singular  = __( 'Classified category', 'classifieds-wp' );
			$plural    = __( 'Classified categories', 'classifieds-wp' );

			if ( current_theme_supports( 'classified-manager-templates' ) ) {
				$rewrite   = array(
					'slug'         => _x( 'classified-category', 'Classified category slug - resave permalinks after changing this', 'classifieds-wp' ),
					'with_front'   => false,
					'hierarchical' => false
				);
				$public    = true;
			} else {
				$rewrite   = false;
				$public    = false;
			}

			register_taxonomy( "classified_listing_category",
				apply_filters( 'register_taxonomy_classified_listing_category_object_type', array( 'classified_listing' ) ),
	       	 	apply_filters( 'register_taxonomy_classified_listing_category_args', array(
		            'hierarchical' 			=> true,
		            'update_count_callback' => '_update_post_term_count',
		            'label' 				=> $plural,
		            'labels' => array(
						'name'              => $plural,
						'singular_name'     => $singular,
						'menu_name'         => ucwords( $plural ),
						'search_items'      => sprintf( __( 'Search %s', 'classifieds-wp' ), $plural ),
						'all_items'         => sprintf( __( 'All %s', 'classifieds-wp' ), $plural ),
						'parent_item'       => sprintf( __( 'Parent %s', 'classifieds-wp' ), $singular ),
						'parent_item_colon' => sprintf( __( 'Parent %s:', 'classifieds-wp' ), $singular ),
						'edit_item'         => sprintf( __( 'Edit %s', 'classifieds-wp' ), $singular ),
						'update_item'       => sprintf( __( 'Update %s', 'classifieds-wp' ), $singular ),
						'add_new_item'      => sprintf( __( 'Add New %s', 'classifieds-wp' ), $singular ),
						'new_item_name'     => sprintf( __( 'New %s Name', 'classifieds-wp' ),  $singular )
	            	),
		            'show_ui' 				=> true,
		            'public' 	     		=> $public,
		            'capabilities'			=> array(
		            	'manage_terms' 		=> $admin_capability,
		            	'edit_terms' 		=> $admin_capability,
		            	'delete_terms' 		=> $admin_capability,
		            	'assign_terms' 		=> $admin_capability,
		            ),
		            'rewrite' 				=> $rewrite,
		        ) )
		    );
		}

	    $singular  = __( 'Classified type', 'classifieds-wp' );
		$plural    = __( 'Classified types', 'classifieds-wp' );

		if ( current_theme_supports( 'classified-manager-templates' ) ) {
			$rewrite   = array(
				'slug'         => _x( 'classified-type', 'Classified type slug - resave permalinks after changing this', 'classifieds-wp' ),
				'with_front'   => false,
				'hierarchical' => false
			);
			$public    = true;
		} else {
			$rewrite   = false;
			$public    = false;
		}

		register_taxonomy( "classified_listing_type",
			apply_filters( 'register_taxonomy_classified_listing_type_object_type', array( 'classified_listing' ) ),
	        apply_filters( 'register_taxonomy_classified_listing_type_args', array(
				'hierarchical' => true,
				'label'        => $plural,
				'labels'       => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
                    'menu_name'         => ucwords( $plural ),
                    'search_items' 		=> sprintf( __( 'Search %s', 'classifieds-wp' ), $plural ),
                    'all_items' 		=> sprintf( __( 'All %s', 'classifieds-wp' ), $plural ),
                    'parent_item' 		=> sprintf( __( 'Parent %s', 'classifieds-wp' ), $singular ),
                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'classifieds-wp' ), $singular ),
                    'edit_item' 		=> sprintf( __( 'Edit %s', 'classifieds-wp' ), $singular ),
                    'update_item' 		=> sprintf( __( 'Update %s', 'classifieds-wp' ), $singular ),
                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'classifieds-wp' ), $singular ),
                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'classifieds-wp' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'public' 			    => $public,
	            'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),
	           'rewrite' 				=> $rewrite,
	        ) )
	    );

	    /**
		 * Post types
		 */
		$singular  = __( 'Classified', 'classifieds-wp' );
		$plural    = __( 'Classifieds', 'classifieds-wp' );

		if ( current_theme_supports( 'classified-manager-templates' ) ) {
			$has_archive = _x( 'classifieds', 'Post type archive slug - resave permalinks after changing this', 'classifieds-wp' );
		} else {
			$has_archive = false;
		}

		$rewrite     = array(
			'slug'       => _x( 'classified', 'Classified permalink - resave permalinks after changing this', 'classifieds-wp' ),
			'with_front' => false,
			'feeds'      => true,
			'pages'      => false
		);

		register_post_type( "classified_listing",
			apply_filters( "register_post_type_classified_listing", array(
				'labels' => array(
					'name' 					=> $plural,
					'singular_name' 		=> $singular,
					'menu_name'             => __( 'Classifieds WP', 'classifieds-wp' ),
					'all_items'             => sprintf( __( 'All %s', 'classifieds-wp' ), $plural ),
					'add_new' 				=> __( 'Add New', 'classifieds-wp' ),
					'add_new_item' 			=> sprintf( __( 'Add %s', 'classifieds-wp' ), $singular ),
					'edit' 					=> __( 'Edit', 'classifieds-wp' ),
					'edit_item' 			=> sprintf( __( 'Edit %s', 'classifieds-wp' ), $singular ),
					'new_item' 				=> sprintf( __( 'New %s', 'classifieds-wp' ), $singular ),
					'view' 					=> sprintf( __( 'View %s', 'classifieds-wp' ), $singular ),
					'view_item' 			=> sprintf( __( 'View %s', 'classifieds-wp' ), $singular ),
					'search_items' 			=> sprintf( __( 'Search %s', 'classifieds-wp' ), $plural ),
					'not_found' 			=> sprintf( __( 'No %s found', 'classifieds-wp' ), $plural ),
					'not_found_in_trash' 	=> sprintf( __( 'No %s found in trash', 'classifieds-wp' ), $plural ),
					'parent' 				=> sprintf( __( 'Parent %s', 'classifieds-wp' ), $singular )
				),
				'description'         => sprintf( __( 'This is where you can create and manage %s.', 'classifieds-wp' ), $plural ),
				'public'              => true,
				'show_ui'             => true,
				'capability_type'     => 'classified_listing',
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'hierarchical'        => false,
				'rewrite'             => $rewrite,
				'query_var'           => true,
				'supports'            => array( 'title', 'editor', 'custom-fields', 'publicize', 'thumbnail' ),
				'has_archive'         => $has_archive,
				'show_in_nav_menus'   => false
			) )
		);

		/**
		 * Feeds
		 */
		add_feed( 'classified_feed', array( $this, 'classified_feed' ) );

		/**
		 * Post status
		 */
		register_post_status( 'expired', array(
			'label'                     => _x( 'Expired', 'post status', 'classifieds-wp' ),
			'public'                    => false,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'classifieds-wp' ),
		) );
		register_post_status( 'preview', array(
			'label'                     => _x( 'Preview', 'post status', 'classifieds-wp' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Preview <span class="count">(%s)</span>', 'Preview <span class="count">(%s)</span>', 'classifieds-wp' ),
		) );
	}

	/**
	 * Change label
	 */
	public function admin_head() {
		global $menu;

		$plural     = __( 'Classified Listings', 'classifieds-wp' );
		$count_classifieds = wp_count_posts( 'classified_listing', 'readable' );

		if ( ! empty( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $key => $menu_item ) {
				if ( strpos( $menu_item[0], $plural ) === 0 ) {
					if ( $order_count = $count_classifieds->pending ) {
						$menu[ $key ][0] .= " <span class='awaiting-mod update-plugins count-$order_count'><span class='pending-count'>" . number_format_i18n( $count_classifieds->pending ) . "</span></span>" ;
					}
					break;
				}
			}
		}
	}

	/**
	 * Toggle filter on and off
	 */
	private function classified_content_filter( $enable ) {
		if ( ! $enable ) {
			remove_filter( 'the_content', array( $this, 'classified_content' ) );
		} else {
			add_filter( 'the_content', array( $this, 'classified_content' ) );
		}
	}

	/**
	 * Add extra content before/after the post for single classified listings.
	 */
	public function classified_content( $content ) {
		global $post;

		if ( ! is_singular( 'classified_listing' ) || ! in_the_loop() || 'classified_listing' !== $post->post_type ) {
			return $content;
		}

		ob_start();

		$this->classified_content_filter( false );

		do_action( 'classified_content_start' );

		get_classified_manager_template_part( 'content-single', 'classified_listing' );

		do_action( 'classified_content_end' );

		$this->classified_content_filter( true );

		return apply_filters( 'classified_manager_single_classified_content', ob_get_clean(), $post );
	}

	/**
	 * Classified listing feeds
	 */
	public function classified_feed() {
		$query_args = array(
			'post_type'           => 'classified_listing',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => isset( $_GET['posts_per_page'] ) ? absint( $_GET['posts_per_page'] ) : 10,
			'tax_query'           => array(),
			'meta_query'          => array()
		);

		if ( ! empty( $_GET['search_location'] ) ) {
			$location_meta_keys = array( 'geolocation_formatted_address', '_classified_location', 'geolocation_state_long' );
			$location_search    = array( 'relation' => 'OR' );
			foreach ( $location_meta_keys as $meta_key ) {
				$location_search[] = array(
					'key'     => $meta_key,
					'value'   => sanitize_text_field( $_GET['search_location'] ),
					'compare' => 'like'
				);
			}
			$query_args['meta_query'][] = $location_search;
		}

		if ( ! empty( $_GET['classified_types'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'classified_listing_type',
				'field'    => 'slug',
				'terms'    => explode( ',', sanitize_text_field( $_GET['classified_types'] ) ) + array( 0 )
			);
		}

		if ( ! empty( $_GET['classified_categories'] ) ) {
			$cats     = explode( ',', sanitize_text_field( $_GET['classified_categories'] ) ) + array( 0 );
			$field    = is_numeric( $cats ) ? 'term_id' : 'slug';
			$operator = 'all' === get_option( 'classified_manager_category_filter_type', 'all' ) && sizeof( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
			$query_args['tax_query'][] = array(
				'taxonomy'         => 'classified_listing_category',
				'field'            => $field,
				'terms'            => $cats,
				'include_children' => $operator !== 'AND' ,
				'operator'         => $operator
			);
		}

		if ( $classified_manager_keyword = sanitize_text_field( $_GET['search_keywords'] ) ) {
			$query_args['_keyword'] = $classified_manager_keyword; // Does nothing but needed for unique hash
			add_filter( 'posts_clauses', 'get_classified_listings_keyword_search' );
		}

		if ( empty( $query_args['meta_query'] ) ) {
			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {
			unset( $query_args['tax_query'] );
		}

		query_posts( apply_filters( 'classified_feed_args', $query_args ) );
		add_action( 'rss2_ns', array( $this, 'classified_feed_namespace' ) );
		add_action( 'rss2_item', array( $this, 'classified_feed_item' ) );
		do_feed_rss2( false );
	}

	/**
	 * Add a custom namespace to the classified feed
	 */
	public function classified_feed_namespace() {
		echo 'xmlns:classified_listing="' .  site_url() . '"' . "\n";
	}

	/**
	 * Add custom data to the classified feed
	 */
	public function classified_feed_item() {
		$post_id  = get_the_ID();
		$location = get_the_classified_location( $post_id );
		$classified_type = get_the_classified_type( $post_id );

		if ( $location ) {
			echo "<classified_listing:location><![CDATA[" . esc_html( $location ) . "]]></classified_listing:location>\n";
		}
		if ( $classified_type ) {
			echo "<classified_listing:classified_type><![CDATA[" . esc_html( $classified_type->name ) . "]]></classified_listing:classified_type>\n";
		}
	}

	/**
	 * Expire classifieds
	 */
	public function check_for_expired_classifieds() {
		global $wpdb;

		// Change status to expired
		$classified_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta
			LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
			WHERE postmeta.meta_key = '_classified_expires'
			AND postmeta.meta_value > 0
			AND postmeta.meta_value < %s
			AND posts.post_status = 'publish'
			AND posts.post_type = 'classified_listing'
		", date( 'Y-m-d', current_time( 'timestamp' ) ) ) );

		if ( $classified_ids ) {
			foreach ( $classified_ids as $classified_id ) {
				$classified_data       = array();
				$classified_data['ID'] = $classified_id;
				$classified_data['post_status'] = 'expired';
				wp_update_post( $classified_data );
			}
		}

		// Delete old expired classifieds
		if ( apply_filters( 'classified_manager_delete_expired_classifieds', false ) ) {
			$classified_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT posts.ID FROM {$wpdb->posts} as posts
				WHERE posts.post_type = 'classified_listing'
				AND posts.post_modified < %s
				AND posts.post_status = 'expired'
			", date( 'Y-m-d', strtotime( '-' . apply_filters( 'classified_manager_delete_expired_classifieds_days', 30 ) . ' days', current_time( 'timestamp' ) ) ) ) );

			if ( $classified_ids ) {
				foreach ( $classified_ids as $classified_id ) {
					wp_trash_post( $classified_id );
				}
			}
		}
	}

	/**
	 * Delete old previewed classifieds after 30 days to keep the DB clean
	 */
	public function delete_old_previews() {
		global $wpdb;

		// Delete old expired classifieds
		$classified_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT posts.ID FROM {$wpdb->posts} as posts
			WHERE posts.post_type = 'classified_listing'
			AND posts.post_modified < %s
			AND posts.post_status = 'preview'
		", date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ) );

		if ( $classified_ids ) {
			foreach ( $classified_ids as $classified_id ) {
				wp_delete_post( $classified_id, true );
			}
		}
	}

	/**
	 * Typo -.-
	 */
	public function set_expirey( $post ) {
		$this->set_expiry( $post );
	}

	/**
	 * Set expirey date when classified status changes
	 */
	public function set_expiry( $post ) {
		if ( $post->post_type !== 'classified_listing' ) {
			return;
		}

		// See if it is already set
		if ( metadata_exists( 'post', $post->ID, '_classified_expires' ) ) {
			$expires = get_post_meta( $post->ID, '_classified_expires', true );
			if ( $expires && strtotime( $expires ) < current_time( 'timestamp' ) ) {
				update_post_meta( $post->ID, '_classified_expires', '' );
				$_POST[ '_classified_expires' ] = '';
			}
			return;
		}

		// No metadata set so we can generate an expiry date
		// See if the user has set the expiry manually:
		if ( ! empty( $_POST[ '_classified_expires' ] ) ) {
			update_post_meta( $post->ID, '_classified_expires', date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ '_classified_expires' ] ) ) ) );

		// No manual setting? Lets generate a date
		} else {
			$expires = calculate_classified_expiry( $post->ID );
			update_post_meta( $post->ID, '_classified_expires', $expires );

			// In case we are saving a post, ensure post data is updated so the field is not overridden
			if ( isset( $_POST[ '_classified_expires' ] ) ) {
				$_POST[ '_classified_expires' ] = $expires;
			}
		}
	}

	/**
	 * The contact content when the contact method is an email
	 */
	public function contact_details_email( $contact ) {
		get_classified_manager_template( 'classified-contact-email.php', array( 'contact' => $contact ) );
	}

	/**
	 * The contact content when the contact method is a phone number
	 */
	public function contact_details_url( $contact ) {
		get_classified_manager_template( 'classified-contact-phone.php', array( 'contact' => $contact ) );
	}

	/**
	 * Fix post name when wp_update_post changes it
	 * @param  array $data
	 * @return array
	 */
	public function fix_post_name( $data, $postarr ) {
		 if ( 'classified_listing' === $data['post_type'] && 'pending' === $data['post_status'] && ! current_user_can( 'publish_posts' ) ) {
				$data['post_name'] = $postarr['post_name'];
		 }
		 return $data;
	}

	/**
	 * Generate location data if a post is added
	 * @param  int $post_id
	 * @param  array $post
	 */
	public function maybe_add_geolocation_data( $object_id, $meta_key, $meta_value ) {
		if ( '_classified_location' !== $meta_key || 'classified_listing' !== get_post_type( $object_id ) ) {
			return;
		}
		do_action( 'classified_manager_classified_location_edited', $object_id, $meta_value );
	}

	/**
	 * Triggered when updating meta on a classified listing
	 */
	public function update_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'classified_listing' === get_post_type( $object_id ) ) {
			switch ( $meta_key ) {
				case '_classified_location' :
					$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
				break;
				case '_featured' :
					$this->maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value );
				break;
				case '_classified_featured_image' :
					$this->maybe_unattach_attachment( $meta_id, $object_id, $meta_key, $meta_value );
				break;
			}
		}
	}

	/**
	 * Generate location data if a post is updated
	 */
	public function maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		do_action( 'classified_manager_classified_location_edited', $object_id, $meta_value );
	}

	/**
	 * Maybe set menu_order if the featured status of a classified is changed
	 */
	public function maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $wpdb;

		if ( '1' == $meta_value ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => -1 ), array( 'ID' => $object_id ) );
		} else {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => 0 ), array( 'ID' => $object_id, 'menu_order' => -1 ) );
		}

		clean_post_cache( $object_id );
	}

	/**
	 * Remove old attachment from listing
	 */
	public function maybe_unattach_attachment( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $wpdb;

		$dir                = wp_upload_dir();
		$old_attachment_url = get_post_meta( $object_id, '_classified_featured_image', true );
		$path = $old_attachment_url;

	    if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
	        $path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
	    }

	    $sql            = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $path );
	    $attachment_ids = $wpdb->get_col( $sql );

		if ( $attachment_ids ) {
			foreach ( $attachment_ids as $attachment_id ) {
				if ( $object_id === wp_get_post_parent_id( $attachment_id ) ) {
					wp_update_post( array(
						'ID'          => $attachment_id,
						'post_parent' => 0
					) );
					break;
				}
			}
		}
	}

	/**
	 * Legacy
	 * @deprecated 1.19.1
	 */
	public function maybe_generate_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
	}

	/**
	 * Maybe set default meta data for classified listings
	 * @param  int $post_id
	 * @param  WP_Post $post
	 */
	public function maybe_add_default_meta_data( $post_id, $post = '' ) {
		if ( empty( $post ) || 'classified_listing' === $post->post_type ) {
			add_post_meta( $post_id, '_classified_unavailable', 0, true );
			add_post_meta( $post_id, '_featured', 0, true );
		}
	}

	/**
	 * Retrieves the single template to be used for classified listings.
	 *
	 * @since 1.2
	 */
	function get_custom_post_type_template( $single_template ) {
	     global $post;

	     if ($post->post_type == 'classified_listing') {
	          $single_template = locate_classified_manager_template( 'single-classified_listing.php' );
	     }
	     return $single_template;
	}

	/**
	 * After importing via WP ALL Import, add default meta data
	 * @param  int $post_id
	 */
	public function pmxi_saved_post( $post_id ) {
		if ( 'classified_listing' === get_post_type( $post_id ) ) {
			$this->maybe_add_default_meta_data( $post_id );
			if ( ! WP_Classified_Manager_Geocode::has_location_data( $post_id ) && ( $location = get_post_meta( $post_id, '_classified_location', true ) ) ) {
				WP_Classified_Manager_Geocode::generate_location_data( $post_id, $location );
			}
		}
	}

	/**
	 * Replace RP4WP template with the template from Classifieds WP
	 * @param  string $located
	 * @param  string $template_name
	 * @param  array $args
	 * @return string
	 */
	public function rp4wp_template( $located, $template_name, $args ) {
		if ( 'related-post-default.php' === $template_name && 'classified_listing' === $args['related_post']->post_type ) {
			return WP_CLASSIFIED_MANAGER_PLUGIN_DIR . '/templates/content-classified_listing.php';
		}
		return $located;
	}

	/**
	 * Add meta fields for RP4WP to relate classifieds by
	 * @param  array $meta_fields
	 * @param  int $post_id
	 * @param  WP_Post $post
	 * @return array
	 */
	public function rp4wp_related_meta_fields( $meta_fields, $post_id, $post ) {
		if ( 'classified_listing' === $post->post_type ) {
			$meta_fields[] = '_classified_location';
		}
		return $meta_fields;
	}

	/**
	 * Add meta fields for RP4WP to relate classifieds by
	 * @param  int $weight
	 * @param  WP_Post $post
	 * @param  string $meta_field
	 * @return int
	 */
	public function rp4wp_related_meta_fields_weight( $weight, $post, $meta_field ) {
		if ( 'classified_listing' === $post->post_type ) {
			$weight = 100;
		}
		return $weight;
	}

	/**
	 * When deleting a classified, delete its attachments
	 * @param  int $post_id
	 */
	public function before_delete_classified( $post_id ) {
    	if ( 'classified_listing' === get_post_type( $post_id ) ) {
			$attachments = get_children( array(
		        'post_parent' => $post_id,
		        'post_type'   => 'attachment'
		    ) );

			if ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID );
					@unlink( get_attached_file( $attachment->ID ) );
				}
			}
		}
	}
}
