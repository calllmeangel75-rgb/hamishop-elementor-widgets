<?php
/**
 * Widget: Delivery day & time slots — faithful conversion of the mock.
 * Desktop: boxed card, 4-col day grid (meta/day/weekday + full-capacity
 * state) and 3-col radio slot grid with part-of-day tags.
 * Mobile: horizontal scrollable day pills + 2-col radio slots.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Checkout_Schedule extends HSW_Checkout_Base {

        public function get_name() {
                return 'hsw-checkout-schedule';
        }

        public function get_title() {
                return __( 'زمان و روز تحویل (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-calendar';
        }

        public function get_keywords() {
                return array( 'checkout', 'delivery time', 'زمان تحویل', 'بازه', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                /* Master off switch: «خاموش» hides the whole delivery section
                 * (the order note then carries no «زمان تحویل» line). */
                if ( method_exists( $this, 'update_control' ) ) {
                        $this->update_control(
                                'vis',
                                array(
                                        'options' => array(
                                                'auto'    => __( 'پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
                                                'desktop' => __( 'فقط دسکتاپ', 'hamishop-elementor-widgets' ),
                                                'mobile'  => __( 'فقط موبایل', 'hamishop-elementor-widgets' ),
                                                'off'     => __( 'خاموش (بدون نمایش)', 'hamishop-elementor-widgets' ),
                                        ),
                                )
                        );
                }

                $this->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب زمان و روز تحویل', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title_m',
                        array(
                                'label'   => __( 'عنوان (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'زمان‌بندی تحویل مرسوله', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'hint_m',
                        array(
                                'label'   => __( 'راهنمای موبایل', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'سریع‌ترین زمان ممکن', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'days_label',
                        array(
                                'label'   => __( 'برچسب روز تحویل (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'روز تحویل سفارش:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'slots_label',
                        array(
                                'label'   => __( 'برچسب بازه ساعت (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'بازه زمانی ساعت تحویل:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'slots_label_m',
                        array(
                                'label'   => __( 'برچسب بازه ساعت (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب بازه زمانی تحویل:', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'days_heading',
                        array( 'label' => __( 'روزها', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $days = new Repeater();
                $days->add_control(
                        'meta',
                        array(
                                'label'       => __( 'سطر بالا (امروز/فردا/تاریخ)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => 'امروز',
                        )
                );
                $days->add_control(
                        'day',
                        array(
                                'label'   => __( 'روز (متن اصلی)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => '۲۴ دی',
                        )
                );
                $days->add_control(
                        'weekday',
                        array(
                                'label'   => __( 'سطر پایین (روز هفته)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'سه‌شنبه',
                        )
                );
                $days->add_control(
                        'tag',
                        array(
                                'label'   => __( 'نشان (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'description' => __( 'مثل «ظرفیت تکمیل» — خودکار روز را غیرفعال می‌کند.', 'hamishop-elementor-widgets' ),
                        )
                );
                $this->add_control(
                        'days',
                        array(
                                'label'       => __( 'روزها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $days->get_controls(),
                                'title_field' => '{{{ day }}}',
                                'default'     => array(
                                        array( 'meta' => 'امروز', 'day' => '۲۴ دی', 'weekday' => 'سه‌شنبه', 'tag' => '' ),
                                        array( 'meta' => 'فردا', 'day' => '۲۵ دی', 'weekday' => 'چهارشنبه', 'tag' => '' ),
                                        array( 'meta' => 'پس‌فردا', 'day' => '۲۶ دی', 'weekday' => 'پنج‌شنبه', 'tag' => '' ),
                                        array( 'meta' => '۲۸ دی', 'day' => 'شنبه', 'weekday' => 'ظرفیت تکمیل', 'tag' => 'ظرفیت تکمیل' ),
                                ),
                        )
                );

                $this->add_control(
                        'day_active',
                        array(
                                'label'   => __( 'روز انتخاب‌شده (شماره)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 1,
                                'min'     => 1,
                        )
                );

                $this->add_control(
                        'slots_heading',
                        array( 'label' => __( 'بازه‌های ساعت', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $slots = new Repeater();
                $slots->add_control(
                        'label',
                        array(
                                'label'   => __( 'بازه ساعت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'ساعت ۹ تا ۱۳',
                        )
                );
                $slots->add_control(
                        'tag',
                        array(
                                'label'   => __( 'برچسب (صبح/عصر/شبانه)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'صبح',
                        )
                );
                $this->add_control(
                        'slots',
                        array(
                                'label'       => __( 'بازه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $slots->get_controls(),
                                'title_field' => '{{{ label }}}',
                                'default'     => array(
                                        array( 'label' => 'ساعت ۹ تا ۱۳', 'tag' => 'صبح' ),
                                        array( 'label' => 'ساعت ۱۳ تا ۱۷', 'tag' => 'عصر' ),
                                        array( 'label' => 'ساعت ۱۷ تا ۲۱', 'tag' => 'شبانه' ),
                                ),
                        )
                );

                $this->add_control(
                        'slot_active',
                        array(
                                'label'   => __( 'بازه انتخاب‌شده (شماره)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::NUMBER,
                                'default' => 3,
                                'min'     => 1,
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-cksched__card' );
                $this->a_color( 'hd_ic', __( 'آیکن عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksched__hd .hsw-ic' );
                $this->a_text( 'hd_tx', '{{WRAPPER}} .hsw-cksched__hd-t', array( 'default' => 16 ) );
                $this->a_text( 'grp_lbl', '{{WRAPPER}} .hsw-cksched__grp-lbl', array( 'default' => 13 ) );
                $this->a_color( 'day_on', __( 'روز انتخاب‌شده (متن)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksched__day.is-sel' );
                $this->a_color( 'day_on_bg', __( 'پس‌زمینه روز انتخاب‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksched__day.is-sel', 'background-color' );
                $this->a_color( 'slot_on', __( 'بازه انتخاب‌شده (متن)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-cksched__slot.is-sel' );

                $this->end_controls_section();
        }

        protected function render() {
                $s = $this->effective_settings();

                $title   = $this->opt( 'title', __( 'انتخاب زمان و روز تحویل', 'hamishop-elementor-widgets' ) );
                $title_m = $this->opt( 'title_m', __( 'زمان‌بندی تحویل مرسوله', 'hamishop-elementor-widgets' ) );

                $days  = ( isset( $s['days'] ) && is_array( $s['days'] ) && ! empty( $s['days'] ) ) ? $s['days'] : $this->demo_days();
                $slots = ( isset( $s['slots'] ) && is_array( $s['slots'] ) && ! empty( $s['slots'] ) ) ? $s['slots'] : $this->demo_slots();

                $day_on  = max( 1, (int) $this->opt( 'day_active', '1' ) );
                $slot_on = max( 1, (int) $this->opt( 'slot_active', '3' ) );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'cksched' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <article class="hsw-cksched__card hsw-d">
                                <div class="hsw-cksched__hd">
                                        <?php echo HSW_Helpers::icon( 'calendar_month', true ); ?>
                                        <h2 class="hsw-cksched__hd-t"><?php echo esc_html( $title ); ?></h2>
                                </div>

                                <div class="hsw-cksched__grp">
                                        <span class="hsw-cksched__grp-lbl"><?php echo esc_html( $this->opt( 'days_label', __( 'روز تحویل سفارش:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        <div class="hsw-cksched__days" data-hswk-days>
                                                <?php foreach ( $days as $i => $d ) : ?>
                                                        <?php
                                                        $disabled = ( '' !== trim( (string) ( isset( $d['tag'] ) ? $d['tag'] : '' ) ) ) || ( isset( $d['disabled'] ) && 'yes' === $d['disabled'] );
                                                        ?>
                                                        <?php if ( $disabled ) : ?>
                                                                <div class="hsw-cksched__day is-off">
                                                                        <span class="hsw-cksched__day-meta"><?php echo esc_html( $d['meta'] ); ?></span>
                                                                        <span class="hsw-cksched__day-t"><?php echo esc_html( $d['day'] ); ?></span>
                                                                        <span class="hsw-cksched__day-tag"><?php echo esc_html( $d['tag'] ); ?></span>
                                                                </div>
                                                        <?php else : ?>
                                                                <button type="button" class="hsw-cksched__day<?php echo ( ( $i + 1 ) === $day_on ) ? ' is-sel' : ''; ?>">
                                                                        <span class="hsw-cksched__day-meta"><?php echo esc_html( $d['meta'] ); ?></span>
                                                                        <span class="hsw-cksched__day-t"><?php echo esc_html( $d['day'] ); ?></span>
                                                                        <span class="hsw-cksched__day-wd"><?php echo esc_html( $d['weekday'] ); ?></span>
                                                                </button>
                                                        <?php endif; ?>
                                                <?php endforeach; ?>
                                        </div>
                                </div>

                                <div class="hsw-cksched__grp">
                                        <span class="hsw-cksched__grp-lbl"><?php echo esc_html( $this->opt( 'slots_label', __( 'بازه زمانی ساعت تحویل:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        <div class="hsw-cksched__slots" data-hswk-slots>
                                                <?php foreach ( $slots as $i => $sl ) : ?>
                                                        <label class="hsw-cksched__slot<?php echo ( ( $i + 1 ) === $slot_on ) ? ' is-sel' : ''; ?>">
                                                                <span class="hsw-cksched__slot-r">
                                                                        <input type="radio" class="hsw-cksched__input" name="hsw_delivery_slot_d" value="<?php echo esc_attr( $i + 1 ); ?>"<?php checked( ( $i + 1 ) === $slot_on ); ?> />
                                                                        <span class="hsw-cksched__slot-t"><?php echo esc_html( $sl['label'] ); ?></span>
                                                                </span>
                                                                <?php if ( '' !== trim( (string) ( isset( $sl['tag'] ) ? $sl['tag'] : '' ) ) ) : ?>
                                                                        <span class="hsw-cksched__slot-tag"><?php echo esc_html( $sl['tag'] ); ?></span>
                                                                <?php endif; ?>
                                                        </label>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </article>

                        <!-- =================== MOBILE =================== -->
                        <section class="hsw-cksched__mcard hsw-m">
                                <div class="hsw-cksched__mhd">
                                        <h2 class="hsw-cksched__hd-t hsw-cksched__hd-t--m">
                                                <?php echo HSW_Helpers::icon( 'calendar_month' ); ?>
                                                <?php echo esc_html( $title_m ); ?>
                                        </h2>
                                        <?php if ( '' !== trim( $this->opt( 'hint_m', '' ) ) ) : ?>
                                                <span class="hsw-cksched__hint"><?php echo esc_html( $this->opt( 'hint_m', '' ) ); ?></span>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-cksched__mdays" data-hswk-days>
                                        <?php foreach ( $days as $i => $d ) : ?>
                                                <?php
                                                $disabled = ( '' !== trim( (string) ( isset( $d['tag'] ) ? $d['tag'] : '' ) ) ) || ( isset( $d['disabled'] ) && 'yes' === $d['disabled'] );
                                                if ( $disabled ) {
                                                        continue;
                                                }
                                                ?>
                                                <button type="button" class="hsw-cksched__mday<?php echo ( ( $i + 1 ) === $day_on ) ? ' is-sel' : ''; ?>">
                                                        <span class="hsw-cksched__day-meta"><?php echo esc_html( $d['meta'] ); ?></span>
                                                        <span class="hsw-cksched__day-t"><?php echo esc_html( $d['day'] ); ?></span>
                                                </button>
                                        <?php endforeach; ?>
                                </div>

                                <div class="hsw-cksched__grp">
                                        <span class="hsw-cksched__grp-lbl"><?php echo esc_html( $this->opt( 'slots_label_m', __( 'انتخاب بازه زمانی تحویل:', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        <div class="hsw-cksched__mslots" data-hswk-slots>
                                                <?php foreach ( $slots as $i => $sl ) : ?>
                                                        <label class="hsw-cksched__slot hsw-cksched__slot--m<?php echo ( ( $i + 1 ) === $slot_on ) ? ' is-sel' : ''; ?>">
                                                                <span><?php echo esc_html( $sl['label'] ); ?></span>
                                                                <input type="radio" class="hsw-cksched__input" name="hsw_delivery_slot_m" value="<?php echo esc_attr( $i + 1 ); ?>"<?php checked( ( $i + 1 ) === $slot_on ); ?> />
                                                        </label>
                                                <?php endforeach; ?>
                                        </div>
                                </div>
                        </section>

                </section>
                <?php
        }
}
