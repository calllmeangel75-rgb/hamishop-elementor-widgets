<?php
/**
 * Dump the widget catalog as JSON — used to generate docs/WIDGETS.md from code
 * instead of from a hand-written list that drifts.
 *
 * Usage: php tools/dump-catalog.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HSW_FILE', dirname( __DIR__ ) . '/hamishop-elementor-widgets.php' );
define( 'HSW_VERSION', '1.11.223' );

function __( $t, $d = '' ) {
	return $t;
}
function esc_html__( $t, $d = '' ) {
	return $t;
}
function add_action() {}
function add_filter() {}
function plugin_basename( $f ) {
	return $f;
}

require_once dirname( __DIR__ ) . '/includes/class-hsw-widget-manager.php';

$catalog = HSW_Widget_Manager::catalog();
$out     = array();

foreach ( $catalog as $group_key => $group ) {
	$items = array();

	foreach ( (array) ( $group['general'] ?? array() ) as $slug => $row ) {
		$items[] = array(
			'slug'     => $slug,
			'label'    => $row['label'] ?? '',
			'desc'     => $row['desc'] ?? '',
			'kind'     => 'general',
			'subgroup' => '',
		);
	}

	foreach ( (array) ( $group['parts'] ?? array() ) as $sub_key => $subgroup ) {
		foreach ( (array) ( $subgroup['items'] ?? array() ) as $slug => $row ) {
			$items[] = array(
				'slug'     => $slug,
				'label'    => $row['label'] ?? '',
				'desc'     => $row['desc'] ?? '',
				'kind'     => 'part',
				'subgroup' => $subgroup['label'] ?? $sub_key,
			);
		}
	}

	$out[] = array(
		'key'         => $group_key,
		'label'       => $group['label'] ?? '',
		'category'    => $group['category'] ?? '',
		'description' => $group['description'] ?? '',
		'count'       => count( $items ),
		'items'       => $items,
	);
}

echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), "\n";
