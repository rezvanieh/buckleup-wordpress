<?php
/**
 * Migrates the real Hall-of-Fame graduate photos from the live site into the
 * `graduate` CPT (client-approved — their own public images), closing the
 * home/locations empty-state parity gap.
 *
 * Source: https://www.buckleupdriving.ca/api/graduates (active GraduateImage rows).
 * provision.sh fetches that JSON + downloads each image into /wp-data/graduates/
 * (host-side, where outbound internet is available) and writes a manifest.json
 * there; this script (run in the wpcli container) side-loads the staged files.
 *
 * Each row → one `graduate` CPT post:
 *   post_title   = source title, else a stable "BuckleUp Graduate N" (source
 *                  titles are null — these are gallery images, no captions)
 *   post_content = source description (usually empty)
 *   menu_order   = source order
 *   bu_is_active = '1'
 *   featured image = the downloaded photo (matched/deduped by source basename)
 *
 * Idempotent: upsert by the source image basename (stored in _bu_grad_source).
 * Run via: wp eval-file /scripts/wp/seed-graduates.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if ( ! post_type_exists( 'graduate' ) ) {
	WP_CLI::warning( 'CPT graduate not registered — activate buckleup-core first. Skipping graduates.' );
	return;
}

$dir      = '/wp-data/graduates';
$manifest = "{$dir}/manifest.json";
if ( ! file_exists( $manifest ) ) {
	WP_CLI::warning( "No graduates manifest at {$manifest}; provision.sh fetches the live /api/graduates there. Skipping." );
	return;
}

$rows = json_decode( file_get_contents( $manifest ), true );
if ( ! is_array( $rows ) ) {
	WP_CLI::warning( "Could not parse {$manifest} (invalid JSON). Skipping graduates." );
	return;
}

/** Find an existing graduate by its source-image basename. */
function bu_grad_by_source( $basename ) {
	$q = get_posts( array(
		'post_type'      => 'graduate',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_bu_grad_source',
		'meta_value'     => $basename,
		'no_found_rows'  => true,
	) );
	return $q ? (int) $q[0] : 0;
}

/** Side-load a staged image file, returning the attachment ID (deduped by basename). */
function bu_grad_sideload( $dir, $basename, $title ) {
	// Reuse an already-imported attachment for this source file.
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_bu_source_file',
		'meta_value'     => $basename,
		'no_found_rows'  => true,
	) );
	if ( $existing ) { return (int) $existing[0]; }

	$path = "{$dir}/{$basename}";
	if ( ! file_exists( $path ) ) { return 0; }
	$tmp = wp_tempnam( $basename );
	if ( ! @copy( $path, $tmp ) ) { @unlink( $tmp ); return 0; }
	$id = media_handle_sideload( array( 'name' => $basename, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) { @unlink( $tmp ); WP_CLI::warning( "  sideload {$basename}: " . $id->get_error_message() ); return 0; }
	update_post_meta( $id, '_bu_source_file', $basename );
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	return (int) $id;
}

$n = 0;
foreach ( array_values( $rows ) as $i => $row ) {
	$url = isset( $row['url'] ) ? $row['url'] : '';
	if ( ! $url ) { continue; }
	$basename = basename( parse_url( $url, PHP_URL_PATH ) );
	if ( ! $basename ) { continue; }

	$title = '';
	if ( ! empty( $row['title'] ) ) {
		$title = (string) $row['title'];
	}
	if ( '' === $title ) {
		$title = 'BuckleUp Graduate ' . ( $i + 1 );
	}
	$desc  = isset( $row['description'] ) && $row['description'] ? (string) $row['description'] : '';
	$order = isset( $row['order'] ) ? (int) $row['order'] : 0;

	$att_id = bu_grad_sideload( $dir, $basename, $title );
	if ( ! $att_id ) {
		WP_CLI::warning( "  graduate {$basename}: image not staged/sideloaded, skipping row." );
		continue;
	}

	$existing = bu_grad_by_source( $basename );
	$data = array(
		'post_type'    => 'graduate',
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => 'publish',
		'menu_order'   => $order,
	);
	if ( $existing ) { $data['ID'] = $existing; }

	$pid = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $pid ) ) { WP_CLI::warning( "  graduate {$basename}: " . $pid->get_error_message() ); continue; }

	update_post_meta( $pid, '_bu_grad_source', $basename );
	update_post_meta( $pid, 'bu_is_active', '1' );
	set_post_thumbnail( $pid, $att_id );

	$verb = $existing ? 'updated' : 'created';
	WP_CLI::log( "  graduate {$verb}: {$title} (#{$pid}) <- {$basename} (img #{$att_id})" );
	$n++;
}

WP_CLI::success( "Graduates migrated: {$n} Hall-of-Fame photo(s) into the graduate CPT." );
