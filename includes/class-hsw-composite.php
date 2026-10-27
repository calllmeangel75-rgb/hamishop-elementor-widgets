<?php
/**
 * Composite renderer.
 *
 * Composite (whole-page) widgets assemble the existing single widgets as
 * their sections without duplicating a single line of markup: HSW_Composite
 * instantiates the part widget, injects the settings resolved from the
 * composite's prefixed controls and captures its output. A Throwable inside
 * one part can never break the page — the error is reported and the part is
 * skipped, mirroring the safety net of HSW_Widget_Base::render_content().
 *
 * @package HamiShop_Elementor_Widgets
 */

defined( 'ABSPATH' ) || exit;

final class HSW_Composite {

        /** @var array<string, array> Per-class control-defaults cache. */
        private static $defaults_cache = array();

        /** @var array<string, array> Per-class full control map cache. */
        private static $controls_cache = array();

        /**
         * Render one part widget and return its HTML.
         *
         * @param string $widget_class Fully-qualified HSW widget class.
         * @param array  $settings     Part settings (unprefixed key space).
         * @return string HTML ('' when the class is unavailable or fails).
         */
        public static function part( $widget_class, array $settings = array() ) {
                $widget_class = ltrim( (string) $widget_class, '\\' );

                if ( ! class_exists( $widget_class ) || ! method_exists( $widget_class, 'hsw_inject_settings' ) ) {
                        return '';
                }

                try {
                        $widget = self::instance( $widget_class );
                } catch ( \Throwable $e ) {
                        self::report( $e, $widget_class );
                        return '';
                }

                $widget->hsw_inject_settings( array_merge( self::defaults( $widget_class ), $settings ) );

                ob_start();
                try {
                        $widget->render_content();
                } catch ( \Throwable $e ) {
                        ob_end_clean();
                        self::report( $e, $widget_class );
                        return '';
                }

                return (string) ob_get_clean();
        }

        /**
         * Default value of every control a widget registers (cached per class).
         * Used to complete the partial settings the composite provides.
         *
         * @param string $widget_class Fully-qualified HSW widget class.
         * @return array<string, mixed>
         */
        public static function defaults( $widget_class ) {
                $widget_class = ltrim( (string) $widget_class, '\\' );

                if ( isset( self::$defaults_cache[ $widget_class ] ) ) {
                        return self::$defaults_cache[ $widget_class ];
                }

                $defaults = array();
                foreach ( self::controls( $widget_class ) as $id => $args ) {
                        if ( is_array( $args ) && array_key_exists( 'default', $args ) ) {
                                $defaults[ $id ] = $args['default'];
                        }
                }

                self::$defaults_cache[ $widget_class ] = $defaults;
                return $defaults;
        }

        /**
         * Full registered control map of a widget (cached per class). Powers
         * the control mirroring used by composite widgets.
         *
         * @param string $widget_class Fully-qualified HSW widget class.
         * @return array<string, array>
         */
        public static function controls( $widget_class ) {
                $widget_class = ltrim( (string) $widget_class, '\\' );

                if ( isset( self::$controls_cache[ $widget_class ] ) ) {
                        return self::$controls_cache[ $widget_class ];
                }

                /* Elementor stores controls that declare `selectors` in a separate
                   “style controls” bucket on the frontend and hides them from
                   get_controls() unless this flag is on. Composite mirroring needs
                   the complete map in every context (editor, CSS, render defaults). */
                $had_style_controls = false;
                if ( class_exists( '\Elementor\Core\Frontend\Performance' ) ) {
                        $had_style_controls = \Elementor\Core\Frontend\Performance::is_use_style_controls();
                        \Elementor\Core\Frontend\Performance::set_use_style_controls( true );
                }

                /*
                 * Child widgets are not registered with Elementor, so their class
                 * file is never loaded — `class_exists()` was false and every
                 * mirrored section (content AND style) came back empty. Load the
                 * file by the project's naming convention before asking.
                 */
                if ( ! class_exists( $widget_class ) ) {
                        $file = 'class-' . strtolower( str_replace( '_', '-', $widget_class ) ) . '.php';
                        $path = HSW_DIR . 'widgets/' . $file;
                        if ( file_exists( $path ) ) {
                                require_once $path;
                        }
                }

                $controls = array();
                try {
                        if ( class_exists( $widget_class ) ) {
                                $widget   = self::instance( $widget_class );
                                $registry = $widget->get_controls();
                                if ( is_array( $registry ) ) {
                                        $controls = $registry;
                                }
                        }
                } catch ( \Throwable $e ) {
                        $controls = array();
                } finally {
                        if ( class_exists( '\Elementor\Core\Frontend\Performance' ) ) {
                                \Elementor\Core\Frontend\Performance::set_use_style_controls( $had_style_controls );
                        }
                }

                self::$controls_cache[ $widget_class ] = $controls;
                return $controls;
        }

	/**
	 * Create a part widget instance with an initialized (empty) settings
	 * array and explicit empty arguments. Both are required to satisfy every
	 * Elementor generation in a single call:
	 *
	 * - 3.x: Controls_Stack::sanitize_settings() requires data['settings'] to
	 *   be an array — a bare `new $class()` leaves it null and any internal
	 *   get_settings() call (e.g. get_current_skin_id() → get_settings('_skin'))
	 *   fatals with a TypeError.
	 * - 4.x: ANY non-empty $data marks the object as a full widget instance
	 *   (Element_Base::__construct), and Widget_Base then throws
	 *   "An `$args` argument is required..." unless a non-null $args is passed.
	 *
	 * A stable `id` is passed too: Elementor's Controls_Stack::init() reads
	 * data['id'] unconditionally and warns when it is missing (WP_DEBUG).
	 *
	 * @param string $widget_class Fully-qualified HSW widget class.
	 * @return HSW_Widget_Base
	 */
	private static function instance( $widget_class ) {
		return new $widget_class(
			array(
				'id'       => 'hsw-part-' . sanitize_key( str_replace( '\\', '-', ltrim( (string) $widget_class, '\\' ) ) ),
				'settings' => array(),
			),
			array()
		);
	}

        /**
         * Report a part failure through the plugin's stored-error channel.
         */
        private static function report( \Throwable $e, $widget_class ) {
                if ( function_exists( 'hsw_report_fatal' ) ) {
                        hsw_report_fatal( $e, 'composite:' . $widget_class );
                }
        }
}
