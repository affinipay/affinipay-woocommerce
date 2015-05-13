<?php
/**
 * Customer related modifications and templates
 *
 * @class       CIO4WC_Customer
 * @version     1.0
 * @author      Domenic Schiera
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class CIO4WC_Customer {

    public function __construct() {

        // Hooks
        add_action( 'woocommerce_after_my_account', array( $this, 'account_saved_cards' ) );
        add_action( 'show_user_profile', array( $this, 'add_customer_profile' ), 20 );
        add_action( 'edit_user_profile', array( $this, 'add_customer_profile' ), 20 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Complete necessary actions and display
     * notifications at the top of the page
     *
     * @access      public
     * @return      void
     */
    public function admin_notices() {
        global $pagenow, $profileuser;

        // If we're on the profile page
        if ( $pagenow === 'profile.php' ) {

            if ( ! empty( $_GET['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'cio4wc_action' ) ) {

                // Delete test data
                if ( $_GET['action'] === 'delete_test_data' ) {

                    // Delete test data if the action has been confirmed
                    if ( ! empty( $_GET['confirm'] ) && $_GET['confirm'] === 'yes' ) {

                        $result = delete_user_meta( $profileuser->ID, '_chargeio_test_customer_info' );

                        if ( $result ) {
                            ?>
                            <div class="updated">
                                <p><?php _e( 'ChargeIO customer data successfully deleted.', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="error">
                                <p><?php _e( 'Unable to delete ChargeIO customer data', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        }
                    }

                    // Ask for confimation before we actually delete data
                    else {
                        ?>
                        <div class="error">
                            <p><?php _e( 'Are you sure you want to delete customer test data? This action cannot be undone.', 'chargeio-for-woocommerce' ); ?></p>
                            <p>
                                <a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_test_data&confirm=yes' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete', 'chargeio-for-woocommerce' ); ?></a>
                                <a href="<?php echo admin_url( 'profile.php' ); ?>" class="button"><?php _e( 'Cancel', 'chargeio-for-woocommerce' ); ?></a>
                            </p>
                        </div>
                        <?php
                    }
                } elseif ( $_GET['action'] === 'delete_live_data' ) {

                    // Delete live data if the action has been confirmed
                    if ( ! empty( $_GET['confirm'] ) && $_GET['confirm'] === 'yes' ) {

                        $result = delete_user_meta( $profileuser->ID, '_chargeio_live_customer_info' );

                        if ( $result ) {
                            ?>
                            <div class="updated">
                                <p><?php _e( 'ChargeIO customer data successfully deleted.', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="error">
                                <p><?php _e( 'Unable to delete ChargeIO customer data', 'chargeio-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        }
                    }

                    // Ask for confimation before we actually delete data
                    else {
                        ?>
                        <div class="error">
                            <p><?php _e( 'Are you sure you want to delete customer live data? This action cannot be undone.', 'chargeio-for-woocommerce' ); ?></p>
                            <p>
                                <a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_live_data&confirm=yes' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete', 'chargeio-for-woocommerce' ); ?></a>
                                <a href="<?php echo admin_url( 'profile.php' ); ?>" class="button"><?php _e( 'Cancel', 'chargeio-for-woocommerce' ); ?></a>
                            </p>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    /**
     * Add to the customer profile
     *
     * @access      public
     * @param       WP_User $user
     * @return      void
     */
    public function add_customer_profile( $user ) {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <table class="form-table">
            <tr>
                <th>Delete ChargeIO Test Data</th>
                <td>
                    <p>
                        <a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_test_data' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete Test Data', 'chargeio-for-woocommerce' ); ?></a>
                        <span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete ChargeIO test data for this customer, make sure to back up your database.', 'chargeio-for-woocommerce' ); ?></span>
                    </p>
                </td>
            </tr>
            <tr>
                <th>Delete ChargeIO Live Data</th>
                <td>
                    <p>
                        <a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_live_data' ), 'cio4wc_action' ); ?>" class="button"><?php _e( 'Delete Live Data', 'chargeio-for-woocommerce' ); ?></a>
                        <span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete ChargeIO live data for this customer, make sure to back up your database.', 'chargeio-for-woocommerce' ); ?></span>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Gives front-end view of saved cards in the account page
     *
     * @access      public
     * @return      void
     */
    public function account_saved_cards() {
        global $cio4wc;

        if ( $cio4wc->settings['saved_cards'] === 'yes' ) {

            // If user requested to delete a card, delete it
            if ( isset( $_POST['delete_card'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'cio4wc_delete_card' ) ) {
                CIO4WC_API::delete_card( get_current_user_id(), intval( $_POST['delete_card'] ) );
            }

            $user_meta    = get_user_meta( get_current_user_id(), $cio4wc->settings['chargeio_db_location'], true );
            $credit_cards = isset( $user_meta['cards'] ) ? $user_meta['cards'] : false;

            $args = array(
                'user_meta'    => $user_meta,
                'credit_cards' => $credit_cards,
            );

            cio4wc_get_template( 'saved-cards.php', $args );
        }
    }
}

new CIO4WC_Customer();
