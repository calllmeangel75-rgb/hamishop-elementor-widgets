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

        /* Last rate the server accepted; '' until the first save returns. */
        var shipConfirmed = '';
        var shipBusy = false;
        /* The rate being saved right now, and the request doing it. */
        var shipPending = '';
        var shipPromise = null;
        var shipWait = false;
        /* Did the last save actually land? A failed save must not let the
           continue click through with the previous rate. */
        var shipFailed = false;

        function shippingCheckedValue() {
                var checked = document.querySelector( 'input[name^="hsw_shipping_method"]:checked' );
                return checked ? checked.value : '';
        }

        /*
         * Seeded once, at parse time, while the server-rendered radio is still
         * the checked one. Reading it inside the change handler was the first
         * version of this fix and it read the *new* value: the browser has
         * already moved the check by the time `change` fires, so the "revert"
         * reverted to the failed choice. The script is enqueued in the footer,
         * so the radios exist here.
         */
        shipConfirmed = shippingCheckedValue();

        /* One message slot per layout (desktop + mobile). */
        function shipMsg( text ) {
                var nodes = document.querySelectorAll( '[data-hswk-shipmsg]' );
                for ( var i = 0; i < nodes.length; i++ ) {
                        if ( text ) {
                                nodes[ i ].textContent = text;
                                nodes[ i ].hidden = false;
                        } else {
                                nodes[ i ].textContent = '';
                                nodes[ i ].hidden = true;
                        }
                }
        }
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
                if ( typeof totals.savings_pay_html === 'string' ) {
                        var savings = document.querySelectorAll( '[data-hswk-savings="pay"]' );
                        for ( var s = 0; s < savings.length; s++ ) {
                                savings[ s ].innerHTML = totals.savings_pay_html;
                        }
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
                        var scope = input.closest( '[data-hswk-ship]' );
                        var live  = !!( scope && '1' === scope.getAttribute( 'data-live' ) );

                        /* The browser has already checked the new radio, so the
                           confirmed value has to come from outside the DOM. */
                        if ( ! shipConfirmed ) {
                                shipConfirmed = shippingCheckedValue();
                        }

                        if ( shipBusy ) {
                                /* One request at a time. Show the rate that is
                                   actually being saved — reverting to the
                                   confirmed one would leave the UI on a value
                                   the in-flight request is about to replace. */
                                syncShippingUI( shipPending || shipConfirmed );
                                return;
                        }

                        syncShippingUI( input.value );

                        if ( ! live ) {
                                return;
                        }

                        var groups = document.querySelectorAll( '[data-hswk-ship]' );
                        var clear = function () {
                                shipBusy    = false;
                                shipPromise = null;
                                for ( var g2 = 0; g2 < groups.length; g2++ ) {
                                        groups[ g2 ].classList.remove( 'is-loading' );
                                }
                        };

                        shipBusy    = true;
                        shipFailed  = false;
                        shipPending = input.value;
                        for ( var g = 0; g < groups.length; g++ ) {
                                groups[ g ].classList.add( 'is-loading' );
                        }
                        shipMsg( '' );

                        shipPromise = post( '/checkout/shipping', { rate: input.value } )
                                .then( function ( json ) {
                                        shipConfirmed = shipPending || input.value;
                                        shipPending   = '';
                                        shipFailed    = false;
                                        patchTotals( json && json.totals );
                                } )
                                .catch( function () {
                                        /* Not saved: put the confirmed rate back
                                           and say so. The old code kept the new
                                           radio selected while the session still
                                           held the old rate, so the order shipped
                                           with a method the customer had replaced. */
                                        shipPending = '';
                                        shipFailed  = true;
                                        if ( shipConfirmed ) {
                                                syncShippingUI( shipConfirmed );
                                        }
                                        shipMsg( CFG.errorText || 'خطا در بروزرسانی روش ارسال' );
                                } )
                                .then( clear, clear );
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

        /* =====================================================
         * Delivery address — one field list for every save path
         *
         * The «ادامه و ورود به مرحله پرداخت» button used to post only five
         * fields, so the server wrote the missing ones as empty strings and
         * wiped the customer's family name and postcode. The order then died
         * on the pay button with «مقدار ... را کامل کنید». Both paths now
         * send the whole form and validate against the same list.
         * ===================================================== */
        var ADDRESS_FIELDS = CFG.addressFields || [ 'first_name', 'last_name', 'phone', 'email', 'state', 'city', 'address_1', 'address_2', 'postcode', 'country' ];
        var ADDRESS_REQUIRED = CFG.addressRequiredFields || [ 'first_name', 'phone', 'state', 'city', 'address_1' ];
        var ADDRESS_LABELS = CFG.addressLabels || {};

        function addressForm() {
                return document.querySelector( '[data-hsw-address-form]' );
        }

        function collectAddress( form ) {
                var body = {};
                ADDRESS_FIELDS.forEach( function ( key ) {
                        var el = form.querySelector( '[name="' + key + '"]' );
                        body[ key ] = el ? el.value.trim() : '';
                } );
                return body;
        }

        function missingAddress( body, form ) {
                var missing = [];
                var isDigital = form && ( form.getAttribute( 'data-hsw-digital' ) === '1' || form.hasAttribute( 'data-hsw-digital' ) );
                var requiredList = isDigital ? [ 'first_name', 'phone' ] : ADDRESS_REQUIRED;

                requiredList.forEach( function ( key ) {
                        var el = form ? form.querySelector( '[name="' + key + '"]' ) : null;
                        if ( form && ! el ) {
                                return;
                        }
                        if ( ! body[ key ] ) {
                                missing.push( key );
                        }
                } );
                return missing;
        }

        function addressMessage( missing ) {
                var names = missing.map( function ( key ) {
                        return ADDRESS_LABELS[ key ] || key;
                } );
                var tpl = CFG.addressMissing || 'برای ادامه، این موارد را کامل کنید: %s';
                return tpl.replace( '%s', names.join( '، ' ) );
        }

        /* Persian/Arabic digits are what a Persian keyboard types; the postcode
           label asks for English digits, so convert them instead of scolding. */
        var DIGIT_MAP = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
        };

        document.addEventListener( 'input', function ( event ) {
                var el = event.target;
                if ( ! el || ! el.name || ( 'postcode' !== el.name && 'phone' !== el.name ) ) {
                        return;
                }
                if ( ! el.closest || ! el.closest( '[data-hsw-address-form]' ) ) {
                        return;
                }

                var next = String( el.value ).replace( /[۰-۹٠-٩]/g, function ( ch ) {
                        return DIGIT_MAP[ ch ] || ch;
                } );
                if ( 'postcode' === el.name ) {
                        next = next.replace( /\s+/g, '' );
                }
                if ( next === el.value ) {
                        return;
                }

                var pos = el.selectionStart;
                el.value = next;
                try { el.setSelectionRange( pos, pos ); } catch ( e ) {}
        } );

        /*
 * «ادامه و ورود به مرحله پرداخت» must not leave the page while the delivery
 * address is incomplete. With a filled form we save it first and then continue
 * to the pay step; otherwise we bring the form into view and point at the
 * empty fields.
 */
