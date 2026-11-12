<?php
/**
 * Ticket-image authorization regression on disposable WordPress only.
 *
 * Covers the v1.11.274 fix: the image is served by admin-post.php with a
 * signed link plus a logged-in owner/staff check, never by REST (which an
 * <img> tag cannot authenticate) and never by the public uploads directory.
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
if ( ! class_exists( 'HSW_Account_Support' ) ) {
    require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-account-support.php';
}

class HSW_QA_Support_Account {
    public function setting( $key, $default = null ) { return $default; }
    public function icon( $name ) { return ''; }
    public function format_date( $timestamp ) { return (string) $timestamp; }
    public function endpoint_enabled( $name ) { return true; }
    public function inbound_secret() { return 'qa-inbound-secret'; }
    public function add_notification( $user_id, $notification ) {}
}

$support = new HSW_Account_Support( new HSW_QA_Support_Account() );
$support->register_post_type();

$sign  = new ReflectionMethod( $support, 'ticket_image_sign' );
$sign->setAccessible( true );
$validate = new ReflectionMethod( $support, 'validate_ticket_image_request' );
$validate->setAccessible( true );

$failed = 0;
$check  = static function ( $name, $ok ) use ( &$failed ) {
    echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL;
    if ( ! $ok ) { ++$failed; }
};

$owner = wp_insert_user( array( 'user_login' => 'hsw_img_qa_owner', 'user_email' => 'hsw-img-owner@example.test', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$other = wp_insert_user( array( 'user_login' => 'hsw_img_qa_other', 'user_email' => 'hsw-img-other@example.test', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$admin = wp_insert_user( array( 'user_login' => 'hsw_img_qa_admin', 'user_email' => 'hsw-img-admin@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );

$ticket = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Image QA', 'post_content' => 'Screenshot attached.' ) );
$other_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Not a ticket' ) );

$uploads = wp_upload_dir( null, false );
$base    = empty( $uploads['error'] ) ? untrailingslashit( $uploads['basedir'] ) : untrailingslashit( WP_CONTENT_DIR ) . '/uploads';
$dir     = $base . '/hsw-ticket-images/' . (int) $ticket . '/';
wp_mkdir_p( $dir );
$file = $dir . 'qa-sample.png';
file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
$path = 'hsw-ticket-images/' . (int) $ticket . '/qa-sample.png';
update_post_meta( $ticket, HSW_Account_Support::IMAGE_PATH_META, $path );

$now = time();
$ok  = static function ( $id, $ts ) use ( $sign, $support ) {
    return $sign->invoke( $support, $id, $ts );
};
$result = static function ( $id, $ts, $sig ) use ( $validate, $support ) {
    return $validate->invoke( $support, $id, $ts, $sig );
};

/* The link must point at admin-post.php: REST cannot authenticate an <img>. */
$url = $support->ticket_image_url( $ticket, $path );
$check( 'image URL uses admin-post.php, not REST', false !== strpos( $url, 'admin-post.php' ) && false === strpos( $url, '/wp-json/' ) && false !== strpos( $url, 'action=hsw_ticket_image' ) );
$check( 'image URL carries ticket, ts and signature', false !== strpos( $url, 'ticket_id=' . $ticket ) && preg_match( '/[?&]ts=\d+/', $url ) && preg_match( '/[?&]sig=[0-9a-f]{64}/', $url ) );

/* Owner and staff are allowed; everyone else is not. */
wp_set_current_user( $owner );
$owner_result = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'ticket owner may view the image', is_string( $owner_result ) && is_file( $owner_result ) );

wp_set_current_user( $admin );
$admin_result = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'administrator with manage_woocommerce may view the image', is_string( $admin_result ) && is_file( $admin_result ) );

wp_set_current_user( $other );
$foreign = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'another customer is forbidden', is_wp_error( $foreign ) && 'hsw_image_forbidden' === $foreign->get_error_code() && 403 === ( $foreign->get_error_data()['status'] ?? 0 ) );

wp_set_current_user( 0 );
$guest = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'guest is forbidden', is_wp_error( $guest ) && 'hsw_image_forbidden' === $guest->get_error_code() );

wp_set_current_user( $owner );
$bad = $result( $ticket, $now, str_repeat( 'a', 64 ) );
$check( 'wrong signature is rejected', is_wp_error( $bad ) && 'hsw_image_bad_sign' === $bad->get_error_code() );
$empty = $result( $ticket, 0, '' );
$check( 'missing ts or signature is rejected', is_wp_error( $empty ) && 'hsw_image_bad_sign' === $empty->get_error_code() );

