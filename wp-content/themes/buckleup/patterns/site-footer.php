<?php
/**
 * Title: Site Footer
 * Slug: buckleup/site-footer
 * Inserter: no
 *
 * Reproduces src/components/layout/Footer.tsx: the landing-only "Ready to Start
 * Driving?" CTA band, the 5-column grid (Brand+socials, Quick Links, Service
 * Areas, Recent Blogs, Contact), and the copyright bar. NAP / socials / hours come
 * from the plugin settings; Service Areas from the location CPT; Recent Blogs from
 * the 3 latest posts. CTA band shows only on the front page (matches isLandingPage).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$get = static function ( $key, $default = '' ) {
	return function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( $key, $default ) : $default;
};

$business   = $get( 'business_name', 'BuckleUp Driving School' );
$street     = $get( 'street_address', '136 Maple Dr' );
$locality   = $get( 'address_locality', 'Port Moody' );
$region     = $get( 'address_region', 'BC' );
$postal     = $get( 'postal_code', 'V3H 0A8' );
$phone      = $get( 'phone', '(604) 441-3677' );
$phone_e164 = $get( 'phone_e164', '+16044413677' );
$email      = $get( 'email', 'info@buckleupdriving.ca' );
$instagram  = $get( 'instagram_url', 'https://www.instagram.com/budrivingschool' );
$facebook   = $get( 'facebook_url', 'https://www.facebook.com/DriveMasterca' );
$hours      = $get( 'hours_display', 'Mon–Sun 9am–9pm' );
$locations  = buckleup_location_items();
$is_landing = is_front_page();

$recent = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish', 'no_found_rows' => true ) );

$quick_links = array(
	array( 'label' => 'Services & Pricing', 'href' => home_url( '/services/' ) ),
	array( 'label' => 'About Us',           'href' => home_url( '/about/' ) ),
	array( 'label' => 'Book a Lesson',      'href' => home_url( '/#single-session' ) ),
	array( 'label' => 'FAQ',                'href' => home_url( '/#faq' ) ),
);
?>
<!-- wp:html -->
<footer class="bg-card border-t border-border relative overflow-hidden">
	<div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

	<?php if ( $is_landing ) : ?>
		<div class="container mx-auto px-4 relative z-10 mb-12">
			<div data-reveal data-reveal-y="30" class="bg-card border border-border rounded-3xl p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6 shadow-lg">
				<div>
					<h3 class="text-2xl md:text-3xl font-bold text-foreground mb-2"><?php esc_html_e( 'Ready to Start Driving?', 'buckleup' ); ?></h3>
					<p class="text-muted-foreground"><?php esc_html_e( 'Book your first lesson today and get 20% off!', 'buckleup' ); ?></p>
				</div>
				<a href="#most-popular">
					<?php
					buckleup_button( array(
						'label' => __( 'Book Now', 'buckleup' ),
						'size'  => 'lg',
						'class' => 'h-14 px-8 rounded-full shine glow-primary whitespace-nowrap',
						'icon'  => buckleup_icon( 'arrow-right', 'ml-2 h-5 w-5' ),
						'attrs' => array(),
					) );
					?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<div class="container mx-auto px-4 relative z-10">
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-12 py-16">

			<!-- Brand -->
			<div class="space-y-6">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-0">
					<?php echo buckleup_logo( 'h-10 w-auto' ); // phpcs:ignore ?>
				</a>
				<p class="text-muted-foreground text-sm leading-relaxed">
					<?php esc_html_e( 'Empowering the next generation of safe, confident drivers in Vancouver. Modern fleet, expert instructors, and a commitment to excellence.', 'buckleup' ); ?>
				</p>
				<div class="flex gap-3">
					<a href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener" aria-label="Instagram"
						class="w-10 h-10 rounded-xl glass flex items-center justify-center text-muted-foreground hover:text-primary hover:border-primary/30 transition-all">
						<?php echo buckleup_icon( 'instagram', 'h-5 w-5' ); // phpcs:ignore ?>
					</a>
					<a href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener" aria-label="Facebook"
						class="w-10 h-10 rounded-xl glass flex items-center justify-center text-muted-foreground hover:text-primary hover:border-primary/30 transition-all">
						<?php echo buckleup_icon( 'facebook', 'h-5 w-5' ); // phpcs:ignore ?>
					</a>
				</div>
			</div>

			<!-- Quick Links -->
			<div>
				<h3 class="font-semibold text-foreground mb-6"><?php esc_html_e( 'Quick Links', 'buckleup' ); ?></h3>
				<ul class="space-y-3">
					<?php foreach ( $quick_links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['href'] ); ?>" class="text-sm text-muted-foreground hover:text-primary transition-colors flex items-center gap-2 group">
								<span class="opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all"><?php echo buckleup_icon( 'arrow-right', 'w-3 h-3' ); // phpcs:ignore ?></span>
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Service Areas -->
			<div>
				<h3 class="font-semibold text-foreground mb-6"><?php esc_html_e( 'Service Areas', 'buckleup' ); ?></h3>
				<ul class="space-y-3">
					<?php foreach ( $locations as $loc ) : ?>
						<li>
							<a href="<?php echo esc_url( $loc['href'] ); ?>" class="text-sm text-muted-foreground hover:text-primary transition-colors flex items-center gap-2">
								<?php echo buckleup_icon( 'map-pin', 'w-3 h-3 text-primary' ); // phpcs:ignore ?>
								<?php
								/*
								 * The label is built as "Driving Lessons in <city>", so a location
								 * whose own title is already phrased that way would render as
								 * "Driving Lessons in Driving Lessons in Coquitlam". That exact bug
								 * was live, though in the Elementor library template that actually
								 * renders the footer today (elementor_library/site-footer), where
								 * the string is literal; it is fixed there by
								 * scripts/wp/fix-footer-duplicate-label.php.
								 *
								 * The guard is kept here because these names are editable in
								 * wp-admin, so this pattern should not assume every location title
								 * is a bare city name if it is ever used again.
								 */
								if ( 0 === stripos( $loc['name'], 'driving lessons in ' ) ) {
									echo esc_html( $loc['name'] );
								} else {
									/* translators: %s: city name */
									printf( esc_html__( 'Driving Lessons in %s', 'buckleup' ), esc_html( $loc['name'] ) );
								}
								?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Recent Blogs -->
			<div>
				<h3 class="font-semibold text-foreground mb-6"><?php esc_html_e( 'Recent Blogs', 'buckleup' ); ?></h3>
				<ul data-recent-blogs class="space-y-3">
					<?php if ( ! empty( $recent ) ) : ?>
						<?php foreach ( $recent as $post ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" title="<?php echo esc_attr( get_the_title( $post ) ); ?>"
									class="text-sm text-muted-foreground hover:text-primary transition-colors flex items-start gap-2 line-clamp-2 pr-2">
									<span class="shrink-0 text-primary mt-0.5"><?php echo buckleup_icon( 'arrow-right', 'w-3 h-3' ); // phpcs:ignore ?></span>
									<?php echo esc_html( get_the_title( $post ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					<?php else : ?>
						<li class="text-sm text-muted-foreground"><?php esc_html_e( 'Coming soon.', 'buckleup' ); ?></li>
					<?php endif; ?>
				</ul>
			</div>

			<!-- Contact -->
			<div>
				<h3 class="font-semibold text-foreground mb-6"><?php esc_html_e( 'Contact Us', 'buckleup' ); ?></h3>
				<ul class="space-y-4">
					<li class="flex items-start gap-3">
						<div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'map-pin', 'h-4 w-4 text-primary' ); // phpcs:ignore ?></div>
						<span class="text-sm text-muted-foreground">
							<?php echo esc_html( $street ); ?><br><?php echo esc_html( "$locality, $region $postal, Canada" ); ?>
						</span>
					</li>
					<li class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'phone', 'h-4 w-4 text-primary' ); // phpcs:ignore ?></div>
						<a href="tel:<?php echo esc_attr( $phone_e164 ); ?>" class="text-sm text-muted-foreground hover:text-primary transition-colors"><?php echo esc_html( $phone ); ?></a>
					</li>
					<li class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'mail', 'h-4 w-4 text-primary' ); // phpcs:ignore ?></div>
						<a href="mailto:<?php echo esc_attr( $email ); ?>" class="text-sm text-muted-foreground hover:text-primary transition-colors"><?php echo esc_html( $email ); ?></a>
					</li>
					<?php if ( $hours ) : ?>
						<li class="flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><?php echo buckleup_icon( 'clock', 'h-4 w-4 text-primary' ); // phpcs:ignore ?></div>
							<span class="text-sm text-muted-foreground"><?php echo esc_html( $hours ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>

		<!-- Bottom Bar -->
		<div class="border-t border-border py-6 flex flex-col md:flex-row justify-between items-center gap-4">
			<p class="text-sm text-muted-foreground">
				<?php
				/* translators: %1$d: current year, %2$s: business name */
				printf( esc_html__( '© %1$d %2$s Ltd. All rights reserved.', 'buckleup' ), (int) gmdate( 'Y' ), esc_html( $business ) );
				?>
			</p>
		</div>
	</div>
</footer>
<!-- /wp:html -->
