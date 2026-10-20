<?php
/**
 * Widget: Archive numeric pagination — faithful conversion of the supplied design.
 * White bar: «صفحه X از Y» + buttons (current = solid). Keeps active filter
 * params in links. Desktop & mobile share one responsive markup.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Archive_Pagination extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-pagination';
        }

        public function get_title() {
                return __( 'صفحه‌بندی آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-pagination';
        }

        public function get_keywords() {
            return array( 'pagination', 'صفحه‌بندی', 'آرشیو', 'hamishop' );
        }

        protected function register_controls() {
                $this->ctrl_palette();

                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'show_state',
                        array(
                                'label'   => __( 'نمایش «صفحه X از Y»', 'hamishop-elementor-widgets' ),
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

                $this->a_box( 'pg', '{{WRAPPER}} .hsw-ap__bar' );
                $this->a_color( 'pg_active', __( 'پس‌زمینه صفحه فعال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ap__bar .page-numbers.current', 'background-color' );
                $this->a_color( 'pg_idle', __( 'متن صفحات دیگر', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ap__bar .page-numbers' );
                $this->a_color( 'pg_state', __( 'متن وضعیت', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ap__state' );

                $this->end_controls_section();
        }

        protected function render() {
                if ( ! $this->is_demo() && ! ( $this->is_archive_context() ) ) {
                        return;
                }

                global $wp_query;
                $s     = $this->effective_settings();
                $total = $this->is_demo() ? 21 : ( $wp_query && $wp_query->max_num_pages ? (int) $wp_query->max_num_pages : 1 );
                $cur   = $this->is_demo() ? 1 : max( 1, (int) get_query_var( 'paged' ) );

                $params = $this->current_params();
                unset( $params['paged'] );

                $links = paginate_links(
                        array(
                                'total'     => $total,
                                'current'   => $cur,
                                'mid_size'  => 2,
                                'type'      => 'array',
                                'prev_next' => true,
                                'prev_text' => '<span class="hsw-sr-only">' . esc_html__( 'صفحه قبل', 'hamishop-elementor-widgets' ) . '</span><i class="hsw-ic" aria-hidden="true">chevron_right</i>',
                                'next_text' => '<span class="hsw-sr-only">' . esc_html__( 'صفحه بعد', 'hamishop-elementor-widgets' ) . '</span><i class="hsw-ic" aria-hidden="true">chevron_left</i>',
                                'format'    => '?paged=%#%',
                                'add_args'  => $params,
                                'base'      => str_replace( PHP_INT_MAX, '%#%', esc_url( add_query_arg( 'paged', PHP_INT_MAX, $this->archive_url() ) ) ),
                        )
                );

                if ( is_array( $links ) ) {
                        $links = array_map(
                                static function ( $link ) {
                                        if ( false !== strpos( $link, 'next page-numbers' ) ) {
                                                return str_replace( '<a ', '<a aria-label="' . esc_attr__( 'صفحه بعد', 'hamishop-elementor-widgets' ) . '" ', $link );
                                        }
                                        if ( false !== strpos( $link, 'prev page-numbers' ) ) {
                                                return str_replace( '<a ', '<a aria-label="' . esc_attr__( 'صفحه قبل', 'hamishop-elementor-widgets' ) . '" ', $link );
                                        }
                                        return $link;
                                },
                                $links
                        );
                }
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ap' ) ); ?>">
                        <?php if ( $total > 1 || $this->is_demo() ) : ?>
                                <div class="hsw-ap__bar">
                                        <?php if ( 'yes' === ( $s['show_state'] ?? 'yes' ) ) : ?>
                                                <span class="hsw-ap__state"><?php echo esc_html( sprintf( __( 'صفحه %1$s از %2$s', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $cur ), HSW_Helpers::fa_num( $total ) ) ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $links ) ) : ?>
                                                <nav class="hsw-ap__links" aria-label="<?php esc_attr_e( 'صفحه‌بندی', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php foreach ( $links as $link ) : ?>
                                                                <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links safe output. ?>
                                                        <?php endforeach; ?>
                                                </nav>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>
                </div>
                <?php
        }
}
