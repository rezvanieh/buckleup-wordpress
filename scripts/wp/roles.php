<?php
/**
 * Idempotent role + capability setup, mirroring the Prisma Role enum
 * (ADMIN / INSTRUCTOR / STUDENT). Run via: wp eval-file /scripts/wp/roles.php
 *
 * ADMIN  -> WP 'administrator' (no new role needed)
 * STUDENT/INSTRUCTOR -> custom roles based on 'subscriber' + portal caps.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// --- Student role ---
remove_role( 'student' );
add_role( 'student', 'Student', array(
	'read'                 => true,
	'buckleup_portal'      => true, // gate for /student/*
	'buckleup_book_lesson' => true,
	'buckleup_leave_review'=> true,
) );

// --- Instructor role ---
remove_role( 'instructor' );
add_role( 'instructor', 'Instructor', array(
	'read'                    => true,
	'buckleup_portal'         => true, // gate for /instructor/*
	'buckleup_manage_schedule'=> true,
	'buckleup_view_students'  => true,
) );

// Give administrators every BuckleUp capability so admin tooling works.
$admin = get_role( 'administrator' );
if ( $admin ) {
	foreach ( array(
		'buckleup_portal',
		'buckleup_book_lesson',
		'buckleup_leave_review',
		'buckleup_manage_schedule',
		'buckleup_view_students',
		'buckleup_manage_buckleup', // master admin-panel cap
	) as $cap ) {
		$admin->add_cap( $cap );
	}
}

WP_CLI::success( 'Roles ensured: student, instructor (+ admin caps).' );
