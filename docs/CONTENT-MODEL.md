# BuckleUp Content Model (v1 marketing site)

**Owner:** `wp-plugin-engineer` · plugin `wp-content/plugins/buckleup-core`
**Status:** stable — these keys are the contract between the plugin, the theme,
the content/seed scripts, and the SEO mu-plugin. Do not rename without a
coordinated change.

This documents every Custom Post Type, its post-type key, every meta key, and
the template-helper API the theme should render against. Field shapes mirror the
source app (`/Users/esfandiyar/Projects/Buckleup` Prisma schema + the live
landing components) per PLAN.md §2/§4.

---

## Conventions

- **Meta-key prefix:** every custom meta key is prefixed `bu_`.
- **Core fields reused:** `post_title` (title/name/question), `post_content`
  (description/quote/answer/bio), the **featured image** (photo), and
  `menu_order` (manual ordering — lower = first). These are NOT duplicated as
  custom meta.
- **`bu_is_active`** (boolean, stored as `'1'`/`''`): when **unset, the item is
  treated as ACTIVE** (shown). Only an explicit `'0'`/empty hides it. The seed
  scripts should set `bu_is_active = '1'` on every seeded row to be explicit.
- **List fields** (`bu_features`, `bu_certifications`, `bu_languages`) are stored
  as **multiple single meta rows under one key** (`register_post_meta` with
  `single => false`), one row per item — NOT a serialized array. From WP-CLI:
  `wp post meta add <id> bu_features "90-minute driving lesson"` (repeat per
  item). The admin UI edits them as a one-item-per-line textarea.
- All meta is registered with `show_in_rest => true`, typed, and sanitized.
- **Don't query these CPTs directly in the theme** — call the template helpers
  (bottom of this doc). They return plain arrays with the documented keys.

---

## CPTs and their keys

| CPT label    | post-type key | public | URL                      | Core supports                          |
|--------------|---------------|--------|--------------------------|----------------------------------------|
| Graduates    | `graduate`    | no     | — (rendered in patterns) | title, editor, thumbnail, page-attributes |
| Testimonials | `testimonial` | no     | —                        | title, editor, thumbnail, page-attributes |
| FAQs         | `faq`         | no     | —                        | title, editor, page-attributes         |
| Services     | `service`     | no     | —                        | title, editor, page-attributes         |
| Packages     | `package`     | no     | —                        | title, editor, page-attributes         |
| Instructors  | `instructor`  | no     | —                        | title, editor, thumbnail, page-attributes |
| Locations    | `location`    | **yes**| **`/locations/{slug}`**  | title, editor, thumbnail, page-attributes |

Only `location` is publicly queryable; it owns the `/locations/{slug}` rewrite
(`rewrite => ['slug' => 'locations', 'with_front' => false]`). The rest surface
only through theme patterns / helpers. Rewrite rules are flushed on plugin
activation. NOTE: pretty permalinks (`/%postname%/`) must be enabled at the site
level for `/locations/` to resolve (owned by provisioning, not this plugin).

---

## Meta keys per CPT

### `graduate` — Hall-of-Fame gallery image
| Field        | Source                | Key            | Type    | Example                  |
|--------------|-----------------------|----------------|---------|--------------------------|
| Title        | `post_title`          | —              | string  | "James M."               |
| Description  | `post_content`        | —              | string  | "Passed first try!"      |
| Photo        | featured image        | —              | image   | (Media Library)          |
| Order        | `menu_order`          | —              | int     | `0`                      |
| Active       | meta                  | `bu_is_active` | boolean | `'1'`                    |

### `testimonial` — named student review
| Field        | Source         | Key              | Type    | Example                          |
|--------------|----------------|------------------|---------|----------------------------------|
| (internal)   | `post_title`   | —                | string  | "Jason Kim review"               |
| Author name  | meta           | `bu_author_name` | string  | "Jason Kim"                      |
| Author role  | meta           | `bu_author_role` | string  | "Passed N Test"                  |
| Rating       | meta           | `bu_rating`      | int 1-5 | `5`                              |
| Quote        | `post_content` | —                | string  | "I failed twice with another…"   |
| Photo        | featured image | —                | image   | (optional)                       |
| Active       | meta           | `bu_is_active`   | boolean | `'1'`                            |

