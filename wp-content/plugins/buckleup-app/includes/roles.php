<?php
/**
 * Roles and capabilities for the three consoles.
 *
 * Roles (mirroring the Prisma Role enum STUDENT/INSTRUCTOR/ADMIN):
 *   - buckleup_student    → front-end Student console only.
 *   - buckleup_instructor → front-end Instructor console only.
 *   - buckleup_admin      → front-end Admin console (+ the existing WP admin
 *                           administrators keep for the blog).
 *
 * Access control is capability-based, and every REST endpoint additionally
 * enforces ROW OWNERSHIP (see rest-helpers.php) — a role grants entry to a
 * console; ownership decides which rows within it a user may touch.
 *
 * Custom capabilities:
 *   buckleup_access_student_console
 *   buckleup_access_instructor_console
 *   buckleup_access_admin_console
 *   buckleup_manage_app   → full app management (admin console + WP admins).
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Bump when buckleup_app_role_caps() changes so caps re-apply to existing roles
// on the next request (see the init hook below). v2: admin gains blog caps.
if ( ! defined( 'BUCKLEUP_APP_ROLES_VERSION' ) ) {
	define( 'BUCKLEUP_APP_ROLES_VERSION', '2' );
}

/**
 * Capability sets per role.
 *
 * @return array<string,array<string,bool>>
 */
function buckleup_app_role_caps() {
	$student = array(
		'read'                            => true,
		'buckleup_access_student_console' => true,
	);

	$instructor = array(
		'read'                               => true,
		'buckleup_access_instructor_console' => true,
	);

	$admin = array(
		'read'                          => true,
		'buckleup_access_admin_console' => true,
		'buckleup_manage_app'           => true,
		// Blog management via wp-admin (the console "Blogs" link → edit.php).
		// Post + media + category + comment caps only — NOT page caps, so the
		// admin can't edit the marketing/console pages.
		'edit_posts'                    => true,
		'edit_others_posts'             => true,
		'edit_published_posts'          => true,
		'edit_private_posts'            => true,
		'publish_posts'                 => true,
		'delete_posts'                  => true,
		'delete_others_posts'           => true,
		'delete_published_posts'        => true,
		'delete_private_posts'          => true,
		'read_private_posts'            => true,
		'upload_files'                  => true,
		'manage_categories'             => true,
		'moderate_comments'             => true,
	);

	return array(
		'buckleup_student'    => $student,
		'buckleup_instructor' => $instructor,
		'buckleup_admin'      => $admin,
	);
}

/**
 * Register the three roles + grant the management caps to administrators.
 * Idempotent: add_role() is a no-op if the role exists, so we also re-apply the
 * caps each call to pick up changes on upgrade.
 */
function buckleup_app_register_roles() {
	$display = array(
		'buckleup_student'    => __( 'Student', 'buckleup-app' ),
		'buckleup_instructor' => __( 'Instructor', 'buckleup-app' ),
		'buckleup_admin'      => __( 'BuckleUp Admin', 'buckleup-app' ),
	);

	foreach ( buckleup_app_role_caps() as $role => $caps ) {
		$existing = get_role( $role );
		if ( null === $existing ) {
			add_role( $role, $display[ $role ], $caps );
		} else {
			// Re-apply caps (covers upgrades that add/remove a cap).
			foreach ( $caps as $cap => $grant ) {
				$existing->add_cap( $cap, $grant );
			}
		}
	}

	// WordPress administrators manage the whole app + every console.
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'buckleup_manage_app' );
		$administrator->add_cap( 'buckleup_access_admin_console' );
		$administrator->add_cap( 'buckleup_access_instructor_console' );
		$administrator->add_cap( 'buckleup_access_student_console' );
	}
}
// Register on init too (cheap; ensures roles exist even if the activation hook
// was missed, e.g. plugin dropped in via bind-mount without re-activation).
add_action( 'init', function () {
	if ( null === get_role( 'buckleup_student' )
		|| get_option( 'buckleup_app_roles_version' ) !== BUCKLEUP_APP_ROLES_VERSION ) {
		buckleup_app_register_roles();
		update_option( 'buckleup_app_roles_version', BUCKLEUP_APP_ROLES_VERSION );
	}
} );

/**
 * Remove the roles (used by uninstall.php, NOT on deactivate).
 */
function buckleup_app_remove_roles() {
	foreach ( array_keys( buckleup_app_role_caps() ) as $role ) {
		remove_role( $role );
	}
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->remove_cap( 'buckleup_manage_app' );
		$administrator->remove_cap( 'buckleup_access_admin_console' );
		$administrator->remove_cap( 'buckleup_access_instructor_console' );
		$administrator->remove_cap( 'buckleup_access_student_console' );
	}
}

/**
 * Map our role to the source's uppercase Role label (for REST responses that
 * mirror the Next.js shapes, which expose role as STUDENT/INSTRUCTOR/ADMIN).
 *
 * @param WP_User|int|null $user User or ID; defaults to current user.
 * @return string STUDENT|INSTRUCTOR|ADMIN|'' .
 */
function buckleup_app_user_role_label( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_user_by( 'id', $user ) ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return '';
	}
	if ( in_array( 'buckleup_admin', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) ) {
		return 'ADMIN';
	}
	if ( in_array( 'buckleup_instructor', (array) $user->roles, true ) ) {
		return 'INSTRUCTOR';
	}
	if ( in_array( 'buckleup_student', (array) $user->roles, true ) ) {
		return 'STUDENT';
	}
	return '';
}
