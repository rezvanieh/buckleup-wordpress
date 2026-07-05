<?php
/**
 * Plugin Name:       BuckleUp Quiz
 * Plugin URI:        https://www.buckleupdriving.ca
 * Description:        ICBC Class 4 knowledge practice-test engine. Public/anonymous-first: a question bank (bu_questions), randomized server-graded attempts (bu_quiz_attempts), REST endpoints under buckleup/v1/quiz/*, attempt-limiting (anon 15 / logged-in unlimited, admin-editable in Settings → Practice Test), a branded HTML result email, and SEO-ready hub + category landing pages. No third-party quiz plugin, no jQuery.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            AgenticSolutions Madeira
 * License:           GPL-2.0-or-later
 * Text Domain:       buckleup-quiz
 *
 * @package BuckleUp_Quiz
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BUCKLEUP_QUIZ_VERSION', '0.1.0' );
define( 'BUCKLEUP_QUIZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'BUCKLEUP_QUIZ_URL', plugin_dir_url( __FILE__ ) );

// Schema version — bump when includes/tables.php changes so dbDelta re-runs.
define( 'BUCKLEUP_QUIZ_DB_VERSION', '2' );

/**
 * Bootstrap. Each include is guarded with file_exists so the plugin keeps
 * activating cleanly while the layers land. Loaded directly so each module can
 * hook init / rest_api_init itself. Mirrors buckleup-app's bootstrap.
 */
$buckleup_quiz_includes = array(
	'includes/config.php',     // category taxonomy + tunables (total/pass/attempts), all filterable
	'includes/tables.php',     // $wpdb tables (dbDelta, versioned, idempotent)
	'includes/seed.php',       // idempotent question-bank seed from the data files
	'includes/selection.php',  // category-balanced random selection + option shuffle (server-side)
	'includes/session.php',    // transient-backed test session (question ids + seed; answers hidden)
	'includes/attempts.php',   // attempt-limit logic (anon: email+ip+cookie; logged-in: unlimited)
	'includes/grading.php',    // server-authoritative scoring + per-category breakdown
	'includes/email.php',      // branded HTML result email (reuses buckleup_email_shell from core)
	'includes/render.php',     // front-end helpers: categories, sample questions, runner markup, stats
	'includes/rest.php',       // buckleup/v1/quiz/* routes
	'includes/certificate.php',// pass-only print-to-PDF certificate page + verify route
	'includes/results.php',    // detailed results page (score + full answer review) by token
	'includes/privacy.php',    // attempt retention cron + GDPR exporter/eraser (email is PII)
	'includes/admin.php',      // Settings → Practice Test: admin-editable free-attempt cap (buckleup_quiz_config filter)
);
foreach ( $buckleup_quiz_includes as $buckleup_quiz_rel ) {
	$buckleup_quiz_path = BUCKLEUP_QUIZ_PATH . $buckleup_quiz_rel;
	if ( file_exists( $buckleup_quiz_path ) ) {
		require_once $buckleup_quiz_path;
	}
}
unset( $buckleup_quiz_includes, $buckleup_quiz_rel, $buckleup_quiz_path );

/**
 * Activation: create/upgrade tables and seed the bank, then flush rewrites.
 */
function buckleup_quiz_activate() {
	if ( function_exists( 'buckleup_quiz_install_tables' ) ) {
		buckleup_quiz_install_tables();
	}
	if ( function_exists( 'buckleup_quiz_seed_questions' ) ) {
		buckleup_quiz_seed_questions();
	}
	if ( function_exists( 'buckleup_quiz_schedule_purge' ) ) {
		buckleup_quiz_schedule_purge();
	}
	if ( function_exists( 'buckleup_quiz_register_cert_rewrite' ) ) {
		buckleup_quiz_register_cert_rewrite(); // register before flushing so the rule sticks
	}
	if ( function_exists( 'buckleup_quiz_register_result_rewrite' ) ) {
		buckleup_quiz_register_result_rewrite();
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'buckleup_quiz_activate' );

/**
 * Deactivation: flush rewrites. Tables + question/attempt data are intentionally
 * LEFT IN PLACE so deactivating never destroys the bank or attempt history;
 * removal happens only on uninstall (uninstall.php), not here.
 */
function buckleup_quiz_deactivate() {
	if ( function_exists( 'buckleup_quiz_unschedule_purge' ) ) {
		buckleup_quiz_unschedule_purge();
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'buckleup_quiz_deactivate' );

/**
 * Safety net: ensure tables exist / upgrade on load when the stored schema
 * version is behind. Cheap (a single option read) on the happy path. This is the
 * shell-less-prod path — the tables self-create on first request after an SFTP
 * deploy, with no WP-CLI needed (bump BUCKLEUP_QUIZ_DB_VERSION to re-trigger).
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'buckleup_quiz_db_version' ) !== BUCKLEUP_QUIZ_DB_VERSION && function_exists( 'buckleup_quiz_install_tables' ) ) {
		buckleup_quiz_install_tables();
	}
} );

/**
 * Load translations.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'buckleup-quiz', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
