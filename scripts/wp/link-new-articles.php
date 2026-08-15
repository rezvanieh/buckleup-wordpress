<?php
/**
 * Wire the six new articles into their hubs.
 *
 * The articles already link UP to their pillars and ACROSS to siblings. This
 * closes the loop the other way, which is the plan's rule 2: the pillar lists
 * and links to each cluster, so authority flows down and the group reads as a
 * set rather than as orphans.
 *
 *   Hub 2 pillar  /services/class-5-driving-lessons/  ->  3 route posts + the
 *                                                          mistakes guide
 *   Hub 3 pillar  the GLP explainer                   ->  the 2 licensing posts
 *   Siblings      the two existing route posts        ->  the three new ones
 *
 * Same mechanics as link-hubs-2-3-4.php: a paragraph is appended to the last
 * text widget of an Elementor document, or to the end of classic content, and
 * every insertion is skipped when its proof link is already present.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/link-new-articles.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BU_NA_SITE = 'https://www.buckleupdriving.ca';

/** Index path of the LAST text-editor widget in an Elementor tree, or null. */
function bu_na_last_text_path( array $els, array $prefix = array() ) {
	$best = null;
	foreach ( $els as $i => $el ) {
		if ( ! is_array( $el ) ) { continue; }
		$here = array_merge( $prefix, array( $i ) );
		if ( 'text-editor' === ( $el['widgetType'] ?? '' ) && isset( $el['settings']['editor'] ) ) {
			$best = $here;
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$deeper = bu_na_last_text_path( $el['elements'], array_merge( $here, array( 'elements' ) ) );
			if ( null !== $deeper ) { $best = $deeper; }
		}
	}
	return $best;
}

/**
 * Append a paragraph to a post, whichever way its body is stored.
 *
 * @return string 'added' | 'present' | 'skipped'
 */
function bu_na_append( $post, $html, $proof ) {
	global $wpdb;
	mysqli_report( MYSQLI_REPORT_OFF );

	$res = mysqli_query( $wpdb->dbh, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=" . (int) $post->ID . " AND meta_key='_elementor_data' LIMIT 1" );
	$row = $res ? mysqli_fetch_row( $res ) : null;
	$raw = ( $row && '' !== $row[0] ) ? $row[0] : '';

	if ( '' !== $raw ) {
		if ( false !== strpos( $raw, $proof ) || false !== strpos( $raw, str_replace( '/', '\/', $proof ) ) ) {
			return 'present';
		}
		$body = json_decode( $raw, true );
		if ( ! is_array( $body ) ) { return 'skipped'; }
		$path = bu_na_last_text_path( $body );
		if ( null === $path ) { return 'skipped'; }
		$ref = &$body;
		foreach ( $path as $key ) { $ref = &$ref[ $key ]; }
		$ref['settings']['editor'] .= $html;
		unset( $ref );
		$json = wp_json_encode( $body );
		if ( ! is_array( json_decode( $json, true ) ) ) { return 'skipped'; }
		update_post_meta( $post->ID, '_elementor_data', wp_slash( $json ) );
		delete_post_meta( $post->ID, '_elementor_element_cache' );
		return 'added';
	}

	if ( false !== strpos( $post->post_content, $proof ) ) { return 'present'; }
	wp_update_post( array( 'ID' => $post->ID, 'post_content' => $post->post_content . "\n" . $html ) );
	return 'added';
}

/** target => [locator, paragraph, proof] */
$JOBS = array(

	// Hub 2 pillar down to its new clusters.
	'page:services/class-5-driving-lessons' => array(
		'html'  => '<p>Testing locally? We have route guides for <a href="' . BU_NA_SITE . '/icbc-road-test-routes-coquitlam-tri-cities/">Coquitlam and the Tri-Cities</a>, <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-moody/">Port Moody</a>, <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-coquitlam/">Port Coquitlam</a>, <a href="' . BU_NA_SITE . '/icbc-road-test-routes-north-vancouver-lynn-valley/">North Vancouver</a> and <a href="' . BU_NA_SITE . '/icbc-road-test-routes-vancouver/">Vancouver</a>, plus a rundown of the <a href="' . BU_NA_SITE . '/common-icbc-road-test-mistakes/">mistakes that cost most road tests</a>.</p>',
		'proof' => '/icbc-road-test-routes-port-moody/',
	),

	// Hub 3 pillar down to its new clusters.
	'post:bc-graduated-licensing-program-explained-7l-7n-class-5' => array(
		'html'  => '<p>Working through the stages? We cover the <a href="' . BU_NA_SITE . '/class-7n-novice-restrictions-bc/">rules that apply on a Class 7N licence</a> and what is involved in <a href="' . BU_NA_SITE . '/class-7n-to-class-5-bc/">going from your N to a full Class 5</a>.</p>',
		'proof' => '/class-7n-novice-restrictions-bc/',
	),

	// Existing route posts across to the new siblings.
	'post:icbc-road-test-routes-coquitlam-tri-cities' => array(
		'html'  => '<p>Testing elsewhere in the region? See our guides to <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-moody/">Port Moody</a>, <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-coquitlam/">Port Coquitlam</a> and <a href="' . BU_NA_SITE . '/icbc-road-test-routes-vancouver/">Vancouver</a> road tests.</p>',
		'proof' => '/icbc-road-test-routes-port-moody/',
	),
	'post:icbc-road-test-routes-north-vancouver-lynn-valley' => array(
		'html'  => '<p>Testing off the North Shore? We also cover <a href="' . BU_NA_SITE . '/icbc-road-test-routes-vancouver/">Vancouver</a>, <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-moody/">Port Moody</a> and <a href="' . BU_NA_SITE . '/icbc-road-test-routes-port-coquitlam/">Port Coquitlam</a>.</p>',
		'proof' => '/icbc-road-test-routes-vancouver/',
	),
);

$added = 0;
foreach ( $JOBS as $key => $job ) {
	list( $type, $path ) = explode( ':', $key, 2 );
	$post = ( 'page' === $type ) ? get_page_by_path( $path ) : get_page_by_path( $path, OBJECT, 'post' );
	if ( ! $post ) {
		$q    = get_posts( array( 'post_type' => $type, 'name' => basename( $path ), 'numberposts' => 1, 'post_status' => 'any' ) );
		$post = $q ? $q[0] : null;
	}
	if ( ! $post ) { printf( "  MISSING %s\n", $key ); continue; }

	$result = bu_na_append( $post, $job['html'], $job['proof'] );
	printf( "  %-8s %s\n", $result, $key );
	if ( 'added' === $result ) { $added++; }
}

if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
wp_cache_flush();

echo "\n$added link paragraph(s) added.\n";
