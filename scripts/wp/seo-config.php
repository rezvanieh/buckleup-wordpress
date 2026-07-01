<?php
/**
 * BuckleUp — Rank Math + Redirection + PWA SEO provisioning (idempotent).
 *
 * Run via:  wp eval-file /scripts/wp/seo-config.php
 *
 * Owns the RUNTIME SEO configuration that lives in plugin options (the schema /
 * geo-meta layer lives in the 10-buckleup-seo.php mu-plugin, not here):
 *
 *   1. rank_math_titles   — title/description templates, homepage SEO, the
 *                           verbatim per-page titles/descriptions, OG/Twitter
 *                           defaults, robots, per-post-type & per-CPT title
 *                           templates, breadcrumbs ON.
 *   2. rank-math-options-sitemap — a COMPLETE sitemap: posts, pages, AND the public
 *                           Location CPT (fixes the source's 3-URL sitemap). NOTE:
 *                           Rank Math reads the HYPHENATED option key, not the
 *                           underscore `rank_math_sitemap` — see the write below.
 *   3. rank_math_general  — self-referential canonicals, strip-category-base,
 *                           attachment redirect. Rank Math's OWN redirections +
 *                           404-monitor modules are DISABLED (the standalone
 *                           Redirection plugin owns redirects + 404 logging per
 *                           PLAN §2; leaving RM's on floods debug.log looking for
 *                           a redirections-cache table and double-handles 301s).
 *   4. Per-page meta      — writes the verbatim title/description onto the actual
 *                           Pages/CPT posts when they exist (matched by slug), so
 *                           inner pages stop inheriting the homepage values.
 *   5. Redirection rules  — apex→www is server-level; here we seed the legacy
 *                           URL-parity + trailing-slash 301s and 404 logging.
 *   6. PWA manifest+robots — manifest.json + a sitemap-aware robots.txt via WP
 *                           filters (registered in the mu-plugin companion below).
 *
 * Safe to re-run: every write is an update_option / idempotent upsert.
 *
 * @package BuckleUp_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

WP_CLI::log( '  -> BuckleUp SEO config starting...' );

/* =========================================================================
 * 0. Mark the Rank Math setup wizard as complete.
 *    Without this, Rank Math stays in a limited post-install state and its
 *    FRONTEND head module never engages — no managed <title>, meta description,
 *    OG/Twitter, breadcrumbs, or sitemap. These flags are what a human clicking
 *    through the wizard would set; doing it here keeps provisioning headless.
 * ====================================================================== */

update_option( 'rank_math_registration_skip', true );
update_option( 'rank_math_wizard_completed', true );
update_option( 'rank_math_remembered_consent', false );
WP_CLI::log( '     Rank Math wizard marked complete (frontend head engaged).' );

/* =========================================================================
 * 1. Rank Math — Titles & Meta
 * ====================================================================== */

$sep = '|';

$titles = array(
	// Global.
	'noindex_empty_taxonomies' => 'on',
	'title_separator'          => $sep,
	'capitalize_titles'        => 'off',

	// Homepage SEO (verbatim from src/app/layout.tsx).
	'homepage_title'           => 'Best Driving School Vancouver | BuckleUp Driving School',
	'homepage_description'     => 'BuckleUp Driving School is a top driving school Vancouver learners trust. Driving lessons are available in Vancouver, Tri-Cities, Coquitlam, Port Moody, and North Vancouver. Book today!',
	'homepage_facebook_title'  => 'BuckleUp Driving School - Vancouver\'s Premier Driving Academy',
	'homepage_facebook_description' => 'Vancouver\'s premier driving academy with ICBC-certified instructors, modern vehicles, and a 98% first-time pass rate.',
	'homepage_custom_robots'   => 'on',
	'homepage_robots'          => array( 'index', 'follow' ),

	// Social defaults (verbatim OG/Twitter from layout.tsx). The default OG image
	// (the brand logo, 1200x630 in the source) is wired below once we resolve the
	// imported attachment so it survives a fresh Media Library.
	'twitter_card_type'        => 'summary_large_image',
	'social_url_facebook'      => 'https://www.facebook.com/DriveMasterca',
	'social_url_instagram'     => 'https://www.instagram.com/budrivingschool',
	'social_additional_profiles' => "https://wa.me/16044413677\n",

	// Local SEO knowledge-graph (Rank Math fills its own LocalBusiness if enabled;
	// our mu-plugin owns the authoritative multi-type node, so keep RM's type as
	// the org but do not let it duplicate — we disable RM's schema on the homepage
	// via the rank_math/json_ld filter in the mu-plugin companion).
	'knowledgegraph_type'      => 'company',
	'knowledgegraph_name'      => 'BuckleUp Driving School',
	'local_business_type'      => 'DrivingSchool',
	'phone_numbers'            => array(
		array(
			'type'   => 'customer support',
			'number' => '+1-604-441-3677',
		),
	),

	// --- Post (blog) ---
	'pt_post_title'            => '%title% %sep% %sitename%',
	'pt_post_description'      => '%excerpt%',
	'pt_post_robots'           => array( 'index', 'follow' ),
	'pt_post_custom_robots'    => 'off',
	'pt_post_default_rich_snippet' => 'article',
	'pt_post_default_article_type' => 'BlogPosting',
	'pt_post_default_snippet_name'        => '%seo_title%',
	'pt_post_default_snippet_desc'        => '%seo_description%',
	'pt_post_facebook_image'   => '',
	'pt_post_add_meta_box'     => 'on',
	'pt_post_link_suggestions' => 'off',

	// --- Page ---
	'pt_page_title'            => '%title% %sep% %sitename% Vancouver',
	'pt_page_description'      => '%excerpt%',
	'pt_page_robots'           => array( 'index', 'follow' ),
	'pt_page_custom_robots'    => 'off',
	'pt_page_default_rich_snippet' => 'off',
	'pt_page_add_meta_box'     => 'on',

	// --- Location CPT (the only public marketing CPT) ---
	'pt_location_title'        => '%title% %sep% %sitename% Vancouver',
	'pt_location_description'  => '%excerpt%',
	'pt_location_robots'       => array( 'index', 'follow' ),
	'pt_location_custom_robots' => 'off',
	'pt_location_default_rich_snippet' => 'off',
	'pt_location_add_meta_box' => 'on',

	// --- Non-public marketing CPTs: keep out of search results entirely ---
	// (graduate, testimonial, faq, service, package, instructor) — they have no
	// public single pages, so noindex prevents thin/duplicate URLs if any leak.
	'pt_attachment_redirect_attachments' => 'on', // attachment pages → parent.

	// --- Author / date archives: noindex (thin, no value for a 1-author site) ---
	'author_archive_title'     => '%name% %sep% %sitename%',
	'disable_author_archives'  => 'on',
	'disable_date_archives'    => 'on',

	// --- Search & 404 ---
	'404_title'                => 'Page Not Found %sep% %sitename%',
	'search_title'             => '%search_query% %sep% %sitename%',

	// Breadcrumbs ON (our mu-plugin also emits BreadcrumbList JSON-LD).
	'breadcrumbs'              => 'on',
	'breadcrumbs_separator'    => '/',
	'breadcrumbs_home'         => 'on',
	'breadcrumbs_home_label'   => 'Home',
);

