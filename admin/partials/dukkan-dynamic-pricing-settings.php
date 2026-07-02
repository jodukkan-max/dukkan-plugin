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
            <select class="dukkan-dp__global-select" id="dukkan-dp-global-limit">
                <option value="none"><?php esc_html_e( 'No discount limit', 'dukkan-plugin' ); ?></option>
                <option value="single"><?php esc_html_e( 'Single discount per product', 'dukkan-plugin' ); ?></option>
                <option value="one"><?php esc_html_e( 'One discount per cart', 'dukkan-plugin' ); ?></option>
            </select>
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
