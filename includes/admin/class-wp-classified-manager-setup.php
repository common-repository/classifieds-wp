<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Classified_Manager_Setup class.
 */
class WP_Classified_Manager_Setup {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_init', array( $this, 'redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 12 );
	}

	/**
	 * admin_menu function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		add_dashboard_page( __( 'Setup', 'classifieds-wp' ), __( 'Setup', 'classifieds-wp' ), 'manage_options', 'classified-manager-setup', array( $this, 'output' ) );
	}

	/**
	 * Add styles just for this page, and remove dashboard page links.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_head() {
		remove_submenu_page( 'index.php', 'classified-manager-setup' );
	}

	/**
	 * Sends user to the setup page on first activation
	 */
	public function redirect() {
		// Bail if no activation redirect transient is set
	    if ( ! get_transient( '_classified_manager_activation_redirect' ) ) {
			return;
	    }

	    if ( ! current_user_can( 'manage_options' ) ) {
	    	return;
	    }

		// Delete the redirect transient
		delete_transient( '_classified_manager_activation_redirect' );

		// Bail if activating from network, or bulk, or within an iFrame
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || defined( 'IFRAME_REQUEST' ) ) {
			return;
		}

		if ( ( isset( $_GET['action'] ) && 'upgrade-plugin' == $_GET['action'] ) && ( isset( $_GET['plugin'] ) && strstr( $_GET['plugin'], 'wp-classified-manager.php' ) ) ) {
			return;
		}

		wp_redirect( admin_url( 'index.php?page=classified-manager-setup' ) );
		exit;
	}

	/**
	 * Enqueue scripts for setup page
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'classified_manager_setup_css', WP_CLASSIFIED_MANAGER_PLUGIN_URL . '/assets/css/setup.css', array( 'dashicons' ) );
	}

	/**
	 * Create a page.
	 * @param  string $title
	 * @param  string $content
	 * @param  string $option
	 */
	public function create_page( $title, $content, $option ) {
		$page_data = array(
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_author'    => 1,
			'post_name'      => sanitize_title( $title ),
			'post_title'     => $title,
			'post_content'   => $content,
			'post_parent'    => 0,
			'comment_status' => 'closed'
		);
		$page_id = wp_insert_post( $page_data );

		if ( $option ) {
			update_option( $option, $page_id );
		}
	}

	/**
	 * Output addons page
	 */
	public function output() {
		$step = ! empty( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

		if ( 3 === $step && ! empty( $_POST ) ) {
			$create_pages    = isset( $_POST['wp-classified-manager-create-page'] ) ? $_POST['wp-classified-manager-create-page'] : array();
			$page_titles     = $_POST['wp-classified-manager-page-title'];
			$pages_to_create = array(
				'submit_classified_form' => '[submit_classified_form]',
				'classified_dashboard'   => '[classified_dashboard]',
				'classifieds'            => '[classifieds]'
			);

			foreach ( $pages_to_create as $page => $content ) {
				if ( ! isset( $create_pages[ $page ] ) || empty( $page_titles[ $page ] ) ) {
					continue;
				}
				$this->create_page( sanitize_text_field( $page_titles[ $page ] ), $content, 'classified_manager_' . $page . '_page_id' );
			}
		}
		?>
		<div class="wrap wp_classified_manager wp_classified_manager_addons_wrap">
			<h2><?php _e( 'Classifieds WP Setup', 'classifieds-wp' ); ?></h2>

			<ul class="wp-classified-manager-setup-steps">
				<li class="<?php if ( $step === 1 ) echo 'wp-classified-manager-setup-active-step'; ?>"><?php _e( '1. Introduction', 'classifieds-wp' ); ?></li>
				<li class="<?php if ( $step === 2 ) echo 'wp-classified-manager-setup-active-step'; ?>"><?php _e( '2. Page Setup', 'classifieds-wp' ); ?></li>
				<li class="<?php if ( $step === 3 ) echo 'wp-classified-manager-setup-active-step'; ?>"><?php _e( '3. Done', 'classifieds-wp' ); ?></li>
			</ul>

			<?php if ( 1 === $step ) : ?>

				<h3><?php _e( 'Setup Wizard Introduction', 'classifieds-wp' ); ?></h3>

				<p><?php _e( 'Thanks for installing <em>Classifieds WP</em>!', 'classifieds-wp' ); ?></p>
				<p><?php _e( 'This setup wizard will help you get started by creating the pages for classified submission, classified management, and listing your classifieds.', 'classifieds-wp' ); ?></p>
				<p><?php printf( __( 'If you want to skip the wizard and setup the pages and shortcodes yourself manually, the process is still relatively simple. Refer to the %sdocumentation%s for help.', 'classifieds-wp' ), '<a href="https://documentation.classifiedswp.com/">', '</a>' ); ?></p>

				<p class="submit">
					<a href="<?php echo esc_url( add_query_arg( 'step', 2 ) ); ?>" class="button button-primary"><?php _e( 'Continue to page setup', 'classifieds-wp' ); ?></a>
					<a href="<?php echo esc_url( add_query_arg( 'skip-classified-manager-setup', 1, admin_url( 'index.php?page=classified-manager-setup&step=3' ) ) ); ?>" class="button"><?php _e( 'Skip setup. I will setup the plugin manually', 'classifieds-wp' ); ?></a>
				</p>

			<?php endif; ?>
			<?php if ( 2 === $step ) : ?>

				<h3><?php _e( 'Page Setup', 'classifieds-wp' ); ?></h3>

				<p><?php printf( __( '<em>Classifieds WP</em> includes %1$sshortcodes%2$s which can be used within your %3$spages%2$s to output content. These can be created for you below. For more information on the classified shortcodes view the %4$sshortcode documentation%2$s.', 'classifieds-wp' ), '<a href="http://codex.wordpress.org/Shortcode" title="What is a shortcode?" target="_blank" class="help-page-link">', '</a>', '<a href="http://codex.wordpress.org/Pages" target="_blank" class="help-page-link">', '<a href="http://classifiedswp.com/document/shortcode-reference/" target="_blank" class="help-page-link">' ); ?></p>

				<form action="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" method="post">
					<table class="wp-classified-manager-shortcodes widefat">
						<thead>
							<tr>
								<th>&nbsp;</th>
								<th><?php _e( 'Page Title', 'classifieds-wp' ); ?></th>
								<th><?php _e( 'Page Description', 'classifieds-wp' ); ?></th>
								<th><?php _e( 'Content Shortcode', 'classifieds-wp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-classified-manager-create-page[submit_classified_form]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Post a Classified', 'Default page title (wizard)', 'classifieds-wp' ) ); ?>" name="wp-classified-manager-page-title[submit_classified_form]" /></td>
								<td>
									<p><?php _e( 'This page allows users to post classifieds to your website from the front-end.', 'classifieds-wp' ); ?></p>

									<p><?php _e( 'If you do not want to accept submissions from users in this way (for example you just want to post classifieds from the admin dashboard) you can skip creating this page.', 'classifieds-wp' ); ?></p>
								</td>
								<td><code>[submit_classified_form]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-classified-manager-create-page[classified_dashboard]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Classified Dashboard', 'Default page title (wizard)', 'classifieds-wp' ) ); ?>" name="wp-classified-manager-page-title[classified_dashboard]" /></td>
								<td>
									<p><?php _e( 'This page allows users to manage and edit their own classifieds from the front-end.', 'classifieds-wp' ); ?></p>

									<p><?php _e( 'If you plan on managing all listings from the admin dashboard you can skip creating this page.', 'classifieds-wp' ); ?></p>
								</td>
								<td><code>[classified_dashboard]</code></td>
							</tr>
							<tr>
								<td><input type="checkbox" checked="checked" name="wp-classified-manager-create-page[classifieds]" /></td>
								<td><input type="text" value="<?php echo esc_attr( _x( 'Classifieds', 'Default page title (wizard)', 'classifieds-wp' ) ); ?>" name="wp-classified-manager-page-title[classifieds]" /></td>
								<td><?php _e( 'This page allows users to browse, search, and filter classified listings on the front-end of your site.', 'classifieds-wp' ); ?></td>
								<td><code>[classifieds]</code></td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4">
									<input type="submit" class="button button-primary" value="Create selected pages" />
									<a href="<?php echo esc_url( add_query_arg( 'step', 3 ) ); ?>" class="button"><?php _e( 'Skip this step', 'classifieds-wp' ); ?></a>
								</th>
							</tr>
						</tfoot>
					</table>
				</form>

			<?php endif; ?>
			<?php if ( 3 === $step ) : ?>

				<h3><?php _e( 'All Done!', 'classifieds-wp' ); ?></h3>

				<p><?php _e( 'Looks like you\'re all set to start using the plugin. In case you\'re wondering where to go next:', 'classifieds-wp' ); ?></p>

				<ul class="wp-classified-manager-next-steps">
					<li><a href="<?php echo admin_url( 'edit.php?post_type=classified_listing&page=classified-manager-settings' ); ?>"><?php _e( 'Tweak the plugin settings', 'classifieds-wp' ); ?></a></li>
					<li><a href="<?php echo admin_url( 'post-new.php?post_type=classified_listing' ); ?>"><?php _e( 'Add a classified via the back-end', 'classifieds-wp' ); ?></a></li>

					<?php if ( $permalink = classified_manager_get_permalink( 'submit_classified_form' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'Add a classified via the front-end', 'classifieds-wp' ); ?></a></li>
					<?php else : ?>
						<li><a href="http://documentation.classifiedswp.com/usage/classified-submission-shortcode/"><?php _e( 'Find out more about the front-end classified submission form', 'classifieds-wp' ); ?></a></li>
					<?php endif; ?>

					<?php if ( $permalink = classified_manager_get_permalink( 'classifieds' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'View submitted classified listings', 'classifieds-wp' ); ?></a></li>
					<?php else : ?>
						<li><a href="http://documentation.classifiedswp.com/usage/classifieds-wp-shortcodes/"><?php _e( 'Add the [classifieds] shortcode to a page to list classifieds', 'classifieds-wp' ); ?></a></li>
					<?php endif; ?>

					<?php if ( $permalink = classified_manager_get_permalink( 'classified_dashboard' ) ) : ?>
						<li><a href="<?php echo esc_url( $permalink ); ?>"><?php _e( 'View the classified dashboard', 'classifieds-wp' ); ?></a></li>
					<?php else : ?>
						<li><a href="http://documentation.classifiedswp.com/usage/classified-dashboard-shortcode/"><?php _e( 'Find out more about the front-end classified dashboard', 'classifieds-wp' ); ?></a></li>
					<?php endif; ?>
				</ul>

				<p><?php printf( __( 'And don\'t forget, if you need any more help using <em>Classifieds WP</em> you can consult the %1$sdocumentation%2$s or %3$spost on the forums%2$s!', 'classifieds-wp' ), '<a href="http://classifiedswp.com/documentation/">', '</a>', '<a href="https://wordpress.org/support/plugin/wp-classified-manager">' ); ?></p>

				<div class="wp-classified-manager-support-the-plugin">
					<h3><?php _e( 'Support the Ongoing Development of this Plugin', 'classifieds-wp' ); ?></h3>
					<p><?php _e( 'There are many ways to support open-source projects such as Classifieds WP, for example code contribution, translation, or even telling your friends how awesome the plugin (hopefully) is. Thanks in advance for your support - it is much appreciated!', 'classifieds-wp' ); ?></p>
					<ul>
						<li class="icon-review"><a href="https://wordpress.org/support/view/plugin-reviews/wp-classified-manager#postform"><?php _e( 'Leave a positive review', 'classifieds-wp' ); ?></a></li>
						<li class="icon-forum"><a href="https://wordpress.org/support/plugin/wp-classified-manager"><?php _e( 'Help other users on the forums', 'classifieds-wp' ); ?></a></li>
					</ul>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}
}

new WP_Classified_Manager_Setup();