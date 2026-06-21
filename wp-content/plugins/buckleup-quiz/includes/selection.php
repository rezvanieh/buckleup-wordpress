<?php
/**
 * Question selection + option shuffling.
 *
 * Randomness without ORDER BY RAND(): we fetch the slim id list per category
 * (covered by the (is_active,category) index), shuffle in PHP, and slice. A full
 * mock is balanced across the 12 categories via round-robin; a category quiz
 * draws from one category. Each question's four options are shuffled into a
 * per-attempt display order; the canonical→display map lives ONLY in the
 * server-side session, so the client never receives the canonical order or the
 * correct answer.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Active question ids grouped by category, each list pre-shuffled.
 *
 * @return array<string,int[]>
 */
function buckleup_quiz_shuffled_ids_by_category() {
	global $wpdb;
	$table = buckleup_quiz_table( 'questions' );
	$rows  = $wpdb->get_results( "SELECT id, category FROM {$table} WHERE is_active = 1", ARRAY_A );

	$by_cat = array();
	foreach ( (array) $rows as $r ) {
		$by_cat[ $r['category'] ][] = (int) $r['id'];
	}
	foreach ( $by_cat as &$ids ) {
		shuffle( $ids );
	}
	unset( $ids );
	return $by_cat;
}

/**
 * Select question ids for a test, GROUPED by category in canonical order.
 *
 * Full mock: a category-balanced set (round-robin to the target), returned as
 * per-category lists in canonical category order so the runner can present the
 * test category-by-category and serve future categories only on demand. A
 * category quiz returns the single requested category. Within each category the
 * ids are already shuffled — only the SEQUENCE is grouped, never the selection.
 *
 * @param string $mode 'full' or a category slug.
 * @return array<string,int[]> slug => selected ids, in canonical category order.
 */
function buckleup_quiz_select_grouped( $mode ) {
	$by_cat = buckleup_quiz_shuffled_ids_by_category();
	if ( empty( $by_cat ) ) {
		return array();
	}
	$order    = array_keys( buckleup_quiz_categories() ); // canonical order
	$selected = array();

	if ( buckleup_quiz_is_category( $mode ) ) {
		$pool = isset( $by_cat[ $mode ] ) ? $by_cat[ $mode ] : array();
		$n    = min( buckleup_quiz_cfg( 'category_total', 10 ), count( $pool ) );
		if ( $n > 0 ) {
			$selected[ $mode ] = array_slice( $pool, 0, $n );
		}
		return $selected;
	}

	// Full mock: round-robin across present categories to reach the target.
	$total_available = array_sum( array_map( 'count', $by_cat ) );
	$target          = min( buckleup_quiz_cfg( 'full_total', 50 ), $total_available );

	$present = array();
	foreach ( $order as $slug ) {
		if ( ! empty( $by_cat[ $slug ] ) ) {
			$present[] = $slug;
		}
	}
	$counts = array_fill_keys( $present, 0 );
	$picked = 0;
	while ( $picked < $target ) {
		$progressed = false;
		foreach ( $present as $slug ) {
			if ( $picked >= $target ) {
				break;
			}
			if ( $counts[ $slug ] < count( $by_cat[ $slug ] ) ) {
				++$counts[ $slug ];
				++$picked;
				$progressed = true;
			}
		}
		if ( ! $progressed ) {
			break;
		}
	}
	// Emit grouped, in canonical order.
	foreach ( $order as $slug ) {
		if ( ! empty( $counts[ $slug ] ) ) {
			$selected[ $slug ] = array_slice( $by_cat[ $slug ], 0, $counts[ $slug ] );
		}
	}
	return $selected;
}

/**
 * A random permutation of [0,1,2,3] — the canonical option indices in display
 * order. perm[displayPosition] = canonicalIndex.
 *
 * @return int[]
 */
function buckleup_quiz_random_perm() {
	$perm = array( 0, 1, 2, 3 );
	shuffle( $perm );
	return $perm;
}

