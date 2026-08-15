<?php
/**
 * The contact page has its opening hours written into the Elementor body as
 * literal text, so it never followed the settings option the way the footer and
 * the schema do: it still read "9am-6pm PST" after the client confirmed on
 * 2026-08-15 that they close at 9pm.
 *
 * In Elementor's JSON an en-dash is stored as the escape sequence u2013 (with a
 * leading backslash), so searching for the on-screen string "9am-6pm" finds
 * nothing. The needle is therefore assembled from its parts rather than typed as
 * a literal, which also stops a shell heredoc or an editor from silently
 * converting it on the way into this file.
 *
 * Raw mysqli for the read: through $wpdb every literal % in Elementor data gets
 * rewritten, and that data is full of "%" units.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-contact-hours.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

$p = get_page_by_path( 'contact' );
if ( ! $p ) { echo "ABORT: no contact page\n"; return; }

$r    = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id={$p->ID} AND meta_key='_elementor_data' LIMIT 1" );
$row  = $r ? mysqli_fetch_row( $r ) : null;
$json = $row ? $row[0] : '';
if ( '' === $json ) { echo "ABORT: no elementor data\n"; return; }

$bs      = chr( 92 );                 // backslash
$esc     = $bs . 'u2013';             // en-dash as Elementor stores it
$endash  = "\xE2\x80\x93";            // en-dash as raw UTF-8

$pairs = array(
	'9am' . $esc . '6pm'    => '9am' . $esc . '9pm',
	'9am' . $endash . '6pm' => '9am' . $endash . '9pm',
	'9am-6pm'               => '9am-9pm',
	'9am 6pm'               => '9am 9pm',
);

$n = 0;
foreach ( $pairs as $from => $to ) {
	$c = substr_count( $json, $from );
	if ( $c ) { $json = str_replace( $from, $to, $json ); $n += $c; }
}

if ( ! $n ) {
	echo "no 6pm found in the contact page body\n";
	// Show what IS there, so a miss is diagnosable instead of silent.
	if ( preg_match_all( '/9am.{0,8}pm/u', $json, $m ) ) {
		echo '  found instead: ' . implode( ', ', array_unique( $m[0] ) ) . "\n";
	}
	return;
}

if ( ! is_array( json_decode( $json, true ) ) ) { echo "ABORT: result is not valid JSON\n"; return; }

update_post_meta( $p->ID, '_elementor_data', wp_slash( $json ) );
delete_post_meta( $p->ID, '_elementor_element_cache' );
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "contact page: $n occurrence(s) of the old closing time corrected to 9pm\n";
