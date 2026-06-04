<?php
/**
 * Title: Console: Instructor — My Students
 * Slug: buckleup/console-instructor-students
 * Inserter: no
 *
 * /instructor/students — roster of this instructor's students, matching
 * src/app/instructor/students/page.tsx. 3 stat cards (Total Students / Upcoming
 * Lessons / Total Lessons Taught) + search + filters (All / Has Upcoming /
 * Active) + per-student cards (contact, license, completed/last/next, latest
 * skills bars, services). All data is server-rendered from GET
 * /instructors/students (rest_do_request); search + filter are client-side
 * (console-students.js) over the already-rendered cards — no extra fetch. The
 * source's dead ChevronRight "open student" action is OMITTED (no detail page).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$students = array();
if ( function_exists( 'rest_do_request' ) ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/buckleup/v1/instructors/students' ) );
	if ( 200 === $res->get_status() ) {
		$d        = (array) $res->get_data();
		$students = (array) ( $d['students'] ?? array() );
	}
}

$total          = count( $students );
$with_upcoming  = 0;
$lessons_taught = 0;
foreach ( $students as $s ) {
	if ( ! empty( $s['nextLessonDate'] ) ) {
		$with_upcoming++;
	}
	$lessons_taught += (int) ( $s['completedLessons'] ?? 0 );
}

// Skill-bar colour by score (5-point scale), mirroring the source thresholds.
$skill_bar = static function ( $rating ) {
	$pct = max( 0, min( 100, ( (float) $rating / 5 ) * 100 ) );
	if ( $pct >= 80 ) {
		$colour = 'bg-green-500';
	} elseif ( $pct >= 60 ) {
		$colour = 'bg-yellow-500';
	} else {
		$colour = 'bg-orange-500';
	}
	return array( $pct, $colour );
};

$stat_cards = array(
	array( 'icon' => 'users',          'icon_class' => 'text-primary', 'label' => __( 'Total Students', 'buckleup' ),       'value' => $total ),
	array( 'icon' => 'calendar',       'icon_class' => 'text-accent',  'label' => __( 'Upcoming Lessons', 'buckleup' ),      'value' => $with_upcoming ),
	array( 'icon' => 'graduation-cap', 'icon_class' => 'text-amber-500', 'label' => __( 'Total Lessons Taught', 'buckleup' ), 'value' => $lessons_taught ),
);

ob_start();
echo buckleup_console_heading( // phpcs:ignore WordPress.Security.EscapeOutput
	__( 'My Students', 'buckleup' ),
	sprintf(
		/* translators: 1: total student count, 2: count with upcoming lessons */
		_n( '%1$d total student • %2$d with upcoming lessons', '%1$d total students • %2$d with upcoming lessons', $total, 'buckleup' ),
		$total,
		$with_upcoming
	)
);
?>
<!-- Stat cards -->
<div class="grid gap-4 md:grid-cols-3 mb-6">
	<?php foreach ( $stat_cards as $c ) : ?>
		<div class="<?php echo esc_attr( buckleup_card_class( 'p-6' ) ); ?>">
			<div class="flex items-center justify-between mb-2">
				<span class="text-sm font-medium text-muted-foreground"><?php echo esc_html( $c['label'] ); ?></span>
				<span class="<?php echo esc_attr( $c['icon_class'] ); ?>"><?php echo buckleup_icon( $c['icon'], 'w-4 h-4' ); // phpcs:ignore ?></span>
			</div>
			<div class="text-3xl font-bold text-foreground"><?php echo (int) $c['value']; ?></div>
		</div>
	<?php endforeach; ?>
</div>

