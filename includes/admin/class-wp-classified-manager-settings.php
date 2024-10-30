<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Classified_Manager_Settings class.
 */
class WP_Classified_Manager_Settings {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->settings_group = 'classified_manager';
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * init_settings function.
	 *
	 * @access protected
	 * @return void
	 */
	protected function init_settings() {
		// Prepare roles option
		$roles         = get_editable_roles();
		$account_roles = array();

		foreach ( $roles as $key => $role ) {
			if ( $key == 'administrator' ) {
				continue;
			}
			$account_roles[ $key ] = $role['name'];
		}

		$this->settings = apply_filters( 'classified_manager_settings',
			array(
				'classified_listings' => array(
					__( 'Classified Listings', 'classifieds-wp' ),
					array(
						'listings' => array(
							'title'  => __( 'Listings', 'classifieds-wp' ),
							'fields' =>	array(
								array(
									'name'        => 'classified_manager_per_page',
									'std'         => '10',
									'placeholder' => '',
									'label'       => __( 'Listings Per Page', 'classifieds-wp' ),
									'desc'        => __( 'How many listings should be shown per page by default?', 'classifieds-wp' ),
									'attributes'  => array( 'class' => 'small-text' ),
								),
								array(
									'name'        => 'classified_manager_per_row',
									'std'         => '3',
									'placeholder' => '',
									'label'       => __( 'Listings Per Row', 'classifieds-wp' ),
									'desc'        => __( 'How many listings should be shown per row by default? Used when determing the grid size layout.', 'classifieds-wp' ),
									'type'       => 'select',
									'options' => array(
										'1'  => __( '1', 'classifieds-wp' ),
										'2'  => __( '2', 'classifieds-wp' ),
										'3'  => __( '3', 'classifieds-wp' ),
										'4'  => __( '4', 'classifieds-wp' ),
										'5'  => __( '5', 'classifieds-wp' ),
									)
								),
								array(
									'name'       => 'classified_manager_hide_unavaliable_classifieds',
									'std'        => '0',
									'label'      => __( 'Unavailable Listings', 'classifieds-wp' ),
									'cb_label'   => __( 'Hide unavailable listings', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, unavailable listings will be hidden from archives.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_hide_expired_content',
									'std'        => '1',
									'label'      => __( 'Expired Listings', 'classifieds-wp' ),
									'cb_label'   => __( 'Hide content within expired listings', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, the content within expired listings will be hidden. Otherwise, expired listings will be displayed as normal (without the contact area).', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
							),
						),
						'single_listings' => array(
							'title'  => __( 'Single Listing', 'classifieds-wp' ),
							'fields' =>	array(),
						),
						'categories' => array(
							'title'  => __( 'Listing Categories', 'classifieds-wp' ),
							'fields' =>	array(
								array(
									'name'       => 'classified_manager_enable_categories',
									'std'        => '0',
									'label'      => __( 'Categories', 'classifieds-wp' ),
									'cb_label'   => __( 'Enable categories for listings', 'classifieds-wp' ),
									'desc'       => __( 'Choose whether to enable categories. Categories must be setup by an admin to allow users to choose them during submission.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_enable_default_category_multiselect',
									'std'        => '0',
									'label'      => __( 'Multi-select Categories', 'classifieds-wp' ),
									'cb_label'   => __( 'Enable category multiselect by default', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, the category select box will default to a multiselect on the [classifieds] shortcode.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_category_filter_type',
									'std'        => 'any',
									'label'      => __( 'Category Filter Type', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, the category select box will default to a multiselect on the [classifieds] shortcode.', 'classifieds-wp' ),
									'type'       => 'select',
									'options' => array(
										'any'  => __( 'Classifieds will be shown if within ANY selected category', 'classifieds-wp' ),
										'all' => __( 'Classifieds will be shown if within ALL selected categories', 'classifieds-wp' ),
									)
								),
							),
						),
					),
				),
				'classified_submission' => array(
					__( 'Classified Submission', 'classifieds-wp' ),
					array(
						'classified_submission' => array(
							'title'  => __( 'Account', 'classifieds-wp' ),
							'fields' =>	array(
								array(
									'name'       => 'classified_manager_user_requires_account',
									'std'        => '1',
									'label'      => __( 'Account Required', 'classifieds-wp' ),
									'cb_label'   => __( 'Submitting listings requires an account', 'classifieds-wp' ),
									'desc'       => __( 'If disabled, non-logged in users will be able to submit listings without creating an account.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_enable_registration',
									'std'        => '1',
									'label'      => __( 'Account Creation', 'classifieds-wp' ),
									'cb_label'   => __( 'Allow account creation', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, non-logged in users will be able to create an account by entering their email address on the submission form.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_generate_username_from_email',
									'std'        => '1',
									'label'      => __( 'Account Username', 'classifieds-wp' ),
									'cb_label'   => __( 'Automatically Generate Username from Email Address', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, a username will be generated from the first part of the user email address. Otherwise, a username field will be shown.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_registration_role',
									'std'        => 'advertiser',
									'label'      => __( 'Account Role', 'classifieds-wp' ),
									'desc'       => __( 'If you enable registration on your submission form, choose a role for the new user.', 'classifieds-wp' ),
									'type'       => 'select',
									'options'    => $account_roles
								),
							),
						),
						'categories' => array(
							'title'  => __( 'Listings', 'classifieds-wp' ),
							'fields' =>	array(
								array(
									'name'       => 'classified_manager_submission_requires_approval',
									'std'        => '1',
									'label'      => __( 'Moderate New Listings', 'classifieds-wp' ),
									'cb_label'   => __( 'New listing submissions require admin approval', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, new submissions will be inactive, pending admin approval.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_user_can_edit_pending_submissions',
									'std'        => '0',
									'label'      => __( 'Allow Pending Edits', 'classifieds-wp' ),
									'cb_label'   => __( 'Submissions awaiting approval can be edited', 'classifieds-wp' ),
									'desc'       => __( 'If enabled, submissions awaiting admin approval can be edited by the user.', 'classifieds-wp' ),
									'type'       => 'checkbox',
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_submission_duration',
									'std'        => '30',
									'label'      => __( 'Listing Duration', 'classifieds-wp' ),
									'desc'       => __( 'How many <strong>days</strong> listings are live before expiring. Can be left blank to never expire.', 'classifieds-wp' ),
									'attributes' => array()
								),
								array(
									'name'       => 'classified_manager_allowed_contact_method',
									'std'        => '',
									'label'      => __( 'Contact Method', 'classifieds-wp' ),
									'desc'       => __( 'Choose the contact method for listings.', 'classifieds-wp' ),
									'type'       => 'select',
									'options'    => array(
										''      => __( 'Email address or phone number', 'classifieds-wp' ),
										'email' => __( 'Email addresses only', 'classifieds-wp' ),
										'phone'   => __( 'Phone numbers only', 'classifieds-wp' ),
									)
								),
								array(
									'name'       => 'classified_manager_listing_currency',
									'std'        => '$',
									'label'      => __( 'Listing Currency Symbol', 'classifieds-wp' ),
									'desc'       => __( 'Indicate the currency symbol that will be used for classified listings.', 'classifieds-wp' ),
									'attributes' => array()
								),
							),
						),
						'images' => array(
							'title'  => __( 'Images', 'classifieds-wp' ),
							'fields' =>	array(
								array(
									'name'     => 'classified_manager_require_images',
									'label'    => __( 'Require Images', 'classifieds-wp' ),
									'type'     => 'checkbox',
									'cb_label' => __( 'Require at least one image uploaded per listing', 'classifieds-wp' ),
									'desc'     => sprintf( __( 'You can set the image sizes in the <a href="%s">media settings</a> page.', 'classifieds-wp' ), esc_url( admin_url('options-media.php') ) ),
									'tip'      => '',
								),
								array(
									'name'    => 'classified_manager_num_images',
									'label'   => __( 'Max Images', 'classifieds-wp' ),
									'type'    => 'select',
									'std'     => 3,
									'options' => array(
										'1'	 => '1',
										'2'	 => '2',
										'3'	 => '3',
										'4'	 => '4',
										'5'	 => '5',
										'6'	 => '6',
										'7'	 => '7',
										'8'	 => '8',
										'9'	 => '9',
										'10' => '10',
									),
									'desc'	 => __( 'Images allowed per listing.', 'classifieds-wp' ),
								),
								array(
									'name'    => 'classified_manager_max_image_size',
									'label'   => __( 'Max File Size', 'classifieds-wp' ),
									'type'    => 'select',
									'options' => array(
										'10'	 => '10KB',
										'100'	 => '100KB',
										'250'	 => '250KB',
										'500'	 => '500KB',
										'1024'	 => '1MB',
										'2048'	 => '2MB',
										'5120'	 => '5MB',
										'7168'	 => '7MB',
										'10240'	 => '10MB',
									),
									'std'  => '250',
									'desc' => __( 'Maximum file size per image.', 'classifieds-wp' ),
								),
							),
						),
					)
				),
				'classified_pages' => array(
					__( 'Pages', 'classifieds-wp' ),
					array(
						'classified_submission' => array(
							'title'  => '',
							'fields' =>	array(
								array(
									'name' 		=> 'classified_manager_submit_classified_form_page_id',
									'std' 		=> '',
									'label' 	=> __( 'Submit Classified Form Page', 'classifieds-wp' ),
									'desc'		=> __( 'Select the page where you have placed the [submit_classified_form] shortcode. This lets the plugin know where the form is located.', 'classifieds-wp' ),
									'type'      => 'page'
								),
								array(
									'name' 		=> 'classified_manager_classified_dashboard_page_id',
									'std' 		=> '',
									'label' 	=> __( 'Classified Dashboard Page', 'classifieds-wp' ),
									'desc'		=> __( 'Select the page where you have placed the [classified_dashboard] shortcode. This lets the plugin know where the dashboard is located.', 'classifieds-wp' ),
									'type'      => 'page'
								),
								array(
									'name' 		=> 'classified_manager_classifieds_page_id',
									'std' 		=> '',
									'label' 	=> __( 'Classified Listings Page', 'classifieds-wp' ),
									'desc'		=> __( 'Select the page where you have placed the [classifieds] shortcode. This lets the plugin know where the classified listings page is located.', 'classifieds-wp' ),
									'type'      => 'page'
								),
							),
						),
					),
				)
			)
		);
	}

	/**
	 * register_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_settings() {
		$this->init_settings();

		foreach ( $this->settings as $tabs ) {

			foreach ( $tabs[1] as $key => $section ) {

				// Support for legacy options structure (without the sections group).
				if ( empty( $section['fields'] ) ) {

					$section = array(
						'title'  => '',
						'fields' => array( $section ),
					);

				}

				foreach( $section['fields'] as $option ) {

					if ( empty( $option['name'] ) ) {
						continue;
					}

					// Skip 'info' type options.
					if ( ! empty( $option['type'] ) && 'info' === $option['type'] ) {
						continue;
					}

					if ( isset( $option['std'] ) ) {
						add_option( $option['name'], $option['std'] );
					}
					register_setting( $this->settings_group, $option['name'] );
				}

			}

		}

	}

	/**
	 * output function.
	 *
	 * @access public
	 * @return void
	 */
	public function output() {
		$this->init_settings();
		?>
		<div class="wrap classified-manager-settings-wrap">
			<form method="post" action="options.php?teste=1">

				<?php settings_fields( $this->settings_group ); ?>

			    <h2 class="nav-tab-wrapper">
			    	<?php
			    		foreach ( $this->settings as $key => $section ) {
			    			echo '<a href="#settings-' . sanitize_title( $key ) . '" class="nav-tab">' . esc_html( $section[0] ) . '</a>';
			    		}
			    	?>
			    </h2>

				<?php
					if ( ! empty( $_GET['settings-updated'] ) ) {
						flush_rewrite_rules();
						echo '<div class="updated fade classified-manager-updated"><p>' . __( 'Settings successfully saved', 'classifieds-wp' ) . '</p></div>';
					}

					foreach ( $this->settings as $key => $tabs ) {

						echo '<div id="settings-' . sanitize_title( $key ) . '" class="settings_panel">';

						foreach ( $tabs[1] as $section_key => $section ) {

							// Support for legacy options structure (without the sections group).
							if ( empty( $section['fields'] ) ) {

								$section = array(
									'title'  => '',
									'fields' => array( $section ),
								);

							}

							if ( ! empty( $section['title'] ) ) {
								echo '<h2 class="title classified-manager-' . sanitize_title( $section_key ). '">' . $section['title'] . '</h2>';
							}

							echo '<table class="form-table">';

							foreach( $section['fields'] as $option ) {

								if ( empty( $option['name'] ) ) {
									continue;
								}

								$placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';
								$class          = ! empty( $option['class'] ) ? $option['class'] : '';
								$value          = get_option( $option['name'] );
								$option['type'] = ! empty( $option['type'] ) ? $option['type'] : '';
								$attributes     = array();

								if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) ) {
									foreach ( $option['attributes'] as $attribute_name => $attribute_value ) {
										$attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';
									}
								}

								echo '<tr valign="top" class="' . $class . '"><th scope="row"><label for="setting-' . $option['name'] . '">' . $option['label'] . '</th><td>';

								switch ( $option['type'] ) {

									case "checkbox" :

										?><label><input id="setting-<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" type="checkbox" value="1" <?php echo implode( ' ', $attributes ); ?> <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php

										if ( $option['desc'] )
											echo ' <p class="description">' . $option['desc'] . '</p>';

									break;
									case "textarea" :

										?><textarea id="setting-<?php echo $option['name']; ?>" class="large-text" cols="50" rows="3" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?>><?php echo esc_textarea( $value ); ?></textarea><?php

										if ( $option['desc'] )
											echo ' <p class="description">' . $option['desc'] . '</p>';

									break;
									case "select" :

										?><select id="setting-<?php echo $option['name']; ?>" class="regular-text" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?>><?php
											foreach( $option['options'] as $key => $name )
												echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';
										?></select><?php

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}

									break;
									case "page" :

										$args = array(
											'name'             => $option['name'],
											'id'               => $option['name'],
											'sort_column'      => 'menu_order',
											'sort_order'       => 'ASC',
											'show_option_none' => __( '--no page--', 'classifieds-wp' ),
											'echo'             => false,
											'selected'         => absint( $value )
										);

										echo str_replace(' id=', " data-placeholder='" . __( 'Select a page&hellip;', 'classifieds-wp' ) .  "' id=", wp_dropdown_pages( $args ) );

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}

									break;
									case "password" :

										?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}

									break;
									case "number" :
										?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="number" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}
									break;
									case "info" :

										echo '<div ' . implode( ' ', $attributes ) . '>';

										if ( ! empty( $option['text'] ) ) {
											echo ' <p class="text">' . $option['text'] . '</p>';
										}

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}

										echo '</div>';

										break;
									case "" :
									case "input" :
									case "text" :
										?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

										if ( $option['desc'] ) {
											echo ' <p class="description">' . $option['desc'] . '</p>';
										}
									break;
									default :
										do_action( 'wp_classified_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );
									break;

								}

								echo '</td></tr>';
							}

							echo '</table>';

						}

						echo '</div>';

					}
				?>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'classifieds-wp' ); ?>" />
				</p>
		    </form>
		</div>
		<script type="text/javascript">
			jQuery('.nav-tab-wrapper a').click(function() {
				jQuery('.settings_panel').hide();
				jQuery('.nav-tab-active').removeClass('nav-tab-active');
				jQuery( jQuery(this).attr('href') ).show();
				jQuery(this).addClass('nav-tab-active');
				return false;
			});
			jQuery('.nav-tab-wrapper a:first').click();
			jQuery('#setting-classified_manager_enable_registration').change(function(){
				if ( jQuery( this ).is(':checked') ) {
					jQuery('#setting-classified_manager_registration_role').closest('tr').show();
					jQuery('#setting-classified_manager_registration_username_from_email').closest('tr').show();
				} else {
					jQuery('#setting-classified_manager_registration_role').closest('tr').hide();
					jQuery('#setting-classified_manager_registration_username_from_email').closest('tr').hide();
				}
			}).change();
		</script>
		<?php
	}
}
