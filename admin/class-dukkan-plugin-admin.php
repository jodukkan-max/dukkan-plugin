<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Admin {

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

		add_action('admin_menu', array($this, 'dukkan_add_admin_menu'));
		add_action('dukkan_settings_tab_content_dukkan_main', array($this, 'dukkan_dukkan_main_tab_content'));
		add_action('dukkan_settings_tab_content_discounts', array($this, 'dukkan_discounts_tab_content'));	
		add_action('dukkan_settings_tab_content_store_app_connection', array($this, 'dukkan_store_app_connection_tab_content'));
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($hook_suffix) {
		if ($hook_suffix !== 'toplevel_page_dukkan-settings') {
			return;
		}
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
		wp_enqueue_style('select2'); // WP registered style

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook_suffix) {
		if ($hook_suffix !== 'toplevel_page_dukkan-settings') {
			return;
		}
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
		// Select2
		// WooCommerce / WP Select2
		wp_enqueue_script('selectWoo'); // safer than select2
		
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/dukkan-plugin-admin.js', array( 'jquery', 'jquery-ui-sortable', 'selectWoo' ), $this->version, false );

		wp_localize_script($this->plugin_name, 'wpldp_ajax', [
			'url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpldp_nonce'),
			'os_i18n' => [
				'add_title'          => __( 'Add New Order Status', 'dukkan-plugin' ),
				'edit_title'         => __( 'Edit Order Status', 'dukkan-plugin' ),
				'name_required'      => __( 'Status name is required.', 'dukkan-plugin' ),
				'slug_required'      => __( 'Status slug is required.', 'dukkan-plugin' ),
				'slug_max'           => __( 'Status slug must be 20 characters or fewer.', 'dukkan-plugin' ),
				'save_btn'           => __( 'Save Status', 'dukkan-plugin' ),
				'saving'             => __( 'Saving…', 'dukkan-plugin' ),
				'deleting'           => __( 'Deleting…', 'dukkan-plugin' ),
				'added'              => __( 'Order status added.', 'dukkan-plugin' ),
				'updated'            => __( 'Order status updated.', 'dukkan-plugin' ),
				'deleted'            => __( 'Order status deleted.', 'dukkan-plugin' ),
				'order_saved'        => __( 'Order saved.', 'dukkan-plugin' ),
				'edit'               => __( 'Edit', 'dukkan-plugin' ),
				'delete'             => __( 'Delete', 'dukkan-plugin' ),
				'delete_confirm'     => __( 'Delete Order Status', 'dukkan-plugin' ),
				'delete_msg'         => __( 'Are you sure you want to delete this order status? This action cannot be undone.', 'dukkan-plugin' ),
				'cancel'             => __( 'Cancel', 'dukkan-plugin' ),
			],
			'dp_i18n' => [
				'add_title'             => __( 'Add Pricing Rule', 'dukkan-plugin' ),
				'edit_title'            => __( 'Edit Pricing Rule', 'dukkan-plugin' ),
				'save_btn'              => __( 'Save Rule', 'dukkan-plugin' ),
				'saving'                => __( 'Saving…', 'dukkan-plugin' ),
				'save_all'              => __( 'Save All Rules', 'dukkan-plugin' ),
				'saved_all'             => __( 'All pricing rules saved.', 'dukkan-plugin' ),
				'deleting'              => __( 'Deleting…', 'dukkan-plugin' ),
				'added'                 => __( 'Pricing rule added.', 'dukkan-plugin' ),
				'updated'               => __( 'Pricing rule updated.', 'dukkan-plugin' ),
				'deleted'               => __( 'Pricing rule deleted.', 'dukkan-plugin' ),
				'duplicated'            => __( 'Pricing rule duplicated.', 'dukkan-plugin' ),
				'order_saved'           => __( 'Order saved.', 'dukkan-plugin' ),
				'setting_saved'         => __( 'Setting saved.', 'dukkan-plugin' ),
				'simple_adjustment'                     => __( 'Simple adjustment', 'dukkan-plugin' ),
				'bulk_pricing'                          => __( 'Bulk pricing', 'dukkan-plugin' ),
				'tiered_pricing'                        => __( 'Tiered pricing', 'dukkan-plugin' ),
				'group_of_products'                     => __( 'Group of products', 'dukkan-plugin' ),
				'group_of_products_repeating'           => __( 'Group of products - Repeating', 'dukkan-plugin' ),
				'buy_x_get_x'                           => __( 'Buy x get x', 'dukkan-plugin' ),
				'buy_x_get_x_repeating'                 => __( 'Buy x get x - Repeating', 'dukkan-plugin' ),
				'buy_x_get_y_label'                     => __( 'Buy x get y', 'dukkan-plugin' ),
				'buy_x_get_y_repeating'                 => __( 'Buy x get y - Repeating', 'dukkan-plugin' ),
				'exclude_products_from_all_rules'       => __( 'Exclude products from all rules', 'dukkan-plugin' ),
				'restrict_purchase_of_matched_products' => __( 'Restrict purchase of matched products', 'dukkan-plugin' ),
				// Product filter i18n.
				'pf_product'                 => __( 'Product', 'dukkan-plugin' ),
				'pf_product_variation'       => __( 'Product variation', 'dukkan-plugin' ),
				'pf_product_category'        => __( 'Product category', 'dukkan-plugin' ),
				'pf_product_attributes'      => __( 'Product attributes', 'dukkan-plugin' ),
				'pf_product_tags'            => __( 'Product tags', 'dukkan-plugin' ),
				'pf_product_regular_price'   => __( 'Product regular price', 'dukkan-plugin' ),
				'pf_product_is_on_sale'      => __( 'Product is on sale', 'dukkan-plugin' ),
				'pf_product_stock_quantity'  => __( 'Product stock quantity', 'dukkan-plugin' ),
				'pf_product_shipping_class'  => __( 'Product shipping class', 'dukkan-plugin' ),
				'pf_product_metadata'        => __( 'Product metadata', 'dukkan-plugin' ),
				'pf_cart_item_data'          => __( 'Cart item data', 'dukkan-plugin' ),
				'pf_coupons_applied'         => __( 'Coupons applied', 'dukkan-plugin' ),
				'pf_yes'                     => __( 'Yes', 'dukkan-plugin' ),
				'pf_no'                      => __( 'No', 'dukkan-plugin' ),
				'pf_search_placeholder'      => __( 'Search or enter value…', 'dukkan-plugin' ),
				'pf_remove'                  => __( 'Remove filter', 'dukkan-plugin' ),
				// Operator i18n.
				'op_in_list'                  => __( 'in list', 'dukkan-plugin' ),
				'op_not_in_list'              => __( 'not in list', 'dukkan-plugin' ),
				'op_equals'                   => __( 'equals', 'dukkan-plugin' ),
				'op_not_equals'               => __( 'not equals', 'dukkan-plugin' ),
				'op_greater_than'             => __( 'greater than', 'dukkan-plugin' ),
				'op_less_than'                => __( 'less than', 'dukkan-plugin' ),
				'op_greater_than_or_equal'    => __( 'greater than or equal', 'dukkan-plugin' ),
				'op_less_than_or_equal'       => __( 'less than or equal', 'dukkan-plugin' ),
				'op_contains'                 => __( 'contains', 'dukkan-plugin' ),
				'op_does_not_contain'         => __( 'does not contain', 'dukkan-plugin' ),
				'applies_all'           => __( 'Applies to all products.', 'dukkan-plugin' ),
				'applies_all_cases'     => __( 'Applies in all cases.', 'dukkan-plugin' ),
				'add_product'           => __( 'Add Product', 'dukkan-plugin' ),
				'add_condition'         => __( 'Add Condition', 'dukkan-plugin' ),
				'product_placeholder'   => __( 'Product selector coming soon.', 'dukkan-plugin' ),
				'condition_placeholder' => __( 'Condition builder coming soon.', 'dukkan-plugin' ),
			],
		]);

	}

	/*
	|--------------------------------------------------------------------------
	| Add Admin Menu
	|--------------------------------------------------------------------------
	*/
	public function dukkan_add_admin_menu(){
		add_menu_page(
			'Dukkan Settings',        // Page title
			'Dukkan Settings',        // Menu title
			'manage_options',         // Capability
			'dukkan-settings',        // Menu slug
			array($this, 'dukkan_settings_page'),   // Callback
			'dashicons-store',        // Icon
			25                        // Position
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Admin Page HTML
	|--------------------------------------------------------------------------
	*/

	public function dukkan_settings_page() {
	?>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
		<div class="wrap">
			<?php require plugin_dir_path(__FILE__) . 'partials/dukkan-settings-main.php'; ?>
			<!-- <form method="post" action="options.php">
				
				<?php
				// settings_fields('dukkan_settings_group');
				// do_settings_sections('dukkan-settings');
				// submit_button();
				?>

			</form> -->
		</div>

	<?php
	}

	public function dukkan_dukkan_main_tab_content(){
		require plugin_dir_path(__FILE__) . 'partials/dukkan-plugin-dashboard-new.php';
	}

	public function dukkan_discounts_tab_content(){
		require plugin_dir_path(__FILE__) . 'partials/dukkan-discount-settings.php';
	}

	public function dukkan_store_app_connection_tab_content(){
		require plugin_dir_path(__FILE__) . 'partials/dukkan-store-app-connection-settings.php';
	}

}
