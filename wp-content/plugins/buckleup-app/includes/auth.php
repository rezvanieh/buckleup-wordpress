<?php
/**
 * Sign-in / auth flow (Task #34).
 *
 * - Role-based post-login redirect: student → /student, instructor →
 *   /instructor, admin (buckleup_admin or WP administrator) → /admin.
 * - Registration: POST buckleup/v1/auth/register creates a WP user with the
 *   `buckleup_student` role + student profile meta (mirrors the source
 *   /auth/register creating a STUDENT) + a welcome email via wp_mail.
 * - wp-admin + admin-bar gating: students/instructors are kept out of wp-admin
 *   (they use the front-end consoles); administrators keep wp-admin for the blog.
 * - Front-end portal guards mirroring src/middleware.ts: logged-out hitting
 *   /student|/instructor|/admin → login with callbackUrl; wrong role → home.
 *
 * Social login (Google/Facebook) is DEFERRED.
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dashboard path for a user based on role.
 *
 * @param WP_User|int|null $user
 * @return string Absolute URL.
 */
function buckleup_dashboard_url( $user = null ) {
	$label = buckleup_app_user_role_label( $user );
	switch ( $label ) {
		case 'ADMIN':
			return home_url( '/admin/' );
		case 'INSTRUCTOR':
			return home_url( '/instructor/' );
		case 'STUDENT':
			return home_url( '/student/' );
		default:
			return home_url( '/' );
	}
}

/**
 * Role-based redirect target after a successful wp-login.
 *
 * @param string           $redirect_to Requested redirect.
 * @param string           $requested   Originally requested redirect.
 * @param WP_User|WP_Error $user
 * @return string
 */
function buckleup_login_redirect( $redirect_to, $requested, $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return $redirect_to;
	}
	// Honor an explicit, safe in-site callbackUrl if present.
	if ( $redirect_to && false === strpos( $redirect_to, '/wp-admin' ) ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$dest = wp_parse_url( $redirect_to, PHP_URL_HOST );
		if ( ! $dest || $dest === $home ) {
			return $redirect_to;
		}
	}
	// Administrators with no explicit destination keep going to wp-admin only if
	// they requested it; otherwise send WP admins to the custom /admin console.
	return buckleup_dashboard_url( $user );
}
add_filter( 'login_redirect', 'buckleup_login_redirect', 10, 3 );

/**
 * Did this wp-login request originate from our branded /login page? Checks the
 * HTTP referer against home_url('/login').
 *
 * @return bool
 */
function buckleup_login_from_branded_page() {
	$referer = wp_get_referer();
	if ( ! $referer ) {
		return false;
	}
	return false !== strpos( $referer, '/login' );
}

/**
 * Preserve a safe in-site callbackUrl from the failed request, if any.
 *
 * @return string Relative path beginning with '/', or ''.
 */
function buckleup_failed_login_callback() {
	// wp-login posts redirect_to; the branded form forwards callbackUrl as that.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
	$raw = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
	if ( ! $raw ) {
		return '';
	}
	$path = wp_parse_url( $raw, PHP_URL_PATH );
	return $path ? '/' . ltrim( $path, '/' ) : '';
}

/**
 * On a FAILED or empty-credentials login that came from the branded /login
 * page, bounce back to home_url('/login/?login=failed') (preserving any
 * callbackUrl) instead of dumping the user on the raw wp-login.php screen.
 * Mirrors the source's inline "Invalid email or password".
 */
function buckleup_login_failed_redirect() {
	if ( ! buckleup_login_from_branded_page() ) {
		return; // let core handle non-branded (e.g. wp-admin) logins.
	}
	$args = array( 'login' => 'failed' );
	$cb   = buckleup_failed_login_callback();
	if ( $cb && '/wp-login.php' !== $cb ) {
		$args['callbackUrl'] = $cb;
	}
	wp_safe_redirect( add_query_arg( $args, home_url( '/login/' ) ) );
	exit;
}
add_action( 'wp_login_failed', 'buckleup_login_failed_redirect' );

/**
 * Treat empty username/password as a login failure routed through the same
 * branded bounce (core would otherwise show its own "empty" error on
 * wp-login.php).
 *
 * @param WP_User|WP_Error $user
 * @param string           $username
 * @param string           $password
 * @return WP_User|WP_Error
 */
function buckleup_handle_empty_login( $user, $username, $password ) {
	if ( ( empty( $username ) || empty( $password ) ) && buckleup_login_from_branded_page() ) {
		buckleup_login_failed_redirect();
	}
	return $user;
}
add_filter( 'authenticate', 'buckleup_handle_empty_login', 30, 3 );

/**
 * Keep students/instructors out of wp-admin (no dashboard access); they use the
 * front-end consoles. Admins are unaffected. AJAX is always allowed.
 */
function buckleup_block_wp_admin() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	$uid = get_current_user_id();
	if ( ! $uid ) {
		return;
	}
	if ( buckleup_is_student( $uid ) || ( buckleup_is_instructor( $uid ) && ! buckleup_is_app_admin( $uid ) ) ) {
		wp_safe_redirect( buckleup_dashboard_url( $uid ) );
		exit;
	}
}
add_action( 'admin_init', 'buckleup_block_wp_admin' );

/**
 * Hide the admin bar for students/instructors on the front end.
 */
add_action( 'after_setup_theme', function () {
	$uid = get_current_user_id();
	if ( $uid && ( buckleup_is_student( $uid ) || ( buckleup_is_instructor( $uid ) && ! buckleup_is_app_admin( $uid ) ) ) ) {
		show_admin_bar( false );
	}
} );

