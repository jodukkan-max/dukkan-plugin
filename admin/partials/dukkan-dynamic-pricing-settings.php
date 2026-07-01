<div class="dukkan-dp">
    <!-- Header -->
    <div class="dukkan-dp__header">
        <h2 class="dukkan-dp__title"><?php esc_html_e( 'Product Pricing', 'dukkan-plugin' ); ?></h2>
        <div class="dukkan-dp__global-controls">
            <select class="dukkan-dp__global-select" id="dukkan-dp-global-apply">
                <option value="first"><?php esc_html_e( 'Apply first applicable rule', 'dukkan-plugin' ); ?></option>
                <option value="all"><?php esc_html_e( 'Apply all applicable rules', 'dukkan-plugin' ); ?></option>
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
            <?php foreach ( $rules as $rule_id => $rule ) : ?>
                <?php $this->render_rule_card( $rule_id, $rule ); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Rule Button -->
    <div class="dukkan-dp__footer">
        <button type="button" class="dukkan-dp__add-rule-btn" id="dukkan-dp-add-rule">
            <i class="fa-solid fa-plus"></i>
            <?php esc_html_e( 'Add Rule', 'dukkan-plugin' ); ?>
        </button>
    </div>
</div>

<!-- Hidden template for new rule cards (cloned by JS) -->
<script type="text/template" id="dukkan-dp-rule-template">
    <?php $this->render_rule_card( '{{RULE_ID}}', array() ); ?>
</script>
