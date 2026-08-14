<?php
/**
 * Seeds marketing content into the buckleup-core CPTs (verbatim from source):
 *   - testimonial (17) real Google reviews (scripts/wp/real-testimonials.php)
 *   - faq (14)         src/components/landing/FAQ.tsx
 *   - instructor (1)   prisma/seed.ts  (Farhad Sanaeifar; Sarah Mitchell removed)
 *   - location (5)     src/app/locations/<slug>/page.tsx  (hero + SEO meta, CPT)
 * Plus the static Pages (Home front page, About/Contact/Services/Blog/Resources)
 * and front-page wiring. Graduates are intentionally NOT seeded (live empty state
 * "NO GRADUATES YET"); they are uploaded by the client later.
 *
 * Idempotent (upsert by slug/title). Run via: wp eval-file /scripts/wp/seed-content.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/lib.php';

/* ===========================================================================
 * TESTIMONIALS (5) — named fallbacks, all 5-star. No photos (the Unsplash
 * placeholders are intentionally dropped per PLAN §4); theme shows the initial.
 * ========================================================================= */
if ( post_type_exists( 'testimonial' ) ) {
	// Remove the original placeholder/fake testimonials (Jason Kim et al.) —
	// replaced 2026-06-05 by the real Google reviews. Idempotent: only deletes
	// a legacy slug if it's still present.
	foreach ( array( 'jason-kim', 'amanda-liu', 'david-wang', 'sarah-martinez', 'michael-chen' ) as $old_slug ) {
		$old = bu_find_post( 'testimonial', $old_slug );
		if ( $old ) {
			wp_delete_post( $old, true );
			WP_CLI::log( "  testimonial removed (legacy fake): {$old_slug} (#{$old})" );
		}
	}

	// Real Google reviews — single source of truth (see real-testimonials.php).
	$testimonials = require __DIR__ . '/real-testimonials.php';
	$order = 0;
	foreach ( $testimonials as $t ) {
		bu_upsert_post(
			array(
				'post_type'    => 'testimonial',
				'post_name'    => $t['slug'],
				'post_title'   => $t['name'] . ' review',
				'post_content' => $t['quote'],
				'menu_order'   => $order++,
			),
			array(
				'bu_author_name' => $t['name'],
				'bu_author_role' => $t['role'],
				'bu_rating'      => (int) $t['rating'],
				'bu_is_active'   => '1',
			)
		);
	}
} else {
	WP_CLI::warning( 'CPT testimonial not registered — skipping testimonials.' );
}

/* ===========================================================================
 * FAQ (14) — verbatim from landing/FAQ.tsx. Question = title, Answer = content.
 * Single source: theme accordion + FAQPage JSON-LD both read these.
 * ========================================================================= */
if ( post_type_exists( 'faq' ) ) {
	$faqs = array(
		array( 'How long does it take to learn to drive in Vancouver?', 'Most beginner drivers in Vancouver need around six professional driving lessons to build basic skills and feel ready for the ICBC road test. Lesson plans are designed around each student’s experience and confidence level. Beginners typically start with vehicle control, parking, and safe lane changes before progressing to real traffic situations.' ),
		array( 'What vehicles are used for driving lessons?', 'We use safe, modern Toyota vehicles that are easy to control and well-suited for beginner drivers.' ),
		array( 'What is the cancellation policy for driving lessons?', 'Lessons cancelled at least 24 hours in advance receive a full refund. Late cancellations or missed appointments may result in a $35 fee.' ),
		array( 'Do you offer ICBC road test preparation?', 'Yes, we provide focused ICBC road test preparation, including parking, lane changes, intersection safety, and defensive driving techniques.' ),
		array( 'Do you offer lessons in different languages?', 'Yes, lessons are available in English and Farsi to ensure clear communication and better learning.' ),
		array( 'What areas do you serve?', 'We offer driving lessons in Vancouver, Coquitlam, Port Coquitlam, Port Moody, and North Vancouver.' ),
		array( 'How can I book lessons?', 'You can book online through the BuckleUp Driving School website by selecting a package and schedule.' ),
		array( 'What should I bring to my first lesson?', 'Bring your valid driver’s license, glasses if required, and arrive early.' ),
		array( 'How long is each lesson?', 'Each lesson typically lasts about 90 minutes, allowing time for instruction, practice, and feedback.' ),
		array( 'What will I learn in my first lesson?', 'You will learn basic vehicle control, safety awareness, steering, and road positioning in a low-traffic area.' ),
		array( 'How many lessons do I need to pass?', 'Most beginners need around six lessons, depending on their experience and confidence level.' ),
		array( 'What payment methods do you accept?', 'We accept cash and e-transfer.' ),
		array( 'Are there any hidden fees?', 'No, we provide transparent pricing with no hidden fees.' ),
		array( 'Can I get a refund for unused lessons?', 'Yes, unused lesson hours may be refunded according to our policy.' ),
	);
	$order = 0;
	foreach ( $faqs as $f ) {
		bu_upsert_post(
			array(
				'post_type'    => 'faq',
				'post_title'   => $f[0],
				'post_content' => $f[1],
				'menu_order'   => $order++,
			),
			array( 'bu_is_active' => '1' )
		);
	}
} else {
	WP_CLI::warning( 'CPT faq not registered — skipping FAQ.' );
}

