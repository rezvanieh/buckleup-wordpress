<?php
/**
 * Assigns a tasteful on-brand DEFAULT featured image to every blog post that
 * lacks one, chosen per CATEGORY, so blog cards / single headers / BlogPosting
 * schema aren't imageless. Client-approved (Task #24).
 *
 * Uses 5 generated per-category brand cards (the design-system primary->accent
 * gradient + category label + logo), built by scripts/gen-blog-cards.sh and
 * imported by import-media.php; resolved here by their _bu_source_file meta so
 * this works on a fresh provision too. One distinct card per category:
 *   Tips       -> blog-card-tips.png
 *   Tutorials  -> blog-card-tutorials.png
 *   Safety     -> blog-card-safety.png
 *   Local      -> blog-card-local.png
 *   Licensing  -> blog-card-licensing.png
 *
 * IDEMPOTENT + non-destructive: only sets a thumbnail when _thumbnail_id is empty,
 * so a client (or editor) choosing a real image later is never overwritten.
 * Run via: wp eval-file /scripts/wp/assign-post-images.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

/** Resolve an attachment ID by the _bu_source_file it was imported under. */
function bu_attachment_by_source( $basename ) {
	$q = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_bu_source_file',
		'meta_value'     => $basename,
		'no_found_rows'  => true,
	) );
	return $q ? (int) $q[0] : 0;
}

// Category → [ generated card basename, alt text ]. One distinct on-brand card
// per category (scripts/gen-blog-cards.sh). bu_attachment_by_source matches on
// the file's basename stem regardless of EWWW's PNG->JPG re-encode (see lookup).
$category_image = array(
	'Tips'      => array( 'blog-card-tips.png',      'BuckleUp Driving School — driving tips' ),
	'Tutorials' => array( 'blog-card-tutorials.png', 'BuckleUp Driving School — step-by-step driving tutorials' ),
	'Safety'    => array( 'blog-card-safety.png',    'BuckleUp Driving School — road safety in British Columbia' ),
	'Local'     => array( 'blog-card-local.png',     'BuckleUp Driving School — local driving lessons across Metro Vancouver' ),
	'Licensing' => array( 'blog-card-licensing.png', 'BuckleUp Driving School — ICBC licensing guidance' ),
);

// Resolve each source basename to an attachment ID once.
$resolved = array();
foreach ( $category_image as $cat => $pair ) {
	$id = bu_attachment_by_source( $pair[0] );
	if ( $id ) {
		$resolved[ $cat ] = array( 'id' => $id, 'alt' => $pair[1] );
	} else {
		WP_CLI::warning( "  no Media Library attachment for {$pair[0]} (run import-media.php first) — {$cat} posts will be skipped." );
	}
}

// Fallback image for any post whose category isn't mapped (use the Tips card,
// then hero_card_image.png as a last resort if the cards weren't generated).
$fallback_id = bu_attachment_by_source( 'blog-card-tips.png' );
if ( ! $fallback_id ) { $fallback_id = bu_attachment_by_source( 'hero_card_image.png' ); }

$set = 0; $skipped = 0;
foreach ( get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $p ) {
	if ( get_post_thumbnail_id( $p->ID ) ) {
		$skipped++;
		continue; // never overwrite an existing/client-chosen image
	}

	$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
	$cat  = $cats ? $cats[0] : '';

	$choice = isset( $resolved[ $cat ] ) ? $resolved[ $cat ] : null;
	$att_id = $choice ? $choice['id'] : $fallback_id;
	$alt    = $choice ? $choice['alt'] : 'BuckleUp Driving School';
	if ( ! $att_id ) {
		WP_CLI::warning( "  {$p->post_name}: no image available to assign, skipped." );
		continue;
	}

	set_post_thumbnail( $p->ID, $att_id );
	// One distinct card per category, so its category alt text is accurate for
	// every post that uses it. The BlogPosting schema reads the featured image URL.
	update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );

	WP_CLI::log( "  {$p->post_name} [{$cat}] -> img #{$att_id}" );
	$set++;
}

WP_CLI::success( "Default featured images: set {$set}, left {$skipped} existing untouched." );