> Live-site fallback testimonials to seed: Jason Kim (Passed N Test), Amanda Liu
> (New Driver), David Wang (Parent), Sarah Martinez (Class 5 License), Michael
> Chen (International Student) — all rating 5. (PLAN §4)

### `faq` — single source for accordion AND FAQPage schema
| Field    | Source         | Key            | Type    | Example                                |
|----------|----------------|----------------|---------|----------------------------------------|
| Question | `post_title`   | —              | string  | "What is the cancellation policy…?"    |
| Answer   | `post_content` | —              | string  | "Lessons cancelled at least 24 hours…" |
| Order    | `menu_order`   | —              | int     | `0`                                    |
| Active   | meta           | `bu_is_active` | boolean | `'1'`                                  |

> 14 verbatim Q&A in PLAN §4 / source `landing/FAQ.tsx`. The theme renders the
> accordion from `buckleup_get_faqs()`; the SEO mu-plugin builds FAQPage JSON-LD
> from the SAME helper.

### `service` — license-class offering (Services page + OfferCatalog)
| Field       | Source         | Key               | Type    | Example                  |
|-------------|----------------|-------------------|---------|--------------------------|
| Name        | `post_title`   | —                 | string  | "Road Test Preparation"  |
| Slug        | `post_name`    | —                 | string  | "road-test-prep"         |
| Description | `post_content` | —                 | string  | "Comprehensive road…"    |
| Type        | meta           | `bu_service_type` | string  | `LESSON` / `TEST_PREP` / `SPECIALIZED` / `REFRESHER` / `PACKAGE` |
| Duration    | meta           | `bu_duration`     | int min | `120`                    |
| Price       | meta           | `bu_price`        | number  | `120`                    |
| Sort        | `menu_order`   | —                 | int     | `2`                      |
| Active      | meta           | `bu_is_active`    | boolean | `'1'`                    |

### `package` — home pricing plan (Pricing section)
| Field         | Source         | Key             | Type    | Example                          |
|---------------|----------------|-----------------|---------|----------------------------------|
| Name          | `post_title`   | —               | string  | "6 Sessions Package"             |
| Description   | `post_content` | —               | string  | "Most popular choice"            |
| Price         | meta           | `bu_price`      | number  | `480`                            |
| Unit          | meta           | `bu_unit`       | string  | `package` (or `lesson`)          |
| Sessions      | meta           | `bu_sessions`   | int     | `6`                              |
| Total hours   | meta           | `bu_hours`      | number  | `9`                              |
| Car-test fee  | meta           | `bu_car_fee`    | number  | `40`                             |
| Most Popular  | meta           | `bu_is_popular` | boolean | `'1'`                            |
| Active        | meta           | `bu_is_active`  | boolean | `'1'`                            |
| CTA label     | meta           | `bu_cta_label`  | string  | "Get Started"                    |
| Feature rows  | meta (list)    | `bu_features`   | string[]| ["6 sessions (90 min each)", "9 hours total driving", "+$40 for car on road test"] |

> The WhatsApp deep link is **derived server-side** from name + price — do NOT
> store it. Use the `whatsapp_link` key returned by `buckleup_get_packages()`,
> which builds the exact live template:
> `Hi! I'm interested in booking the *<name>* ($<price>).` → `wa.me/16044413677`.
>
> Live plans to seed (PLAN §4): Single Session **$100** (unit `lesson`,
> CTA "Book Now"), 4 Sessions **$360** (CTA "Get Started"), 6 Sessions **$480**
> (`bu_is_popular = '1'`, CTA "Get Started"), 8 Sessions **$620** (CTA "Best Value").
> The popular package's anchor id `#most-popular` is the theme's responsibility.

