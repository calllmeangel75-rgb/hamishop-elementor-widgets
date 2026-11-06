<?php
/**
 * Shipping-session scenario — the repeatable form of the 2026-11-06 probe.
 *
 * The question: when the free-shipping switch or its threshold changes while a
 * customer session already holds a rate, what does the page show, and what does
 * the order actually record?
 *
 * It drives the real WordPress/WooCommerce stack, not stubs:
 *
 *   1. the cart is built through WC_Cart::add_to_cart();
 *   2. every step flips the option through update_option(), so the plugin's
 *      add_option and update_option hooks run exactly as they do in wp-admin;
 *   3. "a new request" is WC_Cart_Session::get_cart_from_session(), the same
 *      function WordPress runs on wp_loaded for every page view;
 *   4. the rate list is read from WC_Shipping::calculate_shipping(), which is
 *      what HSW_Checkout_Base::shipping_rates() calls to render the radios;
 *   5. the order is placed through HSW_Checkout_Complete::complete(), the real
 *      handler behind POST /hsw/v1/checkout/complete.
 *
 * Nothing here calls WC()->cart->calculate_totals() to make a step pass. The
 * only recalculations are the ones the code under test performs, and the order
 * is read back from storage without touching its totals.
 *
 * What this is NOT — the browser probe is still the evidence for these:
 *
 *   - not an HTTP test. `choose_shipping()` and `complete()` are called on the
 *     objects, not dispatched through rest_api_loaded, so a route-level defect
 *     (permissions, nonce plumbing, JSON parsing) would not show up here;
 *   - not a browser test. It reads the numbers the summary prints, not the
 *     rendered HTML; a template that ignored them would still pass;
 *   - "a new request" is get_cart_from_session() inside this process, not a
 *     second PHP process carrying a cookie.
 *
 * Safety: this edits a live shipping rate, switches the COD gateway and guest
 * checkout on, and flushes the object cache. It refuses to run when
 * wp_get_environment_type() is 'production' unless HSW_QA_ALLOW=1, and restores
 * in a shutdown handler so a fatal still puts the store back. The original rate
 * settings are recorded *before* the write, not returned from the helper, so a
 * fatal inside the write window is still recoverable; a rate the fixture
 * created is found again by its title. A killed process is not recoverable —
 * run it on an isolated install, never on a live shop.
 *
 * What the numbers mean (measured on WordPress 7.1.4 with WooCommerce 11.2.1
 * locally and 11.2.2 in CI):
 *
 *   - WC_Shipping serves the rate cached in the session until the shipping
 *     cache version changes. The plugin's option hook bumps it; a plain cost
 *     change does not, and then even the rate list is stale.
 *   - WC_Cart_Session::get_cart_from_session() restores `cart_totals` and only
 *     recalculates when the cart changed or the stored totals are missing, so
 *     the summary can show the previous shipping total for one render.
 *   - The order is not affected: HSW_Checkout_Complete::complete() calls
 *     calculate_totals() before create_order().
 *
 * WooCommerce's own pages recalculate once per render —
 * class-wc-shortcode-cart.php:96 and class-wc-shortcode-checkout.php:364 both
 * say `// Calc totals.` — while the composite widgets do not, which is why the
 * stale number is visible there and not on a shortcode cart. Closing that gap
 * means one calculate_totals() at the top of the composite render, not on
 * wp_loaded: a per-request recalculation would run on every page view and
 * re-run every shipping plugin's rate filter for a number nobody reads.
 *
 * Run:
 *   HSW_WP_LOAD=/path/to/wp-load.php php tests/qa/shipping-session.php
 *
 * Optional:
 *   HSW_KEEP=1     keep the created orders and the fixture
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$hsw_wp_load = getenv( 'HSW_WP_LOAD' );
if ( ! $hsw_wp_load || ! is_file( $hsw_wp_load ) ) {
	fwrite( STDERR, "Set HSW_WP_LOAD to the site's wp-load.php.\n" );
	exit( 2 );
}

require $hsw_wp_load;

if ( ! function_exists( 'WC' ) || ! class_exists( 'HSW_Free_Shipping' ) ) {
	fwrite( STDERR, "WooCommerce and HamiShop must both be active.\n" );
	exit( 2 );
}

/*
 * This script edits a live shipping rate, switches the COD gateway and guest
 * checkout on, and flushes the object cache. Restoring happens at the end and
 * in a shutdown handler, but an isolated install is the only safe place to run
 * it — a timeout or a killed process leaves the store changed.
 */
$hsw_env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
if ( 'production' === $hsw_env && ! getenv( 'HSW_QA_ALLOW' ) ) {
	fwrite( STDERR, "Refusing to run: this is a production site.\n" );
	fwrite( STDERR, "The script changes a shipping rate, the COD gateway and guest checkout, and flushes the object cache.\n" );
	fwrite( STDERR, "Run it on an isolated install, or set HSW_QA_ALLOW=1 for a disposable site.\n" );
	exit( 3 );
}

/* The fixture's own marker; nothing is adopted or deleted without it. */
define( 'HSW_SC_SKU', 'HSW-QA-SHIPPING' );

$hsw_keep   = (bool) getenv( 'HSW_KEEP' );
$hsw_failed = 0;
$hsw_rows   = 0;

/* -------------------------------------------------------------------------
 * Output helpers
 * ---------------------------------------------------------------------- */

function hsw_say( $line = '' ) {
	echo $line . PHP_EOL;
}

