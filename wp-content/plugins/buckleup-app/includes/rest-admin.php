<?php
/**
 * Admin console REST endpoints — buckleup/v1/admin/*.
 * Mirrors the source /api/admin/* shapes. Gated by the admin-console capability.
 *
 * Routes:
 *   GET    /admin/stats
 *   GET    /admin/students
 *   DELETE /admin/students/{id}      (cascade: progress, bookings, reviews → user)
 *   GET    /admin/instructors
 *   GET    /admin/bookings
 *   GET    /admin/reviews
 *   PATCH  /admin/reviews/{id}        (approve toggle)
 *   DELETE /admin/reviews/{id}
 *   GET    /admin/notifications       (template list)
 *   POST   /admin/notifications       (create template)
 *   PUT    /admin/notifications/{id}  (update template)
 *   DELETE /admin/notifications/{id}
 *
 * NOTE on revenue: the source aggregates Transaction (Stripe) for totalRevenue;
 * payments are deferred, so totalRevenue is always 0 in v1.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	$admin = array( 'permission_callback' => 'buckleup_perm_admin' );

	register_rest_route( 'buckleup/v1', '/admin/stats', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_stats' ) + $admin ) );
	register_rest_route( 'buckleup/v1', '/admin/students', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_students' ) + $admin ) );
	register_rest_route( 'buckleup/v1', '/admin/students/(?P<id>\d+)', array( array( 'methods' => 'DELETE', 'callback' => 'buckleup_rest_admin_student_delete' ) + $admin ) );
	register_rest_route( 'buckleup/v1', '/admin/instructors', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_instructors' ) + $admin ) );
	register_rest_route( 'buckleup/v1', '/admin/bookings', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_bookings' ) + $admin ) );

	register_rest_route( 'buckleup/v1', '/admin/reviews', array( array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_reviews' ) + $admin ) );
	register_rest_route( 'buckleup/v1', '/admin/reviews/(?P<id>\d+)', array(
		array( 'methods' => 'PATCH', 'callback' => 'buckleup_rest_admin_review_patch' ) + $admin,
		array( 'methods' => 'DELETE', 'callback' => 'buckleup_rest_admin_review_delete' ) + $admin,
	) );

	register_rest_route( 'buckleup/v1', '/admin/notifications', array(
		array( 'methods' => 'GET', 'callback' => 'buckleup_rest_admin_notif_list' ) + $admin,
		array( 'methods' => 'POST', 'callback' => 'buckleup_rest_admin_notif_create' ) + $admin,
	) );
	register_rest_route( 'buckleup/v1', '/admin/notifications/(?P<id>\d+)', array(
		array( 'methods' => 'PUT', 'callback' => 'buckleup_rest_admin_notif_update' ) + $admin,
		array( 'methods' => 'DELETE', 'callback' => 'buckleup_rest_admin_notif_delete' ) + $admin,
	) );
} );

/** ---- Stats ---------------------------------------------------------- */

function buckleup_rest_admin_stats() {
	global $wpdb;
	$bookings = buckleup_app_table( 'bookings' );

	$total_students    = count( get_users( array( 'role' => 'buckleup_student', 'fields' => 'ID' ) ) );
	$total_instructors = count( get_users( array( 'role' => 'buckleup_instructor', 'fields' => 'ID' ) ) );
	$total_bookings    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings}" );

	$recent = $wpdb->get_results( "SELECT * FROM {$bookings} ORDER BY created_at DESC LIMIT 5", ARRAY_A );

	return new WP_REST_Response( array(
		'stats'          => array(
			'totalStudents'    => $total_students,
			'totalInstructors' => $total_instructors,
			'totalBookings'    => $total_bookings,
			'totalRevenue'     => 0, // payments deferred (no transactions in v1).
		),
		'recentBookings' => array_map( 'buckleup_booking_shape', (array) $recent ),
	), 200 );
}

/** ---- Students ------------------------------------------------------- */

/**
 * GET /admin/students — paginated + filtered + enriched, with stats wrappers.
 *
 * Query params: search, status, licenseType, page (1-based), limit.
 * Response: { students, stats{total,active,byStatus,byLicenseType}, pagination{total,pages,page,limit} }.
 */
