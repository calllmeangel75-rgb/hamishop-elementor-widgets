<?php
/**
 * Jalali (Shamsi) date output — opt-in, zero dependencies.
 *
 * Only loaded when the `hsw_jalali_on` option is "yes" (see HSW_Plugin), so a
 * site that does not want it pays nothing: no file, no hook, no assets.
 *
 * @package HamiShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persian calendar conversion and formatting.
 */
class HSW_Jalali {

	/**
	 * Option name for the toggle.
	 */
	const OPTION = 'hsw_jalali_on';

	/**
	 * Is the feature on?
	 *
	 * @return bool
	 */
	public static function is_on() {
		return 'yes' === get_option( self::OPTION, 'no' );
	}

	/**
	 * Gregorian → Jalali (pure integer math, no Intl extension needed).
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Month 1-12.
	 * @param int $gd Day 1-31.
	 * @return array [year, month, day]
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$g_days = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		$gy2 = ( $gm > 2 ) ? $gy + 1 : $gy;
		$days = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
			+ (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_days[ $gm - 1 ];

		$jy   = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy  += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Persian month names.
	 *
	 * @return array
	 */
	private static function months() {
		return array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	/**
	 * Format a timestamp in Jalali, keeping the time part of the format.
	 *
	 * Supports the tokens WordPress uses for dates: Y y m n d j F M l D H G i s.
	 *
	 * @param int    $timestamp Unix timestamp (site timezone already applied).
	 * @param string $format    Date format.
	 * @return string
	 */
	public static function format( $timestamp, $format ) {
		$ts = (int) $timestamp;
		list( $jy, $jm, $jd ) = self::to_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );

		$months = self::months();
		$out    = '';
		$len    = strlen( $format );

		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $format[ $i ];

			switch ( $ch ) {
				case '\\':
					$out .= ( $i + 1 < $len ) ? $format[ ++$i ] : '';
					break;
				case 'Y':
					$out .= $jy;
					break;
				case 'y':
					$out .= substr( (string) $jy, -2 );
					break;
				case 'n':
					$out .= $jm;
					break;
				case 'm':
					$out .= str_pad( (string) $jm, 2, '0', STR_PAD_LEFT );
					break;
				case 'j':
					$out .= $jd;
					break;
				case 'd':
					$out .= str_pad( (string) $jd, 2, '0', STR_PAD_LEFT );
					break;
				case 'F':
				case 'M':
					$out .= $months[ $jm - 1 ];
					break;
				case 'H':
				case 'G':
				case 'i':
				case 's':
				case 'a':
				case 'A':
				case 'l':
				case 'D':
				case ' ':
				case '/':
				case '-':
				case ':':
				case ',':
				case '.':
					$out .= gmdate( $ch, $ts );
					break;
				default:
					$out .= $ch;
			}
		}

		return $out;
	}

	/**
	 * Hook the converters. Called once when the option is on.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'date_i18n', array( __CLASS__, 'filter_date_i18n' ), 10, 4 );
		/*
		 * WordPress 5.3+ formats post dates through wp_date(), not date_i18n(),
		 * so get_the_date() needs its own pass. The post date is already local,
		 * so the timestamp is used as-is.
		 */
		add_filter( 'get_the_date', array( __CLASS__, 'filter_post_date' ), 10, 3 );
		add_filter( 'get_the_modified_date', array( __CLASS__, 'filter_post_date' ), 10, 3 );
		/* Themes and Elementor's post-info often read the time instead. */
		add_filter( 'get_the_time', array( __CLASS__, 'filter_post_time' ), 10, 3 );
	}

	/**
	 * Convert get_the_time() output (same shape as the date filters).
	 *
	 * @param string      $the_time Formatted time.
	 * @param string      $format   Format.
	 * @param int|WP_Post $post     Post.
	 * @return string
	 */
	public static function filter_post_time( $the_time, $format, $post = null ) {
		return self::filter_post_date( $the_time, $format, $post );
	}

	/**
	 * Convert a post date string.
	 *
	 * @param string      $the_date Formatted date.
	 * @param string      $format   Format.
	 * @param int|WP_Post $post     Post.
	 * @return string
	 */
	public static function filter_post_date( $the_date, $format, $post = null ) {
		if ( is_admin() || ! self::is_on() ) {
			return $the_date;
		}

		/*
		 * `get_the_date( '' )` means "use the site's date format", and that is
		 * exactly how our blog widgets call it — the empty string has to be
		 * resolved here or the blog never converts.
		 */
		$format = (string) $format;
		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'F j, Y' );
		}

		if ( ! preg_match( '/[YymndjFM]/', $format ) ) {
			return $the_date;
		}

		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return $the_date;
		}

		$ts = strtotime( 'get_the_modified_date' === current_filter() ? $post->post_modified : $post->post_date );
		if ( ! $ts ) {
			return $the_date;
		}

		return self::format( $ts, $format );
	}

	/**
	 * Swap Gregorian output for Jalali on the frontend.
	 *
	 * Only formats that actually contain a year/month/day token are touched, so
	 * times and "1 hour ago" style strings stay as they are.
	 *
	 * @param string $date Formatted date.
	 * @param string $fmt  Format.
	 * @param int    $ts   Timestamp.
	 * @param bool   $gmt  GMT flag.
	 * @return string
	 */
	public static function filter_date_i18n( $date, $fmt, $ts, $gmt ) {
		if ( is_admin() || ! self::is_on() ) {
			return $date;
		}

		$fmt = (string) $fmt;
		if ( '' === $fmt ) {
			$fmt = (string) get_option( 'date_format', 'F j, Y' );
		}

		if ( ! preg_match( '/[YymndjFM]/', $fmt ) ) {
			return $date;
		}

		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return $date;
		}

		return self::format( $gmt ? $ts : $ts + ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ), $fmt );
	}
}
