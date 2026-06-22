<?php
/**
 * BuckleUp component library — server-side reproductions of the source app's
 * shadcn/Tailwind components, emitting the EXACT same class strings.
 *
 * Each shadcn component (`src/components/ui/*.tsx`) becomes a small PHP helper
 * here. The cva variant matrices (Button, Label) are reproduced 1:1; the rest
 * expose the base class string plus a slot for callers to append. Behavioral
 * components (Dialog, Dropdown, Select, Switch, Tabs, FAQ accordion) emit the
 * same `data-slot` / `data-state` attributes that `tw-animate-css` and the
 * vanilla-JS layer in `src/js/` target — so the markup, styling, and motion all
 * match the React original without React.
 *
 * Helpers return strings (class lists) or echo markup. Class helpers are pure so
 * patterns/templates can compose them; markup helpers escape their own output.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Merge a base class string with caller-supplied extra classes.
 *
 * Mirrors the source's `cn()` (clsx + tailwind-merge) at the level we need:
 * concatenation with de-duplication of exact repeats. We do NOT do full
 * tailwind-merge conflict resolution server-side — callers pass intentional
 * overrides, same as the React components, and Tailwind's cascade handles the
 * rest. Pass extra classes via the `$extra` arg.
 */
function buckleup_cn( string $base, string $extra = '' ): string {
	$classes = preg_split( '/\s+/', trim( $base . ' ' . $extra ), -1, PREG_SPLIT_NO_EMPTY );
	$seen    = array();
	foreach ( $classes as $c ) {
		$seen[ $c ] = true;
	}
	return implode( ' ', array_keys( $seen ) );
}

/**
 * Render an HTML attribute string from an associative array.
 * Skips null/false values; escapes attribute values.
 */
function buckleup_attrs( array $attrs ): string {
	$out = array();
	foreach ( $attrs as $key => $val ) {
		if ( null === $val || false === $val ) {
			continue;
		}
		if ( true === $val ) {
			$out[] = esc_attr( $key );
			continue;
		}
		$out[] = esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
	}
	return implode( ' ', $out );
}

/* -------------------------------------------------------------------------
 * Button — cva matrix ported verbatim from src/components/ui/button.tsx
 * ---------------------------------------------------------------------- */

/**
 * Return the Button class string for a variant/size combo.
 *
 * @param string $variant default|destructive|outline|secondary|ghost|link
 * @param string $size    default|sm|lg|icon|icon-sm|icon-lg
 * @param string $extra   Additional classes to append.
 */
function buckleup_button_class( string $variant = 'default', string $size = 'default', string $extra = '' ): string {
	$base = "no-underline! inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-semibold transition-all duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98]";

	$variants = array(
		'default'     => 'bg-primary text-primary-foreground shadow-md hover:bg-primary/90 hover:shadow-lg',
		'destructive' => 'bg-destructive text-destructive-foreground shadow-md hover:bg-destructive/90',
		'outline'     => 'border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary hover:border-primary/50',
		'secondary'   => 'bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80',
		'ghost'       => 'text-foreground hover:bg-secondary hover:text-foreground',
		'link'        => 'text-primary underline-offset-4 hover:underline',
	);

	$sizes = array(
		'default' => 'h-10 px-4 py-2 has-[>svg]:px-3',
		'sm'      => 'h-9 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5',
		'lg'      => 'h-11 rounded-lg px-8 has-[>svg]:px-6 text-base',
		'icon'    => 'size-10',
		'icon-sm' => 'size-9',
		'icon-lg' => 'size-11',
	);

	$variant_cls = $variants[ $variant ] ?? $variants['default'];
	$size_cls    = $sizes[ $size ] ?? $sizes['default'];

	return buckleup_cn( "$base $variant_cls $size_cls", $extra );
}

/**
 * Echo a button/link rendered with the Button class string.
 *
 * @param array $args label, href, variant, size, class, attrs (assoc), icon (HTML).
 */
