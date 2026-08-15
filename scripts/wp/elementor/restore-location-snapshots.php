<?php
/**
 * Restore the location pages from the version-controlled snapshots in
 * snapshots/locations/, which hold the LIVE, client-edited page bodies.
 *
 * WHY THIS EXISTS
 * ---------------
 * build-locations.php generates location pages from locations-content.php. That was
 * fine while the pages were purely script-generated, but the client now edits them
 * directly in Elementor, so PRODUCTION became the real source of truth and the
 * generator became a loaded gun: running it silently replaced hand-written content
 * with the repo's older version (this happened on 2026-08-14 and cost the client
 * edits made across three pages).
 *
 * So the model is now explicit:
 *   - snapshots/locations/*.json  = the real page bodies. Version-controlled, so a
 *                                   bad deploy is recoverable from git rather than
 *                                   only from a database backup.
 *   - locations-content.php       = still the source for the SEO/geo/JSON-LD data
 *                                   the mu-plugin reads, and for scaffolding a
 *                                   brand-new install from nothing.
 *   - build-locations.php         = scaffolding only. It now refuses to overwrite a
 *                                   page that has a snapshot unless forced.
 *
 * Snapshots store production URLs verbatim (a faithful copy of what is live). This
 * script rewrites them to the target site's home_url() on import, so the same
 * snapshot restores correctly to prod, to a local dev stack, or to staging.
 *
 * Re-capture after the client edits pages:
 *   see README — export _elementor_data per location and commit the result.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/restore-location-snapshots.php
 *      (add slugs to restore only some: ... restore-location-snapshots.php port-moody)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$DIR = __DIR__ . '/snapshots/locations';
if ( ! is_dir( $DIR ) ) {
	echo "ERROR: no snapshots directory at $DIR\n";
	return;
}

$manifest = array();
if ( is_readable( $DIR . '/manifest.json' ) ) {
	$manifest = json_decode( file_get_contents( $DIR . '/manifest.json' ), true );
	if ( ! is_array( $manifest ) ) { $manifest = array(); }
}

$only = ( isset( $args ) && is_array( $args ) ) ? array_filter( array_map( 'sanitize_title', $args ) ) : array();

/** The host the snapshots were captured from; rewritten to this install's URL. */
const BU_SNAPSHOT_HOST = 'https://www.buckleupdriving.ca';

$restored = 0;
$problems = 0;

foreach ( glob( $DIR . '/*.json' ) as $file ) {
	$slug = basename( $file, '.json' );
	if ( 'manifest' === $slug ) { continue; }
	if ( $only && ! in_array( $slug, $only, true ) ) { continue; }

	$post = get_page_by_path( $slug, OBJECT, 'location' );
	if ( ! $post ) {
		echo "  SKIP $slug: no location post with that slug\n";
		$problems++;
		continue;
	}

	$json = file_get_contents( $file );

	// Retarget the snapshot at whichever site we are restoring into. Both the plain
	// and the JSON-escaped form appear, because Elementor stores URLs inside a
	// JSON string where slashes are escaped.
	$home = untrailingslashit( home_url() );
	if ( $home !== BU_SNAPSHOT_HOST ) {
		$json = str_replace(
			array( str_replace( '/', '\/', BU_SNAPSHOT_HOST ), BU_SNAPSHOT_HOST ),
			array( str_replace( '/', '\/', $home ), $home ),
			$json
		);
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) || count( $decoded ) < 3 ) {
		echo "  ABORT $slug: snapshot is not valid Elementor data\n";
		$problems++;
		continue;
	}

	$before = strlen( (string) get_post_meta( $post->ID, '_elementor_data', true ) );
	update_post_meta( $post->ID, '_elementor_data', wp_slash( $json ) );
	update_post_meta( $post->ID, '_elementor_edit_mode', 'builder' );
	// Elementor serves _elementor_element_cache in preference to re-rendering
	// _elementor_data, so new content is invisible until this is dropped.
	delete_post_meta( $post->ID, '_elementor_element_cache' );
	if ( defined( 'ELEMENTOR_VERSION' ) ) {
		update_post_meta( $post->ID, '_elementor_version', ELEMENTOR_VERSION );
	}

	// Restore the SEO meta captured alongside the body.
	if ( ! empty( $manifest[ $slug ]['meta'] ) ) {
		foreach ( $manifest[ $slug ]['meta'] as $key => $value ) {
			if ( '' !== $value ) { update_post_meta( $post->ID, $key, $value ); }
		}
	}

	printf( "  restored %-18s %d -> %d bytes (%d sections)\n", $slug, $before, strlen( $json ), count( $decoded ) );
	$restored++;
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

echo "Restored $restored location page(s)" . ( $problems ? ", $problems problem(s)" : '' ) . ".\n";
