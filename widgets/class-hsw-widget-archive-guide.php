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
                                                'q' => __( 'هزینه ارسال چقدر است و چند روزه به دستم می‌رسد؟', 'hamishop-elementor-widgets' ),
                                                'a' => __( 'هزینه و زمان ارسال براساس آدرس شما در مرحله پرداخت شفاف نمایش داده می‌شود. سفارش‌های دارای نشان «ارسال فوری» همان روز و سایر سفارش‌ها حداکثر طی ۱ تا ۳ روز کاری تحویل می‌شوند.', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'q' => __( 'اصالت و گارانتی کالاها چگونه تضمین می‌شود؟', 'hamishop-elementor-widgets' ),
                                                'a' => __( 'همه کالاها با فاکتور رسمی و ضمانت اصالت عرضه می‌شوند. مدت و نوع گارانتی در جدول مشخصات هر محصول درج شده است.', 'hamishop-elementor-widgets' ),
                                        ),
                                        array(
                                                'q' => __( 'شرایط مرجوعی و تعویض کالا چیست؟', 'hamishop-elementor-widgets' ),
                                                'a' => __( 'تا ۷ روز پس از تحویل، در صورت سالم بودن بسته‌بندی و کالا، امکان مرجوعی یا تعویض وجود دارد؛ کافی است از بخش سفارش‌های حساب کاربری درخواست خود را ثبت کنید.', 'hamishop-elementor-widgets' ),
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
                                'default' => __( 'نکته انتخاب کالا: پیش از خرید، مشخصات، گارانتی و شرایط ارسال محصول را با نیاز خود مقایسه کنید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc_on',
                        array(
                                'label'     => __( 'نمایش بلوک «توضیحات آرشیو» در پایین صفحه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                                'separator' => 'before',
                                'description' => __( 'متن مرتبط با همین دسته‌بندی به‌صورت خودکار از توضیحات دسته (یا برگه فروشگاه) خوانده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'desc_title',
                        array(
                                'label'       => __( 'عنوان بخش توضیحات', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'دربارهٔ {name}', 'hamishop-elementor-widgets' ),
                                'description' => __( '{name} با نام دسته‌بندی/آرشیو جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'desc_on' => 'yes' ),
                        )
                );

                $this->add_control(
                        'desc_custom',
                        array(
                                'label'       => __( 'متن توضیحات (خالی = توضیح دسته/برگه فروشگاه)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::WYSIWYG,
                                'default'     => '',
                                'description' => __( 'برای هر آرشیو، متن همان دسته استفاده می‌شود؛ فقط اگر می‌خواهید همه آرشیوها یک متن ثابت داشته باشند اینجا بنویسید.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'desc_on' => 'yes' ),
                        )
                );

                $this->add_control(
                        'faq_schema',
                        array(
                                'label'     => __( 'اسکیمای سوالات متداول (FAQ JSON-LD)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::SWITCHER,
                                'default'   => 'yes',
                                'separator' => 'before',
                                'description' => __( 'برای گوگل، سوال و پاسخ‌های همین بخش را به‌صورت داده ساخت‌یافته اضافه می‌کند.', 'hamishop-elementor-widgets' ),
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
                $this->a_box( 'gdd', '{{WRAPPER}} .hsw-agd__descbox', array( 'border' => false ) );
                $this->a_text( 'gdd_t', '{{WRAPPER}} .hsw-agd__dtitle', array( 'default' => 16 ) );
                $this->a_text( 'gdd_b', '{{WRAPPER}} .hsw-agd__dbody', array( 'default' => 13, 'weight' => false ) );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->effective_settings();

                $fmt   = $this->opt( 'g_title', __( 'راهنمای خرید و سوالات متداول {name}', 'hamishop-elementor-widgets' ) );
                $name  = $this->archive_name();
                $title = str_replace( '{name}', $name, $fmt );

                $faqs = ( isset( $s['faqs'] ) && is_array( $s['faqs'] ) ) ? $s['faqs'] : array();
                $rows = array();
                foreach ( $faqs as $faq ) {
                        $q = isset( $faq['q'] ) ? trim( (string) $faq['q'] ) : '';
                        $a = isset( $faq['a'] ) ? trim( (string) $faq['a'] ) : '';
                        if ( '' === $q && '' === $a ) {
                                continue;
                        }
                        $rows[] = array( 'q' => $q, 'a' => $a );
                }

                $intro = isset( $s['m_intro'] ) ? wp_strip_all_tags( $s['m_intro'] ) : '';
                $tip   = isset( $s['m_tip'] ) ? wp_strip_all_tags( $s['m_tip'] ) : '';

                /* Bottom archive description (dynamic per category/archive). */
                $desc_on    = ! isset( $s['desc_on'] ) || 'yes' === $s['desc_on'];
                $desc       = $desc_on ? $this->archive_desc_html( isset( $s['desc_custom'] ) ? (string) $s['desc_custom'] : '' ) : '';
                $desc_plain = trim( wp_strip_all_tags( $desc ) );
                $desc_long  = mb_strlen( $desc_plain ) > 200;
                $desc_title = str_replace( '{name}', $name, $this->opt( 'desc_title', __( 'دربارهٔ {name}', 'hamishop-elementor-widgets' ) ) );

                $schema = ( ! isset( $s['faq_schema'] ) || 'yes' === $s['faq_schema'] ) && ! $this->is_demo() && ! empty( $rows );
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'agd' ) ); ?>">

                        <!-- =================== DESKTOP FAQ BOX =================== -->
                        <section class="hsw-agd__box hsw-d">
                                <h3 class="hsw-agd__title"><i class="hsw-ic" aria-hidden="true">help</i><?php echo esc_html( $title ); ?></h3>
                                <div class="hsw-agd__grid">
                                        <?php foreach ( $rows as $row ) : ?>
                                                <div class="hsw-agd__item">
                                                        <?php if ( $row['q'] ) : ?><h4 class="hsw-agd__q"><?php echo esc_html( $row['q'] ); ?></h4><?php endif; ?>
                                                        <?php if ( $row['a'] ) : ?><p class="hsw-agd__a"><?php echo esc_html( $row['a'] ); ?></p><?php endif; ?>
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
                                        <?php foreach ( $rows as $row ) : ?>
                                                <?php if ( '' === $row['q'] ) : ?>
                                                        <?php continue; ?>
                                                <?php endif; ?>
                                                <div class="hsw-agd__mfaq">
                                                        <b><?php echo esc_html( $row['q'] ); ?></b>
                                                        <?php if ( $row['a'] ) : ?><span><?php echo esc_html( $row['a'] ); ?></span><?php endif; ?>
                                                </div>
                                        <?php endforeach; ?>
                                        <button type="button" class="hsw-agd__ok" data-hsw-close><?php esc_html_e( 'متوجه شدم', 'hamishop-elementor-widgets' ); ?></button>
                                </div>
                        </div>

                        <?php if ( $desc_on && '' !== trim( $desc ) ) : ?>
                                <!-- =================== ARCHIVE DESCRIPTION (SEO) =================== -->
                                <section class="hsw-agd__descbox">
                                        <h2 class="hsw-agd__dtitle"><?php echo esc_html( $desc_title ); ?></h2>
                                        <div class="hsw-agd__dbody<?php echo $desc_long ? ' is-clamped' : ''; ?>" data-hsw-seo-target><?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by archive_desc_html(). ?></div>
                                        <?php if ( $desc_long ) : ?>
                                                <button type="button" class="hsw-agd__dmore" data-hsw-seo>
                                                        <span><?php esc_html_e( 'مشاهده بیشتر', 'hamishop-elementor-widgets' ); ?></span>
                                                        <i class="hsw-ic" aria-hidden="true">expand_more</i>
                                                </button>
                                        <?php endif; ?>
                                </section>
                        <?php endif; ?>

                        <?php if ( $schema ) : ?>
                                <?php $json = $this->faq_schema_json( $rows ); ?>
                                <?php if ( '' !== $json ) : ?>
                                        <script type="application/ld+json"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encoded JSON-LD. ?></script>
                                <?php endif; ?>
                        <?php endif; ?>

                </div>
                <?php
        }

        /**
         * FAQPage JSON-LD for the rendered questions.
         *
         * @param array $rows [ q, a ] rows.
         * @return string
         */
        protected function faq_schema_json( $rows ) {
                $items = array();
                foreach ( $rows as $row ) {
                        if ( '' === $row['q'] || '' === $row['a'] ) {
                                continue;
                        }
                        $items[] = array(
                                '@type'          => 'Question',
                                'name'           => wp_strip_all_tags( $row['q'] ),
                                'acceptedAnswer' => array(
                                        '@type' => 'Answer',
                                        'text'  => wp_strip_all_tags( $row['a'] ),
                                ),
                        );
                }
                if ( empty( $items ) ) {
                        return '';
                }
                $json = wp_json_encode(
                        array(
                                '@context'   => 'https://schema.org',
                                '@type'      => 'FAQPage',
                                'mainEntity' => $items,
                        ),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                return $json ? str_replace( '</', '<\/', $json ) : '';
        }
}
