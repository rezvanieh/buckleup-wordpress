<?php
/**
 * Registered post meta for the marketing CPTs.
 *
 * Every editable field is a registered meta key (typed + sanitized) so the
 * theme, the WP-CLI seed scripts, and the SEO mu-plugin can all read/write the
 * SAME stable keys. The canonical list of keys lives in docs/CONTENT-MODEL.md —
 * keep the two in sync.
 *
 * NOTE on REST: the meta is declared with `show_in_rest => true`, but v1 is
 * PHP-rendered and the CPTs do NOT declare `'custom-fields'` support, so the
 * meta is NOT actually exposed in the wp/v2 REST responses (and that's fine —
 * nothing reads it over REST). `show_in_rest` is kept harmlessly for the day a
 * CPT wants `custom-fields`; don't rely on REST meta output in v1.
 *
 * Conventions:
 *   - All keys are prefixed `bu_` to avoid collisions.
 *   - List-like fields (certifications, languages, feature bullets) are stored
 *     as MULTIPLE single string meta rows under one key (`'single' => false`),
 *     never as one serialized array — this keeps them clean and easy for
 *     WP-CLI to append. Use buckleup_get_meta_list() to read them.
 *   - Editing requires the `edit_posts` capability (auth_callback).
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shared auth callback: only users who can edit posts may write these (applies
 * to any REST/meta write path that honors the meta auth_callback).
 *
 * @return bool
 */
function buckleup_meta_auth_callback() {
	return current_user_can( 'edit_posts' );
}

/**
 * Register a single-value string meta key with sane defaults.
 *
 * NOTE: WordPress passes FOUR args to a meta sanitize_callback
 * ($value, $key, $object_type, $object_subtype). PHP built-ins such as
 * floatval()/absint() throw ArgumentCountError when handed the extra three, so
 * the default sanitizers are wrapped in single-arg closures.
 *
 * @param string        $post_type Post type to attach the meta to.
 * @param string        $key       Meta key (already prefixed).
 * @param string        $type      REST/storage type: string|integer|number|boolean.
 * @param callable|null $sanitize  Optional sanitize callback (1-arg; defaults per type).
 */
function buckleup_register_meta( $post_type, $key, $type = 'string', $sanitize = null ) {
	if ( null === $sanitize ) {
		switch ( $type ) {
			case 'integer':
				$sanitize = static function ( $value ) {
					return absint( $value );
				};
				break;
			case 'number':
				$sanitize = static function ( $value ) {
					return floatval( $value );
				};
				break;
			case 'boolean':
				$sanitize = static function ( $value ) {
					return rest_sanitize_boolean( $value ) ? '1' : '';
				};
				break;
			default:
				$sanitize = static function ( $value ) {
					return sanitize_text_field( $value );
				};
		}
	} else {
		// Caller passed a sanitizer by name/closure; wrap to guarantee 1-arg.
		$callable = $sanitize;
		$sanitize = static function ( $value ) use ( $callable ) {
			return call_user_func( $callable, $value );
		};
	}

	register_post_meta(
		$post_type,
		$key,
		array(
			'type'              => $type,
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => $sanitize,
			'auth_callback'     => 'buckleup_meta_auth_callback',
		)
	);
}

/**
 * Register a multi-value string meta key (a list stored as repeating rows).
 *
 * @param string $post_type Post type.
 * @param string $key       Meta key (already prefixed).
 */
function buckleup_register_meta_list( $post_type, $key ) {
	register_post_meta(
		$post_type,
		$key,
		array(
			'type'              => 'string',
			'single'            => false,
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) {
				return sanitize_text_field( $value );
			},
			'auth_callback'     => 'buckleup_meta_auth_callback',
		)
	);
}

/**
 * Register every meta key for every CPT.
 */
function buckleup_register_post_meta_fields() {

	// --- graduate ---------------------------------------------------------
	// title + post_content (description) + featured image are core supports.
	buckleup_register_meta( 'graduate', 'bu_is_active', 'boolean' ); // show in gallery?
	// `menu_order` (core) is reused for ordering; no extra key needed.

	// --- testimonial ------------------------------------------------------
	buckleup_register_meta( 'testimonial', 'bu_author_name', 'string' ); // e.g. "Jason Kim"
	buckleup_register_meta( 'testimonial', 'bu_author_role', 'string' ); // e.g. "Passed N Test"
	buckleup_register_meta( 'testimonial', 'bu_rating', 'integer' );     // 1-5 stars
	buckleup_register_meta( 'testimonial', 'bu_is_active', 'boolean' );
	// quote text lives in post_content; author photo is the featured image.

	// --- faq --------------------------------------------------------------
	// question = post_title, answer = post_content. Ordering via menu_order.
	buckleup_register_meta( 'faq', 'bu_is_active', 'boolean' );

	// --- service ----------------------------------------------------------
	buckleup_register_meta( 'service', 'bu_service_type', 'string' );  // LESSON|TEST_PREP|SPECIALIZED|REFRESHER|PACKAGE
	buckleup_register_meta( 'service', 'bu_duration', 'integer' );     // minutes
	buckleup_register_meta( 'service', 'bu_price', 'number' );         // CAD
	buckleup_register_meta( 'service', 'bu_is_active', 'boolean' );
	// description lives in post_content; sort via menu_order.

	// --- package (home pricing plan) -------------------------------------
	buckleup_register_meta( 'package', 'bu_price', 'number' );          // e.g. 480
	buckleup_register_meta( 'package', 'bu_unit', 'string' );           // "lesson" | "package"
	buckleup_register_meta( 'package', 'bu_sessions', 'integer' );      // # of sessions
	buckleup_register_meta( 'package', 'bu_hours', 'number' );          // total driving hours
	buckleup_register_meta( 'package', 'bu_car_fee', 'number' );        // +$ for car on road test
	buckleup_register_meta( 'package', 'bu_is_popular', 'boolean' );    // "Most Popular" badge
	buckleup_register_meta( 'package', 'bu_is_active', 'boolean' );
	buckleup_register_meta( 'package', 'bu_cta_label', 'string' );      // "Book Now" | "Get Started" | ...
	buckleup_register_meta_list( 'package', 'bu_features' );            // feature bullet rows
	// description lives in post_content; ordering via menu_order.
	// The WhatsApp deep link is derived server-side from title+price (see helpers).

	// --- instructor -------------------------------------------------------
	buckleup_register_meta( 'instructor', 'bu_role', 'string' );        // "Senior Instructor"
	buckleup_register_meta( 'instructor', 'bu_rating', 'number' );      // e.g. 4.9
	buckleup_register_meta( 'instructor', 'bu_is_active', 'boolean' );
	buckleup_register_meta_list( 'instructor', 'bu_certifications' );   // ICBC Approved, ...
	buckleup_register_meta_list( 'instructor', 'bu_languages' );        // English, Farsi, ...
	// bio lives in post_content; photo is the featured image.

	// --- location ---------------------------------------------------------
	buckleup_register_meta( 'location', 'bu_hero_title', 'string' );      // "Driving Lessons in"
	buckleup_register_meta( 'location', 'bu_hero_highlight', 'string' );  // "Coquitlam" (gradient span)
	buckleup_register_meta( 'location', 'bu_hero_subtitle', 'string', 'sanitize_textarea_field' );
	buckleup_register_meta( 'location', 'bu_seo_title', 'string' );
	buckleup_register_meta( 'location', 'bu_seo_description', 'string', 'sanitize_textarea_field' );
}
add_action( 'init', 'buckleup_register_post_meta_fields' );
