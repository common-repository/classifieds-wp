<?php
global $wp_post_types;

switch ( $classified->post_status ) :
	case 'publish' :
		printf( __( '%s listed successfully. To view your listing <a href="%s">click here</a>.', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( get_permalink( $classified->ID ) ) );
	break;
	case 'pending' :
		printf( __( '%s submitted successfully. Your listing will be visible once approved.', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( get_permalink( $classified->ID ) ) );
	break;
	default :
		do_action( 'classified_manager_classified_submitted_content_' . str_replace( '-', '_', sanitize_title( $classified->post_status ) ), $classified );
	break;
endswitch;

do_action( 'classified_manager_classified_submitted_content_after', sanitize_title( $classified->post_status ), $classified );