<?php
/**
 * Seeds the consoles' auth fixtures: the 3 demo users (one per buckleup_* role,
 * with known creds for the live demo + QA) and the 5 console/auth pages.
 *
 * Idempotent: users upsert by email (creds + role + profile meta re-applied each
 * run); pages upsert by slug (post_content = the theme pattern reference, which
 * WP/the block theme renders — patterns built under buckleup/page-{slug}).
 *
 * Requires buckleup-app active (for the roles); if it isn't, users are still
 * created but the role won't exist yet — re-run after activation. Keep the WP
 * `admin` administrator for wp-admin/blog; these are FRONT-END console roles.
 *
 * Run via: wp eval-file /scripts/wp/seed-console-users-pages.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

/**
 * Upsert a demo user by email: create if absent, else update; (re)set the
 * password + role each run so the known creds always work. Returns user ID.
 */
function bu_upsert_user( $login, $email, $pass, $role, $display, $meta = array() ) {
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		$id = $user->ID;
		wp_update_user( array(
			'ID'           => $id,
			'user_pass'    => $pass,      // re-apply known demo password
			'display_name' => $display,
			'role'         => $role,      // single console role
		) );
	} else {
		$id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'display_name' => $display,
			'role'         => $role,
		) );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "  user {$email}: " . $id->get_error_message() );
			return 0;
		}
	}
	foreach ( $meta as $k => $v ) {
		update_user_meta( $id, $k, $v );
	}
	WP_CLI::log( "  user ok: {$email} ({$role}) -> #{$id}" );
	return (int) $id;
}

/* ---------------------------------------------------------------------------
 * 3 demo users — known creds for the live demo + QA. The WP `admin`
 * administrator stays for wp-admin/blog; these are the front-end console roles.
 * ------------------------------------------------------------------------- */
bu_upsert_user(
	'sam.student', 'student@buckleup.test', 'Student123!', 'buckleup_student', 'Sam Student',
	array(
		'bu_status'           => 'ACTIVE',
		'bu_preferred_lang'   => 'en',
		'bu_phone'            => '(604) 555-0142',
		'bu_license_type'     => '7L',
		'bu_emergency_contact'=> 'Jordan Student',
		'bu_emergency_phone'  => '(604) 555-0188',
	)
);

bu_upsert_user(
	'farhad.instructor', 'instructor@buckleup.test', 'Instruct123!', 'buckleup_instructor', 'Farhad Sanaeifar',
	array(
		'bu_is_active'   => 1,
		'bu_bio'         => 'Farhad brings a unique blend of technical expertise and cultural understanding to his teaching. Fluent in multiple languages, he specializes in helping new immigrants adapt to Canadian driving conditions.',
		'bu_phone'       => '+1 (604) 441-3677',
		'bu_hourly_rate' => 60,
	)
);
// List meta (multi-row) — instructor languages + certifications.
$farhad = get_user_by( 'email', 'instructor@buckleup.test' );
if ( $farhad ) {
	delete_user_meta( $farhad->ID, 'bu_languages' );
	foreach ( array( 'English', 'Farsi' ) as $lang ) { add_user_meta( $farhad->ID, 'bu_languages', $lang ); }
	delete_user_meta( $farhad->ID, 'bu_certifications' );
	foreach ( array( 'ICBC Approved', 'Winter Driving Certified' ) as $c ) { add_user_meta( $farhad->ID, 'bu_certifications', $c ); }
}

bu_upsert_user(
	'buckleup.appadmin', 'appadmin@buckleup.test', 'Admin12345!', 'buckleup_admin', 'BuckleUp Admin',
	array()
);

/* ---------------------------------------------------------------------------
 * 5 console/auth pages — slug -> theme pattern. The block theme renders the
 * pattern (buckleup/page-{slug}); a fresh reset builds the pattern cache once
 * with all patterns present, so /login etc. resolve 200.
 * ------------------------------------------------------------------------- */
$console_pages = array(
	'login'      => array( 'Sign In',             'buckleup/page-login' ),
	'register'   => array( 'Create Account',      'buckleup/page-register' ),
	'student'    => array( 'Student Dashboard',   'buckleup/page-student' ),
	'instructor' => array( 'Instructor Dashboard','buckleup/page-instructor' ),
	'admin'      => array( 'Admin Console',        'buckleup/page-admin' ),
);
$parent_ids = array();
foreach ( $console_pages as $slug => $info ) {
	list( $title, $pattern ) = $info;
	$existing = bu_find_post( 'page', $slug );
	$data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => '<!-- wp:pattern {"slug":"' . $pattern . '"} /-->',
	);
	if ( $existing ) { $data['ID'] = $existing; }
	$pid = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $pid ) ) { WP_CLI::warning( "  page {$slug}: " . $pid->get_error_message() ); continue; }
	$parent_ids[ $slug ] = (int) $pid;
	WP_CLI::log( "  page ok: /{$slug}/ (#{$pid})" );
}

/* ---------------------------------------------------------------------------
 * Console SUB-PAGES — child pages under the role dashboards, so they resolve at
 * /student/reviews/, /instructor/schedule/, /admin/graduates/, etc. Each child's
 * content is the role-specific pattern ref console-{role}-{leaf}; the theme's
 * template_include filter renders any page under these parents with the console
 * template (so NO page_template meta). Pages render empty until the matching
 * pattern lands — that's expected; they auto-fill. Idempotent (upsert by path).
 * ------------------------------------------------------------------------- */
$child_map = array(
	'student'    => array( 'reviews', 'profile', 'settings' ),
	'instructor' => array( 'schedule', 'availability', 'students', 'profile', 'settings' ),
	'admin'      => array( 'students', 'graduates', 'reviews', 'settings' ),
);
$child_n = 0;
foreach ( $child_map as $role => $leaves ) {
	$parent_id = isset( $parent_ids[ $role ] ) ? $parent_ids[ $role ] : bu_find_post( 'page', $role );
	if ( ! $parent_id ) { WP_CLI::warning( "  parent /{$role}/ missing — skipping its children." ); continue; }
	foreach ( $leaves as $leaf ) {
		// Resolve existing by full hierarchical path (child pages need the full path).
		$existing = get_page_by_path( "{$role}/{$leaf}", OBJECT, 'page' );
		$pattern  = "buckleup/console-{$role}-{$leaf}";
		$data = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_parent'  => $parent_id,
			'post_name'    => $leaf,
			'post_title'   => ucwords( str_replace( '-', ' ', $leaf ) ),
			'post_content' => '<!-- wp:pattern {"slug":"' . $pattern . '"} /-->',
		);
		if ( $existing ) { $data['ID'] = $existing->ID; }
		$cid = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $cid ) ) { WP_CLI::warning( "  child /{$role}/{$leaf}/: " . $cid->get_error_message() ); continue; }
		WP_CLI::log( "  child ok: /{$role}/{$leaf}/ (#{$cid})" );
		$child_n++;
	}
}

// Flush rewrites + clear the version-keyed pattern cache so the new pages +
// patterns resolve on a running instance (a fresh reset builds it once anyway).
flush_rewrite_rules( false );
$theme = wp_get_theme();
if ( method_exists( $theme, 'delete_pattern_cache' ) ) { $theme->delete_pattern_cache(); }

WP_CLI::success( "Console auth seeded: 3 demo users + 5 console pages + {$child_n} sub-pages." );
