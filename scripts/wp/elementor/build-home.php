<?php
/**
 * Build the Home page (front page, post 38) as native Elementor.
 * Seeds content from the SAME helper data the original patterns used, so the
 * Elementor version is faithful by construction, then becomes static/editable.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-home.php
 */
require __DIR__ . '/lib.php';

$PAGE_ID = el_post_id( 'home' );
if ( ! $PAGE_ID ) {
	echo "ERROR: no page with slug 'home'. Run provision first.\n";
	return;
}

$packages     = function_exists( 'buckleup_get_packages' ) ? buckleup_get_packages() : array();
$testimonials = function_exists( 'buckleup_get_testimonials' ) ? buckleup_get_testimonials() : array();
$faqs         = function_exists( 'buckleup_get_faqs' ) ? buckleup_get_faqs() : array();
$graduates    = function_exists( 'buckleup_get_graduates' ) ? buckleup_get_graduates() : array();
$hero_card    = function_exists( 'buckleup_asset_url' ) ? buckleup_asset_url( 'hero_card_image.png' ) : '';
$hero_bg      = function_exists( 'buckleup_asset_url' ) ? buckleup_asset_url( 'image2.png' ) : '';

/** Centered section heading block: eyebrow pill + h2 (accent word in brand blue) + optional subtitle. */
function home_heading( $eyebrow, $icon, $title_html, $sub = '' ) {
	$kids = array();
	$kids[] = el_container(
		array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'width' => el_size( 100, '%' ) ),
		array( el_pill( $eyebrow, $icon ) )
	);
	$kids[] = el_heading( $title_html, array( 'tag' => 'h2', 'size' => 40, 'align' => 'center', 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.1 ) );
	if ( $sub ) {
		$kids[] = el_text( $sub, array( 'align' => 'center', 'size' => 18, 'color_global' => 'mutedcol', 'max_width' => 620 ) );
	}
	return el_container(
		array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'column', 'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16' ), 'padding' => el_box( 0, 0, 16, 0 ) ),
		$kids
	);
}

/* ---------------------------------------------------------------- HERO -- */
function build_hero( $hero_bg, $hero_card ) {
	$badges = el_container(
		array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), 'width' => el_size( 100, '%' ) ),
		array(
			el_pill( 'ICBC Certified', 'fas fa-shield-alt' ),
			el_pill( '5-Star Rated', 'fas fa-star' ),
			el_pill( 'Dual-Control Vehicles', 'fas fa-check-circle' ),
		)
	);

	$left = el_col(
		array(
			$badges,
			el_heading( 'Master the Road <span class="gradient-text">with Confidence</span>', array( 'tag' => 'h1', 'size' => 64, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.02, 'letter_spacing' => -1 ) ),
			el_text( 'BuckleUp Driving School is a trusted driving school that Vancouver learners choose. We offer expert driving lessons in North Vancouver, Coquitlam, Port Moody, and the Tri-Cities, along with ICBC road test preparation.', array( 'size' => 19, 'color_global' => 'mutedcol', 'max_width' => 520 ) ),
			el_button( 'Start Learning Today', array( 'url' => '#pricing', 'size' => 'lg', 'icon' => 'fas fa-arrow-right' ) ),
		),
		array( 'width' => 54, 'gap' => 24, 'align' => 'flex-start' )
	);

	$card_kids = array();
	if ( $hero_card ) {
		$card_kids[] = el_image( $hero_card, array( 'radius' => 24, 'alt' => 'BuckleUp driving instructor with a student' ) );
	}
	// Floating rating chip under the image.
	$card_kids[] = el_col(
		array(
			el_container(
				array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ) ),
				array(
					el_heading( '4.98', array( 'tag' => 'div', 'size' => 30, 'weight' => 800, 'color_global' => 'primary' ) ),
					el_col(
						array(
							el_stars( 5, array( 'color' => '#F59E0B' ) ),
							el_text( 'Based on 200+ reviews', array( 'size' => 13, 'color_global' => 'mutedcol' ) ),
						),
						array( 'gap' => 4 )
					),
				)
			),
		),
		array( 'bg' => '#FFFFFF', 'pad' => 16, 'radius' => 16, 'border' => '#CBD5E1', 'shadow' => true, 'width' => 'auto' )
	);

	$right = el_col( $card_kids, array( 'width' => 42, 'gap' => 16 ) );

	$row = el_row( array( $left, $right ), 40, 'center', 'space-between' );

	$inner = el_container(
		array( 'content_width' => 'boxed', 'boxed_width' => el_size( 1200 ), 'width' => el_size( 100, '%' ), 'flex_direction' => 'column' ),
		array( $row )
	);

	$outer = el_container(
		array(
			'content_width'        => 'full',
			'min_height'           => el_size( 620 ),
			'flex_direction'       => 'column',
			'flex_justify_content' => 'center',
			'flex_align_items'     => 'center',
			'padding'              => el_box( 72, 16, 72, 16 ),
			'background_background' => 'classic',
			'background_color'     => '#F8FAFC',
			'background_image'     => array( 'url' => $hero_bg, 'id' => '' ),
			'background_position'  => 'center center',
			'background_repeat'    => 'no-repeat',
			'background_size'      => 'cover',
			'background_overlay_background'      => 'gradient',
			'background_overlay_color'           => 'rgba(248,250,252,0.94)',
			'background_overlay_color_b'         => 'rgba(248,250,252,0.45)',
			'background_overlay_gradient_type'   => 'linear',
			'background_overlay_gradient_angle'  => el_size( 90, 'deg' ),
		),
		array( $inner )
	);
	return $outer;
}