// Noindex robots for the 5 non-public CPTs (loop keeps it tidy + future-proof).
foreach ( array( 'graduate', 'testimonial', 'faq', 'service', 'package', 'instructor' ) as $cpt ) {
	$titles[ "pt_{$cpt}_custom_robots" ] = 'on';
	$titles[ "pt_{$cpt}_robots" ]        = array( 'noindex', 'nofollow' );
	$titles[ "pt_{$cpt}_add_meta_box" ]  = 'off';
}

// Default social-share image = the brand logo (source used /logo.png at 1200x630
// for OG/Twitter). Resolve the imported attachment so this survives a fresh
// Media Library; skip silently if the logo hasn't been imported yet.
$logo_ids = get_posts(
	array(
		'name'        => 'buckleup-driving-school-logo-light',
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
);
if ( $logo_ids ) {
	$logo_id  = (int) $logo_ids[0];
	$logo_url = (string) wp_get_attachment_url( $logo_id );
	if ( $logo_url ) {
		$titles['open_graph_image']        = $logo_url;
		$titles['open_graph_image_id']     = $logo_id;
		$titles['twitter_use_facebook']    = 'on'; // mirror OG → Twitter card.
		$titles['homepage_facebook_image']    = $logo_url;
		$titles['homepage_facebook_image_id'] = $logo_id;
	}
}

update_option( 'rank_math_titles', $titles );
WP_CLI::log( '     rank_math_titles set (templates, homepage, social + OG image, robots, breadcrumbs).' );

/* =========================================================================
 * 2. Rank Math — Sitemap (COMPLETE — fixes the 3-URL source sitemap)
 * ====================================================================== */

$sitemap = array(
	'items_per_page'        => 200,
	'include_images'        => 'on',
	'include_featured_image' => 'on',

	// Posts + pages in the sitemap.
	'pt_post_sitemap'       => 'on',
	'pt_page_sitemap'       => 'on',

	// The public Location CPT IN the sitemap (the /locations/* URLs).
	'pt_location_sitemap'   => 'on',

	// Categories/tags in the sitemap (real blog taxonomy).
	'tax_category_sitemap'  => 'on',
	'tax_post_tag_sitemap'  => 'off',

	// Author sitemap off (single-author marketing site).
	'authors_sitemap'       => 'off',

	// Ping search engines on update.
	'ping_search_engines'   => 'on',
);

// The non-public marketing CPTs stay OUT of the sitemap (no public URLs).
foreach ( array( 'graduate', 'testimonial', 'faq', 'service', 'package', 'instructor', 'attachment' ) as $cpt ) {
	$sitemap[ "pt_{$cpt}_sitemap" ] = 'off';
}

// Rank Math READS the hyphenated option key `rank-math-options-sitemap` (see
// includes/class-settings.php → add_options('sitemap','rank-math-options-sitemap')).
// Writing only to the underscore `rank_math_sitemap` key is a silent no-op — it's
// why the Location CPT never appeared in the sitemap. Merge our settings into the
// real option so they take effect, preserving Rank Math's other sitemap defaults.
$rm_sitemap_key = 'rank-math-options-sitemap';
$rm_sitemap_cur = get_option( $rm_sitemap_key, array() );
$rm_sitemap_cur = is_array( $rm_sitemap_cur ) ? $rm_sitemap_cur : array();
update_option( $rm_sitemap_key, array_merge( $rm_sitemap_cur, $sitemap ) );
// Keep the legacy underscore key in sync too (harmless; some tooling reads it).
update_option( 'rank_math_sitemap', $sitemap );
// Bust Rank Math's cached sitemap so the new providers/URLs regenerate.
if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) ) {
	\RankMath\Sitemap\Cache::invalidate_storage();
}
WP_CLI::log( '     rank-math-options-sitemap set (posts + pages + location CPT + category; CPT noise excluded).' );

