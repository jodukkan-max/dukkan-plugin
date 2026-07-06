<div class="dukkan-os">

	<!-- Header -->
	<div class="dukkan-os__header">
		<div class="dukkan-os__header-left">
			<h2><?php esc_html_e( 'Custom Order Statuses', 'dukkan-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Create and manage custom WooCommerce order statuses. Drag to reorder.', 'dukkan-plugin' ); ?></p>
		</div>
		<div class="dukkan-os__header-right">
			<button type="button" class="dukkan-os__add-btn" id="dukkan-os-add-btn">
				<i class="fa-solid fa-plus"></i>
				<?php esc_html_e( 'Add Status', 'dukkan-plugin' ); ?>
			</button>
		</div>
	</div>

	<!-- Status List -->
	<div class="dukkan-os__list-wrap">
		<?php if ( empty( $statuses ) ) : ?>
			<div class="dukkan-os__empty" id="dukkan-os-empty">
				<div class="dukkan-os__empty-icon">
					<i class="fa-solid fa-truck-fast"></i>
				</div>
				<h3><?php esc_html_e( 'No custom order statuses yet', 'dukkan-plugin' ); ?></h3>
				<p><?php esc_html_e( 'Click the "Add Status" button above to create your first custom order status.', 'dukkan-plugin' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="dukkan-os__list" id="dukkan-os-list">
			<?php foreach ( $statuses as $status ) : ?>
				<div class="dukkan-os__item" data-slug="<?php echo esc_attr( $status['slug'] ); ?>">
					<div class="dukkan-os__item-drag">
						<i class="fa-solid fa-grip-vertical"></i>
					</div>
					<div class="dukkan-os__item-content">
						<div class="dukkan-os__item-name"><?php echo esc_html( $status['name'] ); ?></div>
						<div class="dukkan-os__item-slug">
							<code>wc-<?php echo esc_html( $status['slug'] ); ?></code>
						</div>
					</div>
					<div class="dukkan-os__item-actions">
						<button type="button"
								class="dukkan-os__item-btn dukkan-os__item-btn--edit"
								data-slug="<?php echo esc_attr( $status['slug'] ); ?>"
								data-name="<?php echo esc_attr( $status['name'] ); ?>"
								title="<?php esc_attr_e( 'Edit', 'dukkan-plugin' ); ?>">
							<i class="fa-solid fa-pen-to-square"></i>
						</button>
						<button type="button"
								class="dukkan-os__item-btn dukkan-os__item-btn--delete"
								data-slug="<?php echo esc_attr( $status['slug'] ); ?>"
								title="<?php esc_attr_e( 'Delete', 'dukkan-plugin' ); ?>">
							<i class="fa-solid fa-trash-can"></i>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Modal Overlay -->
	<div class="dukkan-os__modal-overlay" id="dukkan-os-modal-overlay"></div>

	<!-- Modal -->
	<div class="dukkan-os__modal" id="dukkan-os-modal">
		<div class="dukkan-os__modal-header">
			<h3 id="dukkan-os-modal-title"><?php esc_html_e( 'Add New Order Status', 'dukkan-plugin' ); ?></h3>
			<button type="button" class="dukkan-os__modal-close" id="dukkan-os-modal-close">
				<i class="fa-solid fa-times"></i>
			</button>
		</div>
		<div class="dukkan-os__modal-body">
			<input type="hidden" id="dukkan-os-modal-old-slug" value="">

			<div class="dukkan-os__field">
				<label for="dukkan-os-modal-name">
					<?php esc_html_e( 'Status Name', 'dukkan-plugin' ); ?>
					<span class="dukkan-os__required">*</span>
				</label>
				<input type="text"
					   id="dukkan-os-modal-name"
					   class="regular-text"
					   maxlength="100"
					   placeholder="<?php esc_attr_e( 'e.g. Ready For Delivery', 'dukkan-plugin' ); ?>">
				<p class="dukkan-os__field-hint">
					<?php esc_html_e( 'The display name shown in WooCommerce admin.', 'dukkan-plugin' ); ?>
				</p>
			</div>

			<div class="dukkan-os__field">
				<label for="dukkan-os-modal-slug">
					<?php esc_html_e( 'Status Slug', 'dukkan-plugin' ); ?>
					<span class="dukkan-os__required">*</span>
				</label>
				<input type="text"
					   id="dukkan-os-modal-slug"
					   class="regular-text"
					   maxlength="20"
					   placeholder="<?php esc_attr_e( 'e.g. ready-delivery', 'dukkan-plugin' ); ?>">
				<p class="dukkan-os__field-hint">
					<?php
					printf(
						/* translators: %s: max character count */
						esc_html__( 'Lowercase letters, numbers, and hyphens only. Maximum %s characters.', 'dukkan-plugin' ),
						esc_html( (string) Dukkan_Plugin_Order_Status::SLUG_MAX_LENGTH )
					);
					?>
				</p>
			</div>

			<div class="dukkan-os__field-error" id="dukkan-os-modal-error"></div>

			<div class="dukkan-os__modal-actions">
				<button type="button" class="button" id="dukkan-os-modal-cancel">
					<?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?>
				</button>
				<button type="button" class="dukkan-os__modal-save" id="dukkan-os-modal-save">
					<?php esc_html_e( 'Save Status', 'dukkan-plugin' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Delete Confirmation Modal -->
	<div class="dukkan-os__modal-overlay" id="dukkan-os-delete-overlay"></div>
	<div class="dukkan-os__modal dukkan-os__modal--delete" id="dukkan-os-delete-modal">
		<div class="dukkan-os__modal-header">
			<h3><?php esc_html_e( 'Delete Order Status', 'dukkan-plugin' ); ?></h3>
			<button type="button" class="dukkan-os__modal-close" id="dukkan-os-delete-close">
				<i class="fa-solid fa-times"></i>
			</button>
		</div>
		<div class="dukkan-os__modal-body">
			<p class="dukkan-os__delete-msg" id="dukkan-os-delete-msg">
				<?php esc_html_e( 'Are you sure you want to delete this order status? This action cannot be undone.', 'dukkan-plugin' ); ?>
			</p>
			<div class="dukkan-os__modal-actions">
				<button type="button" class="button" id="dukkan-os-delete-cancel">
					<?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?>
				</button>
				<button type="button" class="dukkan-os__modal-save dukkan-os__modal-save--danger" id="dukkan-os-delete-confirm">
					<?php esc_html_e( 'Delete', 'dukkan-plugin' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
