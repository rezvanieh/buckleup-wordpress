<?php
/**
 * Wire Hubs 2, 3 and 4 together, and canonicalise every internal link in the blog.
 *
 * Companion to link-hub1-blogs.php, which did Hub 1. Together they implement the
 * linking rules in the content plan (§5) and the priority links in §5/§7.
 *
 * WHY THIS IS NEEDED (measured on the live content, not assumed)
 * -------------------------------------------------------------
 * A crawl of all 71 published URLs and the resulting link graph showed:
 *
 *   - Hub 2 had NO pillar links at all. All six road-test cluster posts
 *     (how-to-pass-class-5, class-7-road-test-prep, what-to-expect-coquitlam, the
 *     two route posts, parallel-parking) linked to nothing that owns road-test
 *     intent, so the site's best-converting content type fed nothing.
 *   - Three posts were fully orphaned, with zero internal links in either
 *     direction: class-7-road-test-preparation-bc (1,832 words),
 *     what-to-expect-icbc-road-test-coquitlam, why-port-moody-best-place-learn-to-drive.
 *   - The Hub 3 pillar (the GLP explainer, the site's highest-impression page)
 *     linked only sideways to three sibling posts and never once to a commercial
 *     page. §5 calls this out explicitly as the link that turns informational
 *     traffic into bookings.
 *   - 35 internal links used a form that 301-redirects: a /blog/<slug> prefix
 *     (posts actually live at /<slug>/) or a missing trailing slash. Every one of
 *     those wastes the link signal the plan is trying to concentrate. This is the
 *     §8 "data needed next" question, answered by testing: /blog/x 301s to /x/.
 *   - Internal links carried target="_blank", which is for external links.
 *
 * STORAGE: 14 of the 20 posts are built in Elementor and 6 are classic, so the
 * body lives in _elementor_data for some and post_content for others. Both are
 * handled here through one transform, applied to the decoded Elementor tree
 * (never by regex over the JSON) so escaping can never be corrupted.
 *
 * Idempotent: canonicalisation converges, and every insertion is skipped when its
 * proof link is already present.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/link-hubs-2-3-4.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const BU_SITE = 'https://www.buckleupdriving.ca';

/* ------------------------------------------------------------ transforms -- */

/**
 * Canonicalise one internal URL: drop the /blog/ prefix, ensure a trailing slash.
 * Anchors, query strings and external URLs are returned untouched.
 */
function bu_canon_url( $url ) {
	$prefix = '';
	if ( 0 === strpos( $url, BU_SITE ) ) {
		$prefix = BU_SITE;
		$url    = substr( $url, strlen( BU_SITE ) );
	}
	if ( '' === $url || '/' !== $url[0] ) { return $prefix . $url; }
	if ( false !== strpos( $url, '#' ) || false !== strpos( $url, '?' ) ) { return $prefix . $url; }
	if ( preg_match( '#^/blog/(.+)$#', $url, $m ) ) { $url = '/' . $m[1]; }
	if ( '/' !== substr( $url, -1 ) ) { $url .= '/'; }
	return $prefix . $url;
}

/**
 * Rewrite every href in a chunk of HTML, and strip target="_blank" (plus the
 * rel="noopener" that exists only to serve it) from INTERNAL links only. Opening
 * an internal link in a new tab is an external-link convention; on internal
 * navigation it just litters the user's tab bar.
 */
function bu_canon_html( $html ) {
	$html = preg_replace_callback(
		'/href="([^"]+)"/',
		function ( $m ) { return 'href="' . bu_canon_url( $m[1] ) . '"'; },
		$html
	);

	return preg_replace_callback(
		'#<a\s[^>]*>#i',
		function ( $m ) {
			$tag = $m[0];
			if ( ! preg_match( '#href="(/|' . preg_quote( BU_SITE, '#' ) . ')#', $tag ) ) {
				return $tag; // external link: leave its target alone
			}
			$tag = preg_replace( '#\s*target="_blank"#i', '', $tag );
			$tag = preg_replace( '#\s*rel="noopener[^"]*"#i', '', $tag );
			return $tag;
		},
		$html
	);
}

/** Apply the transform to every string in a decoded Elementor tree. */
function bu_canon_tree( $node ) {
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) {
			if ( 'url' === $k && is_string( $v ) ) {
				$node[ $k ] = bu_canon_url( $v );
			} else {
				$node[ $k ] = bu_canon_tree( $v );
			}
		}
		return $node;
	}
	if ( is_string( $node ) && false !== strpos( $node, 'href=' ) ) {
		return bu_canon_html( $node );
	}
	return $node;
}

/**
 * Append a paragraph to the end of a body.
 *
 * For Elementor the paragraph is appended to the LAST text-editor widget rather
 * than added as a new widget, so the page keeps its existing layout and spacing
 * and the client sees the sentence inside a block they already know how to edit.
 */