document.addEventListener( 'click', function ( event ) {
	var cta = event.target.closest( '.hsw-k-cta' );
	if ( ! cta ) { return; }
	if ( document.body.classList.contains( 'elementor-editor-active' ) ) { return; }

	/*
	 * A rate change is still being saved. Navigating now lands on a page the
	 * server renders from the previous rate, and the order would carry a
	 * shipping method the customer had already replaced. Wait for the request,
	 * then run the click again.
	 */
	if ( shipPromise ) {
		event.preventDefault();
		if ( ! shipWait ) {
			shipWait = true;
			shipWaitMsg( CFG.shipSaving || 'در حال ذخیره روش ارسال…' );
			Promise.resolve( shipPromise ).catch( function () {} ).then( function () {
				shipWait = false;
				/* The rate did not save: the error is already on screen and the
				   radio is back on the old choice. Continuing here would place
				   the order with a rate the customer had replaced. */
				if ( shipFailed ) {
					/* Replace the "saving…" note with the error: the request is
					   over, and leaving it on screen would keep saying it is
					   still saving. */
					shipWaitMsg( CFG.errorText || 'خطا در بروزرسانی روش ارسال' );
					return;
				}
				shipWaitMsg( '' );
				cta.click();
			} );
		}
		return;
	}

	var has    = cta.getAttribute( 'data-has-address' ) === '1';
	var form   = addressForm();
	var drawer = form ? form.closest( '[data-hsw-address-drawer]' ) : null;
	var open   = !!( form && form.offsetParent !== null );

	/*
	 * A shop without a separate payment page (HamiShop → صفحهٔ پرداخت) used to
	 * land back on the checkout it was already on: the fallback URL is the
	 * checkout permalink, so the button reloaded the form and the order was
	 * never placed. `data-hsw-pay-step="0"` means "there is nowhere to navigate
	 * to" — submit WooCommerce's own checkout instead.
	 */
	var hasPay = cta.getAttribute( 'data-hsw-pay-step' ) !== '0';
	var payUrl = cta.getAttribute( 'data-hsw-pay-url' ) || cta.getAttribute( 'href' ) || '';

	function placeOrder() {
		var place = document.getElementById( 'place_order' );
		if ( place ) { place.click(); return; }

		/*
		 * Fallback: WooCommerce's own form element. It has to be identified by
		 * something only the checkout carries — `.woocommerce-checkout` is a
		 * BODY class on checkout pages, so `.woocommerce-checkout form` matched
		 * the header search form first and submitted it, sending the customer to
		 * `/?s=`. Never select a form by a wrapper class again.
		 *
		 * `form.checkout` and `form[name="checkout"]` are WooCommerce's own
		 * markers; the extra guard rejects anything that still looks like a
		 * search box or carries no checkout fields.
		 */
		var form = document.querySelector( 'form.checkout, form[name="checkout"]' );

		if ( ! form || ! form.querySelector ) { form = null; }

		if ( form ) {
			var looks_like_search = !! form.querySelector( 'input[name="s"]' );
			var has_checkout_field = !! form.querySelector( '#place_order, .woocommerce-checkout-payment, input[name="billing_email"], input[name="billing_first_name"]' );

			if ( looks_like_search || ! has_checkout_field ) { form = null; }
		}

		if ( form && form.requestSubmit ) { form.requestSubmit(); return; }

		/* Nothing safe to submit: stay put rather than navigate somewhere wrong. */
		if ( window.console && console.warn ) {
			console.warn( '[HamiShop] Checkout form not found; the order was not placed. Add the «فرم تسویه» widget to this page.' );
		}
	}

	/*
	 * Never navigate to the page we are already on. A «لینک مرحله بعد» that
	 * points at the checkout itself (an easy mistake: the field is called
	 * "next step", and the payment section may live further down the same page)
	 * used to reload the form and look like the button was dead.
	 */
	function isCurrentPage( url ) {
		try {
			var a = document.createElement( 'a' );
			a.href = url;
			var strip = function ( path ) { return path.replace( /\/+$/, '' ) || '/'; };
			return a.origin === window.location.origin && strip( a.pathname ) === strip( window.location.pathname );
		} catch ( e ) {
			return false;
		}
	}

	function continueToPayment() {
		if ( hasPay && payUrl && ! isCurrentPage( payUrl ) ) { window.location.href = payUrl; return; }
		placeOrder();
	}

	if ( has && ! open ) {
		/*
		 * Address already saved: follow the link straight away — but only when it
		 * actually goes somewhere else. A «لینک مرحله بعد» pointing at this same
		 * page used to return here and let the browser reload the checkout, which
		 * is exactly the "button goes back to itself" report.
		 */
		if ( hasPay && ! isCurrentPage( payUrl ) ) { return; }
		event.preventDefault();
		continueToPayment();
		return;
	}

	event.preventDefault();

	if ( ! form ) { continueToPayment(); return; }

	if ( drawer ) { drawer.hidden = false; }

	var body    = collectAddress( form );
	var missing = missingAddress( body, form );

	ADDRESS_FIELDS.forEach( function ( key ) {
		var el = form.querySelector( '[name="' + key + '"]' );
		if ( ! el ) { return; }
		if ( missing.indexOf( key ) !== -1 ) {
			el.classList.add( 'is-missing' );
		} else {
			el.classList.remove( 'is-missing' );
		}
	} );

	var msg = document.querySelector( '[data-hsw-address-msg]' );

	if ( missing.length ) {
		if ( form.scrollIntoView ) { form.scrollIntoView( { behavior: 'smooth', block: 'center' } ); }
		var first = form.querySelector( '[name="' + missing[0] + '"]' );
		if ( first && first.focus ) { first.focus( { preventScroll: true } ); }
		if ( msg ) {
			msg.hidden = false;
			msg.textContent = addressMessage( missing );
		}
		return;
	}

	/* Every field is there: save the whole form, then continue. */
	var btn = form.querySelector( '.hsw-ckaddr__fsubmit' );
	if ( btn ) { btn.disabled = true; btn.classList.add( 'is-busy' ); }
	if ( msg ) { msg.hidden = true; }

	var extra = {};
	var sched = ( typeof savedSchedule === 'function' ) ? savedSchedule() : null;
	if ( sched && ( sched.day || sched.slot ) ) {
		extra.schedule_day  = sched.day || '';
		extra.schedule_slot = sched.slot || '';
	}

	var req = ( typeof post === 'function' ) ? post( '/checkout/address', Object.assign( {}, body, extra ) ) : Promise.reject( new Error( 'no transport' ) );
	req.then( function () {
		continueToPayment();
	} ).catch( function ( err ) {
		if ( btn ) { btn.disabled = false; btn.classList.remove( 'is-busy' ); }
		if ( msg ) {
			msg.hidden = false;
			msg.textContent = ( err && err.message ) ? err.message : 'ذخیرهٔ نشانی انجام نشد؛ دوباره تلاش کنید.';
		}
	} );
} );

