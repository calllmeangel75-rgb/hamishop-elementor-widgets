<?php
/**
 * Unit tests that need no WordPress, no database and no Composer.
 *
 * Run: php tests/unit/run.php
 * Exit code is 0 only when every assertion passes; the CI "unit" job uses it.
 *
 * Covered here: HSW_Jalali (conversion, continuity, leap years, formatting)
 * and HSW_Helpers::number_words. Money paths need a database, so they live in
 * tests/qa/*.php and are run against a scratch user instead.
 *
 * @package HamiShop
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

/* Minimal WP surface used by the two classes under test. */
function __( $text, $domain = null ) {
	return $text;
}
function get_option( $name, $default = false ) {
	return $default;
}
function apply_filters( $tag, $value ) {
	return $value;
}

require __DIR__ . '/../../includes/class-hsw-jalali.php';
require __DIR__ . '/../../includes/class-hsw-helpers.php';

$pass = 0;
$fail = 0;
$current = '';

function group( $name ) {
	global $current;
	$current = $name;
	echo "\n# {$name}\n";
}

function ok( $label, $condition, $detail = '' ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "  PASS  {$label}\n";
		return;
	}
	$fail++;
	echo "  FAIL  {$label}" . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
}

function same( $label, $got, $want ) {
	ok( $label, $got === $want, 'got=' . var_export( $got, true ) . ' want=' . var_export( $want, true ) );
}

/* --------------------------------------------------------------------- */
group( 'Jalali: known anchors' );

$anchors = array(
	'1357-11-22' => array( 1979, 2, 11 ),
	'1378-10-11' => array( 2000, 1, 1 ),
	'1403-01-01' => array( 2024, 3, 20 ),
	'1403-12-30' => array( 2025, 3, 20 ),
	'1404-01-01' => array( 2025, 3, 21 ),
	'1399-12-30' => array( 2021, 3, 20 ),
);

foreach ( $anchors as $want => $g ) {
	list( $jy, $jm, $jd ) = HSW_Jalali::to_jalali( $g[0], $g[1], $g[2] );
	$got = sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );
	same( "gregorian {$g[0]}-{$g[1]}-{$g[2]} → {$want}", $got, $want );
}

/* --------------------------------------------------------------------- */
group( 'Jalali: every Gregorian day from 1900 to 2100 maps to one Jalali day' );

$ts   = gmmktime( 12, 0, 0, 1, 1, 1900 );
$end  = gmmktime( 12, 0, 0, 12, 31, 2100 );
$prev = null;
$bad  = array();
$days = 0;

while ( $ts <= $end ) {
	$cur = HSW_Jalali::to_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );

	if ( null !== $prev ) {
		list( $py, $pm, $pd ) = $prev;

		/* Successor rules: 31 days in months 1-6, 30 in 7-11, 29/30 in Esfand. */
		if ( $pm <= 6 ) {
			$expect = 31 === $pd ? array( $py, $pm + 1, 1 ) : array( $py, $pm, $pd + 1 );
		} elseif ( $pm <= 11 ) {
			$expect = 30 === $pd ? array( $py, $pm + 1, 1 ) : array( $py, $pm, $pd + 1 );
		} elseif ( 29 === $pd ) {
			/* Esfand 29 is followed by Esfand 30 (leap) or by the new year. */
			$is_thirtieth = ( $cur === array( $py, 12, 30 ) );
			$expect       = $is_thirtieth ? array( $py, 12, 30 ) : array( $py + 1, 1, 1 );
		} elseif ( $pd < 29 ) {
			$expect = array( $py, 12, $pd + 1 );
		} else {
			$expect = array( $py + 1, 1, 1 );
		}

		if ( $cur !== $expect && count( $bad ) < 5 ) {
			$bad[] = sprintf(
				'%s: %s then %s (expected %s)',
				gmdate( 'Y-m-d', $ts ),
				implode( '/', $prev ),
				implode( '/', $cur ),
				implode( '/', $expect )
			);
		}
		$days++;
	}

	$prev = $cur;
	$ts  += DAY_IN_SECONDS;
}

ok( '200 years walked day by day (' . $days . ' days)', $days > 70000, $days . ' days' );
ok( 'no day skipped or repeated', empty( $bad ), implode( ' | ', $bad ) );

/* --------------------------------------------------------------------- */
group( 'Jalali: month lengths and leap years' );

$lengths = array();
$ts      = gmmktime( 12, 0, 0, 1, 1, 1925 );