/**
 * Assemble a complete test: grouped question ids, per-question option
 * permutations (canonical→display, server-only), the per-category MANIFEST the
 * runner's progress rail is built from, and the ordered category BATCHES served
 * one at a time (so future categories never reach the client). No answers in any
 * client-facing part. WP_Error if the bank is empty.
 *
 * @param string $mode 'full' or a category slug.
 * @return array|WP_Error {mode, question_ids[], perms{qid:perm}, categories[manifest], batches[{categoryIndex,slug,label,short,qids}]}
 */
function buckleup_quiz_assemble_test( $mode ) {
	$mode    = buckleup_quiz_is_category( $mode ) ? $mode : 'full';
	$grouped = buckleup_quiz_select_grouped( $mode );
	if ( empty( $grouped ) ) {
		return buckleup_quiz_rest_error( __( 'No practice questions are available yet. Please check back soon.', 'buckleup-quiz' ), 503 );
	}

	$cats      = buckleup_quiz_categories();
	$index_map = buckleup_quiz_category_index_map();

	$perms        = array();
	$question_ids = array();
	$batches      = array();
	$manifest     = array();

	foreach ( $grouped as $slug => $ids ) {
		$ids = array_values( array_map( 'intval', $ids ) );
		foreach ( $ids as $qid ) {
			$perms[ $qid ]  = buckleup_quiz_random_perm();
			$question_ids[] = $qid;
		}
		$idx        = isset( $index_map[ $slug ] ) ? (int) $index_map[ $slug ] : 0;
		$label      = isset( $cats[ $slug ]['label'] ) ? $cats[ $slug ]['label'] : buckleup_quiz_category_label( $slug );
		$short      = isset( $cats[ $slug ]['short'] ) ? $cats[ $slug ]['short'] : $label;
		$batches[]  = array(
			'categoryIndex' => $idx,
			'slug'          => $slug,
			'label'         => $label,
			'short'         => $short,
			'qids'          => $ids,
		);
		$manifest[] = array(
			'slug'  => $slug,
			'index' => $idx,
			'label' => $label,
			'short' => $short,
			'total' => count( $ids ),
		);
	}

	if ( empty( $question_ids ) ) {
		return buckleup_quiz_rest_error( __( 'No practice questions are available yet. Please check back soon.', 'buckleup-quiz' ), 503 );
	}

	return array(
		'mode'         => $mode,
		'question_ids' => $question_ids,
		'perms'        => $perms,
		'categories'   => $manifest,
		'batches'      => $batches,
	);
}

/**
 * Serialize question ids into the client payload (display-ordered options, NO
 * answers), preserving id order. One DB query.
 *
 * @param int[]            $qids
 * @param array<int,int[]> $perms qid → permutation.
 * @return array<int,array>
 */
function buckleup_quiz_serialize_questions( $qids, $perms ) {
	global $wpdb;
	$qids = array_values( array_map( 'intval', $qids ) );
	if ( empty( $qids ) ) {
		return array();
	}
	$table        = buckleup_quiz_table( 'questions' );
	$placeholders = implode( ',', array_fill( 0, count( $qids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are %d.
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, category, question, option_a, option_b, option_c, option_d FROM {$table} WHERE id IN ($placeholders)", $qids ),
		ARRAY_A
	);
	$by_id = array();
	foreach ( (array) $rows as $r ) {
		$by_id[ (int) $r['id'] ] = $r;
	}
	$index_map = buckleup_quiz_category_index_map();
	$out       = array();
	foreach ( $qids as $qid ) {
		if ( ! isset( $by_id[ $qid ], $perms[ $qid ] ) ) {
			continue;
		}
		$row       = $by_id[ $qid ];
		$canonical = array( $row['option_a'], $row['option_b'], $row['option_c'], $row['option_d'] );
		$display   = array();
		foreach ( $perms[ $qid ] as $ci ) {
			$display[] = $canonical[ (int) $ci ];
		}
		$out[] = array(
			'qid'           => (int) $qid,
			'category'      => $row['category'],
			'categoryIndex' => isset( $index_map[ $row['category'] ] ) ? (int) $index_map[ $row['category'] ] : 0,
			'question'      => $row['question'],
			'options'       => $display,
		);
	}
	return $out;
}
