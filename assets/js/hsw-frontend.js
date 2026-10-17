/**
 * HamiShop Elementor Widgets — frontend behaviour (vanilla JS, no jQuery).
 *
 * 1. "Load more" pagination for the Shop Archive widget (REST).
 * 2. Single-product gallery thumbnail switching.
 *
 * Loaded with `defer`; everything is event-delegated so Elementor editor
 * re-renders keep working without re-initialisation.
 */
( function () {
        'use strict';

        if ( typeof window.hswData === 'undefined' ) {
                window.hswData = { restUrl: '' };
        }

        var FA_DIGITS = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };

        function fa( str ) {
                return String( str ).replace( /[0-9]/g, function ( d ) {
                        return FA_DIGITS[ d ];
                } );
        }

        /* -------------------------------------------------------
         * 1) Load more (Shop Archive, custom-query mode)
         * ----------------------------------------------------- */
        document.addEventListener( 'click', function ( e ) {
                var btn = e.target.closest( '.hsw-load-more__button' );
                if ( ! btn || ! window.hswData.restUrl ) {
                        return;
                }

                e.preventDefault();

                var wrap = btn.closest( '.hsw-archive' );
                if ( ! wrap || btn.getAttribute( 'data-busy' ) === '1' ) {
                        return;
                }

                var key = btn.getAttribute( 'data-key' );
                var page = parseInt( btn.getAttribute( 'data-page' ), 10 ) + 1;
                var grid = wrap.querySelector( '.hsw-archive__grid' );

                if ( ! key || ! grid ) {
                        return;
                }

                btn.setAttribute( 'data-busy', '1' );
                btn.classList.add( 'is-busy' );

                var url = window.hswData.restUrl + '?k=' + encodeURIComponent( key ) + '&page=' + page;

                window.fetch( url, { credentials: 'same-origin' } )
                        .then( function ( res ) { return res.ok ? res.json() : Promise.reject( res.status ); } )
                        .then( function ( data ) {
                                btn.removeAttribute( 'data-busy' );
                                btn.classList.remove( 'is-busy' );

                                if ( data && data.html ) {
                                        var tpl = document.createElement( 'template' );
                                        tpl.innerHTML = String( data.html ).trim();
                                        grid.appendChild( tpl.content );
                                }

                                if ( ! data || ! data.has_more ) {
                                        btn.closest( '.hsw-load-more' ).innerHTML = '';
                                } else {
                                        btn.setAttribute( 'data-page', String( page ) );
                                }
                        } )
                        .catch( function () {
                                btn.removeAttribute( 'data-busy' );
                                btn.classList.remove( 'is-busy' );
                        } );
        } );

        /* -------------------------------------------------------
         * 2) Product gallery thumbnail switching (Single Product)
         * ----------------------------------------------------- */
        function switchGallery( gallery, thumb ) {
                var main = gallery.querySelector( '.hsw-gallery__main img' );
                var link = gallery.querySelector( '.hsw-gallery__main' );

                if ( ! main || ! thumb ) {
                        return;
                }

                var src = thumb.getAttribute( 'data-src' );
                var large = thumb.getAttribute( 'data-large' );
                var alt = thumb.getAttribute( 'data-alt' ) || '';

                if ( src ) {
                        main.setAttribute( 'src', src );
                }
                if ( alt ) {
                        main.setAttribute( 'alt', alt );
                }
                if ( link && large ) {
                        link.setAttribute( 'href', large );
                }

                gallery.querySelectorAll( '.hsw-gallery__thumb' ).forEach( function ( t ) {
                        t.classList.toggle( 'is-active', t === thumb );
                        t.setAttribute( 'aria-current', t === thumb ? 'true' : 'false' );
                } );
        }

        document.addEventListener( 'click', function ( e ) {
                var thumb = e.target.closest( '.hsw-gallery__thumb' );
                if ( ! thumb ) {
                        return;
                }
                e.preventDefault();

                var gallery = thumb.closest( '.hsw-gallery' );
                if ( gallery ) {
                        switchGallery( gallery, thumb );
                }
        } );

        /* -------------------------------------------------------
         * 3) Single Product tabs (description / attributes / reviews)
         * ----------------------------------------------------- */
        document.addEventListener( 'click', function ( e ) {
                var btn = e.target.closest( '.hsw-single__tabs-btn' );
                if ( ! btn ) {
                        return;
                }

                var tabs = btn.closest( '.hsw-single__tabs' );
                if ( ! tabs ) {
                        return;
                }

                e.preventDefault();

                var tabKey = btn.getAttribute( 'data-tab' );
                if ( ! tabKey ) {
                        return;
                }

                tabs.querySelectorAll( '.hsw-single__tabs-btn' ).forEach( function ( b ) {
                        var active = b === btn;
                        b.classList.toggle( 'is-active', active );
                        b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
                } );

                tabs.querySelectorAll( '.hsw-single__tab-panel' ).forEach( function ( p ) {
                        p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === tabKey );
                } );
        } );
}() );
