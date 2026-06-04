<?php
/**
 * Title: Console: Admin — Graduates
 * Slug: buckleup/console-admin-graduates
 * Inserter: no
 *
 * /admin/graduates — manage the public Hall-of-Fame photos, matching
 * src/app/admin/graduates/page.tsx. An upload card (file + optional title +
 * live preview + Upload) over a grid of graduate tiles with hover-delete (+
 * confirm modal). The grid + count are server-rendered from GET /graduates
 * (rest_do_request) — the SAME `graduate` CPT the public landing renders, so the
 * admin and showcase never diverge. Upload (multipart POST /graduates → Media
 * Library) and delete (DELETE /graduates/{id}) are JS mutations
 * (console-graduates.js) carrying X-WP-Nonce.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$graduates = array();
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/graduates' ) );
	if ( 200 === $res->get_status() ) {
		$d         = $res->get_data();
		$graduates = is_array( $d ) ? $d : array();
	}
}

ob_start();
echo buckleup_console_heading( __( 'Graduates Management', 'buckleup' ), __( 'Upload and manage images for the public graduates showcase.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<!-- Upload card -->
<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 border-primary/20 bg-primary/5 mb-8' ) ); ?>">
	<form data-graduate-form class="space-y-6" enctype="multipart/form-data">
		<div class="grid md:grid-cols-2 gap-6">
			<div class="space-y-4">
				<div class="space-y-2">
					<label for="bu-grad-file" class="<?php echo esc_attr( buckleup_label_class() ); ?>"><?php esc_html_e( 'Select Image', 'buckleup' ); ?></label>
					<input id="bu-grad-file" type="file" accept="image/*" data-graduate-file required class="<?php echo esc_attr( buckleup_input_class( 'cursor-pointer file:bg-primary file:text-primary-foreground file:border-0 file:rounded-lg file:px-4 file:py-1 file:mr-4 file:hover:bg-primary/90 py-2' ) ); ?>">
				</div>
				<div class="space-y-2">
					<label for="bu-grad-title" class="<?php echo esc_attr( buckleup_label_class() ); ?>"><?php esc_html_e( "Graduate's Name / Title (Optional)", 'buckleup' ); ?></label>
					<input id="bu-grad-title" type="text" data-graduate-title placeholder="<?php esc_attr_e( "e.g. John's Road Test Success", 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class() ); ?>">
				</div>
			</div>

			<div class="flex flex-col items-center justify-center border-2 border-dashed border-border rounded-xl p-4 bg-background/50" data-graduate-preview-wrap>
				<div class="text-center space-y-2" data-graduate-placeholder>
					<span class="block text-muted-foreground mx-auto w-fit"><?php echo buckleup_icon( 'image', 'w-12 h-12' ); // phpcs:ignore ?></span>
					<p class="text-sm text-muted-foreground"><?php esc_html_e( 'Image preview will appear here', 'buckleup' ); ?></p>
				</div>
				<div class="relative w-full aspect-video rounded-lg overflow-hidden hidden" data-graduate-preview>
					<img alt="<?php esc_attr_e( 'Preview', 'buckleup' ); ?>" class="w-full h-full object-contain" data-graduate-preview-img>
					<button type="button" data-graduate-clear aria-label="<?php esc_attr_e( 'Clear', 'buckleup' ); ?>" class="absolute top-2 right-2 p-1 bg-background/80 rounded-full hover:bg-background text-foreground"><?php echo buckleup_icon( 'x', 'w-4 h-4' ); // phpcs:ignore ?></button>
				</div>
			</div>
		</div>

		<div class="flex items-center justify-between gap-4">
			<button type="submit" data-graduate-submit disabled class="<?php echo esc_attr( buckleup_button_class( 'default', 'default', 'min-w-[180px]' ) ); ?>">
				<?php echo buckleup_icon( 'upload', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Upload Graduate Image', 'buckleup' ); ?>
			</button>
			<span data-graduate-status role="status" aria-live="polite" class="flex items-center gap-2 text-sm font-medium hidden" hidden></span>
		</div>
	</form>
</div>

<!-- Grid -->
<div class="space-y-4">
	<h2 class="text-lg font-semibold text-foreground flex items-center gap-2">
		<?php esc_html_e( 'Success Stories', 'buckleup' ); ?>
		<span class="text-xs font-normal text-muted-foreground px-2 py-0.5 rounded-full bg-muted" data-graduate-count><?php echo (int) count( $graduates ); ?></span>
	</h2>

	<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 <?php echo empty( $graduates ) ? 'hidden' : ''; ?>" data-graduate-grid>
		<?php foreach ( $graduates as $g ) :
			$id    = (int) ( $g['id'] ?? 0 );
			$url   = (string) ( $g['url'] ?? '' );
			$title = (string) ( $g['title'] ?? '' );
			?>
			<div class="group relative aspect-square rounded-xl overflow-hidden border border-border bg-card shadow-sm" data-graduate="<?php echo esc_attr( $id ); ?>">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" decoding="async">
				<?php endif; ?>
				<div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
					<div class="absolute bottom-0 left-0 right-0 p-3"><p class="text-white text-xs font-bold truncate"><?php echo esc_html( $title ?: __( 'Untitled graduate', 'buckleup' ) ); ?></p></div>
					<button type="button" data-graduate-delete="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Delete image', 'buckleup' ); ?>" class="absolute top-2 right-2 p-2 bg-destructive/90 text-destructive-foreground rounded-lg hover:bg-destructive shadow-lg transition-colors"><?php echo buckleup_icon( 'trash', 'w-4 h-4' ); // phpcs:ignore ?></button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Empty state -->
	<div class="text-center py-20 rounded-2xl border border-dashed border-border bg-card/50 <?php echo empty( $graduates ) ? '' : 'hidden'; ?>" data-graduate-empty>
		<span class="block text-muted-foreground mx-auto mb-4 w-fit"><?php echo buckleup_icon( 'image', 'w-12 h-12' ); // phpcs:ignore ?></span>
		<h3 class="text-lg font-medium text-foreground"><?php esc_html_e( 'No graduates yet', 'buckleup' ); ?></h3>
		<p class="text-muted-foreground"><?php esc_html_e( 'Upload your first success story!', 'buckleup' ); ?></p>
	</div>
</div>

<!-- Delete confirm modal -->
<div data-graduate-del-dialog data-state="closed" class="fixed inset-0 z-50 hidden items-center justify-center p-4" hidden>
	<div data-graduate-del-overlay class="absolute inset-0 bg-background/80 backdrop-blur-sm"></div>
	<div role="dialog" aria-modal="true" aria-labelledby="bu-grad-del-title" class="relative w-full max-w-sm bg-card border border-border rounded-3xl p-8 shadow-2xl">
		<div class="w-16 h-16 rounded-2xl bg-destructive/10 flex items-center justify-center mx-auto mb-6 text-destructive"><?php echo buckleup_icon( 'trash', 'w-8 h-8' ); // phpcs:ignore ?></div>
		<div class="text-center space-y-2 mb-8">
			<h2 id="bu-grad-del-title" class="text-xl font-bold text-foreground"><?php esc_html_e( 'Delete Story?', 'buckleup' ); ?></h2>
			<p class="text-muted-foreground text-sm"><?php esc_html_e( 'This action cannot be undone. The image will be permanently removed.', 'buckleup' ); ?></p>
		</div>
		<div class="flex flex-col gap-3">
			<button type="button" data-graduate-del-confirm class="<?php echo esc_attr( buckleup_button_class( 'destructive', 'default', 'w-full h-12 rounded-xl' ) ); ?>"><?php esc_html_e( 'Delete story', 'buckleup' ); ?></button>
			<button type="button" data-graduate-del-cancel class="<?php echo esc_attr( buckleup_button_class( 'ghost', 'default', 'w-full h-12 rounded-xl' ) ); ?>"><?php esc_html_e( 'Cancel', 'buckleup' ); ?></button>
		</div>
	</div>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'admin', 'graduates', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
