<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Classified_Manager_CPT class.
 */
class WP_Classified_Manager_CPT {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_filter( 'enter_title_here', array( $this, 'enter_title_here' ), 1, 2 );
		add_filter( 'manage_edit-classified_listing_columns', array( $this, 'columns' ) );
		add_action( 'manage_classified_listing_posts_custom_column', array( $this, 'custom_columns' ), 2 );
		add_filter( 'manage_edit-classified_listing_sortable_columns', array( $this, 'sortable_columns' ) );
		add_filter( 'request', array( $this, 'sort_columns' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_bulk_actions' ) );
		add_action( 'load-edit.php', array( $this, 'do_bulk_actions' ) );
		add_action( 'admin_init', array( $this, 'approve_classified' ) );
		add_action( 'admin_notices', array( $this, 'approved_notice' ) );
		add_action( 'admin_notices', array( $this, 'expired_notice' ) );

		if ( get_option( 'classified_manager_enable_categories' ) ) {
			add_action( "restrict_manage_posts", array( $this, "classifieds_by_category" ) );
		}

		foreach ( array( 'post', 'post-new' ) as $hook ) {
			add_action( "admin_footer-{$hook}.php", array( $this,'extend_submitdiv_post_status' ) );
		}
	}

	/**
	 * Edit bulk actions
	 */
	public function add_bulk_actions() {
		global $post_type, $wp_post_types;;

		if ( $post_type == 'classified_listing' ) {
			?>
			<script type="text/javascript">
		      jQuery(document).ready(function() {
		        jQuery('<option>').val('approve_classifieds').text('<?php printf( __( 'Approve %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ); ?>').appendTo("select[name='action']");
		        jQuery('<option>').val('approve_classifieds').text('<?php printf( __( 'Approve %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ); ?>').appendTo("select[name='action2']");

		        jQuery('<option>').val('expire_classifieds').text('<?php printf( __( 'Expire %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ); ?>').appendTo("select[name='action']");
		        jQuery('<option>').val('expire_classifieds').text('<?php printf( __( 'Expire %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ); ?>').appendTo("select[name='action2']");
		      });
		    </script>
		    <?php
		}
	}

	/**
	 * Do custom bulk actions
	 */
	public function do_bulk_actions() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		switch( $action ) {
			case 'approve_classifieds' :
				check_admin_referer( 'bulk-posts' );

				$post_ids      = array_map( 'absint', array_filter( (array) $_GET['post'] ) );
				$approved_classifieds = array();

				if ( ! empty( $post_ids ) )
					foreach( $post_ids as $post_id ) {
						$classified_data = array(
							'ID'          => $post_id,
							'post_status' => 'publish'
						);
						if ( in_array( get_post_status( $post_id ), array( 'pending', 'pending_payment' ) ) && current_user_can( 'publish_post', $post_id ) && wp_update_post( $classified_data ) ) {
							$approved_classifieds[] = $post_id;
						}
					}

				wp_redirect( add_query_arg( 'approved_classifieds', $approved_classifieds, remove_query_arg( array( 'approved_classifieds', 'expired_classifieds' ), admin_url( 'edit.php?post_type=classified_listing' ) ) ) );
				exit;
			break;
			case 'expire_classifieds' :
				check_admin_referer( 'bulk-posts' );

				$post_ids     = array_map( 'absint', array_filter( (array) $_GET['post'] ) );
				$expired_classifieds = array();

				if ( ! empty( $post_ids ) )
					foreach( $post_ids as $post_id ) {
						$classified_data = array(
							'ID'          => $post_id,
							'post_status' => 'expired'
						);
						if ( current_user_can( 'manage_classified_listings' ) && wp_update_post( $classified_data ) )
							$expired_classifieds[] = $post_id;
					}

				wp_redirect( add_query_arg( 'expired_classifieds', $expired_classifieds, remove_query_arg( array( 'approved_classifieds', 'expired_classifieds' ), admin_url( 'edit.php?post_type=classified_listing' ) ) ) );
				exit;
			break;
		}

		return;
	}