/* ---------------------------------------------------------- GRADUATES -- */
function build_graduates( $graduates ) {
	$cols = array();
	foreach ( array_slice( $graduates, 0, 12 ) as $g ) {
		$img = ! empty( $g['image'] ) ? $g['image'] : '';
		if ( ! $img ) { continue; }
		// Fixed-height cover crop → uniform tiles (fixes the aspect-ratio blowout).
		$cols[] = el_col(
			array( el_image( $img, array( 'radius' => 20, 'alt' => $g['title'], 'height' => 340, 'object_fit' => 'cover', 'width' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ) ),
			array( 'width' => 23, 'shadow' => true, 'gap_px' => 0 )
		);
	}
	$grid = el_row( $cols, 16, 'stretch', 'center' );
	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 72, 'gap' => 32, 'id_css' => 'graduates' ),
		array(
			home_heading( 'Milestones of Success', 'fas fa-medal', 'The Hall of <span class="gradient-text">Fame</span>', 'Join the legacy of confident drivers. Our graduates from North Vancouver, Coquitlam, and Port Moody reflect our commitment to safe driving, expert training, and ICBC road test success.' ),
			$grid,
		)
	);
}

/* ------------------------------------------------------------ PRICING -- */
function build_pricing( $packages ) {
	$cards = array();
	foreach ( $packages as $pkg ) {
		$popular = ! empty( $pkg['is_popular'] );
		$price   = '$' . rtrim( rtrim( number_format( (float) $pkg['price'], 2 ), '0' ), '.' );
		$kids    = array();
		if ( $popular ) {
			$kids[] = el_container(
				array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'background_background' => 'classic', 'background_color' => '#0B5CE0', 'border_radius' => el_box( 9999, 9999, 9999, 9999 ), 'padding' => el_box( 4, 14, 4, 14 ), '_flex_grow' => 0, 'width' => array( 'unit' => 'px', 'size' => '', 'sizes' => array() ) ),
				array( el_text( 'Most Popular', array( 'size' => 12, 'color' => '#FFFFFF' ) ) )
			);
		}
		$kids[] = el_heading( $pkg['name'], array( 'tag' => 'h3', 'size' => 20, 'weight' => 700, 'color_global' => 'text' ) );
		if ( ! empty( $pkg['description'] ) ) {
			$kids[] = el_text( $pkg['description'], array( 'size' => 14, 'color_global' => 'mutedcol' ) );
		}
		$kids[] = el_container(
			array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_align_items' => 'flex-end', 'flex_gap' => array( 'unit' => 'px', 'size' => 4, 'column' => '4', 'row' => '4' ), 'padding' => el_box( 8, 0, 8, 0 ) ),
			array(
				el_heading( $price, array( 'tag' => 'div', 'size' => 38, 'weight' => 800, 'color_global' => 'text' ) ),
				el_text( '/' . ( $pkg['unit'] ?: 'package' ), array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
			)
		);
		if ( ! empty( $pkg['features'] ) ) {
			$kids[] = el_icon_list( $pkg['features'], array( 'icon' => 'fas fa-check', 'color_global' => 'secondary' ) );
		}
		$btn_opts = array(
			'url'      => ! empty( $pkg['whatsapp_link'] ) ? $pkg['whatsapp_link'] : '#contact',
			'external' => true,
			'size'     => 'md',
			'align'    => 'justify', // full-width like the live cards
		);
		if ( $popular ) {
			$btn_opts['bg_global']  = 'primary';
			$btn_opts['text_color'] = '#FFFFFF';
		} else {
			$btn_opts['variant'] = 'outline';
		}
		$kids[] = el_button( $pkg['cta_label'] ?: 'Get Started', $btn_opts );

		$cards[] = el_col(
			$kids,
			array(
				'width'  => 31,
				'bg'     => '#FFFFFF',
				'pad'    => 24,
				'radius' => 20,
				'border' => $popular ? '#0B5CE0' : '#CBD5E1',
				'shadow' => true,
				'gap_px' => 12,
				'align'  => 'flex-start',
				'id'     => $popular ? 'most-popular' : '', // anchor for the hero "Start Learning Today" → /#most-popular
			)
		);
	}
	$grid = el_row( $cards, 20, 'stretch', 'center' );
	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 72, 'gap' => 32, 'id_css' => 'pricing' ),
		array(
			home_heading( 'Simple Pricing', 'fas fa-star', 'Invest in Your <span class="gradient-text">Future</span>', 'Transparent pricing with no hidden fees. Choose the package that fits your needs.' ),
			$grid,
		)
	);
}

