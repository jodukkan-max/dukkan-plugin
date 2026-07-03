<?php

/**
 * The woocommerce api extended functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api/woo-extended
 */

/**
 * The woocommerce api extended functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the woocommerce webhook stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api/woo-extended
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Woo_Extended_API {

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
     * The option name for the store connection auth code.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $store_connection_auth_code    The option name for the store connection auth code.
     */
    private $store_connection_auth_code_option_name = 'dukkan_plugin_store_connection_auth_code';

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
		add_action('before_delete_post', array($this, 'dukkan_plugin_delete_product_with_images'), 10, 1);
		add_action('rest_api_init', array($this, 'dukkan_plugin_woo_extended_api'));
	}

    /**
     * Register WooCommerce extended REST API routes.
     */
    public function dukkan_plugin_woo_extended_api()
    {
        register_rest_route('dukkan-woo-extended/v1', '/request-store-connection-auth-code', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'dukkan_plugin_generate_store_connection_auth_code'),
            'permission_callback' => array($this, 'dukkan_plugin_static_key_permission_callback')
        ));

        register_rest_route('dukkan-woo-extended/v1', '/rest-api-keys', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'dukkan_plugin_generate_woo_rest_api_keys'),
            'permission_callback' => array($this, 'dukkan_plugin_auth_code_permission_callback'),
            'args'                => array(
                'user_id'     => array(
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ),
                'description' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'permissions' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }

    /**
     * Authenticate the key generation endpoint with a store connection auth code.
     */
    public function dukkan_plugin_auth_code_permission_callback(WP_REST_Request $request)
    {
        $generated_auth_code = $this->dukkan_plugin_get_store_connection_auth_code();
        $request_auth_code = trim((string) $request->get_header('x-dukkan-auth-code'));

        if (empty($request_auth_code)) {
            $request_auth_code = trim((string) $request->get_param('auth_code'));
        }

        if (empty($generated_auth_code) || empty($request_auth_code) || !hash_equals($generated_auth_code, $request_auth_code)) {
            return new WP_Error('dukkan_woo_extended_unauthorized', 'Invalid or missing store connection auth code.', array('status' => 401));
        }

        // Delete the auth code after successful authentication.
        delete_option($this->store_connection_auth_code_option_name);

        return true;
    }

    private function dukkan_plugin_get_store_connection_auth_code()
    {
        return get_option($this->store_connection_auth_code_option_name);
    }

    /**
     * Authenticate the key generation endpoint with a static API key.
     */
    public function dukkan_plugin_static_key_permission_callback(WP_REST_Request $request)
    {
        $configured_key = trim((string) $this->dukkan_plugin_get_static_api_key());
        $request_key = trim((string) $request->get_header('x-dukkan-api-key'));

        if (empty($request_key)) {
            $request_key = trim((string) $request->get_param('api_key'));
        }

        if (empty($configured_key) || empty($request_key) || !hash_equals($configured_key, $request_key)) {
            return new WP_Error('dukkan_woo_extended_unauthorized', 'Invalid or missing API key.', array('status' => 401));
        }

        return true;
    }

    /**
     * Get the static API key used to protect the key generation endpoint.
     *
     * Define DUKKAN_WOO_EXTENDED_STATIC_API_KEY in wp-config.php, or override it
     * with the dukkan_plugin_woo_extended_static_api_key filter.
     */
    private function dukkan_plugin_get_static_api_key()
    {
        $api_key = defined('DUKKAN_WOO_EXTENDED_STATIC_API_KEY') ? DUKKAN_WOO_EXTENDED_STATIC_API_KEY : 'dukkan_woo_extended_static_key';

        return apply_filters('dukkan_plugin_woo_extended_static_api_key', $api_key);
    }

    /**
     * Generate store connection auth code.
     */
    public function dukkan_plugin_generate_store_connection_auth_code(WP_REST_Request $request)
    {
        // Generate a 4-character alphanumeric code.
        $auth_code = strtoupper( wp_generate_password( 4, false, false ) );

        // Save the code in plugin settings.
        update_option( $this->store_connection_auth_code_option_name, $auth_code );

        return new WP_REST_Response(
            array(
                'success' => true,
            ),
            200
        );
    }

    /**
     * Generate WooCommerce REST API keys.
     */
    public function dukkan_plugin_generate_woo_rest_api_keys(WP_REST_Request $request)
    {
        if (!class_exists('WooCommerce') || !function_exists('WC')) {
            return new WP_Error('woocommerce_not_active', 'WooCommerce is not active.', array('status' => 400));
        }

        if (!function_exists('wc_rand_hash') || !function_exists('wc_api_hash')) {
            return new WP_Error('woocommerce_api_keys_unavailable', 'WooCommerce API key helpers are unavailable.', array('status' => 500));
        }

        $user_id = absint($request->get_param('user_id'));

        if (empty($user_id)) {
            $user_id = $this->dukkan_plugin_get_first_admin_user_id();
        }

        if (empty($user_id) || !get_userdata($user_id)) {
            return new WP_Error('invalid_user_id', 'A valid user_id is required.', array('status' => 400));
        }

        $permissions = sanitize_text_field($request->get_param('permissions'));
        $permissions = empty($permissions) ? 'read_write' : $permissions;
        $allowed_permissions = array('read', 'write', 'read_write');

        if (!in_array($permissions, $allowed_permissions, true)) {
            return new WP_Error('invalid_permissions', 'Permissions must be read, write, or read_write.', array('status' => 400));
        }

        $description = substr(sanitize_text_field($request->get_param('description')), 0, 200);

        if (empty($description)) {
            $description = sprintf('Dukkan REST API key - %s', current_time('mysql'));
        }

        $keys = $this->dukkan_plugin_create_woo_api_key($description, $user_id, $permissions);

        if (is_wp_error($keys)) {
            return $keys;
        }

        return new WP_REST_Response(array(
            'success'         => true,
            'key_id'          => isset($keys['key_id']) ? absint($keys['key_id']) : 0,
            'user_id'         => $user_id,
            'description'     => $description,
            'permissions'     => $permissions,
            'consumer_key'    => $keys['consumer_key'],
            'consumer_secret' => $keys['consumer_secret'],
        ), 201);
    }

    /**
     * Get the first administrator user by user ID.
     */
    private function dukkan_plugin_get_first_admin_user_id()
    {
        $admin_users = get_users(array(
            'role'    => 'administrator',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
            'fields'  => 'ID',
        ));

        if (empty($admin_users)) {
            return 0;
        }

        return absint($admin_users[0]);
    }

    /**
     * Create a WooCommerce REST API key and return the plaintext credentials once.
     */
    private function dukkan_plugin_create_woo_api_key($description, $user_id, $permissions)
    {
        global $wpdb;

        $consumer_key = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            array(
                'user_id'         => $user_id,
                'description'     => $description,
                'permissions'     => $permissions,
                'consumer_key'    => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key'   => substr($consumer_key, -7),
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if (!$inserted) {
            return new WP_Error('woocommerce_api_key_generation_failed', 'Unable to store WooCommerce API key.', array(
                'status' => 500,
                'error'  => $wpdb->last_error,
            ));
        }

        return array(
            'key_id'          => absint($wpdb->insert_id),
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        );
    }

    /**
     * Delete unused product images when deleting via REST API
     */
    public function dukkan_plugin_delete_product_with_images($post_id) {
        // Only products
        if ('product' !== get_post_type($post_id)) {
            return;
        }

        // Only REST API requests
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return;
        }

        // Check custom parameter
        $delete_images = false;

        if (isset($_REQUEST['delete_images'])) {
            $delete_images = filter_var($_REQUEST['delete_images'], FILTER_VALIDATE_BOOLEAN);
        }

        if (!$delete_images) {
            return;
        }

        $product = wc_get_product($post_id);

        if (!$product) {
            return;
        }

        $image_ids = [];

        // Featured image
        if ($product->get_image_id()) {
            $image_ids[] = $product->get_image_id();
        }

        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();

        if (!empty($gallery_ids)) {
            $image_ids = array_merge($image_ids, $gallery_ids);
        }

        $image_ids = array_unique($image_ids);

        foreach ($image_ids as $attachment_id) {

            // Skip if image used elsewhere
            if ($this->dukkan_plugin_is_attachment_used_by_other_products($attachment_id, $post_id)) {
                continue;
            }

            // Permanently delete attachment + files
            wp_delete_attachment($attachment_id, true);
        }
    }

    /**
     * Check whether attachment is used by other products
     */
    public function dukkan_plugin_is_attachment_used_by_other_products($attachment_id, $current_product_id)
    {
        global $wpdb;

        // Featured image usage
        $featured_exists = $wpdb->get_var($wpdb->prepare(
            "
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id'
            AND meta_value = %d
            AND post_id != %d
            LIMIT 1
            ",
            $attachment_id,
            $current_product_id
        ));

        if ($featured_exists) {
            return true;
        }

        // Gallery usage
        $gallery_exists = $wpdb->get_var($wpdb->prepare(
            "
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_product_image_gallery'
            AND FIND_IN_SET(%d, meta_value)
            AND post_id != %d
            LIMIT 1
            ",
            $attachment_id,
            $current_product_id
        ));

        if ($gallery_exists) {
            return true;
        }

        return false;
    }
}
