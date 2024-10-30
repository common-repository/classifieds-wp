<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ClassifiedsWP Sidebars.
 */
class WP_Classified_Manager_Sidebar {

	/**
	 * Register the new sidebars.
	 */
	public static function register_sidebars() {

	   register_sidebar(
			array(
				'name'          => __( 'Single Classified Sidebar', 'classifieds-wp' ),
				'id'            => 'classified-manager-sidebar-single',
				'description'   => __( 'Widgets in this area will be shown on single classified listings.', 'classifieds-wp' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);

	}

}

/**
 * ClassifiedWP Widgets.
 */
class WP_Classified_Manager_Widget extends WP_Widget {

	public $widget_cssclass;
	public $widget_description;
	public $widget_id;
	public $widget_name;
	public $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Register Widget
	 */
	public function register() {
		$widget_ops = array(
			'classname'   => $this->widget_cssclass,
			'description' => $this->widget_description
		);

		parent::__construct( $this->widget_id, $this->widget_name, $widget_ops );

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * get_cached_widget function.
	 */
	function get_cached_widget( $args ) {
		$cache = wp_cache_get( $this->widget_id, 'widget' );

		if ( ! is_array( $cache ) )
			$cache = array();

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return true;
		}

		return false;
	}

	/**
	 * Cache the widget
	 */
	public function cache_widget( $args, $content ) {
		$cache[ $args['widget_id'] ] = $content;
		wp_cache_set( $this->widget_id, $cache, 'widget' );
	}

	/**
	 * Flush the cache
	 * @return [type]
	 */
	public function flush_widget_cache() {
		wp_cache_delete( $this->widget_id, 'widget' );
	}

	/**
	 * update function.
	 *
	 * @see WP_Widget->update
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		if ( ! $this->settings ) {
			return $instance;
		}

		foreach ( $this->settings as $key => $setting ) {
			$instance[ $key ] = sanitize_text_field( $new_instance[ $key ] );
		}

		$this->flush_widget_cache();

		return $instance;
	}

	/**
	 * form function.
	 *
	 * @see WP_Widget->form
	 * @access public
	 * @param array $instance
	 * @return void
	 */
	function form( $instance ) {

		if ( ! $this->settings ) {
			return;
		}

		foreach ( $this->settings as $key => $setting ) {

			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : ( isset( $setting['std'] ) ? $setting['std'] : '' );

			if ( ! empty( $opened_p ) && $opened_p !== $setting['type'] ) {
				echo '</p>';
			}

			switch ( $setting['type'] ) {
				case 'text' :
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
				break;

				case 'number' :
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting['label']; ?></label>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					</p>
					<?php
				break;

				case 'select' :
					?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting['label']; ?></label>
						<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>">
							<option value="0"><?php _e( '&mdash; Select &mdash;', 'classifieds-wp' ); ?></option>
							<?php foreach( $setting['options'] as $key => $option ): ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key == $value ); ?>><?php echo $option; ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<?php
				break;

				case 'checkbox' :
					$checked = isset( $instance[ $key ] ) ? (bool) $instance[ $key ] : false;

					if ( empty( $previous_setting_type ) || 'checkbox' !== $previous_setting_type ) {
						$opened_p = $setting['type'];
						echo '<p>';
					} else {
						echo '<br/>';
					}
					?>
						<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="checkbox" <?php checked( $checked ); ?> />
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo $setting['label']; ?></label>
					<?php
				break;
			}
			$previous_setting_type = $setting['type'];
		}
	}
}

/**
 * Recent Classifieds Widget
 */
class WP_Classified_Manager_Widget_Recent_Classifieds extends WP_Classified_Manager_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wp_post_types;

		$this->widget_cssclass    = 'classified_manager widget_recent_classifieds';
		$this->widget_description = __( 'Display a list of recent listings on your site, optionally matching a keyword and location.', 'classifieds-wp' );
		$this->widget_id          = 'widget_recent_classifieds';
		$this->widget_name        = sprintf( __( '%1$s - Recent %2$s', 'classifieds-wp' ), 'ClassifiedsWP', $wp_post_types['classified_listing']->labels->name );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => sprintf( __( 'Recent %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ),
				'label' => __( 'Title', 'classifieds-wp' )
			),
			'keyword' => array(
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Keyword', 'classifieds-wp' )
			),
			'location' => array(
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Location', 'classifieds-wp' )
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'classifieds-wp' )
			)
		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		ob_start();