### `instructor` — team profile
| Field          | Source         | Key                 | Type    | Example                  |
|----------------|----------------|---------------------|---------|--------------------------|
| Name           | `post_title`   | —                   | string  | "Farhad Sanaeifar"       |
| Role / title   | meta           | `bu_role`           | string  | "Senior Instructor"      |
| Rating         | meta           | `bu_rating`         | number  | `4.9`                    |
| Bio            | `post_content` | —                   | string  | "Farhad brings a unique…"|
| Photo          | featured image | —                   | image   | (Media Library)          |
| Certifications | meta (list)    | `bu_certifications` | string[]| ["ICBC Approved", "Winter Driving Certified"] |
| Languages      | meta (list)    | `bu_languages`      | string[]| ["English", "Farsi"]     |
| Active         | meta           | `bu_is_active`      | boolean | `'1'`                    |

> Seed the **real** instructors (PLAN §4 / seed.ts): Farhad Sanaeifar and
> Sarah Mitchell — not the Unsplash placeholder personas in `landing/Instructors.tsx`.

### `location` — per-city landing page (`/locations/{slug}`)
| Field            | Source         | Key                  | Type    | Example                       |
|------------------|----------------|----------------------|---------|-------------------------------|
| City name        | `post_title`   | —                    | string  | "Coquitlam"                   |
| Slug             | `post_name`    | —                    | string  | "coquitlam"                   |
| Body             | `post_content` | —                    | string  | (optional page copy)          |
| Hero title       | meta           | `bu_hero_title`      | string  | "Driving Lessons in"          |
| Hero highlight   | meta           | `bu_hero_highlight`  | string  | "Coquitlam" (gradient span)   |
| Hero subtitle    | meta           | `bu_hero_subtitle`   | string  | "Master the roads of Coquitlam…" |
| SEO title        | meta           | `bu_seo_title`       | string  | "Driving Lessons in Coquitlam & Port Coquitlam" |
| SEO description  | meta           | `bu_seo_description` | string  | "Looking for the best driving lessons…" |

> 5 locations to seed with exact slugs (PLAN §2 URL parity): `coquitlam`,
> `north-vancouver`, `port-coquitlam`, `port-moody`, `tri-cities`. Hero
> title/highlight/subtitle + SEO title/description are verbatim in the source
> `src/app/locations/<slug>/page.tsx`.

---

## Site Settings (options page)

Native Settings API page at **Settings → BuckleUp Settings**. Stored in a single
option `buckleup_settings` (array). Read via `buckleup_get_setting($key)` /
`buckleup_get_settings()`. Defaults are pre-seeded to the live values on
activation. Keys:

| Key                | Default                                   | Used by             |
|--------------------|-------------------------------------------|---------------------|
| `business_name`    | BuckleUp Driving School                   | footer, schema      |
| `street_address`   | 136 Maple Dr                              | NAP, schema         |
| `address_locality` | Port Moody                                | NAP, schema         |
| `address_region`   | BC                                        | NAP, schema         |
| `postal_code`      | V3H 0A8                                    | NAP, schema         |
| `address_country`  | CA                                        | schema              |
| `phone`            | (604) 441-3677                            | footer, header CTA  |
| `phone_e164`       | +16044413677                              | `tel:` links        |
| `email`            | info@buckleupdriving.ca                   | footer, `mailto:`   |
| `whatsapp`         | 16044413677                               | `wa.me/` links      |
| `hours_open`       | 09:00                                     | schema OpeningHours |
| `hours_close`      | 18:00                                     | schema OpeningHours |
| `hours_display`    | Mon–Sun 9am–6pm                           | footer display      |
| `geo_lat`          | 49.2838                                   | geo meta, schema    |
| `geo_lng`          | -122.8556                                 | geo meta, schema    |
| `instagram_url`    | https://www.instagram.com/budrivingschool | footer social      |
| `facebook_url`     | https://www.facebook.com/DriveMasterca    | footer social      |
| `rating_value`     | 4.98                                      | AggregateRating     |
| `review_count`     | 500                                       | AggregateRating     |
| `founding_year`    | 2014                                      | schema foundingDate |
| `price_range`      | $$                                        | schema priceRange   |

---

## Template helper API (theme renders against THESE)

Defined in `includes/helpers.php`. Each list helper returns an array of plain
arrays, ordered by `menu_order` then title, active-only by default.

