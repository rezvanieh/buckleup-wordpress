<?php
/**
 * Title: Console: Admin — Reviews
 * Slug: buckleup/console-admin-reviews
 * Inserter: no
 *
 * /admin/reviews — moderate student reviews, matching src/app/admin/reviews/
 * page.tsx. A "{N} Pending" badge + search + All/Pending/Approved filters over
 * review cards (student avatar/name/date, optional instructor, star rating,
 * comment, Approve/Unapprove + Delete). The list is server-rendered from
 * GET /admin/reviews (a BARE array); search + filter are client-side over the
 * rendered cards (console-admin-reviews.js); Approve/Unapprove → PATCH
 * /admin/reviews/{id} {isApproved} (in-place toggle), Delete → DELETE
 * /admin/reviews/{id}. Both carry X-WP-Nonce.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$reviews = array();
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/admin/reviews' ) );
	if ( 200 === $res->get_status() ) {
		$d       = $res->get_data();
		$reviews = is_array( $d ) ? $d : array();
	}
}

$pending = 0;
foreach ( $reviews as $r ) {
	if ( empty( $r['isApproved'] ) ) {
		$pending++;
	}
}

// 5 stars; filled up to $rating.
$stars = static function ( $rating ) {
	$out = '';
	for ( $i = 1; $i <= 5; $i++ ) {
		$on   = $i <= (int) $rating;
		$out .= '<span class="' . ( $on ? 'text-yellow-500' : 'text-muted-foreground/30' ) . '">' . buckleup_icon( 'star', 'w-4 h-4' . ( $on ? ' fill-current' : '' ) ) . '</span>';
	}
	return $out;
};

ob_start();
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
	<div>
		<h1 class="text-2xl md:text-3xl font-bold text-foreground"><?php esc_html_e( 'Reviews', 'buckleup' ); ?></h1>
		<p class="text-muted-foreground mt-1">
			<?php esc_html_e( 'Manage student reviews and testimonials.', 'buckleup' ); ?>
			<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/15 text-yellow-700 dark:text-yellow-500 <?php echo $pending > 0 ? '' : 'hidden'; ?>" data-reviews-pending-badge>
				<span data-reviews-pending><?php echo (int) $pending; ?></span>&nbsp;<?php esc_html_e( 'Pending', 'buckleup' ); ?>
			</span>
		</p>
	</div>
</div>

<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
	<!-- Search + filters -->
	<div class="flex flex-col sm:flex-row gap-4 justify-between mb-6" data-reviews-toolbar>
		<div class="relative w-full sm:w-96">
			<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><?php echo buckleup_icon( 'search', 'w-4 h-4' ); // phpcs:ignore ?></span>
			<input type="search" data-reviews-search placeholder="<?php esc_attr_e( 'Search by student, instructor, or content…', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'pl-9' ) ); ?>">
		</div>
		<div class="flex gap-2">
			<button type="button" data-reviews-filter="all" aria-pressed="true" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-primary text-primary-foreground shadow-lg shadow-primary/20"><?php esc_html_e( 'All', 'buckleup' ); ?></button>
			<button type="button" data-reviews-filter="pending" aria-pressed="false" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-background text-muted-foreground hover:bg-muted"><?php esc_html_e( 'Pending', 'buckleup' ); ?></button>
			<button type="button" data-reviews-filter="approved" aria-pressed="false" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-background text-muted-foreground hover:bg-muted"><?php esc_html_e( 'Approved', 'buckleup' ); ?></button>
		</div>
	</div>

	<!-- List -->
	<div class="space-y-4" data-reviews-list>
		<?php if ( empty( $reviews ) ) : ?>
			<div class="text-center py-12 text-muted-foreground" data-reviews-empty><?php esc_html_e( 'No reviews found matching your criteria.', 'buckleup' ); ?></div>
		<?php else : ?>
			<?php foreach ( $reviews as $r ) :
				$id        = (int) ( $r['id'] ?? 0 );
				$sname     = (string) ( $r['studentName'] ?? __( 'Student', 'buckleup' ) );
				$simage    = (string) ( $r['studentImage'] ?? '' );
				$iname     = (string) ( $r['instructorName'] ?? '' );
				$rating    = (int) ( $r['rating'] ?? 0 );
				$comment   = (string) ( $r['comment'] ?? '' );
				$approved  = ! empty( $r['isApproved'] );
				$created   = ! empty( $r['createdAt'] ) ? date_i18n( 'M j, Y', strtotime( $r['createdAt'] ) ) : '';
				$haystack  = strtolower( $sname . ' ' . $iname . ' ' . $comment );
				?>
				<div class="p-4 rounded-xl bg-card/50 border border-border flex flex-col lg:flex-row gap-6"
					data-review="<?php echo esc_attr( $id ); ?>"
					data-review-search="<?php echo esc_attr( $haystack ); ?>"
					data-review-approved="<?php echo $approved ? '1' : '0'; ?>">
					<!-- Student info -->
					<div class="lg:w-48 shrink-0">
						<div class="flex items-center gap-3 mb-2">
							<div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden shrink-0">
								<?php if ( $simage ) : ?>
									<img src="<?php echo esc_url( $simage ); ?>" alt="<?php echo esc_attr( $sname ); ?>" class="w-full h-full object-cover" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="text-muted-foreground"><?php echo buckleup_icon( 'user', 'w-5 h-5' ); // phpcs:ignore ?></span>
								<?php endif; ?>
							</div>
							<div>
								<div class="font-semibold text-sm text-foreground"><?php echo esc_html( $sname ); ?></div>
								<div class="text-xs text-muted-foreground"><?php echo esc_html( $created ); ?></div>
							</div>
						</div>
						<?php if ( $iname ) : ?>
							<div class="text-xs text-muted-foreground mt-2 px-2 py-1 bg-muted rounded-md inline-block"><?php printf( /* translators: %s: instructor name */ esc_html__( 'Instructor: %s', 'buckleup' ), esc_html( $iname ) ); ?></div>
						<?php endif; ?>
					</div>

					<!-- Content -->
					<div class="flex-1">
						<div class="flex gap-0.5 mb-2"><?php echo $stars( $rating ); // phpcs:ignore ?></div>
						<p class="text-sm text-foreground/90 leading-relaxed"><?php echo esc_html( $comment ); ?></p>
					</div>

					<!-- Actions -->
					<div class="flex lg:flex-col items-center justify-end gap-2 lg:w-32 shrink-0 border-t lg:border-t-0 lg:border-l border-border pt-4 lg:pt-0 lg:pl-4" data-review-actions>
						<button type="button" data-review-approve class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm', 'w-full text-green-600 border-green-500/30 hover:bg-green-500/10' . ( $approved ? '' : ' hidden' ) ) ); ?>">
							<?php echo buckleup_icon( 'check', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Approved', 'buckleup' ); ?>
						</button>
						<button type="button" data-review-pending class="<?php echo esc_attr( buckleup_button_class( 'default', 'sm', 'w-full bg-primary/10 text-primary hover:bg-primary/20 shadow-none' . ( $approved ? ' hidden' : '' ) ) ); ?>">
							<?php esc_html_e( 'Approve', 'buckleup' ); ?>
						</button>
						<div class="flex gap-2 w-full">
							<button type="button" data-review-delete aria-label="<?php esc_attr_e( 'Delete review', 'buckleup' ); ?>" title="<?php esc_attr_e( 'Delete Review', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'ghost', 'icon-sm', 'flex-1 text-muted-foreground hover:text-destructive hover:bg-destructive/10' ) ); ?>"><?php echo buckleup_icon( 'trash', 'w-4 h-4' ); // phpcs:ignore ?></button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>

			<!-- No-results (shown by JS when search/filter empties the list) -->
			<div class="text-center py-12 text-muted-foreground hidden" data-reviews-noresults><?php esc_html_e( 'No reviews found matching your criteria.', 'buckleup' ); ?></div>
		<?php endif; ?>
	</div>
</div>

<div data-reviews-status role="status" aria-live="polite" class="mt-4 text-sm hidden" hidden></div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'admin', 'reviews', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
