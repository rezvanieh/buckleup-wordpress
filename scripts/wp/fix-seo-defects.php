<?php
/**
 * Fix the SEO defects found by auditing the rendered <head> of all 51 indexable
 * URLs against the content plan.
 *
 * Every change here is either a plain bug (metadata belonging to a different
 * post) or something the plan explicitly requires. Anything that would mean
 * ASSERTING A FACT ABOUT THE BUSINESS is deliberately NOT touched - see the
 * "reported, not changed" list at the bottom.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-seo-defects.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$done = array();
$skip = array();

/* ============================================ 1. THE HUB 4 PILLAR IS HIDDEN ==
 * /locations/ carried an explicit per-post Rank Math setting of
 * noindex + nofollow. That is the pillar of Hub 4. noindex removes the hub from
 * search entirely, and nofollow is worse: it tells crawlers not to follow the
 * links on it, which is precisely the internal link flow to the five city pages
 * that the hub exists to provide. The plan (§3 Hub 4, §7 item 8) says to make
 * /locations/ a real, linked index, so this is switched on.
 */
$pillar = get_page_by_path( 'locations' );
if ( $pillar ) {
	$was = get_post_meta( $pillar->ID, 'rank_math_robots', true );
	if ( is_array( $was ) && in_array( 'noindex', $was, true ) ) {
		update_post_meta( $pillar->ID, 'rank_math_robots', array( 'index', 'follow' ) );
		$done[] = '/locations/ robots: ' . implode( ',', $was ) . ' -> index,follow  (Hub 4 pillar was hidden from search)';
	} else {
		$skip[] = '/locations/ robots already index,follow';
	}
}

/* ------ /resources/ and /instructors/: drop nofollow, keep noindex ------
 * These are not pillars in the plan, so whether they belong in the index is the
 * client's call and is left alone. But nofollow blocks link equity from reaching
 * their children (e.g. /resources/icbc-road-test-failures/, which IS indexed),
 * and that is a defect regardless of the indexing decision. noindex,follow keeps
 * them out of search while letting the links work.
 */
foreach ( array( 'resources', 'instructors' ) as $slug ) {
	$p = get_page_by_path( $slug );
	if ( ! $p ) { continue; }
	$was = get_post_meta( $p->ID, 'rank_math_robots', true );
	if ( is_array( $was ) && in_array( 'nofollow', $was, true ) ) {
		$new = array_values( array_diff( $was, array( 'nofollow' ) ) );
		if ( ! in_array( 'follow', $new, true ) ) { $new[] = 'follow'; }
		update_post_meta( $p->ID, 'rank_math_robots', $new );
		$done[] = "/$slug/ robots: " . implode( ',', $was ) . ' -> ' . implode( ',', $new ) . '  (link equity was blocked)';
	}
}

/* ==================================== 2. A POST WEARING ANOTHER POST'S TITLE ==
 * /class-7l-learners-licence-bc-step-by-step/ is the post the plan flags for
 * 1,218 impressions and ZERO clicks at position ~10.7. The cause is not ranking:
 * its Rank Math title and description were copy-pasted from the SEPARATE post
 * /class-7-road-test-preparation-bc/. So a searcher looking for how to GET a 7L
 * learner's licence saw a snippet about road-test skills and mistakes, which
 * answers a different question. Nothing in the title said "learner's licence",
 * "7L", or "how to get".
 *
 * The replacement describes what the page actually is. The other post keeps its
 * own title, which was correct for it.
 */
$p = get_page_by_path( 'class-7l-learners-licence-bc-step-by-step', OBJECT, 'post' );
if ( ! $p ) {
	$q = get_posts( array( 'post_type' => 'post', 'name' => 'class-7l-learners-licence-bc-step-by-step', 'numberposts' => 1, 'post_status' => 'any' ) );
	$p = $q ? $q[0] : null;
}
if ( $p ) {
	$title = 'How to Get a Class 7L Licence in BC (Step-by-Step)';                                  // 50 chars
	$desc  = 'Getting your Class 7L learner\'s licence in BC? Here are the eligibility rules, the knowledge test, and the documents you need, in order.'; // 134
	$old_t = get_post_meta( $p->ID, 'rank_math_title', true );
	if ( $old_t !== $title ) {
		update_post_meta( $p->ID, 'rank_math_title', $title );
		update_post_meta( $p->ID, 'rank_math_description', $desc );
		// Open Graph carried the same wrong strings.
		update_post_meta( $p->ID, 'rank_math_facebook_title', $title );
		update_post_meta( $p->ID, 'rank_math_facebook_description', $desc );
		update_post_meta( $p->ID, 'rank_math_twitter_title', $title );
		update_post_meta( $p->ID, 'rank_math_twitter_description', $desc );
		$done[] = 'class-7l post title: was "' . $old_t . '" (belonged to the road-test post) -> "' . $title . '"';
	} else {
		$skip[] = 'class-7l title already fixed';
	}
}

