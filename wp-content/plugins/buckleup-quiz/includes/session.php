<?php
/**
 * Server-side test session.
 *
 * A started test is stored as a transient (Redis-backed object cache on prod)
 * holding the question ids + the canonical→display option permutations + the
 * identity that started it. The client only ever holds the opaque session token;
 * the authoritative answer mapping never leaves the server. Sessions auto-expire
 * (config `session_ttl`) and are deleted on submit (single use).
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Transient key for a session token.
 *
 * @param string $token
 * @return string
 */
function buckleup_quiz_session_key( $token ) {
	return 'buckleup_quiz_sess_' . $token;
}

/**
 * Create a session for an assembled test. Returns the session token. Stores the
 * full server-authoritative test (ids + perms + ordered category batches + the
 * rail manifest) plus a `served` counter — batches are revealed one at a time,
 * in order, so future categories never reach the client.
 *
 * @param array $test Output of buckleup_quiz_assemble_test().
 * @return string 32-char token.
 */
function buckleup_quiz_session_create( $test ) {
	$token = bin2hex( random_bytes( 16 ) );
	$data  = array(
		'user_id'      => get_current_user_id() ?: null,
		'mode'         => $test['mode'],
		'question_ids' => array_map( 'intval', $test['question_ids'] ),
		'perms'        => $test['perms'],
		'batches'      => $test['batches'],
		'categories'   => $test['categories'],
		'served'       => 1, // batch 0 is returned by /quiz/start
		'started_at'   => time(),
	);
	buckleup_quiz_session_save( $token, $data );
	return $token;
}

/**
 * Persist (create/update) a session's data under its token, preserving TTL.
 *
 * @param string $token
 * @param array  $data
 * @return void
 */
function buckleup_quiz_session_save( $token, $data ) {
	set_transient( buckleup_quiz_session_key( $token ), $data, buckleup_quiz_cfg( 'session_ttl', 2 * HOUR_IN_SECONDS ) );
}

/**
 * Fetch a session by token, or null if missing/expired.
 *
 * @param string $token
 * @return array|null
 */
function buckleup_quiz_session_get( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		return null;
	}
	$data = get_transient( buckleup_quiz_session_key( $token ) );
	return is_array( $data ) ? $data : null;
}

/**
 * Delete a session (called after a successful submit; single-use).
 *
 * @param string $token
 * @return void
 */
function buckleup_quiz_session_delete( $token ) {
	if ( is_string( $token ) && preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		delete_transient( buckleup_quiz_session_key( $token ) );
	}
}