/**
 * Front-end portal access guards (mirror src/middleware.ts).
 *
 * Runs on template_redirect against the request path. Logged-out users hitting
 * a console are sent to the login page with a callbackUrl; wrong-role users are
 * sent home.
 */
function buckleup_guard_portal_routes() {
	if ( is_admin() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	$map = array(
		'student'    => array( 'STUDENT', 'ADMIN' ),
		'instructor' => array( 'INSTRUCTOR', 'ADMIN' ),
		'admin'      => array( 'ADMIN' ),
	);

	$matched = null;
	foreach ( array_keys( $map ) as $base ) {
		if ( $path === $base || 0 === strpos( $path, $base . '/' ) ) {
			$matched = $base;
			break;
		}
	}
	if ( null === $matched ) {
		return;
	}

	// Never shadow the real wp-admin (handled separately).
	if ( 'admin' === $matched && ( is_admin() ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		$login = add_query_arg( 'callbackUrl', rawurlencode( '/' . $path ), home_url( '/login/' ) );
		wp_safe_redirect( $login );
		exit;
	}

	$role = buckleup_app_user_role_label();
	if ( ! in_array( $role, $map[ $matched ], true ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
add_action( 'template_redirect', 'buckleup_guard_portal_routes' );

/**
 * Redirect already-logged-in users away from the login/register pages to their
 * dashboard (mirrors the middleware's /auth handling).
 */
function buckleup_redirect_authed_from_auth_pages() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'login' === $path || 'register' === $path ) {
		wp_safe_redirect( buckleup_dashboard_url() );
		exit;
	}
}
add_action( 'template_redirect', 'buckleup_redirect_authed_from_auth_pages' );

/* -------------------------------------------------------------------------
 * Registration — buckleup/v1/auth/register (public).
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/auth/register', array(
		'methods'             => 'POST',
		'callback'            => 'buckleup_rest_register',
		'permission_callback' => '__return_true', // public; nonce-checked below.
	) );
} );

/**
 * POST /auth/register — create a student account (mirrors /api/auth/register).
 *
 * @param WP_REST_Request $request
 */
function buckleup_rest_register( WP_REST_Request $request ) {
	$check = buckleup_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$p        = (array) $request->get_json_params();
	$name     = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
	$email    = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
	$phone    = isset( $p['phone'] ) ? sanitize_text_field( $p['phone'] ) : '';
	$password = isset( $p['password'] ) ? (string) $p['password'] : '';

	if ( mb_strlen( $name ) < 2 ) {
		return buckleup_rest_error( __( 'Name must be at least 2 characters', 'buckleup-app' ), 400 );
	}
	if ( ! is_email( $email ) ) {
		return buckleup_rest_error( __( 'Invalid email address', 'buckleup-app' ), 400 );
	}
	if ( strlen( $password ) < 8 ) {
		return buckleup_rest_error( __( 'Password must be at least 8 characters', 'buckleup-app' ), 400 );
	}
	if ( email_exists( $email ) ) {
		return buckleup_rest_error( __( 'User with this email already exists', 'buckleup-app' ), 400 );
	}

	$username = buckleup_unique_username_from_email( $email );
	$user_id  = wp_insert_user( array(
		'user_login'   => $username,
		'user_email'   => $email,
		'user_pass'    => $password,
		'display_name' => $name,
		'first_name'   => $name,
		'role'         => 'buckleup_student',
	) );
	if ( is_wp_error( $user_id ) ) {
		return buckleup_rest_error( $user_id->get_error_message(), 400 );
	}

	// Student profile defaults (mirrors create: { status:'ACTIVE', preferredLang:'en' }).
	if ( '' !== $phone ) {
		update_user_meta( $user_id, 'bu_phone', $phone );
	}
	update_user_meta( $user_id, 'bu_status', 'ACTIVE' );
	update_user_meta( $user_id, 'bu_preferred_lang', 'en' );

	buckleup_send_welcome_email( $email, $name );

	return new WP_REST_Response( array(
		'message' => __( 'User created successfully', 'buckleup-app' ),
		'user'    => array(
			'id'    => $user_id,
			'email' => $email,
			'name'  => $name,
			'role'  => 'STUDENT',
		),
	), 201 );
}

/**
 * Derive a unique, valid WP username from an email local-part.
 *
 * @param string $email
 * @return string
 */
function buckleup_unique_username_from_email( $email ) {
	$base = sanitize_user( current( explode( '@', $email ) ), true );
	if ( '' === $base ) {
		$base = 'student';
	}
	$username = $base;
	$i        = 1;
	while ( username_exists( $username ) ) {
		$username = $base . $i;
		$i++;
	}
	return $username;
}

/**
 * Welcome email after registration.
 *
 * @param string $email
 * @param string $name
 */
function buckleup_send_welcome_email( $email, $name ) {
	$site    = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'business_name', 'BuckleUp Driving School' ) : 'BuckleUp Driving School';
	$subject = sprintf( /* translators: %s: site name. */ __( 'Welcome to %s', 'buckleup-app' ), $site );
	$body    = sprintf(
		/* translators: 1: user name, 2: site name, 3: login URL. */
		__( "Hi %1\$s,\n\nWelcome to %2\$s! Your account is ready. You can sign in any time to book lessons and track your progress:\n%3\$s\n\nSee you on the road!\n— %2\$s", 'buckleup-app' ),
		$name,
		$site,
		home_url( '/login/' )
	);
	wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
}
