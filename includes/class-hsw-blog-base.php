<?php
/**
 * Shared base for the blog / magazine widget family.
 *
 * Pure data + layout helpers: real WP_Query posts, categories, tags,
 * reading-time estimate, server-side category/sort/pagination filters
 * (query params — SEO friendly, no JS required for the core flows).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

abstract class HSW_Blog_Base extends HSW_Widget_Base {

        public function get_categories() {
                return array( 'hamishop-blog' );
        }

        public function get_style_depends() {
                return array( 'hsw-fonts', 'hsw-blog' );
        }

        public function get_script_depends() {
                return array();
        }

        /**
         * Root classes for a blog widget (mirrors the other family bases).
         */
        protected function root_class( $slug, $extra = '' ) {
                $s   = $this->effective_settings();
                $vis = isset( $s['vis'] ) ? sanitize_html_class( $s['vis'] ) : 'auto';
                $cls = 'hsw-b hsw-' . $slug . ' hsw-vis-' . $vis . $this->hsw_font_class();
                if ( $extra ) {
                        $cls .= ' ' . $extra;
                }
                return $cls;
        }

        /**
         * Visibility override: auto (design default) / desktop / mobile.
         */
        protected function ctrl_visibility( $default = 'auto' ) {
                $this->add_control(
                        'vis',
                        array(
                                'label'   => __( 'نمایش در', 'hamishop-elementor-widgets' ),
                                'type'    => \Elementor\Controls_Manager::SELECT,
                                'default' => $default,
                                'options' => array(
                                        'auto'    => __( 'پیش‌فرض طرح', 'hamishop-elementor-widgets' ),
                                        'desktop' => __( 'فقط دسکتاپ', 'hamishop-elementor-widgets' ),
                                        'mobile'  => __( 'فقط موبایل', 'hamishop-elementor-widgets' ),
                                ),
                        )
                );
        }

        /* ---------------------------------------------------------------------
         *  URL / FILTER STATE
         * ------------------------------------------------------------------ */

        /**
         * Base URL of the page hosting the blog.
         *
         * Never call `get_permalink()` without an argument here: on an archive
         * or on the posts page the global $post is the FIRST POST of the loop,
         * so every filter link (category pills, sort options, pagination)
         * pointed at that single article — clicking "پربحث‌ترین" opened a post
         * instead of re-sorting the archive.
         *
         * The current request path is the only reliable source: it is correct
         * for a static page, the posts page, a category archive and a search.
         *
         * @return string
         */
        protected function blog_base_url() {
                /*
                 * On a single post the current path IS the article, so every
                 * link built from it — «مجله», «بازگشت به مجله», «مشاهده همه»
                 * and the breadcrumb category — pointed back at the article the
                 * visitor was already reading. The posts page is the right base
                 * there; the rest of the method is for archive and page views.
                 */
                if ( function_exists( 'is_singular' ) && is_singular( 'post' ) ) {
                        $posts_page = (int) get_option( 'page_for_posts' );
                        $target     = $posts_page ? (string) get_permalink( $posts_page ) : '';

                        return '' !== $target ? $target : home_url( '/' );
                }

                $base = '';

                $request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed below, never echoed raw.
                if ( '' !== $request ) {
                        $path  = (string) wp_parse_url( $request, PHP_URL_PATH );
                        $query = (string) wp_parse_url( $request, PHP_URL_QUERY );
                        $home  = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

                        /* home_url() already carries the subdirectory, so strip it
                           from the request path before re-attaching. */
                        if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
                                $path = substr( $path, strlen( $home ) );
                        }

                        $base = home_url( '/' . ltrim( $path, '/' ) );
                        if ( '' !== $query ) {
                                $base .= '?' . $query;
                        }
                }

                if ( '' === $base ) {
                        $object_id = (int) get_queried_object_id();
                        $base      = $object_id ? (string) get_permalink( $object_id ) : home_url( '/' );
                }

                /* Drop our own filters and pagination; blog_url() re-adds them. */
                $base = remove_query_arg(
                        array( 'blog_cat', 'blog_sort', 'blog_s', 'blog_page', 'blog_search', 'paged', 'page' ),
                        $base
                );

                return '' !== $base ? $base : home_url( '/' );
        }

        /**
         * Current filter state from query params (cat, sort, search, page).
         *
         * @return array{cat:string,sort:string,s:string,page:int}
         */
        protected function blog_filters() {
                $cat = isset( $_GET['blog_cat'] ) ? sanitize_title( wp_unslash( $_GET['blog_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filter.
                if ( '' === $cat && function_exists( 'is_category' ) && is_category() ) {
                        $queried = get_queried_object();
                        if ( $queried instanceof WP_Term && ! empty( $queried->slug ) ) {
                                $cat = (string) $queried->slug;
                        }
                }
                $page = isset( $_GET['blog_page'] ) ? max( 1, (int) $_GET['blog_page'] ) : max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return array(
                        'cat'  => $cat,
                        'sort' => isset( $_GET['blog_sort'] ) ? sanitize_key( wp_unslash( $_GET['blog_sort'] ) ) : '',    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        's'    => isset( $_GET['blog_s'] ) ? sanitize_text_field( wp_unslash( $_GET['blog_s'] ) ) : '',   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        'page' => $page,
                );
        }

        /**
         * Build a blog URL keeping the other filters, with overrides.
         *
         * @param array $overrides Keys: cat, sort, s, page.
         * @return string
         */
        protected function blog_url( $overrides = array() ) {
                $f = array_merge( $this->blog_filters(), $overrides );
                $q = array();
                if ( '' !== $f['cat'] ) {
                        $q['blog_cat'] = $f['cat'];
                }
                if ( '' !== $f['sort'] ) {
                        $q['blog_sort'] = $f['sort'];
                }
                if ( '' !== $f['s'] ) {
                        $q['blog_s'] = $f['s'];
                }
                if ( $f['page'] > 1 ) {
                        $q['blog_page'] = $f['page'];
                }
                return $q ? add_query_arg( $q, $this->blog_base_url() ) : $this->blog_base_url();
        }

        /* ---------------------------------------------------------------------
         *  DATA
         * ------------------------------------------------------------------ */

        /**
         * WP_Query over real posts with the current filters applied.
         *
         * @param array $args Overrides (posts_per_page, paged, ignore_filters...).
         * @return WP_Query
         */
        protected function blog_query( $args = array() ) {
                $f = $this->blog_filters();

                $defaults = array(
                        'post_type'           => 'post',
                        'post_status'         => 'publish',
                        'ignore_sticky_posts' => true,
                        'posts_per_page'      => 6,
                        'paged'               => $f['page'],
                );

                if ( empty( $args['ignore_filters'] ) ) {
                        if ( '' !== $f['cat'] ) {
                                $defaults['category_name'] = $f['cat'];
                        }
                        if ( '' !== $f['s'] ) {
                                $defaults['s'] = $f['s'];
                        }
                        switch ( $f['sort'] ) {
                                case 'popular':
                                        $defaults['orderby'] = 'comment_count';
                                        $defaults['order']   = 'DESC';
                                        break;
                                case 'oldest':
                                        $defaults['orderby'] = 'date';
                                        $defaults['order']   = 'ASC';
                                        break;
                                default:
                                        $defaults['orderby'] = 'date';
                                        $defaults['order']   = 'DESC';
                        }
                }

                unset( $args['ignore_filters'] );
                return new WP_Query( array_merge( $defaults, $args ) );
        }

        /**
         * Estimated reading time (minutes) — Persian-friendly word count.
         */
        protected function read_time( $post ) {
                $text  = wp_strip_all_tags( (string) $post->post_content );
                $words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
                $count = is_array( $words ) ? count( $words ) : 0;
                return max( 1, (int) ceil( $count / 200 ) );
        }

        /**
         * Localized, fa-digit date of a post.
         */
        protected function post_date( $post ) {
                $post = get_post( $post );
                if ( ! $post instanceof WP_Post ) {
                        return '';
                }

                /*
                 * Format here instead of trusting the date_i18n/get_the_date
                 * filter: Elementor and some themes print the date through their
                 * own pipeline, and the article hero was the one place that kept
                 * showing Gregorian even with the calendar switched on.
                 */
                if ( class_exists( 'HSW_Jalali' ) && HSW_Jalali::is_on() ) {
                        $ts = strtotime( $post->post_date );
                        if ( $ts ) {
                                return HSW_Helpers::fa_num( HSW_Jalali::format( $ts, (string) get_option( 'date_format', 'F j, Y' ) ) );
                        }
                }

                return HSW_Helpers::fa_num( get_the_date( '', $post ) );
        }

        /**
         * First category of a post (name + url), or null.
         *
         * @return array{name:string,url:string}|null
         */
        protected function post_category( $post ) {
                $cats = get_the_category( $post->ID );
                if ( empty( $cats ) ) {
                        return null;
                }
                /*
                 * On a single post the breadcrumb should reach the category
                 * archive itself. `blog_url()` there points at the posts page,
                 * so `?blog_cat=` on top of it is a filter, not the archive.
                 */
                if ( function_exists( 'is_singular' ) && is_singular( 'post' ) ) {
                        $link = get_category_link( $cats[0] );
                        if ( ! is_wp_error( $link ) && '' !== (string) $link ) {
                                return array(
                                        'name' => $cats[0]->name,
                                        'url'  => (string) $link,
                                );
                        }
                }

                return array(
                        'name' => $cats[0]->name,
                        'url'  => $this->blog_url( array( 'cat' => $cats[0]->slug, 'page' => 1 ) ),
                );
        }

        /**
         * Local avatar-less author name.
         */
        protected function post_author( $post ) {
                return get_the_author_meta( 'display_name', (int) $post->post_author );
        }

        /**
         * Card image (or a neutral gradient placeholder when no thumbnail).
         */
        protected function post_image( $post, $size = 'medium_large', $priority = false ) {
                if ( has_post_thumbnail( $post ) ) {
                        return get_the_post_thumbnail(
                                $post,
                                $size,
                                array(
                                        /* skip-lazy / data-no-lazy opt out of LiteSpeed + WP Rocket lazy load. */
                                        'class'         => $priority ? 'hsw-blog__img skip-lazy' : 'hsw-blog__img',
                                        'loading'       => $priority ? 'eager' : 'lazy',
                                        'fetchpriority' => $priority ? 'high' : 'auto',
                                        'data-no-lazy'  => $priority ? '1' : '',
                                )
                        );
                }
                return '<span class="hsw-blog__img hsw-blog__img--ph" aria-hidden="true"></span>';
        }

        /**
         * Top blog categories (by post count, hide empty).
         *
         * @return WP_Term[]
         */
        protected function blog_categories( $limit = 6 ) {
                $terms = get_categories(
                        array(
                                'orderby'    => 'count',
                                'order'      => 'DESC',
                                'hide_empty' => true,
                                'number'     => max( 1, (int) $limit ),
                        )
                );
                return is_array( $terms ) ? $terms : array();
        }

        /**
         * Popular posts — WP has no native view counter, so comment count is
         * used as the popularity signal (label is configurable in the widget).
         *
         * @return WP_Post[]
         */
        protected function popular_posts( $count = 4, $days = 0 ) {
                $args = array(
                        'post_type'           => 'post',
                        'post_status'         => 'publish',
                        'ignore_sticky_posts' => true,
                        'posts_per_page'      => max( 1, (int) $count ),
                        'orderby'             => 'comment_count',
                        'order'               => 'DESC',
                );
                if ( $days > 0 ) {
                        $args['date_query'] = array(
                                array( 'after' => $days . ' days ago' ),
                        );
                }
                $q = new WP_Query( $args );

                /* Fallback: not enough recent posts → drop the date window. */
                if ( $days > 0 && count( $q->posts ) < $count ) {
                        unset( $args['date_query'] );
                        $q = new WP_Query( $args );
                }
                return $q->posts;
        }

        /**
         * Tag list for the tag-cloud widget.
         *
         * @return WP_Term[]
         */
        protected function blog_tags( $limit = 12 ) {
                $terms = get_tags(
                        array(
                                'orderby'    => 'count',
                                'order'      => 'DESC',
                                'hide_empty' => true,
                                'number'     => max( 1, (int) $limit ),
                        )
                );
                return is_array( $terms ) ? $terms : array();
        }

        /**
         * Comments count of a post, fa digits.
         */
        protected function comment_count( $post ) {
                return HSW_Helpers::fa_num( (int) get_comments_number( $post ) );
        }

        /**
         * Sort options for the toolbar widget.
         */
        protected function sort_options() {
                return array(
                        'newest'  => __( 'جدیدترین', 'hamishop-elementor-widgets' ),
                        'popular' => __( 'پربحث‌ترین', 'hamishop-elementor-widgets' ),
                        'oldest'  => __( 'قدیمی‌ترین', 'hamishop-elementor-widgets' ),
                );
        }

        /**
         * Editor preview without a real blog archive → render the design demo.
         *
         * `blog_archive_description_data()` has called this since the SEO box
         * shipped, but the method only ever existed on the archive, cart and
         * product bases. Rendering the blog page widget threw
         * `Call to undefined method HSW_Widget_Blog_Page::is_demo()`, the
         * error handler caught it, and everything after that call — the whole
         * description box — never rendered.
         *
         * The guard is the same shape as the other bases: a real archive means
         * real data, the editor without one means the sample text.
         */
        protected function is_demo() {
                if ( is_home() || is_category() || is_tag() || is_date() || is_author() || is_tax() ) {
                        return false;
                }
                if ( ! class_exists( '\Elementor\Plugin' ) ) {
                        return false;
                }

                $el      = \Elementor\Plugin::$instance;
                $editing = ( isset( $el->editor ) && $el->editor->is_edit_mode() )
                        || ( isset( $el->preview ) && $el->preview->is_preview_mode() )
                        || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_POST['action'] ) && 'elementor_ajax' === $_POST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

                return $editing;
        }

        /**
         * Resolve the active post category / archive description for the bottom SEO box.
         *
         * Works both on native WordPress category/tag archives (`is_category()`,
         * `is_tag()`, `is_tax()`) and when a category tab is active via `?blog_cat=slug`.
         *
         * @return array{name:string,html:string}|null
         */
        protected function blog_archive_description_data() {
                if ( $this->is_demo() ) {
                        return array(
                                'name' => __( 'تکنولوژی و گجت', 'hamishop-elementor-widgets' ),
                                'html' => '<p>' . esc_html__( 'در این بخش جدیدترین مقالات تخصصی، راهنمای خرید، بررسی عمیق محصولات و اخبار روز دنیای فناوری را مطالعه می‌کنید. برای نمایش این کادر در سایت، کافی است در پیشخوان وردپرس (نوشته‌ها ← دسته‌ها) متن «توضیح» دسته‌بندی مورد نظر را تکمیل کنید.', 'hamishop-elementor-widgets' ) . '</p>',
                        );
                }

                $term = null;
                $f    = $this->blog_filters();
                if ( '' !== $f['cat'] ) {
                        $by_slug = get_term_by( 'slug', $f['cat'], 'category' );
                        if ( $by_slug instanceof WP_Term ) {
                                $term = $by_slug;
                        }
                }
                if ( ! $term && ( is_category() || is_tag() || is_tax() ) ) {
                        $queried = get_queried_object();
                        if ( $queried instanceof WP_Term ) {
                                $term = $queried;
                        }
                }

                if ( $term instanceof WP_Term ) {
                        $raw = term_description( (int) $term->term_id, (string) $term->taxonomy );
                        if ( '' === trim( wp_strip_all_tags( (string) $raw ) ) && '' !== trim( (string) $term->description ) ) {
                                $raw = wpautop( (string) $term->description );
                        }
                        if ( '' !== trim( wp_strip_all_tags( (string) $raw ) ) ) {
                                return array(
                                        'name' => (string) $term->name,
                                        'html' => wp_kses_post( (string) $raw ),
                                );
                        }
                }

                if ( function_exists( 'get_the_archive_description' ) && is_archive() ) {
                        $arch_desc = (string) get_the_archive_description();
                        if ( '' !== trim( wp_strip_all_tags( $arch_desc ) ) ) {
                                return array(
                                        'name' => wp_strip_all_tags( (string) get_the_archive_title() ),
                                        'html' => wp_kses_post( $arch_desc ),
                                );
                        }
                }

                return null;
        }

        /**
         * Print the bottom category/archive description box when available.
         *
         * @param string $title_tpl Optional heading pattern (`{name}` is replaced with term name).
         * @return void
         */
        protected function render_blog_archive_description( $title_tpl = '' ) {
                $desc = $this->blog_archive_description_data();
                if ( ! $desc || '' === trim( $desc['html'] ) ) {
                        return;
                }
                $heading = '';
                if ( '' !== trim( (string) $title_tpl ) ) {
                        $heading = str_replace( '{name}', $desc['name'], (string) $title_tpl );
                } elseif ( '' !== $desc['name'] ) {
                        $heading = sprintf( __( 'دربارهٔ %s', 'hamishop-elementor-widgets' ), $desc['name'] );
                }
                ?>
                <section class="hsw-bdesc" aria-label="<?php echo esc_attr( '' !== $heading ? $heading : __( 'توضیحات دسته‌بندی', 'hamishop-elementor-widgets' ) ); ?>">
                        <?php if ( '' !== trim( $heading ) ) : ?>
                                <h2 class="hsw-bdesc__title"><?php echo esc_html( $heading ); ?></h2>
                        <?php endif; ?>
                        <div class="hsw-bdesc__body"><?php echo $desc['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses_post(). ?></div>
                </section>
                <?php
        }

}
