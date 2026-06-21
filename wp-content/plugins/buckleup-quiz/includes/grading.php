<?php
/**
 * Server-authoritative grading + attempt recording.
 *
 * The client submits only a display-position pick per question. We re-read the
 * canonical correct answer fresh from bu_questions and translate the pick back
 * through the session's permutation — the correct answer is never trusted from,
 * nor exposed to, the client until the graded result is returned.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetch full question rows for a set of ids, keyed by id.
 *
 * @param int[] $ids
 * @return array<int,array<string,mixed>>
 */
function buckleup_quiz_fetch_questions( $ids ) {
	global $wpdb;
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return array();
	}
	$table        = buckleup_quiz_table( 'questions' );
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are %d.
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, category, question, option_a, option_b, option_c, option_d, correct_index, explanation FROM {$table} WHERE id IN ($placeholders)", $ids ),
		ARRAY_A
	);
	$by_id = array();
	foreach ( (array) $rows as $r ) {
		$by_id[ (int) $r['id'] ] = $r;
	}
	return $by_id;
}

/**
 * Grade a submitted test.
 *
 * @param array               $session Session array (question_ids, perms, mode).
 * @param array<int|string,mixed> $answers  Map of qid => chosen DISPLAY index (0-3).
 * @return array{score:int,total:int,pct:int,passed:bool,mode:string,breakdown:array,review:array}
 */
function buckleup_quiz_grade( $session, $answers ) {
	$question_ids = array_map( 'intval', (array) $session['question_ids'] );
	$perms        = (array) $session['perms'];
	$mode         = isset( $session['mode'] ) ? (string) $session['mode'] : 'full';
	$by_id        = buckleup_quiz_fetch_questions( $question_ids );

	$score     = 0;
	$total     = 0;
	$breakdown = array();
	$review    = array();

	foreach ( $question_ids as $qid ) {
		if ( ! isset( $by_id[ $qid ], $perms[ $qid ] ) ) {
			continue; // question removed mid-session — drop from grading
		}
		$row       = $by_id[ $qid ];
		$perm      = array_map( 'intval', $perms[ $qid ] );
		$canonical = array( $row['option_a'], $row['option_b'], $row['option_c'], $row['option_d'] );
		$correct   = (int) $row['correct_index'];
		$category  = $row['category'];

		// Translate the client's display pick back to a canonical index.
		$picked_display   = isset( $answers[ $qid ] ) ? (int) $answers[ $qid ] : -1;
		$picked_canonical = ( $picked_display >= 0 && $picked_display <= 3 && isset( $perm[ $picked_display ] ) ) ? $perm[ $picked_display ] : null;
		$is_correct       = ( null !== $picked_canonical && $picked_canonical === $correct );

		++$total;
		if ( $is_correct ) {
			++$score;
		}

		if ( ! isset( $breakdown[ $category ] ) ) {
			$breakdown[ $category ] = array( 'correct' => 0, 'total' => 0 );
		}
		++$breakdown[ $category ]['total'];
		if ( $is_correct ) {
			++$breakdown[ $category ]['correct'];
		}

		$review[] = array(
			'qid'              => $qid,
			'category'         => $category,
			'question'         => $row['question'],
			'options'          => $canonical,
			'correct_index'    => $correct,
			'picked_index'     => $picked_canonical, // canonical, or null if unanswered
			'is_correct'       => $is_correct,
			'explanation'      => (string) $row['explanation'],
		);
	}

	$pct    = $total > 0 ? (int) round( $score / $total * 100 ) : 0;
	$passed = $pct >= buckleup_quiz_cfg( 'pass_pct', 80 );

	return array(
		'score'     => $score,
		'total'     => $total,
		'pct'       => $pct,
		'passed'    => $passed,
		'mode'      => $mode,
		'breakdown' => $breakdown,
		'review'    => $review,
	);
}

/**
 * Persist a graded attempt; returns the result token + attempt id.
 *
 * Stores a compact answers JSON ([{qid,picked,correct}]) so a result view can be
 * rebuilt later against the live bank, plus the category breakdown.
 *
 * @param array  $result Output of buckleup_quiz_grade().
 * @param string $email
 * @param string $name   Optional display name for the certificate/email.
 * @return array{result_token:string,attempt_id:int}
 */
