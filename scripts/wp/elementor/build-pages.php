<?php
/**
 * Build the 6 remaining marketing pages as native, editable Elementor:
 * About(39), Contact(40), Services(41), Instructors(42), Resources(44), ICBC(45).
 * On-brand via the real design system (gradient-text/glass loaded site-wide);
 * native Elementor Containers + widgets so every page is editable in Elementor.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-pages.php
 */
require __DIR__ . '/lib.php';

/* ---- shared helpers ---------------------------------------------------- */

/** Centered page hero: eyebrow pill + H1 (accent via gradient-text span) + subtitle. */
function page_hero( $eyebrow, $icon, $title_html, $sub, array $o = array() ) {
	$kids = array();
	if ( $eyebrow ) {
		$kids[] = el_container(
			array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_justify_content' => 'center' ),
			array( el_pill( $eyebrow, $icon, isset( $o['pill'] ) ? $o['pill'] : array() ) )
		);
	}
	$kids[] = el_heading( $title_html, array( 'tag' => 'h1', 'size' => isset( $o['size'] ) ? $o['size'] : 52, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.05, 'max_width' => 820 ) );
	if ( $sub ) {
		$kids[] = el_text( $sub, array( 'align' => 'center', 'size' => 18, 'color_global' => 'mutedcol', 'max_width' => 680 ) );
	}
	return el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => isset( $o['pad_y'] ) ? $o['pad_y'] : 72, 'gap' => 18 ),
		array( el_col( $kids, array( 'width' => 100, 'gap_px' => 16, 'align' => 'center' ) ) )
	);
}

/** Centered section heading (h2 + optional subtitle). */
function sec_heading( $title_html, $sub = '', $eyebrow = '', $icon = 'fas fa-star' ) {
	$kids = array();
	if ( $eyebrow ) {
		$kids[] = el_container( array( 'content_width' => 'full', 'width' => el_size( 100, '%' ), 'flex_direction' => 'row', 'flex_justify_content' => 'center' ), array( el_pill( $eyebrow, $icon ) ) );
	}
	$kids[] = el_heading( $title_html, array( 'tag' => 'h2', 'size' => 36, 'weight' => 800, 'align' => 'center', 'color_global' => 'text', 'line_height' => 1.1 ) );
	if ( $sub ) {
		$kids[] = el_text( $sub, array( 'align' => 'center', 'size' => 17, 'color_global' => 'mutedcol', 'max_width' => 640 ) );
	}
	return el_col( $kids, array( 'width' => 100, 'gap_px' => 14, 'align' => 'center' ) );
}

/** A white card column (value/feature/info card). */
function card_col( array $children, $width = 31, array $o = array() ) {
	return el_col( $children, array( 'width' => $width, 'bg' => '#FFFFFF', 'pad' => isset( $o['pad'] ) ? $o['pad'] : 24, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => isset( $o['align'] ) ? $o['align'] : 'flex-start' ) );
}

/** Icon chip (round bg behind an icon). */
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

/** Stat block (big number + label). */
function stat_block( $value, $label ) {
	return el_col(
		array(
			el_heading( $value, array( 'tag' => 'div', 'size' => 36, 'weight' => 800, 'align' => 'center', 'color_global' => 'primary' ) ),
			el_text( $label, array( 'align' => 'center', 'size' => 14, 'color_global' => 'mutedcol' ) ),
		),
		array( 'width' => 22, 'gap_px' => 4, 'align' => 'center' )
	);
}

/** Pricing cards row (shared with Home pricing look). */
function pricing_cards_row( $packages ) {
	$cards = array();
	foreach ( $packages as $pkg ) {
		$popular = ! empty( $pkg['is_popular'] );
		$price   = '$' . rtrim( rtrim( number_format( (float) $pkg['price'], 2 ), '0' ), '.' );
		$kids    = array();
		if ( $popular ) {
			$kids[] = el_container(
				array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'background_background' => 'classic', 'background_color' => '#0B5CE0', 'border_radius' => el_box( 9999, 9999, 9999, 9999 ), 'padding' => el_box( 4, 14, 4, 14 ), '_flex_grow' => 0 ),
				array( el_text( 'Most Popular', array( 'size' => 12, 'color' => '#FFFFFF' ) ) )
			);
		}
		$kids[] = el_heading( $pkg['name'], array( 'tag' => 'h3', 'size' => 19, 'weight' => 700, 'color_global' => 'text' ) );
		if ( ! empty( $pkg['description'] ) ) {
			$kids[] = el_text( $pkg['description'], array( 'size' => 14, 'color_global' => 'mutedcol' ) );
		}
		$kids[] = el_container(
			array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_align_items' => 'flex-end', 'flex_gap' => array( 'unit' => 'px', 'size' => 4, 'column' => '4', 'row' => '4' ), 'padding' => el_box( 6, 0, 6, 0 ) ),
			array(
				el_heading( $price, array( 'tag' => 'div', 'size' => 34, 'weight' => 800, 'color_global' => 'text' ) ),
				el_text( '/' . ( $pkg['unit'] ?: 'package' ), array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
			)
		);
		if ( ! empty( $pkg['features'] ) ) {
			$kids[] = el_icon_list( $pkg['features'], array( 'icon' => 'fas fa-check', 'color_global' => 'secondary' ) );
		}
		$bo = array( 'url' => ! empty( $pkg['whatsapp_link'] ) ? $pkg['whatsapp_link'] : '#contact', 'external' => true, 'size' => 'md', 'align' => 'justify' );
		if ( $popular ) { $bo['bg_global'] = 'primary'; $bo['text_color'] = '#FFFFFF'; } else { $bo['variant'] = 'outline'; }
		$kids[] = el_button( $pkg['cta_label'] ?: 'Get Started', $bo );
		$cards[] = el_col( $kids, array( 'width' => 23, 'bg' => '#FFFFFF', 'pad' => 22, 'radius' => 18, 'border' => $popular ? '#0B5CE0' : '#CBD5E1', 'shadow' => true, 'gap_px' => 10, 'align' => 'flex-start' ) );
	}
	return el_row( $cards, 20, 'stretch', 'center' );
}