/*
 * The checkout CTA refuses to move on without a delivery address: the pay page
 * builds the order from that address, so the customer used to arrive there and
 * be told to go add one in the account. Bring the inline form into view instead.
 */
/* «ویرایش / تغییر آدرس» opens the inline form instead of leaving checkout. */
document.addEventListener( 'click', function ( event ) {
	var edit = event.target.closest( '[data-hsw-address-edit]' );
	if ( ! edit ) { return; }
	var form = document.querySelector( '[data-hsw-address-form]' );
	if ( ! form ) { return; }
	event.preventDefault();
	var drawer = form.closest( '[data-hsw-address-drawer]' );
	if ( drawer ) { drawer.hidden = false; }
	if ( form.scrollIntoView ) { form.scrollIntoView( { behavior: 'smooth', block: 'center' } ); }
	var first = form.querySelector( '[name="first_name"]' );
	if ( first && first.focus ) { first.focus( { preventScroll: true } ); }
} );

document.addEventListener( 'click', function ( event ) {
	var link = event.target.closest( '[data-hsw-need-address]' );
	if ( ! link ) { return; }
	/*
	 * The checkout CTA handler already owns this click and called
	 * preventDefault — it may be waiting for a shipping rate to be saved. Two
	 * handlers writing the same status line meant the "saving the rate" note
	 * was replaced by the address nudge one millisecond later.
	 */
	if ( event.defaultPrevented ) { return; }
	var field = document.querySelector( '[data-hsw-address-form] [name="first_name"]' );
	if ( ! field ) { return; }
	event.preventDefault();
	var card = field.closest( '.hsw-ckaddr__card' ) || field;
	if ( card.scrollIntoView ) { card.scrollIntoView( { behavior: 'smooth', block: 'center' } ); }
	field.focus( { preventScroll: true } );
	var msg = document.querySelector( '[data-hsw-address-msg]' );
	if ( msg ) {
		msg.hidden = false;
		msg.textContent = link.getAttribute( 'data-hsw-need-address-text' ) || 'برای ادامه، نشانی تحویل را کامل کنید.';
	}
} );

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
        function savePayment( keepalive ) {
                if ( isEditor || ! CFG.rest ) {
                        return;
                }
                try {
                        post( '/checkout/payment', {
                                method: currentMethod()
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
                        window.hswIcon('check_circle') +
                        '<span class="hsw-pcpn__applied-tx">کد تخفیف <b class="hsw-pcpn__code">' + escHtml( c.code ) + '</b> اعمال شد</span>' +
                        ( ( c.amount_html && '۰' !== c.amount_html ) ? '<span class="hsw-pcpn__deduct"><span class="hsw-pcpn__amt">' + c.amount_html + '</span> تومان کسر شد</span>' : '' ) +
                        '</span>' +
                        '<button class="hsw-pcpn__remove" type="button" data-hswk-coupon-remove="' + escAttr( c.code ) + '">' +
                        window.hswIcon('close') + '<span>حذف تخفیف</span>' +
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

        /*
         * Terms are enforced when the shop has something to accept, and only
         * then. An absent checkbox is never "accepted" — that turned a missing
         * widget into silent consent, and the server had no way to tell the
         * difference between a customer who agreed and a page that never asked.
         */
        /*
         * The checkout page has no [data-hswk-paymsg] — that lives in the pay
         * summary. Its own status line is the address form's. A "saving the
         * rate" note has to reach whichever one this page actually renders.
         */
        function shipWaitMsg( text ) {
                payMsg( text, false );

                var box = document.querySelector( '[data-hsw-address-msg]' );
                if ( ! box ) {
                        return;
                }
                box.hidden = ! text;
                box.textContent = text || '';
                box.classList.toggle( 'is-busy-note', !! text );
        }

        function termsRequired( cta ) {
                return !!( cta && '1' === cta.getAttribute( 'data-hsw-terms' ) );
        }

        function termsAccepted() {
                var boxes = document.querySelectorAll( '.hsw-k-terms' );
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
                if ( termsRequired( cta ) ) {
                        var termsBoxes = document.querySelectorAll( '.hsw-k-terms' );
                        if ( ! termsBoxes.length ) {
                                /* Required by the shop, absent from the page: a
                                   configuration error, never a silent pass. */
                                payMsg( CFG.termsMissing || 'چک‌باکس قوانین روی این صفحه نیست؛ با مدیر فروشگاه تماس بگیرید.', true );
                                return;
                        }
                        if ( ! termsAccepted() ) {
                                payMsg( 'برای ادامه، قوانین و مقررات را بپذیرید.', true );
                                if ( termsBoxes[0].focus ) {
                                        termsBoxes[0].focus();
                                }
                                return;
                        }
                }

                payMsg( '', false );
                payBusy( cta, true );

                post( '/checkout/payment', {
                        method: currentMethod()
                } )
                        .catch( function () { /* keep the session default */ } )
                        .then( function () {
                                var payload = {
                                        method: currentMethod(),
                                        terms: termsAccepted() ? 1 : 0
                                };
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
                var form = addressForm();
                if ( ! form ) {
                        return;
                }
                var msg = form.querySelector( '[data-hsw-address-msg]' );
                var btn = form.querySelector( '.hsw-ckaddr__fsubmit' );

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

                        var body    = collectAddress( form );
                        var missing = missingAddress( body, form );

                        if ( missing.length ) {
                                say( addressMessage( missing ) );
                                var first = form.querySelector( '[name="' + missing[0] + '"]' );
                                if ( first && first.focus ) { first.focus( { preventScroll: true } ); }
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
