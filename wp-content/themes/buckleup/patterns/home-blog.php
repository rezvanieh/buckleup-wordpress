<?php
/**
 * Title: Blog: Posts Index
 * Slug: buckleup/home-blog
 * Inserter: no
 *
 * The /blog posts index (page_for_posts). Renders the MAIN query's posts as a
 * 2-column card grid (1-col mobile) in a ~1200px container. Each card mirrors
 * buckleup_card_class (bg-card/rounded-xl/border/shadow/hover-lift/card-highlight)
 * but composed inline so it can be edge-to-edge (no py-6/gap-6): a 16:9 cover
 * featured image, date + category, title, line-clamped excerpt, and a "Read
 * more →" link. Below the grid: prominent Previous/Next buttons (buckleup_button_class,
 * outline + primary, arrow icons), centered.
 *
 * Loops the global $wp_query (not a new query) so core pagination (?paged / the
 * pretty /blog/page/N) keeps working. Featured images get WebP via the
 * post_thumbnail_html filter in inc/webp.php.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wp_query;
?>
<!-- wp:html -->
<section class="py-12 md:py-16">
	<div class="container mx-auto px-4 max-w-6xl">

		<!-- Heading -->
		<div class="mb-10 md:mb-12">
			<h1 class="text-4xl md:text-5xl font-bold tracking-tight text-foreground mb-4">
				<?php esc_html_e( 'Driving', 'buckleup' ); ?> <span class="text-primary"><?php esc_html_e( 'Insights', 'buckleup' ); ?></span> <?php esc_html_e( '&amp; News', 'buckleup' ); ?>
			</h1>
			<p class="text-muted-foreground text-lg max-w-2xl">
				<?php esc_html_e( "Expert driving tips, ICBC road test advice, and the latest news from Vancouver's top-rated driving school.", 'buckleup' ); ?>
			</p>
		</div>

		<?php if ( have_posts() ) : ?>

			<!-- Card grid: 1-col mobile, 2-col from md -->
			<div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
				<?php
				while ( have_posts() ) :
					the_post();
					$cat_list = get_the_category_list( ', ' );
					?>
					<?php
					// Card visual matches buckleup_card_class (bg-card/border/rounded/shadow)
					// but WITHOUT its py-6/gap-6 — we want an edge-to-edge cover image and
					// our own inner padding, so we compose the classes directly here.
					$card_cls = 'group flex flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-lg shadow-black/5 dark:shadow-black/20 transition-shadow duration-300 hover-lift card-highlight';
					?>
					<article class="<?php echo esc_attr( $card_cls ); ?>">
						<a href="<?php the_permalink(); ?>" class="block aspect-[16/9] overflow-hidden bg-muted" aria-hidden="true" tabindex="-1">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php
								the_post_thumbnail( 'large', array(
									'class'   => 'w-full h-full object-cover transition-transform duration-500 group-hover:scale-105',
									'loading' => 'lazy',
									'alt'     => the_title_attribute( array( 'echo' => false ) ),
								) );
								?>
							<?php else : ?>
								<!-- Graceful fallback keeps cards uniform 16:9 when a post has no image. -->
								<span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/10 to-accent/10 text-primary/40">
									<?php echo buckleup_icon( 'book-open', 'h-12 w-12' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — icon helper returns safe SVG ?>
								</span>
							<?php endif; ?>
						</a>

						<div class="p-6 flex flex-col gap-3 flex-1">
							<div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<?php if ( $cat_list ) : ?>
									<span aria-hidden="true">&middot;</span>
									<span class="text-primary"><?php echo wp_kses_post( $cat_list ); ?></span>
								<?php endif; ?>
							</div>

							<h2 class="text-xl md:text-2xl font-bold leading-snug tracking-tight text-foreground">
								<a href="<?php the_permalink(); ?>" class="text-foreground no-underline transition-colors duration-200 hover:text-primary hover:underline decoration-2 underline-offset-4 focus-visible:text-primary"><?php the_title(); ?></a>
							</h2>

							<p class="text-sm text-muted-foreground line-clamp-3">
								<?php echo esc_html( wp_trim_words( get_the_excerpt(), 28, '&hellip;' ) ); ?>
							</p>

							<a href="<?php the_permalink(); ?>" class="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold text-primary no-underline pt-2">
								<?php esc_html_e( 'Read more', 'buckleup' ); ?>
								<?php echo buckleup_icon( 'arrow-right', 'h-4 w-4 transition-transform group-hover:translate-x-0.5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — icon helper returns safe SVG ?>
							</a>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php
			// Prominent Previous/Next, centered. get_*_posts_link() returns '' when
			// there's no adjacent page, so a single button centers on its own.
			$prev = get_previous_posts_link(
				buckleup_icon( 'chevron-left', 'h-5 w-5' ) . '<span>' . esc_html__( 'Newer posts', 'buckleup' ) . '</span>'
			);
			$next = get_next_posts_link(
				'<span>' . esc_html__( 'Older posts', 'buckleup' ) . '</span>' . buckleup_icon( 'arrow-right', 'h-5 w-5' ),
				$wp_query->max_num_pages
			);
			if ( $prev || $next ) :
				// Apply the button class string to the <a> core emits.
				$outline_cls = buckleup_button_class( 'outline', 'lg', 'no-underline' );
				$primary_cls = buckleup_button_class( 'default', 'lg', 'no-underline' );
				$prev = $prev ? str_replace( '<a ', '<a class="' . esc_attr( $outline_cls ) . '" ', $prev ) : '';
				$next = $next ? str_replace( '<a ', '<a class="' . esc_attr( $primary_cls ) . '" ', $next ) : '';
				?>
				<nav class="mt-12 flex items-center justify-center gap-4" aria-label="<?php esc_attr_e( 'Posts pagination', 'buckleup' ); ?>">
					<?php
					// Trusted markup: core's get_*_posts_link() (escaped href) + our
					// buckleup_icon() SVG + esc_html__ label. Echoed raw rather than via
					// wp_kses_post(), which strips the inline <svg>/<path> icons — matching
					// how buckleup_icon output is treated elsewhere in this theme.
					echo $prev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $next; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<p class="text-muted-foreground"><?php esc_html_e( 'No posts yet. Check back soon.', 'buckleup' ); ?></p>
		<?php endif; ?>

	</div>
</section>
<!-- /wp:html -->
