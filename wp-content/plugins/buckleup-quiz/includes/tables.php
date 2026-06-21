<?php
/**
 * Custom database tables for the quiz engine.
 *
 * Created/upgraded with dbDelta (idempotent) and versioned via the
 * `buckleup_quiz_db_version` option — identical discipline to buckleup-app.
 *
 *   - bu_questions     : the question bank. One row per MCQ. Flat table (not a
 *                        CPT) so balanced random selection over thousands of rows
 *                        stays fast and individual questions are never indexed.
 *   - bu_quiz_attempts : one row per graded attempt (anon or logged-in).
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fully-qualified table name for a logical table key.
 *
 * @param string $key One of: questions, quiz_attempts.
 * @return string
 */
function buckleup_quiz_table( $key ) {
	global $wpdb;
	return $wpdb->prefix . 'bu_' . $key;
}

/**
 * Create or upgrade all quiz tables. Safe to call repeatedly (dbDelta).
 */
function buckleup_quiz_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$questions       = buckleup_quiz_table( 'questions' );
	$attempts        = buckleup_quiz_table( 'quiz_attempts' );

	// bu_questions — the bank. correct_index (0..3) is canonical order and is
	// NEVER sent to the client; grading reads it fresh from this table.
	$sql_questions = "CREATE TABLE {$questions} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		category VARCHAR(64) NOT NULL,
		question TEXT NOT NULL,
		option_a TEXT NOT NULL,
		option_b TEXT NOT NULL,
		option_c TEXT NOT NULL,
		option_d TEXT NOT NULL,
		correct_index TINYINT NOT NULL,
		explanation TEXT NULL,
		difficulty TINYINT NOT NULL DEFAULT 2,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		source_ref VARCHAR(64) NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY category (category),
		KEY active_cat (is_active, category),
		UNIQUE KEY source_ref (source_ref)
	) {$charset_collate};";

	// bu_quiz_attempts — one row per graded attempt. email_hash/ip_hash power the
	// soft anonymous attempt limit without scanning raw PII; raw IP is never
	// stored. result_token (not the row id) powers a shareable/return result view.
	$sql_attempts = "CREATE TABLE {$attempts} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NULL,
		email VARCHAR(190) NOT NULL,
		email_hash CHAR(32) NOT NULL,
		name VARCHAR(120) NULL,
		mode VARCHAR(64) NOT NULL DEFAULT 'full',
		score SMALLINT NOT NULL,
		total SMALLINT NOT NULL,
		passed TINYINT(1) NOT NULL DEFAULT 0,
		category_breakdown LONGTEXT NULL,
		answers LONGTEXT NULL,
		ip_hash CHAR(32) NULL,
		result_token CHAR(32) NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY email_hash (email_hash),
		KEY ip_hash (ip_hash),
		KEY mode (mode),
		KEY created_at (created_at),
		UNIQUE KEY result_token (result_token)
	) {$charset_collate};";

	dbDelta( $sql_questions );
	dbDelta( $sql_attempts );

	update_option( 'buckleup_quiz_db_version', BUCKLEUP_QUIZ_DB_VERSION );
}