$old_ts = $now - ( 8 * DAY_IN_SECONDS );
$expired = $result( $ticket, $old_ts, $ok( $ticket, $old_ts ) );
$check( 'signed link older than seven days has expired', is_wp_error( $expired ) && 'hsw_image_expired' === $expired->get_error_code() && 403 === ( $expired->get_error_data()['status'] ?? 0 ) );

$wrong_type = $result( $other_post, $now, $ok( $other_post, $now ) );
$check( 'non-ticket post id is not found', is_wp_error( $wrong_type ) && 'hsw_image_ticket' === $wrong_type->get_error_code() && 404 === ( $wrong_type->get_error_data()['status'] ?? 0 ) );

$no_meta = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'No image' ) );
$missing_meta = $result( $no_meta, $now, $ok( $no_meta, $now ) );
$check( 'ticket without an image is not found', is_wp_error( $missing_meta ) && 'hsw_image_missing' === $missing_meta->get_error_code() && 404 === ( $missing_meta->get_error_data()['status'] ?? 0 ) );

update_post_meta( $ticket, HSW_Account_Support::IMAGE_PATH_META, 'hsw-ticket-images/' . (int) $ticket . '/gone.png' );
$missing_file = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'missing file on disk is not found', is_wp_error( $missing_file ) && 'hsw_image_missing' === $missing_file->get_error_code() && 404 === ( $missing_file->get_error_data()['status'] ?? 0 ) );

update_post_meta( $ticket, HSW_Account_Support::IMAGE_PATH_META, $path );
wp_set_current_user( $owner );
$still_ok = $result( $ticket, $now, $ok( $ticket, $now ) );
$check( 'restored path still resolves for the owner', is_string( $still_ok ) && is_file( $still_ok ) );

/* A legacy image written by 1.11.273 under wp-content/uploads must stay readable. */
$legacy_rel = 'hsw-ticket-images/' . (int) $ticket . '/legacy.png';
$legacy_dir = untrailingslashit( WP_CONTENT_DIR ) . '/uploads/' . dirname( $legacy_rel ) . '/';
$legacy_file = untrailingslashit( WP_CONTENT_DIR ) . '/uploads/' . $legacy_rel;
if ( $legacy_file !== $file ) {
    wp_mkdir_p( $legacy_dir );
    file_put_contents( $legacy_file, "legacy" );
    update_post_meta( $ticket, HSW_Account_Support::IMAGE_PATH_META, $legacy_rel );
    $legacy = $result( $ticket, $now, $ok( $ticket, $now ) );
    $check( 'legacy wp-content/uploads image still resolves', is_string( $legacy ) && is_file( $legacy ) );
    @unlink( $legacy_file );
    @rmdir( $legacy_dir );
    update_post_meta( $ticket, HSW_Account_Support::IMAGE_PATH_META, $path );
}

/* An upload must land under wp_upload_dir(), not a hardcoded wp-content path. */
$tmp = get_temp_dir() . 'hsw-qa-upload-' . wp_generate_password( 8, false ) . '.png';
file_put_contents( $tmp, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
$_FILES['image'] = array( 'name' => 'qa upload.png', 'tmp_name' => $tmp, 'size' => filesize( $tmp ), 'error' => UPLOAD_ERR_OK, 'type' => 'image/png' );
$upload = new ReflectionMethod( $support, 'handle_image_upload' );
$upload->setAccessible( true );
$stored = $upload->invoke( $support, $ticket );
unset( $_FILES['image'] );
$check( 'upload stores a relative path under the ticket folder', is_string( $stored ) && 0 === strpos( $stored, 'hsw-ticket-images/' . (int) $ticket . '/' ) );
$stored_abs = is_string( $stored ) ? $base . '/' . $stored : '';
$check( 'uploaded file exists with a random, sanitized name', is_string( $stored ) && is_file( $stored_abs ) && 1 === preg_match( '#^hsw-ticket-images/' . (int) $ticket . '/[A-Za-z0-9]{12}-qa-upload\.png$#', $stored ) );
if ( is_string( $stored ) ) {
    @unlink( $stored_abs );
}
@unlink( $tmp );

@unlink( $file );
@rmdir( $dir );

foreach ( array( $ticket, $other_post, $no_meta ) as $id ) { wp_delete_post( $id, true ); }
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $owner, $other, $admin ) as $id ) { wp_delete_user( $id ); }

exit( $failed ? 1 : 0 );
