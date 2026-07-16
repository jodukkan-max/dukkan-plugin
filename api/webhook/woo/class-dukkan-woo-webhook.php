<?php

/**
 * The woocommerce webhook functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api/webhook/woo
 */

/**
 * The woocommerce webhook functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the woocommerce webhook stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api/webhook/woo
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Woo_Webhook {

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

        add_action( 'rest_api_init', array( $this, 'dukkan_plugin_shipping_status_webhook' ) );
		
	}

    /**
     * ─────────────────────────────────────────────
     *  STATUS MAPPING
     *  Map shipping-platform statuses → WooCommerce order statuses.
     *  WooCommerce built-in statuses: pending, processing, on-hold,
     *  completed, cancelled, refunded, failed, checkout-draft
     *  Add custom statuses here if you use plugins like "WooCommerce Order Status Manager".
     * ─────────────────────────────────────────────
     */
    public function dukkan_plugin_get_woo_order_status_map() {
        return apply_filters( 'dukkan_plugin_woo_order_status_map', array(
            // Shipping platform status  =>  WooCommerce status (without "wc-" prefix)
            'SCANNED_BY_HANDLER_AND_UNLOADED'                    => 'with-carrier',
            'SCANNED_BY_DRIVER_AND_IN_CAR'                    => 'out-for-delivery',
            'DELIVERED_TO_RECIPIENT'                  => 'completed',
            'CANCELLED'                  => 'cancelled',
        ) );
    }

    /**
     * ─────────────────────────────────────────────
     *  REGISTER REST ROUTE
     * ─────────────────────────────────────────────
     * https://yoursite.com/wp-json/dukkan-woo-webhook/v1/shipping-status
     */
    public function dukkan_plugin_shipping_status_webhook() {
        register_rest_route( 'dukkan-woo-webhook/v1', '/shipping-status', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'dukkan_plugin_handle_shipping_status_webhook' ),
            'permission_callback' => '__return_true', // Allow public access to this endpoint
        ) );
    }

    /**
     * ─────────────────────────────────────────────
     *  WEBHOOK CALLBACK
     *  Handle incoming webhook requests from the shipping platform.
     * ─────────────────────────────────────────────
     */
    public function dukkan_plugin_handle_shipping_status_webhook( WP_REST_Request $request ) {
        // ── 1. Authenticate ──────────────────────────────────────────────────────
        // $secret = $request->get_header( 'x-webhook-secret' );
        // if ( SWH_SECRET_TOKEN !== '' && $secret !== SWH_SECRET_TOKEN ) {
        //     $this->dukkan_plugin_webhook_log( 'AUTH_FAIL', 'Invalid or missing X-Webhook-Secret header.' );
        //     return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        // }

        // ── 2. Parse payload ─────────────────────────────────────────────────────
        $payload = $request->get_json_params();
    
        if ( empty( $payload ) ) {
            $this->dukkan_plugin_webhook_log( 'PARSE_FAIL', 'Empty or non-JSON payload received.' );
            return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
        }

        // ── 3. Extract required fields ───────────────────────────────────────────
        $barcode        = isset( $payload['barcode'] )       ? sanitize_text_field( $payload['barcode'] )       : '';
        $new_status     = isset( $payload['newStatus'] )     ? strtoupper( sanitize_text_field( $payload['newStatus'] ) ) : '';
        $invoice_number = isset( $payload['invoiceNumber'] ) ? sanitize_text_field( $payload['invoiceNumber'] ) : '';
        // $notes          = isset( $payload['notes'] )         ? sanitize_text_field( $payload['notes'] )         : '';
        // $driver_name    = isset( $payload['driverName'] )    ? sanitize_text_field( $payload['driverName'] )    : '';
        // $driver_phone   = isset( $payload['driverPhone'] )   ? sanitize_text_field( $payload['driverPhone'] )   : '';
        // $package_id     = isset( $payload['packageId'] )     ? intval( $payload['packageId'] )                  : 0;
        // $cod            = isset( $payload['cod'] )           ? floatval( $payload['cod'] )                      : 0;
        // $payment_type   = isset( $payload['paymentType'] )   ? sanitize_text_field( $payload['paymentType'] )   : '';
        if ( empty( $new_status ) ) {
            $this->dukkan_plugin_webhook_log( 'MISSING_FIELD', 'newStatus is missing from payload.', $payload );
            return new WP_REST_Response( array( 'error' => 'newStatus is required' ), 422 );
        }

        // ── 4. Find the WooCommerce order ─────────────────────────────────────────
        //    Strategy A: match by order meta "_shipping_barcode" or "_tracking_number"
        //    Strategy B: match by invoice number stored in order meta "_invoice_number"
        //    Strategy C: match by WooCommerce order ID embedded in the invoiceNumber
        //                e.g. "3465404-18422171-1" → try last segment as order ID
        $order = null;
        // Strategy C – invoice number as order ID (last resort)
        if ( ! $order && $invoice_number ) {
            $candidate = wc_get_order( $invoice_number );
            if ( $candidate ) {
                $order = $candidate;
            }
        }

        if ( ! $order ) {
            $this->dukkan_plugin_webhook_log( 'ORDER_NOT_FOUND', "Could not find WooCommerce order. barcode={$barcode}, invoice={$invoice_number}, packageId={$package_id}", $payload );
            return new WP_REST_Response( array( 'error' => 'Order not found' ), 404 );
        }

        // ── 5. Map shipping status → WooCommerce status ───────────────────────────
        $status_map   = $this->dukkan_plugin_get_woo_order_status_map();
        $wc_status    = isset( $status_map[ $new_status ] ) ? $status_map[ $new_status ] : '';
    
        if ( empty( $wc_status ) ) {
            $this->dukkan_plugin_webhook_log( 'STATUS_UNMAPPED', "No WooCommerce mapping for shipping status '{$new_status}'.", $payload );
            // Still 200 so the platform doesn't keep retrying; we just don't update.
            return new WP_REST_Response( array(
                'success' => true,
            ), 200 );
        }

        $order_id = $order->get_id();
 
        // ── 6. Update the order status ────────────────────────────────────────────
        $note = sprintf(
            __( 'Shipping platform update: %s', 'shipping-webhook-handler' ),
            $new_status
        );
    
        $order->update_status( $wc_status, $note );
        $order->update_meta_data( '_shipping_last_updated', current_time( 'mysql' ) );

        $order->save();
 
        $this->dukkan_plugin_webhook_log( 'SUCCESS', "Order #{$order_id} updated to '{$wc_status}' (shipping: {$new_status})." );
    
        return new WP_REST_Response( array(
            'success'     => true,
            'order_id'    => $order_id,
            'wc_status'   => $wc_status,
            'ship_status' => $new_status,
        ), 200 );

    }

    /**
     * ─────────────────────────────────────────────
     *  HELPER: Logger
     * ─────────────────────────────────────────────
     */
    public function dukkan_plugin_webhook_log( $code, $message, $context = array() ) {
        $logger  = wc_get_logger();
        $ctx     = array( 'source' => 'shipping-webhook-handler' );
        $entry   = "[{$code}] {$message}";
        if ( ! empty( $context ) ) {
            $entry .= ' | Payload: ' . wp_json_encode( $context );
        }
        if ( in_array( $code, array( 'AUTH_FAIL', 'PARSE_FAIL', 'MISSING_FIELD', 'ORDER_NOT_FOUND' ), true ) ) {
            $logger->error( $entry, $ctx );
        } else {
            $logger->info( $entry, $ctx );
        }
    }

}