while ( $ts < gmmktime( 12, 0, 0, 1, 1, 2050 ) ) {
	list( $jy, $jm, $jd ) = HSW_Jalali::to_jalali( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );
	$lengths[ $jy ][ $jm ] = max( isset( $lengths[ $jy ][ $jm ] ) ? $lengths[ $jy ][ $jm ] : 0, $jd );
	$ts += DAY_IN_SECONDS;
}

$bad_months = array();
$leap_years = array();
foreach ( $lengths as $y => $months ) {
	if ( 12 !== count( $months ) ) {
		continue; /* first and last year of the window are partial */
	}
	for ( $m = 1; $m <= 6; $m++ ) {
		if ( 31 !== $months[ $m ] ) {
			$bad_months[] = "{$y}/{$m}=" . $months[ $m ];
		}
	}
	for ( $m = 7; $m <= 11; $m++ ) {
		if ( 30 !== $months[ $m ] ) {
			$bad_months[] = "{$y}/{$m}=" . $months[ $m ];
		}
	}
	$leap_years[ $y ] = ( 30 === $months[12] );
}

ok( 'months 1-6 have 31 days, 7-11 have 30', empty( $bad_months ), implode( ', ', array_slice( $bad_months, 0, 5 ) ) );

/*
 * Independent leap rule (Borkowski's 33-year arithmetic): a year is leap when
 * ((25y + 11) mod 33) < 8. It is computed here, not taken from the class, so
 * the comparison is a real check.
 */
$wrong = array();
foreach ( $leap_years as $y => $is_leap ) {
	$expect_leap = ( ( ( 25 * $y + 11 ) % 33 ) < 8 );
	if ( $expect_leap !== $is_leap ) {
		$wrong[] = $y . ( $is_leap ? ' (says leap)' : ' (says common)' );
	}
}
ok( 'leap years match the 33-year rule for ' . count( $leap_years ) . ' years', empty( $wrong ), implode( ', ', $wrong ) );
ok( '1403 is a leap year (30 Esfand)', ! empty( $leap_years[1403] ) );
ok( '1404 is not a leap year (29 Esfand)', isset( $leap_years[1404] ) && ! $leap_years[1404] );

/* --------------------------------------------------------------------- */
group( 'Jalali: formatting' );

$ts = gmmktime( 12, 0, 0, 3, 20, 2024 ); /* 1403-01-01 */
same( 'format Y/m/d', HSW_Jalali::format( $ts, 'Y/m/d' ), '1403/01/01' );
same( 'format j F Y', HSW_Jalali::format( $ts, 'j F Y' ), '1 فروردین 1403' );
same( 'format y-n-j', HSW_Jalali::format( $ts, 'y-n-j' ), '03-1-1' );
same( 'unknown token is kept literally', HSW_Jalali::format( $ts, 'Y{Q}' ), '1403{Q}' );
same( 'escaped character', HSW_Jalali::format( $ts, '\\Y Y' ), 'Y 1403' );

/* --------------------------------------------------------------------- */
group( 'number_words' );

same( 'zero', HSW_Helpers::number_words( 0 ), 'صفر' );
same( 'negative is zero', HSW_Helpers::number_words( -25 ), 'صفر' );
same( 'one', HSW_Helpers::number_words( 1 ), 'یک' );
same( 'ten', HSW_Helpers::number_words( 10 ), 'ده' );
same( 'eleven', HSW_Helpers::number_words( 11 ), 'یازده' );
same( 'nineteen', HSW_Helpers::number_words( 19 ), 'نوزده' );
same( 'twenty one', HSW_Helpers::number_words( 21 ), 'بیست و یک' );
same( 'one hundred', HSW_Helpers::number_words( 100 ), 'صد' );
same( 'nine hundred ninety nine', HSW_Helpers::number_words( 999 ), 'نهصد و نود و نه' );
same( 'one thousand reads "هزار"', HSW_Helpers::number_words( 1000 ), 'هزار' );
same( 'one thousand and one', HSW_Helpers::number_words( 1001 ), 'هزار و یک' );
same( 'one million and one', HSW_Helpers::number_words( 1000001 ), 'یک میلیون و یک' );
same( 'one billion', HSW_Helpers::number_words( 1000000000 ), 'یک میلیارد' );
same(
	'largest supported (999,999,999,999,999)',
	HSW_Helpers::number_words( 999999999999999 ),
	'نهصد و نود و نه هزار میلیارد و نهصد و نود و نه میلیارد و نهصد و نود و نه میلیون و نهصد و نود و نه هزار و نهصد و نود و نه'
);
same( 'decimal part is floored', HSW_Helpers::number_words( 12.75 ), 'دوازده' );
ok( 'string amount works', '' !== HSW_Helpers::number_words( '2500' ) );

/* --------------------------------------------------------------------- */
echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "assertions: %d passed, %d failed\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