function buckleup_button( array $args = array() ): void {
	$args = wp_parse_args( $args, array(
		'label'   => '',
		'href'    => '',
		'variant' => 'default',
		'size'    => 'default',
		'class'   => '',
		'icon'    => '',
		'attrs'   => array(),
	) );

	$class = buckleup_button_class( $args['variant'], $args['size'], $args['class'] );
	$tag   = $args['href'] ? 'a' : 'button';
	$attrs = $args['attrs'];
	$attrs['class']      = $class;
	$attrs['data-slot']  = 'button';
	if ( $args['href'] ) {
		$attrs['href'] = esc_url_raw( $args['href'] );
	} else {
		$attrs['type'] = $attrs['type'] ?? 'button';
	}

	printf(
		'<%1$s %2$s>%3$s%4$s</%1$s>',
		esc_html( $tag ),
		buckleup_attrs( $attrs ),
		wp_kses_post( $args['icon'] ),
		esc_html( $args['label'] )
	);
}

/* -------------------------------------------------------------------------
 * Card — src/components/ui/card.tsx
 * ---------------------------------------------------------------------- */

function buckleup_card_class( string $extra = '' ): string {
	return buckleup_cn(
		'bg-card text-card-foreground flex flex-col gap-6 rounded-xl border border-border py-6 shadow-lg shadow-black/5 dark:shadow-black/20 transition-shadow duration-300',
		$extra
	);
}
function buckleup_card_header_class( string $extra = '' ): string {
	return buckleup_cn( '@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6', $extra );
}
function buckleup_card_title_class( string $extra = '' ): string {
	return buckleup_cn( 'leading-none font-semibold', $extra );
}
function buckleup_card_description_class( string $extra = '' ): string {
	return buckleup_cn( 'text-muted-foreground text-sm', $extra );
}
function buckleup_card_content_class( string $extra = '' ): string {
	return buckleup_cn( 'px-6', $extra );
}
function buckleup_card_footer_class( string $extra = '' ): string {
	return buckleup_cn( 'flex items-center px-6 [.border-t]:pt-6', $extra );
}

/* -------------------------------------------------------------------------
 * Input / Textarea / Label — form primitives
 * ---------------------------------------------------------------------- */

function buckleup_input_class( string $extra = '' ): string {
	return buckleup_cn(
		'flex h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 py-2 text-base text-foreground shadow-sm transition-all duration-200 '
		. 'placeholder:text-muted-foreground '
		. 'file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground '
		. 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:border-primary '
		. 'disabled:cursor-not-allowed disabled:opacity-50 '
		. 'aria-invalid:border-destructive aria-invalid:ring-destructive/20 '
		. 'md:text-sm',
		$extra
	);
}

function buckleup_textarea_class( string $extra = '' ): string {
	return buckleup_cn(
		'flex min-h-[100px] w-full rounded-lg border border-input bg-background px-3 py-3 text-base text-foreground shadow-sm transition-all duration-200 '
		. 'placeholder:text-muted-foreground '
		. 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:border-primary '
		. 'disabled:cursor-not-allowed disabled:opacity-50 '
		. 'md:text-sm',
		$extra
	);
}

function buckleup_label_class( string $extra = '' ): string {
	return buckleup_cn( 'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70', $extra );
}

/* -------------------------------------------------------------------------
 * Switch — emits the same data-state attrs the JS toggles
 * ---------------------------------------------------------------------- */

function buckleup_switch_class( string $extra = '' ): string {
	return buckleup_cn(
		'peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
		$extra
	);
}
function buckleup_switch_thumb_class(): string {
	return 'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0';
}

/**
 * Echo a fully-wired Switch (role=switch button + thumb). The JS layer
 * (data-switch) flips data-state and aria-checked on click.
 *
 * @param array $args id, checked (bool), label (sr text), class, attrs.
 */
