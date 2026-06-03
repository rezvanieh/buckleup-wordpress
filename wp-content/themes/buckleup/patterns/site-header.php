<?php
/**
 * Title: Site Header
 * Slug: buckleup/site-header
 * Inserter: no
 *
 * Reproduces src/components/layout/Navbar.tsx (signed-out marketing state):
 * scroll-aware glass header (data-scrolled, set by navbar.js at scrollY>20), logo
 * theme-swap, desktop nav pill at min-[1100px] with a Locations dropdown, a 2-state
 * theme toggle, the mobile hamburger + slide-down menu, and the signed-out mobile
 * bottom tab bar + WhatsApp FAB. Heights h-16 min-[1100px]:h-32 with the 500ms
 * transition; logo h-8 min-[1100px]:h-16. v1 = marketing only (no auth/portal UI).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$nav        = buckleup_nav_items();
$locations  = buckleup_location_items();
$wa         = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677';
// Generic CTAs carry a prefilled message (production parity).
$wa_link    = 'https://wa.me/' . preg_replace( '/\D/', '', $wa ) . '?text=' . rawurlencode( "Hi, I'm interested in driving lessons." );
?>
<!-- wp:html -->
<header data-navbar data-scrolled="false"
	class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 data-[scrolled=false]:bg-background/80 data-[scrolled=false]:backdrop-blur-xl data-[scrolled=true]:bg-background/95 data-[scrolled=true]:backdrop-blur-2xl data-[scrolled=true]:border-b data-[scrolled=true]:border-border data-[scrolled=true]:shadow-lg data-[scrolled=true]:shadow-black/5 dark:data-[scrolled=true]:shadow-black/20">
	<div class="container mx-auto px-4">
		<div class="flex items-center justify-between h-16 min-[1100px]:h-32 transition-all duration-500">

			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-0 shrink-0" aria-label="<?php esc_attr_e( 'BuckleUp home', 'buckleup' ); ?>">
				<?php echo buckleup_logo(); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within ?>
			</a>

			<!-- Desktop nav pill (min-[1100px]) -->
			<nav aria-label="<?php esc_attr_e( 'Primary', 'buckleup' ); ?>" class="hidden min-[1100px]:flex items-center">
				<div class="flex items-center gap-0.5 px-1.5 py-1 rounded-2xl bg-muted/60 backdrop-blur-sm border border-border/50">
					<?php foreach ( $nav as $item ) : ?>
						<a href="<?php echo esc_url( $item['href'] ); ?>"
							class="relative px-4 py-2 rounded-xl text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-background/60 transition-all">
							<?php echo esc_html( $item['name'] ); ?>
						</a>
					<?php endforeach; ?>

					<!-- Locations dropdown -->
					<div data-dropdown data-dropdown-hover class="relative">
						<button type="button" data-dropdown-trigger aria-expanded="false" aria-haspopup="true"
							class="relative flex items-center gap-1 px-4 py-2 rounded-xl text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-background/60 transition-all">
							<?php echo buckleup_icon( 'map-pin', 'size-4' ); // phpcs:ignore ?>
							<?php esc_html_e( 'Locations', 'buckleup' ); ?>
							<?php echo buckleup_icon( 'chevron-down', 'size-4' ); // phpcs:ignore ?>
						</button>
						<div data-dropdown-content data-state="closed" data-side="bottom" hidden
							class="absolute top-full left-0 mt-2 w-48 bg-card/98 backdrop-blur-2xl border border-border rounded-xl shadow-xl shadow-black/10 py-1 overflow-hidden z-50 <?php echo esc_attr( buckleup_dropdown_content_class() ); ?>">
							<?php foreach ( $locations as $loc ) : ?>
								<a href="<?php echo esc_url( $loc['href'] ); ?>"
									class="block px-4 py-2.5 text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
									<?php echo esc_html( $loc['name'] ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</nav>

			<!-- Right cluster: theme toggle + primary CTA (desktop) + hamburger (mobile) -->
			<div class="flex items-center gap-2">
				<button type="button" data-theme-toggle aria-label="<?php esc_attr_e( 'Toggle theme', 'buckleup' ); ?>"
					class="p-2.5 rounded-xl text-muted-foreground hover:text-foreground hover:bg-muted transition-colors border border-border/50">
					<span class="hidden dark:inline"><?php echo buckleup_icon( 'sun', 'size-5' ); // phpcs:ignore ?></span>
					<span class="inline dark:hidden"><?php echo buckleup_icon( 'moon', 'size-5' ); // phpcs:ignore ?></span>
				</button>

				<div class="hidden min-[1100px]:block">
					<?php
					buckleup_button( array(
						'label'   => __( 'Book a Lesson', 'buckleup' ),
						'href'    => $wa_link,
						'size'    => 'lg',
						'class'   => 'rounded-full shine glow-primary',
						'icon'    => buckleup_icon( 'arrow-right', 'size-4' ),
						'attrs'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
					) );
					?>
				</div>

				<button type="button" data-nav-toggle aria-expanded="false" aria-label="<?php esc_attr_e( 'Open menu', 'buckleup' ); ?>"
					class="min-[1100px]:hidden p-2 rounded-xl hover:bg-muted transition-colors border border-border/50">
					<?php echo buckleup_icon( 'menu', 'size-6' ); // phpcs:ignore ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Mobile slide-down menu -->
	<div data-nav-mobile data-state="closed" hidden
		class="min-[1100px]:hidden bg-background/98 backdrop-blur-2xl border-b border-border overflow-hidden data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:slide-in-from-top-2">
		<nav aria-label="<?php esc_attr_e( 'Mobile', 'buckleup' ); ?>" class="container mx-auto px-4 py-4 flex flex-col gap-1">
			<?php foreach ( $nav as $item ) : ?>
				<a href="<?php echo esc_url( $item['href'] ); ?>"
					class="px-4 py-3 rounded-xl text-base font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
					<?php echo esc_html( $item['name'] ); ?>
				</a>
			<?php endforeach; ?>
			<div class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"><?php esc_html_e( 'Locations', 'buckleup' ); ?></div>
			<?php foreach ( $locations as $loc ) : ?>
				<a href="<?php echo esc_url( $loc['href'] ); ?>"
					class="px-4 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
					<?php echo esc_html( $loc['name'] ); ?>
				</a>
			<?php endforeach; ?>
			<div class="px-2 pt-3">
				<?php
				buckleup_button( array(
					'label' => __( 'Book a Lesson', 'buckleup' ),
					'href'  => $wa_link,
					'size'  => 'lg',
					'class' => 'w-full rounded-xl shine glow-primary',
					'icon'  => buckleup_icon( 'arrow-right', 'size-4' ),
					'attrs' => array( 'target' => '_blank', 'rel' => 'noopener' ),
				) );
				?>
			</div>
		</nav>
	</div>
</header>

<!-- Spacer matching the fixed header height -->
<div class="h-16 min-[1100px]:h-32 transition-all duration-500" aria-hidden="true"></div>

<!-- Signed-out mobile bottom tab bar + WhatsApp FAB -->
<div class="fixed bottom-0 left-0 right-0 z-40 min-[1100px]:hidden pointer-events-none">
	<div data-mobile-tabbar class="mx-3 mb-3 p-2 rounded-2xl bg-card/95 backdrop-blur-2xl border border-border shadow-2xl shadow-black/10 dark:shadow-black/30 flex items-center justify-around pointer-events-auto">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-muted-foreground hover:text-primary transition-colors">
			<?php echo buckleup_icon( 'map-pin', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Locations', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( home_url( '/services' ) ); ?>" class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-muted-foreground hover:text-primary transition-colors">
			<?php echo buckleup_icon( 'star', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Services', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-muted-foreground hover:text-primary transition-colors">
			<?php echo buckleup_icon( 'phone', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Contact', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener" data-mobile-fab aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'buckleup' ); ?>"
			class="w-14 h-14 bg-gradient-to-br from-[#25D366] to-[#128C7E] rounded-2xl flex items-center justify-center shadow-xl shadow-[#25D366]/30 text-white shrink-0">
			<?php echo buckleup_icon( 'message-circle', 'size-7' ); // phpcs:ignore ?>
			<span class="sr-only"><?php esc_html_e( 'Chat on WhatsApp', 'buckleup' ); ?></span>
		</a>
	</div>
</div>
<!-- /wp:html -->
