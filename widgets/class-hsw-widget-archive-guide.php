<?php
/**
 * Widget: Archive guide — faithful conversion of the supplied design.
 * Desktop: bottom white box with help icon + Q/A card grid (repeater).
 * Mobile: «راهنما» modal (opened from the header widget via
 * data-hsw-open="hsw-guide"): intro + blue tip + accept button.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Archive_Guide extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-guide';
        }

        public function get_title() {
                return __( 'راهنما و سوالات متداول آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-faq';
        }

        public function get_keywords() {
                return array( 'faq', 'guide', 'راهنما', 'سوالات', 'آرشیو', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'g_title',
                        array(
                                'label'       => __( 'عنوان بخش', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'راهنمای خرید و سوالات متداول {name}', 'hamishop-elementor-widgets' ),
                                'description' => __( '{name} با نام دسته‌بندی جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $repeater = new Repeater();
                $repeater->add_control(
                        'q',
                        array(
                                'label'       => __( 'سوال', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'label_block' => true,
                        )
                );
                $repeater->add_control(
                        'a',
                        array(
                                'label'       => __( 'پاسخ', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXTAREA,
                                'label_block' => true,
                        )
                );
                $this->add_control(
                        'faqs',
                        array(
                                'label'       => __( 'سوال و پاسخ‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $repeater->get_controls(),
                                'title_field' => '{{{ q }}}',
                                'default'     => array(
                                        array(
                                                'q' => __( 'تفاوت پک‌های گلوبال، هند و ویتنام چیست؟', 'hamishop-elementor-widgets' ),
                                                'a' => __( 'از نظر کیفیت ساخت قطعات سخت‌افزاری و پردازنده هیچ تفاوتی وجود ندارد و تنها تفاوت در آداپتور شارژر و دفترچه راهنمای منطقه است.', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'q' => __( 'فرآیند رجیستری و گارانتی چگونه است؟', 'hamishop-elementor-widgets' ),
                                                'a' => __( 'تمامی کالاهای خریداری شده دارای کد فعال‌سازی سامانه همتا بوده و ۱۸ ماه گارانتی رسمی شرکت‌های واردکننده معتبر را دارا هستند.', 'hamishop-elementor-widgets' ),
                                        ),
                                ),
                        )
                );

                $this->add_control(
                        'm_intro',
                        array(
                                'label'   => __( 'متن مودال راهنما (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'default' => __( 'آرشیو رسمی محصولات ووکامرس. کلیه کالاها با تضمین اصالت فیزیکی، مهلت تست ۷ روزه و رجیستری معتبر ارائه می‌شوند.', 'hamishop-elementor-widgets' ),
                                'separator' => 'before',
                        )
                );

                $this->add_control(
                        'm_tip',
                        array(
                                'label'   => __( 'نکته آبی مودال', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'default' => __( 'نکته انتخاب کالا: برای کاربری گیمینگ به ظرفیت رم و پردازنده و برای کاربری روزمره به باتری و کیفیت نمایشگر توجه نمایید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'gd', '{{WRAPPER}} .hsw-agd__box' );
                $this->a_text( 'gd_t', '{{WRAPPER}} .hsw-agd__title', array( 'default' => 16 ) );
                $this->a_color( 'gd_ic', __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-agd__title .hsw-ic, {{WRAPPER}} .hsw-agd__gbtn' );
                $this->a_color( 'gd_qbg', __( 'پس‌زمینه کارت سوال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-agd__item', 'background-color' );
                $this->a_color( 'gd_qt', __( 'متن سوال', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-agd__q' );
                $this->a_color( 'gd_qa', __( 'متن پاسخ', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-agd__a' );
                $this->a_color( 'gd_tipbg', __( 'پس‌زمینه نکته (موبایل)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-agd__tip', 'background-color' );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->get_settings_for_display();

                $fmt   = $this->opt( 'g_title', __( 'راهنمای خرید و سوالات متداول {name}', 'hamishop-elementor-widgets' ) );
                $name  = ( $this->is_demo() || ! $this->is_archive_context() ) ? $this->demo()['term'] : ( $this->current_term() ? $this->current_term()->name : '' );
                $title = str_replace( '{name}', $name, $fmt );

                $faqs = ( isset( $s['faqs'] ) && is_array( $s['faqs'] ) ) ? $s['faqs'] : array();
                $intro = isset( $s['m_intro'] ) ? wp_strip_all_tags( $s['m_intro'] ) : '';
                $tip   = isset( $s['m_tip'] ) ? wp_strip_all_tags( $s['m_tip'] ) : '';
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'agd' ) ); ?>">

                        <!-- =================== DESKTOP FAQ BOX =================== -->
                        <section class="hsw-agd__box hsw-d">
                                <h3 class="hsw-agd__title"><i class="hsw-ic" aria-hidden="true">help</i><?php echo esc_html( $title ); ?></h3>
                                <div class="hsw-agd__grid">
                                        <?php foreach ( $faqs as $faq ) : ?>
                                                <?php
                                                $q = isset( $faq['q'] ) ? trim( (string) $faq['q'] ) : '';
                                                $a = isset( $faq['a'] ) ? trim( (string) $faq['a'] ) : '';
                                                if ( '' === $q && '' === $a ) {
                                                        continue;
                                                }
                                                ?>
                                                <div class="hsw-agd__item">
                                                        <?php if ( $q ) : ?><h4 class="hsw-agd__q"><?php echo esc_html( $q ); ?></h4><?php endif; ?>
                                                        <?php if ( $a ) : ?><p class="hsw-agd__a"><?php echo esc_html( $a ); ?></p><?php endif; ?>
                                                </div>
                                        <?php endforeach; ?>
                                </div>
                        </section>

                        <!-- =================== MOBILE GUIDE MODAL =================== -->
                        <div class="hsw-agd__modal hsw-m" data-hsw-sheet="hsw-guide" aria-hidden="true">
                                <div class="hsw-agd__backdrop" data-hsw-close></div>
                                <div class="hsw-agd__panel">
                                        <div class="hsw-agd__mhead">
                                                <h3 class="hsw-agd__mtitle"><?php esc_html_e( 'راهنمای خرید و دسته‌بندی', 'hamishop-elementor-widgets' ); ?></h3>
                                                <button type="button" class="hsw-agd__mclose" data-hsw-close aria-label="<?php esc_attr_e( 'بستن', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">close</i></button>
                                        </div>
                                        <?php if ( $intro ) : ?>
                                                <p class="hsw-agd__mtext"><?php echo esc_html( $intro ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $tip ) : ?>
                                                <div class="hsw-agd__tip"><?php echo esc_html( $tip ); ?></div>
                                        <?php endif; ?>
                                        <?php foreach ( $faqs as $faq ) : ?>
                                                <?php
                                                $q = isset( $faq['q'] ) ? trim( (string) $faq['q'] ) : '';
                                                $a = isset( $faq['a'] ) ? trim( (string) $faq['a'] ) : '';
                                                if ( '' === $q ) {
                                                        continue;
                                                }
                                                ?>
                                                <div class="hsw-agd__mfaq">
                                                        <b><?php echo esc_html( $q ); ?></b>
                                                        <?php if ( $a ) : ?><span><?php echo esc_html( $a ); ?></span><?php endif; ?>
                                                </div>
                                        <?php endforeach; ?>
                                        <button type="button" class="hsw-agd__ok" data-hsw-close><?php esc_html_e( 'متوجه شدم', 'hamishop-elementor-widgets' ); ?></button>
                                </div>
                        </div>

                </div>
                <?php
        }
}
