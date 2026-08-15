<?php
/**
 * Publish the six articles the content plan marks "Create".
 *
 *   Hub 2  icbc-road-test-routes-port-moody
 *          icbc-road-test-routes-port-coquitlam
 *          icbc-road-test-routes-vancouver
 *          common-icbc-road-test-mistakes
 *   Hub 3  class-7n-novice-restrictions-bc
 *          class-7n-to-class-5-bc
 *
 * Content lives in scripts/wp/articles/, separate from this publisher, so the
 * writing can be reviewed and edited without touching the mechanics.
 *
 * Behaviour:
 *   - Idempotent. An existing post with the same slug is UPDATED, never
 *     duplicated, so re-running after an edit republishes cleanly.
 *   - Sets the category, tags, excerpt and Rank Math title/description/focus
 *     keyword to match how the site's existing posts are configured.
 *   - Reuses the per-category blog-card image the other posts use as their
 *     featured image, so the blog index and social cards look consistent.
 *   - Refuses to publish an article whose internal links are not in the site's
 *     canonical /<slug>/ form, since that is the mistake this project has
 *     already had to clean up once.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/publish-plan-articles.php
 *      add 'draft' to publish them as drafts instead of live posts.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$status = ( isset( $args ) && is_array( $args ) && in_array( 'draft', $args, true ) ) ? 'draft' : 'publish';

$articles = array_merge(
	require __DIR__ . '/articles/hub2-route-posts.php',
	require __DIR__ . '/articles/hub2-hub3-guides.php'
);

/** The blog-card image each category uses, matching the existing posts. */
function bu_article_card_id( $category ) {
	$slug = 'blog-card-' . $category;
	$found = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'name'           => $slug,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	return $found ? (int) $found[0] : 0;
}

$published = 0;
$problems  = 0;

foreach ( $articles as $slug => $a ) {

	// --- guard: internal links must already be canonical -------------------
	if ( preg_match_all( '#href="(/[^"]*)"#', $a['content'], $m ) ) {
		foreach ( array_unique( $m[1] ) as $href ) {
			if ( 0 === strpos( $href, '/blog/' ) ) {
				echo "  !! $slug: /blog/ prefixed link ($href). Not published.\n";
				$problems++;
				continue 2;
			}
			if ( '/' !== substr( $href, -1 ) && false === strpos( $href, '#' ) ) {
				echo "  !! $slug: link missing its trailing slash ($href). Not published.\n";
				$problems++;
				continue 2;
			}
		}
	}

	// --- guard: house style -------------------------------------------------
	if ( false !== strpos( $a['content'], "\xE2\x80\x94" ) ) {
		echo "  !! $slug: contains an em dash, which this site does not use. Not published.\n";
		$problems++;
		continue;
	}

	$existing = get_posts( array( 'post_type' => 'post', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) );

	$postarr = array(
		'post_title'   => $a['title'],
		'post_name'    => $slug,
		'post_content' => trim( $a['content'] ),
		'post_excerpt' => $a['excerpt'],
		'post_status'  => $status,
		'post_type'    => 'post',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing[0]->ID;
		$post_id       = wp_update_post( $postarr, true );
		$verb          = 'updated';
	} else {
		$post_id = wp_insert_post( $postarr, true );
		$verb    = 'created';
	}

	if ( is_wp_error( $post_id ) ) {
		echo "  !! $slug: " . $post_id->get_error_message() . "\n";
		$problems++;
		continue;
	}

	wp_set_post_terms( $post_id, array( $a['category'] ), 'category', false );
	wp_set_post_terms( $post_id, $a['tags'], 'post_tag', false );

	update_post_meta( $post_id, 'rank_math_title', $a['seo_title'] );
	update_post_meta( $post_id, 'rank_math_description', $a['seo_desc'] );
	update_post_meta( $post_id, 'rank_math_focus_keyword', $a['focus_kw'] );

	if ( ! get_post_thumbnail_id( $post_id ) ) {
		$card = bu_article_card_id( $a['category'] );
		if ( $card ) { set_post_thumbnail( $post_id, $card ); }
	}

	$words = str_word_count( wp_strip_all_tags( $a['content'] ) );
	printf( "  %-9s %-42s %5d words  title %2d  desc %3d\n", $verb, $slug, $words, mb_strlen( $a['seo_title'] ), mb_strlen( $a['seo_desc'] ) );
	$published++;
}

wp_cache_flush();

echo "\n$published article(s) $status" . ( $problems ? ", $problems problem(s)" : '' ) . ".\n";
