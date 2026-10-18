<?php
/**
 * Behavioral test: composite part engine (HSW_Composite + part_* helpers).
 *
 * Verifies, with a fake child-widget registry:
 *   1) part_controls() mirrors ids with the prefix, filters junk keys
 *      (name/parent/responsive/selectors), rewrites conditions to the
 *      prefixed space and drops conditions on non-mirrored controls.
 *   2) part_settings() strips the prefix and keeps foreign keys.
 *   3) part_on() defaults to ON and honors the switcher.
 *   4) HSW_Composite::part() renders the child with injected settings and
 *      never leaks a Throwable (soft-fail contract).
 *
 * Usage: php tests/behavior-composite.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', '/home/z/my-project/' );

/* Minimal escaping polyfills (the fake widget renders escaped attrs). */
function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

require __DIR__ . '/stubs.php';

$base = dirname( __DIR__ );
require $base . '/includes/class-hsw-composite.php';
require $base . '/includes/class-hsw-widget-base.php';

$failed = 0;
function check( $label, $cond ) {
        global $failed;
        echo ( $cond ? 'PASS' : 'FAIL' ) . ": $label\n";
        if ( ! $cond ) {
                $failed++;
        }
}

/* -----------------------------------------------------------------------
 *  Fake part widget with a realistic Elementor registry shape.
 * -------------------------------------------------------------------- */
class Fake_Part_Widget extends HSW_Widget_Base {
        public function get_name() {
                return 'fake-part';
        }
        public function get_controls( $control_id = null ) {
                return array(
                        'title' => array(
                                'type'      => 'text',
                                'name'      => 'title',
                                'label'     => 'Title',
                                'default'   => 'سلام',
                                'section'   => 'sec_x',
                                'frontend_available' => true,
                        ),
                        'mode'  => array(
                                'type'    => 'select',
                                'name'    => 'mode',
                                'default' => 'a',
                                'options' => array( 'a' => 'A', 'b' => 'B' ),
                        ),
                        'sub'   => array(
                                'type'      => 'text',
                                'name'      => 'sub',
                                'default'   => '',
                                'condition' => array( 'mode' => 'b', 'ghost' => 'x' ),
                        ),
                        'rep'   => array(
                                'type'    => 'repeater',
                                'name'    => 'rep',
                                'default' => array(),
                                'fields'  => array( 'k' => array( 'type' => 'text' ) ),
                        ),
                        'styled' => array(
                                'type'      => 'slider',
                                'name'      => 'styled',
                                'desktop_default' => array( 'size' => 5 ),
                                'selectors' => array( '{{WRAPPER}} .x' => 'width: {{SIZE}}px;' ),
                                'range'     => array( 'px' => array( 'min' => 0, 'max' => 9 ) ),
                        ),
                        'banner' => array(
                                'type'    => 'text',
                                'name'    => 'banner',
                                'default' => 'B',
                                'responsive' => array( 'max' => 'mobile' ),
                                'parent'  => 'title',
                        ),
                );
        }
        protected function render() {
                $s = $this->effective_settings();
                echo '<i data-title="' . esc_attr( $s['title'] ?? '' ) . '" data-mode="' . esc_attr( $s['mode'] ?? '' ) . '"></i>';
        }
        /* The compile stub's render_content() is empty; wire it to render(). */
        public function render_content() {
                $this->render();
        }
}

/**
 * Records the constructor data + args HSW_Composite::part() passes down,
 * proving parts are instantiated with an initialized (empty) settings array
 * AND a non-null $args — the combined contract of Elementor 3.25+
 * (sanitize_settings(array)) and Elementor 4.x (full instances need $args).
 */
class Recording_Part_Widget extends Fake_Part_Widget {
        public static $ctor_data = null;
        public static $ctor_args = null;
        public function __construct( $data = array(), $args = null ) {
                self::$ctor_data = $data;
                self::$ctor_args = $args;
                parent::__construct( $data, $args );
        }
        public function get_name() {
                return 'recording-part';
        }
}

class Exposed_Composite extends HSW_Widget_Base {
        public $captured = array();
        public function get_name() {
                return 'exposed-composite';
        }
        public function add_control( $id, array $args = array(), $options = array() ) {
                $this->captured[ $id ] = $args;
                return true;
        }
        public function run_part_controls( $ids ) {
                $this->part_controls( 'tt_', 'Fake_Part_Widget', $ids );
        }
        public function expose_part_settings() {
                return $this->part_settings( 'tt_' );
        }
        public function expose_part_on() {
                return $this->part_on( 'tt_' );
        }
}

