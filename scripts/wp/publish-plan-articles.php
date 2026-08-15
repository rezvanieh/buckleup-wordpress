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

/**
 * The blog-card image for a category, matched on FILENAME rather than slug.
 *
 * The cards were side-loaded under SEO-descriptive slugs, so the attachment for
 * blog-card-local.png is named "buckleup-driving-school-local-routes-areas".
 * Looking it up by the slug "blog-card-local" silently found nothing, which is
 * why the first publish left every article without a featured image. The file
 * name is the stable identifier here.
 */
function bu_article_card_id( $category ) {
	global $wpdb;
	mysqli_report( MYSQLI_REPORT_OFF );
	$like = mysqli_real_escape_string( $wpdb->dbh, 'blog-card-' . $category . '.' );
	$res  = mysqli_query(
		$wpdb->dbh,
		"SELECT p.ID FROM {$wpdb->posts} p
		   JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
		  WHERE p.post_type = 'attachment' AND m.meta_value LIKE '%{$like}%' LIMIT 1"
	);
	$row = $res ? mysqli_fetch_row( $res ) : null;
	return $row ? (int) $row[0] : 0;
}

/**
 * The author every blog post on this site is published under.
 *
 * wp_insert_post() falls back to the CURRENT user, and a WP-CLI run has none, so
 * the first publish left all six articles with post_author = 0: no byline, and
 * they would not appear under the author archive with the rest of the blog.
 *
 * Resolved by role rather than hardcoded to id 1, because the id is not
 * guaranteed to match between this dev install and production.
 */
function bu_article_author_id() {
	static $id = null;
	if ( null !== $id ) { return $id; }

	// Prefer whoever already wrote the blog, so new posts match the existing set.
	$existing = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'ASC' ) );
	if ( $existing && (int) $existing[0]->post_author > 0 ) {
		$id = (int) $existing[0]->post_author;
		return $id;
	}

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ) );
	$id     = $admins ? (int) $admins[0] : 0;
	return $id;
}

/**
 * Category term id from its slug.
 *
 * wp_set_post_terms() on a HIERARCHICAL taxonomy does not treat a string as a
 * slug, so passing 'local' did not assign the Local category and the articles
 * published uncategorised. Integer term ids are unambiguous.
 */
function bu_article_category_id( $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	return $term ? (int) $term->term_id : 0;
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

	$author = bu_article_author_id();
	if ( ! $author ) {
		echo "  !! $slug: could not resolve an author, not published\n";
		$problems++;
		continue;
	}

	$postarr = array(
		'post_author'  => $author,
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

	$cat_id = bu_article_category_id( $a['category'] );
	if ( $cat_id ) {
		wp_set_post_terms( $post_id, array( $cat_id ), 'category', false );
	} else {
		echo "  !! $slug: category '{$a['category']}' not found\n";
		$problems++;
	}
	wp_set_post_terms( $post_id, $a['tags'], 'post_tag', false );

	update_post_meta( $post_id, 'rank_math_title', $a['seo_title'] );
	update_post_meta( $post_id, 'rank_math_description', $a['seo_desc'] );
	update_post_meta( $post_id, 'rank_math_focus_keyword', $a['focus_kw'] );

	$card = bu_article_card_id( $a['category'] );
	if ( $card ) {
		set_post_thumbnail( $post_id, $card );
	} else {
		echo "  !! $slug: no blog-card image for category '{$a['category']}'\n";
		$problems++;
	}

	$words = str_word_count( wp_strip_all_tags( $a['content'] ) );
	printf( "  %-9s %-42s %5d words  cat=%-10s card=%-4s\n", $verb, $slug, $words, $a['category'], $card ?: 'none' );
	$published++;
}

wp_cache_flush();

echo "\n$published article(s) $status" . ( $problems ? ", $problems problem(s)" : '' ) . ".\n";