function buckleup_rest_admin_students( WP_REST_Request $request ) {
	global $wpdb;

	$search       = sanitize_text_field( (string) $request->get_param( 'search' ) );
	$status_f     = sanitize_text_field( (string) $request->get_param( 'status' ) );
	$license_f    = sanitize_text_field( (string) $request->get_param( 'licenseType' ) );
	$page         = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
	$limit        = min( 100, max( 1, (int) ( $request->get_param( 'limit' ) ?: 20 ) ) );

	// Pull all students once (the dataset is small for a driving school); filter
	// + paginate in PHP for clarity, computing stats over the FULL set.
	$all = get_users( array( 'role' => 'buckleup_student', 'orderby' => 'display_name', 'order' => 'ASC' ) );

	$stats = array( 'total' => 0, 'active' => 0, 'byStatus' => array(), 'byLicenseType' => array() );
	$rows  = array();
	foreach ( $all as $u ) {
		$status  = buckleup_profile_get( $u->ID, 'bu_status', 'ACTIVE' );
		$license = buckleup_profile_get( $u->ID, 'bu_license_type', '' );

		// Stats over the unfiltered set.
		$stats['total']++;
		if ( 'ACTIVE' === $status ) {
			$stats['active']++;
		}
		$stats['byStatus'][ $status ]            = ( $stats['byStatus'][ $status ] ?? 0 ) + 1;
		$lk                                      = $license ?: 'Unspecified';
		$stats['byLicenseType'][ $lk ]           = ( $stats['byLicenseType'][ $lk ] ?? 0 ) + 1;

		// Filters.
		if ( '' !== $search ) {
			$hay = strtolower( $u->display_name . ' ' . $u->user_email );
			if ( false === strpos( $hay, strtolower( $search ) ) ) {
				continue;
			}
		}
		if ( '' !== $status_f && $status_f !== $status ) {
			continue;
		}
		if ( '' !== $license_f && $license_f !== $license ) {
			continue;
		}

		$rows[] = buckleup_admin_student_row( $u, $status, $license );
	}

	$matched = count( $rows );
	$pages   = (int) ceil( $matched / $limit );
	$offset  = ( $page - 1 ) * $limit;
	$paged   = array_slice( $rows, $offset, $limit );

	return new WP_REST_Response( array(
		'students'   => $paged,
		'stats'      => $stats,
		'pagination' => array(
			'total' => $matched,
			'pages' => $pages,
			'page'  => $page,
			'limit' => $limit,
		),
	), 200 );
}

/**
 * Enriched admin student row (booking count, last booking, emergency + profile).
 *
 * @param WP_User $u
 * @param string  $status
 * @param string  $license
 * @return array<string,mixed>
 */
function buckleup_admin_student_row( $u, $status, $license ) {
	global $wpdb;
	$bk = buckleup_app_table( 'bookings' );

	$booking_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bk} WHERE student_id=%d", $u->ID ) );
	$last_booking  = $wpdb->get_var( $wpdb->prepare( "SELECT datetime FROM {$bk} WHERE student_id=%d ORDER BY datetime DESC LIMIT 1", $u->ID ) );

	return array(
		'id'               => $u->ID,
		'userId'           => $u->ID,
		'name'             => $u->display_name,
		'email'            => $u->user_email,
		'phone'            => buckleup_profile_get( $u->ID, 'bu_phone', '' ),
		'image'            => buckleup_user_public( $u->ID )['avatar'] ?? '',
		'licenseType'      => $license,
		'status'           => $status,
		'bookingCount'     => $booking_count,
		'lastBooking'      => $last_booking ? buckleup_iso8601( $last_booking ) : null,
		'emergencyContact' => buckleup_profile_get( $u->ID, 'bu_emergency_contact', '' ),
		'emergencyPhone'   => buckleup_profile_get( $u->ID, 'bu_emergency_phone', '' ),
		'preferredLang'    => buckleup_profile_get( $u->ID, 'bu_preferred_lang', 'en' ),
		'userCreatedAt'    => buckleup_iso8601( $u->user_registered ),
	);
}

/**
 * DELETE /admin/students/{id} — cascade delete the student's app rows, then the
 * WP user. (Source deletes progress, transactions, bookings, packages, reviews,
 * then the student; we delete the v1 equivalents.)
 */
