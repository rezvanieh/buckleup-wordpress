<?php
/**
 * Idempotent demo users, mirroring prisma/seed.ts.
 * Run via: wp eval-file /scripts/wp/users.php
 *
 * NOTE: source passwords (e.g. 'password123', 'demo123') are bcrypt in Postgres
 * and cannot be reused directly — WP hashes them with phpass on creation here.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function buckleup_upsert_user( $login, $email, $pass, $role, $display, $meta = array() ) {
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		$id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'display_name' => $display,
			'role'         => $role,
		) );
		if ( is_wp_error( $id ) ) { WP_CLI::warning( "skip {$email}: " . $id->get_error_message() ); return; }
	} else {
		$id = $user->ID;
		$u = new WP_User( $id ); $u->set_role( $role );
	}
	foreach ( $meta as $k => $v ) { update_user_meta( $id, $k, $v ); }
	WP_CLI::log( "  user ok: {$email} ({$role})" );
}

// Instructors (Sarah, Farhad) — from seed.ts
buckleup_upsert_user( 'sarah', 'sarah@buckleupdriving.ca', 'password123', 'instructor', 'Sarah Mitchell', array(
	'bu_phone'          => '(604) 555-0101',
	'bu_bio'            => '15+ years teaching new drivers in the Lower Mainland.',
	'bu_certifications' => array( 'ICBC Approved', 'Class 4 Commercial', 'Defensive Driving Specialist' ),
	'bu_languages'      => array( 'English', 'French' ),
	'bu_hourly_rate'    => '50.00',
	'bu_is_active'      => 1,
) );

buckleup_upsert_user( 'farhad', 'farhad@buckleupdriving.ca', 'password123', 'instructor', 'Farhad Sanaeifar', array(
	'bu_phone'          => '+1 (604) 441-3677',
	'bu_bio'            => 'Patient, multilingual instructor focused on newcomer drivers.',
	'bu_certifications' => array( 'ICBC Approved', 'Winter Driving Certified' ),
	'bu_languages'      => array( 'English', 'Farsi' ),
	'bu_hourly_rate'    => '50.00',
	'bu_is_active'      => 1,
) );

// Demo student — from seed.ts
buckleup_upsert_user( 'demo', 'demo@buckleupdriving.ca', 'demo123', 'student', 'Demo Student', array(
	'bu_phone'            => '(604) 555-0200',
	'bu_license_type'     => '7L',
	'bu_status'           => 'ACTIVE',
	'bu_emergency_contact'=> 'Parent Name',
	'bu_emergency_phone'  => '(604) 555-0200',
	'bu_preferred_lang'   => 'en',
) );

WP_CLI::success( 'Demo users ensured (2 instructors + 1 student).' );
