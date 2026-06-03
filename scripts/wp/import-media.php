<?php
/**
 * Sideloads the brand assets from the source Next.js public/ folder into the WP
 * Media Library, sets the Site Icon, wires the light/dark logo theme mods, and
 * attaches Farhad's photo to his instructor CPT.
 *
 * Source files must be staged at /wp-data/media-import (provision.sh copies them
 * from /Users/esfandiyar/Projects/Buckleup/public; the wpcli service mounts
 * ./wp-data -> /wp-data). Idempotent: re-import is matched by _bu_source_file meta.
 *
 * Run via: wp eval-file /scripts/wp/import-media.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$dir = '/wp-data/media-import';
if ( ! is_dir( $dir ) ) {
	WP_CLI::warning( "No media dir at {$dir}; provision.sh stages source public/ assets there. Skipping media import." );
	return;
}

/**
 * Import one file (idempotent by _bu_source_file). Returns attachment ID or 0.
 * $title sets a friendly attachment title/alt.
 */
function bu_import_media( $dir, $file, $title = '' ) {
	$path = "{$dir}/{$file}";
	if ( ! file_exists( $path ) ) {
		WP_CLI::log( "  (absent in source, skipped): {$file}" );
		return 0;
	}

	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_bu_source_file',
		'meta_value'     => $file,
		'no_found_rows'  => true,
	) );
	if ( $existing ) {
		WP_CLI::log( "  media exists: {$file} (#{$existing[0]})" );
		return (int) $existing[0];
	}

	$tmp = wp_tempnam( $file );
	if ( ! @copy( $path, $tmp ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "  copy failed: {$file}" );
		return 0;
	}
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "  skip {$file}: " . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_bu_source_file', $file );
	if ( $title ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
	}
	WP_CLI::log( "  imported: {$file} (#{$id})" );
	return (int) $id;
}

/* Brand media: filename => friendly title. Mirrors source public/. */
$wanted = array(
	'logo.png'              => 'BuckleUp Driving School logo (light)',
	'logo-dark.png'         => 'BuckleUp Driving School logo (dark)',
	'image2.png'            => 'BuckleUp hero background',
	'hero_card_image.png'   => 'BuckleUp hero card',
	'farhad-instructor.jpg' => 'Farhad Sanaeifar — Senior Instructor',
	'owner_withcar.png'     => 'BuckleUp owner with car',
	'icon-16x16.png'        => 'BuckleUp icon 16',
	'icon-32x32.png'        => 'BuckleUp icon 32',
	'icon-192x192.png'      => 'BuckleUp icon 192',
	'icon-512x512.png'      => 'BuckleUp icon 512',
	'apple-touch-icon.png'  => 'BuckleUp Apple touch icon',
	// Per-category blog featured-image cards (scripts/gen-blog-cards.sh).
	'blog-card-tips.png'      => 'BuckleUp Driving School — Driving Tips',
	'blog-card-tutorials.png' => 'BuckleUp Driving School — Step-by-Step Tutorials',
	'blog-card-safety.png'    => 'BuckleUp Driving School — Road Safety',
	'blog-card-local.png'     => 'BuckleUp Driving School — Local Routes & Areas',
	'blog-card-licensing.png' => 'BuckleUp Driving School — ICBC Licensing',
);

$ids = array();
foreach ( $wanted as $file => $title ) {
	$id = bu_import_media( $dir, $file, $title );
	if ( $id ) { $ids[ $file ] = $id; }
}

/* Logo theme mods (light + dark). The theme reads custom_logo and a custom
 * 'buckleup_logo_dark' theme mod for the dark-mode swap. */
if ( ! empty( $ids['logo.png'] ) ) {
	set_theme_mod( 'custom_logo', $ids['logo.png'] );
}
if ( ! empty( $ids['logo-dark.png'] ) ) {
	set_theme_mod( 'buckleup_logo_dark', $ids['logo-dark.png'] );
}

/* Site Icon (favicon / PWA) from the 512px icon. WP derives all sizes from it. */
if ( ! empty( $ids['icon-512x512.png'] ) ) {
	update_option( 'site_icon', $ids['icon-512x512.png'] );
}

/* Attach Farhad's photo to his instructor CPT as the featured image. */
if ( ! empty( $ids['farhad-instructor.jpg'] ) && post_type_exists( 'instructor' ) ) {
	$farhad = bu_find_post( 'instructor', 'farhad-sanaeifar', 'Farhad Sanaeifar' );
	if ( $farhad ) {
		set_post_thumbnail( $farhad, $ids['farhad-instructor.jpg'] );
		WP_CLI::log( "  set Farhad's instructor photo (#{$ids['farhad-instructor.jpg']} -> post #{$farhad})" );
	}
}

WP_CLI::success( 'Brand media imported (logos, hero, icons) + Site Icon + logo theme mods set.' );
