<?php
/**
 * Widget: آکاردئون‌های محصول — faithful conversion of the supplied design.
 * Mobile-only accordion stack (all items closed initially): full specs rows,
 * buyer reviews and warranty/shipping lines with repeater-controlled icons.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Product_Accordions extends HSW_Product_Base {

        /**
         * Widget slug.
         */
        public function get_name() {
                return 'hsw-product-accordions';
        }

        /**
         * Widget label in the editor panel.
         */
        public function get_title() {
                return __( 'آکاردئون‌های محصول (HamiShop)', 'hamishop-elementor-widgets' );
        }

        /**
         * Widget icon.
         */
        public function get_icon() {
                return 'eicon-accordion';
        }

        /**
         * Search keywords.
         */
        public function get_keywords() {
                return array( 'accordion', 'آکاردئون', 'مشخصات', 'دیدگاه', 'گارانتی', 'hamishop', 'hamisys' );
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

                $this->ctrl_visibility( 'mobile' );

                $this->add_control(
                        'acc_specs',
                        array(
                                'label'   => __( 'آکاردئون مشخصات فنی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'acc_specs_title',
                        array(
                                'label'   => __( 'عنوان آکاردئون مشخصات', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'مشخصات فنی کامل', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'acc_reviews',
                        array(
                                'label'   => __( 'آکاردئون دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'acc_reviews_title',
                        array(
                                'label'   => __( 'عنوان آکاردئون دیدگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'دیدگاه‌ها و نظرات خریداران', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'acc_warranty',
                        array(
                                'label'   => __( 'آکاردئون گارانتی و ارسال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'acc_warranty_title',
                        array(
                                'label'   => __( 'عنوان آکاردئون گارانتی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'شرایط گارانتی و ارسال', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'reviews_count2',
                        array(
                                'label'   => __( 'تعداد دیدگاه‌های نمایشی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 3,
                                'min'     => 1,
                                'max'     => 10,
                        )
                );

                $repeater = new Repeater();

                $repeater->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'security',
                                'options' => $this->product_icon_options(),
                        )
                );

                $repeater->add_control(
                        'text',
                        array(
                                'label'       => __( 'متن', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => '',
                                'label_block' => true,
                        )
                );

                $warranty_defaults = array(
                        array(
                                'icon' => 'security',
                                'text' => __( '۱۸ ماه گارانتی شرکتی معتبر همراه با کد رجیستری فعال', 'hamishop-elementor-widgets' ),
                        ),
                        array(
                                'icon' => 'bolt',
                                'text' => __( 'ارسال فوری اکسپرس در تهران (تحویل حداکثر ۳ ساعته)', 'hamishop-elementor-widgets' ),
                        ),
                        array(
                                'icon' => 'assignment_return',
                                'text' => __( '۷ روز مهلت تست و بازگشت بی‌قید و شرط کالا', 'hamishop-elementor-widgets' ),
                        ),
                );

                $this->add_control(
                        'warranty_items',
                        array(
                                'label'       => __( 'اقلام گارانتی و ارسال', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'default'     => $warranty_defaults,
                                'title_field' => '{{{ text }}}',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->ctrl_box( 'acc', '{{WRAPPER}} .hsw-pacc__item' );

                $this->ctrl_text( 'acc_t', '{{WRAPPER}} .hsw-pacc__title' );
                $this->ctrl_icon( 'acc_ic', '{{WRAPPER}} .hsw-pacc__title .hsw-ic' );

                $this->add_control(
                        'rows_heading',
                        array( 'label' => __( 'ردیف‌های مشخصات', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'acc_k', '{{WRAPPER}} .hsw-pacc__k' );
                $this->ctrl_text( 'acc_v', '{{WRAPPER}} .hsw-pacc__v' );

                $this->add_control(
                        'acc_rv_heading',
                        array( 'label' => __( 'دیدگاه‌ها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->ctrl_text( 'acc_rv', '{{WRAPPER}} .hsw-pacc__rvtext' );
                $this->add_control(
                        'acc_rvstar_c',
                        array(
                                'label'     => __( 'رنگ ستاره دیدگاه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pacc__rvstars' => '--hsw-rvstar: {{VALUE}};' ),
                        )
                );

                $this->add_control(
                        'wl_heading',
                        array( 'label' => __( 'خطوط گارانتی', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );
                $this->add_control(
                        'acc_wl_ic_c',
                        array(
                                'label'     => __( 'رنگ آیکون گارانتی', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::COLOR,
                                'selectors' => array( '{{WRAPPER}} .hsw-pacc__wline .hsw-ic' => '--hsw-wl-ic: {{VALUE}};' ),
                        )
                );

                $this->end_controls_section();
        }

        /**
         * Render the three accordion items (all closed initially).
         */
        protected function render() {
                list( $product, $demo ) = $this->product_or_demo();
                $s = $this->effective_settings();

                /* Never render mock data outside the Elementor editor. */
                if ( ! $product && ! $this->is_demo() ) {
                        return;
                }

                if ( $demo ) {
                        $d       = $this->demo();
                        $specs   = $d['specs'];
                        $reviews = $d['reviews_l'];
                } else {
                        $specs   = $this->specs_rows( $product );
                        $reviews = $this->reviews_list( $product, ! empty( $s['reviews_count2'] ) ? (int) $s['reviews_count2'] : 3 );
                }

                $warranties = ( isset( $s['warranty_items'] ) && is_array( $s['warranty_items'] ) ) ? $s['warranty_items'] : array();

                $t_specs    = isset( $s['acc_specs_title'] ) && '' !== trim( (string) $s['acc_specs_title'] ) ? $s['acc_specs_title'] : __( 'مشخصات فنی کامل', 'hamishop-elementor-widgets' );
                $t_reviews  = isset( $s['acc_reviews_title'] ) && '' !== trim( (string) $s['acc_reviews_title'] ) ? $s['acc_reviews_title'] : __( 'دیدگاه‌ها و نظرات خریداران', 'hamishop-elementor-widgets' );
                $t_warranty = isset( $s['acc_warranty_title'] ) && '' !== trim( (string) $s['acc_warranty_title'] ) ? $s['acc_warranty_title'] : __( 'شرایط گارانتی و ارسال', 'hamishop-elementor-widgets' );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'pacc', 'hsw-m' ) ); ?>">

                        <?php if ( 'yes' === ( $s['acc_specs'] ?? 'yes' ) ) : ?>
                                <div class="hsw-pacc__item">
                                        <button type="button" class="hsw-pacc__head" data-hsw-acc>
                                                <span class="hsw-pacc__title">
                                                        <?php echo HSW_Helpers::icon( 'tune' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        <?php echo esc_html( $t_specs ); ?>
                                                </span>
                                                <?php echo HSW_Helpers::icon( 'expand_more', 'hsw-pacc__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                        </button>
                                        <div class="hsw-pacc__body">
                                                <?php if ( empty( $specs ) ) : ?>
                                                        <p class="hsw-pacc__v"><?php esc_html_e( 'مشخصاتی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                                <?php else : ?>
                                                        <?php foreach ( $specs as $row ) : ?>
                                                                <?php
                                                                $k = isset( $row['k'] ) ? $row['k'] : ( isset( $row[0] ) ? $row[0] : '' );
                                                                $v = isset( $row['v'] ) ? $row['v'] : ( isset( $row[1] ) ? $row[1] : '' );
                                                                ?>
                                                                <div class="hsw-pacc__row">
                                                                        <span class="hsw-pacc__k"><?php echo esc_html( $k ); ?></span>
                                                                        <span class="hsw-pacc__v"><?php echo esc_html( $v ); ?></span>
                                                                </div>
                                                        <?php endforeach; ?>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        <?php endif; ?>

                        <?php if ( 'yes' === ( $s['acc_reviews'] ?? 'yes' ) ) : ?>
                                <div class="hsw-pacc__item" data-hsw-acc-item="reviews">
                                        <button type="button" class="hsw-pacc__head" data-hsw-acc>
                                                <span class="hsw-pacc__title">
                                                        <?php echo HSW_Helpers::icon( 'rate_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        <?php echo esc_html( $t_reviews ); ?>
                                                </span>
                                                <?php echo HSW_Helpers::icon( 'expand_more', 'hsw-pacc__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                        </button>
                                        <div class="hsw-pacc__body">
                                                <?php if ( $demo ) : ?>
                                                        <?php if ( empty( $reviews ) ) : ?>
                                                                <p class="hsw-pacc__rvtext"><?php esc_html_e( 'هنوز دیدگاهی ثبت نشده است.', 'hamishop-elementor-widgets' ); ?></p>
                                                        <?php else : ?>
                                                                <?php foreach ( $reviews as $rv ) : ?>
                                                                        <?php
                                                                        $name   = isset( $rv['name'] ) ? $rv['name'] : '';
                                                                        $text   = isset( $rv['text'] ) ? $rv['text'] : '';
                                                                        $rating = max( 0, min( 5, (int) ( isset( $rv['rating'] ) ? $rv['rating'] : 0 ) ) );
                                                                        ?>
                                                                        <div class="hsw-pacc__rvitem">
                                                                                <div class="hsw-pacc__rvtop">
                                                                                        <span class="hsw-pacc__rvname"><?php echo esc_html( $name ); ?></span>
                                                                                        <span class="hsw-pacc__rvstars">
                                                                                                <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                                                                                                        <?php echo HSW_Helpers::icon( 'star', $star <= $rating ? 'hsw-ic--fill' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                                                                <?php endfor; ?>
                                                                                        </span>
                                                                                </div>
                                                                                <p class="hsw-pacc__rvtext"><?php echo esc_html( $text ); ?></p>
                                                                        </div>
                                                                <?php endforeach; ?>
                                                        <?php endif; ?>
                                                <?php else : ?>
                                                        <?php if ( isset( $_GET['unapproved'] ) && absint( $_GET['unapproved'] ) > 0 ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag from WordPress. ?>
                                                                <p class="hsw-review-pending"><?php esc_html_e( 'دیدگاه شما ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.', 'hamishop-elementor-widgets' ); ?></p>
                                                        <?php endif; ?>
                                                        <?php echo $this->review_cards_html( $product, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                                                        <div class="hsw-pacc__rvform">
                                                                <?php echo $this->review_form_html( $product, 'mobile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- native comment_form markup. ?>
                                                        </div>
                                                <?php endif; ?>
                                        </div>
                                </div>
                        <?php endif; ?>

                        <?php if ( 'yes' === ( $s['acc_warranty'] ?? 'yes' ) ) : ?>
                                <div class="hsw-pacc__item">
                                        <button type="button" class="hsw-pacc__head" data-hsw-acc>
                                                <span class="hsw-pacc__title">
                                                        <?php echo HSW_Helpers::icon( 'local_shipping' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                        <?php echo esc_html( $t_warranty ); ?>
                                                </span>
                                                <?php echo HSW_Helpers::icon( 'expand_more', 'hsw-pacc__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                        </button>
                                        <div class="hsw-pacc__body">
                                                <?php foreach ( $warranties as $item ) : ?>
                                                        <?php
                                                        $w_icon = isset( $item['icon'] ) ? $item['icon'] : 'security';
                                                        $w_text = isset( $item['text'] ) ? $item['text'] : '';
                                                        if ( '' === trim( (string) $w_text ) ) {
                                                                continue;
                                                        }
                                                        ?>
                                                        <div class="hsw-pacc__wline">
                                                                <?php echo HSW_Helpers::icon( $w_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes internally. ?>
                                                                <span><?php echo esc_html( $w_text ); ?></span>
                                                        </div>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        <?php endif; ?>

                </section>
                <?php
        }
}
