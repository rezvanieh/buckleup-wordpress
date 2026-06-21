<?php
/** Re-install tables (DB v2 name column) + re-seed bank. make wp CMD="eval-file /scripts/wp/quiz/reseed.php" */
buckleup_quiz_install_tables();
$r = buckleup_quiz_seed_questions();
echo "inserted: {$r['inserted']}  updated: {$r['updated']}  skipped: {$r['skipped']}  active: {$r['total_active']}\n";
global $wpdb;
$cols = $wpdb->get_col( 'DESC ' . buckleup_quiz_table( 'quiz_attempts' ), 0 );
echo 'has name column: ' . ( in_array( 'name', $cols, true ) ? 'YES' : 'NO' ) . "\n";
