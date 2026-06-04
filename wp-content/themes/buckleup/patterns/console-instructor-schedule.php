<?php
/**
 * Title: Console: Instructor — My Schedule
 * Slug: buckleup/console-instructor-schedule
 * Inserter: no
 *
 * /instructor/schedule — Pending/Confirmed count cards + an Upcoming Lessons table
 * (Date&Time, Student, Service, Location, Status, Actions). PENDING → Confirm /
 * Decline; CONFIRMED → Cancel (with reason). Read from GET /instructors/bookings
 * (rest_do_request); status changes are JS mutations (console-schedule.js) →
 * PUT /instructors/bookings/{id}/status → the plugin emails the student.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$bookings = array();
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/instructors/bookings' ) );
	if ( 200 === $res->get_status() ) {
		$d        = (array) $res->get_data();
		$bookings = $d['bookings'] ?? ( is_array( $d ) ? $d : array() );
	}
}

$pending   = 0;
$confirmed = 0;
foreach ( $bookings as $b ) {
	$s = strtoupper( (string) ( $b['status'] ?? '' ) );
	if ( 'PENDING' === $s ) {
		$pending++;
	} elseif ( 'CONFIRMED' === $s ) {
		$confirmed++;
	}
}

$status_pill = static function ( $status ) {
	$s = strtoupper( (string) $status );
	$map = array(
		'PENDING'   => array( __( 'Pending', 'buckleup' ),   'bg-yellow-500/10 text-yellow-600' ),
		'CONFIRMED' => array( __( 'Confirmed', 'buckleup' ), 'bg-accent/10 text-accent' ),
		'COMPLETED' => array( __( 'Completed', 'buckleup' ), 'bg-primary/10 text-primary' ),
		'CANCELLED' => array( __( 'Cancelled', 'buckleup' ), 'bg-destructive/10 text-destructive' ),
	);
	$p = $map[ $s ] ?? array( ucfirst( strtolower( $s ) ), 'bg-muted text-muted-foreground' );
	return '<span class="text-xs font-medium px-2.5 py-1 rounded-full ' . esc_attr( $p[1] ) . '">' . esc_html( $p[0] ) . '</span>';
};

ob_start();
echo buckleup_console_heading( __( 'My Schedule', 'buckleup' ), __( 'Manage your upcoming driving lessons.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<!-- Count cards -->
<div class="grid grid-cols-2 gap-5 mb-8 max-w-md">
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-5' ) ); ?>">
		<div class="text-3xl font-bold text-yellow-600"><?php echo (int) $pending; ?></div>
		<div class="text-sm text-muted-foreground"><?php esc_html_e( 'Pending', 'buckleup' ); ?></div>
	</div>
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-5' ) ); ?>">
		<div class="text-3xl font-bold text-accent"><?php echo (int) $confirmed; ?></div>
		<div class="text-sm text-muted-foreground"><?php esc_html_e( 'Confirmed', 'buckleup' ); ?></div>
	</div>
</div>

<!-- Upcoming lessons table -->
<div class="<?php echo esc_attr( buckleup_card_class( 'overflow-hidden p-0' ) ); ?>">
	<div class="relative w-full overflow-x-auto">
		<table class="<?php echo esc_attr( buckleup_table_class() ); ?>">
			<thead class="[&_tr]:border-b">
				<tr class="<?php echo esc_attr( buckleup_table_row_class() ); ?>">
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Date & Time', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Student', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Service', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Location', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class() ); ?>"><?php esc_html_e( 'Status', 'buckleup' ); ?></th>
					<th class="<?php echo esc_attr( buckleup_table_head_class( 'text-right' ) ); ?>"><?php esc_html_e( 'Actions', 'buckleup' ); ?></th>
				</tr>
			</thead>
			<tbody class="[&_tr:last-child]:border-0">
				<?php if ( empty( $bookings ) ) : ?>
					<tr><td colspan="6" class="<?php echo esc_attr( buckleup_table_cell_class( 'text-center text-muted-foreground py-10' ) ); ?>"><?php esc_html_e( 'No upcoming lessons.', 'buckleup' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $bookings as $b ) :
						$id      = (int) ( $b['id'] ?? 0 );
						$s       = strtoupper( (string) ( $b['status'] ?? '' ) );
						$student = $b['student']['user']['name'] ?? ( $b['student']['name'] ?? '—' );
						$service = $b['service']['name'] ?? '—';
						$loc     = $b['location'] ?? ( $b['pickupLocation'] ?? '—' );
						$when    = ! empty( $b['datetime'] ) ? mysql2date( 'M j, Y · g:i A', $b['datetime'] ) : '—';
						?>
						<tr class="<?php echo esc_attr( buckleup_table_row_class() ); ?>" data-booking="<?php echo esc_attr( $id ); ?>">
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo esc_html( $when ); ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class( 'font-medium text-foreground' ) ); ?>"><?php echo esc_html( $student ); ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo esc_html( $service ); ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>"><?php echo esc_html( $loc ); ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class() ); ?>" data-booking-status><?php echo $status_pill( $s ); // phpcs:ignore ?></td>
							<td class="<?php echo esc_attr( buckleup_table_cell_class( 'text-right whitespace-nowrap' ) ); ?>">
								<?php if ( 'PENDING' === $s ) : ?>
									<button type="button" data-booking-action="CONFIRMED" class="<?php echo esc_attr( buckleup_button_class( 'default', 'sm', 'mr-1' ) ); ?>"><?php esc_html_e( 'Confirm', 'buckleup' ); ?></button>
									<button type="button" data-booking-action="CANCELLED" class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>"><?php esc_html_e( 'Decline', 'buckleup' ); ?></button>
								<?php elseif ( 'CONFIRMED' === $s ) : ?>
									<button type="button" data-booking-action="CANCELLED" data-booking-reason class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>"><?php esc_html_e( 'Cancel', 'buckleup' ); ?></button>
								<?php else : ?>
									<span class="text-xs text-muted-foreground">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<div data-schedule-status role="status" aria-live="polite" class="mt-4 text-sm hidden" hidden></div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'schedule', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
