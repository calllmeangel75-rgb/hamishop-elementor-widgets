<?php
/** Paginated featured-post exclusion on a disposable WordPress database. */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'HSW_QA_DISPOSABLE' ) ) { exit( 2 ); }
define( 'WP_ADMIN', true );
require getenv( 'HSW_WP_LOAD' );
if ( 'production' === wp_get_environment_type() ) { exit( 2 ); }
$root = dirname( __DIR__, 2 );
require_once $root . '/includes/trait-hsw-style-kit.php';
require_once $root . '/includes/class-hsw-widget-base.php';
require_once $root . '/includes/class-hsw-blog-base.php';
require_once $root . '/widgets/class-hsw-widget-blog-grid.php';
require_once $root . '/widgets/class-hsw-widget-blog-newsletter.php';
class HSW_QA_Grid extends HSW_Widget_Blog_Grid { public function html() { ob_start(); $this->render(); return ob_get_clean(); } }
class HSW_QA_News extends HSW_Widget_Blog_Newsletter { public function html() { ob_start(); $this->render(); return ob_get_clean(); } }
$failed = 0;
$check = static function ( $name, $ok ) use ( &$failed ) { echo ( $ok ? 'PASS ' : '::error::FAIL ' ) . $name . PHP_EOL; if ( ! $ok ) { ++$failed; } };
$category = wp_insert_term( 'QA Archive', 'category', array( 'slug' => 'hsw-qa-archive' ) );
$ids = array();
for ( $i = 1; $i <= 14; ++$i ) {
    $ids[] = wp_insert_post( array( 'post_title' => sprintf( 'HSW-ARCHIVE-%02d', $i ), 'post_status' => 'publish', 'post_category' => array( $category['term_id'] ), 'post_date' => sprintf( '2024-01-%02d 12:00:00', $i ) ) );
}
$widget = new HSW_QA_Grid();
$widget->hsw_inject_settings( array( 'per_page' => 6, 'skip_first' => 'yes', 'show_pagination' => 'yes' ) );
$_GET = array( 'blog_cat' => 'hsw-qa-archive', 'blog_page' => 1 );
$first = $widget->html();
$_GET['blog_page'] = 2;
$second = $widget->html();
preg_match_all( '/HSW-ARCHIVE-\d\d/', $first, $m1 );
preg_match_all( '/HSW-ARCHIVE-\d\d/', $second, $m2 );
$a = array_values( array_unique( $m1[0] ) ); $b = array_values( array_unique( $m2[0] ) );
$check( 'six grid items per first and second page', count( $a ) === 6 && count( $b ) === 6 );
$check( 'no featured article or duplicate across pages', ! in_array( 'HSW-ARCHIVE-14', $a, true ) && ! in_array( 'HSW-ARCHIVE-14', $b, true ) && ! array_intersect( $a, $b ) );
$news = new HSW_QA_News(); $news->hsw_inject_settings( array() );
$check( 'unconfigured newsletter does not present a fake email form', false === strpos( $news->html(), '<form' ) );
foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
wp_delete_term( $category['term_id'], 'category' );
exit( $failed ? 1 : 0 );
