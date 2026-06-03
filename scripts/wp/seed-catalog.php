<?php
/**
 * Seeds the bookable catalog: Services (3) + Packages (2), mirroring prisma/seed.ts.
 * Assumes the buckleup-core plugin has registered the 'bu_service' and 'bu_package'
 * custom post types (built by the data/booking team). Idempotent by slug.
 *
 * Run via: wp eval-file /scripts/wp/seed-catalog.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function buckleup_upsert_cpt( $type, $slug, $title, $meta = array(), $content = '' ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	$data = array(
		'post_type'   => $type,
		'post_name'   => $slug,
		'post_title'  => $title,
		'post_status' => 'publish',
		'post_content'=> $content,
	);
	if ( $existing ) { $data['ID'] = $existing->ID; }
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) { WP_CLI::warning( "skip {$slug}: " . $id->get_error_message() ); return 0; }
	foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
	WP_CLI::log( "  {$type} ok: {$title}" );
	return $id;
}

if ( ! post_type_exists( 'bu_service' ) || ! post_type_exists( 'bu_package' ) ) {
	WP_CLI::warning( 'CPTs bu_service/bu_package not registered yet — activate buckleup-core first. Skipping catalog seed.' );
	return;
}

// SERVICES (from seed.ts)
buckleup_upsert_cpt( 'bu_service', 'single-lesson', 'Single Driving Lesson', array(
	'bu_type' => 'LESSON', 'bu_duration' => 90, 'bu_price' => '75.00', 'bu_sort_order' => 1, 'bu_is_active' => 1,
), 'Extended driving lesson.' );

buckleup_upsert_cpt( 'bu_service', 'road-test-prep', 'Road Test Preparation', array(
	'bu_type' => 'TEST_PREP', 'bu_duration' => 120, 'bu_price' => '120.00', 'bu_sort_order' => 2, 'bu_is_active' => 1,
), 'ICBC road test preparation.' );

buckleup_upsert_cpt( 'bu_service', 'highway-driving', 'Highway Driving', array(
	'bu_type' => 'SPECIALIZED', 'bu_duration' => 120, 'bu_price' => '100.00', 'bu_sort_order' => 3, 'bu_is_active' => 1,
), 'Highway and merging practice.' );

// PACKAGES (from seed.ts) — items stored as serialized array of [service_slug => qty]
buckleup_upsert_cpt( 'bu_package', 'beginner-package', 'Beginner Package', array(
	'bu_total_hours' => 10, 'bu_price' => '650.00', 'bu_discount_pct' => 13, 'bu_is_popular' => 1, 'bu_is_active' => 1,
	'bu_items' => array( array( 'service' => 'single-lesson', 'quantity' => 6 ), array( 'service' => 'highway-driving', 'quantity' => 1 ) ),
) );

buckleup_upsert_cpt( 'bu_package', 'advanced-package', 'Advanced Package', array(
	'bu_total_hours' => 20, 'bu_price' => '1200.00', 'bu_discount_pct' => 20, 'bu_is_popular' => 0, 'bu_is_active' => 1,
	'bu_items' => array(),
) );

WP_CLI::success( 'Catalog seeded: 3 services + 2 packages.' );
