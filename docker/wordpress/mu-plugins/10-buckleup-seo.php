<?php
/**
 * Plugin Name: BuckleUp SEO — Structured Data & Geo Meta
 * Description: Hand-authored JSON-LD (multi-type LocalBusiness/EducationalOrganization/
 *              DrivingSchool, FAQPage, BlogPosting, Article, BreadcrumbList, WebSite)
 *              plus geo meta tags, emitted in <head>. Fixes the source's SEO bugs:
 *              every page gets its own self-referential canonical hint, all URLs are
 *              standardised on https://www.buckleupdriving.ca, and the FAQPage schema is
 *              sourced from the single `faq` CPT so it can never drift from the visible
 *              accordion. Runtime-config of Rank Math/Redirection lives in provisioning;
 *              this file is the schema/meta layer only.
 *
 *              Editable values (NAP, hours, rating, social) are read from the
 *              `buckleup_settings` option (or the `buckleup_seo_settings` filter) and
 *              fall back to the verbatim source values, so output is correct even before
 *              the ACF Options page ships.
 *
 * Author:      BuckleUp team
 * Version:     1.0.0
 *
 * @package BuckleUp_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one true public origin. Every absolute URL this plugin emits is built from
 * this so canonicals, schema @id values, and geo meta never disagree (source bug:
 * mixed apex/www and per-page canonicals all pointing at the homepage).
 */
if ( ! defined( 'BUCKLEUP_SEO_BASE_URL' ) ) {
	define( 'BUCKLEUP_SEO_BASE_URL', 'https://www.buckleupdriving.ca' );
}

/**
 * The www-canonical logo URL for schema/OG.
 *
 * Prefers the light brand logo in the Media Library (imported during content
 * migration), then the Site Icon, then the conventional uploads path — and
 * normalises the host to the production origin so the schema advertises the
 * production URL even when served from localhost in dev.
 *
 * @return string
 */
