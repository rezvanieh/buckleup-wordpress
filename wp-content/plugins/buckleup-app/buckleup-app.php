<?php
/**
 * Plugin Name:       BuckleUp App
 * Plugin URI:        https://www.buckleupdriving.ca
 * Description:        Phase-2 application backend for BuckleUp: three roles (student/instructor/admin), custom booking tables, the availability/slot engine, role-gated REST endpoints under buckleup/v1/*, the booking flow (no Stripe), and wp_mail notifications (no Twilio). The console UIs (theme) render against these endpoints.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            AgenticSolutions Madeira
 * License:           GPL-2.0-or-later
 * Text Domain:       buckleup-app
 *
 * @package BuckleUp_App
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BUCKLEUP_APP_VERSION', '0.1.0' );
define( 'BUCKLEUP_APP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BUCKLEUP_APP_URL', plugin_dir_url( __FILE__ ) );

// Schema version — bump when includes/tables.php changes so dbDelta re-runs.
define( 'BUCKLEUP_APP_DB_VERSION', '1' );

/**
 * Bootstrap. Each include is guarded with file_exists so the plugin keeps
 * activating cleanly while the layers land. Loaded directly so each module can
 * hook init / rest_api_init / login_redirect itself.
 *
 * Out of scope (deferred): Stripe checkout/transactions, Twilio SMS/WhatsApp,
 * social login.
 */
$buckleup_app_includes = array(
	'includes/tables.php',          // $wpdb tables (dbDelta, versioned, idempotent)
	'includes/roles.php',           // student/instructor/admin roles + capabilities
	'includes/helpers.php',         // shared lookups (current student/instructor row, ownership)
	'includes/slots.php',           // ported availability/slot engine (shared PHP fn)
	'includes/notifications.php',   // wp_mail templates + the template store (admin CRUD)
	'includes/auth.php',            // role login redirect, registration, wp-admin gating
	'includes/rest-helpers.php',    // REST permission/ownership/response helpers
	'includes/rest-student.php',    // buckleup/v1/students/*
	'includes/rest-instructor.php', // buckleup/v1/instructors/*
	'includes/rest-admin.php',      // buckleup/v1/admin/*
	'includes/rest-booking.php',    // buckleup/v1/bookings, /bookings/slots
	'includes/rest-user.php',       // buckleup/v1/user/theme, /user/avatar
	'includes/rest-graduates.php',  // buckleup/v1/graduates (shares the graduate CPT)
);
foreach ( $buckleup_app_includes as $buckleup_app_rel ) {
	$buckleup_app_path = BUCKLEUP_APP_PATH . $buckleup_app_rel;
	if ( file_exists( $buckleup_app_path ) ) {
		require_once $buckleup_app_path;
	}
}
unset( $buckleup_app_includes, $buckleup_app_rel, $buckleup_app_path );

/**
 * Activation: create/upgrade tables, register roles, then flush rewrites (the
 * portal landing routes are plain pages, but flushing is harmless + future-safe).
 */
function buckleup_app_activate() {
	if ( function_exists( 'buckleup_app_install_tables' ) ) {
		buckleup_app_install_tables();
	}
	if ( function_exists( 'buckleup_app_register_roles' ) ) {
		buckleup_app_register_roles();
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'buckleup_app_activate' );

/**
 * Deactivation: flush rewrites. Roles + tables are intentionally LEFT IN PLACE
 * so deactivating doesn't destroy student/booking data; removal happens only on
 * uninstall (uninstall.php), not here.
 */
function buckleup_app_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'buckleup_app_deactivate' );

/**
 * Safety net: ensure tables exist / upgrade on load when the stored schema
 * version is behind. Cheap (a single option read) on the happy path.
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'buckleup_app_db_version' ) !== BUCKLEUP_APP_DB_VERSION && function_exists( 'buckleup_app_install_tables' ) ) {
		buckleup_app_install_tables();
	}
} );

/**
 * Load translations.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'buckleup-app', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
