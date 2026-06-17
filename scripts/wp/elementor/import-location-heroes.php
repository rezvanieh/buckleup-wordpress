<?php
/**
 * Import the 5 location hero background images into the Media Library.
 *
 * Source files are the pre-processed WebP heroes in assets/heroes/{slug}.webp
 * (resized to 2000px, cwebp q82). Each is a real, recognizable city landmark sourced
 * from Wikimedia Commons under a CC licence — the full attribution (author + licence +
 * source) is stored on the attachment (caption + description) so we can surface a
 * credit line and stay licence-compliant.
 *
 * Idempotent: an attachment is tagged with post-meta `_bu_location_hero = {slug}`;
 * re-running reuses the existing attachment (and refreshes its metadata) instead of
 * creating duplicates. Prints `slug|attachment_id|url` lines for the page builder.
 *
 * Run (dev):  docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/import-location-heroes.php
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * slug => attribution + descriptive alt. Landmark photos, Wikimedia Commons.
 * (artist / licence / source verified at build time — see _prod-data note.)
 */
$heroes = array(
	'coquitlam' => array(
		'landmark' => 'Lafarge Lake, Town Centre Park, Coquitlam',
		'alt'      => 'Lafarge Lake and the mountains at Town Centre Park in Coquitlam, BC — BuckleUp Driving School serves Coquitlam learner drivers',
		'artist'   => 'Rajivkozhikode',
		'licence'  => 'CC BY-SA 4.0',
		'lic_url'  => 'https://creativecommons.org/licenses/by-sa/4.0',
		'source'   => 'https://commons.wikimedia.org/wiki/File:Lafarge_Lake_in_the_fall.jpg',
	),
	'north-vancouver' => array(
		'landmark' => 'Lonsdale Quay, North Vancouver',
		'alt'      => 'Lonsdale Quay and the waterfront in North Vancouver, BC — BuckleUp Driving School driving lessons on the North Shore',
		'artist'   => 'Tavis Ford',
		'licence'  => 'CC BY 2.0',
		'lic_url'  => 'https://creativecommons.org/licenses/by/2.0',
		'source'   => 'https://commons.wikimedia.org/wiki/File:Lonsdale_Quay,_Vancouver_2.jpg',
	),
	'port-coquitlam' => array(
		'landmark' => 'Coast Meridian Overpass, Port Coquitlam',
		'alt'      => 'The Coast Meridian Overpass bridge in Port Coquitlam, BC — BuckleUp Driving School driving lessons in Port Coquitlam',
		'artist'   => 'Northwest',
		'licence'  => 'CC BY 4.0',
		'lic_url'  => 'https://creativecommons.org/licenses/by/4.0',
		'source'   => 'https://commons.wikimedia.org/wiki/File:Coast_Meridian_Overpass.jpg',
	),
	'port-moody' => array(
		'landmark' => 'Rocky Point Park, Port Moody',
		'alt'      => 'Rocky Point Park on Burrard Inlet in Port Moody, BC — BuckleUp Driving School driving lessons in Port Moody',
		'artist'   => 'Eviatar Bach',
		'licence'  => 'CC BY-SA 3.0',
		'lic_url'  => 'https://creativecommons.org/licenses/by-sa/3.0',
		'source'   => 'https://commons.wikimedia.org/wiki/File:Rocky_Point_Park_16.JPG',
	),
	'tri-cities' => array(
		'landmark' => 'Buntzen Lake, Tri-Cities (Anmore)',
		'alt'      => 'Buntzen Lake and the Coast Mountains in the Tri-Cities, BC — BuckleUp Driving School serves Coquitlam, Port Coquitlam and Port Moody',
		'artist'   => 'Jennifer C.',
		'licence'  => 'CC BY 2.0',
		'lic_url'  => 'https://creativecommons.org/licenses/by/2.0',
		'source'   => 'https://commons.wikimedia.org/wiki/File:Buntzen_Lake_(9326084690).jpg',
	),
);

foreach ( $heroes as $slug => $m ) {
	$title   = $m['landmark'] . ' — BuckleUp Driving School';
	$caption = sprintf( '%s. Photo: %s (%s), via Wikimedia Commons.', $m['landmark'], $m['artist'], $m['licence'] );
	$descr   = sprintf(
		'%s. Photo by %s, licensed under %s (%s). Source: %s.',
		$m['landmark'], $m['artist'], $m['licence'], $m['lic_url'], $m['source']
	);

	// Idempotency: reuse an existing tagged attachment.
	$existing = get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_bu_location_hero',
		'meta_value'  => $slug,
		'numberposts' => 1,
		'fields'      => 'ids',
	) );

	$id = $existing ? (int) $existing[0] : 0;

	if ( ! $id ) {
		// __DIR__-relative so it works both in dev (/scripts/wp/elementor) and from the
		// off-webroot deploy dir on prod (e.g. /home2/yuwaeymy/bu-deploy/elementor).
		$src = __DIR__ . "/assets/heroes/{$slug}.webp";
		if ( ! file_exists( $src ) ) {
			echo "MISSING SOURCE: {$src}\n";
			continue;
		}
		// Copy to a temp file (media_handle_sideload moves the file).
		$tmp = wp_tempnam( "{$slug}.webp" );
		copy( $src, $tmp );
		$file_array = array(
			'name'     => "{$slug}-driving-school-buckleup.webp",
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			echo "ERROR {$slug}: " . $id->get_error_message() . "\n";
			continue;
		}
		update_post_meta( $id, '_bu_location_hero', $slug );
	}

	// (Re)apply alt + title + attribution every run so they stay correct.
	update_post_meta( $id, '_wp_attachment_image_alt', $m['alt'] );
	wp_update_post( array(
		'ID'           => $id,
		'post_title'   => $title,
		'post_excerpt' => $caption,
		'post_content' => $descr,
	) );
	// Structured credit meta — read by the [buckleup_image_credits] shortcode so the
	// CC attribution (TASL) renders on the /image-credits page + footer line.
	update_post_meta( $id, '_bu_credit_landmark', $m['landmark'] );
	update_post_meta( $id, '_bu_credit_artist', $m['artist'] );
	update_post_meta( $id, '_bu_credit_license', $m['licence'] );
	update_post_meta( $id, '_bu_credit_license_url', $m['lic_url'] );
	update_post_meta( $id, '_bu_credit_source', $m['source'] );

	echo "{$slug}|{$id}|" . wp_get_attachment_url( $id ) . "\n";
}
