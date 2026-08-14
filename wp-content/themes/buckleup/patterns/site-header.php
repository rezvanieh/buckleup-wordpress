<?php
/**
 * Title: Site Header
 * Slug: buckleup/site-header
 * Inserter: no
 *
 * Reproduces src/components/layout/Navbar.tsx (signed-out marketing state):
 * scroll-aware glass header (data-scrolled, set by navbar.js at scrollY>20), logo
 * theme-swap, the full inline nav pill at min-[1280px] with a Locations dropdown
 * (below that — tablet + mobile, <1280 — a compact header keeps both CTAs visible
 * from min-[1100px] and folds the nav + Sign In into the hamburger, so the bar
 * never wraps/overflows), a 2-state theme toggle, the mobile hamburger + slide-down
 * menu, and the signed-out mobile bottom tab bar + WhatsApp FAB. Heights h-16
 * min-[1100px]:h-32 (the source/Image #11 value — gives the h-16 logo ~32px
 * balanced top/bottom clearance) with the 500ms transition; logo h-8
 * min-[1100px]:h-16. The fixed-header spacer below mirrors the same height.
 *
 * Desktop fit: the full 7-item inline nav + Locations + theme toggle + Sign In +
 * 2 CTAs measured ~1587px wide — too wide for the standard .container cap (1248px
 * at xl / 1504px at 2xl), which is why it used to be pushed to min-[1680px] (and
 * even there it overflowed). To land the full inline nav at the standard desktop
 * breakpoint min-[1280px] WITHOUT overflow we: (1) widen the header row to a
 * max-w-[1600px] wrapper (vs the narrower .container) so the bar has room; (2) drop
 * the per-item lucide icons from the DESKTOP inline nav (text labels only) and
 * tighten item padding/gaps; (3) fold the signed-out "Sign In" into a compact
 * icon-only button beside the theme toggle. The MOBILE slide-down menu keeps its
 * per-item icons + the full-text Sign In, per the original Navbar. v1 = marketing
 * only (no auth/portal UI).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$nav        = buckleup_nav_items();
$locations  = buckleup_location_items();
$services   = function_exists( 'buckleup_service_items' ) ? buckleup_service_items() : array();
$quiz_cta    = function_exists( 'buckleup_quiz_nav_cta' ) ? buckleup_quiz_nav_cta() : null;
$wa         = function_exists( 'buckleup_get_setting' ) ? buckleup_get_setting( 'whatsapp', '16044413677' ) : '16044413677';
// Generic CTAs carry a prefilled message (production parity).
$wa_link    = 'https://wa.me/' . preg_replace( '/\D/', '', $wa ) . '?text=' . rawurlencode( "Hi, I'm interested in driving lessons." );
// Tel: link for the mobile Contact tab — the same number as WhatsApp.
$tel_link   = 'tel:+' . preg_replace( '/\D/', '', $wa );

// Auth state for the Sign In link / user menu.
$is_logged_in   = is_user_logged_in();
$dashboard_url  = function_exists( 'buckleup_dashboard_url' ) ? buckleup_dashboard_url() : home_url( '/student/' );
$current_user   = $is_logged_in ? wp_get_current_user() : null;
$user_name      = $current_user ? ( $current_user->display_name ?: $current_user->user_login ) : '';
$logout_url     = wp_logout_url( home_url() );
?>
<!-- wp:html -->
<header data-navbar data-scrolled="false"
	class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 data-[scrolled=false]:bg-background/80 data-[scrolled=false]:backdrop-blur-xl data-[scrolled=true]:bg-background/95 data-[scrolled=true]:backdrop-blur-2xl data-[scrolled=true]:border-b data-[scrolled=true]:border-border data-[scrolled=true]:shadow-lg data-[scrolled=true]:shadow-black/5 dark:data-[scrolled=true]:shadow-black/20">
	<!-- Wider-than-.container wrapper (max-w-[1600px] vs the 1248/1504px container
	     caps) so the full inline nav fits at min-[1280px] without overflow. Keeps
	     px-4 so the logo's left edge stays aligned with page content. -->
	<div class="mx-auto max-w-[1600px] px-4">
		<div class="flex items-center justify-between h-16 min-[1100px]:h-32 transition-all duration-500">

			<!-- Logo. Desktop/tablet height trimmed h-16 -> h-14 (the wide wordmark is
			     the single widest header element, ~328px at h-16) to give the full
			     inline nav room to fit at min-[1280px] without overflow. Stays keyed off
			     the same min-[1100px] boundary as the header height; mobile stays h-8 and
			     the h-32 header/spacer height is unchanged (slightly more even vertical
			     clearance around the logo). -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-0 shrink-0" aria-label="<?php esc_attr_e( 'BuckleUp home', 'buckleup' ); ?>">
				<?php echo buckleup_logo( 'h-8 min-[1100px]:h-14 w-auto transition-all duration-500' ); // phpcs:ignore WordPress.Security.EscapeOutput — escaped within ?>
			</a>

			<!-- Desktop inline nav pill. Shown at min-[1280px] (standard desktop/laptop)
			     so common 1280–1600px screens get the FULL inline nav, not the compact
			     hamburger header. Below 1280 (tablet + mobile) the nav + Sign In fold
			     into the hamburger. To fit at 1280 without overflow the desktop items
			     carry NO icon (text only), use px-2.5 padding, and the Sign In link is
			     a compact icon-only button in the right cluster. Both CTAs (emerald
			     pill + Book) stay visible from min-[1100px]. Header HEIGHT stays keyed
			     off min-[1100px]. The mobile menu keeps the per-item icons. -->
			<nav aria-label="<?php esc_attr_e( 'Primary', 'buckleup' ); ?>" class="hidden min-[1280px]:flex items-center">
				<div class="flex items-center gap-0 px-0.5 py-1 rounded-2xl bg-muted/60 backdrop-blur-sm border border-border/50">
					<?php
					foreach ( $nav as $item ) :
						$is_active  = ! empty( $item['active'] );
						// Active = the white rounded "box" (source Navbar.tsx). Desktop inline
						// nav is text-only (icons dropped to fit at 1280); the mobile menu
						// below keeps each item's icon.
						$item_class = $is_active
							? 'text-foreground bg-background shadow-sm border border-border/50'
							: 'text-muted-foreground hover:text-foreground hover:bg-background/50';

						// Services carries a dropdown of its cluster pages (Hub 1). The trigger
						// stays a real <a> to the pillar — hover opens the menu, click still
						// goes to /services/ (overlays.js skips preventDefault for link
						// triggers). Falls back to a plain link when there are no clusters.
						if ( 'Services' === $item['name'] && ! empty( $services ) ) :
							?>
							<div data-dropdown data-dropdown-hover class="relative">
								<a href="<?php echo esc_url( $item['href'] ); ?>" data-dropdown-trigger
									aria-expanded="false" aria-haspopup="true"
									class="relative flex items-center gap-1 px-2 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all duration-200 <?php echo esc_attr( $item_class ); ?>"
									<?php echo $is_active ? 'aria-current="page"' : ''; ?>>
									<span><?php echo esc_html( $item['name'] ); ?></span>
									<?php echo buckleup_icon( 'chevron-down', 'w-3.5 h-3.5' ); // phpcs:ignore ?>
								</a>
								<div data-dropdown-content data-state="closed" data-side="bottom" hidden
									class="absolute top-full left-0 mt-2 w-64 bg-card/98 backdrop-blur-2xl border border-border rounded-xl shadow-xl shadow-black/10 py-1 overflow-hidden z-50 <?php echo esc_attr( buckleup_dropdown_content_class() ); ?>">
									<a href="<?php echo esc_url( $item['href'] ); ?>"
										class="block px-4 py-2.5 text-sm font-medium text-foreground hover:bg-muted transition-colors border-b border-border/60">
										<?php esc_html_e( 'All lessons &amp; packages', 'buckleup' ); ?>
									</a>
									<?php foreach ( $services as $svc ) : ?>
										<a href="<?php echo esc_url( $svc['href'] ); ?>"
											class="block px-4 py-2.5 text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
											<?php echo esc_html( $svc['name'] ); ?>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
							<?php
							continue;
						endif;
						?>
						<a href="<?php echo esc_url( $item['href'] ); ?>"
							class="relative flex items-center px-2 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all duration-200 <?php echo esc_attr( $item_class ); ?>"
							<?php echo $is_active ? 'aria-current="page"' : ''; ?>>
							<span><?php echo esc_html( $item['name'] ); ?></span>
						</a>
					<?php endforeach; ?>

					<!-- Locations dropdown (text + chevron only on desktop; map-pin dropped) -->
					<?php
					$loc_active     = function_exists( 'buckleup_path_is' ) && buckleup_path_is( 'locations', true );
					$loc_trig_class = $loc_active
						? 'text-foreground bg-background shadow-sm border border-border/50'
						: 'text-muted-foreground hover:text-foreground hover:bg-background/50';
					?>
					<div data-dropdown data-dropdown-hover class="relative">
						<button type="button" data-dropdown-trigger aria-expanded="false" aria-haspopup="true"
							class="relative flex items-center gap-1 px-2 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all duration-200 <?php echo esc_attr( $loc_trig_class ); ?>">
							<?php esc_html_e( 'Locations', 'buckleup' ); ?>
							<?php echo buckleup_icon( 'chevron-down', 'w-3.5 h-3.5' ); // phpcs:ignore ?>
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

			<!-- Right cluster: primary CTA (desktop) + hamburger (mobile).
			     The dark-mode sun/moon toggle was removed — the site is light-only
			     (dark mode was broken and unwanted). -->
			<div class="flex items-center gap-1.5">
				<?php if ( $is_logged_in ) : ?>
					<!-- Logged-in: user menu (Dashboard + Sign out). Folds into the
					     hamburger below 1280 (where the inline nav also lives). -->
					<div data-dropdown class="relative hidden min-[1280px]:block">
						<button type="button" data-dropdown-trigger aria-expanded="false" aria-haspopup="true"
							class="flex items-center gap-2 p-1.5 pr-3 rounded-full text-sm font-medium text-foreground hover:bg-muted transition-colors border border-border/50">
							<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary/10 text-primary"><?php echo buckleup_icon( 'user', 'size-4' ); // phpcs:ignore ?></span>
							<span class="max-w-[8rem] truncate"><?php echo esc_html( $user_name ); ?></span>
							<?php echo buckleup_icon( 'chevron-down', 'size-4' ); // phpcs:ignore ?>
						</button>
						<div data-dropdown-content data-state="closed" data-side="bottom" hidden
							class="absolute right-0 top-full mt-2 w-48 bg-card/98 backdrop-blur-2xl border border-border rounded-xl shadow-xl shadow-black/10 py-1 overflow-hidden z-50 <?php echo esc_attr( buckleup_dropdown_content_class() ); ?>">
							<a href="<?php echo esc_url( $dashboard_url ); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
								<?php echo buckleup_icon( 'layout-dashboard', 'size-4' ); // phpcs:ignore ?><?php esc_html_e( 'Dashboard', 'buckleup' ); ?>
							</a>
							<a href="<?php echo esc_url( $logout_url ); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
								<?php echo buckleup_icon( 'log-out', 'size-4' ); // phpcs:ignore ?><?php esc_html_e( 'Sign out', 'buckleup' ); ?>
							</a>
						</div>
					</div>
				<?php else : ?>
					<!-- Logged-out: Sign In as a COMPACT ICON-ONLY button beside the theme
					     toggle (saves ~70px vs the text pill so the full inline nav fits at
					     1280). The accessible label keeps it announced; the mobile menu has
					     the full-text "Sign In". Folds into the hamburger below 1280. -->
					<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" aria-label="<?php esc_attr_e( 'Sign In', 'buckleup' ); ?>" title="<?php esc_attr_e( 'Sign In', 'buckleup' ); ?>"
						class="hidden min-[1280px]:inline-flex items-center justify-center p-2 rounded-xl text-muted-foreground hover:text-foreground hover:bg-muted transition-colors border border-border/50">
						<?php echo buckleup_icon( 'user', 'size-5' ); // phpcs:ignore ?>
						<span class="sr-only"><?php esc_html_e( 'Sign In', 'buckleup' ); ?></span>
					</a>
				<?php endif; ?>

					<?php if ( $quiz_cta ) : ?>
						<!-- Free Practice Test — solid emerald pill (single line), distinct from the red Book button. -->
						<a href="<?php echo esc_url( $quiz_cta['href'] ); ?>" data-active="<?php echo $quiz_cta['active'] ? 'true' : 'false'; ?>"
							class="hidden min-[1100px]:inline-flex items-center gap-1.5 h-10 px-3.5 rounded-full text-sm font-semibold whitespace-nowrap text-accent-foreground bg-accent hover:bg-accent/90 transition-colors shadow-sm"
							<?php echo $quiz_cta['active'] ? 'aria-current="page"' : ''; ?>>
							<?php echo buckleup_icon( 'graduation-cap', 'w-4 h-4 shrink-0' ); // phpcs:ignore ?>
							<span class="whitespace-nowrap"><?php echo esc_html( $quiz_cta['label'] ); ?></span>
						</a>
					<?php endif; ?>

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
					class="min-[1280px]:hidden p-2 rounded-xl hover:bg-muted transition-colors border border-border/50">
					<?php echo buckleup_icon( 'menu', 'size-6' ); // phpcs:ignore ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Mobile / tablet slide-down menu (below the min-[1280px] desktop bp) -->
	<div data-nav-mobile data-state="closed" hidden
		class="min-[1280px]:hidden bg-background/98 backdrop-blur-2xl border-b border-border overflow-hidden data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:slide-in-from-top-2">
		<nav aria-label="<?php esc_attr_e( 'Mobile', 'buckleup' ); ?>" class="container mx-auto px-4 py-4 flex flex-col gap-1">
			<?php if ( $quiz_cta ) : ?>
				<!-- Free Practice Test — full-width emerald CTA, pinned at the top of the mobile menu. -->
				<a href="<?php echo esc_url( $quiz_cta['href'] ); ?>" data-active="<?php echo $quiz_cta['active'] ? 'true' : 'false'; ?>"
					class="flex items-center justify-center gap-2 px-4 py-3 mb-2 rounded-xl text-base font-semibold text-accent bg-accent/10 border border-accent/30 hover:bg-accent/15 transition-colors data-[active=true]:bg-accent data-[active=true]:text-accent-foreground"
					<?php echo $quiz_cta['active'] ? 'aria-current="page"' : ''; ?>>
					<?php echo buckleup_icon( 'graduation-cap', 'w-5 h-5' ); // phpcs:ignore ?>
					<span><?php echo esc_html( $quiz_cta['label'] ); ?></span>
				</a>
			<?php endif; ?>
			<?php
			foreach ( $nav as $item ) :
				$m_active = ! empty( $item['active'] );
				$m_class  = $m_active ? 'text-primary bg-primary/10' : 'text-muted-foreground hover:text-foreground hover:bg-muted';
				?>
				<a href="<?php echo esc_url( $item['href'] ); ?>"
					class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium transition-colors <?php echo esc_attr( $m_class ); ?>"
					<?php echo $m_active ? 'aria-current="page"' : ''; ?>>
					<?php if ( ! empty( $item['icon'] ) ) { echo buckleup_icon( $item['icon'], 'w-5 h-5' . ( $m_active ? ' text-primary' : '' ) ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span><?php echo esc_html( $item['name'] ); ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ( ! empty( $services ) ) : ?>
				<?php // Touch devices never fire hover, so the desktop dropdown is unreachable
					  // by tap — the cluster pages are listed here instead. ?>
				<div class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"><?php esc_html_e( 'Lessons', 'buckleup' ); ?></div>
				<?php foreach ( $services as $svc ) : ?>
					<a href="<?php echo esc_url( $svc['href'] ); ?>"
						class="px-4 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
						<?php echo esc_html( $svc['name'] ); ?>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
			<div class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"><?php esc_html_e( 'Locations', 'buckleup' ); ?></div>
			<?php foreach ( $locations as $loc ) : ?>
				<a href="<?php echo esc_url( $loc['href'] ); ?>"
					class="px-4 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
					<?php echo esc_html( $loc['name'] ); ?>
				</a>
			<?php endforeach; ?>
			<!-- Auth (mobile) -->
			<div class="mt-2 pt-2 border-t border-border flex flex-col gap-1">
				<?php if ( $is_logged_in ) : ?>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" class="flex items-center gap-2 px-4 py-3 rounded-xl text-base font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"><?php echo buckleup_icon( 'layout-dashboard', 'size-5' ); // phpcs:ignore ?><?php esc_html_e( 'Dashboard', 'buckleup' ); ?></a>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="flex items-center gap-2 px-4 py-3 rounded-xl text-base font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"><?php echo buckleup_icon( 'log-out', 'size-5' ); // phpcs:ignore ?><?php esc_html_e( 'Sign out', 'buckleup' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="flex items-center gap-2 px-4 py-3 rounded-xl text-base font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"><?php echo buckleup_icon( 'user', 'size-5' ); // phpcs:ignore ?><?php esc_html_e( 'Sign In', 'buckleup' ); ?></a>
				<?php endif; ?>
			</div>

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
			<?php echo buckleup_icon( 'home', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Home', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( home_url( '/services' ) ); ?>" class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-muted-foreground hover:text-primary transition-colors">
			<?php echo buckleup_icon( 'star', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Services', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $tel_link ); ?>" class="flex flex-col items-center gap-0.5 px-3 py-1.5 text-muted-foreground hover:text-primary transition-colors">
			<?php echo buckleup_icon( 'phone', 'size-5' ); // phpcs:ignore ?>
			<span class="text-[10px] font-medium"><?php esc_html_e( 'Call', 'buckleup' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener" data-mobile-fab aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'buckleup' ); ?>"
			class="w-14 h-14 bg-gradient-to-br from-[#25D366] to-[#128C7E] rounded-2xl flex items-center justify-center shadow-xl shadow-[#25D366]/30 text-white shrink-0">
			<?php echo buckleup_icon( 'message-circle', 'size-7' ); // phpcs:ignore ?>
			<span class="sr-only"><?php esc_html_e( 'Chat on WhatsApp', 'buckleup' ); ?></span>
		</a>
	</div>
</div>
<!-- /wp:html -->
