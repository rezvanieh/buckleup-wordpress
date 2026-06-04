<?php
/**
 * Title: Home: Pricing
 * Slug: buckleup/home-pricing
 * Inserter: no
 *
 * Reproduces src/components/landing/Pricing.tsx, driven by the `package` CPT via
 * buckleup_get_packages() (price, unit, features, popular, cta_label, and the
 * server-built whatsapp_link). Eyebrow "Simple Pricing", h2 "Invest in Your
 * Future" (gradient span), 1/2/3-col grid, the "Most Popular" badge + #most-popular
 * anchor (and #single-session on the single lesson), Check-bulleted features, and
 * the WhatsApp CTA per plan.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$packages = function_exists( 'buckleup_get_packages' ) ? buckleup_get_packages() : array();
if ( empty( $packages ) ) {
	return;
}
?>
<!-- wp:html -->
<section id="pricing" class="py-12 md:py-16 relative">
	<div class="container mx-auto px-4">
		<div class="text-center mb-12">
			<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
				<?php echo buckleup_icon( 'star', 'w-4 h-4 text-accent' ); // phpcs:ignore ?>
				<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Simple Pricing', 'buckleup' ); ?></span>
			</div>
			<h2 data-reveal class="text-4xl md:text-5xl font-bold mb-4">
				<span class="text-foreground"><?php esc_html_e( 'Invest in Your ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Future', 'buckleup' ); ?></span>
			</h2>
			<p data-reveal class="text-muted-foreground max-w-2xl mx-auto">
				<?php esc_html_e( 'Transparent pricing with no hidden fees. Choose the package that fits your needs.', 'buckleup' ); ?>
			</p>
		</div>

		<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-6 max-w-6xl mx-auto">
			<?php foreach ( $packages as $pkg ) :
				$is_single = ( ( $pkg['unit'] ?? '' ) === 'lesson' );
				$anchor    = $pkg['is_popular'] ? 'most-popular' : ( $is_single ? 'single-session' : '' );
				$card_extra = $pkg['is_popular']
					? 'relative border-primary/50 ring-2 ring-primary/20 lg:scale-105 card-highlight hover-lift'
					: 'relative hover-lift';
				?>
				<div data-reveal class="relative">
					<?php if ( $pkg['is_popular'] ) : ?>
						<div class="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
							<div class="px-4 py-1 rounded-full bg-primary text-primary-foreground text-xs font-bold shadow-xl glow-primary"><?php esc_html_e( 'Most Popular', 'buckleup' ); ?></div>
						</div>
					<?php endif; ?>

					<div class="<?php echo esc_attr( buckleup_card_class( $card_extra ) ); ?>">
						<div class="<?php echo esc_attr( buckleup_card_header_class( 'p-5 pb-0' ) ); ?>"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
							<h3 class="text-lg font-bold text-foreground"><?php echo esc_html( $pkg['name'] ); ?></h3>
							<?php if ( ! empty( $pkg['description'] ) ) : ?>
								<p class="text-sm text-muted-foreground"><?php echo esc_html( $pkg['description'] ); ?></p>
							<?php endif; ?>
						</div>

						<div class="<?php echo esc_attr( buckleup_card_content_class( 'p-5 pt-4' ) ); ?>">
							<div class="mb-5">
								<div class="flex items-baseline gap-1">
									<span class="text-3xl font-bold text-foreground">$<?php echo esc_html( rtrim( rtrim( number_format( (float) $pkg['price'], 2 ), '0' ), '.' ) ); ?></span>
									<span class="text-sm text-muted-foreground">/<?php echo esc_html( $pkg['unit'] ?: 'package' ); ?></span>
								</div>
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
								'label' => $pkg['cta_label'] ?: __( 'Get Started', 'buckleup' ),
								'href'  => $pkg['whatsapp_link'],
								'class' => 'w-full rounded-full ' . ( $pkg['is_popular'] ? 'shine glow-primary' : '' ),
								'variant' => $pkg['is_popular'] ? 'default' : 'outline',
								'attrs' => array( 'target' => '_blank', 'rel' => 'noopener' ),
							) );
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<!-- /wp:html -->