/* --------------------------------------------------- PRACTICE EXAM -- */
/**
 * Practice Exam Center spotlight — the conversion centrepiece. Sits right after
 * Pricing. Marketing copy + benefit list + a CTA into the free practice exam, with
 * an elegant stats panel (the figures that build trust + intent).
 */
function build_practice() {
	$benefits = el_icon_list(
		array(
			'Mirrors the real ICBC Class 4 test — same length and topics',
			'Instant score with a topic-by-topic breakdown',
			'Find and fix your weak spots before test day',
			'Earn a certificate of completion when you pass',
		),
		array( 'icon' => 'fas fa-check-circle', 'color_global' => 'secondary' )
	);

	$left = el_col(
		array(
			el_pill( 'Free Practice Exam', 'fas fa-graduation-cap' ),
			el_heading( 'Walk Into Your ICBC Class 4 Test <span class="gradient-text">Ready</span>', array( 'tag' => 'h2', 'size' => 40, 'weight' => 800, 'color_global' => 'text', 'line_height' => 1.1 ) ),
			el_text( 'Take our free, timed practice exam before the real thing. It mirrors ICBC&rsquo;s Class 4 knowledge test, so you walk in knowing exactly what to expect — and exactly what to study.', array( 'size' => 18, 'color_global' => 'mutedcol', 'max_width' => 540 ) ),
			$benefits,
			el_button( 'Start the Free Practice Test', array( 'url' => '/icbc-class-4-knowledge-test/', 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ),
		),
		array( 'width' => 50, 'gap_px' => 22, 'align' => 'flex-start' )
	);

	// Stat tiles (2x2) — the figures that read as "data/charts".
	$tile = function ( $num, $label ) {
		return el_col(
			array(
				el_heading( $num, array( 'tag' => 'div', 'size' => 42, 'weight' => 800, 'color_global' => 'primary', 'line_height' => 1 ) ),
				el_text( $label, array( 'size' => 13, 'color_global' => 'mutedcol' ) ),
			),
			array( 'width' => 46, 'bg' => '#FFFFFF', 'pad' => 22, 'radius' => 16, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 6, 'align' => 'flex-start' )
		);
	};
	$tiles = el_row(
		array(
			$tile( '50', 'Questions, like the real test' ),
			$tile( '80%', 'Score you need to pass' ),
			$tile( '45', 'Minute timed exam' ),
			$tile( '12', 'Commercial-driving topics' ),
		),
		16, 'stretch', 'flex-start'
	);
	$highlight = el_col(
		array(
			el_container(
				array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16' ) ),
				array(
					// Was "98% first-time pass rate" — an unverifiable claim. Replaced with a
					// fact we can actually stand behind: the question bank really does hold
					// 231 questions across the 12 official ICBC Class 4 topics.
					el_heading( '231', array( 'tag' => 'div', 'size' => 48, 'weight' => 800, 'color_global' => 'secondary', 'line_height' => 1 ) ),
					el_col(
						array(
							el_text( '<strong>Practice questions</strong>', array( 'raw' => true, 'size' => 17, 'color_global' => 'text' ) ),
							el_text( 'across all 12 official ICBC Class 4 topics — free, no signup to start', array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
						),
						array( 'gap' => 2, 'width' => 'grow', 'align' => 'flex-start' )
					),
				)
			),
		),
		array( 'width' => 100, 'bg' => '#FFFFFF', 'pad' => 24, 'radius' => 16, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 0 )
	);
	$right = el_col( array( $tiles, $highlight ), array( 'width' => 42, 'gap_px' => 16, 'align' => 'stretch' ) );

	$row = el_row( array( $left, $right ), 48, 'flex-start', 'space-between' );

	return el_section(
		array( 'gradient' => array( '#EEF4FF', '#F8FAFC', 155 ), 'pad_y' => 80, 'gap' => 24, 'content_width' => 1200, 'id_css' => 'practice-test' ),
		array( $row )
	);
}

/* ------------------------------------------------------- TESTIMONIALS -- */
function build_testimonials( $testimonials ) {
	$cards = array();
	foreach ( array_slice( $testimonials, 0, 6 ) as $t ) {
		$cards[] = el_col(
			array(
				el_col(
					array(
						el_heading( $t['name'], array( 'tag' => 'div', 'size' => 17, 'weight' => 700, 'color_global' => 'text' ) ),
						el_text( $t['role'] ?: 'Google Review', array( 'size' => 13, 'color_global' => 'mutedcol' ) ),
					),
					array( 'gap' => 2, 'width' => 'auto' )
				),
				el_stars( max( 1, (int) $t['rating'] ), array( 'color' => '#F59E0B' ) ),
				el_text( $t['content'], array( 'size' => 15, 'color_global' => 'fgcolor' ) ),
			),
			array( 'width' => 31, 'bg' => '#FFFFFF', 'pad' => 24, 'radius' => 20, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 14, 'align' => 'flex-start' )
		);
	}
	$grid = el_row( $cards, 20, 'stretch', 'center' );

	$trust = el_container(
		array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'center', 'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '12' ), 'padding' => el_box( 24, 0, 0, 0 ) ),
		array(
			el_text( '<strong>5.0</strong> &nbsp;Google Reviews', array( 'raw' => true, 'size' => 15, 'color_global' => 'fgcolor' ) ),
			el_text( '<strong>A+</strong> &nbsp;BBB Rating', array( 'raw' => true, 'size' => 15, 'color_global' => 'fgcolor' ) ),
			el_text( '<strong>#1</strong> &nbsp;Rated in Vancouver', array( 'raw' => true, 'size' => 15, 'color_global' => 'fgcolor' ) ),
		)
	);

	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 72, 'gap' => 32, 'id_css' => 'testimonials' ),
		array(
			home_heading( 'Student Reviews', 'fas fa-star', 'Loved by <span class="gradient-text">Thousands</span>', 'Join over 10,000 happy students who are now safe, confident drivers on the road.' ),
			$grid,
			$trust,
		)
	);
}

