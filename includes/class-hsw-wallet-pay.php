<?php
/**
 * Wallet: paying with it, and topping it up.
 *
 * Two halves that share one idea — the wallet is real money, so it has to move
 * through WooCommerce like any other payment:
 *
 *  1. `HSW_Wallet_Gateway` is a normal WC payment method. It appears on the
 *     checkout only when the customer is logged in and the balance covers the
 *     order, and it debits the ledger before completing the order.
 *
 *  2. `HSW_Wallet_Topup` turns "شارژ کیف پول" into a WooCommerce order with a
 *     single fee line, so the customer pays with whichever gateway the shop
 *     already has active (آقای پرداخت، زیبال، زرین‌پال …). The wallet is
 *     credited from `woocommerce_payment_complete`, once per order.
 *
 * Card-to-card needs no gateway: the customer sends a receipt through the
 * existing pending-request flow and an admin approves it.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create and settle wallet top-up orders.
 */
class HSW_Wallet_Topup {

	/** Order meta: the user whose wallet this order fills. */
	const META_USER = '_hsw_wallet_topup_user';

	/** Order meta: guards against crediting twice (payment_complete can fire again). */
	const META_DONE = '_hsw_wallet_topup_credited';

	public function __construct() {
		add_action( 'woocommerce_payment_complete', array( $this, 'settle' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'settle' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'settle' ) );
	}

	/**
	 * Wallet settings, with the same defaults the account panel shows.
	 *
	 * @return array
	 */
	public static function settings() {
		$acc = class_exists( 'HSW_Account' ) ? HSW_Account::instance() : null;

		return array(
			'mode'     => $acc ? (string) $acc->setting( 'wallet.topup_mode', 'gateway' ) : 'gateway',
			'gateway'  => $acc ? (string) $acc->setting( 'wallet.topup_gateway', '' ) : '',
			'card'     => $acc ? (string) $acc->setting( 'wallet.card_number', '' ) : '',
			'owner'    => $acc ? (string) $acc->setting( 'wallet.card_owner', '' ) : '',
			'bank'     => $acc ? (string) $acc->setting( 'wallet.card_bank', '' ) : '',
			'min'      => $acc ? (int) $acc->setting( 'wallet.min_topup', 50000 ) : 50000,
		);
	}

	/**
	 * Gateways the shop can send a top-up through.
	 *
	 * Read from WooCommerce rather than a hard-coded list: the shop owner picks
	 * the plugin they already installed instead of us guessing its id.
	 *
	 * @return array<string,string> id => title
	 */
	public static function gateways() {
		$out = array();

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return $out;
		}

