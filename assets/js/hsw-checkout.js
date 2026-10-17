/**
 * HamiShop Elementor Widgets — Checkout shipping-information widgets.
 * Vanilla JS, no dependencies.
 *  - Syncs the custom radio cards (shipping method) + day/slot selection UI.
 *  - Pushes the chosen WooCommerce shipping rate through the plugin REST
 *    endpoint (same session flow WooCommerce itself uses) and patches the
 *    summary totals in place.
 *  - Saves the order note + legal-invoice flag to the WC session when the
 *    customer continues to payment.
 * In the Elementor editor nothing is sent (preview stays stable).
 */
(function () {
        'use strict';

        var isEditor = !!( window.parent && window.parent !== window );
        var CFG = window.hswCheckoutData || {};

        function post( path, body, keepalive ) {
                if ( isEditor || ! CFG.rest ) {
                        return Promise.reject( new Error( 'editor' ) );
                }
                return fetch( CFG.rest + path, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: !! keepalive,
                        headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': CFG.nonce || ''
                        },
                        body: JSON.stringify( body || {} )
                } ).then( function ( res ) {
                        return res.json().then( function ( json ) {
                                if ( ! res.ok ) {
                                        throw new Error( json && json.message ? json.message : 'request failed' );
                                }
                                return json;
                        } );
                } );
        }

        /* =====================================================
         * Shipping method radios — visual sync + live totals
         * ===================================================== */
        function syncShippingUI( selected ) {
                var inputs = document.querySelectorAll( 'input[name^="hsw_shipping_method"]' );
                for ( var i = 0; i < inputs.length; i++ ) {
                        var input = inputs[ i ];
                        var card  = input.closest( '.hsw-ckship__opt' );
                        var on    = ( input.value === selected );
                        if ( input.checked !== on ) {
                                input.checked = on;
                        }
                        if ( card ) {
                                if ( on ) {
                                        card.classList.add( 'is-sel' );
                                } else {
                                        card.classList.remove( 'is-sel' );
                                }
                        }
                }
        }

        function patchTotals( totals ) {
                if ( ! totals ) {
                        return;
                }
                var map = {
                        subtotal: 'subtotal_html',
                        discount: 'discount_html',
                        shipping: 'shipping_html',
                        taxes:    'taxes_html',
                        total:    'total_html'
                };
                for ( var key in map ) {
                        if ( ! Object.prototype.hasOwnProperty.call( map, key ) ) {
                                continue;
                        }
                        var nodes = document.querySelectorAll( '[data-hswk="' + key + '"]' );
                        if ( ! nodes.length || ! totals[ map[ key ] ] ) {
                                continue;
                        }
                        for ( var i = 0; i < nodes.length; i++ ) {
                                nodes[ i ].innerHTML = totals[ map[ key ] ];
                        }
                }
        }

        document.addEventListener( 'change', function ( event ) {
                var input = event.target;
                if ( ! input || input.type !== 'radio' ) {
                        return;
                }

                /* Shipping method */
                if ( 0 === input.name.indexOf( 'hsw_shipping_method' ) ) {
                        syncShippingUI( input.value );

                        var scope = input.closest( '[data-hswk-ship]' );
                        if ( scope && '1' === scope.getAttribute( 'data-live' ) ) {
                                var groups = document.querySelectorAll( '[data-hswk-ship]' );
                                for ( var g = 0; g < groups.length; g++ ) {
                                        groups[ g ].classList.add( 'is-loading' );
                                }
                                var clear = function () {
                                        for ( var g2 = 0; g2 < groups.length; g2++ ) {
                                                groups[ g2 ].classList.remove( 'is-loading' );
                                        }
                                };
                                post( '/checkout/shipping', { rate: input.value } )
                                        .then( function ( json ) {
                                                patchTotals( json && json.totals );
                                        } )
                                        .catch( function () {} )
                                        .then( clear, clear );
                        }
                        return;
                }

                /* Delivery time slots */
                if ( 0 === input.name.indexOf( 'hsw_delivery_slot' ) ) {
                        var inputs = document.querySelectorAll( 'input[name^="hsw_delivery_slot"]' );
                        /* 1) sync the checked state across desktop + mobile groups
                           (each layout has its own radio name, so no cross-talk) */
                        for ( var i2 = 0; i2 < inputs.length; i2++ ) {
                                inputs[ i2 ].checked = ( inputs[ i2 ].value === input.value );
                        }
                        /* 2) then mirror the state onto the visual cards (by value) */
                        for ( var j = 0; j < inputs.length; j++ ) {
                                var slotCard = inputs[ j ].closest( '.hsw-cksched__slot' );
                                if ( slotCard ) {
                                        if ( inputs[ j ].value === input.value ) {
                                                slotCard.classList.add( 'is-sel' );
                                        } else {
                                                slotCard.classList.remove( 'is-sel' );
                                        }
                                }
                        }
                }
        } );

        /* Day pills (desktop buttons + mobile pills share one selectable order) */
        document.addEventListener( 'click', function ( event ) {
                var day = event.target.closest
                        ? event.target.closest( 'button.hsw-cksched__day:not(.is-off), button.hsw-cksched__mday' )
                        : null;
                if ( ! day ) {
                        return;
                }
                var box = day.closest( '[data-hswk-days]' );
                if ( ! box ) {
                        return;
                }
                var idx = Array.prototype.indexOf.call(
                        box.querySelectorAll( 'button.hsw-cksched__day:not(.is-off), button.hsw-cksched__mday' ),
                        day
                );
                var all = document.querySelectorAll( '.hsw-cksched__day, .hsw-cksched__mday' );
                for ( var i = 0; i < all.length; i++ ) {
                        all[ i ].classList.remove( 'is-sel' );
                }
                day.classList.add( 'is-sel' );
                /* Mirror the choice onto the other layout (desktop ⇄ mobile). */
                var dDays = document.querySelectorAll( 'button.hsw-cksched__day:not(.is-off)' );
                var mDays = document.querySelectorAll( '.hsw-cksched__mday' );
                if ( -1 < idx ) {
                        if ( dDays[ idx ] ) {
                                dDays[ idx ].classList.add( 'is-sel' );
                        }
                        if ( mDays[ idx ] ) {
                                mDays[ idx ].classList.add( 'is-sel' );
                        }
                }
        } );

        /* =====================================================
         * Continue-to-payment CTA — save note + invoice flag
         * ===================================================== */
        document.addEventListener( 'click', function ( event ) {
                var cta = event.target.closest ? event.target.closest( '.hsw-k-cta' ) : null;
                if ( ! cta || isEditor || ! CFG.rest ) {
                        return;
                }
                var ta      = document.querySelector( '.hsw-k-note-ta' );
                var inv     = document.querySelector( '.hsw-k-note-inv' );
                var payload = {
                        note:    ta ? ta.value : '',
                        invoice: ( inv && inv.checked ) ? 1 : 0
                };
                /* Fire-and-forget with keepalive so navigation is never blocked. */
                try {
                        post( '/checkout/note', payload, true ).catch( function () {} );
                } catch ( e ) {}
        } );
})();
