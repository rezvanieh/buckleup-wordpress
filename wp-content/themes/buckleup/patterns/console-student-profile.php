<?php
/**
 * Title: Console: Student — Profile
 * Slug: buckleup/console-student-profile
 * Inserter: no
 *
 * /student/profile — Full Name, Email (disabled), Phone, License (7L/7N/5),
 * Emergency name/phone, Preferred Language (en/zh/yue/pa), avatar upload. The form
 * is server-rendered from GET /students/profile (rest_do_request); the save (PUT
 * /students/profile) + avatar (POST/DELETE /user/avatar) are JS mutations
 * (console-profile.js) with an unsaved-changes pill + Save/Discard.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$p = array(
	'name' => '', 'email' => '', 'phone' => '', 'image' => '',
	'licenseType' => '', 'emergencyContact' => '', 'emergencyPhone' => '', 'preferredLang' => 'en',
);
if ( function_exists( 'rest_do_request' ) ) {
	$req = new WP_REST_Request( 'GET', '/buckleup/v1/students/profile' );
	$res = rest_do_request( $req );
	if ( 200 === $res->get_status() ) {
		$data = (array) $res->get_data();
		$p    = array_merge( $p, (array) ( $data['profile'] ?? $data ) );
	}
}

$licenses  = array( '7L' => __( 'Class 7L (Learner)', 'buckleup' ), '7N' => __( 'Class 7N (Novice)', 'buckleup' ), '5' => __( 'Class 5 (Full)', 'buckleup' ) );
$languages = array( 'en' => __( 'English', 'buckleup' ), 'zh' => __( 'Mandarin', 'buckleup' ), 'yue' => __( 'Cantonese', 'buckleup' ), 'pa' => __( 'Punjabi', 'buckleup' ) );

$initials = '';
foreach ( array_slice( preg_split( '/\s+/', trim( (string) $p['name'] ) ), 0, 2 ) as $w ) {
	$initials .= mb_substr( $w, 0, 1 );
}

ob_start();
echo buckleup_console_heading( __( 'Profile', 'buckleup' ), __( 'Keep your contact and licence details up to date.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<form data-profile-form data-profile-endpoint="students/profile" class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8 max-w-3xl' ) ); ?>">
	<div data-profile-status role="status" aria-live="polite" class="mb-4 rounded-lg px-4 py-3 text-sm hidden" hidden></div>

	<!-- Avatar -->
	<div class="flex items-center gap-5 mb-8">
		<span data-avatar-preview class="relative inline-flex items-center justify-center w-20 h-20 rounded-full overflow-hidden bg-muted border-2 border-primary/20 text-lg font-bold text-foreground">
			<?php if ( ! empty( $p['image'] ) ) : ?>
				<img src="<?php echo esc_url( $p['image'] ); ?>" alt="" class="w-full h-full object-cover">
			<?php else : ?>
				<?php echo esc_html( mb_strtoupper( $initials ) ); ?>
			<?php endif; ?>
		</span>
		<div class="flex flex-wrap gap-2">
			<label class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?> cursor-pointer">
				<?php esc_html_e( 'Upload photo', 'buckleup' ); ?>
				<input type="file" data-avatar-input accept="image/*" class="hidden">
			</label>
			<?php if ( ! empty( $p['image'] ) ) : ?>
				<button type="button" data-avatar-remove class="<?php echo esc_attr( buckleup_button_class( 'ghost', 'sm' ) ); ?>"><?php esc_html_e( 'Remove', 'buckleup' ); ?></button>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
		<div>
			<label for="bu-pf-name" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Full name', 'buckleup' ); ?></label>
			<input id="bu-pf-name" name="name" type="text" value="<?php echo esc_attr( $p['name'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
		<div>
			<label for="bu-pf-email" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Email', 'buckleup' ); ?></label>
			<input id="bu-pf-email" type="email" value="<?php echo esc_attr( $p['email'] ); ?>" disabled class="<?php echo esc_attr( buckleup_input_class( 'opacity-60 cursor-not-allowed' ) ); ?>">
		</div>
		<div>
			<label for="bu-pf-phone" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Phone', 'buckleup' ); ?></label>
			<input id="bu-pf-phone" name="phone" type="tel" value="<?php echo esc_attr( $p['phone'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
		<div>
			<label for="bu-pf-license" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Licence type', 'buckleup' ); ?></label>
			<select id="bu-pf-license" name="licenseType" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				<option value=""><?php esc_html_e( 'Select…', 'buckleup' ); ?></option>
				<?php foreach ( $licenses as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $p['licenseType'], $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="bu-pf-ec" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Emergency contact', 'buckleup' ); ?></label>
			<input id="bu-pf-ec" name="emergencyContact" type="text" value="<?php echo esc_attr( $p['emergencyContact'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
		<div>
			<label for="bu-pf-ep" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Emergency phone', 'buckleup' ); ?></label>
			<input id="bu-pf-ep" name="emergencyPhone" type="tel" value="<?php echo esc_attr( $p['emergencyPhone'] ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
		</div>
		<div>
			<label for="bu-pf-lang" class="<?php echo esc_attr( buckleup_label_class( 'mb-1.5 block' ) ); ?>"><?php esc_html_e( 'Preferred language', 'buckleup' ); ?></label>
			<select id="bu-pf-lang" name="preferredLang" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				<?php foreach ( $languages as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $p['preferredLang'], $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="mt-8 flex items-center gap-3">
		<?php
		buckleup_button( array( 'label' => __( 'Save changes', 'buckleup' ), 'class' => '', 'attrs' => array( 'type' => 'submit', 'data-profile-save' => true, 'disabled' => true ) ) );
		?>
		<button type="button" data-profile-discard class="<?php echo esc_attr( buckleup_button_class( 'ghost' ) ); ?> hidden"><?php esc_html_e( 'Discard', 'buckleup' ); ?></button>
		<span data-profile-dirty class="text-sm text-yellow-600 hidden"><?php esc_html_e( 'Unsaved changes', 'buckleup' ); ?></span>
	</div>
</form>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'student', 'profile', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