/* =========================================================================
 * 3. Rank Math — General (canonicals, redirections module, misc)
 * ====================================================================== */

// NOTE: redirects + 404 logging are owned by the STANDALONE Redirection plugin
// (PLAN §2). Rank Math's own Redirections module is deliberately OFF below — two
// redirect managers would double-handle 301s and, worse, Rank Math's module
// hunts for a `wp_rank_math_redirections_cache` table that is never created in
// this env, flooding debug.log. So no `redirections*` keys here.
$general = array(
	'strip_category_base'      => 'off',
	'attachment_redirect_urls' => 'on',
	'attachment_redirect_default' => home_url(),
	'nofollow_external_links'  => 'off',
	'nofollow_image_links'     => 'off',
	'new_window_external_links' => 'on',
	'add_img_alt'              => 'off',
	'breadcrumbs'              => 'on',
	'usage_tracking'           => 'off',
	'support_rank_math'        => 'off',
);

update_option( 'rank_math_general', $general );
WP_CLI::log( '     rank_math_general set (attachment redirect; RM redirections module OFF).' );

// Rank Math modules: keep the ones we use; DISABLE `redirections` and
// `404-monitor` (both owned by the standalone Redirection plugin) so Rank Math
// stops looking for its missing redirections-cache table and we don't run two
// redirect/404 managers. Idempotent: array_diff removes them on every run.
$modules = (array) get_option( 'rank_math_modules', array() );
foreach ( array( 'sitemap', 'rich-snippet', 'acf' ) as $m ) {
	if ( ! in_array( $m, $modules, true ) ) {
		$modules[] = $m;
	}
}
// Drop e-commerce/forum/web-stories modules AND the redirect/404 managers that
// the standalone Redirection plugin owns.
$modules = array_values(
	array_diff(
		$modules,
		array( 'woocommerce', 'bbpress', 'buddypress', 'web-stories', 'redirections', '404-monitor' )
	)
);
update_option( 'rank_math_modules', $modules );
WP_CLI::log( '     rank_math_modules: sitemap + rich-snippet + acf ON; redirections + 404-monitor OFF (Redirection plugin owns them).' );

/* =========================================================================
 * 4. Per-page verbatim titles/descriptions — written onto the real objects.
 *    Matched by slug; no-op for any page not present yet (re-run after content).
 * ====================================================================== */

/**
 * Verbatim per-URL SEO meta from the source app:
 *   src/app/{about,contact,services}/layout.tsx and
 *   src/app/locations/<slug>/page.tsx and the ICBC resource article.
 * Keyed by the page/CPT slug. About/Contact/Services/Resources/ICBC are Pages;
 * the five locations are `location` CPT posts.
 */
