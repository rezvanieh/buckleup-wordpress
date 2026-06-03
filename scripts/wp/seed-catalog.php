<?php
/**
 * Seeds the marketing catalog: Services (3) + home Pricing Packages (4).
 *
 * Writes to the `service` and `package` CPTs and the bu_* meta keys defined in
 * docs/CONTENT-MODEL.md. Values are verbatim from the source:
 *   - Services: prisma/seed.ts (Single Lesson $75 / Road Test Prep $120 / Highway $100)
 *   - Packages: src/components/landing/Pricing.tsx (the 4 home plans $100/$360/$480/$620)
 *
 * Idempotent (upsert by slug/title). Run via: wp eval-file /scripts/wp/seed-catalog.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

if ( ! post_type_exists( 'service' ) || ! post_type_exists( 'package' ) ) {
	WP_CLI::warning( "CPTs service/package not registered yet — activate buckleup-core first. Skipping catalog seed." );
	return;
}

/* ---------------------------------------------------------------------------
 * SERVICES (3) — from prisma/seed.ts
 * ------------------------------------------------------------------------- */
$services = array(
	array(
		'title'       => 'Single Driving Lesson',
		'slug'        => 'single-lesson',
		'description' => 'One-on-one driving instruction (1.5 hours)',
		'type'        => 'LESSON',
		'duration'    => 90,
		'price'       => 75,
		'order'       => 1,
	),
	array(
		'title'       => 'Road Test Preparation',
		'slug'        => 'road-test-prep',
		'description' => 'Comprehensive road test preparation session (2 hours)',
		'type'        => 'TEST_PREP',
		'duration'    => 120,
		'price'       => 120,
		'order'       => 2,
	),
	array(
		'title'       => 'Highway Driving',
		'slug'        => 'highway-driving',
		'description' => 'Highway driving skills and confidence building (2 hours)',
		'type'        => 'SPECIALIZED',
		'duration'    => 120,
		'price'       => 100,
		'order'       => 3,
	),
);

foreach ( $services as $s ) {
	bu_upsert_post(
		array(
			'post_type'    => 'service',
			'post_name'    => $s['slug'],
			'post_title'   => $s['title'],
			'post_content' => $s['description'],
			'menu_order'   => $s['order'],
		),
		array(
			'bu_service_type' => $s['type'],
			'bu_duration'     => $s['duration'],
			'bu_price'        => $s['price'],
			'bu_is_active'    => '1',
		)
	);
}

/* ---------------------------------------------------------------------------
 * PACKAGES (4) — home pricing plans, verbatim from landing/Pricing.tsx
 * Feature bullets preserved verbatim minus the source's empty-string bullet.
 * The "+$NN for car on road test" line is kept as a feature AND parsed into
 * bu_car_fee. WhatsApp link is NOT stored (derived server-side by the plugin).
 * ------------------------------------------------------------------------- */
$packages = array(
	array(
		'title'       => 'Single Session',
		'slug'        => 'single-session',
		'description' => 'Extended driving lesson',
		'price'       => 100,
		'unit'        => 'lesson',
		'sessions'    => 1,
		'hours'       => 1.5,
		'car_fee'     => '',
		'popular'     => '',
		'cta'         => 'Book Now',
		'features'    => array(
			'90-minute driving lesson',
			'Patient instructor',
			'Progress tracking',
		),
	),
	array(
		'title'       => '4 Sessions Package',
		'slug'        => '4-sessions-package',
		'description' => 'Great starter package',
		'price'       => 360,
		'unit'        => 'package',
		'sessions'    => 4,
		'hours'       => 6,
		'car_fee'     => 50,
		'popular'     => '',
		'cta'         => 'Get Started',
		'features'    => array(
			'4 sessions (90 min each)',
			'6 hours total driving',
			'+$50 for car on road test',
		),
	),
	array(
		'title'       => '6 Sessions Package',
		'slug'        => '6-sessions-package',
		'description' => 'Most popular choice',
		'price'       => 480,
		'unit'        => 'package',
		'sessions'    => 6,
		'hours'       => 9,
		'car_fee'     => 40,
		'popular'     => '1',
		'cta'         => 'Get Started',
		'features'    => array(
			'6 sessions (90 min each)',
			'9 hours total driving',
			'+$40 for car on road test',
		),
	),
	array(
		'title'       => '8 Sessions Package',
		'slug'        => '8-sessions-package',
		'description' => 'Complete preparation',
		'price'       => 620,
		'unit'        => 'package',
		'sessions'    => 8,
		'hours'       => 12,
		'car_fee'     => 30,
		'popular'     => '',
		'cta'         => 'Best Value',
		'features'    => array(
			'8 sessions (90 min each)',
			'12 hours total driving',
			'+$30 for car on road test',
		),
	),
);

$order = 0;
foreach ( $packages as $p ) {
	bu_upsert_post(
		array(
			'post_type'    => 'package',
			'post_name'    => $p['slug'],
			'post_title'   => $p['title'],
			'post_content' => $p['description'],
			'menu_order'   => $order++,
		),
		array(
			'bu_price'      => $p['price'],
			'bu_unit'       => $p['unit'],
			'bu_sessions'   => $p['sessions'],
			'bu_hours'      => $p['hours'],
			'bu_car_fee'    => $p['car_fee'],
			'bu_is_popular' => $p['popular'],
			'bu_cta_label'  => $p['cta'],
			'bu_is_active'  => '1',
		),
		array(
			'bu_features' => $p['features'],
		)
	);
}

WP_CLI::success( 'Catalog seeded: 3 services + 4 home pricing packages.' );
