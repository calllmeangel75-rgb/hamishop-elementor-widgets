<?php
/**
 * Regression: the variation pickers must speak WooCommerce's attribute keys.
 *
 * A custom attribute is stored under `sanitize_title()` — `Color` becomes
 * `color`, `رنگ جلد` becomes `%d8%b1%d9%86%da%af-%d8%ac%d9%84%d8%af` — while the
 * picker used to publish the typed label. The JS matcher compares the two maps
 * literally, so every custom attribute silently matched no variation: the price
 * never moved and the first variation was added whatever the customer picked.
 * A name with a space also lost its whole picker, because decoding the stored
 * key gives `رنگ-جلد` (hyphen) instead of `رنگ جلد` (space).
 *
 * This test builds one variable product with both spellings, renders the real
 * widget, and requires every picker key to exist in the variation payload.
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) {
	fwrite( STDERR, "Disposable CLI install required.\n" );
	exit( 2 );
}
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL;
		}
	}
);
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) {
	fwrite( STDERR, "HSW_WP_LOAD required.\n" );
	exit( 2 );
}
require $path;
if ( ! function_exists( 'wc_get_product' ) || ! class_exists( '\Elementor\Plugin' ) ) {
	fwrite( STDERR, "WooCommerce + Elementor must be active.\n" );
	exit( 2 );
}

/* Widget classes are registered on Elementor's hook, so load the two under test. */
$hsw_root  = dirname( __DIR__, 2 );
$hsw_files = array(
	'includes/class-hsw-widget-base.php'          => 'HSW_Widget_Base',
	'includes/class-hsw-product-base.php'         => 'HSW_Product_Base',
	'includes/class-hsw-simple-attributes.php'    => 'HSW_Simple_Attributes',
	'widgets/class-hsw-widget-product-variants.php' => 'HSW_Widget_Product_Variants',
);
foreach ( $hsw_files as $hsw_file => $hsw_class ) {
	/* The plugin may already have loaded its own copy of the file. */
	if ( ! class_exists( $hsw_class ) ) {
		require_once $hsw_root . '/' . $hsw_file;
	}
}
if ( ! class_exists( 'HSW_Widget_Product_Variants' ) ) {
	fwrite( STDERR, "Widget classes could not be loaded.\n" );
	exit( 2 );
}

$failed = 0;
$check  = static function ( $name, $ok, $detail = '' ) use ( &$failed ) {
	echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . ( '' !== $detail ? ' — ' . $detail : '' ) . PHP_EOL;
	if ( ! $ok ) {
		++$failed;
	}
};

/**
 * Call a protected method on a widget instance.
 */
$call = static function ( $object, $method, array $args = array() ) {
	$ref = new ReflectionMethod( $object, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( $object, $args );
};

/* ---------------------------------------------------------------------
 * Fixture: two custom attributes, one Persian with a space, one Latin.
 * ------------------------------------------------------------------ */
$product = new WC_Product_Variable();
$product->set_name( 'HSW QA attribute keys' );
$product->set_status( 'publish' );

$attributes = array();
foreach ( array( 'رنگ جلد' => array( 'مشکی', 'آبی' ), 'Size' => array( 'S', 'M' ) ) as $name => $options ) {
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( 0 );
	$attribute->set_name( $name );
	$attribute->set_options( $options );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$attributes[] = $attribute;
}
$product->set_attributes( $attributes );
$product_id = $product->save();
$keys       = array_keys( $product->get_attributes() );

$i = 0;
foreach ( array( array( 'مشکی', 'S' ), array( 'آبی', 'S' ), array( 'مشکی', 'M' ), array( 'آبی', 'M' ) ) as $combo ) {
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $product_id );
	$variation->set_attributes( array( $keys[0] => $combo[0], $keys[1] => $combo[1] ) );
	$variation->set_regular_price( (string) ( 100000 + $i * 25000 ) );
	$variation->set_stock_status( 'instock' );
	$variation->save();
	++$i;
}

$product = wc_get_product( $product_id );
$widget  = new HSW_Widget_Product_Variants();
$widget->hsw_inject_settings(
	array(
		'vis'          => 'all',
		'attr_color'   => 'pa_color',
		'attr_storage' => 'pa_memory',
	)
);

/* 1. The typed name finds the attribute it names. */
$terms = $call( $widget, 'attribute_terms', array( $product, 'رنگ جلد' ) );
$check( 'colour attribute found by its typed name', 2 === count( $terms ), count( $terms ) . ' option(s)' );

