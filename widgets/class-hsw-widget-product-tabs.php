<?php
/**
 * Widget: تب‌های محصول — faithful conversion of the supplied design.
 * Desktop-only tab strip with three panels: specs table, editorial review
 * and buyer reviews (initials avatar, verified tag, filled star rating).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Product_Tabs extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-tabs';
        }

        /**
         * Widget label in the editor panel.
         */
        public function get_title() {
                return __( 'تب‌های محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-product-tabs';
        }

        /**
         * Search keywords.
         */
        public function get_keywords() {
                return array( 'tabs', 'تب', 'مشخصات', 'بررسی', 'دیدگاه', 'hamishop', 'hamisys' );
        }

        /**
         * Register content + style controls.
         */
        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'tab_specs',
                        array(
                                'label'   => __( 'تب مشخصات فنی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'lbl_specs',
                        array(
                                'label'   => __( 'عنوان تب مشخصات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مشخصات فنی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'tab_review',
                        array(
                                'label'   => __( 'تب بررسی تخصصی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'lbl_review',
                        array(
                                'label'   => __( 'عنوان تب بررسی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'بررسی تخصصی کالا', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'tab_comments',
                        array(
                                'label'   => __( 'تب دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'lbl_comments',
                        array(
                                'label'   => __( 'عنوان تب دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'دیدگاه‌ها و نظرات خریداران', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'tab_warranty',
                        array(
                                'label'   => __( 'تب گارانتی و خدمات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'lbl_warranty',
                        array(
                                'label'   => __( 'عنوان تب گارانتی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'گارانتی و خدمات', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'warranty_title',
                        array(
                                'label'   => __( 'عنوان پنل گارانتی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'گارانتی و خدمات پس از فروش', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'warranty_text',
                        array(
                                'label'       => __( 'متن گارانتی (هر خط یک مورد)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXTAREA,
                                'rows'        => 6,
                                'label_block' => true,
                                'default'     => "گارانتی اصالت و سلامت کالا\nخدمات پس از فروش در مراکز مجاز\nبازگشت کالا تا ۷ روز",
                        )
                );

                $this->add_control(
                        'specs_title',
                        array(
                                'label'   => __( 'عنوان جدول مشخصات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مشخصات کلیدی و فنی کالا', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'reviews_count',
                        array(
                                'label'   => __( 'تعداد دیدگاه‌های نمایشی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 5,
                                'min'     => 1,
                                'max'     => 20,
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'tabs', '{{WRAPPER}} .hsw-ptabs', array( 'padding' => false ) );

                $this->ctrl_text( 'tab_b', '{{WRAPPER}} .hsw-ptabs__btn' );

                $this->add_control(
                        'tab_active_heading',
                        array( 'label' => __( 'تب فعال', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'tab_ac_c',
                        array(
                                'label'     => __( 'رنگ متن تب فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptabs__btn.is-active' => '--hsw-tab-ac: {{VALUE}};' ),
                        )
                );
                $this->add_control(
                        'tab_abc_c',
                        array(
                                'label'     => __( 'رنگ خط زیر تب فعال', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptabs__btn.is-active' => '--hsw-tab-abc: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'panel_heading',
                        array( 'label' => __( 'پنل تب', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_box( 'panel', '{{WRAPPER}} .hsw-ptabs__panel' );

                $this->add_control(
                        'table_heading',
                        array( 'label' => __( 'جدول مشخصات', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'th', '{{WRAPPER}} .hsw-ptabs__th' );
                $this->ctrl_text( 'td', '{{WRAPPER}} .hsw-ptabs__td' );

                $this->add_control(
                        'rv_heading',
                        array( 'label' => __( 'دیدگاه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'rv', '{{WRAPPER}} .hsw-ptabs__rvtext' );
                $this->add_control(
                        'rvstar_c',
                        array(
                                'label'     => __( 'رنگ ستاره دیدگاه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-ptabs__rvstars' => '--hsw-rvstar: {{VALUE}};' ),
                        )
                );

                $this->a_align(
                        'pdesc',
                        __( 'تراز، جهت و ارتفاع خط توضیحات محصول', 'hamishop-elementor-widgets' ),
                        '{{WRAPPER}} .hsw-ptabs__panel',
                        array( 'align' => 'start', 'lh' => 1.9 )
                );

                $this->add_responsive_control(
                        'pdesc_fs',
                        array(
                                'label'      => __( 'اندازهٔ فونت توضیحات محصول', 'hamishop-elementor-widgets' ),
                                'type'       => Controls_Manager::SLIDER,
                                'size_units' => array( 'px' ),
                                'range'      => array( 'px' => array( 'min' => 11, 'max' => 22 ) ),
                                'default'    => array( 'size' => 14, 'unit' => 'px' ),
                                'selectors'  => array(
                                        '{{WRAPPER}} .hsw-ptabs__panel' => 'font-size: {{SIZE}}{{UNIT}};',
                                ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Render the tabs widget (nav buttons + three panels).
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->effective_settings();

                /* Never render mock data outside the Elementor editor. */
                if ( ! $product && ! $this->is_demo() ) {
                        return;
                }

                if ( $demo ) {
                        $d            = $this->demo();
                        $specs        = $d['specs'];
                        $reviews      = $d['reviews_l'];
                        $review_count = (int) $d['reviews'];
                } else {
                        $specs        = $this->specs_rows( $product );
                        $reviews      = $this->reviews_list( $product, ! empty( $s['reviews_count'] ) ? (int) $s['reviews_count'] : 5 );
                        $review_count = (int) $product->get_review_count();
                }

                $lbl_specs    = isset( $s['lbl_specs'] ) && '' !== trim( (string) $s['lbl_specs'] ) ? $s['lbl_specs'] : __( 'مشخصات فنی', 'hamishop-elementor-widgets' );
                $lbl_review   = isset( $s['lbl_review'] ) && '' !== trim( (string) $s['lbl_review'] ) ? $s['lbl_review'] : __( 'بررسی تخصصی کالا', 'hamishop-elementor-widgets' );
                $lbl_comments = isset( $s['lbl_comments'] ) && '' !== trim( (string) $s['lbl_comments'] ) ? $s['lbl_comments'] : __( 'دیدگاه‌ها و نظرات خریداران', 'hamishop-elementor-widgets' );
                $specs_title  = isset( $s['specs_title'] ) && '' !== trim( (string) $s['specs_title'] ) ? $s['specs_title'] : __( 'مشخصات کلیدی و فنی کالا', 'hamishop-elementor-widgets' );

                $lbl_warranty   = isset( $s['lbl_warranty'] ) && '' !== trim( (string) $s['lbl_warranty'] ) ? $s['lbl_warranty'] : __( 'گارانتی و خدمات', 'hamishop-elementor-widgets' );
                $warranty_title = isset( $s['warranty_title'] ) && '' !== trim( (string) $s['warranty_title'] ) ? $s['warranty_title'] : __( 'گارانتی و خدمات پس از فروش', 'hamishop-elementor-widgets' );
                $warranty_text  = isset( $s['warranty_text'] ) && '' !== trim( (string) $s['warranty_text'] ) ? (string) $s['warranty_text'] : "گارانتی اصالت و سلامت کالا\nخدمات پس از فروش در مراکز مجاز\nبازگشت کالا تا ۷ روز";

                $show_specs    = ( 'yes' === ( $s['tab_specs'] ?? 'yes' ) );
                $show_warranty = ( 'yes' === ( $s['tab_warranty'] ?? 'yes' ) );
                $show_review   = ( 'yes' === ( $s['tab_review'] ?? 'yes' ) );
                $show_comments = ( 'yes' === ( $s['tab_comments'] ?? 'yes' ) );

                /*
                 * Visual order: گارانتی → مشخصات (WooCommerce attributes) →
                 * بررسی تخصصی → دیدگاه‌ها. The first visible tab is active so
                 * the strip and its panel always agree.
                 */
                $first_tab = $show_warranty ? 'warranty' : ( $show_specs ? 'specs' : ( $show_review ? 'review' : 'comments' ) );
                ?>
                <?php
                /*
                 * One tab strip for every viewport. The mobile build used to be a
                 * separate accordion with different content; now both breakpoints
                 * read the same tabs, and on a narrow screen the strip scrolls
                 * sideways with a thin chevron hinting there is more.
                 */
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'ptabs' ) ); ?>" data-hsw-ptabs>
                        <div class="hsw-ptabs__navwrap">
                        <div class="hsw-ptabs__nav" data-hsw-tabs-scroll>
                                <?php if ( $show_warranty ) : ?>
                                        <button type="button" class="hsw-ptabs__btn<?php echo 'warranty' === $first_tab ? ' is-active' : ''; ?>" data-hsw-tab="warranty">
                                                <?php echo HSW_Helpers::icon( 'verified_user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                <span><?php echo esc_html( $lbl_warranty ); ?></span>
                                        </button>
                                <?php endif; ?>
                                <?php if ( $show_specs ) : ?>
                                        <button type="button" class="hsw-ptabs__btn<?php echo 'specs' === $first_tab ? ' is-active' : ''; ?>" data-hsw-tab="specs">
                                                <?php echo HSW_Helpers::icon( 'tune' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                <span><?php echo esc_html( $lbl_specs ); ?></span>
                                        </button>
                                <?php endif; ?>
                                <?php if ( $show_review ) : ?>
                                        <button type="button" class="hsw-ptabs__btn<?php echo 'review' === $first_tab ? ' is-active' : ''; ?>" data-hsw-tab="review">
                                                <?php echo HSW_Helpers::icon( 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                <span><?php echo esc_html( $lbl_review ); ?></span>
                                        </button>
                                <?php endif; ?>
                                <?php if ( $show_comments ) : ?>
                                        <button type="button" class="hsw-ptabs__btn<?php echo 'comments' === $first_tab ? ' is-active' : ''; ?>" data-hsw-tab="comments">
                                                <?php echo HSW_Helpers::icon( 'rate_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                <span><?php echo esc_html( $lbl_comments ); ?></span>
                                        </button>
                                <?php endif; ?>
                        </div>
                        <button type="button" class="hsw-ptabs__more" data-hsw-tabs-more aria-label="<?php esc_attr_e( 'تب‌های بیشتر', 'hamishop-elementor-widgets' ); ?>">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 6l-6 6 6 6"></path><path d="M19 6l-6 6 6 6" opacity=".4"></path></svg>
                        </button>
                        </div>

                        <?php if ( $show_warranty ) : ?>
                                <div class="hsw-ptabs__panel<?php echo 'warranty' === $first_tab ? ' is-active' : ''; ?>" data-hsw-panel="warranty">
                                        <h2 class="hsw-ptabs__h2"><?php echo esc_html( $warranty_title ); ?></h2>
                                        <?php
                                        $w_lines = preg_split( '/\r\n|\r|\n/', trim( (string) $warranty_text ) );
                                        $w_lines = array_filter( array_map( 'trim', (array) $w_lines ) );
                                        ?>
                                        <?php if ( empty( $w_lines ) ) : ?>
                                                <p><?php esc_html_e( 'اطلاعاتی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                        <?php else : ?>
                                                <ul class="hsw-ptabs__warranty">
                                                        <?php foreach ( $w_lines as $line ) : ?>
                                                                <li><?php echo HSW_Helpers::icon( 'check_circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $line ); ?></span></li>
                                                        <?php endforeach; ?>
                                                </ul>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>

                        <?php if ( $show_specs ) : ?>
                                <div class="hsw-ptabs__panel<?php echo 'specs' === $first_tab ? ' is-active' : ''; ?>" data-hsw-panel="specs">
                                        <h2 class="hsw-ptabs__h2"><?php echo esc_html( $specs_title ); ?></h2>
                                        <?php if ( empty( $specs ) ) : ?>
                                                <p><?php esc_html_e( 'مشخصاتی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                        <?php else : ?>
                                                <div class="hsw-ptabs__table">
                                                        <?php foreach ( $specs as $i => $row ) : ?>
                                                                <?php
                                                                $k       = isset( $row['k'] ) ? $row['k'] : ( isset( $row[0] ) ? $row[0] : '' );
                                                                $v       = isset( $row['v'] ) ? $row['v'] : ( isset( $row[1] ) ? $row[1] : '' );
                                                                $row_cls = 'hsw-ptabs__row' . ( 1 === $i % 2 ? ' alt' : '' );
                                                                ?>
                                                                <div class="<?php echo esc_attr( $row_cls ); ?>">
                                                                        <span class="hsw-ptabs__th"><?php echo esc_html( $k ); ?></span>
                                                                        <span class="hsw-ptabs__td"><?php echo esc_html( $v ); ?></span>
                                                                </div>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>

                        <?php if ( $show_review ) : ?>
                                <div class="hsw-ptabs__panel<?php echo 'review' === $first_tab ? ' is-active' : ''; ?>" data-hsw-panel="review">
                                        <div class="hsw-ptabs__review">
                                                <?php if ( $demo ) : ?>
                                                        <p><?php echo esc_html( 'پرچمدار جدید سامسونگ نقطه عطف یکپارچه‌سازی سخت‌افزار ممتاز با الگوریتم‌های هوش مصنوعی اختصاصی است. بدنه تیتانیومی مستحکم علاوه بر جلوه مدرن و فوق‌العاده مینیمال، مقاومت دستگاه را در برابر صدمات روزمره تضمین می‌کند.' ); ?></p>
                                                        <p><?php echo esc_html( 'نمایشگر مسطح جدید بازتاب نور محیط را به حداقل ممکن رسانده است، و استفاده از قلم S-Pen در کنار قابلیت‌های پردازش بلادرنگ متن، ابزاری ایده‌آل را برای حرفه‌ای‌ها خلق کرده است.' ); ?></p>
                                                <?php else : ?>
                                                        <?php $desc = (string) $product->get_description(); ?>
                                                        <?php if ( '' === trim( $desc ) ) : ?>
                                                                <p><?php esc_html_e( 'توضیحی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                                        <?php else : ?>
                                                                <?php echo HSW_Helpers::demote_content_h1( apply_filters( 'the_content', $desc ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard WP content pipeline. ?>
                                                        <?php endif; ?>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        <?php endif; ?>

                        <?php if ( $show_comments ) : ?>
                                <?php
                                $review_form_id = $product ? 'hsw-review-form-' . (int) $product->get_id() : '';
                                $can_review     = ! $demo && $product && $this->review_form_allowed( $product );
                                ?>
                                <div class="hsw-ptabs__panel<?php echo 'comments' === $first_tab ? ' is-active' : ''; ?>" data-hsw-panel="comments" id="reviews">
                                        <div class="hsw-ptabs__rvhead">
                                                <div>
                                                        <h2 class="hsw-ptabs__h2"><?php esc_html_e( 'دیدگاه‌های تاییدشده خریداران', 'hamishop-elementor-widgets' ); ?></h2>
                                                        <span class="hsw-ptabs__rvsub">
                                                                <?php echo esc_html( sprintf(
                                                                        /* translators: %s: number of registered reviews. */
                                                                        __( 'بر اساس %s بازخورد کاربران ثبت‌نام شده', 'hamishop-elementor-widgets' ),
                                                                        HSW_Helpers::fa_num( $review_count )
                                                                ) ); ?>
                                                        </span>
                                                </div>
                                                <?php if ( $can_review ) : ?>
                                                        <a class="hsw-ptabs__newrv" href="<?php echo esc_url( '#' . $review_form_id ); ?>" data-hsw-add-review><?php esc_html_e( 'ثبت دیدگاه جدید', 'hamishop-elementor-widgets' ); ?></a>
                                                <?php endif; ?>
                                        </div>
                                        <?php if ( $demo ) : ?>
                                                <?php if ( empty( $reviews ) ) : ?>
                                                        <p class="hsw-ptabs__rvtext"><?php esc_html_e( 'هنوز دیدگاهی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                                <?php else : ?>
                                                        <div class="hsw-ptabs__rvlist">
                                                                <?php foreach ( $reviews as $rv ) : ?>
                                                                        <?php
                                                                        $name     = isset( $rv['name'] ) ? $rv['name'] : '';
                                                                        $initials = isset( $rv['initials'] ) ? $rv['initials'] : '';
                                                                        $text     = isset( $rv['text'] ) ? $rv['text'] : '';
                                                                        $verified = ! empty( $rv['verified'] );
                                                                        $rating   = max( 0, min( 5, (int) ( isset( $rv['rating'] ) ? $rv['rating'] : 0 ) ) );
                                                                        ?>
                                                                        <div class="hsw-ptabs__rvitem">
                                                                                <div class="hsw-ptabs__rvtop">
                                                                                        <div class="hsw-ptabs__rvwho">
                                                                                                <span class="hsw-ptabs__rvav"><?php echo esc_html( $initials ); ?></span>
                                                                                                <span class="hsw-ptabs__rvname"><?php echo esc_html( $name ); ?></span>
                                                                                                <?php if ( $verified ) : ?>
                                                                                                        <span class="hsw-ptabs__rvtag"><?php esc_html_e( 'خریدار این کالا', 'hamishop-elementor-widgets' ); ?></span>
                                                                                                <?php endif; ?>
                                                                                        </div>
                                                                                        <div class="hsw-ptabs__rvstars">
                                                                                                <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                                                                                                        <?php echo HSW_Helpers::icon( 'star', $star <= $rating ? 'hsw-ic--fill' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                                                                <?php endfor; ?>
                                                                                                <span class="hsw-ptabs__rvval"><?php echo esc_html( HSW_Helpers::fa_num( $rating ) . '.۰' ); ?></span>
                                                                                        </div>
                                                                                </div>
                                                                                <p class="hsw-ptabs__rvtext"><?php echo esc_html( $text ); ?></p>
                                                                        </div>
                                                                <?php endforeach; ?>
                                                        </div>
                                                <?php endif; ?>
                                        <?php else : ?>
                                                <?php if ( isset( $_GET['unapproved'] ) && absint( $_GET['unapproved'] ) > 0 ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag from WordPress. ?>
                                                        <p class="hsw-review-pending"><?php esc_html_e( 'دیدگاه شما ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
                                                <?php endif; ?>
                                                <?php echo $this->review_cards_html( $product, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                                                <?php if ( $review_form_id ) : ?>
                                                        <div class="hsw-ptabs__rvform" id="<?php echo esc_attr( $review_form_id ); ?>">
                                                                <?php echo $this->review_form_html( $product, 'tabs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- native comment_form markup. ?>
                                                        </div>
                                                <?php endif; ?>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>
                </section>
                <?php
        }
}
