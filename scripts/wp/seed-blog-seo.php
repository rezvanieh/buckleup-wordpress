<?php
/**
 * Publishes the 10 organic-traffic SEO articles (Task #11 by wp-seo-content-writer)
 * as native WordPress posts under /blog/{slug}, with Rank Math per-post meta.
 *
 * Reads a manifest + HTML bodies staged at /wp-data/blog-seo/ (provision.sh copies
 * content/blog-seo/ -> wp-data/blog-seo/; the wpcli service mounts ./wp-data).
 *
 * Manifest entry keys (canonical, from the writer's brief):
 *   slug, title, seo_title, meta_description, focus_keyword, secondary_keywords[],
 *   category (Tips|Tutorials|Safety|Local|Licensing), tags[], excerpt,
 *   internal_links[] (informational), html_file
 *
 * Mapping:
 *   post_title            <- title
 *   post_content          <- contents of html_file (leading <h1> stripped — the
 *                            theme's single template already renders the title as H1)
 *   post_excerpt          <- excerpt
 *   category / tags       <- created if missing
 *   rank_math_focus_keyword <- focus_keyword
 *   rank_math_title         <- seo_title
 *   rank_math_description   <- meta_description
 *   (secondary_keywords stored as rank_math_focus_keyword extras are NOT set —
 *    Rank Math's secondary-keyword field is pro-only; we keep them in post meta
 *    bu_secondary_keywords for reference only.)
 *
 * Dates: staggered descending from "now" (newest first), 1 day apart, so the blog
 * archive ordering is stable and these sit above the original 5 seed posts.
 *
 * Idempotent: upsert by slug. /blog/{slug} preserved (permalink set in provision).
 * Run via: wp eval-file /scripts/wp/seed-blog-seo.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

$dir      = '/wp-data/blog-seo';
$manifest = "{$dir}/manifest.json";

if ( ! file_exists( $manifest ) ) {
	WP_CLI::warning( "No manifest at {$manifest}; provision.sh stages content/blog-seo/ there. Skipping SEO blog import." );
	return;
}

$entries = json_decode( file_get_contents( $manifest ), true );
if ( ! is_array( $entries ) ) {
	WP_CLI::warning( "Could not parse {$manifest} (invalid JSON). Skipping." );
	return;
}

// bu_strip_leading_h1() is defined in lib.php (shared with import-posts.php).

$now    = current_time( 'timestamp' );
$day    = DAY_IN_SECONDS;
$count  = 0;
$author = bu_post_author_id(); // byline author ("Admin User"), same as the migrated posts

foreach ( $entries as $i => $e ) {
	$slug = isset( $e['slug'] ) ? sanitize_title( $e['slug'] ) : '';
	if ( ! $slug ) { WP_CLI::warning( "  entry #{$i}: missing slug, skipped." ); continue; }

	$html_file = isset( $e['html_file'] ) ? $e['html_file'] : "{$slug}.html";
	$body_path = "{$dir}/{$html_file}";
	if ( ! file_exists( $body_path ) ) {
		WP_CLI::warning( "  {$slug}: body file {$html_file} not found, skipped." );
		continue;
	}
	$body = bu_strip_leading_h1( file_get_contents( $body_path ) );

	// Stagger dates: newest first (index 0 = now), 1 day apart going back.
	$ts   = $now - ( $i * $day );
	$gmt  = gmdate( 'Y-m-d H:i:s', $ts );

	$existing = bu_find_post( 'post', $slug, isset( $e['title'] ) ? $e['title'] : '' );
	$data = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => isset( $e['title'] ) ? $e['title'] : $slug,
		'post_excerpt' => isset( $e['excerpt'] ) ? $e['excerpt'] : '',
		'post_content' => $body,
		'post_author'  => $author,
		'post_date'    => get_date_from_gmt( $gmt ),
		'post_date_gmt'=> $gmt,
	);
	if ( $existing ) { $data['ID'] = $existing; }

	$id = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "  {$slug}: " . $id->get_error_message() );
		continue;
	}

	// Category (single free-text -> term) + tags.
	if ( ! empty( $e['category'] ) ) {
		$cat_id = bu_ensure_category( $e['category'] );
		if ( $cat_id ) { wp_set_post_categories( $id, array( $cat_id ), false ); }
	}
	if ( ! empty( $e['tags'] ) && is_array( $e['tags'] ) ) {
		wp_set_post_tags( $id, $e['tags'], false );
	}

	// Rank Math per-post meta.
	if ( ! empty( $e['focus_keyword'] ) ) {
		update_post_meta( $id, 'rank_math_focus_keyword', $e['focus_keyword'] );
	}
	if ( ! empty( $e['seo_title'] ) ) {
		update_post_meta( $id, 'rank_math_title', $e['seo_title'] );
	}
	if ( ! empty( $e['meta_description'] ) ) {
		update_post_meta( $id, 'rank_math_description', $e['meta_description'] );
	}
	// Secondary keywords kept for reference (Rank Math's field is pro-only).
	if ( ! empty( $e['secondary_keywords'] ) && is_array( $e['secondary_keywords'] ) ) {
		update_post_meta( $id, 'bu_secondary_keywords', implode( ', ', $e['secondary_keywords'] ) );
	}

	$verb = $existing ? 'updated' : 'created';
	WP_CLI::log( "  post {$verb}: {$slug} (#{$id}) [" . ( $e['category'] ?? '—' ) . ']' );
	$count++;
}

WP_CLI::success( "SEO blog import: {$count} post(s) published under /blog/{slug} with Rank Math meta." );