/* ---------------------------------------------------------------- FAQ -- */
function build_faq( $faqs ) {
	$items = array();
	foreach ( $faqs as $f ) {
		$items[] = array( $f['question'], $f['answer'] );
	}
	$contact = el_text(
		'Still have questions? <a href="/contact/" style="color:#0B5CE0;font-weight:600;">Contact us</a> and we&rsquo;ll be happy to help.',
		array( 'raw' => true, 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol' )
	);
	$inner = el_col(
		array(
			home_heading( 'FAQ', 'fas fa-comment-dots', 'Common <span class="gradient-text">Questions</span>' ),
			el_accordion( $items ),
			$contact,
		),
		array( 'width' => 100, 'gap_px' => 20 )
	);
	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 72, 'gap' => 24, 'content_width' => 820, 'id_css' => 'faq' ),
		array( $inner )
	);
}

/* ------------------------------------------------------------- ASSEMBLE */
/** "Ready to Start Driving?" CTA band — HOME ONLY (the original shows it on the front page only, not site-wide). */
function build_cta() {
	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 56, 'gap' => 16, 'content_width' => 1200 ),
		array( el_col(
			array( el_row( array(
				el_col( array(
					el_heading( 'Ready to Start Driving?', array( 'tag' => 'h2', 'size' => 28, 'weight' => 800, 'color_global' => 'text' ) ),
					el_text( 'Book your first lesson today and get 20% off!', array( 'size' => 16, 'color_global' => 'mutedcol' ) ),
				), array( 'gap_px' => 6, 'width' => 'grow', 'align' => 'flex-start' ) ),
				el_col( array( el_button( 'Book Now', array( 'url' => '#pricing', 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ) ), array( 'width' => 'auto' ) ),
			), 24, 'center', 'space-between' ) ),
			array( 'width' => 100, 'bg' => '#FFFFFF', 'pad' => 36, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true )
		) )
	);
}

