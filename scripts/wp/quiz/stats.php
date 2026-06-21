<?php
/** Quick bank stats — make wp CMD="eval-file /scripts/wp/quiz/stats.php" */
global $wpdb;
$t = buckleup_quiz_table( 'questions' );
$d = $wpdb->get_results( "SELECT correct_index, COUNT(*) c FROM {$t} GROUP BY correct_index ORDER BY correct_index", ARRAY_A );
echo "Answer distribution: ";
foreach ( $d as $r ) {
	echo chr( 65 + (int) $r['correct_index'] ) . '=' . $r['c'] . '  ';
}
echo "\n";
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
$ex    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE explanation <> ''" );
echo "Total: {$total}   With explanation: {$ex}\n";
// Spot-check 3 authored questions + their correct answer.
$rows = $wpdb->get_results( "SELECT source_ref, question, option_a, option_b, option_c, option_d, correct_index FROM {$t} WHERE source_ref LIKE 'BU-AUTHORED-%' ORDER BY RAND() LIMIT 3", ARRAY_A );
foreach ( $rows as $r ) {
	$opts = array( $r['option_a'], $r['option_b'], $r['option_c'], $r['option_d'] );
	echo "\n[{$r['source_ref']}] {$r['question']}\n   ✓ " . $opts[ (int) $r['correct_index'] ] . "\n";
}
