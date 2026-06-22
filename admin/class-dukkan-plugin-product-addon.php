<?php

/**
 * The product-addon-specific functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * The product-addon-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the product-addon-specific stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Product_Addon {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

        add_filter('dukkan_settings_tabs', array($this, 'add_product_addon_settings_tabs'));

		add_action('dukkan_settings_tab_content_addons', array($this, 'dukkan_addons_tab_content'));
		
		add_action('wp_ajax_wpldp_get_categories', array($this, 'dukkan_wpldp_get_categories'));
		add_action('wp_ajax_nopriv_wpldp_get_categories', array($this, 'dukkan_wpldp_get_categories'));

		add_action('wp_ajax_wpldp_search_products', array($this, 'dukkan_wpldp_search_products'));
		add_action('wp_ajax_nopriv_wpldp_search_products', array($this, 'dukkan_wpldp_search_products'));

		add_action('wp_ajax_wpldp_save_group', array($this, 'dukkan_wpldp_save_group'));
		add_action('wp_ajax_nopriv_wpldp_save_group', array($this, 'dukkan_wpldp_save_group'));

		add_action('wp_ajax_wpldp_delete_group', array($this, 'dukkan_wpldp_delete_group'));
		add_action('wp_ajax_nopriv_wpldp_delete_group', array($this, 'dukkan_wpldp_delete_group'));

		add_action('wp_ajax_wpldp_duplicate_group', array($this, 'dukkan_wpldp_duplicate_group'));
		add_action('wp_ajax_nopriv_wpldp_duplicate_group', array($this, 'dukkan_wpldp_duplicate_group'));

		add_action('wp_ajax_wpldp_toggle_group_status', array($this, 'dukkan_wpldp_toggle_group_status'));
		add_action('wp_ajax_nopriv_wpldp_toggle_group_status', array($this, 'dukkan_wpldp_toggle_group_status'));

		add_action('wp_ajax_wpldp_get_group', array($this, 'dukkan_wpldp_get_group'));
		add_action('wp_ajax_nopriv_wpldp_get_group', array($this, 'dukkan_wpldp_get_group'));

		add_action('wp_ajax_wpldp_update_group', array($this, 'dukkan_wpldp_update_group'));
		add_action('wp_ajax_nopriv_wpldp_update_group', array($this, 'dukkan_wpldp_update_group'));

		// add_action('wp_ajax_wpldp_update_group_field_data', array($this, 'dukkan_wpldp_update_group_field_data'));
		// add_action('wp_ajax_nopriv_wpldp_update_group_field_data', array($this, 'dukkan_wpldp_update_group_field_data'));

		add_action('wp_ajax_wpldp_update_group_all_fields', array($this, 'dukkan_wpldp_update_group_all_fields'));
		add_action('wp_ajax_nopriv_wpldp_update_group_all_fields', array($this, 'dukkan_wpldp_update_group_all_fields'));

		add_action('wp_ajax_wpldp_duplicate_group_addon_field', array($this, 'dukkan_wpldp_duplicate_group_addon_field'));
		add_action('wp_ajax_nopriv_wpldp_duplicate_group_addon_field', array($this, 'dukkan_wpldp_duplicate_group_addon_field'));

		add_action('wp_ajax_wpldp_delete_group_addon_field', array($this, 'dukkan_wpldp_delete_group_addon_field'));
		add_action('wp_ajax_nopriv_wpldp_delete_group_addon_field', array($this, 'dukkan_wpldp_delete_group_addon_field'));

	}

	/**
	 * Register the stylesheets for the admin product-addon area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dukkan_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dukkan_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_style( $this->plugin_name . '-product-addon', plugin_dir_url( __FILE__ ) . 'css/dp-product-addon.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dukkan_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dukkan_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name . '-product-addon', plugin_dir_url( __FILE__ ) . 'js/dp-product-addon.js', array( 'jquery', 'selectWoo', $this->plugin_name ), $this->version, false );

	}

    public function add_product_addon_settings_tabs($tabs){
		$tabs['addons'] = array(
			'title' => 'Product Add-Ons',
			'icon'  => 'fa-solid fa-dollar-sign',
		);

		return $tabs;
	}

    public function dukkan_addons_tab_content() {
		require plugin_dir_path(__FILE__) . 'partials/product-addons-settings.php';
	}

    public function dukkan_wpldp_get_categories() {
		// check_ajax_referer('wpldp_nonce', 'nonce');

		// $terms = get_terms([
		// 	'taxonomy' => 'product_cat',
		// 	'hide_empty' => false,
		// ]);

		// $tree = [];

		// foreach ($terms as $term) {
		// 	$tree[$term->parent][] = $term;
		// }

		// function build_tree($parent, $tree){
		// 	$html = '';

		// 	if(!empty($tree[$parent])){
		// 		foreach($tree[$parent] as $term){

		// 			$html .= '<div class="cat-item" data-id="'.$term->term_id.'">';
		// 			$html .= '<label><input type="checkbox" class="cat-checkbox" value="'.$term->term_id.'"> '.$term->name.'</label>';

		// 			if(isset($tree[$term->term_id])){
		// 				$html .= '<div class="cat-children">';
		// 				$html .= build_tree($term->term_id, $tree);
		// 				$html .= '</div>';
		// 			}

		// 			$html .= '</div>';
		// 		}
		// 	}

		// 	return $html;
		// }

		// echo build_tree(0, $tree);
		// wp_die();
		check_ajax_referer('wpldp_nonce', 'nonce');

		$terms = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		]);

		$tree = [];

		foreach ($terms as $term) {
			$tree[$term->parent][] = $term;
		}

		function build_tree($parent, $tree){

			$html = '';

			if (!empty($tree[$parent])) {

				foreach ($tree[$parent] as $term) {

					// Parent
					$html .= '<label class="wpldp-cat-item">';
					$html .= '<input type="checkbox" name="product_addon[categories][]" class="cat-checkbox" value="'.$term->term_id.'"> ';
					$html .= '<span class="cat-name">'.$term->name.'</span>';
					$html .= '</label>';

					// Children
					if (isset($tree[$term->term_id])) {

						$html .= '<div class="wpldp-sub-cat">';

						foreach ($tree[$term->term_id] as $child) {

							$html .= '<label class="wpldp-cat-item">';
							$html .= '<input type="checkbox" name="product_addon[categories][]" class="cat-checkbox" value="'.$child->term_id.'"> ';
							$html .= '<span class="cat-name">'.$child->name.'</span>';
							$html .= '</label>';
						}

						$html .= '</div>';
					}
				}
			}

			return $html;
		}

		echo build_tree(0, $tree);

		wp_die();
	}

	public function dukkan_wpldp_search_products() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$search = sanitize_text_field($_GET['q'] ?? '');

		$products = wc_get_products([
			'limit' => 20,
			'status' => 'publish',
			's' => $search
		]);

		$results = [];

		foreach($products as $product){
			$results[] = [
				'id' => $product->get_id(),
				'text' => $product->get_name()
			];
		}

		wp_send_json($results);
	}

	public function dukkan_wpldp_save_group() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$product_addon = $_POST['product_addon'] ?? [];

		$group_name = sanitize_text_field($product_addon['group_name'] ?? '');
		$description = sanitize_textarea_field($product_addon['description'] ?? '');
		$applied_to = sanitize_text_field($product_addon['applied_to'] ?? '');

		$categories = array_map('intval', $product_addon['categories'] ?? []);
		$products = array_map('intval', $product_addon['products'] ?? []);

		if(empty($group_name)){
			wp_send_json_error(['message' => 'Group name is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);
		$group_id = sanitize_title($group_name).'-'.time();
		$groups[$group_id] = [
			'id' => $group_id,
			'group_name' => $group_name,
			'description' => $description,
			'applied_to' => $applied_to,
			'categories' => $categories,
			'products' => $products,
			'status' => 1
		];

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success([
			'id' => $group_id,
			'group_name' => $group_name,
			'status' => 1
		]);
	}

	public function dukkan_wpldp_delete_group() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		unset($groups[$group_id]);

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success();
	}

	public function dukkan_wpldp_duplicate_group() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		$group = $groups[$group_id];

		$new_group_id = sanitize_title($group['group_name']).' (copy)-'.time();
		$group['group_name'] .= ' (Copy)';

		$groups[$new_group_id] = $group;

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success([
			'id' => $new_group_id,
			'group_name' => $group['group_name'],
			'status' => 1
		]);
	}

	public function dukkan_wpldp_toggle_group_status() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');
		$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		$groups[$group_id]['status'] = $status;

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success();
	}

	public function dukkan_wpldp_get_group() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		if(!empty($groups[$group_id]['products'])){

			foreach($groups[$group_id]['products'] as $index => $product_id){

				$product = wc_get_product($product_id);

				if($product){
					$groups[$group_id]['products'][$index] = [
						'id' => $product->get_id(),
						'name' => $product->get_name()
					];
				}else{
					unset($groups[$group_id]['products'][$index]);
				}
			}
		}

		wp_send_json_success($groups[$group_id]);
	}

	public function dukkan_wpldp_update_group() {
        check_ajax_referer('wpldp_nonce', 'nonce');

        $group_id = sanitize_text_field($_POST['group_id'] ?? '');

        if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		$product_addon = $_POST['product_addon'] ?? [];

		$group_name = sanitize_text_field($product_addon['group_name'] ?? '');
		$description = sanitize_textarea_field($product_addon['description'] ?? '');
		$applied_to = sanitize_text_field($product_addon['applied_to'] ?? '');

		$categories = array_map('intval', $product_addon['categories'] ?? []);
		$products = array_map('intval', $product_addon['products'] ?? []);

		if(empty($group_name)){
			wp_send_json_error(['message' => 'Group name is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

        $groups[$group_id] = array_merge($groups[$group_id], [
			'group_name' => $group_name,
			'description' => $description,
			'applied_to' => $applied_to,
			'categories' => $categories,
			'products' => $products,
		]);

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success([
			'id' => $group_id,
			'group_name' => $group_name
		]);



		// check_ajax_referer('wpldp_nonce', 'nonce');

		// $group_id = sanitize_text_field($_POST['group_id'] ?? '');
		// $product_addon = $_POST['product_addon'] ?? [];

		// if(empty($group_id)){
		// 	wp_send_json_error(['message' => 'Group ID is required']);
		// }

		// $groups = get_option('wpldp_product_addon_groups', []);

		// if(!isset($groups[$group_id])){
		// 	wp_send_json_error(['message' => 'Group not found']);
		// }

		// $group_name = sanitize_text_field($product_addon['group_name'] ?? '');
		// $description = sanitize_textarea_field($product_addon['description'] ?? '');
		// $applied_to = sanitize_text_field($product_addon['applied_to'] ?? '');

		// $categories = array_map('intval', $product_addon['categories'] ?? []);
		// $products = array_map('intval', $product_addon['products'] ?? []);

		// if(empty($group_name)){
		// 	wp_send_json_error(['message' => 'Group name is required']);
		// }

		// $groups[$group_id] = array_merge($groups[$group_id], [
		// 	'group_name' => $group_name,
		// 	'description' => $description,
		// 	'applied_to' => $applied_to,
		// 	'categories' => $categories,
		// 	'products' => $products,
		// ]);

		// update_option('wpldp_product_addon_groups', $groups);

		// wp_send_json_success([
		// 	'id' => $group_id,
		// 	'group_name' => $group_name,
		// 	'status' => $groups[$group_id]['status']
		// ]);
	}

	public function dukkan_wpldp_update_group_all_fields() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		$form_fields_data = $_POST['fields'] ?? [];

		foreach($form_fields_data as $field_id => $field_data){
			$this->dukkan_wpldp_update_group_field_data($group_id, $field_id, $field_data); // reuse existing function to update each field data
		}

		wp_send_json_success();
	}

	public function dukkan_wpldp_update_group_field_data($group_id = null, $field_id = null, $field_data = null) {
		// check_ajax_referer('wpldp_nonce', 'nonce');

		// $group_id = sanitize_text_field($_POST['group_id'] ?? '');
		// $field_id = sanitize_text_field($_POST['field_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		// $form_field_data = $_POST['fields'] ?? [];

		// $field_data = $form_field_data[$field_id]??[];

		if($field_data['type'] === 'image'){
			if(isset($field_data['options'])){
				foreach($field_data['options'] as &$option){
					if(!empty($option['image_id'])){
						$option['image_url'] = wp_get_attachment_url($option['image_id']);
					}
				}
			}
		}

		$groups[$group_id]['fields'][$field_id] = $field_data;

		update_option('wpldp_product_addon_groups', $groups);

		// wp_send_json_success();
	}

	public function dukkan_wpldp_duplicate_group_addon_field() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');
		$field_id = sanitize_text_field($_POST['field_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		if(!isset($groups[$group_id]['fields'][$field_id])){
			wp_send_json_error(['message' => 'Field not found']);
		}

		$field_data = $groups[$group_id]['fields'][$field_id];

		$new_field_id = $group_id.'_'.time();

		$groups[$group_id]['fields'][$new_field_id] = $field_data;

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success([
			'field_id' => $new_field_id,
			'field_data' => $field_data
		]);
	}

	public function dukkan_wpldp_delete_group_addon_field() {
		check_ajax_referer('wpldp_nonce', 'nonce');

		$group_id = sanitize_text_field($_POST['group_id'] ?? '');
		$field_id = sanitize_text_field($_POST['field_id'] ?? '');

		if(empty($group_id)){
			wp_send_json_error(['message' => 'Group ID is required']);
		}

		$groups = get_option('wpldp_product_addon_groups', []);

		if(!isset($groups[$group_id])){
			wp_send_json_error(['message' => 'Group not found']);
		}

		if(!isset($groups[$group_id]['fields'][$field_id])){
			wp_send_json_error(['message' => 'Field not found']);
		}

		unset($groups[$group_id]['fields'][$field_id]);

		update_option('wpldp_product_addon_groups', $groups);

		wp_send_json_success();
	}
	
}