function hsw_head( $title ) {
	hsw_say();
	hsw_say( '== ' . $title );
}

function hsw_money( $value ) {
	return number_format( (float) $value, 0, '.', ',' );
}

function hsw_check( $label, $ok, $detail = '' ) {
	global $hsw_failed;
	if ( ! $ok ) {
		$hsw_failed++;
	}
	hsw_say( ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? ' — ' . $detail : '' ) );
}

/**
 * One aligned table row.
 *
 * @param array $cells  Column values.
 * @param array $widths Column widths.
 */
function hsw_row( array $cells, array $widths ) {
	$out = '';
	foreach ( $cells as $i => $cell ) {
		$width = isset( $widths[ $i ] ) ? $widths[ $i ] : 0;
		$out  .= str_pad( (string) $cell, $width ) . '  ';
	}
	hsw_say( rtrim( $out ) );
}

/* -------------------------------------------------------------------------
 * Fixture
 * ---------------------------------------------------------------------- */

/**
 * A dedicated simple product with a known price, created once and reused.
 *
 * It is only ever reused when it still carries the fixture SKU, so a product
 * that merely happens to sit behind the same option is never adopted — and
 * never deleted at the end.
 *
 * @return int
 */
function hsw_sc_product() {
	$id = (int) get_option( 'hsw_qa_shipping_product', 0 );
	if (
		$id
		&& 'product' === get_post_type( $id )
		&& 'publish' === get_post_status( $id )
		&& HSW_SC_SKU === (string) get_post_meta( $id, '_sku', true )
	) {
		return $id;
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'HSW QA shipping product' );
	$product->set_sku( HSW_SC_SKU );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_regular_price( '120000' );
	$product->set_price( '120000' );
	$product->set_virtual( false );
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
	$id = (int) $product->save();

	update_option( 'hsw_qa_shipping_product', $id, false );

	return $id;
}

/**
 * Record a rate's settings before anything writes to them.
 *
 * The restore must not depend on a function returning: the write happens
 * inside hsw_sc_set_cost(), and a fatal between that write and the caller's
 * assignment would leave the store changed with nothing to put back. So the
 * pre-write value is stored here, keyed by instance id, the moment it is first
 * seen. `null` means the fixture created the method and must delete it again.
 *
 * @param int        $instance_id Instance id.
 * @param array|null $settings    Original settings, or null when created here.
 */
function hsw_sc_remember_rate( $instance_id, $settings ) {
	global $hsw_originals;

	$instance_id = (int) $instance_id;
	if ( ! isset( $hsw_originals['flat_rate'] ) || ! is_array( $hsw_originals['flat_rate'] ) ) {
		$hsw_originals['flat_rate'] = array();
	}

	if ( ! array_key_exists( $instance_id, $hsw_originals['flat_rate'] ) ) {
		$hsw_originals['flat_rate'][ $instance_id ] = $settings;
	}
}

/**
 * The flat rate the fixture uses.
 *
 * It has to live in the zone WooCommerce actually matches for this customer: a
 * new zone loses to a catch-all zone that already matches, and a CI store may
 * have no zone at all. So — find the matching zone, take its flat_rate, add one
 * if it has none.
 *
 * @param float $cost Cost in store currency.
 * @return array{zone_id:int,instance_id:int}
 */
function hsw_sc_flat_rate( $cost ) {
	$packages = WC()->cart->get_shipping_packages();
	$zone     = $packages ? WC_Shipping_Zones::get_zone_matching_package( $packages[0] ) : new WC_Shipping_Zone( 0 );

	$instance_id = 0;

	foreach ( $zone->get_shipping_methods( false ) as $method ) {
		if ( 'flat_rate' === $method->id ) {
			$instance_id = (int) $method->instance_id;
			break;
		}
	}

	if ( ! $instance_id ) {
		$instance_id = (int) $zone->add_shipping_method( 'flat_rate' );
		/* Registered as "created here" the line after the id exists. */
		hsw_sc_remember_rate( $instance_id, null );
	}

	hsw_sc_set_cost( $instance_id, $cost );

	return array(
		'zone_id'     => (int) $zone->get_id(),
		'instance_id' => $instance_id,
	);
}

/**
 * Change the fixture rate's cost and make the next calculation read it.
 *
 * @param int   $instance_id Instance id.
 * @param float $cost        Cost in store currency.
 */
function hsw_sc_set_cost( $instance_id, $cost ) {
	$settings = get_option( 'woocommerce_flat_rate_' . (int) $instance_id . '_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	/* Before the write, never after it. */
	hsw_sc_remember_rate( $instance_id, $settings );

	$settings['title']      = 'HSW QA flat';
	$settings['tax_status'] = 'taxable';
	$settings['cost']       = (string) $cost;

	update_option( 'woocommerce_flat_rate_' . (int) $instance_id . '_settings', $settings );

	WC()->shipping()->shipping_methods = null;
	wp_cache_flush();
}

/**
 * Delete any flat rate the fixture created but never got to register.
 *
 * The window between add_shipping_method() and the remember call is one line,
 * but a fatal there would leave an orphan. The fixture's title is the marker;
 * a method that is already registered is restored instead, never deleted.
 *
 * @return void
 */
function hsw_sc_sweep_orphans() {
	global $hsw_originals;

	$known = isset( $hsw_originals['flat_rate'] ) ? (array) $hsw_originals['flat_rate'] : array();

	foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
		$zone = WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
		if ( ! $zone instanceof WC_Shipping_Zone ) {
			continue;
		}
		foreach ( $zone->get_shipping_methods( false ) as $method ) {
			if ( 'flat_rate' !== $method->id || 'HSW QA flat' !== (string) $method->get_title() ) {
				continue;
			}
			if ( array_key_exists( (int) $method->instance_id, $known ) ) {
				continue;
			}
			$zone->delete_shipping_method( (int) $method->instance_id );
		}
	}
}

