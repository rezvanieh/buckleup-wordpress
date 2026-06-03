<?php
/**
 * Inline SVG icons — the lucide-react icons the source app used, reproduced as
 * raw SVG so we don't ship an icon font or React. Each icon uses the lucide
 * defaults (24x24, stroke=currentColor, stroke-width=2, round caps/joins), so
 * `[&_svg:not([class*='size-'])]:size-4` etc. size them exactly as before.
 *
 * Path data is from lucide (ISC licensed). Add icons here as new sections need
 * them; keep names matching lucide (kebab-case) for traceability.
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Return an inline SVG icon string.
 *
 * @param string $name  lucide icon name (e.g. 'chevron-down').
 * @param string $class Classes for the <svg> (default 'size-4').
 * @return string SVG markup, or '' if unknown.
 */
function buckleup_icon( string $name, string $class = '' ): string {
	$paths = array(
		'x'              => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
		'chevron-up'     => '<path d="m18 15-6-6-6 6"/>',
		'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
		'check'          => '<path d="M20 6 9 17l-5-5"/>',
		'menu'           => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
		'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
		'moon'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
		'monitor'        => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
		'star'           => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
		'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'map-pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
		'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'shield-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'instagram'      => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
		'facebook'       => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$cls = $class ?: 'size-4';
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" class="%s" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		esc_attr( $cls ),
		$paths[ $name ] // Trusted static SVG path data.
	);
}
