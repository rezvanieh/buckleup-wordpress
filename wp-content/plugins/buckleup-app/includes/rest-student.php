<?php
/**
 * Student console REST endpoints — buckleup/v1/students/*  (+ /bookings GET via
 * the booking controller). Mirrors the source /api/students/* shapes.
 *
 * Routes:
 *   GET  /students/profile   → { profile }
 *   PUT  /students/profile   → { profile }   (name/phone + student meta)
 *   GET  /students/progress  → { progress: [...] }
 *   GET  /students/reviews   → [ {id,rating,comment,instructorName,isPublic,isApproved,createdAt} ]
 *
 * All gated by the student-console capability; data is implicitly the current
 * user's own (student_id = current user id), so ownership is automatic.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/students/profile', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_student_profile_get',
			'permission_callback' => 'buckleup_perm_student',
		),
		array(
			'methods'             => 'PUT',
			'callback'            => 'buckleup_rest_student_profile_put',
			'permission_callback' => 'buckleup_perm_student',
		),
	) );

	register_rest_route( 'buckleup/v1', '/students/progress', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_rest_student_progress_get',
		'permission_callback' => 'buckleup_perm_student',
	) );

	register_rest_route( 'buckleup/v1', '/students/reviews', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_rest_student_reviews_get',
		'permission_callback' => 'buckleup_perm_student',
	) );
} );

/**
 * Build the student profile shape for a user.
 *
 * @param int $uid
 * @return array<string,mixed>
 */
function buckleup_student_profile_shape( $uid ) {
	$user = get_user_by( 'id', $uid );
	return array(
		'id'               => $uid,
		'name'             => $user ? $user->display_name : '',
		'email'            => $user ? $user->user_email : '',
		'phone'            => buckleup_profile_get( $uid, 'bu_phone', '' ),
		'image'            => buckleup_user_public( $uid )['avatar'] ?? '',
		'licenseType'      => buckleup_profile_get( $uid, 'bu_license_type', '' ),
		'emergencyContact' => buckleup_profile_get( $uid, 'bu_emergency_contact', '' ),
		'emergencyPhone'   => buckleup_profile_get( $uid, 'bu_emergency_phone', '' ),
		'preferredLang'    => buckleup_profile_get( $uid, 'bu_preferred_lang', 'en' ),
		'createdAt'        => buckleup_iso8601( $user ? $user->user_registered : null ),
	);
}

/**
 * GET /students/profile
 */
function buckleup_rest_student_profile_get() {
	return new WP_REST_Response( array( 'profile' => buckleup_student_profile_shape( get_current_user_id() ) ), 200 );
}

/**
 * PUT /students/profile
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_student_profile_put( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$uid    = get_current_user_id();
	$params = $request->get_json_params();
	$params = is_array( $params ) ? $params : array();

	if ( isset( $params['name'] ) ) {
		$name = sanitize_text_field( $params['name'] );
		if ( mb_strlen( $name ) >= 2 ) {
			wp_update_user( array( 'ID' => $uid, 'display_name' => $name ) );
		}
	}
	if ( array_key_exists( 'phone', $params ) ) {
		update_user_meta( $uid, 'bu_phone', sanitize_text_field( (string) $params['phone'] ) );
	}
	foreach ( array(
		'licenseType'      => 'bu_license_type',
		'emergencyContact' => 'bu_emergency_contact',
		'emergencyPhone'   => 'bu_emergency_phone',
	) as $in => $meta ) {
		if ( array_key_exists( $in, $params ) ) {
			update_user_meta( $uid, $meta, sanitize_text_field( (string) $params[ $in ] ) );
		}
	}
	update_user_meta( $uid, 'bu_preferred_lang', isset( $params['preferredLang'] ) && $params['preferredLang'] ? sanitize_text_field( $params['preferredLang'] ) : 'en' );

	return new WP_REST_Response( array( 'profile' => buckleup_student_profile_shape( $uid ) ), 200 );
}

/**
 * GET /students/progress — lesson-progress rows for the current student.
 */
function buckleup_rest_student_progress_get() {
	global $wpdb;
	$uid      = get_current_user_id();
	$progress = buckleup_app_table( 'lesson_progress' );
	$bookings = buckleup_app_table( 'bookings' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.*, b.service_id, b.instructor_id, b.datetime AS booking_datetime
			 FROM {$progress} p
			 LEFT JOIN {$bookings} b ON b.id = p.booking_id
			 WHERE p.student_id = %d
			 ORDER BY p.created_at DESC",
			$uid
		),
		ARRAY_A
	);

	$out = array();
	foreach ( (array) $rows as $row ) {
		$service = $row['service_id'] ? get_post( (int) $row['service_id'] ) : null;
		$out[]   = array(
			'id'              => (int) $row['id'],
			'bookingId'       => (int) $row['booking_id'],
			'skills'          => json_decode( (string) $row['skills'], true ),
			'notes'           => $row['notes'],
			'instructorNotes' => $row['instructor_notes'],
			'createdAt'       => buckleup_iso8601( $row['created_at'] ),
			'booking'         => array(
				'datetime'   => buckleup_iso8601( $row['booking_datetime'] ),
				'service'    => $service ? array( 'name' => $service->post_title ) : null,
				'instructor' => array(
					'user' => array( 'name' => get_the_author_meta( 'display_name', (int) $row['instructor_id'] ) ),
				),
			),
		);
	}

	return new WP_REST_Response( array( 'progress' => $out ), 200 );
}

/**
 * GET /students/reviews — the current student's own reviews + status.
 */
function buckleup_rest_student_reviews_get() {
	global $wpdb;
	$uid     = get_current_user_id();
	$reviews = buckleup_app_table( 'reviews' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$reviews} WHERE student_id = %d ORDER BY created_at DESC",
			$uid
		),
		ARRAY_A
	);

	$out = array();
	foreach ( (array) $rows as $row ) {
		$out[] = array(
			'id'             => (int) $row['id'],
			'rating'         => (int) $row['rating'],
			'comment'        => $row['comment'],
			'instructorName' => $row['instructor_id'] ? get_the_author_meta( 'display_name', (int) $row['instructor_id'] ) : null,
			'isPublic'       => (bool) $row['is_public'],
			'isApproved'     => (bool) $row['is_approved'],
			'createdAt'      => buckleup_iso8601( $row['created_at'] ),
		);
	}

	return new WP_REST_Response( $out, 200 );
}
