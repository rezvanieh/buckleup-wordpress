<?php
/**
 * Title: Console: Instructor — Availability
 * Slug: buckleup/console-instructor-availability
 * Inserter: no
 *
 * /instructor/availability — two tabs (Weekly Schedule + Calendar Exceptions),
 * matching src/app/instructor/availability/page.tsx. The 7-day weekly grid is
 * server-rendered from GET /instructors/availability (rest_do_request) so the
 * page shows real hours without JS; saving each day's switch+times is a JS
 * mutation (console-availability.js) → PUT (enabled) / DELETE (disabled) on
 * /instructors/availability. The Calendar tab (month grid + exception dialog +
 * upcoming list) is JS-rendered from GET .../availability/exceptions and saved
 * via POST/DELETE. Uses window.buckleupAuth.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$days = array(
	__( 'Sunday', 'buckleup' ),
	__( 'Monday', 'buckleup' ),
	__( 'Tuesday', 'buckleup' ),
	__( 'Wednesday', 'buckleup' ),
	__( 'Thursday', 'buckleup' ),
	__( 'Friday', 'buckleup' ),
	__( 'Saturday', 'buckleup' ),
);

// Server-render the weekly state (real data). Map dayOfWeek → {start,end,enabled}.
$weekly = array();
foreach ( $days as $i => $name ) {
	$weekly[ $i ] = array( 'start' => '09:00', 'end' => '17:00', 'enabled' => false );
}
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/instructors/availability' ) );
	if ( 200 === $res->get_status() ) {
		$d = (array) $res->get_data();
		foreach ( (array) ( $d['availability'] ?? array() ) as $a ) {
			$dow = (int) ( $a['dayOfWeek'] ?? -1 );
			if ( isset( $weekly[ $dow ] ) ) {
				$weekly[ $dow ] = array(
					'start'   => substr( (string) ( $a['startTime'] ?? '09:00' ), 0, 5 ),
					'end'     => substr( (string) ( $a['endTime'] ?? '17:00' ), 0, 5 ),
					'enabled' => true,
				);
			}
		}
	}
}

ob_start();
echo buckleup_console_heading( __( 'My Availability', 'buckleup' ), __( 'Manage your weekly schedule and specific date exceptions.', 'buckleup' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<!-- Tabs -->
<div class="flex gap-2 p-1 bg-muted rounded-lg w-fit mb-6" role="tablist" data-avail-tabs>
	<button type="button" role="tab" data-avail-tab="weekly" aria-selected="true" class="px-4 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2 bg-background text-foreground shadow-sm">
		<?php echo buckleup_icon( 'clock', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Weekly Schedule', 'buckleup' ); ?>
	</button>
	<button type="button" role="tab" data-avail-tab="calendar" aria-selected="false" class="px-4 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2 text-muted-foreground hover:text-foreground">
		<?php echo buckleup_icon( 'calendar', 'w-4 h-4' ); // phpcs:ignore ?><?php esc_html_e( 'Calendar Exceptions', 'buckleup' ); ?>
	</button>
</div>

<!-- Weekly Schedule tab -->
<div data-avail-panel="weekly">
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 md:p-8' ) ); ?>">
		<h2 class="text-lg font-semibold text-foreground"><?php esc_html_e( 'Weekly Schedule', 'buckleup' ); ?></h2>
		<p class="text-sm text-muted-foreground mb-5"><?php esc_html_e( 'Set your regular working hours for each day of the week.', 'buckleup' ); ?></p>

		<div class="space-y-3" data-weekly>
			<?php foreach ( $days as $i => $name ) :
				$row = $weekly[ $i ];
				$on  = $row['enabled'];
				?>
				<div class="flex flex-wrap items-center gap-4 p-4 rounded-lg border transition-all <?php echo $on ? 'bg-accent/5 border-accent/20' : 'bg-muted/50 border-transparent'; ?>" data-weekly-day="<?php echo (int) $i; ?>">
					<div class="w-28 flex items-center gap-3">
						<?php
						buckleup_switch( array(
							'checked' => $on,
							'label'   => sprintf( /* translators: %s: weekday name */ __( 'Toggle %s', 'buckleup' ), $name ),
							'attrs'   => array( 'data-weekly-toggle' => (string) $i ),
						) );
						?>
						<span class="font-medium <?php echo $on ? 'text-foreground' : 'text-muted-foreground'; ?>" data-weekly-label><?php echo esc_html( $name ); ?></span>
					</div>
					<div class="flex items-center gap-2 <?php echo $on ? '' : 'hidden'; ?>" data-weekly-times>
						<input type="time" value="<?php echo esc_attr( $row['start'] ); ?>" data-weekly-start aria-label="<?php esc_attr_e( 'Start time', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'w-32' ) ); ?>">
						<span class="text-muted-foreground text-sm"><?php esc_html_e( 'to', 'buckleup' ); ?></span>
						<input type="time" value="<?php echo esc_attr( $row['end'] ); ?>" data-weekly-end aria-label="<?php esc_attr_e( 'End time', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'w-32' ) ); ?>">
					</div>
					<span class="text-sm text-muted-foreground <?php echo $on ? 'hidden' : ''; ?>" data-weekly-off><?php esc_html_e( 'Not available', 'buckleup' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="pt-5 mt-5 border-t border-border">
			<?php
			buckleup_button( array(
				'label' => __( 'Save Weekly Schedule', 'buckleup' ),
				'icon'  => buckleup_icon( 'save', 'w-4 h-4' ),
				'attrs' => array( 'type' => 'button', 'data-weekly-save' => true ),
			) );
			?>
		</div>
	</div>
</div>

<!-- Calendar Exceptions tab -->
<div data-avail-panel="calendar" class="hidden space-y-6">
	<!-- Legend -->
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-5' ) ); ?>">
		<div class="flex flex-wrap gap-6 text-sm">
			<div class="flex items-center gap-2"><span class="w-4 h-4 rounded bg-accent"></span><span class="text-muted-foreground"><?php esc_html_e( 'Available (Weekly)', 'buckleup' ); ?></span></div>
			<div class="flex items-center gap-2"><span class="w-4 h-4 rounded bg-yellow-500"></span><span class="text-muted-foreground"><?php esc_html_e( 'Custom Hours', 'buckleup' ); ?></span></div>
			<div class="flex items-center gap-2"><span class="w-4 h-4 rounded bg-destructive"></span><span class="text-muted-foreground"><?php esc_html_e( 'Day Off', 'buckleup' ); ?></span></div>
			<div class="flex items-center gap-2"><span class="w-4 h-4 rounded bg-muted border border-border"></span><span class="text-muted-foreground"><?php esc_html_e( 'Not Available', 'buckleup' ); ?></span></div>
		</div>
	</div>

	<!-- Month grid -->
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<div class="flex items-center justify-between mb-1">
			<h2 class="text-lg font-semibold text-foreground" data-cal-title></h2>
			<div class="flex gap-2">
				<button type="button" data-cal-prev aria-label="<?php esc_attr_e( 'Previous month', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'outline', 'icon' ) ); ?>"><?php echo buckleup_icon( 'chevron-left', 'w-4 h-4' ); // phpcs:ignore ?></button>
				<button type="button" data-cal-next aria-label="<?php esc_attr_e( 'Next month', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_button_class( 'outline', 'icon' ) ); ?>"><?php echo buckleup_icon( 'chevron-right', 'w-4 h-4' ); // phpcs:ignore ?></button>
			</div>
		</div>
		<p class="text-sm text-muted-foreground mb-4"><?php esc_html_e( 'Click on any date to add or modify exceptions.', 'buckleup' ); ?></p>

		<div class="grid grid-cols-7 gap-1 mb-2">
			<?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $sd ) : ?>
				<div class="text-center text-sm font-medium text-muted-foreground py-2"><?php echo esc_html( $sd ); ?></div>
			<?php endforeach; ?>
		</div>
		<div class="grid grid-cols-7 gap-1" data-cal-grid></div>
	</div>

	<!-- Upcoming exceptions -->
	<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?> hidden" data-exc-card>
		<h3 class="text-lg font-semibold text-foreground mb-4"><?php esc_html_e( 'Upcoming Exceptions', 'buckleup' ); ?></h3>
		<div class="space-y-2" data-exc-list></div>
	</div>
