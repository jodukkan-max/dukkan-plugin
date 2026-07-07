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

    public function dukkan_plugin_product_addon_api(){
        register_rest_route(self::NAMESPACE, '/get_groups/', array(
                array(
                    'methods'             => WP_REST_Server::READABLE, // GET
                    'callback'            => array( $this, 'dukkan_product_addon_get_groups_api' ),
                    'permission_callback' => array( $this, 'check_permissions' ),
                ),
            ));
        register_rest_route(self::NAMESPACE, '/get_group/', array(
                array(
                    'methods'             => WP_REST_Server::READABLE, // GET
                    'callback'            => array( $this, 'dukkan_product_addon_get_group_api' ),
                    'permission_callback' => array( $this, 'check_permissions' ),
                ),
            ));
        register_rest_route(self::NAMESPACE, '/delete_group/', array(
                array(
                    'methods'             => WP_REST_Server::DELETABLE, // DELETE
                    'callback'            => array( $this, 'dukkan_product_addon_delete_group_api' ),
                    'permission_callback' => array( $this, 'check_permissions' ),
                ),
            ));
        register_rest_route(self::NAMESPACE, '/create_group/', array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE, // POST
                    'callback'            => array( $this, 'dukkan_product_addon_create_group_api' ),
                    'permission_callback' => array( $this, 'check_permissions' ),
                ),
            ));
        register_rest_route(self::NAMESPACE, '/duplicate_group/', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => array( $this, 'dukkan_product_addon_duplicate_group_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
        ));
        register_rest_route(self::NAMESPACE, '/update_group/', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
                'callback'            => array( $this, 'dukkan_product_addon_update_group_api' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            ),
        ));
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
                __( 'Sorry, you cannot view this resource.', 'your-plugin' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

    public function dukkan_product_addon_get_groups_api(WP_REST_Request $request) {

        $groups = get_option('wpldp_product_addon_groups', []);

        if(!empty($groups)){
            foreach($groups as $group_id => $group){
                if (!empty($group['products'])) {

                    foreach ($group['products'] as $index => $product_id) {

                        $product = wc_get_product($product_id);

                        if ($product) {
                            $groups[$group_id]['products'][$index] = [
                                'id'   => $product->get_id(),
                                'name' => $product->get_name()
                            ];
                        } else {
                            unset($groups[$group_id]['products'][$index]);
                        }
                    }

                    // Reset array keys
                    $groups[$group_id]['products'] = array_values($groups[$group_id]['products']);
                }
            }
        }
        

        return rest_ensure_response($groups);
    }

    public function dukkan_product_addon_get_group_api(WP_REST_Request $request) {
        $group_id = sanitize_text_field($request['group_id']);

        if (empty($group_id)) {
            return new WP_Error('no_group', 'Group ID is required', ['status' => 400]);
        }

        $groups = get_option('wpldp_product_addon_groups', []);

        if (!isset($groups[$group_id])) {
            return new WP_Error('not_found', 'Group not found', ['status' => 404]);
        }

        if (!empty($groups[$group_id]['products'])) {

            foreach ($groups[$group_id]['products'] as $index => $product_id) {

                $product = wc_get_product($product_id);

                if ($product) {
                    $groups[$group_id]['products'][$index] = [
                        'id'   => $product->get_id(),
                        'name' => $product->get_name()
                    ];
                } else {
                    unset($groups[$group_id]['products'][$index]);
                }
            }

            // Reset array keys
            $groups[$group_id]['products'] = array_values($groups[$group_id]['products']);
        }

        return rest_ensure_response($groups[$group_id]);
    }

    public function dukkan_product_addon_delete_group_api(WP_REST_Request $request){
        $group_id = sanitize_text_field($request['group_id']);

        if (empty($group_id)) {
            return new WP_Error('no_group', 'Group ID is required', ['status' => 400]);
        }

        $groups = get_option('wpldp_product_addon_groups', []);

        if (!isset($groups[$group_id])) {
            return new WP_Error('not_found', 'Group not found', ['status' => 404]);
        }

        // Store deleted group data to return in response
        $deleted_group = $groups[$group_id];

        // Remove the group
        unset($groups[$group_id]);

        // Save updated groups back to options
        $updated = update_option('wpldp_product_addon_groups', $groups);

        if (!$updated) {
            return new WP_Error('delete_failed', 'Failed to delete group', ['status' => 500]);
        }

        return rest_ensure_response([
            'success'  => true,
            'message'  => 'Group deleted successfully',
            'group_id' => $group_id,
            'deleted'  => $deleted_group,
        ]);
    }

    public function dukkan_product_addon_create_group_api(WP_REST_Request $request) {
        $product_addon = $request->get_json_params();

        // Fallback to form-data / urlencoded if JSON body is empty
        if (empty($product_addon)) {
            $product_addon = $request->get_body_params();
        }

        $group_name  = sanitize_text_field($product_addon['group_name'] ?? '');
        $description = sanitize_textarea_field($product_addon['description'] ?? '');
        $applied_to  = sanitize_text_field($product_addon['applied_to'] ?? 'all');
        $categories  = array_map('intval', $product_addon['categories'] ?? []);
        $products    = array_map('intval', $product_addon['products'] ?? []);

        $fields    = isset($product_addon['fields'])    ? $product_addon['fields'] : [];

        if (empty($group_name)) {
            return new WP_Error('missing_group_name', 'Group name is required', ['status' => 400]);
        }

        $group_id = sanitize_title($group_name) . '-' . time();
        $groups   = get_option('wpldp_product_addon_groups', []);

        $new_group = [
            'id'          => $group_id,
            'group_name'  => $group_name,
            'description' => $description,
            'applied_to'  => $applied_to,
            'categories'  => $categories,
            'products'    => $products,
            'status'      => 1,
        ];

        $groups[$group_id] = $new_group;

        $groups[$group_id]['fields'] = array();

        if(!empty($fields)){
            $field_counter = 0;
            foreach($fields as $field){
                $field_id = $group_id.'_'.$field_counter.'_'.time();

                $field['id'] = $field_id;
                $field['required'] = intval($field['required']);
                $field_counter++;

                if(isset($field['options']) && !empty($field['options'])){
                    $field_options = $field['options'];
                    $field['options'] = array();
                    $option_counter = 0;
                    foreach($field_options as $option){
                        $option_id = $field_id.'_option_'.$option_counter.'_'.time();

                        $option['id'] = $option_id;
                        $option_counter++;

                        $field['options'][$option_id] = $option;
                    }
                }

                $groups[$group_id]['fields'][$field_id] = $field;
            }
        }

        $updated = update_option('wpldp_product_addon_groups', $groups);

        if (!$updated) {
            return new WP_Error('create_failed', 'Failed to create group', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Group created successfully',
            'data'    => $groups[$group_id],
        ]);
    }

    public function dukkan_product_addon_duplicate_group_api(WP_REST_Request $request) {
        $params   = $request->get_json_params();

        // Fallback to form-data / urlencoded if JSON body is empty
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $group_id = sanitize_text_field($params['group_id'] ?? '');

        if (empty($group_id)) {
            return new WP_Error('missing_group_id', 'Group ID is required', ['status' => 400]);
        }

        $groups = get_option('wpldp_product_addon_groups', []);

        if (!isset($groups[$group_id])) {
            return new WP_Error('not_found', 'Group not found', ['status' => 404]);
        }

        $group = $groups[$group_id];

        $new_group_id             = sanitize_title($group['group_name']) . '-copy-' . time();
        $group['id']              = $new_group_id;
        $group['group_name']     .= ' (Copy)';

        $groups[$new_group_id] = $group;

        $updated = update_option('wpldp_product_addon_groups', $groups);

        if (!$updated) {
            return new WP_Error('duplicate_failed', 'Failed to duplicate group', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Group duplicated successfully',
            'data'    => [
                'id'         => $new_group_id,
                'group'      => $groups[$new_group_id]
            ],
        ]);
    }

    public function dukkan_product_addon_update_group_api(WP_REST_Request $request) {
        $params = $request->get_json_params();

        // Fallback to form-data / urlencoded if JSON body is empty
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $group_id = sanitize_text_field($params['group_id'] ?? '');

        if (empty($group_id)) {
            return new WP_Error('missing_group_id', 'Group ID is required', ['status' => 400]);
        }

        $groups = get_option('wpldp_product_addon_groups', []);

        if (!isset($groups[$group_id])) {
            return new WP_Error('not_found', 'Group not found', ['status' => 404]);
        }

        $existing_group = $groups[$group_id];

        // Only update fields that are provided in the request
        $group_name  = isset($params['group_name'])  ? sanitize_text_field($params['group_name'])     : $existing_group['group_name'];
        $description = isset($params['description']) ? sanitize_textarea_field($params['description']) : '';
        $applied_to  = isset($params['applied_to'])  ? sanitize_text_field($params['applied_to'])     : $existing_group['applied_to'];
        $categories  = isset($params['categories'])  ? array_map('intval', $params['categories'])     : [];
        $products    = isset($params['products'])    ? array_map('intval', $params['products'])       : [];
        $status      = isset($params['status'])      ? intval($params['status'])                      : 1;

        $fields    = isset($params['fields'])    ? $params['fields'] : [];

        if (empty($group_name)) {
            return new WP_Error('missing_group_name', 'Group name is required', ['status' => 400]);
        }

        $updated_group = [
            //'id'          => $group_id,
            'group_name'  => $group_name,
            'description' => $description,
            'applied_to'  => $applied_to,
            'categories'  => $categories,
            'products'    => $products,
            'status'      => $status,
        ];

        $groups[$group_id] = array_merge($groups[$group_id], $updated_group);
        $available_fields = $groups[$group_id]['fields']??array();

        $groups[$group_id]['fields'] = array();

        if(!empty($fields)){
            $field_counter = 0;
            foreach($fields as $field){
                $field_id = '';
                if(isset($field['id']) && $field['id'] != '' && isset($available_fields[$field['id']])){
                    $field_id = $field['id'];
                }
                else{
                    $field_id = $group_id.'_'.$field_counter.'_'.time();
                }

                $field['id'] = $field_id;
                $field['required'] = intval($field['required']);
                $field_counter++;

                if(isset($field['options']) && !empty($field['options'])){
                    $field_options = $field['options'];
                    $field['options'] = array();
                    $option_counter = 0;
                    foreach($field_options as $option){
                        $option_id = '';
                        if(isset($option['id']) && $option['id'] != '' && isset($available_fields[$field['id']]['options'][$option['id']])){
                            $option_id = $option['id'];
                        }
                        else{
                            $option_id = $field_id.'_option_'.$option_counter.'_'.time();
                        }

                        $option['id'] = $option_id;
                        $option_counter++;

                        $field['options'][$option_id] = $option;
                    }
                }

                $groups[$group_id]['fields'][$field_id] = $field;
            }
        }

        $updated = update_option('wpldp_product_addon_groups', $groups);

        if (!$updated) {
            return new WP_Error('update_failed', 'Failed to update group or no changes were made', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Group updated successfully',
            'data'    => $groups[$group_id]
        ]);
    }
}