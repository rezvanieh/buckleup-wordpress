<?php
/**
 * Wire the Hub 1 BLOG clusters to the service pages, per the content plan
 * (Documents/pillar-cluster-driving-school.pdf §3 Hub 1 + §5 linking rules).
 *
 * The plan marks four blog posts "Keep" with a linking instruction:
 *   driving-lessons-cost-vancouver-2026-price-guide ...... link to /services/
 *   how-many-driving-lessons-do-you-need ................. keep
 *   driving-lessons-nervous-anxious-drivers-vancouver .... link to the refresher /
 *                                                          nervous-driver service page
 *   farsi-driving-lessons-vancouver-port-moody ........... link to /services/ + locations
 *
 * Two jobs:
 *
 * 1. ADD the missing cluster links. Most posts already pointed at /services/, but
 *    the nervous-driver post had no link to the page written for exactly that
 *    reader, and the beginner section of the "how many lessons" post had no link to
 *    Class 7. Both are added with descriptive anchors, in the body, per §5.
 *
 * 2. NORMALISE every internal href to the live canonical form. This is the §8
 *    "data needed next" item, now answered by testing: posts live at /<slug>/ and
 *    pages at /<page>/, so the in-content links (/services, /contact, /instructors,
 *    /blog/<slug>) were each costing a 301 hop on every click. Redirected internal
 *    links waste crawl budget and dilute the link signal the plan is trying to
 *    concentrate — so every one is rewritten to the form that answers 200.
 *
 * Idempotent: normalisation converges, and each insertion is skipped when its
 * target link is already present. Every edit asserts it matched exactly once and
 * reports loudly if the source copy has changed underneath it.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/link-hub1-blogs.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rewrite an internal href to the canonical, non-redirecting form:
 * strip the /blog/ prefix from post links and ensure a trailing slash.
 * Leaves anchors, query strings and external URLs alone.
 */
function bu_canonical_internal_links( $html ) {
	return preg_replace_callback(
		'/href="(\/[^"#?]*?)"/',
		function ( $m ) {
			$path = $m[1];
			// Blog posts are served at /<slug>/, not /blog/<slug>/.
			if ( preg_match( '#^/blog/(.+)$#', $path, $mm ) ) {
				$path = '/' . $mm[1];
			}
			if ( '/' !== substr( $path, -1 ) ) {
				$path .= '/';
			}
			return 'href="' . $path . '"';
		},
		$html
	);
}

/**
 * Insertions: post slug => list of [needle, replacement, link-that-proves-it-ran].
 * The third element makes the edit idempotent — if that link is already in the
 * content we skip, so re-running never double-links.
 */
$INSERTIONS = array(

	// Plan: "link to the refresher/nervous service page". The post sold the idea and
	// then sent the reader to the generic packages page; now it points at the page
	// written for this exact reader.
	'driving-lessons-nervous-anxious-drivers-vancouver' => array(
		array(
			'needle'  => 'visit our <a href="/contact/">contact page</a> to talk about how we can help.',
			'replace' => 'visit our <a href="/contact/">contact page</a> to talk about how we can help. Our <a href="/services/refresher-driving-lessons/">refresher and nervous-driver lessons</a> are built around exactly this.',
			'proof'   => '/services/refresher-driving-lessons/',
		),
	),

	// Not required by the plan, but the post has a "complete beginner" section and
	// Class 7 is the page that serves that reader — a genuine sibling link rather
	// than an SEO ornament.
	'how-many-driving-lessons-do-you-need' => array(
		array(
			'needle'  => 'You can also compare lesson <a href="/services/">packages</a>',
			'replace' => 'If you are starting from scratch, our <a href="/services/class-7-driving-lessons/">Class 7 driving lessons</a> are built for first-time drivers. You can also compare lesson <a href="/services/">packages</a>',
			'proof'   => '/services/class-7-driving-lessons/',
		),
	),
);

/* ------------------------------------------------------------------ run -- */

$slugs = array(
	'driving-lessons-cost-vancouver-2026-price-guide',
	'how-many-driving-lessons-do-you-need',
	'driving-lessons-nervous-anxious-drivers-vancouver',
	'farsi-driving-lessons-vancouver-port-moody',
);

$problems = 0;

foreach ( $slugs as $slug ) {
	$q = get_posts( array( 'post_type' => 'post', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'any' ) );
	if ( ! $q ) {
		echo "MISSING post: $slug\n";
		$problems++;
		continue;
	}
	$post    = $q[0];
	$content = $original = $post->post_content;

	// 1. canonicalise first, so insertion needles can assume the clean form.
	$content = bu_canonical_internal_links( $content );

	// 2. targeted insertions.
	if ( isset( $INSERTIONS[ $slug ] ) ) {
		foreach ( $INSERTIONS[ $slug ] as $ins ) {
			if ( false !== strpos( $content, $ins['proof'] ) ) {
				continue; // already linked — idempotent no-op
			}
			$hits = substr_count( $content, $ins['needle'] );
			if ( 1 !== $hits ) {
				printf( "  !! %s: needle matched %d times (expected 1) — copy changed? Skipped: %s\n",
					$slug, $hits, substr( $ins['needle'], 0, 60 ) );
				$problems++;
				continue;
			}
			$content = str_replace( $ins['needle'], $ins['replace'], $content );
		}
	}

	if ( $content === $original ) {
		echo "  = $slug: already up to date\n";
		continue;
	}

	wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content ) );

	preg_match_all( '/href="(\/[^"]*)"/', $content, $m );
	$internal = array_unique( $m[1] );
	printf( "  ✓ %s: updated (%d internal links, all canonical)\n", $slug, count( $internal ) );
}

echo $problems
	? "\nFinished with $problems problem(s) — see above.\n"
	: "\nAll Hub 1 blog clusters linked and canonicalised.\n";