$page_meta = array(
	// --- Standalone Pages (post_type=page) ---
	'about'    => array(
		'type'        => 'page',
		'title'       => 'About Us - ICBC-Certified Driving Instructors Since 2014',
		'description' => "Meet BuckleUp Driving School's ICBC-certified instructors with a 98% pass rate. Trusted by 5000+ graduates in Vancouver, Port Moody, Coquitlam & Burnaby since 2014. Modern vehicles & personalized training.",
		'og_title'    => 'About BuckleUp Driving School | ICBC-Certified Instructors',
		'og_desc'     => 'Meet our ICBC-certified instructors with 98% pass rate. Serving Vancouver & Tri-Cities since 2014.',
	),
	'contact'  => array(
		'type'        => 'page',
		'title'       => 'Contact Us | Book Driving Lessons in Port Moody & Vancouver',
		'description' => 'Contact BuckleUp Driving School: Call (604) 441-3677 or WhatsApp. Book driving lessons in Port Moody, Vancouver, Coquitlam, Burnaby & New Westminster. 136 Maple Dr, Port Moody.',
		'og_title'    => 'Contact BuckleUp Driving School | Book Your Lesson Today',
		'og_desc'     => 'Call (604) 441-3677 or book online. Serving Port Moody, Vancouver, Coquitlam & Burnaby.',
	),
	'services' => array(
		'type'        => 'page',
		'title'       => 'Driving Lesson Packages & Pricing | Class 7L, 7N, 5 & 4 Training',
		'description' => 'Affordable driving lesson packages from $199 in Vancouver, Port Moody & Coquitlam. Class 7L learner, Class 7N novice, Class 5 & Class 4 commercial training. ICBC road test prep with 98% pass rate.',
		'og_title'    => 'Driving Lesson Packages & Pricing | BuckleUp Vancouver',
		'og_desc'     => 'Affordable driving packages from $199. Class 7L, 7N, Class 5 & 4 training with 98% pass rate.',
	),
	'icbc-road-test-failures' => array(
		'type'        => 'page',
		'title'       => 'Top 5 Reasons Students Fail the ICBC Road Test | BuckleUp',
		'description' => 'Learn the exact reasons why Vancouver students fail their ICBC Class 5 and Class 7 road tests, and how BuckleUp Driving School helps you achieve a 98% pass rate.',
		'og_title'    => 'Top 5 Reasons Students Fail the ICBC Road Test | BuckleUp',
		'og_desc'     => 'Learn why Vancouver students fail their ICBC road tests and how we help you achieve a 98% pass rate.',
	),

	// --- ICBC Class 4 knowledge/practice test: hub + 12 category Pages
	//     (post_type=page). Hub targets the head term "ICBC Class 4 knowledge/
	//     practice test"; each category targets its own long-tail. Unique
	//     title/description per URL avoids thin/duplicate-content cannibalisation
	//     across the 12 near-sibling category pages. Resolved by canonical PATH
	//     (the nested children are real Pages under the hub). ---
	'icbc-class-4-knowledge-test' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Knowledge Test: Free Practice Test 2026 | BuckleUp',
		'description' => 'Free ICBC Class 4 practice test for BC. Hundreds of real-style questions across 12 commercial-driving topics, instant scoring at the 80% pass mark. Pass your Class 4 knowledge test in Vancouver & the Tri-Cities on the first try.',
		'og_title'    => 'Free ICBC Class 4 Knowledge Practice Test (BC) | BuckleUp',
		'og_desc'     => 'Practise real-style ICBC Class 4 questions across 12 topics with instant scoring. Free Class 4 knowledge test prep for Vancouver & the Tri-Cities.',
	),
	'getting-your-licence' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Getting Your Licence Practice Test (BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on licence classes, eligibility, medical and vision standards, and the testing process. Study the Getting Your Licence chapter for your BC Class 4 knowledge test.',
		'og_title'    => 'ICBC Class 4 Getting Your Licence Practice Questions | BuckleUp',
		'og_desc'     => 'Practise Class 4 licence eligibility, medical standards and the testing process. Free ICBC Class 4 knowledge test prep for BC.',
	),
	'heavy-vehicle-braking' => array(
		'type'        => 'page',
		'title'       => 'ICBC Heavy Vehicle Braking Practice Test (Class 4 BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on heavy vehicle braking: stopping distance, the effect of speed and weight, brake fade, and safe downhill braking. Master braking for your BC Class 4 knowledge test.',
		'og_title'    => 'ICBC Heavy Vehicle Braking Practice Questions (Class 4) | BuckleUp',
		'og_desc'     => 'Practise stopping distance, brake fade and downhill braking. Free ICBC Class 4 heavy vehicle braking test prep for BC.',
	),
	'basic-driving-skills' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Basic Driving Skills Practice Test (BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on basic driving skills: following distance, manoeuvring, seeing and being seen, and vehicle and personal safety. Prep for your BC Class 4 knowledge test.',
		'og_title'    => 'ICBC Class 4 Basic Driving Skills Practice Questions | BuckleUp',
		'og_desc'     => 'Practise following distance, manoeuvring and safety. Free ICBC Class 4 basic driving skills test prep for BC.',
	),
	'fuel-efficient-driving' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Fuel-Efficient Driving Practice Test (BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on fuel-efficient driving: smart driving habits and vehicle-maintenance choices that cut fuel use. Study for your BC Class 4 commercial knowledge test.',
		'og_title'    => 'ICBC Class 4 Fuel-Efficient Driving Practice Questions | BuckleUp',
		'og_desc'     => 'Practise fuel-saving driving habits and maintenance. Free ICBC Class 4 fuel-efficient driving test prep for BC.',
	),
	'trucks-and-trailers' => array(
		'type'        => 'page',
		'title'       => 'ICBC Trucks & Trailers Practice Test (Class 4 BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on trucks and trailers: vehicle dimensions, loading basics, weight distribution, and load securement. Prep for your BC Class 4 knowledge test.',
		'og_title'    => 'ICBC Trucks & Trailers Practice Questions (Class 4) | BuckleUp',
		'og_desc'     => 'Practise loading, weight distribution and load securement. Free ICBC Class 4 trucks and trailers test prep for BC.',
	),
	'buses-taxis-limos-ride-hailing' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Buses, Taxis & Ride-Hailing Practice Test | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on buses, taxis, limos and ride-hailing: passenger safety, pick-ups and drop-offs, vehicle operations, and refusing passengers. Prep for your BC Class 4 test.',
		'og_title'    => 'ICBC Class 4 Buses, Taxis & Ride-Hailing Questions | BuckleUp',
		'og_desc'     => 'Practise passenger safety, pick-ups and ride-hailing rules. Free ICBC Class 4 knowledge test prep for BC.',
	),
	'hours-of-service' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Hours of Service Practice Test (BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on hours of service: on-duty and off-duty time limits and the record-keeping rules for commercial drivers in BC. Study for your Class 4 knowledge test.',
		'og_title'    => 'ICBC Class 4 Hours of Service Practice Questions | BuckleUp',
		'og_desc'     => 'Practise on-duty limits and logbook rules. Free ICBC Class 4 hours of service test prep for BC.',
	),
	'air-brakes' => array(
		'type'        => 'page',
		'title'       => 'ICBC Air Brake Practice Test (Class 4 BC) | BuckleUp',
		'description' => 'Free ICBC air brake practice test for BC Class 4. Practice questions on air-brake components and operation, system checks, low-air warnings, and ABS. Pass your air brake knowledge test the first time.',
		'og_title'    => 'Free ICBC Air Brake Practice Test (Class 4) | BuckleUp',
		'og_desc'     => 'Practise air-brake components, system checks, low-air warnings and ABS. Free ICBC air brake test prep for BC.',
	),
	'air-brake-adjustment' => array(
		'type'        => 'page',
		'title'       => 'ICBC Air Brake Adjustment Practice Test (Class 4 BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on air brake adjustment: why adjustment matters, how to check pushrod stroke, and slack-adjuster procedures. Prep for your BC air brake endorsement test.',
		'og_title'    => 'ICBC Air Brake Adjustment Practice Questions (Class 4) | BuckleUp',
		'og_desc'     => 'Practise pushrod stroke checks and slack-adjuster procedures. Free ICBC air brake adjustment test prep for BC.',
	),
	'pre-trip-inspections' => array(
		'type'        => 'page',
		'title'       => 'ICBC Pre-Trip Inspection Practice Test (Class 4 BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on pre-trip inspections: inspection duties, bus and passenger-vehicle procedures, and reporting defects. Study pre-trip inspections for your BC Class 4 test.',
		'og_title'    => 'ICBC Pre-Trip Inspection Practice Questions (Class 4) | BuckleUp',
		'og_desc'     => 'Practise inspection duties and defect reporting. Free ICBC Class 4 pre-trip inspection test prep for BC.',
	),
	'signs-signals-and-markings' => array(
		'type'        => 'page',
		'title'       => 'ICBC Signs, Signals & Markings Practice Test (Class 4) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on signs, signals and road markings: regulatory and warning signs, traffic signals, and lane and road markings. Prep for your BC Class 4 knowledge test.',
		'og_title'    => 'ICBC Signs, Signals & Markings Practice Questions | BuckleUp',
		'og_desc'     => 'Practise regulatory and warning signs, signals and road markings. Free ICBC Class 4 test prep for BC.',
	),
	'industrial-roads' => array(
		'type'        => 'page',
		'title'       => 'ICBC Class 4 Industrial Roads Practice Test (BC) | BuckleUp',
		'description' => 'Free ICBC Class 4 practice questions on industrial roads: awareness and right-of-way when driving on private, resource, and industrial roads in BC. Study for your Class 4 knowledge test.',
		'og_title'    => 'ICBC Class 4 Industrial Roads Practice Questions | BuckleUp',
		'og_desc'     => 'Practise right-of-way and awareness on resource and industrial roads. Free ICBC Class 4 test prep for BC.',
	),

	// --- Locations (post_type=location CPT, resolving at /locations/{slug}/ via
	//     the CPT rewrite — NOT pages; the old location Pages were removed). SEO
	//     title/desc verbatim from src/app/locations/<slug>/page.tsx; per-location
	//     editable bu_seo_* fields override these when set (see apply loop). ---
	// SEO title/desc optimized for local search (single source of truth:
	// scripts/wp/elementor/locations-content.php → seo_title / seo_description).
	// Per-location editable bu_seo_* fields still override these when set.
	'port-moody' => array(
		'type'        => 'location',
		'title'       => 'Driving Lessons in Port Moody | BuckleUp Driving School',
		'description' => 'Your local Port Moody driving school. ICBC-certified instructors, 98% pass rate, and expert prep for Heritage Mountain hills and test routes. Book today!',
	),
	'coquitlam' => array(
		'type'        => 'location',
		'title'       => 'Driving Lessons in Coquitlam | BuckleUp Driving School',
		'description' => 'Driving lessons in Coquitlam with ICBC-certified instructors and a 98% pass rate. Master test routes from Town Centre to Westwood Plateau. Book today!',
	),
	'north-vancouver' => array(
		'type'        => 'location',
		'title'       => 'Driving Lessons in North Vancouver | BuckleUp School',
		'description' => 'Master North Shore hills, bridges & test routes with ICBC-certified instructors and a 98% pass rate. Driving lessons in North Vancouver. Book today!',
	),
	'port-coquitlam' => array(
		'type'        => 'location',
		'title'       => 'Driving Lessons in Port Coquitlam | BuckleUp School',
		'description' => 'Driving lessons in Port Coquitlam with ICBC-certified instructors and a 98% pass rate. Master PoCo rail crossings and local test routes. Book today!',
	),
	'tri-cities' => array(
		'type'        => 'location',
		'title'       => 'Driving Lessons in the Tri-Cities | BuckleUp School',
		'description' => 'ICBC-certified driving lessons across Coquitlam, Port Coquitlam & Port Moody. 98% pass rate and expert local route prep. Book a lesson today!',
	),
);