// Full-Elementor build. EXCEPTION: the Gallery and Reviews are DATA-DRIVEN sections
// fed by the consoles/CPTs (admin adds a graduate photo; a student submits a review →
// admin approves → it must surface here). Those can't be static hand-built widgets or
// the workflow breaks — so they render LIVE via the real section (native Shortcode
// widget), staying positioned in the Elementor page but updating automatically.
// NOTE: the hero is the REAL theme showpiece (rendered by front-page.html before
// post-content) for exact fidelity — its floating rating card / Toyota badge /
// instructor chip / star rendering can't be cleanly reproduced in Elementor. So it
// is NOT built here.
$tree = array(
	el_container( array( 'content_width' => 'full', 'padding' => el_box( 0, 0, 0, 0 ) ), array( el_shortcode( '[buckleup_section name="home-graduates"]' ) ) ),
	build_pricing( $packages ),
	build_practice(),
	el_container( array( 'content_width' => 'full', 'padding' => el_box( 0, 0, 0, 0 ) ), array( el_shortcode( '[buckleup_section name="home-testimonials"]' ) ) ),
	// FAQ as the LIVE theme section (accessible accordion + dynamic from the faq CPT) —
	// avoids the Elementor accordion's aria-allowed-attr a11y violation (QA S3).
	el_container( array( 'content_width' => 'full', 'padding' => el_box( 0, 0, 0, 0 ) ), array( el_shortcode( '[buckleup_section name="home-faq"]' ) ) ),
	build_cta(),
);

el_save_page( $PAGE_ID, $tree );
echo 'Home built: ' . count( $packages ) . ' packages, ' . min( 6, count( $testimonials ) ) . '/' . count( $testimonials ) . ' testimonials, ' . count( $faqs ) . ' faqs, ' . min( 12, count( $graduates ) ) . ' graduates.' . "\n";
echo 'URL: ' . get_permalink( $PAGE_ID ) . "\n";
