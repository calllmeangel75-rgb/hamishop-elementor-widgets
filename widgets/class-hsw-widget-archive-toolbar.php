<?php
/**
 * Widget: Archive toolbar — faithful conversion of the supplied design.
 * Desktop: sort buttons bar + result count + grid/list view toggles.
 * Mobile: sticky two-button row (filter trigger + sort trigger) + sort sheet.
 * Both: active filter tags row with remove links.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Archive_Toolbar extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-toolbar';
        }

        public function get_title() {
                return __( 'نوار مرتب‌سازی و ابزار آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-sort';
        }

        public function get_keywords() {
                return array( 'sort', 'toolbar', 'مرتب‌سازی', 'نمایش', 'آرشیو', 'hamishop' );
        }

        /**
         * Sort options: [ orderby, label ].
         */
        private function sorts() {
                return array(
                        'popularity' => __( 'پرفروش‌ترین‌ها', 'hamishop-elementor-widgets' ),
                        'date'       => __( 'جدیدترین‌ها', 'hamishop-elementor-widgets' ),
                        'price'      => __( 'ارزان‌ترین', 'hamishop-elementor-widgets' ),
                        'price-desc' => __( 'گران‌ترین', 'hamishop-elementor-widgets' ),
                        'discount'   => __( 'بیشترین تخفیف', 'hamishop-elementor-widgets' ),
                );
        }

        private function current_sort() {
                $p = $this->current_params();
                return isset( $p['orderby'] ) && '' !== $p['orderby'] ? $p['orderby'] : 'popularity';
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'show_tags',
                        array(
                                'label'   => __( 'تگ‌های فیلتر فعال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_view',
                        array(
                                'label'   => __( 'کلیدهای نمای شبکه/لیست', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'tb', '{{WRAPPER}} .hsw-at__bar, {{WRAPPER}} .hsw-at__mrow' );
                $this->a_color( 'tb_active_bg', __( 'پس‌زمینه مرتب‌سازی فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__sort.is-active', 'background-color' );
                $this->a_color( 'tb_active_tx', __( 'متن مرتب‌سازی فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__sort.is-active' );
                $this->a_color( 'tb_idle', __( 'متن مرتب‌سازی عادی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__sort' );
                $this->a_color( 'tb_ic', __( 'رنگ آیکون‌ها', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__bar .hsw-ic, {{WRAPPER}} .hsw-at__mrow .hsw-ic' );
                $this->a_text( 'tb_count', '{{WRAPPER}} .hsw-at__count', array( 'default' => 12, 'weight' => false ) );
                $this->a_color( 'tb_tag_bg', __( 'پس‌زمینه تگ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__tag', 'background-color' );
                $this->a_color( 'tb_tag_bd', __( 'حاشیه تگ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__tag', 'border-color' );
                $this->a_color( 'tb_tag_tx', __( 'متن تگ فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-at__tag' );

                $this->end_controls_section();
        }

        protected function render() {
                if ( ! $this->is_demo() && ! $this->is_archive_context() ) {
                        return;
                }

                $sorts   = $this->sorts();
                $current = $this->current_sort();
                $tags    = ( 'yes' === ( $this->effective_settings()['show_tags'] ?? 'yes' ) ) ? $this->active_filter_tags() : array();
                $active  = $this->active_filter_count();

                /* Clear-all: every known filter param + every filter_* param present. */
                $clear = array(
                        'instock'       => null,
                        'express'       => null,
                        'onsale'        => null,
                        'rating_filter' => null,
                        'min_price'     => null,
                        'max_price'     => null,
                );
                foreach ( array_keys( $this->current_params() ) as $key ) {
                        if ( 0 === strpos( $key, 'filter_' ) ) {
                                $clear[ $key ] = null;
                        }
                }

                $show_view = ( 'yes' === ( $this->effective_settings()['show_view'] ?? 'yes' ) );
                $view      = isset( $this->current_params()['view'] ) ? $this->current_params()['view'] : 'grid';

                $from  = 0;
                $found = 0;
                if ( ! $this->is_demo() && $this->is_archive_context() ) {
                        global $wp_query;
                        $found = (int) $wp_query->found_posts;
                        $per   = (int) $wp_query->get( 'posts_per_page' );
                        $paged = max( 1, (int) get_query_var( 'paged' ) );
                        $from  = $found ? ( ( $paged - 1 ) * $per + 1 ) : 0;
                        $to    = min( $found, $paged * $per );
                } else {
                        $found = $this->demo()['count'];
                        $to    = 12;
                }
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'at' ) ); ?>">

                        <!-- =================== DESKTOP BAR =================== -->
                        <div class="hsw-at__bar hsw-d">
                                <div class="hsw-at__sorts">
                                        <span class="hsw-at__sortlabel"><?php echo HSW_Helpers::icon( 'filter_list' ); ?><?php esc_html_e( 'مرتب‌سازی:', 'hamishop-elementor-widgets' ); ?></span>
                                        <?php foreach ( $sorts as $key => $label ) : ?>
                                                <a class="hsw-at__sort<?php echo $key === $current ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( array( 'orderby' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
                                        <?php endforeach; ?>
                                </div>
                                <div class="hsw-at__side">
                                        <span class="hsw-at__count"><?php echo esc_html( sprintf( __( 'نمایش %1$s تا %2$s از %3$s کالا', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $from ), HSW_Helpers::fa_num( $to ), HSW_Helpers::fa_num( $found ) ) ); ?></span>
                                        <?php if ( $show_view ) : ?>
                                                <span class="hsw-at__views">
                                                        <a class="hsw-at__view is-active" href="<?php echo esc_url( $this->filter_url( array( 'view' => null ) ) ); ?>" title="<?php esc_attr_e( 'نمای شبکه‌ای', 'hamishop-elementor-widgets' ); ?>" aria-label="<?php esc_attr_e( 'نمای شبکه‌ای', 'hamishop-elementor-widgets' ); ?>">
                                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><rect x="0" y="0" width="6" height="6" rx="1.4"/><rect x="8" y="0" width="6" height="6" rx="1.4"/><rect x="0" y="8" width="6" height="6" rx="1.4"/><rect x="8" y="8" width="6" height="6" rx="1.4"/></svg>
                                                        </a>
                                                        <a class="hsw-at__view<?php echo 'list' === $view ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( array( 'view' => 'list' ) ) ); ?>" title="<?php esc_attr_e( 'نمای لیستی', 'hamishop-elementor-widgets' ); ?>" aria-label="<?php esc_attr_e( 'نمای لیستی', 'hamishop-elementor-widgets' ); ?>">
                                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><rect x="0" y="1" width="14" height="2.4" rx="1.2"/><rect x="0" y="5.8" width="14" height="2.4" rx="1.2"/><rect x="0" y="10.6" width="14" height="2.4" rx="1.2"/></svg>
                                                        </a>
                                                </span>
                                        <?php endif; ?>
                                </div>
                        </div>

                        <!-- =================== MOBILE STICKY ROW =================== -->
                        <div class="hsw-at__mrow hsw-m">
                                <button type="button" class="hsw-at__mbtn" data-hsw-open="hsw-filter">
                                        <?php echo HSW_Helpers::icon( 'tune' ); ?>
                                        <span><?php esc_html_e( 'فیلتر پیشرفته', 'hamishop-elementor-widgets' ); ?></span>
                                        <?php if ( $active ) : ?>
                                                <b class="hsw-at__mbadge"><?php echo esc_html( HSW_Helpers::fa_num( $active ) ); ?></b>
                                        <?php endif; ?>
                                </button>
                                <button type="button" class="hsw-at__mbtn" data-hsw-open="hsw-sort">
                                        <?php echo HSW_Helpers::icon( 'filter_list' ); ?>
                                        <span><?php esc_html_e( 'مرتب‌سازی', 'hamishop-elementor-widgets' ); ?></span>
                                        <small class="hsw-at__mcur">(<?php echo esc_html( isset( $sorts[ $current ] ) ? $sorts[ $current ] : '' ); ?>)</small>
                                </button>
                        </div>

                        <!-- =================== MOBILE SORT SHEET =================== -->
                        <?php echo HSW_Assets::sheet_guard(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal style tag. ?>
                        <div class="hsw-asheet hsw-asheet--sort hsw-m" data-hsw-sheet="hsw-sort" aria-hidden="true">
                                <div class="hsw-asheet__backdrop" data-hsw-close></div>
                                <div class="hsw-asheet__panel hsw-asheet__panel--sort">
                                        <div class="hsw-asheet__head">
                                                <span class="hsw-asheet__ttl"><?php esc_html_e( 'مرتب‌سازی بر اساس:', 'hamishop-elementor-widgets' ); ?></span>
                                                <button type="button" class="hsw-asheet__close" data-hsw-close aria-label="<?php esc_attr_e( 'بستن', 'hamishop-elementor-widgets' ); ?>"><?php echo HSW_Helpers::icon( 'close' ); ?></button>
                                        </div>
                                        <div class="hsw-asheet__opts">
                                                <?php foreach ( $sorts as $key => $label ) : ?>
                                                        <a class="hsw-asheet__opt<?php echo $key === $current ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->filter_url( array( 'orderby' => $key ) ) ); ?>">
                                                                <span><?php echo esc_html( $label ); ?></span>
                                                                <?php if ( $key === $current ) : ?>
                                                                        <?php echo HSW_Helpers::icon( 'check_circle' ); ?>
                                                                <?php endif; ?>
                                                        </a>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </div>

                        <!-- =================== ACTIVE TAGS (BOTH) =================== -->
                        <?php if ( ! empty( $tags ) ) : ?>
                                <div class="hsw-at__tags">
                                        <span class="hsw-at__tagslabel hsw-d"><?php esc_html_e( 'فیلترهای فعال:', 'hamishop-elementor-widgets' ); ?></span>
                                        <?php foreach ( $tags as $tag ) : ?>
                                                <a class="hsw-at__tag" href="<?php echo esc_url( $tag['url'] ); ?>">
                                                        <span><?php echo esc_html( $tag['label'] ); ?></span>
                                                        <?php echo HSW_Helpers::icon( 'close' ); ?>
                                                </a>
                                        <?php endforeach; ?>
                                        <a class="hsw-at__tagclear" href="<?php echo esc_url( $this->filter_url( $clear ) ); ?>"><?php esc_html_e( 'پاک‌کردن', 'hamishop-elementor-widgets' ); ?></a>
                                </div>
                        <?php endif; ?>

                </div>
                <?php
        }
}
