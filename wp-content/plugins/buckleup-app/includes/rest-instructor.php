<?php
/**
 * Instructor console REST endpoints — buckleup/v1/instructors/*.
 * Mirrors the source /api/instructors/* shapes.
 *
 * Routes (all gated by the instructor-console capability; data is the current
 * instructor's own — instructor_id = current user id — so ownership is implicit
 * EXCEPT bookings/{id}/status, which re-checks the row's instructor_id):
 *   GET    /instructors/stats
 *   GET    /instructors/availability
 *   POST   /instructors/availability                 (delete-then-create per day)
 *   DELETE /instructors/availability                 (by dayOfWeek)
 *   GET    /instructors/availability/exceptions
 *   POST   /instructors/availability/exceptions      (upsert by date)
 *   DELETE /instructors/availability/exceptions      (by date)
 *   GET    /instructors/bookings
 *   PUT    /instructors/bookings/{id}/status         (CONFIRMED|CANCELLED → wp_mail)
 *   GET    /instructors/students                     (aggregated)
 *   GET    /instructors/profile
 *   PUT    /instructors/profile
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	$inst = array( 'permission_callback' => 'buckleup_perm_instructor' );

	register_rest_route( 'buckleup/v1', '/instructors/stats', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_stats' ) + $inst ) );

	register_rest_route( 'buckleup/v1', '/instructors/availability', array(
		array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_avail_get' ) + $inst,
		array( 'methods' => 'POST', 'callback' => 'buckleup_rest_instr_avail_post' ) + $inst,
		array( 'methods' => 'DELETE', 'callback' => 'buckleup_rest_instr_avail_delete' ) + $inst,
	) );

	register_rest_route( 'buckleup/v1', '/instructors/availability/exceptions', array(
		array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_exc_get' ) + $inst,
		array( 'methods' => 'POST', 'callback' => 'buckleup_rest_instr_exc_post' ) + $inst,
		array( 'methods' => 'DELETE', 'callback' => 'buckleup_rest_instr_exc_delete' ) + $inst,
	) );

	register_rest_route( 'buckleup/v1', '/instructors/bookings', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_bookings' ) + $inst ) );

	register_rest_route( 'buckleup/v1', '/instructors/bookings/(?P<id>\d+)/status', array(
		array(
			'methods'  => 'PUT',
			'callback' => 'buckleup_rest_instr_booking_status',
			'args'     => array(
				'id' => array(
					'validate_callback' => static function ( $value ) {
						return is_numeric( $value );
					},
				),
			),
		) + $inst,
	) );

	register_rest_route( 'buckleup/v1', '/instructors/students', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_students' ) + $inst ) );

	register_rest_route( 'buckleup/v1', '/instructors/profile', array(
		array( 'methods' => 'GET', 'callback' => 'buckleup_rest_instr_profile_get' ) + $inst,
		array( 'methods' => 'PUT', 'callback' => 'buckleup_rest_instr_profile_put' ) + $inst,
	) );
} );

/** ---- Stats ---------------------------------------------------------- */

function buckleup_rest_instr_stats() {
	global $wpdb;
	$iid      = get_current_user_id();
	$bookings = buckleup_app_table( 'bookings' );
	$today    = current_time( 'Y-m-d' ) . ' 00:00:00';
	$now      = current_time( 'mysql' );

	$upcoming  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings} WHERE instructor_id=%d AND status='CONFIRMED' AND datetime>=%s", $iid, $today ) );
	$completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings} WHERE instructor_id=%d AND status='COMPLETED'", $iid ) );
	$next_row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bookings} WHERE instructor_id=%d AND status='CONFIRMED' AND datetime>=%s ORDER BY datetime ASC LIMIT 1", $iid, $now ), ARRAY_A );

	return new WP_REST_Response( array(
		'stats'      => array( 'upcomingBookings' => $upcoming, 'completedBookings' => $completed ),
		'nextLesson' => $next_row ? buckleup_booking_shape( $next_row ) : null,
	), 200 );
}

/** ---- Availability (weekly) ------------------------------------------ */

