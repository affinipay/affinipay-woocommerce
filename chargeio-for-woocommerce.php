<?php
/*
 * Plugin Name: ChargeIO for WooCommerce
 * Description: Use ChargeIO for collecting credit card payments on WooCommerce.
 * Version: 1.0
 * Author: Domenic Schiera
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CIO4WC {

    public function __construct() {
        global $wpdb;

        // Include ChargeIO Methods
        include_once( 'classes/class-cio4wc_api.php' );

        // Include Database Manipulation Methods
        include_once( 'classes/class-cio4wc_db.php' );

        // Include Customer Profile Methods
        include_once( 'classes/class-cio4wc_customer.php' );

        // Grab settings
        $this->settings = get_option( 'woocommerce_cio4wc_settings', array() );

        // Add default values for fresh installs
        $this->settings['testmode']                 = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
        $this->settings['test_publishable_key']     = isset( $this->settings['test_publishable_key'] ) ? $this->settings['test_publishable_key'] : '';
        $this->settings['test_secret_key']          = isset( $this->settings['test_secret_key'] ) ? $this->settings['test_secret_key'] : '';
        $this->settings['live_publishable_key']     = isset( $this->settings['live_publishable_key'] ) ? $this->settings['live_publishable_key'] : '';
        $this->settings['live_secret_key']          = isset( $this->settings['live_secret_key'] ) ? $this->settings['live_secret_key'] : '';
        $this->settings['saved_cards']              = isset( $this->settings['saved_cards'] ) ? $this->settings['saved_cards'] : 'yes';

        // API Info
        $this->settings['publishable_key']          = $this->settings['testmode'] == 'yes' ? $this->settings['test_publishable_key'] : $this->settings['live_publishable_key'];
        $this->settings['secret_key']               = $this->settings['testmode'] == 'yes' ? $this->settings['test_secret_key'] : $this->settings['live_secret_key'];
        $this->settings['account']               = $this->settings['testmode'] == 'yes' ? $this->settings['test_account'] : $this->settings['live_account'];

        // Database info location
        $this->settings['chargeio_db_location']       = $this->settings['testmode'] == 'yes' ? '_chargeio_test_customer_info' : '_chargeio_live_customer_info';

        // Hooks
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_chargeio_gateway' ) );
        add_action( 'woocommerce_order_status_processing_to_completed', array( $this, 'order_status_completed' ) );

        // Localization
        load_plugin_textdomain( 'chargeio-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Add ChargeIO Gateway to WooCommerces list of Gateways
     *
     * @access      public
     * @param       array $methods
     * @return      array
     */
    public function add_chargeio_gateway( $methods ) {
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        // Include payment gateway
        include_once( 'classes/class-cio4wc_gateway.php' );

        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            include_once( 'classes/class-cio4wc_subscriptions_gateway.php' );

            $methods[] = 'CIO4WC_Subscriptions_Gateway';
        } else {
            $methods[] = 'CIO4WC_Gateway';
        }

        return $methods;
    }

    /**
     * Localize ChargeIO error messages
     *
     * @access      protected
     * @param       Exception $e
     * @return      string
     */
    public function get_error_message( $e ) {

        switch ( $e->getMessage() ) {

            // Messages from ChargeIO API
            case 'incorrect_number':
                $message = __( 'Your card number is incorrect.', 'chargeio-for-woocommerce' );
                break;
            case 'invalid_number':
                $message = __( 'Your card number is not a valid credit card number.', 'chargeio-for-woocommerce' );
                break;
            case 'invalid_expiry_month':
                $message = __( 'Your card\'s expiration month is invalid.', 'chargeio-for-woocommerce' );
                break;
            case 'invalid_expiry_year':
                $message = __( 'Your card\'s expiration year is invalid.', 'chargeio-for-woocommerce' );
                break;
            case 'invalid_cvc':
                $message = __( 'Your card\'s security code is invalid.', 'chargeio-for-woocommerce' );
                break;
            case 'expired_card':
                $message = __( 'Your card has expired.', 'chargeio-for-woocommerce' );
                break;
            case 'incorrect_cvc':
                $message = __( 'Your card\'s security code is incorrect.', 'chargeio-for-woocommerce' );
                break;
            case 'incorrect_zip':
                $message = __( 'Your zip code failed validation.', 'chargeio-for-woocommerce' );
                break;
            case 'card_declined':
                $message = __( 'Your card was declined.', 'chargeio-for-woocommerce' );
                break;

            // Messages from CIO4WC
            case 'cio4wc_problem_connecting':
            case 'cio4wc_empty_response':
            case 'cio4wc_invalid_response':
                $message = __( 'There was a problem connecting to the payment gateway.', 'chargeio-for-woocommerce' );
                break;

            // Generic failed order
            default:
                $message = __( 'Failed to process the order, please try again later.', 'chargeio-for-woocommerce' );
        }

        return $message;
    }

    /**
     * Process the captured payment when changing order status to completed
     *
     * @access      public
     * @param       int $order_id
     * @return      mixed
     */
    public function order_status_completed( $order_id = null ) {

        if ( ! $order_id ) {
            $order_id = $_POST['order_id'];
        }

        // `_cio4wc_capture` added in 1.35, let `capture` last for a few more updates before removing
        if ( get_post_meta( $order_id, '_cio4wc_capture', true ) || get_post_meta( $order_id, 'capture', true ) ) {

            $order = new WC_Order( $order_id );
            $params = array(
                'amount' => isset( $_POST['amount'] ) ? $_POST['amount'] : $order->order_total * 100,
                'expand[]' => 'balance_transaction',
            );

            try {
                $charge = CIO4WC_API::capture_charge( $order->transaction_id, $params );

                if ( $charge ) {
                    $order->add_order_note(
                        sprintf(
                            __( '%s payment captured.', 'chargeio-for-woocommerce' ),
                            get_class( $this )
                        )
                    );

                    // Save ChargeIO fee
                    if ( isset( $charge->balance_transaction ) && isset( $charge->balance_transaction->fee ) ) {
                        $chargeio_fee = number_format( $charge->balance_transaction->fee / 100, 2, '.', '' );
                        update_post_meta( $order_id, 'ChargeIO Fee', $chargeio_fee );
                    }
                }
            } catch ( Exception $e ) {
                $order->add_order_note(
                    sprintf(
                        __( '%s payment failed to capture. %s', 'chargeio-for-woocommerce' ),
                        get_class( $this ),
                        $this->get_error_message( $e )
                    )
                );
            }
        }
    }
}

$GLOBALS['cio4wc'] = new CIO4WC();

/**
 * Wrapper of wc_get_template to relate directly to cio4wc
 *
 * @param       string $template_name
 * @param       array $args
 * @return      string
 */
function cio4wc_get_template( $template_name, $args = array() ) {
    $template_path = WC()->template_path() . '/cio4wc/';
    $default_path = plugin_dir_path( __FILE__ ) . '/templates/';

    return wc_get_template( $template_name, $args, $template_path, $default_path );
}

/**
 * Helper function to find the key of a nested value
 *
 * @param       string $needle
 * @param       array $haystack
 * @return      mixed
 */
if ( ! function_exists( 'recursive_array_search' ) ) {
    function recursive_array_search( $needle, $haystack ) {

        foreach ( $haystack as $key => $value ) {

            if ( $needle === $value || ( is_array( $value ) && recursive_array_search( $needle, $value ) !== false ) ) {
                return $key;
            }
        }
        return false;
    }
}
