<?php
/**
 * Sideloads the brand assets from the source Next.js public/ folder into the WP
 * media library, so the theme/footer/hero reference real attachment IDs.
 * Idempotent by source filename. Run via: wp eval-file /scripts/wp/import-media.php
 *
 * Expects the source public/ assets to be mounted/copied to /wp-data/media-import.
 * (provision.sh can copy them; otherwise drop files there manually.)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$dir = '/wp-data/media-import';
if ( ! is_dir( $dir ) ) {
	WP_CLI::warning( "No media dir at {$dir}; copy source public/ assets there to import. Skipping." );
	return;
}

$wanted = array(
	'logo.png', 'logo-dark.png', 'image2.png', 'hero_card_image.png',
	'farhad-instructor.jpg', 'icon-192x192.png', 'icon-512x512.png', 'apple-touch-icon.png',
);

foreach ( $wanted as $file ) {
	$path = "{$dir}/{$file}";
	if ( ! file_exists( $path ) ) { continue; }

	// Skip if already imported (match by _bu_source_file meta).
	$existing = get_posts( array(
		'post_type' => 'attachment', 'posts_per_page' => 1, 'fields' => 'ids',
		'meta_key' => '_bu_source_file', 'meta_value' => $file,
	) );
	if ( $existing ) { WP_CLI::log( "  media exists: {$file}" ); continue; }

	$tmp = wp_tempnam( $file );
	copy( $path, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) { @unlink( $tmp ); WP_CLI::warning( "skip {$file}: " . $id->get_error_message() ); continue; }
	update_post_meta( $id, '_bu_source_file', $file );

	// Set the site logo from logo.png.
	if ( 'logo.png' === $file ) { set_theme_mod( 'custom_logo', $id ); }
	WP_CLI::log( "  imported: {$file} (#{$id})" );
}

WP_CLI::success( 'Brand media imported.' );