// Front page: write the verbatim homepage title/description onto the static
// front Page so its SERP entry is correct (it otherwise inherits the page-title
// template "Home | …"). Matches whatever the front page actually is.
$front_id = (int) get_option( 'page_on_front' );
if ( $front_id ) {
	update_post_meta( $front_id, 'rank_math_title', 'Best Driving School Vancouver | BuckleUp Driving School' );
	update_post_meta( $front_id, 'rank_math_description', 'BuckleUp Driving School is a top driving school Vancouver learners trust. Driving lessons are available in Vancouver, Tri-Cities, Coquitlam, Port Moody, and North Vancouver. Book today!' );
	update_post_meta( $front_id, 'rank_math_facebook_title', 'BuckleUp Driving School - Vancouver\'s Premier Driving Academy' );
	update_post_meta( $front_id, 'rank_math_facebook_description', 'Vancouver\'s premier driving academy with ICBC-certified instructors, modern vehicles, and a 98% first-time pass rate.' );
	update_post_meta( $front_id, 'rank_math_robots', array( 'index', 'follow' ) );
	// OG image for the static front page (Rank Math reads the page's own
	// facebook_image meta, not the homepage_facebook_image option, when the front
	// page is a static Page).
	if ( ! empty( $logo_ids ) ) {
		$logo_url = (string) wp_get_attachment_url( (int) $logo_ids[0] );
		if ( $logo_url ) {
			update_post_meta( $front_id, 'rank_math_facebook_image', $logo_url );
			update_post_meta( $front_id, 'rank_math_facebook_image_id', (int) $logo_ids[0] );
			update_post_meta( $front_id, 'rank_math_twitter_use_facebook', 'on' );
		}
	}
	WP_CLI::log( "     homepage meta written to front page #{$front_id}." );
}