/* ===================================================== ABOUT (39) ====== */
function build_about() {
	$founding = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'founding_year', '2014' ) : '2014';
	$img = function_exists( 'buckleup_asset_url' ) ? ( buckleup_asset_url( 'owner_withcar.png' ) ?: buckleup_asset_url( 'hero_card_image.png' ) ) : '';
	$key_points = array( 'ICBC Certified', 'Modern Vehicles', 'Flexible Scheduling', 'Online Booking' );
	$values = array(
		array( 'fas fa-shield-alt', 'Student-Centered', 'Every lesson is tailored to your pace, goals, and learning style.' ),
		array( 'fas fa-shield-alt', 'Safety First', 'Modern vehicles with safety features and comprehensive insurance coverage.' ),
		array( 'fas fa-star', 'Modern Approach', 'Online booking, progress tracking, and flexible scheduling.' ),
		array( 'fas fa-star', 'Excellence', 'Structured lesson plans and honest feedback after every drive.' ),
	);

	$mission_left = el_col( array(
		el_heading( 'Our Mission', array( 'tag' => 'h2', 'size' => 32, 'weight' => 800, 'color_global' => 'text' ) ),
		el_text( 'BuckleUp Driving School was founded in ' . $founding . ' by Farhad Sanaeifar, a certified driving instructor dedicated to helping students become safe, confident, and responsible drivers in North Vancouver, Coquitlam, Port Coquitlam, and Port Moody (Tri-Cities).', array( 'size' => 16, 'color_global' => 'mutedcol' ) ),
		el_text( 'Each lesson is designed to build confidence behind the wheel while focusing on defensive driving techniques and real-world road safety. Students receive personalized instruction tailored to their experience level.', array( 'size' => 16, 'color_global' => 'mutedcol' ) ),
		el_text( 'Lessons are conducted in modern Toyota vehicles known for their reliability and safety features.', array( 'size' => 16, 'color_global' => 'mutedcol' ) ),
		el_icon_list( $key_points, array( 'icon' => 'fas fa-check', 'color_global' => 'secondary' ) ),
	), array( 'width' => 48, 'gap_px' => 14, 'align' => 'flex-start' ) );
	$mission_right = $img
		? el_col( array( el_image( $img, array( 'radius' => 24, 'alt' => 'BuckleUp instructor with a training vehicle', 'height' => 380, 'object_fit' => 'cover', 'width' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ) ), array( 'width' => 48, 'shadow' => true, 'gap_px' => 0 ) )
		: el_col( array( el_text( '', array() ) ), array( 'width' => 48 ) );

	$value_cards = array();
	foreach ( $values as $v ) {
		$value_cards[] = card_col( array(
			icon_chip( $v[0] ),
			el_heading( $v[1], array( 'tag' => 'h3', 'size' => 18, 'weight' => 700, 'align' => 'center', 'color_global' => 'text' ) ),
			el_text( $v[2], array( 'align' => 'center', 'size' => 14, 'color_global' => 'mutedcol' ) ),
		), 23, array( 'align' => 'center' ) );
	}

	return array(
		page_hero( 'Serving Vancouver Since ' . $founding, 'fas fa-shield-alt', 'Driving Excellence <span class="gradient-text">Since ' . $founding . '</span>', "We don't just teach you how to pass a test. We teach you how to become a confident, safe driver for life." ),
		el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 24 ), array( el_row( array( $mission_left, $mission_right ), 40, 'center', 'space-between' ) ) ),
		el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 32 ), array( sec_heading( 'Our Core <span class="gradient-text">Values</span>' ), el_row( $value_cards, 20, 'stretch', 'center' ) ) ),
	);
}

