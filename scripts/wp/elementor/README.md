# Elementor authoring & rebuild — `scripts/wp/elementor/`

The marketing **page bodies** (Home/About/Services/Instructors/Contact/Resources/
ICBC) and the **site footer** are authored in Elementor. Their design lives in the
**database** (`_elementor_data` post-meta + the Kit's global colors/fonts), which is
**NOT version-controlled**. These scripts are the source of truth that *regenerates*
that DB content, so a fresh install (or `make reset`) can rebuild the Elementor site
deterministically. Run them with WP-CLI against the dev stack.

## What the live site is made of (hybrid model)

- **Elementor:** page bodies on pages 38/39/40/41/42/44/45; the **footer** =
  Elementor library template **id 164**, embedded by `[buckleup_elementor id="164"]`
  in `themes/buckleup/parts/footer.html`; the **Kit** = global colors/fonts.
- **Real theme (kept, embedded into Elementor via `[buckleup_section name="…"]`):**
  site header, home hero, and the Graduates gallery / testimonials / FAQ.
- Pages route through `themes/buckleup/templates/page-elementor.html` because each has
  post-meta **`_wp_page_template = page-elementor`**. Without that meta a page falls
  back to `page-{slug}.html` and double-renders. The shortcodes are registered in
  `themes/buckleup/inc/elementor.php`.

## Files

| File | Purpose |
|---|---|
| `lib.php` | Authoring helpers — native Elementor **Container** + widget builders (`el_container`, `el_col`, `el_heading`, `el_button`, `el_image`, `el_pill`, `el_icon_list`, `el_shortcode`, …). `require`d by the builders. |
| `build-home.php` | Builds the Home (id 38) Elementor body: pricing + the home-only CTA, with the live sections embedded via `[buckleup_section]`. |
| `build-pages.php` | Builds the 6 inner pages (About 39, Services 41, Instructors 42, Contact 40, Resources 44, ICBC 45). Contact embeds `[buckleup_contact_form]`. |
| `build-chrome.php` | Builds the footer (library template **164**) and the header (163, retained but **unused** — the real theme header renders live). |
| `set-kit.php` | Writes the Elementor Kit globals (brand colors + Geist) onto the active kit. |
| `export-for-prod.php` | Exports the built DB content (base64 `_elementor_data`, kit globals, options, menu) to a portable `_prod-data.php` for the prod importer. **Generated `_prod-data.php` is gitignored.** |

## Rebuild from scratch (dev)

```bash
make up && make provision && make build-assets   # base site
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/set-kit.php
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-chrome.php   # footer 164 (+header 163)
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-home.php     # page 38
docker compose run --rm -T wpcli wp eval-file /scripts/wp/elementor/build-pages.php    # pages 39-45
docker compose run --rm -T wpcli wp eval 'foreach([38,39,40,41,42,44,45] as $id){update_post_meta($id,"_wp_page_template","page-elementor");}'
docker compose run --rm -T wpcli wp eval '\Elementor\Plugin::$instance->files_manager->clear_cache();'
```
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
