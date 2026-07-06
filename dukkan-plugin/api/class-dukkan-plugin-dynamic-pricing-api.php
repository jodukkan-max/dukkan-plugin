<?php

/**
 * The dynamic-pricing-api functionality of the plugin.
 *
 * Bridges between the app and WCDPD (WooCommerce Dynamic Pricing & Discounts).
 * Rules created via this API appear in WooCommerce > PricePep (WCDPD) dashboard.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.1
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

class Dukkan_Plugin_Dynamic_Pricing_API {

	/**
	 * The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 */
	private $version;

	/**
	 * Pricing methods map (API key → WCDPD internal key).
	 */
	private static $PRICING_METHODS = array(
		'discount__amount',
		'discount__percentage',
		'fee__amount',
		'fee__percentage',
		'fixed__price',
	);

	/**
	 * Valid product/category list methods.
	 */
	private static $LIST_METHODS = array( 'in_list', 'not_in_list' );

	/**
	 * Valid cart condition types and their internal field names.
	 */
	private static $CART_FIELDS = array(
		'cart_subtotal' => 'decimal',
		'cart_quantity' => 'decimal',
		'cart_count'    => 'number',
		'cart_weight'   => 'decimal',
	);

	/**
	 * Valid numeric comparison operators for cart conditions.
	 */
	private static $NUMERIC_METHODS = array(
		'at_least',
		'more_than',
		'not_more_than',
		'less_than',
	);

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	// -------------------------------------------------------------------------
	// Route Registration
	// -------------------------------------------------------------------------

	public function register_routes() {

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/rules', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_rules' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/rules', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_rule' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/rules/(?P<uid>[a-zA-Z0-9_]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_rule' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/rules/(?P<uid>[a-zA-Z0-9_]+)', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_rule' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/rules/(?P<uid>[a-zA-Z0-9_]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( $this, 'delete_rule' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( 'dukkan-dynamic-pricing/v1', '/products/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'search_products' ),
			'permission_callback' => '__return_true',
		));
	}

	// =====================================================================
	// GET /rules  —  List all simple-adjustment rules
	// =====================================================================

	public function get_rules( $request ) {
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );
		$search   = $request->get_param( 'search' );

		$all   = $this->load_simple_rules();
		$total = count( $all );

		if ( $search ) {
			$all = array_values( array_filter( $all, function ( $r ) use ( $search ) {
				$n  = isset( $r['note'] ) ? $r['note'] : '';
				$pn = isset( $r['public_note'] ) ? $r['public_note'] : '';
				return false !== stripos( $n . ' ' . $pn, $search );
			} ) );
			$total = count( $all );
		}

		$page_rules = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );
		$data       = array_map( array( $this, 'to_response' ), $page_rules );
		$pages      = (int) ceil( $total / $per_page );

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $pages );
		return $response;
	}

	// =====================================================================
	// GET /rules/{id}
	// =====================================================================

	public function get_rule( $request ) {
		$uid  = $request->get_param( 'uid' );
		$rule = $this->find_rule( $uid );

		if ( ! $rule ) {
			return new WP_Error( 'rule_not_found', 'Rule not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->to_response( $rule ), 200 );
	}

	// =====================================================================
	// POST /rules  —  Create a new rule
	// =====================================================================

	public function create_rule( $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();

		$pricing_method = sanitize_text_field( $body['pricing_method'] ?? '' );
		$pricing_value  = $body['pricing_value'] ?? null;
		$product_uids   = $body['product_uids'] ?? null;
		$product_method = sanitize_text_field( $body['product_method'] ?? 'in_list' );
		$cat_uids       = $body['product_category_uids'] ?? null;
		$cat_method     = sanitize_text_field( $body['product_category_method'] ?? 'in_list' );
		$conditions     = $body['conditions'] ?? null;
		$note           = sanitize_textarea_field( $body['note'] ?? '' );
		$public_note    = sanitize_textarea_field( $body['public_note'] ?? '' );

		// --- Validate pricing method ---
		if ( ! in_array( $pricing_method, self::$PRICING_METHODS, true ) ) {
			return new WP_Error( 'invalid_pricing_method',
				'pricing_method must be one of: ' . implode( ', ', self::$PRICING_METHODS ),
				array( 'status' => 400 ) );
		}

		// --- Validate pricing value ---
		if ( ! is_numeric( $pricing_value ) || $pricing_value < 0 ) {
			return new WP_Error( 'invalid_pricing_value',
				'pricing_value must be >= 0.',
				array( 'status' => 400 ) );
		}

		// --- At least one product or category required ---
		$has_products  = is_array( $product_uids ) && ! empty( $product_uids );
		$has_cats      = is_array( $cat_uids ) && ! empty( $cat_uids );
		if ( ! $has_products && ! $has_cats ) {
			return new WP_Error( 'missing_filter',
				'product_uids or product_category_uids is required.',
				array( 'status' => 400 ) );
		}

		if ( ! in_array( $product_method, self::$LIST_METHODS, true ) ) {
			return new WP_Error( 'invalid_product_method',
				'product_method must be: ' . implode( ' or ', self::$LIST_METHODS ),
				array( 'status' => 400 ) );
		}
		if ( ! in_array( $cat_method, self::$LIST_METHODS, true ) ) {
			return new WP_Error( 'invalid_category_method',
				'product_category_method must be: ' . implode( ' or ', self::$LIST_METHODS ),
				array( 'status' => 400 ) );
		}

		// --- Build WCDPD rule ---
		$rule = array(
			'uid'            => 'rp_wcdpd_' . md5( uniqid( 'rule', true ) ),
			'exclusivity'    => 'all',
			'note'           => $note,
			'public_note'    => $public_note,
			'method'         => 'simple',
			'pricing_method' => $pricing_method,
			'pricing_value'  => (float) $pricing_value,
			'conditions'     => array(),
		);

		// Product condition
		if ( $has_products ) {
			$validated = array();
			foreach ( $product_uids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product ) {
					return new WP_Error( 'invalid_product',
						"Product ID $pid does not exist.", array( 'status' => 400 ) );
				}
				$validated[] = (string) $pid;
			}
			$rule['conditions'][] = array(
				'uid'           => 'rp_wcdpd_' . md5( uniqid( 'cond', true ) ),
				'type'          => 'product__product',
				'method_option' => $product_method,
				'products'      => $validated,
			);
		}

		// Category condition
		if ( $has_cats ) {
			$validated = array();
			foreach ( $cat_uids as $cid ) {
				$term = get_term( $cid, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'invalid_category',
						"Category ID $cid does not exist.", array( 'status' => 400 ) );
				}
				$validated[] = (string) $cid;
			}
			$rule['conditions'][] = array(
				'uid'                => 'rp_wcdpd_' . md5( uniqid( 'cond', true ) ),
				'type'               => 'product__category',
				'method_option'      => $cat_method,
				'product_categories' => $validated,
			);
		}

		// Cart conditions
		if ( is_array( $conditions ) ) {
			foreach ( $conditions as $i => $c ) {
				$err = $this->validate_cart_condition( $c, $i );
				if ( is_wp_error( $err ) ) {
					return $err;
				}
				$rule['conditions'][] = $this->build_cart_condition( $c );
			}
		}

		$this->append_rule( $rule );

		return new WP_REST_Response( $this->to_response( $rule ), 201 );
	}

	// =====================================================================
	// PUT /rules/{id}  —  Update an existing rule
	// =====================================================================

	public function update_rule( $request ) {
		$uid = $request->get_param( 'uid' );
		$old = $this->find_rule( $uid );
		if ( ! $old ) {
			return new WP_Error( 'rule_not_found', 'Rule not found.', array( 'status' => 404 ) );
		}

		$body = $request->get_json_params() ?: $request->get_body_params();
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_body', 'Request body is required.', array( 'status' => 400 ) );
		}

		// --- Simple scalar fields ---
		if ( isset( $body['pricing_method'] ) ) {
			$pm = sanitize_text_field( $body['pricing_method'] );
			if ( ! in_array( $pm, self::$PRICING_METHODS, true ) ) {
				return new WP_Error( 'invalid_pricing_method',
					'pricing_method must be one of: ' . implode( ', ', self::$PRICING_METHODS ),
					array( 'status' => 400 ) );
			}
			$old['pricing_method'] = $pm;
		}
		if ( isset( $body['pricing_value'] ) ) {
			if ( ! is_numeric( $body['pricing_value'] ) || $body['pricing_value'] < 0 ) {
				return new WP_Error( 'invalid_pricing_value', 'pricing_value must be >= 0.', array( 'status' => 400 ) );
			}
			$old['pricing_value'] = (float) $body['pricing_value'];
		}
		if ( isset( $body['note'] ) ) {
			$old['note'] = sanitize_textarea_field( $body['note'] );
		}
		if ( isset( $body['public_note'] ) ) {
			$old['public_note'] = sanitize_textarea_field( $body['public_note'] );
		}

		$update_products   = isset( $body['product_uids'] );
		$update_categories = isset( $body['product_category_uids'] );
		$update_conditions = isset( $body['conditions'] );

		$new_conditions = array();

		// --- Rebuild product condition ---
		if ( $update_products ) {
			$pids = $body['product_uids'];
			if ( ! is_array( $pids ) || empty( $pids ) ) {
				return new WP_Error( 'invalid_product_uids',
					'product_uids must be a non-empty array.', array( 'status' => 400 ) );
			}
			$pm = isset( $body['product_method'] )
				? sanitize_text_field( $body['product_method'] )
				: null;
			if ( $pm && ! in_array( $pm, self::$LIST_METHODS, true ) ) {
				return new WP_Error( 'invalid_product_method',
					'product_method must be: ' . implode( ' or ', self::$LIST_METHODS ),
					array( 'status' => 400 ) );
			}
			// Fall back to old method if not provided
			if ( ! $pm ) {
				foreach ( $old['conditions'] as $oc ) {
					if ( 'product__product' === ( $oc['type'] ?? '' ) ) {
						$pm = $oc['method_option'];
						break;
					}
				}
				$pm = $pm ?: 'in_list';
			}
			foreach ( $pids as $pid ) {
				if ( ! wc_get_product( $pid ) ) {
					return new WP_Error( 'invalid_product',
						"Product ID $pid does not exist.", array( 'status' => 400 ) );
				}
			}
			$new_conditions[] = array(
				'uid'           => 'rp_wcdpd_' . md5( uniqid( 'cond', true ) ),
				'type'          => 'product__product',
				'method_option' => $pm,
				'products'      => array_map( 'strval', $pids ),
			);
		} else {
			// Keep existing product condition (possibly with new method)
			$new_pm = isset( $body['product_method'] ) ? sanitize_text_field( $body['product_method'] ) : null;
			foreach ( $old['conditions'] as $oc ) {
				if ( 'product__product' === ( $oc['type'] ?? '' ) ) {
					if ( $new_pm ) {
						$oc['method_option'] = $new_pm;
					}
					$new_conditions[] = $oc;
				}
			}
		}

		// --- Rebuild category condition ---
		if ( $update_categories ) {
			$cids = $body['product_category_uids'];
			if ( ! is_array( $cids ) || empty( $cids ) ) {
				return new WP_Error( 'invalid_category_uids',
					'product_category_uids must be a non-empty array.', array( 'status' => 400 ) );
			}
			$cm = isset( $body['product_category_method'] )
				? sanitize_text_field( $body['product_category_method'] )
				: null;
			if ( $cm && ! in_array( $cm, self::$LIST_METHODS, true ) ) {
				return new WP_Error( 'invalid_category_method',
					'product_category_method must be: ' . implode( ' or ', self::$LIST_METHODS ),
					array( 'status' => 400 ) );
			}
			if ( ! $cm ) {
				foreach ( $old['conditions'] as $oc ) {
					if ( 'product__category' === ( $oc['type'] ?? '' ) ) {
						$cm = $oc['method_option'];
						break;
					}
				}
				$cm = $cm ?: 'in_list';
			}
			foreach ( $cids as $cid ) {
				$term = get_term( $cid, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'invalid_category',
						"Category ID $cid does not exist.", array( 'status' => 400 ) );
				}
			}
			$new_conditions[] = array(
				'uid'                => 'rp_wcdpd_' . md5( uniqid( 'cond', true ) ),
				'type'               => 'product__category',
				'method_option'      => $cm,
				'product_categories' => array_map( 'strval', $cids ),
			);
		} else {
			$new_cm = isset( $body['product_category_method'] ) ? sanitize_text_field( $body['product_category_method'] ) : null;
			foreach ( $old['conditions'] as $oc ) {
				if ( 'product__category' === ( $oc['type'] ?? '' ) ) {
					if ( $new_cm ) {
						$oc['method_option'] = $new_cm;
					}
					$new_conditions[] = $oc;
				}
			}
		}

		// --- Cart conditions ---
		if ( $update_conditions ) {
			if ( ! is_array( $body['conditions'] ) ) {
				return new WP_Error( 'invalid_conditions',
					'conditions must be an array.', array( 'status' => 400 ) );
			}
			foreach ( $body['conditions'] as $i => $c ) {
				$err = $this->validate_cart_condition( $c, $i );
				if ( is_wp_error( $err ) ) {
					return $err;
				}
				$new_conditions[] = $this->build_cart_condition( $c );
			}
		} else {
			foreach ( $old['conditions'] as $oc ) {
				$t = $oc['type'] ?? '';
				if ( 'product__product' !== $t && 'product__category' !== $t ) {
					$new_conditions[] = $oc;
				}
			}
		}

		$old['conditions'] = $new_conditions;

		$this->replace_rule( $uid, $old );

		return new WP_REST_Response( $this->to_response( $old ), 200 );
	}

	// =====================================================================
	// DELETE /rules/{id}
	// =====================================================================

	public function delete_rule( $request ) {
		$uid   = $request->get_param( 'uid' );
		$all   = $this->load_all_rules();
		$index = null;

		foreach ( $all as $i => $r ) {
			if ( ( $r['uid'] ?? '' ) === $uid ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index ) {
			return new WP_Error( 'rule_not_found', 'Rule not found.', array( 'status' => 404 ) );
		}

		unset( $all[ $index ] );
		$this->save_all_rules( array_values( $all ) );

		return new WP_REST_Response( array( 'deleted' => true, 'uid' => $uid ), 200 );
	}

	// =====================================================================
	// GET /products/search
	// =====================================================================

	public function search_products( $request ) {
		$search   = $request->get_param( 'search' ) ?: '';
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );

		$args = array(
			'limit'   => $per_page,
			'status'  => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}

		$results = array();
		foreach ( wc_get_products( $args ) as $p ) {
			$results[] = array(
				'id'    => $p->get_id(),
				'name'  => $p->get_name(),
				'sku'   => $p->get_sku(),
				'price' => $p->get_price(),
				'type'  => $p->get_type(),
			);
		}

		return new WP_REST_Response( $results, 200 );
	}

	// =====================================================================
	// RESPONSE FORMATTING
	// =====================================================================

	private function to_response( $rule ) {
		$pids    = array();
		$pmethod = 'in_list';
		$cids    = array();
		$cmethod = 'in_list';
		$conds   = array();

		foreach ( ( $rule['conditions'] ?? array() ) as $c ) {
			$t = $c['type'] ?? '';

			if ( 'product__product' === $t ) {
				$pids    = array_map( 'intval', $c['products'] ?? array() );
				$pmethod = $c['method_option'] ?? 'in_list';
			} elseif ( 'product__category' === $t ) {
				$cids    = array_map( 'intval', $c['product_categories'] ?? array() );
				$cmethod = $c['method_option'] ?? 'in_list';
			} elseif ( isset( self::$CART_FIELDS[ $t ] ) ) {
				$field = self::$CART_FIELDS[ $t ];
				$conds[] = array(
					'type'          => $t,
					'method_option' => $c['method_option'] ?? '',
					'value'         => isset( $c[ $field ] ) ? (float) $c[ $field ] : 0,
				);
			}
		}

		return array(
			'uid'                     => $rule['uid'],
			'note'                    => $rule['note'] ?? '',
			'public_note'             => $rule['public_note'] ?? '',
			'pricing_method'          => $rule['pricing_method'] ?? '',
			'pricing_value'           => (float) ( $rule['pricing_value'] ?? 0 ),
			'product_uids'            => $pids,
			'product_method'          => $pmethod,
			'product_category_uids'   => $cids,
			'product_category_method' => $cmethod,
			'conditions'              => $conds,
		);
	}

	// =====================================================================
	// CART CONDITION HELPERS
	// =====================================================================

	private function validate_cart_condition( $c, $i ) {
		if ( ! isset( $c['type'] ) || ! isset( self::$CART_FIELDS[ $c['type'] ] ) ) {
			return new WP_Error( 'invalid_condition_type',
				'Condition #' . ( $i + 1 ) . ': type must be one of: ' . implode( ', ', array_keys( self::$CART_FIELDS ) ),
				array( 'status' => 400 ) );
		}
		if ( ! isset( $c['method_option'] ) || ! in_array( $c['method_option'], self::$NUMERIC_METHODS, true ) ) {
			return new WP_Error( 'invalid_condition_method',
				'Condition #' . ( $i + 1 ) . ': method_option must be one of: ' . implode( ', ', self::$NUMERIC_METHODS ),
				array( 'status' => 400 ) );
		}
		if ( ! isset( $c['value'] ) || ! is_numeric( $c['value'] ) || $c['value'] < 0 ) {
			return new WP_Error( 'invalid_condition_value',
				'Condition #' . ( $i + 1 ) . ': value must be a positive number.',
				array( 'status' => 400 ) );
		}
		return true;
	}

	private function build_cart_condition( $c ) {
		$field = self::$CART_FIELDS[ $c['type'] ];
		return array(
			'uid'           => 'rp_wcdpd_' . md5( uniqid( 'cond', true ) ),
			'type'          => $c['type'],
			'method_option' => $c['method_option'],
			$field          => (string) $c['value'],
		);
	}

	// =====================================================================
	// WCDPD DATA ACCESS
	// =====================================================================

	/**
	 * WCDPD uses version '1' as the outer key and product_pricing inside it.
	 * So the path is: rp_wcdpd_settings['1']['product_pricing'] = array( ...rules... )
	 */
	private static $WCDPD_VERSION = '1';

	private function load_all_rules() {
		$settings = get_option( 'rp_wcdpd_settings', array() );
		return is_array( $settings[ self::$WCDPD_VERSION ]['product_pricing'] ?? null )
			? $settings[ self::$WCDPD_VERSION ]['product_pricing']
			: array();
	}

	private function load_simple_rules() {
		return array_values( array_filter( $this->load_all_rules(), function ( $r ) {
			return ( $r['method'] ?? '' ) === 'simple';
		} ) );
	}

	private function save_all_rules( $rules ) {
		$settings = get_option( 'rp_wcdpd_settings', array() );
		if ( ! isset( $settings[ self::$WCDPD_VERSION ] ) ) {
			$settings[ self::$WCDPD_VERSION ] = array();
		}
		$settings[ self::$WCDPD_VERSION ]['product_pricing'] = $rules;
		update_option( 'rp_wcdpd_settings', $settings );
		$this->bust_wcdpd_cache();
	}

	private function append_rule( $rule ) {
		$all    = $this->load_all_rules();
		$all[]  = $rule;
		$this->save_all_rules( $all );
	}

	private function replace_rule( $uid, $new_rule ) {
		$all = $this->load_all_rules();
		foreach ( $all as $i => $r ) {
			if ( ( $r['uid'] ?? '' ) === $uid ) {
				$all[ $i ] = $new_rule;
				break;
			}
		}
		$this->save_all_rules( $all );
	}

	private function find_rule( $uid ) {
		foreach ( $this->load_simple_rules() as $r ) {
			if ( ( $r['uid'] ?? '' ) === $uid ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Bump WCDPD settings revision so the admin panel picks up changes.
	 */
	private function bust_wcdpd_cache() {
		if ( class_exists( 'RP_WCDPD_Settings' ) && method_exists( 'RP_WCDPD_Settings', 'reset_settings_revision' ) ) {
			RP_WCDPD_Settings::reset_settings_revision();
		}
	}
}
