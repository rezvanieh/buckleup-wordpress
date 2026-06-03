#!/usr/bin/env bash
# Generates 5 on-brand per-category blog featured-image cards (Task #24, option C).
# Each card: the design-system 135° primary->accent gradient + category label +
# the BuckleUp wordmark, 1200x630 (OG/featured ratio). Authored as SVG and
# rasterized to PNG with rsvg-convert, into content/blog-cards/ (committed source)
# — provision.sh stages these into the media-import dir like the other brand media.
#
# Colors are the theme's light-mode tokens (src/css/app.css):
#   --primary 217 91% 46% = #0b5ce0   --accent 160 84% 39% = #10b77f
# Requires: rsvg-convert (librsvg). Re-run any time; overwrites the PNGs.
set -euo pipefail
cd "$(dirname "$0")/.."

OUT="content/blog-cards"
LOGO_SRC="wp-content/themes/buckleup/assets/img/logo-dark.png"   # white wordmark (for dark bg)
# Fallbacks for the logo source if the theme asset isn't present.
[ -f "$LOGO_SRC" ] || LOGO_SRC="wp-data/media-import/logo-dark.png"
[ -f "$LOGO_SRC" ] || LOGO_SRC="/Users/esfandiyar/Projects/Buckleup/public/logo-dark.png"

if ! command -v rsvg-convert >/dev/null 2>&1; then
  echo "rsvg-convert not found — install librsvg (brew install librsvg) or use the OPTION-A fallback." >&2
  exit 1
fi

mkdir -p "$OUT"

# Embed the logo as a base64 data URI so the SVG is self-contained.
LOGO_DATA=""
if [ -f "$LOGO_SRC" ]; then
  LOGO_DATA="data:image/png;base64,$(base64 < "$LOGO_SRC" | tr -d '\n')"
fi

# category | label | eyebrow
cards="Tips|Driving Tips|EXPERT ADVICE
Tutorials|Step-by-Step Tutorials|HOW-TO GUIDES
Safety|Road Safety|DRIVE CONFIDENT
Local|Local Routes & Areas|METRO VANCOUVER
Licensing|ICBC Licensing|7L · 7N · CLASS 5"

while IFS='|' read -r cat label eyebrow; do
  [ -z "$cat" ] && continue
  slug="$(echo "$cat" | tr '[:upper:]' '[:lower:]')"
  svg="$OUT/card-$slug.svg"
  png="$OUT/blog-card-$slug.png"

  # XML-escape the label/eyebrow text (e.g. "Routes & Areas" -> "&amp;").
  xml_escape() { printf '%s' "$1" | sed -e 's/&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g'; }
  label="$(xml_escape "$label")"
  eyebrow="$(xml_escape "$eyebrow")"

  logo_el=""
  if [ -n "$LOGO_DATA" ]; then
    # wordmark bottom-left, ~320px wide preserving the 1815x355 ratio (~63px tall)
    logo_el="<image x=\"80\" y=\"470\" width=\"320\" height=\"63\" href=\"$LOGO_DATA\" preserveAspectRatio=\"xMinYMin meet\"/>"
  fi

  cat > "$svg" <<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0b5ce0"/>
      <stop offset="55%" stop-color="#7c4dd6"/>
      <stop offset="100%" stop-color="#10b77f"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <!-- subtle glow blobs for depth -->
  <circle cx="1040" cy="120" r="240" fill="#ffffff" opacity="0.08"/>
  <circle cx="170" cy="560" r="200" fill="#ffffff" opacity="0.06"/>
  <text x="80" y="150" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700" letter-spacing="6" fill="#ffffff" opacity="0.85">$eyebrow</text>
  <text x="80" y="300" font-family="Arial, Helvetica, sans-serif" font-size="92" font-weight="800" fill="#ffffff">$label</text>
  <rect x="82" y="345" width="120" height="8" rx="4" fill="#ffffff" opacity="0.9"/>
  $logo_el
</svg>
SVG

  rsvg-convert -w 1200 -h 630 "$svg" -o "$png"
  echo "  generated: $png"
done <<< "$cards"

echo "Done: 5 per-category blog cards in $OUT/"
