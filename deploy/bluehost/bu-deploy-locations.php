<?php
/**
 * ONE-OFF production importer for the 5 location landing pages. DELETE AFTER USE.
 *
 * Deploy model (Bluehost, no WP-CLI — see CLAUDE.md §5C): the build scripts + heroes
 * are SFTP'd off-webroot to $BU_DEPLOY; this token-protected helper (in public_html)
 * loads WP and runs them on prod so _elementor_data carries native prod URLs (no
 * localhost rewrite). As its LAST step it swaps the Elementor single-location template
 * into place, so the location pages never render in a half-state.
 *
 * Trigger:  curl -sS "https://www.buckleupdriving.ca/bu-deploy-locations.php?token=XXXX"
 * Then DELETE this file + the $BU_DEPLOY dir and verify 404.
 */

define( 'BU_TOKEN', '__BU_DEPLOY_TOKEN__' ); // replaced with a fresh `openssl rand -hex 16` at deploy time
if ( ! hash_equals( BU_TOKEN, (string) ( $_GET['token'] ?? '' ) ) ) {
	http_response_code( 403 );
	exit( "forbidden\n" );
}
header( 'Content-Type: text/plain; charset=utf-8' );

$DOCROOT   = '/home2/yuwaeymy/public_html';
$BU_DEPLOY = '/home2/yuwaeymy/bu-deploy/elementor';

require $DOCROOT . '/wp-load.php';
echo "WP loaded. home=" . home_url() . " | prod=" . ( strpos( home_url(), 'buckleupdriving.ca' ) !== false ? 'YES' : 'NO' ) . "\n\n";

/* 1. Enable Elementor's editor + frontend builder for the `location` CPT. */
$cpt = get_option( 'elementor_cpt_support' );
$cpt = is_array( $cpt ) && $cpt ? $cpt : array( 'post', 'page' );
if ( ! in_array( 'location', $cpt, true ) ) {
	$cpt[] = 'location';
	update_option( 'elementor_cpt_support', $cpt );
	echo "[1] elementor_cpt_support += location\n";
} else {
	echo "[1] elementor_cpt_support already includes location\n";
}

/* 2. Import the 5 landmark hero images (idempotent, tagged _bu_location_hero). */
echo "[2] importing heroes:\n";
require $BU_DEPLOY . '/import-location-heroes.php';

/* 3. Build the 5 Elementor location pages (native prod URLs via home_url()). */
echo "[3] building Elementor location pages:\n";
require $BU_DEPLOY . '/build-locations.php';

/* 4. Apply per-location Rank Math title/description + add the Location CPT to the
 *    sitemap (Rank Math reads the HYPHENATED option key). */
$content = require $BU_DEPLOY . '/locations-content.php';
foreach ( $content as $slug => $d ) {
	$p = get_page_by_path( $slug, OBJECT, 'location' );
	if ( ! $p ) { continue; }
	if ( ! empty( $d['seo_title'] ) )       { update_post_meta( $p->ID, 'rank_math_title', $d['seo_title'] ); }
	if ( ! empty( $d['seo_description'] ) )  { update_post_meta( $p->ID, 'rank_math_description', $d['seo_description'] ); }
}
$smk = 'rank-math-options-sitemap';
$sm  = get_option( $smk, array() );
$sm  = is_array( $sm ) ? $sm : array();
$sm['pt_location_sitemap'] = 'on';
update_option( $smk, $sm );
// keep the legacy underscore key in sync (harmless)
update_option( 'rank_math_sitemap', array_merge( (array) get_option( 'rank_math_sitemap', array() ), array( 'pt_location_sitemap' => 'on' ) ) );
if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) { \RankMath\Sitemap\Cache::invalidate_storage(); }
echo "[4] rank_math titles applied + pt_location_sitemap=on\n";

/* 5. /image-credits/ page (idempotent, noindex) for the CC photo attribution. */
$cp = get_page_by_path( 'image-credits' );
if ( ! $cp ) {
	$cid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Image Credits', 'post_name' => 'image-credits', 'post_content' => '[buckleup_image_credits]' ) );
	echo "[5] created image-credits page $cid\n";
} else {
	$cid = $cp->ID;
	wp_update_post( array( 'ID' => $cid, 'post_content' => '[buckleup_image_credits]' ) );
	echo "[5] image-credits page exists $cid (refreshed)\n";
}
update_post_meta( $cid, 'rank_math_robots', array( 'noindex' ) );

/* 6. Swap the Elementor single-location template into place (LAST → no half-state). */
$tpl_src = $BU_DEPLOY . '/single-location.html';
$tpl_dst = $DOCROOT . '/wp-content/themes/buckleup/templates/single-location.html';
if ( is_readable( $tpl_src ) && copy( $tpl_src, $tpl_dst ) ) {
	echo "[6] single-location.html swapped into the theme\n";
} else {
	echo "[6] WARNING: could not swap single-location.html (src readable=" . ( is_readable( $tpl_src ) ? 'y' : 'n' ) . ")\n";
}

/* 7. Regenerate Elementor CSS, flush rewrites (sitemap + CPT routes), purge caches. */
if ( class_exists( '\\Elementor\\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
	echo "[7] Elementor CSS cache cleared\n";
}
flush_rewrite_rules( false );
wp_cache_flush(); // Redis object cache

// Newfold endurance page cache: recursively clear its cache dir.
$purged = 0;
$epc    = WP_CONTENT_DIR . '/endurance-page-cache';
if ( is_dir( $epc ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $epc, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		if ( $f->isDir() ) { @rmdir( $f->getPathname() ); }
		else { if ( @unlink( $f->getPathname() ) ) { $purged++; } }
	}
}
echo "[7] rewrites flushed, object cache flushed, page-cache files purged=$purged\n";

echo "\nDONE. Verify the 5 /locations/* pages, then DELETE this file + the bu-deploy dir.\n";
