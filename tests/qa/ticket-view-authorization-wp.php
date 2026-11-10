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
    public function inbound_secret() { return 'qa-inbound-secret'; }
    public function add_notification( $user_id, $notification ) {}
}
class HSW_QA_Ajax_Die extends Exception {}
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new HSW_QA_Ajax_Die(); }; } );
$support = new HSW_Account_Support( new HSW_QA_Support_Account() );
$support->register_post_type();
$user_a = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_a', 'user_email' => 'hsw-ticket-qa-a@example.test', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
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
// Inbound must also surface the storage error without reopening a closed ticket.
update_post_meta( $own, HSW_Account_Support::STATUS_META, 'closed' );
update_post_meta( $own, HSW_Account_Support::UPDATED_META, 12345 );
$token_method = new ReflectionMethod( $support, 'reply_token' ); $token_method->setAccessible( true );
$request = new WP_REST_Request( 'POST', '/hsw/v1/ticket-inbound' );
$request->set_param( 'secret', 'qa-inbound-secret' );
$request->set_param( 'to', 'support+' . $token_method->invoke( $support, $own ) . '@example.com' );
$request->set_param( 'from', get_userdata( $user_a )->user_email );
$request->set_param( 'text', 'Inbound QA reply' );
$mail_before = $mail_count;
$old_suppress = $wpdb->suppress_errors( true ); add_filter( 'query', $fail_insert );
$inbound = $support->handle_inbound_email( $request );
remove_filter( 'query', $fail_insert ); $wpdb->suppress_errors( $old_suppress );
$check( 'inbound insertion failure returns error without reopening or mail', is_wp_error( $inbound ) && 'hsw_inbound_save' === $inbound->get_error_code() && 'closed' === get_post_meta( $own, HSW_Account_Support::STATUS_META, true ) && '12345' === get_post_meta( $own, HSW_Account_Support::UPDATED_META, true ) && $mail_count === $mail_before );
// Failed upload is a partial success: one ticket exists and a visible warning
// is returned, so a client should not retry and create a duplicate.
set_transient( 'hsw_cap_qaimage', 7, 60 );
$_POST = array( 'nonce' => wp_create_nonce( 'hsw_ticket_ajax' ), 'subject' => 'Image upload QA', 'message' => 'This is a test ticket message.', 'captcha_token' => 'qaimage', 'captcha' => '7' ); $_REQUEST = $_POST;
$_FILES['image'] = array( 'name' => 'example.jpg', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE );
ob_start(); try { $support->ajax_create_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {} $created = json_decode( ob_get_clean(), true );
unset( $_FILES['image'] );
$created_id = (int) ( $created['data']['ticket'] ?? 0 );
$check( 'failed upload creates one ticket and reports partial success', ! empty( $created['success'] ) && $created_id && ! empty( $created['data']['image_warning'] ) && get_post( $created_id ) && ! get_post_meta( $created_id, HSW_Account_Support::IMAGE_META, true ) );
$upload_method = new ReflectionMethod( $support, 'handle_image_upload' ); $upload_method->setAccessible( true );
$check( 'absent image is optional', 0 === $upload_method->invoke( $support, $own ) );
$_FILES['image'] = array( 'name' => 'large.jpg', 'tmp_name' => '/tmp/nonexistent', 'size' => 5 * MB_IN_BYTES, 'error' => UPLOAD_ERR_OK );
$check( 'oversized image reports an error', is_wp_error( $upload_method->invoke( $support, $own ) ) ); unset( $_FILES['image'] );
set_transient( 'hsw_cap_qanoimage', 8, 60 ); $_POST['captcha_token'] = 'qanoimage'; $_POST['captcha'] = '8'; $_REQUEST = $_POST;
ob_start(); try { $support->ajax_create_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {} $plain = json_decode( ob_get_clean(), true );
$plain_id = (int) ( $plain['data']['ticket'] ?? 0 );
$check( 'ticket without an image has no upload warning', ! empty( $plain['success'] ) && $plain_id && empty( $plain['data']['image_warning'] ) );
foreach ( array( $own, $other, $draft, $ordinary, $created_id, $plain_id ) as $id ) { if ( $id ) { wp_delete_post( $id, true ); } }
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_a ); wp_delete_user( $user_b );
exit( $failed ? 1 : 0 );
