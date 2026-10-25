<?php
/**
 * Shared base for the two HamiShop header widgets.
 *
 * The header widgets are deliberately self-contained: native WordPress menu,
 * native WooCommerce product search/cart/account URLs, and local icons/fonts.
 * No theme header markup, no remote assets, no duplicate WooCommerce forms.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

abstract class HSW_Header_Base extends HSW_Widget_Base {

	public function get_categories() {
		return array( 'hamishop-header' );
	}

	public function get_style_depends() {
		return array( 'hsw-fonts', 'hsw-header' );
	}

	public function get_script_depends() {
		return array( 'hsw-header' );
	}

	/* ---------------------------------------------------------------------
	 * URLs / cart
	 * ------------------------------------------------------------------ */

	protected function shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
			if ( $url ) {
				return $url;
			}
		}
		$url = get_post_type_archive_link( 'product' );
		return $url ? $url : home_url( '/' );
	}

	protected function account_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( $url ) {
				return $url;
			}
		}
		return wp_login_url();
	}

	protected function cart_url() {
		if ( function_exists( 'wc_get_cart_url' ) ) {
			$url = wc_get_cart_url();
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' );
	}

	protected function orders_url() {
		if ( function_exists( 'wc_get_endpoint_url' ) ) {
			return wc_get_endpoint_url( 'orders', '', $this->account_url() );
		}
		return $this->account_url();
	}

	protected function blog_url() {
		$page_id = (int) get_option( 'page_for_posts' );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' );
	}

	protected function fragments_url() {
		if ( class_exists( 'WC_AJAX' ) && method_exists( 'WC_AJAX', 'get_endpoint' ) ) {
			return WC_AJAX::get_endpoint( 'get_refreshed_fragments' );
		}
		return home_url( '/?wc-ajax=get_refreshed_fragments' );
	}

	protected function cart_count() {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			return (int) WC()->cart->get_cart_contents_count();
		}
		return 0;
	}

	protected function cart_subtotal() {
		if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			return WC()->cart->get_cart_subtotal();
		}
		return '';
	}

	protected function is_logged_in() {
		return is_user_logged_in();
	}

	protected function current_user_name() {
		$user = wp_get_current_user();
		if ( $user && $user->exists() ) {
			$name = $user->display_name ? $user->display_name : $user->user_login;
			if ( $name ) {
				return $name;
			}
		}
		return __( 'کاربر مهمان', 'hamishop-elementor-widgets' );
	}

	/* ---------------------------------------------------------------------
	 * Settings / markup helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Stable per-widget HTML id for ARIA / drawer / panel targets.
	 *
	 * @return string
	 */
	protected function uid() {
		$id = $this->get_id();
		return $id ? sanitize_html_class( $id ) : wp_unique_id( 'hsw-hd-' );
	}

	/**
	 * Menu choices shown in Elementor.
	 *
	 * @return array
	 */
	protected function menu_options() {
		$options = array(
			0 => __( 'منوی موقعیت قالب (پیش‌فرض)', 'hamishop-elementor-widgets' ),
		);
		$menus = wp_get_nav_menus();
		if ( $menus ) {
			foreach ( $menus as $menu ) {
				$options[ (int) $menu->term_id ] = $menu->name . ' (#' . (int) $menu->term_id . ')';
			}
		}
		return $options;
	}

	/**
	 * Product categories for search/mega menu. Filters the WooCommerce default
	 * category so an empty placeholder never appears in the header.
	 *
	 * @param int $limit  How many terms to return (0 = all).
	 * @param int $parent Parent term id.
	 * @return WP_Term[]
	 */
	protected function product_categories( $limit = 0, $parent = 0 ) {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => absint( $parent ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( $limit > 0 ) {
			$args['number'] = absint( $limit );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$default_id = (int) get_option( 'default_product_cat', 0 );
		return array_values(
			array_filter(
				$terms,
				static function ( $term ) use ( $default_id ) {
					if ( $default_id && (int) $term->term_id === $default_id ) {
						return false;
					}
					return ! in_array( $term->slug, array( 'uncategorized', 'بدون-دسته‌بندی' ), true );
				}
			)
		);
	}

	/**
	 * Brand / logo markup. Uses the selected Elementor image, then the site
	 * logo, then the bundled storefront icon.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function brand_html( $s ) {
		$name    = isset( $s['brand_name'] ) ? trim( (string) $s['brand_name'] ) : '';
		$tagline = isset( $s['brand_tagline'] ) ? trim( (string) $s['brand_tagline'] ) : '';
		/*
		 * No runtime fallback to the site name: the control already defaults
		 * to get_bloginfo( 'name' ), so an empty value means the user cleared
		 * it on purpose and the name must really disappear (logo-only brand).
		 */
		$aria_name = '' !== $name ? $name : get_bloginfo( 'name' );
		$logo_id   = isset( $s['logo']['id'] ) ? absint( $s['logo']['id'] ) : 0;
		$logo      = '';

		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id ) {
			$logo = wp_get_attachment_image(
				$logo_id,
				'full',
				false,
				array(
					'class' => 'hsw-hd__logoimg',
					'alt'   => $aria_name,
				)
			);
		}
		if ( ! $logo && ! empty( $s['logo']['url'] ) ) {
			$logo = '<img class="hsw-hd__logoimg" src="' . esc_url( $s['logo']['url'] ) . '" alt="' . esc_attr( $aria_name ) . '" loading="lazy" decoding="async">';
		}

		$mark = $logo
			? '<span class="hsw-hd__logomark hsw-hd__logomark--image">' . $logo . '</span>'
			: '<span class="hsw-hd__logomark">' . HSW_Helpers::icon( 'storefront' ) . '</span>';

		$out  = '<a class="hsw-hd__brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $aria_name ) . '">';
		$out .= $mark;
		$out .= '<span class="hsw-hd__brandtext">';
		if ( '' !== $name ) {
			$out .= '<span class="hsw-hd__brandname">' . esc_html( $name ) . '</span>';
		}
		if ( '' !== $tagline ) {
			$out .= '<span class="hsw-hd__brandtag">' . esc_html( $tagline ) . '</span>';
		}
		$out .= '</span></a>';

		return $out;
	}

	/**
	 * Main product search form. Uses WooCommerce's native GET query vars
	 * (`s` + `post_type=product`) and does not invent an AJAX endpoint.
	 *
	 * @param array $s       Settings.
	 * @param bool  $compact Compact form for drawer / overlay.
	 * @return string
	 */
	protected function search_form( $s, $compact = false ) {
		$placeholder = isset( $s['search_ph'] ) ? trim( (string) $s['search_ph'] ) : '';
		$placeholder = '' !== $placeholder ? $placeholder : __( 'جستجو در محصولات…', 'hamishop-elementor-widgets' );
		$button      = isset( $s['search_button'] ) ? trim( (string) $s['search_button'] ) : '';
		$button      = '' !== $button ? $button : __( 'جستجو', 'hamishop-elementor-widgets' );
		$show_cat    = ! $compact && ( ! isset( $s['search_cat'] ) || 'yes' === $s['search_cat'] );
		$cats        = $show_cat ? $this->product_categories( 12 ) : array();
		$selected    = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( $_GET['product_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filter.

		$out  = '<form class="hsw-hd__searchform' . ( $compact ? ' hsw-hd__searchform--compact' : '' ) . '" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
		$out .= '<input type="hidden" name="post_type" value="product">';
		if ( $cats ) {
			$out .= '<select class="hsw-hd__searchselect" name="product_cat" aria-label="' . esc_attr__( 'دسته‌بندی محصول', 'hamishop-elementor-widgets' ) . '">';
			$out .= '<option value="">' . esc_html__( 'همه دسته‌ها', 'hamishop-elementor-widgets' ) . '</option>';
			foreach ( $cats as $cat ) {
				$out .= '<option value="' . esc_attr( $cat->slug ) . '"' . selected( $selected, $cat->slug, false ) . '>' . esc_html( $cat->name ) . '</option>';
			}
			$out .= '</select>';
		}

		$out .= '<div class="hsw-hd__searchfield">';
		$out .= HSW_Helpers::icon( 'search', 'hsw-hd__searchicon' );
		$out .= '<input type="search" name="s" value="' . esc_attr( get_search_query() ) . '" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr__( 'جستجو در محصولات', 'hamishop-elementor-widgets' ) . '" autocomplete="off">';
		$out .= '</div>';
		/*
		 * One magnifier only: the field already carries a search icon, so the
		 * submit button shows the text label in the full form and the icon
		 * only in the compact (mobile/drawer) variant where the label is hidden.
		 */
		$btn_icon = $compact ? HSW_Helpers::icon( 'search' ) : '';
		$out .= '<button class="hsw-hd__searchbtn" type="submit">' . $btn_icon . '<span class="hsw-hd__searchbtnlabel">' . esc_html( $button ) . '</span></button>';
		$out .= '</form>';

		return $out;
	}

	/**
	 * Account action.
	 *
	 * @param array $s       Settings.
	 * @param bool  $minimal Minimal variant flag.
	 * @return string
	 */
	protected function account_html( $s, $minimal = false ) {
		$label = isset( $s['account_label'] ) ? trim( (string) $s['account_label'] ) : '';
		$sub   = $this->is_logged_in() ? __( 'حساب من', 'hamishop-elementor-widgets' ) : __( 'ورود / ثبت‌نام', 'hamishop-elementor-widgets' );
		// Empty label = the user cleared it, so no text is printed at all.
		$aria = '' !== $label ? $label : __( 'حساب کاربری', 'hamishop-elementor-widgets' );

		$out  = '<a class="hsw-hd__action hsw-hd__account' . ( $minimal ? ' hsw-hd__action--minimal' : '' ) . '" href="' . esc_url( $this->account_url() ) . '" aria-label="' . esc_attr( $aria ) . '">';
		$out .= '<span class="hsw-hd__actionicon">' . HSW_Helpers::icon( 'person' ) . '</span>';
		if ( $minimal ) {
			if ( '' !== $label ) {
				$out .= '<span class="hsw-hd__actionlabel">' . esc_html( $label ) . '</span>';
			}
		} elseif ( '' !== $label ) {
			$out .= '<span class="hsw-hd__actionmeta"><span class="hsw-hd__actionlabel">' . esc_html( $label ) . '</span><span class="hsw-hd__actionsub">' . esc_html( $sub ) . '</span></span>';
		}
		$out .= '</a>';

		return $out;
	}

	/**
	 * Cart action with a server-rendered count + subtotal. JS updates the
	 * count from WooCommerce fragments after add-to-cart.
	 *
	 * @param array $s       Settings.
	 * @param bool  $minimal Minimal variant flag.
	 * @return string
	 */
	protected function cart_html( $s, $minimal = false ) {
		$count    = $this->cart_count();
		$label    = isset( $s['cart_label'] ) ? trim( (string) $s['cart_label'] ) : '';
		$subtotal = $this->cart_subtotal();
		$total    = $count && $subtotal ? $subtotal : __( 'خالی', 'hamishop-elementor-widgets' );
		// Empty label = cleared on purpose: icon only, no text fallback.
		$aria     = '' !== $label ? $label : __( 'سبد خرید', 'hamishop-elementor-widgets' );

		$out  = '<a class="hsw-hd__action hsw-hd__cart' . ( $minimal ? ' hsw-hd__action--minimal' : '' ) . ( $count ? '' : ' is-empty' ) . '" href="' . esc_url( $this->cart_url() ) . '" aria-label="' . esc_attr( $aria ) . '">';
		/*
		 * `data-count` carries the number as a plain integer. The client reads it
		 * to decide whether a cached badge has to be cleared for a visitor with
		 * no cart; without the attribute the reset never fired.
		 */
		$out .= '<span class="hsw-hd__carticon">' . HSW_Helpers::icon( 'shopping_cart' ) . '<span class="hsw-hd__badge" data-hsw-cart-count data-count="' . esc_attr( (int) $count ) . '">' . esc_html( HSW_Helpers::fa_num( $count ) ) . '</span></span>';
		if ( ! $minimal && '' !== $label ) {
			$out .= '<span class="hsw-hd__actionmeta"><span class="hsw-hd__actionlabel">' . esc_html( $label ) . '</span><span class="hsw-hd__actionsub hsw-hd__carttotal" data-hsw-cart-total>' . wp_kses_post( $total ) . '</span></span>';
		}
		$out .= '</a>';

		return $out;
	}

	/**
	 * Phone link shown in the utility bar / drawer footer.
	 *
	 * @param array  $s     Settings.
	 * @param string $class Extra class.
	 * @return string
	 */
	protected function phone_html( $s, $class = '' ) {
		$phone = isset( $s['support_phone'] ) ? trim( (string) $s['support_phone'] ) : '';
		if ( '' === $phone ) {
			return '';
		}
		$tel = preg_replace( '/[^0-9+]/', '', $phone );
		if ( '' === $tel ) {
			return '';
		}
		$label = isset( $s['support_label'] ) && '' !== trim( (string) $s['support_label'] ) ? trim( (string) $s['support_label'] ) : __( 'پشتیبانی:', 'hamishop-elementor-widgets' );
		return '<a class="hsw-hd__phone ' . esc_attr( $class ) . '" href="' . esc_url( 'tel:' . $tel ) . '">' . HSW_Helpers::icon( 'support_agent' ) . '<span>' . esc_html( $label . ' ' . $phone ) . '</span></a>';
	}

	/**
	 * Top utility bar for the store header.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function topbar_html( $s ) {
		if ( isset( $s['show_topbar'] ) && 'yes' !== $s['show_topbar'] ) {
			return '';
		}
		$badge  = isset( $s['notice_badge'] ) ? trim( (string) $s['notice_badge'] ) : '';
		$notice = isset( $s['notice_text'] ) ? trim( (string) $s['notice_text'] ) : '';
		$locale = isset( $s['locale_label'] ) ? trim( (string) $s['locale_label'] ) : '';
		$links  = array();

		foreach ( array( 1, 2 ) as $i ) {
			$text = isset( $s[ 'top_link_' . $i . '_text' ] ) ? trim( (string) $s[ 'top_link_' . $i . '_text' ] ) : '';
			$url  = isset( $s[ 'top_link_' . $i . '_url' ] ) ? $s[ 'top_link_' . $i . '_url' ] : '';
			if ( is_array( $url ) ) {
				$url = isset( $url['url'] ) ? $url['url'] : '';
			}
			if ( '' !== $text && '' !== $url ) {
				$links[] = array(
					'text' => $text,
					'url'  => $url,
				);
			}
		}

		if ( '' === $badge && '' === $notice && '' === $locale && empty( $links ) && '' === $this->phone_html( $s ) ) {
			return '';
		}

		$out  = '<div class="hsw-hd__topbar"><div class="hsw-hd__topinner">';
		$out .= '<div class="hsw-hd__notice">';
		if ( '' !== $badge ) {
			$out .= '<span class="hsw-hd__noticebadge">' . HSW_Helpers::icon( 'local_shipping' ) . '<span>' . esc_html( $badge ) . '</span></span>';
		}
		if ( '' !== $notice ) {
			$out .= '<span class="hsw-hd__noticetext">' . esc_html( $notice ) . '</span>';
		}
		$out .= '</div>';

		$out .= '<div class="hsw-hd__utils">';
		$out .= $this->phone_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		if ( '' !== $locale ) {
			$out .= '<span class="hsw-hd__locale">' . HSW_Helpers::icon( 'language' ) . '<span>' . esc_html( $locale ) . '</span></span>';
		}
		if ( $links ) {
			$out .= '<span class="hsw-hd__utilslinks">';
			foreach ( $links as $index => $link ) {
				if ( $index > 0 ) {
					$out .= '<span class="hsw-hd__sep" aria-hidden="true"></span>';
				}
				$out .= '<a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['text'] ) . '</a>';
			}
			$out .= '</span>';
		}
		$out .= '</div></div></div>';

		return $out;
	}

	/**
	 * Native WordPress menu output.
	 *
	 * @param array  $s     Settings.
	 * @param string $class UL class.
	 * @return string
	 */
	protected function menu_html( $s, $class = 'hsw-hd__menu' ) {
		$menu_id = isset( $s['menu'] ) ? absint( $s['menu'] ) : 0;
		$args    = array(
			'container'     => false,
			'menu_class'    => $class,
			'menu_id'       => '',
			'items_wrap'    => '<ul class="%2$s">%3$s</ul>',
			'echo'          => false,
			'depth'         => 2,
			'fallback_cb'   => '__return_empty_string',
		);
		if ( $menu_id ) {
			$args['menu'] = $menu_id;
		} else {
			$args['theme_location'] = 'menu-1';
		}

		$html = wp_nav_menu( $args );
		if ( ! $html ) {
			$html = $this->fallback_menu( $class );
		}
		return $html;
	}

	/**
	 * Minimal fallback menu when no WordPress menu / location exists.
	 *
	 * @param string $class UL class.
	 * @return string
	 */
	protected function fallback_menu( $class = 'hsw-hd__menu' ) {
		$items = array(
			array(
				'url'  => home_url( '/' ),
				'text' => __( 'خانه', 'hamishop-elementor-widgets' ),
			),
			array(
				'url'  => $this->shop_url(),
				'text' => __( 'فروشگاه', 'hamishop-elementor-widgets' ),
			),
			array(
				'url'  => $this->blog_url(),
				'text' => __( 'مجله', 'hamishop-elementor-widgets' ),
			),
			array(
				'url'  => $this->account_url(),
				'text' => __( 'حساب کاربری', 'hamishop-elementor-widgets' ),
			),
		);

		$out = '<ul class="' . esc_attr( $class ) . '">';
		foreach ( $items as $item ) {
			$out .= '<li class="menu-item"><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Category mega menu panel (store header).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function mega_html( $s ) {
		$cats = $this->product_categories( 8 );
		if ( ! $cats ) {
			return '';
		}

		$per = isset( $s['mega_products'] ) ? absint( $s['mega_products'] ) : 4;
		$per = in_array( $per, array( 3, 4, 6 ), true ) ? $per : 4;

		$out  = '<div class="hsw-hd__megapanel" data-hsw-hd-mega-panel>';
		$out .= '<div class="hsw-hd__megainner">';
		$out .= '<div class="hsw-hd__megalayout">';

		/* ---- vertical, nested category list ---- */
		$out .= '<div class="hsw-hd__meganav">';
		$out .= '<ul class="hsw-hd__megatree">';

		foreach ( $cats as $cat ) {
			$url = get_term_link( $cat );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			$children = $this->product_categories( 8, $cat->term_id );

			$out .= '<li class="hsw-hd__megaitem" data-hsw-hd-mega-item>';
			$out .= '<button class="hsw-hd__megatoggle" type="button" data-hsw-hd-mega-cat="' . esc_attr( (string) $cat->term_id ) . '" data-hsw-hd-mega-url="' . esc_url( $url ) . '" data-hsw-hd-mega-name="' . esc_attr( $cat->name ) . '" aria-expanded="false">';
			$out .= '<span class="hsw-hd__megaicon">' . HSW_Helpers::icon( 'category' ) . '</span>';
			$out .= '<span class="hsw-hd__meganame">' . esc_html( $cat->name ) . '</span>';
			$out .= '<span class="hsw-hd__megacount">' . esc_html( HSW_Helpers::fa_num( $cat->count ) ) . '</span>';
			$out .= '<span class="hsw-hd__megachev">' . HSW_Helpers::icon( 'expand_more' ) . '</span>';
			$out .= '</button>';

			$out .= '<div class="hsw-hd__megabody" data-hsw-hd-mega-body aria-hidden="true"><div class="hsw-hd__megabodyinner">';
			if ( $children ) {
				$out .= '<ul class="hsw-hd__megachildren">';
				foreach ( $children as $child ) {
					$child_url = get_term_link( $child );
					if ( is_wp_error( $child_url ) ) {
						continue;
					}
					$out .= '<li><a href="' . esc_url( $child_url ) . '"><span>' . esc_html( $child->name ) . '</span><span class="hsw-hd__megacount">' . esc_html( HSW_Helpers::fa_num( $child->count ) ) . '</span></a></li>';
				}
				$out .= '</ul>';
			}
			$out .= '<a class="hsw-hd__megaall" href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهده همهٔ محصولات این دسته', 'hamishop-elementor-widgets' ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
			$out .= '</div></div>';
			$out .= '</li>';
		}

		$out .= '</ul></div>';

		/* ---- product preview (filled lazily by hsw-header.js) ---- */
		$out .= '<aside class="hsw-hd__megapreview" data-hsw-hd-mega-preview>';
		$out .= '<div class="hsw-hd__megapreviewhead">';
		$out .= '<span class="hsw-hd__megapreviewtitle" data-hsw-hd-mega-title>' . esc_html__( 'محصولات دسته', 'hamishop-elementor-widgets' ) . '</span>';
		$out .= '<a class="hsw-hd__megapreviewall" data-hsw-hd-mega-all href="' . esc_url( $this->shop_url() ) . '">' . esc_html__( 'مشاهده همه', 'hamishop-elementor-widgets' ) . '</a>';
		$out .= '</div>';
		$out .= '<div class="hsw-hd__megathumbs" data-hsw-hd-mega-products data-limit="' . esc_attr( (string) $per ) . '"></div>';
		$out .= '<p class="hsw-hd__megahint" data-hsw-hd-mega-hint data-empty="' . esc_attr__( 'محصولی برای نمایش در این دسته نیست.', 'hamishop-elementor-widgets' ) . '">' . esc_html__( 'برای دیدن محصولات، یک دسته را باز کنید.', 'hamishop-elementor-widgets' ) . '</p>';
		$out .= '</aside>';

		$out .= '</div>';

		$out .= '<a class="hsw-hd__megafoot" href="' . esc_url( $this->shop_url() ) . '">';
		$out .= HSW_Helpers::icon( 'storefront' );
		$out .= '<span>' . esc_html__( 'مشاهده همهٔ محصولات فروشگاه', 'hamishop-elementor-widgets' ) . '</span>';
		$out .= HSW_Helpers::icon( 'arrow_left_alt' );
		$out .= '</a>';

		$out .= '</div></div>';

		return $out;
	}

	/**
	 * Category trigger + optional mega panel.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function category_nav_html( $s ) {
		$title = isset( $s['mega_title'] ) && '' !== trim( (string) $s['mega_title'] ) ? trim( (string) $s['mega_title'] ) : __( 'دسته‌بندی محصولات', 'hamishop-elementor-widgets' );
		$mega  = $this->mega_html( $s );

		if ( ! $mega ) {
			return '<a class="hsw-hd__catbtn" href="' . esc_url( $this->shop_url() ) . '">' . HSW_Helpers::icon( 'grid_view' ) . '<span>' . esc_html( $title ) . '</span></a>';
		}

		$trigger = isset( $s['mega_trigger'] ) && 'click' === $s['mega_trigger'] ? 'click' : 'hover';

		$out  = '<div class="hsw-hd__mega' . ( 'hover' === $trigger ? ' hsw-hd__mega--hover' : '' ) . '" data-hsw-hd-mega>';
		$out .= '<button class="hsw-hd__catbtn" type="button" data-hsw-hd-mega-toggle aria-expanded="false">';
		$out .= HSW_Helpers::icon( 'grid_view' );
		$out .= '<span>' . esc_html( $title ) . '</span>';
		$out .= HSW_Helpers::icon( 'expand_more', 'hsw-hd__chev' );
		$out .= '</button>';
		$out .= $mega;
		$out .= '</div>';

		return $out;
	}

	/**
	 * Drawer categories list.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	protected function drawer_categories_html( $s ) {
		$cats = $this->product_categories( 8 );
		if ( ! $cats ) {
			return '';
		}
		$title = isset( $s['mega_title'] ) && '' !== trim( (string) $s['mega_title'] ) ? trim( (string) $s['mega_title'] ) : __( 'دسته‌بندی محصولات', 'hamishop-elementor-widgets' );

		$out  = '<div class="hsw-hd__drawercats">';
		$out .= '<div class="hsw-hd__drawertitle">' . HSW_Helpers::icon( 'grid_view' ) . '<span>' . esc_html( $title ) . '</span></div>';
		$out .= '<ul>';
		foreach ( $cats as $cat ) {
			$url = get_term_link( $cat );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			$out .= '<li><a href="' . esc_url( $url ) . '"><span>' . esc_html( $cat->name ) . '</span><span class="hsw-hd__megacount">' . esc_html( HSW_Helpers::fa_num( $cat->count ) ) . '</span></a></li>';
		}
		$out .= '</ul></div>';

		return $out;
	}

	/**
	 * Shared mobile drawer.
	 *
	 * @param array  $s       Settings.
	 * @param string $variant store|minimal.
	 * @return string
	 */
	protected function drawer_html( $s, $variant = 'store' ) {
		$id        = $this->uid() . '-drawer';
		$show_nav  = ! isset( $s['show_nav'] ) || 'yes' === $s['show_nav'];
		$show_srch = ! isset( $s['show_search'] ) || 'yes' === $s['show_search'];
		$show_cart = ! isset( $s['show_cart'] ) || 'yes' === $s['show_cart'];
		$show_acct = ! isset( $s['show_account'] ) || 'yes' === $s['show_account'];
		$cats      = ! isset( $s['drawer_cats'] ) || 'yes' === $s['drawer_cats'];

		$out  = '<div class="hsw-hd__drawer" id="' . esc_attr( $id ) . '" data-hsw-hd-drawer aria-hidden="true">';
		$out .= '<div class="hsw-hd__draweroverlay" data-hsw-hd-drawer-close></div>';
		$out .= '<aside class="hsw-hd__drawerpanel" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'منوی موبایل', 'hamishop-elementor-widgets' ) . '">';
		$out .= '<div class="hsw-hd__drawerhead">' . $this->brand_html( $s );
		$out .= '<button class="hsw-hd__drawerclose" type="button" data-hsw-hd-drawer-close aria-label="' . esc_attr__( 'بستن منو', 'hamishop-elementor-widgets' ) . '">' . HSW_Helpers::icon( 'close' ) . '</button>';
		$out .= '</div>';

		$out .= '<div class="hsw-hd__drawerbody">';
		if ( $show_acct ) {
			$out .= '<div class="hsw-hd__profile">';
			$out .= '<span class="hsw-hd__profileicon">' . HSW_Helpers::icon( 'person' ) . '</span>';
			$out .= '<span class="hsw-hd__profiletext"><strong>' . esc_html( $this->current_user_name() ) . '</strong><span>' . esc_html__( 'مدیریت حساب و سفارش‌ها', 'hamishop-elementor-widgets' ) . '</span></span>';
			$out .= '</div>';
		}

		if ( $show_srch ) {
			$out .= '<div class="hsw-hd__drawersearch">' . $this->search_form( $s, true ) . '</div>';
		}

		if ( $show_nav ) {
			$out .= '<nav class="hsw-hd__drawernav" aria-label="' . esc_attr__( 'منوی اصلی', 'hamishop-elementor-widgets' ) . '">' . $this->menu_html( $s, 'hsw-hd__menu hsw-hd__menu--drawer' ) . '</nav>';
		}

		if ( $cats ) {
			$out .= $this->drawer_categories_html( $s );
		}

		if ( 'minimal' === $variant && 'yes' === ( $s['show_cta'] ?? 'yes' ) ) {
			$text = isset( $s['cta_text'] ) && '' !== trim( (string) $s['cta_text'] ) ? trim( (string) $s['cta_text'] ) : __( 'مشاهده فروشگاه', 'hamishop-elementor-widgets' );
			$url  = isset( $s['cta_url'] ) ? $s['cta_url'] : array();
			$url  = is_array( $url ) ? ( $url['url'] ?? '' ) : $url;
			$url  = $url ? $url : $this->shop_url();
			$out .= '<a class="hsw-hd__drawercta" href="' . esc_url( $url ) . '">' . esc_html( $text ) . HSW_Helpers::icon( 'arrow_left_alt' ) . '</a>';
		}
		$out .= '</div>';

		$out .= '<div class="hsw-hd__drawerfoot">';
		if ( $show_cart ) {
			$count = $this->cart_count();
			$out  .= '<a class="hsw-hd__drawercart" href="' . esc_url( $this->cart_url() ) . '">' . HSW_Helpers::icon( 'shopping_cart' ) . '<span>' . esc_html( isset( $s['drawer_cart_text'] ) && '' !== trim( (string) $s['drawer_cart_text'] ) ? $s['drawer_cart_text'] : __( 'مشاهده سبد خرید', 'hamishop-elementor-widgets' ) ) . '</span><span class="hsw-hd__badge" data-hsw-cart-count data-count="' . esc_attr( (int) $count ) . '">' . esc_html( HSW_Helpers::fa_num( $count ) ) . '</span></a>';
		}
		$out .= '<div class="hsw-hd__drawerlinks">';
		if ( $show_acct ) {
			if ( $this->is_logged_in() ) {
				$out .= '<a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . HSW_Helpers::icon( 'logout' ) . '<span>' . esc_html__( 'خروج از حساب', 'hamishop-elementor-widgets' ) . '</span></a>';
			} else {
				$out .= '<a href="' . esc_url( $this->account_url() ) . '">' . HSW_Helpers::icon( 'login' ) . '<span>' . esc_html__( 'ورود / ثبت‌نام', 'hamishop-elementor-widgets' ) . '</span></a>';
			}
		}
		$phone = $this->phone_html( $s, 'hsw-hd__drawerphone' );
		if ( $phone ) {
			$out .= $phone;
		}
		$out .= '</div></div>';

		$out .= '</aside></div>';

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Controls
	 * ------------------------------------------------------------------ */

	protected function ctrl_visibility( $default = 'auto' ) {
		$this->add_control(
			'vis',
			array(
				'label'   => __( 'نمایش در', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $default,
				'options' => array(
					'auto'    => __( 'پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
					'desktop' => __( 'فقط دسکتاپ', 'hamishop-elementor-widgets' ),
					'mobile'  => __( 'فقط موبایل', 'hamishop-elementor-widgets' ),
				),
			)
		);
	}

	/**
	 * Brand + logo controls.
	 */
	protected function content_brand() {
		$this->start_controls_section(
			'sec_hd_brand',
			array(
				'label' => __( 'برند و لوگو', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'logo',
			array(
				'label' => __( 'لوگو', 'hamishop-elementor-widgets' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);

		$this->add_control(
			'brand_name',
			array(
				'label'       => __( 'نام برند', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => get_bloginfo( 'name' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'brand_tagline',
			array(
				'label'       => __( 'شعار / زیرنویس برند', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => get_bloginfo( 'description' ),
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'logo_width',
			array(
				'label'      => __( 'عرض لوگو', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 24,
						'max' => 180,
					),
				),
				'default'    => array(
					'size' => 44,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-logow: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Menu / sticky / drawer controls.
	 */
	protected function content_nav() {
		$this->start_controls_section(
			'sec_hd_nav',
			array(
				'label' => __( 'منو و ناوبری', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'menu',
			array(
				'label'       => __( 'منوی وردپرس', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 0,
				'options'     => $this->menu_options(),
				'description' => __( '«منوی موقعیت قالب» از منوی ثبت‌شده برای هدر قالب استفاده می‌کند. برای فروشگاه معمولاً منوی دسته‌بندی محصولات مناسب‌تر است.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'show_nav',
			array(
				'label'   => __( 'نمایش منو', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'drawer_cart_text',
			array(
				'label'       => __( 'متن دکمهٔ سبد خرید (منوی موبایل)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'مشاهده سبد خرید', 'hamishop-elementor-widgets' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'drawer_cats',
			array(
				'label'     => __( 'نمایش دسته‌بندی محصولات در منوی موبایل', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);


		/*
		 * Control id must stay prefixed: Elementor Pro registers its own
		 * `sticky` control on every element (Motion Effects), which would
		 * silently override ours and reset the default to an empty string.
		 */
		$this->add_control(
			'hd_sticky',
			array(
				'label'     => __( 'هدر چسبان (Sticky)', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		/*
		 * Smart hide: out of the way on scroll-down, back on the first
		 * scroll-up. Only meaningful while the header is pinned, hence the
		 * condition. Enabled by default because a pinned header that never
		 * yields eats a third of a phone screen.
		 */
		$this->add_control(
			'hd_autohide',
			array(
				'label'       => __( 'مخفی شدن هوشمند هنگام اسکرول', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'condition'   => array( 'hd_sticky' => 'yes' ),
				'description' => __( 'با اسکرول به پایین هدر کنار می‌رود و با کوچک‌ترین اسکرول به بالا دوباره ظاهر می‌شود — موبایل و دسکتاپ.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'hd_autohide_offset',
			array(
				'label'      => __( 'آستانهٔ شروع (پیکسل)', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 500, 'step' => 10 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 80 ),
				'condition'  => array(
					'hd_sticky'   => 'yes',
					'hd_autohide' => 'yes',
				),
				'description' => __( 'تا این ارتفاع اسکرول، هدر همیشه می‌ماند؛ بعد از آن با اسکرول به پایین مخفی می‌شود.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->ctrl_visibility();

		$this->end_controls_section();

		/* --------------------- منوی موبایل: رنگ‌ها --------------------- */
		$this->start_controls_section(
			'sec_drawer_style',
			array(
				'label' => __( 'منوی موبایل (رنگ‌ها)', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'drawer_cart_bg',
			array(
				'label'     => __( 'پس‌زمینهٔ دکمهٔ سبد خرید', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-hd__drawercart' => 'background: {{VALUE}} !important;' ),
			)
		);

		$this->add_control(
			'drawer_cart_c',
			array(
				'label'     => __( 'رنگ متن و آیکون دکمهٔ سبد خرید', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd__drawercart'          => 'color: {{VALUE}} !important;',
					'{{WRAPPER}} .hsw-hd__drawercart .hsw-ic'  => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'hd_actions_gap',
			array(
				'label'      => __( 'فاصلهٔ آیکن‌ها / اکشن‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .hsw-hd__actions' => 'gap: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
				'description' => __( 'فاصلهٔ بین آیکون‌های سبد، حساب، جستجو و برگر را تنظیم می‌کند؛ برای موبایل/تبلت/دسکتاپ جداگانه قابل تنظیم است.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_responsive_control(
			'hd_align',
			array(
				'label'     => __( 'چیدمان افقی هدر', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => __( 'راست', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-right' ),
					'center'     => array( 'title' => __( 'وسط', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-center' ),
					'flex-end'   => array( 'title' => __( 'چپ', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-h-align-left' ),
				),
				'selectors' => array( '{{WRAPPER}} .hsw-hd__maininner' => 'justify-content: {{VALUE}};' ),
				'separator' => 'before',
				'description' => __( 'برای وسط‌چین کردن هدر در موبایل، آیکون «وسط» را در حالت موبایل (آیکون گوشی) انتخاب کنید.', 'hamishop-elementor-widgets' ),
			)
		);

		$this->add_control(
			'drawer_search_c',
			array(
				'label'     => __( 'رنگ آیکون جستجو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-hd__drawersearch .hsw-ic' => 'color: {{VALUE}} !important;' ),
			)
		);

		$this->add_control(
			'drawer_close_c',
			array(
				'label'     => __( 'رنگ آیکون بستن (×)', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .hsw-hd__drawerclose' => 'color: {{VALUE}} !important;' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Search controls.
	 */
	protected function content_search() {
		$this->start_controls_section(
			'sec_hd_search',
			array(
				'label' => __( 'جستجو', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'   => __( 'نمایش جستجو', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'search_ph',
			array(
				'label'     => __( 'متن راهنمای جستجو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'جستجوی محصول، برند یا دسته‌بندی…', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_control(
			'search_cat',
			array(
				'label'     => __( 'انتخاب دسته‌بندی کنار جستجو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_control(
			'search_button',
			array(
				'label'     => __( 'متن دکمه جستجو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'جستجو', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Account / cart / support controls.
	 */
	protected function content_actions() {
		$this->start_controls_section(
			'sec_hd_actions',
			array(
				'label' => __( 'حساب، سبد و پشتیبانی', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_account',
			array(
				'label'   => __( 'نمایش حساب کاربری', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'account_label',
			array(
				'label'     => __( 'برچسب حساب', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'حساب کاربری', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_account' => 'yes' ),
			)
		);

		$this->add_control(
			'show_cart',
			array(
				'label'     => __( 'نمایش سبد خرید', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'cart_label',
			array(
				'label'     => __( 'برچسب سبد خرید', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'سبد خرید', 'hamishop-elementor-widgets' ),
				'condition' => array( 'show_cart' => 'yes' ),
			)
		);

		$this->add_control(
			'support_phone',
			array(
				'label'       => __( 'شماره پشتیبانی', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '021-00000000',
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'support_label',
			array(
				'label'   => __( 'برچسب پشتیبانی', 'hamishop-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'پشتیبانی:', 'hamishop-elementor-widgets' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Shared style controls.
	 *
	 * @param string $variant store|minimal.
	 */
	protected function content_style( $variant = 'store' ) {
		$this->start_controls_section(
			'sec_hd_style',
			array(
				'label' => __( 'استایل هدر', 'hamishop-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'primary_color',
			array(
				'label'     => __( 'رنگ اصلی', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-primary: {{VALUE}}; --hsw-primary: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'header_bg',
			array(
				'label'     => __( 'پس‌زمینه هدر', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'minimal' === $variant ? 'rgba(255,255,255,0.82)' : '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'رنگ متن', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'muted_color',
			array(
				'label'     => __( 'رنگ متن کم‌رنگ', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#64748b',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-muted: {{VALUE}};',
				),
			)
		);

		/*
		 * Icons used to follow the text colour, which on a dark or transparent
		 * header made the magnifier vanish. One control owns every header icon
		 * (search, account, cart, menu) and falls back to the text colour.
		 */
		$this->add_control(
			'icon_color',
			array(
				'label'       => __( 'رنگ آیکون‌ها', 'hamishop-elementor-widgets' ),
				'description' => __( 'جستجو، حساب کاربری، سبد خرید و منو. خالی = همان رنگ متن هدر.', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-icon: {{VALUE}};',
				),
			)
		);

		/*
		 * Dedicated pair for the three functional buttons. The palette already
		 * ships defaults (`--hsw-hd-act-icon` / `--hsw-hd-act-bg`); these two
		 * controls override them per header.
		 */
		$this->add_control(
			'action_icon_color',
			array(
				'label'       => __( 'رنگ آیکون دکمه‌های هدر (سبد خرید، حساب کاربری، همبرگری)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-act-icon: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'action_icon_bg',
			array(
				'label'       => __( 'رنگ پس‌زمینهٔ دکمه‌های هدر (سبد خرید، حساب کاربری، همبرگری)', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-act-bg: {{VALUE}};',
				),
			)
		);

		/*
		 * The three round icon buttons (menu, account, cart) also accept a
		 * background: on a light header they are invisible without one, and the
		 * customer asked for both knobs in one place.
		 */
		$this->add_control(
			'icon_bg',
			array(
				'label'       => __( 'پس‌زمینه آیکون‌ها', 'hamishop-elementor-widgets' ),
				'description' => __( 'دکمه‌های منو، حساب کاربری و سبد خرید. خالی = پس‌زمینهٔ پیش‌فرض هدر.', 'hamishop-elementor-widgets' ),
				'type'        => Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-icon-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => __( 'رنگ حاشیه‌ها', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#e2e8f0',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'search_bg',
			array(
				'label'     => __( 'پس‌زمینه جستجو', 'hamishop-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f1f5f9',
				'selectors' => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-search: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'گردی گوشه‌ها', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'default'    => array(
					'size' => 14,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_width',
			array(
				'label'      => __( 'عرض محتوا', 'hamishop-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 900,
						'max' => 1800,
					),
				),
				'default'    => array(
					'size' => 1320,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .hsw-hd' => '--hsw-hd-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'brand_typography',
				'label'    => __( 'تایپوگرافی نام برند', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-hd__brandname',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'menu_typography',
				'label'    => __( 'تایپوگرافی منو', 'hamishop-elementor-widgets' ),
				'selector' => '{{WRAPPER}} .hsw-hd__menu > li > a',
			)
		);

		$this->a_button(
			'cat',
			__( 'دکمهٔ دسته‌بندی محصولات', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-hd__catbtn',
			array( 'size' => 12, 'radius' => 10 )
		);

		$this->a_button(
			'search',
			__( 'دکمهٔ جستجو', 'hamishop-elementor-widgets' ),
			'{{WRAPPER}} .hsw-hd__searchbtn',
			array( 'size' => 11.5, 'radius' => 10 )
		);
		$this->end_controls_section();
	}

	/**
	 * Root wrapper opening tag.
	 *
	 * @param string $variant store|minimal.
	 * @param array  $s       Settings.
	 * @return string
	 */
	protected function open_root( $variant, $s ) {
		$sticky   = ! isset( $s['hd_sticky'] ) || 'yes' === $s['hd_sticky'];
		$autohide = $sticky && ( ! isset( $s['hd_autohide'] ) || 'yes' === $s['hd_autohide'] );

		$classes = 'hsw-h hsw-hd hsw-hd--' . $variant;
		if ( $sticky ) {
			$classes .= ' hsw-hd--sticky';
		}
		if ( $autohide ) {
			$classes .= ' hsw-hd--autohide';
		}
		$vis = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
		$classes .= ' hsw-vis-' . $vis . $this->hsw_font_class();

		$offset = isset( $s['hd_autohide_offset']['size'] ) ? (int) $s['hd_autohide_offset']['size'] : 80;

		/*
		 * The pinned header leaves the document flow, so its height has to be
		 * held by something else or the page jumps up behind it. The
		 * placeholder sits in the exact spot the header occupied; JS mirrors
		 * the measured height into it right before pinning.
		 */
		$ph = $sticky ? '<div class="hsw-hd__ph" aria-hidden="true"></div>' : '';

		return $ph . '<div class="' . esc_attr( $classes ) . '" data-hsw-header data-hsw-hideoffset="' . esc_attr( $offset ) . '" data-hsw-fragments="' . esc_url( $this->fragments_url() ) . '" data-hsw-rest="' . esc_url( rest_url( 'hsw/v1/header-products' ) ) . '">';
	}
}