		extract( $args );

		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number = absint( $instance['number'] );

		$classifieds = get_classified_listings( array(
			'search_location'   => isset( $instance['location'] ) ? $instance['location'] : '',
			'search_keywords'   => isset( $instance['keyword'] ) ? $instance['keyword'] : '',
			'posts_per_page'    => $number,
			'orderby'           => 'date',
			'order'             => 'DESC',
		) );

		if ( $classifieds->have_posts() ) : ?>

			<?php echo $before_widget; ?>

			<?php if ( $title ) echo $before_title . $title . $after_title; ?>

			<ul class="classified_listings">

				<?php while ( $classifieds->have_posts() ) : $classifieds->the_post(); ?>

					<?php get_classified_manager_template_part( 'content-widget', 'classified_listing' ); ?>

				<?php endwhile; ?>

			</ul>

			<?php echo $after_widget; ?>

		<?php else : ?>

			<?php get_classified_manager_template_part( 'content-widget', 'no-classifieds-found' ); ?>

		<?php endif;

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget( $args, $content );
	}
}

/**
 * Featured Classifieds Widget
 */
class WP_Classified_Manager_Widget_Featured_Classifieds extends WP_Classified_Manager_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wp_post_types;

		$this->widget_cssclass    = 'classified_manager widget_featured_classifieds';
		$this->widget_description = __( 'Display a list of featured listings on your site.', 'classifieds-wp' );
		$this->widget_id          = 'widget_featured_classifieds';
		$this->widget_name        = sprintf( __( '%1$s - Featured %2$s', 'classifieds-wp' ), 'ClassifiedsWP', $wp_post_types['classified_listing']->labels->name );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => sprintf( __( 'Featured %s', 'classifieds-wp' ), $wp_post_types['classified_listing']->labels->name ),
				'label' => __( 'Title', 'classifieds-wp' )
			),
			'number' => array(
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'classifieds-wp' )
			)
		);
		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		ob_start();

		extract( $args );

		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number = absint( $instance['number'] );
		$classifieds   = get_classified_listings( array(
			'posts_per_page' => $number,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'featured'       => true
		) );

		if ( $classifieds->have_posts() ) : ?>

			<?php echo $before_widget; ?>

			<?php if ( $title ) echo $before_title . $title . $after_title; ?>

			<ul class="classified_listings">

				<?php while ( $classifieds->have_posts() ) : $classifieds->the_post(); ?>

					<?php get_classified_manager_template_part( 'content-widget', 'classified_listing' ); ?>

				<?php endwhile; ?>

			</ul>

			<?php echo $after_widget; ?>

		<?php else : ?>

			<?php get_classified_manager_template_part( 'content-widget', 'no-classifieds-found' ); ?>

		<?php endif;

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content;

		$this->cache_widget( $args, $content );
	}
}

/**
 * Categories Widget.
 *
 * @since 1.1.
 */
