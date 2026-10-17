/**
 * Editor JS: binds the `hsw_products` control to an AJAX-powered select2.
 *
 * Dependencies available in the Elementor editor: jQuery, jQuery.select2,
 * Backbone (elementor.modules.controls.BaseData).
 */
/* global jQuery, elementor */
( function ( window ) {
	'use strict';

	var $ = window.jQuery;

	$( window ).on( 'elementor:init', function () {
		if ( ! window.elementor || ! window.elementor.modules || ! window.hswEditor ) {
			return;
		}

		var BaseData = window.elementor.modules.controls.BaseData;

		var ProductsControl = BaseData.extend( {
			ui: function () {
				var ui = BaseData.prototype.ui.apply( this, arguments );
				return $.extend( ui, {
					select: '.hsw-products-control__select',
					hint: '.hsw-products-control__hint'
				} );
			},

			onReady: function () {
				var self = this;
				var multiple = !! self.model.get( 'multiple' );
				var placeholder = self.model.get( 'placeholder' ) || '';
				var value = self.getControlValue();

				if ( multiple && ! $.isArray( value ) ) {
					value = value ? [ value ] : [];
				}

				var ids = ( multiple ? value : [ value ] ).filter( function ( id ) {
					return '' !== id && null !== id && undefined !== id;
				} ).map( String );

				var finish = function () {
					self.$el.find( self.ui.select ).select2( {
						placeholder: placeholder,
						allowClear: ! multiple,
						multiple: multiple,
						width: '100%',
						minimumResultsForSearch: 0,
						ajax: {
							url: window.hswEditor.ajaxUrl,
							dataType: 'json',
							delay: 250,
							data: function ( params ) {
								return {
									action: 'hsw_search_products',
									nonce: window.hswEditor.nonce,
									s: params.term || '',
									page: params.page || 1
								};
							},
							processResults: function ( data ) {
								return {
									results: ( data && data.results ) || [],
									pagination: { more: !! ( data && data.pagination && data.pagination.more ) }
								};
							},
							cache: true
						}
					} );

					self.$el.find( self.ui.select ).val( multiple ? ids : ( ids[ 0 ] || '' ) ).trigger( 'change.select2' );
				};

				// Pre-populate labels for already-selected products.
				if ( ids.length ) {
					$.get( window.hswEditor.ajaxUrl, {
						action: 'hsw_search_products',
						nonce: window.hswEditor.nonce,
						include: ids.join( ',' )
					} ).done( function ( res ) {
						var results = ( res && res.results ) || [];
						var $select = self.$el.find( self.ui.select );
						results.forEach( function ( item ) {
							if ( ! $select.find( 'option[value="' + item.id + '"]' ).length ) {
								$select.append( new Option( item.text, item.id, false, false ) );
							}
						} );
						finish();
					} ).fail( finish );
				} else {
					finish();
				}

				this.on( 'change', function () {
					var val = self.$el.find( self.ui.select ).val();
					self.setValue( multiple ? ( val || [] ) : ( val || '' ) );
				} );
			},

			onBeforeDestroy: function () {
				try {
					this.$el.find( this.ui.select ).select2( 'destroy' );
				} catch ( e ) { /* noop */ }
			}
		} );

		window.elementor.addControlView( 'hsw_products', ProductsControl );
	} );
}( window ) );