/**
 * The rate key the fixture produces, e.g. flat_rate:7.
 *
 * @param int $instance_id Instance id.
 * @return string
 */
function hsw_sc_rate_key( $instance_id ) {
	return 'flat_rate:' . (int) $instance_id;
}

/* -------------------------------------------------------------------------
 * Request simulation
 * ---------------------------------------------------------------------- */

/**
 * The WC_Cart_Session object behind WC()->cart (the property is protected).
 *
 * @return WC_Cart_Session
 */
function hsw_sc_cart_session() {
	return ( function () {
		return $this->session;
	} )->call( WC()->cart );
}

/**
 * Run what WordPress runs on wp_loaded for a page view.
 *
 * `get_cart_from_session()` restores `cart_totals` from the session and calls
 * `calculate_totals()` only when the cart changed or the stored totals are
 * missing. That condition is the whole point of this test.
 *
 * @return bool True when the totals had to be calculated (a cold session).
 */
function hsw_sc_page_load() {
	$before = WC()->session ? WC()->session->get( 'cart_totals', null ) : null;
	hsw_sc_cart_session()->get_cart_from_session();

	return null === $before;
}

/**
 * A new front-end request: the plugin boots with the current option, then the
 * session is loaded.
 *
 * @return bool Cold-session flag from hsw_sc_page_load().
 */
function hsw_sc_new_request() {
	hsw_sc_detach_free_shipping();
	new HSW_Free_Shipping();

	return hsw_sc_page_load();
}

/**
 * Remove every HSW_Free_Shipping callback from woocommerce_package_rates.
 *
 * A real request builds one instance from the option as it stands. A CLI
 * process builds it once, so each simulated request has to drop the previous
 * one — otherwise switching the feature off would keep the old filter alive.
 */
function hsw_sc_detach_free_shipping() {
	global $wp_filter;

	if ( empty( $wp_filter['woocommerce_package_rates'] ) ) {
		return;
	}

	$hook = $wp_filter['woocommerce_package_rates'];
	foreach ( $hook->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $id => $callback ) {
			$fn = isset( $callback['function'] ) ? $callback['function'] : null;
			if ( is_array( $fn ) && isset( $fn[0] ) && $fn[0] instanceof HSW_Free_Shipping ) {
				unset( $hook->callbacks[ $priority ][ $id ] );
			}
		}
	}
}

/**
 * The rate list exactly as HSW_Checkout_Base::shipping_rates() builds it.
 *
 * @return array<string,array{label:string,cost:float,free:bool}>
 */
function hsw_sc_rates() {
	$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
	$out      = array();

	if ( empty( $packages[0]['rates'] ) || ! is_array( $packages[0]['rates'] ) ) {
		return $out;
	}

	foreach ( $packages[0]['rates'] as $id => $rate ) {
		$meta          = (array) $rate->get_meta_data();
		$out[ $id ]    = array(
			'label' => (string) $rate->get_label(),
			'cost'  => (float) $rate->get_cost(),
			'free'  => ! empty( $meta['hsw_free_shipping'] ),
		);
	}

	return $out;
}

/**
 * What the summary reads: the totals the cart currently holds.
 *
 * @return array{shipping:float,total:float,chosen:array}
 */
function hsw_sc_totals() {
	return array(
		'shipping' => (float) WC()->cart->get_shipping_total(),
		'total'    => (float) WC()->cart->get_total( 'edit' ),
		'chosen'   => (array) WC()->session->get( 'chosen_shipping_methods' ),
	);
}

/**
 * Read an order back from storage, not from the object the handler returned.
 *
 * Every cache the order could be served from is dropped first, then a fresh
 * `WC_Order` is constructed so the data store reads the row again. Nothing here
 * recalculates anything: the point is to see what was written, not to make it
 * right afterwards.
 *
 * @param int $order_id Order id.
 * @return WC_Order|null
 */
function hsw_sc_order_from_storage( $order_id ) {
	$order_id = (int) $order_id;

	wp_cache_delete( $order_id, 'posts' );
	wp_cache_delete( $order_id, 'post_meta' );
	wp_cache_delete( $order_id, 'orders' );
	clean_post_cache( $order_id );

	$order = new WC_Order( $order_id );

	return $order->get_id() ? $order : null;
}

/**
 * The totals exactly as the order row stores them.
 *
 * `get_total()` reads the object, which was just loaded from the row, so the
 * two agree — but the raw meta is what survives a page reload and what an
 * accountant reads. Only meaningful on the posts table; HPOS keeps the same
 * values in its own tables and the object is the source there.
 *
 * @param int $order_id Order id.
 * @return array<string,string>
 */
function hsw_sc_order_raw( $order_id ) {
	if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) ) {
		return array();
	}

	$out = array();
	foreach ( array( '_order_shipping', '_order_shipping_tax', '_order_tax', '_order_total' ) as $key ) {
		$out[ $key ] = (string) get_post_meta( (int) $order_id, $key, true );
	}

	return $out;
}

