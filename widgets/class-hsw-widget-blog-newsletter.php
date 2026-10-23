<?php
/**
 * Blog widget: newsletter box (عضویت در خبرنامه).
 * Form can post to any external service URL or delegate to a shortcode
 * (e.g. Contact Form 7 / Mailchimp plugin) — zero external requests by itself.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Blog_Newsletter extends HSW_Blog_Base {

        public function get_name() {
                return 'hsw-blog-newsletter';
        }

        public function get_title() {
                return __( 'وبلاگ: خبرنامه (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-email-field';
        }

        public function get_keywords() {
                return array( 'blog', 'newsletter', 'email', 'خبرنامه', 'ایمیل', 'hamishop' );
        }

        protected function register_controls() {
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control( 'title', array(
                        'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::TEXT,
                        'default'     => __( 'عضویت در خبرنامه', 'hamishop-elementor-widgets' ),
                        'label_block' => true,
                ) );

                $this->add_control( 'sub', array(
                        'label'   => __( 'توضیح کوتاه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXTAREA,
                        'default' => __( 'جدیدترین مقالات و راهنمای خرید را در ایمیل خود دریافت کنید.', 'hamishop-elementor-widgets' ),
                        'rows'    => 2,
                ) );

                $this->add_control( 'placeholder', array(
                        'label'   => __( 'متن راهنمای ایمیل', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'ایمیل شما', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'btn_label', array(
                        'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'عضویت', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'action_url', array(
                        'label'       => __( 'آدرس فرم (اختیاری)', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::URL,
                        'default'     => array( 'url' => '' ),
                        'description' => __( 'اگر خالی باشد، فرم به همین صفحه ارسال می‌شود. آدرس سرویس ایمیل خود را وارد کنید تا ایمیل‌ها به آنجا ارسال شوند.', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'email_field', array(
                        'label'     => __( 'نام فیلد ایمیل', 'hamishop-elementor-widgets' ),
                        'type'      => Controls_Manager::TEXT,
                        'default'   => 'email',
                        'condition' => array( 'action_url[url]!' => '' ),
                ) );

                $this->add_control( 'shortcode', array(
                        'label'       => __( 'جایگزینی با شورت‌کد (اختیاری)', 'hamishop-elementor-widgets' ),
                        'type'        => Controls_Manager::TEXTAREA,
                        'default'     => '',
                        'rows'        => 2,
                        'description' => __( 'مثال: شورت‌کد فرم‌ساز یا Mailchimp. اگر پر شود، فرم داخلی نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ),
                ) );

                $this->add_control( 'note', array(
                        'label'   => __( 'یادداشت پایین فرم', 'hamishop-elementor-widgets' ),
                        'type'    => Controls_Manager::TEXT,
                        'default' => __( 'بدون اسپم؛ هر زمان بخواهید لغو عضویت کنید.', 'hamishop-elementor-widgets' ),
                ) );

                $this->end_controls_section();

		/*
		 * One type scale per widget. The variables land on the wrapper, so a
		 * child element only has to read them and the generated CSS stays a
		 * single declaration per part.
		 */
		$this->style_typography_section(
			'bnews',
			array(
			'title' => array( __( 'عنوان', 'hamishop-elementor-widgets' ), 15 ),
			'sub' => array( __( 'متن توضیح', 'hamishop-elementor-widgets' ), 13 ),
			)
		);
        }

        protected function render() {
                $s = $this->effective_settings();

                echo '<div class="' . esc_attr( $this->root_class( 'blog-newsletter', 'hsw-bnews' ) ) . '">';

                if ( ! empty( $s['title'] ) ) {
                        echo '<h3 class="hsw-bnews__title">' . HSW_Helpers::icon( 'mail' ) . esc_html( $s['title'] ) . '</h3>';
                }
                if ( ! empty( $s['sub'] ) ) {
                        echo '<p class="hsw-bnews__sub">' . esc_html( $s['sub'] ) . '</p>';
                }

                if ( ! empty( $s['shortcode'] ) ) {
                        echo '<div class="hsw-bnews__embed">' . do_shortcode( wp_unslash( $s['shortcode'] ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                } else {
                        $action = ! empty( $s['action_url']['url'] ) ? $s['action_url']['url'] : $this->blog_base_url();
                        $field  = ! empty( $s['email_field'] ) ? sanitize_key( $s['email_field'] ) : 'email';

                        echo '<form class="hsw-bnews__form" method="post" action="' . esc_url( $action ) . '">';
                        echo '<input type="email" name="' . esc_attr( $field ) . '" required placeholder="' . esc_attr( $s['placeholder'] ?? '' ) . '" aria-label="' . esc_attr__( 'ایمیل شما', 'hamishop-elementor-widgets' ) . '">';
                        echo '<button type="submit">' . esc_html( $s['btn_label'] ?? '' ) . '</button>';
                        echo '</form>';
                }

                if ( ! empty( $s['note'] ) ) {
                        echo '<p class="hsw-bnews__note">' . esc_html( $s['note'] ) . '</p>';
                }

                echo '</div>';
        }
}
