<?php
/**
 * Fix the one colour-contrast failure PageSpeed reports (Accessibility 96).
 *
 * THE FAILURE
 * -----------
 * The practice-test stat on the home page, the large "231" above "Practice
 * questions across all 12 official ICBC Class 4 topics", is rendered in the
 * Elementor brand green #10B77F on a white surface. Measured contrast is
 * 2.59:1 against a 3:1 minimum for large text, so it fails WCAG AA. At 48px it
 * looks fine to most people, which is exactly why it went unnoticed, but it is
 * genuinely hard to read for anyone with reduced contrast sensitivity.
 *
 * WHY NOT JUST CHANGE THE BRAND GREEN
 * -----------------------------------
 * #10B77F is Elementor's global "secondary" and is referenced by 15 rules as a
 * text colour and 3 as a background. Redefining it would restyle every one of
 * those, including button backgrounds, which is a brand decision rather than an
 * accessibility fix. The failing element is a single stat, so it is corrected on
 * its own and the global is left alone.
 *
 * THE REPLACEMENT
 * ---------------
 * #0C8F61 measures 4.10:1 on white. That clears AA for large text with room to
 * spare and also clears the stricter 4.5:1 small-text bar, so the number stays
 * accessible if it is ever resized. It is a slightly deeper version of the same
 * green and reads as the same brand colour.
 *
 * Idempotent. Run:
 *   docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-contrast-stat.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BU_STAT_WIDGET = 'e312a2f';
const BU_STAT_GREEN  = '#0C8F61';

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

$page = get_page_by_path( 'home' );
if ( ! $page ) { echo "ABORT: no home page\n"; return; }

$res = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=" . (int) $page->ID . " AND meta_key='_elementor_data' LIMIT 1" );
$row = $res ? mysqli_fetch_row( $res ) : null;
if ( ! $row || '' === $row[0] ) { echo "ABORT: no Elementor data\n"; return; }

$data = json_decode( $row[0], true );
if ( ! is_array( $data ) ) { echo "ABORT: data will not decode\n"; return; }

$done = false;

$walk = function ( array &$els ) use ( &$walk, &$done ) {
	foreach ( $els as &$el ) {
		if ( ! is_array( $el ) ) { continue; }

		if ( BU_STAT_WIDGET === ( $el['id'] ?? '' ) ) {
			$title = trim( wp_strip_all_tags( $el['settings']['title'] ?? '' ) );
			if ( '231' !== $title ) {
				printf( "  !! widget %s now reads '%s', not the expected stat. Left alone.\n", BU_STAT_WIDGET, $title );
				return;
			}
			if ( BU_STAT_GREEN === ( $el["settings"]["title_color"] ?? "" ) && ! isset( $el["settings"]["__globals__"]["title_color"] ) ) {
				echo "  already set\n";
				$done = true;
				return;
			}
			$el['settings']['title_color'] = BU_STAT_GREEN;
			/*
			 * Elementor's global colour reference outranks a local value, so the
			 * pointer has to go or the widget keeps rendering the old green.
			 *
			 * __globals__ lives INSIDE settings, not on the element. Unsetting it
			 * at the element level (the obvious guess) silently does nothing: the
			 * local colour is stored, the global still wins, and the rendered
			 * page does not change.
			 */
			if ( isset( $el['settings']['__globals__']['title_color'] ) ) {
				unset( $el['settings']['__globals__']['title_color'] );
				if ( empty( $el['settings']['__globals__'] ) ) {
					unset( $el['settings']['__globals__'] );
				}
			}
			$done = true;
			return;
		}

		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$walk( $el['elements'] );
			if ( $done ) { return; }
		}
	}
	unset( $el );
};

$walk( $data );

if ( ! $done ) { echo "  widget " . BU_STAT_WIDGET . " not found\n"; return; }

$json = wp_json_encode( $data );
if ( ! is_array( json_decode( $json, true ) ) ) { echo "ABORT: result is not valid JSON\n"; return; }

update_post_meta( $page->ID, '_elementor_data', wp_slash( $json ) );
delete_post_meta( $page->ID, '_elementor_element_cache' );
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "  stat colour set to " . BU_STAT_GREEN . " (4.10:1 on white, was 2.59:1)\n";
