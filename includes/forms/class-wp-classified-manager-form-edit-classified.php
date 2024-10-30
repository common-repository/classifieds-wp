<?php

include_once( 'class-wp-classified-manager-form-submit-classified.php' );

/**
 * WP_Classified_Manager_Form_Edit_Classified class.
 */
class WP_Classified_Manager_Form_Edit_Classified extends WP_Classified_Manager_Form_Submit_Classified {

	public $form_name           = 'edit-classified';

	/** @var WP_Classified_Manager_Form_Edit_Classified The single instance of the class */
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
	 * Constructor
	 */
	public function __construct() {
		$this->classified_id = ! empty( $_REQUEST['classified_id'] ) ? absint( $_REQUEST[ 'classified_id' ] ) : 0;

		if  ( ! classified_manager_user_can_edit_classified( $this->classified_id ) ) {
			$this->classified_id = 0;
		}
	}

	/**
	 * output function.
	 */
	public function output( $atts = array() ) {
		$this->submit_handler();
		$this->submit();
	}

	/**
	 * Submit Step
	 */
	public function submit() {
		$classified = get_post( $this->classified_id );

		if ( empty( $this->classified_id  ) || ( $classified->post_status !== 'publish' && ! classified_manager_user_can_edit_pending_submissions() ) ) {
			echo wpautop( __( 'Invalid listing', 'classifieds-wp' ) );
			return;
		}

		$this->init_fields();

		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if ( ! isset( $this->fields[ $group_key ][ $key ]['value'] ) ) {
					if ( 'classified_title' === $key ) {
						$this->fields[ $group_key ][ $key ]['value'] = $classified->post_title;

					} elseif ( 'classified_description' === $key ) {
						$this->fields[ $group_key ][ $key ]['value'] = $classified->post_content;

					} elseif ( ! empty( $field['taxonomy'] ) ) {
						$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $classified->ID, $field['taxonomy'], array( 'fields' => 'ids' ) );

					} else {
						$this->fields[ $group_key ][ $key ]['value'] = get_post_meta( $classified->ID, '_' . $key, true );
					}
				}
			}
		}

		$this->fields = apply_filters( 'submit_classified_form_fields_get_classified_data', $this->fields, $classified );

		wp_enqueue_script( 'wp-classified-manager-classified-submission' );

		get_classified_manager_template( 'classified-submit.php', array(
			'form'               => $this->form_name,
			'classified_id'      => $this->get_classified_id(),
			'action'             => $this->get_action(),
			'classified_fields'  => $this->get_fields( 'classified' ),
			'step'               => $this->get_step(),
			'submit_button_text' => __( 'Save changes', 'classifieds-wp' )
		) );

		classified_manager_mv_enqueue_media_viewer( array( '_classified-images' ), array( 'post_id' => $classified->ID ) );
	}

	/**
	 * Submit Step is posted
	 */
	public function submit_handler() {
		if ( empty( $_POST['submit_classified'] ) ) {
			return;
		}

		try {

			// Get posted values
			$values = $this->get_posted_fields();

			// Validate required
			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Update the classified
			$this->save_classified( $values['classified']['classified_title'], $values['classified']['classified_description'], '', $values, false );
			$this->update_classified_data( $values );

			// Successful
			switch ( get_post_status( $this->classified_id ) ) {
				case 'publish' :
					echo '<div class="classified-manager-message">' . __( 'Your changes have been saved.', 'classifieds-wp' ) . ' <a href="' . esc_url( get_permalink( $this->classified_id ) ) . '">' . __( 'View &rarr;', 'classifieds-wp' ) . '</a>' . '</div>';
				break;
				default :
					echo '<div class="classified-manager-message">' . __( 'Your changes have been saved.', 'classifieds-wp' ) . '</div>';
				break;
			}

		} catch ( Exception $e ) {
			echo '<div class="classified-manager-error">' . $e->getMessage() . '</div>';
			return;
		}
	}
}
