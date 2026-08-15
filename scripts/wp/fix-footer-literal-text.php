<?php
/**
 * Correct the literal text baked into the global Elementor footer template.
 *
 * The footer is an Elementor library template (elementor_library / site-footer),
 * NOT the theme's patterns/site-footer.php. That matters: the theme pattern reads
 * the location list and the opening hours from the settings, but the template
 * that actually renders has both written in as literal strings. So changing the
 * settings option fixed the schema and the theme pattern while the visible footer
 * on every page kept showing the old values.
 *
 * Two defects, both sitewide:
 *
 *   1. "Driving Lessons in Driving Lessons in Coquitlam" — the label is built as
 *      "Driving Lessons in <city>" and Coquitlam's title had been changed to
 *      already include that prefix.
 *
 *   2. Opening hours. The client confirmed 2026-08-15 they are open 7am to 9pm,
 *      matching their Google Business Profile. The footer still said 9am.
 *
 * En-dashes are stored by Elementor as the escape sequence u2013 (with a leading
 * backslash), so the needles are assembled from their parts rather than typed as
 * literals — a shell heredoc or an editor will otherwise silently convert them
 * and the replacement then matches nothing.
 *
 * Raw mysqli for the read: through $wpdb every literal % in Elementor data is
 * rewritten, and that data is full of "%" units.
 *
 * Idempotent. Run:
 *   docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-footer-literal-text.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

$q = get_posts( array( 'post_type' => 'elementor_library', 'name' => 'site-footer', 'numberposts' => 1, 'post_status' => 'publish' ) );
$id = $q ? (int) $q[0]->ID : 0;
if ( ! $id ) { echo "ABORT: site-footer template not found\n"; return; }

$r    = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=$id AND meta_key='_elementor_data' LIMIT 1" );
$row  = $r ? mysqli_fetch_row( $r ) : null;
$json = $row ? $row[0] : '';
if ( '' === $json ) { echo "ABORT: no _elementor_data on $id\n"; return; }

$esc    = chr( 92 ) . 'u2013';   // en-dash as Elementor stores it
$endash = "\xE2\x80\x93";        // en-dash as raw UTF-8

$pairs = array(
	'Driving Lessons in Driving Lessons in Coquitlam' => 'Driving Lessons in Coquitlam',
	'9am' . $esc . '9pm'    => '7am' . $esc . '9pm',
	'9am' . $endash . '9pm' => '7am' . $endash . '9pm',
	'9am-9pm'               => '7am-9pm',
	'9am' . $esc . '6pm'    => '7am' . $esc . '9pm',
	'9am' . $endash . '6pm' => '7am' . $endash . '9pm',
);

$n = 0;
$hit = array();
foreach ( $pairs as $from => $to ) {
	$c = substr_count( $json, $from );
	if ( ! $c ) { continue; }
	$json = str_replace( $from, $to, $json );
	$n   += $c;
	$hit[] = sprintf( '%dx %s', $c, strlen( $from ) > 40 ? substr( $from, 0, 40 ) . '...' : $from );
}

if ( ! $n ) {
	echo "nothing to fix (already correct)\n";
	if ( preg_match_all( '/[0-9]{1,2}am.{0,10}pm/u', $json, $m ) ) {
		echo '  hours currently read: ' . implode( ', ', array_unique( $m[0] ) ) . "\n";
	}
	return;
}

if ( ! is_array( json_decode( $json, true ) ) ) { echo "ABORT: result is not valid JSON, nothing written\n"; return; }

update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
delete_post_meta( $id, '_elementor_element_cache' );
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "elementor_library/site-footer (post $id): $n replacement(s)\n";
foreach ( $hit as $h ) { echo "  $h\n"; }
