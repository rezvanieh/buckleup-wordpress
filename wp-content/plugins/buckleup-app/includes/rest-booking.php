<?php
/**
 * Booking REST endpoints — buckleup/v1/bookings + /bookings/slots.
 *
 * Routes:
 *   GET  /bookings/slots?instructorId&date&duration  → { slots: [...] }   (logged-in)
 *   GET  /bookings                                    → { upcoming, past, all } (student)
 *   POST /bookings  (logged-in student) → re-validate availability via the
 *        shared slot engine, then create a PENDING booking. NO Stripe.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/bookings/slots', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_rest_slots',
		'permission_callback' => 'buckleup_perm_logged_in',
	) );

	register_rest_route( 'buckleup/v1', '/bookings', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_bookings_get',
			'permission_callback' => 'buckleup_perm_student',
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'buckleup_rest_bookings_post',
			'permission_callback' => 'buckleup_perm_student',
		),
	) );
} );

/**
 * GET /bookings/slots — available start times for an instructor on a date.
 */
function buckleup_rest_slots( WP_REST_Request $request ) {
	$instructor_id = (int) $request->get_param( 'instructorId' );
	$date          = sanitize_text_field( (string) $request->get_param( 'date' ) );
	$duration      = (int) ( $request->get_param( 'duration' ) ?: 60 );

	if ( ! $instructor_id || ! $date ) {
		return buckleup_rest_error( __( 'Missing required parameters', 'buckleup-app' ), 400 );
	}
	if ( ! buckleup_is_instructor( $instructor_id ) ) {
		return buckleup_rest_error( __( 'Instructor not found', 'buckleup-app' ), 404 );
	}

	$result = buckleup_compute_slots( $instructor_id, $date, $duration );
	return new WP_REST_Response( $result, 200 );
}

/**
 * GET /bookings — current student's bookings split into upcoming/past/all.
 */
function buckleup_rest_bookings_get() {
	global $wpdb;
	$sid  = get_current_user_id();
	$t    = buckleup_app_table( 'bookings' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE student_id=%d ORDER BY datetime DESC", $sid ), ARRAY_A );

	$all      = array_map( 'buckleup_booking_shape', (array) $rows );
	$now      = current_time( 'timestamp', true ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$upcoming = array();
	$past     = array();
	foreach ( (array) $rows as $i => $row ) {
		$ts = strtotime( $row['datetime'] );
		if ( $ts >= $now ) {
			$upcoming[] = $all[ $i ];
		} else {
			$past[] = $all[ $i ];
		}
	}

	return new WP_REST_Response( array( 'upcoming' => $upcoming, 'past' => $past, 'all' => $all ), 200 );
}

/**
 * POST /bookings — create a PENDING booking after re-validating the slot.
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_bookings_post( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	global $wpdb;
	$sid = get_current_user_id();
	$p   = (array) $request->get_json_params();

	$service_id    = isset( $p['serviceId'] ) ? (int) $p['serviceId'] : 0;
	$instructor_id = isset( $p['instructorId'] ) ? (int) $p['instructorId'] : 0;
	$datetime_raw  = isset( $p['datetime'] ) ? sanitize_text_field( $p['datetime'] ) : '';
	$pickup        = isset( $p['pickupAddr'] ) ? sanitize_text_field( $p['pickupAddr'] ) : '';
	$notes         = isset( $p['notes'] ) ? sanitize_textarea_field( $p['notes'] ) : '';

	// Validate references + inputs.
	if ( ! $service_id || ! get_post( $service_id ) || 'service' !== get_post_type( $service_id ) ) {
		return buckleup_rest_error( __( 'Invalid service', 'buckleup-app' ), 400 );
	}
	if ( ! $instructor_id || ! buckleup_is_instructor( $instructor_id ) ) {
		return buckleup_rest_error( __( 'Invalid instructor', 'buckleup-app' ), 400 );
	}
	$ts = strtotime( $datetime_raw );
	if ( false === $ts ) {
		return buckleup_rest_error( __( 'Invalid datetime', 'buckleup-app' ), 400 );
	}

	// Duration is AUTHORITATIVE from the service, never from the client. This
	// keeps the booking length matched to the service and prevents a malicious
	// client from POSTing a huge duration to blanket-block an instructor's day
	// (calendar-DoS). Fall back to 60 and hard-cap at a sane max.
	$duration = (int) get_post_meta( $service_id, 'bu_duration', true );
	if ( $duration < 1 ) {
		$duration = 60;
	}
	$duration = min( $duration, (int) apply_filters( 'buckleup_max_booking_minutes', 480 ) );

	// RE-VALIDATE the slot against the live availability engine (no trust in the
	// client). The candidate start must currently be free.
	$date_str = gmdate( 'Y-m-d', $ts );
	$time_str = gmdate( 'H:i', $ts );
	if ( ! buckleup_slot_is_available( $instructor_id, $date_str, $time_str, $duration ) ) {
		return buckleup_rest_error( __( 'That time slot is no longer available. Please pick another.', 'buckleup-app' ), 409 );
	}

	$t   = buckleup_app_table( 'bookings' );
	$now = current_time( 'mysql', true );
	$ok  = $wpdb->insert( $t, array(
		'student_id'    => $sid,
		'instructor_id' => $instructor_id,
		'service_id'    => $service_id,
		'datetime'      => gmdate( 'Y-m-d H:i:s', $ts ),
		'duration'      => $duration,
		'status'        => 'PENDING',
		'pickup_addr'   => $pickup ?: null,
		'notes'         => $notes ?: null,
		'created_at'    => $now,
		'updated_at'    => $now,
	), array( '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ) );

	if ( ! $ok ) {
		return buckleup_rest_error( __( 'Internal server error', 'buckleup-app' ), 500 );
	}
	$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $wpdb->insert_id ), ARRAY_A );

	return new WP_REST_Response( array(
		'message' => __( 'Booking created successfully', 'buckleup-app' ),
		'booking' => buckleup_booking_shape( $booking ),
	), 201 );
}
