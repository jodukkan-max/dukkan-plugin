<?php

/**
 * Dynamic Product Pricing & Discounts management.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.1
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Manages dynamic pricing rules — add, edit, update, delete, duplicate, reorder.
 *
 * Stores pricing rules in a WordPress option as an ordered indexed array
 * and exposes AJAX handlers for the Dukkan settings UI.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Dynamic_Pricing {

	/**
	 * Option key that holds all dynamic pricing rules.
	 *
	 * @since 1.0.1
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_dynamic_pricing_rules';

	/**
	 * Option key for global pricing settings (application mode, discount limit, etc.).
	 *
	 * @since 1.0.1
	 * @var   string
	 */
	const GLOBAL_OPTION_KEY = 'dukkan_dynamic_pricing_global';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.1
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_dynamic_pricing_tab' ) );
		add_action( 'dukkan_settings_tab_content_dynamic_pricing', array( $this, 'render_tab_content' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_dukkan_dp_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_dukkan_dp_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_dukkan_dp_update', array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_dukkan_dp_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_dukkan_dp_duplicate', array( $this, 'ajax_duplicate' ) );
		add_action( 'wp_ajax_dukkan_dp_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_dukkan_dp_get', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_dukkan_dp_save_all', array( $this, 'ajax_save_all' ) );
		add_action( 'wp_ajax_dukkan_dp_save_global', array( $this, 'ajax_save_global' ) );
		add_action( 'wp_ajax_dukkan_dp_product_search', array( $this, 'ajax_product_search' ) );
	}

	// -------------------------------------------------------------------------
	// Tab Registration
	// -------------------------------------------------------------------------

	/**
	 * Add the Dynamic Pricing tab to Dukkan settings.
	 *
	 * @since  1.0.1
	 * @param  array $tabs Existing tabs.
	 * @return array
	 */
	public function add_dynamic_pricing_tab( $tabs ) {
		$tabs['dynamic_pricing'] = array(
			'title' => __( 'Dynamic Pricing', 'dukkan-plugin' ),
			'icon'  => 'fa-solid fa-tags',
		);
		return $tabs;
	}

	// -------------------------------------------------------------------------
	// Tab Content
	// -------------------------------------------------------------------------

	/**
	 * Render the Dynamic Pricing tab content.
	 *
	 * @since 1.0.1
	 */
	public function render_tab_content() {
		$rules = $this->get_all_rules();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-dynamic-pricing-settings.php';
	}

	/**
	 * Render a single rule card.
	 *
	 * @since 1.0.1
	 * @param string $rule_id Rule ID.
	 * @param array  $rule    Rule data (empty for new rules).
	 */
	public function render_rule_card( $rule_id, $rule ) {
		$method          = $rule['method'] ?? 'simple_adjustment';
		$note            = $rule['note'] ?? '';
		$description     = $rule['description'] ?? '';
		$adjustment_type = $rule['adjustment_type'] ?? 'fixed_discount';
		$adjustment_amount = $rule['adjustment_amount'] ?? '0.00';
		$apply_with      = $rule['apply_with'] ?? 'apply_with_others';
		$products        = $rule['products'] ?? array();
		$conditions      = $rule['conditions'] ?? array();
		$is_template     = ( '{{RULE_ID}}' === $rule_id );
		?>
		<div class="dukkan-dp__rule" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
			<!-- Card Header -->
			<div class="dukkan-dp__rule-header" data-toggle-collapse>
				<div class="dukkan-dp__rule-drag">
					<i class="fa-solid fa-grip-vertical"></i>
				</div>
				<span class="dukkan-dp__rule-label"><?php esc_html_e( 'Pricing Rule', 'dukkan-plugin' ); ?></span>
				<span class="dukkan-dp__rule-method-label" data-method-label>
					<?php
					if ( 'simple_adjustment' === $method ) {
						esc_html_e( 'Simple adjustment', 'dukkan-plugin' );
					} elseif ( 'bulk_pricing' === $method ) {
						esc_html_e( 'Bulk pricing', 'dukkan-plugin' );
					} elseif ( 'tiered_pricing' === $method ) {
						esc_html_e( 'Tiered pricing', 'dukkan-plugin' );
					} elseif ( 'group_of_products' === $method ) {
						esc_html_e( 'Group of products', 'dukkan-plugin' );
					} elseif ( 'group_of_products_repeating' === $method ) {
						esc_html_e( 'Group of products - Repeating', 'dukkan-plugin' );
					} elseif ( 'buy_x_get_x' === $method ) {
						esc_html_e( 'Buy x get x', 'dukkan-plugin' );
					} elseif ( 'buy_x_get_x_repeating' === $method ) {
						esc_html_e( 'Buy x get x - Repeating', 'dukkan-plugin' );
					} elseif ( 'buy_x_get_y' === $method ) {
						esc_html_e( 'Buy x get y', 'dukkan-plugin' );
					} elseif ( 'buy_x_get_y_repeating' === $method ) {
						esc_html_e( 'Buy x get y - Repeating', 'dukkan-plugin' );
					} elseif ( 'exclude_products_from_all_rules' === $method ) {
						esc_html_e( 'Exclude products from all rules', 'dukkan-plugin' );
					} elseif ( 'restrict_purchase_of_matched_products' === $method ) {
						esc_html_e( 'Restrict purchase of matched products', 'dukkan-plugin' );
					} else {
						esc_html_e( 'Simple adjustment', 'dukkan-plugin' );
					}
					?>
				</span>
				<div class="dukkan-dp__rule-header-right">
					<select class="dukkan-dp__rule-apply-with" data-apply-with>
						<optgroup label="<?php esc_attr_e( 'Non-Exclusive', 'dukkan-plugin' ); ?>">
							<option value="apply_with_others" <?php selected( $apply_with, 'apply_with_others' ); ?>>
								<?php esc_html_e( 'Apply with other applicable rules', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Exclusive – Per Cart Item', 'dukkan-plugin' ); ?>">
							<option value="apply_disregard_others" <?php selected( $apply_with, 'apply_disregard_others' ); ?>>
								<?php esc_html_e( 'Apply this rule and disregard other rules', 'dukkan-plugin' ); ?>
							</option>
							<option value="apply_if_others_na" <?php selected( $apply_with, 'apply_if_others_na' ); ?>>
								<?php esc_html_e( 'Apply if other rules are not applicable', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Disabled', 'dukkan-plugin' ); ?>">
							<option value="disabled" <?php selected( $apply_with, 'disabled' ); ?>>
								<?php esc_html_e( 'Disabled', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
					</select>
					<button type="button" class="dukkan-dp__rule-icon-btn" data-duplicate title="<?php esc_attr_e( 'Duplicate rule', 'dukkan-plugin' ); ?>">
						<i class="fa-solid fa-copy"></i>
					</button>
					<button type="button" class="dukkan-dp__rule-icon-btn dukkan-dp__rule-icon-btn--danger" data-remove title="<?php esc_attr_e( 'Remove rule', 'dukkan-plugin' ); ?>">
						<i class="fa-solid fa-trash-can"></i>
					</button>
					<button type="button" class="dukkan-dp__rule-icon-btn dukkan-dp__rule-toggle-btn" title="<?php esc_attr_e( 'Toggle details', 'dukkan-plugin' ); ?>">
						<i class="fa-solid fa-chevron-down dukkan-dp__rule-toggle-icon"></i>
					</button>
				</div>
			</div>

			<!-- Card Body -->
			<div class="dukkan-dp__rule-body dukkan-dp__rule-body--collapsed">
				<!-- Method -->
				<div class="dukkan-dp__field">
					<label><?php esc_html_e( 'Method', 'dukkan-plugin' ); ?></label>
					<select class="dukkan-dp__method-select" data-method>
						<optgroup label="<?php esc_attr_e( 'Simple', 'dukkan-plugin' ); ?>">
							<option value="simple_adjustment" <?php selected( $method, 'simple_adjustment' ); ?>>
								<?php esc_html_e( 'Simple adjustment', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Volume', 'dukkan-plugin' ); ?>">
							<option value="bulk_pricing" <?php selected( $method, 'bulk_pricing' ); ?>>
								<?php esc_html_e( 'Bulk pricing', 'dukkan-plugin' ); ?>
							</option>
							<option value="tiered_pricing" <?php selected( $method, 'tiered_pricing' ); ?>>
								<?php esc_html_e( 'Tiered pricing', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Group', 'dukkan-plugin' ); ?>">
							<option value="group_of_products" <?php selected( $method, 'group_of_products' ); ?>>
								<?php esc_html_e( 'Group of products', 'dukkan-plugin' ); ?>
							</option>
							<option value="group_of_products_repeating" <?php selected( $method, 'group_of_products_repeating' ); ?>>
								<?php esc_html_e( 'Group of products - Repeating', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Buy / Get', 'dukkan-plugin' ); ?>">
							<option value="buy_x_get_x" <?php selected( $method, 'buy_x_get_x' ); ?>>
								<?php esc_html_e( 'Buy x get x', 'dukkan-plugin' ); ?>
							</option>
							<option value="buy_x_get_x_repeating" <?php selected( $method, 'buy_x_get_x_repeating' ); ?>>
								<?php esc_html_e( 'Buy x get x - Repeating', 'dukkan-plugin' ); ?>
							</option>
							<option value="buy_x_get_y" <?php selected( $method, 'buy_x_get_y' ); ?>>
								<?php esc_html_e( 'Buy x get y', 'dukkan-plugin' ); ?>
							</option>
							<option value="buy_x_get_y_repeating" <?php selected( $method, 'buy_x_get_y_repeating' ); ?>>
								<?php esc_html_e( 'Buy x get y - Repeating', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Other', 'dukkan-plugin' ); ?>">
							<option value="exclude_products_from_all_rules" <?php selected( $method, 'exclude_products_from_all_rules' ); ?>>
								<?php esc_html_e( 'Exclude products from all rules', 'dukkan-plugin' ); ?>
							</option>
							<option value="restrict_purchase_of_matched_products" <?php selected( $method, 'restrict_purchase_of_matched_products' ); ?>>
								<?php esc_html_e( 'Restrict purchase of matched products', 'dukkan-plugin' ); ?>
							</option>
						</optgroup>
					</select>
				</div>

				<!-- Note -->
				<div class="dukkan-dp__field">
					<label><?php esc_html_e( 'Note – Private', 'dukkan-plugin' ); ?></label>
					<input type="text" class="dukkan-dp__input" data-note value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'Optional private note', 'dukkan-plugin' ); ?>">
				</div>

				<!-- Description -->
				<div class="dukkan-dp__field">
					<label><?php esc_html_e( 'Description – Public', 'dukkan-plugin' ); ?></label>
					<input type="text" class="dukkan-dp__input" data-description value="<?php echo esc_attr( $description ); ?>" placeholder="<?php esc_attr_e( 'Optional public description', 'dukkan-plugin' ); ?>">
				</div>

				<!-- Adjustment Box -->
				<div class="dukkan-dp__box">
					<div class="dukkan-dp__box-label"><?php esc_html_e( 'Adjustment', 'dukkan-plugin' ); ?></div>
					<div class="dukkan-dp__box-body dukkan-dp__adjustment-body">
						<select class="dukkan-dp__adjustment-type" data-adjustment-type>
							<optgroup label="<?php esc_attr_e( 'Discount', 'dukkan-plugin' ); ?>">
								<option value="fixed_discount" <?php selected( $adjustment_type, 'fixed_discount' ); ?>>
									<?php esc_html_e( 'Fixed discount', 'dukkan-plugin' ); ?>
								</option>
								<option value="percentage_discount" <?php selected( $adjustment_type, 'percentage_discount' ); ?>>
									<?php esc_html_e( 'Percentage discount', 'dukkan-plugin' ); ?>
								</option>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Fee', 'dukkan-plugin' ); ?>">
								<option value="fixed_fee" <?php selected( $adjustment_type, 'fixed_fee' ); ?>>
									<?php esc_html_e( 'Fixed fee', 'dukkan-plugin' ); ?>
								</option>
								<option value="percentage_fee" <?php selected( $adjustment_type, 'percentage_fee' ); ?>>
									<?php esc_html_e( 'Percentage fee', 'dukkan-plugin' ); ?>
								</option>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Price', 'dukkan-plugin' ); ?>">
								<option value="fixed_price" <?php selected( $adjustment_type, 'fixed_price' ); ?>>
									<?php esc_html_e( 'Fixed price', 'dukkan-plugin' ); ?>
								</option>
							</optgroup>
						</select>
						<span class="dukkan-dp__adjustment-currency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<input type="number" class="dukkan-dp__adjustment-amount" data-adjustment-amount
							   value="<?php echo esc_attr( $adjustment_amount ); ?>"
							   min="0" step="0.01" placeholder="0.00">
					</div>
				</div>

				<!-- Products Box -->
				<div class="dukkan-dp__box">
					<div class="dukkan-dp__box-label"><?php esc_html_e( 'Products', 'dukkan-plugin' ); ?></div>
					<div class="dukkan-dp__box-body dukkan-dp__products-body">
						<?php if ( empty( $products ) ) : ?>
							<div class="dukkan-dp__box-empty" data-products-empty>
								<span class="dukkan-dp__box-empty-text"><?php esc_html_e( 'Applies to all products.', 'dukkan-plugin' ); ?></span>
								<button type="button" class="dukkan-dp__box-action-btn" data-add-product>
									<?php esc_html_e( 'Add Product', 'dukkan-plugin' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="dukkan-dp__products-list" data-products-list>
								<?php foreach ( $products as $product ) : ?>
									<?php $this->render_product_filter_row( $product, $is_template ); ?>
								<?php endforeach; ?>
							</div>
							<button type="button" class="dukkan-dp__box-action-btn" data-add-product>
								<?php esc_html_e( 'Add Product', 'dukkan-plugin' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<!-- Conditions Box -->
				<div class="dukkan-dp__box">
					<div class="dukkan-dp__box-label"><?php esc_html_e( 'Conditions', 'dukkan-plugin' ); ?></div>
					<div class="dukkan-dp__box-body dukkan-dp__conditions-body">
						<?php if ( empty( $conditions ) ) : ?>
							<div class="dukkan-dp__box-empty" data-conditions-empty>
								<span class="dukkan-dp__box-empty-text"><?php esc_html_e( 'Applies in all cases.', 'dukkan-plugin' ); ?></span>
								<button type="button" class="dukkan-dp__box-action-btn" data-add-condition>
									<?php esc_html_e( 'Add Condition', 'dukkan-plugin' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="dukkan-dp__box-list" data-conditions-list>
								<?php foreach ( $conditions as $condition ) : ?>
									<div class="dukkan-dp__condition-tag" data-condition-id="<?php echo esc_attr( $condition['id'] ?? '' ); ?>">
										<span><?php echo esc_html( $condition['label'] ?? $condition['type'] ?? '' ); ?></span>
										<button type="button" class="dukkan-dp__tag-remove" data-remove-condition>&times;</button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="dukkan-dp__box-action-btn" data-add-condition>
								<?php esc_html_e( 'Add Condition', 'dukkan-plugin' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single product filter row.
	 *
	 * @since 1.0.1
	 * @param array $filter      Filter data {type, operator, value}.
	 * @param bool  $is_template Whether this is a JS clone template.
	 */
	public function render_product_filter_row( $filter = array(), $is_template = false ) {
		$type     = $is_template ? '' : ( $filter['type'] ?? 'product' );
		$operator = $is_template ? '' : ( $filter['operator'] ?? 'in_list' );
		$value    = $is_template ? '' : ( $filter['value'] ?? '' );
		?>
		<div class="dukkan-dp__product-filter-row" data-product-filter>
			<div class="dukkan-dp__product-filter-drag">
				<i class="fa-solid fa-grip-vertical"></i>
			</div>
			<select class="dukkan-dp__product-filter-type" data-filter-type>
				<optgroup label="<?php esc_attr_e( 'Product', 'dukkan-plugin' ); ?>">
					<option value="product" <?php selected( $type, 'product' ); ?>>
						<?php esc_html_e( 'Product', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_variation" <?php selected( $type, 'product_variation' ); ?>>
						<?php esc_html_e( 'Product variation', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_category" <?php selected( $type, 'product_category' ); ?>>
						<?php esc_html_e( 'Product category', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_attributes" <?php selected( $type, 'product_attributes' ); ?>>
						<?php esc_html_e( 'Product attributes', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_tags" <?php selected( $type, 'product_tags' ); ?>>
						<?php esc_html_e( 'Product tags', 'dukkan-plugin' ); ?>
					</option>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Product Property', 'dukkan-plugin' ); ?>">
					<option value="product_regular_price" <?php selected( $type, 'product_regular_price' ); ?>>
						<?php esc_html_e( 'Product regular price', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_is_on_sale" <?php selected( $type, 'product_is_on_sale' ); ?>>
						<?php esc_html_e( 'Product is on sale', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_stock_quantity" <?php selected( $type, 'product_stock_quantity' ); ?>>
						<?php esc_html_e( 'Product stock quantity', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_shipping_class" <?php selected( $type, 'product_shipping_class' ); ?>>
						<?php esc_html_e( 'Product shipping class', 'dukkan-plugin' ); ?>
					</option>
					<option value="product_metadata" <?php selected( $type, 'product_metadata' ); ?>>
						<?php esc_html_e( 'Product metadata', 'dukkan-plugin' ); ?>
					</option>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Other', 'dukkan-plugin' ); ?>">
					<option value="cart_item_data" <?php selected( $type, 'cart_item_data' ); ?>>
						<?php esc_html_e( 'Cart item data', 'dukkan-plugin' ); ?>
					</option>
					<option value="coupons_applied" <?php selected( $type, 'coupons_applied' ); ?>>
						<?php esc_html_e( 'Coupons applied', 'dukkan-plugin' ); ?>
					</option>
				</optgroup>
			</select>
			<select class="dukkan-dp__product-filter-operator" data-filter-operator>
				<?php if ( 'product' === $type ) : ?>
					<option value="in_list" <?php selected( $operator, 'in_list' ); ?>>
						<?php esc_html_e( 'in list', 'dukkan-plugin' ); ?>
					</option>
					<option value="not_in_list" <?php selected( $operator, 'not_in_list' ); ?>>
						<?php esc_html_e( 'not in list', 'dukkan-plugin' ); ?>
					</option>
				<?php else : ?>
					<option value="in_list" <?php selected( $operator, 'in_list' ); ?>>
						<?php esc_html_e( 'in list', 'dukkan-plugin' ); ?>
					</option>
					<option value="not_in_list" <?php selected( $operator, 'not_in_list' ); ?>>
						<?php esc_html_e( 'not in list', 'dukkan-plugin' ); ?>
					</option>
					<option value="equals" <?php selected( $operator, 'equals' ); ?>>
						<?php esc_html_e( 'equals', 'dukkan-plugin' ); ?>
					</option>
				<option value="not_equals" <?php selected( $operator, 'not_equals' ); ?>>
					<?php esc_html_e( 'not equals', 'dukkan-plugin' ); ?>
				</option>
				<option value="greater_than" <?php selected( $operator, 'greater_than' ); ?>>
					<?php esc_html_e( 'greater than', 'dukkan-plugin' ); ?>
				</option>
				<option value="less_than" <?php selected( $operator, 'less_than' ); ?>>
					<?php esc_html_e( 'less than', 'dukkan-plugin' ); ?>
				</option>
				<option value="greater_than_or_equal" <?php selected( $operator, 'greater_than_or_equal' ); ?>>
					<?php esc_html_e( 'greater than or equal', 'dukkan-plugin' ); ?>
				</option>
				<option value="less_than_or_equal" <?php selected( $operator, 'less_than_or_equal' ); ?>>
					<?php esc_html_e( 'less than or equal', 'dukkan-plugin' ); ?>
				</option>
				<option value="contains" <?php selected( $operator, 'contains' ); ?>>
					<?php esc_html_e( 'contains', 'dukkan-plugin' ); ?>
				</option>
				<option value="does_not_contain" <?php selected( $operator, 'does_not_contain' ); ?>>
						<?php esc_html_e( 'does not contain', 'dukkan-plugin' ); ?>
					</option>
				<?php endif; ?>
			</select>
			<div class="dukkan-dp__product-filter-value" data-filter-value-wrap>
				<?php
				// --- Select2 multi-select for Product type ---
				$show_product_select2 = $is_template || 'product' === $type;
				if ( $show_product_select2 ) :
					// Pre-load selected product options.
					$selected_product_ids = array();
					if ( is_array( $value ) ) {
						$selected_product_ids = array_map( 'intval', $value );
					} elseif ( is_numeric( $value ) ) {
						$selected_product_ids = array( (int) $value );
					}
					?>
					<select class="dukkan-dp__product-filter-value-select2"
							data-filter-value-select2
							multiple
							style="display:<?php echo 'product' === $type ? '' : 'none'; ?>;width:100%;"
							data-placeholder="<?php esc_attr_e( 'Search products…', 'dukkan-plugin' ); ?>">
						<?php foreach ( $selected_product_ids as $pid ) : ?>
							<?php
							$p = wc_get_product( $pid );
							if ( ! $p ) {
								continue;
							}
							$p_sku   = $p->get_sku();
							$p_title = $p->get_name();
							$p_text  = $p_title;
							if ( ! empty( $p_sku ) ) {
								$p_text .= ' (' . $p_sku . ')';
							}
							?>
							<option value="<?php echo esc_attr( (string) $pid ); ?>" selected="selected">
								<?php echo esc_html( $p_text ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<?php
				// --- Select for category / attributes / tags / shipping-class ---
				if ( $is_template || in_array( $type, array( 'product_category', 'product_attributes', 'product_tags', 'product_shipping_class' ), true ) ) :
					?>
					<select class="dukkan-dp__product-filter-value-select" data-filter-value multiple
							data-placeholder="<?php esc_attr_e( 'Select…', 'dukkan-plugin' ); ?>"
							style="display:<?php echo in_array( $type, array( 'product_category', 'product_attributes', 'product_tags', 'product_shipping_class' ), true ) ? '' : 'none'; ?>">
					</select>
				<?php endif; ?>
				<?php if ( $is_template || 'product_is_on_sale' === $type ) : ?>
					<select class="dukkan-dp__product-filter-value-on-sale" data-filter-value-on-sale
							style="display:<?php echo 'product_is_on_sale' === $type ? '' : 'none'; ?>">
						<option value="yes" <?php selected( $value, 'yes' ); ?>><?php esc_html_e( 'Yes', 'dukkan-plugin' ); ?></option>
						<option value="no" <?php selected( $value, 'no' ); ?>><?php esc_html_e( 'No', 'dukkan-plugin' ); ?></option>
					</select>
				<?php endif; ?>
				<?php
				$text_types = array( 'product_variation', 'product_regular_price', 'product_stock_quantity', 'product_metadata', 'cart_item_data', 'coupons_applied' );
				$show_text  = $is_template || ( empty( $type ) || in_array( $type, $text_types, true ) );
				$text_hidden = ! $show_text;
				?>
				<input type="text"
					   class="dukkan-dp__product-filter-value-input"
					   data-filter-value-input
					   value="<?php echo esc_attr( is_array( $value ) ? implode( ', ', $value ) : $value ); ?>"
					   placeholder="<?php esc_attr_e( 'Search or enter value…', 'dukkan-plugin' ); ?>"
					   <?php echo $text_hidden ? 'style="display:none;"' : ''; ?>
				>
			</div>
			<button type="button" class="dukkan-dp__product-filter-remove" data-remove-product-filter
					title="<?php esc_attr_e( 'Remove filter', 'dukkan-plugin' ); ?>">
				<i class="fa-solid fa-xmark"></i>
			</button>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Global Settings
	// -------------------------------------------------------------------------

	/**
	 * Get a global pricing setting with a default fallback.
	 *
	 * @since  1.0.1
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Default value.
	 * @return mixed
	 */
	public function get_global_setting( $key, $default = null ) {
		$settings = get_option( self::GLOBAL_OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Save a global pricing setting.
	 *
	 * @since 1.0.1
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	private function save_global_setting( $key, $value ) {
		$settings = get_option( self::GLOBAL_OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings[ $key ] = $value;
		update_option( self::GLOBAL_OPTION_KEY, $settings, 'no' );
	}

	// -------------------------------------------------------------------------
	// Data Access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all pricing rules as an ordered array.
	 *
	 * @since  1.0.1
	 * @return array<int, array>
	 */
	public function get_all_rules() {
		$rules = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $rules ) ) {
			return array();
		}
		// Convert legacy associative format to indexed.
		if ( $this->is_assoc( $rules ) ) {
			return array_values( $rules );
		}
		return $rules;
	}

	/**
	 * Detect if an array is associative.
	 *
	 * @since  1.0.1
	 * @param  array $arr
	 * @return bool
	 */
	private function is_assoc( $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Persist the rules array.
	 *
	 * @since 1.0.1
	 * @param array $rules
	 */
	private function save_rules( $rules ) {
		update_option( self::OPTION_KEY, $rules, 'no' );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate pricing rule input.
	 *
	 * @since  1.0.1
	 * @param  array $data Raw POST data.
	 * @return array{valid: bool, errors: string[], sanitized: array}
	 */
	private function validate( $data ) {
		$errors = array();

		$sanitized = array(
			'method'            => sanitize_text_field( $data['method'] ?? 'simple_adjustment' ),
			'note'              => sanitize_text_field( $data['note'] ?? '' ),
			'description'       => sanitize_text_field( $data['description'] ?? '' ),
			'adjustment_type'   => sanitize_text_field( $data['adjustment_type'] ?? 'fixed_discount' ),
			'adjustment_amount' => floatval( $data['adjustment_amount'] ?? 0 ),
			'apply_with'        => sanitize_text_field( $data['apply_with'] ?? 'apply_with_others' ),
			'products'          => array(),
			'conditions'        => array(),
		);

		if ( ! in_array( $sanitized['method'], array( 'simple_adjustment', 'bulk_pricing', 'tiered_pricing', 'group_of_products', 'group_of_products_repeating', 'buy_x_get_x', 'buy_x_get_x_repeating', 'buy_x_get_y', 'buy_x_get_y_repeating', 'exclude_products_from_all_rules', 'restrict_purchase_of_matched_products' ), true ) ) {
			$sanitized['method'] = 'simple_adjustment';
		}

		if ( ! in_array( $sanitized['adjustment_type'], array( 'fixed_discount', 'percentage_discount', 'fixed_fee', 'percentage_fee', 'fixed_price' ), true ) ) {
			$sanitized['adjustment_type'] = 'fixed_discount';
		}

		if ( ! in_array( $sanitized['apply_with'], array( 'apply_with_others', 'apply_disregard_others', 'apply_if_others_na', 'disabled' ), true ) ) {
			$sanitized['apply_with'] = 'apply_with_others';
		}

		// Sanitize product filters.
		if ( ! empty( $data['products'] ) && is_array( $data['products'] ) ) {
			$valid_types = array(
				'product', 'product_variation', 'product_category', 'product_attributes',
				'product_tags', 'product_regular_price', 'product_is_on_sale',
				'product_stock_quantity', 'product_shipping_class', 'product_metadata',
				'cart_item_data', 'coupons_applied',
			);
			$valid_operators = array(
				'in_list', 'not_in_list', 'equals', 'not_equals', 'greater_than',
				'less_than', 'greater_than_or_equal', 'less_than_or_equal',
				'contains', 'does_not_contain',
			);
			foreach ( $data['products'] as $product ) {
				if ( ! is_array( $product ) ) {
					continue;
				}
				$filter = array(
					'type'     => sanitize_text_field( $product['type'] ?? 'product' ),
					'operator' => sanitize_text_field( $product['operator'] ?? 'in_list' ),
					'value'    => is_array( $product['value'] ?? '' )
						? array_map( 'sanitize_text_field', $product['value'] )
						: sanitize_text_field( $product['value'] ?? '' ),
				);
				if ( ! in_array( $filter['type'], $valid_types, true ) ) {
					$filter['type'] = 'product';
				}
				if ( ! in_array( $filter['operator'], $valid_operators, true ) ) {
					$filter['operator'] = 'in_list';
				}
				$sanitized['products'][] = $filter;
			}
		}

		// Sanitize conditions if present.
		if ( ! empty( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			foreach ( $data['conditions'] as $cond ) {
				$condition = array(
					'id'    => sanitize_text_field( $cond['id'] ?? '' ),
					'type'  => sanitize_text_field( $cond['type'] ?? '' ),
					'label' => sanitize_text_field( $cond['label'] ?? '' ),
				);
				if ( ! empty( $condition['type'] ) ) {
					$sanitized['conditions'][] = $condition;
				}
			}
		}

		return array(
			'valid'     => empty( $errors ),
			'errors'    => $errors,
			'sanitized' => $sanitized,
		);
	}

	// -------------------------------------------------------------------------
	// AJAX Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify AJAX nonce and capability.
	 *
	 * @since 1.0.1
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * Generate a unique rule ID.
	 *
	 * @since  1.0.1
	 * @return string
	 */
	private function generate_rule_id() {
		return uniqid( 'dp_' );
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: list all pricing rules.
	 *
	 * @since 1.0.1
	 */
	public function ajax_list() {
		$this->verify_ajax();
		wp_send_json_success( $this->get_all_rules() );
	}

	/**
	 * AJAX: add a new pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_add() {
		$this->verify_ajax();

		$raw    = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
		$result = $this->validate( $raw );

		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$sanitized       = $result['sanitized'];
		$rule_id         = $this->generate_rule_id();
		$sanitized['id'] = $rule_id;

		$rules    = $this->get_all_rules();
		$rules[]  = $sanitized;
		$this->save_rules( $rules );

		wp_send_json_success( $sanitized );
	}

	/**
	 * AJAX: update an existing pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_update() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$raw     = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		$found = $this->find_rule_index( $rules, $rule_id );

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$result = $this->validate( $raw );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$sanitized             = $result['sanitized'];
		$sanitized['id']       = $rule_id;
		$rules[ $found ]       = $sanitized;
		$this->save_rules( $rules );

		wp_send_json_success( $sanitized );
	}

	/**
	 * AJAX: delete a pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_delete() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		$found = $this->find_rule_index( $rules, $rule_id );

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		unset( $rules[ $found ] );
		$this->save_rules( array_values( $rules ) );

		wp_send_json_success( array( 'deleted' => true, 'rule_id' => $rule_id ) );
	}

	/**
	 * AJAX: duplicate a pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_duplicate() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		$found = $this->find_rule_index( $rules, $rule_id );

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$new_rule       = $rules[ $found ];
		$new_rule_id    = $this->generate_rule_id();
		$new_rule['id'] = $new_rule_id;

		$rules[] = $new_rule;
		$this->save_rules( $rules );

		wp_send_json_success( $new_rule );
	}

	/**
	 * AJAX: toggle a rule's enabled/disabled status.
	 *
	 * @since 1.0.1
	 */
	public function ajax_toggle() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		$found = $this->find_rule_index( $rules, $rule_id );

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$rules[ $found ]['status'] = $status;
		$this->save_rules( $rules );

		wp_send_json_success( array( 'rule_id' => $rule_id, 'status' => $status ) );
	}

	/**
	 * AJAX: get a single pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_get() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		$found = $this->find_rule_index( $rules, $rule_id );

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$rule = $rules[ $found ];

		// Resolve product IDs to names for display.
		if ( ! empty( $rule['products'] ) ) {
			foreach ( $rule['products'] as $i => $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$rule['products'][ $i ] = array(
						'id'   => $product->get_id(),
						'name' => $product->get_name(),
					);
				} else {
					unset( $rule['products'][ $i ] );
				}
			}
			$rule['products'] = array_values( $rule['products'] );
		}

		wp_send_json_success( $rule );
	}

	/**
	 * AJAX: save all rules (used for reorder).
	 *
	 * @since 1.0.1
	 */
	public function ajax_save_all() {
		$this->verify_ajax();

		$raw_rules = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array();

		$sanitized_rules = array();
		foreach ( $raw_rules as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$result = $this->validate( $raw );
			if ( $result['valid'] ) {
				$sanitized             = $result['sanitized'];
				$sanitized['id']       = sanitize_text_field( $raw['id'] ?? '' );
				$sanitized_rules[]     = $sanitized;
			}
		}

		$this->save_rules( $sanitized_rules );

		wp_send_json_success( array( 'saved' => true, 'count' => count( $sanitized_rules ) ) );
	}

	/**
	 * AJAX: save a global pricing setting.
	 *
	 * @since 1.0.1
	 */
	public function ajax_save_global() {
		$this->verify_ajax();

		$key = isset( $_POST['setting_key'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_key'] ) ) : '';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Setting key is required.', 'dukkan-plugin' ) ), 400 );
		}

		// Sanitize numeric values as floats; everything else as text.
		if ( 'discount_limit_value' === $key ) {
			$value = isset( $_POST['setting_value'] ) ? floatval( wp_unslash( $_POST['setting_value'] ) ) : 0;
		} else {
			$value = isset( $_POST['setting_value'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_value'] ) ) : '';
		}

		$this->save_global_setting( $key, $value );

		wp_send_json_success( array( 'key' => $key, 'value' => $value ) );
	}

	// -------------------------------------------------------------------------
	// Product Search AJAX
	// -------------------------------------------------------------------------

	/**
	 * AJAX: search WooCommerce products by title, SKU, or ID.
	 *
	 * Returns Select2-compatible results for the dynamic pricing product
	 * filter multi-select.
	 *
	 * @since 1.0.1
	 */
	public function ajax_product_search() {
		$this->verify_ajax();

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		// Require at least 2 characters for server-side search.
		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$results = array();
		$limit   = 30;

		// Search by title.
		$products = wc_get_products( array(
			'status'  => 'publish',
			'limit'   => $limit,
			's'       => $term,
			'type'    => array( 'simple', 'variable' ),
			'orderby' => 'title',
			'order'   => 'ASC',
		) );

		$seen_ids = array();

		foreach ( $products as $product ) {
			$pid   = $product->get_id();
			$sku   = $product->get_sku();
			$title = $product->get_name();

			if ( in_array( $pid, $seen_ids, true ) ) {
				continue;
			}
			$seen_ids[] = $pid;

			$text = $title;
			if ( ! empty( $sku ) ) {
				$text .= ' (' . $sku . ')';
			}

			$results[] = array(
				'id'   => (string) $pid,
				'text' => $text,
				'sku'  => $sku,
			);
		}

		// Also search by SKU (meta query) for partial matches that title
		// search might miss.
		if ( count( $results ) < $limit ) {
			$remaining = $limit - count( $results );

			$sku_products = wc_get_products( array(
				'status'     => 'publish',
				'limit'      => $remaining,
				'type'       => array( 'simple', 'variable' ),
				'sku'        => $term,
				'orderby'    => 'title',
				'order'      => 'ASC',
			) );

			foreach ( $sku_products as $product ) {
				$pid   = $product->get_id();
				$sku   = $product->get_sku();
				$title = $product->get_name();

				if ( in_array( $pid, $seen_ids, true ) ) {
					continue;
				}
				$seen_ids[] = $pid;

				$text = $title;
				if ( ! empty( $sku ) ) {
					$text .= ' (' . $sku . ')';
				}

				$results[] = array(
					'id'   => (string) $pid,
					'text' => $text,
					'sku'  => $sku,
				);
			}
		}

		// Also search by numeric ID if the term looks like a number.
		if ( is_numeric( $term ) && ! in_array( (int) $term, $seen_ids, true ) ) {
			$product = wc_get_product( (int) $term );
			if ( $product && 'publish' === $product->get_status() ) {
				$sku   = $product->get_sku();
				$title = $product->get_name();
				$text  = $title;
				if ( ! empty( $sku ) ) {
					$text .= ' (' . $sku . ')';
				}
				$results[] = array(
					'id'   => (string) $product->get_id(),
					'text' => $text,
					'sku'  => $sku,
				);
			}
		}

		wp_send_json_success( $results );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Find a rule's index in the array by its ID.
	 *
	 * @since  1.0.1
	 * @param  array  $rules
	 * @param  string $rule_id
	 * @return int|false
	 */
	private function find_rule_index( $rules, $rule_id ) {
		foreach ( $rules as $i => $rule ) {
			if ( isset( $rule['id'] ) && $rule['id'] === $rule_id ) {
				return $i;
			}
		}
		return false;
	}
}
