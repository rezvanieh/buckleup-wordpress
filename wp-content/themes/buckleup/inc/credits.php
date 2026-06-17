<?php
/**
 * Image credits — CC attribution for the location hero photos.
 *
 * The 5 location landing-page heroes are recognizable city landmarks sourced from
 * Wikimedia Commons under Creative Commons licences (some BY, some BY-SA), which
 * require attribution. `import-location-heroes.php` stores structured credit meta
 * (`_bu_credit_*`) on each attachment; this shortcode renders the TASL list
 * (Title / Author / Source / Licence) so the site stays licence-compliant.
 *
 * Usage: place [buckleup_image_credits] on a page (e.g. /image-credits/). A
 * discreet "Image credits" link in the footer points there.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Collect the credited hero attachments (those tagged `_bu_location_hero`).
 *
 * @return array[] Each: landmark, artist, license, license_url, source.
 */
function buckleup_image_credits_data(): array {
	$atts = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'meta_key'       => '_bu_location_hero',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$out = array();
	foreach ( $atts as $a ) {
		$landmark = (string) get_post_meta( $a->ID, '_bu_credit_landmark', true );
		if ( '' === $landmark ) {
			continue;
		}
		$out[] = array(
			'landmark'    => $landmark,
			'artist'      => (string) get_post_meta( $a->ID, '_bu_credit_artist', true ),
			'license'     => (string) get_post_meta( $a->ID, '_bu_credit_license', true ),
			'license_url' => (string) get_post_meta( $a->ID, '_bu_credit_license_url', true ),
			'source'      => (string) get_post_meta( $a->ID, '_bu_credit_source', true ),
		);
	}
	return $out;
}

/**
 * [buckleup_image_credits] — renders the hero photo attributions.
 */
add_shortcode( 'buckleup_image_credits', function (): string {
	$credits = buckleup_image_credits_data();
	if ( empty( $credits ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="bu-image-credits prose prose-lg max-w-none">
		<p class="text-muted-foreground">The location landing-page hero photographs are
			of real local landmarks, sourced from
			<a href="https://commons.wikimedia.org/" target="_blank" rel="noopener nofollow">Wikimedia Commons</a>
			under Creative Commons licences. We gratefully credit the photographers below.</p>
		<ul class="space-y-3">
			<?php foreach ( $credits as $c ) : ?>
				<li>
					<strong><?php echo esc_html( $c['landmark'] ); ?></strong>
					<?php if ( $c['artist'] ) : ?> — photo by <?php echo esc_html( $c['artist'] ); ?><?php endif; ?>
					<?php if ( $c['license'] && $c['license_url'] ) : ?>
						, licensed under
						<a href="<?php echo esc_url( $c['license_url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $c['license'] ); ?></a>
					<?php endif; ?>
					<?php if ( $c['source'] ) : ?>
						(<a href="<?php echo esc_url( $c['source'] ); ?>" target="_blank" rel="noopener nofollow">source</a>)
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	return (string) ob_get_clean();
} );
