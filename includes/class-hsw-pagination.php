<?php
/**
 * Pagination for every product loop.
 *
 * One renderer for the archive widget and for WooCommerce's own shop/category
 * loop: numbered pills with a sliding indicator, arrows at both ends, and a
 * soft fade of the loop while the next page loads.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Pagination {

	/**
	 * Hook into WooCommerce's loop so every default shop page gets the same UI.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'replace_wc_pagination' ), 20 );
	}

	/**
	 * Swap WooCommerce's plain pagination for ours.
	 */
	public function replace_wc_pagination() {
		if ( ! function_exists( 'woocommerce_pagination' ) ) {
			return;
		}
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'output' ), 10 );
	}

	/**
	 * Print the pagination (the action callback must echo, render() returns).
	 */
	public static function output() {
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* inside.
	}

	/**
	 * Build the numbered link list.
	 *
	 * @param array $args total/current/base/add_args.
	 * @return string
	 */
	public static function render( $args = array() ) {
		global $wp_query;

		$args = wp_parse_args(
			$args,
			array(
				'total'    => ( $wp_query && $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1,
				'current'  => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
				'mid_size' => 1,
				'base'     => '',
				'add_args' => array(),
			)
		);

		$total = (int) $args['total'];
		if ( $total < 2 ) {
			return '';
		}

		$current = min( max( 1, (int) $args['current'] ), $total );

		if ( '' === $args['base'] ) {
			/*
			 * get_pagenum_link() knows the permalink structure: on a site with
			 * pretty permalinks it yields `/shop/page/%#%/` directly. Building
			 * `?paged=` by hand caused a 301 hop (and a bounce back to page 1
			 * when the canonical URL disagreed).
			 */
			$args['base'] = str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) );
		}

		$links = paginate_links(
			array(
				'total'     => $total,
				'current'   => $current,
				'mid_size'  => max( 0, (int) $args['mid_size'] ),
				'type'      => 'array',
				'prev_next' => true,
				'prev_text' => '<span class="hsw-sr-only">' . esc_html__( 'صفحه قبل', 'hamishop-elementor-widgets' ) . '</span>' . HSW_Helpers::icon( 'chevron_right' ),
				'next_text' => '<span class="hsw-sr-only">' . esc_html__( 'صفحه بعد', 'hamishop-elementor-widgets' ) . '</span>' . HSW_Helpers::icon( 'chevron_left' ),
				'format'    => '?paged=%#%',
				'add_args'  => $args['add_args'],
				'base'      => $args['base'],
			)
		);

		if ( ! is_array( $links ) || empty( $links ) ) {
			return '';
		}

		$prev = '';
		$next = '';
		$nums = '';

		foreach ( $links as $link ) {
			if ( false !== strpos( $link, 'prev page-numbers' ) ) {
				$prev = self::arrow( $link, 'prev' );
				continue;
			}
			if ( false !== strpos( $link, 'next page-numbers' ) ) {
				$next = self::arrow( $link, 'next' );
				continue;
			}
			if ( false !== strpos( $link, 'dots' ) ) {
				$nums .= '<span class="hsw-pg__dots" aria-hidden="true">…</span>';
				continue;
			}
			if ( false !== strpos( $link, 'current' ) ) {
				$nums .= '<span class="hsw-pg__num is-active" aria-current="page">' . HSW_Helpers::fa_num( $current ) . '</span>';
				continue;
			}
			/* Plain page link: keep the URL, replace the label with a Persian digit. */
			$nums .= self::number( $link );
		}

		/* Disabled arrow placeholders keep the row from jumping on page 1 / last. */
		if ( '' === $prev ) {
			$prev = '<span class="hsw-pg__arrow hsw-pg__arrow--prev is-disabled" aria-hidden="true">' . HSW_Helpers::icon( 'chevron_right' ) . '</span>';
		}
		if ( '' === $next ) {
			$next = '<span class="hsw-pg__arrow hsw-pg__arrow--next is-disabled" aria-hidden="true">' . HSW_Helpers::icon( 'chevron_left' ) . '</span>';
		}

		$html  = '<nav class="hsw-pg" data-hsw-pg aria-label="' . esc_attr__( 'صفحه‌بندی محصولات', 'hamishop-elementor-widgets' ) . '">';
		$html .= $prev;
		$html .= '<div class="hsw-pg__track"><span class="hsw-pg__pill" data-hsw-pg-pill aria-hidden="true"></span>' . $nums . '</div>';
		$html .= $next;
		$html .= '</nav>';

		$html .= self::script();

		return $html;
	}

	/**
	 * Turn a paginate_links arrow into our button markup.
	 *
	 * @param string $link Raw anchor.
	 * @param string $dir  prev|next.
	 * @return string
	 */
	private static function arrow( $link, $dir ) {
		if ( ! preg_match( '#<a[^>]*href="([^"]+)"[^>]*>(.*)</a>#s', $link, $m ) ) {
			return '';
		}
		return '<a class="hsw-pg__arrow hsw-pg__arrow--' . esc_attr( $dir ) . '" href="' . esc_url( $m[1] ) . '" aria-label="' . esc_attr( 'prev' === $dir ? __( 'صفحه قبل', 'hamishop-elementor-widgets' ) : __( 'صفحه بعد', 'hamishop-elementor-widgets' ) ) . '">' . $m[2] . '</a>';
	}

	/**
	 * Turn a page anchor into a numbered pill with a Persian label.
	 *
	 * @param string $link Raw anchor.
	 * @return string
	 */
	private static function number( $link ) {
		if ( ! preg_match( '#<a[^>]*href="([^"]+)"[^>]*>(.*)</a>#s', $link, $m ) ) {
			return '';
		}
		$num = (int) trim( wp_strip_all_tags( $m[2] ) );
		return '<a class="hsw-pg__num" href="' . esc_url( $m[1] ) . '">' . HSW_Helpers::fa_num( $num ) . '</a>';
	}

	/**
	 * URL of the current archive (filters included).
	 *
	 * @return string
	 */
	private static function current_url() {
		$base = home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request : '/' ) );
		return $base ? $base : home_url( '/' );
	}

	/**
	 * Small runtime: slides the pill to the active number and fades the loop on
	 * navigation. Printed once per page.
	 *
	 * @return string
	 */
	private static function script() {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;

		return '<script>(function(){' .
			'function place(nav){var t=nav.querySelector(".hsw-pg__track"),a=t&&t.querySelector(".hsw-pg__num.is-active"),p=nav.querySelector("[data-hsw-pg-pill]");' .
			'if(!t||!a||!p){return;}p.style.width=a.offsetWidth+"px";p.style.height=a.offsetHeight+"px";p.style.transform="translateX("+a.offsetLeft+"px)";p.classList.add("is-ready");}' .
			'function all(){document.querySelectorAll("[data-hsw-pg]").forEach(place);}' .
			'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",all);}else{all();}' .
			'window.addEventListener("resize",all);window.addEventListener("load",all);' .
			'document.addEventListener("click",function(e){var a=e.target.closest(".hsw-pg a");if(!a){return;}' .
			'var loop=a.closest(".hsw-a")||document.querySelector(".hsw-loop, ul.products");' .
			'if(loop){loop.classList.add("is-pg-leaving");}' .
			'a.closest(".hsw-pg").classList.add("is-leaving");' .
			'setTimeout(function(){window.location.href=a.getAttribute("href");},170);e.preventDefault();});' .
			'})();</script>';
	}
}
