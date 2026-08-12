<?php
/**
 * BuckleUp Elementor authoring library.
 *
 * Reusable PHP helpers that emit NATIVE Elementor element trees (Containers +
 * native widgets — NO inner-sections, NO HTML widgets, NO atomic/V4 elements)
 * so we can convert the hand-coded Tailwind design into a fully admin-editable
 * Elementor build programmatically.
 *
 * Design contract:
 *   - Layout is always `elType: container` (flexbox), nested as needed.
 *   - Brand colors/fonts come from the global Kit (post 161) where it matters,
 *     via `__globals__` references; explicit hex is used for one-offs.
 *   - Kit global color ids:  primary, secondary(green), text, accent  (system)
 *     custom: bgcolor #F8FAFC, cardcol #FFFFFF, mutedcol #64748B,
 *             bordercol #CBD5E1, greencol #10B77F, fgcolor #0F1729
 *
 * Usage: a per-page builder requires this file, composes an array of top-level
 * containers, then calls el_save_page( $post_id, $elements ).
 *
 *   require __DIR__ . '/lib.php';
 *   el_save_page( 38, array( el_section(...) , ... ) );
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Allow `wp eval-file` (ABSPATH is defined by WP) but bail if run raw.
	return;
}

/* --------------------------------------------------------------------------
 * Primitives
 * ----------------------------------------------------------------------- */

/** Deterministic unique 7-char element id (stable across re-runs → clean diffs). */
function el_id() {
	static $n = 0;
	$n++;
	return substr( md5( 'buckleup-elementor-' . $n ), 0, 7 );
}

/** Box value helper for padding/margin/border-radius: el_box(40,0,40,0). */
function el_box( $top, $right, $bottom, $left, $unit = 'px' ) {
	return array(
		'unit'     => $unit,
		'top'      => (string) $top,
		'right'    => (string) $right,
		'bottom'   => (string) $bottom,
		'left'     => (string) $left,
		'isLinked' => false,
	);
}

/** Single-axis size: el_size(1200) or el_size(2,'rem'). */
function el_size( $size, $unit = 'px' ) {
	return array( 'unit' => $unit, 'size' => $size, 'sizes' => array() );
}

/** Link value. */
function el_link( $url, $external = false ) {
	return array(
		'url'         => $url,
		'is_external' => $external ? 'on' : '',
		'nofollow'    => '',
		'custom_attributes' => '',
	);
}

/** Font Awesome icon value. el_fa('fas fa-check') / el_fa('far fa-star'). */
function el_fa( $value ) {
	$lib = ( strpos( $value, 'fab ' ) === 0 ) ? 'fa-brands'
		: ( ( strpos( $value, 'far ' ) === 0 ) ? 'fa-regular' : 'fa-solid' );
	return array( 'value' => $value, 'library' => $lib );
}

/** Merge a `__globals__` map into a settings array (color/typography globals). */
function el_with_globals( array $settings, array $globals = array() ) {
	if ( $globals ) {
		$settings['__globals__'] = $globals;
	}
	return $settings;
}

/** Global color reference string for `__globals__`. */
function el_gcolor( $id ) {
	return 'globals/colors?id=' . $id;
}

/* --------------------------------------------------------------------------
 * Containers & widgets
 * ----------------------------------------------------------------------- */

/**
 * Generic flex container.
 *
 * @param array $settings Elementor container settings (merged over sane defaults).
 * @param array $children Child element arrays.
 * @param bool  $inner    isInner flag (we still avoid the legacy "inner section").
 */
function el_container( array $settings = array(), array $children = array(), $inner = false ) {
	return array(
		'id'       => el_id(),
		'elType'   => 'container',
		'isInner'  => $inner,
		'settings' => $settings,
		'elements' => array_values( $children ),
	);
}

/**
 * Top-level full-width "section" container with a boxed inner content container.
 * Returns the OUTER container; build your content as $children (placed in inner).
 *
 * @param array $opts  bg=>hex|null, bg_global=>colorId, gradient=>[from,to,angle],
 *                     pad_y=>int(px) vertical padding, content_width=>int(px),
 *                     gap=>int(px), align=>flex-start|center|flex-end,
 *                     min_height=>int(px), id_css=>string (CSS id / anchor).
 */