/* ================================================== 3. TITLES THAT TRUNCATE ==
 * Six commercial pages had titles of 64-71 characters. Google truncates around
 * 60, so the brand and part of the value proposition were being cut off mid-word
 * on exactly the pages meant to convert. Each is shortened while keeping the
 * head term at the front. The "| BuckleUp Driving School" suffix becomes
 * "| BuckleUp", which the shorter titles on the site already use.
 */
$titles = array(
	'services'                            => 'Driving Lessons & Packages in Metro Vancouver | BuckleUp',   // 56
	'services/class-7-driving-lessons'    => 'Class 7 Driving Lessons in Coquitlam | BuckleUp',            // 47
	'services/class-5-driving-lessons'    => 'Class 5 Lessons & ICBC Road Test Prep | BuckleUp',           // 48
	'services/class-4-driving-lessons'    => 'Class 4 Driving Lessons in BC | BuckleUp',                   // 40
	'services/highway-driving-lessons'    => 'Highway Driving Lessons in Vancouver | BuckleUp',            // 47
	'services/refresher-driving-lessons'  => 'Refresher & Nervous Driver Lessons | BuckleUp',              // 45
);
foreach ( $titles as $path => $new ) {
	$post = get_page_by_path( $path );
	if ( ! $post ) { $skip[] = "missing page: $path"; continue; }
	$old = (string) get_post_meta( $post->ID, 'rank_math_title', true );
	if ( $old === $new ) { continue; }
	if ( mb_strlen( $new ) > 60 ) { $skip[] = "refused, still too long: $path"; continue; }
	update_post_meta( $post->ID, 'rank_math_title', $new );
	$done[] = sprintf( '/%s/ title: %d -> %d chars', $path, mb_strlen( $old ), mb_strlen( $new ) );
}

/* ============================================ 4. THE BLOG INDEX DESCRIBED BY
 * WHATEVER WAS POSTED LAST ==
 * /blog/ had no meta description, so the social preview fell back to the newest
 * post's excerpt: the description of the blog changed every time anything was
 * published.
 */
$blog_id = (int) get_option( 'page_for_posts' );
if ( ! $blog_id ) { $b = get_page_by_path( 'blog' ); $blog_id = $b ? $b->ID : 0; }
if ( $blog_id ) {
	$d = (string) get_post_meta( $blog_id, 'rank_math_description', true );
	if ( '' === trim( $d ) ) {
		$new = 'Driving tips, ICBC road test preparation and BC licensing guides from BuckleUp Driving School in the Tri-Cities.'; // 113
		update_post_meta( $blog_id, 'rank_math_description', $new );
		$done[] = '/blog/ meta description added (was empty, so social previews used the latest post excerpt)';
	} else {
		$skip[] = '/blog/ already has a description';
	}
}

/* ---------------------------------------------------------------- report -- */

echo "APPLIED:\n";
foreach ( $done as $d ) { echo "  + $d\n"; }
if ( ! $done ) { echo "  (nothing to do)\n"; }
if ( $skip ) {
	echo "\nSKIPPED (already correct or not found):\n";
	foreach ( $skip as $s ) { echo "  - $s\n"; }
}

/*
 * REPORTED, NOT CHANGED - these need a human to supply the true value, because
 * each one asserts a fact about the business and guessing would put a false
 * claim on the site:
 *
 *   a) RESOLVED 2026-08-15: the Google Business Profile shows 5.0 stars and 33
 *      while every location meta description says "rated 4.98" and the homepage
 *      says "200+ reviews". Source: buckleup_settings.rating_value = "5.0" and
 *      review_count is EMPTY. Google requires the schema rating to match what is
 *      visible on the page. Someone must confirm the real current Google rating
 *      and review count.
 *
 *   b) Opening hours disagree between two places in our own code:
 *      buckleup_settings says 09:00-21:00 ("Mon-Sun 9am-9pm", which is what the
 *      schema emits and what the footer shows), but the contact pattern
 *      (patterns/page-contact.php) hard-codes "Mon-Sun, 9am-6pm PST". One is
 *      wrong and only the client knows which.
 *
 *   c) The homepage FAQ answers "We accept cash and e-transfer", while the
 *      Organization schema lists paymentAccepted Cash, Credit Card, E-Transfer.
 *      Either the FAQ or the schema is out of date.
 */
echo "\nNOT CHANGED (need the client to confirm the true value): aggregate rating, opening hours, accepted payment methods. See the comment block at the end of this file.\n";