		/*
		 * COD must never fund a wallet: its process_payment() moves the order
		 * straight to `processing`, and this plugin treats that status as paid
		 * (so a customer could credit the wallet for free). BACS/cheque stay
		 * available because they wait for the shop owner to confirm receipt.
		 * Sites with a custom offline gateway can extend the blocklist.
		 */
		$blocked = (array) apply_filters( 'hsw_wallet_topup_blocked_gateways', array( 'cod' ) );

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			if ( 'hsw_wallet' === $id || in_array( $id, $blocked, true ) ) {
				continue;
			}
			if ( 'yes' !== ( $gateway->enabled ?? '' ) ) {
				continue;
			}
			$out[ $id ] = $gateway->get_method_title() ? $gateway->get_method_title() : $id;
		}

		return $out;
	}

	/**
	 * Create the order that will fund the wallet.
	 *
	 * A fee line is used instead of a hidden product: no stock, no catalogue
	 * entry, and the amount is exactly what the customer typed.
	 *
	 * @param int    $user_id    Customer.
	 * @param float  $amount     Amount in store currency.
	 * @param string $gateway_id Chosen gateway.
	 * @return int|WP_Error Order id.
	 */
	public static function create_order( $user_id, $amount, $gateway_id = '' ) {
		$user_id = (int) $user_id;
		$amount  = (float) $amount;
		$s       = self::settings();
		$minimum = max( 1000, (int) $s['min'] );

		if ( ! $user_id || $amount < $minimum ) {
			return new WP_Error( 'hsw_bad_amount', sprintf(
				/* translators: %s: minimum amount. */
				__( 'حداقل مبلغ شارژ %s تومان است.', 'hamishop-elementor-widgets' ),
				number_format( $minimum )
			) );
		}

		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'hsw_no_wc', __( 'ووکامرس فعال نیست.', 'hamishop-elementor-widgets' ) );
		}

		$gateway_id = sanitize_key( (string) $gateway_id );
		$gateways   = self::gateways();

		/*
		 * "No gateway at all" and "the chosen one is gone" are different
		 * problems: telling a customer to "pick another gateway" when the shop
		 * has none is a dead end. COD is blocked on purpose (see gateways()),
		 * so a site that only enables COD has no online top-up.
		 */
		if ( empty( $gateways ) ) {
			return new WP_Error(
				'hsw_no_gateway',
				__( 'در حال حاضر هیچ درگاه پرداخت آنلاینی روی سایت فعال نیست؛ برای شارژ کیف پول با پشتیبانی تماس بگیرید.', 'hamishop-elementor-widgets' )
			);
		}

		if ( '' === $gateway_id ) {
			$fallback = sanitize_key( (string) $s['gateway'] );
			if ( isset( $gateways[ $fallback ] ) ) {
				$gateway_id = $fallback;
			} elseif ( 1 === count( $gateways ) ) {
				$gateway_id = (string) array_key_first( $gateways );
			}
		}

		if ( '' === $gateway_id || ! isset( $gateways[ $gateway_id ] ) ) {
			return new WP_Error(
				'hsw_bad_gateway',
				__( 'درگاه پرداخت انتخاب‌شده در دسترس نیست. لطفاً درگاه دیگری انتخاب کنید.', 'hamishop-elementor-widgets' )
			);
		}

		$order = wc_create_order( array( 'customer_id' => $user_id ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$fee = new WC_Order_Item_Fee();
		$fee->set_name( __( 'شارژ کیف پول', 'hamishop-elementor-widgets' ) );
		$fee->set_amount( $amount );
		$fee->set_total( $amount );
		$fee->set_tax_status( 'none' );
		$order->add_item( $fee );

		if ( '' !== $gateway_id ) {
			$order->set_payment_method( $gateway_id );
		}

		$order->update_meta_data( self::META_USER, $user_id );
		$order->calculate_totals();
		$order->set_status( 'pending', __( 'در انتظار پرداخت شارژ کیف پول', 'hamishop-elementor-widgets' ) );
		$order->save();

		return $order->get_id();
	}

	/**
	 * Credit the wallet when the top-up order is paid.
	 *
	 * @param int $order_id Order id.
	 */
	public function settle( $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_meta( self::META_USER );
		if ( ! $user_id ) {
			return;
		}

		/*
		 * `payment_complete()` and the two status hooks can fire for the same
		 * order in one request — or two requests can overlap on a retry. The
		 * old check-then-set (`get_meta` … credit … `update_meta`) let both
		 * pass the check and credit the wallet twice.
		 *
		 * A MySQL named lock serialises the whole block; the flag is re-read
		 * *inside* the lock, so the second caller sees `done` and leaves.
		 * GET_LOCK is available on MySQL and MariaDB (shared hosting too) and
		 * is what WooCommerce itself uses for order-level work.
		 */
		$lock = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', 'hsw_topup_' . (int) $order_id )
		);
		if ( '1' !== (string) $lock ) {
			/* Another request holds it; it will do the credit. */
			return;
		}

		try {
			/* Re-read after the lock: a request that ran first may have settled it. */
			$fresh = wc_get_order( $order_id );
			if ( ! $fresh || $fresh->get_meta( self::META_DONE ) ) {
				return;
			}

			$amount = (float) $fresh->get_total();
			if ( $amount <= 0 ) {
				return;
			}

			$credited = HSW_Wallet::credit(
				$user_id,
				$amount,
				'topup',
				sprintf(
					/* translators: %s: order number. */
					__( 'شارژ کیف پول — سفارش #%s', 'hamishop-elementor-widgets' ),
					$fresh->get_order_number()
				),
				0,
				'topup:' . $order_id
			);

			/*
			 * A ledger failure now comes back as an error instead of a silent
			 * zero, so the flag is only set when the money really moved.
			 */
			if ( is_wp_error( $credited ) ) {
				return;
			}

			$fresh->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
			$fresh->add_order_note(
				sprintf(
					/* translators: %s: formatted amount. */
					__( 'کیف پول مشتری به مبلغ %s شارژ شد.', 'hamishop-elementor-widgets' ),
					wp_strip_all_tags( wc_price( $amount ) )
				)
			);
			$fresh->save();
		} finally {
			$wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'hsw_topup_' . (int) $order_id )
			);
		}
	}
}

