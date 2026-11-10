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
foreach ( array( $own, $other, $draft, $ordinary ) as $id ) { wp_delete_post( $id, true ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_a ); wp_delete_user( $user_b );
exit( $failed ? 1 : 0 );