function buckleup_seo_logo_url() {
	$url = '';

	$logo = get_posts(
		array(
			'name'        => 'buckleup-driving-school-logo-light',
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( $logo ) {
		$url = (string) wp_get_attachment_url( $logo[0] );
	}
	if ( '' === $url ) {
		$url = (string) get_site_icon_url( 512 );
	}

	// IMPORTANT: emit the logo/image on the SAME host that is serving the page,
	// i.e. the real, always-fetchable attachment URL — do NOT force it onto the
	// www origin. The dev upload path (e.g. /uploads/2026/06/logo.png) exists on
	// the serving host but NOT at that exact path on production, so www-forcing it
	// makes the schema logo 403/404 for any crawler/validator (the HIGH-bug item
	// (b)). A crawler fetching the live page at www.buckleupdriving.ca already
	// gets a same-origin (www) attachment URL here; a validator hitting the dev
	// host gets the dev URL — both resolve 200. schema.org accepts an absolute
	// URL on the serving host.
	if ( '' === $url ) {
		// Last-resort default: a conventional, host-relative-on-serving-host path.
		$url = home_url( '/wp-content/uploads/logo.png' );
	}
	return $url;
}

/**
 * Editable business settings, merged over the verbatim source defaults.
 *
 * Reads the content plugin's `buckleup_settings` option, maps its key names onto
 * this file's internal schema keys (with the right transforms — e.g. a bare
 * WhatsApp number becomes a full wa.me URL), and falls back to the verbatim
 * source value for anything absent. A final `buckleup_seo_settings` filter lets
 * any caller override the merged result. The schema is therefore always complete,
 * even before the ACF Options page is populated.
 *
 * @return array<string,mixed> Fully-populated settings.
 */
function buckleup_seo_settings() {
	static $settings = null;
	if ( null !== $settings ) {
		return $settings;
	}

	// Verbatim from src/components/seo/LocalBusinessSchema.tsx + layout.tsx.
	$defaults = array(
		'name'           => 'BuckleUp Driving School',
		'alternate_name' => 'BuckleUp Driving School Ltd',
		'description'    => 'Master the road with BuckleUp Driving School. ICBC certified instruction in Vancouver, Port Moody, Coquitlam & North Van. 98% pass rate. Book your lesson today!',
		'phone'          => '+1-604-441-3677',
		'phone_display'  => '(604) 441-3677',
		'email'          => 'info@buckleupdriving.ca',
		'street'         => '136 Maple Dr',
		'locality'       => 'Port Moody',
		'region'         => 'BC',
		'postal'         => 'V3H 0A8',
		'country'        => 'CA',
		'lat'            => 49.2838,
		'lng'            => -122.8556,
		'opens'          => '09:00',
		'closes'         => '18:00',
		'price_range'    => '$$',
		'payments'       => array( 'Cash', 'Credit Card', 'E-Transfer' ),
		'rating_value'   => '4.98',
		// Must match the genuine review volume shown on-page ("200+ Google
		// reviews" in the Elementor hero + body copy) and the real Google
		// Business Profile. The source's verbatim 500 contradicts the visible
		// "200+" claim — an aggregateRating/visible-content mismatch that risks a
		// Google review-snippet policy flag — so we standardise on 200.
		'review_count'   => '200',
		'best_rating'    => '5',
		'worst_rating'   => '1',
		'founding_date'  => '2014',
		'whatsapp'       => 'https://wa.me/16044413677',
		'instagram'      => 'https://www.instagram.com/budrivingschool',
		'facebook'       => 'https://www.facebook.com/DriveMasterca',
		'logo'           => buckleup_seo_logo_url(),
		'area_served'    => array( 'Vancouver', 'Port Moody', 'Coquitlam', 'Burnaby', 'New Westminster' ),
	);

	// Read business settings through buckleup-core's helper when available
	// (the documented contract — docs/CONTENT-MODEL.md), else fall back to the
	// raw `buckleup_settings` option so SEO still works if the plugin is inactive.
	if ( function_exists( 'buckleup_get_settings' ) ) {
		$stored = (array) buckleup_get_settings();
	} else {
		$stored = get_option( 'buckleup_settings', array() );
	}
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	// Map the content plugin's authoritative setting keys onto this file's
	// internal schema keys. `from` is the buckleup_settings key; the closure
	// transforms the stored value into the shape the schema expects. Anything
	// missing/blank keeps its verbatim default.
	// NOTE: `description` is a fixed marketing string (not an editable setting in
	// the content model), so it always keeps its verbatim default — not mapped.
	$map = array(
		'name'          => array( 'business_name', null ),
		'phone'         => array( 'phone_e164', 'buckleup_seo_format_e164' ),
		'phone_display' => array( 'phone', null ),
		'email'         => array( 'email', null ),
		'street'        => array( 'street_address', null ),
		'locality'      => array( 'address_locality', null ),
		'region'        => array( 'address_region', null ),
		'postal'        => array( 'postal_code', null ),
		'country'       => array( 'address_country', null ),
		'lat'           => array( 'geo_lat', 'floatval' ),
		'lng'           => array( 'geo_lng', 'floatval' ),
		'opens'         => array( 'hours_open', null ),
		'closes'        => array( 'hours_close', null ),
		'price_range'   => array( 'price_range', null ),
		'rating_value'  => array( 'rating_value', null ),
		'review_count'  => array( 'review_count', null ),
		'founding_date' => array( 'founding_year', null ),
		'whatsapp'      => array( 'whatsapp', 'buckleup_seo_format_whatsapp' ),
		'instagram'     => array( 'instagram_url', null ),
		'facebook'      => array( 'facebook_url', null ),
	);

	$mapped = array();
	foreach ( $map as $internal => $spec ) {
		list( $from, $transform ) = $spec;
		if ( ! isset( $stored[ $from ] ) ) {
			continue;
		}
		$value = $stored[ $from ];
		if ( '' === $value || null === $value ) {
			continue; // Blank field → keep the verbatim default.
		}
		if ( $transform && is_callable( $transform ) ) {
			$value = call_user_func( $transform, $value );
		}
		if ( '' === $value || null === $value ) {
			continue;
		}
		$mapped[ $internal ] = $value;
	}

	$settings = apply_filters( 'buckleup_seo_settings', array_merge( $defaults, $mapped ) );
	return $settings;
}

/**
 * Normalise a WhatsApp value to a full wa.me URL. The content plugin stores a
 * bare digit string (e.g. "16044413677"); schema sameAs needs the URL.
 *
 * @param string $value Bare number or an already-complete wa.me URL.
 * @return string
 */
function buckleup_seo_format_whatsapp( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( false !== strpos( $value, 'wa.me' ) || 0 === strpos( $value, 'http' ) ) {
		return $value;
	}
	$digits = preg_replace( '/\D+/', '', $value );
	return $digits ? 'https://wa.me/' . $digits : '';
}

/**
 * Format an E.164 phone number (e.g. "+16044413677") into the dashed schema form
 * "+1-604-441-3677" used verbatim by the source LocalBusiness schema.
 *
 * @param string $value E.164 number.
 * @return string
 */
function buckleup_seo_format_e164( $value ) {
	$value  = trim( (string) $value );
	$digits = preg_replace( '/\D+/', '', $value );
	// North American +1 NPA-NXX-XXXX.
	if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
		return sprintf(
			'+1-%s-%s-%s',
			substr( $digits, 1, 3 ),
			substr( $digits, 4, 3 ),
			substr( $digits, 7, 4 )
		);
	}
	// Unknown shape: return as-is (prefixed with + if it had one).
	return ( 0 === strpos( $value, '+' ) ) ? $value : ( $digits ? '+' . $digits : $value );
}

/**
 * Build an absolute, www-canonical URL for a given path.
 *
 * @param string $path Leading-slash path (e.g. "/about"). "" or "/" → site root.
 * @return string
 */
function buckleup_seo_url( $path = '' ) {
	$path = '/' . ltrim( (string) $path, '/' );
	if ( '/' === $path ) {
		return BUCKLEUP_SEO_BASE_URL . '/';
	}
	// Match WP's permalink form (trailing slash) so the self-referential canonical
	// is the final 200 URL, not the slash-less form that 301-redirects (QA B2).
	return BUCKLEUP_SEO_BASE_URL . user_trailingslashit( $path );
}

/**
 * The www-canonical URL of the request currently being rendered.
 *
 * Rebuilds the front-end URL from the queried object onto the canonical origin so
 * localhost:8080 (dev) or an apex request both still emit the production www URL —
 * this is what gives every page its OWN self-referential canonical (source bug:
 * inner pages inherited the homepage canonical).
 *
 * @return string
 */
function buckleup_seo_current_url() {
	if ( is_front_page() ) {
		return buckleup_seo_url( '/' );
	}

	$queried = get_queried_object();
	if ( $queried instanceof WP_Post ) {
		$path = wp_make_link_relative( get_permalink( $queried ) );
		return buckleup_seo_url( $path );
	}
	if ( $queried instanceof WP_Term ) {
		$link = get_term_link( $queried );
		if ( ! is_wp_error( $link ) ) {
			return buckleup_seo_url( wp_make_link_relative( $link ) );
		}
	}

	// Fallback: map the raw request path onto the canonical origin.
	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
	return buckleup_seo_url( (string) $request );
}

/**
 * Encode + print one JSON-LD <script> block.
 *
 * JSON_HEX_TAG + JSON_HEX_AMP escape `<`, `>`, `&` as \u00XX, so a literal
 * `</script>` in any field (now or in future un-stripped content) can never
 * break out of the <script> context — defense-in-depth on top of the
 * wp_strip_all_tags() already applied to user-sourced strings. UNESCAPED_SLASHES
 * keeps URLs readable; UNESCAPED_UNICODE keeps accented copy intact.
 *
 * @param array $schema Associative schema array.
 * @return void
 */
function buckleup_seo_print_jsonld( array $schema ) {
	if ( empty( $schema ) ) {
		return;
	}
	$json = wp_json_encode(
		$schema,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);
	if ( false === $json ) {
		return;
	}
	echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output (with JSON_HEX_TAG|JSON_HEX_AMP) is safe in a script context.
}

/* -------------------------------------------------------------------------
 * Page detection
 * ---------------------------------------------------------------------- */

/**
 * Whether the current request is one of the 5 location pages.
 *
 * Works whether locations are the `location` CPT (the v1 model) or plain Pages
 * under /locations/, so the schema layer is robust to either content shape.
 *
 * @return bool
 */
function buckleup_seo_is_location() {
	if ( is_singular( 'location' ) ) {
		return true;
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
	return ( 0 === strpos( $path, 'locations/' ) );
}

/**
 * Whether the current request is the true site front page.
 *
 * `is_front_page()` is the static front Page (or the posts index when the site
 * shows posts on the front). It is deliberately NOT `is_home()`: with a static
 * front page + a separate "Blog" posts page, `is_home()` is the /blog/ archive,
 * which should NOT carry homepage-only schema (WebSite, FAQ) or geo meta.
 *
 * @return bool
 */
function buckleup_seo_is_front() {
	return (bool) is_front_page();
}

/**
 * Whether FAQPage schema belongs on this request: the front page + every
 * location page, mirroring where the source rendered <FAQSchema />.
 *
 * @return bool
 */
function buckleup_seo_wants_faq() {
	return ( buckleup_seo_is_front() || buckleup_seo_is_location() );
}

/**
 * Whether the current request is the ICBC road-test guide.
 *
 * @return bool
 */
function buckleup_seo_is_icbc_guide() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
	return ( 'resources/icbc-road-test-failures' === $path );
}

/* -------------------------------------------------------------------------
 * Per-location SEO specificity — "ultimate" local SEO for the 5 location pages
 *
 * On a /locations/{slug}/ page the JSON-LD must read as a LOCAL business for THAT
 * city: a city-qualified business name, the city's own geo coordinates + geo meta,
 * a city-scoped areaServed, and a city-specific FAQPage (so the structured FAQ
 * matches that page's visible accordion, not the homepage one).
 *
 * The data lives in scripts/wp/elementor/locations-content.php (the single source
 * shared with the Elementor page body + Rank Math meta). On prod that file is part
 * of the repo deploy, but it lives OUTSIDE wp-content, so the mu-plugin must never
 * fatal if it's absent: a compact, self-contained fallback map below carries the
 * geo + areaServed + name for all 5 slugs, and the FAQ map is loaded from the
 * content file only when present (else the location falls back to the homepage FAQ).
 * ---------------------------------------------------------------------- */

/**
 * The current location slug, or '' when this isn't a location page.
 *
 * Prefers the queried `location` CPT post slug; falls back to the second URL
 * segment under /locations/ so it works whether locations are a CPT or plain Pages.
 *
 * @return string Lowercase slug (e.g. "coquitlam"), or ''.
 */
function buckleup_seo_location_slug() {
	if ( is_singular( 'location' ) ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && '' !== $queried->post_name ) {
			return sanitize_title( $queried->post_name );
		}
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
	if ( 0 === strpos( $path, 'locations/' ) ) {
		$segments = explode( '/', $path );
		if ( isset( $segments[1] ) && '' !== $segments[1] ) {
			return sanitize_title( $segments[1] );
		}
	}
	return '';
}

/**
 * Self-contained slug → { name, locality, geo, area_served } map for the 5
 * locations. Mirrors scripts/wp/elementor/locations-content.php (city / geo /
 * area_served) so the schema is correct even when that content file isn't on the
 * server. Keep these in sync if the content file's geo / area_served change.
 *
 * @return array<string,array<string,mixed>>
 */
function buckleup_seo_location_map() {
	$base = buckleup_seo_settings();
	$name = (string) $base['name'];
	return array(
		'coquitlam'       => array(
			'name'        => $name . ' — Coquitlam',
			'locality'    => 'Coquitlam',
			'lat'         => 49.2838,
			'lng'         => -122.7932,
			'area_served' => array( 'Coquitlam', 'Burquitlam', 'Westwood Plateau', 'Maillardville', 'Eagle Ridge' ),
		),
		'north-vancouver' => array(
			'name'        => $name . ' — North Vancouver',
			'locality'    => 'North Vancouver',
			'lat'         => 49.3200,
			'lng'         => -123.0724,
			'area_served' => array( 'North Vancouver', 'Lonsdale', 'Lynn Valley', 'Deep Cove', 'Capilano' ),
		),
		'port-coquitlam'  => array(
			'name'        => $name . ' — Port Coquitlam',
			'locality'    => 'Port Coquitlam',
			'lat'         => 49.2620,
			'lng'         => -122.7811,
			'area_served' => array( 'Port Coquitlam', 'Citadel Heights', 'Mary Hill', 'Riverwood', 'Birchland Manor' ),
		),
		'port-moody'      => array(
			'name'        => $name . ' — Port Moody',
			'locality'    => 'Port Moody',
			'lat'         => 49.2838,
			'lng'         => -122.8556,
			'area_served' => array( 'Port Moody', 'Moody Centre', 'Inlet Centre', 'Heritage Mountain', 'Newport Village' ),
		),
		'tri-cities'      => array(
			'name'        => $name . ' — Tri-Cities',
			'locality'    => 'Coquitlam', // regional centroid placename for geo meta.
			'lat'         => 49.2780,
			'lng'         => -122.7930,
			'area_served' => array( 'Tri-Cities', 'Coquitlam', 'Port Coquitlam', 'Port Moody' ),
		),
	);
}

/**
 * The per-location data row for the current request, or null when this isn't one
 * of the 5 known location pages.
 *
 * @return array<string,mixed>|null
 */
function buckleup_seo_current_location() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$cache = null;
	$slug  = buckleup_seo_location_slug();
	if ( '' === $slug ) {
		return $cache;
	}
	$map = buckleup_seo_location_map();
	if ( isset( $map[ $slug ] ) ) {
		$cache = $map[ $slug ];
	}
	return $cache;
}