/* ================================================== SERVICES (41) ====== */
/**
 * The /services/ PILLAR for Hub 1 ("Driving Lessons & Packages").
 *
 * Per the content plan (Documents/pillar-cluster-driving-school.pdf §3, Hub 1) this
 * page is no longer just a pricing table: it introduces every lesson type and links
 * DOWN to a dedicated cluster page per licence class, so authority flows to the
 * money pages and Google can see the group.
 *
 * Deliberate SEO decisions:
 *   - The H1 owns the HUB head term ("Driving Lessons & Packages"), NOT city head
 *     terms. §6 of the plan names /locations/coquitlam/ the single owner of Coquitlam
 *     commercial intent; targeting cities here would re-create the cannibalisation
 *     the plan exists to fix. Cities appear as supporting context only.
 *   - Cluster copy comes from services-content.php so the pillar can never drift
 *     from the cluster pages it summarises.
 *   - Cluster links use descriptive anchors ("Class 7 driving lessons"), per §5.
 *   - Until a cluster page exists, each card falls back to the closest existing
 *     article so no card dead-ends and nothing 404s. The link upgrades itself the
 *     moment the cluster page is published — no edit needed here.
 *   - "Related guides" wires this hub to Hubs 2/3/4 (§5 "connect the hubs").
 *   - No new prices, pass rates or awards are introduced (plan adds none).
 */
