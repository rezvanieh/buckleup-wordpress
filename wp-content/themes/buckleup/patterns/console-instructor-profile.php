<?php
/**
 * Title: Console: Instructor — Profile
 * Slug: buckleup/console-instructor-profile
 * Inserter: no
 *
 * /instructor/profile — name/phone (email disabled), bio (500 counter),
 * certifications + languages (tag add/remove), avatar; plus a read-only meta row:
 * real avgRating + "(N reviews)", Active badge, "Member since". Read from GET
 * /instructors/profile (avatar/avgRating/totalReviews keys per gap #4); save via
 * PUT (console-profile.js, shared) + the tag handling in console-tags.js. Rating
 * is REAL (computed from approved reviews) or shows "No reviews yet".
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$p = array(
	'name' => '', 'email' => '', 'phone' => '', 'avatar' => '', 'bio' => '',
	'certifications' => array(), 'languages' => array(),
	'isActive' => true, 'avgRating' => 0, 'totalReviews' => 0, 'createdAt' => '',
);
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/instructors/profile' ) );
	if ( 200 === $res->get_status() ) {
		$data = (array) $res->get_data();
		$p    = array_merge( $p, (array) ( $data['profile'] ?? $data ) );
	}
}

$certs  = is_array( $p['certifications'] ) ? $p['certifications'] : array();
$langs  = is_array( $p['languages'] ) ? $p['languages'] : array();
$member = $p['createdAt'] ? mysql2date( 'F Y', $p['createdAt'] ) : '';

ob_start();
echo buckleup_console_heading( __( 'Profile', 'buckleup' ), __( 'Your public instructor profile.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<form data-profile-form data-profile-endpoint="instructors/profile" data-profile-json="certifications,languages" class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 max-w-3xl' ) ); ?>">
	<div data-profile-status role="status" aria-live="polite" class="mb-4 rounded-lg px-4 py-3 text-sm hidden" hidden></div>

	<!-- Avatar + meta row -->
	<div class="flex flex-wrap items-center gap-5 mb-8">
		<span data-avatar-preview class="relative inline-flex items-center justify-center w-20 h-20 rounded-full overflow-hidden bg-muted border-2 border-primary/20 text-lg font-bold text-foreground">
			<?php if ( ! empty( $p['avatar'] ) ) : ?>
				<img src="<?php echo esc_url( $p['avatar'] ); ?>" alt="" class="w-full h-full object-cover">
			<?php else : ?>
				<?php echo esc_html( mb_strtoupper( mb_substr( (string) $p['name'], 0, 1 ) ) ); ?>
			<?php endif; ?>
		</span>
		<div>
			<div class="flex items-center gap-2 mb-1">
				<?php if ( (float) $p['avgRating'] > 0 ) : ?>
					<?php echo buckleup_icon( 'star', 'w-4 h-4 fill-yellow-500 text-yellow-500' ); // phpcs:ignore ?>
					<span class="font-semibold text-foreground"><?php echo esc_html( number_format( (float) $p['avgRating'], 1 ) ); ?></span>
					<span class="text-sm text-muted-foreground"><?php
						/* translators: %d: review count */
						printf( esc_html( _n( '(%d review)', '(%d reviews)', (int) $p['totalReviews'], 'buckleup' ) ), (int) $p['totalReviews'] );
					?></span>
				<?php else : ?>
					<span class="text-sm text-muted-foreground"><?php esc_html_e( 'No reviews yet', 'buckleup' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $p['isActive'] ) ) : ?>
					<span class="text-xs font-medium px-2 py-0.5 rounded-full bg-accent/10 text-accent"><?php esc_html_e( 'Active', 'buckleup' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $member ) : ?>
				<p class="text-xs text-muted-foreground"><?php
					/* translators: %s: month year */
					printf( esc_html__( 'Member since %s', 'buckleup' ), esc_html( $member ) );
				?></p>
			<?php endif; ?>
			<div class="flex flex-wrap gap-2 mt-2">
				<label class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?> cursor-pointer">
					<?php esc_html_e( 'Upload photo', 'buckleup' ); ?>
					<input type="file" data-avatar-input accept="image/*" class="hidden">
				</label>
			</div>
		</div>
	</div>

	<div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
		<div>
			<label for="bu-ipf-name" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Full name', 'buckleup' ); ?></label>
			<input id="bu-ipf-name" name="name" type="text" value="<?php echo esc_attr( $p['name'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
		<div>
			<label for="bu-ipf-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Email', 'buckleup' ); ?></label>
			<input id="bu-ipf-email" type="email" value="<?php echo esc_attr( $p['email'] ); ?>" disabled class="<?php echo esc_attr( buckleup_input_class( 'opacity-60 cursor-not-allowed' ) ); ?>">
		</div>
		<div>
			<label for="bu-ipf-phone" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Phone', 'buckleup' ); ?></label>
			<input id="bu-ipf-phone" name="phone" type="tel" value="<?php echo esc_attr( $p['phone'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
	</div>

	<div class="mb-5">
		<label for="bu-ipf-bio" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Bio', 'buckleup' ); ?></label>
		<textarea id="bu-ipf-bio" name="bio" rows="4" maxlength="500" class="<?php echo esc_attr( buckleup_textarea_class() ); ?>"><?php echo esc_textarea( $p['bio'] ); ?></textarea>
		<p class="text-xs text-muted-foreground mt-1"><span data-bio-count><?php echo esc_html( mb_strlen( (string) $p['bio'] ) ); ?></span>/500</p>
	</div>

	<!-- Certifications (tags) -->
	<div class="mb-5" data-tag-field data-tag-name="certifications">
		<label class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Certifications', 'buckleup' ); ?></label>
		<div data-tag-list class="flex flex-wrap gap-2 mb-2">
			<?php foreach ( $certs as $c ) : ?>
				<span class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded-full bg-primary/10 text-primary"><?php echo esc_html( $c ); ?><button type="button" data-tag-remove aria-label="<?php esc_attr_e( 'Remove', 'buckleup' ); ?>" class="hover:text-destructive">&times;</button></span>
			<?php endforeach; ?>
		</div>
		<input type="text" data-tag-input placeholder="<?php esc_attr_e( 'Add a certification and press Enter', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		<input type="hidden" name="certifications" value="<?php echo esc_attr( wp_json_encode( array_values( $certs ) ) ); ?>">
	</div>

	<!-- Languages (tags) -->
	<div class="mb-8" data-tag-field data-tag-name="languages">
		<label class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Languages', 'buckleup' ); ?></label>
		<div data-tag-list class="flex flex-wrap gap-2 mb-2">
			<?php foreach ( $langs as $l ) : ?>
				<span class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded-full bg-muted text-muted-foreground"><?php echo esc_html( $l ); ?><button type="button" data-tag-remove aria-label="<?php esc_attr_e( 'Remove', 'buckleup' ); ?>" class="hover:text-destructive">&times;</button></span>
			<?php endforeach; ?>
		</div>
		<input type="text" data-tag-input placeholder="<?php esc_attr_e( 'Add a language and press Enter', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		<input type="hidden" name="languages" value="<?php echo esc_attr( wp_json_encode( array_values( $langs ) ) ); ?>">
	</div>

	<div class="flex items-center gap-3">
		<?php buckleup_button( array( 'label' => __( 'Save changes', 'buckleup' ), 'attrs' => array( 'type' => 'submit', 'data-profile-save' => true, 'disabled' => true ) ) ); ?>
		<button type="button" data-profile-discard class="<?php echo esc_attr( buckleup_button_class( 'ghost' ) ); ?> hidden"><?php esc_html_e( 'Discard', 'buckleup' ); ?></button>
		<span data-profile-dirty class="text-sm text-yellow-600 hidden"><?php esc_html_e( 'Unsaved changes', 'buckleup' ); ?></span>
	</div>
</form>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'profile', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
