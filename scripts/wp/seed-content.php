<?php
/**
 * Seeds marketing content the panel teams render: FAQ (14), Testimonials (5 fallbacks),
 * Pricing plans (4 home plans), and the static Pages (Home/About/Contact/locations).
 * Idempotent. Run via: wp eval-file /scripts/wp/seed-content.php
 *
 * This file intentionally seeds only the STRUCTURE + a couple of exemplar rows so the
 * stack provisions end-to-end today. The marketing/content team fills the full verbatim
 * copy (catalogued in the discovery doc) into these same CPTs.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// --- Ensure the front page is a static "Home" page (block theme renders patterns) ---
$home = get_page_by_path( 'home' );
if ( ! $home ) {
	$home_id = wp_insert_post( array(
		'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home',
		'post_status' => 'publish', 'post_content' => '<!-- wp:pattern {"slug":"buckleup/home"} /-->',
	) );
} else { $home_id = $home->ID; }
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );

// --- Core static pages ---
foreach ( array(
	'about'   => 'About Us',
	'contact' => 'Contact',
	'services'=> 'Services',
	'blog'    => 'Blog',
) as $slug => $title ) {
	if ( ! get_page_by_path( $slug ) ) {
		wp_insert_post( array(
			'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish',
		) );
	}
}

// --- Location pages (5) — geo-targeted, content per discovery doc ---
$locations = array(
	'north-vancouver' => 'Driving School in North Vancouver',
	'coquitlam'       => 'Driving Lessons in Coquitlam',
	'port-coquitlam'  => 'Driving Lessons in Port Coquitlam',
	'port-moody'      => 'Driving School in Port Moody',
	'tri-cities'      => 'Driving Lessons in the Tri-Cities',
);
$loc_parent = get_page_by_path( 'locations' );
$loc_parent_id = $loc_parent ? $loc_parent->ID : wp_insert_post( array(
	'post_type' => 'page', 'post_name' => 'locations', 'post_title' => 'Locations', 'post_status' => 'publish',
) );
foreach ( $locations as $slug => $h1 ) {
	if ( ! get_page_by_path( "locations/{$slug}" ) ) {
		wp_insert_post( array(
			'post_type' => 'page', 'post_name' => $slug, 'post_title' => $h1,
			'post_parent' => $loc_parent_id, 'post_status' => 'publish',
		) );
	}
}

// --- Testimonials (CPT bu_testimonial) — 1 exemplar; team adds the other 4 ---
if ( post_type_exists( 'bu_testimonial' ) ) {
	if ( ! get_page_by_path( 'jason-kim', OBJECT, 'bu_testimonial' ) ) {
		$tid = wp_insert_post( array(
			'post_type' => 'bu_testimonial', 'post_name' => 'jason-kim', 'post_title' => 'Jason Kim',
			'post_status' => 'publish',
			'post_content' => 'I failed my test twice with another school. After 5 lessons with BuckleUp, I passed with zero demerits.',
		) );
		update_post_meta( $tid, 'bu_role', 'Passed N Test' );
		update_post_meta( $tid, 'bu_rating', 5 );
	}
}

WP_CLI::success( 'Content scaffolded: front page, core pages, 5 locations, exemplar testimonial.' );
WP_CLI::log( '   (Marketing team fills full verbatim FAQ/Testimonials/Pricing copy into these CPTs.)' );
