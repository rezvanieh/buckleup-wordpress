<?php
/**
 * REST API — buckleup/v1/quiz/*.
 *
 * All routes are public (the engine must serve anonymous visitors), mirroring
 * the public /auth/register route in buckleup-app. Abuse control for anonymous
 * mutations leans on the proven contact-form triad (transient rate-limit +
 * honeypot + min-fill-time) rather than the REST nonce, which is meaningless for
 * uid 0 on page-cached HTML. The nonce is still verified-if-present so logged-in
 * mutations are CSRF-checked. Grading is always server-authoritative.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'buckleup/v1', '/quiz/start', array(
		'methods'             => 'POST',
		'callback'            => 'buckleup_quiz_rest_start',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'buckleup/v1', '/quiz/batch', array(
		'methods'             => 'POST',
		'callback'            => 'buckleup_quiz_rest_batch',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'buckleup/v1', '/quiz/submit', array(
		'methods'             => 'POST',
		'callback'            => 'buckleup_quiz_rest_submit',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'buckleup/v1', '/quiz/claim/(?P<token>[a-f0-9]{32})', array(
		'methods'             => 'POST',
		'callback'            => 'buckleup_quiz_rest_claim',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'buckleup/v1', '/quiz/status', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_quiz_rest_status',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'buckleup/v1', '/quiz/result/(?P<token>[a-f0-9]{32})', array(
		'methods'             => 'GET',
		'callback'            => 'buckleup_quiz_rest_result',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Verify the REST nonce IF the user is logged in (CSRF protection for authed
 * mutations). Anonymous requests skip this — uid-0 nonces in page-cached HTML
 * are not a meaningful gate; the rate-limit + honeypot + min-fill triad applies.
 *
 * @param WP_REST_Request $request
 * @return true|WP_Error
 */
function buckleup_quiz_check_nonce( WP_REST_Request $request ) {
	if ( ! is_user_logged_in() ) {
		return true;
	}
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return buckleup_quiz_rest_error( __( 'Your session expired. Please refresh and try again.', 'buckleup-quiz' ), 403 );
	}
	return true;
}

/**
 * Light per-IP transient rate-limit. Returns true if under the cap (and counts
 * this hit). Mirrors the contact-form bucket convention.
 *
 * @param string $action 'start' | 'submit'
 * @param int    $max
 * @param int    $window seconds
 * @return bool
 */
function buckleup_quiz_rate_ok( $action, $max, $window ) {
	$key   = 'buckleup_quiz_rl_' . $action . '_' . md5( buckleup_quiz_client_ip() );
	$count = (int) get_transient( $key );
	set_transient( $key, $count + 1, $window );
	return $count < $max;
}

/**
 * POST /quiz/start — begin a test. Body: { mode }.
 */
function buckleup_quiz_rest_start( WP_REST_Request $request ) {
	$check = buckleup_quiz_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}
	if ( ! buckleup_quiz_rate_ok( 'start', (int) apply_filters( 'buckleup_quiz_start_rate_max', 30 ), 10 * MINUTE_IN_SECONDS ) ) {
		return buckleup_quiz_rest_error( __( 'Too many tests started. Please wait a moment and try again.', 'buckleup-quiz' ), 429 );
	}

	if ( ! buckleup_quiz_can_start() ) {
		return buckleup_quiz_rest_error( __( 'You have used all your free practice attempts. Sign up to keep practising.', 'buckleup-quiz' ), 403 );
	}

	$params = (array) $request->get_json_params();
	$mode   = isset( $params['mode'] ) ? sanitize_key( (string) $params['mode'] ) : 'full';

	// Integrity gate: the briefing screen requires the user to accept the test
	// conditions (own work, no AI/help, the timing + format) before the exam can
	// start. The client only sends consent=true once the checkbox is ticked.
	if ( empty( $params['consent'] ) ) {
		return buckleup_quiz_rest_error( __( 'Please review and accept the test conditions to begin.', 'buckleup-quiz' ), 400 );
	}

	$test = buckleup_quiz_assemble_test( $mode );
	if ( is_wp_error( $test ) ) {
		return $test;
	}

	$token = buckleup_quiz_session_create( $test );
	$first = $test['batches'][0];

	return new WP_REST_Response( array(
		'sessionId'  => $token,
		'mode'       => $test['mode'],
		'total'      => count( $test['question_ids'] ),
		'timeLimit'  => buckleup_quiz_time_limit( $test['mode'] ), // seconds; 0 = untimed
		'categories' => $test['categories'],     // rail manifest: [{slug,index,label,short,total}]
		'batchCount' => count( $test['batches'] ),
		'batch'      => array(                    // ONLY the first category's questions
			'categoryIndex' => $first['categoryIndex'],
			'slug'          => $first['slug'],
			'position'      => 0,
			'questions'     => buckleup_quiz_serialize_questions( $first['qids'], $test['perms'] ),
		),
	), 200 );
}