function bu_append_para( $body, $html, $is_elementor ) {
	if ( ! $is_elementor ) { return $body . "\n" . $html; }

	$path = bu_last_text_path( $body );
	if ( null === $path ) { return null; }

	// Walk down to the widget by path and append. A path is used rather than
	// holding a reference to the node, because `$last = &$el` inside a closure
	// silently rebinds the local name and breaks the `use (&$last)` link, which
	// is why an earlier version of this found nothing to append to.
	$ref = &$body;
	foreach ( $path as $key ) { $ref = &$ref[ $key ]; }
	$ref['settings']['editor'] .= $html;
	unset( $ref );

	return $body;
}

/** Index path of the LAST text-editor widget in an Elementor tree, or null. */
function bu_last_text_path( array $els, array $prefix = array() ) {
	$best = null;
	foreach ( $els as $i => $el ) {
		if ( ! is_array( $el ) ) { continue; }
		$here = array_merge( $prefix, array( $i ) );
		if ( 'text-editor' === ( $el['widgetType'] ?? '' ) && isset( $el['settings']['editor'] ) ) {
			$best = $here;
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$deeper = bu_last_text_path( $el['elements'], array_merge( $here, array( 'elements' ) ) );
			if ( null !== $deeper ) { $best = $deeper; }
		}
	}
	return $best;
}

/* ---------------------------------------------------------------- links -- */

$CLASS5 = BU_SITE . '/services/class-5-driving-lessons/'; // Hub 2 pillar (plan §3: the Class 5 service page doubles as the road-test pillar)
$CLASS7 = BU_SITE . '/services/class-7-driving-lessons/';
$CLASS4 = BU_SITE . '/services/class-4-driving-lessons/';
$GLP    = BU_SITE . '/bc-graduated-licensing-program-explained-7l-7n-class-5/'; // Hub 3 pillar
$SERV   = BU_SITE . '/services/';

/**
 * slug => list of paragraphs to append. 'proof' is a substring that, if already
 * present anywhere in the body, means the link exists and the edit is skipped.
 */
$APPEND = array(

	/* ---- HUB 2 clusters -> the road-test pillar (§5 rule 1) ---- */

	'how-to-pass-icbc-class-5-road-test-vancouver' => array(
		array( 'proof' => '/services/class-5-driving-lessons/',
			'html' => '<p>Knowing the scoring is one thing, driving it is another. Our <a href="' . $CLASS5 . '">Class 5 lessons and ICBC road test preparation</a> drill these exact skills with an instructor beside you, and the <a href="' . BU_SITE . '/mastering-parallel-parking-ultimate-guide/">parallel parking guide</a> covers the manoeuvre most people lose points on.</p>' ),
	),

	'class-7-road-test-preparation-bc' => array(
		array( 'proof' => '/services/class-7-driving-lessons/',
			'html' => '<p>When you are ready to practise with an instructor, our <a href="' . $CLASS7 . '">Class 7 driving lessons</a> are built for exactly this stage, and our <a href="' . $CLASS5 . '">ICBC road test preparation</a> covers what the examiner scores on the day.</p>' ),
	),

	'what-to-expect-icbc-road-test-coquitlam' => array(
		array( 'proof' => '/locations/coquitlam/',
			'html' => '<p>Testing locally? See how we run <a href="' . BU_SITE . '/locations/coquitlam/">driving lessons in Coquitlam</a>, or go straight to <a href="' . $CLASS5 . '">ICBC road test preparation</a> if your date is already booked.</p>' ),
	),

	'icbc-road-test-routes-coquitlam-tri-cities' => array(
		array( 'proof' => '/services/class-5-driving-lessons/',
			'html' => '<p>Practising the area is half of it. Our <a href="' . $CLASS5 . '">ICBC road test preparation</a> turns that familiarity into the observation and control the examiner is actually scoring.</p>' ),
	),

	'icbc-road-test-routes-north-vancouver-lynn-valley' => array(
		array( 'proof' => '/services/class-5-driving-lessons/',
			'html' => '<p>Once you know the area, our <a href="' . $CLASS5 . '">ICBC road test preparation</a> covers the hill starts, observation and control that decide a North Shore test.</p>' ),
	),

	'mastering-parallel-parking-ultimate-guide' => array(
		array( 'proof' => '/services/class-5-driving-lessons/',
			'html' => '<p>Parallel parking is one part of the test. For the rest of it, see our <a href="' . $CLASS5 . '">ICBC road test preparation</a>, or read <a href="' . BU_SITE . '/how-to-pass-icbc-class-5-road-test-vancouver/">how the Class 5 road test is scored</a>.</p>' ),
	),

	/* ---- HUB 3: cluster -> pillar, and pillar -> commercial (§5) ---- */

	'icbc-glp-changes-2026' => array(
		array( 'proof' => '/bc-graduated-licensing-program-explained-7l-7n-class-5/',
			'html' => '<p>New to the system? Our full guide to <a href="' . $GLP . '">the BC Graduated Licensing Program</a> explains how the 7L, 7N and Class 5 stages fit together.</p>' ),
	),

	// The plan's single highest-leverage link: the site's biggest informational
	// page has never pointed at a commercial one.
	'bc-graduated-licensing-program-explained-7l-7n-class-5' => array(
		array( 'proof' => '/services/class-7-driving-lessons/',
			'html' => '<p>Ready to practise rather than read? Our <a href="' . $CLASS7 . '">Class 7 driving lessons</a> are built for the learner stage, and you can compare <a href="' . $SERV . '">lesson packages and pricing</a> whenever you want to book.</p>' ),
	),

	'class-7l-learners-licence-bc-step-by-step' => array(
		array( 'proof' => '/services/class-7-driving-lessons/',
			'html' => '<p>Once the L is in your hand, our <a href="' . $CLASS7 . '">Class 7 driving lessons</a> pick up from exactly there.</p>' ),
	),

	'icbc-knowledge-test-practice-pass-first-try' => array(
		array( 'proof' => '/services/class-4-driving-lessons/',
			'html' => '<p>Going for a commercial licence instead? Try the <a href="' . BU_SITE . '/icbc-class-4-knowledge-test/">free Class 4 practice test</a>, then see our <a href="' . $CLASS4 . '">Class 4 driving lessons</a>.</p>' ),
	),

	/* ---- HUB 4: the last orphan, pointed at its city page ---- */

	'why-port-moody-best-place-learn-to-drive' => array(
		array( 'proof' => '/locations/port-moody/',
			'html' => '<p>Learning here? See how we run <a href="' . BU_SITE . '/locations/port-moody/">driving lessons in Port Moody</a>, or compare <a href="' . $SERV . '">lesson packages and pricing</a>.</p>' ),
	),
);

