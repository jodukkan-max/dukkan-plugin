<?php

/**
 * The product-addon-api functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

/**
 * The product-addon-api functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the product-addon-api stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Product_Addon_API {

    /**
     * Namespace for the API.
     */
    const NAMESPACE = 'wc/v3';

    /**
     * Option key for stored product add-on groups.
     *
     * @since 1.0.0
     * @var   string
     */
    const OPTION_KEY = 'wpldp_product_addon_groups';

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('rest_api_init', array($this, 'dukkan_plugin_product_addon_api'));

	}

	/**
	 * Register all REST routes for product add-ons.
	 */
    public function dukkan_plugin_product_addon_api(){

        // GET /product-addons          — list all groups
        register_rest_route( self::NAMESPACE, '/product-addons', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'dukkan_product_addon_get_groups_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dukkan_product_addon_create_group_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
                'args'                => $this->get_group_args(),
            ),
        ) );

        // GET / PUT / DELETE /product-addons/{id} — single group CRUD
        register_rest_route( self::NAMESPACE, '/product-addons/(?P<id>[a-zA-Z0-9_-]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'dukkan_product_addon_get_group_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
                'callback'            => array( $this, 'dukkan_product_addon_update_group_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
                'args'                => $this->get_group_args(),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'dukkan_product_addon_delete_group_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
            ),
        ) );

        // POST /product-addons/{id}/duplicate — duplicate a group
        register_rest_route( self::NAMESPACE, '/product-addons/(?P<id>[a-zA-Z0-9_-]+)/duplicate', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dukkan_product_addon_duplicate_group_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
            ),
        ) );

        // POST /product-addons/{id}/toggle — enable/disable a group
        register_rest_route( self::NAMESPACE, '/product-addons/(?P<id>[a-zA-Z0-9_-]+)/toggle', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dukkan_product_addon_toggle_group_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
                'args'                => array(
                    'status' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'description'       => __( '1 to enable, 0 to disable.', 'dukkan-plugin' ),
                    ),
                ),
            ),
        ) );

        // POST /product-addons/{id}/reorder-fields — reorder the fields of a group
        register_rest_route( self::NAMESPACE, '/product-addons/(?P<id>[a-zA-Z0-9_-]+)/reorder-fields', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dukkan_product_addon_reorder_fields_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
                'args'                => array(
                    'field_order' => array(
                        'required'    => true,
                        'type'        => 'array',
                        'description' => __( 'Ordered list of field IDs.', 'dukkan-plugin' ),
                    ),
                ),
            ),
        ) );

        // DELETE /product-addons/{id}/fields/{field_id} — delete a single field
        register_rest_route( self::NAMESPACE, '/product-addons/(?P<id>[a-zA-Z0-9_-]+)/fields/(?P<field_id>[a-zA-Z0-9_-]+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'dukkan_product_addon_delete_field_api' ),
                'permission_callback' => array( $this, 'check_edit_permissions' ),
            ),
        ) );
    }

    /**
     * Permission callback — requires WooCommerce API read access.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                __( 'Sorry, you cannot view this resource.', 'dukkan-plugin' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

    /**
     * Permission callback for mutations — requires WooCommerce edit access.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_edit_permissions( WP_REST_Request $request ) {
        if ( ! wc_rest_check_manager_permissions( 'settings', 'edit' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_edit',
                __( 'Sorry, you cannot edit this resource.', 'dukkan-plugin' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

    /**
     * Argument schema shared by create/update endpoints.
     *
     * @return array
     */
    private function get_group_args() {
        return array(
            'group_name' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => __( 'Display name of the group.', 'dukkan-plugin' ),
            ),
            'description' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'description'       => __( 'Optional description.', 'dukkan-plugin' ),
            ),
            'applied_to' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => __( '"all" or "specific".', 'dukkan-plugin' ),
            ),
            'status' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => __( '1 to enable, 0 to disable.', 'dukkan-plugin' ),
            ),
        );
    }

    // -------------------------------------------------------------------------
    // Data access helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve all stored groups.
     *
     * @return array
     */
    private function get_groups() {
        $groups = get_option( self::OPTION_KEY, array() );
        return is_array( $groups ) ? $groups : array();
    }

    /**
     * Persist the groups array.
     *
     * @param array $groups
     */
    private function save_groups( $groups ) {
        update_option( self::OPTION_KEY, $groups, 'no' );
    }

    /**
     * Resolve product IDs to {id, name} objects for the response.
     *
     * @param array $group
     * @return array
     */
    private function hydrate_products( $group ) {
        if ( ! empty( $group['products'] ) && is_array( $group['products'] ) ) {
            $hydrated = array();
            foreach ( $group['products'] as $product_id ) {
                if ( is_array( $product_id ) ) {
                    $hydrated[] = $product_id;
                    continue;
                }
                $product = wc_get_product( (int) $product_id );
                if ( $product ) {
                    $hydrated[] = array(
                        'id'   => $product->get_id(),
                        'name' => $product->get_name(),
                    );
                }
            }
            $group['products'] = $hydrated;
        }

        return $group;
    }

    /**
     * Sanitize group-level input, keeping only the keys that are present.
     *
     * @param array $params
     * @return array
     */
    private function sanitize_group_input( $params ) {
        $clean = array();

        if ( isset( $params['group_name'] ) ) {
            $clean['group_name'] = sanitize_text_field( $params['group_name'] );
        }
        if ( isset( $params['description'] ) ) {
            $clean['description'] = sanitize_textarea_field( $params['description'] );
        }
        if ( isset( $params['applied_to'] ) ) {
            $clean['applied_to'] = sanitize_text_field( $params['applied_to'] );
        }
        if ( isset( $params['categories'] ) ) {
            $clean['categories'] = array_map( 'intval', (array) $params['categories'] );
        }
        if ( isset( $params['products'] ) ) {
            $clean['products'] = array_map( 'intval', (array) $params['products'] );
        }
        if ( isset( $params['status'] ) ) {
            $clean['status'] = intval( $params['status'] ) ? 1 : 0;
        }

        return $clean;
    }

    /**
     * Sanitize a single field definition.
     *
     * @param array $field
     * @return array
     */
    private function sanitize_field( $field ) {
        if ( ! is_array( $field ) ) {
            return $field;
        }

        $clean = array();

        if ( isset( $field['id'] ) ) {
            $clean['id'] = sanitize_text_field( $field['id'] );
        }
        $clean['type']     = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
        $clean['title']    = isset( $field['title'] ) ? sanitize_text_field( $field['title'] ) : '';
        $clean['width']    = isset( $field['width'] ) ? sanitize_text_field( $field['width'] ) : '100%';
        $clean['required'] = ! empty( $field['required'] ) ? 1 : 0;
        $clean['price']    = isset( $field['price'] ) ? (float) $field['price'] : 0;

        if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
            $clean['options'] = array();
            foreach ( $field['options'] as $option ) {
                $clean['options'][] = $this->sanitize_option( $option, $clean['type'] );
            }
        }

        return $clean;
    }

    /**
     * Sanitize a single option definition.
     *
     * @param array  $option
     * @param string $field_type
     * @return array
     */
    private function sanitize_option( $option, $field_type ) {
        if ( ! is_array( $option ) ) {
            return $option;
        }

        $clean = array();

        if ( isset( $option['id'] ) ) {
            $clean['id'] = sanitize_text_field( $option['id'] );
        }
        $clean['label'] = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';
        $clean['price'] = isset( $option['price'] ) ? (float) $option['price'] : 0;

        if ( 'color' === $field_type ) {
            $color = isset( $option['color'] ) ? sanitize_hex_color( $option['color'] ) : '';
            $clean['color'] = $color ? $color : '#000000';
            $clean['color_code'] = isset( $option['color_code'] ) ? sanitize_text_field( $option['color_code'] ) : '#000000';
        }

        if ( 'image' === $field_type ) {
            $image_id = isset( $option['image_id'] ) ? intval( $option['image_id'] ) : 0;
            $clean['image_id'] = $image_id;
            $clean['image_url'] = $image_id ? wp_get_attachment_url( $image_id ) : '';
        }

        return $clean;
    }

    /**
     * Build the keyed `fields` array from a list, preserving IDs when they
     * already exist in $available_fields and generating new ones otherwise.
     *
     * @param string $group_id
     * @param array  $fields_list
     * @param array  $available_fields Existing fields keyed by field ID.
     * @return array
     */
    private function build_fields( $group_id, $fields_list, $available_fields ) {
        $result = array();
        $field_counter = 0;

        foreach ( $fields_list as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $field = $this->sanitize_field( $field );

            $field_id = '';
            if ( ! empty( $field['id'] ) && isset( $available_fields[ $field['id'] ] ) ) {
                $field_id = $field['id'];
            } else {
                $field_id = $group_id . '_' . $field_counter . '_' . time();
            }
            $field_counter++;

            // Options are received as a list and stored keyed by option ID.
            $options = array();
            if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                $option_counter = 0;
                foreach ( $field['options'] as $option ) {
                    if ( ! is_array( $option ) ) {
                        continue;
                    }

                    $option = $this->sanitize_option( $option, $field['type'] );

                    $option_id = '';
                    if ( ! empty( $option['id'] ) && isset( $available_fields[ $field_id ]['options'][ $option['id'] ] ) ) {
                        $option_id = $option['id'];
                    } else {
                        $option_id = $field_id . '_option_' . $option_counter . '_' . time();
                    }
                    $option_counter++;

                    $option['id'] = $option_id;
                    $options[ $option_id ] = $option;
                }
            }

            $field['options'] = $options;
            $field['id']      = $field_id;

            $result[ $field_id ] = $field;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /product-addons — list all groups.
     */
    public function dukkan_product_addon_get_groups_api( WP_REST_Request $request ) {
        $groups = $this->get_groups();
        $result = array();

        foreach ( $groups as $group_id => $group ) {
            if ( empty( $group['id'] ) ) {
                $group['id'] = $group_id;
            }
            $result[] = $this->hydrate_products( $group );
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /product-addons/{id} — single group.
     */
    public function dukkan_product_addon_get_group_api( WP_REST_Request $request ) {
        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'no_group', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        $group = $groups[ $group_id ];
        if ( empty( $group['id'] ) ) {
            $group['id'] = $group_id;
        }

        return rest_ensure_response( $this->hydrate_products( $group ) );
    }

    /**
     * DELETE /product-addons/{id} — delete a group.
     */
    public function dukkan_product_addon_delete_group_api( WP_REST_Request $request ) {
        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'no_group', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        unset( $groups[ $group_id ] );
        $this->save_groups( $groups );

        return rest_ensure_response( array(
            'deleted' => true,
            'id'      => $group_id,
        ) );
    }

    /**
     * POST /product-addons — create a group.
     */
    public function dukkan_product_addon_create_group_api( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        // Fallback to form-data / urlencoded if JSON body is empty.
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $group_name = sanitize_text_field( $params['group_name'] ?? '' );

        if ( empty( $group_name ) ) {
            return new WP_Error( 'missing_group_name', __( 'Group name is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $group_id = sanitize_title( $group_name ) . '-' . time();
        $groups   = $this->get_groups();

        $input = $this->sanitize_group_input( $params );

        $new_group = array(
            'id'          => $group_id,
            'group_name'  => $group_name,
            'description' => $input['description'] ?? '',
            'applied_to'  => $input['applied_to'] ?? 'all',
            'categories'  => $input['categories'] ?? array(),
            'products'    => $input['products'] ?? array(),
            'status'      => $input['status'] ?? 1,
            'fields'      => array(),
        );

        if ( ! empty( $params['fields'] ) && is_array( $params['fields'] ) ) {
            $new_group['fields'] = $this->build_fields( $group_id, $params['fields'], array() );
        }

        $groups[ $group_id ] = $new_group;
        $this->save_groups( $groups );

        $response = rest_ensure_response( $this->hydrate_products( $new_group ) );
        $response->set_status( 201 );

        return $response;
    }

    /**
     * POST /product-addons/{id}/duplicate — duplicate a group.
     */
    public function dukkan_product_addon_duplicate_group_api( WP_REST_Request $request ) {
        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'missing_group_id', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        $group = $groups[ $group_id ];

        $new_group_id         = sanitize_title( $group['group_name'] ) . '-copy-' . time();
        $group['id']          = $new_group_id;
        $group['group_name'] .= ' (Copy)';

        // Regenerate nested field/option IDs so the copy is independent.
        if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
            $new_fields    = array();
            $field_counter = 0;

            foreach ( $group['fields'] as $field ) {
                $new_field_id = $new_group_id . '_' . $field_counter . '_' . time();
                $field['id']  = $new_field_id;
                $field_counter++;

                if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                    $new_options    = array();
                    $option_counter = 0;

                    foreach ( $field['options'] as $option ) {
                        $new_option_id = $new_field_id . '_option_' . $option_counter . '_' . time();
                        $option['id']  = $new_option_id;
                        $option_counter++;

                        $new_options[ $new_option_id ] = $option;
                    }

                    $field['options'] = $new_options;
                }

                $new_fields[ $new_field_id ] = $field;
            }

            $group['fields'] = $new_fields;
        }

        $groups[ $new_group_id ] = $group;
        $this->save_groups( $groups );

        $response = rest_ensure_response( $this->hydrate_products( $group ) );
        $response->set_status( 201 );

        return $response;
    }

    /**
     * PUT/PATCH /product-addons/{id} — update a group (merge semantics).
     */
    public function dukkan_product_addon_update_group_api( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        // Fallback to form-data / urlencoded if JSON body is empty.
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'missing_group_id', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        $existing = $groups[ $group_id ];
        $input    = $this->sanitize_group_input( $params );

        if ( isset( $input['group_name'] ) && '' === $input['group_name'] ) {
            return new WP_Error( 'missing_group_name', __( 'Group name is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $merged = array_merge( $existing, $input );

        // Replace fields only when the key is explicitly provided.
        if ( isset( $params['fields'] ) ) {
            $available_fields = ( isset( $existing['fields'] ) && is_array( $existing['fields'] ) ) ? $existing['fields'] : array();

            $merged['fields'] = ( is_array( $params['fields'] ) && ! empty( $params['fields'] ) )
                ? $this->build_fields( $group_id, $params['fields'], $available_fields )
                : array();
        }

        $groups[ $group_id ] = $merged;
        $this->save_groups( $groups );

        return rest_ensure_response( $this->hydrate_products( $merged ) );
    }

    /**
     * POST /product-addons/{id}/toggle — enable/disable a group.
     */
    public function dukkan_product_addon_toggle_group_api( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'missing_group_id', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        $status = isset( $params['status'] ) ? intval( $params['status'] ) : 0;
        $groups[ $group_id ]['status'] = $status ? 1 : 0;

        $this->save_groups( $groups );

        return rest_ensure_response( array(
            'id'     => $group_id,
            'status' => $groups[ $group_id ]['status'],
        ) );
    }

    /**
     * POST /product-addons/{id}/reorder-fields — reorder fields.
     */
    public function dukkan_product_addon_reorder_fields_api( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $group_id = sanitize_text_field( $request['id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'missing_group_id', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $field_order = isset( $params['field_order'] ) ? (array) $params['field_order'] : array();

        if ( empty( $field_order ) ) {
            return new WP_Error( 'missing_field_order', __( 'field_order is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        $current_fields = ( isset( $groups[ $group_id ]['fields'] ) && is_array( $groups[ $group_id ]['fields'] ) )
            ? $groups[ $group_id ]['fields']
            : array();

        $ordered = array();

        foreach ( $field_order as $field_id ) {
            $field_id = sanitize_text_field( $field_id );
            if ( isset( $current_fields[ $field_id ] ) ) {
                $ordered[ $field_id ] = $current_fields[ $field_id ];
            }
        }

        // Append any fields not present in the posted order.
        foreach ( $current_fields as $field_id => $field_data ) {
            if ( ! isset( $ordered[ $field_id ] ) ) {
                $ordered[ $field_id ] = $field_data;
            }
        }

        $groups[ $group_id ]['fields'] = $ordered;
        $this->save_groups( $groups );

        return rest_ensure_response( $this->hydrate_products( $groups[ $group_id ] ) );
    }

    /**
     * DELETE /product-addons/{id}/fields/{field_id} — delete a single field.
     */
    public function dukkan_product_addon_delete_field_api( WP_REST_Request $request ) {
        $group_id = sanitize_text_field( $request['id'] );
        $field_id = sanitize_text_field( $request['field_id'] );

        if ( empty( $group_id ) ) {
            return new WP_Error( 'missing_group_id', __( 'Group ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        if ( empty( $field_id ) ) {
            return new WP_Error( 'missing_field_id', __( 'Field ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
        }

        $groups = $this->get_groups();

        if ( ! isset( $groups[ $group_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        if ( empty( $groups[ $group_id ]['fields'] ) || ! isset( $groups[ $group_id ]['fields'][ $field_id ] ) ) {
            return new WP_Error( 'field_not_found', __( 'Field not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
        }

        unset( $groups[ $group_id ]['fields'][ $field_id ] );
        $this->save_groups( $groups );

        return rest_ensure_response( array(
            'deleted'  => true,
            'group_id' => $group_id,
            'field_id' => $field_id,
        ) );
    }
}