/**
 * Self-contained slug → location FAQ map (question/answer), mirroring the `faqs`
 * in scripts/wp/elementor/locations-content.php (which feeds the visible Elementor
 * accordion). Embedded here so the location FAQPage schema renders correctly even
 * on a server / container where the content file isn't on the include path (the
 * web tier doesn't mount ./scripts; prod keeps the content file outside wp-content)
 * — schema and accordion still match because both derive from the SAME copy. Keep
 * in sync with the content file if the FAQs change.
 *
 * @return array<string,array<int,array{q:string,a:string}>>
 */
function buckleup_seo_location_faq_map() {
	return array(
		'coquitlam' => array(
			array( 'q' => 'Where do you offer driving lessons in Coquitlam?', 'a' => 'We offer lessons across all of Coquitlam, including Town Centre, Burquitlam, Maillardville, Westwood Plateau, Eagle Ridge, and Austin Heights. We can pick you up from home, work, school, or a SkyTrain station.' ),
			array( 'q' => 'Do you prepare students for the Coquitlam ICBC road test?', 'a' => 'Yes. We train directly on the routes used by the Coquitlam ICBC Driver Licensing office — the same multi-lane changes, hill starts, and parking manoeuvres the examiner will ask for — so you walk in already familiar with the area.' ),
			array( 'q' => 'How many lessons will I need to pass in Coquitlam?', 'a' => 'Most beginners need around six to ten lessons depending on prior experience. After a free assessment, your instructor will recommend a realistic plan for the Coquitlam test routes.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in Coquitlam?', 'a' => 'Yes. Lessons are available in both English and Farsi, which many students in Coquitlam\'s diverse community find makes learning faster and less stressful.' ),
			array( 'q' => 'What licence classes can I train for in Coquitlam?', 'a' => 'We provide training for Class 7L (learner), Class 7N (novice), Class 5 (full licence), and Class 4 (commercial), all with ICBC-certified instructors and modern Toyota vehicles.' ),
		),
		'north-vancouver' => array(
			array( 'q' => 'Where do you offer driving lessons in North Vancouver?', 'a' => 'We cover all of North Vancouver, including Lower and Central Lonsdale, Lynn Valley, Deep Cove, Capilano, and Edgemont Village, with pickup from home, work, school, or the SeaBus terminal.' ),
			array( 'q' => 'Do you teach hill starts and steep-grade driving?', 'a' => 'Yes — and it is a core part of every North Shore lesson. We practise hill starts, downhill braking, and hill parking on the real Lonsdale and Lynn Valley grades the ICBC examiner uses.' ),
			array( 'q' => 'Can you prepare me for the North Vancouver ICBC road test?', 'a' => 'Absolutely. We train on the routes used by the North Vancouver ICBC Driver Licensing office so you are already familiar with the hills, arterials, and parking spots before test day.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in North Vancouver?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer, more comfortable learning.' ),
			array( 'q' => 'How do you handle North Shore wet weather in lessons?', 'a' => 'We deliberately build wet-weather skills — safe braking distances, smooth steering, and visibility management — because the North Shore sees the region\'s heaviest rainfall and the road test happens rain or shine.' ),
		),
		'port-coquitlam' => array(
			array( 'q' => 'Where do you offer driving lessons in Port Coquitlam?', 'a' => 'We serve all of Port Coquitlam, including Downtown PoCo, Citadel Heights, Mary Hill, Riverwood, and Birchland Manor, with pickup from home, work, or school.' ),
			array( 'q' => 'Do you teach how to handle PoCo\'s rail crossings?', 'a' => 'Yes. Port Coquitlam has many level rail crossings, and we make them a focus — proper stopping, scanning, and never stopping on the tracks are exactly what examiners watch for.' ),
			array( 'q' => 'Where will I take my Port Coquitlam road test?', 'a' => 'Most PoCo road tests are booked at the Coquitlam ICBC Driver Licensing office, which serves the Tri-Cities. We train on those routes so you arrive familiar and confident.' ),
			array( 'q' => 'How many lessons do I need to pass in Port Coquitlam?', 'a' => 'Most beginners need around six to ten lessons. After a quick assessment, your instructor will give you an honest plan for the PoCo test routes.' ),
			array( 'q' => 'Do you offer lessons in Farsi in Port Coquitlam?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer communication and faster learning.' ),
		),
		'port-moody' => array(
			array( 'q' => 'Where do you offer driving lessons in Port Moody?', 'a' => 'Port Moody is our home base, so we cover the entire city — Moody Centre, Inlet Centre, Newport Village, Heritage Mountain, and College Park — with pickup from home, work, school, or a SkyTrain station.' ),
			array( 'q' => 'Is BuckleUp actually located in Port Moody?', 'a' => 'Yes. BuckleUp Driving School is based in Port Moody, which means our instructors teach on these exact streets, hills, and test routes every day.' ),
			array( 'q' => 'Do you teach the Heritage Mountain hill starts?', 'a' => 'Definitely. Hill starts and downhill control on the Heritage Mountain and College Park grades are a core part of Port Moody lessons because examiners frequently test them.' ),
			array( 'q' => 'Where will I take my Port Moody road test?', 'a' => 'Most Port Moody road tests are booked at the nearby Coquitlam ICBC Driver Licensing office, which serves the Tri-Cities. We train you directly on those routes.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in Port Moody?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer, more comfortable learning.' ),
		),
		'tri-cities' => array(
			array( 'q' => 'Which cities do you cover in the Tri-Cities?', 'a' => 'We provide driving lessons across all three Tri-Cities — Coquitlam, Port Coquitlam, and Port Moody — with pickup from home, work, school, or a SkyTrain station anywhere in the region.' ),
			array( 'q' => 'Where will I take my Tri-Cities road test?', 'a' => 'Most Tri-Cities road tests are booked at the Coquitlam ICBC Driver Licensing office, which serves Coquitlam, Port Coquitlam, and Port Moody. We train you on those exact routes regardless of which city you live in.' ),
			array( 'q' => 'Do you cover both the hills and the rail crossings?', 'a' => 'Yes. The Tri-Cities mix steep grades (Westwood Plateau, Heritage Mountain) with Port Coquitlam\'s level rail crossings, and our lessons prepare you confidently for both.' ),
			array( 'q' => 'How many lessons will I need to pass in the Tri-Cities?', 'a' => 'Most beginners need around six to ten lessons. After a free assessment, your instructor will recommend a plan based on the test routes you\'ll be driving.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi across the Tri-Cities?', 'a' => 'Yes. Lessons are available in both English and Farsi throughout Coquitlam, Port Coquitlam, and Port Moody.' ),
		),
	);
}

/**
 * The location-specific FAQ Q&A pairs for the current location page.
 *
 * Uses the embedded slug→FAQ map (always available), but lets the shared content
 * file (scripts/wp/elementor/locations-content.php) override it when that file IS
 * on the include path — so a future content edit there flows through automatically
 * without touching this mu-plugin. NEVER fatals if the file is absent.
 *
 * Returns [] when this isn't a location page or the slug has no FAQs — callers then
 * fall back to the homepage/CPT FAQ so the schema is never empty.
 *
 * @return array<int,array{question:string,answer:string}>
 */