/* ===========================================================================
 * INSTRUCTORS (1) — Farhad Sanaeifar. Photo: farhad-instructor.jpg (set by
 * import-media.php).
 *
 * Sarah Mitchell was removed on 2026-08-14: she came from the original
 * prisma/seed.ts and is no longer an instructor (confirmed by the client). She
 * had already been deleted from the instructor CPT on production, but the
 * instructors PAGE kept showing a stale card until it was rebuilt, and this
 * seeder would have recreated her on any fresh install or `make reset`.
 * ========================================================================= */
if ( post_type_exists( 'instructor' ) ) {
	$instructors = array(
		array(
			'slug'  => 'farhad-sanaeifar',
			'name'  => 'Farhad Sanaeifar',
			'role'  => 'Senior Instructor',
			'rating'=> 4.9,
			'bio'   => 'Farhad brings a unique blend of technical expertise and cultural understanding to his teaching. Fluent in multiple languages, he specializes in helping new immigrants adapt to Canadian driving conditions.',
			'certs' => array( 'ICBC Approved', 'Winter Driving Certified' ),
			'langs' => array( 'English', 'Farsi' ),
		),
	);
	$order = 0;
	foreach ( $instructors as $i ) {
		bu_upsert_post(
			array(
				'post_type'    => 'instructor',
				'post_name'    => $i['slug'],
				'post_title'   => $i['name'],
				'post_content' => $i['bio'],
				'menu_order'   => $order++,
			),
			array(
				'bu_role'     => $i['role'],
				'bu_rating'   => $i['rating'],
				'bu_is_active'=> '1',
			),
			array(
				'bu_certifications' => $i['certs'],
				'bu_languages'      => $i['langs'],
			)
		);
	}
} else {
	WP_CLI::warning( 'CPT instructor not registered — skipping instructors.' );
}

/* ===========================================================================
 * LOCATIONS (5) — CPT at /locations/<slug>, hero + SEO meta verbatim from
 * src/app/locations/<slug>/page.tsx. Exact slugs preserved for URL parity.
 * ========================================================================= */
