<div class="dukkan-dp">
    <!-- Header -->
    <div class="dukkan-dp__header">
        <h2 class="dukkan-dp__title"><?php esc_html_e( 'Product Pricing', 'dukkan-plugin' ); ?></h2>
        <div class="dukkan-dp__global-controls">
            <select class="dukkan-dp__global-select" id="dukkan-dp-global-apply" data-global-setting="rule_application">
                <optgroup label="<?php esc_attr_e( 'Apply All', 'dukkan-plugin' ); ?>">
                    <option value="apply_all" <?php selected( $this->get_global_setting( 'rule_application', 'apply_first' ), 'apply_all' ); ?>>
                        <?php esc_html_e( 'Apply all applicable rules', 'dukkan-plugin' ); ?>
                    </option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Apply One – Per Cart Item', 'dukkan-plugin' ); ?>">
                    <option value="apply_first" <?php selected( $this->get_global_setting( 'rule_application', 'apply_first' ), 'apply_first' ); ?>>
                        <?php esc_html_e( 'Apply first applicable rule', 'dukkan-plugin' ); ?>
                    </option>
                    <option value="apply_smaller_price" <?php selected( $this->get_global_setting( 'rule_application', 'apply_first' ), 'apply_smaller_price' ); ?>>
                        <?php esc_html_e( 'Apply rule for smaller price', 'dukkan-plugin' ); ?>
                    </option>
                    <option value="apply_bigger_price" <?php selected( $this->get_global_setting( 'rule_application', 'apply_first' ), 'apply_bigger_price' ); ?>>
                        <?php esc_html_e( 'Apply rule for bigger price', 'dukkan-plugin' ); ?>
                    </option>
                </optgroup>
                <optgroup label="<?php esc_attr_e( 'Disabled', 'dukkan-plugin' ); ?>">
                    <option value="all_disabled" <?php selected( $this->get_global_setting( 'rule_application', 'apply_first' ), 'all_disabled' ); ?>>
                        <?php esc_html_e( 'All rules disabled', 'dukkan-plugin' ); ?>
                    </option>
                </optgroup>
            </select>
            <div class="dukkan-dp__global-limit-wrap">
                <select class="dukkan-dp__global-select dukkan-dp__global-limit-select" id="dukkan-dp-global-limit" data-global-setting="discount_limit">
                    <optgroup label="<?php esc_attr_e( 'No Limit', 'dukkan-plugin' ); ?>">
                        <option value="none" <?php selected( $this->get_global_setting( 'discount_limit', 'none' ), 'none' ); ?>>
                            <?php esc_html_e( 'No discount limit', 'dukkan-plugin' ); ?>
                        </option>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e( 'Price Discount Limit', 'dukkan-plugin' ); ?>">
                        <option value="price_discount_amount" <?php selected( $this->get_global_setting( 'discount_limit', 'none' ), 'price_discount_amount' ); ?>>
                            <?php esc_html_e( 'Price discount limit $', 'dukkan-plugin' ); ?>
                        </option>
                        <option value="price_discount_percent" <?php selected( $this->get_global_setting( 'discount_limit', 'none' ), 'price_discount_percent' ); ?>>
                            <?php esc_html_e( 'Price discount limit %', 'dukkan-plugin' ); ?>
                        </option>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e( 'Total Discount Limit', 'dukkan-plugin' ); ?>">
                        <option value="total_discount_amount" <?php selected( $this->get_global_setting( 'discount_limit', 'none' ), 'total_discount_amount' ); ?>>
                            <?php esc_html_e( 'Total discount limit $', 'dukkan-plugin' ); ?>
                        </option>
                    </optgroup>
                </select>
                <input type="number"
                       class="dukkan-dp__global-limit-input <?php echo 'none' === $this->get_global_setting( 'discount_limit', 'none' ) ? 'dukkan-dp__global-limit-input--hidden' : ''; ?>"
                       id="dukkan-dp-global-limit-value"
                       data-global-setting="discount_limit_value"
                       value="<?php echo esc_attr( $this->get_global_setting( 'discount_limit_value', '0.0' ) ); ?>"
                       min="0"
                       step="0.01"
                       placeholder="0.0"
                >
            </div>
        </div>
    </div>

    <!-- Rule List (sortable) -->
    <div class="dukkan-dp__list" id="dukkan-dp-list">
        <?php if ( ! empty( $rules ) ) : ?>
            <?php foreach ( $rules as $rule ) : ?>
                <?php $this->render_rule_card( $rule['id'] ?? '', $rule ); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer Actions -->
    <div class="dukkan-dp__footer">
        <button type="button" class="dukkan-dp__add-rule-btn" id="dukkan-dp-add-rule">
            <i class="fa-solid fa-plus"></i>
            <?php esc_html_e( 'Add Rule', 'dukkan-plugin' ); ?>
        </button>
        <button type="button" class="dukkan-dp__save-all-btn" id="dukkan-dp-save-all">
            <i class="fa-solid fa-floppy-disk"></i>
            <?php esc_html_e( 'Save All Rules', 'dukkan-plugin' ); ?>
        </button>
    </div>
</div>

<!-- Hidden template for new rule cards (cloned by JS) -->
<script type="text/template" id="dukkan-dp-rule-template">
    <?php $this->render_rule_card( '{{RULE_ID}}', array() ); ?>
</script>
