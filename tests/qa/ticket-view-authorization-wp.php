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
// Exercise ownership, close and closed-reply behaviour with two customers.
$action = static function ( $method, $id, $nonce, $message = 'A customer reply' ) use ( $support ) {
    $_POST = array( 'ticket_id' => $id, 'nonce' => $nonce, 'message' => $message ); $_REQUEST = $_POST;
    ob_start(); try { $support->$method(); } catch ( HSW_QA_Ajax_Die $e ) {} return json_decode( ob_get_clean(), true );
};
wp_set_current_user( $user_b ); $nonce_b = wp_create_nonce( 'hsw_ticket_ajax' );
$other_close = $action( 'ajax_close_ticket', $plain_id, $nonce_b );
$other_reply = $action( 'ajax_reply_ticket', $plain_id, $nonce_b );
$check( 'another customer cannot close or reply to ticket', empty( $other_close['success'] ) && empty( $other_reply['success'] ) && 'open' === get_post_meta( $plain_id, HSW_Account_Support::STATUS_META, true ) && 0 === count( get_comments( array( 'post_id' => $plain_id, 'type' => 'hsw_ticket_reply' ) ) ) );
wp_set_current_user( $user_a ); $nonce_a = wp_create_nonce( 'hsw_ticket_ajax' );
$draft_close = $action( 'ajax_close_ticket', $draft, $nonce_a );
$check( 'unpublished ticket cannot be closed by AJAX', empty( $draft_close['success'] ) && '' === get_post_meta( $draft, HSW_Account_Support::STATUS_META, true ) );
$bad_nonce = $action( 'ajax_close_ticket', $plain_id, 'invalid-nonce' );
$check( 'invalid nonce cannot close ticket', 'open' === get_post_meta( $plain_id, HSW_Account_Support::STATUS_META, true ) );
$closed = $action( 'ajax_close_ticket', $plain_id, $nonce_a );
$reply_closed = $action( 'ajax_reply_ticket', $plain_id, $nonce_a );
$check( 'owner closes ticket and cannot reply while closed', ! empty( $closed['success'] ) && empty( $reply_closed['success'] ) && 'closed' === get_post_meta( $plain_id, HSW_Account_Support::STATUS_META, true ) );
// Closed state must be enforced by the server, not just by hiding the composer.
ob_start(); $support->render_thread_box( get_post( $plain_id ) ); $closed_html = ob_get_clean();
$check( 'closed admin thread asks to reopen and omits reply textarea', false !== strpos( $closed_html, 'بازگشایی' ) && false === strpos( $closed_html, 'name="message"' ) );
$admin = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_admin', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
wp_set_current_user( $admin );
$_POST = array( 'ticket_id' => $plain_id, 'hsw_ticket_reply_nonce' => wp_create_nonce( 'hsw_admin_ticket_reply_' . $plain_id ), 'message' => 'A staff reply' ); $_REQUEST = $_POST;
$comments_before = count( get_comments( array( 'post_id' => $plain_id, 'type' => 'hsw_ticket_reply' ) ) ); $mail_before = $mail_count;
ob_start(); try { $support->admin_reply(); } catch ( HSW_QA_Ajax_Die $e ) {} ob_end_clean();
$check( 'staff direct POST cannot reply to closed ticket or send mail', 'closed' === get_post_meta( $plain_id, HSW_Account_Support::STATUS_META, true ) && $comments_before === count( get_comments( array( 'post_id' => $plain_id, 'type' => 'hsw_ticket_reply' ) ) ) && $mail_before === $mail_count );
$request->set_param( 'to', 'support+' . $token_method->invoke( $support, $plain_id ) . '@example.com' );
$request->set_param( 'from', get_option( 'admin_email' ) );
$staff_closed = $support->handle_inbound_email( $request );
$check( 'staff inbound cannot reply to closed ticket', is_wp_error( $staff_closed ) && 'hsw_inbound_closed' === $staff_closed->get_error_code() && 'closed' === get_post_meta( $plain_id, HSW_Account_Support::STATUS_META, true ) && $comments_before === count( get_comments( array( 'post_id' => $plain_id, 'type' => 'hsw_ticket_reply' ) ) ) );
update_post_meta( $plain_id, HSW_Account_Support::STATUS_META, 'open' );
ob_start(); $support->render_thread_box( get_post( $plain_id ) ); $open_html = ob_get_clean();
$check( 'reopened admin thread restores reply textarea', false !== strpos( $open_html, 'name="message"' ) );
// Restore customer identity and original inbound request for subsequent tests.
wp_set_current_user( $user_a );
$request->set_param( 'to', 'support+' . $token_method->invoke( $support, $own ) . '@example.com' ); $request->set_param( 'from', get_userdata( $user_a )->user_email );
// Document default WordPress role behaviour; site-specific roles may differ.
$editor = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$author = wp_insert_user( array( 'user_login' => 'hsw_ticket_qa_author', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$check( 'default editor can edit customer ticket via generic post capabilities', user_can( $editor, 'edit_post', $plain_id ) );
$check( 'default author and subscriber cannot edit another customer ticket in admin', ! user_can( $author, 'edit_post', $plain_id ) && ! user_can( $user_b, 'edit_post', $plain_id ) );
// Inbound authentication and successful reopening, with intercepted mail.
$inbound_before = count( get_comments( array( 'post_id' => $own, 'type' => 'hsw_ticket_reply' ) ) );
$request->set_param( 'secret', 'wrong' ); $denied_secret = $support->handle_inbound_email( $request );
$request->set_param( 'secret', 'qa-inbound-secret' ); $request->set_param( 'from', 'stranger@example.test' ); $denied_sender = $support->handle_inbound_email( $request );
$request->set_param( 'from', get_userdata( $user_a )->user_email ); $request->set_param( 'to', 'support+t' . $own . '.0000000000@example.com' ); $denied_tag = $support->handle_inbound_email( $request );
$check( 'inbound rejects bad secret, sender and tag without recording replies', is_wp_error( $denied_secret ) && is_wp_error( $denied_sender ) && is_wp_error( $denied_tag ) && $inbound_before === count( get_comments( array( 'post_id' => $own, 'type' => 'hsw_ticket_reply' ) ) ) && 'closed' === get_post_meta( $own, HSW_Account_Support::STATUS_META, true ) );
$request->set_param( 'to', 'support+' . $token_method->invoke( $support, $own ) . '@example.com' ); $accepted = $support->handle_inbound_email( $request );
$check( 'authenticated customer inbound reopens and stores reply', ! is_wp_error( $accepted ) && 'open' === get_post_meta( $own, HSW_Account_Support::STATUS_META, true ) && $inbound_before + 1 === count( get_comments( array( 'post_id' => $own, 'type' => 'hsw_ticket_reply' ) ) ) );
// Related order must belong to the ticket creator.
$order = wc_create_order( array( 'customer_id' => $user_b ) );
set_transient( 'hsw_cap_qaorder', 4, 60 );
$_POST = array( 'nonce' => $nonce_a, 'subject' => 'Order QA subject', 'message' => 'Order related QA message.', 'captcha_token' => 'qaorder', 'captcha' => '4', 'order_id' => $order->get_id() ); $_REQUEST = $_POST;
ob_start(); try { $support->ajax_create_ticket(); } catch ( HSW_QA_Ajax_Die $e ) {} $foreign_order_result = json_decode( ob_get_clean(), true );
$check( 'foreign related order is rejected before ticket creation', isset( $foreign_order_result['success'] ) && false === $foreign_order_result['success'] && empty( $foreign_order_result['data']['ticket'] ) );
$order->delete( true );
foreach ( array( $own, $other, $draft, $ordinary, $created_id, $plain_id ) as $id ) { if ( $id ) { wp_delete_post( $id, true ); } }
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_a ); wp_delete_user( $user_b ); wp_delete_user( $editor ); wp_delete_user( $author ); wp_delete_user( $admin );
exit( $failed ? 1 : 0 );
