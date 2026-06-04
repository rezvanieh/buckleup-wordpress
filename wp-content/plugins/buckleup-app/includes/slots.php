<?php
/**
 * Availability / slot engine.
 *
 * Direct port of src/app/api/bookings/slots/route.ts:
 *   1. A per-date exception overrides the weekly schedule. If the exception
 *      marks the day unavailable → no slots.
 *   2. Otherwise use the exception's hours if it supplies them, else the
 *      instructor's weekly availability row for that weekday. No row → no slots.
 *   3. Existing non-CANCELLED bookings on that date block overlapping slots.
 *   4. Generate 30-minute-spaced start times from workStart up to
 *      (workEnd - duration), excluding any that overlap a booking.
 *
 * Overlap rule (identical to source): a candidate [start, start+duration)
 * conflicts with a booking [bStart, bStart+bDuration) iff
 *   start < bEnd  AND  start+duration > bStart.
 *
 * All times are handled as local wall-clock "HH:MM" on the given date, matching
 * how the instructor's hours are stored.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compute available start times for an instructor on a date.
 *
 * @param int    $instructor_id Instructor (WP user) ID.
 * @param string $date_str      YYYY-MM-DD.
 * @param int    $duration      Lesson length in minutes (default 60).
 * @return array{slots:string[],reason?:string} List of "HH:MM" starts; `reason`
 *                                               present when an exception blocks
 *                                               the whole day.
 */
function buckleup_compute_slots( $instructor_id, $date_str, $duration = 60 ) {
	global $wpdb;

	$instructor_id = (int) $instructor_id;
	$duration      = max( 1, (int) $duration );

	// Validate the date.
	$ts = strtotime( $date_str . ' 00:00:00' );
	if ( ! $instructor_id || false === $ts || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date_str ) ) {
		return array( 'slots' => array() );
	}
	$day_of_week = (int) gmdate( 'w', $ts ); // 0=Sun … 6=Sat (matches JS getDay()).

	$exceptions_table   = buckleup_app_table( 'availability_exceptions' );
	$availability_table = buckleup_app_table( 'availability' );
	$bookings_table     = buckleup_app_table( 'bookings' );

	// 1. Per-date exception.
	$exception = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT is_available, start_time, end_time, reason FROM {$exceptions_table} WHERE instructor_id = %d AND date = %s",
			$instructor_id,
			$date_str
		),
		ARRAY_A
	);

	if ( $exception && ! (int) $exception['is_available'] ) {
		return array(
			'slots'  => array(),
			'reason' => $exception['reason'] ? $exception['reason'] : 'Unavailable',
		);
	}

	// 2. Resolve the working hours.
	$start_time = null;
	$end_time   = null;

	if ( $exception && (int) $exception['is_available'] && $exception['start_time'] && $exception['end_time'] ) {
		$start_time = $exception['start_time'];
		$end_time   = $exception['end_time'];
	} else {
		$availability = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT start_time, end_time FROM {$availability_table} WHERE instructor_id = %d AND day_of_week = %d ORDER BY id ASC LIMIT 1",
				$instructor_id,
				$day_of_week
			),
			ARRAY_A
		);
		if ( ! $availability ) {
			return array( 'slots' => array() );
		}
		$start_time = $availability['start_time'];
		$end_time   = $availability['end_time'];
	}

	$work_start = buckleup_hm_to_minutes( $start_time );
	$work_end   = buckleup_hm_to_minutes( $end_time );
	if ( null === $work_start || null === $work_end || $work_end <= $work_start ) {
		return array( 'slots' => array() );
	}

	// 3. Existing non-CANCELLED bookings on this date → [startMin, endMin) ranges.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT datetime, duration FROM {$bookings_table} WHERE instructor_id = %d AND status <> 'CANCELLED' AND DATE(datetime) = %s",
			$instructor_id,
			$date_str
		),
		ARRAY_A
	);
	$busy = array();
	foreach ( (array) $rows as $row ) {
		$b_start = (int) gmdate( 'G', strtotime( $row['datetime'] ) ) * 60 + (int) gmdate( 'i', strtotime( $row['datetime'] ) );
		$busy[]  = array( $b_start, $b_start + (int) $row['duration'] );
	}

	// 4. Generate 30-min slots up to (work_end - duration).
	$slots              = array();
	$last_possible_start = $work_end - $duration;
	for ( $start = $work_start; $start <= $last_possible_start; $start += 30 ) {
		$slot_end  = $start + $duration;
		$conflict  = false;
		foreach ( $busy as $range ) {
			// start < bEnd AND slotEnd > bStart
			if ( $start < $range[1] && $slot_end > $range[0] ) {
				$conflict = true;
				break;
			}
		}
		if ( ! $conflict ) {
			$slots[] = buckleup_minutes_to_hm( $start );
		}
	}

	return array( 'slots' => $slots );
}

/**
 * "HH:MM" → minutes since midnight, or null if malformed.
 *
 * @param string $hm
 * @return int|null
 */
function buckleup_hm_to_minutes( $hm ) {
	if ( ! is_string( $hm ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', $hm, $m ) ) {
		return null;
	}
	$h = (int) $m[1];
	$i = (int) $m[2];
	if ( $h > 23 || $i > 59 ) {
		return null;
	}
	return $h * 60 + $i;
}

/**
 * Minutes since midnight → "HH:MM".
 *
 * @param int $minutes
 * @return string
 */
function buckleup_minutes_to_hm( $minutes ) {
	$minutes = max( 0, (int) $minutes );
	return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
}

/**
 * Is a specific start time still available (used to re-validate at booking
 * creation)? Re-runs the slot engine and checks membership.
 *
 * @param int    $instructor_id
 * @param string $date_str  YYYY-MM-DD
 * @param string $time_str  HH:MM
 * @param int    $duration
 * @return bool
 */
function buckleup_slot_is_available( $instructor_id, $date_str, $time_str, $duration ) {
	$result = buckleup_compute_slots( $instructor_id, $date_str, $duration );
	return in_array( $time_str, $result['slots'], true );
}
