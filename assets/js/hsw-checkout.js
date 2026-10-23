/**
 * HamiShop Elementor Widgets — Checkout widgets (اطلاعات ارسال + پرداخت نهایی).
 * Vanilla JS, no dependencies.
 *  - Syncs the custom radio cards (shipping method) + day/slot selection UI.
 *  - Pushes the chosen WooCommerce shipping rate through the plugin REST
 *    endpoint (same session flow WooCommerce itself uses) and patches the
 *    summary totals in place.
 *  - Saves the order note + legal-invoice flag to the WC session when the
 *    customer continues to payment.
 *  - Payment step: syncs the gateway / bank / wallet selection, applies and
 *    removes coupons through the REST endpoint (totals re-patched) and
 *    persists chosen_payment_method + wallet flag before the final submit.
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
                                        var err = new Error( json && json.message ? json.message : 'request failed' );
                                        err.payload = json || {};
                                        throw err;
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

        /* =====================================================
         * Delivery day/time — persisted so the final order
         * bridge can append it to the order note.
         * ===================================================== */
        function schedulePayload() {
                var root = document.querySelector( '.hsw-cksched' );
                if ( ! root || root.classList.contains( 'hsw-vis-off' ) ) {
                        return null;
                }
                var dayBtn  = root.querySelector( '.hsw-cksched__day.is-sel' ) || root.querySelector( '.hsw-cksched__mday.is-sel' );
                var slotBox = root.querySelector( '.hsw-cksched__slot.is-sel' );
                if ( ! dayBtn && ! slotBox ) {
                        return null;
                }
                var label = function ( nodes ) {
                        var out = [];
                        for ( var i = 0; i < nodes.length; i++ ) {
                                var t = ( nodes[ i ].textContent || '' ).replace( /\s+/g, ' ' ).trim();
                                if ( t && -1 === out.indexOf( t ) ) {
                                        out.push( t );
                                }
                        }
                        return out.join( ' ' );
                };
                var day = dayBtn
                        ? label( dayBtn.querySelectorAll( '.hsw-cksched__day-meta, .hsw-cksched__day-t, .hsw-cksched__day-wd' ) )
                        : '';
                var slot = '';
                if ( slotBox ) {
                        var parts = slotBox.querySelectorAll( '.hsw-cksched__slot-t, .hsw-cksched__slot-tag' );
                        slot = label( parts.length ? parts : slotBox.querySelectorAll( 'span' ) );
                }
                if ( ! day && ! slot ) {
                        return null;
                }
                return { day: day, slot: slot };
        }

        /* The picked day/slot also travels through sessionStorage so the pay
         * page can hand it to /checkout/complete — the CTA's keepalive request
         * can be lost while the browser navigates to the payment step. */
        var SCHED_KEY = 'hswDeliverySchedule';
        function saveScheduleDraft( payload ) {
                try {
                        window.sessionStorage.setItem( SCHED_KEY, JSON.stringify( { p: payload, t: Date.now() } ) );
                } catch ( e ) {}
        }
        function savedSchedule() {
                try {
                        var raw = window.sessionStorage.getItem( SCHED_KEY );
                        if ( ! raw ) {
                                return null;
                        }
                        var box = JSON.parse( raw );
                        if ( ! box || ! box.p || ! ( box.t > 0 ) || ( Date.now() - box.t ) > 6 * 60 * 60 * 1000 ) {
                                window.sessionStorage.removeItem( SCHED_KEY );
                                return null;
                        }
                        return box.p;
                } catch ( e ) {
                        return null;
                }
        }

        var schedBusy = false;
        var schedNext = null;

        function syncSchedule( keepalive ) {
                var payload = schedulePayload();
                if ( ! payload ) {
                        return;
                }
                saveScheduleDraft( payload );
                if ( schedBusy ) {
                        /* Latest selection wins: requests can finish out of order. */
                        schedNext = payload;
                        return;
                }
                schedBusy = true;
                var done = function () {
                        schedBusy = false;
                        if ( schedNext ) {
                                var next = schedNext;
                                schedNext = null;
                                syncSchedule();
                        }
                };
                try {
                        post( '/checkout/schedule', payload, !! keepalive ).catch( function () {} ).then( done, done );
                } catch ( e ) {
                        done();
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
                        /* 3) persist the choice for the order note */
                        syncSchedule();
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
                /* Persist the choice for the order note. */
                syncSchedule();
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
                /* The delivery day/slot rides along with the same click (covers
                   the untouched default selection too). */
                syncSchedule( true );
        } );

        /* =====================================================================
         * PAYMENT STEP (پرداخت نهایی)
         * ===================================================================== */

        /* Current payment selection helpers. */
        function currentMethod() {
                var checked = document.querySelector( 'input[name="hsw_pay_method_d"]:checked, input[name="hsw_pay_method_m"]:checked' );
                return checked ? checked.value : '';
        }
        function walletOn() {
                var cb = document.querySelector( '.hsw-pm__wallet-cb' );
                return !!( cb && cb.checked );
        }
        function savePayment( keepalive ) {
                if ( isEditor || ! CFG.rest ) {
                        return;
                }
                try {
                        post( '/checkout/payment', {
                                method: currentMethod(),
                                wallet: walletOn() ? 1 : 0
                        }, !! keepalive ).catch( function () {} );
                } catch ( e ) {}
        }

        /* Payment-method radio cards (desktop + mobile groups). */
        function syncPayUI( selected ) {
                var inputs = document.querySelectorAll( 'input[name^="hsw_pay_method"]' );
                for ( var i = 0; i < inputs.length; i++ ) {
                        var input = inputs[ i ];
                        var on    = ( input.value === selected );
                        if ( input.checked !== on ) {
                                input.checked = on;
                        }
                        var card = input.closest( '.hsw-pm__opt' );
                        if ( card ) {
                                if ( on ) {
                                        card.classList.add( 'is-sel' );
                                } else {
                                        card.classList.remove( 'is-sel' );
                                }
                        }
                }
        }

        /* Bank-gateway highlight (desktop cards + mobile chips by index). */
        function syncBanksUI( selectedValue ) {
                var inputs = document.querySelectorAll( 'input[name^="hsw_pay_bank"]' );
                var idx    = -1;
                for ( var i = 0; i < inputs.length; i++ ) {
                        var on = ( inputs[ i ].value === selectedValue );
                        if ( on && idx < 0 ) {
                                idx = i;
                        }
                        inputs[ i ].checked = on;
                        var card = inputs[ i ].closest( '.hsw-pm__bank' );
                        if ( card ) {
                                if ( on ) {
                                        card.classList.add( 'is-sel' );
                                } else {
                                        card.classList.remove( 'is-sel' );
                                }
                        }
                }
                if ( idx < 0 ) {
                        idx = 0;
                }
                var chips = document.querySelectorAll( '.hsw-pm__mbank' );
                for ( var j = 0; j < chips.length; j++ ) {
                        if ( j === idx ) {
                                chips[ j ].classList.add( 'is-sel' );
                        } else {
                                chips[ j ].classList.remove( 'is-sel' );
                        }
                }
        }

        /* Coupon row markup (mirrors the PHP renderer). */
        function couponRowHTML( c ) {
                return '<div class="hsw-pcpn__applied" data-hswk-coupon="' + escAttr( c.code ) + '">' +
                        '<span class="hsw-pcpn__applied-l">' +
                        '<i class="hsw-ic" aria-hidden="true">check_circle</i>' +
                        '<span class="hsw-pcpn__applied-tx">کد تخفیف <b class="hsw-pcpn__code">' + escHtml( c.code ) + '</b> اعمال شد</span>' +
                        ( ( c.amount_html && '۰' !== c.amount_html ) ? '<span class="hsw-pcpn__deduct"><span class="hsw-pcpn__amt">' + c.amount_html + '</span> تومان کسر شد</span>' : '' ) +
                        '</span>' +
                        '<button class="hsw-pcpn__remove" type="button" data-hswk-coupon-remove="' + escAttr( c.code ) + '">' +
                        '<i class="hsw-ic" aria-hidden="true">close</i><span>حذف تخفیف</span>' +
                        '</button>' +
                        '</div>';
        }
        function summaryRowHTML( c ) {
                return '<div class="hsw-psum__row is-discount" data-hswk-coupon-row="' + escAttr( c.code ) + '">' +
                        '<span>تخفیف کوپن اختصاصی (' + escHtml( c.code ) + ')</span>' +
                        '<span class="hsw-psum__v">' + ( c.minus_html || c.amount_html || '' ) + '</span>' +
                        '</div>';
        }
        function escHtml( s ) {
                return String( s ).replace( /[&<>"']/g, function ( m ) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ m ];
                } );
        }
        function escAttr( s ) {
                return escHtml( s );
        }
        function renderCoupons( coupons ) {
                var applied = '';
                var rows    = '';
                for ( var i = 0; i < coupons.length; i++ ) {
                        applied += couponRowHTML( coupons[ i ] );
                        rows    += summaryRowHTML( coupons[ i ] );
                }

                /* Desktop coupon card: rows are siblings before the form. */
                var form = document.querySelector( '.hsw-pcpn__form' );
                if ( form && form.parentNode ) {
                        var box = form.parentNode;
                        if ( box.hasAttribute && box.hasAttribute( 'data-hswk-coupons' ) ) {
                                var old = box.querySelectorAll( '.hsw-pcpn__applied' );
                                for ( var r = 0; r < old.length; r++ ) {
                                        old[ r ].parentNode.removeChild( old[ r ] );
                                }
                                var rowsBox = document.createElement( 'div' );
                                rowsBox.innerHTML = applied;
                                for ( var n = rowsBox.childNodes.length - 1; n >= 0; n-- ) {
                                        box.insertBefore( rowsBox.childNodes[ n ], box.firstChild );
                                }
                        }
                }

                /* Mobile coupon card. */
                var mList = document.querySelector( '[data-hswk-coupons-m]' );
                if ( mList ) {
                        mList.innerHTML = applied;
                }

                /* Invoice summary coupon rows. */
                var sRows = document.querySelectorAll( '[data-hswk="couponrows"]' );
                for ( var q = 0; q < sRows.length; q++ ) {
                        sRows[ q ].innerHTML = rows;
                }
        }
        function couponMsg( scope, text, ok ) {
                var root = scope.closest( '.hsw-pcpn' ) || document;
                var msgs = root.querySelectorAll( '.hsw-pcpn__msg' );
                for ( var i = 0; i < msgs.length; i++ ) {
                        msgs[ i ].textContent = text || '';
                        msgs[ i ].classList.toggle( 'is-error', ! ok );
                        msgs[ i ].classList.toggle( 'is-ok', !! ok );
                }
        }

        /* Change events: payment radios, bank radios, wallet toggles. */
        document.addEventListener( 'change', function ( event ) {
                var input = event.target;

                /* Payment method */
                if ( input.type === 'radio' && 0 === input.name.indexOf( 'hsw_pay_method' ) ) {
                        syncPayUI( input.value );
                        savePayment( false );
                        return;
                }

                /* Bank gateway */
                if ( input.type === 'radio' && 0 === input.name.indexOf( 'hsw_pay_bank' ) ) {
                        syncBanksUI( input.value );
                        return;
                }

                /* Wallet toggle (desktop + mobile share one state) */
                if ( input.classList && input.classList.contains( 'hsw-pm__wallet-cb' ) ) {
                        var all = document.querySelectorAll( '.hsw-pm__wallet-cb' );
                        for ( var i = 0; i < all.length; i++ ) {
                                all[ i ].checked = input.checked;
                        }
                        savePayment( false );
                        return;
                }
        } );

        /* Mobile bank chips → mirror onto the desktop radios. */
        document.addEventListener( 'click', function ( event ) {
                var chip = event.target.closest ? event.target.closest( '.hsw-pm__mbank' ) : null;
                if ( ! chip ) {
                        return;
                }
                var chips = chip.parentNode.querySelectorAll( '.hsw-pm__mbank' );
                var idx   = Array.prototype.indexOf.call( chips, chip );
                if ( idx < 0 ) {
                        return;
                }
                var inputs = document.querySelectorAll( 'input[name^="hsw_pay_bank"]' );
                if ( inputs[ idx ] ) {
                        inputs[ idx ].checked = true;
                        syncBanksUI( inputs[ idx ].value );
                }
        } );

        /* Coupon apply / remove. */
        document.addEventListener( 'click', function ( event ) {
                var removeBtn = event.target.closest ? event.target.closest( '[data-hswk-coupon-remove]' ) : null;
                var applyBtn  = event.target.closest ? event.target.closest( '[data-hswk-coupon-apply]' ) : null;
                if ( ! removeBtn && ! applyBtn ) {
                        return;
                }
                if ( isEditor || ! CFG.rest ) {
                        return;
                }

                if ( removeBtn ) {
                        var code = removeBtn.getAttribute( 'data-hswk-coupon-remove' );
                        post( '/checkout/coupon', { remove: code } )
                                .then( function ( json ) {
                                        renderCoupons( ( json && json.coupons ) || [] );
                                        patchTotals( json && json.totals );
                                } )
                                .catch( function ( err ) {
                                        couponMsg( removeBtn, err && err.message ? err.message : '', false );
                                } );
                        return;
                }

                /* apply: pick the input inside the same widget part (desktop/mobile). */
                var part = applyBtn.closest( '[data-hswk-coupons]' ) || applyBtn.closest( '.hsw-pcpn' ) || document;
                var field = part.querySelector( 'input[name="hsw_coupon_code"], input[name="hsw_coupon_code_m"]' );
                var code  = field ? field.value.trim() : '';
                if ( '' === code ) {
                        couponMsg( applyBtn, 'کد تخفیف را وارد کنید.', false );
                        return;
                }
                var btn = applyBtn;
                btn.disabled = true;
                post( '/checkout/coupon', { code: code } )
                        .then( function ( json ) {
                                renderCoupons( ( json && json.coupons ) || [] );
                                patchTotals( json && json.totals );
                                if ( field ) {
                                        field.value = '';
                                }
                                couponMsg( applyBtn, 'کد تخفیف با موفقیت اعمال شد.', true );
                        } )
                        .catch( function ( err ) {
                                couponMsg( applyBtn, err && err.message ? err.message : 'کد تخفیف وارد شده معتبر نیست.', false );
                        } )
                        .then( function () { btn.disabled = false; }, function () { btn.disabled = false; } );
        } );

        /* =====================================================================
         * FINAL CTA — place the order through the plugin's checkout bridge and
         * follow WooCommerce's redirect (gateway page or order-received).
         * A native form.checkout (legacy pages) still wins when present.
         * ===================================================================== */

        /* Inline feedback near the pay CTA (desktop card + mobile bar). */
        function payMsg( text, isError, link ) {
                var nodes = document.querySelectorAll( '[data-hswk-paymsg]' );
                for ( var i = 0; i < nodes.length; i++ ) {
                        var box = nodes[ i ];
                        box.textContent = '';
                        box.hidden = true;
                        box.classList.remove( 'is-error' );
                        if ( ! text ) {
                                continue;
                        }
                        box.hidden = false;
                        if ( isError ) {
                                box.classList.add( 'is-error' );
                        }
                        box.appendChild( document.createTextNode( text ) );
                        if ( link && link.url ) {
                                var a = document.createElement( 'a' );
                                a.className = 'hsw-psum__msg-a';
                                a.href = link.url;
                                a.textContent = link.label ? link.label : link.url;
                                box.appendChild( document.createTextNode( ' ' ) );
                                box.appendChild( a );
                        }
                }
        }

        /* Busy state while the server builds the order / redirects to a gateway. */
        function payBusy( btn, busy ) {
                if ( ! btn ) {
                        return;
                }
                btn.disabled = !! busy;
                if ( busy ) {
                        btn.classList.add( 'is-busy' );
                } else {
                        btn.classList.remove( 'is-busy' );
                }
                var label = btn.querySelector( 'span' );
                if ( ! label ) {
                        return;
                }
                if ( busy ) {
                        if ( ! btn.getAttribute( 'data-hswk-label' ) ) {
                                btn.setAttribute( 'data-hswk-label', label.textContent );
                        }
                        label.textContent = 'در حال ثبت سفارش…';
                } else if ( btn.getAttribute( 'data-hswk-label' ) ) {
                        label.textContent = btn.getAttribute( 'data-hswk-label' );
                }
        }

        /* Terms must be accepted wherever the design renders the checkbox. */
        function termsAccepted() {
                var boxes = document.querySelectorAll( '.hsw-k-terms' );
                if ( ! boxes.length ) {
                        return true;
                }
                for ( var i = 0; i < boxes.length; i++ ) {
                        if ( boxes[ i ].checked ) {
                                return true;
                        }
                }
                return false;
        }

        document.addEventListener( 'click', function ( event ) {
                var cta = event.target.closest ? event.target.closest( '.hsw-k-pay' ) : null;
                if ( ! cta || isEditor || ! CFG.rest ) {
                        return;
                }

                var form = document.querySelector( 'form.checkout' );
                if ( form ) {
                        /* the native checkout form picks chosen_payment_method from the session */
                        savePayment( true );
                        window.setTimeout( function () {
                                if ( typeof jQuery !== 'undefined' ) {
                                        jQuery( 'form.checkout' ).trigger( 'submit' );
                                } else if ( typeof form.requestSubmit === 'function' ) {
                                        form.requestSubmit();
                                } else {
                                        form.submit();
                                }
                        }, 50 );
                        return;
                }

                /* Design flow: the server creates the order and hands the customer
                 * over to the gateway (or straight to order-received). */
                event.preventDefault();
                if ( ! termsAccepted() ) {
                        payMsg( 'برای ادامه، قوانین و مقررات را بپذیرید.', true );
                        return;
                }

                payMsg( '', false );
                payBusy( cta, true );

                post( '/checkout/payment', {
                        method: currentMethod(),
                        wallet: walletOn() ? 1 : 0
                } )
                        .catch( function () { /* keep the session default */ } )
                        .then( function () {
                                var payload = { method: currentMethod() };
                                var sched   = savedSchedule();
                                if ( sched && ( sched.day || sched.slot ) ) {
                                        payload.schedule_day  = sched.day || '';
                                        payload.schedule_slot = sched.slot || '';
                                }
                                return post( '/checkout/complete', payload );
                        } )
                        .then( function ( json ) {
                                if ( json && json.redirect ) {
                                        window.location.href = json.redirect;
                                        return;
                                }
                                throw new Error( 'پاسخی از درگاه پرداخت دریافت نشد؛ دوباره تلاش کنید.' );
                        } )
                        .catch( function ( err ) {
                                var payload = ( err && err.payload ) || {};
                                var data    = payload.data || {};
                                payBusy( cta, false );
                                payMsg(
                                        ( err && err.message ) ? err.message : 'ثبت سفارش انجام نشد؛ دوباره تلاش کنید.',
                                        true,
                                        data.hsw_link
                                );
                        } );
        } );
        /* =====================================================
         * Delivery address form (shown while none is saved)
         * ===================================================== */
        ( function () {
                var form = document.querySelector( '[data-hsw-address-form]' );
                if ( ! form ) {
                        return;
                }
                var msg = form.querySelector( '[data-hsw-address-msg]' );
                var btn = form.querySelector( '.hsw-ckaddr__fsubmit' );
                var FIELDS = [ 'first_name', 'last_name', 'phone', 'state', 'city', 'address_1', 'address_2', 'postcode', 'country' ];

                function say( text ) {
                        if ( ! msg ) {
                                return;
                        }
                        msg.textContent = text || '';
                        msg.hidden = ! text;
                }

                form.addEventListener( 'submit', function ( event ) {
                        event.preventDefault();
                        if ( isEditor ) {
                                return;
                        }

                        var body = {};
                        FIELDS.forEach( function ( key ) {
                                var el = form.querySelector( '[name="' + key + '"]' );
                                body[ key ] = el ? el.value.trim() : '';
                        } );

                        if ( ! body.first_name || ! body.phone || ! body.state || ! body.city || ! body.address_1 ) {
                                say( CFG.addressRequired || 'نام، شماره تماس، استان، شهر و نشانی را کامل کنید.' );
                                return;
                        }

                        say( '' );
                        if ( btn ) {
                                btn.disabled = true;
                                btn.classList.add( 'is-busy' );
                        }

                        post( '/checkout/address', body )
                                .then( function () {
                                        /* Reload so the summary, shipping rates and the
                                           pay step all pick up the new address. */
                                        window.location.reload();
                                } )
                                .catch( function ( err ) {
                                        if ( btn ) {
                                                btn.disabled = false;
                                                btn.classList.remove( 'is-busy' );
                                        }
                                        say( ( err && err.message ) ? err.message : ( CFG.errorText || 'ذخیرهٔ آدرس انجام نشد.' ) );
                                } );
                } );
        } )();
})();