/**
 * POST /quiz/batch — serve the NEXT category's questions (sequential; future
 * categories are never returned early). Body: { sessionId }.
 */
function buckleup_quiz_rest_batch( WP_REST_Request $request ) {
	$params  = (array) $request->get_json_params();
	$token   = isset( $params['sessionId'] ) ? sanitize_text_field( (string) $params['sessionId'] ) : '';
	$session = buckleup_quiz_session_get( $token );
	if ( ! $session ) {
		return buckleup_quiz_rest_error( __( 'Your test session expired. Please start a new test.', 'buckleup-quiz' ), 410 );
	}

	$served  = (int) $session['served'];
	$batches = (array) $session['batches'];
	if ( $served >= count( $batches ) ) {
		return new WP_REST_Response( array( 'done' => true ), 200 );
	}

	$batch             = $batches[ $served ];
	$session['served'] = $served + 1;
	buckleup_quiz_session_save( $token, $session );

	return new WP_REST_Response( array(
		'batch' => array(
			'categoryIndex' => $batch['categoryIndex'],
			'slug'          => $batch['slug'],
			'position'      => $served,
			'questions'     => buckleup_quiz_serialize_questions( $batch['qids'], $session['perms'] ),
		),
		'done'  => ( $served + 1 ) >= count( $batches ),
	), 200 );
}

/**
 * POST /quiz/submit — grade + record. Body: { sessionId, answers:{qid:idx},
 * email, website (honeypot), ts (form-render unix seconds) }.
 */
function buckleup_quiz_rest_submit( WP_REST_Request $request ) {
	$check = buckleup_quiz_check_nonce( $request );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	$params  = (array) $request->get_json_params();
	$token   = isset( $params['sessionId'] ) ? sanitize_text_field( (string) $params['sessionId'] ) : '';
	$session = buckleup_quiz_session_get( $token );
	if ( ! $session ) {
		return buckleup_quiz_rest_error( __( 'Your test session expired. Please start a new test.', 'buckleup-quiz' ), 410 );
	}

	// Honeypot: a hidden `website` field no human fills in.
	if ( ! empty( $params['website'] ) ) {
		buckleup_quiz_session_delete( $token );
		return buckleup_quiz_rest_error( __( 'Submission rejected.', 'buckleup-quiz' ), 400 );
	}
	// Min-fill-time: an implausibly fast submit is a bot. Uses the server-held
	// session start time (set at /quiz/start) — not a client value — so it can't
	// be spoofed.
	$started  = (int) $session['started_at'];
	$min_fill = buckleup_quiz_cfg( 'min_fill_seconds', 10 );
	if ( $started > 0 && ( time() - $started ) < $min_fill ) {
		buckleup_quiz_session_delete( $token );
		return buckleup_quiz_rest_error( __( 'That was too quick — please take the test before submitting.', 'buckleup-quiz' ), 400 );
	}

	// Resolve the email: logged-in users use their account email; anonymous must
	// supply a valid one (business rule).
	if ( is_user_logged_in() ) {
		$email = wp_get_current_user()->user_email;
	} else {
		$email = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return buckleup_quiz_rest_error( __( 'Please enter a valid email address to see your results.', 'buckleup-quiz' ), 400 );
		}
	}

	// Per-IP submit rate-limit (cheap flood guard on top of the attempt cap).
	if ( ! buckleup_quiz_rate_ok( 'submit', (int) apply_filters( 'buckleup_quiz_submit_rate_max', 20 ), 10 * MINUTE_IN_SECONDS ) ) {
		return buckleup_quiz_rest_error( __( 'Too many submissions. Please wait a moment and try again.', 'buckleup-quiz' ), 429 );
	}

	// Attempt cap (anon only; logged-in unlimited).
	if ( ! buckleup_quiz_can_submit( $email ) ) {
		buckleup_quiz_session_delete( $token );
		return buckleup_quiz_rest_error( __( 'You have used all your free practice attempts with this email. Sign up to keep practising.', 'buckleup-quiz' ), 403 );
	}

	// Normalise answers to { (int)qid => (int)displayIndex }.
	$raw_answers = isset( $params['answers'] ) && is_array( $params['answers'] ) ? $params['answers'] : array();
	$answers     = array();
	foreach ( $raw_answers as $qid => $idx ) {
		$answers[ (int) $qid ] = (int) $idx;
	}

	// Optional display name for the certificate / email greeting.
	$name = isset( $params['name'] ) ? buckleup_quiz_clean_name( (string) $params['name'] ) : '';
	if ( '' === $name && is_user_logged_in() ) {
		$name = buckleup_quiz_clean_name( wp_get_current_user()->display_name );
	}

	$result         = buckleup_quiz_grade( $session, $answers );
	$result['name'] = $name;
	$categories     = isset( $session['categories'] ) ? $session['categories'] : array();
	$record         = buckleup_quiz_record_attempt( $result, $email, $name );

	buckleup_quiz_session_delete( $token ); // single use

	// Email the report (non-fatal if it fails — the web result still shows). The
	// token powers the certificate CTA inside the email.
	$result['result_token'] = $record['result_token'];
	buckleup_quiz_send_result_email( $email, $result );

	$cert_url = ( $result['passed'] && function_exists( 'buckleup_quiz_certificate_url' ) )
		? buckleup_quiz_certificate_url( $record['result_token'] )
		: '';

	return new WP_REST_Response( array(
		'resultToken'    => $record['result_token'],
		'mode'           => $result['mode'],
		'name'           => $name,
		'score'          => $result['score'],
		'total'          => $result['total'],
		'pct'            => $result['pct'],
		'passed'         => $result['passed'],
		'breakdown'      => $result['breakdown'],
		'categories'     => $categories, // {slug,index,label,short,total} for result colors/labels
		'review'         => $result['review'],
		'attempts'       => buckleup_quiz_attempts_status(),
		'certificateUrl' => $cert_url,
	), 200 );
}