function buckleup_seo_location_faq_items() {
	$slug = buckleup_seo_location_slug();
	if ( '' === $slug ) {
		return array();
	}

	// Optional override from the shared content file when it's readable here.
	static $content = null;
	if ( null === $content ) {
		$content = array();
		$file    = dirname( __DIR__, 2 ) . '/scripts/wp/elementor/locations-content.php';
		if ( is_readable( $file ) ) {
			$loaded = include $file;
			if ( is_array( $loaded ) ) {
				$content = $loaded;
			}
		}
	}

	$raw = array();
	if ( ! empty( $content[ $slug ]['faqs'] ) && is_array( $content[ $slug ]['faqs'] ) ) {
		$raw = $content[ $slug ]['faqs'];
	} else {
		$map = buckleup_seo_location_faq_map();
		if ( ! empty( $map[ $slug ] ) ) {
			$raw = $map[ $slug ];
		}
	}

	$items = array();
	foreach ( (array) $raw as $faq ) {
		$q = isset( $faq['q'] ) ? trim( wp_strip_all_tags( (string) $faq['q'] ) ) : '';
		$a = isset( $faq['a'] ) ? trim( wp_strip_all_tags( (string) $faq['a'] ) ) : '';
		if ( '' !== $q && '' !== $a ) {
			$items[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
	}
	return $items;
}

/* -------------------------------------------------------------------------
 * Organization / LocalBusiness node (the @graph anchor)
 * ---------------------------------------------------------------------- */

/**
 * The multi-type LocalBusiness/EducationalOrganization/DrivingSchool node,
 * verbatim from src/components/seo/LocalBusinessSchema.tsx but with a stable @id
 * so other nodes (BreadcrumbList, BlogPosting publisher, WebSite) can reference it.
 *
 * @return array
 */
function buckleup_seo_organization_node() {
	$s         = buckleup_seo_settings();
	$org_id    = BUCKLEUP_SEO_BASE_URL . '/#organization';

	$payments = array();
	foreach ( (array) $s['payments'] as $method ) {
		$payments[] = $method;
	}

	// Per-location specificity: on a /locations/{slug}/ page, qualify the business
	// name with the city, swap in the city's areaServed + geo, so the node reads as
	// a local business for THAT city. Everywhere else (homepage, etc.) keeps the
	// global name + the head-office geo + the regional areaServed. The @id stays the
	// SAME sitewide #organization id so cross-references (WebSite/Breadcrumb/Article
	// publisher) still resolve — this is one business with a local face per page.
	$loc          = buckleup_seo_current_location();
	$name         = $loc ? (string) $loc['name'] : (string) $s['name'];
	$geo_lat      = $loc ? (float) $loc['lat'] : (float) $s['lat'];
	$geo_lng      = $loc ? (float) $loc['lng'] : (float) $s['lng'];
	$served_cities = ( $loc && ! empty( $loc['area_served'] ) ) ? (array) $loc['area_served'] : (array) $s['area_served'];

	$area_served = array();
	foreach ( $served_cities as $city ) {
		$area_served[] = array(
			'@type' => 'City',
			'name'  => $city,
		);
	}

	return array(
		'@type'         => array( 'LocalBusiness', 'EducationalOrganization', 'DrivingSchool' ),
		'@id'           => $org_id,
		'name'          => $name,
		'alternateName' => $s['alternate_name'],
		'description'   => $s['description'],
		'url'           => BUCKLEUP_SEO_BASE_URL,
		'logo'          => $s['logo'],
		'image'         => $s['logo'],
		'telephone'     => $s['phone'],
		'email'         => $s['email'],
		'foundingDate'  => (string) $s['founding_date'],
		'address'       => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $s['street'],
			'addressLocality' => $s['locality'],
			'addressRegion'   => $s['region'],
			'postalCode'      => $s['postal'],
			'addressCountry'  => $s['country'],
		),
		'geo'           => array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $geo_lat,
			'longitude' => $geo_lng,
		),
		'openingHoursSpecification' => array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
			'opens'     => $s['opens'],
			'closes'    => $s['closes'],
		),
		'priceRange'     => $s['price_range'],
		'paymentAccepted' => $payments,
		'areaServed'     => $area_served,
		'hasOfferCatalog' => array(
			'@type'           => 'OfferCatalog',
			'name'            => 'Driving Lesson Services',
			'itemListElement' => array(
				array(
					'@type'       => 'Offer',
					'itemOffered' => array(
						'@type'       => 'Service',
						'name'        => 'Class 7L Learner Training',
						'description' => 'Comprehensive driving training for learner drivers. Packages range from $100 single sessions to $620 full course packages.',
					),
				),
				array(
					'@type'       => 'Offer',
					'itemOffered' => array(
						'@type'       => 'Service',
						'name'        => 'Class 7N Novice Training',
						'description' => 'Road test preparation for novice license. Packages range from $100 single sessions to $620 full course packages.',
					),
				),
				array(
					'@type'       => 'Offer',
					'itemOffered' => array(
						'@type'       => 'Service',
						'name'        => 'Class 5 Road Test Prep',
						'description' => 'Advanced training for full license. Packages range from $100 single sessions to $620 full course packages.',
					),
				),
				array(
					'@type'       => 'Offer',
					'itemOffered' => array(
						'@type'       => 'Service',
						'name'        => 'Class 4 Commercial Training',
						'description' => 'Professional commercial driving training.',
					),
				),
			),
		),
		'aggregateRating' => array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $s['rating_value'],
			'reviewCount' => (string) $s['review_count'],
			'bestRating'  => (string) $s['best_rating'],
			'worstRating' => (string) $s['worst_rating'],
		),
		'sameAs'          => array_values(
			array_filter(
				array( $s['whatsapp'], $s['instagram'], $s['facebook'] )
			)
		),
	);
}

/* -------------------------------------------------------------------------
 * FAQ node — SINGLE SOURCE: the `faq` CPT (title=question, content=answer)
 * ---------------------------------------------------------------------- */

/**
 * The 14 Q&A, verbatim from src/components/seo/FAQSchema.tsx. Used ONLY as a
 * fallback when the `faq` CPT has no published posts yet (e.g. before content
 * migration). Once the CPT is seeded, the CPT is the single source for both the
 * visible accordion and this schema, so they can never drift.
 *
 * @return array<int,array{question:string,answer:string}>
 */
function buckleup_seo_faq_fallback() {
	return array(
		array(
			'question' => 'How long does it take to learn to drive in Vancouver?',
			'answer'   => 'Most beginner drivers in Vancouver need around six professional driving lessons to build basic skills and feel ready for the ICBC road test. Lesson plans are designed around each student’s experience and confidence level. Beginners typically start with vehicle control, parking, and safe lane changes before progressing to real traffic situations.',
		),
		array(
			'question' => 'What vehicles are used for driving lessons?',
			'answer'   => 'We use safe, modern Toyota vehicles that are easy to control and well-suited for beginner drivers.',
		),
		array(
			'question' => 'What is the cancellation policy for driving lessons?',
			'answer'   => 'Lessons cancelled at least 24 hours in advance receive a full refund. Late cancellations or missed appointments may result in a $35 fee.',
		),
		array(
			'question' => 'Do you offer ICBC road test preparation?',
			'answer'   => 'Yes, we provide focused ICBC road test preparation, including parking, lane changes, intersection safety, and defensive driving techniques.',
		),
		array(
			'question' => 'Do you offer lessons in different languages?',
			'answer'   => 'Yes, lessons are available in English and Farsi to ensure clear communication and better learning.',
		),
		array(
			'question' => 'What areas do you serve?',
			'answer'   => 'We offer driving lessons in Vancouver, Coquitlam, Port Coquitlam, Port Moody, and North Vancouver.',
		),
		array(
			'question' => 'How can I book lessons?',
			'answer'   => 'You can book online through the BuckleUp Driving School website by selecting a package and schedule.',
		),
		array(
			'question' => 'What should I bring to my first lesson?',
			'answer'   => 'Bring your valid driver’s license, glasses if required, and arrive early.',
		),
		array(
			'question' => 'How long is each lesson?',
			'answer'   => 'Each lesson typically lasts about 90 minutes, allowing time for instruction, practice, and feedback.',
		),
		array(
			'question' => 'What will I learn in my first lesson?',
			'answer'   => 'You will learn basic vehicle control, safety awareness, steering, and road positioning in a low-traffic area.',
		),
		array(
			'question' => 'How many lessons do I need to pass?',
			'answer'   => 'Most beginners need around six lessons, depending on their experience and confidence level.',
		),
		array(
			'question' => 'What payment methods do you accept?',
			'answer'   => 'We accept cash and e-transfer.',
		),
		array(
			'question' => 'Are there any hidden fees?',
			'answer'   => 'No, we provide transparent pricing with no hidden fees.',
		),
		array(
			'question' => 'Can I get a refund for unused lessons?',
			'answer'   => 'Yes, unused lesson hours may be refunded according to our policy.',
		),
	);
}

/**
 * The FAQ Q&A pairs to render.
 *
 * SINGLE SOURCE: the buckleup-core helper `buckleup_get_faqs()` (returns
 * `[ id, question, answer (plain text) ]`, ordered by menu_order, active-only) —
 * the SAME helper the theme renders the visible accordion from, so the accordion
 * and this FAQPage schema can never drift (per docs/CONTENT-MODEL.md).
 *
 * If the helper isn't loaded yet (buckleup-core inactive) or returns nothing
 * (CPT not seeded), fall back to the 14 verbatim source Q&A so the schema is
 * never empty.
 *
 * @return array<int,array{question:string,answer:string}>
 */