```php
buckleup_get_setting( $key, $default = '' ) : string   // one site setting
buckleup_get_settings() : array                         // all settings
buckleup_get_meta_list( $post_id, $key ) : string[]     // a list meta as array

buckleup_get_graduates( $limit = -1 ) : array
//   [ id, title, description, image (url), image_id ]

buckleup_get_testimonials( $limit = -1 ) : array
//   [ id, name, role, rating (int), content, image (url) ]

buckleup_get_faqs( $limit = -1 ) : array
//   [ id, question, answer (plain text) ]      ← same source feeds FAQPage schema

buckleup_get_services( $limit = -1 ) : array
//   [ id, name, slug, description, type, duration (int min), price (float) ]

buckleup_get_packages( $limit = -1 ) : array
//   [ id, name, description, price (float), unit, sessions (int), hours (float),
//     car_fee (float), is_popular (bool), cta_label, features (string[]),
//     whatsapp_link (absolute url) ]

buckleup_get_instructors( $limit = -1 ) : array
//   [ id, name, role, rating (float), bio, image (url),
//     certifications (string[]), languages (string[]) ]

buckleup_get_locations() : array                        // all, for nav/footer/sitemap
//   [ id, title, slug, url ]

buckleup_get_location_fields( $post = null ) : array    // single location hero/SEO
//   [ hero_title, hero_highlight, hero_subtitle, seo_title, seo_description ]

buckleup_whatsapp_link( $name, $price = '' ) : string   // exact wa.me template
```

### Example: render the Pricing section in a theme template/pattern

```php
foreach ( buckleup_get_packages() as $pkg ) {
    printf(
        '<a href="%s">%s — $%s</a>',
        esc_url( $pkg['whatsapp_link'] ),
        esc_html( $pkg['name'] ),
        esc_html( $pkg['price'] )
    );
    if ( $pkg['is_popular'] ) { /* "Most Popular" badge + id="most-popular" */ }
}
```

---

## Contact form submission (server-side handler)

Defined in `includes/contact.php`. Faithful to the source `/api/contact`,
implemented as a simple **no-JS admin-post POST** (matches the theme form).

**Fields** (POST input names — use these exact snake_case names):
`first_name` (required), `last_name` (optional), `email` (required),
`phone` (optional), `subject` (required), `message` (required).
Email is validated; message is plain text. All inputs sanitized.

The email goes to the `email` site setting (`info@buckleupdriving.ca`), subject
`New contact: <subject>`, **Reply-To = submitter**. In dev it lands in Mailpit
(http://localhost:8025).

### admin-post (the contract)
- **Form:** `<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">`
- **Hidden fields:** `<input type="hidden" name="action" value="buckleup_contact">`
  and `<?php wp_nonce_field( 'buckleup_contact', 'buckleup_contact_nonce' ); ?>`
- **Hooks:** `admin_post_buckleup_contact` + `admin_post_nopriv_buckleup_contact`.
- **Result:** `wp_safe_redirect()` back to the referring /contact page with
  `?contact=success` or `?contact=error` (theme reads `$_GET['contact']` to show
  the success/error state).

> **Stable names:** admin-post action `buckleup_contact`; nonce action
> `buckleup_contact`, nonce field `buckleup_contact_nonce`. Theme wires the form
> markup into `[data-contact-form-slot]`.

### Anti-spam (handled server-side)
- **Honeypot:** the theme renders a hidden `<input name="website">`. If it's
  non-empty the handler silently redirects `?contact=success` and sends nothing.
- **Rate limit:** transient-based, ~3 submissions / 10 min per IP **and** per
  email (tunable via the `buckleup_contact_rate_max` / `buckleup_contact_rate_window`
  filters). Over the cap → silent `?contact=success`, no send.
- **Min-fill-time (optional):** if the form includes a hidden `bu_ts` (unix
  seconds rendered server-side), submits completed in < ~3s are dropped as bots
  (silent success). It's a **no-op when `bu_ts` is absent**, so the current form
  works as-is; the theme can add `<input type="hidden" name="bu_ts" value="<?php
  echo time(); ?>">` to activate it.
- Bot/abuse rejections deliberately return `?contact=success` (not `error`) so a
  scraper can't distinguish a block from a real send.