function build_services() {
	$packages = function_exists( 'buckleup_get_packages' ) ? buckleup_get_packages() : array();
	$svc      = require __DIR__ . '/services-content.php';

	// Transitional targets: used only while a cluster page doesn't exist yet.
	$fallback = array(
		'class-7-driving-lessons'     => array( '/class-7-road-test-preparation-bc/', 'Read the Class 7 road test guide' ),
		'class-5-driving-lessons'     => array( '/how-to-pass-icbc-class-5-road-test-vancouver/', 'Read the Class 5 road test guide' ),
		'class-4-driving-lessons'     => array( '/icbc-class-4-knowledge-test/', 'Try the free Class 4 practice test' ),
		'highway-driving-lessons'     => array( '/contact/', 'Ask about highway lessons' ),
		'refresher-driving-lessons'   => array( '/driving-lessons-nervous-anxious-drivers-vancouver/', 'Read the nervous-driver guide' ),
	);

	/** One pillar card: icon, keyword-bearing H3, short intro, descriptive link down. */
	$card = function ( $slug, array $s ) use ( $fallback ) {
		$page = get_page_by_path( 'services/' . $slug );
		if ( $page && 'publish' === $page->post_status ) {
			$url   = get_permalink( $page->ID );
			$label = strip_tags( html_entity_decode( $s['card_title'] ) );
		} else {
			$url   = home_url( $fallback[ $slug ][0] );
			$label = $fallback[ $slug ][1];
		}
		// Titles wrap to different line counts, so let the description absorb the
		// slack and every CTA lands on the same baseline. Elementor ignores
		// _flex_grow unless _flex_size is 'custom'.
		$intro = el_text( $s['short'], array( 'size' => 15, 'color_global' => 'mutedcol' ) );
		$intro['settings']['_flex_size']   = 'custom';
		$intro['settings']['_flex_grow']   = 1;
		$intro['settings']['_flex_shrink'] = 1;

		return el_col(
			array(
				icon_chip( $s['icon'] ),
				el_heading( $s['card_title'], array( 'tag' => 'h3', 'size' => 20, 'weight' => 700, 'color_global' => 'text', 'line_height' => 1.25 ) ),
				$intro,
				el_button( $label, array( 'url' => $url, 'size' => 'sm', 'variant' => 'outline', 'icon' => 'fas fa-arrow-right' ) ),
			),
			array( 'width' => 31, 'bg' => '#FFFFFF', 'pad' => 24, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => 'flex-start' )
		);
	};

	$by_class   = array( 'class-7-driving-lessons', 'class-5-driving-lessons', 'class-4-driving-lessons' );
	$specialist = array( 'highway-driving-lessons', 'refresher-driving-lessons' );

	$tree = array(
		page_hero(
			'Driving Lessons &amp; Packages',
			'fas fa-graduation-cap',
			'Driving Lessons &amp; <span class="gradient-text">Packages</span>',
			'ICBC-certified lessons for every BC licence class — Class 7 for new drivers, Class 5 road test preparation and Class 4 commercial training — plus highway and refresher lessons across Coquitlam, Port Coquitlam, Port Moody and North Vancouver.'
		),
		// Pillar → cluster: the licence-class money pages.
		el_section(
			array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 30, 'id_css' => 'lessons' ),
			array(
				sec_heading(
					'Lessons by <span class="gradient-text">ICBC Licence Class</span>',
					'Every BC licence class is a different test with different habits to build. Pick the one you are working toward.'
				),
				el_row( array_map( function ( $k ) use ( $card, $svc ) { return $card( $k, $svc[ $k ] ); }, $by_class ), 20, 'stretch', 'center' ),
			)
		),
		// Pillar → cluster: the non-licence-class services.
		el_section(
			array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 30 ),
			array(
				sec_heading(
					'Specialist <span class="gradient-text">Lessons</span>',
					'Already licensed, or working on one specific thing? These lessons are built around a single goal.'
				),
				el_row( array_map( function ( $k ) use ( $card, $svc ) { return $card( $k, $svc[ $k ] ); }, $specialist ), 20, 'stretch', 'center' ),
			)
		),
		// Commercial core — kept on the pillar (§6: /services/ stays the price owner,
		// the cost blog stays informational; they interlink rather than compete).
		el_section(
			array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 28, 'id_css' => 'pricing' ),
			array(
				sec_heading( 'Lesson <span class="gradient-text">Packages</span>', 'Transparent pricing — choose the package that fits your needs.' ),
				pricing_cards_row( $packages ),
				el_text(
					'<div class="prose" style="text-align:center"><p>Not sure how much to budget? Our <a href="' . esc_url( home_url( '/driving-lessons-cost-vancouver-2026-price-guide/' ) ) . '">guide to what driving lessons cost in Vancouver</a> breaks down what affects the price, and <a href="' . esc_url( home_url( '/how-many-driving-lessons-do-you-need/' ) ) . '">how many lessons you actually need</a> explains why the answer differs from person to person.</p></div>',
					array( 'raw' => true, 'align' => 'center', 'size' => 15, 'color_global' => 'mutedcol' )
				),
			)
		),
	);

	/* The stats band (98% pass rate / 5000+ graduates / 15+ years / 50+ instructors)
	 * was removed at the client's request. Those figures were unverified and
	 * internally inconsistent — "98% pass rate" contradicted the "100% Pass
	 * Guarantee" badge in the hero, and "50+ instructors" did not match a school
	 * that presents a single named instructor. Unsupported numbers on a money page
	 * are a trust and compliance risk, so nothing replaces it. */

	/* ---- Cross-hub internal linking (plan §5 "then connect the hubs") -------
	 * Grouped by reader intent rather than by hub, so the list reads as help
	 * rather than as an SEO link dump. Every anchor is descriptive; `.prose` is a
	 * custom (non-Tailwind) class, so it survives the CSS purge and gives these
	 * links the brand colour + underline treatment.
	 */
	$link_col = function ( $title, array $links ) {
		$items = '';
		foreach ( $links as $href => $text ) {
			$items .= '<li><a href="' . esc_url( $href ) . '">' . $text . '</a></li>';
		}
		return el_col(
			array(
				el_heading( $title, array( 'tag' => 'h3', 'size' => 17, 'weight' => 700, 'color_global' => 'text' ) ),
				el_text( '<div class="prose"><ul>' . $items . '</ul></div>', array( 'raw' => true, 'size' => 15, 'color_global' => 'mutedcol' ) ),
			),
			array( 'width' => 31, 'gap_px' => 10, 'align' => 'flex-start' )
		);
	};

	$tree[] = el_section(
		array( 'bg_global' => 'bgcolor', 'pad_y' => 64, 'gap' => 30 ),
		array(
			sec_heading( 'Helpful <span class="gradient-text">Guides</span>', 'Free reading before you book — written for BC learners, not generic advice.' ),
			el_row(
				array(
					$link_col( 'Getting your licence', array(
						home_url( '/bc-graduated-licensing-program-explained-7l-7n-class-5/' ) => 'BC Graduated Licensing explained: 7L &rarr; 7N &rarr; Class 5',
						home_url( '/class-7l-learners-licence-bc-step-by-step/' )             => 'How to get your Class 7L learner&#8217;s licence',
						home_url( '/icbc-glp-changes-2026/' )                                  => 'ICBC&#8217;s 2026 licensing changes',
						home_url( '/exchange-foreign-drivers-licence-bc-new-to-canada/' )      => 'Exchanging a foreign driver&#8217;s licence in BC',
					) ),
					$link_col( 'Preparing for the road test', array(
						home_url( '/how-to-pass-icbc-class-5-road-test-vancouver/' ) => 'How to pass your ICBC Class 5 road test',
						home_url( '/class-7-road-test-preparation-bc/' )             => 'Class 7 road test preparation',
						home_url( '/mastering-parallel-parking-ultimate-guide/' )    => 'Mastering parallel parking',
						home_url( '/driving-school-car-icbc-road-test/' )            => 'Using our car for your ICBC road test',
					) ),
					$link_col( 'Lessons &amp; practice', array(
						home_url( '/icbc-class-4-knowledge-test/' )                          => 'Free ICBC Class 4 knowledge practice test',
						home_url( '/driving-lessons-nervous-anxious-drivers-vancouver/' )     => 'Lessons for nervous and anxious drivers',
						home_url( '/farsi-driving-lessons-vancouver-port-moody/' )            => 'Driving lessons in Farsi',
						home_url( '/locations/' )                                            => 'Where we teach across Metro Vancouver',
					) ),
				),
				24, 'stretch', 'center'
			),
		)
	);

	$tree[] = el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 64, 'gap' => 16 ), array(
		el_col( array(
			el_heading( 'Ready to start your driving journey?', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
			el_text( "Book a free consultation and we'll map the fastest route to your licence.", array( 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol', 'max_width' => 560 ) ),
			el_container( array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_gap' => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ), '_flex_grow' => 0, 'padding' => el_box( 8, 0, 0, 0 ) ), array(
				el_button( 'See Pricing', array( 'url' => '#pricing', 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ),
				el_button( 'Book Free Consultation', array( 'url' => home_url( '/contact' ), 'size' => 'lg', 'variant' => 'outline' ) ),
			) ),
		), array( 'width' => 100, 'gap_px' => 14, 'align' => 'center', 'bg' => '#FFFFFF', 'pad' => 32, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true ) ),
	) );
	return $tree;
}

/* =============================================== INSTRUCTORS (42) ====== */
function build_instructors() {
	$instructors = function_exists( 'buckleup_get_instructors' ) ? buckleup_get_instructors() : array();
	$wa = preg_replace( '/\D/', '', function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677' );
	// Was "10,000+ Students Taught / 94% Avg Pass Rate / 4.8 Avg Rating" — all
	// unverifiable, and the 4.8 contradicted the 4.98 shown everywhere else. These
	// three are checkable: the instructor count comes from the CPT, and the rating
	// and review count come from the real Google reviews the testimonials are seeded from.
	$stats = array(
		array( (string) max( 1, count( $instructors ) ), 'ICBC-Certified Instructors' ),
		array( '4.98★', 'Google rating' ),
		array( '200+', 'Google reviews' ),
	);
	$why = array(
		array( 'fas fa-shield-alt', 'ICBC Certified', 'All instructors are ICBC-approved with clean driving records.' ),
		array( 'fas fa-comment-dots', 'Multilingual', 'Lessons available in English, Farsi, French, and more.' ),
		array( 'fas fa-star', 'Patient & Supportive', 'Specializing in nervous and first-time drivers.' ),
		array( 'fas fa-check', 'Structured Lessons', 'A clear plan for each drive, with honest feedback afterwards.' ),
	);

	$stat_blocks = array();
	foreach ( $stats as $s ) { $stat_blocks[] = stat_block( $s[0], $s[1] ); }

	$cards = array();
	foreach ( $instructors as $ins ) {
		$first = explode( ' ', trim( $ins['name'] ) )[0];
		$wa_link = 'https://wa.me/' . $wa . '?text=' . rawurlencode( "Hi! I'd like to book a lesson with " . $ins['name'] . '.' );
		$kids = array();
		$head = array( el_col( array(
			el_heading( $ins['name'], array( 'tag' => 'h3', 'size' => 18, 'weight' => 700, 'color_global' => 'text' ) ),
			! empty( $ins['role'] ) ? el_text( $ins['role'], array( 'size' => 14, 'color_global' => 'mutedcol' ) ) : el_text( '', array() ),
		), array( 'gap_px' => 2, 'width' => 'grow', 'align' => 'flex-start' ) ) );
		if ( ! empty( $ins['rating'] ) ) {
			$head[] = el_col( array( el_stars( 5, array( 'color' => '#F59E0B' ) ), el_text( (string) $ins['rating'], array( 'size' => 13, 'color_global' => 'mutedcol' ) ) ), array( 'width' => 'auto', 'gap_px' => 2, 'align' => 'flex-end' ) );
		}
		$kids[] = el_row( $head, 12, 'center', 'space-between' );
		if ( ! empty( $ins['bio'] ) ) {
			$kids[] = el_text( $ins['bio'], array( 'size' => 14, 'color_global' => 'mutedcol' ) );
		}
		if ( ! empty( $ins['languages'] ) ) {
			$kids[] = el_text( 'Languages: ' . implode( ', ', (array) $ins['languages'] ), array( 'size' => 13, 'color_global' => 'fgcolor' ) );
		}
		$kids[] = el_button( 'Book with ' . $first, array( 'url' => $wa_link, 'external' => true, 'size' => 'md', 'align' => 'justify', 'variant' => 'outline', 'icon' => 'fas fa-comment-dots' ) );
		$cards[] = card_col( $kids, 31 );
	}

	$why_cards = array();
	foreach ( $why as $w ) {
		$why_cards[] = card_col( array(
			icon_chip( $w[0] ),
			el_heading( $w[1], array( 'tag' => 'h3', 'size' => 17, 'weight' => 700, 'align' => 'center', 'color_global' => 'text' ) ),
			el_text( $w[2], array( 'align' => 'center', 'size' => 14, 'color_global' => 'mutedcol' ) ),
		), 23, array( 'align' => 'center' ) );
	}

	$tree = array(
		// page_hero emits the H1 (was a sec_heading h2 → QA B1: page had no <h1>).
		page_hero( 'Our Team', 'fas fa-shield-alt', 'Meet Your <span class="gradient-text">Instructors</span>', 'ICBC-certified, patient, and fluent in the languages our students speak.', array( 'pad_y' => 56 ) ),
		el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 24, 'gap' => 16 ), array( el_row( $stat_blocks, 24, 'stretch', 'center' ) ) ),
	);
	if ( $cards ) {
		$tree[] = el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 24, 'gap' => 24 ), array( el_row( $cards, 24, 'stretch', 'center' ) ) );
	}
	$tree[] = el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 56, 'gap' => 28 ), array( sec_heading( 'Why Choose Our Instructors' ), el_row( $why_cards, 20, 'stretch', 'center' ) ) );
	return $tree;
}

