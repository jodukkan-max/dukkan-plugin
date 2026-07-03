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
					} elseif ( 'buy_x_get_y' === $method ) {
						esc_html_e( 'Buy X Get Y', 'dukkan-plugin' );
					} elseif ( 'bundle' === $method ) {
						esc_html_e( 'Bundle', 'dukkan-plugin' );
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
					<button type="button" class="dukkan-dp__rule-icon-btn dukkan-dp__rule-toggle-btn" data-toggle-collapse title="<?php esc_attr_e( 'Toggle details', 'dukkan-plugin' ); ?>">
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
						<option value="simple_adjustment" <?php selected( $method, 'simple_adjustment' ); ?>>
							<?php esc_html_e( 'Simple adjustment', 'dukkan-plugin' ); ?>
						</option>
						<option value="bulk_pricing" <?php selected( $method, 'bulk_pricing' ); ?>>
							<?php esc_html_e( 'Bulk pricing', 'dukkan-plugin' ); ?>
						</option>
						<option value="buy_x_get_y" <?php selected( $method, 'buy_x_get_y' ); ?>>
							<?php esc_html_e( 'Buy X Get Y', 'dukkan-plugin' ); ?>
						</option>
						<option value="bundle" <?php selected( $method, 'bundle' ); ?>>
							<?php esc_html_e( 'Bundle', 'dukkan-plugin' ); ?>
						</option>
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
							<option value="fixed_discount" <?php selected( $adjustment_type, 'fixed_discount' ); ?>>
								<?php esc_html_e( 'Fixed discount', 'dukkan-plugin' ); ?>
							</option>
							<option value="percentage_discount" <?php selected( $adjustment_type, 'percentage_discount' ); ?>>
								<?php esc_html_e( 'Percentage discount', 'dukkan-plugin' ); ?>
							</option>
							<option value="fixed_price" <?php selected( $adjustment_type, 'fixed_price' ); ?>>
								<?php esc_html_e( 'Fixed price', 'dukkan-plugin' ); ?>
							</option>
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
							<div class="dukkan-dp__box-list" data-products-list>
								<?php foreach ( $products as $product ) : ?>
									<div class="dukkan-dp__product-tag" data-product-id="<?php echo esc_attr( is_array( $product ) ? $product['id'] : $product ); ?>">
										<span><?php echo esc_html( is_array( $product ) ? $product['name'] : $product ); ?></span>
										<button type="button" class="dukkan-dp__tag-remove" data-remove-product>&times;</button>
									</div>
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
			'products'          => array_map( 'intval', $data['products'] ?? array() ),
			'conditions'        => array(),
		);

		if ( ! in_array( $sanitized['method'], array( 'simple_adjustment', 'bulk_pricing', 'buy_x_get_y', 'bundle' ), true ) ) {
			$sanitized['method'] = 'simple_adjustment';
		}

		if ( ! in_array( $sanitized['adjustment_type'], array( 'fixed_discount', 'percentage_discount', 'fixed_price' ), true ) ) {
			$sanitized['adjustment_type'] = 'fixed_discount';
		}

		if ( ! in_array( $sanitized['apply_with'], array( 'apply_with_others', 'apply_disregard_others', 'apply_if_others_na', 'disabled' ), true ) ) {
			$sanitized['apply_with'] = 'apply_with_others';
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
