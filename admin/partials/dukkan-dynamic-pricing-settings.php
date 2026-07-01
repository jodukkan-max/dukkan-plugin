<div class="dukkan-dp">
    <!-- Header -->
    <div class="dukkan-dp__header">
        <div class="dukkan-dp__header-left">
            <h2><?php esc_html_e( 'Dynamic Pricing & Discounts', 'dukkan-plugin' ); ?></h2>
            <p><?php esc_html_e( 'Create and manage pricing rules to offer discounts, bulk pricing, and promotions.', 'dukkan-plugin' ); ?></p>
        </div>
        <div class="dukkan-dp__header-right">
            <button type="button" class="dukkan-dp__add-btn" id="dukkan-dp-add-btn">
                <i class="fa-solid fa-plus"></i>
                <?php esc_html_e( 'Add Pricing Rule', 'dukkan-plugin' ); ?>
            </button>
        </div>
    </div>

    <!-- List Wrapper -->
    <div class="dukkan-dp__list-wrap">
        <!-- Empty State -->
        <div class="dukkan-dp__empty" id="dukkan-dp-empty"<?php echo ! empty( $rules ) ? ' style="display:none;"' : ''; ?>>
            <div class="dukkan-dp__empty-icon">
                <i class="fa-solid fa-tags"></i>
            </div>
            <h3><?php esc_html_e( 'No Pricing Rules Yet', 'dukkan-plugin' ); ?></h3>
            <p><?php esc_html_e( 'Create your first dynamic pricing rule to start offering discounts.', 'dukkan-plugin' ); ?></p>
        </div>

        <!-- Rule List -->
        <div class="dukkan-dp__list" id="dukkan-dp-list">
            <?php foreach ( $rules as $rule_id => $rule ) : ?>
                <div class="dukkan-dp__item" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
                    <div class="dukkan-dp__item-main">
                        <div class="dukkan-dp__item-info">
                            <div class="dukkan-dp__item-name">
                                <?php echo esc_html( $rule['name'] ); ?>
                            </div>
                            <div class="dukkan-dp__item-meta">
                                <span class="dukkan-dp__item-badge">
                                    <?php
                                    if ( 'percentage' === $rule['discount_type'] ) {
                                        echo esc_html( sprintf( __( '%%%s off', 'dukkan-plugin' ), $rule['discount_value'] ) );
                                    } elseif ( 'fixed' === $rule['discount_type'] ) {
                                        echo esc_html( sprintf( __( '%s off', 'dukkan-plugin' ), wc_price( $rule['discount_value'] ) ) );
                                    } else {
                                        echo esc_html( __( 'Buy X Get Y', 'dukkan-plugin' ) );
                                    }
                                    ?>
                                </span>
                                <span class="dukkan-dp__item-scope">
                                    <?php
                                    if ( 'all' === $rule['applies_to'] ) {
                                        esc_html_e( 'All Products', 'dukkan-plugin' );
                                    } elseif ( 'categories' === $rule['applies_to'] ) {
                                        echo esc_html( sprintf(
                                            /* translators: %d: number of categories */
                                            _n( '%d Category', '%d Categories', count( $rule['categories'] ), 'dukkan-plugin' ),
                                            count( $rule['categories'] )
                                        ) );
                                    } else {
                                        echo esc_html( sprintf(
                                            /* translators: %d: number of products */
                                            _n( '%d Product', '%d Products', count( $rule['products'] ), 'dukkan-plugin' ),
                                            count( $rule['products'] )
                                        ) );
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <label class="dukkan-dp__toggle wpldp-switch">
                            <input type="checkbox" class="dukkan-dp__toggle-input" data-rule-id="<?php echo esc_attr( $rule_id ); ?>" <?php checked( ! empty( $rule['status'] ) ); ?>>
                            <span class="wpldp-slider"></span>
                        </label>
                    </div>
                    <div class="dukkan-dp__item-actions">
                        <button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--edit"
                                data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
                                title="<?php esc_attr_e( 'Edit', 'dukkan-plugin' ); ?>">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--copy"
                                data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
                                title="<?php esc_attr_e( 'Duplicate', 'dukkan-plugin' ); ?>">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        <button type="button" class="dukkan-dp__item-btn dukkan-dp__item-btn--delete"
                                data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
                                title="<?php esc_attr_e( 'Delete', 'dukkan-plugin' ); ?>">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Modal Overlay -->
<div class="dukkan-dp__modal-overlay" id="dukkan-dp-modal-overlay"></div>

<!-- Add/Edit Modal -->
<div class="dukkan-dp__modal" id="dukkan-dp-modal">
    <div class="dukkan-dp__modal-header">
        <h3 id="dukkan-dp-modal-title"><?php esc_html_e( 'Add Pricing Rule', 'dukkan-plugin' ); ?></h3>
        <button type="button" class="dukkan-dp__modal-close" id="dukkan-dp-modal-close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dukkan-dp__modal-body">
        <input type="hidden" id="dukkan-dp-modal-rule-id" value="">

        <div class="dukkan-dp__modal-error" id="dukkan-dp-modal-error"></div>

        <!-- Rule Name -->
        <div class="dukkan-dp__field">
            <label for="dukkan-dp-rule-name">
                <?php esc_html_e( 'Rule Name', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <input type="text" id="dukkan-dp-rule-name" placeholder="<?php esc_attr_e( 'e.g. Summer Sale 20% Off', 'dukkan-plugin' ); ?>">
        </div>

        <!-- Description -->
        <div class="dukkan-dp__field">
            <label for="dukkan-dp-rule-desc">
                <?php esc_html_e( 'Description', 'dukkan-plugin' ); ?>
            </label>
            <textarea id="dukkan-dp-rule-desc" rows="2" placeholder="<?php esc_attr_e( 'Optional description for this rule', 'dukkan-plugin' ); ?>"></textarea>
        </div>

        <!-- Discount Type -->
        <div class="dukkan-dp__field">
            <label for="dukkan-dp-discount-type">
                <?php esc_html_e( 'Discount Type', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <select id="dukkan-dp-discount-type">
                <option value="percentage"><?php esc_html_e( 'Percentage Discount', 'dukkan-plugin' ); ?></option>
                <option value="fixed"><?php esc_html_e( 'Fixed Amount Discount', 'dukkan-plugin' ); ?></option>
                <option value="buy_x_get_y"><?php esc_html_e( 'Buy X Get Y', 'dukkan-plugin' ); ?></option>
            </select>
        </div>

        <!-- Discount Value -->
        <div class="dukkan-dp__field">
            <label for="dukkan-dp-discount-value">
                <?php esc_html_e( 'Discount Value', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <input type="number" id="dukkan-dp-discount-value" min="0" step="0.01" placeholder="0">
            <p class="dukkan-dp__field-hint" id="dukkan-dp-value-hint">
                <?php esc_html_e( 'Enter the discount percentage (e.g. 20 for 20% off).', 'dukkan-plugin' ); ?>
            </p>
        </div>

        <!-- Applies To -->
        <div class="dukkan-dp__field">
            <label for="dukkan-dp-applies-to">
                <?php esc_html_e( 'Applies To', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <select id="dukkan-dp-applies-to">
                <option value="all"><?php esc_html_e( 'All Products', 'dukkan-plugin' ); ?></option>
                <option value="categories"><?php esc_html_e( 'Specific Categories', 'dukkan-plugin' ); ?></option>
                <option value="products"><?php esc_html_e( 'Specific Products', 'dukkan-plugin' ); ?></option>
            </select>
        </div>

        <!-- Categories (hidden unless applies_to=categories) -->
        <div class="dukkan-dp__field dukkan-dp__field--conditional" id="dukkan-dp-categories-field" style="display:none;">
            <label>
                <?php esc_html_e( 'Select Categories', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <select id="dukkan-dp-categories" multiple="multiple" style="width:100%;">
            </select>
        </div>

        <!-- Products (hidden unless applies_to=products) -->
        <div class="dukkan-dp__field dukkan-dp__field--conditional" id="dukkan-dp-products-field" style="display:none;">
            <label>
                <?php esc_html_e( 'Select Products', 'dukkan-plugin' ); ?>
                <span class="dukkan-dp__required">*</span>
            </label>
            <select id="dukkan-dp-products" multiple="multiple" style="width:100%;">
            </select>
        </div>

        <!-- Conditions Section -->
        <div class="dukkan-dp__field-group">
            <h4 class="dukkan-dp__field-group-title">
                <i class="fa-solid fa-filter"></i>
                <?php esc_html_e( 'Conditions (Optional)', 'dukkan-plugin' ); ?>
            </h4>

            <!-- Min Quantity -->
            <div class="dukkan-dp__field">
                <label for="dukkan-dp-min-qty">
                    <?php esc_html_e( 'Minimum Quantity', 'dukkan-plugin' ); ?>
                </label>
                <input type="number" id="dukkan-dp-min-qty" min="0" placeholder="0">
                <p class="dukkan-dp__field-hint">
                    <?php esc_html_e( 'Apply only when cart quantity meets this minimum.', 'dukkan-plugin' ); ?>
                </p>
            </div>

            <!-- Min Amount -->
            <div class="dukkan-dp__field">
                <label for="dukkan-dp-min-amount">
                    <?php esc_html_e( 'Minimum Cart Amount', 'dukkan-plugin' ); ?>
                </label>
                <input type="number" id="dukkan-dp-min-amount" min="0" step="0.01" placeholder="0.00">
                <p class="dukkan-dp__field-hint">
                    <?php esc_html_e( 'Apply only when cart subtotal meets this minimum.', 'dukkan-plugin' ); ?>
                </p>
            </div>
        </div>

        <!-- Schedule Section -->
        <div class="dukkan-dp__field-group">
            <h4 class="dukkan-dp__field-group-title">
                <i class="fa-solid fa-calendar-days"></i>
                <?php esc_html_e( 'Schedule (Optional)', 'dukkan-plugin' ); ?>
            </h4>

            <div class="dukkan-dp__field-row">
                <div class="dukkan-dp__field dukkan-dp__field--half">
                    <label for="dukkan-dp-start-date">
                        <?php esc_html_e( 'Start Date', 'dukkan-plugin' ); ?>
                    </label>
                    <input type="date" id="dukkan-dp-start-date">
                </div>
                <div class="dukkan-dp__field dukkan-dp__field--half">
                    <label for="dukkan-dp-end-date">
                        <?php esc_html_e( 'End Date', 'dukkan-plugin' ); ?>
                    </label>
                    <input type="date" id="dukkan-dp-end-date">
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="dukkan-dp__field dukkan-dp__field--inline">
            <label class="dukkan-dp__field-check-label" for="dukkan-dp-status">
                <?php esc_html_e( 'Enable Rule', 'dukkan-plugin' ); ?>
            </label>
            <label class="wpldp-switch">
                <input type="checkbox" id="dukkan-dp-status" checked>
                <span class="wpldp-slider"></span>
            </label>
        </div>
    </div>
    <div class="dukkan-dp__modal-actions">
        <button type="button" class="dukkan-dp__modal-cancel" id="dukkan-dp-modal-cancel">
            <?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?>
        </button>
        <button type="button" class="dukkan-dp__modal-save" id="dukkan-dp-modal-save">
            <?php esc_html_e( 'Save Rule', 'dukkan-plugin' ); ?>
        </button>
    </div>
</div>

<!-- Delete Confirmation Modal Overlay -->
<div class="dukkan-dp__modal-overlay dukkan-dp__delete-overlay" id="dukkan-dp-delete-overlay"></div>

<!-- Delete Confirmation Modal -->
<div class="dukkan-dp__modal dukkan-dp__delete-modal" id="dukkan-dp-delete-modal">
    <div class="dukkan-dp__modal-header">
        <h3><?php esc_html_e( 'Delete Pricing Rule', 'dukkan-plugin' ); ?></h3>
        <button type="button" class="dukkan-dp__modal-close" id="dukkan-dp-delete-close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dukkan-dp__modal-body">
        <p class="dukkan-dp__delete-msg">
            <?php esc_html_e( 'Are you sure you want to delete this pricing rule? This action cannot be undone.', 'dukkan-plugin' ); ?>
        </p>
    </div>
    <div class="dukkan-dp__modal-actions">
        <button type="button" class="dukkan-dp__modal-cancel" id="dukkan-dp-delete-cancel">
            <?php esc_html_e( 'Cancel', 'dukkan-plugin' ); ?>
        </button>
        <button type="button" class="dukkan-dp__modal-save dukkan-dp__modal-save--danger" id="dukkan-dp-delete-confirm">
            <?php esc_html_e( 'Delete', 'dukkan-plugin' ); ?>
        </button>
    </div>
</div>
