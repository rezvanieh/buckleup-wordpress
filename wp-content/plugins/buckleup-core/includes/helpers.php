<?php
/**
 * Template helper functions — the public API the theme renders against.
 *
 * The theme should NOT call WP_Query / get_post_meta directly for these CPTs.
 * Instead it calls these helpers, which return plain associative arrays with
 * stable keys (documented in docs/CONTENT-MODEL.md). This keeps the data shape
 * decoupled from how it's stored, so the plugin can evolve storage without
 * breaking templates.
 *
 * All `bu_*()` query helpers return arrays of row-arrays, ordered by menu_order
 * then title, filtered to active items by default.
 *
 * @package BuckleUp_Core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read a single global site setting with default fallback.
 *
 * @param string $key     Setting key (see buckleup_settings_defaults()).
 * @param string $default Fallback if unset.
 * @return string
 */
function buckleup_get_setting( $key, $default = '' ) {
	static $settings = null;
	if ( null === $settings ) {
		$settings = wp_parse_args(
			get_option( BUCKLEUP_SETTINGS_OPTION, array() ),
			function_exists( 'buckleup_settings_defaults' ) ? buckleup_settings_defaults() : array()
		);
	}
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

/**
 * Read all settings as an array (for the SEO mu-plugin / schema builders).
 *
 * @return array<string,string>
 */
function buckleup_get_settings() {
	return wp_parse_args(
		get_option( BUCKLEUP_SETTINGS_OPTION, array() ),
		function_exists( 'buckleup_settings_defaults' ) ? buckleup_settings_defaults() : array()
	);
}

/**
 * Read a list-style meta key (repeating rows) as a clean array of strings.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return string[]
 */
function buckleup_get_meta_list( $post_id, $key ) {
	$values = get_post_meta( $post_id, $key, false );
	return array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) );
}

/**
 * Generic active-CPT query used by the typed helpers below.
 *
 * @param string $post_type    CPT key.
 * @param bool   $active_only  Only return rows with bu_is_active = 1.
 * @param int    $limit        -1 for all.
 * @return WP_Post[]
 */
function buckleup_query_cpt( $post_type, $active_only = true, $limit = -1 ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	);

	if ( $active_only ) {
		// Treat missing meta as active so newly-created posts show by default;
		// only an explicit '0' hides a row.
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => 'bu_is_active',
				'value'   => '1',
				'compare' => '=',
			),
			array(
				'key'     => 'bu_is_active',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	return get_posts( $args );
}

/**
 * Build the standard WhatsApp deep link with the exact live-site template:
 *   Hi! I'm interested in booking the *<name>* ($<price>).
 *
 * @param string     $name  Plan/service name.
 * @param int|string $price Price (numeric).
 * @return string Absolute https://wa.me/... URL.
 */
function buckleup_whatsapp_link( $name, $price = '' ) {
	$number = buckleup_get_setting( 'whatsapp', '16044413677' );
	if ( '' !== $price ) {
		$text = sprintf( "Hi! I'm interested in booking the *%s* ($%s).", $name, $price );
	} else {
		$text = sprintf( "Hi! I'm interested in the *%s*.", $name );
	}
	return 'https://wa.me/' . rawurlencode( $number ) . '?text=' . rawurlencode( $text );
}

/* -------------------------------------------------------------------------
 * Typed list helpers — each returns an array of plain arrays.
 * ---------------------------------------------------------------------- */

/**
 * Graduates for the Hall-of-Fame rail.
 *
 * @param int $limit Max rows (-1 all).
 * @return array<int,array{id:int,title:string,description:string,image:string,image_id:int}>
 */
function buckleup_get_graduates( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'graduate', true, $limit ) as $post ) {
		$out[] = array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'description' => $post->post_content,
			'image'       => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'image_id'    => (int) get_post_thumbnail_id( $post ),
		);
	}
	return $out;
}

/**
 * Testimonials for the grid/carousel.
 *
 * @param int $limit Max rows.
 * @return array<int,array<string,mixed>>
 */
