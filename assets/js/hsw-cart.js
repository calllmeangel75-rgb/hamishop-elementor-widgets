/**
 * HamiShop Elementor Widgets — Cart widgets.
 * Vanilla JS, no dependencies. Handles the design quantity steppers by
 * updating the WooCommerce hidden qty inputs and submitting the native
 * update_cart form (WC then reloads with fresh totals/fragments).
 * In the Elementor editor nothing is submitted (preview stays stable).
 */
(function () {
	'use strict';

	var isEditor = !!( window.parent && window.parent !== window );

	function faNum( n ) {
		try {
			return ( n ).toLocaleString( 'fa-IR', { useGrouping: false } );
		} catch ( e ) {
			return String( n );
		}
	}

	function submitForm( form ) {
		if ( ! form ) {
			return;
		}
		var btn = form.querySelector( '[name="update_cart"]' );
		if ( ! btn ) {
			return;
		}
		if ( btn.disabled ) {
			btn.disabled = false;
		}
		if ( typeof form.requestSubmit === 'function' ) {
			form.requestSubmit( btn );
		} else {
			btn.click();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest ? event.target.closest( '.hsw-c-qty__btn' ) : null;
		if ( ! btn ) {
			return;
		}
		var box = btn.closest( '.hsw-c-qty' );
		if ( ! box ) {
			return;
		}
		var input = box.querySelector( 'input[name^="cart["]' );
		if ( ! input ) {
			return;
		}

		var min = parseInt( input.getAttribute( 'min' ) || '1', 10 );
		if ( isNaN( min ) || min < 1 ) {
			min = 1;
		}
		var max = parseInt( input.getAttribute( 'max' ) || '0', 10 );
		var value = parseInt( input.value || String( min ), 10 );
		if ( isNaN( value ) || value < min ) {
			value = min;
		}

		value = ( 'plus' === btn.getAttribute( 'data-dir' ) ) ? value + 1 : value - 1;
		if ( max > 0 && value > max ) {
			value = max;
		}
		if ( value < min ) {
			value = min;
		}

		input.value = String( value );

		var mirror = box.querySelector( '.hsw-c-qty__v' );
		if ( mirror ) {
			mirror.textContent = faNum( value );
		}

		/* Editor preview: update the number, never submit. */
		if ( isEditor ) {
			event.preventDefault();
			return;
		}

		submitForm( btn.closest( 'form' ) );
	} );

	/* Editor preview: block every cart form submission / navigation. */
	if ( isEditor ) {
		document.addEventListener( 'submit', function ( event ) {
			var form = event.target;
			if ( form && form.closest && form.closest( '.hsw-c' ) ) {
				event.preventDefault();
			}
		}, true );
	}
}() );
