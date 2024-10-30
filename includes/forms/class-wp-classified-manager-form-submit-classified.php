<?php

/**
 * WP_Classified_Manager_Form_Submit_Classified class.
 */
class WP_Classified_Manager_Form_Submit_Classified extends WP_Classified_Manager_Form {

	public    $form_name = 'submit-classified';
	protected $classified_id;
	protected $preview_classified;

	/** @var WP_Classified_Manager_Form_Submit_Classified The single instance of the class */
	protected static $_instance = null;

	/**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'process' ) );

		$this->steps  = (array) apply_filters( 'submit_classified_steps', array(
			'submit' => array(
				'name'     => __( 'Submit Details', 'classifieds-wp' ),
				'view'     => array( $this, 'submit' ),
				'handler'  => array( $this, 'submit_handler' ),
				'priority' => 10
				),
			'preview' => array(
				'name'     => __( 'Preview', 'classifieds-wp' ),
				'view'     => array( $this, 'preview' ),
				'handler'  => array( $this, 'preview_handler' ),
				'priority' => 20
			),
			'done' => array(
				'name'     => __( 'Done', 'classifieds-wp' ),
				'view'     => array( $this, 'done' ),
				'priority' => 30
			)
		) );

		uasort( $this->steps, array( $this, 'sort_by_priority' ) );

		// Get step/classified
		if ( isset( $_POST['step'] ) ) {
			$this->step = is_numeric( $_POST['step'] ) ? max( absint( $_POST['step'] ), 0 ) : array_search( $_POST['step'], array_keys( $this->steps ) );
		} elseif ( ! empty( $_GET['step'] ) ) {
			$this->step = is_numeric( $_GET['step'] ) ? max( absint( $_GET['step'] ), 0 ) : array_search( $_GET['step'], array_keys( $this->steps ) );
		}

		$this->classified_id = ! empty( $_REQUEST['classified_id'] ) ? absint( $_REQUEST[ 'classified_id' ] ) : 0;

		// Allow resuming from cookie.
		if ( ! $this->classified_id && ! empty( $_COOKIE['wp-classified-manager-submitting-classified-id'] ) && ! empty( $_COOKIE['wp-classified-manager-submitting-classified-key'] ) ) {
			$classified_id     = absint( $_COOKIE['wp-classified-manager-submitting-classified-id'] );
			$classified_status = get_post_status( $classified_id );

			if ( 'preview' === $classified_status && get_post_meta( $classified_id, '_submitting_key', true ) === $_COOKIE['wp-classified-manager-submitting-classified-key'] ) {
				$this->classified_id = $classified_id;
			}
		}

		// Load classified details
		if ( $this->classified_id ) {
			$classified_status = get_post_status( $this->classified_id );
			if ( 'expired' === $classified_status ) {
				if ( ! classified_manager_user_can_edit_classified( $this->classified_id ) ) {
					$this->classified_id = 0;
					$this->step   = 0;
				}
			} elseif ( ! in_array( $classified_status, apply_filters( 'classified_manager_valid_submit_classified_statuses', array( 'preview' ) ) ) ) {
				$this->classified_id = 0;
				$this->step   = 0;
			}
		}
	}

	/**
	 * Get the submitted classified ID
	 * @return int
	 */
	public function get_classified_id() {
		return absint( $this->classified_id );
	}

