<?php
/** AJAX ticket ownership regression on disposable WordPress only. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
define( 'DOING_AJAX', true );
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-account-support.php';
class HSW_QA_Support_Account {
    public function setting( $key, $default = null ) { return $default; }
    public function icon( $name ) { return ''; }
    public function format_date( $timestamp ) { return (string) $timestamp; }
    public function endpoint_enabled( $name ) { return true; }
}
class HSW_QA_Ajax_Die extends Exception {}
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new HSW_QA_Ajax_Die(); }; } );
$support = new HSW_Account_Support( new HSW_QA_Support_Account() );
$support->register_post_type();
$user_a = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_a', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$user_b = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_b', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$own = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $user_a, 'post_title' => 'Own subject', 'post_content' => 'Own private message' ) );
$other = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $user_b, 'post_title' => 'Secret subject', 'post_content' => 'Secret private message' ) );
$draft = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'draft', 'post_author' => $user_a, 'post_title' => 'Draft secret' ) );
$ordinary = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user_a, 'post_title' => 'Not a ticket' ) );
wp_set_current_user( $user_a );
$call = static function ( $id ) use ( $support ) {
    $_POST = array( 'ticket_id' => $id, 'nonce' => wp_create_nonce( 'hsw_ticket_ajax' ) );
    $_REQUEST = $_POST;
    ob_start();
    try { $support->ajax_view_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {}
    return json_decode( ob_get_clean(), true );
};
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$own_result = $call( $own );
$check( 'owner can view own ticket and row', ! empty( $own_result['success'] ) && false !== strpos( $own_result['data']['row'], 'Own subject' ) );
foreach ( array( 'another customer' => $other, 'draft ticket' => $draft, 'other post type' => $ordinary, 'missing ID' => 0 ) as $name => $id ) {
    $result = $call( $id );
    $check( "$name rejected without row or content", isset( $result['success'] ) && false === $result['success'] && empty( $result['data']['row'] ) && false === strpos( wp_json_encode( $result ), 'Secret' ) );
}
// Simulate a database insert failure, without changing any real site's data.
update_post_meta( $own, HSW_Account_Support::STATUS_META, 'answered' );
update_post_meta( $own, HSW_Account_Support::UPDATED_META, 12345 );
update_post_meta( $own, HSW_Account_Support::UNREAD_ADMIN, 0 );
$mail_count = 0;
add_filter( 'pre_wp_mail', static function ( $result ) use ( &$mail_count ) { ++$mail_count; return true; } );
$fail_insert = static function ( $query ) use ( $wpdb ) {
    if ( preg_match( '/^\s*INSERT\s+INTO\s+`?' . preg_quote( $wpdb->comments, '/' ) . '`?\s/i', $query ) ) {
        return 'INSERT INTO hsw_qa_missing_table VALUES (1)';
    }
    return $query;
};
$old_suppress = $wpdb->suppress_errors( true );
add_filter( 'query', $fail_insert );
$_POST = array( 'ticket_id' => $own, 'nonce' => wp_create_nonce( 'hsw_ticket_ajax' ), 'message' => 'QA reply text' ); $_REQUEST = $_POST;
ob_start(); try { $support->ajax_reply_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {} $result = json_decode( ob_get_clean(), true );
remove_filter( 'query', $fail_insert );
$wpdb->suppress_errors( $old_suppress );
$check( 'failed reply returns error', isset( $result['success'] ) && false === $result['success'] );
$check( 'failed reply leaves status, timestamp, unread and mail unchanged', 'answered' === get_post_meta( $own, HSW_Account_Support::STATUS_META, true ) && '12345' === get_post_meta( $own, HSW_Account_Support::UPDATED_META, true ) && '0' === get_post_meta( $own, HSW_Account_Support::UNREAD_ADMIN, true ) && 0 === $mail_count );
$check( 'failed reply creates no comment', 0 === count( get_comments( array( 'post_id' => $own, 'type' => 'hsw_ticket_reply' ) ) ) );
ob_start(); try { $support->ajax_reply_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {} $result = json_decode( ob_get_clean(), true );
$check( 'successful reply is stored and opens ticket', ! empty( $result['success'] ) && ! empty( $result['data']['comment'] ) && 'open' === get_post_meta( $own, HSW_Account_Support::STATUS_META, true ) && 1 === count( get_comments( array( 'post_id' => $own, 'type' => 'hsw_ticket_reply' ) ) ) );
foreach ( array( $own, $other, $draft, $ordinary ) as $id ) { wp_delete_post( $id, true ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_a ); wp_delete_user( $user_b );
exit( $failed ? 1 : 0 );
