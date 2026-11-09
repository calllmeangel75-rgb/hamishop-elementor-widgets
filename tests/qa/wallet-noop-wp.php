<?php
/** Disposable integration test: never run on a live shop. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { fwrite( STDERR, "Disposable CLI install required.\n" ); exit( 2 ); }
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { echo '::error::' . str_replace( "\n", ' ', $e['message'] ) . PHP_EOL; } } );
$path = getenv( 'HSW_WP_LOAD' );
if ( ! $path || ! is_file( $path ) ) { fwrite( STDERR, "HSW_WP_LOAD required.\n" ); exit( 2 ); }
require $path;
if ( ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type() || ! class_exists( 'HSW_Wallet' ) ) { fwrite( STDERR, "Disposable WP/WC/HamiShop required.\n" ); exit( 2 ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! HSW_Wallet::instance()->install_tables() ) { exit( 2 ); }
$login = 'hsw_noop_' . strtolower( wp_generate_password( 12, false ) );
$user = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.invalid' );
if ( is_wp_error( $user ) ) { exit( 2 ); }
register_shutdown_function( static function () use ( $user ) { global $wpdb; $wpdb->delete( HSW_Wallet::table(), array( 'user_id' => $user ) ); $wpdb->delete( HSW_Wallet::balance_table(), array( 'user_id' => $user ) ); wp_delete_user( $user ); } );
global $wpdb;
$count = static function () use ( $wpdb, $user ) { return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSW_Wallet::table() . ' WHERE user_id = %d', $user ) ); };
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$result = HSW_Wallet::set_balance( $user, 0, 'qa', 1, 0 );
$check( 'new zero balance does not create ledger row', ! is_wp_error( $result ) && 0 === $count() && 0.0 === (float) HSW_Wallet::balance( $user ) );
$result = HSW_Wallet::set_balance( $user, 0, 'qa', 1, 0 );
$check( 'existing unchanged zero with original value creates no row', ! is_wp_error( $result ) && 0 === $count() );
$result = HSW_Wallet::set_balance( $user, 100, 'qa', 1, 0 );
$check( 'real adjustment records one row', ! is_wp_error( $result ) && 1 === $count() && 100.0 === (float) HSW_Wallet::balance( $user ) );
$result = HSW_Wallet::set_balance( $user, 100, 'qa', 1, 100 );
$check( 'unchanged nonzero balance creates no row', ! is_wp_error( $result ) && 1 === $count() );
$result = HSW_Wallet::set_balance( $user, 100, 'qa', 1, 0 );
$check( 'stale original is rejected even when target matches', is_wp_error( $result ) && 'hsw_wallet_stale' === $result->get_error_code() && 1 === $count() );
$result = HSW_Wallet::set_balance( $user, 0, 'qa', 1, 100 );
$check( 'real debit to zero records another row', ! is_wp_error( $result ) && 2 === $count() && 0.0 === (float) HSW_Wallet::balance( $user ) );
exit( $failed ? 1 : 0 );