function buckleup_get_testimonials( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'testimonial', true, $limit ) as $post ) {
		$out[] = array(
			'id'      => $post->ID,
			'name'    => get_post_meta( $post->ID, 'bu_author_name', true ) ?: get_the_title( $post ),
			'role'    => get_post_meta( $post->ID, 'bu_author_role', true ),
			'rating'  => (int) ( get_post_meta( $post->ID, 'bu_rating', true ) ?: 5 ),
			'content' => $post->post_content,
			'image'   => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
		);
	}
	return $out;
}

/**
 * FAQ entries — the single source for both the accordion and FAQPage schema.
 *
 * @param int $limit Max rows.
 * @return array<int,array{id:int,question:string,answer:string}>
 */
function buckleup_get_faqs( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'faq', true, $limit ) as $post ) {
		$out[] = array(
			'id'       => $post->ID,
			'question' => get_the_title( $post ),
			// Plain-text answer for schema; theme can wpautop() for display.
			'answer'   => wp_strip_all_tags( $post->post_content ),
		);
	}
	return $out;
}

/**
 * Services for the Services page / OfferCatalog.
 *
 * @param int $limit Max rows.
 * @return array<int,array<string,mixed>>
 */
function buckleup_get_services( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'service', true, $limit ) as $post ) {
		$out[] = array(
			'id'          => $post->ID,
			'name'        => get_the_title( $post ),
			'slug'        => $post->post_name,
			'description' => $post->post_content,
			'type'        => get_post_meta( $post->ID, 'bu_service_type', true ),
			'duration'    => (int) get_post_meta( $post->ID, 'bu_duration', true ),
			'price'       => (float) get_post_meta( $post->ID, 'bu_price', true ),
		);
	}
	return $out;
}

/**
 * Packages for the home Pricing section. Includes a ready-to-use WhatsApp link.
 *
 * @param int $limit Max rows.
 * @return array<int,array<string,mixed>>
 */
function buckleup_get_packages( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'package', true, $limit ) as $post ) {
		$name  = get_the_title( $post );
		$price = get_post_meta( $post->ID, 'bu_price', true );
		$out[] = array(
			'id'            => $post->ID,
			'name'          => $name,
			'description'   => $post->post_content,
			'price'         => (float) $price,
			'unit'          => get_post_meta( $post->ID, 'bu_unit', true ) ?: 'package',
			'sessions'      => (int) get_post_meta( $post->ID, 'bu_sessions', true ),
			'hours'         => (float) get_post_meta( $post->ID, 'bu_hours', true ),
			'car_fee'       => (float) get_post_meta( $post->ID, 'bu_car_fee', true ),
			'is_popular'    => '1' === get_post_meta( $post->ID, 'bu_is_popular', true ),
			'cta_label'     => get_post_meta( $post->ID, 'bu_cta_label', true ) ?: 'Get Started',
			'features'      => buckleup_get_meta_list( $post->ID, 'bu_features' ),
			'whatsapp_link' => buckleup_whatsapp_link( $name, $price ),
		);
	}
	return $out;
}

/**
 * Instructors for the Instructors page / home section.
 *
 * @param int $limit Max rows.
 * @return array<int,array<string,mixed>>
 */
function buckleup_get_instructors( $limit = -1 ) {
	$out = array();
	foreach ( buckleup_query_cpt( 'instructor', true, $limit ) as $post ) {
		$out[] = array(
			'id'             => $post->ID,
			'name'           => get_the_title( $post ),
			'role'           => get_post_meta( $post->ID, 'bu_role', true ),
			'rating'         => (float) get_post_meta( $post->ID, 'bu_rating', true ),
			'bio'            => $post->post_content,
			'image'          => get_the_post_thumbnail_url( $post, 'medium' ) ?: '',
			'certifications' => buckleup_get_meta_list( $post->ID, 'bu_certifications' ),
			'languages'      => buckleup_get_meta_list( $post->ID, 'bu_languages' ),
		);
	}
	return $out;
}

/**
 * All location entries (for nav dropdown / footer / sitemap).
 *
 * Locations are always "active" (no bu_is_active gate) since each is a real
 * published page.
 *
 * @return array<int,array<string,mixed>>
 */
