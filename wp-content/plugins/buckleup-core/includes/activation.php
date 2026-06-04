<?php
/**
 * Activation / deactivation routines.
 *
 * On activation we register the CPTs once (so their rewrite rules exist) and
 * flush, seed the settings option, then flush again. On deactivation we flush
 * to clean up the `/locations/` rewrite.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Run on plugin activation.
 */
function buckleup_activate() {
	// CPTs must be registered before flushing so the location rewrite sticks.
	if ( function_exists( 'buckleup_register_post_types' ) ) {
		buckleup_register_post_types();
	}
	if ( function_exists( 'buckleup_seed_settings_defaults' ) ) {
		buckleup_seed_settings_defaults();
	}
	flush_rewrite_rules();
}

/**
 * Run on plugin deactivation.
 */
function buckleup_deactivate() {
	flush_rewrite_rules();
}
