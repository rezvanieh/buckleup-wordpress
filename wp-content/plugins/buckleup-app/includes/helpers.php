<?php
/**
 * Shared lookups + profile-meta helpers.
 *
 * A "student" / "instructor" is a WP user with the matching role; profile data
 * lives in user meta (keys below). There is no separate profile-row id — the WP
 * user ID IS the student_id / instructor_id used throughout the tables.
 *
 * User-meta keys (documented in docs/CONSOLES-MODEL.md):
 *   Student:    bu_license_type, bu_status, bu_emergency_contact,
 *               bu_emergency_phone, bu_student_notes, bu_preferred_lang
 *   Instructor: bu_bio, bu_certifications (multi), bu_languages (multi),
 *               bu_hourly_rate, bu_is_active, bu_rating
 *   Shared:     bu_phone, bu_theme (system|light|dark), bu_avatar_id
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is this user a student / instructor / admin (by role or admin cap)?
 *
 * @param int|WP_User|null $user
 * @return bool
 */
function buckleup_is_student( $user = null ) {
	return buckleup_user_has_role( $user, 'buckleup_student' );
}
function buckleup_is_instructor( $user = null ) {
	return buckleup_user_has_role( $user, 'buckleup_instructor' );
}
function buckleup_is_app_admin( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', $user ) ) : wp_get_current_user();
	return $user && $user->exists() && ( in_array( 'buckleup_admin', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) );
}

/**
 * Role-membership check.
 *
 * @param int|WP_User|null $user
 * @param string           $role
 * @return bool
 */
function buckleup_user_has_role( $user, $role ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', $user ) ) : wp_get_current_user();
	return $user && $user->exists() && in_array( $role, (array) $user->roles, true );
}

/**
 * Read a profile meta value with a default.
 *
 * @param int    $user_id
 * @param string $key      Meta key (already prefixed bu_).
 * @param mixed  $default
 * @return mixed
 */
function buckleup_profile_get( $user_id, $key, $default = '' ) {
	$value = get_user_meta( $user_id, $key, true );
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Read a multi-value profile meta (certifications, languages) as a clean array.
 *
 * @param int    $user_id
 * @param string $key
 * @return string[]
 */
function buckleup_profile_get_list( $user_id, $key ) {
	$values = get_user_meta( $user_id, $key, false );
	return array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) );
}

/**
 * Replace a multi-value profile meta with the given list.
 *
 * @param int      $user_id
 * @param string   $key
 * @param string[] $items
 */
function buckleup_profile_set_list( $user_id, $key, array $items ) {
	delete_user_meta( $user_id, $key );
	foreach ( $items as $item ) {
		$item = sanitize_text_field( $item );
		if ( '' !== $item ) {
			add_user_meta( $user_id, $key, $item, false );
		}
	}
}

/**
 * Public-facing profile summary for a user (name/email/phone/avatar/role),
 * mirroring the `user` object shape the source returns.
 *
 * @param int $user_id
 * @return array<string,mixed>
 */
function buckleup_user_public( $user_id ) {
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return array();
	}
	$avatar_id = (int) buckleup_profile_get( $user_id, 'bu_avatar_id', 0 );
	return array(
		'id'     => $user->ID,
		'name'   => $user->display_name,
		'email'  => $user->user_email,
		'phone'  => buckleup_profile_get( $user_id, 'bu_phone', '' ),
		'avatar' => $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '',
		'role'   => buckleup_app_user_role_label( $user ),
	);
}

/**
 * Find the instructor user ID for a given service-less context — i.e. the
 * currently-logged-in instructor. Returns 0 if the current user isn't one.
 *
 * @return int
 */
function buckleup_current_instructor_id() {
	$uid = get_current_user_id();
	return buckleup_is_instructor( $uid ) || buckleup_is_app_admin( $uid ) ? $uid : 0;
}

/**
 * Current student user ID, or 0.
 *
 * @return int
 */
function buckleup_current_student_id() {
	$uid = get_current_user_id();
	return buckleup_is_student( $uid ) ? $uid : 0;
}
