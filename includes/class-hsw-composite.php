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
                        $widget = new $widget_class();
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

                $controls = array();
                try {
                        if ( class_exists( $widget_class ) ) {
                                $widget   = new $widget_class();
                                $registry = $widget->get_controls();
                                if ( is_array( $registry ) ) {
                                        $controls = $registry;
                                }
                        }
                } catch ( \Throwable $e ) {
                        $controls = array();
                }

                self::$controls_cache[ $widget_class ] = $controls;
                return $controls;
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
