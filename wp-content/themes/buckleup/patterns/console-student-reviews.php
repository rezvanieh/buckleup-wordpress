<?php
/**
 * Title: Console: Student — Leave a Review
 * Slug: buckleup/console-student-reviews
 * Inserter: no
 *
 * /student/reviews — the review form (5-star, comment ≥10/≤1000 with counter,
 * "display publicly" default on, Submit disabled until valid) + the server-
 * rendered "My Reviews" list with Approved/Pending pills. List read in PHP via
 * rest_do_request (GET /students/reviews); submit is a JS mutation (POST /reviews)
 * handled by src/js/modules/console-reviews.js using window.buckleupAuth.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Server-render the current student's reviews (read).
$reviews = array();
if ( function_exists( 'rest_do_request' ) ) {
	$req = new WP_REST_Request( 'GET', '/buckleup/v1/students/reviews' );
	$res = rest_do_request( $req );
	if ( 200 === $res->get_status() ) {
		$data = $res->get_data();
		$reviews = is_array( $data ) ? $data : array();
	}
}

$stars = static function ( $n ) {
	$out = '';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= buckleup_icon( 'star', 'w-4 h-4 ' . ( $i <= (int) $n ? 'fill-yellow-500 text-yellow-500' : 'text-muted-foreground/30' ) );
	}
	return $out;
};

ob_start();
echo buckleup_console_heading( __( 'Leave a Review', 'buckleup' ), __( 'Share your experience with BuckleUp Driving School', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
	<!-- Review form -->
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
		<div data-review-status role="status" aria-live="polite" class="mb-4 rounded-lg px-4 py-3 text-sm hidden" hidden></div>

		<form data-review-form class="space-y-6" novalidate>
			<div>
				<label class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block' ) ); ?>"><?php esc_html_e( 'Your Rating', 'buckleup' ); ?></label>
				<div class="flex items-center gap-1" data-star-rating>
					<input type="hidden" name="rating" value="0">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<button type="button" data-star="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d stars */ _n( '%d star', '%d stars', $i, 'buckleup' ), $i ) ); ?>" class="p-1 text-muted-foreground/30 hover:text-yellow-500 transition-colors">
							<?php echo buckleup_icon( 'star', 'w-7 h-7' ); // phpcs:ignore ?>
						</button>
					<?php endfor; ?>
				</div>
			</div>

			<div>
				<label for="bu-review-comment" class="<?php echo esc_attr( buckleup_label_class( 'mb-2 block' ) ); ?>"><?php esc_html_e( 'Your Review', 'buckleup' ); ?></label>
				<textarea id="bu-review-comment" name="comment" rows="5" minlength="10" maxlength="1000" placeholder="<?php esc_attr_e( 'Share your experience learning to drive with us...', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_textarea_class() ); ?>"></textarea>
				<p class="text-xs text-muted-foreground mt-1"><?php esc_html_e( 'Minimum 10 characters.', 'buckleup' ); ?> <span data-review-count>0</span>/1000</p>
			</div>

			<label class="flex items-center gap-2 text-sm text-foreground cursor-pointer">
				<input type="checkbox" name="isPublic" checked class="rounded border-input text-primary focus:ring-ring">
				<?php esc_html_e( 'Display my review publicly on the website', 'buckleup' ); ?>
			</label>

			<?php
			buckleup_button( array(
				'label' => __( 'Submit Review', 'buckleup' ),
				'size'  => 'lg',
				'class' => 'w-full',
				'attrs' => array( 'type' => 'submit', 'data-review-submit' => true, 'disabled' => true ),
			) );
			?>
		</form>
	</div>

	<!-- My Reviews -->
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
		<h2 class="text-xl font-semibold text-foreground mb-4"><?php esc_html_e( 'My Reviews', 'buckleup' ); ?></h2>
		<div data-review-list class="space-y-4">
			<?php if ( empty( $reviews ) ) : ?>
				<p data-review-empty class="text-sm text-muted-foreground py-8 text-center"><?php esc_html_e( "You haven't submitted any reviews yet.", 'buckleup' ); ?></p>
			<?php else : ?>
				<?php foreach ( $reviews as $r ) :
					$approved = ! empty( $r['isApproved'] ) || ! empty( $r['is_approved'] );
					?>
					<div class="rounded-xl border border-border p-4">
						<div class="flex items-center justify-between mb-2">
							<div class="flex items-center gap-0.5"><?php echo $stars( $r['rating'] ?? 0 ); // phpcs:ignore ?></div>
							<span class="text-xs font-medium px-2.5 py-1 rounded-full <?php echo $approved ? 'bg-accent/10 text-accent' : 'bg-yellow-500/10 text-yellow-600'; ?>">
								<?php echo $approved ? esc_html__( 'Approved', 'buckleup' ) : esc_html__( 'Pending', 'buckleup' ); ?>
							</span>
						</div>
						<p class="text-sm text-muted-foreground text-pretty"><?php echo esc_html( $r['comment'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'student', 'reviews', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
