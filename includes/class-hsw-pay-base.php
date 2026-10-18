<?php
/**
 * Base for the payment & final-confirmation widgets (پرداخت و تایید نهایی).
 * Extends the checkout base so customer/shipping/money resolvers are shared;
 * adds payment-specific resolvers: WooCommerce payment gateways, wallet
 * flag, applied coupons and the demo data mirroring both supplied mocks.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

abstract class HSW_Pay_Base extends HSW_Checkout_Base {

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-checkout' );
        }

        public function get_script_depends() {
                return array( 'hsw-checkout' );
        }

        /* =========================================================================
         *  LIVE PAYMENT RESOLVERS
         * ====================================================================== */

        /**
         * Available (enabled) WooCommerce payment gateways —
         * array( id, title, desc, icon, chosen ).
         */
        protected function gateways() {
                if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
                        return array();
                }
                $all = WC()->payment_gateways()->get_available_payment_gateways();
                if ( empty( $all ) || ! is_array( $all ) ) {
                        return array();
                }
                $chosen = '';
                if ( WC()->session ) {
                        $chosen = (string) WC()->session->get( 'chosen_payment_method' );
                }
                $out = array();
                foreach ( $all as $id => $gateway ) {
                        $title = (string) $gateway->get_title();
                        $desc  = (string) $gateway->get_description();
                        $out[] = array(
                                'id'     => (string) $id,
                                'title'  => ( '' !== trim( $title ) ) ? $title : $id,
                                'desc'   => ( '' !== trim( $desc ) ) ? wp_strip_all_tags( $desc ) : '',
                                'icon'   => $this->icon_for_gateway( $id, $title ),
                                'chosen' => ( $chosen === (string) $id ),
                        );
                }
                /* Nothing chosen yet → preselect the first (WC's own fallback). */
                if ( $out && ! wp_list_filter( $out, array( 'chosen' => true ) ) ) {
                        $out[0]['chosen'] = true;
                }
                return $out;
        }

        /**
         * Currently chosen gateway id (or first available).
         */
        protected function chosen_gateway_id() {
                $gateways = $this->gateways();
                foreach ( $gateways as $gw ) {
                        if ( ! empty( $gw['chosen'] ) ) {
                                return (string) $gw['id'];
                        }
                }
                return ( $gateways ? (string) $gateways[0]['id'] : '' );
        }

        /**
         * Pick an icon for a gateway by its id/title keywords.
         */
        protected function icon_for_gateway( $id, $title = '' ) {
                $t = strtolower( (string) $id . ' ' . (string) $title );
                if ( false !== strpos( $t, 'wallet' ) || false !== strpos( $t, 'کیف پول' ) || false !== strpos( $t, 'کیف‌پول' ) ) {
                        return 'account_balance_wallet';
                }
                if ( false !== strpos( $t, 'snap' ) || false !== strpos( $t, 'bnpl' ) || false !== strpos( $t, 'اقساط' ) || false !== strpos( $t, 'tara' ) || false !== strpos( $t, 'credit' ) ) {
                        return 'credit_card';
                }
                if ( false !== strpos( $t, 'cod' ) || false !== strpos( $t, 'پرداخت در محل' ) ) {
                        return 'payments';
                }
                if ( false !== strpos( $t, 'bank' ) || false !== strpos( $t, 'saman' ) || false !== strpos( $t, 'mellat' ) || false !== strpos( $t, 'zarinpal' ) || false !== strpos( $t, 'pasargad' ) || false !== strpos( $t, 'sadad' ) || false !== strpos( $t, 'sep' ) || false !== strpos( $t, 'idpay' ) || false !== strpos( $t, 'payping' ) || false !== strpos( $t, 'زرین' ) || false !== strpos( $t, 'بانک' ) || false !== strpos( $t, 'درگاه' ) ) {
                        return 'account_balance';
                }
                return 'credit_card';
        }

        /**
         * Applied coupons of the live cart — array( code, amount, label ).
         */
        protected function applied_coupons() {
                if ( ! $this->has_cart() ) {
                        return array();
                }
                $out = array();
                foreach ( WC()->cart->get_applied_coupons() as $code ) {
                        $amount = 0;
                        if ( method_exists( WC()->cart, 'get_coupon_discount_amount' ) ) {
                                $amount = (float) WC()->cart->get_coupon_discount_amount( $code, true );
                        }
                        $out[] = array(
                                'code'   => (string) $code,
                                'amount' => $amount,
                                'label'  => (string) $code,
                        );
                }
                return $out;
        }

        /**
         * Total deducted by coupons (live cart).
         */
        protected function coupons_total() {
                $sum = 0;
                foreach ( $this->applied_coupons() as $c ) {
                        $sum += (float) $c['amount'];
                }
                return $sum;
        }

        /**
         * Wallet flag saved in the WC session (switch state).
         */
        protected function wallet_enabled() {
                if ( $this->has_cart() && WC()->session ) {
                        return 'yes' === WC()->session->get( 'hsw_use_wallet' );
                }
                return true; // demo/editor default mirrors the mock.
        }

        /**
         * Terms & conditions page URL (empty when not configured).
         */
        protected function terms_url() {
                if ( function_exists( 'wc_get_page_id' ) ) {
                        $id = (int) wc_get_page_id( 'terms' );
                        if ( 0 < $id && 'publish' === get_post_status( $id ) ) {
                                return get_permalink( $id );
                        }
                }
                return '';
        }

        /* =========================================================================
         *  DEMO DATA (editor preview only — mirrors the supplied payment mocks)
         * ====================================================================== */

        /**
         * Demo customer for THIS mock (Amirhossein Rezaei / Saadat Abad).
         */
        protected function demo_pay_customer() {
                return array(
                        'name'  => 'امیرحسین رضایی',
                        'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
                        'line'  => 'تهران، سعادت‌آباد، میدان کاج، خیابان سرو غربی، کوچه مروارید، پلاک ۱۲، واحد ۴',
                );
        }

        /**
         * Demo chosen shipping for the recall card (the mock).
         */
        protected function demo_pay_shipping() {
                return array(
                        'method' => 'پیک اختصاصی فوری تهران (رایگان)',
                        'slot'   => 'فردا (پنجشنبه) - ساعت ۱۴ الی ۱۸',
                        'free'   => true,
                        'm_line' => 'تحویل: فردا (۱۰ الی ۱۴) - ارسال اکسپرس',
                );
        }

        /**
         * Demo gateways mirroring the desktop mock exactly:
         * online gateway + 4 banks, BNPL, internal wallet.
         */
        protected function demo_gateways() {
                return array(
                        array(
                                'id'     => 'online',
                                'title'  => 'پرداخت اینترنتی (با کلیه کارت‌های عضو شبکه شتاب)',
                                'desc'   => 'اتصال مستقیم و پرسرعت به درگاه بانکی رسمی مورد نظر شما',
                                'title_m' => 'درگاه پرداخت اینترنتی آنلاین',
                                'desc_m'  => 'پرداخت آنی با کلیه کارت‌های عضو شتاب',
                                'badge_m' => 'پیشنهادی',
                                'icon'   => 'credit_card',
                                'type'   => 'online',
                                'chosen' => true,
                        ),
                        array(
                                'id'     => 'bnpl',
                                'title'  => 'پرداخت اقساطی بدون ضامن و چک (BNPL)',
                                'desc'   => 'خرید در لحظه با اعتبار اسنپ‌پی (SnappPay) یا کارت اعتباری تارا، بدون معطلی و ضمانت بانکی. قسط اول امروز و ۳ قسط بعدی در ماه‌های آینده.',
                                'title_m' => 'پرداخت اقساطی اسنپ‌پی',
                                'desc_m'  => 'در ۴ قسط ماهانه، قسط اول امروز ۲,۴۲۵,۰۰۰ تومان',
                                'badge_m' => 'بدون کارمزد',
                                'badge'  => 'بدون سود و کارمزد',
                                'note'   => '۴ قسط ماهیانه ۱۶,۰۹۷,۵۰۰ تومان',
                                'chip_m' => 'قسطی',
                                'icon'   => 'credit_card',
                                'type'   => 'bnpl',
                                'chosen' => false,
                        ),
                );
        }

        /**
         * Demo bank gateways of the online option (desktop + mobile labels).
         */
        protected function demo_banks() {
                return array(
                        array(
                                'label'   => 'به‌پرداخت ملت',
                                'label_m' => 'بانک سامان',
                                'icon'    => 'account_balance',
                                'tag'     => 'پایداری ۹۹.۸٪',
                                'tag_on'  => 'yes',
                                'chosen'  => 'yes',
                        ),
                        array(
                                'label'   => 'سامان کیش (SEP)',
                                'label_m' => 'به‌پرداخت ملت',
                                'icon'    => 'account_balance_wallet',
                                'tag'     => 'پایداری عالی',
                                'tag_on'  => '',
                                'chosen'  => '',
                        ),
                        array(
                                'label'   => 'بانک پاسارگاد',
                                'label_m' => 'سداد ملی',
                                'icon'    => 'credit_card',
                                'tag'     => 'پایداری عالی',
                                'tag_on'  => '',
                                'chosen'  => '',
                        ),
                        array(
                                'label'   => 'زرین‌پال هوشمند',
                                'label_m' => '',
                                'icon'    => 'shield',
                                'tag'     => 'تراکنش امن',
                                'tag_on'  => '',
                                'chosen'  => '',
                        ),
                );
        }

        /**
         * Demo card-to-card gateway (mobile option 3).
         */
        protected function demo_card_transfer() {
                return array(
                        'title_m' => 'کارت به کارت / فیش واریزی',
                        'desc_m'  => 'واریز به حساب و ثبت شماره پیگیری فیش',
                );
        }

        /**
         * Demo wallet balances (desktop methods row / mobile summary row).
         */
        protected function demo_wallet_balance() {
                return 250000;
        }

        protected function demo_wallet_balance_m() {
                return 350000;
        }

        /**
         * Demo invoice totals — exactly the DESKTOP mock:
         * ۶۷,۳۴۰,۰۰۰ − ۲,۹۰۰,۰۰۰ − ۵۰,۰۰۰ (کوپن WELCOME) + ارسال رایگان = ۶۴,۳۹۰,۰۰۰.
         */
        protected function demo_totals() {
                return array(
                        'count'    => 3,
                        'subtotal' => 67340000,
                        'discount' => 2900000,
                        'coupons'  => array(
                                array( 'code' => 'WELCOME', 'amount' => 50000 ),
                        ),
                        'shipping' => array( 'free', 0 ),
                        'taxes'    => 0,
                        'wallet'   => 0,
                        'total'    => 64390000,
                );
        }

        /**
         * Demo totals for the MOBILE summary + bottom bar — exactly the
         * mobile mock: ۱۰,۹۰۰,۰۰۰ − ۸۵۰,۰۰۰ − ۳۵۰,۰۰۰ (کیف پول) = ۹,۷۰۰,۰۰۰.
         */
        protected function demo_totals_m() {
                return array(
                        'count'    => 3,
                        'subtotal' => 10900000,
                        'discount' => 850000,
                        'coupons'  => array(),
                        'shipping' => array( 'free', 0 ),
                        'taxes'    => 0,
                        'wallet'   => 350000,
                        'total'    => 9700000,
                );
        }

        /**
         * Demo shipping-free pill captions (the two mocks differ).
         */
        protected function demo_free_note() {
                return 'رایگان (خرید بالای ۵۰۰ هزار)';
        }

        protected function demo_free_note_m() {
                return 'رایگان (خرید بالای ۵۰۰ تومان)';
        }

        /**
         * Demo coupon for the applied-coupon display (the mock).
         */
        protected function demo_coupon() {
                return array(
                        'code'   => 'WELCOME',
                        'amount' => 50000,
                );
        }

        /**
         * Demo support phone for the payment navbar (mock: ۰۲۱-۹۱۰۰۰۰۰۰).
         */
        protected function demo_pay_support_phone() {
                return '۰۲۱-۹۱۰۰۰۰۰۰';
        }

        /* =========================================================================
         *  SHARED CONTROLS
         * ====================================================================== */

        /**
         * Trust-badge icon choices (bundled subset only).
         */
        public static function badge_icon_choices() {
                return array(
                        'verified_user'           => 'سپر تأیید (ضمانت اصالت)',
                        'history'                 => 'بازگشت (۷ روز بازگشت)',
                        'shield'                  => 'سپر ساده (شاپرک)',
                        'published_with_changes'  => 'بازگشت وجه',
                        'lock'                    => 'قفل (SSL)',
                        'gpp_good'                => 'سپر تأیید ۲',
                        'workspace_premium'       => 'مدال',
                        'verified'                => 'تأیید',
                        'support_agent'           => 'پشتیبانی',
                        'local_shipping'          => 'ارسال',
                        'star'                    => 'ستاره',
                );
        }

        /**
         * Bank-gateway icon choices (bundled subset only).
         */
        public static function bank_icon_choices() {
                return array(
                        'account_balance'         => 'بانک (ستون ساختمان)',
                        'account_balance_wallet'  => 'کیف پول',
                        'credit_card'             => 'کارت بانکی',
                        'shield'                  => 'سپر امنیت',
                        'payments'                => 'پرداخت',
                        'gpp_good'                => 'سپر تأیید',
                        'lock'                    => 'قفل',
                );
        }
}
