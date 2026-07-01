<?php
/**
 * Seed the practice-test landing pages: 1 hub + 12 hierarchical category
 * children. Idempotent (looks up by path, updates in place; never duplicates).
 * Run in the dev container:
 *   make wp CMD="eval-file /scripts/wp/quiz/seed-pages.php"
 *
 * Each page body is just the dynamic section block — the theme's
 * patterns/practice-test.php renders the crawlable intro + sample questions +
 * the JS runner mount, detecting hub vs category from the URL. The 13 URLs are
 * hierarchical WP Pages so Rank Math auto-sitemaps them and the breadcrumb
 * builder walks the path natively.
 */

if ( ! function_exists( 'buckleup_quiz_base_slug' ) ) {
	WP_CLI::error( 'buckleup-quiz plugin not active.' );
}

$base    = buckleup_quiz_base_slug();
$content = "<!-- wp:buckleup/section {\"name\":\"practice-test\"} /-->";

/**
 * Upsert a page by full path. Returns the post ID.
 */
function bu_quiz_upsert_page( $slug, $title, $content, $parent_id = 0, $full_path = '', $template = 'page-practice-test' ) {
	$existing = $full_path ? get_page_by_path( $full_path ) : get_page_by_path( $slug );
	$postarr  = array(
		'post_type'    => 'page',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => 'publish',
		'post_content' => $content,
		'post_parent'  => $parent_id,
	);
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$id            = (int) wp_update_post( $postarr ) ? (int) $existing->ID : 0;
	} else {
		$id = (int) wp_insert_post( $postarr );
	}
	if ( $id ) {
		// Custom template so the pattern owns the page chrome (landing: header +
		// content, no post-title; exam: a distraction-free shell, no header/footer).
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
}

// Hub.
$hub_id = bu_quiz_upsert_page(
	$base,
	'ICBC Class 4 Knowledge Test: Free Practice Test (BC)',
	$content,
	0,
	$base
);
WP_CLI::log( "Hub page #{$hub_id} -> /{$base}/" );

// Category children.
$created = 0;
foreach ( buckleup_quiz_categories() as $slug => $cat ) {
	$title = $cat['label'] . ': ICBC Class 4 Practice Test';
	$pid   = bu_quiz_upsert_page( $slug, $title, $content, $hub_id, $base . '/' . $slug );
	if ( $pid ) {
		++$created;
		WP_CLI::log( "  #{$pid} -> /{$base}/{$slug}/" );
	}
}

// Dedicated, distraction-free EXAM page (one page handles all modes via ?mode=).
// Uses the 'page-exam' template (no site header/footer) + a noindex robots meta.
$exam_id = bu_quiz_upsert_page(
	'exam',
	'Practice Exam: ICBC Class 4 Knowledge Test',
	"<!-- wp:buckleup/section {\"name\":\"exam\"} /-->",
	$hub_id,
	$base . '/exam',
	'page-exam'
);
if ( $exam_id ) {
	update_post_meta( $exam_id, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
	WP_CLI::log( "  #{$exam_id} -> /{$base}/exam/  (exam shell, noindex)" );
}

flush_rewrite_rules( false );
WP_CLI::success( "Seeded hub + {$created} category pages + exam page. (Permalinks flushed.)" );