/* ================================================== CONTACT (40) ====== */
function build_contact() {
	$get = function ( $k, $d = '' ) { return function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( $k, $d ) : $d; };
	$phone = $get( 'phone', '(604) 441-3677' ); $phone_e = $get( 'phone_e164', '+16044413677' );
	$email = $get( 'email', 'info@buckleupdriving.ca' );
	$methods = array(
		array( 'fas fa-phone', 'Phone', $phone, 'Mon–Sun, 9am–6pm PST' ),
		array( 'fas fa-envelope', 'Email', $email, 'We reply within 24 hours' ),
		array( 'fas fa-map-marker-alt', 'Office', '136 Maple Dr', 'Port Moody, BC V3H 0A8' ),
		array( 'fas fa-clock', 'Hours', 'Mon – Sun', '9:00 AM – 6:00 PM' ),
	);
	$info_cards = array();
	foreach ( $methods as $m ) {
		$info_cards[] = card_col( array(
			icon_chip( $m[0] ),
			el_heading( $m[1], array( 'tag' => 'div', 'size' => 16, 'weight' => 700, 'align' => 'center', 'color_global' => 'text' ) ),
			el_text( $m[2], array( 'align' => 'center', 'size' => 14, 'color_global' => 'fgcolor' ) ),
			el_text( $m[3], array( 'align' => 'center', 'size' => 12, 'color_global' => 'mutedcol' ) ),
		), 23, array( 'align' => 'center' ) );
	}

	$form_col = el_col( array(
		el_heading( 'Send us a Message', array( 'tag' => 'h2', 'size' => 24, 'weight' => 800, 'color_global' => 'text' ) ),
		el_text( 'Have a quick question? Fill out the form below and we&rsquo;ll get back to you.', array( 'raw' => true, 'size' => 14, 'color_global' => 'mutedcol' ) ),
		el_shortcode( '[buckleup_contact_form]' ),
	), array( 'width' => 50, 'bg' => '#FFFFFF', 'pad' => 28, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => 'flex-start' ) );

	$map_q = '136+Maple+Dr,+Port+Moody,+BC+V3H+0A8,+Canada';
	$right_col = el_col( array(
		el_widget( 'google_maps', array( 'address' => '136 Maple Dr, Port Moody, BC V3H 0A8', 'zoom' => array( 'unit' => 'px', 'size' => 15 ), 'height' => el_size( 280 ) ) ),
		card_col( array(
			el_heading( 'Fast Response Time', array( 'tag' => 'h3', 'size' => 17, 'weight' => 700, 'color_global' => 'text' ) ),
			el_text( 'We typically respond to all inquiries within 2–4 hours during business hours.', array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
		), 100 ),
		el_button( 'Get Directions', array( 'url' => 'https://maps.google.com/maps?q=' . $map_q, 'external' => true, 'size' => 'md', 'variant' => 'outline', 'icon' => 'fas fa-map-marker-alt' ) ),
	), array( 'width' => 48, 'gap_px' => 16, 'align' => 'flex-start' ) );

	return array(
		page_hero( 'Get in Touch', 'fas fa-comment-dots', "We'd Love to <span class=\"gradient-text\">Hear From You</span>", 'Have questions about our driving lessons? Ready to start your journey?' ),
		el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 24, 'gap' => 24 ), array( el_row( $info_cards, 20, 'stretch', 'center' ) ) ),
		el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 48, 'gap' => 24 ), array( el_row( array( $form_col, $right_col ), 28, 'flex-start', 'center' ) ) ),
	);
}

