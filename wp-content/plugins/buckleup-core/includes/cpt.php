<?php
/**
 * Custom post types for the v1 marketing site.
 *
 * Seven content types back the editable sections of the site:
 *   graduate, testimonial, faq, service, package, instructor, location.
 *
 * None of them are public single-page entities except `location`, which owns
 * the `/locations/{slug}` URLs (see PLAN.md §2 URL parity). The rest surface
 * only through the theme's section patterns / template helpers, so they are
 * registered as non-public, admin-managed lists with REST + Gutenberg access.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shared label builder so each CPT gets a full, translated label set without
 * repeating the same array shape seven times.
 *
 * @param string $singular Singular display name (e.g. "Graduate").
 * @param string $plural   Plural display name (e.g. "Graduates").
 * @return array<string,string>
 */
function buckleup_cpt_labels( $singular, $plural ) {
	return array(
		'name'                  => $plural,
		'singular_name'         => $singular,
		'menu_name'             => $plural,
		'name_admin_bar'        => $singular,
		'add_new'               => __( 'Add New', 'buckleup-core' ),
		/* translators: %s: post type singular name. */
		'add_new_item'          => sprintf( __( 'Add New %s', 'buckleup-core' ), $singular ),
		/* translators: %s: post type singular name. */
		'new_item'              => sprintf( __( 'New %s', 'buckleup-core' ), $singular ),
		/* translators: %s: post type singular name. */
		'edit_item'             => sprintf( __( 'Edit %s', 'buckleup-core' ), $singular ),
		/* translators: %s: post type singular name. */
		'view_item'             => sprintf( __( 'View %s', 'buckleup-core' ), $singular ),
		/* translators: %s: post type plural name. */
		'all_items'             => sprintf( __( 'All %s', 'buckleup-core' ), $plural ),
		/* translators: %s: post type plural name. */
		'search_items'          => sprintf( __( 'Search %s', 'buckleup-core' ), $plural ),
		/* translators: %s: post type plural name. */
		'not_found'             => sprintf( __( 'No %s found.', 'buckleup-core' ), strtolower( $plural ) ),
		/* translators: %s: post type plural name. */
		'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'buckleup-core' ), strtolower( $plural ) ),
		'featured_image'        => __( 'Photo', 'buckleup-core' ),
		'set_featured_image'    => __( 'Set photo', 'buckleup-core' ),
		'remove_featured_image' => __( 'Remove photo', 'buckleup-core' ),
		'use_featured_image'    => __( 'Use as photo', 'buckleup-core' ),
	);
}

/**
 * Register all marketing CPTs.
 *
 * Runs on `init`. Rewrite rules for `location` are flushed on plugin
 * activation (see includes/activation.php), not here.
 */
function buckleup_register_post_types() {

	// Graduate — Hall-of-Fame gallery images. Title + photo + description.
	register_post_type(
		'graduate',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Graduate', 'buckleup-core' ), __( 'Graduates', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-awards',
			'menu_position' => 26,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
			'hierarchical'  => false,
		)
	);

	// Testimonial — named student reviews (grid/carousel).
	register_post_type(
		'testimonial',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Testimonial', 'buckleup-core' ), __( 'Testimonials', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-format-quote',
			'menu_position' => 27,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	// FAQ — single source for the accordion AND FAQPage JSON-LD.
	// Title = question; the long-form answer lives in post_content.
	register_post_type(
		'faq',
		array(
			'labels'        => buckleup_cpt_labels( __( 'FAQ', 'buckleup-core' ), __( 'FAQs', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-editor-help',
			'menu_position' => 28,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	// Service — license-class offerings (drives Services page + OfferCatalog).
	register_post_type(
		'service',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Service', 'buckleup-core' ), __( 'Services', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-car',
			'menu_position' => 29,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	// Package — home pricing plans. One CPT post per plan (no repeaters).
	register_post_type(
		'package',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Package', 'buckleup-core' ), __( 'Packages', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-tag',
			'menu_position' => 30,
			'supports'      => array( 'title', 'editor', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	// Instructor — team profiles (Farhad; Sarah Mitchell removed 2026-08-14).
	register_post_type(
		'instructor',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Instructor', 'buckleup-core' ), __( 'Instructors', 'buckleup-core' ) ),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-businessman',
			'menu_position' => 31,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	// Location — the ONLY publicly-queryable CPT. Owns /locations/{slug}.
	register_post_type(
		'location',
		array(
			'labels'        => buckleup_cpt_labels( __( 'Location', 'buckleup-core' ), __( 'Locations', 'buckleup-core' ) ),
			'public'        => true,
			'publicly_queryable' => true,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-location',
			'menu_position' => 32,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'   => false,
			'hierarchical'  => false,
			'rewrite'       => array(
				'slug'       => 'locations',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'buckleup_register_post_types' );
