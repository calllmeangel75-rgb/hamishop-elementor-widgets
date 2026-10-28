<?php
/**
 * Widget: Archive product grid — faithful conversion of the supplied design.
 * Single responsive card markup: 2 columns on mobile (h-32 media, plus CTA),
 * 3 columns on desktop (h-48 media, cart CTA, «تخفیف» badge text).
 * Products: the main WooCommerce archive query (theme-builder compatible).
 * Add-to-cart: AJAX like the loop widgets.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Archive_Grid extends HSW_Archive_Base {

        public function get_name() {
                return 'hsw-archive-grid';
        }

        public function get_title() {
                return __( 'گرید محصولات آرشیو (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-gallery-grid';
        }

        public function get_keywords() {
                return array( 'grid', 'products', 'محصولات', 'آرشیو', 'شبکه', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'stock_text',
                        array(
                                'label'       => __( 'متن وضعیت موجود', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'موجود در انبار', 'hamishop-elementor-widgets' ),
                                'description' => __( '{city} = نام شهر (اگر تنظیم شده باشد).', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'city',
                        array(
                                'label'   => __( 'شهر انبار (اختیاری)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تهران', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'express_note',
                        array(
                                'label'   => __( 'متن ارسال اکسپرس', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'ارسال اکسپرس امروز', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'express_slug',
                        array(
                                'label'       => __( 'کلاس ارسال فوری', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SELECT,
                                'options'     => $this->shipping_class_options(),
                                'default'     => '',
                                'description' => __( 'کالاهای دارای این کلاس ارسال، یادداشت «ارسال فوری» می‌گیرند. خالی = خودکار (express).', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'currency',
                        array(
                                'label'   => __( 'واحد پول', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'تومان', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'grid_heading',
                        array( 'label' => __( 'شبکه', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_responsive_control(
                        'cols',
                        array(
                                'label'           => __( 'تعداد ستون', 'hamishop-elementor-widgets' ),
                                'type'            => Controls_Manager::SELECT,
                                'default'         => '3',
                                'tablet_default'  => '3',
                                'mobile_default'  => '2',
                                'options'         => array( '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵' ),
                                /* `:not(.hsw-ag--list)` keeps نمای لیستی single-column. */
                                'selectors'       => array(
                                        '{{WRAPPER}} .hsw-ag:not(.hsw-ag--list) .hsw-ag__grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                                ),
                                'devices'         => array( 'desktop', 'tablet', 'mobile' ),
                                'description'     => __( 'پیش‌فرض طرح: موبایل ۲ — تبلت و دسکتاپ ۳. در نمای لیستی، یک ستونه نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_wishlist',
                        array(
                                'label'   => __( 'دکمه علاقه‌مندی', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل کارت', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'card', '{{WRAPPER}} .hsw-ag__card' );
                $this->a_color( 'card_badge', __( 'پس‌زمینه بج تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__badge--sale', 'background-color' );
                $this->a_color( 'card_featbg', __( 'پس‌زمینه بج ویژه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__badge--featured', 'background-color' );
                $this->a_color( 'card_meta', __( 'رنگ برند و متا', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__brand, {{WRAPPER}} .hsw-ag__revcount' );
                $this->a_color( 'card_star', __( 'رنگ ستاره', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__rate .hsw-ic' );
                $this->a_color( 'card_title', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__title' );
                $this->a_color( 'card_stock', __( 'رنگ وضعیت موجود', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__note' );
                $this->a_color( 'card_old', __( 'رنگ قیمت قدیم', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__old' );
                $this->a_color( 'card_price', __( 'رنگ قیمت جدید', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__price' );
                $this->a_color( 'card_cta', __( 'پس‌زمینه دکمه خرید', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__cta, {{WRAPPER}} .hsw-ag__cta--m', 'background-color' );
                $this->a_color( 'card_media', __( 'پس‌زمینه محفظه تصویر', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__media', 'background-color' );

                $this->end_controls_section();

                /*
                 * Card type scale. The archive card carries three sizes; they
                 * were hard-coded in the stylesheet, so a shop that wanted a
                 * larger price had to touch CSS.
                 */
                $this->style_typography_section(
                        'ag',
                        array(
                                'title' => array( __( 'عنوان محصول', 'hamishop-elementor-widgets' ), 14 ),
                                'price' => array( __( 'قیمت', 'hamishop-elementor-widgets' ), 18 ),
                                'note'  => array( __( 'یادداشت زیر عنوان', 'hamishop-elementor-widgets' ), 11 ),
                        )
                );
        }

        /**
         * Products for the grid: real query → demo.
         *
         * @return array[] [ 'product'|'demo', ... ] rows.
         */
        protected function products() {
                if ( ! $this->is_demo() && $this->is_archive_context() ) {
                        global $wp_query;
                        $rows = array();
                        foreach ( $wp_query->posts as $post ) {
                                $product = wc_get_product( $post );
                                if ( ! $product ) {
                                        continue;
                                }
                                $rows[] = array( 'product', $product );
                        }
                        return $rows;
                }
                $rows = array();
                foreach ( $this->demo()['products'] as $p ) {
                        $rows[] = array( 'demo', $p );
                }
                return $rows;
        }

        /**
         * Status note for a real product.
         */
        protected function status_note( $product, $s ) {
                $city     = isset( $s['city'] ) ? trim( (string) $s['city'] ) : '';
                $in_stock = isset( $s['stock_text'] ) && '' !== trim( (string) $s['stock_text'] ) ? trim( (string) $s['stock_text'] ) : __( 'موجود در انبار', 'hamishop-elementor-widgets' );
                $express  = isset( $s['express_note'] ) && '' !== trim( (string) $s['express_note'] ) ? trim( (string) $s['express_note'] ) : __( 'ارسال اکسپرس امروز', 'hamishop-elementor-widgets' );
                $e_class  = $this->express_slug();

                if ( ! $product->is_in_stock() ) {
                        return array( __( 'ناموجود', 'hamishop-elementor-widgets' ), false, 'close' );
                }
                if ( $e_class && has_term( $e_class, 'product_shipping_class', $product->get_id() ) ) {
                        return array( $express, true, 'bolt' );
                }
                $text = str_replace( '{city}', $city ? ' ' . $city : '', $in_stock );
                return array( $text, true, 'task_alt' );
        }

        /**
         * Card media markup for a product (responsive srcset via core).
         *
         * @param WC_Product $product Product.
         * @param string     $alt     Alt text.
         * @return string
         */
        protected function image_html( $product, $alt, $priority = false ) {
                $img_id = $product->get_image_id();
                if ( $img_id ) {
                        $html = wp_get_attachment_image(
                                $img_id,
                                'woocommerce_thumbnail',
                                false,
                                array(
                                        'alt'           => $alt,
                                        /*
                                         * The first card is the LCP candidate: never lazy-load it.
                                         * data-no-lazy / skip-lazy keep LiteSpeed Cache and
                                         * WP Rocket from replacing it with a lazy placeholder.
                                         */
                                        'loading'       => $priority ? 'eager' : 'lazy',
                                        'fetchpriority' => $priority ? 'high' : 'auto',
                                        'class'         => $priority ? 'skip-lazy' : '',
                                        'data-no-lazy'  => $priority ? '1' : '',
                                        'decoding'      => 'async',
                                        /*
                                         * WooCommerce defaults to `sizes="300px"`, so a
                                         * 196px card downloaded the 300w file. Declaring
                                         * the real box lets the browser pick a smaller
                                         * candidate and shrinks the LCP payload.
                                         */
                                        'sizes'         => '(max-width: 480px) 45vw, (max-width: 767px) 30vw, 196px',
                                )
                        );
                        if ( $html ) {
                                return $html;
                        }
                }
                return '<img src="' . esc_url( HSW_URL . 'assets/img/placeholder.svg' ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" width="300" height="300">';
        }

        /**
         * Card markup for one row.
         */
        protected function card( $kind, $item, $s, $priority = false ) {
                $is_demo   = ( 'demo' === $kind );
                $img_html  = '';
                $img2_html = '';
                $name      = '';
                $brand     = '';
                $rating    = '';
                $reviews   = '';
                $price     = '';
                $regular   = '';
                $discount  = 0;
                $note      = '';
                $note_ok   = true;
                $note_ic   = 'task_alt';
                $badge     = '';
                $pid       = 0;
                $url       = '#';
                $addable   = false;
                $cfp       = false;
                /* Only set in the live branch; the demo branch never has one. */
                $product   = null;

                if ( $is_demo ) {
                        $p        = $item;
                        $name     = $p['name'];
                        $brand    = $p['brand'];
                        $rating   = $p['rating'];
                        $reviews  = HSW_Helpers::fa_num( $p['reviews'] );
                        $price    = $p['price'];
                        $regular  = $p['regular'];
                        $discount = (int) $p['discount'];
                        $note     = $p['note'];
                        $note_ic  = $p['note_icon'];
                        $img_html = '<img src="' . esc_url( $p['img'] ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async" width="300" height="300">';
                        $badge    = $p['badge'];
                        $url      = '#';
                } else {
                        $product  = $item;
                        $pid      = $product->get_id();
                        $url      = get_permalink( $pid );
                        $name     = $product->get_name();
                        $rating   = HSW_Helpers::fa_num( number_format( (float) $product->get_average_rating(), 1 ) );
                        $reviews  = HSW_Helpers::fa_num( $product->get_review_count() );
                        $brand    = HSW_Helpers::brand_label( $product );
                        $cfp      = HSW_Helpers::is_call_for_price( $product );
                        if ( $cfp ) {
                                $price    = '';
                                $regular  = '';
                                $discount = 0;
                                $badge    = $product->is_featured() ? 'featured' : '';
                        } else {
                                $sale     = (float) wc_get_price_to_display( $product );
                                $reg      = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
                                $price    = HSW_Helpers::price_num( $sale > 0 ? $sale : $reg );
                                $regular  = ( $reg > 0 && $sale > 0 && $sale < $reg ) ? HSW_Helpers::price_num( $reg ) : '';
                                $discount = HSW_Helpers::discount_percent( $product );
                                $badge    = ( $discount > 0 ) ? 'sale' : ( $product->is_featured() ? 'featured' : '' );
                                $addable  = $product->is_type( 'simple' ) && $product->is_in_stock() && $product->is_purchasable();
                        }
                        list( $note, $note_ok, $note_ic ) = $this->status_note( $product, $s );
                        $img_html = $this->image_html( $product, $name, $priority );

                /*
                 * Second gallery image: fades in on hover with a slight zoom.
                 * Only rendered when the product actually has one, so a card
                 * with a single photo keeps its single <img>.
                 */
                $img2_html = '';
                if ( $product instanceof WC_Product ) {
                        $gallery = $product->get_gallery_image_ids();
                        if ( ! empty( $gallery ) ) {
                                $img2_html = wp_get_attachment_image(
                                        (int) $gallery[0],
                                        'woocommerce_thumbnail',
                                        false,
                                        array(
                                                'class'    => 'hsw-ag__img2',
                                                'alt'      => '',
                                                'loading'  => 'lazy',
                                                'decoding' => 'async',
                                                'aria-hidden' => 'true',
                                        )
                                );
                        }
                }
                }

                $cur      = isset( $s['currency'] ) && '' !== trim( (string) $s['currency'] ) ? trim( (string) $s['currency'] ) : HSW_Helpers::currency_label();
                $wish     = ( 'yes' === ( $s['show_wishlist'] ?? 'yes' ) );
                ?>
                <?php $is_oos = ( $product instanceof WC_Product ) && ! $product->is_in_stock(); ?>
                <article class="hsw-ag__card<?php echo $is_oos ? ' is-oos' : ''; ?>">

                        <?php if ( 'sale' === $badge || 'featured' === $badge ) : ?>
                                <div class="hsw-ag__badges">
                                        <?php if ( 'sale' === $badge ) : ?>
                                                <span class="hsw-ag__badge hsw-ag__badge--sale"><span class="hsw-d"><?php echo esc_html( HSW_Helpers::fa_num( $discount ) ); ?>٪ <?php esc_html_e( 'تخفیف', 'hamishop-elementor-widgets' ); ?></span><span class="hsw-m"><?php echo esc_html( HSW_Helpers::fa_num( $discount ) ); ?>٪</span></span>
                                        <?php else : ?>
                                                <span class="hsw-ag__badge hsw-ag__badge--featured"><span class="hsw-d"><?php esc_html_e( 'پیشنهاد ویژه', 'hamishop-elementor-widgets' ); ?></span><span class="hsw-m"><?php esc_html_e( 'ویژه', 'hamishop-elementor-widgets' ); ?></span></span>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>

                        <?php if ( $wish ) : ?>
                                <button type="button" class="hsw-ag__wish" aria-label="<?php esc_attr_e( 'افزودن به علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ); ?>" data-hsw-wish="<?php echo esc_attr( $pid ); ?>">
                                        <?php echo HSW_Helpers::icon( 'favorite' ); ?>
                                </button>
                        <?php endif; ?>

                        <a class="hsw-ag__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
                                <?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe image markup. ?>
                                <?php echo $img2_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe image markup. ?>
                                <?php if ( $is_oos ) : ?>
                                        <span class="hsw-ag__oosbadge"><?php echo esc_html( isset( $s['oos_text'] ) && '' !== trim( (string) $s['oos_text'] ) ? (string) $s['oos_text'] : __( 'ناموجود', 'hamishop-elementor-widgets' ) ); ?></span>
                                <?php endif; ?>
                        </a>

                        <div class="hsw-ag__body">
                                <div class="hsw-ag__meta">
                                        <?php if ( $brand ) : ?>
                                                <span class="hsw-ag__brand"><?php echo esc_html( $brand ); ?></span>
                                        <?php endif; ?>
                                        <span class="hsw-ag__rate">
                                                <?php echo HSW_Helpers::icon( 'star', '', true ); ?>
                                                <b class="hsw-ag__score"><?php echo esc_html( $rating ); ?></b>
                                                <span class="hsw-ag__revcount hsw-d">(<?php echo esc_html( $reviews ); ?>)</span>
                                        </span>
                                </div>
                                <h2 class="hsw-ag__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></h2>
                                <div class="hsw-ag__note<?php echo $note_ok ? '' : ' is-oos'; ?>">
                                        <?php echo HSW_Helpers::icon( $note_ic ); ?>
                                        <span><?php echo esc_html( $note ); ?></span>
                                </div>
                        </div>

                        <div class="hsw-ag__pricing<?php echo $cfp ? ' hsw-ag__pricing--cfp' : ''; ?>">
                                <?php if ( $cfp ) : ?>
                                        <div class="hsw-ag__prices hsw-ag__prices--cfp">
                                                <?php echo HSW_Helpers::cfp_inline_html( $product, false, 'hsw-ag__cfp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
                                        </div>
                                        <?php echo HSW_Helpers::cfp_action_html( $product, 'hsw-ag__cta hsw-ag__cta--cfp hsw-d', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
                                        <?php echo HSW_Helpers::cfp_action_html( $product, 'hsw-ag__cta--m hsw-ag__cta--m--cfp hsw-m', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
                                <?php else : ?>
                                        <div class="hsw-ag__prices">
                                                <?php if ( $regular ) : ?>
                                                        <del class="hsw-ag__old"><?php echo esc_html( $regular ); ?></del>
                                                <?php else : ?>
                                                        <span class="hsw-ag__old hsw-ag__old--ph">&nbsp;</span>
                                                <?php endif; ?>
                                                <span class="hsw-ag__price"><?php echo esc_html( $price ); ?> <small><?php echo esc_html( $cur ); ?></small></span>
                                        </div>
                                        <?php if ( $is_demo || $addable ) : ?>
                                                <button type="button" class="hsw-ag__cta hsw-d" data-hsw-add="<?php echo esc_attr( $pid ); ?>" data-hsw-name="<?php echo esc_attr( $name ); ?>" aria-label="<?php esc_attr_e( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ); ?>" title="<?php esc_attr_e( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'add_shopping_cart' ); ?>
                                                </button>
                                                <button type="button" class="hsw-ag__cta--m hsw-m" data-hsw-add="<?php echo esc_attr( $pid ); ?>" data-hsw-name="<?php echo esc_attr( $name ); ?>" aria-label="<?php esc_attr_e( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'add' ); ?>
                                                </button>
                                        <?php else : ?>
                                                <a class="hsw-ag__cta hsw-d" href="<?php echo esc_url( $url ); ?>" aria-label="<?php esc_attr_e( 'مشاهده محصول', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'shopping_cart' ); ?>
                                                </a>
                                                <a class="hsw-ag__cta--m hsw-m" href="<?php echo esc_url( $url ); ?>" aria-label="<?php esc_attr_e( 'مشاهده محصول', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'shopping_cart' ); ?>
                                                </a>
                                        <?php endif; ?>
                                <?php endif; ?>
                        </div>
                </article>
                <?php
        }

        protected function render() {
                $s = $this->effective_settings();
                if ( ! $this->is_demo() && ! $this->is_archive_context() ) {
                        return;
                }
                $products = $this->products();
                $is_list  = ( isset( $this->current_params()['view'] ) && 'list' === $this->current_params()['view'] );
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ag' ) ); ?><?php echo $is_list ? ' hsw-ag--list' : ''; ?>">
                        <div class="hsw-ag__grid">
                                <?php $hsw_i = 0; ?>
                                <?php foreach ( $products as $row ) : ?>
                                        <?php $this->card( $row[0], $row[1], $s, 0 === $hsw_i ); ?>
                                        <?php $hsw_i++; ?>
                                <?php endforeach; ?>
                        </div>
                        <?php if ( empty( $products ) ) : ?>
                                <div class="hsw-ag__empty hsw-d">
                                        <?php esc_html_e( 'محصولی با این فیلترها یافت نشد.', 'hamishop-elementor-widgets' ); ?>
                                </div>
                        <?php endif; ?>
                </div>
                <?php
        }
}