/* ------------------------------------------------------------------- run -- */

$canon_changed = 0;
$appended      = 0;
$problems      = 0;

foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'name', 'order' => 'ASC' ) ) as $post ) {
	$slug         = $post->post_name;
	$raw          = (string) get_post_meta( $post->ID, '_elementor_data', true );
	$is_elementor = strlen( $raw ) > 10;

	$body = $is_elementor ? json_decode( $raw, true ) : $post->post_content;
	if ( $is_elementor && ! is_array( $body ) ) {
		echo "  !! $slug: _elementor_data will not decode, skipped\n";
		$problems++;
		continue;
	}

	$before_json = $is_elementor ? wp_json_encode( $body ) : $body;

	// 1. canonicalise first, so the proof checks below see the clean form.
	$body = $is_elementor ? bu_canon_tree( $body ) : bu_canon_html( $body );

	// 2. append the cluster links this post is missing.
	$did_append = 0;
	if ( isset( $APPEND[ $slug ] ) ) {
		$flat = $is_elementor ? wp_json_encode( $body ) : $body;
		foreach ( $APPEND[ $slug ] as $ins ) {
			$needle = str_replace( '/', '\/', $ins['proof'] );
			if ( false !== strpos( $flat, $ins['proof'] ) || false !== strpos( $flat, $needle ) ) {
				continue; // already linked
			}
			$out = bu_append_para( $body, $ins['html'], $is_elementor );
			if ( null === $out ) {
				echo "  !! $slug: no text widget to append to, skipped\n";
				$problems++;
				continue;
			}
			$body = $out;
			$flat = $is_elementor ? wp_json_encode( $body ) : $body;
			$did_append++;
		}
	}

	$after_json = $is_elementor ? wp_json_encode( $body ) : $body;
	if ( $after_json === $before_json ) {
		continue;
	}

	if ( $is_elementor ) {
		update_post_meta( $post->ID, '_elementor_data', wp_slash( $after_json ) );
		// Elementor serves cached rendered markup in preference to re-rendering.
		delete_post_meta( $post->ID, '_elementor_element_cache' );
	} else {
		wp_update_post( array( 'ID' => $post->ID, 'post_content' => $body ) );
	}

	$canon = ( $after_json !== $before_json && ! $did_append );
	if ( $canon ) { $canon_changed++; }
	if ( $did_append ) { $appended += $did_append; }

	printf( "  %-56s %-9s %s%s\n", $slug, $is_elementor ? 'elementor' : 'classic',
		$did_append ? "+$did_append link para" : '', $canon ? 'links canonicalised' : '' );
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

echo "\nAppended $appended cluster-link paragraph(s); $canon_changed post(s) canonicalised only.\n";
echo $problems ? "Finished with $problems problem(s).\n" : "No problems.\n";
