<?php
/**
 * Close the last two gaps in the pillar/cluster structure, found by re-auditing
 * the whole link graph after the six new articles went live.
 *
 * 1. THE PLAN'S NUMBER ONE PRIORITY LINK WAS STILL MISSING.
 *
 *    Section 5 lists "Homepage -> /locations/coquitlam/ with anchor 'driving
 *    lessons in Coquitlam'" first, and notes it is already in the footer so an
 *    in-body link should be added too. The footer link exists; the in-body one
 *    never did. That matters because the whole point of the exercise is pushing
 *    the home page's authority at the Coquitlam page, which the plan measured at
 *    position 52 on 1,691 impressions.
 *
 *    The lessons section already names all four cities in its intro, so the city
 *    names simply become links. Descriptive anchors, no new sentence bolted on.
 *
 *    That paragraph also carried an em dash, which this site does not use, so it
 *    is replaced at the same time.
 *
 * 2. THREE CITY PAGES DID NOT LINK UP TO THEIR PILLAR.
 *
 *    Coquitlam and Port Coquitlam link to /locations/; Port Moody, North
 *    Vancouver and Tri-Cities did not, so rule 1 of section 5 (every cluster
 *    links up to its pillar) was only 2 of 5 satisfied in Hub 4. Each has a
 *    "BuckleUp also provides..." paragraph ending in "Explore our", which is
 *    exactly where Port Coquitlam's pillar link already sits, so the same
 *    sentence shape is reused.
 *
 * Idempotent: every edit is skipped when its link is already present.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-remaining-hub-links.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BU_RH_SITE = 'https://www.buckleupdriving.ca';

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

/** Raw read: through $wpdb every literal % in Elementor data gets rewritten. */
function bu_rh_data( $post_id ) {
	global $wpdb;
	$r   = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=" . (int) $post_id . " AND meta_key='_elementor_data' LIMIT 1" );
	$row = $r ? mysqli_fetch_row( $r ) : null;
	return $row ? (string) $row[0] : '';
}

function bu_rh_save( $post_id, $json ) {
	if ( ! is_array( json_decode( $json, true ) ) ) { return false; }
	update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
	delete_post_meta( $post_id, '_elementor_element_cache' );
	return true;
}

$changed = 0;

/* ------------------------------------------- 1. home -> the city pages ---- */

$home = get_page_by_path( 'home' );
if ( $home ) {
	$json = bu_rh_data( $home->ID );

	if ( false !== strpos( $json, '/locations/coquitlam/' ) ) {
		echo "  home: already links to the city pages\n";
	} else {
		// The dek is stored inside JSON, so quotes and slashes arrive escaped.
		$find = 'ICBC-certified driving lessons in Coquitlam, Port Coquitlam, Port Moody and North Vancouver';
		$link = function ( $city, $slug ) {
			return '<a href=\"' . str_replace( '/', '\/', BU_RH_SITE . '/locations/' . $slug . '/' ) . '\">' . $city . '<\/a>';
		};
		$replace = 'ICBC-certified driving lessons in '
			. $link( 'Coquitlam', 'coquitlam' ) . ', '
			. $link( 'Port Coquitlam', 'port-coquitlam' ) . ', '
			. $link( 'Port Moody', 'port-moody' ) . ' and '
			. $link( 'North Vancouver', 'north-vancouver' );

		$count = substr_count( $json, $find );
		if ( 1 !== $count ) {
			printf( "  !! home: intro sentence matched %d times, expected 1. Skipped.\n", $count );
		} else {
			$json = str_replace( $find, $replace, $json );
			/*
			 * House style: no em dashes. Elementor stores the character escaped
			 * as — inside the JSON, so a literal em dash in this file
			 * matches nothing. Built from chr(92) so the escape survives however
			 * this file is edited.
			 */
			$esc  = chr( 92 ) . 'u2014';
			$json = str_replace( ' ' . $esc . ' from your first lesson', ', from your first lesson', $json );
			if ( bu_rh_save( $home->ID, $json ) ) {
				echo "  home: linked all four city names in the lessons intro\n";
				$changed++;
			} else {
				echo "  !! home: result was not valid JSON, nothing written\n";
			}
		}
	}
}

/* ------------------------------ 2. city pages -> the /locations/ pillar ---- */

$pillar_link = 'See <a href=\"' . str_replace( '/', '\/', BU_RH_SITE . '/locations/' ) . '\">every area we teach in<\/a>, explore our';

foreach ( array( 'port-moody', 'north-vancouver', 'tri-cities' ) as $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'location' );
	if ( ! $p ) { printf( "  MISSING location:%s\n", $slug ); continue; }

	$json = bu_rh_data( $p->ID );
	if ( '' === $json ) { printf( "  !! %s: no Elementor data\n", $slug ); continue; }

	if ( false !== strpos( $json, str_replace( '/', '\/', BU_RH_SITE . '/locations/' ) . '\">' ) ) {
		printf( "  %-16s already links to the pillar\n", $slug );
		continue;
	}

	$count = substr_count( $json, 'Explore our' );
	if ( 1 !== $count ) {
		printf( "  !! %-13s 'Explore our' matched %d times, expected 1. Skipped.\n", $slug, $count );
		continue;
	}

	$json = str_replace( 'Explore our', $pillar_link, $json );
	if ( bu_rh_save( $p->ID, $json ) ) {
		printf( "  %-16s pillar link added\n", $slug );
		$changed++;
	} else {
		printf( "  !! %s: result was not valid JSON, nothing written\n", $slug );
	}
}

if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "\n$changed document(s) updated.\n";