/**
 * Flip the feature through the real option API.
 *
 * @param string $action reset|first-on|off|on-high|on-low|same.
 */
function hsw_sc_apply( $action ) {
	$on  = HSW_Free_Shipping::ENABLED;
	$min = HSW_Free_Shipping::OPTION;

	switch ( $action ) {
		case 'reset':
			delete_option( $on );
			delete_option( $min );
			break;

		case 'first-on':
			/* Both options are created here, so add_option_* is what fires. */
			delete_option( $on );
			delete_option( $min );
			update_option( $on, 'yes', false );
			update_option( $min, 1, false );
			break;

		case 'off':
			update_option( $on, 'no', false );
			break;

		case 'on-high':
			update_option( $on, 'yes', false );
			update_option( $min, 999999999, false );
			break;

		case 'on-low':
			update_option( $on, 'yes', false );
			update_option( $min, 1, false );
			break;

		case 'same':
			/* Saving the value that is already stored: no hook, no flush. */
			update_option( $on, 'yes', false );
			break;
	}
}

/**
 * Shipping-cache version — the plugin bumps it on every real change.
 *
 * @return string
 */
function hsw_sc_version() {
	return class_exists( 'WC_Cache_Helper' ) ? (string) WC_Cache_Helper::get_transient_version( 'shipping' ) : '';
}

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */

wp_set_current_user( 0 );

$hsw_originals = array(
	'on'      => get_option( HSW_Free_Shipping::ENABLED, null ),
	'min'     => get_option( HSW_Free_Shipping::OPTION, null ),
	'guest'   => get_option( 'woocommerce_enable_guest_checkout', 'no' ),
	'cod'     => get_option( 'woocommerce_cod_settings', array() ),
	'created' => array(),
);

/* Defaults first: the shutdown handler may run before the fixture exists. */
$hsw_zone        = null;
$hsw_instance_id = 0;
$hsw_product_id  = 0;
$hsw_restored    = false;

/* A fatal or an early exit still puts the store back. */
register_shutdown_function( 'hsw_sc_restore' );

$hsw_product_id = hsw_sc_product();
$hsw_product    = wc_get_product( $hsw_product_id );
$hsw_price      = (float) $hsw_product->get_price( 'edit' );

update_option( 'woocommerce_enable_guest_checkout', 'yes' );

$hsw_cod             = is_array( $hsw_originals['cod'] ) ? $hsw_originals['cod'] : array();
$hsw_cod['enabled']  = 'yes';
update_option( 'woocommerce_cod_settings', $hsw_cod );
if ( WC()->payment_gateways() ) {
	WC()->payment_gateways()->init();
}

/* A guest customer with a complete Iranian address in the fixture zone. */
$hsw_customer = WC()->customer;
$hsw_customer->set_billing_first_name( 'QA' );
$hsw_customer->set_billing_last_name( 'Scenario' );
$hsw_customer->set_billing_company( '' );
$hsw_customer->set_billing_country( 'IR' );
$hsw_customer->set_billing_state( 'THR' );
$hsw_customer->set_billing_postcode( '1234567890' );
$hsw_customer->set_billing_city( 'تهران' );
$hsw_customer->set_billing_address_1( 'خیابان آزمون ۱' );
$hsw_customer->set_billing_address_2( '' );
$hsw_customer->set_billing_phone( '09120000000' );
$hsw_customer->set_billing_email( 'qa@example.com' );
$hsw_customer->set_shipping_first_name( 'QA' );
$hsw_customer->set_shipping_last_name( 'Scenario' );
$hsw_customer->set_shipping_country( 'IR' );
$hsw_customer->set_shipping_state( 'THR' );
$hsw_customer->set_shipping_postcode( '1234567890' );
$hsw_customer->set_shipping_city( 'تهران' );
$hsw_customer->set_shipping_address_1( 'خیابان آزمون ۱' );
$hsw_customer->set_shipping_phone( '09120000000' );
$hsw_customer->save();

/**
 * Build a clean cart and a warm session.
 *
 * @return void
 */
function hsw_sc_fresh_cart() {
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( (int) get_option( 'hsw_qa_shipping_product', 0 ), 1 );
	WC()->session->set( 'chosen_shipping_methods', array() );
	WC()->session->set( 'cart_totals', null );

	/* The rate cache is keyed by the shipping version; bump it so the next
	   phase starts from a calculation and not from a previous phase's rates. */
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

hsw_sc_fresh_cart();

$hsw_zone        = hsw_sc_flat_rate( 25000 );
$hsw_instance_id = (int) $hsw_zone['instance_id'];
$hsw_rate_key    = hsw_sc_rate_key( $hsw_instance_id );

hsw_say( 'HamiShop shipping-session scenario' );
hsw_say(
	sprintf(
		'  WordPress %s · WooCommerce %s · PHP %s · HPOS %s',
		get_bloginfo( 'version' ),
		defined( 'WC_VERSION' ) ? WC_VERSION : 'n/a',
		PHP_VERSION,
		'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' ) ? 'on' : 'off'
	)
);
hsw_say( sprintf( '  fixture: product #%d at %s · zone #%d · rate %s', $hsw_product_id, hsw_money( $hsw_price ), (int) $hsw_zone['zone_id'], $hsw_rate_key ) );

