<?php
/**
 * Title: Console: Admin — Students
 * Slug: buckleup/console-admin-students
 * Inserter: no
 *
 * /admin/students — students management, matching src/app/admin/students/page.tsx.
 * 4 stat cards (Total / Active / By License / By Status) + search + Status &
 * License filters + a paginated table (Student, Contact, License, Status,
 * Bookings, Last Lesson, Joined, Delete). The initial page-1 state is
 * server-rendered from GET /admin/students; search, filters, pagination, and the
 * delete-with-confirm flow re-fetch + re-render client-side (console-admin-
 * students.js) → DELETE /admin/students/{id} (cascade). All escaped; mutations
 * carry X-WP-Nonce.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$data = array( 'students' => array(), 'stats' => array( 'total' => 0, 'active' => 0, 'byStatus' => array(), 'byLicenseType' => array() ), 'pagination' => array( 'total' => 0, 'pages' => 0, 'page' => 1, 'limit' => 20 ) );
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/admin/students' ) );
	if ( 200 === $res->get_status() ) {
		$data = array_merge( $data, (array) $res->get_data() );
	}
}

$stats      = (array) $data['stats'];
$pagination = (array) $data['pagination'];
$students   = (array) $data['students'];
$pct_active = ! empty( $stats['total'] ) ? (int) round( ( (int) $stats['active'] / (int) $stats['total'] ) * 100 ) : 0;

// Status pill colour map (verbatim status text).
$status_pill = static function ( $status ) {
	$s   = strtoupper( (string) $status );
	$map = array(
		'ACTIVE'    => 'bg-green-500/10 text-green-600 dark:text-green-400',
		'INACTIVE'  => 'bg-muted text-muted-foreground',
		'COMPLETED' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
		'SUSPENDED' => 'bg-destructive/10 text-destructive',
	);
	$cls = $map[ $s ] ?? $map['INACTIVE'];
	return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . esc_attr( $cls ) . '">' . esc_html( $s ) . '</span>';
};

ob_start();
echo buckleup_console_heading( __( 'Students Management', 'buckleup' ), __( 'View and manage all registered students.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<!-- Stat cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<div class="flex items-center justify-between mb-2"><span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Total Students', 'buckleup' ); ?></span><span class="text-muted-foreground"><?php echo buckleup_icon( 'users', 'w-4 h-4' ); // phpcs:ignore ?></span></div>
		<div class="text-2xl font-bold text-foreground" data-stat-total><?php echo (int) $stats['total']; ?></div>
	</div>
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<div class="flex items-center justify-between mb-2"><span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'Active Students', 'buckleup' ); ?></span><span class="text-green-500"><?php echo buckleup_icon( 'check-circle', 'w-4 h-4' ); // phpcs:ignore ?></span></div>
		<div class="text-2xl font-bold text-green-600" data-stat-active><?php echo (int) $stats['active']; ?></div>
		<p class="text-xs text-muted-foreground mt-1"><span data-stat-active-pct><?php echo (int) $pct_active; ?></span>% <?php esc_html_e( 'of total', 'buckleup' ); ?></p>
	</div>
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<div class="flex items-center justify-between mb-2"><span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'By License Type', 'buckleup' ); ?></span><span class="text-muted-foreground"><?php echo buckleup_icon( 'graduation-cap', 'w-4 h-4' ); // phpcs:ignore ?></span></div>
		<div class="space-y-1" data-stat-license>
			<?php if ( ! empty( $stats['byLicenseType'] ) ) : ?>
				<?php foreach ( (array) $stats['byLicenseType'] as $type => $count ) : ?>
					<div class="flex justify-between text-sm"><span class="text-muted-foreground"><?php echo esc_html( $type ); ?></span><span class="font-medium text-foreground"><?php echo (int) $count; ?></span></div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="text-sm text-muted-foreground"><?php esc_html_e( 'No data', 'buckleup' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<div class="flex items-center justify-between mb-2"><span class="text-sm font-medium text-muted-foreground"><?php esc_html_e( 'By Status', 'buckleup' ); ?></span><span class="text-muted-foreground"><?php echo buckleup_icon( 'user', 'w-4 h-4' ); // phpcs:ignore ?></span></div>
		<div class="space-y-1" data-stat-status>
			<?php foreach ( (array) $stats['byStatus'] as $status => $count ) : ?>
				<div class="flex justify-between text-sm"><span class="text-muted-foreground"><?php echo esc_html( $status ); ?></span><span class="font-medium text-foreground"><?php echo (int) $count; ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- Filters -->
<div class="flex flex-wrap items-center gap-4 mb-6" data-admin-students-toolbar>
	<div class="relative flex-1 min-w-[200px] max-w-md">
		<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><?php echo buckleup_icon( 'search', 'w-4 h-4' ); // phpcs:ignore ?></span>
		<input type="search" data-admin-students-search placeholder="<?php esc_attr_e( 'Search by name or email…', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'pl-10' ) ); ?>">
	</div>
	<select data-admin-students-status-filter aria-label="<?php esc_attr_e( 'Filter by status', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'w-[160px]' ) ); ?>">
		<option value=""><?php esc_html_e( 'All Status', 'buckleup' ); ?></option>
		<option value="ACTIVE"><?php esc_html_e( 'Active', 'buckleup' ); ?></option>
		<option value="INACTIVE"><?php esc_html_e( 'Inactive', 'buckleup' ); ?></option>
		<option value="COMPLETED"><?php esc_html_e( 'Completed', 'buckleup' ); ?></option>
		<option value="SUSPENDED"><?php esc_html_e( 'Suspended', 'buckleup' ); ?></option>
	</select>
	<select data-admin-students-license aria-label="<?php esc_attr_e( 'Filter by license', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'w-[190px]' ) ); ?>">
		<option value=""><?php esc_html_e( 'All Licenses', 'buckleup' ); ?></option>
		<option value="7L"><?php esc_html_e( 'Class 7L (Learner)', 'buckleup' ); ?></option>
		<option value="7N"><?php esc_html_e( 'Class 7N (Novice)', 'buckleup' ); ?></option>
		<option value="5"><?php esc_html_e( 'Class 5 (Full)', 'buckleup' ); ?></option>
	</select>
</div>

<!-- Table -->
<div class="<?php echo esc_attr( buckleup_card_class( 'overflow-hidden p-0' ) ); ?>">
	<div class="relative w-full overflow-x-auto">
		<table class="<?php echo esc_attr( buckleup_table_class() ); ?>">
			<thead class="[&_tr]:border-b">
				<tr class="<?php echo esc_attr( buckleup_table_row_class( 'bg-muted/50' ) ); ?>">
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Student', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Contact', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'License', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Status', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class( 'text-center' ) ); ?>"><?php esc_html_e( 'Bookings', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Last Lesson', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Joined', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class( 'text-right' ) ); ?>"><?php esc_html_e( 'Actions', 'buckleup' ); ?></th>
				</tr>
			</thead>
			<tbody class="[&_tr:last-child]:border-0" data-admin-students-rows>
				<?php if ( empty( $students ) ) : ?>
					<tr><td colspan="8" class="<?php echo esc_attr( buckleup_table_cell_class( 'text-center text-muted-foreground py-12' ) ); ?>"><?php esc_html_e( 'No students found.', 'buckleup' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $students as $s ) :
						$id      = (int) ( $s['id'] ?? 0 );
						$name    = (string) ( $s['name'] ?? '' );
						$email   = (string) ( $s['email'] ?? '' );
						$phone   = (string) ( $s['phone'] ?? '' );
						$license = (string) ( $s['licenseType'] ?? '' );
						$status  = (string) ( $s['status'] ?? 'ACTIVE' );
						$count   = (int) ( $s['bookingCount'] ?? 0 );
						$last    = ! empty( $s['lastBooking'] ) ? date_i18n( 'M j, Y', strtotime( $s['lastBooking'] ) ) : '';
						$joined  = ! empty( $s['userCreatedAt'] ) ? date_i18n( 'M j, Y', strtotime( $s['userCreatedAt'] ) ) : '';
						$initials = '';
						foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) as $part ) {
							$initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
						}
						?>
						<tr class="<?php echo esc_attr( buckleup_table_row_class() ); ?>" data-admin-student="<?php echo esc_attr( $id ); ?>">
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>">
								<div class="flex items-center gap-3">
									<?php buckleup_avatar( array( 'src' => (string) ( $s['image'] ?? '' ), 'alt' => $name, 'fallback' => strtoupper( $initials ), 'class' => 'size-10 bg-primary/10 text-primary' ) ); ?>
									<div>
										<p class="font-medium text-foreground"><?php echo esc_html( $name ); ?></p>
										<p class="text-sm text-muted-foreground flex items-center gap-1"><?php echo buckleup_icon( 'mail', 'w-3 h-3' ); // phpcs:ignore ?><?php echo esc_html( $email ); ?></p>
									</div>
								</div>
							</td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo $phone ? '<span class="flex items-center gap-1 text-sm">' . buckleup_icon( 'phone', 'w-3 h-3 text-muted-foreground' ) . esc_html( $phone ) . '</span>' : '<span class="text-sm text-muted-foreground">—</span>'; // phpcs:ignore ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo $license ? '<span class="text-sm font-medium text-foreground">' . esc_html( $license ) . '</span>' : '<span class="text-sm text-muted-foreground">' . esc_html__( 'Not set', 'buckleup' ) . '</span>'; // phpcs:ignore ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo $status_pill( $status ); // phpcs:ignore ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class( 'text-center' ) ); ?>"><span class="inline-flex items-center justify-center gap-1"><?php echo buckleup_icon( 'check', 'w-4 h-4 text-muted-foreground' ); // phpcs:ignore ?><span class="font-medium text-foreground"><?php echo (int) $count; ?></span></span></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo $last ? '<span class="text-sm flex items-center gap-1">' . buckleup_icon( 'clock', 'w-3 h-3 text-muted-foreground' ) . esc_html( $last ) . '</span>' : '<span class="text-sm text-muted-foreground">' . esc_html__( 'Never', 'buckleup' ) . '</span>'; // phpcs:ignore ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><span class="text-sm flex items-center gap-1"><?php echo buckleup_icon( 'calendar', 'w-3 h-3 text-muted-foreground' ); // phpcs:ignore ?><?php echo esc_html( $joined ); ?></span></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class( 'text-right' ) ); ?>">
								<button type="button" data-admin-student-delete="<?php echo esc_attr( $id ); ?>" data-admin-student-name="<?php echo esc_attr( $name ); ?>" aria-label="<?php esc_attr_e( 'Delete student', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'ghost', 'sm', 'text-destructive hover:bg-destructive/10' ) ); ?>"><?php echo buckleup_icon( 'trash', 'w-4 h-4' ); // phpcs:ignore ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Pagination -->
<div class="flex items-center justify-between mt-4 <?php echo (int) $pagination['pages'] > 1 ? '' : 'hidden'; ?>" data-admin-students-pager>
	<p class="text-sm text-muted-foreground" data-admin-students-count>
		<?php
		printf(
			/* translators: 1: shown count, 2: total count */
			esc_html__( 'Showing %1$d of %2$d students', 'buckleup' ),
			count( $students ),
			(int) $pagination['total']
		);
		?>
	</p>
	<div class="flex gap-2 items-center">
		<button type="button" data-admin-students-prev class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>" <?php disabled( (int) $pagination['page'] <= 1 ); ?>><?php esc_html_e( 'Previous', 'buckleup' ); ?></button>
		<span class="flex items-center px-3 text-sm text-muted-foreground" data-admin-students-pageinfo>
			<?php
			printf(
				/* translators: 1: current page, 2: total pages */
				esc_html__( 'Page %1$d of %2$d', 'buckleup' ),
				(int) $pagination['page'],
				(int) $pagination['pages']
			);
			?>
		</span>
		<button type="button" data-admin-students-next class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>" <?php disabled( (int) $pagination['page'] >= (int) $pagination['pages'] ); ?>><?php esc_html_e( 'Next', 'buckleup' ); ?></button>
	</div>