	/**
	 * Approve a single classified
	 */
	public function approve_classified() {
		if ( ! empty( $_GET['approve_classified'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'approve_classified' ) && current_user_can( 'publish_post', $_GET['approve_classified'] ) ) {
			$post_id = absint( $_GET['approve_classified'] );
			$classified_data = array(
				'ID'          => $post_id,
				'post_status' => 'publish'
			);
			wp_update_post( $classified_data );
			wp_redirect( remove_query_arg( 'approve_classified', add_query_arg( 'approved_classifieds', $post_id, admin_url( 'edit.php?post_type=classified_listing' ) ) ) );
			exit;
		}
	}

	/**
	 * Show a notice if we did a bulk action or approval
	 */
	public function approved_notice() {
		 global $post_type, $pagenow;

		if ( $pagenow == 'edit.php' && $post_type == 'classified_listing' && ! empty( $_REQUEST['approved_classifieds'] ) ) {
			$approved_classifieds = $_REQUEST['approved_classifieds'];
			if ( is_array( $approved_classifieds ) ) {
				$approved_classifieds = array_map( 'absint', $approved_classifieds );
				$titles        = array();
				foreach ( $approved_classifieds as $classified_id )
					$titles[] = get_the_title( $classified_id );
				echo '<div class="updated"><p>' . sprintf( __( '%s approved', 'classifieds-wp' ), '&quot;' . implode( '&quot;, &quot;', $titles ) . '&quot;' ) . '</p></div>';
			} else {
				echo '<div class="updated"><p>' . sprintf( __( '%s approved', 'classifieds-wp' ), '&quot;' . get_the_title( $approved_classifieds ) . '&quot;' ) . '</p></div>';
			}
		}
	}

	/**
	 * Show a notice if we did a bulk action or approval
	 */
	public function expired_notice() {
		 global $post_type, $pagenow;

		if ( $pagenow == 'edit.php' && $post_type == 'classified_listing' && ! empty( $_REQUEST['expired_classifieds'] ) ) {
			$expired_classifieds = $_REQUEST['expired_classifieds'];
			if ( is_array( $expired_classifieds ) ) {
				$expired_classifieds = array_map( 'absint', $expired_classifieds );
				$titles        = array();
				foreach ( $expired_classifieds as $classified_id )
					$titles[] = get_the_title( $classified_id );
				echo '<div class="updated"><p>' . sprintf( __( '%s expired', 'classifieds-wp' ), '&quot;' . implode( '&quot;, &quot;', $titles ) . '&quot;' ) . '</p></div>';
			} else {
				echo '<div class="updated"><p>' . sprintf( __( '%s expired', 'classifieds-wp' ), '&quot;' . get_the_title( $expired_classifieds ) . '&quot;' ) . '</p></div>';
			}
		}
	}

	/**
	 * Show category dropdown
	 */
	public function classifieds_by_category() {
		global $typenow, $wp_query;

	    if ( $typenow != 'classified_listing' || ! taxonomy_exists( 'classified_listing_category' ) ) {
	    	return;
	    }

	    include_once( WP_CLASSIFIED_MANAGER_PLUGIN_DIR . '/includes/class-wp-classified-manager-category-walker.php' );

		$r                 = array();
		$r['pad_counts']   = 1;
		$r['hierarchical'] = 1;
		$r['hide_empty']   = 0;
		$r['show_count']   = 1;
		$r['selected']     = ( isset( $wp_query->query['classified_listing_category'] ) ) ? $wp_query->query['classified_listing_category'] : '';
		$r['menu_order']   = false;
		$terms             = get_terms( 'classified_listing_category', $r );
		$walker            = new WP_Classified_Manager_Category_Walker;

		if ( ! $terms ) {
			return;
		}

		$output  = "<select name='classified_listing_category' id='dropdown_classified_listing_category'>";
		$output .= '<option value="" ' . selected( isset( $_GET['classified_listing_category'] ) ? $_GET['classified_listing_category'] : '', '', false ) . '>' . __( 'Select category', 'classifieds-wp' ) . '</option>';
		$output .= $walker->walk( $terms, 0, $r );
		$output .= "</select>";

		echo $output;
	}

	/**
	 * enter_title_here function.
	 *
	 * @access public
	 * @return void
	 */
	public function enter_title_here( $text, $post ) {
		if ( $post->post_type == 'classified_listing' )
			return __( 'Listing', 'classifieds-wp' );
		return $text;
	}

	/**
	 * post_updated_messages function.
	 *
	 * @access public
	 * @param mixed $messages
	 * @return void
	 */
	public function post_updated_messages( $messages ) {
		global $post, $post_ID, $wp_post_types;

		$messages['classified_listing'] = array(
			0 => '',
			1 => sprintf( __( '%s updated. <a href="%s">View</a>', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', 'classifieds-wp' ),
			3 => __( 'Custom field deleted.', 'classifieds-wp' ),
			4 => sprintf( __( '%s updated.', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( '%s published. <a href="%s">View</a>', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( get_permalink( $post_ID ) ) ),
			7 => sprintf( __( '%s saved.', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name ),
			8 => sprintf( __( '%s submitted. <a target="_blank" href="%s">Preview</a>', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			9 => sprintf( __( '%s scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview</a>', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name,
			  date_i18n( __( 'M j, Y @ G:i', 'classifieds-wp' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( '%s draft updated. <a target="_blank" href="%s">Preview</a>', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->singular_name, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}

	/**
	 * columns function.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		unset( $columns['title'], $columns['date'], $columns['author'] );

		$columns["classified_listing_type"]     = __( "Type", 'classifieds-wp' );
		$columns["classified_listing"]          = __( "Listing", 'classifieds-wp' );
		$columns["classified_location"]         = __( "Location", 'classifieds-wp' );
		$columns['classified_status']           = '<span class="tips" data-tip="' . __( "Status", 'classifieds-wp' ) . '">' . __( "Status", 'classifieds-wp' ) . '</span>';
		$columns["classified_posted"]           = __( "Posted", 'classifieds-wp' );
		$columns["classified_expires"]          = __( "Expires", 'classifieds-wp' );
		$columns["classified_listing_category"] = __( "Categories", 'classifieds-wp' );
		$columns['featured_classified']         = '<span class="tips" data-tip="' . __( "Featured?", 'classifieds-wp' ) . '">' . __( "Featured?", 'classifieds-wp' ) . '</span>';
		$columns['classified_unavailable']      = '<span class="tips" data-tip="' . __( "Unavailable", 'classifieds-wp' ) . '">' . __( "Unavailable", 'classifieds-wp' ) . '</span>';
		$columns['classified_actions']          = __( "Actions", 'classifieds-wp' );

		if ( ! get_option( 'classified_manager_enable_categories' ) ) {
			unset( $columns["classified_listing_category"] );
		}

		return $columns;
	}

	/**
	 * custom_columns function.
	 *
	 * @access public
	 * @param mixed $column
	 * @return void
	 */
	public function custom_columns( $column ) {
		global $post;

		switch ( $column ) {
			case "classified_listing_type" :
				$type = get_the_classified_type( $post );
				if ( $type )
					echo '<span class="classified-type ' . $type->slug . '">' . $type->name . '</span>';
			break;
			case "classified_listing" :
				echo '<div class="classified_listing">';
				echo '<a href="' . admin_url('post.php?post=' . $post->ID . '&action=edit') . '" class="tips classified_title" data-tip="' . sprintf( __( 'ID: %d', 'classifieds-wp' ), $post->ID ) . '">' . $post->post_title . '</a>';

				the_classified_featured_image();
				echo '</div>';
			break;
			case "classified_location" :
				the_classified_location( $post );
			break;
			case "classified_listing_category" :
				if ( ! $terms = get_the_term_list( $post->ID, $column, '', ', ', '' ) ) echo '<span class="na">&ndash;</span>'; else echo $terms;
			break;
			case "classified_unavailable" :
				if ( is_classified_unavailable( $post ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "featured_classified" :
				if ( is_listing_featured( $post ) ) echo '&#10004;'; else echo '&ndash;';
			break;
			case "classified_posted" :
				echo '<strong>' . date_i18n( __( 'M j, Y', 'classifieds-wp' ), strtotime( $post->post_date ) ) . '</strong><span>';
				echo ( empty( $post->post_author ) ? __( 'by a guest', 'classifieds-wp' ) : sprintf( __( 'by %s', 'classifieds-wp' ), '<a href="' . esc_url( add_query_arg( 'author', $post->post_author ) ) . '">' . get_the_author() . '</a>' ) ) . '</span>';
			break;
			case "classified_expires" :
				if ( $post->_classified_expires )
					echo '<strong>' . date_i18n( __( 'M j, Y', 'classifieds-wp' ), strtotime( $post->_classified_expires ) ) . '</strong>';
				else
					echo '&ndash;';
			break;
			case "classified_status" :
				echo '<span data-tip="' . esc_attr( get_the_classified_status( $post ) ) . '" class="tips status-' . esc_attr( $post->post_status ) . '">' . get_the_classified_status( $post ) . '</span>';
			break;
			case "classified_actions" :
				echo '<div class="actions">';
				$admin_actions = apply_filters( 'post_row_actions', array(), $post );

				if ( in_array( $post->post_status, array( 'pending', 'pending_payment' ) ) && current_user_can ( 'publish_post', $post->ID ) ) {
					$admin_actions['approve']   = array(
						'action'  => 'approve',
						'name'    => __( 'Approve', 'classifieds-wp' ),
						'url'     =>  wp_nonce_url( add_query_arg( 'approve_classified', $post->ID ), 'approve_classified' )
					);
				}
				if ( $post->post_status !== 'trash' ) {
					if ( current_user_can( 'read_post', $post->ID ) ) {
						$admin_actions['view']   = array(
							'action'  => 'view',
							'name'    => __( 'View', 'classifieds-wp' ),
							'url'     => get_permalink( $post->ID )
						);
					}
					if ( current_user_can( 'edit_post', $post->ID ) ) {
						$admin_actions['edit']   = array(
							'action'  => 'edit',
							'name'    => __( 'Edit', 'classifieds-wp' ),
							'url'     => get_edit_post_link( $post->ID )
						);
					}
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						$admin_actions['delete'] = array(
							'action'  => 'delete',
							'name'    => __( 'Delete', 'classifieds-wp' ),
							'url'     => get_delete_post_link( $post->ID )
						);
					}
				}

				$admin_actions = apply_filters( 'classified_manager_admin_actions', $admin_actions, $post );

				foreach ( $admin_actions as $action ) {
					if ( is_array( $action ) ) {
						printf( '<a class="button button-icon tips icon-%1$s" href="%2$s" data-tip="%3$s">%4$s</a>', $action['action'], esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_html( $action['name'] ) );
					} else {
						echo str_replace( 'class="', 'class="button ', $action );
					}
				}

				echo '</div>';

			break;
		}
	}

	/**
	 * sortable_columns function.
	 *
	 * @access public
	 * @param mixed $columns
	 * @return void
	 */
	public function sortable_columns( $columns ) {
		$custom = array(
			'classified_posted'   => 'date',
			'classified_listing' => 'title',
			'classified_location' => 'classified_location',
			'classified_expires'  => 'classified_expires'
		);
		return wp_parse_args( $custom, $columns );
	}

	/**
	 * sort_columns function.
	 *
	 * @access public
	 * @param mixed $vars
	 * @return void
	 */
	public function sort_columns( $vars ) {
		if ( isset( $vars['orderby'] ) ) {
			if ( 'classified_expires' === $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
					'meta_key' 	=> '_classified_expires',
					'orderby' 	=> 'meta_value'
				) );
			} elseif ( 'classified_location' === $vars['orderby'] ) {
				$vars = array_merge( $vars, array(
					'meta_key' 	=> '_classified_location',
					'orderby' 	=> 'meta_value'
				) );
			}
		}
		return $vars;
	}

    /**
	 * Adds post status to the "submitdiv" Meta Box and post type WP List Table screens. Based on https://gist.github.com/franz-josef-kaiser/2930190
	 *
	 * @return void
	 */
	public function extend_submitdiv_post_status() {
		global $post, $post_type;

		// Abort if we're on the wrong post type, but only if we got a restriction
		if ( 'classified_listing' !== $post_type ) {
			return;
		}

		// Get all non-builtin post status and add them as <option>
		$options = $display = '';
		foreach ( get_classified_listing_post_statuses() as $status => $name ) {
			$selected = selected( $post->post_status, $status, false );

			// If we one of our custom post status is selected, remember it
			$selected AND $display = $name;

			// Build the options
			$options .= "<option{$selected} value='{$status}'>{$name}</option>";
		}
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function($) {
				<?php if ( ! empty( $display ) ) : ?>
					jQuery( '#post-status-display' ).html( '<?php echo $display; ?>' );
				<?php endif; ?>

				var select = jQuery( '#post-status-select' ).find( 'select' );
				jQuery( select ).html( "<?php echo $options; ?>" );
			} );
		</script>
		<?php
	}
}

new WP_Classified_Manager_CPT();