function el_section( array $opts, array $children ) {
	$pad_y   = isset( $opts['pad_y'] ) ? $opts['pad_y'] : 80;
	$cwidth  = isset( $opts['content_width'] ) ? $opts['content_width'] : 1200;
	$gap     = isset( $opts['gap'] ) ? $opts['gap'] : 24;
	$align   = isset( $opts['align'] ) ? $opts['align'] : 'center';

	$outer = array(
		'content_width'        => 'full',
		'flex_direction'       => 'column',
		'flex_align_items'     => 'center',
		'padding'              => el_box( $pad_y, 16, $pad_y, 16 ),
	);
	if ( ! empty( $opts['id_css'] ) ) {
		$outer['_element_id'] = $opts['id_css'];
	}
	if ( ! empty( $opts['min_height'] ) ) {
		$outer['min_height'] = el_size( $opts['min_height'] );
		$outer['flex_justify_content'] = 'center';
	}
	// Background
	if ( ! empty( $opts['gradient'] ) ) {
		list( $from, $to, $angle ) = array_pad( $opts['gradient'], 3, null );
		$outer['background_background']       = 'gradient';
		$outer['background_color']            = $from;
		$outer['background_color_b']          = $to;
		$outer['background_gradient_type']    = 'linear';
		$outer['background_gradient_angle']   = el_size( $angle !== null ? $angle : 180, 'deg' );
	} elseif ( ! empty( $opts['bg'] ) ) {
		$outer['background_background'] = 'classic';
		$outer['background_color']      = $opts['bg'];
	} elseif ( ! empty( $opts['bg_global'] ) ) {
		$outer['background_background'] = 'classic';
		$outer = el_with_globals( $outer, array( 'background_color' => el_gcolor( $opts['bg_global'] ) ) );
	}
	// Background IMAGE (e.g. a recognizable landmark hero), cover-cropped + centered,
	// with an optional dark overlay so foreground text stays legible. Wins over a
	// solid/gradient bg if both are supplied (the landmark photo is the backdrop).
	if ( ! empty( $opts['bg_image'] ) ) {
		$img = $opts['bg_image'];
		$outer['background_background'] = 'classic';
		$outer['background_image']      = array(
			'url' => is_array( $img ) ? ( isset( $img['url'] ) ? $img['url'] : '' ) : $img,
			'id'  => is_array( $img ) && isset( $img['id'] ) ? $img['id'] : '',
		);
		$outer['background_position'] = 'center center';
		$outer['background_repeat']   = 'no-repeat';
		$outer['background_size']     = 'cover';
		// Optional overlay: ['color' => 'rgba(...)|#hex', 'opacity' => 0..1].
		if ( ! empty( $opts['overlay'] ) ) {
			$ov = $opts['overlay'];
			$outer['background_overlay_background'] = 'classic';
			$outer['background_overlay_color']      = is_array( $ov ) ? ( isset( $ov['color'] ) ? $ov['color'] : 'rgba(15,23,41,0.7)' ) : $ov;
			$outer['background_overlay_opacity']    = el_size( ( is_array( $ov ) && isset( $ov['opacity'] ) ) ? $ov['opacity'] : 0.65, '' );
		}
	}

	$inner = array(
		'content_width'    => 'boxed',
		'boxed_width'      => el_size( $cwidth ),
		'width'            => el_size( 100, '%' ),
		'flex_direction'   => 'column',
		'flex_align_items' => $align,
		'flex_gap'         => array( 'unit' => 'px', 'size' => $gap, 'column' => (string) $gap, 'row' => (string) $gap ),
		'padding'          => el_box( 0, 0, 0, 0 ),
	);

	return el_container( $outer, array( el_container( $inner, $children ) ) );
}

/** A flex row that wraps into columns; pass child containers as columns. */
function el_row( array $columns, $gap = 24, $align = 'stretch', $justify = 'center', $wrap = true ) {
	$s = array(
		'content_width'      => 'full',
		'width'              => el_size( 100, '%' ),
		'flex_direction'     => 'row',
		'flex_wrap'          => $wrap ? 'wrap' : 'nowrap',
		'flex_gap'           => array( 'unit' => 'px', 'size' => $gap, 'column' => (string) $gap, 'row' => (string) $gap ),
		'flex_align_items'   => $align,
		'flex_justify_content' => $justify,
	);
	return el_container( $s, $columns );
}

