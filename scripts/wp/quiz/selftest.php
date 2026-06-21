<?php
/**
 * Engine self-test — run in the dev container:
 *   make wp CMD="eval-file /scripts/wp/quiz/selftest.php"
 *
 * Covers assembly (randomness, category grouping, no answer leak), the rail
 * manifest, server-authoritative grading, category mode, and result round-trip.
 * Prints PASS/FAIL lines.
 */

$fail = 0;
$pass = 0;
function check( $label, $cond ) {
	if ( $cond ) { echo "PASS  $label\n"; } else { echo "FAIL  $label\n"; }
	return $cond ? 1 : 0;
}

// 1) Full mock assembly.
$full   = buckleup_quiz_assemble_test( 'full' );
$ok     = ! is_wp_error( $full );
$pass  += check( 'full assembles (not WP_Error)', $ok );
$qcount = $ok ? count( $full['question_ids'] ) : 0;
$pass  += check( "full has 50 questions (got $qcount)", 50 === $qcount );

// Manifest present + totals add up to the question count.
$manifest_total = 0;
foreach ( (array) ( $ok ? $full['categories'] : array() ) as $m ) { $manifest_total += (int) $m['total']; }
$pass += check( "category manifest totals == $qcount", $manifest_total === $qcount );

// Serialize the whole test (as the batches would) → check no answer leak + grouping.
$payload = $ok ? buckleup_quiz_serialize_questions( $full['question_ids'], $full['perms'] ) : array();
$leak    = false;
$last_ci = -1;
$grouped = true;
foreach ( $payload as $q ) {
	if ( array_key_exists( 'correct_index', $q ) || array_key_exists( 'answer', $q ) || array_key_exists( 'explanation', $q ) ) { $leak = true; }
	if ( 4 !== count( $q['options'] ) ) { $leak = true; }
	if ( (int) $q['categoryIndex'] < $last_ci ) { $grouped = false; } // non-decreasing = grouped by category
	$last_ci = (int) $q['categoryIndex'];
}
$pass += check( 'payload leaks no answers + 4 options each', ! $leak );
$pass += check( 'full mock is GROUPED by category (rail-ready)', $grouped );

// Batches: first batch belongs to one category; batch count matches manifest.
$pass += check( 'batches count == manifest count', $ok && count( $full['batches'] ) === count( $full['categories'] ) );

// Randomness.
$full2 = buckleup_quiz_assemble_test( 'full' );
$pass += check( 'two assemblies differ (randomized)', ! is_wp_error( $full2 ) && $full['question_ids'] !== $full2['question_ids'] );

// 2) Grading from perms + DB.
function correct_display_for( $perm, $correct_index ) {
	foreach ( $perm as $display => $canonical ) {
		if ( (int) $canonical === (int) $correct_index ) { return (int) $display; }
	}
	return -1;
}
$session = array( 'question_ids' => $full['question_ids'], 'perms' => $full['perms'], 'mode' => 'full' );
$by_id   = buckleup_quiz_fetch_questions( $full['question_ids'] );
$all_correct = array();
$all_wrong   = array();
foreach ( $full['question_ids'] as $qid ) {
	$ci = (int) $by_id[ $qid ]['correct_index'];
	$cd = correct_display_for( $full['perms'][ $qid ], $ci );
	$all_correct[ $qid ] = $cd;
	$all_wrong[ $qid ]   = ( 0 === $cd ) ? 1 : 0;
}
$g_correct = buckleup_quiz_grade( $session, $all_correct );
$pass += check( "all-correct => score==total ({$g_correct['score']}/{$g_correct['total']})", $g_correct['score'] === $qcount );
$pass += check( 'all-correct => 100% + passed', 100 === $g_correct['pct'] && true === $g_correct['passed'] );
$g_wrong = buckleup_quiz_grade( $session, $all_wrong );
$pass += check( "all-wrong => score==0 ({$g_wrong['score']})", 0 === $g_wrong['score'] );

// 3) Category mode.
$cat    = buckleup_quiz_assemble_test( 'air-brakes' );
$ccount = is_wp_error( $cat ) ? 0 : count( $cat['question_ids'] );
$pass  += check( "category 'air-brakes' has 10 questions (got $ccount)", 10 === $ccount );
$cpay   = is_wp_error( $cat ) ? array() : buckleup_quiz_serialize_questions( $cat['question_ids'], $cat['perms'] );
$only   = true;
foreach ( $cpay as $q ) { if ( 'air-brakes' !== $q['category'] ) { $only = false; } }
$pass += check( 'category payload is single-category', $only );

// 4) Record + token round-trip (with a name → certificate-ready).
$rec = buckleup_quiz_record_attempt( $g_correct, 'selftest@example.com', 'Jane Selftest' );
$pass += check( 'record returns a 32-char token', isset( $rec['result_token'] ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $rec['result_token'] ) );
$loaded = buckleup_quiz_get_result_by_token( $rec['result_token'] );
$pass += check( 'token round-trip score matches', $loaded && (int) $loaded['score'] === (int) $g_correct['score'] );
$pass += check( 'stored name round-trips (certificate)', $loaded && 'Jane Selftest' === $loaded['name'] );
global $wpdb;
$wpdb->delete( buckleup_quiz_table( 'quiz_attempts' ), array( 'result_token' => $rec['result_token'] ), array( '%s' ) );

echo "\n----\n$pass checks passed\n";