/* -------------------------------------------------------------------------
 * Phase 0 — control: a plain WooCommerce cost change, no HamiShop involved
 *
 * Splits the two effects apart:
 *   A. the cost changes, nothing else — WooCommerce serves its session cache;
 *   B. the cost changes and the shipping version is bumped, which is what the
 *      plugin's option hook does — the list refreshes, the totals do not.
 * ---------------------------------------------------------------------- */

hsw_head( 'Phase 0 — control: plain WooCommerce cost change (no HamiShop option)' );

hsw_sc_apply( 'reset' );
hsw_sc_set_cost( $hsw_instance_id, 25000 );
hsw_sc_fresh_cart();
hsw_sc_new_request();               // cold: calculates and stores the totals
WC()->session->set( 'chosen_shipping_methods', array( $hsw_rate_key ) );
hsw_sc_new_request();               // warm: restores them

$hsw_control_warm = array(
	'rates'  => hsw_sc_rates(),
	'totals' => hsw_sc_totals(),
);

/* A — the cost option changes, no cache version bump. */
hsw_sc_set_cost( $hsw_instance_id, 30000 );
hsw_sc_new_request();

$hsw_control_cost = array(
	'rates'  => hsw_sc_rates(),
	'totals' => hsw_sc_totals(),
);

/* B — the same change, plus the bump the plugin's option hook performs. */
WC_Cache_Helper::get_transient_version( 'shipping', true );
hsw_sc_new_request();

$hsw_control_bump = array(
	'rates'  => hsw_sc_rates(),
	'totals' => hsw_sc_totals(),
);

$hsw_c = static function ( $set ) use ( $hsw_rate_key ) {
	$rate = isset( $set['rates'][ $hsw_rate_key ] ) ? $set['rates'][ $hsw_rate_key ] : null;

	return array(
		'cost'     => $rate ? (float) $rate['cost'] : -1.0,
		'shipping' => (float) $set['totals']['shipping'],
	);
};

$hsw_c_warm = $hsw_c( $hsw_control_warm );
$hsw_c_cost = $hsw_c( $hsw_control_cost );
$hsw_c_bump = $hsw_c( $hsw_control_bump );

hsw_row( array( 'step', 'rate cost', 'shipping_total', 'lag' ), array( 34, 12, 16, 6 ) );
hsw_row( array( 'warm session, cost 25,000', hsw_money( $hsw_c_warm['cost'] ), hsw_money( $hsw_c_warm['shipping'] ), 'no' ), array( 34, 12, 16, 6 ) );
hsw_row( array( 'cost 30,000, no version bump', hsw_money( $hsw_c_cost['cost'] ), hsw_money( $hsw_c_cost['shipping'] ), abs( $hsw_c_cost['shipping'] - $hsw_c_cost['cost'] ) > 0.01 ? 'YES' : 'no' ), array( 34, 12, 16, 6 ) );
hsw_row( array( 'cost 30,000, version bumped', hsw_money( $hsw_c_bump['cost'] ), hsw_money( $hsw_c_bump['shipping'] ), abs( $hsw_c_bump['shipping'] - $hsw_c_bump['cost'] ) > 0.01 ? 'YES' : 'no' ), array( 34, 12, 16, 6 ) );

hsw_say();
hsw_check(
	'control A: a plain cost change does not even refresh the rate list',
	abs( $hsw_c_cost['cost'] - 25000 ) < 0.01,
	'list still ' . hsw_money( $hsw_c_cost['cost'] ) . ' — WooCommerce serves the rate cached in the session'
);
hsw_check(
	'control B: the version bump refreshes the rate list',
	abs( $hsw_c_bump['cost'] - 30000 ) < 0.01,
	'list now ' . hsw_money( $hsw_c_bump['cost'] )
);
hsw_check(
	'control B: the totals still lag behind the refreshed list',
	abs( $hsw_c_bump['shipping'] - 25000 ) < 0.01,
	'shipping_total ' . hsw_money( $hsw_c_bump['shipping'] ) . ' — core restores cart_totals from the session'
);

/* -------------------------------------------------------------------------
 * Phase 1 — the HamiShop switch, read on a page load
 * ---------------------------------------------------------------------- */

hsw_head( 'Phase 1 — HamiShop switch, read on a page load (no manual recalculation)' );

hsw_sc_set_cost( $hsw_instance_id, 25000 );
hsw_sc_apply( 'reset' );
hsw_sc_fresh_cart();
hsw_sc_new_request();
WC()->session->set( 'chosen_shipping_methods', array( $hsw_rate_key ) );
hsw_sc_new_request();

$hsw_steps = array(
	array( 'reset', 'off — options absent' ),
	array( 'on-low', 'on — threshold 1 (below cart)' ),
	array( 'off', 'off — switched off' ),
	array( 'on-high', 'on — threshold 999,999,999' ),
	array( 'on-low', 'on — threshold 1 again' ),
	array( 'same', 'unchanged save (on, same value)' ),
);

hsw_row( array( 'step', 'rate', 'rate cost', 'shipping_total', 'cart_total', 'lag', 'ver+' ), array( 34, 14, 11, 15, 13, 6, 6 ) );

$hsw_matrix = array();

