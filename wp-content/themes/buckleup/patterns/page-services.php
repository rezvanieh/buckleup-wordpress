<?php
/**
 * Title: Page: Services
 * Slug: buckleup/page-services
 * Inserter: no
 *
 * Services page — matches the LIVE page's structure (hero w/ 98% pass-rate eyebrow,
 * 4 license-class tabs, pricing, a stats row, and a closing CTA) but with HONEST
 * data: the source's per-class price tiers ($699–$1799) are mock/placeholder and
 * contradict the client's real pricing, so per PLAN §4 ("don't clone source
 * defects") we drive pricing from the real `package` CPT (home-pricing pattern) and
 * the offerings from the real `service` CPT. The 4 tabs (Class 7L/7N/5/4) are the
 * source's CustomTabs FLIP pills; selecting one filters the visible service cards by
 * a per-class tag where available, else shows all.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$services = function_exists( 'buckleup_get_services' ) ? buckleup_get_services() : array();

$classes = array(
	array( 'id' => '7l', 'label' => 'Class 7L', 'sublabel' => __( 'Learner', 'buckleup' ),      'desc' => __( 'Starting your driving journey', 'buckleup' ) ),
	array( 'id' => '7n', 'label' => 'Class 7N', 'sublabel' => __( 'Novice', 'buckleup' ),       'desc' => __( 'Ready for the road test', 'buckleup' ) ),
	array( 'id' => '5',  'label' => 'Class 5',  'sublabel' => __( 'Full License', 'buckleup' ), 'desc' => __( 'Complete driving freedom', 'buckleup' ) ),
	array( 'id' => '4',  'label' => 'Class 4',  'sublabel' => __( 'Commercial', 'buckleup' ),   'desc' => __( 'Professional driving', 'buckleup' ) ),
);

$stats = array(
	array( 'value' => '98%',   'label' => __( 'Pass Rate', 'buckleup' ),        'icon' => 'shield-check' ),
	array( 'value' => '5000+', 'label' => __( 'Graduates', 'buckleup' ),        'icon' => 'star' ),
	array( 'value' => '15+',   'label' => __( 'Years Experience', 'buckleup' ), 'icon' => 'check' ),
	array( 'value' => '50+',   'label' => __( 'Instructors', 'buckleup' ),      'icon' => 'shield-check' ),
);

$tabs = array_map( static function ( $c ) {
	return array( 'id' => $c['id'], 'label' => $c['label'] );
}, $classes );
?>
<!-- wp:html -->
<!-- Hero -->
<section class="py-16 md:py-20 text-center">
	<div class="container mx-auto px-4 max-w-3xl">
		<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/20 mb-6">
			<?php echo buckleup_icon( 'shield-check', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
			<span class="text-sm font-medium text-primary"><?php esc_html_e( '98% First-Time Pass Rate', 'buckleup' ); ?></span>
		</div>
		<h1 data-reveal class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 tracking-tight text-foreground">
			<?php esc_html_e( 'Choose Your Path to', 'buckleup' ); ?> <span class="text-primary"><?php esc_html_e( 'Driving Success', 'buckleup' ); ?></span>
		</h1>
		<p data-reveal class="text-lg text-muted-foreground"><?php esc_html_e( 'Structured lesson packages and ICBC road-test prep for every BC licence class — taught in modern Toyota vehicles.', 'buckleup' ); ?></p>
	</div>
</section>

<!-- License-class tabs -->
<section class="pb-4">
	<div class="container mx-auto px-4">
		<div data-reveal class="flex justify-center mb-8">
			<?php buckleup_custom_tabs( array( 'tabs' => $tabs, 'active' => '7l', 'group' => 'license', 'class' => 'flex-wrap' ) ); ?>
		</div>

		<!-- Per-class tab panels. Each shows the class blurb + the REAL package cards
		     (price/features/WhatsApp CTA from buckleup_get_packages()). The packages
		     aren't class-segmented in reality, so every class surfaces the same honest
		     pricing — this matches the LIVE page's per-class card weight without
		     inventing per-class price tiers. -->
		<?php $packages = function_exists( 'buckleup_get_packages' ) ? buckleup_get_packages() : array(); ?>
		<div data-tab-panels="license">
			<?php foreach ( $classes as $i => $c ) : ?>
				<div data-tab-panel="<?php echo esc_attr( $c['id'] ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?> data-state="<?php echo 0 === $i ? 'active' : 'inactive'; ?>">
					<div class="text-center max-w-2xl mx-auto mb-8">
						<h2 class="text-2xl md:text-3xl font-bold text-foreground mb-2"><?php echo esc_html( $c['label'] . ' — ' . $c['sublabel'] ); ?></h2>
						<p class="text-muted-foreground"><?php echo esc_html( $c['desc'] ); ?></p>
					</div>

					<?php if ( ! empty( $packages ) ) : ?>
						<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-6 max-w-6xl mx-auto">
							<?php foreach ( $packages as $pkg ) :
								$card_extra = $pkg['is_popular'] ? 'relative border-primary/50 ring-2 ring-primary/20 card-highlight hover-lift' : 'relative hover-lift';
								?>
								<div data-reveal class="relative"<?php echo ( $pkg['is_popular'] && 0 === $i ) ? ' id="most-popular"' : ''; ?>>
									<?php if ( $pkg['is_popular'] ) : ?>
										<div class="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
											<div class="px-4 py-1 rounded-full bg-primary text-primary-foreground text-xs font-bold shadow-xl glow-primary"><?php esc_html_e( 'Most Popular', 'buckleup' ); ?></div>
										</div>
									<?php endif; ?>
									<div class="<?php echo esc_attr( buckleup_card_class( $card_extra ) ); ?>">
										<div class="<?php echo esc_attr( buckleup_card_header_class( 'p-5 pb-0' ) ); ?>">
											<h3 class="text-lg font-bold text-foreground"><?php echo esc_html( $pkg['name'] ); ?></h3>
											<?php if ( ! empty( $pkg['description'] ) ) : ?>
												<p class="text-sm text-muted-foreground"><?php echo esc_html( $pkg['description'] ); ?></p>
											<?php endif; ?>
										</div>
										<div class="<?php echo esc_attr( buckleup_card_content_class( 'p-5 pt-4' ) ); ?>">
											<div class="mb-5 flex items-baseline gap-1">
												<span class="text-3xl font-bold text-foreground">$<?php echo esc_html( rtrim( rtrim( number_format( (float) $pkg['price'], 2 ), '0' ), '.' ) ); ?></span>
												<span class="text-sm text-muted-foreground">/<?php echo esc_html( $pkg['unit'] ?: 'package' ); ?></span>
											</div>
											<?php if ( ! empty( $pkg['features'] ) ) : ?>
												<ul class="space-y-2 mb-5">
													<?php foreach ( $pkg['features'] as $feature ) : ?>
														<li class="flex items-start gap-2">
															<?php echo buckleup_icon( 'check', 'w-4 h-4 text-accent shrink-0 mt-0.5' ); // phpcs:ignore ?>
															<span class="text-sm text-muted-foreground"><?php echo esc_html( $feature ); ?></span>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
											<?php
											buckleup_button( array(
												'label'   => $pkg['cta_label'] ?: __( 'Get Started', 'buckleup' ),
												'href'    => $pkg['whatsapp_link'],
												'class'   => 'w-full rounded-full ' . ( $pkg['is_popular'] ? 'shine glow-primary' : '' ),
												'variant' => $pkg['is_popular'] ? 'default' : 'outline',
												'attrs'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
											) );
											?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<!-- /wp:html -->
<!-- Service offerings from the service CPT -->
<?php if ( ! empty( $services ) ) : ?>
<section class="py-12 md:py-16">
	<div class="container mx-auto px-4">
		<div class="text-center mb-10">
			<h2 data-reveal class="text-3xl md:text-4xl font-bold text-foreground"><?php esc_html_e( 'What We Offer', 'buckleup' ); ?></h2>
		</div>
		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
			<?php foreach ( $services as $svc ) : ?>
				<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight' ) ); ?>">
					<div class="flex items-start justify-between gap-3 mb-3">
						<h3 class="text-lg font-bold text-foreground"><?php echo esc_html( $svc['name'] ); ?></h3>
						<?php if ( ! empty( $svc['price'] ) ) : ?>
							<span class="text-lg font-bold text-primary shrink-0">$<?php echo esc_html( rtrim( rtrim( number_format( (float) $svc['price'], 2 ), '0' ), '.' ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $svc['description'] ) ) : ?>
						<p class="text-sm text-muted-foreground leading-relaxed mb-4 text-pretty"><?php echo esc_html( $svc['description'] ); ?></p>
					<?php endif; ?>
					<div class="flex flex-wrap gap-2">
						<?php if ( ! empty( $svc['duration'] ) ) : ?>
							<?php buckleup_pill( array( 'label' => sprintf( /* translators: %d minutes */ __( '%d min', 'buckleup' ), (int) $svc['duration'] ), 'tone' => 'muted', 'class' => 'text-xs px-3 py-1' ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $svc['type'] ) ) : ?>
							<?php buckleup_pill( array( 'label' => ucwords( strtolower( str_replace( '_', ' ', $svc['type'] ) ) ), 'tone' => 'accent', 'class' => 'text-xs px-3 py-1' ) ); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- Stats -->