/**
 * POST /quiz/claim/{token} — attach a display name to an existing attempt (the
 * post-results "claim your certificate" step). Only succeeds while the name is
 * still empty. Body: { name }.
 */
function buckleup_quiz_rest_claim( WP_REST_Request $request ) {
	global $wpdb;
	if ( ! buckleup_quiz_rate_ok( 'claim', (int) apply_filters( 'buckleup_quiz_claim_rate_max', 20 ), 10 * MINUTE_IN_SECONDS ) ) {
		return buckleup_quiz_rest_error( __( 'Too many requests. Please wait a moment.', 'buckleup-quiz' ), 429 );
	}
	$token  = (string) $request['token'];
	$params = (array) $request->get_json_params();
	$name   = isset( $params['name'] ) ? buckleup_quiz_clean_name( (string) $params['name'] ) : '';
	if ( '' === $name ) {
		return buckleup_quiz_rest_error( __( 'Please enter a name for your certificate.', 'buckleup-quiz' ), 400 );
	}

	$table = buckleup_quiz_table( 'quiz_attempts' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, passed FROM {$table} WHERE result_token = %s", $token ), ARRAY_A );
	if ( ! $row ) {
		return buckleup_quiz_rest_error( __( 'Result not found.', 'buckleup-quiz' ), 404 );
	}
	if ( ! empty( $row['name'] ) ) {
		return buckleup_quiz_rest_error( __( 'A name is already on this result.', 'buckleup-quiz' ), 409 );
	}
	$wpdb->update( $table, array( 'name' => $name ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );

	return new WP_REST_Response( array(
		'name'           => $name,
		'certificateUrl' => ( ! empty( $row['passed'] ) && function_exists( 'buckleup_quiz_certificate_url' ) ) ? buckleup_quiz_certificate_url( $token ) : '',
	), 200 );
}

/**
 * GET /quiz/status — remaining attempts + bank availability for the UI.
 */
function buckleup_quiz_rest_status( WP_REST_Request $request ) {
	$mode = sanitize_key( (string) $request->get_param( 'mode' ) );
	return new WP_REST_Response( array(
		'attempts'  => buckleup_quiz_attempts_status(),
		'loggedIn'  => is_user_logged_in(),
	), 200 );
}

/**
 * GET /quiz/result/{token} — fetch a stored result (bookmarkable / return view).
 */
function buckleup_quiz_rest_result( WP_REST_Request $request ) {
	$token  = (string) $request['token'];
	$result = buckleup_quiz_get_result_by_token( $token );
	if ( ! $result ) {
		return buckleup_quiz_rest_error( __( 'Result not found.', 'buckleup-quiz' ), 404 );
	}
	return new WP_REST_Response( $result, 200 );
}