foreach ( $hsw_steps as $hsw_step ) {
	list( $hsw_action, $hsw_label ) = $hsw_step;

	$hsw_ver_before = hsw_sc_version();
	hsw_sc_apply( $hsw_action );
	$hsw_ver_after = hsw_sc_version();

	$hsw_cold = hsw_sc_new_request();

	$hsw_rates  = hsw_sc_rates();
	$hsw_totals = hsw_sc_totals();
	$hsw_rate   = isset( $hsw_rates[ $hsw_rate_key ] ) ? $hsw_rates[ $hsw_rate_key ] : null;
	$hsw_cost   = $hsw_rate ? (float) $hsw_rate['cost'] : -1.0;
	$hsw_lag    = abs( $hsw_totals['shipping'] - $hsw_cost ) > 0.01;

	$hsw_matrix[] = array(
		'action'   => $hsw_action,
		'label'    => $hsw_label,
		'rate'     => $hsw_rate,
		'cost'     => $hsw_cost,
		'shipping' => $hsw_totals['shipping'],
		'total'    => $hsw_totals['total'],
		'lag'      => $hsw_lag,
		'bumped'   => $hsw_ver_before !== $hsw_ver_after,
		'cold'     => $hsw_cold,
	);

	hsw_row(
		array(
			$hsw_label,
			$hsw_rate ? $hsw_rate_key : '—',
			hsw_money( $hsw_cost ),
			hsw_money( $hsw_totals['shipping'] ),
			hsw_money( $hsw_totals['total'] ),
			$hsw_lag ? 'YES' : 'no',
			$hsw_ver_before !== $hsw_ver_after ? 'yes' : 'no',
		),
		array( 34, 14, 11, 15, 13, 6, 6 )
	);
}

hsw_say();
hsw_say( 'the rate list is what the radios render; shipping_total is what the summary prints.' );

/* The two states that matter: free → off and paid → free. */
$hsw_free = null;
$hsw_paid = null;
foreach ( $hsw_matrix as $hsw_row_data ) {
	if ( null === $hsw_free && 'on-low' === $hsw_row_data['action'] ) {
		$hsw_free = $hsw_row_data;
	}
	if ( null === $hsw_paid && 'off' === $hsw_row_data['action'] ) {
		$hsw_paid = $hsw_row_data;
	}
}

hsw_check( 'switching on below the threshold makes the rate free', $hsw_free && abs( $hsw_free['cost'] ) < 0.01, $hsw_free ? hsw_money( $hsw_free['cost'] ) : '—' );
hsw_check( 'switching off restores the flat rate cost', $hsw_paid && abs( $hsw_paid['cost'] - 25000 ) < 0.01, $hsw_paid ? hsw_money( $hsw_paid['cost'] ) : '—' );
hsw_check(
	'a real change bumps the shipping cache version',
	$hsw_free && $hsw_free['bumped'],
	'first activation: ' . ( $hsw_matrix[1]['bumped'] ? 'bumped' : 'not bumped' )
);
hsw_check(
	'an unchanged save does not bump the version',
	! end( $hsw_matrix )['bumped'],
	'last step: ' . ( end( $hsw_matrix )['bumped'] ? 'bumped' : 'not bumped' )
);

/* -------------------------------------------------------------------------
 * Phase 2 — the natural checkout interaction
 * ---------------------------------------------------------------------- */

hsw_head( 'Phase 2 — natural checkout interaction (POST /checkout/shipping)' );

hsw_sc_apply( 'on-low' );
hsw_sc_fresh_cart();                // free, calculated, totals 0
hsw_sc_new_request();
WC()->session->set( 'chosen_shipping_methods', array( $hsw_rate_key ) );
hsw_sc_new_request();

hsw_sc_apply( 'off' );              // the rate is paid again; the totals are not
hsw_sc_new_request();

$hsw_before_pick = array(
	'rates'  => hsw_sc_rates(),
	'totals' => hsw_sc_totals(),
);

$hsw_handler = new HSW_Checkout_Rest();
$hsw_request = new WP_REST_Request( 'POST', '/hsw/v1/checkout/shipping' );
$hsw_request->set_param( 'rate', $hsw_rate_key );
$hsw_result = $hsw_handler->choose_shipping( $hsw_request );

$hsw_after_pick = array(
	'rates'  => hsw_sc_rates(),
	'totals' => hsw_sc_totals(),
);

$hsw_pick_cost = isset( $hsw_before_pick['rates'][ $hsw_rate_key ] ) ? (float) $hsw_before_pick['rates'][ $hsw_rate_key ]['cost'] : -1.0;

hsw_row( array( 'step', 'rate cost', 'shipping_total', 'lag' ), array( 34, 12, 16, 6 ) );
hsw_row( array( 'page load (stale)', hsw_money( $hsw_pick_cost ), hsw_money( $hsw_before_pick['totals']['shipping'] ), abs( $hsw_before_pick['totals']['shipping'] - $hsw_pick_cost ) > 0.01 ? 'YES' : 'no' ), array( 34, 12, 16, 6 ) );
hsw_row( array( 'after choose_shipping()', hsw_money( $hsw_pick_cost ), hsw_money( $hsw_after_pick['totals']['shipping'] ), abs( $hsw_after_pick['totals']['shipping'] - $hsw_pick_cost ) > 0.01 ? 'YES' : 'no' ), array( 34, 12, 16, 6 ) );

hsw_say();
hsw_check( 'the checkout handler answers without an error', ! is_wp_error( $hsw_result ), is_wp_error( $hsw_result ) ? $hsw_result->get_error_message() : 'ok' );
hsw_check( 'picking a rate recalculates the totals', abs( $hsw_after_pick['totals']['shipping'] - $hsw_pick_cost ) < 0.01, 'shipping_total ' . hsw_money( $hsw_after_pick['totals']['shipping'] ) );

