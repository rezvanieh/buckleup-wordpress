<?php
/**
 * Title: Page: Resources
 * Slug: buckleup/page-resources
 * Inserter: no
 *
 * Resources index, reproducing src/app/resources/page.tsx: hero ("Student
 * Resources"), Downloadable Materials (3 category cards with item lists),
 * Latest Articles & Guides (4 article cards — the ICBC road-test guide links to
 * the real /resources/icbc-road-test-failures article owned by content), and the
 * "Practice Quizzes Coming Soon" card. Placeholder download items match the source
 * (they're sample materials, not live files — non-functional download buttons, as
 * on the live site).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$categories = array(
	array(
		'title' => __( 'Knowledge Test Preparation', 'buckleup' ),
		'items' => array(
			array( 'name' => 'ICBC Practice Questions', 'type' => 'PDF', 'size' => '2.5 MB' ),
			array( 'name' => 'Road Signs Guide', 'type' => 'PDF', 'size' => '4.1 MB' ),
			array( 'name' => 'Rules of the Road Summary', 'type' => 'PDF', 'size' => '1.8 MB' ),
		),
	),
	array(
		'title' => __( 'Video Tutorials', 'buckleup' ),
		'items' => array(
			array( 'name' => 'Parallel Parking Mastery', 'type' => 'Video', 'size' => '15 min' ),
			array( 'name' => 'Lane Changes & Merging', 'type' => 'Video', 'size' => '12 min' ),
			array( 'name' => 'Intersection Navigation', 'type' => 'Video', 'size' => '18 min' ),
		),
	),
	array(
		'title' => __( 'Checklists & Guides', 'buckleup' ),
		'items' => array(
			array( 'name' => 'Road Test Day Checklist', 'type' => 'PDF', 'size' => '500 KB' ),
			array( 'name' => 'Pre-Trip Vehicle Inspection', 'type' => 'PDF', 'size' => '750 KB' ),
			array( 'name' => 'Common Mistakes to Avoid', 'type' => 'PDF', 'size' => '1.2 MB' ),
		),
	),
);

$articles = array(
	array( 'title' => __( 'Winter Driving in BC: Essential Tips', 'buckleup' ),            'category' => 'Safety',    'read' => '5 min read', 'href' => '' ),
	array( 'title' => __( 'How to Conquer Parallel Parking', 'buckleup' ),                 'category' => 'Skills',    'read' => '4 min read', 'href' => '' ),
	array( 'title' => __( 'Understanding the BC Graduated Licensing Program', 'buckleup' ),'category' => 'Licensing', 'read' => '6 min read', 'href' => '' ),
	array( 'title' => __( 'Defensive Driving: Key Principles', 'buckleup' ),               'category' => 'Safety',    'read' => '7 min read', 'href' => '' ),
);
// Surface the real ICBC road-test article if it has been published (content task).
$icbc = get_page_by_path( 'icbc-road-test-failures', OBJECT, array( 'page', 'post' ) );
if ( $icbc ) {
	$articles[1]['href'] = (string) get_permalink( $icbc );
}
?>
<!-- wp:html -->
<section class="py-16 md:py-24">
	<div class="container mx-auto px-4">

		<!-- Hero -->
		<div class="text-center mb-12 max-w-3xl mx-auto">
			<h1 data-reveal class="text-4xl md:text-5xl font-bold mb-4 text-foreground"><?php esc_html_e( 'Student Resources', 'buckleup' ); ?></h1>
			<p data-reveal class="text-muted-foreground text-lg"><?php esc_html_e( 'Free study materials, guides, and tutorials to help you become a confident, safe driver', 'buckleup' ); ?></p>
		</div>

		<!-- Downloadable Materials -->
		<div class="mb-16">
			<h2 data-reveal class="text-3xl font-bold mb-8 text-foreground"><?php esc_html_e( 'Downloadable Materials', 'buckleup' ); ?></h2>
			<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-3 gap-6">
				<?php foreach ( $categories as $cat ) : ?>
					<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
						<h3 class="text-lg font-bold text-foreground mb-4"><?php echo esc_html( $cat['title'] ); ?></h3>
						<ul class="space-y-3">
							<?php foreach ( $cat['items'] as $item ) : ?>
								<li class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2.5">
									<span class="flex items-center gap-2 min-w-0">
										<?php echo buckleup_icon( 'arrow-right', 'w-4 h-4 text-primary shrink-0' ); // phpcs:ignore ?>
										<span class="text-sm text-foreground truncate"><?php echo esc_html( $item['name'] ); ?></span>
									</span>
									<span class="text-xs text-muted-foreground shrink-0"><?php echo esc_html( $item['type'] . ' · ' . $item['size'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Latest Articles & Guides -->
		<div class="mb-16">
			<h2 data-reveal class="text-3xl font-bold mb-8 text-foreground"><?php esc_html_e( 'Latest Articles & Guides', 'buckleup' ); ?></h2>
			<div data-reveal-stagger="0.05" class="grid grid-cols-1 md:grid-cols-2 gap-6">
				<?php foreach ( $articles as $a ) :
					$tag      = $a['href'] ? 'a' : 'div';
					$href     = $a['href'] ? ' href="' . esc_url( $a['href'] ) . '"' : '';
					?>
					<<?php echo esc_html( $tag ); ?><?php echo $href; // phpcs:ignore ?> data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift card-highlight flex flex-col gap-3' . ( $a['href'] ? '' : '' ) ) ); ?>">
						<div class="flex items-center gap-3">
							<?php buckleup_pill( array( 'label' => $a['category'], 'tone' => 'primary', 'class' => 'text-xs px-3 py-1' ) ); ?>
							<span class="text-xs text-muted-foreground"><?php echo esc_html( $a['read'] ); ?></span>
						</div>
						<h3 class="text-xl font-bold text-foreground leading-snug"><?php echo esc_html( $a['title'] ); ?></h3>
						<?php if ( $a['href'] ) : ?>
							<span class="text-sm font-medium text-primary inline-flex items-center gap-1"><?php esc_html_e( 'Read the guide', 'buckleup' ); ?><?php echo buckleup_icon( 'arrow-right', 'w-4 h-4' ); // phpcs:ignore ?></span>
						<?php endif; ?>
					</<?php echo esc_html( $tag ); ?>>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Coming Soon -->
		<div data-reveal class="<?php echo esc_attr( buckleup_card_class( 'p-8' ) ); ?>">
			<div class="flex items-start gap-4">
				<div class="p-3 bg-primary rounded-lg shrink-0"><?php echo buckleup_icon( 'check', 'w-8 h-8 text-primary-foreground' ); // phpcs:ignore ?></div>
				<div>
					<h3 class="text-2xl font-bold text-foreground mb-2"><?php esc_html_e( 'Practice Quizzes Coming Soon', 'buckleup' ); ?></h3>
					<p class="text-muted-foreground mb-4"><?php esc_html_e( 'Interactive quizzes to test your knowledge of BC driving rules, road signs, and safe driving practices.', 'buckleup' ); ?></p>
					<?php
					buckleup_button( array(
						'label' => __( 'Get Notified', 'buckleup' ),
						'href'  => home_url( '/contact' ),
						'class' => 'rounded-lg',
					) );
					?>
				</div>
			</div>
		</div>

	</div>
</section>
<!-- /wp:html -->
