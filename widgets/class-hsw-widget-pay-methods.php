<?php
/**
 * Widget: Payment methods selection — faithful conversion of the mock.
 * Desktop: «انتخاب روش پرداخت» — online gateway card (Shaparak note +
 * توصیه شده ribbon + bank-gateway grid), BNPL option with installments
 * note, wallet-deduction row with a switch.
 * Mobile: wallet balance card (toggle) + «شیوه پرداخت» option cards with
 * the compact bank chips grid.
 * Live: real WooCommerce gateways (session chosen_payment_method) — the
 * wallet row/switch and bank chips come from the repeater/controls.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class HSW_Widget_Pay_Methods extends HSW_Pay_Base {

        public function get_name() {
                return 'hsw-pay-methods';
        }

        public function get_title() {
                return __( 'روش‌های پرداخت (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-button';
        }

        public function get_keywords() {
                return array( 'checkout', 'payment', 'gateway', 'wallet', 'bnpl', 'پرداخت', 'درگاه', 'کیف پول', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'source',
                        array(
                                'label'       => __( 'منبع درگاه‌ها', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'default'     => 'live',
                                'options'     => array(
                                        'live' => __( 'درگاه‌های واقعی ووکامرس', 'hamishop-elementor-widgets' ),
                                        'demo' => __( 'نمایش پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
                                ),
                                'description' => __( 'حالت طرح، همان سه گزینه و درگاه‌های بانکی نمونه است.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'title',
                        array(
                                'label'   => __( 'عنوان بخش', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'انتخاب روش پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'shaparak_note',
                        array(
                                'label'   => __( 'برچسب شاپرک (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'کلیه تراکنش‌ها در بستر شاپرک رمزنگاری می‌شوند', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_title',
                        array(
                                'label'   => __( 'عنوان بخش (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'شیوه پرداخت', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'ribbon_text',
                        array(
                                'label'   => __( 'برچسب گوشه درگاه آنلاین', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'توصیه شده', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'm_badge_text',
                        array(
                                'label'   => __( 'نشان درگاه آنلاین (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'پیشنهادی', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'banks_heading',
                        array( 'label' => __( 'درگاه‌های بانکی (داخل درگاه آنلاین)', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $bank = new Repeater();
                $bank->add_control(
                        'label',
                        array(
                                'label'   => __( 'نام (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'بانک',
                        )
                );
                $bank->add_control(
                        'label_m',
                        array(
                                'label'       => __( 'نام (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'description' => __( 'خالی = در موبایل نمایش داده نمی‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );
                $bank->add_control(
                        'icon',
                        array(
                                'label'   => __( 'آیکن', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'account_balance',
                                'options' => self::bank_icon_choices(),
                        )
                );
                $bank->add_control(
                        'tag',
                        array(
                                'label'   => __( 'برچسب وضعیت (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => 'پایداری عالی',
                        )
                );
                $bank->add_control(
                        'tag_on',
                        array(
                                'label'   => __( 'برچسب سبز (پایداری بالا)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => '',
                        )
                );
                $bank->add_control(
                        'chosen',
                        array(
                                'label'   => __( 'انتخاب‌شده پیش‌فرض', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => '',
                        )
                );
                $this->add_control(
                        'banks',
                        array(
                                'label'       => __( 'درگاه‌های بانکی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::REPEATER,
                                'fields'      => $bank->get_controls(),
                                'title_field' => '{{{ label }}}',
                                'default'     => array(
                                        array( 'label' => 'به‌پرداخت ملت', 'label_m' => 'بانک سامان', 'icon' => 'account_balance', 'tag' => 'پایداری ۹۹.۸٪', 'tag_on' => 'yes', 'chosen' => 'yes' ),
                                        array( 'label' => 'سامان کیش (SEP)', 'label_m' => 'به‌پرداخت ملت', 'icon' => 'account_balance_wallet', 'tag' => 'پایداری عالی' ),
                                        array( 'label' => 'بانک پاسارگاد', 'label_m' => 'سداد ملی', 'icon' => 'credit_card', 'tag' => 'پایداری عالی' ),
                                        array( 'label' => 'زرین‌پال هوشمند', 'label_m' => '', 'icon' => 'shield', 'tag' => 'تراکنش امن' ),
                                ),
                        )
                );

                $this->add_control(
                        'wallet_heading',
                        array( 'label' => __( 'کیف پول', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_wallet',
                        array(
                                'label'   => __( 'نمایش گزینه کسر از کیف پول', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'wallet_title',
                        array(
                                'label'     => __( 'عنوان (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'کسر از کیف پول اختصاصی', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_label',
                        array(
                                'label'     => __( 'برچسب موجودی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'موجودی: ۲۵۰,۰۰۰ تومان', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_desc',
                        array(
                                'label'     => __( 'توضیح (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXTAREA,
                                'default'   => __( 'موجودی کیف پول از فاکتور نهایی به صورت خودکار کسر می‌گردد.', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_title_m',
                        array(
                                'label'     => __( 'عنوان کارت موبایل', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'موجودی کیف پول', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_label_m',
                        array(
                                'label'     => __( 'برچسب موجودی (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( '۳۵۰,۰۰۰ تومان', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_desc_m',
                        array(
                                'label'     => __( 'توضیح (موبایل)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXTAREA,
                                'default'   => __( 'کسر خودکار موجودی از فاکتور نهایی', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->add_control(
                        'wallet_switch',
                        array(
                                'label'     => __( 'متن کنار کلید (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'استفاده از موجودی', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_wallet' => 'yes' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-pm__card' );
                $this->a_text( 'hd', '{{WRAPPER}} .hsw-pm__hd-t', array( 'default' => 16 ) );
                $this->a_text( 'opt_t', '{{WRAPPER}} .hsw-pm__opt-t', array( 'default' => 14 ) );
                $this->a_color( 'opt_sel_bc', __( 'حاشیه گزینه انتخاب‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pm__opt.is-sel', 'border-color' );
                $this->a_color( 'bank_sel_bc', __( 'حاشیه بانک انتخاب‌شده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pm__bank.is-sel', 'border-color' );
                $this->a_color( 'ribbon_bg', __( 'پس‌زمینه برچسب توصیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pm__ribbon', 'background-color' );
                $this->a_color( 'ribbon_c', __( 'متن برچسب توصیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pm__ribbon' );
                $this->a_color( 'switch_on', __( 'کلید روشن', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-pm__switch .hsw-pm__knob', 'background-color' );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  OPTION SOURCES
         * ====================================================================== */

        /**
         * Normalized options: each row =
         * [ id, title, desc, title_m, desc_m, badge_m, type, chosen, badge, note, chip_m ].
         */
        protected function options() {
                $s   = $this->effective_settings();
                $src = $this->opt( 'source', 'live' );

                if ( 'demo' === $src || $this->is_demo() ) {
                        $out = array();
                        foreach ( $this->demo_gateways() as $g ) {
                                $out[] = array(
                                        $g['id'], $g['title'], $g['desc'],
                                        $g['title_m'], $g['desc_m'], $g['badge_m'],
                                        $g['type'], $g['chosen'],
                                        isset( $g['badge'] ) ? $g['badge'] : '',
                                        isset( $g['note'] ) ? $g['note'] : '',
                                        isset( $g['chip_m'] ) ? $g['chip_m'] : '',
                                );
                        }
                        $ct = $this->demo_card_transfer();
                        $out[] = array( 'card_transfer', 'کارت به کارت / فیش واریزی', 'واریز به حساب و ثبت شماره پیگیری فیش', $ct['title_m'], $ct['desc_m'], '', 'offline', false, '', '', '' );
                        return $out;
                }

                $out = array();
                foreach ( $this->gateways() as $g ) {
                        $out[] = array(
                                $g['id'], $g['title'], $g['desc'],
                                $g['title'], $g['desc'], '',
                                'gateway', $g['chosen'],
                                '', '', '',
                        );
                }
                return $out;
        }

        /**
         * Bank rows: [ label, label_m, icon, tag, tag_on, chosen ].
         */
        protected function banks() {
                $s     = $this->effective_settings();
                $banks = ( isset( $s['banks'] ) && is_array( $s['banks'] ) ) ? $s['banks'] : array();
                if ( empty( $banks ) ) {
                        return $this->demo_banks();
                }
                $out = array();
                foreach ( $banks as $b ) {
                        $label = isset( $b['label'] ) ? trim( (string) $b['label'] ) : '';
                        if ( '' === $label ) {
                                continue;
                        }
                        $out[] = array(
                                $label,
                                isset( $b['label_m'] ) ? trim( (string) $b['label_m'] ) : '',
                                isset( $b['icon'] ) && '' !== trim( (string) $b['icon'] ) ? $b['icon'] : 'account_balance',
                                isset( $b['tag'] ) ? trim( (string) $b['tag'] ) : '',
                                isset( $b['tag_on'] ) && 'yes' === $b['tag_on'],
                                isset( $b['chosen'] ) && 'yes' === $b['chosen'],
                        );
                }
                return $out;
        }

        /**
         * Show the ribbon on the chosen (or first) online-style option.
         */
        protected function ribbon_index( $options ) {
                foreach ( $options as $i => $o ) {
                        if ( ! empty( $o[7] ) ) {
                                return $i;
                        }
                }
                return 0;
        }

        protected function render() {
                $s       = $this->effective_settings();
                $options = $this->options();
                $banks   = $this->banks();
                $ribbon  = $this->ribbon_index( $options );
                $show_w  = ( 'yes' === ( $s['show_wallet'] ?? 'yes' ) );
                $wallet_on = $this->wallet_enabled();
                $ribbon_t  = $this->opt( 'ribbon_text', __( 'توصیه شده', 'hamishop-elementor-widgets' ) );
                $m_badge_t = $this->opt( 'm_badge_text', __( 'پیشنهادی', 'hamishop-elementor-widgets' ) );
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'pm' ) ); ?>" data-hswk-pay>

                        <!-- =================== DESKTOP =================== -->
                        <div class="hsw-pm__card hsw-d">
                                <div class="hsw-pm__hd">
                                        <div class="hsw-pm__hd-r">
                                                <i class="hsw-ic" aria-hidden="true">payments</i>
                                                <h2 class="hsw-pm__hd-t"><?php echo esc_html( $this->opt( 'title', __( 'انتخاب روش پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></h2>
                                        </div>
                                        <?php if ( '' !== trim( $this->opt( 'shaparak_note', '' ) ) ) : ?>
                                                <span class="hsw-pm__note"><?php echo esc_html( $this->opt( 'shaparak_note', '' ) ); ?></span>
                                        <?php endif; ?>
                                </div>

                                <div class="hsw-pm__list">
                                        <?php foreach ( $options as $i => $o ) : ?>
                                                <?php
                                                list( $id, $title, $desc, $title_m, $desc_m, $badge_m, $type, $chosen, $badge, $note, $chip_m ) = $o;
                                                $is_first = ( 0 === $i );
                                                ?>
                                                <?php if ( 'offline' === $type ) { continue; } // desktop mock has no card-to-card row. ?>

                                                <?php if ( 'online' === $type || $is_first ) : ?>
                                                        <!-- Online gateway + banks -->
                                                        <div class="hsw-pm__opt is-online<?php echo $chosen ? ' is-sel' : ''; ?>">
                                                                <?php if ( $i === $ribbon && '' !== trim( $ribbon_t ) ) : ?>
                                                                        <span class="hsw-pm__ribbon"><?php echo esc_html( $ribbon_t ); ?></span>
                                                                <?php endif; ?>
                                                                <label class="hsw-pm__opt-row">
                                                                        <input type="radio" name="hsw_pay_method_d" class="hsw-pm__radio" value="<?php echo esc_attr( $id ); ?>"<?php checked( $chosen, true ); ?> />
                                                                        <span class="hsw-pm__opt-body">
                                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $title ); ?></span>
                                                                                <?php if ( '' !== trim( (string) $desc ) ) : ?>
                                                                                        <span class="hsw-pm__opt-d"><?php echo esc_html( $desc ); ?></span>
                                                                                <?php endif; ?>
                                                                        </span>
                                                                </label>

                                                                <?php if ( ! empty( $banks ) ) : ?>
                                                                        <div class="hsw-pm__banks">
                                                                                <?php foreach ( $banks as $b ) : ?>
                                                                                        <label class="hsw-pm__bank<?php echo $b[5] ? ' is-sel' : ''; ?>">
                                                                                                <input type="radio" name="hsw_pay_bank_d" class="hsw-pm__bank-radio" value="<?php echo esc_attr( $b[0] ); ?>"<?php checked( $b[5], true ); ?> />
                                                                                                <span class="hsw-pm__bank-ic"><i class="hsw-ic" aria-hidden="true"><?php echo esc_html( $b[2] ); ?></i></span>
                                                                                                <span class="hsw-pm__bank-tx"><?php echo esc_html( $b[0] ); ?></span>
                                                                                                <?php if ( '' !== $b[3] ) : ?>
                                                                                                        <?php if ( $b[4] ) : ?>
                                                                                                                <span class="hsw-pm__bank-tag is-good"><b></b><?php echo esc_html( $b[3] ); ?></span>
                                                                                                        <?php else : ?>
                                                                                                                <span class="hsw-pm__bank-tag"><?php echo esc_html( $b[3] ); ?></span>
                                                                                                        <?php endif; ?>
                                                                                                <?php endif; ?>
                                                                                                <span class="hsw-pm__bank-dot" aria-hidden="true"></span>
                                                                                        </label>
                                                                                <?php endforeach; ?>
                                                                        </div>
                                                                <?php endif; ?>
                                                        </div>

                                                <?php elseif ( 'bnpl' === $type ) : ?>
                                                        <!-- BNPL -->
                                                        <div class="hsw-pm__opt<?php echo $chosen ? ' is-sel' : ''; ?>">
                                                                <label class="hsw-pm__opt-row">
                                                                        <input type="radio" name="hsw_pay_method_d" class="hsw-pm__radio" value="<?php echo esc_attr( $id ); ?>"<?php checked( $chosen, true ); ?> />
                                                                        <span class="hsw-pm__opt-body is-wide">
                                                                                <span class="hsw-pm__opt-head">
                                                                                        <span class="hsw-pm__opt-tx">
                                                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $title ); ?></span>
                                                                                                <?php if ( '' !== trim( (string) $badge ) ) : ?>
                                                                                                        <span class="hsw-pm__opt-badge is-bnpl"><?php echo esc_html( $badge ); ?></span>
                                                                                                <?php endif; ?>
                                                                                        </span>
                                                                                        <?php if ( '' !== trim( (string) $note ) ) : ?>
                                                                                                <span class="hsw-pm__opt-note"><?php echo esc_html( $note ); ?></span>
                                                                                        <?php endif; ?>
                                                                                </span>
                                                                                <?php if ( '' !== trim( (string) $desc ) ) : ?>
                                                                                        <span class="hsw-pm__opt-d"><?php echo esc_html( $desc ); ?></span>
                                                                                <?php endif; ?>
                                                                        </span>
                                                                </label>
                                                        </div>

                                                <?php endif; ?>
                                        <?php endforeach; ?>

                                        <?php if ( $show_w ) : ?>
                                                <!-- Wallet deduction row -->
                                                <div class="hsw-pm__wallet">
                                                        <span class="hsw-pm__wallet-main">
                                                                <i class="hsw-ic" aria-hidden="true">account_balance_wallet</i>
                                                                <span class="hsw-pm__wallet-tx">
                                                                        <span class="hsw-pm__wallet-head">
                                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $this->opt( 'wallet_title', '' ) ); ?></span>
                                                                                <?php if ( '' !== trim( $this->opt( 'wallet_label', '' ) ) ) : ?>
                                                                                        <span class="hsw-pm__wallet-bal"><?php echo esc_html( $this->opt( 'wallet_label', '' ) ); ?></span>
                                                                                <?php endif; ?>
                                                                        </span>
                                                                        <span class="hsw-pm__opt-d"><?php echo esc_html( $this->opt( 'wallet_desc', '' ) ); ?></span>
                                                                </span>
                                                        </span>
                                                        <label class="hsw-pm__switch">
                                                                <input type="checkbox" class="hsw-pm__wallet-cb hsw-pm__wallet-cb--d" name="hsw_wallet_d"<?php checked( $wallet_on, true ); ?> />
                                                                <span class="hsw-pm__knob" aria-hidden="true"></span>
                                                                <span class="hsw-pm__switch-tx"><?php echo esc_html( $this->opt( 'wallet_switch', '' ) ); ?></span>
                                                        </label>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <?php if ( $show_w ) : ?>
                                <section class="hsw-pm__mwallet hsw-m">
                                        <span class="hsw-pm__mw-main">
                                                <span class="hsw-pm__mw-ic"><i class="hsw-ic" aria-hidden="true">account_balance_wallet</i></span>
                                                <span class="hsw-pm__mw-tx">
                                                        <span class="hsw-pm__mw-head">
                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $this->opt( 'wallet_title_m', '' ) ); ?></span>
                                                                <?php if ( '' !== trim( $this->opt( 'wallet_label_m', '' ) ) ) : ?>
                                                                        <span class="hsw-pm__mw-bal"><?php echo esc_html( $this->opt( 'wallet_label_m', '' ) ); ?></span>
                                                                <?php endif; ?>
                                                        </span>
                                                        <span class="hsw-pm__opt-d"><?php echo esc_html( $this->opt( 'wallet_desc_m', '' ) ); ?></span>
                                                </span>
                                        </span>
                                        <label class="hsw-pm__switch">
                                                <input type="checkbox" class="hsw-pm__wallet-cb hsw-pm__wallet-cb--m" name="hsw_wallet_m"<?php checked( $wallet_on, true ); ?> />
                                                <span class="hsw-pm__knob" aria-hidden="true"></span>
                                        </label>
                                </section>
                        <?php endif; ?>

                        <section class="hsw-pm__mcard hsw-m">
                                <div class="hsw-pm__mhd">
                                        <i class="hsw-ic" aria-hidden="true">credit_card</i>
                                        <h2 class="hsw-pm__hd-t is-m"><?php echo esc_html( $this->opt( 'm_title', __( 'شیوه پرداخت', 'hamishop-elementor-widgets' ) ) ); ?></h2>
                                </div>

                                <div class="hsw-pm__mlist">
                                        <?php foreach ( $options as $i => $o ) : ?>
                                                <?php
                                                list( $id, $title, $desc, $title_m, $desc_m, $badge_m, $type, $chosen, $badge, $note, $chip_m ) = $o;
                                                $is_first = ( 0 === $i );
                                                ?>
                                                <label class="hsw-pm__opt is-m<?php echo $chosen ? ' is-sel' : ''; ?>">
                                                        <span class="hsw-pm__opt-row">
                                                                <input type="radio" name="hsw_pay_method_m" class="hsw-pm__radio" value="<?php echo esc_attr( $id ); ?>"<?php checked( $chosen, true ); ?> />
                                                                <span class="hsw-pm__opt-body">
                                                                        <?php if ( 'offline' !== $type ) : ?>
                                                                                <span class="hsw-pm__opt-head">
                                                                                        <span class="hsw-pm__opt-tx">
                                                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $title_m ); ?></span>
                                                                                                <?php if ( ! $is_first && '' !== trim( (string) $badge_m ) ) : ?>
                                                                                                        <span class="hsw-pm__opt-badge"><?php echo esc_html( $badge_m ); ?></span>
                                                                                                <?php endif; ?>
                                                                                        </span>
                                                                                        <?php if ( $is_first && '' !== trim( $m_badge_t ) ) : ?>
                                                                                                <span class="hsw-pm__opt-badge is-acc"><?php echo esc_html( $m_badge_t ); ?></span>
                                                                                        <?php elseif ( '' !== trim( (string) $chip_m ) ) : ?>
                                                                                                <span class="hsw-pm__opt-chip"><?php echo esc_html( $chip_m ); ?></span>
                                                                                        <?php endif; ?>
                                                                                </span>
                                                                        <?php else : ?>
                                                                                <span class="hsw-pm__opt-t"><?php echo esc_html( $title_m ); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ( '' !== trim( (string) $desc_m ) ) : ?>
                                                                                <span class="hsw-pm__opt-d is-block"><?php echo esc_html( $desc_m ); ?></span>
                                                                        <?php endif; ?>
                                                                </span>
                                                        </span>

                                                        <?php if ( ( 'online' === $type || $is_first ) && ! empty( $banks ) ) : ?>
                                                                <?php
                                                                $m_banks = array();
                                                                foreach ( $banks as $b ) {
                                                                        if ( '' !== $b[1] ) {
                                                                                $m_banks[] = $b;
                                                                        }
                                                                }
                                                                ?>
                                                                <?php if ( ! empty( $m_banks ) ) : ?>
                                                                        <?php $any_chosen = false; ?>
                                                                        <?php foreach ( $m_banks as $b ) { if ( $b[5] ) { $any_chosen = true; break; } } ?>
                                                                        <span class="hsw-pm__mbanks">
                                                                                <?php foreach ( $m_banks as $bi => $b ) : ?>
                                                                                        <span class="hsw-pm__mbank<?php echo ( $b[5] || ( ! $any_chosen && 0 === $bi ) ) ? ' is-sel' : ''; ?>" data-hswk-bank="<?php echo esc_attr( $bi ); ?>">
                                                                                                <?php echo esc_html( $b[1] ); ?>
                                                                                        </span>
                                                                                <?php endforeach; ?>
                                                                        </span>
                                                                <?php endif; ?>
                                                        <?php endif; ?>
                                                </label>
                                        <?php endforeach; ?>
                                </div>
                        </section>

                </section>
                <?php
        }
}