function buckleup_rest_admin_student_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$id = (int) $request['id'];

	$user = get_user_by( 'id', $id );
	if ( ! $user || ! buckleup_is_student( $user ) ) {
		return buckleup_rest_error( __( 'Student not found', 'buckleup-app' ), 404 );
	}

	$wpdb->delete( buckleup_app_table( 'lesson_progress' ), array( 'student_id' => $id ), array( '%d' ) );
	$wpdb->delete( buckleup_app_table( 'bookings' ), array( 'student_id' => $id ), array( '%d' ) );
	$wpdb->delete( buckleup_app_table( 'reviews' ), array( 'student_id' => $id ), array( '%d' ) );

	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $id );

	return new WP_REST_Response( array( 'message' => __( 'Student and reviews deleted successfully', 'buckleup-app' ) ), 200 );
}

/** ---- Instructors ---------------------------------------------------- */

function buckleup_rest_admin_instructors() {
	$instructors = array();
	foreach ( get_users( array( 'role' => 'buckleup_instructor', 'orderby' => 'display_name' ) ) as $u ) {
		$instructors[] = array(
			'id'             => $u->ID,
			'userId'         => $u->ID,
			'name'           => $u->display_name,
			'email'          => $u->user_email,
			'phone'          => buckleup_profile_get( $u->ID, 'bu_phone', '' ),
			'bio'            => buckleup_profile_get( $u->ID, 'bu_bio', '' ),
			'certifications' => buckleup_profile_get_list( $u->ID, 'bu_certifications' ),
			'languages'      => buckleup_profile_get_list( $u->ID, 'bu_languages' ),
			'hourlyRate'     => (float) buckleup_profile_get( $u->ID, 'bu_hourly_rate', 0 ),
			'isActive'       => (bool) buckleup_profile_get( $u->ID, 'bu_is_active', 1 ),
			'rating'         => (float) buckleup_profile_get( $u->ID, 'bu_rating', 0 ),
		);
	}
	return new WP_REST_Response( array( 'instructors' => $instructors ), 200 );
}

/** ---- Bookings ------------------------------------------------------- */

function buckleup_rest_admin_bookings() {
	global $wpdb;
	$t    = buckleup_app_table( 'bookings' );
	$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY datetime DESC", ARRAY_A );
	return new WP_REST_Response( array( 'bookings' => array_map( 'buckleup_booking_shape', (array) $rows ) ), 200 );
}

/** ---- Reviews -------------------------------------------------------- */

function buckleup_admin_review_shape( $row ) {
	$sid = (int) $row['student_id'];
	return array(
		'id'             => (int) $row['id'],
		'studentName'    => get_the_author_meta( 'display_name', $sid ),
		'studentEmail'   => get_the_author_meta( 'user_email', $sid ),
		'studentImage'   => buckleup_user_public( $sid )['avatar'] ?? '',
		'instructorName' => $row['instructor_id'] ? get_the_author_meta( 'display_name', (int) $row['instructor_id'] ) : null,
		'rating'         => (int) $row['rating'],
		'comment'        => $row['comment'],
		'isPublic'       => (bool) $row['is_public'],
		'isApproved'     => (bool) $row['is_approved'],
		'createdAt'      => buckleup_iso8601( $row['created_at'] ),
	);
}

/**
 * GET /admin/reviews — BARE array (not wrapped) to match the source shape the
 * admin reviews page reads.
 */
function buckleup_rest_admin_reviews() {
	global $wpdb;
	$t    = buckleup_app_table( 'reviews' );
	$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created_at DESC", ARRAY_A );
	return new WP_REST_Response( array_map( 'buckleup_admin_review_shape', (array) $rows ), 200 );
}

function buckleup_rest_admin_review_patch( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$id = (int) $request['id'];
	$p  = (array) $request->get_json_params();
	if ( ! array_key_exists( 'isApproved', $p ) || ! is_bool( $p['isApproved'] ) ) {
		return buckleup_rest_error( __( 'Invalid status', 'buckleup-app' ), 400 );
	}
	$t  = buckleup_app_table( 'reviews' );
	$ok = $wpdb->update( $t, array( 'is_approved' => $p['isApproved'] ? 1 : 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	if ( false === $ok ) {
		return buckleup_rest_error( __( 'Internal server error', 'buckleup-app' ), 500 );
	}
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $id ), ARRAY_A );
	if ( ! $row ) {
		return buckleup_rest_error( __( 'Review not found', 'buckleup-app' ), 404 );
	}
	return new WP_REST_Response( buckleup_admin_review_shape( $row ), 200 );
}

function buckleup_rest_admin_review_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	global $wpdb;
	$id = (int) $request['id'];
	$wpdb->delete( buckleup_app_table( 'reviews' ), array( 'id' => $id ), array( '%d' ) );
	return new WP_REST_Response( array( 'message' => __( 'Review deleted', 'buckleup-app' ) ), 200 );
}

