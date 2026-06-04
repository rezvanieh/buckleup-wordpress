<?php
/**
 * Interactive component partials — Dialog, DropdownMenu, Select, CustomTabs, and
 * the FAQ accordion. Each emits the same `data-slot` / `data-state` / `data-side`
 * attributes the React+Radix originals produced, so:
 *   - the `tw-animate-css` enter/exit utilities (data-[state=open]:animate-in …)
 *     fire exactly as before, and
 *   - the vanilla-JS layer in src/js/ (which only toggles those attributes)
 *     drives open/close, selection, and the FLIP "magic-move" tab bubble —
 *     replacing Radix + framer-motion with no behavior change.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 * CustomTabs — pill tabs with the framer-motion `layoutId="bubble"` magic-move
 * indicator (src/components/ui/custom-tabs.tsx). The active bubble is a single
 * absolutely-positioned span the JS FLIP module moves between buttons.
 * ---------------------------------------------------------------------- */

/**
 * Echo a CustomTabs pill group.
 *
 * @param array $args {
 *   @type array  $tabs    List of ['id'=>, 'label'=>].
 *   @type string $active  Active tab id.
 *   @type string $group   Unique group key (for the FLIP layout id + ARIA).
 *   @type string $class   Extra classes on the container.
 * }
 */
function buckleup_custom_tabs( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'tabs' => array(), 'active' => '', 'group' => 'tabs', 'class' => '' ) );
	if ( empty( $args['tabs'] ) ) {
		return;
	}
	$active = $args['active'] ?: $args['tabs'][0]['id'];
	$group  = sanitize_html_class( $args['group'] );

	printf(
		'<div role="tablist" data-tabs="%s" class="%s">',
		esc_attr( $group ),
		esc_attr( buckleup_cn( 'flex space-x-1 rounded-full bg-white/5 p-1 border border-white/10 backdrop-blur-md', $args['class'] ) )
	);

	foreach ( $args['tabs'] as $tab ) {
		$is_active = ( $tab['id'] === $active );
		// Active label uses primary-foreground on the solid primary pill (below) for
		// guaranteed AA in BOTH themes; inactive is muted with a foreground hover.
		$btn_class = buckleup_cn(
			'relative rounded-full px-6 py-2.5 text-sm font-medium transition focus-visible:outline-2',
			$is_active ? 'text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
		);
		printf(
			'<button type="button" role="tab" data-tab="%1$s" data-state="%2$s" aria-selected="%3$s" class="%4$s" style="-webkit-tap-highlight-color: transparent;">',
			esc_attr( $tab['id'] ),
			$is_active ? 'active' : 'inactive',
			$is_active ? 'true' : 'false',
			esc_attr( $btn_class )
		);
		// The two stacked magic-move spans (layoutId "bubble" + "bubble-bg").
		// Rendered only inside the active tab; the JS FLIP module relocates them.
		// The bubble is a SOLID bg-primary (not mix-blend-overlay) so the active
		// label's primary-foreground color hits its intended AA contrast in both
		// themes — the source's overlay blend washed the blue out to ~3:1 in dark.
		if ( $is_active ) {
			echo '<span data-bubble class="absolute inset-0 z-10 bg-primary rounded-full shadow-sm"></span>';
			echo '<span data-bubble-bg class="absolute inset-0 z-0 bg-primary/20 rounded-full"></span>';
		}
		printf( '<span class="relative z-20">%s</span>', esc_html( $tab['label'] ) );
		echo '</button>';
	}
	echo '</div>';
}

/* -------------------------------------------------------------------------
 * Dialog — class strings + a helper that assembles an overlay + content shell.
 * The JS layer toggles data-state=open/closed on trigger/close/Escape/backdrop.
 * ---------------------------------------------------------------------- */

function buckleup_dialog_overlay_class( string $extra = '' ): string {
	return buckleup_cn(
		'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/50',
		$extra
	);
}
function buckleup_dialog_content_class( string $extra = '' ): string {
	return buckleup_cn(
		'bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg',
		$extra
	);
}
function buckleup_dialog_title_class( string $extra = '' ): string {
	return buckleup_cn( 'text-lg leading-none font-semibold', $extra );
}
function buckleup_dialog_description_class( string $extra = '' ): string {
	return buckleup_cn( 'text-muted-foreground text-sm', $extra );
}

/**
 * Echo a complete dialog (overlay + content + close button), hidden until the
 * JS layer opens it. Trigger anything with data-dialog-open="{id}".
 *
 * @param array $args id (required), title, description, body (HTML), class.
 */
