<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists('WP_List_Table') ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Extends 'WP_List_Table' table for displaying a list of Add-ons and their licenses status.
 */
class WP_Classified_Manager_License_Manager_List extends WP_List_Table {

    function __construct() {

        // Set parent defaults.
        parent::__construct( array(
            'singular' => 'addon',
            'plural'   => 'addons',
            'ajax'     => false
        ) );

    }

    /**
     * The default output for the columns.
     */
    protected function column_default( $item, $column_name ) {

        switch( $column_name ) {
            case 'status':
                $item[ $column_name ] = 'active' === $item[ $column_name ] ? '<div class="license-status active"></div>' . __( 'Activated', 'classifieds-wp' ) : '<div class="license-status deactivated"></div>' . __( 'Deactivated', 'classifieds-wp' );

            case 'license_key':
                return ! empty( $item[ $column_name ] ) ? $item[ $column_name ] : '-';

           case 'version':
                return $item[ $column_name ] . $item['license']->download_update_link();

            default:
                return print_r( $item, true ); // Display the whole array for troubleshooting purposes.
        }

    }

    /**
     * The custom title column output.
     */
    protected function column_title( $item ) {

        $actions = array(
			'edit' => sprintf('<a href="%1$s">Edit</a>', esc_url( add_query_arg( 'addon', $item['ID'], WP_Classified_Manager_License_Manager_Menu::get_page( 'edit', 'url' ) ) ) ),
        );

        return sprintf('<strong>%1$s</strong> %2$s', $item['title'], $this->row_actions( $actions ) );
    }

    /**
     * Retrieves the column list.
     */
    public function get_columns() {
        $columns = array(
            'title'       => __( 'Add-on', 'classifieds-wp' ),
            'license_key' => __( 'License Key', 'classifieds-wp' ),
            'status'      => __( 'Status', 'classifieds-wp' ),
            'version'     => __( 'Version', 'classifieds-wp' ),
        );
        return $columns;
    }

    /**
     * Retrieves the sortable columns list,
     */
    protected function get_sortable_columns() {
        $sortable_columns = array(
            'title'       => array( 'title', false ),
            'status'      => array( 'status', false ),
            'license_key' => array( 'license_key',false )
        );
        return $sortable_columns;
    }

    /**
     * Sort columns.
     */
	private function usort_reorder($a,$b){
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'title';
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order']     : 'asc';
		$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return ( $order === 'asc' ) ? $result : -$result;
	}

    /**
     * Retrieves the add-ons/licenses as a tabular list.
     */
    protected function prepare_data() {

        $list = array();

        foreach( wpcm_license_manager()->get_licenses() as $addon => $license ) {

            $list[] = array(
                'ID'          => $addon,
                'title'       => $addon,
                'license_key' => $license->api_key,
                'status'      => $license->status,
                'version'     => $license->version,
                'license'     => $license,
            );

        }
        return $list;
    }

    /**
     * Prepare and retrieve the items to be displayed.
     */
    public function prepare_items() {

        $per_page = 5;

        $columns  = $this->get_columns();
        $sortable = $this->get_sortable_columns();
        $hidden   = array();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        $data = $this->prepare_data();

        usort( $data, array( $this, 'usort_reorder' ) );

        $current_page = $this->get_pagenum();
        $total_items  = count( $data );

        $data = array_slice( $data, ( ( $current_page-1 ) * $per_page ), $per_page );

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
    }


    /**
     * Display the list of add-ons/licenses.
     */
    public function list_licenses() {

        // Fetch, prepare, sort, and filter the data...
        $this->prepare_items();

        WP_Classified_Manager_License_Manager_Edit::add_inline_css( true );

        ?><div class="wrap">

            <h2><?php echo __( 'Manage Add-ons Licenses', 'classifieds-wp' ); ?></h2>

            <p><?php echo sprintf( __( 'Here you can manage all your installed <em>%s</em> add-ons and manage their licenses.', 'classifieds-wp' ), 'Classifieds WP' ); ?></p>

            <?php //@todo: maybe add filters ?>
            <?php //@todo: maybe add search ?>

            <form id="addons-filter" method="get">

                <!-- Ensure that the form posts back to our current page -->
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />

                <!-- Render the completed list table -->
                <?php $this->display() ?>

            </form>

        </div><?php
    }

}