function buckleup_rest_instr_avail_get() {
	global $wpdb;
	$iid = get_current_user_id();
	$t   = buckleup_app_table( 'availability' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE instructor_id=%d ORDER BY day_of_week ASC", $iid ), ARRAY_A );
	$out  = array_map( 'buckleup_availability_shape', (array) $rows );
	return new WP_REST_Response( array( 'availability' => $out ), 200 );
}

function buckleup_availability_shape( $row ) {
	return array(
		'id'           => (int) $row['id'],
		'instructorId' => (int) $row['instructor_id'],
		'dayOfWeek'    => (int) $row['day_of_week'],
		'startTime'    => $row['start_time'],
		'endTime'      => $row['end_time'],
		'isRecurring'  => (bool) $row['is_recurring'],
	);
}

function buckleup_rest_instr_avail_post( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$iid = get_current_user_id();
	$p   = (array) $request->get_json_params();

	$day   = isset( $p['dayOfWeek'] ) ? (int) $p['dayOfWeek'] : -1;
	$start = isset( $p['startTime'] ) ? sanitize_text_field( $p['startTime'] ) : '';
	$end   = isset( $p['endTime'] ) ? sanitize_text_field( $p['endTime'] ) : '';
	$recur = isset( $p['isRecurring'] ) ? (bool) $p['isRecurring'] : true;

	if ( $day < 0 || $day > 6 || null === buckleup_hm_to_minutes( $start ) || null === buckleup_hm_to_minutes( $end ) ) {
		return buckleup_rest_error( __( 'Invalid availability data', 'buckleup-app' ), 400 );
	}

	$t = buckleup_app_table( 'availability' );
	$wpdb->delete( $t, array( 'instructor_id' => $iid, 'day_of_week' => $day ), array( '%d', '%d' ) );
	$wpdb->insert( $t, array(
		'instructor_id' => $iid,
		'day_of_week'   => $day,
		'start_time'    => $start,
		'end_time'      => $end,
		'is_recurring'  => $recur ? 1 : 0,
	), array( '%d', '%d', '%s', '%s', '%d' ) );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $wpdb->insert_id ), ARRAY_A );
	return new WP_REST_Response( array( 'availability' => buckleup_availability_shape( $row ) ), 200 );
}

function buckleup_rest_instr_avail_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$iid = get_current_user_id();
	$p   = (array) $request->get_json_params();
	$day = isset( $p['dayOfWeek'] ) ? (int) $p['dayOfWeek'] : -1;
	if ( $day < 0 || $day > 6 ) {
		return buckleup_rest_error( __( 'Invalid day', 'buckleup-app' ), 400 );
	}
	$t       = buckleup_app_table( 'availability' );
	$deleted = $wpdb->delete( $t, array( 'instructor_id' => $iid, 'day_of_week' => $day ), array( '%d', '%d' ) );
	if ( ! $deleted ) {
		return buckleup_rest_error( __( 'No availability found for this day', 'buckleup-app' ), 404 );
	}
	return new WP_REST_Response( array( 'message' => __( 'Availability removed successfully', 'buckleup-app' ), 'dayOfWeek' => $day ), 200 );
}

/** ---- Availability exceptions ---------------------------------------- */

function buckleup_exception_shape( $row ) {
	return array(
		'id'           => (int) $row['id'],
		'instructorId' => (int) $row['instructor_id'],
		'date'         => $row['date'],
		'isAvailable'  => (bool) $row['is_available'],
		'startTime'    => $row['start_time'],
		'endTime'      => $row['end_time'],
		'reason'       => $row['reason'],
	);
}

function buckleup_rest_instr_exc_get( WP_REST_Request $request ) {
	global $wpdb;
	$iid   = get_current_user_id();
	$t     = buckleup_app_table( 'availability_exceptions' );
	$start = $request->get_param( 'startDate' );
	$end   = $request->get_param( 'endDate' );

	$sql    = "SELECT * FROM {$t} WHERE instructor_id=%d";
	$params = array( $iid );
	if ( $start && $end ) {
		$sql     .= ' AND date >= %s AND date <= %s';
		$params[] = $start;
		$params[] = $end;
	} elseif ( $start ) {
		$sql     .= ' AND date >= %s';
		$params[] = $start;
	}
	$sql .= ' ORDER BY date ASC';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return new WP_REST_Response( array( 'exceptions' => array_map( 'buckleup_exception_shape', (array) $rows ) ), 200 );
}

