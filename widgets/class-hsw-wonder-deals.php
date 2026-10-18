<?php
/**
 * Widget: پیشنهاد شگفت‌انگیز (Wonder Deals) — Section 1 of the supplied design.
 * Gradient shell, header (icon + badge + subtitle), countdown pill/boxes,
 * lead banner card, horizontal snap carousel with stock-progress deal cards.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

class HSW_Widget_Wonder_Deals extends HSW_Widget_Base {

        public function get_name() {
                return 'hsw-wonder-deals';
        }

        public function get_title() {
                return __( 'پیشنهاد شگفت‌انگیز (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-fire';
        }

        public function get_keywords() {
                return array( 'شگفت انگیز', 'پیشنهاد ویژه', 'حراج', 'تخفیف', 'wonder', 'deal', 'sale', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->controls_source( 'sale' );
                $this->controls_header(
                        array(
                                'icon'   => 'local_fire_department',
                                'title'  => __( 'پیشنهاد شگفت‌انگیز روز', 'hamishop-elementor-widgets' ),
                                'badge'  => __( 'تخفیف تا ۴۵٪', 'hamishop-elementor-widgets' ),
                                'sub'    => __( 'فرصت طلایی خرید برترین کالاهای دیجیتال با تضمین بهترین قیمت بازار', 'hamishop-elementor-widgets' ),
                                'link_d' => '',
                                'link_m' => '',
                        )
                );
                $this->controls_countdown();
                $this->controls_lead();
                $this->controls_card_content(
                        array(
                                'show_rank'   => false,
                                'price_label' => false,
                                'txt_buy'     => false,
                        )
                );

                /* ------------------------- STYLE ------------------------- */
                $this->controls_shell( 'deal' );
                $this->controls_header_style();
                $this->controls_countdown_style();
                $this->controls_card_style();
                $this->controls_badge_style();
                $this->controls_price_style();
                $this->controls_lead_style();
        }

        /**
         * Countdown end timestamp per settings.
         */
        private function cd_end( $products ) {
                $s = $this->effective_settings();
                if ( 'yes' !== ( $s['show_cd'] ?? 'yes' ) ) {
                        return 0;
                }
                if ( 'manual' === ( $s['cd_mode'] ?? 'auto' ) ) {
                        $ts = strtotime( (string) ( $s['cd_manual'] ?? '' ) );
                        return $ts ? (int) $ts : 0;
                }
                return HSW_Helpers::latest_sale_end( $products );
        }

        protected function render() {
                $s        = $this->effective_settings();
                $products = $this->resolve_products();
                $args     = $this->renderer_args();

                $show_header = 'yes' === ( $s['show_header'] ?? 'yes' );
                $show_lead   = 'yes' === ( $s['show_lead'] ?? 'yes' );
                $cd_end      = $this->cd_end( $products );

                $link_url = $s['hdr_link_url']['url'] ?? '';
                $lead_url = $s['lead_url']['url'] ?? '';
                ?>
                <section class="hsw-loop hsw-loop--deal hsw-loop--hsw-wonder-deals<?php echo esc_attr( $this->hsw_font_class() ); ?>">
                        <div class="hsw-decor hsw-decor--1" aria-hidden="true"></div>
                        <div class="hsw-decor hsw-decor--2" aria-hidden="true"></div>

                        <?php if ( $show_header ) : ?>
                        <header class="hsw-hdr">
                                <div class="hsw-hdr__main">
                                        <div class="hsw-hdr__brand">
                                                <span class="hsw-hdr__bar" aria-hidden="true"></span>
                                                <span class="hsw-hdr__icon hsw-d"><?php echo HSW_Helpers::icon( $s['hdr_icon'] ?? 'local_fire_department' ); // phpcs:ignore ?></span>
                                                <div class="hsw-hdr__titles">
                                                        <div class="hsw-hdr__row">
                                                                <h2 class="hsw-hdr__title"><?php echo esc_html( $s['hdr_title'] ?? '' ); ?></h2>
                                                                <?php if ( ! empty( $s['hdr_badge'] ) ) : ?>
                                                                        <span class="hsw-hdr__badge"><?php echo esc_html( $s['hdr_badge'] ); ?></span>
                                                                <?php endif; ?>
                                                        </div>
                                                        <?php if ( ! empty( $s['hdr_sub'] ) ) : ?>
                                                                <p class="hsw-hdr__sub"><?php echo esc_html( $s['hdr_sub'] ); ?></p>
                                                        <?php endif; ?>
                                                </div>
                                        </div>

                                        <div class="hsw-hdr__side">
                                                <?php if ( $cd_end > 0 ) : ?>
                                                        <div class="hsw-cd hsw-d" data-hsw-end="<?php echo esc_attr( $cd_end ); ?>" data-hsw-fa="1">
                                                                <?php echo HSW_Helpers::icon( 'timer', 'hsw-ic--timer' ); // phpcs:ignore ?>
                                                                <span><?php echo esc_html( $s['cd_label'] ?? '' ); ?></span>
                                                                <span class="hsw-cd__val">۰۰:۰۰:۰۰</span>
                                                        </div>
                                                        <div class="hsw-cd--m hsw-m" data-hsw-end="<?php echo esc_attr( $cd_end ); ?>" data-hsw-fa="1" aria-label="<?php esc_attr_e( 'زمان باقی‌مانده', 'hamishop-elementor-widgets' ); ?>">
                                                                <span class="hsw-cd__box" data-hsw-cd="h">۰۰</span><span class="hsw-cd__sep">:</span><span class="hsw-cd__box" data-hsw-cd="m">۰۰</span><span class="hsw-cd__sep">:</span><span class="hsw-cd__box" data-hsw-cd="s">۰۰</span>
                                                        </div>
                                                <?php endif; ?>

                                                <?php if ( ! empty( $s['hdr_link_m'] ) ) : ?>
                                                        <a class="hsw-hdr__link" href="<?php echo esc_url( $link_url ? $link_url : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '#' ) ); ?>">
                                                                <span class="hsw-d"><?php echo esc_html( $s['hdr_link_d'] ?? '' ); ?></span>
                                                                <span class="hsw-m"><?php echo esc_html( $s['hdr_link_m'] ); ?></span>
                                                                <?php echo HSW_Helpers::icon( 'chevron_left' ); // phpcs:ignore ?>
                                                        </a>
                                                <?php endif; ?>

                                                <div class="hsw-arrows hsw-d">
                                                        <button type="button" class="hsw-arrow" data-hsw-dir="-1" aria-label="<?php esc_attr_e( 'اسلاید قبلی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_right' ); // phpcs:ignore ?></button>
                                                        <button type="button" class="hsw-arrow" data-hsw-dir="1" aria-label="<?php esc_attr_e( 'اسلاید بعدی', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'chevron_left' ); // phpcs:ignore ?></button>
                                                </div>
                                        </div>
                                </div>
                        </header>
                        <?php endif; ?>

                        <div class="hsw-track">
                                <?php if ( $show_lead ) : ?>
                                <div class="hsw-lead">
                                        <div class="hsw-lead__body">
                                                <?php echo HSW_Helpers::icon( $s['lead_icon'] ?? 'local_fire_department', 'hsw-lead__ico' ); // phpcs:ignore ?>
                                                <span class="hsw-lead__title"><?php echo esc_html( $s['lead_title'] ?? '' ); ?></span>
                                                <span class="hsw-lead__sub"><?php echo esc_html( $s['lead_sub'] ?? '' ); ?></span>
                                        </div>
                                        <a class="hsw-lead__btn" href="<?php echo esc_url( $lead_url ? $lead_url : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '#' ) ); ?>">
                                                <span><?php echo esc_html( $s['lead_btn'] ?? '' ); ?></span>
                                                <?php echo HSW_Helpers::icon( 'arrow_back' ); // phpcs:ignore ?>
                                        </a>
                                </div>
                                <?php endif; ?>

                                <?php
                                $cards = self::cards_html( 'deal', $products, $args );
                                if ( '' === $cards ) {
                                        echo '<div class="hsw-empty">' . esc_html__( 'محصولی برای نمایش یافت نشد.', 'hamishop-elementor-widgets' ) . '</div>';
                                } else {
                                        echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
                                }
                                ?>
                        </div>
                </section>
                <?php
        }
}
