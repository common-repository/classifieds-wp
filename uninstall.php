<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

wp_clear_scheduled_hook( 'classified_manager_delete_old_previews' );
wp_clear_scheduled_hook( 'classified_manager_check_for_expired_classifieds' );

wp_trash_post( get_option( 'classified_manager_submit_classified_form_page_id' ) );
wp_trash_post( get_option( 'classified_manager_classified_dashboard_page_id' ) );
wp_trash_post( get_option( 'classified_manager_classifieds_page_id' ) );

$options = array(
	'wp_classified_manager_version',
	'classified_manager_per_page',
	'classified_manager_hide_unavaliable_classifieds',
	'classified_manager_enable_categories',
	'classified_manager_enable_default_category_multiselect',
	'classified_manager_category_filter_type',
	'classified_manager_user_requires_account',
	'classified_manager_enable_registration',
	'classified_manager_registration_role',
	'classified_manager_submission_requires_approval',
	'classified_manager_user_can_edit_pending_submissions',
	'classified_manager_submission_duration',
	'classified_manager_allowed_contact_method',
	'classified_manager_submit_classified_form_page_id',
	'classified_manager_classified_dashboard_page_id',
	'classified_manager_classifieds_page_id',
	'classified_manager_installed_terms',
	'classified_manager_submit_page_slug',
	'classified_manager_classified_dashboard_page_slug'
);

foreach ( $options as $option ) {
	delete_option( $option );
}