/** ---- Notification templates (CRUD) ---------------------------------- */

function buckleup_notif_template_shape( $post ) {
	return array(
		'id'        => $post->ID,
		'eventKey'  => (string) get_post_meta( $post->ID, 'bu_event_key', true ),
		'channel'   => (string) get_post_meta( $post->ID, 'bu_channel', true ),
		'locale'    => (string) ( get_post_meta( $post->ID, 'bu_locale', true ) ?: 'en' ),
		'subject'   => (string) get_post_meta( $post->ID, 'bu_subject', true ),
		'textBody'  => $post->post_content,
		'htmlBody'  => (string) get_post_meta( $post->ID, 'bu_html_body', true ),
		'isActive'  => '1' === get_post_meta( $post->ID, 'bu_is_active', true ),
		'updatedAt' => buckleup_iso8601( $post->post_modified_gmt ),
	);
}

function buckleup_rest_admin_notif_list() {
	$posts = get_posts( array( 'post_type' => 'bu_notif_template', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	return new WP_REST_Response( array( 'templates' => array_map( 'buckleup_notif_template_shape', $posts ) ), 200 );
}

/**
 * Persist template fields from the request onto a post id.
 *
 * @param int                  $post_id
 * @param array<string,mixed>  $p
 */
function buckleup_notif_template_save_meta( $post_id, $p ) {
	if ( isset( $p['eventKey'] ) ) { update_post_meta( $post_id, 'bu_event_key', sanitize_text_field( $p['eventKey'] ) ); }
	if ( isset( $p['channel'] ) ) { update_post_meta( $post_id, 'bu_channel', strtoupper( sanitize_text_field( $p['channel'] ) ) ); }
	if ( isset( $p['locale'] ) ) { update_post_meta( $post_id, 'bu_locale', sanitize_text_field( $p['locale'] ) ); }
	if ( isset( $p['subject'] ) ) { update_post_meta( $post_id, 'bu_subject', sanitize_text_field( $p['subject'] ) ); }
	if ( isset( $p['htmlBody'] ) ) { update_post_meta( $post_id, 'bu_html_body', wp_kses_post( $p['htmlBody'] ) ); }
	update_post_meta( $post_id, 'bu_is_active', ( ! isset( $p['isActive'] ) || $p['isActive'] ) ? '1' : '' );
}

function buckleup_rest_admin_notif_create( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	$p = (array) $request->get_json_params();
	if ( empty( $p['eventKey'] ) || empty( $p['channel'] ) ) {
		return buckleup_rest_error( __( 'eventKey and channel are required', 'buckleup-app' ), 400 );
	}
	$title   = sanitize_text_field( $p['eventKey'] ) . ' / ' . strtoupper( sanitize_text_field( $p['channel'] ) );
	$post_id = wp_insert_post( array(
		'post_type'    => 'bu_notif_template',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => isset( $p['textBody'] ) ? wp_kses_post( $p['textBody'] ) : '',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return buckleup_rest_error( $post_id->get_error_message(), 500 );
	}
	buckleup_notif_template_save_meta( $post_id, $p );
	return new WP_REST_Response( buckleup_notif_template_shape( get_post( $post_id ) ), 201 );
}

function buckleup_rest_admin_notif_update( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	$id   = (int) $request['id'];
	$post = get_post( $id );
	if ( ! $post || 'bu_notif_template' !== $post->post_type ) {
		return buckleup_rest_error( __( 'Template not found', 'buckleup-app' ), 404 );
	}
	$p = (array) $request->get_json_params();
	if ( isset( $p['textBody'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_content' => wp_kses_post( $p['textBody'] ) ) );
	}
	buckleup_notif_template_save_meta( $id, $p );
	return new WP_REST_Response( buckleup_notif_template_shape( get_post( $id ) ), 200 );
}

function buckleup_rest_admin_notif_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) { return $check; }
	$id   = (int) $request['id'];
	$post = get_post( $id );
	if ( ! $post || 'bu_notif_template' !== $post->post_type ) {
		return buckleup_rest_error( __( 'Template not found', 'buckleup-app' ), 404 );
	}
	wp_delete_post( $id, true );
	return new WP_REST_Response( array( 'message' => __( 'Template deleted', 'buckleup-app' ) ), 200 );
}
