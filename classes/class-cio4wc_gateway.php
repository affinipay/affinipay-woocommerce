<?php
/**
 * ChargeIO Gateway
 *
 * Provides a ChargeIO Payment Gateway.
 *
 * @class       CIO4WC_Gateway
 * @extends     WC_Payment_Gateway_CC
 * @version     1.5
 * @package     WooCommerce/Classes/Payment
 * @author      AffiniPay, LLC
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CIO4WC_Gateway extends WC_Payment_Gateway_CC {
    protected $order                     = null;
    protected $form_data                 = null;
    protected $transaction_id            = null;
    protected $transaction_error_message = null;

    public function __construct() {
        global $cio4wc;

        $this->id           = 'cio4wc';
        $this->method_title = 'ChargeIO for WooCommerce';
        $this->has_fields   = true;
        $this->supports     = array(
            'default_credit_card_form',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'refunds',
            'tokenization'
        );

        // Init settings
        $this->init_form_fields();
        $this->init_settings();

        // Use settings
        $this->enabled     = $this->settings['enabled'];
        $this->title       = $this->settings['title'];
        $this->description = $this->settings['description'];

        // Get current user information
        $this->chargeio_customer_info = get_user_meta( get_current_user_id(), $cio4wc->settings['chargeio_db_location'], true );

        // Add an icon with a filter for customization
        $icon_url = apply_filters( 'cio4wc_icon_url', plugins_url( 'assets/images/credits.png', dirname(__FILE__) ) );
        if ( $icon_url ) {
            $this->icon = $icon_url;
        }

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'woocommerce_credit_card_form_start', array( $this, 'before_cc_form' ) );
    }

    /**
     * Check if this gateway is enabled and all dependencies are fine.
     * Disable the plugin if dependencies fail.
     *
     * @access      public
     * @return      bool
     */
    public function is_available() {
        global $cio4wc;

        if ( $this->enabled === 'no' ) {
            return false;
        }

        // ChargeIO won't work without keys
        if ( ! $cio4wc->settings['publishable_key'] && ! $cio4wc->settings['secret_key'] ) {
            return false;
        }

        // Allow smaller orders to process for WooCommerce Bookings
        if ( is_checkout_pay_page() ) {
            $order_key = urldecode( $_GET['key'] );
            $order_id  = absint( get_query_var( 'order-pay' ) );
            $order     = new WC_Order( $order_id );

            if ( $order->get_id() == $order_id && $order->order_key == $order_key && $this->get_order_total() * 100 < 50) {
                return false;
            }
        }

        // ChargeIO will only process orders of at least 50 cents otherwise
        elseif ( $this->get_order_total() * 100 < 50 ) {
            return false;
        }

        return true;
    }

    /**
     * Send notices to users if requirements fail, or for any other reason
     *
     * @access      public
     * @return      bool
     */
    public function admin_notices() {
        global $cio4wc, $pagenow, $wpdb;

        if ( $this->enabled == 'no') {
            return false;
        }

        // Check for API Keys
        if ( ! $cio4wc->settings['publishable_key'] && ! $cio4wc->settings['secret_key'] ) {
            echo '<div class="error"><p>' . __( 'ChargeIO needs API Keys to work, please find your secret and publishable keys in the <a href="https://secure.affinipay.com/settings/advanced" target="_blank">Developer Settings</a>.', 'chargeio-for-woocommerce' ) . '</p></div>';
            return false;
        }

        // Force SSL on production
        if ( $this->settings['testmode'] == 'no' && get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) {
            echo '<div class="error"><p>' . __( 'ChargeIO needs SSL in order to be secure. Read mode about forcing SSL on checkout in <a href="http://docs.woothemes.com/document/ssl-and-https/" target="_blank">the WooCommerce docs</a>.', 'chargeio-for-woocommerce' ) . '</p></div>';
            return false;
        }

        // Add notices for admin page
        if ( $pagenow === 'admin.php' ) {
            $options_base = 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( get_class( $this ) );

            if ( ! empty( $_GET['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'cio4wc_action' ) ) {

                // Delete all test data
                if ( $_GET['action'] === 'delete_test_data' ) {

                    // Delete test data if the action has been confirmed
                    if ( ! empty( $_GET['confirm'] ) && $_GET['confirm'] === 'yes' ) {

                        $result = $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_chargeio_test_customer_info' ) );

                        if ( $result !== false ) :
                            ?>
                            <div class="updated">
                                <p><?php _e( 'ChargeIO Test Data successfully deleted.', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        else :
                            ?>
                            <div class="error">
                                <p><?php _e( 'Unable to delete ChargeIO Test Data', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        endif;
                    }

                    // Ask for confimation before we actually delete data
                    else {
                        ?>
                        <div class="error">
                            <p><?php _e( 'Are you sure you want to delete all test data? This action cannot be undone.', 'chargeio-for-woocommerce' ); ?></p>
                            <p>
                                <a href="<?php echo wp_nonce_url( admin_url( $options_base . '&action=delete_test_data&confirm=yes' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete', 'chargeio-for-woocommerce' ); ?></a>
                                <a href="<?php echo admin_url( $options_base ); ?>" class="button"><?php _e( 'Cancel', 'chargeio-for-woocommerce' ); ?></a>
                            </p>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access      public
     * @return      void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Enable/Disable', 'chargeio-for-woocommerce' ),
                'label'         => __( 'Enable ChargeIO for WooCommerce', 'chargeio-for-woocommerce' ),
                'default'       => 'yes'
            ),
            'title' => array(
                'type'          => 'text',
                'title'         => __( 'Title', 'chargeio-for-woocommerce' ),
                'description'   => __( 'This controls the title which the user sees during checkout.', 'chargeio-for-woocommerce' ),
                'default'       => __( 'Credit Card Payment', 'chargeio-for-woocommerce' )
            ),
            'description' => array(
                'type'          => 'textarea',
                'title'         => __( 'Description', 'chargeio-for-woocommerce' ),
                'description'   => __( 'This controls the description which the user sees during checkout.', 'chargeio-for-woocommerce' ),
                'default'       => '',
            ),
            'additional_fields' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Additional Fields', 'chargeio-for-woocommerce' ),
                'description'   => __( 'Add a Billing ZIP and a Name on Card for ChargeIO authentication purposes. This is only neccessary if you check the "Only ship to the users billing address" box on WooCommerce Shipping settings.', 'chargeio-for-woocommerce' ),
                'label'         => __( 'Use Additional Fields', 'chargeio-for-woocommerce' ),
                'default'       => 'no'
            ),
            'saved_cards' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Saved Cards', 'chargeio-for-woocommerce' ),
                'description'   => __( 'Allow customers to use saved cards for future purchases.', 'chargeio-for-woocommerce' ),
                'default'       => 'yes',
            ),
            'testmode' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Test Mode', 'chargeio-for-woocommerce' ),
                'description'   => __( 'Use the test mode on ChargeIO\'s dashboard to verify everything works before going live.', 'chargeio-for-woocommerce' ),
                'label'         => __( 'Turn on testing', 'chargeio-for-woocommerce' ),
                'default'       => 'no'
            ),
            'test_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'ChargeIO API Test Secret key', 'chargeio-for-woocommerce' ),
                'default'       => '',
            ),
            'test_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'ChargeIO API Test Publishable key', 'chargeio-for-woocommerce' ),
                'default'       => '',
            ),
            'test_account'  => array(
                'type'      => 'text',
                'title'     => __( 'ChargeIO API Test Account', 'chargeio-for-woocommerce' ),
                'default'   => '',
            ),
            'live_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'ChargeIO API Live Secret key', 'chargeio-for-woocommerce' ),
                'default'       => '',
            ),
            'live_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'ChargeIO API Live Publishable key', 'chargeio-for-woocommerce' ),
                'default'       => '',
            ),
            'live_account'  => array(
                'type'      => 'text',
                'title'     => __( 'ChargeIO API Live Account', 'chargeio-for-woocommerce' ),
                'default'   => '',
            ),
        );
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @access      public
     * @return      void
     */
    public function admin_options() {

        $options_base = 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( get_class( $this ) );
        ?>
        <h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>
        <p><?php _e( 'Allows Credit Card payments through <a href="https://chargeio.com/">ChargeIO</a>. You can manage your API Keys in your <a href="https://secure.affinipay.com/settings/advanced">Developer Settings</a>.', 'chargeio-for-woocommerce' ); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <tr>
                <th><?php _e( 'Delete ChargeIO Test Data', 'chargeio-for-woocommerce' ); ?></th>
                <td>
                    <p>
                        <a href="<?php echo wp_nonce_url( admin_url( $options_base . '&action=delete_test_data' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete all Test Data', 'chargeio-for-woocommerce' ); ?></a>
                        <span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete all ChargeIO test customer data, make sure to back up your database.', 'chargeio-for-woocommerce' ); ?></span>
                    </p>
                </td>
            </tr>
        </table>

        <?php
    }

    /**
     * Load dependent scripts
     * - chargeio.js from the chargeio servers
     * - cio4wc.js for handling the data to submit to chargeio
     *
     * @access      public
     * @return      void
     */
    public function load_scripts() {
        global $cio4wc;


        // Main chargeio js
        wp_dequeue_script( 'stripe');
        wp_enqueue_script( 'chargeio', 'https://api.chargeio.com/assets/api/v1/chargeio.min.js', false, '1.0', true );

        // Plugin js
        wp_enqueue_script( 'cio4wc_js', plugins_url( 'assets/js/cio4wc.js', dirname( __FILE__ ) ), array( 'chargeio', 'wc-credit-card-form' ), '1.36', true );

        // Add data that cio4wc.js needs
        $cio4wc_info = array(
            'publishableKey'    => $cio4wc->settings['publishable_key'],
            'savedCardsEnabled' => $cio4wc->settings['saved_cards'] === 'yes' ? true : false,
            'hasCard'           => ( $this->chargeio_customer_info && count( $this->chargeio_customer_info['cards'] ) ) ? true : false
        );

        // If we're on the pay page, ChargeIO needs the address
        if ( is_checkout_pay_page() ) {
            $order_key = urldecode( $_GET['key'] );
            $order_id  = absint( get_query_var( 'order-pay' ) );
            $order     = new WC_Order( $order_id );

            if ( $order->get_id() == $order_id && $order->order_key == $order_key ) {
                $cio4wc_info['billing_name']      = $order->billing_first_name . ' ' . $order->billing_last_name;
                $cio4wc_info['billing_address_1'] = $order->billing_address_1;
                $cio4wc_info['billing_address_2'] = $order->billing_address_2;
                $cio4wc_info['billing_city']      = $order->billing_city;
                $cio4wc_info['billing_state']     = $order->billing_state;
                $cio4wc_info['billing_postcode']  = $order->billing_postcode;
                $cio4wc_info['billing_country']   = $order->billing_country;
            }
        }

        wp_localize_script( 'cio4wc_js', 'cio4wc_info', $cio4wc_info );
    }

    /**
     * Add additional fields just above the credit card form
     *
     * @access      public
     * @param       string $gateway_id
     * @return      void
     */
    public function before_cc_form( $gateway_id ) {
        global $cio4wc;

        // Ensure that we're only outputting this for the cio4wc gateway
        if ( $gateway_id === $this->id && $cio4wc->settings['additional_fields'] == 'yes' ) {
            woocommerce_form_field( 'billing-name', array(
                'label'             => __( 'Name on Card', 'chargeio-for-woocommerce' ),
                'required'          => true,
                'class'             => array( 'form-row-first' ),
                'input_class'       => array( 'cio4wc-billing-name' ),
                'custom_attributes' => array(
                    'autocomplete'  => 'off'
                )
            ) );

            woocommerce_form_field( 'billing-zip', array(
                'label'             => __( 'Billing Zip', 'chargeio-for-woocommerce' ),
                'required'          => true,
                'class'             => array( 'form-row-last' ),
                'input_class'       => array( 'cio4wc-billing-zip' ),
                'clear'             => true,
                'custom_attributes' => array(
                    'autocomplete'  => 'off'
                )
            ) );
        }
    }

    /**
     * Output payment fields, optional additional fields and woocommerce cc form
     *
     * @access      public
     * @return      void
     */
    public function payment_fields() {

        // Output the saved card data
        cio4wc_get_template( 'payment-fields.php' );
        $this->form();
    }

    /**
     * Validate credit card form fields
     *
     * @access      public
     * @return      void
     */
    public function validate_fields() {

        $form = array(
            'card-number'   => isset( $_POST['cio4wc-card-number'] ) ? $_POST['cio4wc-card-number'] : null,
            'card-expiry'   => isset( $_POST['cio4wc-card-expiry'] ) ? $_POST['cio4wc-card-expiry'] : null,
            'card-cvc'      => isset( $_POST['cio4wc-card-cvc'] ) ? $_POST['cio4wc-card-cvc'] : null,
        );

        if ( $form['card-number'] ) {
            $field = __( 'Credit Card Number', 'chargeio-for-woocommerce' );

            wc_add_notice( $this->get_form_error_message( $field, $form['card-number'] ), 'error' );
        }
        if ( $form['card-expiry'] ) {
            $field = __( 'Credit Card Expiration', 'chargeio-for-woocommerce' );

            wc_add_notice( $this->get_form_error_message( $field, $form['card-expiry'] ), 'error' );
        }
        if ( $form['card-cvc'] ) {
            $field = __( 'Credit Card CVC', 'chargeio-for-woocommerce' );

            wc_add_notice( $this->get_form_error_message( $field, $form['card-cvc'] ), 'error' );
        }
    }

    /**
     * Get error message for form validator given field name and type of error
     *
     * @access      protected
     * @param       string $field
     * @param       string $type
     * @return      string
     */
    protected function get_form_error_message( $field, $type = 'undefined' ) {

        if ( $type === 'invalid' ) {
            return sprintf( __( 'Please enter a valid %s.', 'chargeio-for-woocommerce' ), "<strong>$field</strong>" );
        } else {
            return sprintf( __( '%s is a required field.', 'chargeio-for-woocommerce' ), "<strong>$field</strong>" );
        }
    }

    /**
     * Process the payment and return the result
     *
     * @access      public
     * @param       int $order_id
     * @return      array
     */
    public function process_payment( $order_id ) {

        if ( $this->send_to_chargeio( $order_id ) ) {
            $this->order_complete();

            $this->order->reduce_order_stock();

            WC()->cart->empty_cart();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $this->order )
            );

            return $result;
        } else {
            $this->payment_failed();

            // Add a generic error message if we don't currently have any others
            if ( wc_notice_count( 'error' ) == 0 ) {
                wc_add_notice( __( 'Transaction Error: Could not complete your payment.', 'chargeio-for-woocommerce' ), 'error' );
            }
        }
    }

    /**
     * Process refund
     *
     * Overriding refund method
     *
     * @access      public
     * @param       int $order_id
     * @param       float $amount
     * @param       string $reason
     * @return      mixed True or False based on success, or WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $this->order = new WC_Order( $order_id );
        $this->transaction_id = $this->order->get_transaction_id();

        if ( ! $this->transaction_id ) {
            return new WP_Error( 'cio4wc_refund_error',
                sprintf(
                    __( '%s Credit Card Refund failed because the Transaction ID is missing.', 'chargeio-for-woocommerce' ),
                    get_class( $this )
                )
            );
        }

        try {

            $refund_data = array();

            if ( ! $amount ) {
                return new WP_Error( 'cio4wc_refund_error',
                    sprintf(
                        __( '%s Credit Card Refund failed because no amount was specified.', 'chargeio-for-woocommerce' ),
                        get_class( $this )
                    )
                );
            }

            $refund_data['amount'] = $amount * 100;

            if ( $reason ) {
                $refund_data['reference'] = $reason;
            }

            // Send the refund to the ChargeIO API
            return CIO4WC_API::create_refund( $this->transaction_id, $refund_data );

        } catch ( Exception $e ) {
            $this->transaction_error_message = $cio4wc->get_error_message( $e );

            $this->order->add_order_note(
                sprintf(
                    __( '%s Credit Card Refund Failed with message: "%s"', 'chargeio-for-woocommerce' ),
                    get_class( $this ),
                    $this->transaction_error_message
                )
            );

            // Something failed somewhere, send a message.
            return new WP_Error( 'cio4wc_refund_error', $this->transaction_error_message );
        }

        return false;
    }

    /**
     * Send form data to ChargeIO
     * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
     *
     * @access      protected
     * @param       int $order_id
     * @return      bool
     */
    protected function send_to_chargeio( $order_id ) {
        global $cio4wc;

        // Get the order based on order_id
        $this->order = new WC_Order( $order_id );

        // Get the credit card details submitted by the form
        $this->form_data = $this->get_form_data();

        // If there are errors on the form, don't bother sending to ChargeIO.
        if ( $this->form_data['errors'] == 1 ) {
            return;
        }

        // Set up the charge for ChargeIO's servers
        try {

            // Allow for any type of charge to use the same try/catch config
            $this->charge_set_up();

            // Save ChargeIO fee
            if ( isset( $this->charge->balance_transaction ) && isset( $this->charge->balance_transaction->fee ) ) {
                $chargeio_fee = number_format( $this->charge->balance_transaction->fee / 100, 2, '.', '' );
                update_post_meta( $this->order->get_id(), 'ChargeIO Fee', $chargeio_fee );
            }

            return true;

        } catch ( Exception $e ) {

            // Stop page reload if we have errors to show
            unset( WC()->session->reload_checkout );

            $this->transaction_error_message = $cio4wc->get_error_message( $e );

            wc_add_notice( __( 'Error:', 'chargeio-for-woocommerce' ) . ' ' . $this->transaction_error_message, 'error' );

            return false;
        }
    }

    /**
     * Create a customer if the current user isn't already one
     * Retrieve a customer if one already exists
     * Add a card to a customer if necessary
     *
     * @access      protected
     * @return      array
     */
    protected function get_customer() {
        global $cio4wc;

        $output = array();
        $customer_info = get_user_meta( $this->order->get_user_id(), $cio4wc->settings['chargeio_db_location'], true );

        if ( $customer_info ) {

            // If the user is already registered on the chargeio servers, retreive their information
            //$customer = CIO4WC_API::get_customer( $customer_info['customer_id'] );

            // If the user doesn't have cards or is adding a new one
            if ( $this->form_data['chosen_card'] === 'new' ) {

                // Add new card on chargeio servers and make default
                $card = CIO4WC_API::save_card( $customer_info['customer_id'], array(
                    'card' => $this->form_data['token']
                ) );

                $output['card'] = $card->id;
            } else {
                $output['card'] = $customer_info['cards'][ intval( $this->form_data['chosen_card'] ) ]['id'];
            }

        } else {

            $user = get_userdata( $this->order->get_user_id() );

            // Allow options to be set without modifying sensitive data like token, email, etc
            $customer_data = apply_filters( 'cio4wc_customer_data', array(), $this->form_data, $this->order );

            // Set default customer description
            $customer_description = $user->user_login . ' (#' . $this->order->get_user_id() . ' - ' . $user->user_email . ') ' . $this->form_data['customer']['name']; // username (user_id - user_email) Full Name

            // Set up basics for customer
            $customer_data['description'] = apply_filters( 'cio4wc_customer_description', $customer_description, $this->form_data, $this->order );
            $customer_data['email']       = $this->form_data['customer']['billing_email'];
            $customer_data['card']        = $this->form_data['token'];

            // Create the customer in the api with the above data
            $card = CIO4WC_API::save_card( $this->order->get_user_id(), $customer_data );

            $output['card'] = $this->order->get_user_id();
        }

        // Set up charging data to include customer information
        $output['customer_id'] = $this->order->get_user_id();

        // Save data for cross-reference between ChargeIO Dashboard and WooCommerce
        update_post_meta( $this->order->get_id(), 'ChargeIO Customer Id', $customer->id );

        return $output;
    }

    /**
     * Get the charge's description
     *
     * @access      protected
     * @param       string $type Type of product being bought
     * @return      string
     */
    protected function get_charge_description( $type = 'simple' ) {
        $order_items = $this->order->get_items();

        // Set a default name, override with a product name if it exists for ChargeIO's dashboard
        if ( $type === 'subscription' ) {
            $product_name = __( 'Subscription', 'chargeio-for-woocommerce' );
        } else {
            $product_name = __( 'Purchases', 'chargeio-for-woocommerce' );
        }

        // Grab first viable product name and use it
        foreach ( $order_items as $key => $item ) {

            if ( $type === 'subscription' && isset( $item['subscription_status'] ) ) {
                $product_name = $item['name'];
                break;
            } elseif ( $type === 'simple' ) {
                $product_name = $item['name'];
                break;
            }
        }

        // Charge description
        $charge_description = sprintf(
            __( 'Payment for %s (Order: %s)', 'chargeio-for-woocommerce' ),
            $product_name,
            $this->order->get_order_number()
        );

        if ( $type === 'subscription' ) {
            return apply_filters( 'cio4wc_subscription_charge_description', $charge_description, $this->order );
        } else {
            return apply_filters( 'cio4wc_charge_description', $charge_description, $this->form_data, $this->order );
        }
    }

    /**
     * Mark the payment as failed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function payment_failed() {
        $this->order->add_order_note(
            sprintf(
                __( '%s payment failed with message: "%s"', 'chargeio-for-woocommerce' ),
                get_class( $this ),
                $this->transaction_error_message
            )
        );
    }

    /**
     * Mark the payment as completed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function order_complete() {

        if ( $this->order->get_status() == 'completed' ) {
            return;
        }

        $this->order->payment_complete( $this->transaction_id );

        $this->order->add_order_note(
            sprintf(
                __( '%s payment completed with Transaction Id of "%s"', 'chargeio-for-woocommerce' ),
                get_class( $this ),
                $this->transaction_id
            )
        );
    }

    /**
     * Retrieve the form fields
     *
     * @access      protected
     * @return      mixed
     */
    protected function get_form_data() {

        if ( $this->order && $this->order != null ) {
            return array(
                'amount'      => $this->get_order_total() * 100 . "",
                'currency'    => strtolower( $this->order->get_currency() ),
                'token'       => isset( $_POST['chargeio_token'] ) ? $_POST['chargeio_token'] : '',
                'chosen_card' => isset( $_POST['cio4wc_card'] ) ? $_POST['cio4wc_card'] : 'new',
                'customer'    => array(
                    'name'          => $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name(),
                    'billing_email' => $this->order->get_billing_email(),
                ),
                'errors'      => isset( $_POST['form_errors'] ) ? $_POST['form_errors'] : ''
            );
        }

        return false;
    }

    /**
     * Set up the charge that will be sent to ChargeIO
     *
     * @access      private
     * @return      void
     */
    private function charge_set_up() {
        global $cio4wc;

        $customer_info = get_user_meta( $this->order->get_user_id(), $cio4wc->settings['chargeio_db_location'], true );

        // Allow options to be set without modifying sensitive data like amount, currency, etc.
        $chargeio_charge_data = apply_filters( 'cio4wc_charge_data', array(), $this->form_data, $this->order );

        // Set up basics for charging
        $chargeio_charge_data['amount']   = $this->form_data['amount']; // amount in cents
        $chargeio_charge_data['currency'] = strtoupper($this->form_data['currency']);
        $chargeio_charge_data['auto_capture']  = 'true';

        // Make sure we only create customers if a user is logged in
        if ( is_user_logged_in() && $this->settings['saved_cards'] === 'yes' ) {

            // Add a customer or retrieve an existing one
            $customer = $this->get_customer();

            $chargeio_charge_data['method'] = $customer['card'];
            //$chargeio_charge_data['customer'] = $customer['customer_id'];

            // Update default card
            if ( count( $customer_info['cards'] ) && $this->form_data['chosen_card'] !== 'new' ) {
                $default_card = $customer_info['cards'][ intval( $this->form_data['chosen_card'] ) ]['id'];
                CIO4WC_DB::update_customer( $this->order->get_user_id(), array( 'default_card' => $default_card ) );
            }

        } else {

            // Set up one time charge
            $chargeio_charge_data['method'] = $this->form_data['token'];



        }

        // Charge description
        //$chargeio_charge_data['description'] = $this->get_charge_description();

        // Create the charge on ChargeIO's servers - this will charge the user's card
        $charge = CIO4WC_API::create_charge( $chargeio_charge_data );

        $this->charge = $charge;
        $this->transaction_id = $charge->id;
    }
}
