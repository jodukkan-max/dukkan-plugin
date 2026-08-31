<?php

/**
 * The product-badge-specific functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.24
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * The product-badge-specific functionality of the plugin.
 *
 * Defines the admin UI for managing product badges, plus the AJAX handlers
 * that power the create / update / delete / toggle / search operations.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Badge {

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

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_badge_settings_tab' ) );
		add_action( 'dukkan_settings_tab_content_badges', array( $this, 'render_tab_content' ) );

		add_action( 'wp_ajax_wpldp_badge_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_wpldp_badge_get', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_wpldp_badge_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_wpldp_badge_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_wpldp_badge_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_wpldp_badge_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_wpldp_badge_search_categories', array( $this, 'ajax_search_categories' ) );
		add_action( 'wp_ajax_wpldp_badge_search_tags', array( $this, 'ajax_search_tags' ) );
	}

	/**
	 * Register the stylesheets for the admin badge area.
	 *
	 * @since 1.0.24
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		$badge_css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dp-badge.css' );
		wp_enqueue_style( $this->plugin_name . '-product-badge', plugin_dir_url( __FILE__ ) . 'css/dp-badge.css', array(), $badge_css_version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin badge area.
	 *
	 * @since 1.0.24
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		$badge_js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/dp-badge.js' );
		wp_enqueue_script( $this->plugin_name . '-product-badge', plugin_dir_url( __FILE__ ) . 'js/dp-badge.js', array( 'jquery', 'wp-color-picker', $this->plugin_name ), $badge_js_version, false );

		wp_localize_script( $this->plugin_name . '-product-badge', 'wpldp_badge_ajax', array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpldp_nonce' ),
		) );
	}

	/**
	 * Add the Product Badges tab to the Dukkan settings page.
	 *
	 * @since  1.0.24
	 * @param  array $tabs Existing tabs.
	 * @return array
	 */
	public function add_badge_settings_tab( $tabs ) {
		$tabs['badges'] = array(
			'title' => __( 'Product Badges', 'dukkan-plugin' ),
			'icon'  => 'dashicons-tag',
		);
		return $tabs;
	}

	/**
	 * Render the Product Badges tab content.
	 *
	 * @since 1.0.24
	 */
	public function render_tab_content() {
		$badges = $this->get_all_badges();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-badges-settings.php';
	}

	// -------------------------------------------------------------------------
	// Data access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all stored badges, keyed by badge id.
	 *
	 * @since  1.0.24
	 * @return array
	 */
	public function get_all_badges() {
		$badges = get_option( self::OPTION_KEY, array() );
		return is_array( $badges ) ? $badges : array();
	}

	/**
	 * Persist the badges array.
	 *
	 * @since  1.0.24
	 * @param array $badges
	 */
	private function save_badges( $badges ) {
		update_option( self::OPTION_KEY, $badges, 'no' );
	}

	/**
	 * Pre-register a badge's text lines into TranslatePress's dictionary so they
	 * appear in its String Translation list without needing a frontend visit.
	 *
	 * Each line (split on <br>) is registered separately, since TranslatePress
	 * translates text nodes individually when rendering HTML.
	 *
	 * @since  1.0.24
	 * @param  string $text The raw badge text (may contain <br>).
	 */
	private function register_translatable_string( $text ) {
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return;
		}

		$parts = preg_split( '/<br\s*\/?>/i', (string) $text );
		$strings = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' !== $part ) {
				$strings[] = $part;
			}
		}

		if ( empty( $strings ) ) {
			return;
		}

		$settings    = get_option( 'trp_settings', array() );
		$source_lang = isset( $settings['default-language'] ) ? $settings['default-language'] : 'en_US';

		$trp       = TRP_Translate_Press::get_trp_instance();
		$trp_query = $trp->get_component( 'query' );

		if ( $trp_query && method_exists( $trp_query, 'insert_strings' ) ) {
			$trp_query->insert_strings( $strings, $source_lang );
		}
	}

	/**
	 * Sanitize a badge payload, keeping only recognized keys.
	 *
	 * @since  1.0.24
	 * @param  array $data Raw posted badge data.
	 * @return array
	 */
	private function sanitize_badge( $data ) {
		$clean = array();

		if ( isset( $data['text'] ) ) {
			$clean['text'] = trim( wp_kses( wp_unslash( $data['text'] ), array( 'br' => array() ) ) );
		}
		if ( isset( $data['shape'] ) ) {
			$shape = sanitize_text_field( wp_unslash( $data['shape'] ) );
			$clean['shape'] = in_array( $shape, array( 'rectangular', 'circle' ), true ) ? $shape : 'rectangular';
		}
		if ( isset( $data['background_color'] ) ) {
			$clean['background_color'] = sanitize_hex_color( wp_unslash( $data['background_color'] ) );
			if ( ! $clean['background_color'] ) {
				$clean['background_color'] = '#e53935';
			}
		}
		if ( isset( $data['text_color'] ) ) {
			$clean['text_color'] = sanitize_hex_color( wp_unslash( $data['text_color'] ) );
			if ( ! $clean['text_color'] ) {
				$clean['text_color'] = '#ffffff';
			}
		}
		if ( isset( $data['position'] ) ) {
			$position = sanitize_text_field( wp_unslash( $data['position'] ) );
			$clean['position'] = in_array( $position, array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? $position : 'top-left';
		}
		if ( isset( $data['applied_to'] ) ) {
			$applied_to = sanitize_text_field( wp_unslash( $data['applied_to'] ) );
			$clean['applied_to'] = in_array( $applied_to, array( 'all', 'specific_products', 'specific_categories', 'specific_tags' ), true ) ? $applied_to : 'all';
		}
		if ( isset( $data['products'] ) ) {
			$clean['products'] = array_values( array_filter( array_map( 'intval', (array) $data['products'] ) ) );
		}
		if ( isset( $data['categories'] ) ) {
			$clean['categories'] = array_values( array_filter( array_map( 'intval', (array) $data['categories'] ) ) );
		}
		if ( isset( $data['tags'] ) ) {
			$clean['tags'] = array_values( array_filter( array_map( 'intval', (array) $data['tags'] ) ) );
		}
		if ( isset( $data['status'] ) ) {
			$clean['status'] = intval( $data['status'] ) ? 1 : 0;
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Verify the AJAX nonce and capability.
	 *
	 * @since 1.0.24
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * AJAX: list all badges.
	 *
	 * @since 1.0.24
	 */
	public function ajax_list() {
		$this->verify_ajax();
		wp_send_json_success( array_values( $this->get_all_badges() ) );
	}

	/**
	 * AJAX: get a single badge, with products/categories hydrated to
	 * `{ id, name }` objects so the editor can render readable tags.
	 *
	 * @since 1.0.24
	 */
	public function ajax_get() {
		$this->verify_ajax();

		$badge_id = isset( $_POST['badge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['badge_id'] ) ) : '';
		if ( empty( $badge_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$badges = $this->get_all_badges();
		if ( ! isset( $badges[ $badge_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge not found.', 'dukkan-plugin' ) ), 404 );
		}

		wp_send_json_success( $this->hydrate_badge( $badges[ $badge_id ] ) );
	}

	/**
	 * Replace stored product/category IDs with `{ id, name }` objects.
	 *
	 * @since  1.0.24
	 * @param  array $badge
	 * @return array
	 */
	private function hydrate_badge( $badge ) {
		if ( ! empty( $badge['products'] ) && is_array( $badge['products'] ) ) {
			$products = array();
			foreach ( $badge['products'] as $product_id ) {
				$product = wc_get_product( (int) $product_id );
				if ( $product ) {
					$products[] = array(
						'id'   => $product->get_id(),
						'name' => $product->get_name(),
					);
				}
			}
			$badge['products'] = $products;
		}

		if ( ! empty( $badge['categories'] ) && is_array( $badge['categories'] ) ) {
			$categories = array();
			foreach ( $badge['categories'] as $term_id ) {
				$term = get_term( (int) $term_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$categories[] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
					);
				}
			}
			$badge['categories'] = $categories;
		}

		if ( ! empty( $badge['tags'] ) && is_array( $badge['tags'] ) ) {
			$tags = array();
			foreach ( $badge['tags'] as $term_id ) {
				$term = get_term( (int) $term_id, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tags[] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
					);
				}
			}
			$badge['tags'] = $tags;
		}

		return $badge;
	}

	/**
	 * AJAX: create or update a badge.
	 *
	 * @since 1.0.24
	 */
	public function ajax_save() {
		$this->verify_ajax();

		$badge_id = isset( $_POST['badge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['badge_id'] ) ) : '';
		$data     = $this->sanitize_badge( $_POST );

		if ( empty( $data['text'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge text is required.', 'dukkan-plugin' ) ), 400 );
		}

		$badges = $this->get_all_badges();

		if ( $badge_id && isset( $badges[ $badge_id ] ) ) {
			$existing = $badges[ $badge_id ];
			$badge    = array_merge( $existing, $data );
			$badge['id'] = $badge_id;
		} else {
			$badge_id = sanitize_title( $data['text'] ) . '-' . time();
			$badge    = array_merge( array(
				'id'               => $badge_id,
				'text'             => '',
				'shape'            => 'rectangular',
				'background_color' => '#e53935',
				'text_color'       => '#ffffff',
				'position'         => 'top-left',
				'applied_to'       => 'all',
				'products'         => array(),
				'categories'       => array(),
				'tags'             => array(),
				'status'           => 1,
			), $data );
			$badge['id'] = $badge_id;
		}

		$badges[ $badge_id ] = $badge;
		$this->save_badges( $badges );

		$this->register_translatable_string( $badge['text'] );

		wp_send_json_success( $badge );
	}

	/**
	 * AJAX: delete a badge.
	 *
	 * @since 1.0.24
	 */
	public function ajax_delete() {
		$this->verify_ajax();

		$badge_id = isset( $_POST['badge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['badge_id'] ) ) : '';
		if ( empty( $badge_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$badges = $this->get_all_badges();
		if ( ! isset( $badges[ $badge_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge not found.', 'dukkan-plugin' ) ), 404 );
		}

		unset( $badges[ $badge_id ] );
		$this->save_badges( $badges );

		wp_send_json_success( array( 'deleted' => true, 'badge_id' => $badge_id ) );
	}

	/**
	 * AJAX: toggle a badge's enabled/disabled state.
	 *
	 * @since 1.0.24
	 */
	public function ajax_toggle() {
		$this->verify_ajax();

		$badge_id = isset( $_POST['badge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['badge_id'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;

		if ( empty( $badge_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$badges = $this->get_all_badges();
		if ( ! isset( $badges[ $badge_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Badge not found.', 'dukkan-plugin' ) ), 404 );
		}

		$badges[ $badge_id ]['status'] = $status ? 1 : 0;
		$this->save_badges( $badges );

		wp_send_json_success( array( 'badge_id' => $badge_id, 'status' => $badges[ $badge_id ]['status'] ) );
	}

	/**
	 * AJAX: search products for the combo widget.
	 *
	 * @since 1.0.24
	 */
	public function ajax_search_products() {
		$this->verify_ajax();

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$products = wc_get_products( array(
			'limit'  => 20,
			'status' => 'publish',
			's'      => $search,
		) );

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: search categories for the combo widget.
	 *
	 * @since 1.0.24
	 */
	public function ajax_search_categories() {
		$this->verify_ajax();

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'search'     => $search,
			'number'     => 20,
		) );

		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: search product tags for the combo widget.
	 *
	 * @since 1.0.24
	 */
	public function ajax_search_tags() {
		$this->verify_ajax();

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$terms = get_terms( array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'search'     => $search,
			'number'     => 20,
		) );

		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
				);
			}
		}

		wp_send_json_success( $results );
	}
}