/**
 * The canonical front-end PATH each meta entry lives at. Resolving by the URL
 * the visitor actually hits (url_to_postid) — rather than by slug+type — means
 * the meta always lands on the entity WordPress actually renders, even if both a
 * Page and a `location` CPT post share a slug (in which case the CPT wins the
 * URL). Falls back to slug/name lookup when the URL doesn't resolve yet.
 */
$page_paths = array(
	'about'    => '/about/',
	'contact'  => '/contact/',
	'services' => '/services/',
	'icbc-road-test-failures' => '/resources/icbc-road-test-failures/',

	// ICBC Class 4 practice-test hub + 12 nested category Pages.
	'icbc-class-4-knowledge-test'    => '/icbc-class-4-knowledge-test/',
	'getting-your-licence'           => '/icbc-class-4-knowledge-test/getting-your-licence/',
	'heavy-vehicle-braking'          => '/icbc-class-4-knowledge-test/heavy-vehicle-braking/',
	'basic-driving-skills'           => '/icbc-class-4-knowledge-test/basic-driving-skills/',
	'fuel-efficient-driving'         => '/icbc-class-4-knowledge-test/fuel-efficient-driving/',
	'trucks-and-trailers'            => '/icbc-class-4-knowledge-test/trucks-and-trailers/',
	'buses-taxis-limos-ride-hailing' => '/icbc-class-4-knowledge-test/buses-taxis-limos-ride-hailing/',
	'hours-of-service'               => '/icbc-class-4-knowledge-test/hours-of-service/',
	'air-brakes'                     => '/icbc-class-4-knowledge-test/air-brakes/',
	'air-brake-adjustment'           => '/icbc-class-4-knowledge-test/air-brake-adjustment/',
	'pre-trip-inspections'           => '/icbc-class-4-knowledge-test/pre-trip-inspections/',
	'signs-signals-and-markings'     => '/icbc-class-4-knowledge-test/signs-signals-and-markings/',
	'industrial-roads'               => '/icbc-class-4-knowledge-test/industrial-roads/',

	'port-moody'      => '/locations/port-moody/',
	'coquitlam'       => '/locations/coquitlam/',
	'north-vancouver' => '/locations/north-vancouver/',
	'port-coquitlam'  => '/locations/port-coquitlam/',
	'tri-cities'      => '/locations/tri-cities/',
);

