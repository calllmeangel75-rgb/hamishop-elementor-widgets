<?php
/**
 * 404 screen (rendered through template_include, so header/footer stay).
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

$hsw_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$hsw_home = home_url( '/' );
?>
<?php get_header(); ?>
<style id="hsw-404-css"><?php echo HSW_404::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS. ?></style>
<main class="hsw-404" role="main">
	<span class="hsw-404__orb hsw-404__orb--1" aria-hidden="true"></span>
	<span class="hsw-404__orb hsw-404__orb--2" aria-hidden="true"></span>
	<span class="hsw-404__orb hsw-404__orb--3" aria-hidden="true"></span>

	<section class="hsw-404__card">
		<span class="hsw-404__code" aria-hidden="true">۴۰۴</span>
		<h1 class="hsw-404__title"><?php esc_html_e( 'صفحه‌ای که دنبالش بودید پیدا نشد!', 'hamishop-elementor-widgets' ); ?></h1>
		<p class="hsw-404__desc"><?php esc_html_e( 'ممکن است آدرس را اشتباه وارد کرده باشید یا این صفحه جابه‌جا شده باشد. از جستجوی زیر استفاده کنید یا به فروشگاه برگردید.', 'hamishop-elementor-widgets' ); ?></p>

		<form class="hsw-404__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="hsw-404-s"><?php esc_html_e( 'جستجوی محصول', 'hamishop-elementor-widgets' ); ?></label>
			<input type="search" id="hsw-404-s" name="s" placeholder="<?php esc_attr_e( 'نام محصول را جستجو کنید…', 'hamishop-elementor-widgets' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit"><?php esc_html_e( 'جستجو', 'hamishop-elementor-widgets' ); ?></button>
		</form>

		<div class="hsw-404__actions">
			<a class="hsw-404__cta" href="<?php echo esc_url( $hsw_shop ); ?>"><?php esc_html_e( 'ورود به فروشگاه', 'hamishop-elementor-widgets' ); ?></a>
			<a class="hsw-404__ghost" href="<?php echo esc_url( $hsw_home ); ?>"><?php esc_html_e( 'بازگشت به صفحهٔ اصلی', 'hamishop-elementor-widgets' ); ?></a>
		</div>
	</section>
</main>
<?php get_footer(); ?>
