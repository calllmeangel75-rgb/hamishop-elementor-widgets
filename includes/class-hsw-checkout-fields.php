<?php
/**
 * Native checkout extras: delivery day + time slot and the legal-invoice
 * request.
 *
 * These used to be two custom widgets with their own REST routes, session
 * storage and JavaScript. Here they are ordinary WooCommerce checkout fields:
 * WooCommerce validates them, saves them to order meta (HPOS-safe), prints
 * them in the admin order screen, in emails and on the order-received page.
 *
 * Everything is behind one switch (`hsw_checkout_fields`, default on). When it
 * is off the class registers no hook at all and costs nothing.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Checkout_Fields {

	/** On/off switch. */
	const OPTION = 'hsw_checkout_fields';

	/** Order meta keys (HPOS-safe, order meta table). */
	const META_DATE    = '_hsw_delivery_date';
	const META_SLOT    = '_hsw_delivery_slot';
	const META_INVOICE = '_hsw_legal_invoice';

	/**
	 * Is the feature enabled?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === get_option( self::OPTION, 'yes' );
	}

	/**
	 * Wire the WooCommerce hooks — only when the feature is on.
	 */
	public function __construct() {
		if ( ! self::enabled() ) {
			return;
		}

		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_fields' ), 30 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_display' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display' ) );
	}

	/* =========================================================================
	 *  FIELDS
	 * ====================================================================== */

	/**
	 * Add the fields to WooCommerce's native `order` fieldset.
	 *
	 * Delivery day/slot only make sense for a cart that needs shipping; the
	 * invoice request applies to every order. Order notes are moved to the end
	 * so the delivery choice is the first thing the customer sees.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function add_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $fields;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return $fields;
		}

		if ( ! isset( $fields['order'] ) || ! is_array( $fields['order'] ) ) {
			$fields['order'] = array();
		}

		if ( self::needs_delivery() ) {
			$fields['order']['hsw_delivery_date'] = array(
				'type'     => 'select',
				'label'    => __( 'روز تحویل', 'hamishop-elementor-widgets' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'hsw-field-delivery' ),
				'options'  => self::date_options(),
				'priority' => 21,
			);

			$fields['order']['hsw_delivery_slot'] = array(
				'type'     => 'select',
				'label'    => __( 'بازهٔ زمانی تحویل', 'hamishop-elementor-widgets' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'hsw-field-delivery' ),
				'options'  => self::slot_field_options(),
				'priority' => 22,
			);
		}

		$fields['order']['hsw_legal_invoice'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'برای این سفارش فاکتور قانونی صادر شود.', 'hamishop-elementor-widgets' ),
			'required'    => false,
			'class'       => array( 'form-row-wide', 'hsw-field-invoice' ),
			'priority'    => 23,
		);

		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['priority'] = 30;
		}

		return $fields;
	}

	/**
	 * Does this cart need a courier?
	 *
	 * @return bool
	 */
	private static function needs_delivery() {
		return function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping();
	}

	/**
	 * Selectable delivery days.
	 *
	 * Starts tomorrow by default, so the customer never picks a day that has
	 * already passed while the page was open. Both numbers are filterable for
	 * stores that need same-day delivery or a longer horizon.
	 *
	 * @return array<string,string> ISO date => label.
	 */
	public static function date_options() {
		$days  = max( 1, min( 30, (int) apply_filters( 'hsw_checkout_delivery_days', 7 ) ) );
		$lead  = max( 0, min( 14, (int) apply_filters( 'hsw_checkout_delivery_lead_days', 1 ) ) );
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'now', $tz );
		$out   = array( '' => __( 'روز تحویل را انتخاب کنید', 'hamishop-elementor-widgets' ) );

		for ( $i = 0; $i < $days; $i++ ) {
			$day         = $today->modify( '+' . ( $lead + $i ) . ' day' )->setTime( 0, 0, 0 );
			$out[ $day->format( 'Y-m-d' ) ] = self::date_label( $day );
		}

		return $out;
	}

	/**
	 * Human label for a delivery day.
	 *
	 * @param DateTimeImmutable $day Day at midnight in the site timezone.
	 * @return string
	 */
	private static function date_label( DateTimeImmutable $day ) {
		$weekdays = array(
			'0' => __( 'یکشنبه', 'hamishop-elementor-widgets' ),
			'1' => __( 'دوشنبه', 'hamishop-elementor-widgets' ),
			'2' => __( 'سه‌شنبه', 'hamishop-elementor-widgets' ),
			'3' => __( 'چهارشنبه', 'hamishop-elementor-widgets' ),
			'4' => __( 'پنجشنبه', 'hamishop-elementor-widgets' ),
			'5' => __( 'جمعه', 'hamishop-elementor-widgets' ),
			'6' => __( 'شنبه', 'hamishop-elementor-widgets' ),
		);

		$weekday = isset( $weekdays[ $day->format( 'w' ) ] ) ? $weekdays[ $day->format( 'w' ) ] : '';
		$label   = trim( $weekday . ' ' . HSW_Helpers::format_date( $day->getTimestamp(), 'j F' ) );

		$tomorrow = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 day' )->setTime( 0, 0, 0 );
		if ( $day->format( 'Y-m-d' ) === $tomorrow->format( 'Y-m-d' ) ) {
			/* translators: %s: date label, e.g. «شنبه ۱۲ فروردین». */
			return sprintf( __( 'فردا، %s', 'hamishop-elementor-widgets' ), $label );
		}

		return $label;
	}

	/**
	 * Available delivery slots (stored value == display label).
	 *
	 * @return string[]
	 */
	public static function slot_options() {
		$slots = apply_filters(
			'hsw_checkout_delivery_slots',
			array(
				__( 'ساعت ۹ تا ۱۳', 'hamishop-elementor-widgets' ),
				__( 'ساعت ۱۳ تا ۱۷', 'hamishop-elementor-widgets' ),
				__( 'ساعت ۱۷ تا ۲۱', 'hamishop-elementor-widgets' ),
			)
		);

		return array_values( array_filter( array_map( 'strval', (array) $slots ) ) );
	}

	/**
	 * Slot options with the empty placeholder WooCommerce expects.
	 *
	 * @return array<string,string>
	 */
	public static function slot_field_options() {
		$out = array( '' => __( 'بازهٔ زمانی را انتخاب کنید', 'hamishop-elementor-widgets' ) );
		foreach ( self::slot_options() as $slot ) {
			$out[ $slot ] = $slot;
		}
		return $out;
	}

	/* =========================================================================
	 *  VALIDATE / SAVE
	 * ====================================================================== */

	/**
	 * Reject a date/slot the customer could not have picked from today's form
	 * (stale page, tampered POST, or the clock rolled past midnight).
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Validation errors.
	 */
	public function validate( $data, $errors ) {
		if ( ! self::needs_delivery() || ! is_array( $data ) ) {
			return;
		}

		$date = isset( $data['hsw_delivery_date'] ) ? wc_clean( $data['hsw_delivery_date'] ) : '';
		if ( '' !== $date && ! array_key_exists( $date, self::date_options() ) ) {
			$errors->add(
				'hsw_delivery_date',
				__( 'روز تحویل انتخاب‌شده معتبر نیست. صفحه را دوباره باز کنید و روز را انتخاب کنید.', 'hamishop-elementor-widgets' )
			);
		}

		$slot = isset( $data['hsw_delivery_slot'] ) ? wc_clean( $data['hsw_delivery_slot'] ) : '';
		if ( '' !== $slot && ! in_array( $slot, self::slot_options(), true ) ) {
			$errors->add(
				'hsw_delivery_slot',
				__( 'بازهٔ زمانی تحویل انتخاب‌شده معتبر نیست. لطفاً دوباره انتخاب کنید.', 'hamishop-elementor-widgets' )
			);
		}
	}

	/**
	 * Persist the fields on the new order.
	 *
	 * @param WC_Order $order New order.
	 * @param array    $data  Posted checkout data.
	 */
	public function save( $order, $data ) {
		if ( ! $order instanceof WC_Order || ! is_array( $data ) ) {
			return;
		}

		if ( self::needs_delivery() ) {
			$date = isset( $data['hsw_delivery_date'] ) ? wc_clean( $data['hsw_delivery_date'] ) : '';
			if ( '' !== $date && array_key_exists( $date, self::date_options() ) ) {
				$order->update_meta_data( self::META_DATE, $date );
			}

			$slot = isset( $data['hsw_delivery_slot'] ) ? wc_clean( $data['hsw_delivery_slot'] ) : '';
			if ( in_array( $slot, self::slot_options(), true ) ) {
				$order->update_meta_data( self::META_SLOT, $slot );
			}
		}

		$order->update_meta_data( self::META_INVOICE, empty( $data['hsw_legal_invoice'] ) ? 'no' : 'yes' );
	}

	/* =========================================================================
	 *  DISPLAY
	 * ====================================================================== */

	/**
	 * Rows for the admin screen, emails and the customer's order page.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string,array{label:string,value:string}>
	 */
	private static function order_rows( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		$rows = array();

		$date = (string) $order->get_meta( self::META_DATE );
		if ( '' !== $date ) {
			$rows['delivery_date'] = array(
				'label' => __( 'روز تحویل', 'hamishop-elementor-widgets' ),
				'value' => self::format_date_value( $date ),
			);
		}

		$slot = (string) $order->get_meta( self::META_SLOT );
		if ( '' !== $slot ) {
			$rows['delivery_slot'] = array(
				'label' => __( 'بازهٔ زمانی تحویل', 'hamishop-elementor-widgets' ),
				'value' => $slot,
			);
		}

		if ( 'yes' === (string) $order->get_meta( self::META_INVOICE ) ) {
			$rows['legal_invoice'] = array(
				'label' => __( 'فاکتور قانونی', 'hamishop-elementor-widgets' ),
				'value' => __( 'درخواست شده', 'hamishop-elementor-widgets' ),
			);
		}

		return $rows;
	}

	/**
	 * ISO date (Y-m-d) to the site's display format.
	 *
	 * @param string $iso ISO date.
	 * @return string
	 */
	public static function format_date_value( $iso ) {
		$iso = trim( (string) $iso );
		if ( '' === $iso ) {
			return '';
		}

		$date = DateTime::createFromFormat( '!Y-m-d', $iso, wp_timezone() );
		if ( ! $date instanceof DateTime ) {
			return $iso;
		}

		return HSW_Helpers::format_date( $date->getTimestamp(), 'l j F Y' );
	}

	/**
	 * Admin order screen card.
	 *
	 * @param WC_Order $order Order.
	 */
	public function admin_display( $order ) {
		$rows = self::order_rows( $order );
		if ( empty( $rows ) ) {
			return;
		}

		echo '<div class="hsw-order-meta hsw-order-meta--admin">';
		echo '<h3>' . esc_html__( 'زمان تحویل و فاکتور', 'hamishop-elementor-widgets' ) . '</h3>';
		echo '<ul class="hsw-order-meta__list">';
		foreach ( $rows as $row ) {
			echo '<li><strong>' . esc_html( $row['label'] ) . ':</strong> ' . esc_html( $row['value'] ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Customer order page / order-received card.
	 *
	 * @param WC_Order $order Order.
	 */
	public function display( $order ) {
		$rows = self::order_rows( $order );
		if ( empty( $rows ) ) {
			return;
		}

		echo '<section class="hsw-order-meta">';
		echo '<h2 class="hsw-order-meta__title">' . esc_html__( 'زمان تحویل و فاکتور', 'hamishop-elementor-widgets' ) . '</h2>';
		echo '<div class="hsw-order-meta__rows">';
		foreach ( $rows as $row ) {
			echo '<div class="hsw-order-meta__row"><span>' . esc_html( $row['label'] ) . '</span><b>' . esc_html( $row['value'] ) . '</b></div>';
		}
		echo '</div></section>';
	}

	/**
	 * Add the rows to every WooCommerce email.
	 *
	 * @param array    $fields        Email fields.
	 * @param bool     $sent_to_admin Whether this is the admin copy.
	 * @param WC_Order $order         Order.
	 * @return array
	 */
	public function email_fields( $fields, $sent_to_admin, $order ) {
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( self::order_rows( $order ) as $key => $row ) {
			$fields[ 'hsw_' . $key ] = array(
				'label' => $row['label'],
				'value' => $row['value'],
			);
		}

		return $fields;
	}
}
