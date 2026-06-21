<?php
/**
 * Privacy + data retention for stored quiz attempts (which hold an email — PII).
 *
 *   - A daily cron purges attempt rows older than the retention window.
 *   - WordPress personal-data exporter + eraser are registered so data-subject
 *     requests (Tools → Export/Erase Personal Data) cover quiz attempts, keyed
 *     on the email's hash.
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Attempt retention window in days (filterable). Default 12 months.
 *
 * @return int
 */
function buckleup_quiz_retention_days() {
	return (int) apply_filters( 'buckleup_quiz_retention_days', 365 );
}

/**
 * Delete attempt rows older than the retention window.
 *
 * @return int Rows deleted.
 */
function buckleup_quiz_purge_old_attempts() {
	global $wpdb;
	$table  = buckleup_quiz_table( 'quiz_attempts' );
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - buckleup_quiz_retention_days() * DAY_IN_SECONDS );
	return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
}
add_action( 'buckleup_quiz_daily_purge', 'buckleup_quiz_purge_old_attempts' );

/**
 * Schedule / unschedule the daily purge. Called from (de)activation.
 */
function buckleup_quiz_schedule_purge() {
	if ( ! wp_next_scheduled( 'buckleup_quiz_daily_purge' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'buckleup_quiz_daily_purge' );
	}
}
function buckleup_quiz_unschedule_purge() {
	$ts = wp_next_scheduled( 'buckleup_quiz_daily_purge' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'buckleup_quiz_daily_purge' );
	}
}

/** WordPress personal-data exporter + eraser (keyed on email hash) ---------- */

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['buckleup-quiz'] = array(
		'exporter_friendly_name' => __( 'BuckleUp Practice Test', 'buckleup-quiz' ),
		'callback'               => 'buckleup_quiz_privacy_exporter',
	);
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['buckleup-quiz'] = array(
		'eraser_friendly_name' => __( 'BuckleUp Practice Test', 'buckleup-quiz' ),
		'callback'             => 'buckleup_quiz_privacy_eraser',
	);
	return $erasers;
} );

/**
 * Export a person's quiz attempts.
 *
 * @param string $email
 * @param int    $page
 * @return array
 */
function buckleup_quiz_privacy_exporter( $email, $page = 1 ) {
	global $wpdb;
	$table = buckleup_quiz_table( 'quiz_attempts' );
	$rows  = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s", buckleup_quiz_email_hash( $email ) ),
		ARRAY_A
	);
	$items = array();
	foreach ( (array) $rows as $r ) {
		$items[] = array(
			'group_id'    => 'buckleup_quiz_attempts',
			'group_label' => __( 'Practice test attempts', 'buckleup-quiz' ),
			'item_id'     => 'attempt-' . (int) $r['id'],
			'data'        => array(
				array( 'name' => __( 'Date', 'buckleup-quiz' ), 'value' => $r['created_at'] ),
				array( 'name' => __( 'Test', 'buckleup-quiz' ), 'value' => $r['mode'] ),
				array( 'name' => __( 'Score', 'buckleup-quiz' ), 'value' => $r['score'] . '/' . $r['total'] ),
				array( 'name' => __( 'Email', 'buckleup-quiz' ), 'value' => $r['email'] ),
			),
		);
	}
	return array( 'data' => $items, 'done' => true );
}

/**
 * Erase a person's quiz attempts.
 *
 * @param string $email
 * @param int    $page
 * @return array
 */
function buckleup_quiz_privacy_eraser( $email, $page = 1 ) {
	global $wpdb;
	$table   = buckleup_quiz_table( 'quiz_attempts' );
	$removed = (int) $wpdb->delete( $table, array( 'email_hash' => buckleup_quiz_email_hash( $email ) ), array( '%s' ) );
	return array(
		'items_removed'  => $removed > 0,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}