function buckleup_rest_instr_exc_post( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$iid = get_current_user_id();
	$p   = (array) $request->get_json_params();

	$date         = isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '';
	$is_available = ! empty( $p['isAvailable'] );
	$start        = isset( $p['startTime'] ) ? sanitize_text_field( $p['startTime'] ) : '';
	$end          = isset( $p['endTime'] ) ? sanitize_text_field( $p['endTime'] ) : '';
	$reason       = isset( $p['reason'] ) ? sanitize_text_field( $p['reason'] ) : '';

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return buckleup_rest_error( __( 'Invalid date', 'buckleup-app' ), 400 );
	}
	if ( $is_available && ( null === buckleup_hm_to_minutes( $start ) || null === buckleup_hm_to_minutes( $end ) ) ) {
		return buckleup_rest_error( __( 'Start time and end time are required when marking day as available with custom hours', 'buckleup-app' ), 400 );
	}

	$t   = buckleup_app_table( 'availability_exceptions' );
	$row = array(
		'instructor_id' => $iid,
		'date'          => $date,
		'is_available'  => $is_available ? 1 : 0,
		'start_time'    => $is_available ? $start : null,
		'end_time'      => $is_available ? $end : null,
		'reason'        => $reason,
	);

	// Upsert by UNIQUE(instructor_id, date).
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE instructor_id=%d AND date=%s", $iid, $date ) );
	if ( $existing ) {
		$wpdb->update( $t, $row, array( 'id' => (int) $existing ), array( '%d', '%s', '%d', '%s', '%s', '%s' ), array( '%d' ) );
		$id = (int) $existing;
	} else {
		$row['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $t, $row, array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		$id = (int) $wpdb->insert_id;
	}

	$saved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $id ), ARRAY_A );
	return new WP_REST_Response( array( 'exception' => buckleup_exception_shape( $saved ), 'message' => __( 'Exception saved successfully', 'buckleup-app' ) ), 200 );
}

function buckleup_rest_instr_exc_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$iid  = get_current_user_id();
	$p    = (array) $request->get_json_params();
	$date = isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '';
	if ( ! $date ) {
		return buckleup_rest_error( __( 'Date is required', 'buckleup-app' ), 400 );
	}
	$t       = buckleup_app_table( 'availability_exceptions' );
	$deleted = $wpdb->delete( $t, array( 'instructor_id' => $iid, 'date' => $date ), array( '%d', '%s' ) );
	if ( ! $deleted ) {
		return buckleup_rest_error( __( 'Exception not found', 'buckleup-app' ), 404 );
	}
	return new WP_REST_Response( array( 'message' => __( 'Exception removed successfully', 'buckleup-app' ) ), 200 );
}

/** ---- Bookings ------------------------------------------------------- */

