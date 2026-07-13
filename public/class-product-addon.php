<?php

/**
 * The product addon public-facing functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * The product addon public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Product_Addon {

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

        //add_action('woocommerce_before_add_to_cart_button', array($this, 'wpldp_render_addons') );

        add_action('woocommerce_before_add_to_cart_button', array($this, 'wpldp_render_addons_new') );

        add_action( 'wp_ajax_wpldp_upload_file',        array( $this, 'wpldp_ajax_upload_file' ) );
        add_action( 'wp_ajax_nopriv_wpldp_upload_file', array( $this, 'wpldp_ajax_upload_file' ) );

        // cart calculation and validation hooks
        add_action( 'woocommerce_add_to_cart_validation', array( $this, 'validate_addon_fields' ), 10, 3 );
        add_action( 'woocommerce_add_cart_item_data', array( $this, 'add_addon_data_to_cart_item' ), 10, 2 );
        add_action( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_addon_price' ) );
        add_action( 'woocommerce_get_item_data', array( $this, 'display_addon_data_in_cart' ), 10, 2 );

        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_addon_data_to_order_items' ), 10, 4 );
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'hide_internal_meta' ), 10, 2 );

	}

	/**
	 * Register the stylesheets for the product addon public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( ! is_product() ) {
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

		wp_enqueue_style( $this->plugin_name.'-product-addon-public', plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-product-addon.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the product addon public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_product() ) {
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

		wp_enqueue_script( $this->plugin_name.'-product-addon-public', plugin_dir_url( __FILE__ ) . 'js/dukkan-plugin-product-addon.js', array( 'jquery' ), $this->version, true );

        wp_localize_script( $this->plugin_name.'-product-addon-public', 'WPLDP', [
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_pos'    => get_option( 'woocommerce_currency_pos', 'left' ),
            'decimals'        => wc_get_price_decimals(),
            'decimal_sep'     => wc_get_price_decimal_separator(),
            'thousand_sep'    => wc_get_price_thousand_separator(),
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'wpldp_upload_nonce' ),
        ] );

	}

    public function get_groups_for_product( int $product_id ): array {
        $all_groups = $this->get_all_groups();
        $result     = [];

        foreach ( $all_groups as $group ) {
            if ( empty( $group['status'] ) ) continue;
            if ( $this->group_applies_to_product( $group, $product_id ) ) {
                $result[] = $group;
            }
        }

        return $result;
    }

    public function get_all_groups(): array {
        $raw     = get_option( 'wpldp_product_addon_groups', [] );
        if ( ! is_array( $raw ) ) return [];
        
        return $raw;
    }

    private function group_applies_to_product( array $group, int $product_id ): bool {
        $applied_to = $group['applied_to'] ?? 'all';

        if ( $applied_to === 'all' ) return true;

        if ( $applied_to === 'specific' ) {
            $allowed = $group['products'] ?? [];
            if(in_array($product_id, $allowed)){
                return true;
            }else if(!empty($group['categories'])){
                $term_ids = array_map( 'intval', (array) ( $group['categories'] ?? [] ) );
                return has_term( $term_ids, 'product_cat', $product_id );
            }
        }

        return false;
    }

    public function get_group( string $group_id ): ?array {
        $all_groups = $this->get_all_groups();
        if(!empty($all_groups) && isset($all_groups[$group_id])){
            return $all_groups[$group_id];
        }
        return null;
    }

    public function wpldp_render_addons_new(){
        global $product;
        if ( ! $product ) return;

        $groups = $this->get_groups_for_product( $product->get_id() );
        if ( empty( $groups ) ) return;

        $groups_json = wp_json_encode( $groups );
        echo "<script>var WPLDP_GROUPS = {$groups_json};</script>";

        // Base price — use sale price if on sale, regular price otherwise
        $base_price = 0;
        if ( $product->is_type( 'simple' ) ) {
            $base_price = (float) $product->get_price(); // get_price() always returns active price (sale or regular)
        }
        // For variable products base_price stays 0 — JS will read from variation data

        echo '<div class="wpa-addons-wrapper" id="wpa-addons-wrapper" data-base-price="' . esc_attr( $base_price ) . '" data-product-type="' . esc_attr( $product->get_type() ) . '">';
        foreach ( $groups as $group ) {
            $this->render_group( $group );
        }

        // echo '<div class="wpa-price-summary" id="wpa-price-summary" style="display:none;">';
        // echo '<div class="wpa-price-summary-inner">';
        // echo '<span class="wpa-price-label">Addons Total:</span>';
        // echo '<span class="wpa-price-value price" id="wpa-addons-total"></span>';
        // echo '</div></div>';

        echo '<div class="wpa-price-summary" id="wpa-price-summary" style="display:none;">';
        echo '<div class="wpa-price-summary-inner">';

        echo '<div class="wpa-price-row">';
        echo '<span class="wpa-price-label">' . esc_html__( 'Addons Total:', 'dukkan-plugin' ) . '</span>';
        echo '<span class="wpa-price-value price" id="wpa-addons-total"></span>';
        echo '</div>';

        echo '<div class="wpa-price-row wpa-price-row--total">';
        echo '<span class="wpa-price-label wpa-price-label--total">' . esc_html__( 'Total:', 'dukkan-plugin' ) . '</span>';
        echo '<span class="wpa-price-value wpa-price-value--total price" id="wpa-grand-total"></span>';
        echo '</div>';

        echo '</div></div>';

        echo '</div>';
    }

    private function render_group( array $group ) {
        $group_id = esc_attr( $group['id'] );
        $fields   = $group['fields'] ?? [];

        if( empty( $fields ) ) return;

        echo "<div class='wpa-group' data-group='{$group_id}'>";
        if ( ! empty( $group['group_name'] ) ) {
            //echo '<h4 class="wpa-group-title">' . esc_html( $group['group_name'] ) . '</h4>';
        }
        if ( ! empty( $group['description'] ) ) {
            //echo '<p class="wpa-group-desc">' . esc_html( $group['description'] ) . '</p>';
        }
        foreach ( $fields as $field_key => $field ) {
            $this->render_field( $field_key, $field, $group_id );
        }

        echo '</div>';
    }

    private function render_field( string $field_key, array $field, string $group_id ) {
        $type     = $field['type'] ?? 'text';
        $title    = $field['title'] ?? '';
        $width    = $field['width'] ?? '100%';
        $required = ! empty( $field['required'] ) ? 'required' : '';
        $req_mark = $required ? ' <span class="wpa-required">*</span>' : '';
        $price    = isset( $field['price'] ) ? (float) $field['price'] : 0;
        $name     = 'wpa_fields[' . esc_attr( $field_key ) . ']';
        $id       = 'wpa_' . esc_attr( $field_key );

        echo '<div style="width:'.esc_html( $width ).'" class="wpa-field wpa-field-type-' . esc_attr( $type ) . '" data-field-key="' . esc_attr( $field_key ) . '" data-field-price="' . esc_attr( $price ) . '" data-field-type="' . esc_attr( $type ) . '">';
        echo '<label class="wpa-label" for="' . $id . '">' . esc_html( $title ) . $req_mark . '</label>';

        switch ( $type ) {

            case 'text':
                echo '<input type="text" class="wpa-input wpa-price-trigger" id="' . $id . '" name="' . $name . '" ' . $required . ' />';
                if ( $price > 0 ) echo '<span class="wpa-field-price-hint">+' . wc_price( $price ) . '</span>';
                break;

            case 'textarea':
                echo '<textarea class="wpa-textarea wpa-price-trigger" id="' . $id . '" name="' . $name . '" rows="4" ' . $required . '></textarea>';
                if ( $price > 0 ) echo '<span class="wpa-field-price-hint">+' . wc_price( $price ) . '</span>';
                break;

            case 'number':
                echo '<input type="number" class="wpa-input wpa-price-trigger" id="' . $id . '" name="' . $name . '" min="0" ' . $required . ' />';
                if ( $price > 0 ) echo '<span class="wpa-field-price-hint">+' . wc_price( $price ) . '</span>';
                break;

            case 'date':
                echo '<input type="date" class="wpa-input wpa-price-trigger" id="' . $id . '" name="' . $name . '" ' . $required . ' />';
                if ( $price > 0 ) echo '<span class="wpa-field-price-hint">+' . wc_price( $price ) . '</span>';
                break;

            case 'file':
                echo '<div class="wpa-file-wrapper">';

                // Visible file input
                echo '<label class="wpa-file-btn" for="' . $id . '_trigger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Choose File
                    </label>';
                echo '<input type="file"
                            class="wpa-file wpa-price-trigger"
                            id="' . $id . '_trigger"
                            data-field-key="' . esc_attr( $field_key ) . '"
                            style="display:none;"
                            ' . $required . ' />';

                // Hidden field that holds the uploaded URL (used in cart)
                echo '<input type="hidden"
                            class="wpa-file-url-input"
                            id="' . $id . '"
                            name="' . $name . '"
                            value="" />';

                // Upload status message
                echo '<span class="wpa-file-status"></span>';

                if ( $price > 0 ) echo '<span class="wpa-field-price-hint">+' . wc_price( $price ) . '</span>';

                // Preview container
                echo '<div class="wpa-file-preview" id="' . $id . '_preview" style="display:none;"></div>';

                echo '</div>'; // .wpa-file-wrapper
                break;

            case 'select':
                echo '<select class="wpa-select wpa-price-trigger" id="' . $id . '" name="' . $name . '" ' . $required . '>';
                echo '<option value="">-- Select --</option>';
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_key => $opt ) {
                    $opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
                    $label     = $opt['label'] ?? '';
                    $label_str = $opt_price > 0 ? "{$label} (+" . number_format( $opt_price, 2 ) . ")" : $label;
                    echo '<option value="' . esc_attr( $opt_key ) . '" data-price="' . esc_attr( $opt_price ) . '">' . esc_html( $label_str ) . '</option>';
                }
                echo '</select>';
                break;

            case 'radio':
                echo '<div class="wpa-radio-group">';
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_key => $opt ) {
                    $opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
                    $label     = $opt['label'] ?? '';
                    echo '<label class="wpa-radio-label">';
                    echo '<input type="radio" class="wpa-radio wpa-price-trigger" name="' . $name . '" value="' . esc_attr( $opt_key ) . '" data-price="' . esc_attr( $opt_price ) . '" ' . $required . ' />';
                    echo esc_html( $label );
                    if ( $opt_price > 0 ) echo ' <span class="wpa-opt-price">+' . wc_price( $opt_price ) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'checkbox':
                echo '<div class="wpa-checkbox-group">';
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_key => $opt ) {
                    $opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
                    $label     = $opt['label'] ?? '';
                    echo '<label class="wpa-checkbox-label">';
                    echo '<input type="checkbox" class="wpa-checkbox wpa-price-trigger" name="' . $name . '[]" value="' . esc_attr( $opt_key ) . '" data-price="' . esc_attr( $opt_price ) . '" />';
                    echo esc_html( $label );
                    if ( $opt_price > 0 ) echo ' <span class="wpa-opt-price">+' . wc_price( $opt_price ) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'image':
                echo '<div class="wpa-image-group">';
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_key => $opt ) {
                    $opt_price = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
                    $label     = $opt['label'] ?? '';
                    $img_url   = esc_url( $opt['image_url'] ?? '' );
                    echo '<label class="wpa-image-label">';
                    echo '<input type="radio" class="wpa-image-radio wpa-price-trigger" name="' . $name . '" value="' . esc_attr( $opt_key ) . '" data-price="' . esc_attr( $opt_price ) . '" ' . $required . ' />';
                    if ( $img_url ) echo '<img src="' . $img_url . '" alt="' . esc_attr( $label ) . '" class="wpa-image-option" />';
                    echo '<span class="wpa-image-caption">' . esc_html( $label );
                    if ( $opt_price > 0 ) echo ' <span class="wpa-opt-price">+' . wc_price( $opt_price ) . '</span>';
                    echo '</span></label>';
                }
                echo '</div>';
                break;

            case 'color':
                echo '<div class="wpa-color-group">';
                foreach ( (array) ( $field['options'] ?? [] ) as $opt_key => $opt ) {
                    $opt_price  = isset( $opt['price'] ) ? (float) $opt['price'] : 0;
                    $label      = $opt['label'] ?? '';
                    $color_code = esc_attr( $opt['color_code'] ?? $opt['color'] ?? '#000' );
                    echo '<label class="wpa-color-label" title="' . esc_attr( $label ) . ( $opt_price > 0 ? ' (+' . number_format( $opt_price, 2 ) . ')' : '' ) . '">';
                    echo '<input type="radio" class="wpa-color-radio wpa-price-trigger" name="' . $name . '" value="' . esc_attr( $opt_key ) . '" data-price="' . esc_attr( $opt_price ) . '" ' . $required . ' />';
                    echo '<span class="wpa-color-swatch" style="background:' . $color_code . ';"></span>';
                    echo '<span class="wpa-color-caption">' . esc_html( $label ) . '</span>';
                    if ( $opt_price > 0 ) echo ' <span class="wpa-opt-price">+' . wc_price( $opt_price ) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
        }

        echo '</div>'; // .wpa-field

    }

    public function wpldp_ajax_upload_file() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpldp_upload_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ] );
        }

        $field_key = sanitize_text_field( $_POST['field_key'] ?? '' );

        if ( empty( $_FILES['file']['name'] ) ) {
            wp_send_json_error( [ 'message' => 'No file received.' ] );
        }

        // Define custom upload folder
        $upload_dir  = wp_upload_dir();
        $custom_dir  = $upload_dir['basedir'] . '/dukkan-product-addon';
        $custom_url  = $upload_dir['baseurl'] . '/dukkan-product-addon';

        // Create folder if it doesn't exist
        if ( ! file_exists( $custom_dir ) ) {
            wp_mkdir_p( $custom_dir );
            // Protect folder from direct listing
            file_put_contents( $custom_dir . '/index.php', '<?php // Silence is golden.' );
        }

        // Sanitize filename
        $filename  = sanitize_file_name( $_FILES['file']['name'] );
        $filename  = wp_unique_filename( $custom_dir, $filename );
        $filepath  = $custom_dir . '/' . $filename;
        $file_url  = $custom_url . '/' . $filename;

        // Allowed mime types
        $allowed = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ];

        $file_type = mime_content_type( $_FILES['file']['tmp_name'] );
        if ( ! in_array( $file_type, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => 'File type not allowed.' ] );
        }

        // Max file size: 5MB
        if ( $_FILES['file']['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => 'File size exceeds 5MB limit.' ] );
        }

        // Move uploaded file
        if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $filepath ) ) {
            wp_send_json_error( [ 'message' => 'Failed to save file.' ] );
        }

        wp_send_json_success( [
            'url'       => $file_url,
            'filename'  => $filename,
            'mime_type' => $file_type,
        ] );
    }

    // Hide raw addon meta from order details and emails
    public function hide_internal_meta( array $formatted_meta, $item ): array {
        foreach ( $formatted_meta as $key => $meta ) {
            if ( in_array( $meta->key, [ '_wpa_addons', '_wpa_addons_price' ], true ) ) {
                unset( $formatted_meta[ $key ] );
            }
        }
        return $formatted_meta;
    }

    // Save addon data to order items
    public function add_addon_data_to_order_items( $item, string $cart_item_key, array $values, $order ) {
        // if ( empty( $values['wpa_addons'] ) ) return;

        // // Raw data for programmatic access
        // $item->add_meta_data( '_wpa_addons',       $values['wpa_addons'], true );
        // $item->add_meta_data( '_wpa_addons_price', $values['wpa_addons_price'] ?? 0, true );

        // // Human-readable meta shown in admin order screen & emails
        // foreach ( $values['wpa_addons'] as $field_key => $addon ) {
        //     $value_str = $addon['value'] ?? '';
        //     if ( ! empty( $addon['price'] ) ) {
        //         $value_str .= ' (+' . wc_price( $addon['price'] ) . ')';
        //     }
        //     $item->add_meta_data( $addon['label'], $value_str );
        // }
        if ( empty( $values['wpa_addons'] ) ) return;

        // Raw data for programmatic access
        $item->add_meta_data( '_wpa_addons',       $values['wpa_addons'], true );
        $item->add_meta_data( '_wpa_addons_price', $values['wpa_addons_price'] ?? 0, true );

        // Human-readable meta shown in admin order screen & emails
        foreach ( $values['wpa_addons'] as $field_key => $addon ) {

            // For file fields show a clickable link instead of raw URL
            if ( ( $addon['type'] ?? '' ) === 'file' && ! empty( $addon['value'] ) ) {
                $value_str = '<a href="' . esc_url( $addon['value'] ) . '" target="_blank" rel="noopener">View Attachment</a>';
            } else {
                $value_str = $addon['value'] ?? '';
            }

            if ( ! empty( $addon['price'] ) ) {
                $value_str .= ' (+' . wc_price( $addon['price'] ) . ')';
            }

            $item->add_meta_data( $addon['label'], $value_str );
        }
    }

    // Display addon data in cart and checkout
    public function display_addon_data_in_cart( array $item_data, array $cart_item ): array {
        if ( empty( $cart_item['wpa_addons'] ) ) return $item_data;

        foreach ( $cart_item['wpa_addons'] as $addon ) {
            $price_str = ! empty( $addon['price'] ) ? ' (+' . wc_price( $addon['price'] ) . ')' : '';

            // For file fields, show a clickable link instead of the raw URL
            if ( $addon['type'] === 'file' && ! empty( $addon['value'] ) ) {
                $display_value = '<a href="' . esc_url( $addon['value'] ) . '" target="_blank" rel="noopener">View Attachment</a>' . $price_str;
            } else {
                $display_value = esc_html( $addon['value'] ) . $price_str;
            }

            $item_data[] = [
                'key'     => esc_html( $addon['label'] ),
                'value'   => $display_value,
                'display' => $display_value, // WooCommerce uses 'display' key in some contexts
            ];
        }

        return $item_data;
    }
    
    // Add addon price to cart item price
    public function calculate_addon_price( WC_Cart $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['wpa_addons_price'] ) ) continue;
            $product = $cart_item['data'];
            $product->set_price( (float) $product->get_price() + (float) $cart_item['wpa_addons_price'] );
        }
    }

    // Restore addon data when cart is loaded from session
    public function get_cart_item_from_session( array $cart_item, array $values ): array {
        if ( isset( $values['wpa_addons'] ) ) {
            $cart_item['wpa_addons']       = $values['wpa_addons'];
            $cart_item['wpa_addons_price'] = $values['wpa_addons_price'] ?? 0;
        }
        return $cart_item;
    }

    // Add addon data to cart item
    public function add_addon_data_to_cart_item( array $cart_item_data, int $product_id ): array {
        $groups = $this->get_groups_for_product( $product_id );
        if ( empty( $groups ) ) return $cart_item_data;

        $posted      = isset( $_POST['wpa_fields'] ) ? (array) $_POST['wpa_fields'] : [];
        $saved       = [];
        $total_price = 0.0;

        foreach ( $groups as $group ) {
            foreach ( (array) ( $group['fields'] ?? [] ) as $field_key => $field ) {
                $type  = $field['type'] ?? 'text';
                $entry = [ 'type' => $type, 'label' => $field['title'] ?? $field_key, 'group' => $group['id'] ];

                switch ( $type ) {

                    case 'text':
                    case 'textarea':
                    case 'number':
                    case 'date':
                        $val = sanitize_text_field( $posted[ $field_key ] ?? '' );
                        if ( $val !== '' ) {
                            $entry['value'] = $val;
                            $entry['price'] = (float) ( $field['price'] ?? 0 );
                            $total_price   += $entry['price'];
                        }
                        break;
                    // REPLACE the old 'file' case in add_cart_item_data() with this:
                    case 'file':
                        $url = sanitize_text_field( $posted[ $field_key ] ?? '' );
                        if ( ! empty( $url ) ) {
                            $entry['value'] = $url;
                            $entry['price'] = (float) ( $field['price'] ?? 0 );
                            $total_price   += $entry['price'];
                        }
                        break;

                    case 'select':
                    case 'radio':
                    case 'image':
                    case 'color':
                        $opt_key = sanitize_text_field( $posted[ $field_key ] ?? '' );
                        if ( isset( $field['options'][ $opt_key ] ) ) {
                            $opt            = $field['options'][ $opt_key ];
                            $entry['value'] = $opt['label'] ?? $opt_key;
                            $entry['price'] = (float) ( $opt['price'] ?? 0 );
                            $total_price   += $entry['price'];
                        }
                        break;

                    case 'checkbox':
                        $selected = (array) ( $posted[ $field_key ] ?? [] );
                        $labels   = [];
                        $cb_price = 0.0;
                        foreach ( $selected as $opt_key => $opt_val ) {
                            $opt_key = sanitize_text_field( $opt_val );
                            if ( isset( $field['options'][ $opt_val ] ) ) {
                                $opt      = $field['options'][ $opt_val ];
                                $labels[] = $opt['label'] ?? $opt_val;
                                $cb_price += (float) ( $opt['price'] ?? 0 );
                            }
                        }
                        if ( ! empty( $labels ) ) {
                            $entry['value'] = implode( ', ', $labels );
                            $entry['price'] = $cb_price;
                            $total_price   += $cb_price;
                        }
                        break;
                }

                if ( isset( $entry['value'] ) ) {
                    $saved[ $field_key ] = $entry;
                }
            }
        }

        if ( ! empty( $saved ) ) {
            $cart_item_data['wpa_addons']       = $saved;
            $cart_item_data['wpa_addons_price'] = $total_price;
            // Unique key so different addon combos are separate cart items
            $cart_item_data['wpa_unique_key']   = md5( wp_json_encode( $saved ) );
        }

        return $cart_item_data;
    }

    // Validate addon fields on add to cart
    public function validate_addon_fields( bool $valid, int $product_id, int $quantity ): bool {
        $groups = $this->get_groups_for_product( $product_id );
        if ( empty( $groups ) ) return $valid;

        $posted = isset( $_POST['wpa_fields'] ) ? (array) $_POST['wpa_fields'] : [];
        foreach ( $groups as $group ) {
            foreach ( (array) ( $group['fields'] ?? [] ) as $field_key => $field ) {
                if ( empty( $field['required'] ) ) continue;

                $type = $field['type'] ?? 'text';

                if ( $type === 'checkbox' ) {
                    if ( empty( $posted[ $field_key ] ) ) {
                        wc_add_notice( sprintf( __( 'Please select at least one option for "%s".' ), $field['title'] ), 'error' );
                        $valid = false;
                    }
                } elseif ( in_array( $type, [ 'select', 'radio', 'image', 'color' ], true ) ) {
                    if ( empty( $posted[ $field_key ] ) ) {
                        wc_add_notice( sprintf( __( 'Please select an option for "%s".' ), $field['title'] ), 'error' );
                        $valid = false;
                    }
                } elseif ( $type === 'file' ) {
                    $url = trim( $posted[ $field_key ] ?? '' );
                    if ( empty( $url ) ) {
                        wc_add_notice( sprintf( __( 'Please upload a file for "%s".' ), $field['title'] ), 'error' );
                        $valid = false;
                    }
                } else {
                    if ( trim( $posted[ $field_key ] ?? '' ) === '' ) {
                        wc_add_notice( sprintf( __( '"%s" is required.' ), $field['title'] ), 'error' );
                        $valid = false;
                    }
                }
            }
        }

        return $valid;
    }

}
