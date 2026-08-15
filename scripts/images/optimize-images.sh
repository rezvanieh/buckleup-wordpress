#!/bin/sh
# Re-encode the site's images.
#
#   - WebP siblings are regenerated at a sane quality. The existing ones were
#     encoded at near-lossless settings, so several were within 0.1% of the JPEG
#     they were supposed to replace, and two logo siblings were 10x LARGER than
#     their PNG.
#   - JPEG originals over the 250 KB budget are re-encoded, so the fallback the
#     browser gets when it cannot do WebP is also within budget.
#   - Flat logo art is encoded LOSSLESS, where WebP genuinely beats PNG; photos
#     are encoded lossy.
#
# A new file is only kept when it is actually smaller than what it replaces.
# Originals are backed up to /wp/wp-content/uploads/.bu-image-backup first.
#
# Usage: optimize-images.sh <listfile> [apply]
#   listfile: newline-separated paths relative to the uploads dir
set -e

LIST="$1"
APPLY="$2"
UP=/wp/wp-content/uploads
BACKUP="$UP/.bu-image-backup"
BUDGET=250000
Q=70

[ "$APPLY" = "apply" ] && mkdir -p "$BACKUP"

saved_total=0
changed=0

backup() {
  [ "$APPLY" = "apply" ] || return 0
  rel="${1#$UP/}"
  d="$BACKUP/$(dirname "$rel")"
  mkdir -p "$d"
  [ -f "$BACKUP/$rel" ] || cp -p "$1" "$BACKUP/$rel"
}

# is this flat logo/graphic art rather than a photo?
is_logo() {
  case "$(basename "$1")" in
    logo*|*-logo*|icon-*) return 0 ;;
    *) return 1 ;;
  esac
}

while IFS= read -r rel; do
  [ -z "$rel" ] && continue
  src="$UP/$rel"
  [ -f "$src" ] || continue
  osize=$(stat -c%s "$src")

  # ---------- 1. the WebP sibling ----------
  webp="$src.webp"
  cur=0
  [ -f "$webp" ] && cur=$(stat -c%s "$webp")

  tmp=/tmp/out.webp
  if is_logo "$src"; then
    cwebp -quiet -lossless -z 9 "$src" -o "$tmp" 2>/dev/null || true
  else
    cwebp -quiet -q $Q -m 6 "$src" -o "$tmp" 2>/dev/null || true
  fi

  if [ -f "$tmp" ]; then
    new=$(stat -c%s "$tmp")
    # Keep the sibling only if it beats BOTH the current sibling and the original;
    # a WebP bigger than its own JPEG/PNG is worse than having none.
    if [ "$new" -lt "$osize" ] && { [ "$cur" -eq 0 ] || [ "$new" -lt "$cur" ]; }; then
      was=$cur; [ "$was" -eq 0 ] && was=$osize
      printf '  webp  %9s -> %9s  %s\n' "$was" "$new" "$rel"
      saved_total=$((saved_total + was - new))
      changed=$((changed+1))
      if [ "$APPLY" = "apply" ]; then
        [ -f "$webp" ] && backup "$webp"
        cp "$tmp" "$webp"
      fi
    elif [ "$cur" -gt "$osize" ]; then
      # Existing sibling is bigger than the original and we cannot beat it:
      # remove it so <picture> stops serving the heavier file.
      printf '  DROP  %9s (bigger than the %s original)  %s.webp\n' "$cur" "$osize" "$rel"
      saved_total=$((saved_total + cur - osize))
      changed=$((changed+1))
      [ "$APPLY" = "apply" ] && { backup "$webp"; rm -f "$webp"; }
    fi
    rm -f "$tmp"
  fi

  # ---------- 2. the original, if over budget ----------
  if [ "$osize" -gt "$BUDGET" ]; then
    case "$rel" in
      *.jpg|*.jpeg|*.JPG|*.JPEG)
        tmp2=/tmp/out.jpg
        convert "$src" -strip -interlace Plane -quality $Q "$tmp2" 2>/dev/null || true
        if [ -f "$tmp2" ]; then
          n2=$(stat -c%s "$tmp2")
          if [ "$n2" -lt "$osize" ]; then
            printf '  jpeg  %9s -> %9s  %s\n' "$osize" "$n2" "$rel"
            saved_total=$((saved_total + osize - n2))
            changed=$((changed+1))
            [ "$APPLY" = "apply" ] && { backup "$src"; cp "$tmp2" "$src"; }
          fi
          rm -f "$tmp2"
        fi
        ;;
      *.png|*.PNG)
        tmp3=/tmp/out.png
        optipng -quiet -o2 -out "$tmp3" "$src" 2>/dev/null || true
        if [ -f "$tmp3" ]; then
          n3=$(stat -c%s "$tmp3")
          if [ "$n3" -lt "$osize" ]; then
            printf '  png   %9s -> %9s  %s\n' "$osize" "$n3" "$rel"
            saved_total=$((saved_total + osize - n3))
            changed=$((changed+1))
            [ "$APPLY" = "apply" ] && { backup "$src"; cp "$tmp3" "$src"; }
          fi
          rm -f "$tmp3"
        fi
        ;;
    esac
  fi
done < "$LIST"

echo ""
echo "  files changed: $changed"
echo "  bytes saved:   $saved_total"
[ "$APPLY" = "apply" ] || echo "  (DRY RUN — pass 'apply' to write)"