class WP_Classified_Manager_Widget_Taxonomies extends WP_Classified_Manager_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wp_post_types;

		$taxonomies = get_object_taxonomies( 'classified_listing', 'objects' );

		$options = array();

		foreach ( $taxonomies as $key => $taxonomy ) {
			$options[ $key ] = $taxonomy->labels->name;
		}

		$all_pages = get_pages();

		foreach ( $all_pages as $page ) {
			$pages[ $page->ID ] = $page->post_title;
		}

		$this->widget_cssclass    = 'classified_manager widget_categories';
		$this->widget_description = __( 'Display a list of classified types or classified categories on your site.', 'classifieds-wp' );
		$this->widget_id          = 'widget_classifieds_taxonomies';
		$this->widget_name        = sprintf( __( '%s - Classified Categories/Types', 'classifieds-wp' ), 'Classifieds WP' );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __( 'Classified Types', 'classifieds-wp' ),
				'label' => __( 'Title', 'classifieds-wp' )
			),
			'shortcode_page' => array(
				'type'    => 'select',
				'options' => $pages,
				'extra'   => array( 'class' => 'widefat' ),
				'label'   => __( 'Classifieds page', 'classifieds-wp' )
			),
			'taxonomy' => array(
				'type'    => 'select',
				'options' => $options,
				'extra'   => array( 'class' => 'widefat' ),
				'label'   => __( 'Taxonomy', 'classifieds-wp' )
			),
			'dropdown' => array(
				'type' => 'checkbox',
				'label' => __( 'Display as dropdown', 'classifieds-wp' ),
			),
			'hierarchical' => array(
				'type' => 'checkbox',
				'label' => __( 'Show hierarchy', 'classifieds-wp' ),
			),
			'show_count' => array(
				'type' => 'checkbox',
				'label' => __( 'Show counts', 'classifieds-wp' ),
			),
		);

		// Don't show the shortcode page option if already set.
		if ( classified_manager_get_permalink('classifieds') ) {
			unset( $this->settings['shortcode_page'] );
		}

		$this->register();
	}

	/**
	 * widget function.
	 *
	 * @see WP_Widget
	 * @access public
	 * @param array $args
	 * @param array $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		static $first_dropdown = true;

		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		extract( $args );

		ob_start();

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$taxonomy = ! empty( $instance['taxonomy'] ) ? $instance['taxonomy'] : 'category';

		if ( is_tax( $instance['taxonomy'] ) ) {
			$parent_tax = get_queried_object_id();
		}

		$show_count   = ! empty( $instance['show_count'] ) ? '1' : '0';
		$hierarchical = ! empty( $instance['hierarchical'] ) ? '1' : '0';
		$dropdown     = ! empty( $instance['dropdown'] ) ? '1' : '0';

		$cat_args = array(
			'orderby'            => 'name',
			'show_count'         => $show_count,
			'hierarchical'       => $hierarchical,
			'taxonomy'           => $taxonomy,
			'classified_listing' => true,
		);

		if ( $dropdown ) {
			$dropdown_id = ( $first_dropdown ) ? 'cat' : "{$this->id_base}-dropdown-{$this->number}";
			$first_dropdown = false;

			echo '<label class="screen-reader-text" for="' . esc_attr( $dropdown_id ) . '">' . $title . '</label>';

			$cat_args['show_option_none'] = __( 'Select Category' );
			$cat_args['id'] = $dropdown_id;

			/**
			 * Filter the arguments for the Categories widget drop-down.
			 *
			 * @see 'wp-includes/widgets/class-wp-widget-categories.php'
			 *
			 * @param array $cat_args An array of Categories widget drop-down arguments.
			 */
			wp_dropdown_categories( apply_filters( 'widget_categories_dropdown_args', $cat_args ) );
?>
			<script type='text/javascript'>
				/* <![CDATA[ */
				(function() {
					var dropdown = document.getElementById( "<?php echo esc_js( $dropdown_id ); ?>" );
					function onCatChange() {
						if ( dropdown.options[ dropdown.selectedIndex ].value > 0 ) {
							location.href = "<?php echo home_url(); ?>/?cat=" + dropdown.options[ dropdown.selectedIndex ].value;
						}
					}
					dropdown.onchange = onCatChange;
				})();
				/* ]]> */
			</script>
<?php
		} else {
?>
			<ul>
<?php
				$cat_args['title_li'] = '';

				/**
				 * Filter the arguments for the Categories widget.
				 *
	 			 * @see 'wp-includes/widgets/class-wp-widget-categories.php'
				 *
				 * @param array $cat_args An array of Categories widget options.
				 */
				wp_list_categories( apply_filters( 'widget_categories_args', $cat_args ) );
?>
			</ul>
<?php
		}

		$content = ob_get_clean();

		if ( ! ( $page_id = classified_manager_get_page_id('classifieds') ) ) {
			$page_id = (int) ! empty( $instance['shortcode_page'] ) ? $instance['shortcode_page'] : 0;
		}

		// Replace links with the 'classifieds' shortcode page permalink (if exists).
		if ( $permalink = get_permalink( $page_id ) ) {

			$link = apply_filters( 'classified_manager_taxonomy_widget_term_link', add_query_arg( 'qcm', '1', trailingslashit( $permalink ) ), $permalink );

			if ( $link ) {
				$content = preg_replace( "/(?<=href=\"|')(.*)\?(.*)(?=\"|')/", $link . '&$2', $content );
			}

		}

		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		echo $content;

		echo $after_widget;

		$this->cache_widget( $args, $content );
	}

}

WP_Classified_Manager_Sidebar::register_sidebars();

register_widget( 'WP_Classified_Manager_Widget_Recent_Classifieds' );
register_widget( 'WP_Classified_Manager_Widget_Featured_Classifieds' );
register_widget( 'WP_Classified_Manager_Widget_Taxonomies' );

