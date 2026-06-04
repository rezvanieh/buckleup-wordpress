<?php
/**
 * Plugin Name:       BuckleUp Core
 * Plugin URI:        https://www.buckleupdriving.ca
 * Description:        Domain plugin for BuckleUp: registers the v1 marketing content types (graduate, testimonial, faq, service, package, instructor, location), their native fields/meta boxes, a site-settings options page (NAP, hours, social, schema claims), and the template helpers the block theme renders against. Phase 2 (bookings, payments, portals, notifications) builds on top of this scaffold.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            AgenticSolutions Madeira
 * License:           GPL-2.0-or-later
 * Text Domain:       buckleup-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BUCKLEUP_CORE_VERSION', '0.1.0' );
define( 'BUCKLEUP_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BUCKLEUP_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap. Each include is guarded with file_exists so the plugin activates
 * cleanly even before a file lands. Loaded directly (not on plugins_loaded) so
 * the CPT/meta/options registrations can hook `init`/`admin_init` themselves.
 *
 * v1 = marketing content model. Phase-2 includes (custom tables, REST booking
 * endpoints, notification engine) will be added here when that work begins.
 */
$buckleup_includes = array(
	'includes/cpt.php',         // register the 7 marketing CPTs (location → /locations/)
	'includes/meta.php',        // register_post_meta for every field (typed + sanitized)
	'includes/meta-boxes.php',  // native admin meta boxes (no ACF dependency)
	'includes/settings.php',    // site-settings options page (NAP, hours, social, schema)
	'includes/helpers.php',     // template helper API the theme renders against
	'includes/contact.php',     // contact form handler (admin-post → wp_mail; honeypot + rate-limit)
	'includes/security.php',    // lean hardening: XML-RPC off, enumeration lock, no version leak
	'includes/activation.php',  // (de)activation: register CPTs + seed settings + flush
);
foreach ( $buckleup_includes as $buckleup_rel ) {
	$buckleup_path = BUCKLEUP_CORE_PATH . $buckleup_rel;
	if ( file_exists( $buckleup_path ) ) {
		require_once $buckleup_path;
	}
}
unset( $buckleup_includes, $buckleup_rel, $buckleup_path );

// Activation: register CPTs, seed settings defaults, and flush rewrite rules
// so /locations/{slug} resolves. Deactivation flushes to clean up.
register_activation_hook( __FILE__, 'buckleup_activate' );
register_deactivation_hook( __FILE__, 'buckleup_deactivate' );

/**
 * Load translations.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'buckleup-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