function buckleup_rest_instr_bookings() {
	global $wpdb;
	$iid  = get_current_user_id();
	$t    = buckleup_app_table( 'bookings' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE instructor_id=%d ORDER BY datetime DESC", $iid ), ARRAY_A );
	return new WP_REST_Response( array( 'bookings' => array_map( 'buckleup_booking_shape', (array) $rows ) ), 200 );
}

/**
 * PUT /instructors/bookings/{id}/status — CONFIRMED|CANCELLED, then wp_mail the
 * student. Re-checks the row's instructor_id for ownership (403 otherwise).
 */
function buckleup_rest_instr_booking_status( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$iid = get_current_user_id();
	$id  = (int) $request['id'];
	$p   = (array) $request->get_json_params();

	$status = isset( $p['status'] ) ? sanitize_text_field( $p['status'] ) : '';
	$reason = isset( $p['reason'] ) ? sanitize_text_field( $p['reason'] ) : '';
	if ( ! in_array( $status, array( 'CONFIRMED', 'CANCELLED' ), true ) ) {
		return buckleup_rest_error( __( 'Invalid status', 'buckleup-app' ), 400 );
	}

	$t       = buckleup_app_table( 'bookings' );
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $id ), ARRAY_A );
	if ( ! $booking ) {
		return buckleup_rest_error( __( 'Booking not found', 'buckleup-app' ), 404 );
	}
	// OWNERSHIP: the booking must belong to this instructor (admins bypass).
	if ( ! buckleup_owns_instructor_scope( (int) $booking['instructor_id'] ) ) {
		return buckleup_rest_error( __( 'Not authorized to modify this booking', 'buckleup-app' ), 403 );
	}
	if ( 'COMPLETED' === $booking['status'] ) {
		return buckleup_rest_error( __( 'Cannot modify completed bookings', 'buckleup-app' ), 400 );
	}
	if ( 'CANCELLED' === $booking['status'] ) {
		return buckleup_rest_error( __( 'Booking is already cancelled', 'buckleup-app' ), 400 );
	}

	$notes = $booking['notes'];
	if ( $reason ) {
		$notes = trim( (string) $booking['notes'] . "\n[" . $status . ']: ' . $reason );
	}
	$wpdb->update( $t, array( 'status' => $status, 'notes' => $notes, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );

	// Notify the student via wp_mail (SMS/WhatsApp deferred).
	$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $id ), ARRAY_A );
	if ( function_exists( 'buckleup_notify_booking_status' ) ) {
		buckleup_notify_booking_status( $updated, $status, $reason );
	}

	return new WP_REST_Response( array(
		'booking' => buckleup_booking_shape( $updated ),
		/* translators: %s: new status, lowercased. */
		'message' => sprintf( __( 'Booking %s successfully', 'buckleup-app' ), strtolower( $status ) ),
	), 200 );
}

/** ---- Students (aggregated) ------------------------------------------ */