/** A column container (child of a row); $basis like '1','50%','360px' controls width. */
function el_col( array $children, array $opts = array() ) {
	$s = array(
		'content_width'  => 'full',
		'flex_direction' => 'column',
		'flex_gap'       => array( 'unit' => 'px', 'size' => isset( $opts['gap'] ) ? $opts['gap'] : 16, 'column' => '16', 'row' => '16' ),
	);
	if ( isset( $opts['width'] ) ) {
		if ( 'auto' === $opts['width'] ) {
			// Hug content. Elementor containers force width:100%, so a CSS utility
			// class (.bu-hug{width:fit-content}) is the reliable override.
			$s['css_classes'] = trim( ( isset( $s['css_classes'] ) ? $s['css_classes'] . ' ' : '' ) . 'bu-hug' );
			$s['_flex_grow']  = 0;
			$s['_flex_shrink'] = 0;
		} elseif ( 'grow' === $opts['width'] ) {
			$s['_flex_grow'] = 1;
			$s['flex_basis'] = el_size( 0, 'px' );
		} elseif ( is_array( $opts['width'] ) ) {
			$s['width'] = $opts['width'];
		} else {
			$s['width'] = el_size( (float) $opts['width'], '%' );
		}
	} else {
		$s['_flex_grow'] = 1; // share row equally by default
		$s['flex_basis'] = el_size( 0, 'px' );
	}
	if ( ! empty( $opts['bg'] ) ) {
		$s['background_background'] = 'classic';
		$s['background_color']      = $opts['bg'];
	}
	if ( isset( $opts['pad'] ) ) {
		$s['padding'] = is_array( $opts['pad'] ) ? $opts['pad'] : el_box( $opts['pad'], $opts['pad'], $opts['pad'], $opts['pad'] );
	}
	if ( ! empty( $opts['radius'] ) ) {
		$s['border_radius'] = is_array( $opts['radius'] ) ? $opts['radius'] : el_box( $opts['radius'], $opts['radius'], $opts['radius'], $opts['radius'] );
	}
	if ( ! empty( $opts['border'] ) ) {
		$s['border_border'] = 'solid';
		$s['border_width']  = el_box( 1, 1, 1, 1 );
		$s['border_color']  = is_string( $opts['border'] ) ? $opts['border'] : '#CBD5E1';
	}
	if ( ! empty( $opts['align'] ) ) {
		$s['flex_align_items'] = $opts['align'];
	}
	if ( ! empty( $opts['id'] ) ) {
		$s['_element_id'] = $opts['id']; // CSS id / scroll anchor
	}
	if ( ! empty( $opts['shadow'] ) ) {
		$s['box_shadow_box_shadow_type'] = 'yes';
		$s['box_shadow_box_shadow']      = array( 'horizontal' => 0, 'vertical' => 12, 'blur' => 32, 'spread' => 0, 'color' => 'rgba(15,23,41,0.08)' );
	}
	if ( isset( $opts['gap_px'] ) ) {
		$s['flex_gap'] = array( 'unit' => 'px', 'size' => $opts['gap_px'], 'column' => (string) $opts['gap_px'], 'row' => (string) $opts['gap_px'] );
	}
	if ( ! empty( $opts['relative'] ) ) {
		$s['position'] = 'relative';
	}
	return el_container( $s, $children );
}

