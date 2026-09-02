<?php

/**
 * The loyalty-points core engine of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.25
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */

/**
 * Core loyalty-points engine.
 *
 * Registered customers earn points based on the amount they spend and can
 * redeem those points as a cart discount at checkout. This class owns the
 * settings, the points ledger, the running balance, and the WooCommerce
 * order-status hooks that award / spend / reverse points.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Loyalty {

	/**
	 * Option key that holds the loyalty settings.
	 *
	 * @since 1.0.25
	 * @var   string
	 */
	const SETTINGS_KEY = 'dukkan_loyalty_settings';

	/**
	 * Ledger table name (without prefix).
	 *
	 * @since 1.0.25
	 * @var   string
	 */
	const TABLE = 'dukkan_loyalty_ledger';

	/**
	 * User meta key for the running points balance.
	 *
	 * @since 1.0.25
	 * @var   string
	 */
	const BALANCE_META = '_dukkan_loyalty_points';

	/**
	 * Order meta keys.
	 *
	 * @since 1.0.25
	 * @var   string
	 */
	const META_EARNED          = '_dukkan_points_earned';
	const META_SPENT           = '_dukkan_points_spent';
	const META_RESERVED        = '_dukkan_points_reserved';
	const META_REDEEMED_VALUE  = '_dukkan_points_redeemed_value';
	const META_REVERSED_EARNED = '_dukkan_points_reversed_earned';
	const META_REVERSED_SPENT  = '_dukkan_points_reversed_spent';

	/**
	 * WC session key holding the points the customer chose to redeem.
	 *
	 * @since 1.0.25
	 * @var   string
	 */
	const SESSION_KEY = 'dukkan_loyalty_redeem_points';

	/**
	 * Default settings.
	 *
	 * @since 1.0.25
	 * @var   array
	 */
	protected $defaults = array(
		'enabled'               => 0,
		'points_per_amount'     => 1,
		'amount'                => 1,
		'value_per_point'       => 0.01,
		'max_redeem_percent'    => 100,
		'excluded_product_ids'  => array(),
		'excluded_category_ids' => array(),
		'redeem_with_coupons'   => 1,
	);

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
	 * Initialize the class and register hooks.
	 *
	 * @since 1.0.25
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'reserve_points' ), 10, 3 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'award_and_finalize' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'release_or_reverse' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'release_or_reverse' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( $this, 'reverse_on_refund' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/**
	 * Retrieve the loyalty settings merged with defaults.
	 *
	 * @since  1.0.25
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::SETTINGS_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, $this->defaults );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @since  1.0.25
	 * @param  string $key Setting key.
	 * @return mixed
	 */
	public function get_setting( $key ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Persist settings.
	 *
	 * @since  1.0.25
	 * @param  array $settings
	 */
	public function save_settings( $settings ) {
		update_option( self::SETTINGS_KEY, $settings, 'no' );
	}

	/**
	 * Whether loyalty points are enabled.
	 *
	 * @since  1.0.25
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->get_setting( 'enabled' ) );
	}

	// -------------------------------------------------------------------------
	// Ledger + balance
	// -------------------------------------------------------------------------

	/**
	 * Full ledger table name.
	 *
	 * @since  1.0.25
	 * @return string
	 */
	public function ledger_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Current points balance for a user.
	 *
	 * @since  1.0.25
	 * @param  int $user_id
	 * @return int
	 */
	public function get_balance( $user_id ) {
		return (int) get_user_meta( (int) $user_id, self::BALANCE_META, true );
	}

	/**
	 * Persist a user's running balance.
	 *
	 * @since  1.0.25
	 * @param int $user_id
	 * @param int $points
	 */
	public function set_balance( $user_id, $points ) {
		update_user_meta( (int) $user_id, self::BALANCE_META, (int) $points );
	}

	/**
	 * Append a row to the ledger.
	 *
	 * @since  1.0.25
	 * @param int    $user_id
	 * @param int    $order_id
	 * @param int    $points Signed points delta.
	 * @param string $type    earn|spend|adjust|reversal
	 * @param string $note
	 */
	public function ledger_add( $user_id, $order_id, $points, $type, $note = '' ) {
		global $wpdb;

		$wpdb->insert(
			$this->ledger_table(),
			array(
				'user_id'    => (int) $user_id,
				'order_id'   => (int) $order_id,
				'points'     => (int) $points,
				'type'       => sanitize_key( $type ),
				'note'       => sanitize_text_field( $note ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Add points to a user (balance + ledger).
	 *
	 * @since  1.0.25
	 * @param int    $user_id
	 * @param int    $points
	 * @param int    $order_id
	 * @param string $type
	 * @param string $note
	 */
	public function add_points( $user_id, $points, $order_id = 0, $type = 'adjust', $note = '' ) {
		$points = (int) $points;
		if ( 0 === $points ) {
			return;
		}
		$this->set_balance( $user_id, $this->get_balance( $user_id ) + $points );
		$this->ledger_add( $user_id, $order_id, $points, $type, $note );
	}

	/**
	 * Deduct points from a user (balance + ledger). Deduction is written as a
	 * negative ledger entry so the running total can be reconstructed.
	 *
	 * @since  1.0.25
	 * @param int    $user_id
	 * @param int    $points
	 * @param int    $order_id
	 * @param string $type
	 * @param string $note
	 */
	public function deduct_points( $user_id, $points, $order_id = 0, $type = 'spend', $note = '' ) {
		$points = (int) $points;
		if ( 0 === $points ) {
			return;
		}
		$new_balance = $this->get_balance( $user_id ) - $points;
		if ( $new_balance < 0 ) {
			$new_balance = 0;
		}
		$this->set_balance( $user_id, $new_balance );
		$this->ledger_add( $user_id, $order_id, -$points, $type, $note );
	}

	// -------------------------------------------------------------------------
	// Earn calculation
	// -------------------------------------------------------------------------

	/**
	 * Eligible order subtotal for earning: non-excluded product line subtotals
	 * minus the cash value already redeemed with points.
	 *
	 * @since  1.0.25
	 * @param  WC_Order $order
	 * @return float
	 */
	public function get_eligible_subtotal( $order ) {
		$settings            = $this->get_settings();
		$excluded_products   = array_map( 'intval', (array) $settings['excluded_product_ids'] );
		$excluded_categories = array_map( 'intval', (array) $settings['excluded_category_ids'] );

		$eligible = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = (int) $item->get_product_id();

			if ( in_array( $product_id, $excluded_products, true ) ) {
				continue;
			}
			if ( ! empty( $excluded_categories ) && has_term( $excluded_categories, 'product_cat', $product_id ) ) {
				continue;
			}

			$eligible += (float) $item->get_subtotal();
		}

		$redeemed = (float) $order->get_meta( self::META_REDEEMED_VALUE );
		$eligible -= $redeemed;

		return max( 0.0, $eligible );
	}

	/**
	 * Points a completed order earns.
	 *
	 * @since  1.0.25
	 * @param  WC_Order $order
	 * @return int
	 */
	public function calculate_earn_points( $order ) {
		$settings          = $this->get_settings();
		$amount            = (float) $settings['amount'];
		$points_per_amount = (int) $settings['points_per_amount'];

		if ( $amount <= 0 || $points_per_amount <= 0 ) {
			return 0;
		}

		$eligible = $this->get_eligible_subtotal( $order );
		if ( $eligible <= 0 ) {
			return 0;
		}

		return (int) floor( $eligible / $amount ) * $points_per_amount;
	}

	// -------------------------------------------------------------------------
	// Redeem calculation
	// -------------------------------------------------------------------------

	/**
	 * Monetary value of a number of points.
	 *
	 * @since  1.0.25
	 * @param  int $points
	 * @return float
	 */
	public function calculate_redeem_value( $points ) {
		$value_per_point = (float) $this->get_setting( 'value_per_point' );
		if ( $value_per_point <= 0 ) {
			return 0.0;
		}
		return round( (int) $points * $value_per_point, wc_get_price_decimals() );
	}

	/**
	 * Maximum points a customer can redeem for the current cart.
	 *
	 * @since  1.0.25
	 * @param  int $user_id
	 * @return int
	 */
	public function max_redeemable_points( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return 0;
		}

		$value_per_point = (float) $this->get_setting( 'value_per_point' );
		if ( $value_per_point <= 0 ) {
			return 0;
		}

		$balance = $this->get_balance( $user_id );
		if ( $balance <= 0 ) {
			return 0;
		}

		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		$subtotal     = (float) WC()->cart->get_subtotal();
		$max_percent  = (float) $this->get_setting( 'max_redeem_percent' );

		if ( $max_percent <= 0 ) {
			return 0;
		}

		if ( $max_percent >= 100 ) {
			$max_by_value = floor( $subtotal / $value_per_point );
		} else {
			$max_by_value = floor( $subtotal * $max_percent / 100 / $value_per_point );
		}

		return (int) min( $balance, max( 0, $max_by_value ) );
	}

	/**
	 * Points the customer currently has selected to redeem (from session).
	 *
	 * @since  1.0.25
	 * @return int
	 */
	public function get_session_redeem_points() {
		if ( ! isset( WC()->session ) ) {
			return 0;
		}
		return (int) WC()->session->get( self::SESSION_KEY, 0 );
	}

	/**
	 * Store the selected redemption points in the session.
	 *
	 * @since  1.0.25
	 * @param int $points
	 */
	public function set_session_redeem_points( $points ) {
		if ( isset( WC()->session ) ) {
			WC()->session->set( self::SESSION_KEY, (int) $points );
		}
	}

	// -------------------------------------------------------------------------
	// WooCommerce order-status hooks
	// -------------------------------------------------------------------------

	/**
	 * Reserve the redeemed points on the order once checkout completes.
	 *
	 * @since  1.0.25
	 * @param int      $order_id
	 * @param array    $posted_data
	 * @param WC_Order $order
	 */
	public function reserve_points( $order_id, $posted_data, $order ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$points = $this->get_session_redeem_points();
		if ( $points <= 0 ) {
			return;
		}

		$order->update_meta_data( self::META_RESERVED, $points );
		$order->update_meta_data( self::META_REDEEMED_VALUE, $this->calculate_redeem_value( $points ) );
		$order->save();

		$this->set_session_redeem_points( 0 );
	}

	/**
	 * On "Completed": award earned points and finalize the redeemed points.
	 *
	 * @since 1.0.25
	 * @param int $order_id
	 */
	public function award_and_finalize( $order_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_customer_id();
		if ( ! $user_id ) {
			return;
		}

		// Award earned points (once only).
		if ( '' === $order->get_meta( self::META_EARNED ) ) {
			$earned = $this->calculate_earn_points( $order );
			$order->update_meta_data( self::META_EARNED, $earned );
			if ( $earned > 0 ) {
				$this->add_points(
					$user_id,
					$earned,
					$order_id,
					'earn',
					sprintf( __( 'Points earned for order #%d', 'dukkan-plugin' ), $order_id )
				);
			}
		}

		// Finalize spent points (once only).
		$reserved = (int) $order->get_meta( self::META_RESERVED );
		if ( $reserved > 0 && '' === $order->get_meta( self::META_SPENT ) ) {
			$this->deduct_points(
				$user_id,
				$reserved,
				$order_id,
				'spend',
				sprintf( __( 'Points redeemed on order #%d', 'dukkan-plugin' ), $order_id )
			);
			$order->update_meta_data( self::META_SPENT, $reserved );
			$order->update_meta_data( self::META_RESERVED, 0 );
		}

		$order->save();
	}

	/**
	 * On "Cancelled"/"Failed": release the reservation if the order never
	 * completed, otherwise reverse the spent points.
	 *
	 * @since 1.0.25
	 * @param int $order_id
	 */
	public function release_or_reverse( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_customer_id();
		if ( ! $user_id ) {
			return;
		}

		$reserved = (int) $order->get_meta( self::META_RESERVED );
		$spent    = (int) $order->get_meta( self::META_SPENT );

		if ( $reserved > 0 ) {
			// Never completed: simply release the reservation.
			$order->update_meta_data( self::META_RESERVED, 0 );
			$order->save();
		} elseif ( $spent > 0 ) {
			// Already completed then cancelled/failed: restore the spent points.
			$this->add_points(
				$user_id,
				$spent,
				$order_id,
				'reversal',
				sprintf( __( 'Points restored for order #%d', 'dukkan-plugin' ), $order_id )
			);
			$order->update_meta_data( self::META_SPENT, 0 );
			$order->save();
		}
	}

	/**
	 * On refund: reverse earned points and restore spent points proportionally.
	 *
	 * @since 1.0.25
	 * @param int $order_id
	 * @param int $refund_id
	 */
	public function reverse_on_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_customer_id();
		if ( ! $user_id ) {
			return;
		}

		$order_total   = (float) $order->get_total();
		$refund_amount = 0.0;

		$refund = wc_get_order( $refund_id );
		if ( $refund ) {
			$refund_amount = (float) $refund->get_amount();
		}

		if ( $order_total <= 0 || $refund_amount <= 0 ) {
			return;
		}

		$ratio = $refund_amount / $order_total;
		if ( $ratio > 1 ) {
			$ratio = 1;
		}

		// Reverse earned points proportionally (once per portion).
		$earned          = (int) $order->get_meta( self::META_EARNED );
		$reversed_earned = (int) $order->get_meta( self::META_REVERSED_EARNED );
		$earn_to_reverse = (int) floor( $earned * $ratio );
		$earn_to_reverse = min( $earn_to_reverse, max( 0, $earned - $reversed_earned ) );

		if ( $earn_to_reverse > 0 ) {
			$this->deduct_points(
				$user_id,
				$earn_to_reverse,
				$order_id,
				'reversal',
				sprintf( __( 'Points reversed for refund on order #%d', 'dukkan-plugin' ), $order_id )
			);
			$order->update_meta_data( self::META_REVERSED_EARNED, $reversed_earned + $earn_to_reverse );
		}

		// Restore spent points proportionally (once per portion).
		$spent          = (int) $order->get_meta( self::META_SPENT );
		$reversed_spent = (int) $order->get_meta( self::META_REVERSED_SPENT );
		$spend_to_restore = (int) floor( $spent * $ratio );
		$spend_to_restore = min( $spend_to_restore, max( 0, $spent - $reversed_spent ) );

		if ( $spend_to_restore > 0 ) {
			$this->add_points(
				$user_id,
				$spend_to_restore,
				$order_id,
				'reversal',
				sprintf( __( 'Points restored for refund on order #%d', 'dukkan-plugin' ), $order_id )
			);
			$order->update_meta_data( self::META_REVERSED_SPENT, $reversed_spent + $spend_to_restore );
		}

		$order->save();
	}
}
