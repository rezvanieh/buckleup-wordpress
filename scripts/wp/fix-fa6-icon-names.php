<?php
/**
 * Find and fix Font Awesome 6 icon names in Elementor content.
 *
 * Elementor 4.2.2 ships Font Awesome **5.15.3**. Icons authored with FA6 names
 * therefore match nothing: no glyph in the font, and no entry in Elementor's
 * SVG map either, so Elementor falls back to rendering a bare
 * <i class="fas fa-circle-check"> that displays as nothing at all.
 *
 * The five location pages carry 5-9 of these each (fa-circle-check,
 * fa-location-dot), which have been invisible on the live site. They are also
 * the reason those pages could not drop the 132 KB Font Awesome stylesheet: the
 * <i> fallback markup makes the page look like it needs the icon font, when in
 * truth the font does not contain the glyph either.
 *
 * Renaming them to their FA5 equivalents fixes both problems at once: Elementor
 * resolves the name, renders an inline SVG, the icon becomes visible, and the
 * page no longer emits icon-font markup.
 *
 * Pass "apply" to write; runs as a report by default.
 *
 *   docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-fa6-icon-names.php
 *   docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-fa6-icon-names.php apply
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$APPLY = isset( $args ) && is_array( $args ) && in_array( 'apply', $args, true );

/** FA6 name => FA5 equivalent shipped with Elementor. */
$RENAMES = array(
	'fa-circle-check'    => 'fa-check-circle',
	'fa-location-dot'    => 'fa-map-marker-alt',
	'fa-circle-info'     => 'fa-info-circle',
	'fa-circle-xmark'    => 'fa-times-circle',
	'fa-circle-question' => 'fa-question-circle',
	'fa-triangle-exclamation' => 'fa-exclamation-triangle',
	'fa-house'           => 'fa-home',
	'fa-house-chimney'   => 'fa-home',
	'fa-map-location-dot' => 'fa-map-marked-alt',
	'fa-arrow-right-long' => 'fa-long-arrow-alt-right',
	'fa-phone-flip'      => 'fa-phone',
	'fa-envelope-open-text' => 'fa-envelope-open',
	'fa-gauge-high'      => 'fa-tachometer-alt',
	'fa-car-side'        => 'fa-car-side', // exists in FA5, listed for clarity
);

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

// The authoritative list of glyphs the shipped stylesheet actually defines.
$css_file = WP_PLUGIN_DIR . '/elementor/assets/lib/font-awesome/css/all.css';
$known    = array();
if ( is_readable( $css_file ) ) {
	if ( preg_match_all( '/\.(fa-[a-z0-9-]+):before/', file_get_contents( $css_file ), $m ) ) {
		$known = array_flip( array_unique( $m[1] ) );
	}
}
echo 'Font Awesome glyphs available: ' . count( $known ) . "\n\n";

$rows = mysqli_query( $wpdb->dbh, "SELECT p.ID, p.post_type, p.post_name, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data' WHERE p.post_status = 'publish'" );

$total_fixed = 0;
$unknown     = array();

while ( $rows && $row = mysqli_fetch_assoc( $rows ) ) {
	$data  = $row['meta_value'];
	$label = $row['post_type'] . ':' . $row['post_name'];

	// Every fa- name referenced in this document.
	if ( ! preg_match_all( '/fa-[a-z0-9-]+/', $data, $m ) ) { continue; }

	$missing = array();
	foreach ( array_unique( $m[0] ) as $name ) {
		// Library values like "fa-solid"/"fa-regular"/"fa-brands" are not glyphs.
		if ( in_array( $name, array( 'fa-solid', 'fa-regular', 'fa-brands', 'fa-light', 'fa-duotone' ), true ) ) { continue; }
		if ( isset( $known[ $name ] ) ) { continue; }
		$missing[] = $name;
	}
	if ( ! $missing ) { continue; }

	$new   = $data;
	$fixed = array();
	foreach ( $missing as $name ) {
		if ( isset( $RENAMES[ $name ] ) && isset( $known[ $RENAMES[ $name ] ] ) ) {
			$count = substr_count( $new, $name );
			$new   = str_replace( $name, $RENAMES[ $name ], $new );
			$fixed[] = "$name -> {$RENAMES[$name]} (x$count)";
		} else {
			$unknown[ $name ] = ( $unknown[ $name ] ?? 0 ) + 1;
		}
	}

	if ( ! $fixed ) { continue; }

	printf( "%-34s %s\n", $label, implode( ', ', $fixed ) );

	if ( $APPLY && $new !== $data ) {
		if ( ! is_array( json_decode( $new, true ) ) ) {
			echo "  ABORT: result is not valid JSON, skipped\n";
			continue;
		}
		update_post_meta( (int) $row['ID'], '_elementor_data', wp_slash( $new ) );
		delete_post_meta( (int) $row['ID'], '_elementor_element_cache' );
		$total_fixed++;
	}
}

if ( $unknown ) {
	echo "\nUNMAPPED names that do not exist in the shipped Font Awesome (these still render as nothing):\n";
	foreach ( $unknown as $n => $c ) { echo "  $n (in $c document(s))\n"; }
}

if ( $APPLY ) {
	if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
	wp_cache_flush();
	echo "\nUpdated $total_fixed document(s).\n";
} else {
	echo "\nReport only. Re-run with the argument 'apply' to write.\n";
}
