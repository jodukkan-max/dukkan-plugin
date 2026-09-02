<?php

/**
 * The loyalty-points public-facing functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.25
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * Loyalty points on the storefront: a cart/checkout redemption box, the
 * discount fee, and the customer balance on the My Account dashboard.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Loyalty_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.25
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.25
	 * @var string
	 */
	private $version;

	/**
	 * Shared loyalty engine.
	 *
	 * @since 1.0.25
	 * @var Dukkan_Plugin_Loyalty
	 */
	private $loyalty;

	/**
	 * Initialize the class and register hooks.
	 *
	 * @since 1.0.25
	 * @param string                $plugin_name The name of this plugin.
	 * @param string                $version     The version of this plugin.
	 * @param Dukkan_Plugin_Loyalty $loyalty     The loyalty engine.
	 */
	public function __construct( $plugin_name, $version, $loyalty ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loyalty     = $loyalty;

		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_discount' ), 20 );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render_redeem_box' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'render_redeem_box' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'render_account_balance' ) );

		add_action( 'wp_ajax_dukkan_loyalty_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_dukkan_loyalty_remove', array( $this, 'ajax_remove' ) );
	}

	/**
	 * Register the public styles.
	 *
	 * @since 1.0.25
	 */
	public function enqueue_styles() {
		if ( ! function_exists( 'is_checkout' ) && ! function_exists( 'is_cart' ) && ! function_exists( 'is_account_page' ) ) {
			return;
		}
		if ( ! ( is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}

		$css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dukkan-plugin-loyalty.css' );
		wp_enqueue_style( $this->plugin_name . '-loyalty', plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-loyalty.css', array(), $css_version, 'all' );
	}

	/**
	 * Register the public scripts.
	 *
	 * @since 1.0.25
	 */
	public function enqueue_scripts() {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) {
			return;
		}

		$js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/dukkan-plugin-loyalty.js' );
		wp_enqueue_script( $this->plugin_name . '-loyalty', plugin_dir_url( __FILE__ ) . 'js/dukkan-plugin-loyalty.js', array( 'jquery' ), $js_version, true );

		wp_localize_script(
			$this->plugin_name . '-loyalty',
			'dukkan_loyalty',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'dukkan_loyalty_nonce' ),
			)
		);
	}

	/**
	 * Apply the points discount as a negative fee.
	 *
	 * @since 1.0.25
	 * @param WC_Cart $cart
	 */
	public function apply_discount( $cart ) {
		if ( ! $this->loyalty->is_enabled() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $this->loyalty->get_balance( $user_id ) <= 0 ) {
			$this->loyalty->set_session_redeem_points( 0 );
			return;
		}

		// Coupon-stacking rule.
		if ( empty( $this->loyalty->get_setting( 'redeem_with_coupons' ) ) && ! empty( $cart->get_applied_coupons() ) ) {
			$this->loyalty->set_session_redeem_points( 0 );
			return;
		}

		$requested = $this->loyalty->get_session_redeem_points();
		if ( $requested <= 0 ) {
			return;
		}

		$max    = $this->loyalty->max_redeemable_points( $user_id );
		$points = min( $requested, $max );

		if ( $points <= 0 ) {
			$this->loyalty->set_session_redeem_points( 0 );
			return;
		}

		$discount = (float) $this->loyalty->calculate_redeem_value( $points );
		$subtotal = (float) $cart->get_subtotal();

		if ( $discount > $subtotal ) {
			$discount = $subtotal;
		}

		if ( $discount <= 0 ) {
			return;
		}

		// Keep the session in sync with what was actually applied.
		$this->loyalty->set_session_redeem_points( $points );

		$cart->add_fee( __( 'Points redemption', 'dukkan-plugin' ), -$discount, false );
	}

	/**
	 * Render the redemption box on cart and checkout.
	 *
	 * @since 1.0.25
	 */
	public function render_redeem_box() {
		// Guard against double rendering when a theme fires both the cart-totals
		// and checkout order-total hooks on the same page.
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		if ( ! $this->loyalty->is_enabled() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$balance = $this->loyalty->get_balance( $user_id );

		if ( $balance <= 0 ) {
			return;
		}

		$max     = $this->loyalty->max_redeemable_points( $user_id );
		$applied = $this->loyalty->get_session_redeem_points();

		$balance_value = $this->loyalty->calculate_redeem_value( $balance );
		$applied_value = $this->loyalty->calculate_redeem_value( $applied );

		?>
		<div class="dukkan-loyalty-box" data-max="<?php echo esc_attr( $max ); ?>" data-balance="<?php echo esc_attr( $balance ); ?>">
			<div class="dukkan-loyalty-box__head">
				<span class="dukkan-loyalty-box__title"><?php esc_html_e( 'Use your loyalty points', 'dukkan-plugin' ); ?></span>
				<span class="dukkan-loyalty-box__balance">
					<?php
					/* translators: 1: points balance, 2: cash value */
					printf( esc_html__( 'You have %1$s points (worth %2$s)', 'dukkan-plugin' ), number_format_i18n( $balance ), wp_kses_post( wc_price( $balance_value ) ) );
					?>
				</span>
			</div>

			<?php if ( $max <= 0 ) : ?>
				<p class="dukkan-loyalty-box__note"><?php esc_html_e( 'Your points are not redeemable on this order.', 'dukkan-plugin' ); ?></p>
			<?php elseif ( $applied > 0 ) : ?>
				<p class="dukkan-loyalty-box__applied">
					<?php
					/* translators: 1: applied points, 2: applied value */
					printf( esc_html__( '%1$s points applied (%2$s off)', 'dukkan-plugin' ), number_format_i18n( $applied ), wp_kses_post( wc_price( $applied_value ) ) );
					?>
				</p>
				<button type="button" class="button dukkan-loyalty-remove"><?php esc_html_e( 'Remove', 'dukkan-plugin' ); ?></button>
			<?php else : ?>
				<div class="dukkan-loyalty-box__apply">
					<input type="number" min="1" max="<?php echo esc_attr( $max ); ?>" class="dukkan-loyalty-input" value="<?php echo esc_attr( $max ); ?>" />
					<button type="button" class="button dukkan-loyalty-apply"><?php esc_html_e( 'Apply', 'dukkan-plugin' ); ?></button>
				</div>
				<p class="dukkan-loyalty-box__note">
					<?php
					/* translators: %s: maximum redeemable points */
					printf( esc_html__( 'You can redeem up to %s points on this order.', 'dukkan-plugin' ), number_format_i18n( $max ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the points balance on the My Account dashboard.
	 *
	 * @since 1.0.25
	 */
	public function render_account_balance() {
		if ( ! $this->loyalty->is_enabled() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$balance = $this->loyalty->get_balance( $user_id );
		$value   = $this->loyalty->calculate_redeem_value( $balance );

		echo '<div class="dukkan-loyalty-account">';
		echo '<h3>' . esc_html__( 'Loyalty points', 'dukkan-plugin' ) . '</h3>';
		/* translators: 1: points balance, 2: cash value */
		printf(
			'<p>' . esc_html__( 'Your balance: %1$s points (worth %2$s).', 'dukkan-plugin' ) . '</p>',
			esc_html( number_format_i18n( $balance ) ),
			wp_kses_post( wc_price( $value ) )
		);
		echo '</div>';
	}

	/**
	 * AJAX: set the points to redeem in the session.
	 *
	 * @since 1.0.25
	 */
	public function ajax_apply() {
		check_ajax_referer( 'dukkan_loyalty_nonce', 'nonce' );

		if ( ! is_user_logged_in() || ! $this->loyalty->is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Points redemption is unavailable.', 'dukkan-plugin' ) ) );
		}

		$user_id   = get_current_user_id();
		$requested = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;
		$max       = $this->loyalty->max_redeemable_points( $user_id );

		if ( $requested <= 0 || $requested > $max ) {
			wp_send_json_error( array( 'message' => __( 'Invalid points amount.', 'dukkan-plugin' ) ) );
		}

		$this->loyalty->set_session_redeem_points( $requested );

		wp_send_json_success(
			array(
				'points' => $requested,
				'value'  => $this->loyalty->calculate_redeem_value( $requested ),
			)
		);
	}

	/**
	 * AJAX: clear the points to redeem from the session.
	 *
	 * @since 1.0.25
	 */
	public function ajax_remove() {
		check_ajax_referer( 'dukkan_loyalty_nonce', 'nonce' );
		$this->loyalty->set_session_redeem_points( 0 );
		wp_send_json_success( array( 'points' => 0 ) );
	}
}