if ( post_type_exists( 'location' ) ) {
	$locations = array(
		array(
			'slug'      => 'coquitlam',
			'city'      => 'Coquitlam',
			'hero'      => 'Driving Lessons in',
			'highlight' => 'Coquitlam',
			'subtitle'  => 'Master the roads of Coquitlam and Port Coquitlam. From navigating Lougheed Highway to the local testing centers, our certified instructors will guide you every step of the way.',
			'seo_title' => 'Driving Lessons in Coquitlam & Port Coquitlam',
			'seo_desc'  => 'Looking for the best driving lessons in Coquitlam and Port Coquitlam? BuckleUp Driving School offers top-tier ICBC-certified training rated 4.98★ on Google.',
		),
		array(
			'slug'      => 'north-vancouver',
			'city'      => 'North Vancouver',
			'hero'      => 'Driving School in',
			'highlight' => 'North Vancouver',
			'subtitle'  => 'Learn to drive confidently on the North Shore. Our specialized lessons prepare you for Lynn Valley test routes, bridge traffic, and tricky mountain weather conditions.',
			'seo_title' => 'Driving School in North Vancouver',
			'seo_desc'  => 'Premier driving school in North Vancouver. Learn to navigate the North Shore confidently with our ICBC-certified instructors. Start your Class 5 or Class 7 lessons today!',
		),
		array(
			'slug'      => 'port-coquitlam',
			'city'      => 'Port Coquitlam',
			'hero'      => 'Driving Lessons in',
			'highlight' => 'Port Coquitlam',
			'subtitle'  => 'Master the roads of Port Coquitlam. From navigating local neighborhoods to the test center, our certified instructors will guide you every step of the way.',
			'seo_title' => 'Driving Lessons in Port Coquitlam',
			'seo_desc'  => 'Looking for the best driving lessons in Port Coquitlam? BuckleUp Driving School offers top-tier ICBC-certified training rated 4.98★ on Google.',
		),
		array(
			'slug'      => 'port-moody',
			'city'      => 'Port Moody',
			'hero'      => 'Driving School in',
			'highlight' => 'Port Moody',
			'subtitle'  => 'Located right here in Port Moody, we specialize in helping students navigate local test routes and tricky intersections. Our ICBC-certified instructors ensure you pass your test with flying colors.',
			'seo_title' => 'Driving School in Port Moody',
			'seo_desc'  => 'Top-rated driving school in Port Moody. Master the Port Moody road test routes with our ICBC-certified instructors. Book your Class 5 or 7 lessons today!',
		),
		array(
			'slug'      => 'tri-cities',
			'city'      => 'Tri-Cities',
			'hero'      => 'Driving Lessons in the',
			'highlight' => 'Tri-Cities',
			'subtitle'  => 'Master the roads across Coquitlam, Port Coquitlam, and Port Moody. Our certified instructors will guide you every step of the way to ensure you are ready for any condition.',
			'seo_title' => 'Driving Lessons in the Tri-Cities',
			'seo_desc'  => 'Looking for the best driving lessons in the Tri-Cities? BuckleUp Driving School offers top-tier ICBC-certified training rated 4.98★ on Google, across Coquitlam, Port Coquitlam, and Port Moody.',
		),
	);
	$order = 0;
	foreach ( $locations as $l ) {
		bu_upsert_post(
			array(
				'post_type'  => 'location',
				'post_name'  => $l['slug'],
				'post_title' => $l['city'],
				'menu_order' => $order++,
			),
			array(
				'bu_hero_title'      => $l['hero'],
				'bu_hero_highlight'  => $l['highlight'],
				'bu_hero_subtitle'   => $l['subtitle'],
				'bu_seo_title'       => $l['seo_title'],
				'bu_seo_description' => $l['seo_desc'],
			)
		);
	}
} else {
	WP_CLI::warning( 'CPT location not registered — skipping locations.' );
}

/* ===========================================================================
 * STATIC PAGES + front page wiring.
 * Front page is a "Home" page rendering the home pattern; the theme owns the
 * pattern markup. Other pages are placeholders the page team fills with patterns.
 * ========================================================================= */
$home_id = bu_find_post( 'page', 'home' );
if ( ! $home_id ) {
	$home_id = wp_insert_post( wp_slash( array(
		'post_type'    => 'page',
		'post_name'    => 'home',
		'post_title'   => 'Home',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:pattern {"slug":"buckleup/home"} /-->',
	) ) );
}
$page_ids = array();
foreach ( array(
	'about'     => 'About Us',
	'contact'   => 'Contact',
	'services'  => 'Services',
	'instructors' => 'Instructors',
	'blog'      => 'Blog',
	'resources' => 'Resources',
) as $slug => $title ) {
	$pid = bu_find_post( 'page', $slug );
	if ( ! $pid ) {
		$pid = wp_insert_post( wp_slash( array(
			'post_type'   => 'page',
			'post_name'   => $slug,
			'post_title'  => $title,
			'post_status' => 'publish',
		) ) );
	}
	$page_ids[ $slug ] = (int) $pid;
}

