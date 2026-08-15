<?php
/**
 * Build the site Header (template 163) and Footer (template 164) as native
 * Elementor, reproducing patterns/site-header.php + site-footer.php in a clean
 * native form: header = logo + Primary nav (ElementsKit nav widget, menu id 51)
 * + "Book a Lesson" CTA; footer = 5-column grid (brand+socials, quick links,
 * service areas, recent blogs, contact) + copyright.
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-chrome.php
 */
require __DIR__ . '/lib.php';

// Resolved by slug, created on first run — ids are not stable across installs
// (see el_post_id()/el_library_id() in lib.php). parts/footer.html embeds the footer
// by the same slug via [buckleup_elementor slug="site-footer"].
$HEADER_ID = el_library_id( 'site-header', 'Site Header' );
$FOOTER_ID = el_library_id( 'site-footer', 'Site Footer' );
// The header template is retained but unused (the real theme header renders live),
// so a missing menu is not fatal — the nav widget just renders empty.
$menu    = wp_get_nav_menu_object( 'Primary' );
$MENU_ID = $menu ? (int) $menu->term_id : 0;

$get = static function ( $k, $d = '' ) {
	return function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( $k, $d ) : $d;
};
$logo      = ( $lid = get_theme_mod( 'custom_logo' ) ) ? wp_get_attachment_image_url( $lid, 'full' ) : '';
$wa_raw    = preg_replace( '/\D/', '', (string) $get( 'whatsapp', '16044413677' ) );
$wa_link   = 'https://wa.me/' . $wa_raw . '?text=' . rawurlencode( "Hi, I'm interested in driving lessons." );
$locations = function_exists( 'buckleup_location_items' ) ? buckleup_location_items() : array();
$recent    = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish', 'no_found_rows' => true ) );

/** Save an Elementor library template (no page template / title handling). */
function el_save_template( $id, array $elements ) {
	if ( ! $id || ! get_post( $id ) ) {
		echo "SKIP: no library template with id " . var_export( $id, true ) . ".\n";
		return;
	}
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array_values( $elements ) ) ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'section' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.1.3' );
	if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		\Elementor\Core\Files\CSS\Post::create( $id )->update();
	}
}

