<div class="dukkan-order-status">

	<div class="dukkan-order-status__header">
		<h2><?php esc_html_e( 'Custom Order Statuses', 'dukkan-plugin' ); ?></h2>
		<p><?php esc_html_e( 'Create and manage custom WooCommerce order statuses. These will appear alongside the default WooCommerce statuses.', 'dukkan-plugin' ); ?></p>
	</div>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice_type ? 'error' : 'success' ); ?> inline">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
	<?php endif; ?>

	<div class="dukkan-order-status__layout">
		<!-- LEFT COLUMN — Add / Edit Form -->
		<div class="dukkan-order-status__form-panel">
			<div class="dukkan-order-status__card">
				<div class="dukkan-order-status__card-header">
					<h3>
						<?php echo $edit_status ? esc_html__( 'Edit Order Status', 'dukkan-plugin' ) : esc_html__( 'Add New Order Status', 'dukkan-plugin' ); ?>
					</h3>
				</div>
				<div class="dukkan-order-status__card-body">
					<form method="post"
						  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						  class="dukkan-order-status__form">

						<input type="hidden"
							   name="action"
							   value="<?php echo $edit_status ? 'dukkan_update_order_status' : 'dukkan_add_order_status'; ?>">
						<?php wp_nonce_field( $edit_status ? 'dukkan_update_order_status' : 'dukkan_add_order_status', 'dukkan_os_nonce' ); ?>

						<?php if ( $edit_status ) : ?>
							<input type="hidden" name="dukkan_os_old_slug" value="<?php echo esc_attr( $edit_status['slug'] ); ?>">
						<?php endif; ?>

						<div class="dukkan-order-status__field">
							<label for="dukkan_os_name">
								<?php esc_html_e( 'Status Name', 'dukkan-plugin' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text"
								   id="dukkan_os_name"
								   name="dukkan_os_name"
								   class="regular-text"
								   value="<?php echo $edit_status ? esc_attr( $edit_status['name'] ) : ''; ?>"
								   maxlength="100"
								   required
								   placeholder="<?php esc_attr_e( 'e.g. Ready For Delivery', 'dukkan-plugin' ); ?>">
							<p class="description">
								<?php esc_html_e( 'The display name shown in WooCommerce admin.', 'dukkan-plugin' ); ?>
							</p>
						</div>

						<div class="dukkan-order-status__field">
							<label for="dukkan_os_slug">
								<?php esc_html_e( 'Status Slug', 'dukkan-plugin' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text"
								   id="dukkan_os_slug"
								   name="dukkan_os_slug"
								   class="regular-text"
								   value="<?php echo $edit_status ? esc_attr( $edit_status['slug'] ) : ''; ?>"
								   maxlength="20"
								   required
								   placeholder="<?php esc_attr_e( 'e.g. ready-delivery', 'dukkan-plugin' ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: max character count */
									esc_html__( 'Lowercase letters, numbers, and hyphens only. Maximum %s characters.', 'dukkan-plugin' ),
									esc_html( (string) Dukkan_Plugin_Order_Status::SLUG_MAX_LENGTH )
								);
								?>
							</p>
						</div>

						<div class="dukkan-order-status__actions">
							<?php submit_button(
								$edit_status ? __( 'Update Status', 'dukkan-plugin' ) : __( 'Add Status', 'dukkan-plugin' ),
								'primary',
								'submit',
								false
							); ?>

							<?php if ( $edit_status ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dukkan-settings', 'tab' => 'order_status' ), admin_url( 'admin.php' ) ) ); ?>"
								   class="button">
									<?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- RIGHT COLUMN — Status List -->
		<div class="dukkan-order-status__list-panel">
			<div class="dukkan-order-status__card">
				<div class="dukkan-order-status__card-header">
					<h3><?php esc_html_e( 'Your Custom Statuses', 'dukkan-plugin' ); ?></h3>
				</div>
				<div class="dukkan-order-status__card-body">
					<?php if ( empty( $statuses ) ) : ?>
						<div class="dukkan-order-status__empty">
							<p><?php esc_html_e( 'No custom order statuses yet. Create your first one using the form on the left.', 'dukkan-plugin' ); ?></p>
						</div>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped dukkan-order-status__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'dukkan-plugin' ); ?></th>
									<th><?php esc_html_e( 'Slug', 'dukkan-plugin' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'dukkan-plugin' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $statuses as $key => $status ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $status['name'] ); ?></strong>
										</td>
										<td>
											<code>wc-<?php echo esc_html( $status['slug'] ); ?></code>
										</td>
										<td class="dukkan-order-status__actions-cell">
											<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dukkan-settings', 'tab' => 'order_status', 'edit' => $status['slug'] ), admin_url( 'admin.php' ) ) ); ?>"
											   class="button button-small">
												<?php esc_html_e( 'Edit', 'dukkan-plugin' ); ?>
											</a>

											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'dukkan_delete_order_status', 'slug' => $status['slug'] ), admin_url( 'admin-post.php' ) ), 'dukkan_delete_order_status', 'dukkan_os_nonce' ) ); ?>"
											   class="button button-small button-link-delete"
											   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this order status? This action cannot be undone.', 'dukkan-plugin' ); ?>');">
												<?php esc_html_e( 'Delete', 'dukkan-plugin' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

</div>