<section class="py-12 md:py-16 bg-muted/30">
	<div class="container mx-auto px-4">
		<div data-reveal-stagger="0.05" class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto text-center">
			<?php foreach ( $stats as $s ) : ?>
				<div data-reveal>
					<div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/10 mb-3"><?php echo buckleup_icon( $s['icon'], 'w-7 h-7 text-primary' ); // phpcs:ignore ?></div>
					<div class="text-3xl font-bold text-foreground mb-1"><?php echo esc_html( $s['value'] ); ?></div>
					<div class="text-sm text-muted-foreground"><?php echo esc_html( $s['label'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- Closing CTA -->
<section class="py-16 md:py-20">
	<div class="container mx-auto px-4">
		<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'max-w-3xl mx-auto p-8 md:p-12 text-center items-center' ) ); ?>">
			<h2 class="text-2xl md:text-3xl font-bold text-foreground mb-4"><?php esc_html_e( 'Ready to start your driving journey?', 'buckleup' ); ?></h2>
			<p class="text-muted-foreground mb-6 max-w-xl"><?php esc_html_e( 'Book a free consultation and we’ll map the fastest route to your licence.', 'buckleup' ); ?></p>
			<div class="flex flex-wrap gap-4 justify-center">
				<a href="#most-popular"><?php
					buckleup_button( array( 'label' => __( 'See Pricing', 'buckleup' ), 'size' => 'lg', 'class' => 'rounded-full shine glow-primary', 'icon' => buckleup_icon( 'arrow-right', 'ml-2 h-5 w-5' ) ) );
				?></a>
				<?php
				buckleup_button( array(
					'label'   => __( 'Book Free Consultation', 'buckleup' ),
					'href'    => home_url( '/contact' ),
					'size'    => 'lg',
					'variant' => 'outline',
					'class'   => 'rounded-full',
				) );
				?>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->
