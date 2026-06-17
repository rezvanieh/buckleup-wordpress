<?php
/**
 * Build the 5 LOCATION landing pages as native, fully-editable Elementor.
 *
 * Each `location` CPT post (Coquitlam 33, North Vancouver 34, Port Coquitlam 35,
 * Port Moody 36, Tri-Cities 37) gets a stunning, SEO-optimised body authored in
 * Elementor — fronted by a recognizable city-landmark hero background — so admins
 * can edit every page directly in Elementor and refresh content for SEO.
 *
 * Content is the single source of truth in `locations-content.php`; the hero
 * images were imported by `import-location-heroes.php` (tagged `_bu_location_hero`).
 * Elementor must be enabled for the `location` CPT (elementor_cpt_support) and the
 * theme's `single-location.html` is the full-bleed Elementor template.
 *
 * Idempotent: resolves posts/attachments by slug and overwrites the same IDs.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-locations.php
 *
 * @package BuckleUp
 */

require __DIR__ . '/lib.php';

$CONTENT = require __DIR__ . '/locations-content.php';

/* ---- shared helpers (mirrors build-pages.php; guarded so both can co-load) -- */

if ( ! function_exists( 'sec_heading' ) ) {
	/** Centered section heading (h2 + optional subtitle). */
	function sec_heading( $title_html, $sub = '' ) {
		$kids   = array();
		$kids[] = el_heading( $title_html, array( 'tag' => 'h2', 'size' => 36, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.1 ) );
		if ( $sub ) {
			$kids[] = el_text( $sub, array( 'align' => 'center', 'size' => 17, 'color_global' => 'mutedcol', 'max_width' => 640 ) );
		}
		return el_col( $kids, array( 'width' => 100, 'gap_px' => 14, 'align' => 'center' ) );
	}
}

if ( ! function_exists( 'card_col' ) ) {
	/** A white card column. */
	function card_col( array $children, $width = 31, array $o = array() ) {
		return el_col( $children, array( 'width' => $width, 'bg' => '#FFFFFF', 'pad' => isset( $o['pad'] ) ? $o['pad'] : 24, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => isset( $o['align'] ) ? $o['align'] : 'flex-start' ) );
	}
}

if ( ! function_exists( 'icon_chip' ) ) {
	/** Round-bg icon chip. */
	function icon_chip( $fa, $color_global = 'primary' ) {
		return el_container(
			array(
				'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'flex_align_items' => 'center',
				'background_background' => 'classic', 'background_color' => 'rgba(11,92,224,0.10)',
				'border_radius' => el_box( 16, 16, 16, 16 ), 'padding' => el_box( 14, 14, 14, 14 ), '_flex_grow' => 0,
			),
			array( el_icon( $fa, array( 'size' => 26, 'color_global' => $color_global ) ) )
		);
	}
}

/* ---- location-specific builders ---------------------------------------- */

