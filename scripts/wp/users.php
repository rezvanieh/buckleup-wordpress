<?php
/**
 * Idempotent demo users, mirroring prisma/seed.ts.
 * Run via: wp eval-file /scripts/wp/users.php
 *
 * NOTE: passwords are NOT hard-coded — see buckleup_seed_password(). Source hashes were bcrypt in Postgres
 * and cannot be reused directly — WP hashes them with phpass on creation here.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Password for a seeded demo account: the env var when set, else a generated one
 * that is printed once. Never a literal in the repo — these files are public, and
 * hard-coded demo passwords were still live on production until 2026-08-14.
 */
if ( ! function_exists( 'buckleup_seed_password' ) ) {
	function buckleup_seed_password( $env_var ) {
		$pw = getenv( $env_var );
		if ( is_string( $pw ) && '' !== trim( $pw ) ) {
			WP_CLI::log( "  password for {$env_var}: (from environment)" );
			return trim( $pw );
		}
		$pw = wp_generate_password( 20, true, false );
		WP_CLI::log( "  GENERATED password ({$env_var}): {$pw}" );
		return $pw;
	}
}

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

// Instructor accounts. The 'sarah' account (Sarah Mitchell, from the original
// seed.ts) was removed on 2026-08-14 — she is no longer an instructor, and seeding
// a login for a former staff member is worse than pointless.
buckleup_upsert_user( 'farhad', 'farhad@buckleupdriving.ca', buckleup_seed_password( 'BU_SEED_FARHAD_PW' ), 'instructor', 'Farhad Sanaeifar', array(
	'bu_phone'          => '+1 (604) 441-3677',
	'bu_bio'            => 'Patient, multilingual instructor focused on newcomer drivers.',
	'bu_certifications' => array( 'ICBC Approved', 'Winter Driving Certified' ),
	'bu_languages'      => array( 'English', 'Farsi' ),
	'bu_hourly_rate'    => '50.00',
	'bu_is_active'      => 1,
) );

// Demo student — from seed.ts
buckleup_upsert_user( 'demo', 'demo@buckleupdriving.ca', buckleup_seed_password( 'BU_SEED_DEMO_PW' ), 'student', 'Demo Student', array(
	'bu_phone'            => '(604) 555-0200',
	'bu_license_type'     => '7L',
	'bu_status'           => 'ACTIVE',
	'bu_emergency_contact'=> 'Parent Name',
	'bu_emergency_phone'  => '(604) 555-0200',
	'bu_preferred_lang'   => 'en',
) );

WP_CLI::success( 'Demo users ensured (2 instructors + 1 student).' );