function buckleup_get_locations() {
	$out = array();
	foreach ( buckleup_query_cpt( 'location', false, -1 ) as $post ) {
		$out[] = array(
			'id'    => $post->ID,
			'title' => get_the_title( $post ),
			'slug'  => $post->post_name,
			'url'   => get_permalink( $post ),
		);
	}
	return $out;
}

/**
 * Fields for a single location's hero/SEO. Pass a post ID or WP_Post; defaults
 * to the current post in the loop.
 *
 * @param int|WP_Post|null $post Post or ID.
 * @return array<string,string>
 */
function buckleup_get_location_fields( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return array(
		'hero_title'      => get_post_meta( $post->ID, 'bu_hero_title', true ),
		'hero_highlight'  => get_post_meta( $post->ID, 'bu_hero_highlight', true ),
		'hero_subtitle'   => get_post_meta( $post->ID, 'bu_hero_subtitle', true ),
		'seo_title'       => get_post_meta( $post->ID, 'bu_seo_title', true ),
		'seo_description' => get_post_meta( $post->ID, 'bu_seo_description', true ),
	);
}

/**
 * Resolve the brand logo URL for transactional emails. Prefers a named logo
 * attachment, then the theme custom logo, then the square site icon.
 *
 * @return string
 */
function buckleup_email_logo_url() {
	foreach ( array( 'buckleup-driving-school-logo-light', 'logo' ) as $slug ) {
		$ids = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( $ids ) {
			$url = wp_get_attachment_image_url( $ids[0], 'full' );
			if ( $url ) {
				return $url;
			}
		}
	}
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$url = wp_get_attachment_image_url( $custom, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) get_site_icon_url( 192 );
}

/**
 * Shared branded HTML email chrome: the logo header (red top accent), the white
 * 600px card, and the footer. Callers supply the inner `<tr>…</tr>` rows so each
 * email keeps full control of its body while sharing one house style. Used by
 * the contact form and the quiz result report.
 *
 * @param string $title       Document <title> + accessible heading (escaped).
 * @param string $inner        Pre-built, already-escaped table rows for the body.
 * @param string $footer_note  Pre-built footer note HTML; a default is used if ''.
 * @return string Full HTML document.
 */
function buckleup_email_shell( $title, $inner, $footer_note = '' ) {
	$logo   = esc_url( buckleup_email_logo_url() );
	$site   = esc_html( get_bloginfo( 'name' ) );
	$home   = esc_url( home_url( '/' ) );
	$accent = '#dc2626';
	$ink    = '#111827';
	$muted  = '#6b7280';
	$border = '#e5e7eb';
	$panel  = '#f9fafb';
	$page   = '#f3f4f6';
	$title_e = esc_html( $title );

	$logo_html = $logo
		? sprintf( '<a href="%1$s"><img src="%2$s" alt="%3$s" height="44" style="height:44px;width:auto;display:block;border:0;"></a>', $home, $logo, $site )
		: sprintf( '<a href="%1$s" style="font:700 22px/1 Arial,Helvetica,sans-serif;color:%2$s;text-decoration:none;">%3$s</a>', $home, $ink, $site );

	if ( '' === $footer_note ) {
		$footer_note = sprintf(
			/* translators: %s: linked site domain. */
			esc_html__( 'Sent from %s', 'buckleup-core' ),
			'<a href="' . $home . '" style="color:' . $muted . ';">buckleupdriving.ca</a>'
		);
	}

	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title_e . '</title></head>'
		. '<body style="margin:0;padding:0;background:' . $page . ';">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $page . ';padding:24px 12px;"><tr><td align="center">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid ' . $border . ';border-radius:12px;overflow:hidden;">'
		. '<tr><td align="center" style="padding:28px 24px 22px;border-bottom:3px solid ' . $accent . ';">' . $logo_html . '</td></tr>'
		. $inner
		. '<tr><td style="padding:18px 32px;background:' . $panel . ';border-top:1px solid ' . $border . ';">'
		. '<p style="margin:0;font:400 12px/1.5 Arial,Helvetica,sans-serif;color:' . $muted . ';">' . $footer_note . '</p>'
		. '</td></tr></table></td></tr></table></body></html>';
}