/* --------------------------------------------------------------- HEADER -- */
function build_header( $logo, $wa_link, $menu_id ) {
	$logo_w = $logo ? el_image( $logo, array( 'width' => array( 'unit' => 'px', 'size' => 150, 'sizes' => array() ), 'align' => 'left', 'alt' => 'BuckleUp Driving School' ) )
		: el_heading( 'BuckleUp', array( 'tag' => 'div', 'size' => 24, 'weight' => 800, 'color_global' => 'primary' ) );

	$nav = el_widget( 'ekit-nav-menu', array(
		'elementskit_nav_menu'             => (string) $menu_id,
		'elementskit_main_menu_position'   => 'elementskit-menu-justify-end',
		'elementskit_dropdown_in_min'      => 'tablet',
		'elementskit_nav_menu_layout'      => 'horizontal',
		'elementskit_menu_notext_typography_typography' => 'custom',
		'elementskit_menu_notext_typography_font_family' => 'Geist',
		'elementskit_menu_notext_typography_font_weight' => '500',
	) );

	$signin = el_button( 'Sign In', array( 'url' => home_url( '/login/' ), 'size' => 'sm', 'variant' => 'outline' ) );
	$cta = el_button( 'Book a Lesson', array( 'url' => $wa_link, 'external' => true, 'size' => 'sm', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) );
	$right = el_container(
		array( 'content_width' => 'full', 'css_classes' => 'bu-hug', 'flex_direction' => 'row', 'flex_align_items' => 'center', 'flex_gap' => array( 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10' ), '_flex_grow' => 0 ),
		array( $signin, $cta )
	);

	$row = el_container(
		array(
			'content_width'        => 'boxed',
			'boxed_width'          => el_size( 1200 ),
			'width'                => el_size( 100, '%' ),
			'flex_direction'       => 'row',
			'flex_align_items'     => 'center',
			'flex_justify_content' => 'space-between',
			'flex_gap'             => array( 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '12' ),
			'padding'              => el_box( 0, 0, 0, 0 ),
		),
		array(
			el_col( array( $logo_w ), array( 'width' => 'auto', 'gap' => 0 ) ),
			el_col( array( $nav ), array( 'width' => 'auto', 'gap' => 0 ) ),
			el_col( array( $right ), array( 'width' => 'auto', 'gap' => 0 ) ),
		)
	);

	$outer = el_container(
		array(
			'content_width'         => 'full',
			'flex_direction'        => 'row',
			'flex_justify_content'  => 'center',
			'flex_align_items'      => 'center',
			'padding'               => el_box( 14, 16, 14, 16 ),
			'background_background'  => 'classic',
			'background_color'      => '#FFFFFF',
			'border_border'         => 'solid',
			'border_width'          => el_box( 0, 0, 1, 0 ),
			'border_color'          => '#CBD5E1',
		),
		array( $row )
	);
	return $outer;
}

/* --------------------------------------------------------------- FOOTER -- */
function footer_links_html( array $links ) {
	$lis = '';
	foreach ( $links as $l ) {
		$lis .= '<li style="margin-bottom:10px;"><a href="' . esc_url( $l['href'] ) . '" style="color:#64748B;text-decoration:none;">' . esc_html( $l['label'] ) . '</a></li>';
	}
	return '<ul style="list-style:none;padding:0;margin:0;">' . $lis . '</ul>';
}

function footer_column( $title, $body_html, $width = 18 ) {
	return el_col(
		array(
			el_heading( $title, array( 'tag' => 'h3', 'size' => 16, 'weight' => 700, 'color_global' => 'text' ) ),
			el_text( $body_html, array( 'raw' => true, 'size' => 14, 'color_global' => 'mutedcol' ) ),
		),
		array( 'gap' => 16, 'align' => 'flex-start', 'width' => $width )
	);
}

function build_footer( $logo, $get, $locations, $recent, $wa_link ) {
	// Brand column
	$brand_kids = array();
	if ( $logo ) {
		$brand_kids[] = el_image( $logo, array( 'width' => array( 'unit' => 'px', 'size' => 150, 'sizes' => array() ), 'align' => 'left' ) );
	}
	$brand_kids[] = el_text( 'Empowering the next generation of safe, confident drivers in Vancouver. Modern fleet, expert instructors, and a commitment to excellence.', array( 'size' => 14, 'color_global' => 'mutedcol' ) );
	$brand_kids[] = el_container(
		array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_gap' => array( 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10' ) ),
		array(
			el_icon( 'fab fa-instagram', array( 'size' => 20, 'color_global' => 'primary' ) ),
			el_icon( 'fab fa-facebook', array( 'size' => 20, 'color_global' => 'primary' ) ),
		)
	);
	$brand = el_col( $brand_kids, array( 'gap' => 16, 'align' => 'flex-start', 'width' => 22 ) );

	// Quick Links
	// Graduates + FAQ are home-page ANCHORS, not pages, so they were dropped from the
	// primary nav. The footer is where they belong — it keeps both sections reachable
	// by a link (otherwise nothing on the site would point at #graduates at all).
	$quick = footer_column( 'Quick Links', footer_links_html( array(
		array( 'label' => 'Services & Pricing', 'href' => home_url( '/services/' ) ),
		array( 'label' => 'About Us', 'href' => home_url( '/about/' ) ),
		array( 'label' => 'Book a Lesson', 'href' => home_url( '/#pricing' ) ),
		array( 'label' => 'Graduates', 'href' => home_url( '/#graduates' ) ),
		array( 'label' => 'FAQ', 'href' => home_url( '/#faq' ) ),
	) ), 14 );

	// Service Areas
	$area_links = array();
	foreach ( $locations as $loc ) {
		$area_links[] = array( 'label' => 'Driving Lessons in ' . $loc['name'], 'href' => $loc['href'] );
	}
	$areas = footer_column( 'Service Areas', footer_links_html( $area_links ), 19 );

	// Recent Blogs
	$blog_links = array();
	foreach ( $recent as $p ) {
		$blog_links[] = array( 'label' => get_the_title( $p ), 'href' => get_permalink( $p ) );
	}
	if ( ! $blog_links ) {
		$blog_links[] = array( 'label' => 'Coming soon.', 'href' => home_url( '/blog/' ) );
	}
	$blogs = footer_column( 'Recent Blogs', footer_links_html( $blog_links ), 19 );

	// Contact
	$street   = $get( 'street_address', '136 Maple Dr' );
	$locality = $get( 'address_locality', 'Port Moody' );
	$region   = $get( 'address_region', 'BC' );
	$postal   = $get( 'postal_code', 'V3H 0A8' );
	$phone    = $get( 'phone', '(604) 441-3677' );
	$phone_e  = $get( 'phone_e164', '+16044413677' );
	$email    = $get( 'email', 'info@buckleupdriving.ca' );
	$hours    = $get( 'hours_display', 'Mon–Sun 9am–9pm' );
	$contact_html = '<ul style="list-style:none;padding:0;margin:0;line-height:1.6;">'
		. '<li style="margin-bottom:10px;">' . esc_html( $street ) . '<br>' . esc_html( "$locality, $region $postal, Canada" ) . '</li>'
		. '<li style="margin-bottom:10px;"><a href="tel:' . esc_attr( $phone_e ) . '" style="color:#64748B;text-decoration:none;">' . esc_html( $phone ) . '</a></li>'
		. '<li style="margin-bottom:10px;"><a href="mailto:' . esc_attr( $email ) . '" style="color:#64748B;text-decoration:none;">' . esc_html( $email ) . '</a></li>'
		. '<li>' . esc_html( $hours ) . '</li></ul>';
	$contact = footer_column( 'Contact Us', $contact_html, 16 );

	$grid = el_row( array( $brand, $quick, $areas, $blogs, $contact ), 16, 'flex-start', 'space-between' );

	// CTA band
	$cta_band = el_col(
		array(
			el_row(
				array(
					el_col(
						array(
							el_heading( 'Ready to Start Driving?', array( 'tag' => 'h3', 'size' => 28, 'weight' => 800, 'color_global' => 'text' ) ),
							el_text( 'Book your first lesson today and get 20% off!', array( 'size' => 16, 'color_global' => 'mutedcol' ) ),
						),
						array( 'gap' => 6, 'width' => 'grow', 'align' => 'flex-start' )
					),
					el_col( array( el_button( 'Book Now', array( 'url' => $wa_link, 'external' => true, 'size' => 'lg', 'icon' => 'fas fa-arrow-right', 'bg_global' => 'primary' ) ) ), array( 'width' => 'auto' ) ),
				),
				24, 'center', 'space-between'
			),
		),
		array( 'bg' => '#FFFFFF', 'pad' => 32, 'radius' => 24, 'border' => '#CBD5E1', 'shadow' => true, 'width' => 100 )
	);

	$copyright = el_text( '© ' . gmdate( 'Y' ) . ' BuckleUp Driving School Ltd. All rights reserved.', array( 'align' => 'center', 'size' => 13, 'color_global' => 'mutedcol' ) );

	// The "Ready to Start Driving?" CTA band ($cta_band) is intentionally NOT included
	// in the footer — it would show site-wide. The original shows it only on the front
	// page, so it lives in the Home Elementor content instead (build-home.php build_cta()).
	unset( $cta_band );
	return el_section(
		array( 'bg' => '#FFFFFF', 'pad_y' => 56, 'gap' => 40, 'content_width' => 1200 ),
		array( $grid, $copyright )
	);
}

el_save_template( $HEADER_ID, array( build_header( $logo, $wa_link, $MENU_ID ) ) );
el_save_template( $FOOTER_ID, array( build_footer( $logo, $get, $locations, $recent, $wa_link ) ) );
echo "Header ($HEADER_ID) + Footer ($FOOTER_ID) authored. Logo=" . ( $logo ? 'yes' : 'no' ) . ", menu=" . ( $MENU_ID ?: 'none' ) . ", locations=" . count( $locations ) . ", recent=" . count( $recent ) . "\n";
