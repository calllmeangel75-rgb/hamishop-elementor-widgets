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
 * Return wallet money when a wallet-paid order is cancelled or refunded.
 *
 * There was no such path at all: paying an order from the wallet debited the
 * customer and cancelling it kept the money. Every refund here carries a
 * unique ledger reference, so the same refund can be requested any number of
 * times (cancel + full refund + a retried webhook) and only the first one
 * moves money.
 */
class HSW_Wallet_Refunds {

	/** Order meta: how much of this order was actually paid from the wallet. */
	const META_PAID = '_hsw_wallet_paid';

	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'refund_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refund_order' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'refund_order' ), 10, 2 );
	}

	/**
	 * Record the amount a wallet payment took, so a later refund knows how much
	 * to give back without trusting the order total (which can be edited).
	 *
	 * @param int   $order_id Order.
	 * @param float $amount   Amount paid from the wallet.
	 */
	public static function mark_paid( $order_id, $amount ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$paid = round( (float) $order->get_meta( self::META_PAID ), 2 );
		$order->update_meta_data( self::META_PAID, round( $paid + (float) $amount, 2 ) );
		$order->save();
	}

	/**
	 * Refund the wallet part of an order.
	 *
	 * @param int $order_id  Order.
	 * @param int $refund_id WooCommerce refund id (0 = cancel / full refund).
	 * @return float|WP_Error Amount credited.
	 */
	public function refund_order( $order_id, $refund_id = 0 ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'hsw_wallet' !== $order->get_payment_method() ) {
			return 0.0;
		}

		$paid = round( (float) $order->get_meta( self::META_PAID ), 2 );
		if ( $paid <= 0 ) {
			/*
			 * process_payment() debits first and marks the order second; a
			 * death between the two left the order unmarked and a later cancel
			 * silently kept the customer's money. The ledger is the truth:
			 * the unique `spend:<order>` row is what was actually taken.
			 */
			$paid = self::ledger_spend_total( $order_id );
			if ( $paid <= 0 ) {
				return 0.0;
			}
		}

		$user_id = (int) $order->get_customer_id();
		if ( ! $user_id ) {
			return 0.0;
		}

		if ( $refund_id ) {
			$refund = wc_get_order( $refund_id );
			$amount = $refund ? round( abs( (float) $refund->get_amount() ), 2 ) : 0.0;
			$ref    = 'refund:' . (int) $order_id . ':' . (int) $refund_id;

			/* A partial refund cannot hand back more than the order took. */
			$amount = min( $amount, max( 0, $paid - self::refunded_total( $order_id ) ) );
		} else {
			$amount = round( $paid - self::refunded_total( $order_id ), 2 );
			$ref    = 'refund:' . (int) $order_id . ':full';
		}

		if ( $amount <= 0 ) {
			return 0.0;
		}

		$credited = HSW_Wallet::credit(
			$user_id,
			$amount,
			'refund',
			sprintf(
				/* translators: %s: order number. */
				__( 'بازگشت وجه سفارش #%s به کیف پول', 'hamishop-elementor-widgets' ),
				$order->get_order_number()
			),
			0,
			$ref
		);

		if ( is_wp_error( $credited ) ) {
			/* Duplicate reference = this refund already happened. */
			return 'hsw_wallet_duplicate' === $credited->get_error_code() ? 0.0 : $credited;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: formatted amount. */
				__( 'مبلغ %s به کیف پول مشتری بازگشت.', 'hamishop-elementor-widgets' ),
				wp_strip_all_tags( wc_price( $amount ) )
			)
		);
		$order->save();

		return $amount;
	}

	/**
	 * How much has already been refunded to the wallet for this order.
	 *
	 * @param int $order_id Order.
	 * @return float
	 */
	public static function refunded_total( $order_id ) {
		global $wpdb;

		return (float) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount),0) FROM ' . HSW_Wallet::table() . " WHERE kind = 'refund' AND status = 'done' AND ref LIKE %s",
				'refund:' . (int) $order_id . ':%'
			)
		);
	}

	/**
	 * What the wallet actually took for this order, straight from the ledger.
	 *
	 * @param int $order_id Order.
	 * @return float Absolute spend amount, 0 when the order was never charged.
	 */
	public static function ledger_spend_total( $order_id ) {
		global $wpdb;

		return (float) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT COALESCE(SUM(ABS(amount)),0) FROM ' . HSW_Wallet::table() . " WHERE kind = 'spend' AND status = 'done' AND ref = %s",
				'spend:' . (int) $order_id
			)
		);
	}
}

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
	 * Idempotent by database constraint, not by a flag: the ledger has a unique
	 * index on `ref`, and this method always uses `topup:<order id>` as the ref.
	 * A second call — from the same hook firing twice, from
	 * `order_status_processing` after `payment_complete`, or from two requests
	 * racing — inserts a duplicate ref, the unique index rejects it, the balance
	 * write is rolled back and the caller sees `hsw_wallet_duplicate`, which is
	 * treated as success.
	 *
	 * The `_hsw_wallet_topup_credited` meta is still written, but only as an
	 * audit trail. It is **not** the guard: WooCommerce serves order meta from
	 * its object cache, and a request that loaded the order before the first
	 * credit would read a stale, empty flag (this is exactly how five parallel
	 * `payment_complete` calls each credited 25000 before the ref index existed).
	 *
	 * @param int $order_id Order id.
	 */
	public function settle( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_meta( self::META_USER );
		if ( ! $user_id ) {
			return;
		}

		$amount = (float) $order->get_total();
		if ( $amount <= 0 ) {
			return;
		}

		$ref = 'topup:' . (int) $order_id;

		/* Fast path: already in the statement (indexed, cache-free read). */
		if ( self::ledger_has_ref( $ref ) ) {
			self::mark_done( $order_id );
			return;
		}

		$credited = HSW_Wallet::credit(
			$user_id,
			$amount,
			'topup',
			sprintf(
				/* translators: %s: order number. */
				__( 'شارژ کیف پول — سفارش #%s', 'hamishop-elementor-widgets' ),
				$order->get_order_number()
			),
			0,
			$ref
		);

		if ( is_wp_error( $credited ) ) {
			if ( 'hsw_wallet_duplicate' === $credited->get_error_code() ) {
				/* Another request won the race: the money is in, mark and stop. */
				self::mark_done( $order_id );
			}
			return;
		}

		self::mark_done( $order_id );

		$order->add_order_note(
			sprintf(
				/* translators: %s: formatted amount. */
				__( 'کیف پول مشتری به مبلغ %s شارژ شد.', 'hamishop-elementor-widgets' ),
				wp_strip_all_tags( wc_price( $amount ) )
			)
		);
		$order->save();
	}

	/**
	 * Is this reference already in the ledger?
	 *
	 * @param string $ref Reference.
	 * @return bool
	 */
	private static function ledger_has_ref( $ref ) {
		global $wpdb;

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT id FROM ' . HSW_Wallet::table() . ' WHERE ref = %s LIMIT 1',
				$ref
			)
		);
	}

	/**
	 * Audit flag on the order (best effort; never the guard).
	 *
	 * @param int $order_id Order.
	 */
	private static function mark_done( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::META_DONE ) ) {
			return;
		}
		$order->update_meta_data( self::META_DONE, current_time( 'mysql' ) );
		$order->save();
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
			 * Has this order already been charged to the wallet?
			 *
			 * Covers both reference formats: `spend:<id>` (current) and the bare
			 * `<id>` written by earlier versions.
			 *
			 * @param int $order_id Order.
			 * @return bool
			 */
			protected static function order_already_paid( $order_id ) {
				global $wpdb;

				return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						'SELECT id FROM ' . HSW_Wallet::table() . " WHERE kind = 'spend' AND status = 'done' AND ref IN (%s, %s) LIMIT 1",
						'spend:' . (int) $order_id,
						(string) (int) $order_id
					)
				);
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

				/*
				 * A double click (or a browser retry) used to debit twice. The
				 * ledger reference `spend:<order>` is unique, and older rows
				 * used the bare order id, so both are checked before charging.
				 */
				if ( self::order_already_paid( $order_id ) ) {
					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order ),
					);
				}

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
					'spend:' . $order_id
				);

				/* debit() refuses to go negative and hands back a WP_Error. */
				if ( is_wp_error( $debit ) ) {
					if ( 'hsw_wallet_duplicate' === $debit->get_error_code() ) {
						/* The other request already charged this order. */
						return array(
							'result'   => 'success',
							'redirect' => $this->get_return_url( $order ),
						);
					}

					wc_add_notice( $debit->get_error_message(), 'error' );
					return array( 'result' => 'failure' );
				}

				HSW_Wallet_Refunds::mark_paid( $order_id, $total );

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
