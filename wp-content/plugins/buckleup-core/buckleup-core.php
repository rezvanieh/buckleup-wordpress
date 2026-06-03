<?php
/**
 * Plugin Name:       BuckleUp Core
 * Plugin URI:        https://www.buckleupdriving.ca
 * Description:        Domain plugin for BuckleUp: registers CPTs (service, package, graduate, testimonial, booking, instructor, etc.), REST endpoints (slots, checkout, Stripe webhook), roles, and the notification engine. The data/booking/portal teams build on top of this scaffold.
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
 * Bootstrap. Each include is owned by a panel team and registered here.
 * Files are created as the teams build them; guard with file_exists so the
 * plugin activates cleanly from day one.
 */
add_action( 'plugins_loaded', function () {
	$includes = array(
		'includes/cpt.php',            // custom post types (service, package, graduate, testimonial, booking, instructor)
		'includes/tables.php',         // custom DB tables (availability, bookings, transactions, notifications)
		'includes/rest-booking.php',   // /buckleup/v1/slots, /checkout, webhook
		'includes/rest-portal.php',    // student + instructor portal endpoints
		'includes/notifications.php',  // template engine + queue + WP-Cron
		'includes/schema.php',         // LocalBusiness / FAQ / BlogPosting JSON-LD
	);
	foreach ( $includes as $rel ) {
		$path = BUCKLEUP_CORE_PATH . $rel;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}, 5 );

// Flush rewrite rules on (de)activation so CPT slugs resolve.
register_activation_hook( __FILE__, function () { flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { flush_rewrite_rules(); } );