function buckleup_seo_faq_items() {
	$items = array();

	// On a location page, prefer that city's OWN FAQs (the same Q&A the Elementor
	// page body shows) so the FAQPage schema matches the visible accordion for
	// THAT location and is rich with local-intent terms. Falls through to the
	// homepage/CPT FAQ below when the content file isn't deployed.
	$location_faqs = buckleup_seo_location_faq_items();
	if ( ! empty( $location_faqs ) ) {
		return $location_faqs;
	}

	if ( function_exists( 'buckleup_get_faqs' ) ) {
		foreach ( (array) buckleup_get_faqs() as $faq ) {
			$question = isset( $faq['question'] ) ? trim( wp_strip_all_tags( $faq['question'] ) ) : '';
			$answer   = isset( $faq['answer'] ) ? trim( wp_strip_all_tags( $faq['answer'] ) ) : '';
			if ( '' !== $question && '' !== $answer ) {
				$items[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}
	}

	if ( empty( $items ) ) {
		$items = buckleup_seo_faq_fallback();
	}

	return $items;
}

/**
 * FAQPage node built from the single FAQ source.
 *
 * @return array
 */
function buckleup_seo_faq_node() {
	$entities = array();
	foreach ( buckleup_seo_faq_items() as $item ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $item['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['answer'],
			),
		);
	}
	return array(
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
}

/* -------------------------------------------------------------------------
 * BreadcrumbList — sitewide
 * ---------------------------------------------------------------------- */

/**
 * Human-friendly breadcrumb label for a path segment.
 *
 * @param string $segment Raw URL segment (e.g. "icbc-road-test-failures").
 * @return string
 */
function buckleup_seo_humanise_segment( $segment ) {
	$overrides = array(
		'icbc-road-test-failures' => 'Top 5 Reasons Students Fail the ICBC Road Test',
		'north-vancouver'         => 'North Vancouver',
		'port-coquitlam'          => 'Port Coquitlam',
		'port-moody'              => 'Port Moody',
		'tri-cities'              => 'Tri-Cities',
		'coquitlam'               => 'Coquitlam',
		'icbc'                    => 'ICBC',
		'faq'                     => 'FAQ',

		// ICBC Class 4 practice-test hub + 12 categories. Prefer the quiz
		// plugin's own labels for the categories (kept in sync via the loop
		// below); these literals are the fallback when the plugin isn't loaded.
		'icbc-class-4-knowledge-test'     => 'ICBC Class 4 Knowledge Test',
		'getting-your-licence'            => 'Getting Your Licence',
		'heavy-vehicle-braking'           => 'Heavy Vehicle Braking',
		'basic-driving-skills'            => 'Basic Driving Skills',
		'fuel-efficient-driving'          => 'Fuel-Efficient Driving',
		'trucks-and-trailers'             => 'Trucks and Trailers',
		'buses-taxis-limos-ride-hailing'  => 'Buses, Taxis, Limos & Ride-Hailing',
		'hours-of-service'                => 'Hours of Service',
		'air-brakes'                      => 'Air Brakes',
		'air-brake-adjustment'            => 'Air Brake Adjustment',
		'pre-trip-inspections'            => 'Pre-Trip Inspections',
		'signs-signals-and-markings'      => 'Signs, Signals & Road Markings',
		'industrial-roads'                => 'Industrial Roads',
	);

	// Defer to the quiz plugin's canonical category label when available, so a
	// future label edit there flows through to breadcrumbs automatically.
	if ( function_exists( 'buckleup_quiz_is_category' ) && function_exists( 'buckleup_quiz_category_label' ) && buckleup_quiz_is_category( $segment ) ) {
		return buckleup_quiz_category_label( $segment );
	}
	if ( isset( $overrides[ $segment ] ) ) {
		return $overrides[ $segment ];
	}
	return ucwords( str_replace( '-', ' ', $segment ) );
}

/**
 * BreadcrumbList node for the current request (Home → … → current). Built from the
 * canonical path so every segment links to its www URL. Omitted on the homepage,
 * where a single-item trail adds nothing.
 *
 * @return array|null
 */
function buckleup_seo_breadcrumb_node() {
	if ( is_front_page() ) {
		return null;
	}

	$path = wp_make_link_relative( buckleup_seo_current_url() );
	$path = trim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return null;
	}

	$segments = explode( '/', $path );
	$last_idx = count( $segments ) - 1;

	// On a singular post/page, use the REAL queried-object title for the leaf
	// crumb instead of a humanised slug (e.g. "How to Pass Your ICBC Class 5
	// Road Test in Vancouver" rather than "How To Pass Icbc Class 5 …").
	$leaf_title = '';
	if ( is_singular() ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$leaf_title = trim( wp_strip_all_tags( get_the_title( $queried ) ) );
		}
	}

	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => 'Home',
			'item'     => buckleup_seo_url( '/' ),
		),
	);

	$accum    = '';
	$position = 2;
	foreach ( $segments as $i => $segment ) {
		$accum  .= '/' . $segment;
		$name    = ( $i === $last_idx && '' !== $leaf_title )
			? $leaf_title
			: buckleup_seo_humanise_segment( $segment );

		$item = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $name,
		);

		// Only advertise an `item` URL for a crumb that resolves to a real,
		// fetchable page. The leaf is always the current request (a 200), so it
		// keeps its URL; an INTERMEDIATE segment that has no page of its own —
		// e.g. /locations/, which 404s because the cities are a flat CPT with no
		// archive page — would otherwise point Google at a 404 (a structured-data
		// quality defect). `item` is optional per schema.org, so we omit it for an
		// intermediate crumb that doesn't resolve, keeping a valid, non-broken
		// BreadcrumbList. (Resolution is checked against the SERVING host because
		// url_to_postid matches on home_url(); the emitted URL stays www-canonical.)
		$is_leaf   = ( $i === $last_idx );
		$resolves  = $is_leaf || ( 0 !== url_to_postid( home_url( user_trailingslashit( $accum ) ) ) );
		if ( $resolves ) {
			$item['item'] = buckleup_seo_url( $accum );
		}

		$items[] = $item;
		$position++;
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/* -------------------------------------------------------------------------
 * BlogPosting / Article — single posts + the ICBC guide
 * ---------------------------------------------------------------------- */

/**
 * BlogPosting node for a native post.
 *
 * @return array|null
 */
function buckleup_seo_blogposting_node() {
	if ( ! is_singular( 'post' ) ) {
		return null;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	$s   = buckleup_seo_settings();
	$url = buckleup_seo_current_url();

	$image = get_the_post_thumbnail_url( $post, 'full' );
	if ( ! $image ) {
		$image = $s['logo'];
	}

	$excerpt = has_excerpt( $post )
		? wp_strip_all_tags( get_the_excerpt( $post ) )
		: wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '' );

	return array(
		'@type'            => 'BlogPosting',
		'@id'              => $url . '#article',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $url,
		),
		'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
		'description'      => $excerpt,
		'image'            => $image,
		'datePublished'    => get_the_date( DATE_W3C, $post ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
		'author'           => array(
			'@type' => 'Organization',
			'name'  => $s['name'],
			'url'   => BUCKLEUP_SEO_BASE_URL,
		),
		'publisher'        => array( '@id' => BUCKLEUP_SEO_BASE_URL . '/#organization' ),
	);
}

/**
 * Article node for the ICBC road-test guide (verbatim headline/description).
 *
 * @return array|null
 */
function buckleup_seo_icbc_article_node() {
	if ( ! buckleup_seo_is_icbc_guide() ) {
		return null;
	}
	$s   = buckleup_seo_settings();
	$url = buckleup_seo_current_url();

	return array(
		'@type'            => 'Article',
		'@id'              => $url . '#article',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $url,
		),
		'headline'         => 'Top 5 Reasons Students Fail the ICBC Road Test',
		'description'      => 'Learn the exact reasons why Vancouver students fail their ICBC Class 5 and Class 7 road tests, and how BuckleUp Driving School helps you achieve a 98% pass rate.',
		'image'            => $s['logo'],
		'author'           => array(
			'@type' => 'Organization',
			'name'  => $s['name'],
			'url'   => BUCKLEUP_SEO_BASE_URL,
		),
		'publisher'        => array( '@id' => BUCKLEUP_SEO_BASE_URL . '/#organization' ),
	);
}

/* -------------------------------------------------------------------------
 * ICBC Class 4 knowledge/practice test — Quiz + Question (Education Q&A) on the
 * hub + 12 category pages, plus an FAQPage on the hub only.
 *
 * The interactive runner is JS, so it is invisible to indexing; the theme
 * pattern renders a FIXED set of crawlable sample questions per landing page via
 * buckleup_quiz_sample_questions(). To stay compliant with Google's
 * practice-problems guidance (marked-up content MUST be the content visible on
 * the page), this layer marks up ONLY those same visible samples — it calls the
 * SAME helper with the SAME arguments the theme uses (mixed `('',6)` on the hub,
 * `($cat,6)` on a category page), so the Question count in the JSON-LD always
 * equals the questions a visitor (and a crawler) actually see, and the full
 * question bank stays behind the JS runner where it can't become a free SERP
 * answer key. All quiz helpers are guarded with function_exists() because they
 * come from the separately-owned buckleup-quiz plugin.
 * ---------------------------------------------------------------------- */

/**
 * The current practice-test page context, or null when this isn't one.
 *
 * Prefers the buckleup-quiz plugin's own detector (the single source of truth
 * the theme + REST runner share) and falls back to a path check on the base
 * slug so the schema layer still works if the helper is briefly unavailable.
 *
 * @return array{type:string,category:string}|null
 */
