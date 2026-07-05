<?php
/**
 * Attempt limiting.
 *
 * Policy (locked with the business):
 *   - Logged-in users: UNLIMITED practice attempts.
 *   - Anonymous users: capped at `max_attempts` (default 15, admin-editable in
 *     Settings → Practice Test), tracked by three
 *     soft signals — email hash, IP hash, and a signed first-party cookie. The
 *     limit is enforced if ANY signal is at the cap. Acknowledged soft (emails,
 *     IPs and cookies can all be evaded); it nudges sign-up, it isn't auth.
 *
 * An "attempt" is a COMPLETED (graded) submission — a started-but-abandoned test
 * never counts. Counts come from bu_quiz_attempts (email_hash / ip_hash) so we
 * never scan raw PII.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The anonymous attempt cap.
 *
 * @return int
 */
function buckleup_quiz_max_attempts() {
	return (int) apply_filters( 'buckleup_quiz_max_attempts', buckleup_quiz_cfg( 'max_attempts', 15 ) );
}

/**
 * Best-effort client IP (mirrors buckleup-core's contact-form helper).
 *
 * @return string
 */
function buckleup_quiz_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return $ip ? sanitize_text_field( $ip ) : 'unknown';
}

/**
 * md5 of a normalised email (matches the rate-limit bucket convention).
 *
 * @param string $email
 * @return string
 */
function buckleup_quiz_email_hash( $email ) {
	return md5( strtolower( trim( (string) $email ) ) );
}

/**
 * md5 of the client IP.
 *
 * @return string
 */
function buckleup_quiz_ip_hash() {
	return md5( buckleup_quiz_client_ip() );
}

/**
 * Count completed attempts matching a hashed identity column.
 *
 * @param string $column 'email_hash' or 'ip_hash'.
 * @param string $hash
 * @return int
 */
function buckleup_quiz_count_by( $column, $hash ) {
	global $wpdb;
	if ( ! in_array( $column, array( 'email_hash', 'ip_hash' ), true ) ) {
		return 0;
	}
	$table = buckleup_quiz_table( 'quiz_attempts' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is whitelisted above.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s", $hash ) );
}

/** Signed first-party cookie ------------------------------------------------ */

define( 'BUCKLEUP_QUIZ_COOKIE', 'bu_quiz_n' );

/**
 * Read the (verified) attempt count stored in the signed cookie. 0 if absent or
 * tampered.
 *
 * @return int
 */
function buckleup_quiz_cookie_count() {
	if ( empty( $_COOKIE[ BUCKLEUP_QUIZ_COOKIE ] ) ) {
		return 0;
	}
	$raw = sanitize_text_field( wp_unslash( $_COOKIE[ BUCKLEUP_QUIZ_COOKIE ] ) );
	if ( ! preg_match( '/^(\d+)\.([a-f0-9]{64})$/', $raw, $m ) ) {
		return 0;
	}
	$count = (int) $m[1];
	$sig   = hash_hmac( 'sha256', (string) $count, wp_salt( 'auth' ) );
	return hash_equals( $sig, $m[2] ) ? $count : 0;
}

/**
 * Write the signed attempt-count cookie. Best-effort (headers may already be
 * sent in some contexts); failure is non-fatal since email/IP are the primary
 * signals.
 *
 * @param int $count
 * @return void
 */
function buckleup_quiz_cookie_set( $count ) {
	$count = max( 0, (int) $count );
	$sig   = hash_hmac( 'sha256', (string) $count, wp_salt( 'auth' ) );
	$value = $count . '.' . $sig;
	if ( ! headers_sent() ) {
		setcookie(
			BUCKLEUP_QUIZ_COOKIE,
			$value,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
	$_COOKIE[ BUCKLEUP_QUIZ_COOKIE ] = $value; // reflect immediately within this request
}

/** Gates -------------------------------------------------------------------- */

/**
 * May the current visitor START a test? Logged-in: always. Anonymous: blocked
 * when the IP or cookie signal is already at the cap (email is unknown yet).
 *
 * @return bool
 */
function buckleup_quiz_can_start() {
	if ( is_user_logged_in() ) {
		return true;
	}
	$max = buckleup_quiz_max_attempts();
	return buckleup_quiz_count_by( 'ip_hash', buckleup_quiz_ip_hash() ) < $max
		&& buckleup_quiz_cookie_count() < $max;
}

/**
 * May the current visitor SUBMIT (record) an attempt for this email? Logged-in:
 * always. Anonymous: blocked when ANY signal (email/IP/cookie) is at the cap.
 *
 * @param string $email
 * @return bool
 */
function buckleup_quiz_can_submit( $email ) {
	if ( is_user_logged_in() ) {
		return true;
	}
	$max = buckleup_quiz_max_attempts();
	return buckleup_quiz_count_by( 'email_hash', buckleup_quiz_email_hash( $email ) ) < $max
		&& buckleup_quiz_count_by( 'ip_hash', buckleup_quiz_ip_hash() ) < $max
		&& buckleup_quiz_cookie_count() < $max;
}

/**
 * Remaining attempts for the /quiz/status endpoint (best estimate before an
 * email is known). Logged-in → unlimited.
 *
 * @return array{unlimited:bool,max:int,used:int,remaining:int}
 */
function buckleup_quiz_attempts_status() {
	if ( is_user_logged_in() ) {
		return array( 'unlimited' => true, 'max' => 0, 'used' => 0, 'remaining' => -1 );
	}
	$max  = buckleup_quiz_max_attempts();
	$used = max( buckleup_quiz_count_by( 'ip_hash', buckleup_quiz_ip_hash() ), buckleup_quiz_cookie_count() );
	return array(
		'unlimited' => false,
		'max'       => $max,
		'used'      => min( $used, $max ),
		'remaining' => max( 0, $max - $used ),
	);
}
