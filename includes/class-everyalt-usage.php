<?php
/**
 * Monthly spending totals and the optional monthly spending limit.
 *
 * Costs are estimates calculated from token usage reported by the provider and the published prices
 * in Every_Alt_Providers, so they can differ slightly from the provider's invoice.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Usage {

	/** Option: array of 'YYYY-MM' => { cost: float, requests: int, models: { slug: { cost, requests } } }. */
	const OPTION = 'every_alt_usage';

	/** Option: monthly spending limit in USD; '' or 0 means no limit. */
	const BUDGET_OPTION = 'every_alt_monthly_budget';

	/** Months of history kept. */
	const MONTHS_KEPT = 12;

	/**
	 * Current month key in the site's timezone, e.g. "2026-09".
	 *
	 * @return string
	 */
	public static function current_month() {
		return wp_date( 'Y-m' );
	}

	/**
	 * Add the cost of one API request to this month's totals.
	 *
	 * @param string $model_slug
	 * @param float  $cost_usd
	 */
	public static function record( $model_slug, $cost_usd ) {
		$cost_usd = (float) $cost_usd;
		$usage    = self::all();
		$month    = self::current_month();
		if ( ! isset( $usage[ $month ] ) ) {
			$usage[ $month ] = array( 'cost' => 0.0, 'requests' => 0, 'models' => array() );
		}
		$usage[ $month ]['cost']     += $cost_usd;
		$usage[ $month ]['requests'] += 1;
		if ( ! isset( $usage[ $month ]['models'][ $model_slug ] ) ) {
			$usage[ $month ]['models'][ $model_slug ] = array( 'cost' => 0.0, 'requests' => 0 );
		}
		$usage[ $month ]['models'][ $model_slug ]['cost']     += $cost_usd;
		$usage[ $month ]['models'][ $model_slug ]['requests'] += 1;

		krsort( $usage );
		$usage = array_slice( $usage, 0, self::MONTHS_KEPT, true );
		update_option( self::OPTION, $usage, false );
	}

	/**
	 * All recorded months, newest first.
	 *
	 * @return array
	 */
	public static function all() {
		$usage = get_option( self::OPTION, array() );
		if ( ! is_array( $usage ) ) {
			return array();
		}
		krsort( $usage );
		return $usage;
	}

	/**
	 * Estimated spend so far this month, in USD.
	 *
	 * @return float
	 */
	public static function month_total() {
		$usage = self::all();
		$month = self::current_month();
		return isset( $usage[ $month ]['cost'] ) ? (float) $usage[ $month ]['cost'] : 0.0;
	}

	/**
	 * Monthly limit in USD, or 0 for no limit.
	 *
	 * @return float
	 */
	public static function budget() {
		return max( 0.0, (float) get_option( self::BUDGET_OPTION, '' ) );
	}

	/**
	 * Whether a limit is set and this month's spend has reached it.
	 *
	 * @return bool
	 */
	public static function budget_reached() {
		$budget = self::budget();
		return $budget > 0 && self::month_total() >= $budget;
	}

	/**
	 * Format a USD amount for display. Amounts under $1 keep up to 4 decimals so small spends and
	 * overages stay visible ($0.0125, not $0.01); whole-cent amounts still show 2 ($0.50).
	 *
	 * @param float $usd
	 * @return string e.g. "$0.0042", "$0.0125", "$0.50", "$12.37"
	 */
	public static function format_usd( $usd ) {
		$usd = (float) $usd;
		if ( $usd >= 1 || $usd <= 0 ) {
			return '$' . number_format( max( 0, $usd ), 2 );
		}
		$formatted = rtrim( number_format( $usd, 4, '.', '' ), '0' );
		$decimals  = strlen( substr( strrchr( $formatted, '.' ), 1 ) );
		return '$' . ( $decimals < 2 ? number_format( $usd, 2 ) : $formatted );
	}
}
