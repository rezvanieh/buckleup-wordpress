<?php
/**
 * Configure the Elementor global Kit (Global Colors + Global Fonts) to the
 * BuckleUp brand tokens, so every Elementor widget can reference brand globals
 * and the admin can retune the palette/typography site-wide.
 *
 * Brand tokens (light mode :root, from wp-content/themes/buckleup/src/css/app.css):
 *   --primary 217 91% 46% -> #0B5CE0   --accent 160 84% 39% -> #10B77F
 *   --foreground 222 47% 11% -> #0F1729 --background 210 20% 98% -> #F8FAFC
 *   --card #FFFFFF  --border 214 32% 85% -> #CBD5E1  --muted-fg 215 16% 41% -> #64748B
 *   font: Geist
 *
 * Run: docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/set-kit.php
 */

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	fwrite( STDERR, "Elementor not active\n" );
	return;
}

$kit_id = (int) get_option( 'elementor_active_kit' );
if ( ! $kit_id ) {
	$kit    = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
	$kit_id = $kit ? $kit->get_id() : 0;
}
if ( ! $kit_id ) {
	fwrite( STDERR, "No active kit\n" );
	return;
}

$settings = array(
	// --- Global Colors -------------------------------------------------
	'system_colors' => array(
		array( '_id' => 'primary',   'title' => 'Primary',   'color' => '#0B5CE0' ),
		array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#10B77F' ),
		array( '_id' => 'text',      'title' => 'Text',      'color' => '#0F1729' ),
		array( '_id' => 'accent',    'title' => 'Accent',    'color' => '#0B5CE0' ),
	),
	'custom_colors' => array(
		array( '_id' => 'bgcolor',   'title' => 'Background',   'color' => '#F8FAFC' ),
		array( '_id' => 'cardcol',   'title' => 'Card',         'color' => '#FFFFFF' ),
		array( '_id' => 'mutedcol',  'title' => 'Muted',        'color' => '#64748B' ),
		array( '_id' => 'bordercol', 'title' => 'Border',       'color' => '#CBD5E1' ),
		array( '_id' => 'greencol',  'title' => 'Accent Green', 'color' => '#10B77F' ),
		array( '_id' => 'fgcolor',   'title' => 'Foreground',   'color' => '#0F1729' ),
	),

	// --- Global Fonts (Geist everywhere) -------------------------------
	'system_typography' => array(
		array( '_id' => 'primary',   'title' => 'Primary',   'typography_typography' => 'custom', 'typography_font_family' => 'Geist', 'typography_font_weight' => '700' ),
		array( '_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Geist', 'typography_font_weight' => '600' ),
		array( '_id' => 'text',      'title' => 'Text',      'typography_typography' => 'custom', 'typography_font_family' => 'Geist', 'typography_font_weight' => '400' ),
		array( '_id' => 'accent',    'title' => 'Accent',    'typography_typography' => 'custom', 'typography_font_family' => 'Geist', 'typography_font_weight' => '600' ),
	),

	// --- Body defaults -------------------------------------------------
	'body_typography_typography'  => 'custom',
	'body_typography_font_family' => 'Geist',
	'body_typography_font_weight' => '400',
	'body_color'                  => '#0F1729',
	'body_background_background'   => 'classic',
	'body_background_color'       => '#F8FAFC',

	// Container defaults: full width, sensible content width for boxed containers.
	'container_width'        => array( 'unit' => 'px', 'size' => 1200 ),
	'space_between_widgets'  => array( 'unit' => 'px', 'size' => 0 ),
);

// Merge over whatever exists so we don't clobber unrelated kit keys.
$existing = get_post_meta( $kit_id, '_elementor_page_settings', true );
if ( ! is_array( $existing ) ) {
	$existing = array();
}
$merged = array_merge( $existing, $settings );

update_post_meta( $kit_id, '_elementor_page_settings', $merged );

// Rebuild Elementor's generated CSS (kit/global stylesheet).
\Elementor\Plugin::$instance->files_manager->clear_cache();

echo "Kit $kit_id updated.\n";
echo "Colors: primary=#0B5CE0 secondary=#10B77F text=#0F1729 accent=#0B5CE0\n";
echo "Customs: bg #F8FAFC, card #FFFFFF, muted #64748B, border #CBD5E1, green #10B77F\n";
echo "Fonts: Geist (system primary/secondary/text/accent + body)\n";
