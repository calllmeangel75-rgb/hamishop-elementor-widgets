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

                /* --------------------- EMPTY STATE --------------------- */
                /*
                 * A category with no products used to print one grey line, and
                 * only on desktop (`.hsw-d`). Every word of it is now a control,
                 * because the right sentence depends on the shop: «بهزودی» for a
                 * new collection, «دستهٔ دیگری را ببینید» for a dead end.
                 */
                $this->start_controls_section(
                        'sec_empty',
                        array( 'label' => __( 'حالت خالی (بدون محصول)', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'empty_icon',
                        array(
                                'label'   => __( 'آیکون', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SELECT,
                                'default' => 'inventory_2',
                                'options' => array(
                                        'inventory_2'  => __( 'جعبه خالی', 'hamishop-elementor-widgets' ),
                                        'category'     => __( 'دسته‌بندی', 'hamishop-elementor-widgets' ),
                                        'search'       => __( 'جستجو', 'hamishop-elementor-widgets' ),
                                        'shopping_bag' => __( 'کیف خرید', 'hamishop-elementor-widgets' ),
                                        'storefront'   => __( 'فروشگاه', 'hamishop-elementor-widgets' ),
                                        'grid_view'    => __( 'شبکه محصولات', 'hamishop-elementor-widgets' ),
                                        'filter_list'  => __( 'فیلتر', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );

                $this->add_control(
                        'empty_title',
                        array(
                                'label'       => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'هیچ محصولی در حال حاضر در این دسته‌بندی وجود ندارد', 'hamishop-elementor-widgets' ),
                                'label_block' => true,
                        )
                );

                $this->add_control(
                        'empty_text',
                        array(
                                'label'   => __( 'توضیح', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'default' => __( 'می‌توانید دستهٔ دیگری را ببینید یا به فروشگاه برگردید.', 'hamishop-elementor-widgets' ),
                                'rows'    => 2,
                        )
                );

                $this->add_control(
                        'empty_filter_title',
                        array(
                                'label'       => __( 'عنوان وقتی فیلتر فعال است', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'محصولی با این فیلترها یافت نشد', 'hamishop-elementor-widgets' ),
                                'label_block' => true,
                                'description' => __( 'اگر حداقل یک فیلتر یا جستجو فعال باشد، این عنوان جای عنوان بالا نمایش داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'empty_show_btn',
                        array(
                                'label'   => __( 'دکمه بازگشت', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'empty_btn',
                        array(
                                'label'     => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'بازگشت به فروشگاه', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'empty_show_btn' => 'yes' ),
                        )
                );

                $this->add_control(
                        'empty_btn_url',
                        array(
                                'label'       => __( 'لینک دکمه', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::URL,
                                'default'     => array( 'url' => '' ),
                                'description' => __( 'خالی بگذارید تا به صفحهٔ فروشگاه برود.', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'empty_show_btn' => 'yes' ),
                        )
                );

                $this->add_control(
                        'empty_preview',
                        array(
                                'label'       => __( 'پیش‌نمایش حالت خالی در ویرایشگر', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => '',
                                'description' => __( 'وقتی روشن باشد، در ویرایشگر المنتور به‌جای کارت‌های نمونه همین بلوک نمایش داده می‌شود تا متن و رنگ‌ها را همان‌جا تنظیم کنید. در سایت واقعی هیچ اثری ندارد.', 'hamishop-elementor-widgets' ),
                                'separator'   => 'before',
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

                $this->start_controls_section(
                        'sec_empty_style',
                        array( 'label' => __( 'استایل حالت خالی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->add_responsive_control(
                        'empty_align',
                        array(
                                'label'     => __( 'چینش', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::CHOOSE,
                                'default'   => 'center',
                                'options'   => array(
                                        'start'  => array( 'title' => __( 'راست', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-right' ),
                                        'center' => array( 'title' => __( 'وسط', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-center' ),
                                        'end'    => array( 'title' => __( 'چپ', 'hamishop-elementor-widgets' ), 'icon' => 'eicon-text-align-left' ),
                                ),
                                'selectors' => array(
                                        '{{WRAPPER}} .hsw-ag__empty' => 'text-align: {{VALUE}}; align-items: {{VALUE}};',
                                ),
                        )
                );

                $this->a_color( 'empty_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__empty', 'background-color' );
                $this->a_color( 'empty_border', __( 'رنگ حاشیه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__empty', 'border-color' );
                $this->a_color( 'empty_iconbg', __( 'پس‌زمینه آیکون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptyicon', 'background-color' );
                $this->a_color( 'empty_iconc', __( 'رنگ آیکون', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptyicon' );
                $this->a_color( 'empty_titlec', __( 'رنگ عنوان', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptytitle' );
                $this->a_color( 'empty_textc', __( 'رنگ توضیح', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptytext' );
                $this->a_color( 'empty_btnbg', __( 'پس‌زمینه دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptybtn', 'background-color' );
                $this->a_color( 'empty_btnc', __( 'رنگ متن دکمه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptybtn' );
                $this->a_color( 'empty_btnhoverbg', __( 'پس‌زمینه دکمه (هاور)', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-ag__emptybtn:hover', 'background-color' );

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
                                         *
                                         * `45vw` was the first attempt and it was 36%
                                         * too wide: 185px on a 412px phone for a 136px
                                         * box, which pushed the browser past the 300w
                                         * candidate into the 500x500 original — 28.8 KiB
                                         * for a thumbnail, on the LCP element. Measured
                                         * on the live archive: 500w picked before,
                                         * 300w (10.8 KiB) after.
                                         *
                                         * The 42px is exact and comes from our own CSS,
                                         * not from the site: half the 12px grid gap (6)
                                         * + card padding (10x2) + media padding (8x2).
                                         * The Elementor container padding is not in the
                                         * formula on purpose — it only makes the real box
                                         * narrower, so this can overstate but never
                                         * understate, and an understated `sizes` is what
                                         * blurs product photos. Desktop stays the 196px
                                         * that was measured (the box is 195 at 1280).
                                         */
                                        'sizes'         => '(max-width: 767px) calc(50vw - 42px), 196px',
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
                                /*
                                 * A variable product has no regular price of its own, so
                                 * the card printed no struck-through price next to a
                                 * badge that said «۲۲٪ تخفیف». Read the same variation
                                 * the price comes from.
                                 */
                                $reg_raw  = $product->is_type( 'variable' )
                                        ? $product->get_variation_regular_price( 'min', true )
                                        : $product->get_regular_price();
                                $reg      = (float) wc_get_price_to_display( $product, array( 'price' => $reg_raw ) );
                                $price    = HSW_Helpers::price_num( $sale > 0 ? $sale : $reg );
                                $regular  = ( $reg > 0 && $sale > 0 && $sale < $reg ) ? HSW_Helpers::price_num( $reg ) : '';
                                $badge_d  = HSW_Helpers::discount_badge( $product );
                                $discount = (int) $badge_d['percent'];
                                $up_to    = ! empty( $badge_d['up_to'] );
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
                                                <span class="hsw-ag__badge hsw-ag__badge--sale"><span class="hsw-d"><?php echo $up_to ? esc_html__( 'تا', 'hamishop-elementor-widgets' ) . ' ' : ''; ?><?php echo esc_html( HSW_Helpers::fa_num( $discount ) ); ?>٪ <?php esc_html_e( 'تخفیف', 'hamishop-elementor-widgets' ); ?></span><span class="hsw-m"><?php echo $up_to ? esc_html__( 'تا', 'hamishop-elementor-widgets' ) . ' ' : ''; ?><?php echo esc_html( HSW_Helpers::fa_num( $discount ) ); ?>٪</span></span>
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
                                                        <?php echo HSW_Helpers::icon( 'shopping_cart' ); ?>
                                                </button>
                                                <button type="button" class="hsw-ag__cta--m hsw-m" data-hsw-add="<?php echo esc_attr( $pid ); ?>" data-hsw-name="<?php echo esc_attr( $name ); ?>" aria-label="<?php esc_attr_e( 'افزودن به سبد خرید', 'hamishop-elementor-widgets' ); ?>">
                                                        <?php echo HSW_Helpers::icon( 'shopping_cart' ); ?>
                                                </button>
                                        <?php else : ?>
                                                <a class="hsw-ag__cta hsw-d" href="<?php echo esc_url( $url ); ?>" aria-label="<?php esc_attr_e( 'مشاهده محصول', 'hamishop-elementor-widgets' ); ?>" title="<?php esc_attr_e( 'مشاهده محصول', 'hamishop-elementor-widgets' ); ?>">
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

                /*
                 * The empty state is invisible in the editor otherwise — the demo
                 * grid always has cards — so there was no way to see the text you
                 * were writing. This switch swaps the demo grid for the block.
                 */
                $preview_empty = $this->is_demo() && isset( $s['empty_preview'] ) && 'yes' === $s['empty_preview'];
                ?>
                <div class="<?php echo esc_attr( $this->root_class( 'ag' ) ); ?><?php echo $is_list ? ' hsw-ag--list' : ''; ?>">
                        <?php if ( $preview_empty ) : ?>
                                <?php echo $this->empty_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>
                        <?php else : ?>
                                <div class="hsw-ag__grid">
                                        <?php $hsw_i = 0; ?>
                                        <?php foreach ( $products as $row ) : ?>
                                                <?php $this->card( $row[0], $row[1], $s, 0 === $hsw_i ); ?>
                                                <?php $hsw_i++; ?>
                                        <?php endforeach; ?>
                                </div>
                                <?php if ( empty( $products ) ) : ?>
                                        <?php echo $this->empty_html( $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>
                                <?php endif; ?>
                        <?php endif; ?>
                </div>
                <?php
        }

        /**
         * True when the visitor narrowed the archive with something other than
         * the category itself: a filter, a price range, or a search.
         *
         * The empty state says «هیچ محصولی در این دسته‌بندی وجود ندارد» — which
         * would be wrong when the category does have products and a filter hid
         * them. `s` counts, `orderby`/`paged`/`view` do not.
         *
         * @return bool
         */
        protected function has_active_filters() {
                $params = $this->current_params();

                foreach ( array_keys( (array) $params ) as $key ) {
                        $key = (string) $key;
                        if ( 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'query_type_' ) ) {
                                return true;
                        }
                }

                foreach ( array( 'min_price', 'max_price', 'instock', 'onsale', 'express', 'rating_filter', 's' ) as $key ) {
                        if ( isset( $params[ $key ] ) && '' !== trim( (string) $params[ $key ] ) ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Empty-state block: icon, title, description, back-to-shop button.
         *
         * Every string is a control with a sensible default, so a shop that
         * wants «به‌زودی» instead of «هیچ محصولی وجود ندارد» changes one field
         * in Elementor and nothing else.
         *
         * @param array $s Effective settings.
         * @return string
         */
        protected function empty_html( $s ) {
                $filtered = $this->has_active_filters();

                $icon = isset( $s['empty_icon'] ) ? sanitize_key( (string) $s['empty_icon'] ) : 'inventory_2';
                if ( '' === $icon ) {
                        $icon = 'inventory_2';
                }

                $title_key = $filtered ? 'empty_filter_title' : 'empty_title';
                $title     = isset( $s[ $title_key ] ) ? trim( (string) $s[ $title_key ] ) : '';

                if ( '' === $title ) {
                        $title = $filtered
                                ? __( 'محصولی با این فیلترها یافت نشد', 'hamishop-elementor-widgets' )
                                : __( 'هیچ محصولی در حال حاضر در این دسته‌بندی وجود ندارد', 'hamishop-elementor-widgets' );
                }

                $text     = isset( $s['empty_text'] ) ? trim( (string) $s['empty_text'] ) : '';
                $show_btn = ! isset( $s['empty_show_btn'] ) || 'yes' === $s['empty_show_btn'];
                $btn      = isset( $s['empty_btn'] ) ? trim( (string) $s['empty_btn'] ) : '';

                if ( '' === $btn ) {
                        $btn = __( 'بازگشت به فروشگاه', 'hamishop-elementor-widgets' );
                }

                $url = isset( $s['empty_btn_url']['url'] ) ? trim( (string) $s['empty_btn_url']['url'] ) : '';

                if ( '' === $url ) {
                        $url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
                }

                $out  = '<div class="hsw-ag__empty" role="status">';
                $out .= '<span class="hsw-ag__emptyicon" aria-hidden="true">' . HSW_Helpers::icon( $icon ) . '</span>';
                $out .= '<p class="hsw-ag__emptytitle">' . esc_html( $title ) . '</p>';

                if ( '' !== $text ) {
                        $out .= '<p class="hsw-ag__emptytext">' . esc_html( $text ) . '</p>';
                }

                if ( $show_btn && '' !== $btn ) {
                        $out .= '<a class="hsw-ag__emptybtn" href="' . esc_url( $url ) . '">' . esc_html( $btn ) . '</a>';
                }

                $out .= '</div>';

                return $out;
        }
}