/* Reading settings: static "Home" front page + "Blog" posts page. With the
 * /blog/%postname%/ permalink, /blog/ becomes the post archive and posts resolve
 * at /blog/{slug}/ — and the post-slug no longer collides with top-level pages. */
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', (int) $home_id );
if ( ! empty( $page_ids['blog'] ) ) {
	update_option( 'page_for_posts', $page_ids['blog'] );
}

/* ===========================================================================
 * RESOURCES article — Top 5 Reasons Students Fail the ICBC Road Test.
 * Child of the Resources page so it resolves at /resources/icbc-road-test-failures/
 * (live site has it; our site 404'd without it). Content verbatim from source
 * src/app/resources/icbc-road-test-failures/page.tsx, authored as semantic HTML
 * for the theme's .prose page template. CTA links to /#most-popular (the popular
 * package anchor the theme owns) per the agreed routing.
 * ========================================================================= */
if ( ! empty( $page_ids['resources'] ) ) {
	$icbc_html = <<<'HTML'
<p>Our instructors know exactly what ICBC examiners are looking for. Here is why most test-takers fail, and how we ensure you don't.</p>

<h2>1. Rolling Through Stop Signs</h2>
<p><strong>The Mistake:</strong> It sounds simple, but a complete stop means absolute zero forward momentum behind the line. A 'Hollywood roll' is an automatic failure.</p>
<p><strong>The BuckleUp Fix:</strong> We drill the 'Stop, Think, Scan' method until coming to a complete, full-second stop becomes pure muscle memory.</p>

<h2>2. Failing to Shoulder Check</h2>
<p><strong>The Mistake:</strong> Missing a shoulder check before merging, turning, or pulling out is the single most common reason for demerits. Examiners watch your head movements closely.</p>
<p><strong>The BuckleUp Fix:</strong> Our instructors build the mirror-signal-shoulder check sequence into every single maneuver from your very first lesson.</p>

<h2>3. Speed Maintenance in School Zones</h2>
<p><strong>The Mistake:</strong> Hitting 35km/h in a 30km/h school or playground zone during restricted hours is an instant fail. Nerves often cause students to lose track of their speed.</p>
<p><strong>The BuckleUp Fix:</strong> We map out and practice on actual local test routes (like Port Moody and Burnaby) so you know exactly where the traps are before test day.</p>

<h2>4. Poor Gap Selection When Merging</h2>
<p><strong>The Mistake:</strong> Hesitating when it's safe to turn, or pulling out into a gap that is too small, shows the examiner a lack of spatial awareness and confidence.</p>
<p><strong>The BuckleUp Fix:</strong> We use structured exposure therapy on busier roads to safely build your confidence in assessing speed and distance of oncoming traffic.</p>

<h2>5. Improper Left Turns on Yellow</h2>
<p><strong>The Mistake:</strong> Getting 'stuck' in the intersection on a red light because you failed to establish properly, or turning when oncoming traffic hasn't fully stopped.</p>
<p><strong>The BuckleUp Fix:</strong> We teach precise positioning and the 'point of no return' framework so you never have to guess when it's safe to clear the intersection.</p>

<h2>Ready to Pass on Your First Try?</h2>
<p>Don't leave your licence to chance. Work through these with an ICBC-certified instructor before your road test the very first time.</p>
HTML;

	// CTA: the theme's primary Button look (default/lg variant) as a plain anchor
	// so it inherits the correct primary colors + AA contrast (replacing the
	// Gutenberg button block that rendered #0b64f4 on #32373c = 2.37, fails AA).
	// SVG-only arbitrary variants are omitted (this button has no icon) — that
	// also avoids HTML-entity noise in the class attribute. All utilities here are
	// already in the compiled theme CSS (buckleup_button_class default/lg).
	$cta_class = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg font-semibold transition-all duration-200 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] bg-primary text-primary-foreground shadow-md hover:bg-primary/90 hover:shadow-lg h-11 px-8 text-base';
	$icbc_html .= "\n<p><a class=\"{$cta_class}\" href=\"/#most-popular\">Book Road Test Prep</a></p>";

	// Self-heal any dedup-suffixed duplicates (icbc-road-test-failures-2, -3, …)
	// left by an earlier leaf-slug lookup bug.
	$icbc_dupes = get_posts( array(
		'post_type'      => 'page',
		'post_parent'    => $page_ids['resources'],
		'post_status'    => 'any',
		'posts_per_page' => -1,
	) );
	foreach ( $icbc_dupes as $dupe ) {
		if ( preg_match( '/^icbc-road-test-failures-\d+$/', $dupe->post_name ) ) {
			wp_delete_post( $dupe->ID, true );
			WP_CLI::log( "  removed duplicate ICBC page: {$dupe->post_name} (#{$dupe->ID})" );
		}
	}

	// Child page: get_page_by_path needs the FULL hierarchical path, not the leaf
	// slug, or the lookup misses and each run creates a -2/-3 duplicate.
	$icbc_existing = get_page_by_path( 'resources/icbc-road-test-failures', OBJECT, 'page' );
	$icbc_id = $icbc_existing ? (int) $icbc_existing->ID : 0;
	$icbc_data = array(
		'post_type'    => 'page',
		'post_name'    => 'icbc-road-test-failures',
		'post_title'   => 'Top 5 Reasons Students Fail the ICBC Road Test',
		'post_status'  => 'publish',
		'post_parent'  => $page_ids['resources'],
		'post_content' => $icbc_html,
	);
	if ( $icbc_id ) { $icbc_data['ID'] = $icbc_id; }
	$icbc_id = wp_insert_post( wp_slash( $icbc_data ), true );
	if ( is_wp_error( $icbc_id ) ) {
		WP_CLI::warning( '  icbc-road-test-failures: ' . $icbc_id->get_error_message() );
	} else {
		// Rank Math per-page meta (verbatim source title/description).
		update_post_meta( $icbc_id, 'rank_math_title', 'Top 5 Reasons Students Fail the ICBC Road Test | BuckleUp' );
		update_post_meta( $icbc_id, 'rank_math_description', 'Learn the exact reasons why Vancouver students fail their ICBC Class 5 and Class 7 road tests, and how BuckleUp Driving School helps you avoid them.' );
		WP_CLI::log( "  resources article: icbc-road-test-failures (#{$icbc_id}) child of Resources #{$page_ids['resources']}" );
	}
}