/* ================================================= RESOURCES (44) ====== */
function build_resources() {
	$categories = array(
		array( 'Knowledge Test Preparation', array( 'ICBC Practice Questions · PDF · 2.5 MB', 'Road Signs Guide · PDF · 4.1 MB', 'Rules of the Road Summary · PDF · 1.8 MB' ) ),
		array( 'Video Tutorials', array( 'Parallel Parking Mastery · 15 min', 'Lane Changes & Merging · 12 min', 'Intersection Navigation · 18 min' ) ),
		array( 'Checklists & Guides', array( 'Road Test Day Checklist · PDF · 500 KB', 'Pre-Trip Vehicle Inspection · PDF · 750 KB', 'Common Mistakes to Avoid · PDF · 1.2 MB' ) ),
	);
	$icbc = get_page_by_path( 'icbc-road-test-failures', OBJECT, array( 'page', 'post' ) );
	$icbc_href = $icbc ? (string) get_permalink( $icbc ) : home_url( '/resources/icbc-road-test-failures' );
	$articles = array(
		array( 'Winter Driving in BC: Essential Tips', 'Safety', '5 min read', home_url( '/blog' ) ),
		array( 'How to Conquer Parallel Parking', 'Skills', '4 min read', $icbc_href ),
		array( 'Understanding the BC Graduated Licensing Program', 'Licensing', '6 min read', home_url( '/blog' ) ),
		array( 'Defensive Driving: Key Principles', 'Safety', '7 min read', home_url( '/blog' ) ),
	);

	$cat_cards = array();
	foreach ( $categories as $cat ) {
		$cat_cards[] = card_col( array(
			el_heading( $cat[0], array( 'tag' => 'h3', 'size' => 17, 'weight' => 700, 'color_global' => 'text' ) ),
			el_icon_list( $cat[1], array( 'icon' => 'fas fa-arrow-right', 'color_global' => 'primary' ) ),
		), 31 );
	}
	$art_cards = array();
	foreach ( $articles as $a ) {
		$art_cards[] = el_col( array(
			el_container( array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_gap' => array( 'unit' => 'px', 'size' => 8, 'column' => '8', 'row' => '8' ), '_flex_grow' => 0 ), array( el_pill( $a[1], 'fas fa-tag' ) ) ),
			el_heading( $a[0], array( 'tag' => 'h3', 'size' => 19, 'weight' => 700, 'color_global' => 'text' ) ),
			el_button( 'Read more', array( 'url' => $a[3], 'size' => 'sm', 'variant' => 'outline', 'icon' => 'fas fa-arrow-right' ) ),
		), array( 'width' => 48, 'bg' => '#FFFFFF', 'pad' => 24, 'radius' => 18, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 12, 'align' => 'flex-start' ) );
	}

	return array(
		page_hero( '', '', 'Student <span class="gradient-text">Resources</span>', 'Free study materials, guides, and tutorials to help you become a confident, safe driver.' ),
		el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 48, 'gap' => 28 ), array( sec_heading( 'Downloadable Materials' ), el_row( $cat_cards, 20, 'stretch', 'center' ) ) ),
		el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 48, 'gap' => 28 ), array( sec_heading( 'Latest Articles &amp; Guides' ), el_row( $art_cards, 20, 'stretch', 'center' ) ) ),
		el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 48, 'gap' => 16 ), array(
			el_col( array(
				icon_chip( 'fas fa-check' ),
				el_heading( 'Practice Quizzes Coming Soon', array( 'tag' => 'h3', 'size' => 24, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
				el_text( 'Interactive quizzes to test your knowledge of BC driving rules, road signs, and safe driving practices.', array( 'align' => 'center', 'size' => 15, 'color_global' => 'mutedcol', 'max_width' => 560 ) ),
				el_button( 'Get Notified', array( 'url' => home_url( '/contact' ), 'size' => 'md', 'bg_global' => 'primary' ) ),
			), array( 'width' => 100, 'gap_px' => 12, 'align' => 'center', 'bg' => '#FFFFFF', 'pad' => 32, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true ) ),
		) ),
	);
}

