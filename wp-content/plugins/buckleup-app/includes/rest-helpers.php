<?php
/**
 * REST permission / ownership / response helpers.
 *
 * Two-layer authorization on every route:
 *   1. CAPABILITY — the role grants entry to a console (permission_callback).
 *   2. OWNERSHIP — within a console, a user may only touch their own rows
 *      (an instructor only their own students/schedule; a student only their
 *      own data). Ownership is checked in the callback against the row's
 *      student_id / instructor_id, NOT assumed from the capability.
 *
 * Mutations additionally require a valid REST nonce (X-WP-Nonce). All SQL uses
 * $wpdb->prepare.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Permission callbacks (capability gate) ------------------------------- */

function buckleup_perm_student() {
	return is_user_logged_in() && current_user_can( 'buckleup_access_student_console' );
}
function buckleup_perm_instructor() {
	return is_user_logged_in() && current_user_can( 'buckleup_access_instructor_console' );
}
function buckleup_perm_admin() {
	return is_user_logged_in() && current_user_can( 'buckleup_access_admin_console' );
}
function buckleup_perm_logged_in() {
	return is_user_logged_in();
}

/**
 * Standard error response mirroring the source `{ error }` shape.
 *
 * @param string $message
 * @param int    $status
 * @return WP_Error
 */
function buckleup_rest_error( $message, $status = 400 ) {
	return new WP_Error( 'buckleup_error', $message, array( 'status' => $status ) );
}

/**
 * Ownership guard for instructor-scoped rows: the current user must be the
 * instructor on the row, OR an app admin. Returns true/false.
 *
 * @param int $instructor_id Row's instructor_id.
 * @return bool
 */
function buckleup_owns_instructor_scope( $instructor_id ) {
	$uid = get_current_user_id();
	return ( (int) $instructor_id === $uid && buckleup_is_instructor( $uid ) ) || buckleup_is_app_admin( $uid );
}

/**
 * Ownership guard for student-scoped rows.
 *
 * @param int $student_id Row's student_id.
 * @return bool
 */
function buckleup_owns_student_scope( $student_id ) {
	$uid = get_current_user_id();
	return ( (int) $student_id === $uid && buckleup_is_student( $uid ) ) || buckleup_is_app_admin( $uid );
}

/**
 * Hydrate a booking row into the REST shape the source returns, with nested
 * student/instructor/service objects.
 *
 * @param array<string,mixed> $row Raw bu_bookings row (ARRAY_A).
 * @return array<string,mixed>
 */
function buckleup_booking_shape( $row ) {
	$service_id = (int) $row['service_id'];
	$service    = $service_id ? get_post( $service_id ) : null;

	return array(
		'id'           => (int) $row['id'],
		'studentId'    => (int) $row['student_id'],
		'instructorId' => (int) $row['instructor_id'],
		'serviceId'    => $service_id,
		'datetime'     => buckleup_iso8601( $row['datetime'] ),
		'duration'     => (int) $row['duration'],
		'status'       => $row['status'],
		'pickupAddr'   => $row['pickup_addr'],
		'notes'        => $row['notes'],
		'createdAt'    => buckleup_iso8601( $row['created_at'] ),
		'student'      => array(
			'id'   => (int) $row['student_id'],
			'user' => array( 'name' => get_the_author_meta( 'display_name', (int) $row['student_id'] ) ),
		),
		'instructor'   => array(
			'id'   => (int) $row['instructor_id'],
			'user' => array( 'name' => get_the_author_meta( 'display_name', (int) $row['instructor_id'] ) ),
		),
		'service'      => $service ? array(
			'id'    => $service->ID,
			'name'  => $service->post_title,
			'price' => (float) get_post_meta( $service->ID, 'bu_price', true ),
		) : null,
	);
}

/**
 * MySQL DATETIME → ISO-8601 (UTC marker), matching the JSON the source emits.
 *
 * @param string $datetime
 * @return string|null
 */
function buckleup_iso8601( $datetime ) {
	if ( empty( $datetime ) ) {
		return null;
	}
	$ts = strtotime( $datetime );
	return false === $ts ? null : gmdate( 'c', $ts );
}

/**
 * Verify the REST nonce on a mutating request. Returns true or a WP_Error.
 * (WP also auto-checks `wp_rest` for cookie-authed REST, but we assert it
 * explicitly so a missing/expired nonce is a clear 403.)
 *
 * @param WP_REST_Request $request
 * @return true|WP_Error
 */
function buckleup_check_nonce( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return buckleup_rest_error( __( 'Your session expired. Please refresh and try again.', 'buckleup-app' ), 403 );
	}
	return true;
}
