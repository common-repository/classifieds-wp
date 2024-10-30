<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_Classified_Manager_Writepanels {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 1, 2 );
		add_action( 'classified_manager_save_classified_listing', array( $this, 'save_classified_listing_data' ), 20, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
	}

	/**
	 * Enqueues scripts for the meta boxes.
	 */
	public function wp_enqueue_scripts( $hook ) {
		global $post;

		if ( ! $post || 'classified_listing' !== $post->post_type ) {
			return;
		}

		classified_manager_mv_enqueue_media_viewer( array( '_classified-images' ), array( 'post_id' => $post->ID ) );
	}

	/**
	 * classified_listing_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public function classified_listing_fields() {
		global $post;

		$current_user = wp_get_current_user();

		$fields = array(
			'_classified_price' => array(
				'label'       => __( 'Price', 'classifieds-wp' ),
				'placeholder' => __( 'e.g. "59,00"', 'classifieds-wp' ),
				'priority'    => 1,
				'type'        => 'number'
			),
			'_classified_location' => array(
				'label' => __( 'Location', 'classifieds-wp' ),
				'placeholder' => __( 'e.g. "London"', 'classifieds-wp' ),
				'description' => __( 'Leave this blank if the location is not important.', 'classifieds-wp' ),
				'priority'    => 2
			),
			'_classified_contact' => array(
				'label'       => __( 'Contact Email or Phone', 'classifieds-wp' ),
				'placeholder' => __( 'URL or Phone number which users use to contact author.', 'classifieds-wp' ),
				'description' => __( 'This field is required for the contact information area to appear beneath the listing.', 'classifieds-wp' ),
				'value'       => metadata_exists( 'post', $post->ID, '_classified_contact' ) ? get_post_meta( $post->ID, '_classified_contact', true ) : $current_user->user_email,
				'priority'    => 3
			),
			'_classified_type' => array(
				'label'       => __( 'Type', 'classifieds-wp' ),
				'placeholder' => '',
				'type'        => 'term-select',
				'required'    => true,
				'priority'    => 4,
				'default'     => 'used',
				'taxonomy'    => 'classified_listing_type'
			),
			'_classified_website' => array(
				'label'       => __( 'Website', 'classifieds-wp' ),
				'placeholder' => __( 'e.g. "http://google.com"', 'classifieds-wp' ),
				'priority'    => 5
			),
			'_classified_unavailable' => array(
				'label'       => __( 'Unavailable', 'classifieds-wp' ),
				'type'        => 'checkbox',
				'priority'    => 7,
				'description' => __( 'Select this option to mark this listing as unavailable.', 'classifieds-wp' ),
			)
		);

		if ( $current_user->has_cap( 'manage_classified_listings' ) ) {

			$fields['_featured'] = array(
				'label'       => __( 'Featured Listing', 'classifieds-wp' ),
				'type'        => 'checkbox',
				'description' => __( 'Featured listings will be sticky during searches, and can be styled differently.', 'classifieds-wp' ),
				'priority'    => 8
			);
			$fields['_classified_expires'] = array(
				'label'       => __( 'Listing Expiry Date', 'classifieds-wp' ),
				'placeholder' => __( 'yyyy-mm-dd', 'classifieds-wp' ),
				'priority'    => 9,
				'value'       => metadata_exists( 'post', $post->ID, '_classified_expires' ) ? get_post_meta( $post->ID, '_classified_expires', true ) : calculate_classified_expiry( $post->ID ),
			);

		}

		if ( $current_user->has_cap( 'edit_others_classified_listings' ) ) {

			$fields['_classified_author'] = array(
				'label'    => __( 'Posted by', 'classifieds-wp' ),
				'type'     => 'author',
				'priority' => 10
			);

		}

		$fields = apply_filters( 'classified_manager_classified_listing_data_fields', $fields );

		uasort( $fields, array( $this, 'sort_by_priority' ) );

		return $fields;
	}

	/**
	 * Sort array by priority value.
	 */
	protected function sort_by_priority( $a, $b ) {
	    if ( ! isset( $a['priority'] ) || ! isset( $b['priority'] ) || $a['priority'] === $b['priority'] ) {
	        return 0;
	    }
	    return ( $a['priority'] < $b['priority'] ) ? -1 : 1;
	}

	/**
	 * Display metaboxes.
	 */
	public function add_meta_boxes() {
		global $wp_post_types;

		add_meta_box( 'classified_listing_data', sprintf( __( '%s Data', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name ), array( $this, 'classified_listing_data' ), 'classified_listing', 'normal', 'high' );

		if ( classified_manager_enable_media_viewer() ) {
			add_meta_box( 'classified_listing_gallery', sprintf( __( '%s Images Gallery', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name ), array( $this, 'classified_listing_gallery' ), 'classified_listing', 'normal', 'high' );
		}
	}

	/**
	 * Output file field.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_file( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( empty( $field['placeholder'] ) ) {
			$field['placeholder'] = 'http://';
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<?php
			if ( ! empty( $field['multiple'] ) ) {
				foreach ( (array) $field['value'] as $value ) {
					?><span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>[]" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $value ); ?>" /><button class="button button-small wp_classified_manager_upload_file_button" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'classifieds-wp' ); ?>"><?php _e( 'Upload', 'classifieds-wp' ); ?></button></span><?php
				}
			} else {
				?><span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" /><button class="button button-small wp_classified_manager_upload_file_button" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'classifieds-wp' ); ?>"><?php _e( 'Upload', 'classifieds-wp' ); ?></button></span><?php
			}
			if ( ! empty( $field['multiple'] ) ) {
				?><button class="button button-small wp_classified_manager_add_another_file_button" data-field_name="<?php echo esc_attr( $key ); ?>" data-field_placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'classifieds-wp' ); ?>" data-uploader_button="<?php esc_attr_e( 'Upload', 'classifieds-wp' ); ?>"><?php esc_attr_e( 'Add file', 'classifieds-wp' ); ?></button><?php
			}
			?>
		</p>
		<?php
	}

	/**
	 * Output text field.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_text( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
		</p>
		<?php
	}

	/**
	 * Output number field.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_number( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<input type="number" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" step="any"/>
		</p>
		<?php
	}

	/**
	 * Output textarea.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_textarea( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_html( $field['value'] ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Output 'select' input.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_select( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php if ( isset( $field['value'] ) ) selected( $field['value'], $key ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Output 'multi-select' input.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_multiselect( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<select multiple="multiple" name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php if ( ! empty( $field['value'] ) && is_array( $field['value'] ) ) selected( in_array( $key, $field['value'] ), true ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Output a checkbox.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_checkbox( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?></label>
			<input type="checkbox" class="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $field['value'], 1 ); ?> />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Box to choose who posted the classified
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_author( $key, $field ) {
		global $thepostid, $post;

		if ( ! $post || $thepostid !== $post->ID ) {
			$the_post  = get_post( $thepostid );
			$author_id = $the_post->post_author;
		} else {
			$author_id = $post->post_author;
		}

		$posted_by      = get_user_by( 'id', $author_id );
		$field['value'] = ! isset( $field['value'] ) ? get_post_meta( $thepostid, $key, true ) : $field['value'];
		$name           = ! empty( $field['name'] ) ? $field['name'] : $key;
		?>
		<p class="form-field form-field-author">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<span class="current-author">
				<?php
					if ( $posted_by ) {
						echo '<a href="' . admin_url( 'user-edit.php?user_id=' . absint( $author_id ) ) . '">#' . absint( $author_id ) . ' &ndash; ' . $posted_by->user_login . '</a>';
					} else {
						 _e( 'Guest User', 'classifieds-wp' );
					}
				?> <a href="#" class="change-author button button-small"><?php _e( 'Change', 'classifieds-wp' ); ?></a>
			</span>
			<span class="hidden change-author">
				<input type="number" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" step="1" value="<?php echo esc_attr( $author_id ); ?>" style="width: 4em;" />
				<span class="description"><?php _e( 'Enter the ID of the user, or leave blank if submitted by a guest.', 'classifieds-wp' ) ?></span>
			</span>
		</p>
		<?php
	}

	/**
	 * Oututs a 'radio' input.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_radio( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label><?php echo esc_html( $field['label'] ) ; ?></label>
			<?php foreach ( $field['options'] as $option_key => $value ) : ?>
				<label><input type="radio" class="radio" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" value="<?php echo esc_attr( $option_key ); ?>" <?php checked( $field['value'], $option_key ); ?> /> <?php echo esc_html( $value ); ?></label>
			<?php endforeach; ?>
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * classified_listing_data function.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	public function classified_listing_data( $post ) {
		global $post, $thepostid;

		$thepostid = $post->ID;

		echo '<div class="wp_classified_manager_meta_data">';

		wp_nonce_field( 'save_meta_data', 'classified_manager_nonce' );

		do_action( 'classified_manager_classified_listing_data_start', $thepostid );

		foreach ( $this->classified_listing_fields() as $key => $field ) {
			$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

			if ( has_action( 'classified_manager_input_' . $type ) ) {
				do_action( 'classified_manager_input_' . $type, $key, $field );
			} elseif ( method_exists( $this, 'input_' . $type ) ) {
				call_user_func( array( $this, 'input_' . $type ), $key, $field );
			}
		}

		do_action( 'classified_manager_classified_listing_data_end', $thepostid );

		echo '</div>';
	}

	/**
	 * Output the media viewer.
	 */
	public function classified_listing_gallery( $post ) {

		// Get the featured image ID to remove it from the images gallery.
		$featured_id = get_post_thumbnail_id( $post );

		wp_classified_manager_ui( $post->ID, array( 'exclude_ids' => $featured_id ) );
	}

	/**
	 * save_post function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) || empty( $_POST ) ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( is_int( wp_is_post_revision( $post ) ) ) return;
		if ( is_int( wp_is_post_autosave( $post ) ) ) return;
		if ( empty($_POST['classified_manager_nonce']) || ! wp_verify_nonce( $_POST['classified_manager_nonce'], 'save_meta_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( $post->post_type != 'classified_listing' ) return;

		classified_manager_mv_handle_media_upload( $post_id );

		do_action( 'classified_manager_save_classified_listing', $post_id, $post );
	}

	/**
	 * save_classified_listing_data function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_classified_listing_data( $post_id, $post ) {
		global $wpdb;

		// These need to exist
		add_post_meta( $post_id, '_classified_unavailable', 0, true );
		add_post_meta( $post_id, '_featured', 0, true );

		// Save fields
		foreach ( $this->classified_listing_fields() as $key => $field ) {
			// Expirey date
			if ( '_classified_expires' === $key ) {
				if ( ! empty( $_POST[ $key ] ) ) {
					update_post_meta( $post_id, $key, date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ $key ] ) ) ) );
				} else {
					update_post_meta( $post_id, $key, '' );
				}
			}

			// Locations
			elseif ( '_classified_location' === $key ) {
				if ( update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) ) ) {
					// Location data will be updated by hooked in methods
				} elseif ( apply_filters( 'classified_manager_geolocation_enabled', true ) && ! WP_Classified_Manager_Geocode::has_location_data( $post_id ) ) {
					WP_Classified_Manager_Geocode::generate_location_data( $post_id, sanitize_text_field( $_POST[ $key ] ) );
				}
			}

			elseif ( '_classified_author' === $key ) {
				$wpdb->update( $wpdb->posts, array( 'post_author' => $_POST[ $key ] > 0 ? absint( $_POST[ $key ] ) : 0 ), array( 'ID' => $post_id ) );
			}

			elseif ( '_classified_contact' === $key ) {
				update_post_meta( $post_id, $key, sanitize_text_field( urldecode( $_POST[ $key ] ) ) );
			}

			// Everything else
			else {
				$type = ! empty( $field['type'] ) ? $field['type'] : '';

				switch ( $type ) {
					case 'textarea' :
						update_post_meta( $post_id, $key, wp_kses_post( stripslashes( $_POST[ $key ] ) ) );
					break;
					case 'checkbox' :
						if ( isset( $_POST[ $key ] ) ) {
							update_post_meta( $post_id, $key, 1 );
						} else {
							update_post_meta( $post_id, $key, 0 );
						}
					break;
					default :
						if ( ! isset( $_POST[ $key ] ) ) {
							continue;
						} elseif ( is_array( $_POST[ $key ] ) ) {
							update_post_meta( $post_id, $key, array_filter( array_map( 'sanitize_text_field', $_POST[ $key ] ) ) );
						} else {
							update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
						}
					break;
				}
			}
		}
	}
}

new WP_Classified_Manager_Writepanels();