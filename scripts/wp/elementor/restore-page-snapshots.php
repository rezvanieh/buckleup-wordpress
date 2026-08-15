<?php
/**
 * Restore the marketing pages from the version-controlled snapshots in
 * snapshots/pages/, which hold the LIVE page bodies exactly as production serves
 * them.
 *
 * Companion to restore-location-snapshots.php; same reasoning, wider scope.
 *
 * WHY
 * ---
 * build-pages.php generates the marketing pages from PHP. That is right for
 * scaffolding a fresh install, but production is edited (in Elementor, and by
 * targeted scripts), so the live page is the truth. Regenerating blind replaces
 * real content with the repo's older idea of it — which is how the location pages
 * were lost on 2026-08-14.
 *
 * Snapshots are keyed by page PATH with `/` written as `--`, so nested pages like
 * services/class-7-driving-lessons round-trip safely:
 *     services--class-7-driving-lessons.json  ->  /services/class-7-driving-lessons/
 *
 * Production URLs are stored verbatim and rewritten to the target site's home_url()
 * on import, so the same snapshot restores to prod, local or staging.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/restore-page-snapshots.php
 *      (optional path args restore a subset: ... restore-page-snapshots.php services)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$DIR = __DIR__ . '/snapshots/pages';
if ( ! is_dir( $DIR ) ) {
	echo "ERROR: no snapshots directory at $DIR\n";
	return;
}

$manifest = array();
if ( is_readable( $DIR . '/manifest.json' ) ) {
	$manifest = json_decode( file_get_contents( $DIR . '/manifest.json' ), true );
	if ( ! is_array( $manifest ) ) { $manifest = array(); }
}

$only = ( isset( $args ) && is_array( $args ) ) ? array_filter( array_map( 'strval', $args ) ) : array();

/** Host the snapshots were captured from. */
const BU_PAGE_SNAPSHOT_HOST = 'https://www.buckleupdriving.ca';

$restored = 0;
$problems = 0;

foreach ( glob( $DIR . '/*.json' ) as $file ) {
	$key = basename( $file, '.json' );
	if ( 'manifest' === $key ) { continue; }

	$path = isset( $manifest[ $key ]['path'] ) ? $manifest[ $key ]['path'] : str_replace( '--', '/', $key );
	if ( $only && ! in_array( $path, $only, true ) ) { continue; }

	$post = get_page_by_path( $path );
	if ( ! $post ) {
		echo "  SKIP $path: no page at that path\n";
		$problems++;
		continue;
	}

	$json = file_get_contents( $file );

	$home = untrailingslashit( home_url() );
	if ( $home !== BU_PAGE_SNAPSHOT_HOST ) {
		$json = str_replace(
			array( str_replace( '/', '\/', BU_PAGE_SNAPSHOT_HOST ), BU_PAGE_SNAPSHOT_HOST ),
			array( str_replace( '/', '\/', $home ), $home ),
			$json
		);
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) || ! $decoded ) {
		echo "  ABORT $path: snapshot is not valid Elementor data\n";
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

	if ( ! empty( $manifest[ $key ]['meta'] ) ) {
		foreach ( $manifest[ $key ]['meta'] as $mk => $mv ) {
			if ( '' !== $mv ) { update_post_meta( $post->ID, $mk, $mv ); }
		}
	}

	printf( "  restored %-40s %d -> %d bytes (%d sections)\n", $path, $before, strlen( $json ), count( $decoded ) );
	$restored++;
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

echo "Restored $restored page(s)" . ( $problems ? ", $problems problem(s)" : '' ) . ".\n";
