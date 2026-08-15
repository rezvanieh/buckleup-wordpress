<?php
/**
 * Turn off the accordion widgets' own FAQ-schema switches, so each page emits
 * exactly one FAQPage block.
 *
 * THE PROBLEM
 * -----------
 * Both accordion plugins can publish their own FAQPage JSON-LD, and the switch
 * was on:
 *
 *   faq_schema="yes"                 2 location pages   (Elementor's accordion)
 *   ekit_accordian_faq_schema="yes"  13 blog posts      (ElementsKit's accordion)
 *
 * The SEO mu-plugin also emits a FAQPage for those URLs, so each was serving TWO
 * FAQPage blocks. Google expects one FAQPage per page; duplicates are at best
 * redundant and at worst cause the markup to be ignored.
 *
 * WHICH ONE TO KEEP
 * -----------------
 * The mu-plugin's, because it is now derived from the page's own accordion
 * (buckleup_seo_page_faq_items), so it matches the visible content by
 * construction, and it sits in the same @graph as the LocalBusiness and
 * BreadcrumbList nodes rather than floating in a second script tag.
 *
 * Turning the widget switch off changes no visible content — it only stops the
 * widget printing a second JSON-LD block.
 *
 * Idempotent. Run:
 *   docker compose run --rm -T wpcli wp eval-file /scripts/wp/fix-duplicate-faq-schema.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
mysqli_report( MYSQLI_REPORT_OFF );

/** Widget settings that make an accordion print its own FAQPage JSON-LD. */
$SWITCHES = array( 'faq_schema', 'ekit_accordian_faq_schema' );

$res = mysqli_query(
	$wpdb->dbh,
	"SELECT p.ID, p.post_type, p.post_name, m.meta_value
	   FROM {$wpdb->posts} p
	   JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
	  WHERE p.post_status = 'publish'"
);

$changed = 0;
$total   = 0;

while ( $res && $row = mysqli_fetch_assoc( $res ) ) {
	$json = $row['meta_value'];
	$new  = $json;
	$hits = 0;

	foreach ( $SWITCHES as $key ) {
		// Only flip an explicit "yes"; leave any other value alone.
		$needle = '"' . $key . '":"yes"';
		$count  = substr_count( $new, $needle );
		if ( ! $count ) { continue; }
		$new   = str_replace( $needle, '"' . $key . '":""', $new );
		$hits += $count;
	}

	if ( ! $hits ) { continue; }
	$total += $hits;

	if ( ! is_array( json_decode( $new, true ) ) ) {
		printf( "  ABORT %s: result is not valid JSON, left untouched\n", $row['post_name'] );
		continue;
	}

	update_post_meta( (int) $row['ID'], '_elementor_data', wp_slash( $new ) );
	// Elementor serves cached rendered markup in preference to re-rendering.
	delete_post_meta( (int) $row['ID'], '_elementor_element_cache' );

	printf( "  %-12s %-52s %d switch(es) off\n", $row['post_type'], $row['post_name'], $hits );
	$changed++;
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

echo "\n$changed document(s) updated, $total widget switch(es) turned off.\n";
echo "Each page now emits a single FAQPage, from the SEO mu-plugin.\n";
