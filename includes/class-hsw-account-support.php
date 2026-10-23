<?php
/**
 * HamiShop support tickets.
 *
 * Tickets are private WordPress posts owned by the customer, replies are
 * comments. Customers manage tickets from the account endpoint; staff manage
 * them inside wp-admin with the same look and the same security rules.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Account_Support {

	const POST_TYPE   = 'hsw_ticket';
	const STATUS_META = '_hsw_ticket_status';
	const UPDATED_META= '_hsw_ticket_updated';
	const UNREAD_ADMIN= '_hsw_ticket_unread_admin';
	const UNREAD_USER = '_hsw_ticket_unread_user';
	const PRIORITY    = '_hsw_ticket_priority';
	const DEPARTMENT  = '_hsw_ticket_department';

	/** Related WooCommerce order ID (0 when none). */
	const ORDER_META = '_hsw_ticket_order';

	/** Attachment ID of the screenshot the customer attached. */
	const IMAGE_META = '_hsw_ticket_image';

	/** @var HSW_Account */
	private $account;

	public function __construct( $account ) {
		$this->account = $account;

		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_frontend_actions' ) );
		add_action( 'woocommerce_account_support_endpoint', array( $this, 'render_endpoint' ) );

		/* Chat UI actions — logged-in customers only. */
		add_action( 'wp_ajax_hsw_ticket_create', array( $this, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_hsw_ticket_reply', array( $this, 'ajax_reply_ticket' ) );
		add_action( 'wp_ajax_hsw_ticket_view', array( $this, 'ajax_view_ticket' ) );
		add_action( 'wp_ajax_hsw_ticket_close', array( $this, 'ajax_close_ticket' ) );
		add_action( 'wp_ajax_hsw_orders_search', array( $this, 'ajax_orders_search' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_ticket' ), 10, 2 );
			add_action( 'admin_post_hsw_admin_ticket_reply', array( $this, 'admin_reply' ) );
			add_action( 'admin_post_hsw_admin_ticket_close', array( $this, 'admin_set_status_action' ) );
			add_action( 'admin_post_hsw_admin_ticket_reopen', array( $this, 'admin_set_status_action' ) );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'admin_status_filter' ) );
			add_action( 'pre_get_posts', array( $this, 'admin_filter_query' ) );
			add_filter( 'post_updated_messages', array( $this, 'admin_messages' ) );
			add_filter( 'enter_title_here', array( $this, 'admin_title_placeholder' ), 10, 2 );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'admin_footer', array( $this, 'render_ticket_action_forms' ) );
		}
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => 'تیکت‌های پشتیبانی',
					'singular_name'      => 'تیکت پشتیبانی',
					'menu_name'          => 'تیکت‌های پشتیبانی',
					'all_items'          => 'همه تیکت‌ها',
					'edit_item'          => 'مشاهده تیکت',
					'view_item'          => 'مشاهده تیکت',
					'search_items'       => 'جستجوی تیکت',
					'not_found'          => 'تیکتی یافت نشد',
					'not_found_in_trash' => 'تیکتی در زباله‌دان نیست',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'hamishop',
				'show_in_admin_bar'   => false,
				'menu_position'       => 56,
				'menu_icon'           => 'dashicons-format-chat',
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'hierarchical'        => false,
			)
		);
	}

	public function open_ticket_count( $user_id ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => absint( $user_id ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$count = 0;
		foreach ( $posts as $id ) {
			if ( in_array( $this->get_status( $id ), array( 'open', 'answered' ), true ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function support_url( $value = '' ) {
		$base = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'support' ) : home_url( '/' );
		$base = trailingslashit( $base );
		return '' === $value ? $base : $base . rawurlencode( (string) $value ) . '/';
	}

	private function ticket_url( $ticket_id ) {
		return $this->support_url( absint( $ticket_id ) );
	}

	private function new_ticket_url() {
		return $this->support_url( 'new' );
	}

	public function get_status( $ticket_id ) {
		$status = get_post_meta( $ticket_id, self::STATUS_META, true );
		return $status ? $status : 'open';
	}

	private function status_label( $status ) {
		$map = array(
			'open'     => 'در انتظار پاسخ',
			'answered' => 'پاسخ داده شده',
			'closed'   => 'بسته شده',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	private function status_tone( $status ) {
		$map = array(
			'open'     => 'warning',
			'answered' => 'success',
			'closed'   => 'muted',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : 'info';
	}

	private function priority_label( $priority ) {
		$priorities = $this->account->setting( 'support.priorities', array() );
		return isset( $priorities[ $priority ] ) ? $priorities[ $priority ] : 'معمولی';
	}

	/* ---------------------------------------------------------------------
	 * Front-end rendering
	 * ------------------------------------------------------------------ */

	public function render_content( $ctx ) {
		$user_id = isset( $ctx['user_id'] ) ? absint( $ctx['user_id'] ) : get_current_user_id();
		$value   = isset( $ctx['support_value'] ) ? (string) $ctx['support_value'] : '';
		if ( ! $user_id ) {
			return '';
		}
		return $this->content_for_user( $user_id, $value );
	}

	public function render_endpoint( $value = '' ) {
		if ( ! is_user_logged_in() || ! $this->account->endpoint_enabled( 'support' ) ) {
			return;
		}
		echo $this->account->notices_html();
		echo $this->content_for_user( get_current_user_id(), (string) $value );
	}

	private function content_for_user( $user_id, $value ) {
		$active   = 0;
		$open_new = false;

		if ( 'new' === $value ) {
			$open_new = true;
		} elseif ( '' !== $value ) {
			$active = absint( $value );
		}

		return $this->chat_shell( $user_id, $active, $open_new );
	}

	private function timestamp_from_local( $local ) {
		$local = (string) $local;
		if ( '' === $local ) {
			return 0;
		}
		$gmt = get_gmt_from_date( $local );
		return $gmt ? strtotime( $gmt . ' UTC' ) : 0;
	}

	private function format_date( $timestamp ) {
		return $this->account->format_date( $timestamp );
	}

	/* ---------------------------------------------------------------------
	 * Front-end actions
	 * ------------------------------------------------------------------ */

	public function handle_frontend_actions() {
		if ( empty( $_POST['hsw_ticket_action'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$action  = sanitize_key( wp_unslash( $_POST['hsw_ticket_action'] ) );
		$user_id = get_current_user_id();

		if ( 'create' === $action ) {
			$this->handle_create_ticket( $user_id );
		} elseif ( 'reply' === $action ) {
			$this->handle_reply_ticket( $user_id );
		} elseif ( 'close' === $action ) {
			$this->handle_close_ticket( $user_id );
		}
	}

	private function handle_create_ticket( $user_id ) {
		if ( ! isset( $_POST['hsw_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_ticket_nonce'] ) ), 'hsw_ticket_create' ) ) {
			$this->redirect_with_notice( $this->support_url(), 'error', 'درخواست نامعتبر است. دوباره تلاش کنید.' );
		}
		if ( ! empty( $_POST['hsw_website'] ) ) {
			$this->redirect_with_notice( $this->support_url(), 'error', 'ارسال تیکت ممکن نشد.' );
		}

		$lock = get_transient( 'hsw_ticket_lock_' . $user_id );
		if ( $lock ) {
			$this->redirect_with_notice( $this->new_ticket_url(), 'error', 'برای ارسال تیکت جدید کمی صبر کنید.' );
		}

		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$dept     = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '';
		$priority = isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : 'normal';
		$departments = (array) $this->account->setting( 'support.departments', array() );
		$priorities  = (array) $this->account->setting( 'support.priorities', array() );
		$max_message = max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) );

		if ( strlen( $subject ) < 3 || strlen( $subject ) > 120 ) {
			$this->redirect_with_notice( $this->new_ticket_url(), 'error', 'موضوع تیکت باید بین ۳ تا ۱۲۰ کاراکتر باشد.' );
		}
		if ( strlen( $message ) < 10 || strlen( $message ) > $max_message ) {
			$this->redirect_with_notice( $this->new_ticket_url(), 'error', 'متن تیکت کوتاه یا بیش از حد بلند است.' );
		}
		if ( ! in_array( $dept, $departments, true ) ) {
			$dept = $departments ? $departments[0] : 'پشتیبانی';
		}
		if ( ! isset( $priorities[ $priority ] ) ) {
			$priority = 'normal';
		}
		$max_open = max( 1, absint( $this->account->setting( 'support.max_open', 5 ) ) );
		if ( $this->open_ticket_count( $user_id ) >= $max_open ) {
			$this->redirect_with_notice( $this->support_url(), 'error', 'حد مجاز تیکت‌های باز تکمیل است.' );
		}

		$ticket_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => $subject,
				'post_content' => $message,
			),
			true
		);
		if ( is_wp_error( $ticket_id ) ) {
			$this->redirect_with_notice( $this->new_ticket_url(), 'error', 'ثبت تیکت ناموفق بود. دوباره تلاش کنید.' );
		}

		update_post_meta( $ticket_id, self::STATUS_META, 'open' );
		update_post_meta( $ticket_id, self::PRIORITY, $priority );
		update_post_meta( $ticket_id, self::DEPARTMENT, $dept );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		update_post_meta( $ticket_id, self::UNREAD_ADMIN, 1 );
		update_post_meta( $ticket_id, self::UNREAD_USER, 0 );

		set_transient( 'hsw_ticket_lock_' . $user_id, 1, 30 );
		$this->notify_admin( $ticket_id, false );

		$this->account->add_notification(
			$user_id,
			array(
				'type'  => 'ticket',
				'title' => 'تیکت شما ثبت شد',
				'text'  => sprintf( 'تیکت «%s» با موفقیت ثبت شد و در انتظار پاسخ تیم پشتیبانی است.', $subject ),
				'url'   => $this->ticket_url( $ticket_id ),
			)
		);

		$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'success', 'تیکت شما با موفقیت ثبت شد.' );
	}

	private function handle_reply_ticket( $user_id ) {
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$ticket    = $ticket_id ? get_post( $ticket_id ) : null;
		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id ) {
			$this->redirect_with_notice( $this->support_url(), 'error', 'تیکت مورد نظر پیدا نشد.' );
		}
		if ( ! isset( $_POST['hsw_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_ticket_nonce'] ) ), 'hsw_ticket_reply_' . $ticket_id ) ) {
			$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'error', 'درخواست نامعتبر است.' );
		}
		if ( 'closed' === $this->get_status( $ticket_id ) ) {
			$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'error', 'این تیکت بسته شده است.' );
		}

		$message     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$max_message = max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) );
		if ( strlen( $message ) < 2 || strlen( $message ) > $max_message ) {
			$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'error', 'متن پاسخ خالی یا بیش از حد بلند است.' );
		}

		$this->insert_reply( $ticket_id, $user_id, $message, false );
		update_post_meta( $ticket_id, self::STATUS_META, 'open' );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		update_post_meta( $ticket_id, self::UNREAD_ADMIN, 1 );
		$this->notify_admin( $ticket_id, true );

		$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'success', 'پاسخ شما ارسال شد.' );
	}

	private function handle_close_ticket( $user_id ) {
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$ticket    = $ticket_id ? get_post( $ticket_id ) : null;
		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id ) {
			$this->redirect_with_notice( $this->support_url(), 'error', 'تیکت مورد نظر پیدا نشد.' );
		}
		if ( ! isset( $_POST['hsw_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_ticket_nonce'] ) ), 'hsw_ticket_close_' . $ticket_id ) ) {
			$this->redirect_with_notice( $this->ticket_url( $ticket_id ), 'error', 'درخواست نامعتبر است.' );
		}

		update_post_meta( $ticket_id, self::STATUS_META, 'closed' );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		$this->redirect_with_notice( $this->support_url(), 'success', 'تیکت بسته شد.' );
	}

	private function insert_reply( $ticket_id, $user_id, $message, $is_staff ) {
		$user = get_user_by( 'id', $user_id );
		return (int) wp_insert_comment(
			array(
				'comment_post_ID'      => $ticket_id,
				'comment_author'       => $user ? $user->display_name : ( $is_staff ? 'پشتیبانی' : 'کاربر' ),
				'comment_author_email' => $user ? $user->user_email : '',
				'comment_content'      => $message,
				'comment_type'         => 'hsw_ticket_reply',
				'comment_approved'     => 1,
				'user_id'              => $user_id,
			)
		);
	}

	private function notify_admin( $ticket_id, $is_reply ) {
		if ( 'yes' !== $this->account->setting( 'support.email_notifications', 'yes' ) ) {
			return;
		}
		$to = (string) $this->account->setting( 'support.support_email', '' );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		$to = apply_filters( 'hsw_account_support_email', $to, $ticket_id );
		if ( ! is_email( $to ) ) {
			return;
		}

		$ticket = get_post( $ticket_id );
		$user   = $ticket ? get_user_by( 'id', $ticket->post_author ) : null;
		$subject = sprintf(
			'%s تیکت #T-%s: %s',
			$is_reply ? 'پاسخ جدید روی' : 'تیکت جدید',
			$ticket_id,
			$ticket ? $ticket->post_title : ''
		);
		$lines = array(
			'تیکت: #T-' . $ticket_id,
			'کاربر: ' . ( $user ? $user->display_name . ' (' . $user->user_email . ')' : 'نامشخص' ),
			'موضوع: ' . ( $ticket ? $ticket->post_title : '' ),
			'دپارتمان: ' . get_post_meta( $ticket_id, self::DEPARTMENT, true ),
			'اولویت: ' . $this->priority_label( get_post_meta( $ticket_id, self::PRIORITY, true ) ),
		);

		$order_id = absint( get_post_meta( $ticket_id, self::ORDER_META, true ) );
		if ( $order_id ) {
			$lines[] = 'سفارش مرتبط: #' . $order_id;
		}

		$image_id = absint( get_post_meta( $ticket_id, self::IMAGE_META, true ) );
		if ( $image_id ) {
			$lines[] = 'عکس پیوست: ' . (string) wp_get_attachment_url( $image_id );
		}

		$lines[] = '';
		$lines[] = $is_reply ? 'پاسخ کاربر:' : 'متن تیکت:';
		$lines[] = $is_reply ? '' : ( $ticket ? $ticket->post_content : '' );
		$lines[] = '';
		$lines[] = 'مشاهده در پیشخوان: ' . admin_url( 'post.php?post=' . $ticket_id . '&action=edit' );

		wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	private function redirect_with_notice( $url, $type, $message ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'success' === $type ? 'success' : 'error' );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Chat UI (v1.11.24)
	 *
	 * One screen instead of three pages: conversations on the side, the thread
	 * in the middle, and the new-ticket form in a modal behind the «+» button.
	 *
	 * Everything is server-rendered, so the section still works with JS off —
	 * the list items are real links and the modal is a real form. The script
	 * only adds instant switching, inline replies and client-side image
	 * compression.
	 * ------------------------------------------------------------------ */

	/**
	 * Two-pane support screen.
	 *
	 * @param int  $user_id   Customer.
	 * @param int  $active_id Ticket to open, 0 for the empty state.
	 * @param bool $open_new  Open the modal straight away.
	 * @return string
	 */
	private function chat_shell( $user_id, $active_id, $open_new = false ) {
		$max_open = max( 1, absint( $this->account->setting( 'support.max_open', 5 ) ) );
		$can_new  = $this->open_ticket_count( $user_id ) < $max_open;

		$html  = '<section class="hsw-ac__chat" data-hsw-chat data-active="' . esc_attr( $active_id ) . '">';
		$html .= $this->chat_list_html( $user_id, $can_new );
		$html .= '<div class="hsw-ac__chat-main" data-hsw-chat-main>';
		$html .= $active_id ? $this->chat_thread_html( $user_id, $active_id ) : $this->chat_empty_html();
		$html .= '</div>';
		$html .= $this->ticket_modal_html( $can_new, $open_new );
		$html .= '</section>';

		return $html;
	}

	/**
	 * Conversation list.
	 *
	 * @param int  $user_id Customer.
	 * @param bool $can_new Whether another open ticket is allowed.
	 * @return string
	 */
	private function chat_list_html( $user_id, $can_new ) {
		$tickets = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => 50,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$html  = '<aside class="hsw-ac__chat-side">';
		$html .= '<header class="hsw-ac__chat-sidehead"><div><h2>' . esc_html__( 'گفتگوها', 'hamishop-elementor-widgets' ) . '</h2><span>' . esc_html( sprintf( '%s گفتگو', HSW_Helpers::fa_num( count( $tickets ) ) ) ) . '</span></div>';
		if ( $can_new ) {
			$html .= '<button type="button" class="hsw-ac__chat-add" data-hsw-ticket-new aria-label="' . esc_attr__( 'تیکت جدید', 'hamishop-elementor-widgets' ) . '" title="' . esc_attr__( 'تیکت جدید', 'hamishop-elementor-widgets' ) . '">' . $this->account->icon( 'plus' ) . '</button>';
		}
		$html .= '</header>';

		$html .= '<div class="hsw-ac__chat-search"><span aria-hidden="true">' . $this->account->icon( 'search' ) . '</span><input type="search" data-hsw-ticket-search placeholder="' . esc_attr__( 'جستجوی گفتگو…', 'hamishop-elementor-widgets' ) . '" aria-label="' . esc_attr__( 'جستجوی گفتگو', 'hamishop-elementor-widgets' ) . '"></div>';

		/*
		 * The list element is always rendered, even when empty: the first ticket
		 * created through the modal is inserted into it by JS, and a missing
		 * <ul> would silently swallow that row.
		 */
		$html .= '<ul class="hsw-ac__chat-list" data-hsw-ticket-list>';
		if ( ! $tickets ) {
			$html .= '<li class="hsw-ac__chat-none" data-hsw-chat-placeholder><span aria-hidden="true">' . $this->account->icon( 'support' ) . '</span><p>' . esc_html__( 'هنوز گفتگویی ندارید.', 'hamishop-elementor-widgets' ) . '</p></li>';
		} else {
			foreach ( $tickets as $ticket ) {
				$html .= $this->chat_row_html( $ticket );
			}
		}
		$html .= '</ul>';

		$html .= '</aside>';
		return $html;
	}

	/**
	 * One row in the conversation list.
	 *
	 * @param WP_Post $ticket Ticket post.
	 * @return string
	 */
	private function chat_row_html( $ticket ) {
		$status  = $this->get_status( $ticket->ID );
		$unread  = (int) get_post_meta( $ticket->ID, self::UNREAD_USER, true );
		$updated = get_post_meta( $ticket->ID, self::UPDATED_META, true );
		$updated = $updated ? absint( $updated ) : $this->timestamp_from_local( $ticket->post_modified );
		$order   = absint( get_post_meta( $ticket->ID, self::ORDER_META, true ) );
		$excerpt = wp_trim_words( wp_strip_all_tags( (string) $ticket->post_content ), 9, '…' );

		$html  = '<li class="hsw-ac__chat-item' . ( $unread ? ' is-unread' : '' ) . '" data-id="' . esc_attr( $ticket->ID ) . '">';
		$html .= '<a class="hsw-ac__chat-link" href="' . esc_url( $this->ticket_url( $ticket->ID ) ) . '" data-hsw-ticket="' . esc_attr( $ticket->ID ) . '">';
		$html .= '<span class="hsw-ac__chat-rowtop"><span class="hsw-ac__chat-subj">' . esc_html( $ticket->post_title ) . '</span>';
		if ( $unread ) {
			$html .= '<span class="hsw-ac__chat-dot" aria-label="' . esc_attr__( 'پاسخ خوانده‌نشده', 'hamishop-elementor-widgets' ) . '"></span>';
		}
		$html .= '</span>';
		$html .= '<span class="hsw-ac__chat-excerpt">';
		if ( $order ) {
			$html .= '<b>' . esc_html( sprintf( 'سفارش #%s', HSW_Helpers::fa_num( $order ) ) ) . '</b> — ';
		}
		$html .= esc_html( $excerpt ) . '</span>';
		$html .= '<span class="hsw-ac__chat-rowmeta"><span class="hsw-ac__status-badge is-' . esc_attr( $this->status_tone( $status ) ) . '">' . esc_html( $this->status_label( $status ) ) . '</span><span class="hsw-ac__chat-date">' . esc_html( $this->format_date( $updated ) ) . '</span></span>';
		$html .= '</a></li>';

		return $html;
	}

	/**
	 * Placeholder shown in the thread pane before a conversation is picked.
	 *
	 * @param string $text Optional message.
	 * @return string
	 */
	private function chat_empty_html( $text = '' ) {
		$html  = '<div class="hsw-ac__chat-empty"><span class="hsw-ac__chat-emptyic" aria-hidden="true">' . $this->account->icon( 'support' ) . '</span>';
		$html .= '<h3>' . esc_html__( 'یک گفتگو انتخاب کنید', 'hamishop-elementor-widgets' ) . '</h3>';
		$html .= '<p>' . esc_html( '' !== $text ? $text : __( 'از فهرست کنار یک گفتگو را باز کنید یا گفتگوی تازه‌ای بسازید.', 'hamishop-elementor-widgets' ) ) . '</p>';
		$html .= '<button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-ticket-new>' . $this->account->icon( 'plus' ) . esc_html__( 'تیکت جدید', 'hamishop-elementor-widgets' ) . '</button></div>';
		return $html;
	}

	/**
	 * Thread pane for one ticket. Also returned by the AJAX switch action.
	 *
	 * @param int $user_id   Customer.
	 * @param int $ticket_id Ticket.
	 * @return string
	 */
	private function chat_thread_html( $user_id, $ticket_id ) {
		$ticket = get_post( $ticket_id );

		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id || 'publish' !== $ticket->post_status ) {
			return $this->chat_empty_html( __( 'این گفتگو وجود ندارد یا به حساب شما تعلق ندارد.', 'hamishop-elementor-widgets' ) );
		}

		update_post_meta( $ticket_id, self::UNREAD_USER, 0 );

		$status = $this->get_status( $ticket_id );
		$closed = 'closed' === $status;
		$order  = absint( get_post_meta( $ticket_id, self::ORDER_META, true ) );

		$html  = '<div class="hsw-ac__thread" data-hsw-thread="' . esc_attr( $ticket_id ) . '">';
		$html .= '<header class="hsw-ac__thread-head"><div class="hsw-ac__thread-title"><h2>' . esc_html( $ticket->post_title ) . '</h2>';
		$html .= '<div class="hsw-ac__thread-meta"><span class="hsw-ac__status-badge is-' . esc_attr( $this->status_tone( $status ) ) . '">' . esc_html( $this->status_label( $status ) ) . '</span><span class="hsw-ac__ticket-id">#T-' . esc_html( HSW_Helpers::fa_num( $ticket_id ) ) . '</span>';
		if ( $order ) {
			$html .= '<a class="hsw-ac__thread-order" href="' . esc_url( wc_get_endpoint_url( 'view-order', $order, wc_get_page_permalink( 'myaccount' ) ) ) . '">' . $this->account->icon( 'receipt' ) . esc_html( sprintf( 'سفارش #%s', HSW_Helpers::fa_num( $order ) ) ) . '</a>';
		}
		$html .= '</div></div>';
		if ( ! $closed ) {
			$html .= '<button type="button" class="hsw-ac__thread-close" data-hsw-ticket-close="' . esc_attr( $ticket_id ) . '">' . $this->account->icon( 'check' ) . '<span>' . esc_html__( 'بستن', 'hamishop-elementor-widgets' ) . '</span></button>';
		}
		$html .= '</header>';

		$html .= '<div class="hsw-ac__thread-scroll" data-hsw-thread-scroll>';
		$html .= $this->bubble_html( $user_id, $ticket->post_content, $this->timestamp_from_local( $ticket->post_date ), false, absint( get_post_meta( $ticket_id, self::IMAGE_META, true ) ) );

		$comments = get_comments(
			array(
				'post_id' => $ticket_id,
				'type'    => 'hsw_ticket_reply',
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
		foreach ( $comments as $comment ) {
			$author   = get_user_by( 'id', (int) $comment->user_id );
			$is_staff = $author && ( user_can( $author, 'manage_woocommerce' ) || user_can( $author, 'manage_options' ) );
			$html    .= $this->bubble_html(
				(int) $comment->user_id,
				$comment->comment_content,
				$this->timestamp_from_local( $comment->comment_date ),
				$is_staff,
				absint( get_comment_meta( $comment->comment_ID, self::IMAGE_META, true ) )
			);
		}
		$html .= '</div>';

		if ( $closed ) {
			$html .= '<div class="hsw-ac__thread-closed">' . $this->account->icon( 'lock' ) . '<p>' . esc_html__( 'این گفتگو بسته شده است. برای موضوع جدید یک گفتگوی تازه بسازید.', 'hamishop-elementor-widgets' ) . '</p><button type="button" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-ticket-new>' . esc_html__( 'گفتگوی جدید', 'hamishop-elementor-widgets' ) . '</button></div>';
		} else {
			$html .= '<form class="hsw-ac__composer" data-hsw-reply-form data-ticket="' . esc_attr( $ticket_id ) . '">';
			$html .= '<textarea name="message" rows="1" maxlength="' . esc_attr( max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) ) ) . '" placeholder="' . esc_attr__( 'پیامت رو بنویس…', 'hamishop-elementor-widgets' ) . '" required></textarea>';
			$html .= '<button type="submit" class="hsw-ac__composer-send" aria-label="' . esc_attr__( 'ارسال', 'hamishop-elementor-widgets' ) . '">' . $this->account->icon( 'arrow-left' ) . '</button>';
			$html .= '</form>';
			$html .= '<p class="hsw-ac__composer-msg" data-hsw-reply-msg role="status" aria-live="polite"></p>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * One message bubble.
	 *
	 * @param int    $author_id  Author.
	 * @param string $content    Text.
	 * @param int    $timestamp  Unix time.
	 * @param bool   $is_staff   Staff message.
	 * @param int    $image_id   Attachment ID, 0 for none.
	 * @return string
	 */
	private function bubble_html( $author_id, $content, $timestamp, $is_staff, $image_id = 0 ) {
		$author  = get_user_by( 'id', $author_id );
		$name    = $author ? $author->display_name : ( $is_staff ? 'پشتیبانی' : 'کاربر' );
		$staff   = class_exists( 'HSW_Branding' ) ? sprintf( 'پشتیبانی %s', HSW_Branding::name() ) : $name;
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );

		$image = '';
		if ( $image_id ) {
			$image = '<a class="hsw-ac__bubble-img" href="' . esc_url( (string) wp_get_attachment_url( $image_id ) ) . '" target="_blank" rel="noopener">'
				. wp_get_attachment_image( $image_id, 'medium', false, array( 'loading' => 'lazy', 'alt' => '' ) )
				. '</a>';
		}

		return '<article class="hsw-ac__bubble' . ( $is_staff ? ' is-staff' : ' is-user' ) . '">'
			. '<div class="hsw-ac__bubble-avatar" aria-hidden="true">' . ( $is_staff ? $this->account->icon( 'support' ) : esc_html( $initial ) ) . '</div>'
			. '<div class="hsw-ac__bubble-body"><div class="hsw-ac__bubble-head"><strong>' . esc_html( $is_staff ? $staff : $name ) . '</strong><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">' . esc_html( $this->format_date( $timestamp ) ) . '</time></div>'
			. '<div class="hsw-ac__bubble-text">' . nl2br( esc_html( $content ) ) . $image . '</div></div>'
			. '</article>';
	}

	/**
	 * New-ticket modal.
	 *
	 * @param bool $can_new Whether the customer may open another ticket.
	 * @param bool $open    Render it open (used by the no-JS ?new=1 link).
	 * @return string
	 */
	private function ticket_modal_html( $can_new, $open = false ) {
		$cap     = $this->captcha_new();
		$subject = isset( $_GET['subject'] ) ? sanitize_text_field( wp_unslash( $_GET['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max     = max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) );

		$html  = '<div class="hsw-ac__modal" data-hsw-ticket-modal' . ( $open && $can_new ? ' data-open="1"' : '' ) . ' hidden>';
		$html .= '<div class="hsw-ac__modal-backdrop" data-hsw-modal-close></div>';
		$html .= '<div class="hsw-ac__modal-card" role="dialog" aria-modal="true" aria-labelledby="hsw-ac-modal-title">';

		$html .= '<header class="hsw-ac__modal-head"><div><h2 id="hsw-ac-modal-title">' . esc_html__( 'یه تیکت جدید بزن', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'مشکل یا سؤالتو بنویس، سریع بهت جواب می‌دیم.', 'hamishop-elementor-widgets' ) . '</p></div>';
		$html .= '<button type="button" class="hsw-ac__modal-x" data-hsw-modal-close aria-label="' . esc_attr__( 'بستن', 'hamishop-elementor-widgets' ) . '">' . $this->account->icon( 'x' ) . '</button></header>';

		$html .= '<form class="hsw-ac__modal-body" data-hsw-ticket-form novalidate>';

		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-t-subject">' . esc_html__( 'موضوع تیکت', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label>';
		$html .= '<input type="text" id="hsw-ac-t-subject" name="subject" maxlength="120" required value="' . esc_attr( $subject ) . '" placeholder="' . esc_attr__( 'موضوع تیکتو بنویس…', 'hamishop-elementor-widgets' ) . '"></div>';

		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-t-order">' . esc_html__( 'سفارش / سرویس مرتبط', 'hamishop-elementor-widgets' ) . ' <span class="hsw-ac__optional">(' . esc_html__( 'اختیاری', 'hamishop-elementor-widgets' ) . ')</span></label>';
		$html .= '<div class="hsw-ac__picker" data-hsw-order-picker>';
		$html .= '<input type="text" id="hsw-ac-t-order" data-hsw-order-input autocomplete="off" placeholder="' . esc_attr__( 'جستجوی سفارش…', 'hamishop-elementor-widgets' ) . '">';
		$html .= '<input type="hidden" name="order_id" value="0" data-hsw-order-value>';
		$html .= '<div class="hsw-ac__picker-list" data-hsw-order-list hidden></div>';
		$html .= '<p class="hsw-ac__picker-hint">' . esc_html__( 'شماره سفارش را بنویس یا از فهرست انتخاب کن.', 'hamishop-elementor-widgets' ) . '</p>';
		$html .= '</div></div>';

		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-t-message">' . esc_html__( 'متن پیام', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label>';
		$html .= '<textarea id="hsw-ac-t-message" name="message" rows="5" maxlength="' . esc_attr( $max ) . '" required placeholder="' . esc_attr__( 'پیامتو بنویس…', 'hamishop-elementor-widgets' ) . '"></textarea></div>';

		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-t-image">' . esc_html__( 'عکس', 'hamishop-elementor-widgets' ) . ' <span class="hsw-ac__optional">(' . esc_html__( 'اختیاری', 'hamishop-elementor-widgets' ) . ')</span></label>';
		$html .= '<label class="hsw-ac__upload" data-hsw-upload><input type="file" id="hsw-ac-t-image" name="image" accept="image/*" data-hsw-image-input hidden>';
		$html .= '<span class="hsw-ac__upload-ic" aria-hidden="true">' . $this->account->icon( 'image' ) . '</span>';
		$html .= '<span class="hsw-ac__upload-copy"><strong>' . esc_html__( 'یه عکس اضافه کن', 'hamishop-elementor-widgets' ) . '</strong><small>' . esc_html__( 'خودکار فشرده می‌شه', 'hamishop-elementor-widgets' ) . '</small></span>';
		$html .= '<span class="hsw-ac__upload-preview" data-hsw-image-preview></span></label></div>';

		$html .= '<div class="hsw-ac-field hsw-ac__captcha"><label for="hsw-ac-t-captcha">' . esc_html__( 'یه سوال کوچیک', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label>';
		$html .= '<div class="hsw-ac__captcha-row"><span class="hsw-ac__captcha-q" data-hsw-captcha-q>' . esc_html( HSW_Helpers::fa_num( $cap['a'] ) . ' + ' . HSW_Helpers::fa_num( $cap['b'] ) . ' = ?' ) . '</span>';
		$html .= '<input type="text" id="hsw-ac-t-captcha" name="captcha" inputmode="numeric" pattern="[0-9۰-۹]*" autocomplete="off" required placeholder="' . esc_attr__( 'جواب رو بنویس…', 'hamishop-elementor-widgets' ) . '">';
		$html .= '<input type="hidden" name="captcha_token" value="' . esc_attr( $cap['token'] ) . '" data-hsw-captcha-token></div></div>';

		$html .= '<p class="hsw-ac__modal-msg" data-hsw-ticket-msg role="status" aria-live="polite"></p>';

		$html .= '<footer class="hsw-ac__modal-foot"><button type="button" class="hsw-ac__btn hsw-ac__btn--ghost" data-hsw-modal-close>' . esc_html__( 'بی‌خیال', 'hamishop-elementor-widgets' ) . '</button>';
		$html .= '<button type="submit" class="hsw-ac__btn hsw-ac__btn--primary" data-hsw-ticket-submit>' . esc_html__( 'ثبت تیکت', 'hamishop-elementor-widgets' ) . '</button></footer>';

		$html .= '</form></div></div>';

		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Captcha
	 * ------------------------------------------------------------------ */

	/**
	 * A fresh two-number challenge. The answer lives in a transient keyed by the
	 * token, so the form only carries the token — never the result.
	 *
	 * @return array{token:string,a:int,b:int}
	 */
	private function captcha_new() {
		$a     = wp_rand( 1, 9 );
		$b     = wp_rand( 1, 9 );
		$token = wp_generate_password( 20, false, false );

		set_transient( 'hsw_cap_' . $token, $a + $b, 30 * MINUTE_IN_SECONDS );

		return array(
			'token' => $token,
			'a'     => $a,
			'b'     => $b,
		);
	}

	/**
	 * Check and burn a challenge.
	 *
	 * @param string $token  Challenge token.
	 * @param string $answer Raw answer (Persian or Latin digits).
	 * @return bool
	 */
	private function captcha_check( $token, $answer ) {
		$token = sanitize_text_field( (string) $token );
		if ( '' === $token ) {
			return false;
		}

		$expected = get_transient( 'hsw_cap_' . $token );
		if ( false === $expected ) {
			return false;
		}
		delete_transient( 'hsw_cap_' . $token );

		$answer = HSW_Helpers::en_num( (string) $answer );

		return '' !== $answer && (int) $expected === (int) $answer;
	}

	/* ---------------------------------------------------------------------
	 * Chat UI — AJAX
	 * ------------------------------------------------------------------ */

	/**
	 * Shared guard for every chat action: nonce + login.
	 *
	 * @return int Current user ID (exits on failure).
	 */
	private function ajax_user() {
		check_ajax_referer( 'hsw_ticket_ajax', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا وارد حساب خود شوید.', 'hamishop-elementor-widgets' ) ), 401 );
		}
		return get_current_user_id();
	}

	/**
	 * Character count that behaves for Persian (bytes would count each letter 2×).
	 *
	 * @param string $value Text.
	 * @return int
	 */
	private function len( $value ) {
		$value = (string) $value;
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : (int) strlen( $value );
	}

	/**
	 * Create a ticket from the modal.
	 */
	public function ajax_create_ticket() {
		$user_id = $this->ajax_user();

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$order   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$token   = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';
		$answer  = isset( $_POST['captcha'] ) ? wp_unslash( $_POST['captcha'] ) : '';

		$max_message = max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) );

		/* Every failure ships a fresh challenge so the customer can retry. */
		$fail = function ( $text, $status = 400 ) {
			wp_send_json_error(
				array(
					'message' => $text,
					'captcha' => $this->captcha_new(),
				),
				$status
			);
		};

		if ( ! $this->captcha_check( $token, $answer ) ) {
			$fail( __( 'جواب سؤال امنیتی درست نیست. دوباره حساب کن.', 'hamishop-elementor-widgets' ) );
		}
		if ( $this->len( $subject ) < 3 || $this->len( $subject ) > 120 ) {
			$fail( __( 'موضوع باید بین ۳ تا ۱۲۰ کاراکتر باشد.', 'hamishop-elementor-widgets' ) );
		}
		if ( $this->len( $message ) < 10 || $this->len( $message ) > $max_message ) {
			$fail( __( 'متن پیام کوتاه یا بیش از حد بلند است.', 'hamishop-elementor-widgets' ) );
		}
		if ( $order && ! $this->user_owns_order( $user_id, $order ) ) {
			$fail( __( 'این سفارش به حساب شما تعلق ندارد.', 'hamishop-elementor-widgets' ) );
		}

		$max_open = max( 1, absint( $this->account->setting( 'support.max_open', 5 ) ) );
		if ( $this->open_ticket_count( $user_id ) >= $max_open ) {
			$fail( __( 'حد مجاز گفتگوهای باز تکمیل است. یکی را ببندید و دوباره تلاش کنید.', 'hamishop-elementor-widgets' ), 409 );
		}

		$departments = (array) $this->account->setting( 'support.departments', array() );
		$priorities  = (array) $this->account->setting( 'support.priorities', array() );
		$dept        = $departments ? (string) reset( $departments ) : 'پشتیبانی';
		$priority    = (string) $this->account->setting( 'support.priority_default', 'normal' );
		if ( ! isset( $priorities[ $priority ] ) ) {
			$priority = 'normal';
		}

		$ticket_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => $subject,
				'post_content' => $message,
			),
			true
		);

		if ( is_wp_error( $ticket_id ) ) {
			$fail( __( 'ثبت تیکت ناموفق بود. دوباره تلاش کنید.', 'hamishop-elementor-widgets' ), 500 );
		}

		update_post_meta( $ticket_id, self::STATUS_META, 'open' );
		update_post_meta( $ticket_id, self::PRIORITY, $priority );
		update_post_meta( $ticket_id, self::DEPARTMENT, $dept );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		update_post_meta( $ticket_id, self::UNREAD_ADMIN, 1 );
		update_post_meta( $ticket_id, self::UNREAD_USER, 0 );

		if ( $order ) {
			update_post_meta( $ticket_id, self::ORDER_META, $order );
		}

		$image = $this->handle_image_upload( $ticket_id );
		if ( $image ) {
			update_post_meta( $ticket_id, self::IMAGE_META, $image );
		}

		$this->notify_admin( $ticket_id, false );

		$this->account->add_notification(
			$user_id,
			array(
				'type'  => 'ticket',
				'title' => __( 'تیکت شما ثبت شد', 'hamishop-elementor-widgets' ),
				'text'  => sprintf( 'گفتگوی «%s» ثبت شد و در انتظار پاسخ تیم پشتیبانی است.', $subject ),
				'url'   => $this->ticket_url( $ticket_id ),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'تیکت شما ثبت شد.', 'hamishop-elementor-widgets' ),
				'ticket'  => (int) $ticket_id,
				'html'    => $this->chat_thread_html( $user_id, $ticket_id ),
				'row'     => $this->chat_row_html( get_post( $ticket_id ) ),
			)
		);
	}

	/**
	 * Post a reply into an open thread.
	 */
	public function ajax_reply_ticket() {
		$user_id   = $this->ajax_user();
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$ticket    = $ticket_id ? get_post( $ticket_id ) : null;

		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id || 'publish' !== $ticket->post_status ) {
			wp_send_json_error( array( 'message' => __( 'گفتگو پیدا نشد.', 'hamishop-elementor-widgets' ) ), 404 );
		}
		if ( 'closed' === $this->get_status( $ticket_id ) ) {
			wp_send_json_error( array( 'message' => __( 'این گفتگو بسته شده است.', 'hamishop-elementor-widgets' ) ), 409 );
		}

		$message     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$max_message = max( 200, absint( $this->account->setting( 'support.max_message', 3000 ) ) );

		if ( $this->len( $message ) < 2 || $this->len( $message ) > $max_message ) {
			wp_send_json_error( array( 'message' => __( 'متن پاسخ خالی یا بیش از حد بلند است.', 'hamishop-elementor-widgets' ) ), 400 );
		}

		$comment_id = $this->insert_reply( $ticket_id, $user_id, $message, false );

		update_post_meta( $ticket_id, self::STATUS_META, 'open' );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		update_post_meta( $ticket_id, self::UNREAD_ADMIN, 1 );
		update_post_meta( $ticket_id, self::UNREAD_USER, 0 );

		$this->notify_admin( $ticket_id, true );

		wp_send_json_success(
			array(
				'message' => __( 'پاسخ شما ارسال شد.', 'hamishop-elementor-widgets' ),
				'html'    => $this->bubble_html( $user_id, $message, time(), false, 0 ),
				'row'     => $this->chat_row_html( get_post( $ticket_id ) ),
				'comment' => (int) $comment_id,
			)
		);
	}

	/**
	 * Return one thread — used when switching conversations without a reload.
	 */
	public function ajax_view_ticket() {
		$user_id   = $this->ajax_user();
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

		wp_send_json_success(
			array(
				'html'   => $this->chat_thread_html( $user_id, $ticket_id ),
				'row'    => $this->chat_row_html( get_post( $ticket_id ) ),
				'ticket' => (int) $ticket_id,
			)
		);
	}

	/**
	 * Close a conversation from the thread header.
	 */
	public function ajax_close_ticket() {
		$user_id   = $this->ajax_user();
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$ticket    = $ticket_id ? get_post( $ticket_id ) : null;

		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id ) {
			wp_send_json_error( array( 'message' => __( 'گفتگو پیدا نشد.', 'hamishop-elementor-widgets' ) ), 404 );
		}

		update_post_meta( $ticket_id, self::STATUS_META, 'closed' );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );

		wp_send_json_success(
			array(
				'message' => __( 'گفتگو بسته شد.', 'hamishop-elementor-widgets' ),
				'html'    => $this->chat_thread_html( $user_id, $ticket_id ),
				'row'     => $this->chat_row_html( get_post( $ticket_id ) ),
			)
		);
	}

	/**
	 * Order suggestions for the picker — the customer's own orders only.
	 */
	public function ajax_orders_search() {
		$user_id = $this->ajax_user();

		if ( ! function_exists( 'wc_get_orders' ) ) {
			wp_send_json_success( array( 'orders' => array() ) );
		}

		$term   = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$digits = HSW_Helpers::en_num( $term );
		$found  = array();

		/* A number is almost always an order id — try that first. */
		if ( '' !== $digits ) {
			$order = wc_get_order( absint( $digits ) );
			if ( $order && (int) $order->get_customer_id() === (int) $user_id ) {
				$found[] = $order;
			}
		}

		if ( ! $found ) {
			$found = wc_get_orders(
				array(
					'customer' => $user_id,
					'limit'    => 8,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'type'     => 'shop_order',
				)
			);
		}

		$out = array();
		foreach ( $found as $order ) {
			$out[] = array(
				'id'    => (int) $order->get_id(),
				'label' => $this->order_label( $order ),
			);
		}

		wp_send_json_success( array( 'orders' => $out ) );
	}

	/* ---------------------------------------------------------------------
	 * Chat UI — helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Human label for the order picker: «#۶۶۵۹ - ۳۷۵,۸۸۸ تومان».
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function order_label( $order ) {
		$price = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ), ENT_QUOTES, 'UTF-8' );

		return sprintf( '#%s - %s', HSW_Helpers::fa_num( $order->get_id() ), HSW_Helpers::fa_num( $price ) );
	}

	/**
	 * @param int $user_id  Customer.
	 * @param int $order_id Order.
	 * @return bool
	 */
	private function user_owns_order( $user_id, $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$order = wc_get_order( $order_id );

		return $order && (int) $order->get_customer_id() === (int) $user_id;
	}

	/**
	 * Store the screenshot the customer attached to the modal.
	 *
	 * The browser already downscaled it, so this only guards against a wrong
	 * type or a runaway size. Returns 0 when there is nothing usable.
	 *
	 * @param int $ticket_id Parent ticket.
	 * @return int Attachment ID.
	 */
	private function handle_image_upload( $ticket_id ) {
		if ( empty( $_FILES['image'] ) || ! isset( $_FILES['image']['tmp_name'] ) ) {
			return 0;
		}
		if ( UPLOAD_ERR_OK !== (int) $_FILES['image']['error'] ) {
			return 0;
		}
		if ( (int) $_FILES['image']['size'] > 4 * MB_IN_BYTES ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		/*
		 * `wp_check_filetype_and_ext()` takes an `ext => mime` map (the same shape
		 * as `get_allowed_mime_types()`), not `mime => ext` — passing it the other
		 * way round makes every upload look invalid.
		 */
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
		$check = wp_check_filetype_and_ext( $_FILES['image']['tmp_name'], $_FILES['image']['name'], $mimes ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by WordPress.

		if ( empty( $check['type'] ) || ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return 0;
		}

		$attachment_id = media_handle_upload( 'image', $ticket_id, array(), array( 'test_form' => false ) );

		return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
	}

	/**
	 * Tell the customer a staff member answered.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param string $message   Staff reply text.
	 */
	private function notify_customer( $ticket_id, $message ) {
		if ( 'yes' !== $this->account->setting( 'support.email_notifications', 'yes' ) ) {
			return;
		}

		$ticket = get_post( $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$user = get_user_by( 'id', (int) $ticket->post_author );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$brand = class_exists( 'HSW_Branding' ) ? HSW_Branding::name() : get_bloginfo( 'name' );
		$link  = $this->ticket_url( $ticket_id );

		$subject = sprintf(
			/* translators: 1: store name, 2: ticket subject. */
			__( '%1$s — پاسخ جدید در گفتگوی «%2$s»', 'hamishop-elementor-widgets' ),
			$brand,
			$ticket->post_title
		);

		$body  = sprintf(
			/* translators: %s: customer name. */
			__( 'سلام %s،', 'hamishop-elementor-widgets' ),
			$user->display_name
		) . "\n\n";
		$body .= sprintf(
			/* translators: %s: ticket subject. */
			__( 'تیم پشتیبانی به گفتگوی «%s» پاسخ داد:', 'hamishop-elementor-widgets' ),
			$ticket->post_title
		) . "\n\n";
		$body .= $message . "\n\n";
		$body .= __( 'برای دیدن و ادامهٔ گفتگو:', 'hamishop-elementor-widgets' ) . "\n" . $link . "\n";

		wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin management
	 * ------------------------------------------------------------------ */

	public function add_meta_boxes() {
		add_meta_box( 'hsw_ticket_details', 'اطلاعات تیکت', array( $this, 'render_details_box' ), self::POST_TYPE, 'side', 'high' );
		add_meta_box( 'hsw_ticket_thread', 'گفتگوی تیکت', array( $this, 'render_thread_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render ticket action forms outside the main post form.
	 *
	 * Meta boxes live inside WordPress' #post form, so nested forms are invalid
	 * and can hijack the normal "Save" action. The visible controls reference
	 * these footer forms through their form="" attributes instead.
	 */
	public function render_ticket_action_forms() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$ticket_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $ticket_id || self::POST_TYPE !== get_post_type( $ticket_id ) ) {
			return;
		}
		$status = $this->get_status( $ticket_id );
		?>
		<form id="hsw-ticket-status-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( 'closed' === $status ? 'hsw_admin_ticket_reopen' : 'hsw_admin_ticket_close' ); ?>">
			<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket_id ); ?>">
			<?php wp_nonce_field( 'hsw_admin_ticket_status_' . $ticket_id, 'hsw_ticket_status_nonce' ); ?>
		</form>
		<form id="hsw-ticket-reply-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hsw_admin_ticket_reply">
			<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket_id ); ?>">
			<?php wp_nonce_field( 'hsw_admin_ticket_reply_' . $ticket_id, 'hsw_ticket_reply_nonce' ); ?>
		</form>
		<?php
	}


	public function render_details_box( $post ) {
		$user_id = (int) $post->post_author;
		$user    = get_user_by( 'id', $user_id );
		$status  = $this->get_status( $post->ID );
		$priority= get_post_meta( $post->ID, self::PRIORITY, true );
		$dept    = get_post_meta( $post->ID, self::DEPARTMENT, true );
		$updated = get_post_meta( $post->ID, self::UPDATED_META, true );
		$unread  = (int) get_post_meta( $post->ID, self::UNREAD_ADMIN, true );
		wp_nonce_field( 'hsw_ticket_meta_' . $post->ID, 'hsw_ticket_meta_nonce' );
		?>
		<div class="hsw-ticket-meta">
			<?php if ( $unread ) : ?><p class="hsw-ticket-unread">پاسخ جدید خوانده‌نشده</p><?php endif; ?>
			<p><strong>کاربر:</strong> <?php echo $user ? '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user->display_name ) . '</a>' : 'نامشخص'; ?><br>
			<strong>ایمیل:</strong> <span dir="ltr"><?php echo $user ? esc_html( $user->user_email ) : '—'; ?></span></p>
			<p><label for="hsw-ticket-status"><strong>وضعیت:</strong></label><br>
			<select name="hsw_ticket_status" id="hsw-ticket-status">
				<option value="open" <?php selected( $status, 'open' ); ?>>در انتظار پاسخ</option>
				<option value="answered" <?php selected( $status, 'answered' ); ?>>پاسخ داده شده</option>
				<option value="closed" <?php selected( $status, 'closed' ); ?>>بسته شده</option>
			</select></p>
			<p><strong>اولویت:</strong> <?php echo esc_html( $this->priority_label( $priority ) ); ?><br>
			<strong>دپارتمان:</strong> <?php echo esc_html( $dept ? $dept : '—' ); ?><br>
			<strong>ایجاد:</strong> <?php echo esc_html( $this->format_date( $this->timestamp_from_local( $post->post_date ) ) ); ?><br>
			<strong>آخرین به‌روزرسانی:</strong> <?php echo esc_html( $updated ? $this->format_date( $updated ) : '—' ); ?></p>
			<div class="hsw-ticket-admin-actions">
				<?php if ( 'closed' !== $status ) : ?>
					<button type="submit" form="hsw-ticket-status-form" class="button">بستن تیکت</button>
				<?php else : ?>
					<button type="submit" form="hsw-ticket-status-form" class="button">بازگشایی تیکت</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_thread_box( $post ) {
		$comments = get_comments(
			array(
				'post_id' => $post->ID,
				'type'    => 'hsw_ticket_reply',
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
		?>
		<div class="hsw-ticket-thread">
			<div class="hsw-ticket-msg is-customer">
				<div class="hsw-ticket-msg-head"><strong><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></strong><span><?php echo esc_html( $this->format_date( $this->timestamp_from_local( $post->post_date ) ) ); ?></span></div>
				<div class="hsw-ticket-msg-text"><?php echo nl2br( esc_html( $post->post_content ) ); ?></div>
			</div>
			<?php foreach ( $comments as $comment ) : ?>
				<?php $is_staff = user_can( (int) $comment->user_id, 'edit_posts' ); ?>
				<div class="hsw-ticket-msg <?php echo $is_staff ? 'is-staff' : 'is-customer'; ?>">
					<div class="hsw-ticket-msg-head"><strong><?php echo esc_html( $comment->comment_author ); ?></strong><span><?php echo esc_html( $this->format_date( $this->timestamp_from_local( $comment->comment_date ) ) ); ?></span></div>
					<div class="hsw-ticket-msg-text"><?php echo nl2br( esc_html( $comment->comment_content ) ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="hsw-ticket-reply">
			<textarea form="hsw-ticket-reply-form" name="message" rows="5" class="widefat" required placeholder="پاسخ خود را بنویسید…"></textarea>
			<p><button type="submit" form="hsw-ticket-reply-form" class="button button-primary">ارسال پاسخ به کاربر</button></p>
		</div>
		<style>
			.hsw-ticket-thread{max-height:420px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;border-radius:6px}
			.hsw-ticket-msg{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 12px;margin:0 0 10px}
			.hsw-ticket-msg.is-staff{border-color:#2271b1;background:#f0f6fc}
			.hsw-ticket-msg-head{display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;font-size:12px;color:#646970}
			.hsw-ticket-msg-text{white-space:normal;line-height:1.8}
			.hsw-ticket-reply{margin-top:12px}
			.hsw-ticket-reply textarea{margin-bottom:8px}
			.hsw-ticket-unread{color:#b32d2e;font-weight:700}
			.hsw-ticket-admin-actions form{display:inline-block}
		</style>
		<?php
	}

	public function save_ticket( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['hsw_ticket_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hsw_ticket_meta_nonce'] ) ), 'hsw_ticket_meta_' . $post_id ) ) {
			return;
		}
		if ( isset( $_POST['hsw_ticket_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['hsw_ticket_status'] ) );
			if ( in_array( $status, array( 'open', 'answered', 'closed' ), true ) ) {
				update_post_meta( $post_id, self::STATUS_META, $status );
				update_post_meta( $post_id, self::UPDATED_META, time() );
			}
		}
	}

	public function admin_reply() {
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$nonce     = isset( $_POST['hsw_ticket_reply_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hsw_ticket_reply_nonce'] ) ) : '';
		if ( ! $ticket_id || ! current_user_can( 'edit_post', $ticket_id ) || ! wp_verify_nonce( $nonce, 'hsw_admin_ticket_reply_' . $ticket_id ) ) {
			wp_die( 'درخواست نامعتبر است.' );
		}
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === trim( $message ) ) {
			wp_safe_redirect( get_edit_post_link( $ticket_id, 'url' ) );
			exit;
		}

		$this->insert_reply( $ticket_id, get_current_user_id(), $message, true );
		update_post_meta( $ticket_id, self::STATUS_META, 'answered' );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		update_post_meta( $ticket_id, self::UNREAD_ADMIN, 0 );
		update_post_meta( $ticket_id, self::UNREAD_USER, 1 );

		/* The customer has no way of knowing otherwise — mail them. */
		$this->notify_customer( $ticket_id, $message );

		$ticket = get_post( $ticket_id );
		if ( $ticket && 'yes' === $this->account->setting( 'notifications.ticket_reply', 'yes' ) ) {
			$this->account->add_notification(
				(int) $ticket->post_author,
				array(
					'type'  => 'ticket',
					'title' => 'پاسخ جدید در تیکت شما',
					'text'  => sprintf( 'تیم پشتیبانی به تیکت «%s» پاسخ داد.', $ticket->post_title ),
					'url'   => $this->ticket_url( $ticket_id ),
				)
			);
		}

		$redirect = add_query_arg( 'hsw_replied', '1', get_edit_post_link( $ticket_id, 'url' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public function admin_set_status_action() {
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
		$nonce     = isset( $_POST['hsw_ticket_status_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hsw_ticket_status_nonce'] ) ) : '';
		$action    = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( ! $ticket_id || ! current_user_can( 'edit_post', $ticket_id ) || ! wp_verify_nonce( $nonce, 'hsw_admin_ticket_status_' . $ticket_id ) ) {
			wp_die( 'درخواست نامعتبر است.' );
		}
		$status = 'hsw_admin_ticket_close' === $action ? 'closed' : 'open';
		update_post_meta( $ticket_id, self::STATUS_META, $status );
		update_post_meta( $ticket_id, self::UPDATED_META, time() );
		wp_safe_redirect( add_query_arg( 'hsw_status', $status, get_edit_post_link( $ticket_id, 'url' ) ) );
		exit;
	}

	public function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new[ $key ] = 'موضوع تیکت';
				$new['hsw_customer'] = 'کاربر';
				$new['hsw_status']   = 'وضعیت';
				$new['hsw_priority'] = 'اولویت';
				$new['hsw_dept']     = 'دپارتمان';
				$new['hsw_replies']  = 'پاسخ‌ها';
				$new['hsw_updated']  = 'آخرین به‌روزرسانی';
			} elseif ( 'date' === $key ) {
				$new['hsw_id'] = 'شناسه';
				$new[ $key ]   = $label;
			} else {
				$new[ $key ] = $label;
			}
		}
		return $new;
	}

	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'hsw_customer':
				$user = get_user_by( 'id', (int) get_post_field( 'post_author', $post_id ) );
				echo $user ? '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>' : '—';
				break;
			case 'hsw_status':
				echo '<span class="hsw-admin-badge is-' . esc_attr( $this->status_tone( $this->get_status( $post_id ) ) ) . '">' . esc_html( $this->status_label( $this->get_status( $post_id ) ) ) . '</span>';
				break;
			case 'hsw_priority':
				echo esc_html( $this->priority_label( get_post_meta( $post_id, self::PRIORITY, true ) ) );
				break;
			case 'hsw_dept':
				echo esc_html( get_post_meta( $post_id, self::DEPARTMENT, true ) );
				break;
			case 'hsw_replies':
				echo esc_html( (int) get_comments( array( 'post_id' => $post_id, 'type' => 'hsw_ticket_reply', 'count' => true ) ) );
				break;
			case 'hsw_updated':
				$updated = get_post_meta( $post_id, self::UPDATED_META, true );
				echo esc_html( $updated ? $this->format_date( $updated ) : '—' );
				break;
			case 'hsw_id':
				echo '#T-' . esc_html( HSW_Helpers::fa_num( $post_id ) );
				break;
		}
	}

	public function admin_status_filter() {
		global $typenow;
		if ( self::POST_TYPE !== $typenow ) {
			return;
		}
		$current = isset( $_GET['hsw_ticket_status'] ) ? sanitize_key( wp_unslash( $_GET['hsw_ticket_status'] ) ) : '';
		echo '<select name="hsw_ticket_status"><option value="">همه وضعیت‌ها</option>';
		foreach ( array( 'open' => 'در انتظار پاسخ', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده' ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function admin_filter_query( $query ) {
		global $pagenow, $typenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || self::POST_TYPE !== $typenow || ! $query->is_main_query() ) {
			return;
		}
		if ( ! empty( $_GET['hsw_ticket_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['hsw_ticket_status'] ) );
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => self::STATUS_META,
						'value' => $status,
					),
				)
			);
		}
	}

	public function admin_messages( $messages ) {
		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => 'تیکت به‌روزرسانی شد.',
			6  => 'تیکت ایجاد شد.',
			7  => 'تیکت ذخیره شد.',
			8  => 'تیکت ذخیره شد.',
			9  => 'تیکت ذخیره شد.',
			10 => 'تیکت ذخیره شد.',
		);
		return $messages;
	}

	public function admin_title_placeholder( $text, $post ) {
		if ( $post && self::POST_TYPE === $post->post_type ) {
			return 'موضوع تیکت را بنویسید';
		}
		return $text;
	}

	public function admin_notices() {
		if ( isset( $_GET['hsw_replied'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>پاسخ برای کاربر ارسال شد.</p></div>';
		}
		if ( isset( $_GET['hsw_status'] ) ) {
			$status  = sanitize_key( wp_unslash( $_GET['hsw_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = 'closed' === $status ? 'تیکت بسته شد.' : 'تیکت بازگشایی شد.';
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

}