function buckleup_dialog( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'id' => '', 'title' => '', 'description' => '', 'body' => '', 'class' => '' ) );
	if ( ! $args['id'] ) {
		return;
	}
	$id    = sanitize_html_class( $args['id'] );
	$label = 'dialog-title-' . $id;
	?>
	<div data-dialog="<?php echo esc_attr( $id ); ?>" data-state="closed" hidden>
		<div data-dialog-overlay data-state="closed" class="<?php echo esc_attr( buckleup_dialog_overlay_class() ); ?>"></div>
		<div data-slot="dialog-content" data-state="closed" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $label ); ?>"
			class="<?php echo esc_attr( buckleup_dialog_content_class( $args['class'] ) ); ?>">
			<?php if ( $args['title'] || $args['description'] ) : ?>
				<div data-slot="dialog-header" class="flex flex-col gap-2 text-center sm:text-left">
					<?php if ( $args['title'] ) : ?>
						<h2 id="<?php echo esc_attr( $label ); ?>" class="<?php echo esc_attr( buckleup_dialog_title_class() ); ?>"><?php echo esc_html( $args['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( $args['description'] ) : ?>
						<p class="<?php echo esc_attr( buckleup_dialog_description_class() ); ?>"><?php echo esc_html( $args['description'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php echo wp_kses_post( $args['body'] ); ?>
			<button type="button" data-dialog-close
				class="ring-offset-background focus:ring-ring absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4">
				<?php echo buckleup_icon( 'x' ); // phpcs:ignore — trusted inline SVG ?>
				<span class="sr-only"><?php esc_html_e( 'Close', 'buckleup' ); ?></span>
			</button>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * DropdownMenu — class strings. The Locations nav dropdown (Task #3) and any
 * menu use these; JS toggles data-state + data-side on the content.
 * ---------------------------------------------------------------------- */

function buckleup_dropdown_content_class( string $extra = '' ): string {
	return buckleup_cn(
		'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md',
		$extra
	);
}
function buckleup_dropdown_item_class( string $extra = '' ): string {
	return buckleup_cn(
		"focus:bg-accent focus:text-accent-foreground [&_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
		$extra
	);
}

/* -------------------------------------------------------------------------
 * Select — class strings (trigger/content/item). JS opens the listbox and sets
 * the value + data-state, emitting the same markup Radix Select did.
 * ---------------------------------------------------------------------- */

function buckleup_select_trigger_class( string $extra = '' ): string {
	return buckleup_cn(
		"border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex w-fit items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
		$extra
	);
}
function buckleup_select_content_class( string $extra = '' ): string {
	return buckleup_cn(
		'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border shadow-md',
		$extra
	);
}
function buckleup_select_item_class( string $extra = '' ): string {
	return buckleup_cn(
		"focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
		$extra
	);
}

/* -------------------------------------------------------------------------
 * FAQ accordion — single source feeds both this UI and the FAQPage JSON-LD
 * (the plugin owns the schema). Native <details>/<summary> gives keyboard +
 * no-JS behavior; the JS layer animates height and toggles data-state for the
 * chevron. tw-animate-css is not needed here — CSS handles the rotate.
 * ---------------------------------------------------------------------- */

/**
 * Echo a FAQ accordion.
 *
 * @param array $args {
 *   @type array  $items List of ['question'=>, 'answer'=> (HTML allowed)].
 *   @type string $class Extra classes on the container.
 *   @type bool   $first_open  Whether the first item starts open.
 * }
 */
function buckleup_faq_accordion( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'items' => array(), 'class' => '', 'first_open' => false ) );
	if ( empty( $args['items'] ) ) {
		return;
	}
	printf( '<div data-faq class="%s">', esc_attr( buckleup_cn( 'divide-y divide-border rounded-xl border border-border bg-card', $args['class'] ) ) );
	$i = 0;
	foreach ( $args['items'] as $item ) {
		$open = ( 0 === $i && $args['first_open'] );
		printf(
			'<details data-faq-item data-state="%s" class="group"%s>',
			$open ? 'open' : 'closed',
			$open ? ' open' : ''
		);
		echo '<summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5 text-left font-medium text-foreground transition-colors hover:text-primary [&::-webkit-details-marker]:hidden">';
		echo '<span>' . esc_html( $item['question'] ) . '</span>';
		echo '<span data-faq-icon class="shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180">' . buckleup_icon( 'chevron-down' ) . '</span>'; // phpcs:ignore
		echo '</summary>';
		echo '<div data-faq-panel class="px-6 pb-5 text-muted-foreground text-pretty">' . wp_kses_post( $item['answer'] ) . '</div>';
		echo '</details>';
		$i++;
	}
	echo '</div>';
}