/* ===========================================================================
 * Remove the redundant location PAGES that collide with the canonical `location`
 * CPT (/locations/{slug}). An older scaffold created a "Locations" parent page
 * with 5 city children; the CPT (registered by buckleup-core) now owns
 * /locations/ and /locations/{slug}, so these pages are double content. We
 * force-delete the 5 children (matched by slug + their parent being the
 * "locations" page) then the parent. The `location` CPT entries are untouched.
 * Idempotent: no-ops once the pages are gone.
 * ========================================================================= */
$loc_parent = get_page_by_path( 'locations', OBJECT, 'page' );
if ( $loc_parent ) {
	$city_slugs = array( 'north-vancouver', 'coquitlam', 'port-coquitlam', 'port-moody', 'tri-cities' );
	$children = get_posts( array(
		'post_type'      => 'page',
		'post_parent'    => $loc_parent->ID,
		'post_status'    => 'any',
		'posts_per_page' => -1,
	) );
	foreach ( $children as $child ) {
		if ( in_array( $child->post_name, $city_slugs, true ) ) {
			wp_delete_post( $child->ID, true );
			WP_CLI::log( "  removed redundant location page: {$child->post_name} (#{$child->ID})" );
		}
	}
	wp_delete_post( $loc_parent->ID, true );
	WP_CLI::log( "  removed redundant 'Locations' parent page (#{$loc_parent->ID})" );
}

/* ===========================================================================
 * Remove WordPress's default sample content for parity (idempotent — only
 * trashes them if they still carry their default slug + default status).
 * ========================================================================= */
$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
if ( $hello && 'publish' === $hello->post_status ) {
	wp_trash_post( $hello->ID );
	WP_CLI::log( "  removed default 'Hello world!' post (#{$hello->ID})" );
}
$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
if ( $sample ) {
	wp_trash_post( $sample->ID );
	WP_CLI::log( "  removed default 'Sample Page' (#{$sample->ID})" );
}

WP_CLI::success( 'Content seeded: 17 testimonials, 14 FAQ, 2 instructors, 5 locations, static pages.' );