/** Full-bleed landmark hero: image bg + dark overlay, left-aligned content. */
function loc_hero( array $d, $hero_url, $hero_id, $wa_link ) {
	// H1: plain lead-in + gradient-highlighted city (mirrors the home hero).
	$title_html = trim( esc_html( $d['hero_title'] ) . ' <span class="gradient-text">' . esc_html( $d['hero_highlight'] ) . '</span>' );

	// Eyebrow pill — white-on-glass for the dark hero.
	$eyebrow = el_container(
		array(
			'content_width' => 'full', 'css_classes' => 'bu-pill', 'flex_direction' => 'row', 'flex_align_items' => 'center',
			'flex_gap' => array( 'unit' => 'px', 'size' => 8, 'column' => '8', 'row' => '8' ),
			'border_radius' => el_box( 9999, 9999, 9999, 9999 ), 'border_border' => 'solid', 'border_width' => el_box( 1, 1, 1, 1 ),
			'border_color' => 'rgba(255,255,255,0.35)', 'background_background' => 'classic', 'background_color' => 'rgba(255,255,255,0.14)',
			'padding' => el_box( 6, 16, 6, 16 ), '_flex_grow' => 0, 'flex_shrink' => 0,
		),
		array(
			el_icon( 'fas fa-location-dot', array( 'size' => 15, 'color' => '#FFFFFF' ) ),
			el_text( $d['hero_eyebrow'], array( 'size' => 14, 'color' => '#FFFFFF' ) ),
		)
	);

	// CTAs.
	$ctas = el_container(
		array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), '_flex_grow' => 0, 'padding' => el_box( 8, 0, 0, 0 ) ),
		array(
			el_button( 'Start Learning Today', array( 'url' => '#pricing', 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary', 'text_color' => '#FFFFFF' ) ),
			el_button( 'Chat on WhatsApp', array( 'url' => $wa_link, 'external' => true, 'size' => 'lg', 'bg' => 'rgba(255,255,255,0.16)', 'text_color' => '#FFFFFF', 'icon' => 'fas fa-comment-dots' ) ),
		)
	);

	// Trust stats row (white).
	$stat_cols = array();
	foreach ( $d['hero_stats'] as $st ) {
		$stat_cols[] = el_col(
			array(
				el_heading( $st['value'], array( 'tag' => 'div', 'size' => 30, 'weight' => 800, 'color' => '#FFFFFF', 'line_height' => 1.0 ) ),
				el_text( $st['label'], array( 'size' => 13, 'color' => 'rgba(255,255,255,0.82)' ) ),
			),
			array( 'width' => 'auto', 'gap_px' => 2, 'align' => 'flex-start' )
		);
	}
	$stats = el_container(
		array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => array( 'unit' => 'px', 'size' => 36, 'column' => '36', 'row' => '20' ), '_flex_grow' => 0, 'padding' => el_box( 18, 0, 0, 0 ) ),
		$stat_cols
	);

	$col = el_col(
		array(
			$eyebrow,
			el_heading( $title_html, array( 'tag' => 'h1', 'size' => 60, 'weight' => 800, 'color' => '#FFFFFF', 'line_height' => 1.02, 'max_width' => 780 ) ),
			el_text( $d['hero_subtitle'], array( 'size' => 19, 'color' => 'rgba(255,255,255,0.9)', 'max_width' => 660 ) ),
			$ctas,
			$stats,
		),
		array( 'width' => 100, 'gap_px' => 20, 'align' => 'flex-start' )
	);

	return el_section(
		array(
			'bg_image'   => array( 'url' => $hero_url, 'id' => $hero_id ),
			'overlay'    => array( 'color' => '#0F1729', 'opacity' => 0.6 ),
			'pad_y'      => 104,
			'min_height' => 600,
			'content_width' => 1180,
			'gap'        => 22,
			'align'      => 'flex-start',
		),
		array( $col )
	);
}

/** Intro: local context copy + a "why BuckleUp" feature card. */
function loc_intro( array $d ) {
	$left_kids = array( el_heading( $d['intro_heading'], array( 'tag' => 'h2', 'size' => 34, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.14 ) ) );
	foreach ( $d['intro_body'] as $p ) {
		$left_kids[] = el_text( $p, array( 'size' => 16, 'color_global' => 'mutedcol' ) );
	}
	$left = el_col( $left_kids, array( 'width' => 58, 'gap_px' => 14, 'align' => 'flex-start' ) );

	$card = el_col(
		array(
			el_heading( 'Why learn with BuckleUp', array( 'tag' => 'h3', 'size' => 19, 'weight' => 700, 'color_global' => 'text' ) ),
			el_icon_list(
				array( 'ICBC-certified instructors', '98% first-time pass rate', 'English &amp; Farsi lessons', 'Modern dual-control Toyotas', 'Free local pickup &amp; drop-off' ),
				array( 'icon' => 'fas fa-circle-check', 'color_global' => 'secondary' )
			),
			el_button( 'Book a Lesson', array( 'url' => '#pricing', 'size' => 'md', 'bg_global' => 'primary', 'icon' => 'fas fa-arrow-right' ) ),
		),
		array( 'width' => 38, 'bg' => '#FFFFFF', 'pad' => 28, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 14, 'align' => 'flex-start' )
	);

	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 72, 'gap' => 24 ),
		array( el_row( array( $left, $card ), 40, 'flex-start', 'space-between' ) )
	);
}

/** Why-choose: 4 value-prop cards. */
function loc_why( array $d ) {
	$cards = array();
	foreach ( $d['why_items'] as $w ) {
		$cards[] = card_col(
			array(
				icon_chip( $w['icon'] ),
				el_heading( $w['title'], array( 'tag' => 'h3', 'size' => 17, 'weight' => 700, 'align' => 'center', 'color_global' => 'text' ) ),
				el_text( $w['desc'], array( 'align' => 'center', 'size' => 14, 'color_global' => 'mutedcol' ) ),
			),
			23,
			array( 'align' => 'center' )
		);
	}
	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 32 ),
		array( sec_heading( $d['why_heading'] ), el_row( $cards, 20, 'stretch', 'center' ) )
	);
}

/** Neighbourhoods served: wrapping pill cloud (local SEO). */
function loc_neighborhoods( array $d ) {
	$pills = array();
	foreach ( $d['neighborhoods'] as $n ) {
		$pills[] = el_pill( $n, 'fas fa-location-dot' );
	}
	$cloud = el_container(
		array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ) ),
		$pills
	);
	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 56, 'gap' => 24 ),
		array( sec_heading( $d['neighborhoods_heading'], 'We pick up and teach right across the area.' ), $cloud )
	);
}

