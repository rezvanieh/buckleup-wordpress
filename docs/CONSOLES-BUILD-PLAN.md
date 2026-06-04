# BuckleUp Consoles — v1 Build Plan

**Scope decision (client, 2026-06-03):** *Match the source — linked, real-data pages only.* Build the pages that are actually linked in each console's sidebar AND backed by real data in the source app. Skip the source's mock/unlinked pages and dead controls. Minus live payments (Stripe) + SMS/WhatsApp (Twilio) + SSO — all deferred. Avatars/graduate images → WP Media Library (no Supabase).

Derived from the 3-console source mapping (one agent per console). Source app: `/Users/esfandiyar/Projects/Buckleup/src/app/{student,instructor,admin}`. Backend: `wp-content/plugins/buckleup-app/includes/rest-*.php` (`buckleup/v1/*`).

---

## Architecture

- **Auth/gating:** already done (`auth.php` + caps `buckleup_access_{student,instructor,admin}_console`). The user ID **is** the student_id/instructor_id (no separate profile row); profile data is `bu_*` user meta.
- **Render model:** server-render the console **shell + initial read data** in PHP (call the plugin's data helpers, or `rest_do_request` internally) for speed/simplicity; use **progressive-enhancement JS** only for live mutations (profile save, avatar upload, review submit, theme toggle, instructor confirm/decline/cancel, availability save, admin delete/approve). Reuse the `auth.js` fetch+`X-WP-Nonce` pattern (`window.buckleupAuth.nonce`/`restUrl`). Each page = a self-contained theme pattern (`patterns/console-*.php`) so pages can be built independently.
- **Console layout:** shared sidebar shell per role (brand + nav + sign-out), built once and reused. Pattern-cache is version-keyed — clear via `wp_get_theme()->delete_pattern_cache()` after adding patterns on a running instance.
- **Verbatim copy** to preserve: page titles/sublines, license options (`7L/7N/5`), language options (`en/zh/yue/pa`), review helper/footnote text, theme tile labels, status pill text ("Approved"/"Pending Approval"/"Confirmed"/"Pending").
- **Drop the source's fakes:** instructor dashboard "4.9 (47 reviews)" tile, admin "+20.1%/+12/+19%/+2" trend strings, revenue (always $0), and all dead toggles. Show real data or omit.

---

## Backend gaps (plugin engineer — extends #30)

Only the gaps needed for the IN-SCOPE pages:

1. **`buckleup_booking_shape`** → add `service.duration` (from the booking row `duration` or `bu_duration`) and enrich `student.user` with `email` + `phone`. *(instructor Dashboard + Schedule)*
2. **`GET /instructors/bookings`** → `WHERE status != 'CANCELLED' AND datetime >= now ORDER BY datetime ASC` (currently returns ALL DESC — skews the pending/confirmed counts). *(instructor Schedule)*
3. **`/instructors/availability`** → also accept **PUT** (alias the POST callback) — the weekly-save client uses PUT. *(instructor Availability)*
4. **`GET /instructors/profile`** → return `avatar` (alias/replace `image`), `avgRating` (alias/replace `rating`), and add `totalReviews` + `createdAt`; compute `avgRating`/`totalReviews` from **approved** `bu_reviews` (not stored `bu_rating`). *(instructor Profile)*
5. **`/user/avatar`** → (a) confirm the POST response key the theme reads (source used `url`; WP returns `avatar` — pick one, document it); (b) add **`GET /user/avatar` → `{image,name,email}`** for the admin Settings profile card. *(profiles + admin Settings)*
6. **`GET /admin/students`** → add `pagination:{total,pages,page,limit}` + `stats:{total,active,byStatus,byLicenseType}` wrappers + server-side `search/status/licenseType/page/limit`; enrich rows with `image, bookingCount, lastBooking, emergencyContact, emergencyPhone, preferredLang, userCreatedAt`. *(admin Students)*
7. **`GET /admin/reviews`** → return a **bare array** (page does `setReviews(data)`), and add `studentEmail` + `studentImage` per row. *(admin Reviews)*
8. **Graduates endpoints — NET-NEW** → `GET /graduates` (public, for landing + admin), `POST /graduates` (admin, multipart → Media Library via `media_handle_sideload`), `DELETE /graduates/{id}` (admin). Return `[{id,title,description,url,category,createdAt}]`. Back with a `bu_graduate` CPT or the bu tables. *(admin Graduates — note: the public landing Hall-of-Fame already renders graduates; align with that data source.)*

All mutations: `buckleup_check_nonce` + ownership + `$wpdb->prepare`. (Also fold in the earlier security-review fixes: booking duration clamp, register rate-limit, Referer→hidden-marker.)

---

## Shared console shell (build first)

Per-role sidebar layout matching the source `{role}/layout.tsx`:
- Brand block (logo tile + "Student/Instructor/Admin Portal" + the user's name), links to `/`.
- Sidebar nav (exact order below), active item highlighted; **Sign out** at the bottom (`wp_logout_url(home_url())`).
- Mobile: hamburger → slide-in drawer with the same nav.
- Main content = glass panel.

Sidebar nav (linked items only):
- **Student:** Home Page (`/`) · Dashboard (`/student`) · Leave a Review (`/student/reviews`) · Profile (`/student/profile`) · Settings (`/student/settings`)
- **Instructor:** Dashboard (`/instructor`) · My Schedule (`/instructor/schedule`) · Availability (`/instructor/availability`) · My Students (`/instructor/students`) · Profile (`/instructor/profile`) · Settings (`/instructor/settings`)
- **Admin:** Overview (`/admin`) · Blogs (→ `wp-admin` Posts) · Students (`/admin/students`) · Graduates (`/admin/graduates`) · Reviews (`/admin/reviews`) · Settings (`/admin/settings`)

*(The current `page-student/instructor/admin.php` welcome shells get folded into the new Dashboard/Overview pages inside this layout.)*

---

## Student console

1. **Dashboard `/student`** — thin welcome: "Welcome back, {firstName}" + a generic subline (the source's "2 lessons until road test" is hard-coded — make it generic or omit). No data fetch.
2. **Leave a Review `/student/reviews`** — form (5-star rating, comment ≥10/≤1000 chars + counter, "display publicly" checkbox default on, Submit disabled until valid) + "My Reviews" list with Approved/Pending pills. `GET /students/reviews` (list), `POST /reviews` `{rating,comment,isPublic}` → is_approved=0. States: loading/empty("haven't submitted any")/error. **Backend: parity (exists).**
3. **Profile `/student/profile`** — Full Name, Email (disabled), Phone, License select (`7L/7N/5`), Emergency name/phone, Preferred Language (`en/zh/yue/pa`), avatar upload. Unsaved-changes pill + Save/Discard. `GET/PUT /students/profile`, `POST/DELETE /user/avatar`. **Backend: parity (exists; mind avatar key — gap #5).**
4. **Settings `/student/settings`** — single Appearance card → theme toggle (Light/Dark/System) persisted to `bu_theme`. `GET/PUT /user/theme`. Don't build Notifications/Password/Privacy (don't exist in source). **Backend: parity.**

## Instructor console

1. **Dashboard `/instructor`** — 3 tiles: Upcoming Lessons, Completed Lessons, **(real rating or omit — NOT the fake 4.9)**; "Next Upcoming Lesson" card (student, service, date/time, duration, contact, pickup) or empty state; Quick Actions. `GET /instructors/stats`. **Backend: gap #1 (service.duration + student email/phone).**
2. **My Schedule `/instructor/schedule`** — Pending/Confirmed count cards + Upcoming Lessons table (Date&Time, Student, Service, Location, Status, Actions). PENDING→Confirm/Decline, CONFIRMED→Cancel (reason). `GET /instructors/bookings`, `PUT /instructors/bookings/{id}/status` → email the student (email-only, SMS deferred). **Backend: gaps #1 + #2 (filter/order).**
3. **Availability `/instructor/availability`** — tabs: Weekly (7 day rows, switch + start/end times, Save) + Calendar Exceptions (month grid, click date → available-custom-hours / day-off + reason; upcoming list). `GET/POST/PUT/DELETE /instructors/availability` + `.../exceptions`. **Backend: gap #3 (accept PUT).**
4. **My Students `/instructor/students`** — roster: 3 stat cards + search + filters (All/Has Upcoming/Active) + per-student cards (contact, license, completed/last/next, latest-skills bars, services). `GET /instructors/students`. **Backend: parity (exists).**
5. **Profile `/instructor/profile`** — name/phone (email disabled), bio (500 counter), certifications + languages (tag add/remove), avatar; rating + "(N reviews)" + Active badge + "Member since". `GET/PUT /instructors/profile`, avatar endpoints. **Backend: gap #4 (keys/rating) + #5 (avatar).**
6. **Settings `/instructor/settings`** — Appearance → theme toggle (real). Source's Notification/Calendar/Booking-preference controls are decorative — render minimally or omit (don't build backends). **Backend: theme parity.**

## Admin console

1. **Overview `/admin`** — 4 KPI tiles: Active Students, Instructors, Total Bookings, Total Revenue ($0, payments deferred). **Drop the fake trend strings.** "Manage Students" link. `GET /admin/stats`. **Backend: exists.**
2. **Students `/admin/students`** — 4 stat cards (Total/Active/ByLicense/ByStatus) + search + Status/License filters + paginated table (Student, Contact, License, Status, Bookings, Last Lesson, Joined, Delete-with-confirm cascade). `GET /admin/students`, `DELETE /admin/students/{id}`. **Backend: gap #6 (pagination+stats+filters+enrichment).**
3. **Graduates `/admin/graduates`** — upload card (file + optional title + preview + Upload) + grid of image tiles with hover-delete (confirm modal). `GET /graduates`, `POST /graduates`, `DELETE /graduates/{id}` → Media Library. **Backend: gap #8 (net-new).**
4. **Reviews `/admin/reviews`** — "{N} Pending" badge + search + All/Pending/Approved filters; rows = student, stars, comment, Approve/Unapprove + Delete. `GET /admin/reviews` (bare array), `PATCH /admin/reviews/{id}` `{isApproved}`, `DELETE /admin/reviews/{id}`. **Backend: gap #7 (bare array + email/image).**
5. **Settings `/admin/settings`** — Profile card (avatar upload + disabled name/email) + Appearance (theme). `GET /user/avatar`, avatar POST/DELETE, `GET/PUT /user/theme`. Don't build the commented-out System/Data sections. **Backend: gap #5 (GET /user/avatar).**

---

## Build order

1. **Backend gaps** (plugin engineer) — do #1–#7 (shape fixes, cheap) + #8 graduates (net-new) so the frontend has correct data. Commit.
2. **Shared console shell** (theme engineer) — the per-role sidebar layout; fold the existing welcome shells into the Dashboards.
3. **Demo data** (content engineer, #32) — seed bookings (PENDING + CONFIRMED + COMPLETED across the 3 demo users), availability (weekly + an exception), lesson_progress (skills JSON), and a couple of reviews (1 approved, 1 pending) + graduate images — so every page shows real content.
4. **Pages** — console by console (student → instructor → admin), each page server-rendered + JS for its mutations. Low concurrency.
5. **QA** (#33) — per-page parity + role-gating + row-ownership (instructor sees only their own; student only their own).

## Omitted (out of scope, per the decision)

Student My Lessons + My Progress (mock, unlinked); admin Instructors/Bookings/Notification-templates (orphan, not in sidebar); blog management (native wp-admin); all dead toggles (notifications/calendar/system/data); revenue/transactions; SSO; SMS/WhatsApp dispatch.
