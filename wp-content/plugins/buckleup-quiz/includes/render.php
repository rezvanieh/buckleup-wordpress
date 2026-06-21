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
				__( "The ICBC Class 4 knowledge test is a multiple-choice exam of roughly %d questions drawn from ICBC's Driving Commercial Vehicles manual. Our free full practice test mirrors that length so you know exactly what to expect on test day.", 'buckleup-quiz' ),
				$full_total
			),
		),
		array(
			'question' => __( 'What score do I need to pass the ICBC Class 4 knowledge test?', 'buckleup-quiz' ),
			'answer'   => sprintf(
				/* translators: %d: passing percentage. */
				__( 'You need %d%% to pass the ICBC knowledge test. On this practice test you will see your score instantly and a breakdown by topic, so you know which categories to study before you book.', 'buckleup-quiz' ),
				$pass_pct
			),
		),
		array(
			'question' => __( 'Who needs an ICBC Class 4 licence in BC?', 'buckleup-quiz' ),
			'answer'   => __( 'A Class 4 licence is required in British Columbia to drive taxis, limousines, ride-hailing vehicles, ambulances, and small passenger buses or shuttles with seating for up to 25 people. There are unrestricted (Class 4U) and restricted (Class 4R) variants depending on the vehicle.', 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Is the Class 4 knowledge test the same as the Class 5 test?', 'buckleup-quiz' ),
			'answer'   => __( 'No. The Class 4 knowledge test covers everything in the Class 5 material plus commercial-driving topics such as heavy-vehicle braking, air brakes, pre-trip inspections, hours of service, and carrying passengers safely. This practice test is organised into those 12 commercial-specific categories.', 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Is this ICBC Class 4 practice test free?', 'buckleup-quiz' ),
			'answer'   => __( 'Yes. You can take the sample questions and a full timed practice test for free. Practising the real question style until you score well above the pass mark is the single best way to pass on your first attempt.', 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'Where do I take the ICBC Class 4 knowledge test near Vancouver?', 'buckleup-quiz' ),
			'answer'   => __( 'You can take the Class 4 knowledge test at any ICBC Driver Licensing office, including the Coquitlam office that serves Port Moody, Coquitlam, and Port Coquitlam in the Tri-Cities. BuckleUp Driving School is based in Port Moody and offers in-car Class 4 commercial training to go with your knowledge-test prep.', 'buckleup-quiz' ),
		),
		array(
			'question' => __( 'How should I use this practice test to study?', 'buckleup-quiz' ),
			'answer'   => __( 'Start with the categories you are least confident in — for example air brakes or pre-trip inspections — then take full mixed practice tests until you are consistently scoring above the pass mark. Read the explanation after every question; that active recall is what makes the rules stick.', 'buckleup-quiz' ),
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
	return array(
		'mode'          => buckleup_quiz_is_category( $mode ) ? $mode : 'full',
		'fullTotal'     => buckleup_quiz_cfg( 'full_total', 50 ),
		'categoryTotal' => buckleup_quiz_cfg( 'category_total', 10 ),
		'passPct'       => buckleup_quiz_cfg( 'pass_pct', 80 ),
		'hubUrl'        => buckleup_quiz_hub_url(),
		'catIndex'      => buckleup_quiz_category_index_map(),
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