function buckleup_seo_practice_context() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$cache = null;

	if ( function_exists( 'buckleup_quiz_page_context' ) ) {
		$ctx = buckleup_quiz_page_context();
		if ( is_array( $ctx ) && ! empty( $ctx['type'] ) ) {
			$cache = array(
				'type'     => (string) $ctx['type'],
				'category' => isset( $ctx['category'] ) ? (string) $ctx['category'] : '',
			);
		}
		return $cache;
	}

	// Fallback: derive context from the request path against the known base slug.
	$base = function_exists( 'buckleup_quiz_base_slug' ) ? buckleup_quiz_base_slug() : 'icbc-class-4-knowledge-test';
	$path = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) : '';
	if ( '' === $path || 0 !== strpos( $path, $base ) ) {
		return $cache;
	}
	$rest = trim( substr( $path, strlen( $base ) ), '/' );
	if ( '' === $rest ) {
		$cache = array( 'type' => 'hub', 'category' => '' );
	} else {
		$segment = explode( '/', $rest )[0];
		$cache   = array( 'type' => 'category', 'category' => $segment );
	}
	return $cache;
}

/**
 * Whether the current request is the practice-test HUB page.
 *
 * @return bool
 */
function buckleup_seo_is_practice_hub() {
	$ctx = buckleup_seo_practice_context();
	return ( null !== $ctx && 'hub' === $ctx['type'] );
}

/**
 * Whether the current request is one of the 12 practice-test CATEGORY pages.
 *
 * @return bool
 */
function buckleup_seo_is_practice_category() {
	$ctx = buckleup_seo_practice_context();
	return ( null !== $ctx && 'category' === $ctx['type'] && '' !== $ctx['category'] );
}

/**
 * Whether the current request is any practice-test landing page.
 *
 * @return bool
 */
function buckleup_seo_is_practice() {
	return buckleup_seo_is_practice_hub() || buckleup_seo_is_practice_category();
}

/**
 * Build a single Quiz `Question` (flashcard) node from a sample-question row.
 *
 * Maps the buckleup-quiz helper row onto Google's practice-problems shape:
 *   - eduQuestionType "Flashcard"  → eligible for the Education Q&A rich result
 *   - text            = the prompt
 *   - acceptedAnswer  = the CORRECT option (resolved via correct_index)
 *   - suggestedAnswer = the distractor options (the other three)
 *   - comment         = the explanation (shown on the page, so safe to mark up)
 *
 * @param array{qid:int,question:string,options:array<int,string>,correct_index:int,explanation:string} $row
 * @return array|null Null when the row is malformed.
 */
function buckleup_seo_practice_question_node( array $row ) {
	$question = isset( $row['question'] ) ? trim( wp_strip_all_tags( (string) $row['question'] ) ) : '';
	$options  = isset( $row['options'] ) && is_array( $row['options'] ) ? array_values( $row['options'] ) : array();
	$correct  = isset( $row['correct_index'] ) ? (int) $row['correct_index'] : -1;

	if ( '' === $question || count( $options ) < 2 || $correct < 0 || ! isset( $options[ $correct ] ) ) {
		return null;
	}

	$accepted = trim( wp_strip_all_tags( (string) $options[ $correct ] ) );
	if ( '' === $accepted ) {
		return null;
	}

	$suggested = array();
	foreach ( $options as $i => $opt ) {
		if ( $i === $correct ) {
			continue;
		}
		$text = trim( wp_strip_all_tags( (string) $opt ) );
		if ( '' !== $text ) {
			$suggested[] = array(
				'@type' => 'Answer',
				'text'  => $text,
			);
		}
	}

	$node = array(
		'@type'                => 'Question',
		'eduQuestionType'      => 'Flashcard',
		'learningResourceType' => 'Flashcard',
		'text'                 => $question,
		'acceptedAnswer'       => array(
			'@type' => 'Answer',
			'text'  => $accepted,
		),
	);

	if ( $suggested ) {
		$node['suggestedAnswer'] = $suggested;
	}

	$explanation = isset( $row['explanation'] ) ? trim( wp_strip_all_tags( (string) $row['explanation'] ) ) : '';
	if ( '' !== $explanation ) {
		$node['comment'] = array(
			'@type' => 'Comment',
			'text'  => $explanation,
		);
	}

	return $node;
}

/**
 * The Quiz node for the current practice-test landing page (hub or category).
 *
 * Marks up ONLY the visible sample questions — the same set the theme renders —
 * by calling buckleup_quiz_sample_questions() with the identical arguments the
 * pattern uses (mixed on the hub, single-category otherwise). Returns null when
 * this isn't a practice page or the quiz plugin/data isn't available, so the
 * schema is never an empty/orphaned Quiz.
 *
 * @return array|null
 */
function buckleup_seo_practice_quiz_node() {
	if ( ! buckleup_seo_is_practice() ) {
		return null;
	}
	if ( ! function_exists( 'buckleup_quiz_sample_questions' ) ) {
		return null;
	}

	$ctx      = buckleup_seo_practice_context();
	$category = ( null !== $ctx && 'category' === $ctx['type'] ) ? (string) $ctx['category'] : '';

	// 6 = the theme pattern's sample_count (buckleup_quiz_cfg('sample_count')).
	// Calling with the SAME args keeps schema == visible content.
	$rows = (array) buckleup_quiz_sample_questions( $category, 6 );

	$questions = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$q = buckleup_seo_practice_question_node( $row );
		if ( null !== $q ) {
			$questions[] = $q;
		}
	}

	if ( empty( $questions ) ) {
		return null;
	}

	$url = buckleup_seo_current_url();

	if ( '' !== $category ) {
		$label    = function_exists( 'buckleup_quiz_category_label' ) ? buckleup_quiz_category_label( $category ) : ucwords( str_replace( '-', ' ', $category ) );
		$quiz_name = sprintf( 'ICBC Class 4 %s Practice Test', $label );
		$about     = sprintf( 'ICBC Class 4 %s knowledge test (British Columbia)', $label );
	} else {
		$quiz_name = 'ICBC Class 4 Knowledge Test: Free Practice Questions';
		$about     = 'ICBC Class 4 commercial driver knowledge test (British Columbia)';
	}

	return array(
		'@type'                => 'Quiz',
		'@id'                  => $url . '#quiz',
		'name'                 => $quiz_name,
		'url'                  => $url,
		'about'                => array(
			'@type' => 'Thing',
			'name'  => $about,
		),
		'educationalLevel'     => 'Professional certification',
		'educationalAlignment' => array(
			'@type'          => 'AlignmentObject',
			'alignmentType'  => 'educationalSubject',
			'targetName'     => 'ICBC Class 4 Commercial Driver Licensing (British Columbia)',
		),
		'hasPart'              => $questions,
		'mainEntityOfPage'     => array(
			'@type' => 'WebPage',
			'@id'   => $url,
		),
		'publisher'            => array( '@id' => BUCKLEUP_SEO_BASE_URL . '/#organization' ),
	);
}

/**
 * The top-of-funnel FAQPage node for the practice-test HUB only.
 *
 * Distinct from the homepage/location FAQ (different intent), so it is a
 * dedicated builder rather than reusing buckleup_seo_faq_node(). Answers the
 * real informational queries about the ICBC Class 4 knowledge test (question
 * count, pass mark, who needs it, where to take it locally). Figures are pulled
 * from the quiz engine config so the schema can't drift from the live runner.
 *
 * NOTE: these Q&A must also be rendered as a visible accordion by the theme
 * pattern on the hub (the same single-source discipline used elsewhere). The
 * pattern is being built in parallel; the copy here is the agreed source.
 *
 * @return array|null
 */
function buckleup_seo_practice_faq_node() {
	if ( ! buckleup_seo_is_practice_hub() ) {
		return null;
	}

	// Single source of truth: the quiz plugin owns the hub FAQ copy so the visible
	// accordion and this FAQPage schema can never drift. Fall back to a minimal
	// inline set only if the plugin helper is unavailable.
	if ( function_exists( 'buckleup_quiz_hub_faqs' ) ) {
		$faqs = buckleup_quiz_hub_faqs();
	} else {
		$full_total = function_exists( 'buckleup_quiz_cfg' ) ? (int) buckleup_quiz_cfg( 'full_total', 50 ) : 50;
		$pass_pct   = function_exists( 'buckleup_quiz_cfg' ) ? (int) buckleup_quiz_cfg( 'pass_pct', 80 ) : 80;
		$faqs       = array(
			array(
				'question' => 'How many questions are on the ICBC Class 4 knowledge test?',
				'answer'   => sprintf( 'The ICBC Class 4 knowledge test is a multiple-choice exam of roughly %d questions. Our free full practice test mirrors that length.', $full_total ),
			),
			array(
				'question' => 'What score do I need to pass the ICBC Class 4 knowledge test?',
				'answer'   => sprintf( 'You need %d%% to pass. This practice test shows your score and a topic breakdown instantly.', $pass_pct ),
			),
		);
	}

	$entities = array();
	foreach ( $faqs as $item ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $item['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['answer'],
			),
		);
	}

	return array(
		'@type'      => 'FAQPage',
		'@id'        => buckleup_seo_current_url() . '#faq',
		'mainEntity' => $entities,
	);
}

