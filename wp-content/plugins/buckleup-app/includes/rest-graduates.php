<?php
/**
 * Graduates REST — buckleup/v1/graduates.
 *
 * Operates on the SAME `graduate` CPT that buckleup-core registers and the
 * public landing Hall-of-Fame renders via buckleup_get_graduates(), so the
 * admin console and the public showcase never diverge.
 *
 * Routes:
 *   GET    /graduates        → [ {id,title,description,url,category,createdAt} ]  (public)
 *   POST   /graduates        → created row   (admin; multipart image → Media Library)
 *   DELETE /graduates/{id}   → { message }   (admin)
 *
 * Field mapping on the `graduate` CPT:
 *   title       = post_title
 *   description = post_content
 *   url         = featured image URL
 *   category    = bu_category meta (additive; landing ignores it)
 *   active      = bu_is_active meta (landing filters on this)
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/graduates', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'buckleup_rest_graduates_get',
			'permission_callback' => '__return_true', // public (landing + admin list).
		),
		array(
			'methods'             => 'POST',
			'callback'            => 'buckleup_rest_graduates_post',
			'permission_callback' => 'buckleup_perm_admin',
		),
	) );

	register_rest_route( 'buckleup/v1', '/graduates/(?P<id>\d+)', array(
		array(
			'methods'             => 'DELETE',
			'callback'            => 'buckleup_rest_graduates_delete',
			'permission_callback' => 'buckleup_perm_admin',
		),
	) );
} );

/**
 * Shape a graduate CPT post for the REST response.
 *
 * @param WP_Post $post
 * @return array<string,mixed>
 */
function buckleup_graduate_shape( $post ) {
	return array(
		'id'          => $post->ID,
		'title'       => $post->post_title,
		'description' => $post->post_content,
		'url'         => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
		'category'    => (string) get_post_meta( $post->ID, 'bu_category', true ),
		'createdAt'   => buckleup_iso8601( $post->post_date_gmt ),
	);
}

/**
 * GET /graduates — active graduates, newest first (landing parity).
 */
function buckleup_rest_graduates_get() {
	$posts = get_posts( array(
		'post_type'      => 'graduate',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => 'bu_is_active', 'value' => '1' ),
			array( 'key' => 'bu_is_active', 'compare' => 'NOT EXISTS' ),
		),
	) );
	return new WP_REST_Response( array_map( 'buckleup_graduate_shape', $posts ), 200 );
}

/**
 * POST /graduates — create a graduate with an uploaded photo.
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_graduates_post( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$title       = sanitize_text_field( (string) $request->get_param( 'title' ) );
	$description = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
	$category    = sanitize_text_field( (string) $request->get_param( 'category' ) );
	$files       = $request->get_file_params();

	if ( empty( $files['file'] ) && empty( $files['image'] ) ) {
		return buckleup_rest_error( __( 'A photo is required', 'buckleup-app' ), 400 );
	}
	$file = ! empty( $files['file'] ) ? $files['file'] : $files['image'];

	$type = wp_check_filetype( $file['name'] );
	if ( ! preg_match( '#^image/#', (string) $type['type'] ) ) {
		return buckleup_rest_error( __( 'Graduate photo must be an image', 'buckleup-app' ), 400 );
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'graduate',
		'post_status'  => 'publish',
		'post_title'   => $title ?: __( 'Graduate', 'buckleup-app' ),
		'post_content' => $description,
	), true );
	if ( is_wp_error( $post_id ) ) {
		return buckleup_rest_error( $post_id->get_error_message(), 500 );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attach_id = media_handle_sideload( $file, $post_id, $title );
	if ( is_wp_error( $attach_id ) ) {
		wp_delete_post( $post_id, true );
		return buckleup_rest_error( $attach_id->get_error_message(), 500 );
	}
	set_post_thumbnail( $post_id, $attach_id );
	update_post_meta( $post_id, 'bu_is_active', '1' );
	if ( '' !== $category ) {
		update_post_meta( $post_id, 'bu_category', $category );
	}

	return new WP_REST_Response( buckleup_graduate_shape( get_post( $post_id ) ), 201 );
}

/**
 * DELETE /graduates/{id} — remove a graduate + its attached photo.
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_graduates_delete( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	$id   = (int) $request['id'];
	$post = get_post( $id );
	if ( ! $post || 'graduate' !== $post->post_type ) {
		return buckleup_rest_error( __( 'Graduate not found', 'buckleup-app' ), 404 );
	}
	$thumb = get_post_thumbnail_id( $id );
	if ( $thumb ) {
		wp_delete_attachment( $thumb, true );
	}
	wp_delete_post( $id, true );
	return new WP_REST_Response( array( 'message' => __( 'Graduate deleted', 'buckleup-app' ) ), 200 );
}
