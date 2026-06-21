<?php
/** Audit: one authored question per category with the marked answer + explanation. */
global $wpdb;
$t = buckleup_quiz_table( 'questions' );
foreach ( array_keys( buckleup_quiz_categories() ) as $cat ) {
	$r = $wpdb->get_row( $wpdb->prepare(
		"SELECT source_ref, question, option_a, option_b, option_c, option_d, correct_index, explanation
		 FROM {$t} WHERE category = %s AND source_ref LIKE 'BU-AUTHORED-%%' ORDER BY RAND() LIMIT 1", $cat ), ARRAY_A );
	if ( ! $r ) { continue; }
	$opts = array( $r['option_a'], $r['option_b'], $r['option_c'], $r['option_d'] );
	echo "\n### {$cat}\nQ: {$r['question']}\n";
	foreach ( $opts as $i => $o ) {
		echo ( $i === (int) $r['correct_index'] ? ' >> ' : '    ' ) . chr( 65 + $i ) . ". {$o}\n";
	}
	echo "   ↳ {$r['explanation']}\n";
}