</div>

<div data-avail-status role="status" aria-live="polite" class="mt-4 text-sm hidden" hidden></div>

<!-- Exception dialog -->
<div data-exc-dialog data-state="closed" class="fixed inset-0 z-50 hidden items-center justify-center p-4" hidden>
	<div data-exc-overlay class="absolute inset-0 bg-black/50"></div>
	<div role="dialog" aria-modal="true" aria-labelledby="bu-exc-title" class="relative w-full max-w-md <?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
		<button type="button" data-exc-close aria-label="<?php esc_attr_e( 'Close', 'buckleup' ); ?>" class="absolute right-4 top-4 text-muted-foreground hover:text-foreground"><?php echo buckleup_icon( 'x', 'w-4 h-4' ); // phpcs:ignore ?></button>
		<h2 id="bu-exc-title" class="text-lg font-semibold text-foreground" data-exc-date></h2>
		<p class="text-sm text-muted-foreground mb-4"><?php esc_html_e( 'Set an exception for this specific date.', 'buckleup' ); ?></p>

		<div class="flex items-center justify-between p-4 rounded-lg bg-muted/50 mb-4">
			<span class="font-medium text-foreground" data-exc-mode-label><?php esc_html_e( 'Day off (Unavailable)', 'buckleup' ); ?></span>
			<?php
			buckleup_switch( array(
				'checked' => false,
				'label'   => __( 'Available with custom hours', 'buckleup' ),
				'attrs'   => array( 'data-exc-available' => true ),
			) );
			?>
		</div>

		<div class="flex items-center gap-3 mb-4 hidden" data-exc-times>
			<div class="flex-1">
				<label class="<?php echo esc_attr( buckleup_label_class( 'mb-1 block' ) ); ?>"><?php esc_html_e( 'Start Time', 'buckleup' ); ?></label>
				<input type="time" value="09:00" data-exc-start class="<?php echo esc_attr( buckleup_input_class() ); ?>">
			</div>
			<div class="flex-1">
				<label class="<?php echo esc_attr( buckleup_label_class( 'mb-1 block' ) ); ?>"><?php esc_html_e( 'End Time', 'buckleup' ); ?></label>
				<input type="time" value="17:00" data-exc-end class="<?php echo esc_attr( buckleup_input_class() ); ?>">
			</div>
		</div>

		<div class="mb-5" data-exc-reason-wrap>
			<label class="<?php echo esc_attr( buckleup_label_class( 'mb-1 block' ) ); ?>"><?php esc_html_e( 'Reason (optional)', 'buckleup' ); ?></label>
			<textarea rows="2" data-exc-reason placeholder="<?php esc_attr_e( "e.g., Vacation, Doctor's appointment, Personal day…", 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_textarea_class( 'min-h-16' ) ); ?>"></textarea>
		</div>

		<div class="flex flex-wrap gap-2">
			<button type="button" data-exc-remove class="<?php echo esc_attr( buckleup_button_class( 'outline', 'default', 'mr-auto text-destructive hover:bg-destructive/10 hidden' ) ); ?>"><?php esc_html_e( 'Remove Exception', 'buckleup' ); ?></button>
			<button type="button" data-exc-cancel class="<?php echo esc_attr( buckleup_button_class( 'outline' ) ); ?>"><?php esc_html_e( 'Cancel', 'buckleup' ); ?></button>
			<button type="button" data-exc-save class="<?php echo esc_attr( buckleup_button_class() ); ?>"><?php esc_html_e( 'Save Exception', 'buckleup' ); ?></button>
		</div>
	</div>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'availability', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
