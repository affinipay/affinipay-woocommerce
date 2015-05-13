<?php
/**
 * The Template for displaying the credit card form on the checkout page
 *
 * Override this template by copying it to yourtheme/woocommerce/cio4wc/payment-fields.php
 *
 * @author      Domenic Schiera
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $cio4wc;

// Add notification to the user that this will fail miserably if they attempt it.
echo '<noscript>';
    printf( __( '%s payment does not work without Javascript. Please enable Javascript or use a different payment method.', 'chargeio-for-woocommerce' ), $cio4wc->settings['title'] );
echo '</noscript>';

// Payment method description
if ( $cio4wc->settings['description'] ) {
    echo '<p class="cio4wc-description">' .  $cio4wc->settings['description'] . '</p>';
}

// Get user database object
$chargeio_customer_info = get_user_meta( get_current_user_id(), $cio4wc->settings['chargeio_db_location'], true );

if ( is_user_logged_in() && $chargeio_customer_info && isset( $chargeio_customer_info['cards'] ) && count( $chargeio_customer_info['cards'] ) && $cio4wc->settings['saved_cards'] === 'yes' ) :

    // Add option to use a saved card
    foreach ( $chargeio_customer_info['cards'] as $i => $credit_card ) :
        $checked = ( $chargeio_customer_info['default_card'] == $credit_card['id'] ) ? ' checked' : '';

        if ( $i === 0 && $chargeio_customer_info['default_card'] === '' ) {
            $checked = ' checked';
        }
    ?>

        <input type="radio" id="chargeio_card_<?php echo $i; ?>" name="cio4wc_card" value="<?php echo $i; ?>"<?php echo $checked; ?>>
        <label for="chargeio_card_<?php echo $i; ?>"><?php printf( __( 'Card ending with %s (%s/%s)', 'chargeio-for-woocommerce' ), $credit_card['last4'], $credit_card['exp_month'], $credit_card['exp_year'] ); ?></label><br>

    <?php endforeach; ?>

    <input type="radio" id="new_card" name="cio4wc_card" value="new">
    <label for="new_card"><?php _e( 'Use a new credit card', 'chargeio-for-woocommerce' ); ?></label>

<?php endif; ?>
