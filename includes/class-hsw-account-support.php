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

	/** @var HSW_Account */
	private $account;

	public function __construct( $account ) {
		$this->account = $account;

		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_frontend_actions' ) );
		add_action( 'woocommerce_account_support_endpoint', array( $this, 'render_endpoint' ) );

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

	private function status_filter() {
		$filter = isset( $_GET['ticket_status'] ) ? sanitize_key( wp_unslash( $_GET['ticket_status'] ) ) : 'all';
		return in_array( $filter, array( 'all', 'open', 'answered', 'closed' ), true ) ? $filter : 'all';
	}

	private function current_page() {
		$page = isset( $_GET['ticket_page'] ) ? absint( $_GET['ticket_page'] ) : 1;
		return max( 1, $page );
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
		if ( '' === $value ) {
			return $this->list_content( $user_id );
		}
		if ( 'new' === $value ) {
			return $this->new_ticket_content( $user_id );
		}
		return $this->ticket_detail_content( $user_id, absint( $value ) );
	}

	private function list_content( $user_id ) {
		$all = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$counts = array( 'all' => count( $all ), 'open' => 0, 'answered' => 0, 'closed' => 0 );
		foreach ( $all as $post ) {
			$status = $this->get_status( $post->ID );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}

		$filter = $this->status_filter();
		$tickets = array();
		foreach ( $all as $post ) {
			if ( 'all' === $filter || $this->get_status( $post->ID ) === $filter ) {
				$tickets[] = $post;
			}
		}

		$per_page = 10;
		$page     = $this->current_page();
		$pages    = max( 1, (int) ceil( count( $tickets ) / $per_page ) );
		$page     = min( $page, $pages );
		$slice    = array_slice( $tickets, ( $page - 1 ) * $per_page, $per_page );
		$max_open = max( 1, absint( $this->account->setting( 'support.max_open', 5 ) ) );
		$open     = $counts['open'] + $counts['answered'];
		$can_new  = $open < $max_open;

		$html  = '<section class="hsw-ac__card hsw-ac__tickets" aria-labelledby="hsw-ac-tickets-title">';
		$html .= '<header class="hsw-ac__page-head"><div><h2 id="hsw-ac-tickets-title">' . esc_html__( 'تیکت‌های پشتیبانی', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'گفتگوهای خود با تیم پشتیبانی را از این‌جا پیگیری کنید.', 'hamishop-elementor-widgets' ) . '</p></div>';
		if ( $can_new ) {
			$html .= '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $this->new_ticket_url() ) . '">' . $this->account->icon( 'plus' ) . esc_html__( 'تیکت جدید', 'hamishop-elementor-widgets' ) . '</a>';
		}
		$html .= '</header>';

		if ( ! $can_new ) {
			$html .= '<p class="hsw-ac__note is-warning">' . $this->account->icon( 'alert' ) . '<span>' . esc_html( sprintf( 'حداکثر %s تیکت باز هم‌زمان مجاز است. ابتدا یکی از تیکت‌ها را ببندید.', HSW_Helpers::fa_num( $max_open ) ) ) . '</span></p>';
		}

		$html .= '<div class="hsw-ac__ticket-tabs" role="tablist">';
		$tabs = array(
			'all'      => 'همه',
			'open'     => 'در انتظار پاسخ',
			'answered' => 'پاسخ داده شده',
			'closed'   => 'بسته شده',
		);
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( 'ticket_status', $key, $this->support_url() );
			$html .= '<a href="' . esc_url( $url ) . '" class="' . ( $filter === $key ? 'is-active' : '' ) . '"' . ( $filter === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '<span>' . esc_html( HSW_Helpers::fa_num( $counts[ $key ] ) ) . '</span></a>';
		}
		$html .= '</div>';

		if ( $slice ) {
			$html .= '<ul class="hsw-ac__ticket-list">';
			foreach ( $slice as $post ) {
				$html .= $this->ticket_card( $post );
			}
			$html .= '</ul>';
			$html .= $this->pagination( $pages, $page, $filter );
		} else {
			$html .= '<div class="hsw-ac__empty hsw-ac__empty--inline"><span class="hsw-ac__empty-ic">' . $this->account->icon( 'support' ) . '</span><h3>' . esc_html__( 'تیکتی در این بخش نیست', 'hamishop-elementor-widgets' ) . '</h3><p>' . esc_html__( 'اگر سؤالی دارید، یک تیکت جدید ثبت کنید تا تیم پشتیبانی پاسخ دهد.', 'hamishop-elementor-widgets' ) . '</p></div>';
		}

		$html .= '</section>';
		return $html;
	}

	private function ticket_card( $post ) {
		$status   = $this->get_status( $post->ID );
		$updated  = get_post_meta( $post->ID, self::UPDATED_META, true );
		$updated  = $updated ? absint( $updated ) : $this->timestamp_from_local( $post->post_modified );
		$priority = get_post_meta( $post->ID, self::PRIORITY, true );
		$dept     = get_post_meta( $post->ID, self::DEPARTMENT, true );
		$replies  = get_comments(
			array(
				'post_id' => $post->ID,
				'type'    => 'hsw_ticket_reply',
				'status'  => 'approve',
				'count'   => true,
			)
		);
		$unread   = (int) get_post_meta( $post->ID, self::UNREAD_USER, true );

		$html  = '<li class="hsw-ac__ticket' . ( $unread ? ' is-unread' : '' ) . '">';
		$html .= '<a class="hsw-ac__ticket-main" href="' . esc_url( $this->ticket_url( $post->ID ) ) . '">';
		$html .= '<div class="hsw-ac__ticket-top"><span class="hsw-ac__status-badge is-' . esc_attr( $this->status_tone( $status ) ) . '">' . esc_html( $this->status_label( $status ) ) . '</span><span class="hsw-ac__ticket-id">#T-' . esc_html( HSW_Helpers::fa_num( $post->ID ) ) . '</span></div>';
		$html .= '<h3 class="hsw-ac__ticket-title">' . esc_html( $post->post_title ) . '</h3>';
		$html .= '<div class="hsw-ac__ticket-meta"><span>' . $this->account->icon( 'support' ) . esc_html( $dept ? $dept : 'پشتیبانی' ) . '</span><span>' . $this->account->icon( 'alert' ) . esc_html( $this->priority_label( $priority ) ) . '</span><span>' . $this->account->icon( 'clock' ) . esc_html( $this->format_date( $updated ) ) . '</span><span>' . $this->account->icon( 'ticket' ) . esc_html( sprintf( '%s پاسخ', HSW_Helpers::fa_num( $replies ) ) ) . '</span></div>';
		$html .= '</a>';
		$html .= '<a class="hsw-ac__ticket-open" href="' . esc_url( $this->ticket_url( $post->ID ) ) . '" aria-label="' . esc_attr__( 'مشاهده تیکت', 'hamishop-elementor-widgets' ) . '">' . $this->account->icon( 'arrow-left' ) . '</a>';
		$html .= '</li>';
		return $html;
	}

	private function pagination( $pages, $current, $filter ) {
		if ( $pages < 2 ) {
			return '';
		}
		$base = add_query_arg( 'ticket_status', $filter, $this->support_url() );
		$base = add_query_arg( 'ticket_page', '%#%', $base );
		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $current,
				'total'     => $pages,
				'type'      => 'array',
				'prev_text' => '‹',
				'next_text' => '›',
			)
		);
		if ( ! $links ) {
			return '';
		}
		return '<nav class="hsw-ac__pagination" aria-label="' . esc_attr__( 'صفحه‌بندی تیکت‌ها', 'hamishop-elementor-widgets' ) . '"><ul><li>' . implode( '</li><li>', $links ) . '</li></ul></nav>';
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

	private function new_ticket_content( $user_id ) {
		$max_open = max( 1, absint( $this->account->setting( 'support.max_open', 5 ) ) );
		if ( $this->open_ticket_count( $user_id ) >= $max_open ) {
			$html  = '<section class="hsw-ac__card hsw-ac__empty"><span class="hsw-ac__empty-ic">' . $this->account->icon( 'alert' ) . '</span>';
			$html .= '<h2>' . esc_html__( 'حد مجاز تیکت‌های باز', 'hamishop-elementor-widgets' ) . '</h2>';
			$html .= '<p>' . esc_html( sprintf( 'حداکثر %s تیکت باز هم‌زمان می‌توانید داشته باشید.', HSW_Helpers::fa_num( $max_open ) ) ) . '</p>';
			$html .= '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $this->support_url() ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به تیکت‌ها', 'hamishop-elementor-widgets' ) . '</a></section>';
			return $html;
		}

		$departments = (array) $this->account->setting( 'support.departments', array() );
		$priorities  = (array) $this->account->setting( 'support.priorities', array() );
		$default_pri = (string) $this->account->setting( 'support.priority_default', 'normal' );
		$prefill     = isset( $_GET['department'] ) ? sanitize_text_field( wp_unslash( $_GET['department'] ) ) : '';

		$html  = '<section class="hsw-ac__card hsw-ac__ticket-page" aria-labelledby="hsw-ac-new-ticket-title">';
		$html .= '<a class="hsw-ac__back" href="' . esc_url( $this->support_url() ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به تیکت‌ها', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '<header class="hsw-ac__page-head"><div><h2 id="hsw-ac-new-ticket-title">' . esc_html__( 'ثبت تیکت جدید', 'hamishop-elementor-widgets' ) . '</h2><p>' . esc_html__( 'موضوع را دقیق بنویسید تا سریع‌تر به پاسخ درست برسید.', 'hamishop-elementor-widgets' ) . '</p></div></header>';
		$html .= '<form class="hsw-ac-form hsw-ac-form--ticket" method="post" action="' . esc_url( $this->support_url() ) . '">';
		$html .= '<div class="hsw-ac-form-grid">';
		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-ticket-subject">' . esc_html__( 'موضوع تیکت', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label><input type="text" id="hsw-ac-ticket-subject" name="subject" maxlength="120" required></div>';
		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-ticket-dept">' . esc_html__( 'دپارتمان', 'hamishop-elementor-widgets' ) . '</label><select id="hsw-ac-ticket-dept" name="department">';
		foreach ( $departments as $department ) {
			$html .= '<option value="' . esc_attr( $department ) . '"' . selected( $department, $prefill, false ) . '>' . esc_html( $department ) . '</option>';
		}
		$html .= '</select></div>';
		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-ticket-priority">' . esc_html__( 'اولویت', 'hamishop-elementor-widgets' ) . '</label><select id="hsw-ac-ticket-priority" name="priority">';
		foreach ( $priorities as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_pri, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></div>';
		$html .= '</div>';
		$html .= '<div class="hsw-ac-field"><label for="hsw-ac-ticket-message">' . esc_html__( 'متن پیام', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label><textarea id="hsw-ac-ticket-message" name="message" rows="8" maxlength="' . esc_attr( $this->account->setting( 'support.max_message', 3000 ) ) . '" required placeholder="' . esc_attr__( 'شماره سفارش، توضیح مشکل و هر اطلاعات مفید دیگری را بنویسید…', 'hamishop-elementor-widgets' ) . '"></textarea></div>';
		$html .= '<div class="hsw-ac-hp" aria-hidden="true"><label>Website<input type="text" name="hsw_website" tabindex="-1" autocomplete="off"></label></div>';
		$html .= '<input type="hidden" name="hsw_ticket_action" value="create">';
		$html .= wp_nonce_field( 'hsw_ticket_create', 'hsw_ticket_nonce', true, false );
		$html .= '<div class="hsw-ac-form-actions"><button type="submit" class="hsw-ac__btn hsw-ac__btn--primary">' . $this->account->icon( 'ticket' ) . esc_html__( 'ارسال تیکت', 'hamishop-elementor-widgets' ) . '</button><span class="hsw-ac-form-hint">' . esc_html__( 'پاسخ‌گویی معمولاً کمتر از ۲۴ ساعت کاری است.', 'hamishop-elementor-widgets' ) . '</span></div>';
		$html .= '</form></section>';
		return $html;
	}

	private function ticket_detail_content( $user_id, $ticket_id ) {
		$ticket = get_post( $ticket_id );
		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== (int) $user_id || 'publish' !== $ticket->post_status ) {
			$html  = '<section class="hsw-ac__card hsw-ac__empty"><span class="hsw-ac__empty-ic">' . $this->account->icon( 'alert' ) . '</span>';
			$html .= '<h2>' . esc_html__( 'تیکت پیدا نشد', 'hamishop-elementor-widgets' ) . '</h2>';
			$html .= '<p>' . esc_html__( 'این تیکت وجود ندارد یا به حساب شما تعلق ندارد.', 'hamishop-elementor-widgets' ) . '</p>';
			$html .= '<a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $this->support_url() ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به تیکت‌ها', 'hamishop-elementor-widgets' ) . '</a></section>';
			return $html;
		}

		update_post_meta( $ticket_id, self::UNREAD_USER, 0 );

		$status   = $this->get_status( $ticket_id );
		$priority = get_post_meta( $ticket_id, self::PRIORITY, true );
		$dept     = get_post_meta( $ticket_id, self::DEPARTMENT, true );
		$created  = $this->timestamp_from_local( $ticket->post_date );
		$updated  = get_post_meta( $ticket_id, self::UPDATED_META, true );
		$updated  = $updated ? absint( $updated ) : $this->timestamp_from_local( $ticket->post_modified );
		$closed   = 'closed' === $status;

		$html  = '<section class="hsw-ac__card hsw-ac__ticket-page" aria-labelledby="hsw-ac-ticket-title">';
		$html .= '<a class="hsw-ac__back" href="' . esc_url( $this->support_url() ) . '">' . $this->account->icon( 'arrow-right' ) . esc_html__( 'بازگشت به تیکت‌ها', 'hamishop-elementor-widgets' ) . '</a>';
		$html .= '<header class="hsw-ac__ticket-head"><div><div class="hsw-ac__ticket-top"><span class="hsw-ac__status-badge is-' . esc_attr( $this->status_tone( $status ) ) . '">' . esc_html( $this->status_label( $status ) ) . '</span><span class="hsw-ac__ticket-id">#T-' . esc_html( HSW_Helpers::fa_num( $ticket_id ) ) . '</span></div>';
		$html .= '<h2 id="hsw-ac-ticket-title">' . esc_html( $ticket->post_title ) . '</h2>';
		$html .= '<div class="hsw-ac__ticket-meta"><span>' . $this->account->icon( 'support' ) . esc_html( $dept ? $dept : 'پشتیبانی' ) . '</span><span>' . $this->account->icon( 'alert' ) . esc_html( $this->priority_label( $priority ) ) . '</span><span>' . $this->account->icon( 'calendar' ) . esc_html( sprintf( 'ایجاد: %s', $this->format_date( $created ) ) ) . '</span><span>' . $this->account->icon( 'clock' ) . esc_html( sprintf( 'آخرین به‌روزرسانی: %s', $this->format_date( $updated ) ) ) . '</span></div></div>';
		if ( ! $closed ) {
			$html .= '<form method="post" action="' . esc_url( $this->support_url() ) . '" class="hsw-ac-ticket-close-form">';
			$html .= '<input type="hidden" name="hsw_ticket_action" value="close"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '">';
			$html .= wp_nonce_field( 'hsw_ticket_close_' . $ticket_id, 'hsw_ticket_nonce', true, false );
			$html .= '<button type="submit" class="hsw-ac__btn hsw-ac__btn--ghost hsw-ac__btn--danger">' . $this->account->icon( 'check' ) . esc_html__( 'بستن تیکت', 'hamishop-elementor-widgets' ) . '</button></form>';
		}
		$html .= '</header>';

		$html .= '<div class="hsw-ac__thread">';
		$html .= $this->message_bubble( $user_id, $ticket->post_content, $created, false );
		$comments = get_comments(
			array(
				'post_id' => $ticket_id,
				'type'    => 'hsw_ticket_reply',
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
		foreach ( $comments as $comment ) {
			$comment_author = get_user_by( 'id', (int) $comment->user_id );
			$is_staff       = $comment_author && ( user_can( $comment_author, 'manage_woocommerce' ) || user_can( $comment_author, 'manage_options' ) );
			$html .= $this->message_bubble( (int) $comment->user_id, $comment->comment_content, $this->timestamp_from_local( $comment->comment_date ), $is_staff );
		}
		$html .= '</div>';

		if ( $closed ) {
			$html .= '<div class="hsw-ac__ticket-closed">' . $this->account->icon( 'lock' ) . '<p>' . esc_html__( 'این تیکت بسته شده است. برای موضوع جدید یک تیکت تازه ثبت کنید.', 'hamishop-elementor-widgets' ) . '</p><a class="hsw-ac__btn hsw-ac__btn--primary" href="' . esc_url( $this->new_ticket_url() ) . '">' . esc_html__( 'تیکت جدید', 'hamishop-elementor-widgets' ) . '</a></div>';
		} else {
			$html .= '<form class="hsw-ac-form hsw-ac-form--reply" method="post" action="' . esc_url( $this->support_url() ) . '">';
			$html .= '<div class="hsw-ac-field"><label for="hsw-ac-reply-message">' . esc_html__( 'پاسخ شما', 'hamishop-elementor-widgets' ) . ' <span class="required">*</span></label><textarea id="hsw-ac-reply-message" name="message" rows="5" maxlength="' . esc_attr( $this->account->setting( 'support.max_message', 3000 ) ) . '" required placeholder="' . esc_attr__( 'پاسخ یا توضیح تکمیلی خود را بنویسید…', 'hamishop-elementor-widgets' ) . '"></textarea></div>';
			$html .= '<input type="hidden" name="hsw_ticket_action" value="reply"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '">';
			$html .= wp_nonce_field( 'hsw_ticket_reply_' . $ticket_id, 'hsw_ticket_nonce', true, false );
			$html .= '<div class="hsw-ac-form-actions"><button type="submit" class="hsw-ac__btn hsw-ac__btn--primary">' . $this->account->icon( 'arrow-left' ) . esc_html__( 'ارسال پاسخ', 'hamishop-elementor-widgets' ) . '</button></div>';
			$html .= '</form>';
		}
		$html .= '</section>';
		return $html;
	}

	private function message_bubble( $author_id, $content, $timestamp, $is_staff ) {
		$author = get_user_by( 'id', $author_id );
		$name   = $author ? $author->display_name : ( $is_staff ? 'پشتیبانی' : 'کاربر' );
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );
		$staff   = class_exists( 'HSW_Branding' ) ? sprintf( 'پشتیبانی %s', HSW_Branding::name() ) : $name;

		return '<article class="hsw-ac__msg' . ( $is_staff ? ' is-staff' : '' ) . '">'
			. '<div class="hsw-ac__msg-avatar" aria-hidden="true">' . ( $is_staff ? $this->account->icon( 'support' ) : esc_html( $initial ) ) . '</div>'
			. '<div class="hsw-ac__msg-body"><div class="hsw-ac__msg-head"><strong>' . esc_html( $is_staff ? $staff : $name ) . '</strong><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">' . esc_html( $this->format_date( $timestamp ) ) . '</time></div>'
			. '<div class="hsw-ac__msg-text">' . nl2br( esc_html( $content ) ) . '</div></div>'
			. '</article>';
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
		wp_insert_comment(
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
			'',
			$is_reply ? 'پاسخ کاربر:' : 'متن تیکت:',
			$is_reply ? '' : ( $ticket ? $ticket->post_content : '' ),
			'',
			'مشاهده در پیشخوان: ' . admin_url( 'post.php?post=' . $ticket_id . '&action=edit' ),
		);
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