	/**
	 * init_fields function.
	 */
	public function init_fields() {
		if ( $this->fields ) {
			return;
		}

		$allowed_contact_method = get_option( 'classified_manager_allowed_contact_method', '' );
		switch ( $allowed_contact_method ) {
			case 'email' :
				$contact_method_label       = __( 'Contact email', 'classifieds-wp' );
				$contact_method_placeholder = __( 'you@yourdomain.com', 'classifieds-wp' );
			break;
			case 'phone' :
				$contact_method_label       = __( 'Contact Number', 'classifieds-wp' );
				$contact_method_placeholder = __( '555-5555', 'classifieds-wp' );
			break;
			default :
				$contact_method_label       = __( 'Contact Email / Number', 'classifieds-wp' );
				$contact_method_placeholder = __( 'Enter an email address or phone number', 'classifieds-wp' );
			break;
		}

		$this->fields = apply_filters( 'submit_classified_form_fields', array(
			'classified' => array(
				'classified_title' => array(
					'label'       => __( 'Title', 'classifieds-wp' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 1
				),
				'classified_location' => array(
					'label'       => __( 'Location', 'classifieds-wp' ),
					'description' => __( 'Leave this blank if the location is not important', 'classifieds-wp' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'e.g. "London"', 'classifieds-wp' ),
					'priority'    => 2
				),
				'classified_type' => array(
					'label'       => __( 'Type', 'classifieds-wp' ),
					'type'        => 'term-select',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 3,
					'default'     => 'used',
					'taxonomy'    => 'classified_listing_type'
				),
				'classified_category' => array(
					'label'       => __( 'Category', 'classifieds-wp' ),
					'type'        => 'term-multiselect',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 4,
					'default'     => '',
					'taxonomy'    => 'classified_listing_category'
				),
				'classified_description' => array(
					'label'       => __( 'Description', 'classifieds-wp' ),
					'type'        => 'wp-editor',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 5
				),

				'classified_images' => $this->get_image_field(),

				'classified_price' => array(
					'label'       => __( 'Price', 'classifieds-wp' ),
					'type'        => 'number',
					'required'    => false,
					'placeholder' => __( 'e.g. "59,00"', 'classifieds-wp' ),
					'priority'    => 7
				),
				'classified_website' => array(
					'label'       => __( 'Website', 'classifieds-wp' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'e.g. "http://google.com"', 'classifieds-wp' ),
					'priority'    => 8
				),
				'classified_contact' => array(
					'label'       => $contact_method_label,
					'type'        => 'text',
					'required'    => true,
					'placeholder' => $contact_method_placeholder,
					'priority'    => 9
				)
			)
		) );

		if ( ! get_option( 'classified_manager_enable_categories' ) || wp_count_terms( 'classified_listing_category' ) == 0 ) {
			unset( $this->fields['classified']['classified_category'] );
		}
	}

	/**
	 * Retrieve the image field considering the user settings.
	 *
	 * The media viewer is only available for logged users.
	 */
	protected function get_image_field() {

		if ( classified_manager_enable_media_viewer() ) {

			$field = array(
				'label'       => __( 'Images', 'classifieds-wp' ),
				'type'        => 'wp-media-viewer',
				'required'    => (bool) get_option('classified_manager_require_images'),
				'priority'    => 6,
				'default'     => '',
			);

		} else {

			$field = array(
				'label'       => __( 'Featured Image', 'classifieds-wp' ),
				'type'        => 'file',
				'required'    => (bool) get_option('classified_manager_require_images'),
				'placeholder' => '',
				'priority'    => 6,
				'ajax'        => true,
				'multiple'    => false,
				'allowed_mime_types' => array(
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'gif'  => 'image/gif',
					'png'  => 'image/png'
				)
			);

		}
		return $field;
	}

	/**
	 * Validate the posted fields
	 *
	 * @return bool on success, WP_ERROR on failure
	 */
	protected function validate_fields( $values ) {

		foreach ( $this->fields as $group_key => $group_fields ) {

			foreach ( $group_fields as $key => $field ) {

				if ( $field['required'] && empty( $values[ $group_key ][ $key ] ) ) {

					if ( 'classified_images' !== $key || ( ! classified_manager_enable_media_viewer() && 'classified_images' === $key ) ) {
						return new WP_Error( 'validation-error', sprintf( __( '%s is a required field', 'classifieds-wp' ), $field['label'] ) );
					}

				}

				if ( ! empty( $field['taxonomy'] ) && in_array( $field['type'], array( 'term-checklist', 'term-select', 'term-multiselect' ) ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = $values[ $group_key ][ $key ];
					} else {
						$check_value = empty( $values[ $group_key ][ $key ] ) ? array() : array( $values[ $group_key ][ $key ] );
					}
					foreach ( $check_value as $term ) {
						if ( ! term_exists( $term, $field['taxonomy'] ) ) {
							return new WP_Error( 'validation-error', sprintf( __( '%s is invalid', 'classifieds-wp' ), $field['label'] ) );
						}
					}
				}

				if ( 'file' === $field['type'] && ! empty( $field['allowed_mime_types'] ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = array_filter( $values[ $group_key ][ $key ] );
					} else {
						$check_value = array_filter( array( $values[ $group_key ][ $key ] ) );
					}
					if ( ! empty( $check_value ) ) {
						foreach ( $check_value as $file_url ) {
							$file_url = current( explode( '?', $file_url ) );

							if ( ( $info = wp_check_filetype( $file_url ) ) && ! in_array( $info['type'], $field['allowed_mime_types'] ) ) {
								throw new Exception( sprintf( __( '"%s" (filetype %s) needs to be one of the following file types: %s', 'classifieds-wp' ), $field['label'], $info['ext'], implode( ', ', array_keys( $field['allowed_mime_types'] ) ) ) );
							}
						}
					}
				}

			}

		}

		// Application method
		if ( isset( $values['classified']['contact'] ) && ! empty( $values['classified']['contact'] ) ) {
			$allowed_contact_method = get_option( 'classified_manager_allowed_contact_method', '' );
			$values['classified']['contact'] = str_replace( ' ', '+', $values['classified']['contact'] );
			switch ( $allowed_contact_method ) {
				case 'email' :
					if ( ! is_email( $values['classified']['contact'] ) ) {
						throw new Exception( __( 'Please enter a valid contact email address', 'classifieds-wp' ) );
					}
				break;
				case 'url' :
					// Prefix http if needed
					if ( ! strstr( $values['classified']['contact'], 'http:' ) && ! strstr( $values['classified']['contact'], 'https:' ) ) {
						$values['classified']['contact'] = 'http://' . $values['classified']['contact'];
					}
					if ( ! filter_var( $values['classified']['contact'], FILTER_VALIDATE_URL ) ) {
						throw new Exception( __( 'Please enter a valid contact URL', 'classifieds-wp' ) );
					}
				break;
				default :
					if ( ! is_email( $values['classified']['contact'] ) ) {
						// Prefix http if needed
						if ( ! strstr( $values['classified']['contact'], 'http:' ) && ! strstr( $values['classified']['contact'], 'https:' ) ) {
							$values['classified']['contact'] = 'http://' . $values['classified']['contact'];
						}
						if ( ! filter_var( $values['classified']['contact'], FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Please enter a valid contact email address or URL', 'classifieds-wp' ) );
						}
					}
				break;
			}
		}

		return apply_filters( 'submit_classified_form_validate_fields', true, $this->fields, $values );
	}

	/**
	 * classified_types function.
	 */
	private function classified_types() {
		$options = array();
		$terms   = get_classified_listing_types();
		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}

	/**
	 * Submit Step
	 */
	public function submit() {
		$this->init_fields();

		// Load data if necessary.
		if ( $this->classified_id ) {

			$classified = get_post( $this->classified_id );

			foreach ( $this->fields as $group_key => $group_fields ) {
				foreach ( $group_fields as $key => $field ) {
					switch ( $key ) {
						case 'classified_title' :
							$this->fields[ $group_key ][ $key ]['value'] = $classified->post_title;
						break;
						case 'classified_description' :
							$this->fields[ $group_key ][ $key ]['value'] = $classified->post_content;
						break;
						case 'classified_type' :
							$this->fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $classified->ID, 'classified_listing_type', array( 'fields' => 'ids' ) ) );
						break;
						case 'classified_category' :
							$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $classified->ID, 'classified_listing_category', array( 'fields' => 'ids' ) );
						break;
						case 'classified_images' :

							// Only treat the images field as regular field if not using the media viewer (non logged users).
							if ( classified_manager_enable_media_viewer() ) {
								break;
							}

						default:
							$this->fields[ $group_key ][ $key ]['value'] = get_post_meta( $classified->ID, '_' . $key, true );
						break;
					}
				}
			}

			$this->fields = apply_filters( 'submit_classified_form_fields_get_classified_data', $this->fields, $classified );

		// Get user meta
		} elseif ( is_user_logged_in() && empty( $_POST['submit_classified'] ) ) {
			if ( ! empty( $this->fields['classified']['contact'] ) ) {
				$allowed_contact_method = get_option( 'classified_manager_allowed_contact_method', '' );
				if ( $allowed_contact_method !== 'url' ) {
					$current_user = wp_get_current_user();
					$this->fields['classified']['contact']['value'] = $current_user->user_email;
				}
			}
			$this->fields = apply_filters( 'submit_classified_form_fields_get_user_data', $this->fields, get_current_user_id() );
		}