/* -----------------------------------------------------------------------
 *  1) Control mirroring
 * -------------------------------------------------------------------- */
$w = new Exposed_Composite();
$w->run_part_controls( array( 'title', 'mode', 'sub', 'rep', 'missing', 'banner', 'styled' ) );

check( 'mirrors prefixed id + default', isset( $w->captured['tt_title'] ) && 'سلام' === $w->captured['tt_title']['default'] );
check( 'junk keys filtered (name/section/frontend_available)', ! isset( $w->captured['tt_title']['name'] ) && ! isset( $w->captured['tt_title']['section'] ) && ! isset( $w->captured['tt_title']['frontend_available'] ) );
check( 'condition rewritten to prefixed space', isset( $w->captured['tt_sub']['condition']['tt_mode'] ) );
check( 'condition on non-mirrored control dropped', ! isset( $w->captured['tt_sub']['condition']['tt_ghost'] ) && ! isset( $w->captured['tt_sub']['condition']['ghost'] ) );
check( 'repeater fields preserved', isset( $w->captured['tt_rep']['fields']['k'] ) );
check( 'unknown id skipped', ! isset( $w->captured['tt_missing'] ) );
check( 'responsive/parent keys dropped (banner)', ! isset( $w->captured['tt_banner']['responsive'] ) && ! isset( $w->captured['tt_banner']['parent'] ) );
check( 'selectors preserved (retargeted via {{WRAPPER}}) + desktop_default becomes default (styled)', isset( $w->captured['tt_styled']['selectors'] ) && array( 'size' => 5 ) === $w->captured['tt_styled']['default'] );

/* -----------------------------------------------------------------------
 *  2) Settings resolution
 * -------------------------------------------------------------------- */
$w->hsw_inject_settings(
        array(
                'tt_title' => 'دنیای واقعی',
                'tt_mode'  => 'b',
                'other'    => 'keep',
        )
);
$ps = $w->expose_part_settings();
check( 'prefix stripped', ! isset( $ps['tt_title'] ) && 'دنیای واقعی' === $ps['title'] );
check( 'unprefixed keys are not forwarded to the child', ! isset( $ps['other'] ) );

/* -----------------------------------------------------------------------
 *  3) Enable switcher
 * -------------------------------------------------------------------- */
check( 'part_on defaults to ON', true === $w->expose_part_on() );

$w2 = new Exposed_Composite();
$w2->hsw_inject_settings( array( 'tt_on' => 'no' ) );
check( 'part_on honors switcher off', false === $w2->expose_part_on() );

/* -----------------------------------------------------------------------
 *  4) part() render contract
 * -------------------------------------------------------------------- */
$w3 = new Exposed_Composite();
$html = \HSW_Composite::part(
        'Fake_Part_Widget',
        array( 'title' => 'نقشه', 'mode' => 'b' )
);
check( 'part() renders child markup with injected settings', false !== strpos( $html, 'data-title="نقشه"' ) && false !== strpos( $html, 'data-mode="b"' ) );

$html2 = \HSW_Composite::part( 'Fake_Part_Widget', array( 'title' => 'فقط عنوان' ) );
check( 'part() completes missing settings from the child registry defaults', false !== strpos( $html2, 'data-mode="a"' ) );

/* -----------------------------------------------------------------------
 *  5) Constructor contract: parts must get an initialized settings array
 *     (a bare `new` leaves data['settings'] null → Elementor 3.x
 *     sanitize_settings() TypeErrors) AND a non-null $args (Elementor 4.x
 *     turns any non-empty $data into a full instance and then throws
 *     "An `$args` argument is required..."). The stub enforces both.
 * -------------------------------------------------------------------- */
\HSW_Composite::part( 'Recording_Part_Widget', array( 'title' => 'ذخیره' ) );
check(
        'part() instantiates children with an initialized settings array',
        is_array( Recording_Part_Widget::$ctor_data )
                && isset( Recording_Part_Widget::$ctor_data['settings'] )
                && is_array( Recording_Part_Widget::$ctor_data['settings'] )
);
check(
        'part() passes non-null $args (Elementor 4.x full-instance requirement)',
        is_array( Recording_Part_Widget::$ctor_args )
);

check( 'part() soft-fails on missing class', '' === \HSW_Composite::part( 'No_Such_HSW_Class', array() ) );

echo $failed ? "COMPOSITE RESULT: $failed FAILURE(S)\n" : "COMPOSITE RESULT: ALL CLEAN\n";
exit( $failed ? 1 : 0 );
