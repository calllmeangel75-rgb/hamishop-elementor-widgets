<?php
/**
 * White-label helpers: Persian wording and the classic editor.
 *
 * Both switches live in HamiShop → برند و لوگو → «تنظیمات نام حامی» and are off
 * by default, so a fresh install behaves exactly like vanilla WordPress.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Rebrand {

	/**
	 * Option that holds the switches (the branding array).
	 */
	const OPTION = 'hsw_brand';

	/**
	 * Register the filters only when a switch is on.
	 */
	public function __construct() {
		$b = HSW_Branding::get();

		if ( 'yes' === ( $b['rebrand'] ?? 'no' ) ) {
			add_filter( 'gettext', array( $this, 'translate' ), 20, 2 );
		}

		if ( 'yes' === ( $b['no_block_editor'] ?? 'no' ) ) {
			/*
			 * Elementor keeps its own editor; this only takes Gutenberg away from
			 * posts, pages and products so the classic screen is used again.
			 */
			add_filter( 'use_block_editor_for_post', '__return_false', 99 );
			add_filter( 'use_block_editor_for_post_type', '__return_false', 99 );
		}
	}

	/**
	 * «ووکامرس» → «فروشگاه» everywhere, «وردپرس» → «پنل مدیریت» in the admin.
	 *
	 * Runs on every translated string, so it stays a couple of comparisons: the
	 * admin replacement only touches strings that actually contain the word.
	 *
	 * @param string $translated Translated text.
	 * @param string $original   Original text.
	 * @return string
	 */
	public function translate( $translated, $original = '' ) {
		if ( 'ووکامرس' === $translated ) {
			return 'فروشگاه';
		}

		if ( is_admin() && false !== strpos( $translated, 'وردپرس' ) ) {
			return str_replace( 'وردپرس', 'پنل مدیریت', $translated );
		}

		return $translated;
	}
}
