<?php
/** Regression tests; run only on the CI disposable WordPress database. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
define( 'WP_ADMIN', true );
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
$root = dirname( __DIR__, 2 );
require_once $root . '/includes/trait-hsw-style-kit.php';
require_once $root . '/includes/class-hsw-widget-base.php';
require_once $root . '/includes/class-hsw-blog-base.php';
require_once $root . '/widgets/class-hsw-widget-post-page.php';
class HSW_QA_Post_Page extends HSW_Widget_Post_Page {
    public function content_probe( $post, $mode ) { return $this->prepared_content( $post, $mode ); }
    public function toc_probe( $item, $mode, $variant = 'main' ) { return $this->toc_link_html( $item, $mode, $variant ); }
    public function comment_probe( $post ) { ob_start(); $this->render_comments( $post ); return ob_get_clean(); }
    public function product_probe( $product ) { ob_start(); $this->render_product_card( $product, array() ); return ob_get_clean(); }
    public function dock_probe( $post ) { ob_start(); $this->render_dock( $post, null, 29, true ); return ob_get_clean(); }
}
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$widget = new HSW_QA_Post_Page();
$post_id = wp_insert_post( array( 'post_title' => 'QA article', 'post_status' => 'publish', 'comment_status' => 'open', 'post_content' => '<h2>Same &amp; special</h2><div id="hsw-h-1"></div><h3 id="hsw-h-2">Authored</h3><h2>Same &amp; special</h2>' ) );
$post = get_post( $post_id );
$GLOBALS['post'] = $post;
setup_postdata( $post );
$content = $widget->content_probe( $post, 'index' );
$check( 'reserved IDs on later elements are not generated', $content['toc'][0]['id'] === 'hsw-h-3' && $content['toc'][1]['id'] === 'hsw-h-2' && $content['toc'][2]['id'] === 'hsw-h-4' );
$check( 'decoded headings are safely escaped once', false !== strpos( $widget->toc_probe( $content['toc'][0], 'index' ), 'Same &amp; special' ) && false === strpos( $widget->toc_probe( $content['toc'][0], 'index' ), '&amp;amp;' ) );
$check( 'legacy none remains a button', false !== strpos( $widget->toc_probe( $content['toc'][0], 'none' ), '<button' ) );
$link = $widget->toc_probe( $content['toc'][0], 'index' );
$check( 'fragment is a working anchor without unnecessary nofollow', false !== strpos( $link, 'href="#hsw-h-3"' ) && false === strpos( $link, 'nofollow' ) );
$side = $widget->toc_probe( $content['toc'][0], 'index', 'side' );
$check( 'sidebar uses real fragment links in link mode', false !== strpos( $side, '<a ' ) && false !== strpos( $side, 'href="#hsw-h-3"' ) );
$check( 'sidebar preserves scroll-only mode', false !== strpos( $widget->toc_probe( $content['toc'][0], 'none', 'side' ), '<button' ) );
$form = $widget->comment_probe( $post );
$check( 'no-JS comment form supplies admin-post action', false !== strpos( $form, 'name="action" value="hsw_submit_comment"' ) && false !== strpos( $form, 'admin-post.php' ) );
$dock = $widget->dock_probe( $post );
$check( 'local like is not presented as comment count', false === strpos( $dock, '>۲۹</span>' ) && false !== strpos( $dock, 'پسندیدن در این مرورگر' ) );
$product = new WC_Product_Simple(); $product->set_name( 'QA product' ); $product->set_status( 'publish' ); $product->set_regular_price( '100' ); $product->set_sale_price( '80' ); $product->save();
$html = $widget->product_probe( $product );
$check( 'sale price markup has no extra old price', substr_count( $html, '<del' ) === 1 );
$controls = $widget->get_controls();
$check( 'body color also targets paragraphs', false !== strpos( implode( ' ', array_keys( $controls['body_c']['selectors'] ?? array() ) ), '.hsw-pp__body p' ) );
wp_delete_post( $post_id, true ); $product->delete( true ); wp_reset_postdata();
exit( $failed ? 1 : 0 );