/** Bare widget. */
function el_widget( $type, array $settings = array() ) {
	return array(
		'id'         => el_id(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
}

/* --- Native widget convenience wrappers ---------------------------------- */

/**
 * Heading. $o: size(px), weight, color(hex), color_global(id), tag(h1..h6/p/div),
 * align, line_height, max_width(px), spans (array of [text,color] for a gradient-ish accent).
 */
function el_heading( $text, array $o = array() ) {
	$s = array(
		'title'        => $text,
		'header_size'  => isset( $o['tag'] ) ? $o['tag'] : 'h2',
		'align'        => isset( $o['align'] ) ? $o['align'] : 'left',
	);
	$s['typography_typography'] = 'custom';
	$s['typography_font_family'] = 'Geist';
	$s['typography_font_weight'] = isset( $o['weight'] ) ? (string) $o['weight'] : '700';
	if ( isset( $o['size'] ) ) {
		$s['typography_font_size'] = el_size( $o['size'] );
	}
	if ( isset( $o['line_height'] ) ) {
		$s['typography_line_height'] = el_size( $o['line_height'], 'em' );
	}
	if ( isset( $o['letter_spacing'] ) ) {
		$s['typography_letter_spacing'] = el_size( $o['letter_spacing'] );
	}
	if ( ! empty( $o['max_width'] ) ) {
		$s['_element_custom_width'] = el_size( $o['max_width'] );
	}
	$globals = array();
	if ( ! empty( $o['color_global'] ) ) {
		$globals['title_color'] = el_gcolor( $o['color_global'] );
	} elseif ( ! empty( $o['color'] ) ) {
		$s['title_color'] = $o['color'];
	}
	return el_widget( 'heading', el_with_globals( $s, $globals ) );
}

/** Rich text (text-editor). $o: color(hex), color_global, align, size(px). */
function el_text( $html, array $o = array() ) {
	$s = array( 'editor' => '<p>' . $html . '</p>' );
	if ( isset( $o['raw'] ) && $o['raw'] ) {
		$s['editor'] = $html;
	}
	$s['align'] = isset( $o['align'] ) ? $o['align'] : 'left';
	$s['typography_typography'] = 'custom';
	$s['typography_font_family'] = 'Geist';
	if ( isset( $o['size'] ) ) {
		$s['typography_font_size'] = el_size( $o['size'] );
	}
	$globals = array();
	if ( ! empty( $o['color_global'] ) ) {
		$globals['text_color'] = el_gcolor( $o['color_global'] );
	} elseif ( ! empty( $o['color'] ) ) {
		$s['text_color'] = $o['color'];
	}
	return el_widget( 'text-editor', el_with_globals( $s, $globals ) );
}

/**
 * Button. $o: url, size(xs|sm|md|lg|xl), align, bg(hex)/bg_global, text_color,
 * radius(px), icon(fa class), full(bool).
 */
function el_button( $text, array $o = array() ) {
	$s = array(
		'text'        => $text,
		'link'        => el_link( isset( $o['url'] ) ? $o['url'] : '#', ! empty( $o['external'] ) ),
		'size'        => isset( $o['size'] ) ? $o['size'] : 'md',
		'align'       => isset( $o['align'] ) ? $o['align'] : 'left',
		'border_radius' => el_box(
			isset( $o['radius'] ) ? $o['radius'] : 9999,
			isset( $o['radius'] ) ? $o['radius'] : 9999,
			isset( $o['radius'] ) ? $o['radius'] : 9999,
			isset( $o['radius'] ) ? $o['radius'] : 9999
		),
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Geist',
		'typography_font_weight' => '600',
	);
	if ( ! empty( $o['icon'] ) ) {
		$s['selected_icon']  = el_fa( $o['icon'] );
		$s['icon_align']     = 'right';
	}
	$globals = array();
	// Outline variant: matches the theme's secondary/outline button
	// (border-2 border-border, transparent bg, foreground text).
	if ( isset( $o['variant'] ) && 'outline' === $o['variant'] ) {
		$s['background_color']   = 'rgba(0,0,0,0)';
		$s['border_border']      = 'solid';
		$s['border_width']       = el_box( 2, 2, 2, 2 );
		$s['border_color']       = '#CBD5E1';
		$s['button_text_color']  = isset( $o['text_color'] ) ? $o['text_color'] : '#0F1729';
		return el_widget( 'button', $s );
	}
	if ( ! empty( $o['bg_global'] ) ) {
		$globals['background_color'] = el_gcolor( $o['bg_global'] );
	} elseif ( ! empty( $o['bg'] ) ) {
		$s['background_color'] = $o['bg'];
	} else {
		$globals['background_color'] = el_gcolor( 'primary' );
	}
	$s['button_text_color'] = isset( $o['text_color'] ) ? $o['text_color'] : '#FFFFFF';
	return el_widget( 'button', el_with_globals( $s, $globals ) );
}

/** Image. $o: url(required) or id, width(px/%), align, radius(px), alt. */
function el_image( $url, array $o = array() ) {
	$s = array(
		'image' => array( 'url' => $url, 'id' => isset( $o['id'] ) ? $o['id'] : '', 'alt' => isset( $o['alt'] ) ? $o['alt'] : '' ),
		'image_size' => 'full',
		'align' => isset( $o['align'] ) ? $o['align'] : 'center',
	);
	if ( isset( $o['width'] ) ) {
		$s['width'] = is_array( $o['width'] ) ? $o['width'] : el_size( (float) $o['width'], '%' );
	}
	if ( ! empty( $o['radius'] ) ) {
		$s['image_border_radius'] = el_box( $o['radius'], $o['radius'], $o['radius'], $o['radius'] );
	}
	if ( ! empty( $o['height'] ) ) {
		// Fixed height + cover crop → uniform gallery tiles (no aspect-ratio blowout).
		$s['height']     = el_size( $o['height'] );
		$s['object-fit'] = isset( $o['object_fit'] ) ? $o['object_fit'] : 'cover';
	} elseif ( ! empty( $o['object_fit'] ) ) {
		$s['object-fit'] = $o['object_fit'];
	}
	return el_widget( 'image', $s );
}

/** Icon. $o: size(px), color(hex)/color_global, align, view(default|stacked|framed). */
function el_icon( $fa, array $o = array() ) {
	$s = array(
		'selected_icon' => el_fa( $fa ),
		'view'  => isset( $o['view'] ) ? $o['view'] : 'default',
		'align' => isset( $o['align'] ) ? $o['align'] : 'left',
	);
	if ( isset( $o['size'] ) ) {
		$s['size'] = el_size( $o['size'] );
	}
	$globals = array();
	if ( ! empty( $o['color_global'] ) ) {
		$globals['primary_color'] = el_gcolor( $o['color_global'] );
	} elseif ( ! empty( $o['color'] ) ) {
		$s['primary_color'] = $o['color'];
	}
	return el_widget( 'icon', el_with_globals( $s, $globals ) );
}

/** Icon box: icon + title + description. $o: icon, title, desc, color_global, align, title_size. */
function el_icon_box( array $o ) {
	$s = array(
		'selected_icon'    => el_fa( isset( $o['icon'] ) ? $o['icon'] : 'fas fa-check' ),
		'title_text'       => isset( $o['title'] ) ? $o['title'] : '',
		'description_text' => isset( $o['desc'] ) ? $o['desc'] : '',
		'position'         => isset( $o['position'] ) ? $o['position'] : 'top',
		'text_align'       => isset( $o['align'] ) ? $o['align'] : 'center',
		'title_typography_typography'  => 'custom',
		'title_typography_font_family' => 'Geist',
		'title_typography_font_weight' => '700',
	);
	if ( isset( $o['title_size'] ) ) {
		$s['title_typography_font_size'] = el_size( $o['title_size'] );
	}
	$globals = array();
	$globals['icon_primary_color'] = el_gcolor( ! empty( $o['icon_global'] ) ? $o['icon_global'] : 'primary' );
	$globals['title_color']        = el_gcolor( 'text' );
	$globals['description_color']  = el_gcolor( 'mutedcol' );
	return el_widget( 'icon-box', el_with_globals( $s, $globals ) );
}

/** Star rating. $o: rating(0-5), scale(5), color(hex). */
function el_stars( $rating = 5, array $o = array() ) {
	$s = array(
		'rating'      => (string) $rating,
		'star_style'  => 'star_fontawesome',
		'stars'       => isset( $o['scale'] ) ? (string) $o['scale'] : '5',
		'star_color'  => isset( $o['color'] ) ? $o['color'] : '#F59E0B',
		'align'       => isset( $o['align'] ) ? $o['align'] : 'left',
	);
	return el_widget( 'star-rating', $s );
}

/** Accordion (FAQ). $items = [ [title, content_html], ... ]. */
function el_accordion( array $items, array $o = array() ) {
	$tabs = array();
	foreach ( $items as $it ) {
		$tabs[] = array(
			'_id'         => el_id(),
			'tab_title'   => $it[0],
			'tab_content' => '<p>' . $it[1] . '</p>',
		);
	}
	$s = array(
		'tabs' => $tabs,
		'title_typography_typography'  => 'custom',
		'title_typography_font_family' => 'Geist',
		'title_typography_font_weight' => '600',
	);
	return el_widget( 'accordion', $s );
}

/** Icon list (e.g. pricing features). $items = ['text', ...]; $o: icon, color_global, gap. */
function el_icon_list( array $items, array $o = array() ) {
	$icon = isset( $o['icon'] ) ? $o['icon'] : 'fas fa-check';
	$list = array();
	foreach ( $items as $text ) {
		$list[] = array(
			'_id'           => el_id(),
			'text'          => $text,
			'selected_icon' => el_fa( $icon ),
		);
	}
	$s = array(
		'icon_list'   => $list,
		'space_between' => el_size( isset( $o['gap'] ) ? $o['gap'] : 10 ),
		'icon_size'   => el_size( 16 ),
		'icon_typography_typography' => 'custom',
		'text_typography_typography'  => 'custom',
		'text_typography_font_family' => 'Geist',
		'text_typography_font_size'   => el_size( isset( $o['size'] ) ? $o['size'] : 15 ),
	);
	$globals = array(
		'icon_color' => el_gcolor( ! empty( $o['color_global'] ) ? $o['color_global'] : 'secondary' ),
		'text_color' => el_gcolor( 'mutedcol' ),
	);
	return el_widget( 'icon-list', el_with_globals( $s, $globals ) );
}

/**
 * Small pill (icon + label), e.g. eyebrow/trust badges. Matches the live site's
 * glass pill (`rounded-full glass border border-border/50`) by applying the
 * theme's real `.glass` utility (now loaded on Elementor pages).
 * $o: icon_color_global (default 'secondary'), text_color_global (default 'mutedcol').
 */
function el_pill( $label, $icon = 'fas fa-check-circle', array $o = array() ) {
	$s = array(
		'content_width'    => 'full',
		'css_classes'      => 'bu-pill glass',
		'flex_direction'   => 'row',
		'flex_align_items' => 'center',
		'flex_gap'         => array( 'unit' => 'px', 'size' => 8, 'column' => '8', 'row' => '8' ),
		'border_radius'    => el_box( 9999, 9999, 9999, 9999 ),
		'border_border'    => 'solid',
		'border_width'     => el_box( 1, 1, 1, 1 ),
		'border_color'     => 'rgba(203,213,225,0.5)',
		'padding'          => el_box( 6, 16, 6, 16 ),
		'_flex_grow'       => 0,
		'flex_shrink'      => 0,
	);
	return el_container( $s, array(
		el_icon( $icon, array( 'size' => 16, 'color_global' => isset( $o['icon_color_global'] ) ? $o['icon_color_global'] : 'secondary' ) ),
		el_text( $label, array( 'size' => 14, 'color_global' => isset( $o['text_color_global'] ) ? $o['text_color_global'] : 'mutedcol' ) ),
	) );
}

/** Native Shortcode widget (used to embed the existing buckleup-core contact form, etc.). */
function el_shortcode( $shortcode ) {
	return el_widget( 'shortcode', array( 'shortcode' => $shortcode ) );
}

/** Spacer. */
function el_spacer( $px = 32 ) {
	return el_widget( 'spacer', array( 'space' => el_size( $px ) ) );
}

/* --------------------------------------------------------------------------
 * Target resolution
 *
 * Post IDs are NOT stable across installs. A fresh `make provision` numbers posts
 * in seed order, so anything that shifts the seed (e.g. going from 5 placeholder
 * testimonials to the 17 real Google reviews) renumbers every page after it —
 * Home was 38 on the original build and is 49 on a current rebuild. The builders
 * therefore resolve their targets by SLUG, the way build-locations.php always has.
 * ----------------------------------------------------------------------- */

/**
 * Resolve a post id by slug, or 0 when it doesn't exist (caller reports + skips).
 *
 * @param string $slug      Post slug (post_name).
 * @param string $post_type Post type to look in.
 * @return int Post id, or 0.
 */
function el_post_id( $slug, $post_type = 'page' ) {
	// get_page_by_path() matches a full hierarchical PATH, so a bare slug misses any
	// child page (e.g. icbc-road-test-failures lives under resources/). Accept either
	// a full path or a bare slug — slugs are unique enough within these seeds.
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( $post ) {
		return (int) $post->ID;
	}
	$found = get_posts( array(
		'post_type'        => $post_type,
		'name'             => $slug,
		'post_status'      => 'any',
		'numberposts'      => 1,
		'suppress_filters' => false,
	) );
	return $found ? (int) $found[0]->ID : 0;
}

/**
 * Resolve an Elementor library template by slug, CREATING it if absent.
 *
 * Unlike pages (seeded by provision.sh), the header/footer library templates only
 * exist because build-chrome.php made them — on a fresh DB there is nothing to
 * resolve, and writing meta to a non-existent id silently produced an orphaned row
 * and then fataled in Elementor's CSS generator.
 *
 * @param string $slug  Template slug.
 * @param string $title Human title, used when creating.
 * @return int Post id (never 0).
 */
function el_library_id( $slug, $title ) {
	$id = el_post_id( $slug, 'elementor_library' );
	if ( $id ) {
		return $id;
	}
	$id = wp_insert_post( array(
		'post_type'   => 'elementor_library',
		'post_name'   => $slug,
		'post_title'  => $title,
		'post_status' => 'publish',
	) );
	if ( is_wp_error( $id ) ) {
		echo "ERROR: could not create library template '$slug': " . $id->get_error_message() . "\n";
		return 0;
	}
	echo "Created Elementor library template '$slug' (id $id).\n";
	return (int) $id;
}

/* --------------------------------------------------------------------------
 * Save runner
 * ----------------------------------------------------------------------- */

/**
 * Persist an element tree to a page as native Elementor data, assign the
 * full-width template, and regenerate that page's CSS.
 *
 * @param int   $post_id  Target page id.
 * @param array $elements Top-level element array (containers).
 * @param array $opts     template(string|false)=>'page-elementor', hide_title(bool).
 */
function el_save_page( $post_id, array $elements, array $opts = array() ) {
	// Never write meta to a non-existent post: that used to leave orphaned rows and
	// then fatal inside Elementor's CSS generator (which needs a real document).
	if ( ! $post_id || ! get_post( $post_id ) ) {
		echo "SKIP: no post with id " . var_export( $post_id, true ) . " — nothing saved.\n";
		return;
	}
	$json = wp_json_encode( array_values( $elements ) );
	// Mirror Elementor's own save: wp_slash counteracts update_metadata's unslash,
	// preserving the JSON's escaped slashes/unicode.
	update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	// Document type: 'wp-page' for Pages, 'wp-post' for CPT singles (e.g. location).
	update_post_meta( $post_id, '_elementor_template_type', isset( $opts['template_type'] ) ? $opts['template_type'] : 'wp-page' );
	update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );

	// For Pages, assign the full-width Elementor template. CPT singles resolve their
	// own template via the hierarchy (single-location.html), so pass template=>false.
	$tpl = array_key_exists( 'template', $opts ) ? $opts['template'] : 'page-elementor';
	if ( $tpl ) {
		update_post_meta( $post_id, '_wp_page_template', $tpl );
	}

	// Per-page Elementor settings: hide the theme/Elementor page title so our
	// designed hero is the H1.
	if ( empty( $opts['keep_title'] ) ) {
		$ps = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $ps ) ) {
			$ps = array();
		}
		$ps['hide_title'] = 'yes';
		update_post_meta( $post_id, '_elementor_page_settings', $ps );
	}

	// Regenerate this page's CSS file.
	if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		$css = \Elementor\Core\Files\CSS\Post::create( $post_id );
		$css->update();
	}

	$bytes = strlen( $json );
	echo "Saved Elementor data to page $post_id ($bytes bytes, template=" . ( $tpl ?: 'default' ) . ").\n";
}
