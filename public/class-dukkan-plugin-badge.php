<?php

/**
 * The product-badge public-facing functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.24
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * Renders product badges on the WooCommerce storefront.
 *
 * Badges are text labels (colored, positionable) assigned to all products,
 * specific products, or specific categories. They are output on both the
 * shop/product loop and the single product page.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Badge_Public {

	/**
	 * Option key that holds all product badges.
	 *
	 * @since 1.0.24
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_product_badges';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.24
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.24
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.24
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version           The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'render_badges' ) );
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_badges' ) );
	}

	/**
	 * Register the stylesheets for the public-facing badge area.
	 *
	 * @since 1.0.24
	 */
	public function enqueue_styles() {
		$badge_css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dukkan-plugin-badge.css' );
		wp_enqueue_style( $this->plugin_name . '-product-badge', plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-badge.css', array(), $badge_css_version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing badge area.
	 *
	 * @since 1.0.24
	 */
	public function enqueue_scripts() {
		// No frontend JS required for badges.
	}

	/**
	 * Render badges for the current product.
	 *
	 * Called on both the loop and single-product hooks.
	 *
	 * @since 1.0.24
	 */
	public function render_badges() {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$badges = $this->get_badges_for_product( $product->get_id() );

		if ( empty( $badges ) ) {
			return;
		}

		echo '<div class="dukkan-badges">';

		foreach ( $badges as $badge ) {
			$position = sanitize_html_class( $badge['position'] ?? 'top-left' );
			$shape    = sanitize_html_class( $badge['shape'] ?? 'rectangular' );
			$shape    = 'circle' === $shape ? 'circle' : 'rectangular';
			$bg       = sanitize_hex_color( $badge['background_color'] ?? '#e53935' );
			$fg       = sanitize_hex_color( $badge['text_color'] ?? '#ffffff' );
			$text     = $badge['text'] ?? '';

			if ( '' !== $text && function_exists( 'trp_translate' ) ) {
				$text = trp_translate( $text, null, false );
			}

			$text = wp_kses( $text, array( 'br' => array() ) );

			if ( '' === $text ) {
				continue;
			}

			printf(
				'<span class="dukkan-badge dukkan-badge--%1$s dukkan-badge--%2$s" style="background:%3$s;color:%4$s;">%5$s</span>',
				esc_attr( $position ),
				esc_attr( $shape ),
				esc_attr( $bg ? $bg : '#e53935' ),
				esc_attr( $fg ? $fg : '#ffffff' ),
				$text
			);
		}

		echo '</div>';
	}

	/**
	 * Retrieve all badges.
	 *
	 * @since  1.0.24
	 * @return array
	 */
	public function get_all_badges() {
		$badges = get_option( self::OPTION_KEY, array() );
		return is_array( $badges ) ? $badges : array();
	}

	/**
	 * Retrieve enabled badges that apply to the given product.
	 *
	 * @since  1.0.24
	 * @param  int $product_id
	 * @return array
	 */
	public function get_badges_for_product( $product_id ) {
		$result = array();

		foreach ( $this->get_all_badges() as $badge ) {
			if ( empty( $badge['status'] ) ) {
				continue;
			}

			if ( $this->badge_applies_to_product( $badge, (int) $product_id ) ) {
				$result[] = $badge;
			}
		}

		return $result;
	}

	/**
	 * Determine whether a badge applies to a product.
	 *
	 * @since  1.0.24
	 * @param  array $badge
	 * @param  int   $product_id
	 * @return bool
	 */
	private function badge_applies_to_product( array $badge, int $product_id ) {
		$applied_to = $badge['applied_to'] ?? 'all';

		if ( 'all' === $applied_to ) {
			return true;
		}

		if ( 'specific_products' === $applied_to ) {
			$products = array_map( 'intval', (array) ( $badge['products'] ?? array() ) );
			return in_array( $product_id, $products, true );
		}

		if ( 'specific_categories' === $applied_to ) {
			$categories = array_map( 'intval', (array) ( $badge['categories'] ?? array() ) );
			if ( empty( $categories ) ) {
				return false;
			}
			return has_term( $categories, 'product_cat', $product_id );
		}

		if ( 'specific_tags' === $applied_to ) {
			$tags = array_map( 'intval', (array) ( $badge['tags'] ?? array() ) );
			if ( empty( $tags ) ) {
				return false;
			}
			return has_term( $tags, 'product_tag', $product_id );
		}

		return false;
	}
}
