<?php
/** Inspect registered Elementor controls on a disposable WP/WC/Elementor install. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL; } } );
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $path;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! class_exists( '\\Elementor\\Plugin' ) ) { exit( 2 ); }
$root = dirname( __DIR__, 2 );
require_once $root . '/includes/trait-hsw-style-kit.php';
require_once $root . '/includes/class-hsw-widget-base.php';
foreach ( array( 'class-hsw-wonder-deals.php', 'class-hsw-best-sellers.php', 'class-hsw-popular.php' ) as $file ) { require_once $root . '/widgets/' . $file; }
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
foreach ( array( 'HSW_Widget_Wonder_Deals', 'HSW_Widget_Best_Sellers', 'HSW_Widget_Popular' ) as $class ) {
    $widget = new $class();
    $get = static function ( $id ) use ( $widget ) { return $widget->get_controls( $id ); };
    foreach ( array( 'btn_bg', 'btn_bgh', 'btn_c', 'btn_ch', 'btn_radius' ) as $id ) {
        $c = $get( $id ) ?: array();
        $check( "$class legacy $id retained and hidden only in editor", isset( $c['selectors'] ) && 'hsw-loop-legacy-button-control' === ( $c['classes'] ?? '' ) && empty( $c['condition'] ) );
    }
    foreach ( array( 'add_btn_bg', 'add_btn_c', 'add_btn_bg_h', 'add_btn_c_h', 'add_btn_r' ) as $id ) {
        $check( "$class canonical $id visible", is_array( $get( $id ) ) && false === strpos( (string) ( $get( $id )['classes'] ?? '' ), 'hsw-loop-legacy-button-control' ) );
    }
    if ( 'HSW_Widget_Wonder_Deals' === $class ) {
        foreach ( array( 'lead_btn_bg', 'lead_btn_c', 'lead_btn_ch', 'lead_btn_bgh', 'lead_button_radius' ) as $id ) {
            $check( "$id in separate lead button section", is_array( $get( $id ) ) && 'sec_style_lead_button' === ( $get( $id )['section'] ?? '' ) );
        }
        $check( 'lead title color still in lead banner section', 'sec_style_lead' === ( $get( 'lead_c' )['section'] ?? '' ) );
    }
}
exit( $failed ? 1 : 0 );
