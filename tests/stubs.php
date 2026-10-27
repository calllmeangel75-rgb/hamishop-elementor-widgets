<?php
/**
 * Elementor 3.25.4-compatible compile stubs.
 * Signatures replicate the REAL released Elementor so any override
 * mismatch (static/nonstatic, visibility narrowing) fatals exactly
 * like it would inside WordPress.
 */

namespace Elementor;

abstract class Widget_Base {

        /** @var array Data handed to the constructor (mirrors Elementor's Controls_Stack::__construct). */
        public $stub_data = array();

        /** @var array|null Args handed to the constructor (4.x requires non-null for full instances). */
        public $stub_args = null;

        /** @var bool Mirrors Element_Base::is_type_instance (true until non-empty $data arrives). */
        public $stub_is_type_instance = true;

        /**
         * Real Elementor signature: Widget_Base::__construct( $data = [], $args = null ).
         *
         * Contract replicated from two generations:
         * - 3.x: get_current_skin_id() → get_settings('_skin') needs
         *   data['settings'] to be an array — a bare `new $class()` leaves it
         *   null and fatals with a sanitize_settings() TypeError.
         * - 4.x: ANY non-empty $data marks a full instance
         *   (Element_Base::__construct) and Widget_Base then throws
         *   "An `$args` argument is required..." unless $args is non-null.
         *
         * The stub enforces both so the behavior test catches regressions in
         * either direction: composites must pass settings AND args.
         */
        public function __construct( $data = array(), $args = null ) {
                if ( $data ) {
                        $this->stub_is_type_instance = false;
                }
                $this->stub_data = is_array( $data ) ? $data : array();
                $this->stub_args = $args;

                if ( ! $this->stub_is_type_instance && null === $args ) {
                        throw new \Exception( 'An `$args` argument is required when initializing a full widget instance.' );
                }
        }

        abstract public function get_name();

        public function get_title() { return ''; }

        public function get_icon() { return ''; }

        public function get_keywords() { return array(); }

        public function get_categories() { return array(); }

        protected function get_upsale_data() { return array(); }

        public function render_content() {}

        public function get_style_depends() { return array(); }

        public function get_script_depends() { return array(); }

        protected function register_controls() {}

        protected function render() {}

        public function start_controls_section( $section_id, array $args = array() ) {}

        public function end_controls_section() {}

        public function add_control( $id, array $args = array(), $options = array() ) {}

        public function add_group_control( $group_name, array $args = array() ) {}

        public function add_responsive_control( $id, array $args = array(), $options = array() ) {}

        public function get_settings_for_display( $setting_key = null ) { return array(); }

        public function get_name_for_editor() { return ''; }
}

class Controls_Manager {
        const TEXT = 'text';
        const TEXTAREA = 'textarea';
        const WYSIWYG = 'wysiwyg';
        const NUMBER = 'number';
        const URL = 'url';
        const MEDIA = 'media';
        const SELECT = 'select';
        const SELECT2 = 'select2';
        const SWITCHER = 'switcher';
        const COLOR = 'color';
        const SLIDER = 'slider';
        const REPEATER = 'repeater';
        const RAW_HTML = 'raw_html';
        const ICON = 'icon';
        const DATE = 'date';
        const HIDDEN = 'hidden';
        const DIMENSIONS = 'dimensions';
        const BOX_SHADOW = 'box_shadow';
        const HEADING = 'heading';
        const DIVIDER = 'divider';
        const CODE = 'code';
        const TAB_STYLE = 'style';
        const TAB_CONTENT = 'content';
        const TAB_ADVANCED = 'advanced';
        const TAB_RESPONSIVE = 'responsive';
        const TAB_LAYOUT = 'layout';
        const TAB_SETTINGS = 'settings';
        const TAB_GENERAL = 'general';
        const TABS_START = 'tabs_start';
        const TABS_END = 'tabs_end';
        const TAB_START = 'tab_start';
        const TAB_END = 'tab_end';
        const SECTION_START = 'section_start';
        const SECTION_END = 'section_end';
}

class Repeater {
        public function add_control( $id, array $args = array() ) { return $this; }
        public function get_controls() { return array(); }
}

class Group_Control_Typography {}
class Group_Control_Border {}
class Group_Control_Box_Shadow {}

class Plugin {
        /** @var Plugin */
        public static $instance;
        public $editor;
        public $preview;
        public $frontend;
        public function __construct() {
                self::$instance = $this;
        }
}
