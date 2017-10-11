<?php
/**
 * Functions for interfacing with ChargeIO's API
 *
 * @class       CIO4WC_API
 * @version     1.5
 * @author      AffiniPay, LLC
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CIO4WC_API {
    public static $api_endpoint = 'https://api.chargeio.com/v1/';

    /**
     * Create customer on chargeio servers
     *
     * @access      public
     * @param       int $user_id
     * @param       array $customer_data
     * @return      array
     */
    public static function save_card( $user_id, $customer_data ) {

        // Create a card on ChargeIO servers
        $token['token_id'] = $customer_data['card'];
        $card = CIO4WC_API::post_data( $token, 'cards' );

        //set this card as the active card.
        $active_card = $card->id;

        // Save users customer information for later use
        $customerArray = array(
            'customer_id'   => $user_id,
            'card'          => array(
                'id'            => $card->id,
                'brand'         => $card->card_type,
                'last4'         => substr($card->number, -4),
                'exp_year'      => $card->exp_year,
                'exp_month'     => $card->exp_month
            ),
            'default_card'  => $active_card
        );
        CIO4WC_DB::update_customer( $user_id, $customerArray );

        return $card;
    }

    /**
     * Get customer from chargeio servers
     *
     * @access      public
     * @param       string $customer_id
     * @return      array
     */
    public static function get_customer( $customer_id ) {
        return CIO4WC_API::get_data( 'customers/' . $customer_id );
    }

    /**
     * Update customer on chargeio servers
     *
     * @access      public
     * @param       string $customer_id
     * @param       array $request
     * @return      array
     */
    public static function update_customer( $customer_id, $customer_data ) {
        return CIO4WC_API::post_data( $customer_data, 'customers/' . $customer_id );
    }

    /**
     * Delete card from chargeio servers
     *
     * @access      public
     * @param       int $user_id
     * @param       int $position
     * @return      array
     */
    public static function delete_card( $user_id, $position = 0 ) {
        global $cio4wc;

        $user_meta = get_user_meta( $user_id, $cio4wc->settings['chargeio_db_location'], true );

        CIO4WC_DB::delete_customer( $user_id, array( 'card' => $user_meta['cards'][ $position ]['id'] ) );

        return CIO4WC_API::delete_data( 'cards/' . $user_meta['cards'][ $position ]['id'] );
    }

    /**
     * Create charge on chargeio servers
     *
     * @access      public
     * @param       array $charge_data
     * @return      array
     */
    public static function create_charge( $charge_data ) {
        global $cio4wc;
        $charge_data['account_id'] = $cio4wc->settings['account'];
        return CIO4WC_API::post_data( $charge_data );
    }

    /**
     * Capture charge on chargeio servers
     *
     * @access      public
     * @param       string $transaction_id
     * @param       array $charge_data
     * @return      array
     */
    public static function capture_charge( $transaction_id, $charge_data ) {
        return CIO4WC_API::post_data( $charge_data, 'charges/' . $transaction_id . '/capture' );
    }

    /**
     * Create refund on chargeio servers
     *
     * @access      public
     * @param       string $transaction_id
     * @param       array $refund_data
     * @return      array
     */
    public static function create_refund( $transaction_id, $refund_data ) {
        return CIO4WC_API::post_data( $refund_data, 'charges/' . $transaction_id . '/refund' );
    }

    /**
     * Get data from ChargeIO's servers by passing an API endpoint
     *
     * @access      public
     * @param       string $get_location
     * @return      array
     */
    public static function get_data( $get_location ) {
        global $cio4wc;

        $response = wp_remote_get( self::$api_endpoint . $get_location, array(
            'method'        => 'GET',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $cio4wc->settings['secret_key'] . ':' ),
            ),
            'timeout'       => 70,
            'sslverify'     => false,
            'user-agent'    => 'WooCommerce-ChargeIO',
        ) );

        return CIO4WC_API::parse_response( $response );
    }

    /**
     * Post data to ChargeIO's servers by passing data and an API endpoint
     *
     * @access      public
     * @param       array $post_data
     * @param       string $post_location
     * @return      array
     */
    public static function post_data( $post_data, $post_location = 'charges' ) {
        global $cio4wc;

        $response = wp_remote_post( self::$api_endpoint . $post_location, array(
            'method'        => 'POST',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $cio4wc->settings['secret_key'] . ':' ),
                'Content-Type'  => 'application/json'
            ),
            'body'          => json_encode($post_data),
            'timeout'       => 70,
            'sslverify'     => false,
            //'user-agent'    => 'WooCommerce-ChargeIO',
        ) );

            return CIO4WC_API::parse_response( $response );
    }

    /**
     * Delete data from ChargeIO's servers by passing an API endpoint
     *
     * @access      public
     * @param       string $delete_location
     * @return      array
     */
    public static function delete_data( $delete_location ) {
        global $cio4wc;

        $response = wp_remote_post( self::$api_endpoint . $delete_location, array(
            'method'        => 'DELETE',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $cio4wc->settings['secret_key'] . ':' ),
            ),
            'timeout'       => 70,
            'sslverify'     => false,
            'user-agent'    => 'WooCommerce-ChargeIO',
        ) );

        return CIO4WC_API::parse_response( $response );
    }

    /**
     * Parse ChargeIO's response after interacting with the API
     *
     * @access      public
     * @param       array $response
     * @return      array
     */
    public static function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'cio4wc_problem_connecting' );
        }

        if ( empty( $response['body'] ) ) {
            throw new Exception( 'cio4wc_empty_response' );
        }

        $parsed_response = json_decode( $response['body'] );

        // Handle response
        if ( ! empty( $parsed_response->messages ) && ! empty( $parsed_response->messages[0]->code ) ) {
            throw new Exception( $parsed_response->messages[0]->code );
        } elseif ( empty( $parsed_response->id ) ) {
            throw new Exception( 'cio4wc_invalid_response' );
        }

        return $parsed_response;
    }
}