/* ====================================================== ICBC (45) ====== */
function build_icbc() {
	$causes = array(
		array( 'Rolling Through Stop Signs', "A complete stop means absolute zero forward momentum behind the line. A 'Hollywood roll' is an automatic failure.", "We drill the 'Stop, Think, Scan' method until a complete, full-second stop becomes pure muscle memory." ),
		array( 'Failing to Shoulder Check', 'Missing a shoulder check before merging, turning, or pulling out is the single most common reason for demerits.', 'Our instructors build the mirror-signal-shoulder check sequence into every maneuver from your first lesson.' ),
		array( 'Speed in School Zones', 'Hitting 35km/h in a 30km/h school or playground zone during restricted hours is an instant fail.', 'We practice on actual local test routes (Port Moody, Burnaby) so you know exactly where the traps are.' ),
		array( 'Poor Gap Selection When Merging', "Hesitating when it's safe to turn, or pulling into a gap that's too small, shows a lack of spatial awareness.", 'We use structured exposure on busier roads to safely build your confidence assessing speed and distance.' ),
		array( 'Improper Left Turns on Yellow', "Getting 'stuck' in the intersection, or turning when oncoming traffic hasn't fully stopped.", "We teach precise positioning and the 'point of no return' framework so you never have to guess." ),
	);
	$cards = array();
	foreach ( $causes as $i => $c ) {
		$cards[] = el_col( array(
			el_row( array(
				el_col( array( el_heading( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ), array( 'tag' => 'div', 'size' => 30, 'weight' => 800, 'color' => 'rgba(11,92,224,0.35)' ) ) ), array( 'width' => 'auto' ) ),
				el_col( array( el_heading( $c[0], array( 'tag' => 'h2', 'size' => 22, 'weight' => 700, 'color_global' => 'text' ) ) ), array( 'width' => 'grow', 'align' => 'flex-start' ) ),
			), 14, 'center', 'flex-start' ),
			el_row( array(
				el_col( array(
					el_text( '<strong style="color:#ef4444;">✕ The Mistake</strong>', array( 'raw' => true, 'size' => 14, 'color_global' => 'text' ) ),
					el_text( $c[1], array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
				), array( 'width' => 48, 'gap_px' => 6, 'align' => 'flex-start' ) ),
				el_col( array(
					el_text( '<strong style="color:#0B5CE0;">✓ The BuckleUp Fix</strong>', array( 'raw' => true, 'size' => 14, 'color_global' => 'text' ) ),
					el_text( $c[2], array( 'size' => 14, 'color_global' => 'mutedcol' ) ),
				), array( 'width' => 48, 'gap_px' => 6, 'align' => 'flex-start' ) ),
			), 24, 'flex-start', 'space-between' ),
		), array( 'width' => 100, 'pad' => 28, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true, 'gap_px' => 16, 'align' => 'flex-start', 'bg' => '#FFFFFF' ) );
	}
	$cards_section = el_section( array( 'bg_global' => 'bgcolor', 'pad_y' => 32, 'gap' => 24, 'content_width' => 920 ), array( el_col( $cards, array( 'width' => 100, 'gap_px' => 24 ) ) ) );

	$cta = el_section( array( 'bg' => '#FFFFFF', 'pad_y' => 56, 'gap' => 16, 'content_width' => 920 ), array(
		el_col( array(
			el_heading( 'Ready to Pass on Your First Try?', array( 'tag' => 'h2', 'size' => 30, 'weight' => 800, 'align' => 'center', 'color_global' => 'text' ) ),
			el_text( "Don't leave your licence to chance. Work through these with an ICBC-certified instructor before test day.", array( 'align' => 'center', 'size' => 16, 'color_global' => 'mutedcol', 'max_width' => 600 ) ),
			el_button( 'Book Road Test Prep', array( 'url' => home_url( '/#pricing' ), 'size' => 'lg', 'icon' => 'fas fa-chevron-right', 'bg_global' => 'primary' ) ),
		), array( 'width' => 100, 'gap_px' => 14, 'align' => 'center', 'bg' => 'rgba(11,92,224,0.06)', 'pad' => 36, 'radius' => 24, 'border' => 'rgba(11,92,224,0.2)' ) ),
	) );

	return array(
		page_hero( 'ICBC Road Test Guide', 'fas fa-shield-alt', 'Top 5 Reasons Students Fail the <span class="gradient-text">ICBC Road Test</span>', 'ICBC examiners look for the same handful of habits every time. Here is why most test-takers fail, and how to make sure you don\'t.', array( 'pill' => array( 'icon_color_global' => 'primary' ), 'size' => 44 ) ),
		$cards_section,
		$cta,
	);
}