/* -------------------------------------------------------------------------
 * Phase 3 — the order placed from a stale page
 * ---------------------------------------------------------------------- */

hsw_head( 'Phase 3 — the order placed from the stale page' );

hsw_sc_apply( 'on-low' );
hsw_sc_fresh_cart();
hsw_sc_new_request();
WC()->session->set( 'chosen_shipping_methods', array( $hsw_rate_key ) );
hsw_sc_new_request();

$hsw_free_view = hsw_sc_totals();

hsw_sc_apply( 'off' );
hsw_sc_new_request();

$hsw_stale_rates = hsw_sc_rates();
$hsw_stale_view  = hsw_sc_totals();
$hsw_stale_cost  = isset( $hsw_stale_rates[ $hsw_rate_key ] ) ? (float) $hsw_stale_rates[ $hsw_rate_key ]['cost'] : -1.0;

hsw_say( sprintf( '  page before the order : rate %s · shipping_total %s · cart_total %s', hsw_money( $hsw_stale_cost ), hsw_money( $hsw_stale_view['shipping'] ), hsw_money( $hsw_stale_view['total'] ) ) );

$hsw_order_request = new WP_REST_Request( 'POST', '/hsw/v1/checkout/complete' );
$hsw_order_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$hsw_order_request->set_param( 'method', 'cod' );
$hsw_order_request->set_param( 'terms', true );

$hsw_complete = new HSW_Checkout_Complete();
$hsw_placed   = $hsw_complete->complete( $hsw_order_request );

if ( is_wp_error( $hsw_placed ) ) {
	hsw_check( 'the order was placed', false, $hsw_placed->get_error_message() );
} else {
	$hsw_payload  = $hsw_placed->get_data();
	$hsw_order_id = (int) $hsw_payload['order_id'];

	/*
	 * Read the row back. No `calculate_totals()` on the order, no save: the
	 * numbers below are what the handler wrote, which is the only thing this
	 * assertion is allowed to measure.
	 */
	$hsw_order = hsw_sc_order_from_storage( $hsw_order_id );

	if ( ! $hsw_order ) {
		hsw_check( 'the order exists', false, 'id ' . $hsw_order_id );
	} else {
		$hsw_lines  = $hsw_order->get_items( 'shipping' );
		$hsw_line   = $hsw_lines ? reset( $hsw_lines ) : null;
		$hsw_method = $hsw_line ? $hsw_line->get_name() : $hsw_order->get_shipping_method();
		$hsw_raw    = hsw_sc_order_raw( $hsw_order_id );

		hsw_say( sprintf(
			'  order #%d            : shipping %s · shipping_tax %s · tax %s · total %s · method «%s»',
			$hsw_order->get_id(),
			hsw_money( $hsw_order->get_shipping_total() ),
			hsw_money( $hsw_order->get_shipping_tax() ),
			hsw_money( $hsw_order->get_total_tax() ),
			hsw_money( $hsw_order->get_total() ),
			$hsw_method
		) );

		if ( $hsw_raw ) {
			hsw_say( sprintf(
				'  stored meta          : _order_shipping %s · _order_shipping_tax %s · _order_tax %s · _order_total %s',
				$hsw_raw['_order_shipping'],
				$hsw_raw['_order_shipping_tax'],
				$hsw_raw['_order_tax'],
				$hsw_raw['_order_total']
			) );
		}

		if ( $hsw_line ) {
			hsw_say( sprintf(
				'  shipping line        : %s · total %s · tax %s',
				$hsw_line->get_name(),
				hsw_money( $hsw_line->get_total() ),
				hsw_money( $hsw_line->get_total_tax() )
			) );
		}

		$hsw_expected = $hsw_price + $hsw_stale_cost;

		hsw_check(
			'the order records the current rate, not the stale summary',
			abs( (float) $hsw_order->get_shipping_total() - $hsw_stale_cost ) < 0.01,
			'order ' . hsw_money( $hsw_order->get_shipping_total() ) . ' vs rate ' . hsw_money( $hsw_stale_cost ) . ' vs page ' . hsw_money( $hsw_stale_view['shipping'] )
		);
		hsw_check(
			'the order total matches product + rate',
			abs( (float) $hsw_order->get_total() - $hsw_expected ) < 0.01,
			'order ' . hsw_money( $hsw_order->get_total() ) . ' vs expected ' . hsw_money( $hsw_expected )
		);
		hsw_check(
			'the shipping line carries the same amount as the order',
			$hsw_line && abs( (float) $hsw_line->get_total() - (float) $hsw_order->get_shipping_total() ) < 0.01,
			$hsw_line ? 'line ' . hsw_money( $hsw_line->get_total() ) : 'no shipping line'
		);

		if ( $hsw_raw ) {
			hsw_check(
				'the stored meta agrees with the order object',
				abs( (float) $hsw_raw['_order_shipping'] - (float) $hsw_order->get_shipping_total() ) < 0.01
					&& abs( (float) $hsw_raw['_order_total'] - (float) $hsw_order->get_total() ) < 0.01,
				'meta ' . $hsw_raw['_order_shipping'] . ' / ' . $hsw_raw['_order_total']
			);
		}

		/*
		 * Internal consistency, read-only: the stored total against its lines.
		 *
		 * `WC_Order_Item_Product::get_total()` is already net of the line's
		 * discount (`line_total`, not `line_subtotal`), so subtracting the
		 * order discount again would count it twice. Skipped when a discount
		 * is present because no coupon scenario covers this yet — a green tick
		 * there would be a guess, not a measurement.
		 */
		if ( ! wc_tax_enabled() ) {
			if ( (float) $hsw_order->get_total_discount() > 0 ) {
				hsw_say( '  totals check skipped: the order carries a discount, and no coupon scenario covers it yet' );
			} else {
				$hsw_rebuilt = 0.0;
				foreach ( $hsw_order->get_items() as $hsw_item ) {
					$hsw_rebuilt += (float) $hsw_item->get_total();
				}
				foreach ( $hsw_order->get_items( 'shipping' ) as $hsw_ship_line ) {
					$hsw_rebuilt += (float) $hsw_ship_line->get_total();
				}
				$hsw_rebuilt += (float) $hsw_order->get_total_fees();

				hsw_check(
					'the stored total equals its own line items',
					abs( $hsw_rebuilt - (float) $hsw_order->get_total() ) < 0.01,
					'lines ' . hsw_money( $hsw_rebuilt ) . ' vs total ' . hsw_money( $hsw_order->get_total() )
				);
			}
		}

		hsw_check(
			'the stale summary was wrong',
			abs( $hsw_stale_view['shipping'] - $hsw_stale_cost ) > 0.01,
			'page ' . hsw_money( $hsw_stale_view['shipping'] ) . ' vs rate ' . hsw_money( $hsw_stale_cost )
		);

		$hsw_originals['created'][] = $hsw_order->get_id();
	}
}