		wp_enqueue_script( 'wp-classified-manager-classified-submission' );

		// Enqueue 'jQuery.validate' if registered.
		wp_enqueue_script( 'jquery-validate' );
		wp_enqueue_script( 'jquery-validate-locale' );

		get_classified_manager_template( 'classified-submit.php', array(
			'form'               => $this->form_name,
			'classified_id'      => $this->get_classified_id(),
			'action'             => $this->get_action(),
			'classified_fields'  => $this->get_fields( 'classified' ),
			'step'               => $this->get_step(),
			'submit_button_text' => apply_filters( 'submit_classified_form_submit_button_text', __( 'Preview', 'classifieds-wp' ) )
		) );

		classified_manager_mv_enqueue_media_viewer( array( '_classified-images' ), array( 'post_id' => $this->classified_id ) );
	}

	/**
	 * Submit Step is posted
	 */
	public function submit_handler() {
		try {
			// Init fields
			$this->init_fields();

			// Get posted values
			$values = $this->get_posted_fields();

			if ( empty( $_POST['submit_classified'] ) ) {
				return;
			}

			// Validate required
			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Account creation
			if ( ! is_user_logged_in() ) {
				$create_account = false;

				if ( classified_manager_enable_registration() ) {
					if ( classified_manager_user_requires_account() ) {
						if ( ! classified_manager_generate_username_from_email() && empty( $_POST['create_account_username'] ) ) {
							throw new Exception( __( 'Please enter a username.', 'classifieds-wp' ) );
						}
						if ( empty( $_POST['create_account_email'] ) ) {
							throw new Exception( __( 'Please enter your email address.', 'classifieds-wp' ) );
						}
					}
					if ( ! empty( $_POST['create_account_email'] ) ) {
						$create_account = wp_classified_manager_create_account( array(
							'username' => empty( $_POST['create_account_username'] ) ? '' : $_POST['create_account_username'],
							'email'    => $_POST['create_account_email'],
							'role'     => get_option( 'classified_manager_registration_role' )
						) );
					}
				}

				if ( is_wp_error( $create_account ) ) {
					throw new Exception( $create_account->get_error_message() );
				}
			}

			if ( classified_manager_user_requires_account() && ! is_user_logged_in() ) {
				throw new Exception( __( 'You must be signed in to post a new listing.' ) );
			}

			// Update the classified
			$this->save_classified( $values['classified']['classified_title'], $values['classified']['classified_description'], $this->classified_id ? '' : 'preview', $values );
			$this->update_classified_data( $values );

			// Late check to see if images are required.
			if ( $this->fields['classified']['classified_images']['required'] && ! classified_manager_mv_get_post_attachments( $this->classified_id ) ) {
				throw new Exception( sprintf( __( '%s is a required field', 'classifieds-wp' ), $this->fields['classified']['classified_images']['label'] ) );
			}

			// Successful, show next step
			$this->step ++;

		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Update or create a classified listing from posted data
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 * @param  array $values
	 * @param  bool $update_slug
	 */
	protected function save_classified( $post_title, $post_content, $status = 'preview', $values = array(), $update_slug = true ) {
		$classified_data = array(
			'post_title'     => $post_title,
			'post_content'   => $post_content,
			'post_type'      => 'classified_listing',
			'comment_status' => 'closed'
		);

		if ( $update_slug ) {
			$classified_slug   = array();

			// Prepend location
			if ( apply_filters( 'submit_classified_form_prefix_post_name_with_location', true ) && ! empty( $values['classified']['classified_location'] ) ) {
				$classified_slug[] = $values['classified']['classified_location'];
			}

			// Prepend with classified type
			if ( apply_filters( 'submit_classified_form_prefix_post_name_with_classified_type', true ) && ! empty( $values['classified']['classified_type'] ) ) {
				$classified_slug[] = $values['classified']['classified_type'];
			}

			$classified_slug[]            = $post_title;
			$classified_data['post_name'] = sanitize_title( implode( '-', $classified_slug ) );
		}

		if ( $status ) {
			$classified_data['post_status'] = $status;
		}

		$classified_data = apply_filters( 'submit_classified_form_save_classified_data', $classified_data, $post_title, $post_content, $status, $values );

		if ( $this->classified_id ) {
			$classified_data['ID'] = $this->classified_id;
			wp_update_post( $classified_data );
		} else {
			$this->classified_id = wp_insert_post( $classified_data );

			if ( ! headers_sent() ) {
				$submitting_key = uniqid();

				setcookie( 'wp-classified-manager-submitting-classified-id', $this->classified_id, false, COOKIEPATH, COOKIE_DOMAIN, false );
				setcookie( 'wp-classified-manager-submitting-classified-key', $submitting_key, false, COOKIEPATH, COOKIE_DOMAIN, false );

				update_post_meta( $this->classified_id, '_submitting_key', $submitting_key );
			}
		}
	}

	/**
	 * Set classified meta + terms based on posted values
	 *
	 * @param  array $values
	 */
	protected function update_classified_data( $values ) {

		// Set defaults
		add_post_meta( $this->classified_id, '_classified_unavailable', 0, true );
		add_post_meta( $this->classified_id, '_featured', 0, true );

		$maybe_attach = array();

		// Loop fields and save meta and term data
		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// Save taxonomies
				if ( ! empty( $field['taxonomy'] ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						wp_set_object_terms( $this->classified_id, $values[ $group_key ][ $key ], $field['taxonomy'], false );
					} else {
						wp_set_object_terms( $this->classified_id, array( $values[ $group_key ][ $key ] ), $field['taxonomy'], false );
					}

				// Save meta data
				} else {

					if ( 'classified_images' === $key ) {
						$this->set_classified_images();
					} else {
						$value = $values[ $group_key ][ $key ];

						update_post_meta( $this->classified_id, '_' . $key, $value );
					}

				}

				// Handle attachments
				if ( 'file' === $field['type'] ) {
					// Must be absolute
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						foreach ( $values[ $group_key ][ $key ] as $file_url ) {
							$maybe_attach[] = str_replace( array( WP_CONTENT_URL, site_url() ), array( WP_CONTENT_DIR, ABSPATH ), $file_url );
						}
					} else {
						$maybe_attach[] = str_replace( array( WP_CONTENT_URL, site_url() ), array( WP_CONTENT_DIR, ABSPATH ), $values[ $group_key ][ $key ] );
					}
				}

			}
		}

		$maybe_attach = array_filter( $maybe_attach );

		// Handle attachments - Only used for normal FILE input uploads. Ignored by the media viewer.
		if ( sizeof( $maybe_attach ) && apply_filters( 'classified_manager_attach_uploaded_files', true ) ) {
			/** WordPress Administration Image API */
			include_once( ABSPATH . 'wp-admin/includes/image.php' );
			include_once( ABSPATH . 'wp-admin/includes/media.php' );

			// Get attachments
			$attachments     = get_posts( 'post_parent=' . $this->classified_id . '&post_type=attachment&fields=ids&post_mime_type=image&numberposts=-1' );
			$attachment_urls = array();

			// Loop attachments already attached to the classified.
			foreach ( $attachments as $attachment_key => $attachment ) {
				$attachment_urls[] = str_replace( array( WP_CONTENT_URL, site_url() ), array( WP_CONTENT_DIR, ABSPATH ), wp_get_attachment_url( $attachment ) );
			}

			$attach_ids = array();

			foreach ( $maybe_attach as $attachment_url ) {
				if ( ! in_array( $attachment_url, $attachment_urls ) ) {
					$attachment = array(
						'post_title'   => get_the_title( $this->classified_id ),
						'post_content' => '',
						'post_status'  => 'inherit',
						'post_parent'  => $this->classified_id,
						'guid'         => $attachment_url
					);

					if ( $info = wp_check_filetype( $attachment_url ) ) {
						$attachment['post_mime_type'] = $info['type'];
					}

					$attachment_id = wp_insert_attachment( $attachment, $attachment_url, $this->classified_id );

					if ( ! is_wp_error( $attachment_id ) ) {
						wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $attachment_url ) );

						// Provide compatibility with the media viewer files.
						update_post_meta( $attachment_id, '_classified_manager_attachment_type', CLASSIFIED_MANAGER_ATTACHMENT_FILE );

						$attach_ids[] = $attachment_id;

						if ( ! has_post_thumbnail( $this->classified_id ) ) {
							set_post_thumbnail( $this->classified_id, $attachment_id );
						}

					}
				}

				if ( $attach_ids ) {
					update_post_meta( $this->classified_id, '_classified-images', $attach_ids );
				}

			}

		}

		do_action( 'classified_manager_update_classified_data', $this->classified_id, $values );
	}

	/**
	 * Handle image uploads through the media viewer and set the featured image.
	 */
	protected function set_classified_images() {

		if ( ! classified_manager_enable_media_viewer() ) {
			return;
		}

		// Handle media manager upload.
		classified_manager_mv_handle_media_upload( $this->classified_id );

		$attachments = get_post_meta( $this->classified_id, '_classified-images', true );

		if ( ! $attachments ) {
			return;
		}

		$featured_id = reset( $attachments );

		delete_post_thumbnail( $this->classified_id );
		set_post_thumbnail( $this->classified_id, $featured_id );
	}

	/**
	 * Preview Step
	 */
	public function preview() {
		global $post, $classified_preview;

		if ( $this->classified_id ) {
			$classified_preview = true;
			$action             = $this->get_action();
			$post               = get_post( $this->classified_id );

			setup_postdata( $post );

			$post->post_status  = 'preview';
			?>
			<form method="post" id="classified_preview" action="<?php echo esc_url( $action ); ?>">
				<div class="classified_listing_preview_title">
					<input type="submit" name="continue" id="classified_preview_submit_button" class="button classified-manager-button-submit-listing" value="<?php echo apply_filters( 'submit_classified_step_preview_submit_text', __( 'Submit Listing', 'classifieds-wp' ) ); ?>" />
					<input type="submit" name="edit_classified" class="button classified-manager-button-edit-listing" value="<?php _e( 'Edit listing', 'classifieds-wp' ); ?>" />
					<input type="hidden" name="classified_id" value="<?php echo esc_attr( $this->classified_id ); ?>" />
					<input type="hidden" name="step" value="<?php echo esc_attr( $this->step ); ?>" />
					<input type="hidden" name="classified_manager_form" value="<?php echo $this->form_name; ?>" />
					<h2>
						<?php _e( 'Preview', 'classifieds-wp' ); ?>
					</h2>
				</div>
				<div class="classified_listing_preview single_classified_listing">
					<?php get_classified_manager_template_part( 'content-single', 'classified_listing' ); ?>
				</div>
			</form>
			<?php
			wp_reset_postdata();
		}
	}

	/**
	 * Preview Step Form handler
	 */
	public function preview_handler() {
		if ( ! $_POST ) {
			return;
		}

		// Edit = show submit form again
		if ( ! empty( $_POST['edit_classified'] ) ) {
			$this->step --;
		}

		// Continue = change classified status then show next screen
		if ( ! empty( $_POST['continue'] ) ) {
			$classified = get_post( $this->classified_id );

			if ( in_array( $classified->post_status, array( 'preview', 'expired' ) ) ) {

				// Reset expiry
				delete_post_meta( $classified->ID, '_classified_expires' );

				// Update classified listing
				$update_classified                  = array();
				$update_classified['ID']            = $classified->ID;
				$update_classified['post_status']   = apply_filters( 'submit_classified_post_status', get_option( 'classified_manager_submission_requires_approval' ) ? 'pending' : 'publish', $classified );
				$update_classified['post_date']     = current_time( 'mysql' );
				$update_classified['post_date_gmt'] = current_time( 'mysql', 1 );
				$update_classified['post_author']   = get_current_user_id();

				wp_update_post( $update_classified );
			}

			$this->step ++;
		}
	}

	/**
	 * Done Step
	 */
	public function done() {
		do_action( 'classified_manager_classified_submitted', $this->classified_id );
		get_classified_manager_template( 'classified-submitted.php', array( 'classified' => get_post( $this->classified_id ) ) );
	}
}