/* -------------------------------------------------------------------------
 * WebSite node — enables the sitelinks searchbox + names the publisher
 * ---------------------------------------------------------------------- */

/**
 * WebSite node, emitted only on the homepage.
 *
 * @return array
 */
function buckleup_seo_website_node() {
	$s = buckleup_seo_settings();
	return array(
		'@type'     => 'WebSite',
		'@id'       => BUCKLEUP_SEO_BASE_URL . '/#website',
		'url'       => buckleup_seo_url( '/' ),
		'name'      => $s['name'],
		'publisher' => array( '@id' => BUCKLEUP_SEO_BASE_URL . '/#organization' ),
	);
}

/* -------------------------------------------------------------------------
 * Head output
 * ---------------------------------------------------------------------- */

/**
 * Emit all JSON-LD as a single @graph, plus geo meta tags.
 *
 * One @graph keeps nodes cross-referenced by @id (Organization, WebSite,
 * BreadcrumbList, BlogPosting/Article, FAQPage) and is the shape Google prefers.
 * The Organization node is sitewide; FAQ is home + locations; breadcrumb is
 * everywhere but the homepage; BlogPosting/Article are page-specific.
 *
 * @return void
 */
function buckleup_seo_print_head() {
	// Never emit marketing schema on admin, feeds, search, or 404s.
	if ( is_admin() || is_feed() || is_search() || is_404() ) {
		return;
	}

	$graph = array();

	// Organization is the anchor on every public page.
	$graph[] = buckleup_seo_organization_node();

	if ( buckleup_seo_is_front() ) {
		$graph[] = buckleup_seo_website_node();
	}

	if ( buckleup_seo_wants_faq() ) {
		$graph[] = buckleup_seo_faq_node();
	}

	$breadcrumb = buckleup_seo_breadcrumb_node();
	if ( $breadcrumb ) {
		$graph[] = $breadcrumb;
	}

	$blogposting = buckleup_seo_blogposting_node();
	if ( $blogposting ) {
		$graph[] = $blogposting;
	}

	$article = buckleup_seo_icbc_article_node();
	if ( $article ) {
		$graph[] = $article;
	}

	// ICBC Class 4 practice test: a Quiz (visible samples only) on the hub + 12
	// category pages, and an FAQPage on the hub. Both are no-ops off those pages.
	$practice_quiz = buckleup_seo_practice_quiz_node();
	if ( $practice_quiz ) {
		$graph[] = $practice_quiz;
	}

	$practice_faq = buckleup_seo_practice_faq_node();
	if ( $practice_faq ) {
		$graph[] = $practice_faq;
	}

	buckleup_seo_print_jsonld(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		)
	);

	// Geo meta tags — verbatim from layout.tsx `other`. Local pages anchor on
	// the business location; emitted on the front page + the location pages where
	// local-intent ranking matters most (not the /blog/ archive).
	if ( buckleup_seo_is_front() || buckleup_seo_is_location() ) {
		buckleup_seo_print_geo_meta();
	}
}
add_action( 'wp_head', 'buckleup_seo_print_head', 5 );

/**
 * Print the geo.* / ICBM meta tags.
 *
 * @return void
 */
function buckleup_seo_print_geo_meta() {
	$s   = buckleup_seo_settings();

	// On a location page, anchor the geo meta on THAT city (placename + position),
	// so local-intent ranking signals point at the city the page targets — not the
	// head office. geo.region stays CA-BC for every page (same province).
	$loc       = buckleup_seo_current_location();
	$lat       = $loc ? (float) $loc['lat'] : (float) $s['lat'];
	$lng       = $loc ? (float) $loc['lng'] : (float) $s['lng'];
	$placename = $loc ? (string) $loc['locality'] : (string) $s['locality'];

	printf(
		"<meta name=\"geo.region\" content=\"%s\" />\n",
		esc_attr( $s['country'] . '-' . $s['region'] )
	);
	printf(
		"<meta name=\"geo.placename\" content=\"%s\" />\n",
		esc_attr( $placename )
	);
	printf(
		"<meta name=\"geo.position\" content=\"%s;%s\" />\n",
		esc_attr( (string) $lat ),
		esc_attr( (string) $lng )
	);
	printf(
		"<meta name=\"ICBM\" content=\"%s, %s\" />\n",
		esc_attr( (string) $lat ),
		esc_attr( (string) $lng )
	);
}

/* -------------------------------------------------------------------------
 * Self-referential canonical — the source's #1 SEO bug
 * ---------------------------------------------------------------------- */

/**
 * Force a self-referential canonical to the page's OWN www URL.
 *
 * Source bug: inner pages inherited the homepage canonical. Whichever canonical
 * pipeline is live for a given request — Rank Math's frontend head when its meta
 * module is active, or WordPress core's `rel_canonical()` otherwise — gets routed
 * through this single function, so the emitted canonical is always the page's own
 * www-normalised URL (and never localhost in dev).
 *
 * @param string $canonical Incoming canonical URL.
 * @return string
 */
function buckleup_seo_filter_canonical( $canonical ) {
	if ( is_admin() || is_feed() ) {
		return $canonical;
	}
	return buckleup_seo_current_url();
}

// Hook BOTH canonical pipelines unconditionally. Only one of them actually
// prints a <link rel="canonical"> per request — Rank Math removes core's
// rel_canonical when its frontend head is active, and vice-versa — so there is
// never a duplicate, but the correct one is always corrected regardless of which
// is live (Rank Math may be installed-but-not-yet-configured during provisioning).
add_filter( 'rank_math/frontend/canonical', 'buckleup_seo_filter_canonical', 20 );
add_filter( 'get_canonical_url', 'buckleup_seo_filter_canonical', 20 );

// Core's rel_canonical() only prints on singular views, and Rank Math may not be
// emitting head meta yet. Guarantee a canonical on the homepage + archives too,
// but only when nothing else will print one (avoid duplicates).
add_action(
	'wp_head',
	function () {
		if ( is_admin() || is_feed() || is_404() ) {
			return;
		}
		// Core already prints on singular; Rank Math (if its head is active)
		// prints on everything. Skip in those cases.
		if ( is_singular() ) {
			return;
		}
		if ( has_action( 'rank_math/head' ) || did_action( 'rank_math/head' ) ) {
			return; // Rank Math frontend head is driving meta output.
		}
		printf(
			"<link rel=\"canonical\" href=\"%s\" />\n",
			esc_url( buckleup_seo_current_url() )
		);
	},
	9
);

/* -------------------------------------------------------------------------
 * De-duplicate the <title> tag.
 * ---------------------------------------------------------------------- */

/**
 * De-duplicate the <title> tag when Rank Math is active.
 *
 * Root cause (diagnosed): the block theme declares add_theme_support(
 * 'title-tag' ). Rank Math's Head module moves core's _wp_render_title_tag onto
 * its own `rank_math/head` action so the managed title renders inside the Rank
 * Math block — but the FSE block-theme head pass ALSO renders a <title>, so two
 * title tags ship (a duplicate-title SEO defect).
 *
 * Belt-and-suspenders cleanup at the very end of the buffered <head>: collapse
 * any duplicate <title> tags down to the FIRST one (Rank Math's SEO-managed
 * title, which is emitted first). Implemented as an output-buffer pass over the
 * head so it is robust to whichever component emits the extra tag, and is a
 * no-op when only one title is present. Scoped to the front end only.
 */
add_action(
	'template_redirect',
	function () {
		if ( is_admin() || is_feed() || is_robots() ) {
			return;
		}
		// Bracket the whole wp_head pass: open the buffer before any callback
		// runs (priority below 0) and close it after every callback (max
		// priority). Both <title> tags are emitted within wp_head, so this
		// captures and de-duplicates them. Works in FSE/block themes, which fire
		// wp_head directly without get_header().
		add_action( 'wp_head', 'buckleup_seo_start_head_buffer', -PHP_INT_MAX );
		add_action( 'wp_head', 'buckleup_seo_end_head_buffer', PHP_INT_MAX );
	}
);

/**
 * Open an output buffer at the start of the head so we can post-process it.
 *
 * @return void
 */
function buckleup_seo_start_head_buffer() {
	ob_start();
}

/**
 * Close the head buffer and drop every <title> after the first.
 *
 * @return void
 */
function buckleup_seo_end_head_buffer() {
	// Only act if a buffer we opened is active.
	if ( ob_get_level() < 1 ) {
		return;
	}
	$head = ob_get_clean();
	if ( false === $head || '' === $head ) {
		return;
	}
	$count = preg_match_all( '#<title\b[^>]*>.*?</title>#is', $head, $matches );
	if ( $count > 1 ) {
		$seen = false;
		$head = preg_replace_callback(
			'#<title\b[^>]*>.*?</title>#is',
			static function ( $m ) use ( &$seen ) {
				if ( $seen ) {
					return ''; // drop subsequent duplicates.
				}
				$seen = true;
				return $m[0]; // keep the first (Rank Math's SEO title).
			},
			$head
		);
	}

	// Rewrite og:locale → en_CA on location pages (Rank Math can't emit en_CA
	// because it's not on Facebook's locale whitelist — see the function docblock).
	$head = buckleup_seo_localise_og_locale( $head );

	echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- re-emitting already-escaped head markup.
}

