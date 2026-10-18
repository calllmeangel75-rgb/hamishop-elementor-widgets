<?php
/**
 * Elementor 3.25.4-compatible compile stubs.
 * Signatures replicate the REAL released Elementor so any override
 * mismatch (static/nonstatic, visibility narrowing) fatals exactly
 * like it would inside WordPress.
 */

namespace Elementor;

abstract class Widget_Base {

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
        const CHOOSER = 'chooser';
        const SWITCHER = 'switcher';
        const COLOR = 'color';
        const SLIDER = 'slider';
        const REPEATER = 'repeater';
        const RAW_HTML = 'raw_html';
        const ICON = 'icon';
        const DATE = 'date';
        const HIDDEN = 'hidden';
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
