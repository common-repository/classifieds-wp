<div id="classified-manager-classified-dashboard">
	<p><?php _e( 'Your listings are shown in the table below.', 'classifieds-wp' ); ?></p>
	<table class="classified-manager-classifieds">
		<thead>
			<tr>
				<?php foreach ( $classified_dashboard_columns as $key => $column ) : ?>
					<th class="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $column ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $classifieds ) : ?>
				<tr>
					<td colspan="6"><?php _e( 'You do not have any active listings.', 'classifieds-wp' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $classifieds as $classified ) : ?>
					<tr>
						<?php foreach ( $classified_dashboard_columns as $key => $column ) : ?>
							<td class="<?php echo esc_attr( $key ); ?>">
								<?php if ('classified_title' === $key ) : ?>
									<?php if ( $classified->post_status == 'publish' ) : ?>
										<a href="<?php echo get_permalink( $classified->ID ); ?>"><?php echo $classified->post_title; ?></a>
									<?php else : ?>
										<?php echo $classified->post_title; ?> <small>(<?php the_classified_status( $classified ); ?>)</small>
									<?php endif; ?>
									<ul class="classified-dashboard-actions">
										<?php
											$actions = array();

											switch ( $classified->post_status ) {
												case 'publish' :
													$actions['edit'] = array( 'label' => __( 'Edit', 'classifieds-wp' ), 'nonce' => false );

													if ( is_classified_unavailable( $classified ) ) {
														$actions['mark_not_sold'] = array( 'label' => __( 'Mark Available', 'classifieds-wp' ), 'nonce' => true );
													} else {
														$actions['mark_sold'] = array( 'label' => __( 'Mark Unavailable', 'classifieds-wp' ), 'nonce' => true );
													}
													break;
												case 'expired' :
													if ( classified_manager_get_permalink( 'submit_classified_form' ) ) {
														$actions['relist'] = array( 'label' => __( 'Relist', 'classifieds-wp' ), 'nonce' => true );
													}
													break;
												case 'pending_payment' :
												case 'pending' :
													if ( classified_manager_user_can_edit_pending_submissions() ) {
														$actions['edit'] = array( 'label' => __( 'Edit', 'classifieds-wp' ), 'nonce' => false );
													}
												break;
											}

											$actions['delete'] = array( 'label' => __( 'Delete', 'classifieds-wp' ), 'nonce' => true );
											$actions           = apply_filters( 'classified_manager_my_classified_actions', $actions, $classified );

											foreach ( $actions as $action => $value ) {
												$action_url = add_query_arg( array( 'action' => $action, 'classified_id' => $classified->ID ) );
												if ( $value['nonce'] ) {
													$action_url = wp_nonce_url( $action_url, 'classified_manager_my_classified_actions' );
												}
												echo '<li><a href="' . esc_url( $action_url ) . '" class="classified-dashboard-action-' . esc_attr( $action ) . '">' . esc_html( $value['label'] ) . '</a></li>';
											}
										?>
									</ul>
								<?php elseif ('date' === $key ) : ?>
									<?php echo date_i18n( get_option( 'date_format' ), strtotime( $classified->post_date ) ); ?>
								<?php elseif ('expires' === $key ) : ?>
									<?php echo $classified->_classified_expires ? date_i18n( get_option( 'date_format' ), strtotime( $classified->_classified_expires ) ) : '&ndash;'; ?>
								<?php elseif ('classified_unavailable' === $key ) : ?>
									<?php echo is_classified_unavailable( $classified ) ? '&#10004;' : '&ndash;'; ?>
								<?php else : ?>
									<?php do_action( 'classified_manager_classified_dashboard_column_' . $key, $classified ); ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php get_classified_manager_template( 'pagination.php', array( 'max_num_pages' => $max_num_pages ) ); ?>
</div>
