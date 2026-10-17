<?php
/**
 * Shared product-card renderer.
 *
 * Used by both the "Loop Item" widget and the "Shop Archive" widget so the
 * DOM contract (hsw-card__*) stays identical everywhere and Elementor style
 * selectors keep working on both.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders one product card.
 */
final class HSW_Card_Renderer {

        /**
         * Default card arguments (mirrors widget setting keys).
         *
         * @return array
         */
        public static function defaults() {
                return [
                        'card_layout'      => 'vertical',
                        'show_badge'       => 'yes',
                        'show_badge_new'   => 'no',
                        'new_badge_days'   => 14,
                        'badge_text_new'   => '',
                        'show_image'       => 'yes',
                        'image_hover_swap' => 'no',
                        'image_hover_zoom' => 'yes',
                        'show_cats'        => 'no',
                        'cats_count'       => 1,
                        'show_title'       => 'yes',
                        'title_tag'        => 'h3',
                        'show_rating'      => 'no',
                        'show_rating_count'=> 'no',
                        'show_excerpt'     => 'no',
                        'excerpt_length'   => 12,
                        'show_price'       => 'yes',
                        'show_cart'        => 'yes',
                        'classes'          => [],
                ];
        }

        /**
         * Echo one product card.
         *
         * @param \WC_Product $product  Product to render.
         * @param array       $settings Widget settings (keys as in defaults()).
         * @return void
         */
        public static function render( $product, array $settings = [] ) {
                $o = wp_parse_args( $settings, self::defaults() );

                $classes   = (array) $o['classes'];
                $classes[] = 'hsw-card';
                if ( 'horizontal' === $o['card_layout'] ) {
                        $classes[] = 'hsw-card--horizontal';
                }
                if ( 'yes' === $o['image_hover_zoom'] ) {
                        $classes[] = 'hsw-card--zoom';
                }

                $title_tag = in_array( $o['title_tag'], [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $o['title_tag'] : 'h3';
                $permalink = get_permalink( $product->get_id() );
                ?>
                <article class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ); ?>" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">

                        <?php if ( 'yes' === $o['show_image'] ) : ?>
                                <div class="hsw-card__media">
                                        <a class="hsw-card__media-link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
                                                <?php
                                                echo HSW_Helpers::product_image( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.

                                                if ( 'yes' === $o['image_hover_swap'] ) {
                                                        echo HSW_Helpers::product_hover_image( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
                                                }
                                                ?>
                                        </a>

                                        <?php if ( 'yes' === $o['show_badge'] ) : ?>
                                                <?php $percent = HSW_Helpers::sale_percent( $product ); ?>
                                                <?php if ( $percent > 0 ) : ?>
                                                        <span class="hsw-card__badge hsw-card__badge--sale">
                                                                <?php echo esc_html( HSW_Helpers::maybe_fa( '٪' . (string) $percent ) ); ?>
                                                        </span>
                                                <?php endif; ?>

                                                <?php if ( 'yes' === $o['show_badge_new'] && HSW_Helpers::is_new( $product, (int) $o['new_badge_days'] ) ) : ?>
                                                        <span class="hsw-card__badge hsw-card__badge--new">
                                                                <?php echo esc_html( '' !== (string) $o['badge_text_new'] ? $o['badge_text_new'] : __( 'New', 'hamishop-elementor-widgets' ) ); ?>
                                                        </span>
                                                <?php endif; ?>
                                        <?php endif; ?>
                                </div>
                        <?php endif; ?>

                        <div class="hsw-card__body">

                                <?php if ( 'yes' === $o['show_cats'] ) : ?>
                                        <div class="hsw-card__cats">
                                                <?php echo HSW_Helpers::product_categories( $product, (int) $o['cats_count'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( 'yes' === $o['show_title'] ) : ?>
                                        <<?php echo tag_escape( $title_tag ); ?> class="hsw-card__title">
                                                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
                                        </<?php echo tag_escape( $title_tag ); ?>>
                                <?php endif; ?>

                                <?php if ( 'yes' === $o['show_rating'] ) : ?>
                                        <div class="hsw-card__rating">
                                                <?php echo HSW_Helpers::rating_stars( $product, 'yes' === $o['show_rating_count'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( 'yes' === $o['show_excerpt'] ) : ?>
                                        <div class="hsw-card__excerpt">
                                                <?php echo esc_html( HSW_Helpers::maybe_fa( HSW_Helpers::trim_words( $product->get_short_description(), (int) $o['excerpt_length'] ) ) ); ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( 'yes' === $o['show_price'] ) : ?>
                                        <div class="hsw-card__price">
                                                <?php echo HSW_Helpers::maybe_fa( $product->get_price_html() ); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?>
                                        </div>
                                <?php endif; ?>

                                <?php if ( 'yes' === $o['show_cart'] ) : ?>
                                        <div class="hsw-card__actions">
                                                <?php echo HSW_Helpers::add_to_cart_markup( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce generates escaped markup. ?>
                                        </div>
                                <?php endif; ?>

                        </div>
                </article>
                <?php
        }

        /**
         * Sanitize a text setting that is echoed as-is (e.g. badge text).
         *
         * @param mixed $value Raw value.
         * @return string
         */
        public static function text( $value ) {
                return sanitize_text_field( (string) $value );
        }
}
