<?php

/**
 * WooCommerce-related admin functionality.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Manages Dukkan WooCommerce extensions.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_WooCommerce {

	/**
	 * Option key for enabling built-in custom order statuses.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ORDER_STATUS_SETTING = 'dukkan_woo_order_status';

	/**
	 * Option key that holds user-managed custom order statuses.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const USER_STATUSES_OPTION = 'dukkan_custom_order_statuses';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name The name of this plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'plugins_loaded', array( $this, 'register_hooks' ) );

	}

	/**
	 * Register WooCommerce hooks when WooCommerce is active.
	 *
	 * User-managed statuses are always registered regardless of the
	 * built-in status toggle. The built-in toggle only gates the three
	 * hardcoded statuses below.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_custom_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_statuses' ) );
	}

	/**
	 * Register custom WooCommerce order statuses.
	 *
	 * Registers both the built-in statuses (gated by the store-settings
	 * toggle) and all user-managed statuses from the Dukkan Order Status tab.
	 *
	 * @since 1.0.0
	 */
	public function register_custom_order_statuses() {
		// Built-in statuses (only when toggle is enabled).
		if ( $this->is_custom_order_status_enabled() ) {
			$this->register_builtin_statuses();
		}

		// User-managed statuses from the Dukkan Order Status UI / API.
		$this->register_user_managed_statuses();
	}

	/**
	 * Add custom statuses to WooCommerce's order status list.
	 *
	 * @since  1.0.0
	 * @param  array $statuses Existing WooCommerce order statuses.
	 * @return array
	 */
	public function add_custom_order_statuses( $statuses ) {
		// Built-in statuses.
		if ( $this->is_custom_order_status_enabled() ) {
			$statuses['wc-ready-delivery']   = _x( 'Ready For Delivery', 'Order status', 'dukkan-plugin' );
			$statuses['wc-out-for-delivery'] = _x( 'Out For Delivery', 'Order status', 'dukkan-plugin' );
			$statuses['wc-with-carrier']     = _x( 'With Carrier', 'Order status', 'dukkan-plugin' );
		}

		// User-managed statuses.
		$user_statuses = get_option( self::USER_STATUSES_OPTION, array() );
		if ( is_array( $user_statuses ) ) {
			foreach ( $user_statuses as $data ) {
				$slug                 = 'wc-' . sanitize_title( $data['slug'] );
				$statuses[ $slug ]    = sanitize_text_field( $data['name'] );
			}
		}

		return $statuses;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Register the three built-in Dukkan order statuses.
	 *
	 * @since 1.0.0
	 */
	private function register_builtin_statuses() {
		$builtins = array(
			'ready-delivery'    => _x( 'Ready For Delivery', 'Order status', 'dukkan-plugin' ),
			'out-for-delivery'  => _x( 'Out For Delivery', 'Order status', 'dukkan-plugin' ),
			'with-carrier'      => _x( 'With Carrier', 'Order status', 'dukkan-plugin' ),
		);

		foreach ( $builtins as $slug_part => $label ) {
			$key = 'wc-' . $slug_part;
			register_post_status( $key, array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: order count */
				'label_count'               => _n_noop(
					$label . ' (%s)',
					$label . ' (%s)',
					'dukkan-plugin'
				),
			) );
		}
	}

	/**
	 * Register all user-managed custom order statuses from the option.
	 *
	 * @since 1.0.0
	 */
	private function register_user_managed_statuses() {
		$user_statuses = get_option( self::USER_STATUSES_OPTION, array() );
		if ( ! is_array( $user_statuses ) ) {
			return;
		}

		foreach ( $user_statuses as $data ) {
			$slug  = 'wc-' . sanitize_title( $data['slug'] );
			$label = sanitize_text_field( $data['name'] );

			register_post_status( $slug, array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: order count */
				'label_count'               => _n_noop(
					$label . ' (%s)',
					$label . ' (%s)',
					'dukkan-plugin'
				),
			) );
		}
	}

	/**
	 * Check whether built-in custom order statuses are enabled in Dukkan store settings.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_custom_order_status_enabled() {
		$settings = get_option( Dukkan_Plugin_Store_Settings::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		return isset( $settings[ self::ORDER_STATUS_SETTING ] ) && 'yes' === $settings[ self::ORDER_STATUS_SETTING ];
	}

}
