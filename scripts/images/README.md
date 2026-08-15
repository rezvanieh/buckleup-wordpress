# Image optimisation

`optimize-images.sh` re-encodes the site's images. Uploads are runtime data and
are **not** in this repo (`.gitignore`: `wp-content/uploads/`), so the script is
kept here and run against the Docker volume that holds them.

## Why it exists

The WebP siblings the theme serves via `<picture>` (see `inc/webp.php`) had been
generated at near-lossless quality. Measured on 2026-08-15:

- `b16a4f74….jpeg` — 348,534 bytes, WebP sibling 348,056. A **0.1%** saving, so
  the WebP was doing nothing.
- `logo.png` — 22,071 bytes, WebP sibling **248,650**. WebP was 10x *larger* than
  the PNG, because flat logo art was encoded lossy with an alpha channel. Encoded
  losslessly it is 18,652.

## What it does

- Photos: `cwebp -q 70 -m 6`. Logos and icons: `cwebp -lossless -z 9`.
- Originals over 250 KB are re-encoded (JPEG via ImageMagick, PNG via optipng).
- A new file is kept **only if it is smaller** than what it replaces, and a WebP
  sibling is kept only if it also beats its own original — a WebP larger than its
  JPEG is worse than having none, since `<picture>` would serve the heavier file.
- Everything it replaces is copied to `wp-content/uploads/.bu-image-backup`
  first, preserving paths, so any file can be restored.

## Run it

```bash
# dry run (prints what would change)
docker run --rm -v buckleup_wp-core:/wp -v "$PWD/scripts/images:/s" debian:stable-slim sh -lc \
  'apt-get update -qq && apt-get install -y -qq webp imagemagick optipng && sh /s/optimize-images.sh /s/worklist.txt'

# apply
... sh /s/optimize-images.sh /s/worklist.txt apply
```

`worklist.txt` is a newline-separated list of paths relative to the uploads
directory. Build it from the images the site actually references rather than
every file on disk — most of the 422 files in uploads are unused size variants.

Run it **twice** when originals change: the first pass shrinks the original, the
second rebuilds the WebP sibling from the improved source.

## Known limit

Two full-size gallery photos cannot reach 250 KB. They are already stored at
JPEG quality 50 and are grainy low-light phone photos: re-encoding at q60 makes
them *bigger*, and q30 still lands at ~253 KB while visibly degrading them. They
are left alone. It does not matter in practice — the gallery's `srcset` means
browsers download the 768w variant (≈155–175 KB as WebP), never the full size.
