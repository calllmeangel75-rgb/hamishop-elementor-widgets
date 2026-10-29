<?php
/**
 * Printable invoice / postal label.
 *
 * One order, one A4 sheet, rendered on demand and never written to disk: the
 * document is a plain HTML page with print CSS, so "Save as PDF" in the
 * browser produces the PDF without a PDF library, temporary files or uploads
 * usage. That keeps the plugin light and the host quiet.
 *
 * Two entry points share the same document:
 *   - the customer, from the order view in the account panel;
 *   - the shop manager, from the order edit screen (HPOS and legacy posts).
 *
 * Access is a nonce plus either order ownership or `edit_shop_orders`, so a
 * leaked URL alone is not enough.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Invoice {

	/** Feature switch, flipped from HamiShop → کنترل ویجت‌ها. */
	const ENABLED = 'hsw_invoice_enabled';

	/** Force pure black and white instead of the palette accent. */
	const MONO = 'hsw_invoice_mono';

	public function __construct() {
		/* Priority 0: answer before the theme or WooCommerce templates run. */
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );

		/* Order screen button: HPOS and the legacy post editor. */
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'hpos_button' ) );
		add_action( 'add_meta_boxes', array( $this, 'legacy_meta_box' ) );
	}

	public static function enabled() {
		return 'yes' === get_option( self::ENABLED, 'yes' );
	}

	/* ---------------------------------------------------------------------
	 * Links
	 * ------------------------------------------------------------------ */

	/**
	 * Signed URL for one order.
	 *
	 * @param int  $order_id Order id.
	 * @param bool $autoprint Open the print dialog on load.
	 * @return string
	 */
	public static function url( $order_id, $autoprint = false ) {
		$args = array(
			'hsw_invoice' => (int) $order_id,
			'key'         => wp_create_nonce( 'hsw_invoice_' . (int) $order_id ),
		);
		if ( $autoprint ) {
			$args['autoprint'] = 1;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/* ---------------------------------------------------------------------
	 * Access
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve the order for the current request, or an error code.
	 *
	 * @return array{order:WC_Order|null,error:string}
	 */
	private function authorize() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$order_id = isset( $_GET['hsw_invoice'] ) ? absint( $_GET['hsw_invoice'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			return array(
				'order' => null,
				'error' => 'not_found',
			);
		}

		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $key || ! wp_verify_nonce( $key, 'hsw_invoice_' . $order_id ) ) {
			return array(
				'order' => null,
				'error' => 'forbidden',
			);
		}

		$is_owner  = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
		$is_staff  = current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
		if ( ! $is_owner && ! $is_staff ) {
			return array(
				'order' => null,
				'error' => 'forbidden',
			);
		}

		return array(
			'order' => $order,
			'error' => '',
		);
	}

	/**
	 * Serve `?hsw_invoice=<id>&key=<nonce>` as a standalone print page.
	 */
	public function maybe_serve() {
		if ( empty( $_GET['hsw_invoice'] ) || ! self::enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified in authorize().
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$check = $this->authorize();
		if ( ! $check['order'] ) {
			status_header( 'forbidden' === $check['error'] ? 403 : 404 );
			$this->render_error( $check['error'] );
			exit;
		}

		$autoprint = ! empty( $_GET['autoprint'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo $this->standalone( $check['order'], $autoprint ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		exit;
	}

	private function render_error( $code ) {
		$message = 'forbidden' === $code
			? __( 'برای مشاهدهٔ این فاکتور دسترسی ندارید.', 'hamishop-elementor-widgets' )
			: __( 'این سفارش پیدا نشد.', 'hamishop-elementor-widgets' );
		$login   = wp_login_url( home_url( '/' ) );

		echo '<!doctype html><html dir="rtl" lang="fa-IR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $message ) . '</title>';
		echo '<style>body{margin:0;display:grid;place-items:center;min-height:100vh;background:#f5f5f5;font:14px/1.8 Tahoma,Arial,sans-serif;color:#111}'
			. 'div{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:26px 30px;text-align:center;max-width:420px}'
			. 'a{display:inline-block;margin-top:12px;padding:8px 16px;border-radius:9px;background:#111;color:#fff;text-decoration:none}</style></head><body>';
		echo '<div><p>' . esc_html( $message ) . '</p><a href="' . esc_url( $login ) . '">' . esc_html__( 'ورود به حساب', 'hamishop-elementor-widgets' ) . '</a></div>';
		echo '</body></html>';
	}

	/* ---------------------------------------------------------------------
	 * Standalone page
	 * ------------------------------------------------------------------ */

	private static function css_url() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		return HSW_URL . 'assets/css/hsw-invoice' . $suffix . '.css?ver=' . HSW_VERSION;
	}

	/**
	 * @param WC_Order $order     Order.
	 * @param bool     $autoprint Open the print dialog on load.
	 * @return string
	 */
	private function standalone( $order, $autoprint ) {
		$title = sprintf(
			/* translators: %s: order number. */
			__( 'فاکتور سفارش %s', 'hamishop-elementor-widgets' ),
			$order->get_order_number()
		);

		$html  = '<!doctype html><html dir="rtl" lang="fa-IR"><head><meta charset="utf-8">';
		$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
		$html .= '<meta name="robots" content="noindex,nofollow">';
		$html .= '<title>' . esc_html( $title ) . '</title>';
		$html .= '<link rel="stylesheet" href="' . esc_url( self::css_url() ) . '">';
		$html .= '</head><body class="hsw-sheetbody">';

		$html .= '<div class="hsw-sheetbar">';
		$html .= '<button type="button" class="hsw-sheetbar__print" onclick="window.print()">' . esc_html__( 'چاپ / ذخیرهٔ PDF', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '<span>' . esc_html__( 'در پنجرهٔ چاپ، مقصد را «Save as PDF» بگذارید تا فایل بدون ذخیره‌سازی روی هاست ساخته شود.', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<button type="button" class="hsw-sheetbar__close" onclick="window.close()">' . esc_html__( 'بستن', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '</div>';

		$html .= $this->document( $order );
		$html .= $autoprint ? '<script>window.addEventListener("load",function(){window.print();});</script>' : '';
		$html .= '</body></html>';

		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Document
	 * ------------------------------------------------------------------ */

	/**
	 * The A4 sheet itself.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public function document( $order ) {
		$brand    = class_exists( 'HSW_Branding' ) ? HSW_Branding::get() : array();
		$name     = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$logo     = class_exists( 'HSW_Branding' ) ? HSW_Branding::logo_html( 'hsw-inv__logo' ) : '';
		$currency = $order->get_currency();

		$created = $order->get_date_created();
		$ts      = $created ? $created->getTimestamp() : 0;
		$date    = $ts ? HSW_Helpers::format_date( $ts, 'j F Y' ) : '—';
		$year    = $ts ? HSW_Helpers::format_date( $ts, 'Y' ) : '';
		$number  = $order->get_order_number();

		$items      = $order->get_items();
		$item_count = 0;
		$weight     = 0.0;
		foreach ( $items as $item ) {
			$item_count += (int) $item->get_quantity();
			$product     = $item->get_product();
			if ( $product && $product->get_weight() ) {
				$weight += (float) $product->get_weight() * (int) $item->get_quantity();
			}
		}

		$rows     = count( $items );
		$density  = $rows > 32 ? 'hsw-inv--ultra' : ( $rows > 18 ? 'hsw-inv--tight' : ( $rows > 10 ? 'hsw-inv--dense' : '' ) );
		$mono     = 'yes' === get_option( self::MONO, 'no' );
		$is_cod   = 'cod' === $order->get_payment_method();
		$ship     = $order->get_address( 'shipping' );
		$bill     = $order->get_address( 'billing' );
		$to       = '' !== trim( (string) ( $ship['address_1'] ?? '' ) ) ? $ship : $bill;

		$classes = 'hsw-inv hsw-inv--sheet' . ( $density ? ' ' . $density : '' ) . ( $mono ? ' hsw-inv--mono' : '' );

		/* ---------- head ---------- */
		$html  = '<article class="' . esc_attr( $classes ) . '">';
		$html .= '<header class="hsw-inv__top">';
		$html .= '<div class="hsw-inv__brand">' . $logo;
		$html .= '<div class="hsw-inv__brandtext"><strong>' . esc_html( $name ) . '</strong>';
		$html .= '<span dir="ltr">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</span>';
		$html .= '</div></div>';

		$html .= '<div class="hsw-inv__title-block">';
		$html .= '<h1 class="hsw-inv__title">' . esc_html__( 'فاکتور فروش', 'hamishop-elementor-widgets' ) . '</h1>';
		$html .= '<dl class="hsw-inv__meta">';
		$html .= '<div><dt>' . esc_html__( 'شماره فاکتور', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( ( '' !== $year ? $year . '-' : '' ) . $number ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'تاریخ صدور', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( $date ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'شماره سفارش', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( $number ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'وضعیت', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</dd></div>';
		$html .= '</dl></div>';
		$html .= '</header>';

		/* ---------- postal chart: sender / recipient ---------- */
		$html .= '<section class="hsw-inv__postal" aria-label="' . esc_attr__( 'مشخصات مرسولهٔ پستی', 'hamishop-elementor-widgets' ) . '">';
		$html .= '<header class="hsw-inv__posthead"><span>' . esc_html__( 'مشخصات مرسولهٔ پستی', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<small>' . esc_html__( 'چسباندن روی بسته؛ گیرنده و فرستنده مطابق فرم ادارهٔ پست.', 'hamishop-elementor-widgets' ) . '</small></header>';

		$html .= '<div class="hsw-inv__postgrid">';

		/* sender */
		$html .= '<div class="hsw-inv__postbox hsw-inv__postbox--from">';
		$html .= '<h2>' . esc_html__( 'فرستنده', 'hamishop-elementor-widgets' ) . '</h2>';
		$html .= '<p class="hsw-inv__postname">' . esc_html( $name ) . '</p>';
		if ( '' !== trim( (string) ( $brand['store_address'] ?? '' ) ) ) {
			$html .= '<p class="hsw-inv__postaddr">' . esc_html( (string) $brand['store_address'] ) . '</p>';
		}
		$html .= '<dl class="hsw-inv__postmeta">';
		$postal = trim( (string) ( $brand['store_postal'] ?? '' ) );
		$html  .= '<div><dt>' . esc_html__( 'کد پستی', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( '' !== $postal ? HSW_Helpers::fa_num( $postal ) : '—' ) . '</dd></div>';
		if ( '' !== trim( (string) ( $brand['store_phone'] ?? '' ) ) ) {
			$html .= '<div><dt>' . esc_html__( 'تلفن', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( (string) $brand['store_phone'] ) ) . '</dd></div>';
		}
		$html .= '</dl></div>';

		/* recipient */
		$to_name = trim( ( $to['first_name'] ?? '' ) . ' ' . ( $to['last_name'] ?? '' ) );
		$html   .= '<div class="hsw-inv__postbox hsw-inv__postbox--to">';
		$html   .= '<h2>' . esc_html__( 'گیرنده', 'hamishop-elementor-widgets' ) . '</h2>';
		$html   .= '<p class="hsw-inv__postname">' . esc_html( '' !== $to_name ? $to_name : '—' ) . '</p>';

		$addr_lines = array_filter(
			array(
				(string) ( $to['company'] ?? '' ),
				(string) ( $to['address_1'] ?? '' ),
				(string) ( $to['address_2'] ?? '' ),
				trim( (string) ( $to['state'] ?? '' ) . ' - ' . (string) ( $to['city'] ?? '' ), ' -' ),
			)
		);
		$html .= '<p class="hsw-inv__postaddr">' . esc_html( implode( '، ', $addr_lines ) ) . '</p>';

		/* postal code as ten boxes, the way the counter writes it */
		$zip   = preg_replace( '/\D/', '', (string) ( $to['postcode'] ?? '' ) );
		$html .= '<div class="hsw-inv__zip" aria-label="' . esc_attr__( 'کد پستی گیرنده', 'hamishop-elementor-widgets' ) . '">';
		$html .= '<span class="hsw-inv__ziplabel">' . esc_html__( 'کد پستی', 'hamishop-elementor-widgets' ) . '</span><span class="hsw-inv__zipdigits">';
		for ( $d = 0; $d < 10; $d++ ) {
			$digit = isset( $zip[ $d ] ) ? $zip[ $d ] : '';
			$html .= '<b' . ( '' === $digit ? ' class="is-empty"' : '' ) . '>' . esc_html( '' !== $digit ? HSW_Helpers::fa_num( $digit ) : '&nbsp;' ) . '</b>';
		}
		$html .= '</span></div>';

		$html .= '<dl class="hsw-inv__postmeta">';
		$html .= '<div><dt>' . esc_html__( 'تلفن', 'hamishop-elementor-widgets' ) . '</dt><dd dir="ltr">' . esc_html( HSW_Helpers::fa_num( (string) ( $to['phone'] ?? '' ) ) ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'تعداد اقلام', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( $item_count ) ) . '</dd></div>';
		if ( $weight > 0 ) {
			$html .= '<div><dt>' . esc_html__( 'وزن', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( HSW_Helpers::fa_num( number_format( $weight, 2, '.', '' ) ) . ' ' . esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) ) . '</dd></div>';
		}
		if ( $is_cod ) {
			$html .= '<div class="hsw-inv__cod"><dt>' . esc_html__( 'پرداخت در محل', 'hamishop-elementor-widgets' ) . '</dt><dd>' . wp_kses_post( $this->money( $order->get_total(), $currency ) ) . '</dd></div>';
		}
		$html .= '</dl></div>';

		$html .= '</div></section>';

		/* ---------- items ---------- */
		$html .= '<table class="hsw-inv__items"><thead><tr>';
		$html .= '<th class="hsw-inv__c-num">' . esc_html__( 'ردیف', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-name">' . esc_html__( 'شرح کالا', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-qty">' . esc_html__( 'تعداد', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-price">' . esc_html__( 'بهای واحد', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '<th class="hsw-inv__c-total">' . esc_html__( 'مبلغ کل', 'hamishop-elementor-widgets' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$i = 0;
		foreach ( $items as $item ) {
			$i++;
			$qty  = (int) $item->get_quantity();
			$line = (float) $item->get_total();
			$unit = $qty > 0 ? $line / $qty : $line;

			$meta = wc_display_item_meta( $item, array( 'echo' => false, 'before' => '', 'separator' => ' · ', 'after' => '' ) );
			$meta = trim( wp_strip_all_tags( (string) $meta ) );

			$html .= '<tr>';
			$html .= '<td class="hsw-inv__c-num">' . esc_html( HSW_Helpers::fa_num( $i ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-name"><span class="hsw-inv__pname">' . esc_html( $item->get_name() ) . '</span>';
			if ( '' !== $meta ) {
				$html .= '<span class="hsw-inv__pmeta">' . esc_html( $meta ) . '</span>';
			}
			$html .= '</td>';
			$html .= '<td class="hsw-inv__c-qty">' . esc_html( HSW_Helpers::fa_num( $qty ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-price">' . wp_kses_post( $this->money( $unit, $currency ) ) . '</td>';
			$html .= '<td class="hsw-inv__c-total">' . wp_kses_post( $this->money( $line, $currency ) ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		/* ---------- totals ---------- */
		$totals   = array();
		$totals[] = array( __( 'جمع کل', 'hamishop-elementor-widgets' ), (float) $order->get_subtotal() );
		if ( (float) $order->get_discount_total() > 0 ) {
			$totals[] = array( __( 'تخفیف', 'hamishop-elementor-widgets' ), -(float) $order->get_discount_total() );
		}
		if ( (float) $order->get_shipping_total() > 0 ) {
			$totals[] = array( __( 'هزینهٔ ارسال', 'hamishop-elementor-widgets' ), (float) $order->get_shipping_total() );
		}
		foreach ( $order->get_fees() as $fee ) {
			$totals[] = array( $fee->get_name(), (float) $fee->get_total() );
		}
		if ( (float) $order->get_total_tax() > 0 ) {
			$totals[] = array( __( 'مالیات', 'hamishop-elementor-widgets' ), (float) $order->get_total_tax() );
		}

		$html .= '<div class="hsw-inv__settle">';
		$html .= '<table class="hsw-inv__totals"><tbody>';
		foreach ( $totals as $row ) {
			$value = (float) $row[1];
			$html .= '<tr><th>' . esc_html( $row[0] ) . '</th><td>' . wp_kses_post( $value < 0 ? '− ' . $this->money( abs( $value ), $currency ) : $this->money( $value, $currency ) ) . '</td></tr>';
		}
		$html .= '<tr class="hsw-inv__grand"><th>' . esc_html__( 'مبلغ کل', 'hamishop-elementor-widgets' ) . '</th><td>' . wp_kses_post( $this->money( $order->get_total(), $currency ) ) . '</td></tr>';
		$html .= '</tbody></table>';

		$words = HSW_Helpers::number_words( (float) $order->get_total() );
		$html .= '<div class="hsw-inv__paidbox"><span>' . esc_html__( 'به حروف:', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<p class="hsw-inv__words">' . esc_html( $words . ' ' . $this->currency_label( $currency ) ) . '</p>';
		$html .= '</div></div>';

		$payment = (string) $order->get_payment_method_title();
		$html   .= '<dl class="hsw-inv__paymeta">';
		$html   .= '<div><dt>' . esc_html__( 'روش پرداخت:', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( '' !== trim( $payment ) ? $payment : __( 'ثبت نشده', 'hamishop-elementor-widgets' ) ) . '</dd></div>';
		$html   .= '<div><dt>' . esc_html__( 'تاریخ ثبت:', 'hamishop-elementor-widgets' ) . '</dt><dd>' . esc_html( $ts ? HSW_Helpers::format_date( $ts ) : '—' ) . '</dd></div>';
		$html   .= '</dl>';

		/* ---------- stamp + signature ---------- */
		$stamp = trim( (string) ( $brand['stamp_url'] ?? '' ) );
		$html .= '<div class="hsw-inv__sign">';
		$html .= '<span class="hsw-inv__signline">' . esc_html__( 'امضای خریدار', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<span class="hsw-inv__signline hsw-inv__signline--seller">' . esc_html__( 'مهر و امضای فروشنده', 'hamishop-elementor-widgets' ) . '</span>';
		if ( '' !== $stamp ) {
			$html .= '<img class="hsw-inv__stamp" src="' . esc_url( $stamp ) . '" alt="">';
		}
		$html .= '</div>';

		$html .= '<footer class="hsw-inv__foot"><span>' . esc_html__( 'این فاکتور به‌صورت الکترونیکی و بدون ذخیره‌سازی روی سرور صادر شده است.', 'hamishop-elementor-widgets' ) . '</span>';
		$html .= '<span dir="ltr">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</span></footer>';

		return $html . '</article>';
	}

	/**
	 * Price with Persian digits, matching the rest of the store.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	private function money( $amount, $currency ) {
		$html = wc_price( $amount, array( 'currency' => $currency ) );
		return class_exists( 'HSW_Helpers' ) ? HSW_Helpers::price_html_fa( $html ) : $html;
	}

	private function currency_label( $currency ) {
		if ( function_exists( 'get_woocommerce_currency' ) && 'IRT' === $currency ) {
			return __( 'تومان', 'hamishop-elementor-widgets' );
		}

		return $currency;
	}

	/* ---------------------------------------------------------------------
	 * Admin entry points
	 * ------------------------------------------------------------------ */

	/**
	 * Button on the HPOS / modern order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public function hpos_button( $order ) {
		if ( ! self::enabled() || ! $order instanceof WC_Order ) {
			return;
		}
		echo '<p class="hsw-inv-btnrow"><a class="button button-primary hsw-inv-live" target="_blank" rel="noopener" href="'
			. esc_url( self::url( $order->get_id() ) ) . '">' . esc_html__( 'چاپ فاکتور و برچسب پستی', 'hamishop-elementor-widgets' ) . '</a></p>';
	}

	/**
	 * Legacy meta box for the classic order post screen.
	 */
	public function legacy_meta_box() {
		if ( ! self::enabled() ) {
			return;
		}
		$screen = class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box(
			'hsw-invoice-box',
			__( 'فاکتور HamiShop', 'hamishop-elementor-widgets' ),
			array( $this, 'legacy_box_html' ),
			$screen,
			'side',
			'high'
		);
	}

	public function legacy_box_html( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : (int) $post_or_order );
		if ( ! $order ) {
			return;
		}
		echo '<p><a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( self::url( $order->get_id() ) ) . '">'
			. esc_html__( 'چاپ فاکتور و برچسب پستی', 'hamishop-elementor-widgets' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'در پنجرهٔ چاپ مقصد را «Save as PDF» بگذارید؛ فایلی روی هاست ذخیره نمی‌شود.', 'hamishop-elementor-widgets' ) . '</p>';
	}
}