/* -------------------------------------------------------------------------
 * Cleanup
 *
 * One function, called twice: once at the end and once from a shutdown
 * handler, so a fatal or an early `exit` still puts the store back. A killed
 * process cannot be caught — which is why the script refuses to run on a
 * production site in the first place.
 * ---------------------------------------------------------------------- */

/**
 * Put the store back the way it was found. Safe to call more than once.
 *
 * @return void
 */
function hsw_sc_restore() {
	global $hsw_originals, $hsw_product_id, $hsw_keep, $hsw_restored;

	if ( $hsw_restored || $hsw_keep ) {
		$hsw_restored = true;
		return;
	}
	$hsw_restored = true;

	foreach ( (array) $hsw_originals['created'] as $hsw_order_id ) {
		$hsw_order = wc_get_order( (int) $hsw_order_id );
		if ( $hsw_order ) {
			$hsw_order->delete( true );
		}
	}

	/*
	 * The rate registry, not a value returned by the fixture function: the
	 * write happens before that return, so the registry is the only record
	 * that survives a fatal in between.
	 */
	$hsw_rates = isset( $hsw_originals['flat_rate'] ) ? (array) $hsw_originals['flat_rate'] : array();
	foreach ( $hsw_rates as $hsw_rate_instance => $hsw_rate_original ) {
		if ( null === $hsw_rate_original ) {
			$hsw_rate_zone = WC_Shipping_Zones::get_zone_by( 'instance_id', (int) $hsw_rate_instance );
			if ( $hsw_rate_zone instanceof WC_Shipping_Zone ) {
				$hsw_rate_zone->delete_shipping_method( (int) $hsw_rate_instance );
			}
		} else {
			update_option( 'woocommerce_flat_rate_' . (int) $hsw_rate_instance . '_settings', $hsw_rate_original );
		}
	}

	/* Anything the fixture created but did not get to register. */
	hsw_sc_sweep_orphans();

	/* The option is written right after save(), so it outlives a fatal there. */
	$hsw_product_id = $hsw_product_id ? $hsw_product_id : (int) get_option( 'hsw_qa_shipping_product', 0 );
	if ( $hsw_product_id ) {
		$hsw_product_obj = wc_get_product( $hsw_product_id );
		/* Only the fixture's own product: the SKU is the proof. */
		if ( $hsw_product_obj && HSW_SC_SKU === (string) $hsw_product_obj->get_sku() ) {
			$hsw_product_obj->delete( true );
			delete_option( 'hsw_qa_shipping_product' );
		}
	}

	foreach ( array( 'on' => HSW_Free_Shipping::ENABLED, 'min' => HSW_Free_Shipping::OPTION ) as $hsw_key => $hsw_option ) {
		if ( null === $hsw_originals[ $hsw_key ] ) {
			delete_option( $hsw_option );
		} else {
			update_option( $hsw_option, $hsw_originals[ $hsw_key ], false );
		}
	}

	update_option( 'woocommerce_enable_guest_checkout', $hsw_originals['guest'] );
	update_option( 'woocommerce_cod_settings', $hsw_originals['cod'] );

	if ( WC()->cart ) {
		WC()->cart->empty_cart();
	}
	if ( WC()->session ) {
		WC()->session->set( 'cart_totals', null );
	}
}

hsw_say();
hsw_say( '== cleanup' );

if ( $hsw_keep ) {
	hsw_say( '  HSW_KEEP=1 — fixture and orders left in place' );
} else {
	hsw_sc_restore();
	hsw_say( '  fixture, orders and options restored' );
}

hsw_say();
hsw_say( $hsw_failed ? sprintf( 'FAILED — %d assertion(s)', $hsw_failed ) : 'ALL PASS' );

exit( $hsw_failed ? 1 : 0 );
