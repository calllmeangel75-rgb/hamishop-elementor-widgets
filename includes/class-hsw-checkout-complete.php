<?php
/**
 * Checkout REST route: final order placement for the design's own pay button.
 *
 *  - POST /hsw/v1/checkout/complete : creates the order through WooCommerce's
 *    own API (WC_Checkout::create_order) using the session customer address,
 *    the chosen shipping method / payment gateway, coupons and the order note
 *    stored by the other checkout routes — then hands the customer over to
 *    $gateway->process_payment() and returns the redirect URL (the gateway's
 *    own page or WooCommerce's order-received page).
 *
 * This is the bridge that makes the Elementor pay page a real final step:
 * without it the design's CTA could only redirect back to checkout because
 * the page hosts no native `form.checkout`.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Checkout_Complete {

	const NS = 'hsw/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'route' ) );
	}

	public function route() {
		register_rest_route(
			self::NS,
			'/checkout/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => array( $this, 'can_checkout' ),
				'args'                => array(
					'method' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * Any visitor with a valid wp_rest nonce and a cart session.
	 */
	public function can_checkout( $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'hsw_rest_nonce', __( 'نشست منقضی شده؛ صفحه را دوباره بارگذاری کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return new WP_Error( 'hsw_no_cart', __( 'سبد خرید یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
		}
		if ( ! WC()->session ) {
			return new WP_Error( 'hsw_no_session', __( 'نشست یافت نشد.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Place the order and start the payment hand-off.
	 */
	public function complete( $request ) {
		if ( WC()->cart->is_empty() ) {
			return new WP_Error( 'hsw_empty_cart', __( 'سبد خرید خالی است؛ ابتدا محصولی به سبد اضافه کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
		}

		WC()->cart->calculate_totals();
		$this->maybe_default_shipping();

		$data = $this->order_data( $request );

		/* The design takes the delivery address from the customer account;
		 * guests (and accounts without any address) get a friendly pointer. */
		if ( '' === trim( $data['billing_first_name'] . $data['billing_last_name'] . $data['billing_address_1'] ) ) {
			$logged = is_user_logged_in();
			return new WP_Error(
				'hsw_no_address',
				$logged
					? __( 'آدرسی برای تحویل ثبت نشده است؛ ابتدا در حساب کاربری آدرس را کامل کنید.', 'hamishop-elementor-widgets' )
					: __( 'برای ثبت سفارش، وارد حساب کاربری شوید و آدرس تحویل را ذخیره کنید.', 'hamishop-elementor-widgets' ),
				array(
					'status'   => 400,
					'hsw_link' => array(
						'url'   => $logged ? wc_get_account_endpoint_url( 'edit-address' ) : wc_get_page_permalink( 'myaccount' ),
						'label' => $logged ? __( 'تکمیل آدرس در حساب کاربری', 'hamishop-elementor-widgets' ) : __( 'ورود / ثبت‌نام', 'hamishop-elementor-widgets' ),
					),
				)
			);
		}

		$errors = $this->validate( $data );
		if ( $errors ) {
			return new WP_Error( 'hsw_checkout_validation', implode( ' ', array_slice( $errors, 0, 3 ) ), array( 'status' => 400 ) );
		}

		try {
			$order_id = WC()->checkout()->create_order( $data );
		} catch ( Throwable $e ) {
			return new WP_Error( 'hsw_order_failed', wp_strip_all_tags( $e->getMessage() ), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $order_id ) ) {
			return new WP_Error( 'hsw_order_failed', wp_strip_all_tags( $order_id->get_error_message() ), array( 'status' => 500 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'hsw_order_failed', __( 'ثبت سفارش انجام نشد؛ دوباره تلاش کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 500 ) );
		}

		$this->decorate_order( $order );

		/** Same hook WooCommerce fires in process_checkout() (emails, plugins). */
		do_action( 'woocommerce_checkout_order_processed', $order_id, $data, $order );

		if ( $order->needs_payment() ) {
			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			$method   = (string) $data['payment_method'];
			if ( ! isset( $gateways[ $method ] ) ) {
				return new WP_Error( 'hsw_bad_gateway', __( 'روش پرداخت انتخاب‌شده در دسترس نیست؛ روش دیگری را انتخاب کنید.', 'hamishop-elementor-widgets' ), array( 'status' => 400 ) );
			}

			/* Mirror WC_Checkout::process_order_payment(). */
			WC()->session->set( 'order_awaiting_payment', $order_id );
			WC()->session->save_data();

			try {
				$result = $gateways[ $method ]->process_payment( $order_id );
			} catch ( Throwable $e ) {
				$result = array( 'result' => 'failure', 'messages' => wp_strip_all_tags( $e->getMessage() ) );
			}

			if ( ! is_array( $result ) || empty( $result['result'] ) || 'success' !== $result['result'] ) {
				$msg = '';
				if ( is_array( $result ) && ! empty( $result['messages'] ) ) {
					$msg = wp_strip_all_tags( is_array( $result['messages'] ) ? implode( ' ', $result['messages'] ) : (string) $result['messages'] );
				}
				return new WP_Error(
					'hsw_pay_failed',
					'' !== trim( $msg ) ? $msg : __( 'پرداخت انجام نشد؛ دوباره تلاش کنید یا روش پرداخت دیگری انتخاب کنید.', 'hamishop-elementor-widgets' ),
					array( 'status' => 402 )
				);
			}

			$result   = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );
			$redirect = ! empty( $result['redirect'] ) ? (string) $result['redirect'] : $order->get_checkout_order_received_url();
		} else {
			$order->payment_complete();
			wc_empty_cart();
			$redirect = apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order );
		}

		return rest_ensure_response(
			array(
				'ok'       => true,
				'result'   => 'success',
				'order_id' => (int) $order_id,
				'redirect' => $redirect,
			)
		);
	}

	/* =========================================================================
	 *  INTERNALS
	 * ====================================================================== */

	/**
	 * The posted-data equivalent for WC_Checkout::create_order(), sourced from
	 * the customer (account address / session), the WC session note + gateway.
	 */
	protected function order_data( $request ) {
		$customer = WC()->customer;
		$fields   = array( 'first_name', 'last_name', 'company', 'country', 'state', 'postcode', 'city', 'address_1', 'address_2', 'phone', 'email' );
		$data     = array();

		foreach ( $fields as $field ) {
			$billing  = is_callable( array( $customer, 'get_billing_' . $field ) ) ? (string) $customer->{'get_billing_' . $field}() : '';
			$shipping = is_callable( array( $customer, 'get_shipping_' . $field ) ) ? (string) $customer->{'get_shipping_' . $field}() : '';
			$data[ 'billing_' . $field ]  = $billing;
			$data[ 'shipping_' . $field ] = $shipping;
		}

		if ( '' === $data['billing_email'] && is_user_logged_in() ) {
			$user                  = wp_get_current_user();
			$data['billing_email'] = (string) $user->user_email;
		}

		/* WooCommerce's "ship to billing" default when no separate address exists. */
		foreach ( $fields as $field ) {
			if ( '' === $data[ 'shipping_' . $field ] ) {
				$data[ 'shipping_' . $field ] = $data[ 'billing_' . $field ];
			}
		}

		$method = (string) $request->get_param( 'method' );
		if ( '' === $method ) {
			$method = (string) WC()->session->get( 'chosen_payment_method' );
		}

		$data['payment_method']            = $method;
		$data['order_comments']            = (string) WC()->session->get( 'hsw_checkout_note' );
		$data['ship_to_different_address'] = false;
		$data['terms']                     = 1;

		return $data;
	}

	/**
	 * When the customer never picked a shipping rate (our UI is radio cards),
	 * fall back to WooCommerce's own default: the first rate of each package.
	 */
	protected function maybe_default_shipping() {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}
		$chosen = WC()->session->get( 'chosen_shipping_methods' );
		$chosen = is_array( $chosen ) ? array_filter( $chosen, 'strlen' ) : array();
		if ( $chosen ) {
			return;
		}
		$shipping = WC()->shipping();
		if ( ! $shipping ) {
			return;
		}
		$shipping->calculate_shipping( WC()->cart->get_shipping_packages() );
		$defaults = array();
		foreach ( (array) $shipping->get_packages() as $index => $package ) {
			if ( empty( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
				continue;
			}
			$keys               = array_keys( $package['rates'] );
			$defaults[ $index ] = (string) $keys[0];
		}
		if ( $defaults ) {
			WC()->session->set( 'chosen_shipping_methods', $defaults );
		}
	}

	/**
	 * Required checkout fields + cart integrity + gateway rules.
	 * Returns an array of human messages (empty = valid).
	 */
	protected function validate( &$data ) {
		$messages = array();

		foreach ( (array) WC()->checkout()->get_checkout_fields() as $fieldset_key => $fieldset ) {
			/* WooCommerce skips the shipping fieldset when shipping to billing. */
			if ( 'shipping' === $fieldset_key && ( ! WC()->cart->needs_shipping() || empty( $data['ship_to_different_address'] ) ) ) {
				continue;
			}
			foreach ( $fieldset as $key => $field ) {
				if ( ! isset( $data[ $key ] ) ) {
					continue;
				}
				if ( ! empty( $field['required'] ) && '' === trim( (string) $data[ $key ] ) ) {
					$label      = isset( $field['label'] ) ? $field['label'] : $key;
					$messages[] = sprintf(
						/* translators: %s: checkout field label. */
						__( 'مقدار «%s» را کامل کنید.', 'hamishop-elementor-widgets' ),
						$label
					);
					continue;
				}
				$format = isset( $field['validate'] ) ? (array) $field['validate'] : array();
				if ( '' !== $data[ $key ] && in_array( 'email', $format, true ) && ! is_email( $data[ $key ] ) ) {
					$messages[] = __( 'ایمیل وارد شده معتبر نیست.', 'hamishop-elementor-widgets' );
				}
			}
		}

		/* Cart integrity: stock, sold-individually, … */
		$messages = array_merge( $messages, $this->collect_notices( array( WC()->cart, 'check_cart_items' ) ) );

		/* Shipping rate must exist for the destination (WC's own final check). */
		if ( ! $messages && WC()->cart->needs_shipping() ) {
			$chosen = WC()->session->get( 'chosen_shipping_methods' );
			foreach ( (array) WC()->shipping()->get_packages() as $i => $package ) {
				if ( ! isset( $chosen[ $i ], $package['rates'][ $chosen[ $i ] ] ) ) {
					$messages[] = __( 'برای این آدرس روش ارسال در دسترس نیست؛ آدرس را بررسی کنید.', 'hamishop-elementor-widgets' );
					break;
				}
			}
		}

		/* Gateway rules (most gateways validate nothing by default). */
		if ( WC()->cart->needs_payment() ) {
			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			if ( ! isset( $gateways[ $data['payment_method'] ] ) ) {
				$messages[] = __( 'روش پرداخت انتخاب‌شده در دسترس نیست؛ روش دیگری را انتخاب کنید.', 'hamishop-elementor-widgets' );
			} else {
				$messages = array_merge( $messages, $this->collect_notices( array( $gateways[ $data['payment_method'] ], 'validate_fields' ) ) );
			}
		}

		/* Other checkout validators (field plugins, …) join through this hook. */
		$errors = new WP_Error();
		do_action( 'woocommerce_after_checkout_validation', $data, $errors );
		foreach ( $errors->get_error_messages() as $message ) {
			$messages[] = (string) $message;
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $messages ) ) ) );
	}

	/**
	 * Run a callable that reports failure through wc_notices and collect them.
	 */
	protected function collect_notices( $callable ) {
		$messages = array();
		wc_clear_notices();
		if ( is_callable( $callable ) ) {
			try {
				call_user_func( $callable );
			} catch ( Throwable $e ) {
				$messages[] = wp_strip_all_tags( $e->getMessage() );
			}
		}
		foreach ( wc_get_notices( 'error' ) as $notice ) {
			$text = is_array( $notice ) && isset( $notice['notice'] ) ? $notice['notice'] : (string) $notice;
			$text = trim( wp_strip_all_tags( $text ) );
			if ( '' !== $text ) {
				$messages[] = $text;
			}
		}
		wc_clear_notices();
		return $messages;
	}

	/**
	 * Design extras that ride along with the order (legal-invoice request).
	 */
	protected function decorate_order( $order ) {
		if ( 'yes' === (string) WC()->session->get( 'hsw_checkout_invoice' ) ) {
			$order->update_meta_data( '_hsw_invoice_requested', 'yes' );
			$order->add_order_note( __( 'درخواست فاکتور رسمی از سوی مشتری ثبت شد.', 'hamishop-elementor-widgets' ) );
		}
		$order->update_meta_data( '_hsw_placed_via', 'hamishop-pay' );
		$order->save();
	}
}
