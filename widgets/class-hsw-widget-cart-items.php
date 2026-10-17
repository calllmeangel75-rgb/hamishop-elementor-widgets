<?php
/**
 * Widget: Cart items — faithful conversion of the supplied design.
 * Desktop: box with header (title + count + empty-cart) and item rows
 * (thumb, brand eyebrow, title, variation chips, warranty/warehouse meta,
 * price cluster with discount badge, quantity stepper, favorite/delete).
 * Mobile: card list with absolute discount badge, chips, warranty row and
 * add/delete stepper. Real WooCommerce forms: update_cart, remove, empty.
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class HSW_Widget_Cart_Items extends HSW_Cart_Base {

        public function get_name() {
                return 'hsw-cart-items';
        }

        public function get_title() {
                return __( 'اقلام سبد خرید (HamiShop)', 'hamishop-elementor-widgets' );
        }

        public function get_icon() {
                return 'eicon-cart';
        }

        public function get_keywords() {
                return array( 'cart', 'items', 'quantity', 'سبد خرید', 'اقلام', 'تعداد', 'hamishop' );
        }

        protected function register_controls() {
                /* ------------------------- CONTENT ------------------------- */
                $this->start_controls_section(
                        'sec_content',
                        array( 'label' => __( 'تنظیمات اقلام', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->ctrl_visibility();

                $this->add_control(
                        'box_title',
                        array(
                                'label'     => __( 'عنوان باکس (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'اقلام سبد خرید', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'count_tpl',
                        array(
                                'label'       => __( 'قالب شمارنده', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '{count} کالا', 'hamishop-elementor-widgets' ),
                                'description' => __( '{count} با تعداد کل اقلام جایگزین می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_empty_btn',
                        array(
                                'label'   => __( 'دکمه «خالی کردن سبد»', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'empty_btn_label',
                        array(
                                'label'     => __( 'برچسب دکمه خالی کردن', 'hamishop-elementor-widgets' ),
                                'type'      => Controls_Manager::TEXT,
                                'default'   => __( 'خالی کردن سبد', 'hamishop-elementor-widgets' ),
                                'condition' => array( 'show_empty_btn' => 'yes' ),
                        )
                );

                $this->add_control(
                        'sec_item_heading',
                        array( 'label' => __( 'اقلام', 'hamishop-elementor-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
                );

                $this->add_control(
                        'show_eyebrow',
                        array(
                                'label'   => __( 'برند/دسته بالای عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_chips',
                        array(
                                'label'   => __( 'چیپ‌های ویژگی (رنگ، حافظه و…)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_warranty',
                        array(
                                'label'       => __( 'چیپ/ردیف گارانتی (از صفت محصول)', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::SWITCHER,
                                'default'     => 'yes',
                                'description' => __( 'از صفتی که نامش «گارانتی/ضمانت/warranty» دارد خوانده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'warehouse_text',
                        array(
                                'label'       => __( 'چیپ انبار (دسکتاپ) — خالی = مخفی', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( 'ارسال از انبار تهران', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_old',
                        array(
                                'label'   => __( 'قیمت قبل از تخفیف (خط‌خورده)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_disc',
                        array(
                                'label'   => __( 'نشان درصد تخفیف', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'disc_tpl',
                        array(
                                'label'       => __( 'قالب نشان تخفیف', 'hamishop-elementor-widgets' ),
                                'type'        => Controls_Manager::TEXT,
                                'default'     => __( '{percent}٪-', 'hamishop-elementor-widgets' ),
                                'condition'   => array( 'show_disc' => 'yes' ),
                                'description' => __( 'موبایل: «{percent}٪ تخفیف» به‌صورت خودکار نشان داده می‌شود.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'show_favorite',
                        array(
                                'label'   => __( 'دکمه علاقه‌مندی (دسکتاپ)', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->add_control(
                        'show_del',
                        array(
                                'label'   => __( 'دکمه حذف کالا', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::SWITCHER,
                                'default' => 'yes',
                        )
                );

                $this->end_controls_section();

                $this->start_controls_section(
                        'sec_empty',
                        array( 'label' => __( 'سبد خالی', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_CONTENT )
                );

                $this->add_control(
                        'empty_title',
                        array(
                                'label'   => __( 'عنوان', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'سبد خرید شما خالی است!', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'empty_desc',
                        array(
                                'label'   => __( 'توضیح', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXTAREA,
                                'rows'    => 2,
                                'default' => __( 'برای ادامه خرید می‌توانید از فروشگاه شروع کنید.', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->add_control(
                        'empty_btn',
                        array(
                                'label'   => __( 'متن دکمه', 'hamishop-elementor-widgets' ),
                                'type'    => Controls_Manager::TEXT,
                                'default' => __( 'بازگشت به فروشگاه', 'hamishop-elementor-widgets' ),
                        )
                );

                $this->end_controls_section();

                /* ------------------------- STYLE ------------------------- */
                $this->start_controls_section(
                        'sec_style',
                        array( 'label' => __( 'استایل', 'hamishop-elementor-widgets' ), 'tab' => Controls_Manager::TAB_STYLE )
                );

                $this->a_box( 'box', '{{WRAPPER}} .hsw-citems__box, {{WRAPPER}} .hsw-citems__mitem' );
                $this->a_color( 'head_bg', __( 'پس‌زمینه سربرگ باکس', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__head', 'background-color' );
                $this->a_text( 'head_t', '{{WRAPPER}} .hsw-citems__t', array( 'default' => 16 ) );
                $this->a_color( 'head_count_bg', __( 'پس‌زمینه شمارنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__count', 'background-color' );
                $this->a_text( 'head_count', '{{WRAPPER}} .hsw-citems__count', array( 'default' => 11 ) );
                $this->a_color( 'head_empty', __( 'دکمه خالی کردن سبد', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__empty' );

                $this->a_color( 'eyebrow', __( 'متن برند/دسته', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__eyebrow, {{WRAPPER}} .hsw-citems__meyebrow' );
                $this->a_text( 'name', '{{WRAPPER}} .hsw-citems__name, {{WRAPPER}} .hsw-citems__mname', array( 'default' => 14 ) );
                $this->a_color( 'chip_bg', __( 'پس‌زمینه چیپ ویژگی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__mchip', 'background-color' );
                $this->a_text( 'chip', '{{WRAPPER}} .hsw-citems__chip, {{WRAPPER}} .hsw-citems__mchip', array( 'default' => 12 ) );

                $this->a_color( 'price_old', __( 'قیمت خط‌خورده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__old, {{WRAPPER}} .hsw-citems__mold' );
                $this->a_color( 'disc_bg', __( 'پس‌زمینه نشان تخفیف', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__disc, {{WRAPPER}} .hsw-citems__mdisc', 'background-color' );
                $this->a_text( 'disc', '{{WRAPPER}} .hsw-citems__disc', array( 'default' => 11 ) );
                $this->a_color( 'price', __( 'قیمت اصلی', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-citems__price, {{WRAPPER}} .hsw-citems__mprice-v' );
                $this->a_color( 'cur', __( 'واحد پول', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-c-cur' );

                $this->a_color( 'qty_bd', __( 'حاشیه شمارنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-c-qty', 'border-color' );
                $this->a_color( 'qty_btn', __( 'دکمه‌های شمارنده', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-c-qty__btn' );

                $this->end_controls_section();
        }

        /* =========================================================================
         *  RENDER
         * ====================================================================== */

        protected function render() {
                $s     = $this->get_settings_for_display();
                $demo  = $this->is_demo();
                $items = $demo ? $this->demo_items() : $this->cart_items();
                $empty = ( ! $demo && empty( $items ) );

                if ( $empty ) {
                        $this->render_empty();
                        return;
                }
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'citems' ) ); ?>">

                        <!-- =================== DESKTOP =================== -->
                        <div class="hsw-d">
                                <div class="hsw-citems__box">
                                        <div class="hsw-citems__head">
                                                <div class="hsw-citems__head-l">
                                                        <span class="hsw-citems__t"><?php echo esc_html( $this->opt( 'box_title', __( 'اقلام سبد خرید', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                        <span class="hsw-citems__count"><?php echo esc_html( $this->with_count( $this->opt( 'count_tpl', __( '{count} کالا', 'hamishop-elementor-widgets' ) ), $demo ? 2 : $this->cart_count() ) ); ?></span>
                                                </div>
                                                <?php if ( 'yes' === ( $s['show_empty_btn'] ?? 'yes' ) && ( $demo || $this->has_cart() ) ) : ?>
                                                        <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-citems__emptyform">
                                                                <?php echo $this->empty_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                                                <button type="submit" name="hsw_empty_cart" value="1" class="hsw-citems__empty">
                                                                        <i class="hsw-ic" aria-hidden="true">delete_sweep</i>
                                                                        <span><?php echo esc_html( $this->opt( 'empty_btn_label', __( 'خالی کردن سبد', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                                                </button>
                                                        </form>
                                                <?php endif; ?>
                                        </div>

                                        <?php foreach ( $items as $item ) : ?>
                                                <?php $this->render_row( $item ); ?>
                                        <?php endforeach; ?>
                                </div>
                        </div>

                        <!-- =================== MOBILE =================== -->
                        <div class="hsw-citems__mlist hsw-m">
                                <?php foreach ( $items as $item ) : ?>
                                        <?php $this->render_mcard( $item ); ?>
                                <?php endforeach; ?>
                        </div>

                </section>
                <?php
        }

        /**
         * One desktop row.
         */
        protected function render_row( $item ) {
                $s            = $this->get_settings_for_display();
                $show_eyebrow = ( 'yes' === ( $s['show_eyebrow'] ?? 'yes' ) );
                $show_chips   = ( 'yes' === ( $s['show_chips'] ?? 'yes' ) );
                $show_warranty = ( 'yes' === ( $s['show_warranty'] ?? 'yes' ) );
                $warehouse    = trim( (string) ( $s['warehouse_text'] ?? '' ) );
                $show_old     = ( 'yes' === ( $s['show_old'] ?? 'yes' ) );
                $show_disc    = ( 'yes' === ( $s['show_disc'] ?? 'yes' ) );
                $show_fav     = ( 'yes' === ( $s['show_favorite'] ?? 'yes' ) );
                $show_del     = ( 'yes' === ( $s['show_del'] ?? 'yes' ) );
                $disc_tpl     = $this->opt( 'disc_tpl', __( '{percent}٪-', 'hamishop-elementor-widgets' ) );
                ?>
                <article class="hsw-citems__item">
                        <div class="hsw-citems__ident">
                                <a href="<?php echo esc_url( $item['url'] ); ?>" class="hsw-citems__thumb"><?php echo $item['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC image HTML. ?></a>
                                <div class="hsw-citems__info">
                                        <?php if ( $show_eyebrow && '' !== $item['eyebrow'] ) : ?>
                                                <span class="hsw-citems__eyebrow"><?php echo esc_html( $item['eyebrow'] ); ?></span>
                                        <?php endif; ?>
                                        <h3 class="hsw-citems__name"><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a></h3>
                                        <?php
                                        $chips = $show_chips ? $item['chips'] : array();
                                        if ( $show_warranty && '' !== $item['warranty'] ) {
                                                $chips[] = array( 'label' => $item['warranty'], 'hex' => '', 'icon' => 'verified' );
                                        }
                                        if ( '' !== $warehouse ) {
                                                $chips[] = array( 'label' => $warehouse, 'hex' => '', 'icon' => 'warehouse' );
                                        }
                                        if ( ! empty( $chips ) ) :
                                                $last = count( $chips ) - 1;
                                                ?>
                                                <div class="hsw-citems__meta">
                                                        <?php foreach ( $chips as $ci => $chip ) : ?>
                                                                <?php if ( $ci > 0 ) : ?>
                                                                        <span class="hsw-citems__sep" aria-hidden="true">•</span>
                                                                <?php endif; ?>
                                                                <span class="hsw-citems__chip">
                                                                        <?php if ( '' !== $chip['hex'] ) : ?>
                                                                                <span class="hsw-citems__dot" style="background:<?php echo esc_attr( $chip['hex'] ); ?>"></span>
                                                                        <?php elseif ( '' !== $chip['icon'] ) : ?>
                                                                                <i class="hsw-ic hsw-citems__chip-ic" aria-hidden="true"><?php echo esc_html( $chip['icon'] ); ?></i>
                                                                        <?php endif; ?>
                                                                        <?php echo esc_html( $chip['label'] ); ?>
                                                                </span>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        </div>

                        <div class="hsw-citems__side">
                                <div class="hsw-citems__pricing">
                                        <?php if ( $show_old && 0 < $item['regular'] ) : ?>
                                                <div class="hsw-citems__old-row">
                                                        <span class="hsw-citems__old"><?php echo esc_html( $this->money( $item['regular'] ) ); ?></span>
                                                        <?php if ( $show_disc && 0 < $item['disc'] ) : ?>
                                                                <span class="hsw-citems__disc"><?php echo esc_html( str_replace( '{percent}', HSW_Helpers::fa_num( $item['disc'] ), $disc_tpl ) ); ?></span>
                                                        <?php endif; ?>
                                                </div>
                                        <?php endif; ?>
                                        <div class="hsw-citems__price"><?php echo $this->money( $item['price'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- internal money HTML. ?></div>
                                </div>

                                <div class="hsw-citems__ops">
                                        <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-citems__qtyform">
                                                <?php echo $this->cart_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                                <div class="hsw-c-qty">
                                                        <button type="button" class="hsw-c-qty__btn" data-dir="plus" aria-label="<?php esc_attr_e( 'افزایش تعداد', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">add</i></button>
                                                        <input type="number" class="hsw-c-qty__in" inputmode="numeric" name="cart[<?php echo esc_attr( $item['key'] ); ?>][qty]" value="<?php echo esc_attr( $item['qty'] ); ?>" min="<?php echo esc_attr( $item['min'] ); ?>"<?php echo ( 0 < $item['max'] ) ? ' max="' . esc_attr( $item['max'] ) . '"' : ''; ?> readonly aria-label="<?php esc_attr_e( 'تعداد', 'hamishop-elementor-widgets' ); ?>">
                                                        <button type="button" class="hsw-c-qty__btn is-minus" data-dir="minus" aria-label="<?php esc_attr_e( 'کاهش تعداد', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">remove</i></button>
                                                </div>
                                                <button type="submit" name="update_cart" value="1" class="hsw-citems__update" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'به‌روزرسانی سبد', 'hamishop-elementor-widgets' ); ?></button>
                                        </form>

                                        <?php if ( $show_fav || $show_del ) : ?>
                                                <div class="hsw-citems__acts">
                                                        <?php if ( $show_fav ) : ?>
                                                                <button type="button" class="hsw-citems__fav" title="<?php esc_attr_e( 'ذخیره در علاقه‌مندی‌ها', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">favorite</i></button>
                                                        <?php endif; ?>
                                                        <?php if ( $show_del && '' !== $item['remove'] ) : ?>
                                                                <a href="<?php echo esc_url( $item['remove'] ); ?>" class="hsw-citems__del" title="<?php esc_attr_e( 'حذف کالا', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">delete</i></a>
                                                        <?php endif; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        </div>
                </article>
                <?php
        }

        /**
         * One mobile card.
         */
        protected function render_mcard( $item ) {
                $s            = $this->get_settings_for_display();
                $show_eyebrow = ( 'yes' === ( $s['show_eyebrow'] ?? 'yes' ) );
                $show_chips   = ( 'yes' === ( $s['show_chips'] ?? 'yes' ) );
                $show_warranty = ( 'yes' === ( $s['show_warranty'] ?? 'yes' ) );
                $show_old     = ( 'yes' === ( $s['show_old'] ?? 'yes' ) );
                $show_disc    = ( 'yes' === ( $s['show_disc'] ?? 'yes' ) );
                $show_del     = ( 'yes' === ( $s['show_del'] ?? 'yes' ) );
                ?>
                <article class="hsw-citems__mitem">
                        <?php if ( $show_disc && 0 < $item['disc'] ) : ?>
                                <span class="hsw-citems__mdisc"><?php echo esc_html( sprintf( /* translators: %s: discount percent */ __( '%s٪ تخفیف', 'hamishop-elementor-widgets' ), HSW_Helpers::fa_num( $item['disc'] ) ) ); ?></span>
                        <?php endif; ?>

                        <div class="hsw-citems__mrow">
                                <a href="<?php echo esc_url( $item['url'] ); ?>" class="hsw-citems__mthumb"><?php echo $item['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC image HTML. ?></a>
                                <div class="hsw-citems__minfo">
                                        <?php if ( $show_eyebrow && '' !== $item['eyebrow'] ) : ?>
                                                <span class="hsw-citems__meyebrow"><?php echo esc_html( $item['eyebrow'] ); ?></span>
                                        <?php endif; ?>
                                        <h2 class="hsw-citems__mname"><?php echo esc_html( $item['name'] ); ?></h2>
                                        <?php if ( $show_chips && ! empty( $item['chips'] ) ) : ?>
                                                <div class="hsw-citems__mchips">
                                                        <?php foreach ( $item['chips'] as $chip ) : ?>
                                                                <span class="hsw-citems__mchip">
                                                                        <?php if ( '' !== $chip['hex'] ) : ?>
                                                                                <span class="hsw-citems__dot" style="background:<?php echo esc_attr( $chip['hex'] ); ?>"></span>
                                                                        <?php endif; ?>
                                                                        <?php echo esc_html( $chip['label'] ); ?>
                                                                </span>
                                                        <?php endforeach; ?>
                                                </div>
                                        <?php endif; ?>
                                </div>
                        </div>

                        <?php if ( $show_warranty && '' !== $item['warranty'] ) : ?>
                                <div class="hsw-citems__mwarranty">
                                        <i class="hsw-ic" aria-hidden="true">verified_user</i>
                                        <span><?php echo esc_html( $item['warranty'] ); ?></span>
                                </div>
                        <?php endif; ?>

                        <div class="hsw-citems__mctrl<?php echo ( ! $show_warranty || '' === $item['warranty'] ) ? ' is-divided' : ''; ?>">
                                <form method="post" action="<?php echo esc_url( $this->cart_url() ); ?>" class="hsw-citems__qtyform">
                                        <?php echo $this->cart_nonce(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hidden nonce inputs. ?>
                                        <div class="hsw-c-qty hsw-citems__mqty">
                                                <button type="button" class="hsw-c-qty__btn is-plus" data-dir="plus" aria-label="<?php esc_attr_e( 'افزایش تعداد', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">add</i></button>
                                                <input type="hidden" name="cart[<?php echo esc_attr( $item['key'] ); ?>][qty]" value="<?php echo esc_attr( $item['qty'] ); ?>">
                                                <span class="hsw-c-qty__v"><?php echo esc_html( HSW_Helpers::fa_num( $item['qty'] ) ); ?></span>
                                                <?php if ( $show_del && '' !== $item['remove'] ) : ?>
                                                        <a href="<?php echo esc_url( $item['remove'] ); ?>" class="hsw-citems__mremove" aria-label="<?php esc_attr_e( 'حذف یا کاهش', 'hamishop-elementor-widgets' ); ?>"><i class="hsw-ic" aria-hidden="true">delete</i></a>
                                                <?php endif; ?>
                                        </div>
                                        <button type="submit" name="update_cart" value="1" class="hsw-citems__update" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'به‌روزرسانی سبد', 'hamishop-elementor-widgets' ); ?></button>
                                </form>

                                <div class="hsw-citems__mprice">
                                        <?php if ( $show_old && 0 < $item['regular'] ) : ?>
                                                <span class="hsw-citems__mold"><?php echo esc_html( $this->money( $item['regular'] ) ); ?></span>
                                        <?php endif; ?>
                                        <div class="hsw-citems__mprice-row">
                                                <span class="hsw-citems__mprice-v"><?php echo esc_html( $this->money( $item['price'] ) ); ?></span>
                                                <span class="hsw-c-cur"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                        </div>
                                </div>
                        </div>
                </article>
                <?php
        }

        /**
         * Empty-cart card.
         */
        protected function render_empty() {
                ?>
                <section class="<?php echo esc_attr( $this->root_class( 'citems' ) ); ?>">
                        <div class="hsw-c-empty">
                                <span class="hsw-c-empty__ic"><i class="hsw-ic" aria-hidden="true">shopping_bag</i></span>
                                <h3 class="hsw-c-empty__title"><?php echo esc_html( $this->opt( 'empty_title', __( 'سبد خرید شما خالی است!', 'hamishop-elementor-widgets' ) ) ); ?></h3>
                                <p class="hsw-c-empty__desc"><?php echo esc_html( $this->opt( 'empty_desc', '' ) ); ?></p>
                                <a class="hsw-c-empty__btn" href="<?php echo esc_url( $this->shop_url() ); ?>">
                                        <span><?php echo esc_html( $this->opt( 'empty_btn', __( 'بازگشت به فروشگاه', 'hamishop-elementor-widgets' ) ) ); ?></span>
                                        <i class="hsw-ic" aria-hidden="true">arrow_back</i>
                                </a>
                        </div>
                </section>
                <?php
        }
}
