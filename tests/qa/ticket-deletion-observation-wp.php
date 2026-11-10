<?php
/** Destructive only to freshly created fixtures in disposable CI WordPress. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
require_once dirname( __DIR__, 2 ) . '/includes/class-hsw-account-support.php';
$account = new class { public function setting( $key, $default = null ) { return $default; } };
$support = new HSW_Account_Support( $account ); $support->register_post_type();
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$user = wp_insert_user( array( 'user_login' => 'hsw_delete_qa_user', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$ticket = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $user, 'post_title' => 'Deletion test' ) );
$comment = wp_insert_comment( array( 'comment_post_ID' => $ticket, 'comment_content' => 'Test reply', 'comment_type' => 'hsw_ticket_reply', 'comment_approved' => 1, 'user_id' => $user ) );
add_comment_meta( $comment, 'hsw_delete_qa', 'reply metadata' ); add_post_meta( $ticket, 'hsw_delete_qa', 'ticket metadata' );
$ticket_url = $support->support_url( $ticket );
$notifications = array(
	array( 'type' => 'ticket', 'ticket_id' => $ticket, 'url' => 'https://old.example/ticket/' ),
	array( 'type' => 'ticket', 'url' => $ticket_url ),
	array( 'type' => 'ticket', 'ticket_id' => $ticket + 1, 'url' => $ticket_url ),
	array( 'type' => 'order', 'url' => $ticket_url ),
	array( 'type' => 'ticket', 'url' => 'https://old.example/unknown/' ),
);
update_user_meta( $user, '_hsw_notifications', $notifications );
$uploads = wp_upload_dir(); $folder = trailingslashit( $uploads['basedir'] ) . 'hsw-delete-qa-' . wp_generate_password( 12, false, false );
wp_mkdir_p( $folder ); $original = $folder . '/qa.jpg'; $thumb = $folder . '/qa-150x150.jpg';
file_put_contents( $original, 'qa-original' ); file_put_contents( $thumb, 'qa-thumbnail' );
$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'QA image', 'post_status' => 'inherit' ), $original, $ticket );
wp_update_attachment_metadata( $attachment, array( 'file' => basename( $folder ) . '/qa.jpg', 'sizes' => array( 'thumbnail' => array( 'file' => basename( $thumb ), 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg' ) ) ) );
update_post_meta( $ticket, HSW_Account_Support::IMAGE_META, $attachment );
update_comment_meta( $comment, HSW_Account_Support::IMAGE_META, $attachment );
// Parentage alone is not exclusive ownership: another ticket and a regular
// post can both reuse the exact same media attachment.
$shared_ticket = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $user ) );
update_post_meta( $shared_ticket, HSW_Account_Support::IMAGE_META, $attachment );
$shared_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user, 'post_content' => '<img src="' . esc_url( wp_get_attachment_url( $attachment ) ) . '">' ) );
set_post_thumbnail( $shared_post, $attachment );
$cancel_delete = static function ( $result, $post ) use ( $ticket ) { return (int) $post->ID === (int) $ticket ? false : $result; };
add_filter( 'pre_delete_post', $cancel_delete, 10, 2 );
wp_delete_post( $ticket, true );
remove_filter( 'pre_delete_post', $cancel_delete, 10 );
$check( 'blocked deletion does not queue attachment', get_post( $ticket ) && ! metadata_exists( 'post', $attachment, HSW_Account_Support::IMAGE_REVIEW_META ) );
wp_trash_post( $ticket );
$check( 'trash retains ticket, replies, attachment and notifications', 'trash' === get_post_status( $ticket ) && get_comment( $comment ) && get_post( $attachment ) && file_exists( $original ) && get_user_meta( $user, '_hsw_notifications', true ) === $notifications );
$check( 'trash does not flag attachment for review', ! metadata_exists( 'post', $attachment, HSW_Account_Support::IMAGE_REVIEW_META ) );
wp_untrash_post( $ticket );
$check( 'untrash restores ticket without altering image or notifications', get_post( $ticket ) && 'trash' !== get_post_status( $ticket ) && get_post( $attachment ) && file_exists( $original ) && get_user_meta( $user, '_hsw_notifications', true ) === $notifications );
wp_delete_post( $ticket, true );
$check( 'permanent deletion removes ticket, metadata, reply and reply metadata', ! get_post( $ticket ) && ! metadata_exists( 'post', $ticket, 'hsw_delete_qa' ) && ! get_comment( $comment ) && ! metadata_exists( 'comment', $comment, 'hsw_delete_qa' ) );
$check( 'attachment and image files remain after ticket deletion', get_post( $attachment ) && file_exists( $original ) && file_exists( $thumb ) );
$check( 'deleted ticket image is queued once for review', get_post_meta( $attachment, HSW_Account_Support::IMAGE_REVIEW_META, false ) === array( (string) $ticket ) );
$review = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => array( 'inherit', 'private', 'publish', 'trash' ), 'meta_query' => array( array( 'key' => HSW_Account_Support::IMAGE_REVIEW_META, 'compare' => 'EXISTS' ) ) ) );
$check( 'review queue finds candidate attachment', in_array( $attachment, wp_list_pluck( $review->posts, 'ID' ), true ) );
$check( 'shared attachment remains usable by other ticket and post', (int) get_post_meta( $shared_ticket, HSW_Account_Support::IMAGE_META, true ) === $attachment && (int) get_post_thumbnail_id( $shared_post ) === $attachment && get_post( $attachment ) && file_exists( $original ) );
$check( 'only matching ticket notifications removed', get_user_meta( $user, '_hsw_notifications', true ) === array_slice( $notifications, 2 ) );
wp_delete_post( $shared_ticket, true );
wp_delete_post( $shared_post, true );
wp_delete_attachment( $attachment, true );
$check( 'explicit attachment deletion removes attachment, original and thumbnail', ! get_post( $attachment ) && ! file_exists( $original ) && ! file_exists( $thumb ) );
$other_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user ) );
$before = get_user_meta( $user, '_hsw_notifications', true );
wp_delete_post( $other_post, true );
$check( 'other post deletion does not alter notifications', get_user_meta( $user, '_hsw_notifications', true ) === $before );
$old_ticket = wp_insert_post( array( 'post_type' => HSW_Account_Support::POST_TYPE, 'post_status' => 'publish', 'post_author' => $user ) );
$retained_file = $folder . '/retention.jpg';
file_put_contents( $retained_file, 'qa-retention' );
$retained_attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $retained_file, $old_ticket );
update_post_meta( $old_ticket, HSW_Account_Support::IMAGE_META, $retained_attachment );
update_post_meta( $old_ticket, HSW_Account_Support::STATUS_META, 'closed' );
update_post_meta( $old_ticket, HSW_Account_Support::UPDATED_META, time() - 400 * DAY_IN_SECONDS );
update_user_meta( $user, '_hsw_notifications', array( array( 'type' => 'ticket', 'ticket_id' => $old_ticket ) ) );
$support->cleanup_old_tickets();
$check( 'retention cleanup also removes matching notifications', ! get_post( $old_ticket ) && array() === get_user_meta( $user, '_hsw_notifications', true ) );
$check( 'retention cleanup leaves attachment and file untouched', get_post( $retained_attachment ) && file_exists( $retained_file ) );
$check( 'retention cleanup queues image for review', get_post_meta( $retained_attachment, HSW_Account_Support::IMAGE_REVIEW_META, false ) === array( (string) $old_ticket ) );
wp_delete_attachment( $retained_attachment, true );
if ( is_dir( $folder ) ) { rmdir( $folder ); }
require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user );
exit( $failed ? 1 : 0 );