function buckleup_quiz_record_attempt( $result, $email, $name = '' ) {
	global $wpdb;
	$table = buckleup_quiz_table( 'quiz_attempts' );
	$token = bin2hex( random_bytes( 16 ) );

	$compact = array();
	foreach ( $result['review'] as $r ) {
		$compact[] = array(
			'qid'     => (int) $r['qid'],
			'picked'  => $r['picked_index'], // canonical or null
			'correct' => $r['is_correct'] ? 1 : 0,
		);
	}

	$name = buckleup_quiz_clean_name( $name );
	if ( '' === $name && is_user_logged_in() ) {
		$name = buckleup_quiz_clean_name( wp_get_current_user()->display_name );
	}

	$wpdb->insert(
		$table,
		array(
			'user_id'            => get_current_user_id() ?: null,
			'email'              => sanitize_email( $email ),
			'email_hash'         => buckleup_quiz_email_hash( $email ),
			'name'               => '' !== $name ? $name : null,
			'mode'               => $result['mode'],
			'score'              => (int) $result['score'],
			'total'              => (int) $result['total'],
			'passed'             => $result['passed'] ? 1 : 0,
			'category_breakdown' => wp_json_encode( $result['breakdown'] ),
			'answers'            => wp_json_encode( $compact ),
			'ip_hash'            => buckleup_quiz_ip_hash(),
			'result_token'       => $token,
			'created_at'         => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);

	// Bump the anonymous soft-limit cookie (no-op effect for logged-in users).
	if ( ! is_user_logged_in() ) {
		buckleup_quiz_cookie_set( buckleup_quiz_cookie_count() + 1 );
	}

	return array( 'result_token' => $token, 'attempt_id' => (int) $wpdb->insert_id );
}

/**
 * Load a stored attempt by result token and rebuild a full result payload
 * (re-reading question text/answers from the live bank). For the bookmarkable
 * result view + the emailed report link.
 *
 * @param string $token
 * @return array|null
 */
function buckleup_quiz_get_result_by_token( $token ) {
	global $wpdb;
	if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		return null;
	}
	$table = buckleup_quiz_table( 'quiz_attempts' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE result_token = %s", $token ), ARRAY_A );
	if ( ! $row ) {
		return null;
	}

	$compact = json_decode( (string) $row['answers'], true );
	$compact = is_array( $compact ) ? $compact : array();
	$ids     = array_map( static function ( $a ) {
		return (int) $a['qid'];
	}, $compact );
	$by_id   = buckleup_quiz_fetch_questions( $ids );

	$review = array();
	foreach ( $compact as $a ) {
		$qid = (int) $a['qid'];
		if ( ! isset( $by_id[ $qid ] ) ) {
			continue;
		}
		$r      = $by_id[ $qid ];
		$picked = array_key_exists( 'picked', $a ) && null !== $a['picked'] ? (int) $a['picked'] : null;
		$review[] = array(
			'qid'           => $qid,
			'category'      => $r['category'],
			'question'      => $r['question'],
			'options'       => array( $r['option_a'], $r['option_b'], $r['option_c'], $r['option_d'] ),
			'correct_index' => (int) $r['correct_index'],
			'picked_index'  => $picked,
			'is_correct'    => ! empty( $a['correct'] ),
			'explanation'   => (string) $r['explanation'],
		);
	}

	$breakdown = json_decode( (string) $row['category_breakdown'], true );

	return array(
		'result_token' => $row['result_token'],
		'mode'         => $row['mode'],
		'name'         => (string) $row['name'],
		'score'        => (int) $row['score'],
		'total'        => (int) $row['total'],
		'pct'          => (int) $row['total'] > 0 ? (int) round( (int) $row['score'] / (int) $row['total'] * 100 ) : 0,
		'passed'       => ! empty( $row['passed'] ),
		'breakdown'    => is_array( $breakdown ) ? $breakdown : array(),
		'review'       => $review,
		'created_at'   => $row['created_at'],
	);
}
