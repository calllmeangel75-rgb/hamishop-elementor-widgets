<?php
/**
 * Fixture: a page that renders the minimal glass header, used by
 * tests/header-minimal-probe.js so the widget can be inspected outside the
 * Elementor editor (no Elementor editor session needed). Idempotent: re-running
 * updates the same page. Run it against the dev site before the probe.
 */
require 'C:/xampp/htdocs/sks/wp-load.php';

$slug = 'qa-header-minimal';
$existing = get_page_by_path( $slug, OBJECT, 'page' );

$settings = array(
	'brand_name'      => 'فروشگاه حامی',
	'brand_tagline'   => 'تجهیزات پزشکی و درمانی',
	'menu_icon'       => 'grid_view',
	'search_icon'     => 'search',
	'account_icon'    => 'person',
	'cart_icon'       => 'shopping_bag',
	'cta_text'        => 'مشاهده فروشگاه',
	'cta_icon'        => 'arrow_forward',
	'pill_gap'        => array( 'unit' => 'px', 'size' => 20 ),
	'action_size'     => array( 'unit' => 'px', 'size' => 44 ),
	'pill_height'     => array( 'unit' => 'px', 'size' => 68 ),
	'pill_padding_v'  => array( 'unit' => 'px', 'size' => 10 ),
	'pill_padding_h'  => array( 'unit' => 'px', 'size' => 16 ),
	'pill_bg'         => '#ffffff',
	'nav_link_color'  => '#0f766e',
	'nav_link_hover'  => '#0b5c56',
	'cta_btn_bg'      => '#0f766e',
	'cta_btn_c'       => '#ffffff',
);

$data = array(
	array(
		'id'       => 'qa1sec01',
		'elType'   => 'section',
		'settings' => array( 'layout' => 'full_width', 'gap' => 'no' ),
		'elements' => array(
			array(
				'id'       => 'qa1col01',
				'elType'   => 'column',
				'settings' => array( '_column_size' => 100, '_inline_size' => 100 ),
				'elements' => array(
					array(
						'id'         => 'qa1wid01',
						'elType'     => 'widget',
						'widgetType' => 'hsw-header-minimal',
						'settings'   => $settings,
					),
				),
			),
		),
		'isInner'  => false,
	),
);

$postarr = array(
	'post_title'   => 'QA — هدر مینیمال',
	'post_name'    => $slug,
	'post_status'  => 'publish',
	'post_type'    => 'page',
	'post_content' => '',
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
	$page_id = wp_update_post( $postarr );
} else {
	$page_id = wp_insert_post( $postarr );
}

update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_elementor_version', '3.25.0' );
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );
delete_post_meta( $page_id, '_elementor_css' );

if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

update_option( 'hsw_qa_minimal_page', $page_id, false );

echo 'page ' . $page_id . ' → ' . get_permalink( $page_id ) . PHP_EOL;