/* -------------------------------------------------------------------------
 * Rank Math integration — make THIS plugin the single source of JSON-LD, and
 * normalise Rank Math's social/OG URLs onto the canonical www origin.
 * ---------------------------------------------------------------------- */

/**
 * Suppress Rank Math's auto-generated JSON-LD entirely.
 *
 * Rank Math emits its own Person/Organization/WebSite/WebPage/Article graph,
 * which (a) duplicates the hand-authored multi-type LocalBusiness/DrivingSchool
 * + FAQPage + BreadcrumbList graph this plugin owns, and (b) is polluted with the
 * dev host and a spurious homepage Article/Person. Returning an empty array
 * leaves our @graph as the only structured data on the page.
 *
 * @param array $data Rank Math JSON-LD pieces.
 * @return array Always empty.
 */
add_filter( 'rank_math/json_ld', '__return_empty_array', 99 );

/**
 * Guarantee an og:image / twitter:image on every page.
 *
 * Rank Math's global default OG image isn't reliably emitted as a fallback on
 * inner pages that lack their own featured/social image. Supply the brand logo
 * (the source's social image) whenever Rank Math would otherwise emit none, so
 * every shared URL renders a card image. Pages with their own image are
 * untouched.
 *
 * @param string $image Resolved image URL ('' when none).
 * @return string
 */
function buckleup_seo_og_image_fallback( $image ) {
	if ( is_string( $image ) && '' !== $image ) {
		return $image;
	}
	return buckleup_seo_logo_url();
}
add_filter( 'rank_math/opengraph/facebook/image', 'buckleup_seo_og_image_fallback', 20 );
add_filter( 'rank_math/opengraph/twitter/image', 'buckleup_seo_og_image_fallback', 20 );

/**
 * Normalise any absolute URL Rank Math emits (og:url, og:image, twitter:*,
 * og:image, etc.) from the dev/apex host onto the canonical www origin, so the
 * social cards advertise production URLs even while the site is served from
 * localhost. No-op in production where the host already matches.
 *
 * @param string $url Incoming URL.
 * @return string
 */
function buckleup_seo_normalise_host( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}
	$home = home_url();
	if ( $home && 0 === strpos( $url, $home ) ) {
		return BUCKLEUP_SEO_BASE_URL . substr( $url, strlen( $home ) );
	}
	// Also rewrite a bare apex origin to www.
	$apex = 'https://buckleupdriving.ca';
	if ( 0 === strpos( $url, $apex . '/' ) || $url === $apex ) {
		return BUCKLEUP_SEO_BASE_URL . substr( $url, strlen( $apex ) );
	}
	return $url;
}
add_filter( 'rank_math/frontend/canonical', 'buckleup_seo_normalise_host', 5 );
add_filter( 'rank_math/opengraph/url', 'buckleup_seo_normalise_host', 5 );
// Also normalise the social image URLs. Priority 25 runs AFTER the og-image
// fallback (priority 20) so a page that DOES have its own image (e.g. the Home
// page stores a localhost logo URL) still gets its host rewritten to www —
// otherwise prod social shares would point at the dev host.
add_filter( 'rank_math/opengraph/facebook/image', 'buckleup_seo_normalise_host', 25 );
add_filter( 'rank_math/opengraph/twitter/image', 'buckleup_seo_normalise_host', 25 );
add_filter( 'rank_math/opengraph/facebook/image_secure_url', 'buckleup_seo_normalise_host', 25 );

/* -------------------------------------------------------------------------
 * Location pages: enrich og:image with width/height/alt + emit og:locale en_CA.
 *
 * On the 5 /locations/{slug}/ pages the social image is the city's landmark
 * hero (the page's featured image), but it was wired into Rank Math as a bare
 * URL (rank_math_facebook_image), so Rank Math's add_image_by_url() path emits
 * og:image WITHOUT og:image:width/height/alt (those are only populated when the
 * image is resolved by attachment ID). We restore them from the featured-image
 * attachment metadata so Facebook/LinkedIn render the card reliably, and we use
 * the attachment's real (descriptive) alt text instead of the bare city name.
 * Scoped to is_singular('location') so the homepage and every other page are
 * untouched.
 * ---------------------------------------------------------------------- */

/**
 * The featured-image enrichment (url/width/height/alt) for the current location
 * single, or null when this isn't a location single or it has no featured image.
 *
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function buckleup_seo_location_image_meta() {
	if ( ! is_singular( 'location' ) ) {
		return null;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	$thumb_id = (int) get_post_thumbnail_id( $post );
	if ( ! $thumb_id ) {
		return null;
	}
	$meta = wp_get_attachment_metadata( $thumb_id );
	$url  = (string) wp_get_attachment_image_url( $thumb_id, 'full' );
	if ( '' === $url ) {
		return null;
	}
	$alt = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );

	return array(
		'url'    => buckleup_seo_normalise_host( $url ),
		'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
		'height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
		'alt'    => $alt,
	);
}

/**
 * Add width/height/alt to Rank Math's og:image array on location singles.
 *
 * Hooks the `image_array` filter (the secondary hook Rank Math exposes to change
 * the whole image array) so the og:image:width / og:image:height / og:image:alt
 * tags get emitted from the real attachment. Only fills a key that's missing or
 * empty — never clobbers a value Rank Math already resolved.
 *
 * @param array $attachment Rank Math's image array (at least ['url'=>...]).
 * @return array
 */
function buckleup_seo_og_image_enrich( $attachment ) {
	if ( ! is_array( $attachment ) ) {
		return $attachment;
	}
	$enrich = buckleup_seo_location_image_meta();
	if ( null === $enrich ) {
		return $attachment;
	}
	if ( empty( $attachment['width'] ) && $enrich['width'] ) {
		$attachment['width'] = $enrich['width'];
	}
	if ( empty( $attachment['height'] ) && $enrich['height'] ) {
		$attachment['height'] = $enrich['height'];
	}
	if ( empty( $attachment['alt'] ) && '' !== $enrich['alt'] ) {
		$attachment['alt'] = $enrich['alt'];
	}
	return $attachment;
}
add_filter( 'rank_math/opengraph/facebook/image_array', 'buckleup_seo_og_image_enrich', 30 );

/**
 * Emit og:locale = en_CA on location pages.
 *
 * BuckleUp is a Canadian (BC) business, so en_CA is the correct regional locale.
 * Rank Math derives og:locale from get_locale() and then validates it against
 * Facebook's OFFICIAL locale whitelist (which lists en_US / en_GB but NOT en_CA),
 * silently falling back to en_US — and exposes no filter on the emitted value. So
 * we rewrite the already-printed <meta property="og:locale"> in the head buffer
 * this plugin already brackets (the same pass that de-dupes <title>). en_CA is a
 * valid Open Graph locale and a clearer regional signal for Google. Location
 * pages only; the homepage's og:locale is left as Rank Math emits it.
 *
 * @param string $head The buffered <head> HTML.
 * @return string
 */
function buckleup_seo_localise_og_locale( $head ) {
	if ( ! is_singular( 'location' ) ) {
		return $head;
	}
	return preg_replace(
		'#(<meta\s+property=(["\'])og:locale\2\s+content=(["\']))[^"\']*(\3\s*/?>)#i',
		'${1}en_CA${4}',
		$head
	);
}

/**
 * Homepage SEO title/description fallback.
 *
 * The static front page (a "Home" Page) otherwise inherits the page-title
 * template ("Home | …"). When the front page has no manual Rank Math title, fall
 * back to the verbatim homepage title/description from the source app so the
 * homepage SERP entry is correct even before anyone edits page meta.
 */
add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		if ( is_front_page() ) {
			$page  = get_queried_object();
			$has_meta = ( $page instanceof WP_Post ) ? get_post_meta( $page->ID, 'rank_math_title', true ) : '';
			if ( '' === $has_meta ) {
				return 'Best Driving School Vancouver | BuckleUp Driving School';
			}
		}
		return $title;
	},
	20
);
add_filter(
	'rank_math/frontend/description',
	function ( $description ) {
		if ( is_front_page() ) {
			$page  = get_queried_object();
			$has_meta = ( $page instanceof WP_Post ) ? get_post_meta( $page->ID, 'rank_math_description', true ) : '';
			if ( '' === $has_meta ) {
				return 'BuckleUp Driving School is a top driving school Vancouver learners trust. Driving lessons are available in Vancouver, Tri-Cities, Coquitlam, Port Moody, and North Vancouver. Book today!';
			}
		}
		return $description;
	},
	20
);