/** ICBC road-test prep: local routes copy + a tips card. */
function loc_icbc( array $d ) {
	$icbc      = $d['icbc'];
	$left_kids = array(
		el_heading( $icbc['heading'], array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.14 ) ),
		el_text( '<strong>Test centre:</strong> ' . $icbc['centre'], array( 'raw' => true, 'size' => 15, 'color_global' => 'fgcolor' ) ),
	);
	foreach ( $icbc['body'] as $p ) {
		$left_kids[] = el_text( $p, array( 'size' => 16, 'color_global' => 'mutedcol' ) );
	}
	$left = el_col( $left_kids, array( 'width' => 54, 'gap_px' => 14, 'align' => 'flex-start' ) );

	$tips = el_col(
		array(
			el_heading( 'Local Test-Day Tips', array( 'tag' => 'h3', 'size' => 18, 'weight' => 700, 'color_global' => 'text' ) ),
			el_icon_list( $icbc['tips'], array( 'icon' => 'fas fa-circle-check', 'color_global' => 'secondary', 'size' => 15 ) ),
		),
		array( 'width' => 40, 'bg' => '#FFFFFF', 'pad' => 28, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => 'flex-start' )
	);

	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 24 ),
		array( el_row( array( $left, $tips ), 32, 'flex-start', 'space-between' ) )
	);
}

/** Embed a shared theme section (pricing/graduates/testimonials) edge-to-edge. */
function loc_embed( $name, $anchor = '' ) {
	$s = array( 'content_width' => 'full', 'padding' => el_box( 0, 0, 0, 0 ) );
	if ( $anchor ) {
		$s['_element_id'] = $anchor;
	}
	return el_container( $s, array( el_shortcode( '[buckleup_section name="' . $name . '"]' ) ) );
}

/** Location-specific FAQ accordion. */
function loc_faq( array $d ) {
	$items = array();
	foreach ( $d['faqs'] as $f ) {
		$items[] = array( $f['q'], $f['a'] );
	}
	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 28, 'content_width' => 900 ),
		array(
			sec_heading( 'Frequently Asked Questions', 'Common questions about driving lessons in ' . $d['city'] . '.' ),
			el_col( array( el_accordion( $items ) ), array( 'width' => 100, 'gap_px' => 0 ) ),
		)
	);
}

/** Internal-linking "nearby areas" section — contextual links to sibling pages. */
function loc_nearby( $slug, array $content ) {
	$links = array();
	foreach ( $content as $sib_slug => $sib ) {
		if ( $sib_slug === $slug ) {
			continue;
		}
		$label   = ( 'tri-cities' === $sib_slug ) ? 'the ' . $sib['city'] : $sib['city'];
		$links[] = '<a href="' . esc_url( home_url( "/locations/{$sib_slug}/" ) ) . '">' . esc_html( $label ) . '</a>';
	}
	// Natural-language sentence with keyword-rich anchor text (stronger than nav links).
	$last     = array_pop( $links );
	$list     = $links ? implode( ', ', $links ) . ', and ' . $last : $last;
	$services = '<a href="' . esc_url( home_url( '/services/' ) ) . '">lesson packages and pricing</a>';
	$contact  = '<a href="' . esc_url( home_url( '/contact/' ) ) . '">get in touch</a>';
	$body     = "BuckleUp also provides ICBC-certified driving lessons in {$list}. Explore our {$services}, or {$contact} to book your first lesson.";

	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 48, 'gap' => 14, 'content_width' => 900 ),
		array(
			el_col(
				array(
					el_heading( 'Driving Lessons Across the Tri-Cities &amp; North Shore', array( 'tag' => 'h2', 'size' => 26, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
					el_text( $body, array( 'raw' => true, 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol', 'max_width' => 760 ) ),
				),
				array( 'width' => 100, 'gap_px' => 10, 'align' => 'center' )
			),
		)
	);
}

/** Closing CTA band (brand gradient). */
function loc_cta( array $d, $wa_link ) {
	$buttons = el_container(
		array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), '_flex_grow' => 0, 'padding' => el_box( 8, 0, 0, 0 ) ),
		array(
			el_button( 'Start Learning Today', array( 'url' => '#pricing', 'size' => 'lg', 'bg' => '#FFFFFF', 'text_color' => '#0B5CE0', 'icon' => 'fas fa-arrow-right' ) ),
			el_button( 'Chat on WhatsApp', array( 'url' => $wa_link, 'external' => true, 'size' => 'lg', 'bg' => 'rgba(255,255,255,0.18)', 'text_color' => '#FFFFFF', 'icon' => 'fas fa-comment-dots' ) ),
		)
	);
	return el_section(
		array( 'gradient' => array( '#0B5CE0', '#10B77F', 135 ), 'pad_y' => 76, 'gap' => 18 ),
		array(
			el_col(
				array(
					el_heading( $d['cta_heading'], array( 'tag' => 'h2', 'size' => 34, 'weight' => 800, 'align' => 'center', 'color' => '#FFFFFF', 'line_height' => 1.1 ) ),
					el_text( $d['cta_body'], array( 'align' => 'center', 'size' => 17, 'color' => 'rgba(255,255,255,0.92)', 'max_width' => 620 ) ),
					$buttons,
				),
				array( 'width' => 100, 'gap_px' => 16, 'align' => 'center' )
			),
		)
	);
}

