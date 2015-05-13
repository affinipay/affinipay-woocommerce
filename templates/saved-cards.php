<?php
/**
 * The Template for displaying the saved credit cards on the account page
 *
 * Override this template by copying it to yourtheme/woocommerce/cio4wc/saved-cards.php
 *
 * @author      Domenic Schiera
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If the customer has credit cards, output them
if ( $credit_cards ) :
?>
    <h2 id="saved-cards"><?php _e( 'Saved cards', 'chargeio-for-woocommerce' ); ?></h2>
    <table class="shop_table">
        <thead>
            <tr>
                <th><?php _e( 'Card ending in...', 'chargeio-for-woocommerce' ); ?></th>
                <th><?php _e( 'Expires', 'chargeio-for-woocommerce' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $credit_cards as $i => $credit_card ) : ?>
            <tr>
                <td><?php echo esc_html( $credit_card['last4'] ); ?></td>
                <td><?php echo esc_html( $credit_card['exp_month'] ) . '/' . esc_html( $credit_card['exp_year'] ); ?></td>
                <td>
                    <form action="#saved-cards" method="POST">
                        <?php wp_nonce_field( 'cio4wc_delete_card' ); ?>
                        <input type="hidden" name="delete_card" value="<?php echo esc_attr( $i ); ?>">
                        <input type="submit" value="<?php _e( 'Delete card', 'chargeio-for-woocommerce' ); ?>">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
endif;
