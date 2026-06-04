<?php
/**
 * Cross-role user endpoints — buckleup/v1/user/* and the public/POST reviews.
 *
 * Routes:
 *   GET  /user/theme    → { theme }                      (any logged-in user)
 *   PUT  /user/theme    → { theme }    (light|dark|system → bu_theme user meta)
 *   POST /user/avatar   → { avatar }   (multipart upload → Media Library)
 *   DELETE /user/avatar → { avatar:null }
 *   GET  /reviews       → [ approved+public reviews ]    (PUBLIC, landing page)
 *   POST /reviews       → { message, review }  (logged-in student; isApproved=false)
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/user/theme', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_theme_get',
			'permission_callback' => 'buckleup_perm_logged_in',
		),
		array(
			'methods'             => 'PUT',
			'callback'            => 'buckleup_rest_theme_put',
			'permission_callback' => 'buckleup_perm_logged_in',
		),
	) );

	register_rest_route( 'buckleup/v1', '/user/avatar', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_avatar_get',
			'permission_callback' => 'buckleup_perm_logged_in',
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'buckleup_rest_avatar_post',
			'permission_callback' => 'buckleup_perm_logged_in',
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => 'buckleup_rest_avatar_delete',
			'permission_callback' => 'buckleup_perm_logged_in',
		),
	) );

	register_rest_route( 'buckleup/v1', '/reviews', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_reviews_public_get',
			'permission_callback' => '__return_true', // public landing data.
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'buckleup_rest_reviews_post',
			'permission_callback' => 'buckleup_perm_student',
		),
	) );
} );

/** ---- Theme ---------------------------------------------------------- */

function buckleup_rest_theme_get() {
	$theme = buckleup_profile_get( get_current_user_id(), 'bu_theme', 'system' );
	return new WP_REST_Response( array( 'theme' => $theme ), 200 );
}

function buckleup_rest_theme_put( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$params = $request->get_json_params();
	$theme  = isset( $params['theme'] ) ? sanitize_text_field( $params['theme'] ) : '';
	if ( ! in_array( $theme, array( 'light', 'dark', 'system' ), true ) ) {
		return buckleup_rest_error( __( 'Invalid theme', 'buckleup-app' ), 400 );
	}
	update_user_meta( get_current_user_id(), 'bu_theme', $theme );
	return new WP_REST_Response( array( 'theme' => $theme ), 200 );
}

/** ---- Avatar (Media Library) ----------------------------------------- */

/**
 * GET /user/avatar — current user's avatar URL + identity (admin Settings card).
 * Returns { avatar, image, name, email } (avatar === image for compatibility).
 */
function buckleup_rest_avatar_get() {
	$uid    = get_current_user_id();
	$pub    = buckleup_user_public( $uid );
	$avatar = $pub['avatar'] ?? '';
	return new WP_REST_Response( array(
		'avatar' => $avatar,
		'image'  => $avatar,
		'name'   => $pub['name'] ?? '',
		'email'  => $pub['email'] ?? '',
	), 200 );
}

function buckleup_rest_avatar_post( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$files = $request->get_file_params();
	if ( empty( $files['file'] ) ) {
		return buckleup_rest_error( __( 'No file uploaded', 'buckleup-app' ), 400 );
	}

	// Constrain to images.
	$check_type = wp_check_filetype( $files['file']['name'] );
	if ( ! preg_match( '#^image/#', (string) $check_type['type'] ) ) {
		return buckleup_rest_error( __( 'Avatar must be an image', 'buckleup-app' ), 400 );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$uid       = get_current_user_id();
	$attach_id = media_handle_sideload( $files['file'], 0, sprintf( 'Avatar for user %d', $uid ) );
	if ( is_wp_error( $attach_id ) ) {
		return buckleup_rest_error( $attach_id->get_error_message(), 500 );
	}

	// Remove the previous avatar attachment if any.
	$old = (int) buckleup_profile_get( $uid, 'bu_avatar_id', 0 );
	if ( $old && $old !== (int) $attach_id ) {
		wp_delete_attachment( $old, true );
	}

	update_user_meta( $uid, 'bu_avatar_id', (int) $attach_id );
	return new WP_REST_Response( array( 'avatar' => wp_get_attachment_image_url( $attach_id, 'thumbnail' ) ), 200 );
}

function buckleup_rest_avatar_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$uid = get_current_user_id();
	$old = (int) buckleup_profile_get( $uid, 'bu_avatar_id', 0 );
	if ( $old ) {
		wp_delete_attachment( $old, true );
	}
	delete_user_meta( $uid, 'bu_avatar_id' );
	return new WP_REST_Response( array( 'avatar' => null ), 200 );
}

/** ---- Reviews (public GET + student POST) ---------------------------- */

/**
 * Fetch approved + public review rows, newest first. Single source of truth for
 * both the public REST endpoint and the server-side homepage helper.
 *
 * @param int $limit -1 for all, else cap the row count.
 * @return array<int,array<string,mixed>> Raw bu_reviews rows (ARRAY_A).
 */