/* ---- run --------------------------------------------------------------- */

// Prerequisite: Elementor's editor + frontend builder must be enabled for the
// `location` CPT (it defaults to post/page only). Self-enable so a fresh reset
// reproduces editable location pages. (export-for-prod.php also carries this option.)
$cpt_support = get_option( 'elementor_cpt_support' );
$cpt_support = is_array( $cpt_support ) && $cpt_support ? $cpt_support : array( 'post', 'page' );
if ( ! in_array( 'location', $cpt_support, true ) ) {
	$cpt_support[] = 'location';
	update_option( 'elementor_cpt_support', $cpt_support );
	echo "Enabled Elementor for the 'location' CPT (elementor_cpt_support).\n";
}

$wa_number = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677';
$wa_number = preg_replace( '/\D/', '', (string) $wa_number );

$built = 0;
foreach ( $CONTENT as $slug => $d ) {
	$post = get_page_by_path( $slug, OBJECT, 'location' );
	if ( ! $post ) {
		echo "SKIP {$slug}: no location post.\n";
		continue;
	}

	// Resolve the imported landmark hero by its tag.
	$att = get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_bu_location_hero',
		'meta_value'  => $slug,
		'numberposts' => 1,
		'fields'      => 'ids',
	) );
	$hero_id  = $att ? (int) $att[0] : 0;
	$hero_url = $hero_id ? wp_get_attachment_url( $hero_id ) : '';
	if ( ! $hero_url ) {
		echo "WARN {$slug}: no hero image (run import-location-heroes.php first).\n";
	}

	$wa_link = 'https://wa.me/' . $wa_number . '?text=' . rawurlencode( "Hi, I'm interested in driving lessons in " . $d['city'] . '.' );

	$tree = array(
		loc_hero( $d, $hero_url, $hero_id, $wa_link ),
		loc_intro( $d ),
		loc_why( $d ),
		loc_neighborhoods( $d ),
		loc_icbc( $d ),
		loc_embed( 'home-pricing', 'pricing' ),
		loc_embed( 'home-graduates' ),
		loc_embed( 'home-testimonials' ),
		loc_faq( $d ),
		loc_nearby( $slug, $CONTENT ),
		loc_cta( $d, $wa_link ),
	);

	// CPT single: no page template (single-location.html resolves via hierarchy),
	// document type wp-post.
	el_save_page( $post->ID, $tree, array( 'template' => false, 'template_type' => 'wp-post' ) );

	// Sync the admin-editable per-location SEO fields from the content SSOT so the
	// optimized title/description flow into Rank Math (seo-config.php prefers these
	// bu_seo_* fields when set). Keeps locations-content.php the single source.
	if ( ! empty( $d['seo_title'] ) ) {
		update_post_meta( $post->ID, 'bu_seo_title', $d['seo_title'] );
	}
	if ( ! empty( $d['seo_description'] ) ) {
		update_post_meta( $post->ID, 'bu_seo_description', $d['seo_description'] );
	}

	// Featured image = the landmark hero. Not rendered visibly (single-location.html
	// is Elementor-only) but it drives the social/SEO image: Rank Math uses it for
	// og:image/twitter:image, and the theme preloads it for a faster LCP.
	if ( $hero_id ) {
		set_post_thumbnail( $post->ID, $hero_id );
		// Explicit per-page social image so og:image/twitter:image are the city hero
		// regardless of Rank Math's fallback config.
		update_post_meta( $post->ID, 'rank_math_facebook_image', $hero_url );
		update_post_meta( $post->ID, 'rank_math_facebook_image_id', $hero_id );
		update_post_meta( $post->ID, 'rank_math_twitter_use_facebook', 'on' );
	}

	$built++;
}

echo "Built {$built} location pages (Elementor). Hero images: attachment-backed.\n";