/* 2. The payload carries the key WooCommerce stores on each variation. */
$payload   = $call( $widget, 'variations_payload', array( $product ) );
$canonical = array( sanitize_title( 'رنگ جلد' ), sanitize_title( 'Size' ) );
$missing   = array();
foreach ( $payload as $row ) {
	foreach ( $canonical as $key ) {
		if ( ! isset( $row['attrs'][ $key ] ) ) {
			$missing[ $key ] = true;
		}
	}
}
$check( 'variation payload uses the stored attribute keys', ! $missing, implode( ', ', array_keys( $missing ) ) );

/* 3. The rendered picker agrees with that payload. */
$GLOBALS['product'] = $product;
$GLOBALS['post']    = get_post( $product_id );
setup_postdata( $GLOBALS['post'] );
ob_start();
$call( $widget, 'render' );
$html = ob_get_clean();
wp_reset_postdata();

$check( 'picker renders a colour group', false !== strpos( $html, 'data-hsw-color="' ) && false !== strpos( $html, 'hsw-pvar__swatch' ) );

preg_match( "/data-hsw-attrs='([^']+)'/", $html, $map_match );
preg_match( "/data-hsw-variations='([^']+)'/", $html, $runs_match );
$map  = isset( $map_match[1] ) ? json_decode( html_entity_decode( $map_match[1], ENT_QUOTES ), true ) : null;
$list = isset( $runs_match[1] ) ? json_decode( html_entity_decode( $runs_match[1], ENT_QUOTES ), true ) : null;
$check( 'picker publishes its attribute map', is_array( $map ) && is_array( $list ), is_array( $map ) ? 'ok' : 'no map' );

$picker_keys = array();
if ( is_array( $map ) ) {
	foreach ( array_merge( array( $map['color'] ?? '', $map['storage'] ?? '' ), (array) ( $map['chips'] ?? array() ) ) as $key ) {
		if ( '' !== $key ) {
			$picker_keys[ $key ] = true;
		}
	}
}
$orphans = array();
if ( is_array( $list ) ) {
	foreach ( array_keys( $picker_keys ) as $key ) {
		$found = false;
		foreach ( $list as $row ) {
			if ( isset( $row['attrs'][ $key ] ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$orphans[] = $key;
		}
	}
}
$check( 'every picker key exists in the variation payload', ! $orphans, implode( ', ', $orphans ) );
$check( 'both attributes became pickers', 2 === count( $picker_keys ), count( $picker_keys ) . ' key(s)' );

/* 4. A simple product keeps the value the customer sees, and survives sanitising. */
$simple = new WC_Product_Simple();
$simple->set_name( 'HSW QA simple attribute' );
$simple->set_status( 'publish' );
$simple->set_regular_price( '35000' );
$pick = new WC_Product_Attribute();
$pick->set_id( 0 );
$pick->set_name( 'رنگ' );
$pick->set_options( array( 'آبی', 'مشکی' ) );
$pick->set_visible( true );
$pick->set_variation( false );
$simple->set_attributes( array( $pick ) );
$simple_id = $simple->save();
$simple    = wc_get_product( $simple_id );

$picked = $call( $widget, 'pickable_attributes', array( $simple ) );
$slug   = $picked[0]['terms'][0]['slug'] ?? '';
$check( 'simple picker posts the option, not a percent-encoded slug', 'آبی' === $slug, $slug );

if ( class_exists( 'HSW_Simple_Attributes' ) ) {
	$picker = new HSW_Simple_Attributes();
	$_REQUEST = array( 'attribute_رنگ' => $slug );
	$read = $call( $picker, 'read_request', array( $simple ) );
	$check( 'cart data accepts a Persian option', isset( $read['رنگ'] ) && 'آبی' === $read['رنگ']['value'], wp_json_encode( $read, JSON_UNESCAPED_UNICODE ) );

	$_REQUEST = array( 'attribute_رنگ' => sanitize_title( 'آبی' ) );
	$read     = $call( $picker, 'read_request', array( $simple ) );
	$check( 'cart data still accepts the encoded slug', isset( $read['رنگ'] ) && 'آبی' === $read['رنگ']['value'], wp_json_encode( $read, JSON_UNESCAPED_UNICODE ) );
	$_REQUEST = array();
}

/* Cleanup. */
foreach ( array( $product_id, $simple_id ) as $id ) {
	foreach ( get_children( array( 'post_parent' => $id, 'post_type' => 'product_variation', 'fields' => 'ids' ) ) as $child ) {
		wp_delete_post( $child, true );
	}
	wp_delete_post( $id, true );
}

exit( $failed ? 1 : 0 );