$applied = 0;
$missing = array();
foreach ( $page_meta as $slug => $meta ) {
	$page = null;

	// 1) Resolve by the canonical URL — catches whatever actually serves it.
	if ( isset( $page_paths[ $slug ] ) ) {
		$pid = url_to_postid( home_url( $page_paths[ $slug ] ) );
		if ( $pid ) {
			$page = get_post( $pid );
		}
	}
	// 2) Slug + declared type.
	if ( ! $page ) {
		$page = get_page_by_path( $slug, OBJECT, $meta['type'] );
	}
	// 3) Raw name lookup across any type (nested pages / CPT).
	if ( ! $page ) {
		$found = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => array( 'page', 'post', 'location' ),
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);
		$page = $found ? $found[0] : null;
	}

	if ( ! $page ) {
		$missing[] = "{$meta['type']}:{$slug}";
		continue;
	}

	$title = $meta['title'];
	$desc  = $meta['description'];

	// For a `location` CPT post, prefer the editable per-location SEO fields from
	// the content model (`bu_seo_title` / `bu_seo_description`) when set, so an
	// editor changing them in the CPT flows straight into Rank Math. Fall back to
	// the verbatim source values otherwise.
	if ( 'location' === $page->post_type ) {
		$cpt_title = (string) get_post_meta( $page->ID, 'bu_seo_title', true );
		$cpt_desc  = (string) get_post_meta( $page->ID, 'bu_seo_description', true );
		if ( '' !== $cpt_title ) {
			$title = $cpt_title;
		}
		if ( '' !== $cpt_desc ) {
			$desc = $cpt_desc;
		}
	}

	update_post_meta( $page->ID, 'rank_math_title', $title );
	update_post_meta( $page->ID, 'rank_math_description', $desc );
	if ( ! empty( $meta['og_title'] ) ) {
		update_post_meta( $page->ID, 'rank_math_facebook_title', $meta['og_title'] );
	}
	if ( ! empty( $meta['og_desc'] ) ) {
		update_post_meta( $page->ID, 'rank_math_facebook_description', $meta['og_desc'] );
	}
	// Robots: index,follow on every public marketing page.
	update_post_meta( $page->ID, 'rank_math_robots', array( 'index', 'follow' ) );
	$applied++;
}
WP_CLI::log( "     per-page meta applied to {$applied} object(s)." );
if ( $missing ) {
	WP_CLI::log( '     (not present yet, re-run after content seeding: ' . implode( ', ', $missing ) . ')' );
}

/* =========================================================================
 * 5. Redirection plugin — legacy URL-parity 301s + 404 logging.
 *    apex→www is handled at the server/nginx level (and by canonical), but we
 *    seed defensive in-app 301s so a few legacy/variant paths still resolve.
 *
 *    Wrapped in try/catch: this is the riskiest part of the script (it
 *    instantiates Red_Database and runs DDL). The Rank Math config above is
 *    ALREADY persisted by this point, so even if Redirection throws, the
 *    critical per-page SEO output survives — a Redirection hiccup must never be
 *    able to leave Rank Math dormant (the HIGH bug this task fixes).
 * ====================================================================== */

try {
if ( class_exists( 'Red_Item' ) && class_exists( 'Red_Group' ) ) {

	// Ensure ALL of Redirection's DB tables exist. The plugin only creates them
	// on an admin visit / its REST installer, so a headless `wp plugin install
	// --activate` can leave them missing (or PARTIALLY created) — and a missing
	// `wp_redirection_groups` floods debug.log with "Table … doesn't exist" on
	// every request, while Red_Item::create() silently no-ops. Check all four
	// and (re)install the latest schema if ANY is absent. install() is a
	// CREATE TABLE IF NOT EXISTS, so repairing a partial install is safe.
	global $wpdb;
	$required_tables = array(
		$wpdb->prefix . 'redirection_items',
		$wpdb->prefix . 'redirection_groups',
		$wpdb->prefix . 'redirection_logs',
		$wpdb->prefix . 'redirection_404',
	);
	$missing_tables = array();
	foreach ( $required_tables as $tbl ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) ) {
			$missing_tables[] = $tbl;
		}
	}
	$has_tables = empty( $missing_tables );
	if ( ! $has_tables && class_exists( 'Red_Database' ) ) {
		$db     = new Red_Database();
		$latest = $db->get_latest_database();
		if ( method_exists( $latest, 'install' ) ) {
			$latest->install();        // creates all four tables + default groups.
		}
		if ( defined( 'REDIRECTION_DB_VERSION' ) ) {
			update_option( 'redirection_version', REDIRECTION_DB_VERSION );
		}
		// Re-check after install.
		$missing_tables = array();
		foreach ( $required_tables as $tbl ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) ) {
				$missing_tables[] = $tbl;
			}
		}
		$has_tables = empty( $missing_tables );
		if ( $has_tables ) {
			WP_CLI::log( '     Redirection DB tables installed/repaired (all 4 present).' );
		} else {
			WP_CLI::warning( '     Redirection tables still missing after install: ' . implode( ', ', $missing_tables ) );
		}
	}

	// Ensure a group exists to hold our parity rules.
	$group_id   = 1;
	$groups     = Red_Group::get_all();
	$group_name = 'BuckleUp URL Parity';
	$existing   = null;
	foreach ( (array) $groups as $g ) {
		if ( isset( $g['name'] ) && $g['name'] === $group_name ) {
			$existing = $g['id'];
			break;
		}
	}
	if ( ! $existing ) {
		$created  = Red_Group::create( $group_name, 1 );
		$group_id = is_object( $created ) ? $created->get_id() : 1;
	} else {
		$group_id = $existing;
	}

	/**
	 * Legacy → canonical 301s. These preserve old inbound links from the Next
	 * app while standardising everything on the WP URL shapes. The 5 blog slugs,
	 * /locations/*, and /resources/* are preserved 1:1, so they need NO redirect
	 * — they're listed here only as documentation of what must stay stable.
	 */
	$redirects = array(
		// Trailing/alternate forms of the home + section pages.
		array( '/home', '/' ),
		array( '/index.html', '/' ),
		// Common legacy variants → canonical section pages.
		array( '/our-services', '/services' ),
		array( '/pricing', '/services' ),
		array( '/packages', '/services' ),
		array( '/about-us', '/about' ),
		array( '/contact-us', '/contact' ),
		array( '/resources/icbc-road-test', '/resources/icbc-road-test-failures' ),
		// Trailing-/blog/ alias for the resource article if linked under /blog.
		array( '/blog/icbc-road-test-failures', '/resources/icbc-road-test-failures' ),
	);

	$seeded = 0;
	$items  = Red_Item::get_all_for_module( 1 ); // existing URL-source rules.
	$have   = array();
	foreach ( (array) $items as $it ) {
		if ( method_exists( $it, 'get_url' ) ) {
			$have[ untrailingslashit( $it->get_url() ) ] = true;
		}
	}
	foreach ( $redirects as $pair ) {
		list( $from, $to ) = $pair;
		if ( isset( $have[ untrailingslashit( $from ) ] ) ) {
			continue; // idempotent: already present.
		}
		Red_Item::create(
			array(
				'url'         => $from,
				'action_data' => array( 'url' => $to ),
				'regex'       => false,
				'group_id'    => $group_id,
				'match_type'  => 'url',
				'action_type' => 'url',
				'action_code' => 301,
				'title'       => 'BuckleUp parity: ' . $from,
			)
		);
		$seeded++;
	}
	WP_CLI::log( "     Redirection: seeded {$seeded} legacy parity 301(s) (404 logging on by default)." );
} else {
	WP_CLI::log( '     Redirection plugin not active yet — skipping parity rules (re-run after activation).' );
}
} catch ( \Throwable $e ) {
	// Never let a Redirection failure abort the SEO config — the Rank Math
	// per-page meta (set above) is what matters most and is already saved.
	WP_CLI::warning( '     Redirection setup threw (' . $e->getMessage() . ') — continuing; Rank Math config is already persisted.' );
}

