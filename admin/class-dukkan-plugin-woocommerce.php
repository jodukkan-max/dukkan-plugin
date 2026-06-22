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
	 * Option key for enabling custom order statuses.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ORDER_STATUS_SETTING = 'dukkan_woo_order_status';

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
	 * Register WooCommerce hooks when WooCommerce is active and the feature is enabled.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		if ( ! class_exists( 'WooCommerce' ) || ! $this->is_custom_order_status_enabled() ) {
			return;
		}

		add_action( 'init', array( $this, 'register_custom_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_statuses' ) );
	}

	/**
	 * Register custom WooCommerce order statuses.
	 *
	 * @since 1.0.0
	 */
	public function register_custom_order_statuses() {
		register_post_status(
			'wc-ready-delivery',
			array(
				'label'                     => _x( 'Ready For Delivery', 'Order status', 'dukkan-plugin' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Ready For Delivery (%s)',
					'Ready For Delivery (%s)',
					'dukkan-plugin'
				),
			)
		);

		register_post_status(
			'wc-out-for-delivery',
			array(
				'label'                     => _x( 'Out For Delivery', 'Order status', 'dukkan-plugin' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Out For Delivery (%s)',
					'Out For Delivery (%s)',
					'dukkan-plugin'
				),
			)
		);

		register_post_status(
			'wc-with-carrier',
			array(
				'label'                     => _x( 'With Carrier', 'Order status', 'dukkan-plugin' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'With Carrier (%s)',
					'With Carrier (%s)',
					'dukkan-plugin'
				),
			)
		);
	}

	/**
	 * Add custom statuses to WooCommerce's order status list.
	 *
	 * @since  1.0.0
	 * @param  array $statuses Existing WooCommerce order statuses.
	 * @return array
	 */
	public function add_custom_order_statuses( $statuses ) {
		$statuses['wc-ready-delivery'] = _x( 'Ready For Delivery', 'Order status', 'dukkan-plugin' );
		$statuses['wc-out-for-delivery'] = _x( 'Out For Delivery', 'Order status', 'dukkan-plugin' );
		$statuses['wc-with-carrier'] = _x( 'With Carrier', 'Order status', 'dukkan-plugin' );

		return $statuses;
	}

	/**
	 * Check whether custom order statuses are enabled in Dukkan store settings.
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