/* ----------------------------------------------------------- RUN ------ */
// Keyed by SLUG, not id — see el_post_id() in lib.php for why ids aren't stable.
$pages = array(
	'about'                   => 'build_about',
	'services'                => 'build_services',
	'instructors'             => 'build_instructors',
	'contact'                 => 'build_contact',
	'resources'               => 'build_resources',
	'icbc-road-test-failures' => 'build_icbc',
);
// Optional slug filter: `wp eval-file build-pages.php services` rebuilds ONLY that
// page. Without it every page is rebuilt, which silently discards any edit made in
// the Elementor editor on the pages you weren't trying to touch — so prefer the
// filter when you only mean to change one.
$only = array();
if ( isset( $args ) && is_array( $args ) ) {
	$only = array_filter( array_map( 'sanitize_title', $args ) );
}

$built = array();
foreach ( $pages as $slug => $builder ) {
	if ( $only && ! in_array( $slug, $only, true ) ) {
		continue;
	}
	$id = el_post_id( $slug );
	if ( ! $id ) {
		echo "SKIP $slug: no page with that slug.\n";
		continue;
	}
	el_save_page( $id, $builder() );
	$built[] = "$slug($id)";
}
if ( $only ) {
	$missing = array_diff( $only, array_keys( $pages ) );
	foreach ( $missing as $m ) { echo "WARNING: '$m' is not a page this script builds.\n"; }
}
echo 'Built ' . count( $built ) . ' page(s): ' . implode( ', ', $built ) . ".\n";