<!-- Search + filters -->
<div class="flex flex-col sm:flex-row gap-4 mb-6" data-students-toolbar>
	<div class="relative flex-1">
		<span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><?php echo buckleup_icon( 'search', 'w-4 h-4' ); // phpcs:ignore ?></span>
		<input type="search" data-students-search placeholder="<?php esc_attr_e( 'Search by name or email…', 'buckleup' ); ?>" class="<?php echo esc_attr( buckleup_input_class( 'pl-10' ) ); ?>">
	</div>
	<div class="flex gap-2">
		<button type="button" data-students-filter="all" aria-pressed="true" class="<?php echo esc_attr( buckleup_button_class( 'default', 'sm' ) ); ?>"><?php esc_html_e( 'All', 'buckleup' ); ?></button>
		<button type="button" data-students-filter="upcoming" aria-pressed="false" class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>"><?php esc_html_e( 'Has Upcoming', 'buckleup' ); ?></button>
		<button type="button" data-students-filter="active" aria-pressed="false" class="<?php echo esc_attr( buckleup_button_class( 'outline', 'sm' ) ); ?>"><?php esc_html_e( 'Active', 'buckleup' ); ?></button>
	</div>
</div>

<!-- Roster -->
<div class="grid gap-4" data-students-list>
	<?php if ( empty( $students ) ) : ?>
		<div class="<?php echo esc_attr( buckleup_card_class( 'items-center justify-center py-12 text-center' ) ); ?>" data-students-empty>
			<span class="text-muted-foreground/50 mb-4"><?php echo buckleup_icon( 'users', 'w-12 h-12' ); // phpcs:ignore ?></span>
			<p class="text-lg font-medium text-foreground"><?php esc_html_e( 'No students found', 'buckleup' ); ?></p>
			<p class="text-sm text-muted-foreground"><?php esc_html_e( 'Students will appear here once they book lessons with you.', 'buckleup' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $students as $s ) :
			$name      = (string) ( $s['name'] ?? __( 'Student', 'buckleup' ) );
			$email     = (string) ( $s['email'] ?? '' );
			$phone     = (string) ( $s['phone'] ?? '' );
			$avatar    = (string) ( $s['avatar'] ?? '' );
			$license   = (string) ( $s['licenseType'] ?? '' );
			$status    = strtoupper( (string) ( $s['status'] ?? 'ACTIVE' ) );
			$completed = (int) ( $s['completedLessons'] ?? 0 );
			$last      = ! empty( $s['lastLessonDate'] ) ? date_i18n( 'M j, Y', strtotime( $s['lastLessonDate'] ) ) : '';
			$next      = ! empty( $s['nextLessonDate'] ) ? date_i18n( 'M j, Y', strtotime( $s['nextLessonDate'] ) ) : '';
			$services  = array_values( (array) ( $s['services'] ?? array() ) );
			$progress  = is_array( $s['latestProgress'] ?? null ) ? $s['latestProgress'] : array();
			$initials  = '';
			foreach ( array_slice( preg_split( '/\s+/', trim( $name ) ), 0, 2 ) as $part ) {
				$initials .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
			}
			// Lower-cased haystack for the JS search filter.
			$haystack = strtolower( $name . ' ' . $email );
			?>
			<div class="<?php echo esc_attr( buckleup_card_class( 'p-6 hover-lift' ) ); ?>"
				data-student
				data-student-search="<?php echo esc_attr( $haystack ); ?>"
				data-student-upcoming="<?php echo $next ? '1' : '0'; ?>"
				data-student-active="<?php echo 'ACTIVE' === $status ? '1' : '0'; ?>">
				<div class="flex items-start gap-4">
					<!-- Avatar -->
					<div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center shrink-0 overflow-hidden">
						<?php if ( $avatar ) : ?>
							<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="w-full h-full object-cover" loading="lazy" decoding="async">
						<?php else : ?>
							<span class="text-lg font-bold text-primary"><?php echo esc_html( strtoupper( $initials ) ); ?></span>
						<?php endif; ?>
					</div>

					<div class="flex-1 min-w-0">
						<!-- Name + status -->
						<div class="flex items-center gap-2 mb-1">
							<h3 class="font-semibold text-lg text-foreground truncate"><?php echo esc_html( $name ); ?></h3>
							<?php if ( 'ACTIVE' === $status ) : ?>
								<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400"><?php esc_html_e( 'Active', 'buckleup' ); ?></span>
							<?php else : ?>
								<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground"><?php echo esc_html( ucfirst( strtolower( $status ) ) ); ?></span>
							<?php endif; ?>
						</div>

						<!-- Contact line -->
						<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground mb-3">
							<?php if ( $email ) : ?><span class="flex items-center gap-1"><?php echo buckleup_icon( 'mail', 'w-3.5 h-3.5' ); // phpcs:ignore ?><?php echo esc_html( $email ); ?></span><?php endif; ?>
							<?php if ( $phone ) : ?><span class="flex items-center gap-1"><?php echo buckleup_icon( 'phone', 'w-3.5 h-3.5' ); // phpcs:ignore ?><?php echo esc_html( $phone ); ?></span><?php endif; ?>
							<?php if ( $license ) : ?><span class="flex items-center gap-1"><?php echo buckleup_icon( 'graduation-cap', 'w-3.5 h-3.5' ); // phpcs:ignore ?><?php printf( /* translators: %s: licence class */ esc_html__( 'Class %s', 'buckleup' ), esc_html( $license ) ); ?></span><?php endif; ?>
						</div>

						<!-- Lesson stats -->
						<div class="flex flex-wrap gap-4 text-sm">
							<span class="flex items-center gap-2"><span class="text-green-500"><?php echo buckleup_icon( 'check-circle', 'w-4 h-4' ); // phpcs:ignore ?></span><span class="text-foreground font-medium"><?php echo (int) $completed; ?></span><span class="text-muted-foreground"><?php esc_html_e( 'completed', 'buckleup' ); ?></span></span>
							<span class="flex items-center gap-2"><span class="text-muted-foreground"><?php echo buckleup_icon( 'clock', 'w-4 h-4' ); // phpcs:ignore ?></span><span class="text-muted-foreground"><?php echo $last ? esc_html( sprintf( /* translators: %s: date */ __( 'Last: %s', 'buckleup' ), $last ) ) : esc_html__( 'No lessons yet', 'buckleup' ); ?></span></span>
							<?php if ( $next ) : ?>
								<span class="flex items-center gap-2"><span class="text-primary"><?php echo buckleup_icon( 'calendar', 'w-4 h-4' ); // phpcs:ignore ?></span><span class="text-primary font-medium"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Next: %s', 'buckleup' ), $next ) ); ?></span></span>
							<?php endif; ?>
						</div>

						<!-- Latest skills assessment -->
						<?php if ( ! empty( $progress ) ) : ?>
							<div class="mt-4 pt-4 border-t border-border">
								<p class="text-xs font-medium text-muted-foreground mb-2"><?php esc_html_e( 'Latest Skills Assessment', 'buckleup' ); ?></p>
								<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
									<?php foreach ( array_slice( $progress, 0, 4, true ) as $skill => $rating ) :
										list( $pct, $colour ) = $skill_bar( $rating );
										?>
										<div class="space-y-1">
											<div class="flex justify-between text-xs">
												<span class="text-muted-foreground truncate"><?php echo esc_html( $skill ); ?></span>
												<span class="text-foreground font-medium"><?php echo esc_html( (float) $rating ); ?>/5</span>
											</div>
											<div class="w-full h-1.5 bg-muted rounded-full overflow-hidden">
												<div class="h-full rounded-full <?php echo esc_attr( $colour ); ?>" style="width: <?php echo esc_attr( (float) $pct ); ?>%"></div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<!-- Services -->
						<?php if ( ! empty( $services ) ) : ?>
							<div class="flex flex-wrap gap-2 mt-3">
								<?php foreach ( $services as $svc ) : ?>
									<span class="px-2 py-1 bg-secondary text-secondary-foreground text-xs rounded-md"><?php echo esc_html( $svc ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>

		<!-- No-results state (shown by JS when search/filter empties the list) -->
		<div class="<?php echo esc_attr( buckleup_card_class( 'items-center justify-center py-12 text-center hidden' ) ); ?>" data-students-noresults>
			<span class="text-muted-foreground/50 mb-4"><?php echo buckleup_icon( 'users', 'w-12 h-12' ); // phpcs:ignore ?></span>
			<p class="text-lg font-medium text-foreground"><?php esc_html_e( 'No students found', 'buckleup' ); ?></p>
			<p class="text-sm text-muted-foreground"><?php esc_html_e( 'Try adjusting your search.', 'buckleup' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<?php
$content = (string) ob_get_clean();

echo buckleup_console_shell( 'instructor', 'students', $content ); // phpcs:ignore WordPress.Security.EscapeOutput
