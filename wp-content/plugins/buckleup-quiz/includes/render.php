<?php
/**
 * Front-end helpers: landing-page URLs, crawlable sample questions, page-context
 * detection, and the runner's JS config. Shared by the theme pattern that
 * renders the hub/category pages and by the SEO schema (which marks up ONLY the
 * visible sample questions these helpers return).
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The base URL slug for the practice-test pages (the hub). Filterable.
 *
 * @return string
 */
function buckleup_quiz_base_slug() {
	return (string) apply_filters( 'buckleup_quiz_base_slug', 'icbc-class-4-knowledge-test' );
}

/**
 * Hub landing-page URL.
 *
 * @return string
 */
function buckleup_quiz_hub_url() {
	return home_url( '/' . buckleup_quiz_base_slug() . '/' );
}

/**
 * Category landing-page URL.
 *
 * @param string $slug
 * @return string
 */
function buckleup_quiz_category_url( $slug ) {
	return home_url( '/' . buckleup_quiz_base_slug() . '/' . sanitize_title( $slug ) . '/' );
}

/**
 * Detect which practice-test page is being viewed.
 *
 * @return array{type:string,category:string} type = 'hub' | 'category' | ''.
 */
function buckleup_quiz_page_context() {
	$none = array( 'type' => '', 'category' => '' );
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$path = trim( $path, '/' );
	$base = buckleup_quiz_base_slug();

	if ( '' === $path || 0 !== strpos( $path, $base ) ) {
		return $none;
	}
	$rest = trim( substr( $path, strlen( $base ) ), '/' );
	if ( '' === $rest ) {
		return array( 'type' => 'hub', 'category' => '' );
	}
	$segment = explode( '/', $rest )[0];
	if ( buckleup_quiz_is_category( $segment ) ) {
		return array( 'type' => 'category', 'category' => $segment );
	}
	return $none;
}

/**
 * Stable set of sample questions to render as crawlable HTML on a landing page.
 *
 * IMPORTANT (SEO compliance): these are the ONLY questions whose answer is shown
 * on the page, so they are the only questions that may be marked up in
 * Quiz/Question JSON-LD. Stable (ORDER BY id) so the rendered samples and the
 * schema stay identical across requests and survive page caching.
 *
 * @param string $category Category slug, or '' for a mixed set across categories.
 * @param int    $count
 * @return array<int,array{qid:int,category:string,question:string,options:string[],correct_index:int,explanation:string}>
 */
function buckleup_quiz_sample_questions( $category = '', $count = 0 ) {
	global $wpdb;
	$count = $count > 0 ? (int) $count : buckleup_quiz_cfg( 'sample_count', 6 );
	$table = buckleup_quiz_table( 'questions' );

	if ( buckleup_quiz_is_category( $category ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, category, question, option_a, option_b, option_c, option_d, correct_index, explanation
				 FROM {$table} WHERE is_active = 1 AND category = %s ORDER BY id ASC LIMIT %d",
				$category,
				$count
			),
			ARRAY_A
		);
	} else {
		// Mixed: one-per-category round-robin for variety, stable order.
		$rows_all = $wpdb->get_results(
			"SELECT id, category, question, option_a, option_b, option_c, option_d, correct_index, explanation
			 FROM {$table} WHERE is_active = 1 ORDER BY category ASC, id ASC",
			ARRAY_A
		);
		$by_cat = array();
		foreach ( (array) $rows_all as $r ) {
			$by_cat[ $r['category'] ][] = $r;
		}
		$rows = array();
		$cats = array_keys( $by_cat );
		$i    = 0;
		while ( count( $rows ) < $count && $cats ) {
			$progressed = false;
			foreach ( $cats as $c ) {
				if ( count( $rows ) >= $count ) {
					break;
				}
				if ( isset( $by_cat[ $c ][ $i ] ) ) {
					$rows[]     = $by_cat[ $c ][ $i ];
					$progressed = true;
				}
			}
			if ( ! $progressed ) {
				break;
			}
			++$i;
		}
	}

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'qid'           => (int) $r['id'],
			'category'      => $r['category'],
			'question'      => $r['question'],
			'options'       => array( $r['option_a'], $r['option_b'], $r['option_c'], $r['option_d'] ),
			'correct_index' => (int) $r['correct_index'],
			'explanation'   => (string) $r['explanation'],
		);
	}
	return $out;
}

