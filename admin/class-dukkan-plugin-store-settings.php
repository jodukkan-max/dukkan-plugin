<?php

/**
 * Store settings admin functionality.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Adds the Dukkan Store Settings tab and saves its fields.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Store_Settings {

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
	 * Option name used to store all Dukkan store settings.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const OPTION_NAME = 'dukkan_store_settings';

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

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_store_settings_tab' ) );
		add_action( 'dukkan_settings_tab_content_store_settings', array( $this, 'store_settings_tab_content' ) );
		add_action( 'admin_post_dukkan_store_settings_save', array( $this, 'save_store_settings' ) );

	}

	/**
	 * Add Dukkan Store Settings tab.
	 *
	 * @since  1.0.0
	 * @param  array $tabs Existing settings tabs.
	 * @return array
	 */
	public function add_store_settings_tab( $tabs ) {
		$tabs['store_settings'] = array(
			'title' => __( 'Dukkan Store Settings', 'dukkan-plugin' ),
			'icon'  => 'fa-solid fa-store',
		);

		return $tabs;
	}

	/**
	 * Get JSON-style field definitions for this settings tab.
	 *
	 * Add more fields by filtering `dukkan_store_settings_fields`.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_fields() {
		$fields = array(
			'dukkan_woo_order_status' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Dukkan Woo Order Status', 'dukkan-plugin' ),
				'description' => __( 'Enable Dukkan WooCommerce order status handling.', 'dukkan-plugin' ),
				'default'     => 'no',
			),
		);

		return apply_filters( 'dukkan_store_settings_fields', $fields );
	}

	/**
	 * Get saved option values with defaults.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		foreach ( $this->get_fields() as $field_id => $field ) {
			if ( ! array_key_exists( $field_id, $settings ) ) {
				$settings[ $field_id ] = isset( $field['default'] ) ? $field['default'] : '';
			}
		}

		return $settings;
	}

	/**
	 * Render the settings tab content.
	 *
	 * @since 1.0.0
	 */
	public function store_settings_tab_content() {
		$fields   = $this->get_fields();
		$settings = $this->get_settings();
		$saved    = isset( $_GET['dukkan_store_settings_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['dukkan_store_settings_saved'] ) ) : '';
		?>
		<div class="dukkan-store-settings">
			<div class="dukkan-store-settings__header">
				<h2><?php esc_html_e( 'Dukkan Store Settings', 'dukkan-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Manage store-level Dukkan options.', 'dukkan-plugin' ); ?></p>
			</div>

			<?php if ( '1' === $saved ) : ?>
				<div class="notice notice-success inline">
					<p><?php esc_html_e( 'Store settings saved.', 'dukkan-plugin' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dukkan-store-settings__form">
				<input type="hidden" name="action" value="dukkan_store_settings_save">
				<?php wp_nonce_field( 'dukkan_store_settings_save', 'dukkan_store_settings_nonce' ); ?>

				<?php foreach ( $fields as $field_id => $field ) : ?>
					<?php $this->render_field( $field_id, $field, $settings ); ?>
				<?php endforeach; ?>

				<?php do_action( 'dukkan_store_settings_after_fields', $fields, $settings ); ?>

				<?php submit_button( __( 'Save Store Settings', 'dukkan-plugin' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one field from the field definition.
	 *
	 * @since 1.0.0
	 * @param string $field_id Field ID.
	 * @param array  $field    Field definition.
	 * @param array  $settings Saved settings.
	 */
	private function render_field( $field_id, $field, $settings ) {
		$type        = isset( $field['type'] ) ? $field['type'] : 'text';
		$label       = isset( $field['label'] ) ? $field['label'] : $field_id;
		$description = isset( $field['description'] ) ? $field['description'] : '';
		$value       = isset( $settings[ $field_id ] ) ? $settings[ $field_id ] : '';
		$name        = self::OPTION_NAME . '[' . $field_id . ']';
		?>
		<div class="dukkan-store-settings__field dukkan-store-settings__field--<?php echo esc_attr( $type ); ?>">
			<div class="dukkan-store-settings__field-label">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>

			<div class="dukkan-store-settings__field-control">
				<?php if ( 'checkbox' === $type ) : ?>
					<label class="wpldp-switch">
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( $value, 'yes' ); ?>>
						<span class="wpldp-slider"></span>
					</label>
				<?php else : ?>
					<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Dukkan store settings.
	 *
	 * @since 1.0.0
	 */
	public function save_store_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'dukkan-plugin' ) );
		}

		check_admin_referer( 'dukkan_store_settings_save', 'dukkan_store_settings_nonce' );

		$fields = $this->get_fields();
		$raw    = isset( $_POST[ self::OPTION_NAME ] ) && is_array( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array();
		$values = array();

		do_action( 'dukkan_store_settings_before_save', $raw, $fields );

		foreach ( $fields as $field_id => $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : 'text';

			if ( 'checkbox' === $type ) {
				$values[ $field_id ] = isset( $raw[ $field_id ] ) ? 'yes' : 'no';
				$values[ $field_id ] = apply_filters( 'dukkan_store_settings_field_value_before_save', $values[ $field_id ], $field_id, $field, $raw );
				continue;
			}

			$field_value = isset( $raw[ $field_id ] ) && is_scalar( $raw[ $field_id ] ) ? sanitize_text_field( $raw[ $field_id ] ) : '';

			$values[ $field_id ] = apply_filters( 'dukkan_store_settings_field_value_before_save', $field_value, $field_id, $field, $raw );
		}

		$values = apply_filters( 'dukkan_store_settings_values_before_save', $values, $fields, $raw );

		update_option( self::OPTION_NAME, $values );

		do_action( 'dukkan_store_settings_after_save', $values, $fields );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                         => 'dukkan-settings',
					'tab'                          => 'store_settings',
					'dukkan_store_settings_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

}
