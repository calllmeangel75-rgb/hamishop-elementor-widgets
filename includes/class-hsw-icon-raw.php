<?php
/**
 * Iconsax (linear) symbols — local, stroke-based.
 *
 * The bundled Material sprite draws filled paths on a 0 -960 960 960 grid.
 * These six icons are stroke art on a 24×24 grid, so they carry their own
 * viewBox and inner markup. `stroke="currentColor"` means the panel colour
 * (the widget's text colour) tints them — no second file, no CDN, and only the
 * icons a page actually renders are printed.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string,array{viewBox:string,body:string}>
 */
function hsw_icon_raw_map() {
	return array(
		'account_balance_wallet' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H18a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path><path d="M3 8.5V17a2 2 0 0 0 2 2h13.5a2.5 2.5 0 0 0 2.5-2.5v-5A2.5 2.5 0 0 0 18.5 9H3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path><path d="M16.5 13.75h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"></path>',
		),
		'favorite' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M12.62 20.81c-.34.12-.9.12-1.24 0C8.48 19.82 2 15.69 2 8.69 2 5.6 4.49 3.1 7.56 3.1c1.82 0 3.43.88 4.44 2.24 1.01-1.36 2.62-2.24 4.44-2.24C19.51 3.1 22 5.6 22 8.69c0 7-6.48 11.13-9.38 12.12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path>',
		),
		'settings' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path>',
		),
		'storefront' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M21.01 11.2207V15.7107C21.01 20.2007 19.22 22.0007 14.72 22.0007H9.33002C8.75002 22.0007 8.21998 21.9708 7.72998 21.9008" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M3.04004 15.5208V11.2207" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M12.03 12C13.86 12 15.21 10.5101 15.03 8.68005L14.36 2H9.69001L9.02003 8.68005C8.84003 10.5101 10.2 12 12.03 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M18.3301 12C20.3501 12 21.8301 10.36 21.6301 8.34998L21.35 5.59998C20.99 2.99998 19.9901 2 17.3701 2H14.3201L15.0201 9.01001C15.2001 10.66 16.6801 12 18.3301 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M5.67004 12C7.32004 12 8.81003 10.66 8.97003 9.01001L9.19006 6.80005L9.67004 2H6.62005C4.00005 2 3.00007 2.99998 2.64007 5.59998L2.36004 8.34998C2.16004 10.36 3.65004 12 5.67004 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M9 19C9 19.75 8.78998 20.4601 8.41998 21.0601C8.22998 21.3801 7.99998 21.67 7.72998 21.9C7.69998 21.94 7.67 21.97 7.63 22C6.93 22.63 6.01 23 5 23C3.78 23 2.68997 22.45 1.96997 21.59C1.94997 21.56 1.92002 21.54 1.90002 21.51C1.78002 21.37 1.67002 21.2201 1.58002 21.0601C1.21002 20.4601 1 19.75 1 19C1 17.74 1.58 16.61 2.5 15.88C2.67 15.74 2.84998 15.62 3.03998 15.52C3.61998 15.19 4.29 15 5 15C6 15 6.89998 15.36 7.59998 15.97C7.71998 16.06 7.82999 16.17 7.92999 16.28C8.58999 17 9 17.95 9 19Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M6.48999 18.9805H3.51001" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M5 17.5195V20.5095" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>',
		),
		'cart' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M4.75016 13.9709C4.61016 15.6009 5.90015 17.0009 7.54015 17.0009H18.1902C19.6302 17.0009 20.8902 15.8209 21.0002 14.3909L21.5401 6.89087C21.6601 5.23087 20.4002 3.88086 18.7302 3.88086H5.82016" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2 2H3.74001C4.82001 2 5.67 2.93 5.58 4L5.08 10.05" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M16.25 22C16.9404 22 17.5 21.4404 17.5 20.75C17.5 20.0596 16.9404 19.5 16.25 19.5C15.5596 19.5 15 20.0596 15 20.75C15 21.4404 15.5596 22 16.25 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M8.25 22C8.94036 22 9.5 21.4404 9.5 20.75C9.5 20.0596 8.94036 19.5 8.25 19.5C7.55964 19.5 7 20.0596 7 20.75C7 21.4404 7.55964 22 8.25 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M9 8H21" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>',
		),
		'person' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M20.5901 22C20.5901 18.13 16.7402 15 12.0002 15C7.26015 15 3.41016 18.13 3.41016 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
		),
		'shopping_cart' => array(
			'viewBox' => '0 0 24 24',
			'body'    => '<path d="M4.75016 13.9709C4.61016 15.6009 5.90015 17.0009 7.54015 17.0009H18.1902C19.6302 17.0009 20.8902 15.8209 21.0002 14.3909L21.5401 6.89087C21.6601 5.23087 20.4002 3.88086 18.7302 3.88086H5.82016" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2 2H3.74001C4.82001 2 5.67 2.93 5.58 4L5.08 10.05" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M16.25 22C16.9404 22 17.5 21.4404 17.5 20.75C17.5 20.0596 16.9404 19.5 16.25 19.5C15.5596 19.5 15 20.0596 15 20.75C15 21.4404 15.5596 22 16.25 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M8.25 22C8.94036 22 9.5 21.4404 9.5 20.75C9.5 20.0596 8.94036 19.5 8.25 19.5C7.55964 19.5 7 20.0596 7 20.75C7 21.4404 7.55964 22 8.25 22Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/> <path d="M9 8H21" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>',
		),
	);
}
