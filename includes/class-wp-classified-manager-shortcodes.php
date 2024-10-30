<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Classified_Manager_Shortcodes class.
 */
class WP_Classified_Manager_Shortcodes {

	private $classified_dashboard_message = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'shortcode_action_handler' ) );
		add_action( 'classified_manager_classified_dashboard_content_edit', array( $this, 'edit_classified' ) );
		add_action( 'classified_manager_classified_filters_end', array( $this, 'classified_filter_classified_types' ), 20 );
		add_action( 'classified_manager_classified_filters_end', array( $this, 'classified_filter_results' ), 30 );
		add_action( 'classified_manager_output_classifieds_no_results', array( $this, 'output_no_results' ) );

		add_shortcode( 'submit_classified_form', array( $this, 'submit_classified_form' ) );
		add_shortcode( 'classified_dashboard', array( $this, 'classified_dashboard' ) );
		add_shortcode( 'classifieds', array( $this, 'output_classifieds' ) );
		add_shortcode( 'classified', array( $this, 'output_classified' ) );
		add_shortcode( 'classified_summary', array( $this, 'output_classified_summary' ) );
		add_shortcode( 'classified_apply', array( $this, 'output_classified_apply' ) );
	}

	/**
	 * Handle actions which need to be run before the shortcode e.g. post actions
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( is_page() && strstr( $post->post_content, '[classified_dashboard' ) ) {
			$this->classified_dashboard_handler();
		}
	}

	/**
	 * Show the classified submission form
	 */
	public function submit_classified_form( $atts = array() ) {
		return $GLOBALS['classified_manager']->forms->get_form( 'submit-classified', $atts );
	}

	/**
	 * Handles actions on classified dashboard
	 */
	public function classified_dashboard_handler() {
		if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'classified_manager_my_classified_actions' ) ) {

			$action        = sanitize_title( $_REQUEST['action'] );
			$classified_id = absint( $_REQUEST['classified_id'] );

			try {
				// Get Classified
				$classified = get_post( $classified_id );

				// Check ownership
				if ( ! classified_manager_user_can_edit_classified( $classified_id ) ) {
					throw new Exception( __( 'Invalid ID', 'classifieds-wp' ) );
				}

				switch ( $action ) {
					case 'mark_sold' :
						// Check status
						if ( $classified->_classified_unavailable == 1 )
							throw new Exception( __( 'This listing has already been marked as unavailable.', 'classifieds-wp' ) );

						// Update
						update_post_meta( $classified_id, '_classified_unavailable', 1 );

						// Message
						$this->classified_dashboard_message = '<div class="classified-manager-message">' . sprintf( __( '%s has been been marked as unavailable.', 'classifieds-wp' ), $classified->post_title ) . '</div>';
						break;
					case 'mark_not_sold' :
						// Check status
						if ( $classified->_classified_unavailable != 1 ) {
							throw new Exception( __( 'This listing has already been marked as available.', 'classifieds-wp' ) );
						}

						// Update
						update_post_meta( $classified_id, '_classified_unavailable', 0 );

						// Message
						$this->classified_dashboard_message = '<div class="classified-manager-message">' . sprintf( __( '%s has been marked as available.', 'classifieds-wp' ), $classified->post_title ) . '</div>';
						break;
					case 'delete' :
						// Trash it
						wp_trash_post( $classified_id );

						// Message
						$this->classified_dashboard_message = '<div class="classified-manager-message">' . sprintf( __( '%s has been deleted', 'classifieds-wp' ), $classified->post_title ) . '</div>';

						break;
					case 'relist' :
						// redirect to post page
						wp_redirect( add_query_arg( array( 'classified_id' => absint( $classified_id ) ), classified_manager_get_permalink( 'submit_classified_form' ) ) );

						break;
					default :
						do_action( 'classified_manager_classified_dashboard_do_action_' . $action );
						break;
				}

				do_action( 'classified_manager_my_classified_do_action', $action, $classified_id );

			} catch ( Exception $e ) {
				$this->classified_dashboard_message = '<div class="classified-manager-error">' . $e->getMessage() . '</div>';
			}
		}
	}

	/**
	 * Shortcode which lists the logged in user's classifieds
	 */
	public function classified_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			get_classified_manager_template( 'classified-dashboard-login.php' );
			return ob_get_clean();
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		wp_enqueue_script( 'wp-classified-manager-classified-dashboard' );

		ob_start();

		// If doing an action, show conditional content if needed....
		if ( ! empty( $_REQUEST['action'] ) ) {
			$action = sanitize_title( $_REQUEST['action'] );

			// Show alternative content if a plugin wants to
			if ( has_action( 'classified_manager_classified_dashboard_content_' . $action ) ) {
				do_action( 'classified_manager_classified_dashboard_content_' . $action, $atts );

				return ob_get_clean();
			}
		}

		// ....If not show the classified dashboard
		$args     = apply_filters( 'classified_manager_get_dashboard_classifieds_args', array(
			'post_type'           => 'classified_listing',
			'post_status'         => array( 'publish', 'expired', 'pending' ),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $posts_per_page,
			'offset'              => ( max( 1, get_query_var('paged') ) - 1 ) * $posts_per_page,
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id()
		) );

		$classifieds = new WP_Query;

		echo $this->classified_dashboard_message;

		$classified_dashboard_columns = apply_filters( 'classified_manager_classified_dashboard_columns', array(
			'classified_title' => __( 'Title', 'classifieds-wp' ),
			'classified_unavailable'    => __( 'Unavailable', 'classifieds-wp' ),
			'date'      => __( 'Date Posted', 'classifieds-wp' ),
			'expires'   => __( 'Listing Expires', 'classifieds-wp' )
		) );

		get_classified_manager_template( 'classified-dashboard.php', array( 'classifieds' => $classifieds->query( $args ), 'max_num_pages' => $classifieds->max_num_pages, 'classified_dashboard_columns' => $classified_dashboard_columns ) );

		return ob_get_clean();
	}

	/**
	 * Edit classified form
	 */
	public function edit_classified() {
		global $classified_manager;

		echo $classified_manager->forms->get_form( 'edit-classified' );
	}

	/**
	 * output_classifieds function.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	public function output_classifieds( $atts ) {

		$atts = apply_filters( 'classified_manager_output_classifieds_taxonomy_override', $this->maybe_override_taxonomy( $atts ), $atts );

		ob_start();

		extract( $atts = shortcode_atts( apply_filters( 'classified_manager_output_classifieds_defaults', array(
			'per_page'                  => get_option( 'classified_manager_per_page' ),
			'per_row'                   => get_option( 'classified_manager_per_row'),
			'orderby'                   => 'featured',
			'order'                     => 'DESC',

			// Filters + cats
			'show_filters'              => true,
			'show_categories'           => true,
			'show_category_multiselect' => get_option( 'classified_manager_enable_default_category_multiselect', false ),
			'show_pagination'           => false,
			'show_more'                 => true,

			// Limit what classifieds are shown based on category and type
			'categories'                => '',
			'classified_types'          => '',
			'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
			'classified_unavailable'    => null, // True to show only unavailable, false to hide unavailable, leave null to show both/use the settings.

			// Default values for filters
			'location'                  => '',
			'keywords'                  => '',
			'selected_category'         => '',
			'selected_classified_types' => implode( ',', array_values( get_classified_listing_types( 'id=>slug' ) ) ),
		) ), $atts ) );


		if ( ! get_option( 'classified_manager_enable_categories' ) ) {
			$show_categories = false;
		}

		// String and bool handling
		$show_filters              = $this->string_to_bool( $show_filters );
		$show_categories           = $this->string_to_bool( $show_categories );
		$show_category_multiselect = $this->string_to_bool( $show_category_multiselect );
		$show_more                 = $this->string_to_bool( $show_more );
		$show_pagination           = $this->string_to_bool( $show_pagination );

		if ( ! is_null( $featured ) ) {
			$featured = ( is_bool( $featured ) && $featured ) || in_array( $featured, array( '1', 'true', 'yes' ) ) ? true : false;
		}

		if ( ! is_null( $classified_unavailable ) ) {
			$classified_unavailable = ( is_bool( $classified_unavailable ) && $classified_unavailable ) || in_array( $classified_unavailable, array( '1', 'true', 'yes' ) ) ? true : false;
		}

		// Array handling
		$categories                = is_array( $categories ) ? $categories : array_filter( array_map( 'trim', explode( ',', $categories ) ) );
		$classified_types          = is_array( $classified_types ) ? $classified_types : array_filter( array_map( 'trim', explode( ',', $classified_types ) ) );
		$selected_classified_types = is_array( $selected_classified_types ) ? $selected_classified_types : array_filter( array_map( 'trim', explode( ',', $selected_classified_types ) ) );

		// Get keywords and location from querystring if set
		if ( ! empty( $_GET['search_keywords'] ) ) {
			$keywords = sanitize_text_field( $_GET['search_keywords'] );
		}
		if ( ! empty( $_GET['search_location'] ) ) {
			$location = sanitize_text_field( $_GET['search_location'] );
		}
		if ( ! empty( $_GET['search_category'] ) ) {
			$selected_category = sanitize_text_field( $_GET['search_category'] );
		}

		if ( $show_filters ) {

			get_classified_manager_template( 'classified-filters.php', array( 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order, 'show_categories' => $show_categories, 'categories' => $categories, 'selected_category' => $selected_category, 'classified_types' => $classified_types, 'atts' => $atts, 'location' => $location, 'keywords' => $keywords, 'selected_classified_types' => $selected_classified_types, 'show_category_multiselect' => $show_category_multiselect ) );

			get_classified_manager_template( 'classified-listings-start.php' );
			get_classified_manager_template( 'classified-listings-end.php' );

			if ( ! $show_pagination && $show_more ) {
				echo '<a class="load_more_classifieds" href="#" style="display:none;"><strong>' . __( 'Load more listings', 'classifieds-wp' ) . '</strong></a>';
			}

		} else {

			$classifieds = get_classified_listings( apply_filters( 'classified_manager_output_classifieds_args', array(
				'search_location'        => $location,
				'search_keywords'        => $keywords,
				'search_categories'      => $categories,
				'classified_types'       => $classified_types,
				'orderby'                => $orderby,
				'order'                  => $order,
				'posts_per_page'         => $per_page,
				'posts_per_row'          => $per_row,
				'featured'               => $featured,
				'classified_unavailable' => $classified_unavailable
			) ) );

			if ( $classifieds->have_posts() ) : ?>

				<?php get_classified_manager_template( 'classified-listings-start.php' ); ?>

				<?php while ( $classifieds->have_posts() ) : $classifieds->the_post(); ?>

					<?php get_classified_manager_template( 'content-classified_listing.php', array( 'per_row' => $per_row ) ); ?>

				<?php endwhile; ?>

				<?php get_classified_manager_template( 'classified-listings-end.php' ); ?>

				<?php if ( $classifieds->found_posts > $per_page && $show_more ) : ?>

					<?php wp_enqueue_script( 'wp-classified-manager-ajax-filters' ); ?>

					<?php if ( $show_pagination ) : ?>
						<?php echo get_classified_listing_pagination( $classifieds->max_num_pages ); ?>
					<?php else : ?>
						<a class="load_more_classifieds" href="#"><strong><?php _e( 'Load more listings', 'classifieds-wp' ); ?></strong></a>
					<?php endif; ?>

				<?php endif; ?>

			<?php else :
				do_action( 'classified_manager_output_classifieds_no_results' );
			endif;

			wp_reset_postdata();
		}

		$data_attributes_string = '';
		$data_attributes        = array(
			'location'        => $location,
			'keywords'        => $keywords,
			'show_filters'    => $show_filters ? 'true' : 'false',
			'show_pagination' => $show_pagination ? 'true' : 'false',
			'per_page'        => $per_page,
			'per_row'		  => $per_row,
			'orderby'         => $orderby,
			'order'           => $order,
			'categories'      => implode( ',', $categories ),
		);
		if ( ! is_null( $featured ) ) {
			$data_attributes[ 'featured' ] = $featured ? 'true' : 'false';
		}
		if ( ! is_null( $classified_unavailable ) ) {
			$data_attributes[ 'classified_unavailable' ]   = $classified_unavailable ? 'true' : 'false';
		}
		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$classified_listings_output = apply_filters( 'classified_manager_classified_listings_output', ob_get_clean() );

		return '<div class="classified_listings" ' . $data_attributes_string . '>' . $classified_listings_output . '</div>';
	}

	/**
	 * Output some content when no results were found
	 */
	public function output_no_results() {
		get_classified_manager_template( 'content-no-classifieds-found.php' );
	}

	/**
	 * Get string as a bool
	 * @param  string $value
	 * @return bool
	 */
	public function string_to_bool( $value ) {
		return ( is_bool( $value ) && $value ) || in_array( $value, array( '1', 'true', 'yes' ) ) ? true : false;
	}

	/**
	 * Show classified types
	 * @param  array $atts
	 */
	public function classified_filter_classified_types( $atts ) {
		extract( $atts );

		$classified_types          = array_filter( array_map( 'trim', explode( ',', $classified_types ) ) );
		$selected_classified_types = array_filter( array_map( 'trim', explode( ',', $selected_classified_types ) ) );

		get_classified_manager_template( 'classified-filter-classified-types.php', array( 'classified_types' => $classified_types, 'atts' => $atts, 'selected_classified_types' => $selected_classified_types ) );
	}

	/**
	 * Show results div
	 */
	public function classified_filter_results() {
		echo '<div class="showing_classifieds"></div>';
	}

	/**
	 * output_classified function.
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_classified( $atts ) {
		extract( shortcode_atts( array(
			'id' => '',
		), $atts ) );

		if ( ! $id )
			return;

		ob_start();

		$args = array(
			'post_type'   => 'classified_listing',
			'post_status' => 'publish',
			'p'           => $id
		);

		$classifieds = new WP_Query( $args );

		if ( $classifieds->have_posts() ) : ?>

			<?php while ( $classifieds->have_posts() ) : $classifieds->the_post(); ?>

				<h1><?php the_title(); ?></h1>

				<?php get_classified_manager_template_part( 'content-single', 'classified_listing' ); ?>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return '<div class="classified_shortcode single_classified_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Classified Summary shortcode
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_classified_summary( $atts ) {
		extract( shortcode_atts( array(
			'id'       => '',
			'width'    => '250px',
			'align'    => 'left',
			'featured' => null, // True to show only featured, false to hide featured, leave null to show both (when leaving out id)
			'limit'    => 1
		), $atts ) );

		ob_start();

		$args = array(
			'post_type'   => 'classified_listing',
			'post_status' => 'publish'
		);

		if ( ! $id ) {
			$args['posts_per_page'] = $limit;
			$args['orderby']        = 'rand';
			if ( ! is_null( $featured ) ) {
				$args['meta_query'] = array( array(
					'key'     => '_featured',
					'value'   => '1',
					'compare' => $featured ? '=' : '!='
				) );
			}
		} else {
			$args['p'] = absint( $id );
		}

		$classifieds = new WP_Query( $args );

		if ( $classifieds->have_posts() ) : ?>

			<?php while ( $classifieds->have_posts() ) : $classifieds->the_post(); ?>

				<div class="classified_summary_shortcode align<?php echo $align ?>" style="width: <?php echo $width ? $width : auto; ?>">

					<?php get_classified_manager_template_part( 'content-summary', 'classified_listing' ); ?>

				</div>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Show the contact area
	 */
	public function output_classified_apply( $atts ) {
		extract( shortcode_atts( array(
			'id'       => ''
		), $atts ) );

		ob_start();

		$args = array(
			'post_type'   => 'classified_listing',
			'post_status' => 'publish'
		);

		if ( ! $id ) {
			return '';
		} else {
			$args['p'] = absint( $id );
		}

		$classifieds = new WP_Query( $args );

		if ( $classifieds->have_posts() ) : ?>

			<?php while ( $classifieds->have_posts() ) :
				$classifieds->the_post();
				$contact = get_the_classified_contact_method();
				?>

				<?php do_action( 'classified_manager_before_classified_apply_' . absint( $id ) ); ?>

				<?php if ( apply_filters( 'classified_manager_show_classified_apply_' . absint( $id ), true ) ) : ?>
					<div class="classified-manager-contact-wrapper">
						<?php do_action( 'classified_manager_contact_details_' . $contact->type, $contact ); ?>
					</div>
				<?php endif; ?>

				<?php do_action( 'classified_manager_after_classified_apply_' . absint( $id ) ); ?>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Checks for taxonomy queries from the taxonomies widget and overrides the related shortcode attributes.
	 *
	 * @since 1.1
	 */
	protected function maybe_override_taxonomy( $atts ) {

		if ( ! empty( $_GET['qcm'] ) ) {

			$taxonomy = sanitize_text_field( $_GET['taxonomy'] );
			$term     = sanitize_text_field( $_GET['term'] );

			if ( 'classified_listing_category' === $taxonomy ) {
				$term_obj = get_term_by( 'slug', $term, 'classified_listing_category' );

				if ( ! empty( $term_obj ) ) {
					$atts['selected_category'] = $term_obj->term_id;
				}

			} else {
				$atts['selected_classified_types'] = $term;
			}

		}
		return $atts;
	}

}

new WP_Classified_Manager_Shortcodes();