function buckleup_switch( array $args = array() ): void {
	$args  = wp_parse_args( $args, array( 'id' => '', 'checked' => false, 'label' => '', 'class' => '', 'attrs' => array() ) );
	$state = $args['checked'] ? 'checked' : 'unchecked';
	$attrs = array_merge( array(
		'type'         => 'button',
		'role'         => 'switch',
		'data-switch'  => true,
		'data-slot'    => 'switch',
		'data-state'   => $state,
		'aria-checked' => $args['checked'] ? 'true' : 'false',
		'class'        => buckleup_switch_class( $args['class'] ),
	), $args['attrs'] );
	if ( $args['id'] ) {
		$attrs['id'] = $args['id'];
	}
	if ( $args['label'] ) {
		$attrs['aria-label'] = $args['label'];
	}
	printf(
		'<button %1$s><span data-slot="switch-thumb" data-state="%2$s" class="%3$s"></span></button>',
		buckleup_attrs( $attrs ),
		esc_attr( $state ),
		esc_attr( buckleup_switch_thumb_class() )
	);
}

/* -------------------------------------------------------------------------
 * Avatar — Radix Avatar reproduction (image with text fallback)
 * ---------------------------------------------------------------------- */

function buckleup_avatar_class( string $extra = '' ): string {
	return buckleup_cn( 'relative flex size-8 shrink-0 overflow-hidden rounded-full', $extra );
}

/**
 * @param array $args src, alt, fallback (initials), class.
 */
function buckleup_avatar( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'src' => '', 'alt' => '', 'fallback' => '', 'class' => '' ) );
	echo '<span data-slot="avatar" class="' . esc_attr( buckleup_avatar_class( $args['class'] ) ) . '">';
	if ( $args['src'] ) {
		printf(
			'<img data-slot="avatar-image" class="aspect-square size-full object-cover" src="%s" alt="%s" loading="lazy" decoding="async">',
			esc_url( $args['src'] ),
			esc_attr( $args['alt'] )
		);
	} else {
		printf(
			'<span data-slot="avatar-fallback" class="bg-muted flex size-full items-center justify-center rounded-full text-sm font-medium">%s</span>',
			esc_html( $args['fallback'] )
		);
	}
	echo '</span>';
}

/* -------------------------------------------------------------------------
 * Table — class strings only (rendered by callers/blocks)
 * ---------------------------------------------------------------------- */

function buckleup_table_class( string $extra = '' ): string {
	return buckleup_cn( 'w-full caption-bottom text-sm', $extra );
}
function buckleup_table_row_class( string $extra = '' ): string {
	return buckleup_cn( 'border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', $extra );
}
function buckleup_table_head_class( string $extra = '' ): string {
	return buckleup_cn( 'h-12 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0', $extra );
}
function buckleup_table_cell_class( string $extra = '' ): string {
	return buckleup_cn( 'p-4 align-middle [&:has([role=checkbox])]:pr-0', $extra );
}

/* -------------------------------------------------------------------------
 * Eyebrow / badge pills — the small "ICBC Certified" etc. chips used across
 * landing sections. These weren't a single source component; they're the
 * recurring inline-flex rounded-full chip the landing sections share.
 * ---------------------------------------------------------------------- */

/**
 * @param string $tone primary|accent|muted|glass
 */
function buckleup_pill_class( string $tone = 'primary', string $extra = '' ): string {
	$base  = 'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium';
	$tones = array(
		'primary' => 'bg-primary/10 text-primary border border-primary/20',
		'accent'  => 'bg-accent/10 text-accent border border-accent/20',
		'muted'   => 'bg-muted text-muted-foreground border border-border',
		'glass'   => 'glass text-foreground',
	);
	return buckleup_cn( "$base " . ( $tones[ $tone ] ?? $tones['primary'] ), $extra );
}

/**
 * Echo an eyebrow/badge pill.
 *
 * @param array $args label, tone, icon (HTML), class.
 */
function buckleup_pill( array $args = array() ): void {
	$args = wp_parse_args( $args, array( 'label' => '', 'tone' => 'primary', 'icon' => '', 'class' => '' ) );
	printf(
		'<span class="%s">%s%s</span>',
		esc_attr( buckleup_pill_class( $args['tone'], $args['class'] ) ),
		wp_kses_post( $args['icon'] ),
		esc_html( $args['label'] )
	);
}
