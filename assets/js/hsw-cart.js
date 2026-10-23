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

		/* At `min` the minus has nothing left to do; the bin removes the line. */
		var minus = box.querySelector( '.hsw-c-qty__btn[data-dir="minus"]' );
		if ( minus ) {
			var floor = value <= min;
			minus.disabled = floor;
			minus.setAttribute( 'aria-disabled', floor ? 'true' : 'false' );
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

	/* ---------- Typing a quantity ---------- */

	/* Persian/Arabic digits are what a Persian keyboard produces. */
	function toLatin( value ) {
		return String( value )
			.replace( /[\u06F0-\u06F9]/g, function ( d ) { return String( d.charCodeAt( 0 ) - 0x06F0 ); } )
			.replace( /[\u0660-\u0669]/g, function ( d ) { return String( d.charCodeAt( 0 ) - 0x0660 ); } )
			.replace( /[^\d]/g, '' );
	}

	function commitQty( input, force ) {
		var box = input.closest ? input.closest( '.hsw-c-qty' ) : null;
		var min = parseInt( input.getAttribute( 'min' ) || '1', 10 );
		if ( isNaN( min ) || min < 1 ) {
			min = 1;
		}
		var max = parseInt( input.getAttribute( 'max' ) || '0', 10 );
		var raw = toLatin( input.value );
		var value = raw === '' ? min : parseInt( raw, 10 );
		if ( isNaN( value ) || value < min ) {
			value = min;
		}
		if ( max > 0 && value > max ) {
			value = max;
		}

		input.value = String( value );

		var mirror = box && box.querySelector( '.hsw-c-qty__v' );
		if ( mirror ) {
			mirror.textContent = faNum( value );
		}

		if ( isEditor ) {
			return;
		}

		/* Blur without an edit (or an unchanged value) must not reload the page. */
		var original = parseInt( input.defaultValue, 10 );
		if ( ! force && ! isNaN( original ) && value === original ) {
			return;
		}

		submitForm( input.closest( 'form' ) );
	}

	document.addEventListener( 'keydown', function ( event ) {
		var input = event.target;
		if ( ! input || ! input.classList || ! input.classList.contains( 'hsw-c-qty__in' ) ) {
			return;
		}
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			commitQty( input, true );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;
		if ( ! input || ! input.classList || ! input.classList.contains( 'hsw-c-qty__in' ) ) {
			return;
		}
		commitQty( input, false );
	} );

	/* Selecting the field should not leave a leading zero in the way. */
	document.addEventListener( 'focusin', function ( event ) {
		var input = event.target;
		if ( input && input.classList && input.classList.contains( 'hsw-c-qty__in' ) ) {
			input.select();
		}
	} );
}() );