/* =========================================================================
 * 6. Reload Rank Math's module registry + verify the critical config stuck.
 * ====================================================================== */

// Force Rank Math to drop any cached module/option state so the front-end head
// engages on the very next request without needing a container restart. Safe
// no-ops if the methods/classes aren't present in this RM version.
if ( function_exists( 'rank_math' ) ) {
	wp_cache_delete( 'rank_math_modules', 'options' );
	wp_cache_delete( 'rank_math_titles', 'options' );
	if ( class_exists( 'RankMath\\Helper' ) && method_exists( 'RankMath\\Helper', 'clear_cache' ) ) {
		RankMath\Helper::clear_cache();
	}
}

// HARD VERIFICATION GATE: re-read the options and fail LOUDLY if the critical
// Rank Math config didn't persist. provision.sh runs this via wp_eval, which
// swallows errors — so a silent failure here would otherwise ship a site with
// dead per-page meta (the exact HIGH regression). WP_CLI::error exits non-zero.
$verify_titles = (array) get_option( 'rank_math_titles', array() );
$verify_wizard = (bool) get_option( 'rank_math_wizard_completed' );
$verify_mods   = (array) get_option( 'rank_math_modules', array() );
$problems      = array();
if ( ! $verify_wizard ) {
	$problems[] = 'rank_math_wizard_completed is not set';
}
if ( empty( $verify_titles['homepage_title'] ) ) {
	$problems[] = 'rank_math_titles.homepage_title is empty';
}
if ( empty( $verify_titles['pt_page_title'] ) ) {
	$problems[] = 'rank_math_titles.pt_page_title is empty';
}
if ( in_array( 'redirections', $verify_mods, true ) ) {
	$problems[] = 'rank_math redirections module is still ON (should be OFF)';
}
if ( $problems ) {
	WP_CLI::error( 'SEO config verification FAILED: ' . implode( '; ', $problems ) . '. Re-run /scripts/wp/seo-config.php after Rank Math is active.' );
}
WP_CLI::log( '     VERIFIED: Rank Math wizard complete, titles populated (' . count( $verify_titles ) . ' keys), redirections module off.' );

/* =========================================================================
 * 7. Flush rewrite rules.
 * ====================================================================== */

// NOTE: we deliberately do NOT change home/siteurl to the production www URL in
// local dev (the site is served from localhost:8080). The mu-plugin emits the
// canonical/schema URLs against the production origin regardless, and the
// apex→www 301 is enforced at the web-server layer in production. Changing
// siteurl here would break local asset/login URLs.

if ( function_exists( 'rank_math_flush_rewrite' ) ) {
	rank_math_flush_rewrite();
}
flush_rewrite_rules( false );

WP_CLI::log( '  -> BuckleUp SEO config complete.' );
