<?php
/** Mint a passing attempt + print its certificate URL. make wp CMD="eval-file /scripts/wp/quiz/make-cert.php" */
$test    = buckleup_quiz_assemble_test( 'air-brakes' );
$session = array( 'question_ids' => $test['question_ids'], 'perms' => $test['perms'], 'mode' => 'air-brakes' );
$by_id   = buckleup_quiz_fetch_questions( $test['question_ids'] );
$answers = array();
foreach ( $test['question_ids'] as $qid ) {
	$ci = (int) $by_id[ $qid ]['correct_index'];
	foreach ( $test['perms'][ $qid ] as $disp => $canon ) {
		if ( (int) $canon === $ci ) { $answers[ $qid ] = (int) $disp; break; }
	}
}
$result = buckleup_quiz_grade( $session, $answers );
$rec    = buckleup_quiz_record_attempt( $result, 'cert@example.com', 'Jordan Sample' );
echo "TOKEN={$rec['result_token']}\n";
echo 'URL=' . buckleup_quiz_certificate_url( $rec['result_token'] ) . "\n";
echo 'passed=' . ( $result['passed'] ? '1' : '0' ) . " pct={$result['pct']}\n";
