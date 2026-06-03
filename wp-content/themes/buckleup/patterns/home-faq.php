<?php
/**
 * Title: Home: FAQ
 * Slug: buckleup/home-faq
 * Inserter: no
 *
 * Reproduces src/components/landing/FAQ.tsx: eyebrow "FAQ" (help icon), h2
 * "Common Questions" (gradient span), the accordion (rendered by
 * buckleup_faq_accordion from the SAME buckleup_get_faqs() source the SEO
 * mu-plugin uses for FAQPage JSON-LD), and the "Still have questions? Contact us"
 * footer line. Anchor id="faq" so the nav/footer "FAQ" links land here.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$faqs = function_exists( 'buckleup_get_faqs' ) ? buckleup_get_faqs() : array();
if ( empty( $faqs ) ) {
	return;
}

$items = array_map( static function ( $f ) {
	return array( 'question' => $f['question'], 'answer' => wpautop( esc_html( $f['answer'] ) ) );
}, $faqs );
?>
<!-- wp:html -->
<section id="faq" class="py-20 md:py-28 relative">
	<div class="container mx-auto px-4">
		<div class="max-w-3xl mx-auto">
			<div class="text-center mb-12">
				<div data-reveal class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass border border-border/50 mb-4">
					<?php echo buckleup_icon( 'message-circle', 'w-4 h-4 text-primary' ); // phpcs:ignore ?>
					<span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'FAQ', 'buckleup' ); ?></span>
				</div>
				<h2 data-reveal class="text-4xl md:text-5xl font-bold mb-4">
					<span class="text-foreground"><?php esc_html_e( 'Common ', 'buckleup' ); ?></span><span class="gradient-text"><?php esc_html_e( 'Questions', 'buckleup' ); ?></span>
				</h2>
			</div>

			<div data-reveal>
				<?php buckleup_faq_accordion( array( 'items' => $items, 'first_open' => true ) ); ?>
			</div>

			<p data-reveal class="text-center text-muted-foreground mt-8">
				<?php esc_html_e( 'Still have questions?', 'buckleup' ); ?>
				<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="text-primary font-medium hover:underline"><?php esc_html_e( 'Contact us', 'buckleup' ); ?></a>
				<?php esc_html_e( "and we'll be happy to help.", 'buckleup' ); ?>
			</p>
		</div>
	</div>
</section>
<!-- /wp:html -->
