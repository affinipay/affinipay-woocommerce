// Let jsHint know about the globals that it should stop pestering me about
/* global ChargeIO, cio4wc_info, woocommerce_params */

// Set API key
ChargeIO.init( {public_key:cio4wc_info.publishableKey} );

jQuery( function ( $ ) {
    var $form = $( 'form.checkout, form#order_review' ),
        savedFieldValues = {},
        $ccForm, $ccNumber, $ccExpiry, $ccCvc;

    function initCCForm () {
        $ccForm   = $( '#cio4wc-cc-form' );
        $ccNumber = $ccForm.find( '#cio4wc-card-number' );
        $ccExpiry = $ccForm.find( '#cio4wc-card-expiry' );
        $ccCvc    = $ccForm.find( '#cio4wc-card-cvc' );

        // Hide the CC form if the user has a saved card.
        if ( cio4wc_info.hasCard && cio4wc_info.savedCardsEnabled ) {
            $ccForm.hide();
        }

        // Toggle new card form
        $form.on( 'change', 'input[name="cio4wc_card"]', function () {

            if ( $( 'input[name="cio4wc_card"]:checked' ).val() === 'new' ) {
                $ccForm.slideDown( 200 );
            } else {
                $ccForm.slideUp( 200 );
            }
        });

        // Add in lost data
        if ( savedFieldValues.number ) {
            $ccNumber.val( savedFieldValues.number.val ).attr( 'class', savedFieldValues.number.classes );
        }
        if ( savedFieldValues.expiry ) {
            $ccExpiry.val( savedFieldValues.expiry.val );
        }
        if ( savedFieldValues.cvv ) {
            $ccCvc.val( savedFieldValues.cvv.val );
        }
    }

    function chargeioFormHandler (e) {
        if ( $( '#payment_method_cio4wc' ).is( ':checked' ) && ( ! $( 'input[name="cio4wc_card"]' ).length || $( 'input[name="cio4wc_card"]:checked' ).val() === 'new' ) ) {

            if ( ! $( 'input.chargeio_token' ).length ) {
                var cardExpiry = $ccExpiry.payment( 'cardExpiryVal' ),
                    name = ( $( '#billing_first_name' ).val() || $( '#billing_last_name' ).val() ) ? $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val() : cio4wc_info.billing_name;

                var chargeioData = {
                    number          : $ccNumber.val() || '',
                    cvv             : $ccCvc.val() || '',
                    exp_month       : cardExpiry.month || '',
                    exp_year        : cardExpiry.year || '',
                    name            : $( '.cio4wc-billing-name' ).val() || name || '',
                    address1   : $( '#billing_address_1' ).val() || cio4wc_info.billing_address_1 || '',
                    address2   : $( '#billing_address_2' ).val() || cio4wc_info.billing_address_2 || '',
                    phone   : $( '#billing_phone').val() || cio4wc_info.billing_phone || '',
                    city    : $( '#billing_city' ).val() || cio4wc_info.billing_city || '',
                    state   : $( '#billing_state' ).val() || cio4wc_info.billing_state || '',
                    postal_code     : $( '.cio4wc-billing-zip' ).val() || $( '#billing_postcode' ).val() || cio4wc_info.billing_postcode || '',
                    country : $( '#billing_country' ).val() || cio4wc_info.billing_country || ''
                };

                $form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff url(' + woocommerce_params.ajax_loader_url + ') no-repeat center',
                        opacity: 0.6
                    }
                });

                // Validate form fields, create token if form is valid
                if ( chargeioFormValidator( chargeioData ) ) {
                    ChargeIO.create_token( chargeioData, chargeioResponseHandler );
                    return false;
                }
            }
        }
        return true;
    }

    function chargeioResponseHandler ( response ) {
        if ( response.error ) {
            // show the errors on the form
            $( '.payment-errors, .chargeio_token, .form_errors' ).remove();
            $ccForm.before( '<span class="payment-errors required">' + response.error.message + '</span>' );
            $form.unblock();

        } else {
            // insert the token into the form so it gets submitted to the server
            $form.append('<input type="hidden" class="chargeio_token" name="chargeio_token" value="' + response.id + '"/>');
            $form.submit();
        }
    }

    function chargeioFormValidator ( chargeioData ) {

        // Validate form fields
        var errors = fieldValidator( chargeioData );

        // If there are errors, display them using wc_add_notice on the backend
        if ( errors.length ) {

            $( '.chargeio_token, .form_errors' ).remove();

            for ( var i = 0, len = errors.length; i < len; i++ ) {
                var field = errors[i].field,
                    type  = errors[i].type;

                $form.append( '<input type="hidden" class="form_errors" name="' + field + '" value="' + type + '">' );
            }

            $form.append( '<input type="hidden" class="form_errors" name="form_errors" value="1">' );

            $form.unblock();

            return false;
        }

        // Create the token if we don't have any errors
        else {
            // Clear out notices
            $form.find( '.woocommerce-error' ).remove();

            return true;
        }
    }

    function fieldValidator ( chargeioData ) {
        var errors = [];

        // Card number validation
        if ( ! chargeioData.number ) {
            errors.push({
                'field' : 'cio4wc-card-number',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardNumber( chargeioData.number ) ) {
            errors.push({
                'field' : 'cio4wc-card-number',
                'type'  : 'invalid'
            });
        }

        // Card expiration validation
        if ( ! chargeioData.exp_month || ! chargeioData.exp_year ) {
            errors.push({
                'field' : 'cio4wc-card-expiry',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardExpiry( chargeioData.exp_month, chargeioData.exp_year ) ) {
            errors.push({
                'field' : 'cio4wc-card-expiry',
                'type'  : 'invalid'
            });
        }

        // Card CVC validation
        if ( ! chargeioData.cvv ) {
            errors.push({
                'field' : 'cio4wc-card-cvc',
                'type'  : 'undefined'
            });
        } else if ( ! $.payment.validateCardCVC( chargeioData.cvv, $.payment.cardType( chargeioData.number ) ) ) {
            errors.push({
                'field' : 'cio4wc-card-cvc',
                'type'  : 'invalid'
            });
        }

        // Send the errors back
        return errors;
    }

    // Make sure the credit card form exists before we try working with it
    $( 'body' ).on( 'updated_checkout.cio4wc', initCCForm ).trigger( 'updated_checkout.cio4wc' );

    // Checkout Form
    $( 'form.checkout' ).on( 'checkout_place_order', chargeioFormHandler );

    // Pay Page Form
    $( 'form#order_review' ).on( 'submit', chargeioFormHandler );


    // Both Forms
    $form.on( 'keyup change', '#cio4wc-card-number, #cio4wc-card-expiry, #cio4wc-card-cvc, input[name="cio4wc_card"], input[name="payment_method"]', function () {

        // Save credit card details in case the address changes (or something else)
        savedFieldValues.number = {
            'val'     : $ccNumber.val(),
            'classes' : $ccNumber.attr( 'class' )
        };
        savedFieldValues.expiry = {
            'val' : $ccExpiry.val()
        };
        savedFieldValues.cvv = {
            'val' : $ccCvc.val()
        };

        $( '.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message, .chargeio_token, .form_errors' ).remove();
    });
});