/*
 * The gateway class needs WC_Payment_Gateway, which WooCommerce only defines
 * after its own includes run — hence `woocommerce_loaded` instead of a plain
 * class declaration at file scope.
 */
function hsw_register_wallet_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'HSW_Wallet_Gateway' ) ) {
			return;
		}

		/**
		 * Pay an order from the wallet balance.
		 */
		class HSW_Wallet_Gateway extends WC_Payment_Gateway {

			public function __construct() {
				$this->id                 = 'hsw_wallet';
				$this->has_fields         = false;
				$this->method_title       = __( 'کیف پول HamiShop', 'hamishop-elementor-widgets' );
				$this->method_description = __( 'پرداخت کامل سفارش از موجودی کیف پول. فقط وقتی نمایش داده می‌شود که موجودی مشتری کافی باشد.', 'hamishop-elementor-widgets' );
				$this->supports           = array( 'products' );

				$this->init_settings();
				$this->title   = __( 'پرداخت از کیف پول', 'hamishop-elementor-widgets' );
				$this->enabled = 'yes';

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			/**
			 * Balance of the current customer.
			 *
			 * @return float
			 */
			protected function balance() {
				return is_user_logged_in() ? HSW_Wallet::balance( get_current_user_id() ) : 0.0;
			}

			/**
			 * Cart total, without touching the session twice.
			 *
			 * @return float
			 */
			protected function cart_total() {
				return ( WC()->cart ) ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
			}

			public function is_available() {
				if ( ! is_user_logged_in() || ! parent::is_available() ) {
					return false;
				}

				$balance = $this->balance();
				if ( $balance <= 0 ) {
					return false;
				}

				/*
				 * The cart is empty inside a REST request (the payment-method
				 * route runs before WooCommerce rebuilds it), so `get_total()`
				 * returned 0 and the gateway was rejected as "invalid method".
				 * Without a total we cannot compare — show it and let
				 * process_payment() enforce the real amount.
				 */
				$total = $this->cart_total();

				return $total <= 0 || $balance + 0.001 >= $total;
			}

			public function payment_fields() {
				$balance = $this->balance();
				$total   = $this->cart_total();
				$after   = max( 0, $balance - $total );

				echo '<p class="hsw-wallet-pay-note">';
				echo esc_html( sprintf(
					/* translators: 1: balance, 2: remaining balance. */
					__( 'موجودی کیف پول شما %1$s است. پس از پرداخت، %2$s باقی می‌ماند.', 'hamishop-elementor-widgets' ),
					wp_strip_all_tags( wc_price( $balance ) ),
					wp_strip_all_tags( wc_price( $after ) )
				) );
				echo '</p>';
			}

			public function process_payment( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return array( 'result' => 'failure' );
				}

				$user_id = (int) $order->get_customer_id();
				$total   = (float) $order->get_total();
				$balance = HSW_Wallet::balance( $user_id );

				if ( $total <= 0 || $balance + 0.001 < $total ) {
					wc_add_notice( __( 'موجودی کیف پول برای این سفارش کافی نیست.', 'hamishop-elementor-widgets' ), 'error' );
					return array( 'result' => 'failure' );
				}

				$debit = HSW_Wallet::debit(
					$user_id,
					$total,
					'spend',
					sprintf(
						/* translators: %s: order number. */
						__( 'پرداخت سفارش #%s', 'hamishop-elementor-widgets' ),
						$order->get_order_number()
					),
					0,
					(string) $order_id
				);

				/* debit() refuses to go negative and hands back a WP_Error. */
				if ( is_wp_error( $debit ) ) {
					wc_add_notice( $debit->get_error_message(), 'error' );
					return array( 'result' => 'failure' );
				}

				$order->add_order_note( sprintf(
					/* translators: %s: formatted amount. */
					__( 'پرداخت از کیف پول: %s', 'hamishop-elementor-widgets' ),
					wp_strip_all_tags( wc_price( $total ) )
				) );

				$order->payment_complete();
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		}

		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'HSW_Wallet_Gateway';
				return $gateways;
			}
		);
}

/*
 * WooCommerce fires `woocommerce_loaded` while its own bootstrap runs, which is
 * usually *before* a plugin loaded from `plugins_loaded` gets here — waiting for
 * the action alone left the gateway undefined and it never appeared on checkout.
 */
if ( did_action( 'woocommerce_loaded' ) ) {
	hsw_register_wallet_gateway();
} else {
	add_action( 'woocommerce_loaded', 'hsw_register_wallet_gateway' );
}