function buckleup_query_public_reviews( $limit = -1 ) {
	global $wpdb;
	$reviews = buckleup_app_table( 'reviews' );

	if ( $limit > 0 ) {
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$reviews} WHERE is_public = 1 AND is_approved = 1 ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}
	return (array) $wpdb->get_results(
		"SELECT * FROM {$reviews} WHERE is_public = 1 AND is_approved = 1 ORDER BY created_at DESC",
		ARRAY_A
	);
}

/**
 * PUBLIC server-side helper: approved + public reviews for the homepage
 * testimonials (the homepage is server-rendered PHP, not a JS fetch). Newest
 * first. Same data as GET /reviews; compact shape for direct theme rendering.
 *
 * @param int $limit Max reviews (default 12).
 * @return array<int,array{id:int,name:string,rating:int,comment:string,date:string|null}>
 */
function buckleup_get_public_reviews( $limit = 12 ) {
	$out = array();
	foreach ( buckleup_query_public_reviews( (int) $limit ) as $row ) {
		$sid   = (int) $row['student_id'];
		$out[] = array(
			'id'      => (int) $row['id'],
			'name'    => get_the_author_meta( 'display_name', $sid ),
			'rating'  => (int) $row['rating'],
			'comment' => $row['comment'] ? $row['comment'] : '',
			'date'    => buckleup_iso8601( $row['created_at'] ),
		);
	}
	return $out;
}

/**
 * GET /reviews — approved + public reviews, transformed for the landing page.
 * Mirrors the source /api/reviews shape so the theme's Testimonials can read it.
 * Shares the row query with buckleup_get_public_reviews() (single source).
 */
function buckleup_rest_reviews_public_get() {
	$out = array();
	foreach ( buckleup_query_public_reviews( -1 ) as $row ) {
		$sid   = (int) $row['student_id'];
		$out[] = array(
			'id'             => (int) $row['id'],
			'name'           => get_the_author_meta( 'display_name', $sid ),
			'image'          => buckleup_user_public( $sid )['avatar'] ?? '',
			'role'           => buckleup_profile_get( $sid, 'bu_license_type', '' ) ?: 'Student',
			'content'        => $row['comment'] ? $row['comment'] : '',
			'rating'         => (int) $row['rating'],
			'instructorName' => $row['instructor_id'] ? get_the_author_meta( 'display_name', (int) $row['instructor_id'] ) : null,
			'createdAt'      => buckleup_iso8601( $row['created_at'] ),
		);
	}

	return new WP_REST_Response( $out, 200 );
}

/**
 * POST /reviews — a student submits a review (isApproved=false, needs moderation).
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_reviews_post( WP_REST_Request $request ) {
	global $wpdb;
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$params        = $request->get_json_params();
	$params        = is_array( $params ) ? $params : array();
	$rating        = isset( $params['rating'] ) ? (int) $params['rating'] : 0;
	$comment       = isset( $params['comment'] ) ? sanitize_textarea_field( $params['comment'] ) : '';
	$instructor_id = isset( $params['instructorId'] ) ? (int) $params['instructorId'] : 0;
	$is_public     = isset( $params['isPublic'] ) ? (bool) $params['isPublic'] : true;

	if ( $rating < 1 || $rating > 5 ) {
		return buckleup_rest_error( __( 'Rating must be between 1 and 5', 'buckleup-app' ), 400 );
	}
	$trimmed = trim( $comment );
	if ( mb_strlen( $trimmed ) < 10 ) {
		return buckleup_rest_error( __( 'Comment must be at least 10 characters', 'buckleup-app' ), 400 );
	}
	if ( mb_strlen( $comment ) > 1000 ) {
		return buckleup_rest_error( __( 'Comment must be less than 1000 characters', 'buckleup-app' ), 400 );
	}

	// Validate the instructor if one was named.
	if ( $instructor_id && ! buckleup_is_instructor( $instructor_id ) ) {
		return buckleup_rest_error( __( 'Instructor not found', 'buckleup-app' ), 404 );
	}

	$reviews = buckleup_app_table( 'reviews' );
	$now     = current_time( 'mysql', true );
	$ok      = $wpdb->insert(
		$reviews,
		array(
			'student_id'    => get_current_user_id(),
			'instructor_id' => $instructor_id ?: null,
			'rating'        => $rating,
			'comment'       => $trimmed,
			'is_public'     => $is_public ? 1 : 0,
			'is_approved'   => 0,
			'created_at'    => $now,
		),
		array( '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
	);
	if ( ! $ok ) {
		return buckleup_rest_error( __( 'Internal server error', 'buckleup-app' ), 500 );
	}
	$id = (int) $wpdb->insert_id;

	return new WP_REST_Response(
		array(
			'message' => __( 'Review submitted successfully. It will appear on the website after approval.', 'buckleup-app' ),
			'review'  => array(
				'id'         => $id,
				'rating'     => $rating,
				'comment'    => $trimmed,
				'isPublic'   => $is_public,
				'isApproved' => false,
				'createdAt'  => buckleup_iso8601( $now ),
			),
		),
		201
	);
}
