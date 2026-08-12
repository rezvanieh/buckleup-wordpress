# Elementor authoring & rebuild — `scripts/wp/elementor/`

The marketing **page bodies** (Home/About/Services/Instructors/Contact/Resources/
ICBC) and the **site footer** are authored in Elementor. Their design lives in the
**database** (`_elementor_data` post-meta + the Kit's global colors/fonts), which is
**NOT version-controlled**. These scripts are the source of truth that *regenerates*
that DB content, so a fresh install (or `make reset`) can rebuild the Elementor site
deterministically. Run them with WP-CLI against the dev stack.

## What the live site is made of (hybrid model)

- **Elementor:** page bodies on Home/About/Services/Instructors/Contact/Resources/ICBC;
  the **footer** = Elementor library template `site-footer`, embedded by
  `[buckleup_elementor slug="site-footer"]` in `themes/buckleup/parts/footer.html`;
  the **Kit** = global colors/fonts.
- ⚠ **Post IDs are not stable and are no longer hard-coded.** A fresh `make provision`
  numbers posts in seed order, so any seed change renumbers everything after it — the
  17 real Google-review testimonials pushed Home from **38** to **49**. Every builder
  now resolves its target by **slug** via `el_post_id()` / `el_library_id()` (lib.php),
  the way `build-locations.php` always did. The IDs quoted below are illustrative only.
- **Real theme (kept, embedded into Elementor via `[buckleup_section name="…"]`):**
  site header, and the Graduates gallery / testimonials / FAQ.
- **Custom theme widget:** the **home hero** is a native Elementor widget
  (`BuckleUp Hero`, widgetType `buckleup-hero`, panel category "BuckleUp") defined in
  `themes/buckleup/inc/elementor-widgets/`. Its `render()` calls the theme's own
  `buckleup_hero_markup()`, so the hero is fully control-driven **and** pixel-identical
  to the hand-coded original — every control default is the previous hard-coded copy.
- Pages route through `themes/buckleup/templates/page-elementor.html` because each has
  post-meta **`_wp_page_template = page-elementor`**. Without that meta a page falls
  back to `page-{slug}.html` and double-renders. The shortcodes are registered in
  `themes/buckleup/inc/elementor.php`.

## Files

| File | Purpose |
|---|---|
| `lib.php` | Authoring helpers — native Elementor **Container** + widget builders (`el_container`, `el_col`, `el_heading`, `el_button`, `el_image`, `el_pill`, `el_icon_list`, `el_shortcode`, …). `require`d by the builders. |
| `build-home.php` | Builds the Home (id 38) Elementor body: pricing + the home-only CTA, with the live sections embedded via `[buckleup_section]`. Does **not** build the hero — run `build-hero.php` after it. |
| `build-hero.php` | Prepends the `BuckleUp Hero` widget to Home (id 38) as element #1, in a full-width zero-padding container. Writes **no** widget settings (control defaults = today's copy). Idempotent — strips a previous injection first. **Paired with** the `templates/front-page.html` edit that removed `wp:buckleup/section {"name":"home-hero"}`; run one without the other and the hero renders twice or not at all. |
| `build-pages.php` | Builds the 6 inner pages (About 39, Services 41, Instructors 42, Contact 40, Resources 44, ICBC 45). Contact embeds `[buckleup_contact_form]`. |
| `build-locations.php` | Builds the **5 location landing pages** (Coquitlam 33, North Vancouver 34, Port Coquitlam 35, Port Moody 36, Tri-Cities 37) as fully-editable Elementor on the `location` CPT — landmark hero (image bg + overlay) + local intro/why/neighbourhoods/ICBC/FAQ/CTA, with shared pricing/graduates/testimonials embedded via `[buckleup_section]`. Self-enables `elementor_cpt_support` for `location`, and syncs each post's `bu_seo_title`/`bu_seo_description` from the content file. Consumes `locations-content.php`. |
| `locations-content.php` | **Single source of truth** for per-location copy + SEO (hero, intro, why, neighbourhoods, ICBC routes, FAQs, `seo_title`/`seo_description`, `geo`, `area_served`). Also mirrored by the SEO mu-plugin (per-location JSON-LD) and `seo-config.php`. |
| `import-location-heroes.php` | Imports the 5 landmark hero WebPs from `assets/heroes/{slug}.webp` into the Media Library (tagged `_bu_location_hero`, idempotent) with alt text + CC attribution baked into each attachment. |
| `assets/heroes/*.webp` | The 5 pre-processed landmark hero images (Wikimedia Commons, CC-licensed — attribution stored on the attachments). |
| `build-chrome.php` | Builds the footer (library template **164**) and the header (163, retained but **unused** — the real theme header renders live). |
| `set-kit.php` | Writes the Elementor Kit globals (brand colors + Geist) onto the active kit. |
| `export-for-prod.php` | Exports the built DB content (base64 `_elementor_data`, kit globals, options, menu) to a portable `_prod-data.php` for the prod importer. **Generated `_prod-data.php` is gitignored.** |

## Rebuild from scratch (dev)

```bash
make up && make provision && make build-assets   # base site
# Elementor is NOT installed by provision.sh — install it before the builders run
# (set-kit.php just prints "Elementor not active" and everything downstream no-ops).
docker compose run --rm -T wpcli wp plugin install elementor elementskit-lite --activate
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/set-kit.php
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-chrome.php   # footer 164 (+header 163)
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-home.php     # page 38
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-hero.php     # page 38: hero widget on top (AFTER build-home)
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-pages.php    # pages 39-45
docker compose run --rm -T wpcli wp eval 'foreach([38,39,40,41,42,44,45] as $id){update_post_meta($id,"_wp_page_template","page-elementor");}'
# --- location landing pages (CPT 33-37) ---
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/import-location-heroes.php  # media: 5 landmark heroes
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-locations.php         # locations 33-37 (+enables CPT support)
docker compose run --rm -T wpcli wp eval-file /scripts/wp/seo-config.php                          # push bu_seo_* -> Rank Math titles
docker compose run --rm -T wpcli wp eval '\Elementor\Plugin::$instance->files_manager->clear_cache();'
```
The location CPT singles render via the theme's full-bleed `templates/single-location.html`
(no `_wp_page_template` — the template hierarchy resolves it), so they are NOT in the
page-template loop above.
The builders write meta with `update_post_meta($id,"_elementor_data",wp_slash(wp_json_encode($elements)))`
(Elementor's own save form). Re-running is idempotent (overwrites the same post IDs).

## Deploy to prod (Bluehost — no WP-CLI)

See memory `buckleup-elementor-conversion` + CLAUDE.md §5C. Outline: `export-for-prod.php`
→ SFTP `_prod-data.php` off-webroot → a token-PHP importer activates Elementor +
ElementsKit, writes the kit/footer/page meta + `_wp_page_template`, rewrites
`localhost:8080` → `www.buckleupdriving.ca` in `_elementor_data` only, regenerates CSS,
flushes caches; the theme dir is swapped in **last**. Two prod gotchas: Elementor's
activation grabs the next free post ID for its default kit (collided with the footer's
**164** — relocate the kit + raw-`$wpdb`-delete to free 164, because `wp_delete_post`
on a kit hits a `wp_die` guard); and `_wp_page_template` must be set or pages fall back
to the old patterns.
