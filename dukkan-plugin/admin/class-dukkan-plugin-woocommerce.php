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
 * Registers user-managed custom WooCommerce order statuses.
 *
 * All statuses are read from the `dukkan_custom_order_statuses` option —
 * there are no built-in statuses. Default statuses are seeded on plugin
 * activation by Dukkan_Plugin_Activator.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_WooCommerce {

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
	 * @var      string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
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
	 * Register all user-managed custom WooCommerce order statuses.
	 *
	 * Reads statuses from the `dukkan_custom_order_statuses` option and
	 * registers each via register_post_status() with the `wc-` prefix.
	 *
	 * @since 1.0.0
	 */
	public function register_custom_order_statuses() {
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
	 * Add custom statuses to WooCommerce's order status list.
	 *
	 * @since  1.0.0
	 * @param  array $statuses Existing WooCommerce order statuses.
	 * @return array
	 */
	public function add_custom_order_statuses( $statuses ) {
		$user_statuses = get_option( self::USER_STATUSES_OPTION, array() );
		if ( is_array( $user_statuses ) ) {
			foreach ( $user_statuses as $data ) {
				$slug              = 'wc-' . sanitize_title( $data['slug'] );
				$statuses[ $slug ] = sanitize_text_field( $data['name'] );
			}
		}

		return $statuses;
	}

}