function buckleup_rest_instr_students() {
	global $wpdb;
	$iid  = get_current_user_id();
	$t    = buckleup_app_table( 'bookings' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE instructor_id=%d ORDER BY datetime DESC", $iid ), ARRAY_A );

	$now = current_time( 'timestamp', true ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$map = array();
	foreach ( (array) $rows as $b ) {
		$sid = (int) $b['student_id'];
		if ( ! isset( $map[ $sid ] ) ) {
			$map[ $sid ] = array(
				'total' => 0, 'completed' => 0, 'last' => null, 'next' => null, 'services' => array(),
			);
		}
		$map[ $sid ]['total']++;
		$svc = $b['service_id'] ? get_the_title( (int) $b['service_id'] ) : '';
		if ( $svc ) { $map[ $sid ]['services'][ $svc ] = true; }
		$ts = strtotime( $b['datetime'] );
		if ( 'COMPLETED' === $b['status'] ) {
			$map[ $sid ]['completed']++;
			if ( null === $map[ $sid ]['last'] || $ts > $map[ $sid ]['last'] ) { $map[ $sid ]['last'] = $ts; }
		}
		if ( in_array( $b['status'], array( 'PENDING', 'CONFIRMED' ), true ) && $ts > $now ) {
			if ( null === $map[ $sid ]['next'] || $ts < $map[ $sid ]['next'] ) { $map[ $sid ]['next'] = $ts; }
		}
	}

	$students = array();
	foreach ( $map as $sid => $d ) {
		$students[] = array(
			'id'               => $sid,
			'userId'           => $sid,
			'name'             => get_the_author_meta( 'display_name', $sid ),
			'email'            => get_the_author_meta( 'user_email', $sid ),
			'phone'            => buckleup_profile_get( $sid, 'bu_phone', '' ),
			'avatar'           => buckleup_user_public( $sid )['avatar'] ?? '',
			'licenseType'      => buckleup_profile_get( $sid, 'bu_license_type', '' ),
			'status'           => buckleup_profile_get( $sid, 'bu_status', 'ACTIVE' ),
			'totalLessons'     => $d['total'],
			'completedLessons' => $d['completed'],
			'lastLessonDate'   => $d['last'] ? gmdate( 'c', $d['last'] ) : null,
			'nextLessonDate'   => $d['next'] ? gmdate( 'c', $d['next'] ) : null,
			'services'         => array_keys( $d['services'] ),
			'latestProgress'   => buckleup_latest_progress_skills( $sid ),
		);
	}

	// Sort: upcoming first (by next asc), then by last desc.
	usort( $students, function ( $a, $b ) {
		if ( $a['nextLessonDate'] && ! $b['nextLessonDate'] ) { return -1; }
		if ( ! $a['nextLessonDate'] && $b['nextLessonDate'] ) { return 1; }
		if ( $a['nextLessonDate'] && $b['nextLessonDate'] ) {
			return strcmp( $a['nextLessonDate'], $b['nextLessonDate'] );
		}
		if ( $a['lastLessonDate'] && $b['lastLessonDate'] ) {
			return strcmp( $b['lastLessonDate'], $a['lastLessonDate'] );
		}
		return 0;
	} );

	return new WP_REST_Response( array( 'students' => $students ), 200 );
}

/**
 * Latest lesson-progress skills JSON for a student, or null.
 *
 * @param int $student_id
 * @return mixed
 */
function buckleup_latest_progress_skills( $student_id ) {
	global $wpdb;
	$t    = buckleup_app_table( 'lesson_progress' );
	$json = $wpdb->get_var( $wpdb->prepare( "SELECT skills FROM {$t} WHERE student_id=%d ORDER BY created_at DESC LIMIT 1", $student_id ) );
	return $json ? json_decode( $json, true ) : null;
}

/** ---- Instructor profile --------------------------------------------- */

function buckleup_instructor_profile_shape( $iid ) {
	$user = get_user_by( 'id', $iid );
	return array(
		'id'             => $iid,
		'name'           => $user ? $user->display_name : '',
		'email'          => $user ? $user->user_email : '',
		'phone'          => buckleup_profile_get( $iid, 'bu_phone', '' ),
		'image'          => buckleup_user_public( $iid )['avatar'] ?? '',
		'bio'            => buckleup_profile_get( $iid, 'bu_bio', '' ),
		'certifications' => buckleup_profile_get_list( $iid, 'bu_certifications' ),
		'languages'      => buckleup_profile_get_list( $iid, 'bu_languages' ),
		'hourlyRate'     => (float) buckleup_profile_get( $iid, 'bu_hourly_rate', 0 ),
		'isActive'       => (bool) buckleup_profile_get( $iid, 'bu_is_active', 1 ),
		'rating'         => (float) buckleup_profile_get( $iid, 'bu_rating', 0 ),
	);
}

function buckleup_rest_instr_profile_get() {
	return new WP_REST_Response( array( 'profile' => buckleup_instructor_profile_shape( get_current_user_id() ) ), 200 );
}

function buckleup_rest_instr_profile_put( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	$iid = get_current_user_id();
	$p   = (array) $request->get_json_params();

	if ( isset( $p['name'] ) && mb_strlen( sanitize_text_field( $p['name'] ) ) >= 2 ) {
		wp_update_user( array( 'ID' => $iid, 'display_name' => sanitize_text_field( $p['name'] ) ) );
	}
	if ( array_key_exists( 'phone', $p ) ) {
		update_user_meta( $iid, 'bu_phone', sanitize_text_field( (string) $p['phone'] ) );
	}
	if ( array_key_exists( 'bio', $p ) ) {
		update_user_meta( $iid, 'bu_bio', sanitize_textarea_field( (string) $p['bio'] ) );
	}
	if ( array_key_exists( 'hourlyRate', $p ) ) {
		update_user_meta( $iid, 'bu_hourly_rate', (float) $p['hourlyRate'] );
	}
	if ( array_key_exists( 'isActive', $p ) ) {
		update_user_meta( $iid, 'bu_is_active', $p['isActive'] ? 1 : 0 );
	}
	if ( isset( $p['certifications'] ) && is_array( $p['certifications'] ) ) {
		buckleup_profile_set_list( $iid, 'bu_certifications', $p['certifications'] );
	}
	if ( isset( $p['languages'] ) && is_array( $p['languages'] ) ) {
		buckleup_profile_set_list( $iid, 'bu_languages', $p['languages'] );
	}

	return new WP_REST_Response( array( 'profile' => buckleup_instructor_profile_shape( $iid ) ), 200 );
}