/**
 * Hub FAQ Q&A — the SINGLE SOURCE for both the visible accordion (theme pattern)
 * and the FAQPage JSON-LD (SEO mu-plugin). Keeping one source guarantees the
 * marked-up answers always match what the visitor sees (avoids a hidden-content
 * flag), the same discipline used for the rest of the site's FAQ.
 *
 * @return array<int,array{question:string,answer:string}>
 */
function buckleup_quiz_hub_faqs() {
	$full_total = buckleup_quiz_cfg( 'full_total', 50 );
	$pass_pct   = buckleup_quiz_cfg( 'pass_pct', 80 );

	$faqs = array(
		array(
			'question' => __( 'How many questions are on the ICBC Class 4 knowledge test?', 'buckleup-quiz' ),
			'answer'   => sprintf(
				/* translators: %d: number of questions in the full practice test. */
				__( "You'll get roughly %d multiple-choice questions, all pulled from ICBC's Driving Commercial Vehicles manual. Our free practice test runs the same length, so the format won't catch you off guard on test day.", 'buckleup-quiz' ),
				$full_total
			),
		),
		array(
			'question' => __( 'What score do I need to pass the ICBC Class 4 knowledge test?', 'buckleup-quiz' ),
			'answer'   => sprintf(
				/* translators: %d: passing percentage. */
				__( "%d%%. That's the number ICBC actually uses, and it's what we score against here too — instantly, with a topic-by-topic breakdown, so you know what to brush up on before you book.", 'buckleup-quiz' ),
				$pass_pct
			),
		),
		array(
			'question' => __( 'Who needs an ICBC Class 4 licence in BC?', 'buckleup-quiz' ),
			'answer'   => __( "If you're driving a taxi, limo, ride-hailing vehicle, ambulance, or a small bus or shuttle (up to 25 seats) anywhere in BC, you'll need a Class 4. There's an unrestricted version (4U) and a restricted one (4R) — which one applies depends on the vehicle.", 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Is the Class 4 knowledge test the same as the Class 5 test?', 'buckleup-quiz' ),
			'answer'   => __( "No — it builds on it. You still need your Class 5 rules of the road, but Class 4 adds commercial-driving topics on top: heavy-vehicle braking, air brakes, pre-trip inspections, hours of service, carrying passengers safely, and more. We've split those into the 12 categories above.", 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Is this ICBC Class 4 practice test free?', 'buckleup-quiz' ),
			'answer'   => __( "Yes, completely — the sample questions and the full timed test, no cost either way. Honestly, the best thing you can do before test day is keep practising until you're scoring comfortably above the pass mark, not just barely over it.", 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Where do I take the ICBC Class 4 knowledge test near Vancouver?', 'buckleup-quiz' ),
			'answer'   => __( "Any ICBC Driver Licensing office runs the Class 4 knowledge test. In the Tri-Cities, that's the Coquitlam office, which covers Port Moody, Coquitlam, and Port Coquitlam. We're based right in Port Moody, and once you've got the knowledge test sorted, our instructors can take you through the in-car Class 4 training too.", 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'How should I use this practice test to study?', 'buckleup-quiz' ),
			'answer'   => __( "Start with whatever makes you nervous — for a lot of people that's air brakes or pre-trip inspections — then move on to full mixed tests until you're consistently clearing the pass mark, not just scraping by. And actually read the explanation after each question; that's what makes the rules stick, not just staring at the correct answer.", 'buckleup-quiz' ),
		),
	);

	/**
	 * Filter the hub FAQ Q&A (single source for visible accordion + FAQPage schema).
	 *
	 * @param array $faqs
	 */
	return apply_filters( 'buckleup_quiz_hub_faqs', $faqs );
}

/**
 * Stable slug → index (0..11) map, in canonical category order. The front-end
 * keys the per-category COLOR palette off this integer via a single
 * `data-cat="N"` attribute (purge-safe — no dynamically-built class strings).
 *
 * @return array<string,int>
 */
function buckleup_quiz_category_index_map() {
	return array_flip( array_keys( buckleup_quiz_categories() ) );
}

/**
 * Config object for the front-end runner (serialised into a data attribute by
 * the theme pattern). REST URL + nonce come from the theme's window.buckleupAuth.
 *
 * @param string $mode 'full' or a category slug.
 * @return array<string,mixed>
 */
function buckleup_quiz_js_config( $mode = 'full' ) {
	// slug → inline SVG (currentColor, sized by the runner) so the JS rail/card
	// can show each category's icon, matching the server-rendered landing pages.
	$cat_icons = array();
	foreach ( array_keys( buckleup_quiz_categories() ) as $slug ) {
		$cat_icons[ $slug ] = function_exists( 'buckleup_icon' )
			? buckleup_icon( buckleup_quiz_category_icon( $slug ), 'w-full h-full' )
			: '';
	}
	return array(
		'mode'          => buckleup_quiz_is_category( $mode ) ? $mode : 'full',
		'fullTotal'     => buckleup_quiz_cfg( 'full_total', 50 ),
		'categoryTotal' => buckleup_quiz_cfg( 'category_total', 10 ),
		'passPct'       => buckleup_quiz_cfg( 'pass_pct', 80 ),
		'hubUrl'        => buckleup_quiz_hub_url(),
		'catIndex'      => buckleup_quiz_category_index_map(),
		'catIcons'      => $cat_icons,
	);
}

/**
 * Cached aggregate stats for the social-proof band: tests taken, average score,
 * most-missed category, and pass-mark hit rate. Cached 1h (the most-missed
 * rollup scans recent rows). Returns zeros when the bank is brand-new — the
 * landing pattern can choose to render the band only past a threshold.
 *
 * @return array{tests_taken:int,avg_pct:int,pass_rate:int,most_missed:string,most_missed_label:string}
 */
function buckleup_quiz_aggregate_stats() {
	$cached = get_transient( 'buckleup_quiz_agg_stats' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$table       = buckleup_quiz_table( 'quiz_attempts' );
	$tests_taken = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$avg_pct     = 0;
	$pass_rate   = 0;
	$most_missed = '';

	if ( $tests_taken > 0 ) {
		$avg_pct   = (int) round( (float) $wpdb->get_var( "SELECT AVG(score / total) * 100 FROM {$table} WHERE total > 0" ) );
		$passed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE passed = 1" );
		$pass_rate = (int) round( 100 * $passed / max( 1, $tests_taken ) );

		// Most-missed: lowest correct/total ratio across a recent window.
		$rows = $wpdb->get_col( "SELECT category_breakdown FROM {$table} WHERE category_breakdown <> '' ORDER BY id DESC LIMIT 2000" );
		$agg  = array();
		foreach ( (array) $rows as $json ) {
			$b = json_decode( (string) $json, true );
			if ( ! is_array( $b ) ) {
				continue;
			}
			foreach ( $b as $slug => $ct ) {
				if ( ! isset( $agg[ $slug ] ) ) {
					$agg[ $slug ] = array( 0, 0 );
				}
				$agg[ $slug ][0] += (int) ( isset( $ct['correct'] ) ? $ct['correct'] : 0 );
				$agg[ $slug ][1] += (int) ( isset( $ct['total'] ) ? $ct['total'] : 0 );
			}
		}
		$worst = 1.1;
		foreach ( $agg as $slug => $ct ) {
			if ( $ct[1] < 20 ) {
				continue; // need a minimum sample
			}
			$ratio = $ct[0] / $ct[1];
			if ( $ratio < $worst ) {
				$worst       = $ratio;
				$most_missed = $slug;
			}
		}
	}

	$stats = array(
		'tests_taken'       => $tests_taken,
		'avg_pct'           => $avg_pct,
		'pass_rate'         => $pass_rate,
		'most_missed'       => $most_missed,
		'most_missed_label' => $most_missed ? buckleup_quiz_category_label( $most_missed ) : '',
	);
	set_transient( 'buckleup_quiz_agg_stats', $stats, HOUR_IN_SECONDS );
	return $stats;
}