</div>

<div data-admin-students-status role="status" aria-live="polite" class="mt-4 text-sm hidden" hidden></div>

<!-- Delete confirm dialog -->
<div data-admin-del-dialog data-state="closed" class="fixed inset-0 z-50 hidden items-center justify-center p-4" hidden>
	<div data-admin-del-overlay class="absolute inset-0 bg-black/50"></div>
	<div role="dialog" aria-modal="true" aria-labelledby="bu-del-title" class="relative w-full max-w-md <?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<h2 id="bu-del-title" class="text-lg font-semibold text-foreground mb-1"><?php esc_html_e( 'Delete Student', 'buckleup' ); ?></h2>
		<p class="text-sm text-muted-foreground mb-5"><?php esc_html_e( 'Are you sure you want to delete this student? This action cannot be undone. It will permanently remove the student profile and their reviews.', 'buckleup' ); ?></p>
		<div class="flex justify-end gap-2">
			<button type="button" data-admin-del-cancel class="<?php echo esc_attr( buckleup_button_class( 'outline' ) ); ?>"><?php esc_html_e( 'Cancel', 'buckleup' ); ?></button>
			<button type="button" data-admin-del-confirm class="<?php echo esc_attr( buckleup_button_class( 'destructive' ) ); ?>"><?php esc_html_e( 'Delete', 'buckleup' ); ?></button>
		</div>
	</div>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'admin', 'students', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
