# BuckleUp Consoles Backend (Phase-2 app) — Data + REST Contract

**Owner:** `wp-plugin-engineer` · plugin `wp-content/plugins/buckleup-app`
**Status:** stable contract for the consoles (Student / Instructor / Admin).
Tasks: backend #30, auth #34, UI #31, seed #32, QA #33.

The marketing content model lives in `docs/CONTENT-MODEL.md` (plugin
`buckleup-core`). This file documents the **application** layer: roles, custom
tables, user-meta profile keys, the slot engine, and every `buckleup/v1/*` REST
endpoint. Out of scope (deferred): Stripe/payments, Twilio SMS/WhatsApp, social
login.

---

## Roles & capabilities

| Role key              | Console        | Capabilities |
|-----------------------|----------------|--------------|
| `buckleup_student`    | `/student`     | `read`, `buckleup_access_student_console` |
| `buckleup_instructor` | `/instructor`  | `read`, `buckleup_access_instructor_console` |
| `buckleup_admin`      | `/admin`       | `read`, `buckleup_access_admin_console`, `buckleup_manage_app` |

WordPress `administrator` is granted all three console caps + `buckleup_manage_app`.
A "student"/"instructor" **is** a WP user with that role; the **WP user ID is the
`student_id` / `instructor_id`** used throughout the tables (no separate profile-row id).

**Auth flow (auth.php):** post-login redirect → role dashboard
(student→/student, instructor→/instructor, admin→/admin; admins may still reach
/wp-admin). wp-admin + admin bar are blocked for student/instructor. Logged-out
hitting a console → `/login/?callbackUrl=…`; wrong role → home. Failed login from
the branded /login page bounces to `/login/?login=failed` (theme must POST a
hidden `bu_branded=1` field; Referer is a fallback).

---

## Custom tables ($wpdb, prefix `wp_bu_`)

All created idempotently via dbDelta on activation; helper `buckleup_app_table('<key>')`.
IDs are BIGINT WP user/post IDs.

### `bu_bookings`
`id, student_id, instructor_id, service_id (service CPT post), datetime DATETIME,
duration INT, status VARCHAR(20) [PENDING|CONFIRMED|COMPLETED|CANCELLED|NO_SHOW],
pickup_addr, notes, created_at, updated_at`

### `bu_availability` (instructor weekly recurring hours)
`id, instructor_id, day_of_week TINYINT [0=Sun..6=Sat], start_time "HH:MM",
end_time "HH:MM", is_recurring TINYINT(1)`

### `bu_availability_exceptions` (per-date override; UNIQUE(instructor_id,date))
`id, instructor_id, date DATE, is_available TINYINT(1), start_time, end_time,
reason, created_at`

### `bu_lesson_progress` (one row per booking; UNIQUE(booking_id))
`id, booking_id, student_id, skills LONGTEXT (JSON), notes, instructor_notes, created_at`

### `bu_reviews`
`id, student_id, instructor_id (nullable), rating TINYINT [1-5], comment,
is_public TINYINT(1), is_approved TINYINT(1), created_at`

> Seeding (content #32): write directly with `$wpdb->insert(buckleup_app_table('bookings'), …)`.
> `skills` must be a JSON string. A review is public on the landing only when
> `is_public=1 AND is_approved=1`.

---

## Profile = user meta

Student: `bu_license_type, bu_status [ACTIVE|…], bu_emergency_contact,
bu_emergency_phone, bu_preferred_lang`
Instructor: `bu_bio, bu_certifications (multi), bu_languages (multi),
bu_hourly_rate, bu_is_active, bu_rating (legacy — live rating is computed from
approved reviews)`
Shared: `bu_phone, bu_theme [system|light|dark], bu_avatar_id (attachment ID)`

Multi-value meta (certifications/languages) = repeating single rows, like
buckleup-core lists. Use `buckleup_profile_set_list($uid,$key,$arr)`.

---

## Availability / slot engine

`buckleup_compute_slots($instructor_id, 'YYYY-MM-DD', $duration)` →
`['slots' => ['09:00','09:30',…]]` (or `['slots'=>[], 'reason'=>…]` when a
per-date exception blocks the day). Faithful port of
`src/app/api/bookings/slots`: exception overrides weekly; non-CANCELLED bookings
block overlapping slots; 30-min spacing; last start = workEnd − duration.
`buckleup_slot_is_available(...)` re-validates a single start (used at booking creation).

---

## REST endpoints — `buckleup/v1/*`

Auth: **capability** in `permission_callback` + **row-ownership** in the callback.
Mutations require the `wp_rest` nonce (`X-WP-Nonce` header) EXCEPT public
`/auth/register` and public GETs. Errors: `{ "code", "message", "data":{"status"} }`.

### Student (cap: student console)
- `GET  /students/profile` → `{ profile{id,name,email,phone,image,licenseType,emergencyContact,emergencyPhone,preferredLang,createdAt} }`
- `PUT  /students/profile` → same; accepts name,phone,licenseType,emergencyContact,emergencyPhone,preferredLang
- `GET  /students/progress` → `{ progress:[{id,bookingId,skills,notes,instructorNotes,createdAt,booking{datetime,service{name},instructor{user{name}}}}] }`
- `GET  /students/reviews` → `[ {id,rating,comment,instructorName,isPublic,isApproved,createdAt} ]`
- `GET  /bookings` → `{ upcoming, past, all }` (own bookings)
- `POST /bookings` → `{ message, booking }` 201. Body `{serviceId,instructorId,datetime,pickupAddr?,notes?}`; **duration is taken from the service (client value ignored)**; re-validates the slot (409 if taken).

### Instructor (cap: instructor console; own rows)
- `GET  /instructors/stats` → `{ stats{upcomingBookings,completedBookings}, nextLesson }`
- `GET  /instructors/availability` → `{ availability:[{id,instructorId,dayOfWeek,startTime,endTime,isRecurring}] }`
- `POST|PUT /instructors/availability` → `{ availability }` (delete-then-create that day). Body `{dayOfWeek,startTime,endTime,isRecurring?}`
- `DELETE /instructors/availability` → `{ message, dayOfWeek }`. Body `{dayOfWeek}`
- `GET  /instructors/availability/exceptions?startDate&endDate` → `{ exceptions:[{id,instructorId,date,isAvailable,startTime,endTime,reason}] }`
- `POST /instructors/availability/exceptions` → `{ exception, message }` (upsert by date). Body `{date,isAvailable,startTime?,endTime?,reason?}`
- `DELETE /instructors/availability/exceptions` → `{ message }`. Body `{date}`
- `GET  /instructors/bookings` → `{ bookings:[…] }` (non-cancelled, upcoming, ASC)
- `PUT  /instructors/bookings/{id}/status` → `{ booking, message }`. Body `{status:CONFIRMED|CANCELLED, reason?}`. **Ownership:** booking must be this instructor's (403). Emails the student (booking.approved/cancelled).
- `GET  /instructors/students` → `{ students:[{id,userId,name,email,phone,avatar,licenseType,status,totalLessons,completedLessons,lastLessonDate,nextLessonDate,services[],latestProgress}] }`
- `GET|PUT /instructors/profile` → `{ profile{id,name,email,phone,avatar,image,bio,certifications[],languages[],hourlyRate,isActive,avgRating,totalReviews,createdAt} }` (avgRating/totalReviews from APPROVED reviews)

### Admin (cap: admin console)
- `GET  /admin/stats` → `{ stats{totalStudents,totalInstructors,totalBookings,totalRevenue(=0)}, recentBookings }`
- `GET  /admin/students?search&status&licenseType&page&limit` → `{ students[], stats{total,active,byStatus,byLicenseType}, pagination{total,pages,page,limit} }`
- `DELETE /admin/students/{id}` → cascade delete (progress, bookings, reviews) + WP user
- `GET  /admin/instructors` → `{ instructors[] }`
- `GET  /admin/bookings` → `{ bookings[] }` (all, DESC)
- `GET  /admin/reviews` → **bare array** `[ {id,studentName,studentEmail,studentImage,instructorName,rating,comment,isPublic,isApproved,createdAt} ]`
- `PATCH /admin/reviews/{id}` → updated review. Body `{isApproved:bool}`
- `DELETE /admin/reviews/{id}` → `{ message }`
- `GET|POST /admin/notifications`, `PUT|DELETE /admin/notifications/{id}` → template CRUD (eventKey,channel,locale,subject,textBody,htmlBody,isActive)

### Cross-role / public
- `GET  /user/theme` / `PUT /user/theme` `{theme:light|dark|system}` (any logged-in)
- `GET  /user/avatar` → `{avatar,image,name,email}` ; `POST /user/avatar` (multipart `file`) → `{avatar}` ; `DELETE /user/avatar` → `{avatar:null}`
- `GET  /reviews` (PUBLIC) → `[ {id,name,image,role,content,rating,instructorName,createdAt} ]` (approved+public; landing testimonials)
- `POST /reviews` (student) → `{ message, review }` 201; `isApproved=false`. Body `{rating,comment(10-1000),instructorId?,isPublic?}`
- `GET  /graduates` (PUBLIC) → `[ {id,title,description,url,category,createdAt} ]` (the `graduate` CPT — same store the landing Hall-of-Fame uses)
- `POST /graduates` (admin, multipart `file`) → graduate ; `DELETE /graduates/{id}` (admin) → `{ message }`
- `POST /auth/register` (PUBLIC, no nonce) → `{ message, user{id,email,name,role:STUDENT} }` 201. Body `{name,email,phone?,password}`. Honeypot `website` + per-IP/email rate-limit. Creates a `buckleup_student` + welcome email.

### Frontend nonce (theme)
Localize `wp_create_nonce('wp_rest')` and send it as `X-WP-Nonce` on every
mutating fetch. Login form posts to `wp_login_url()` with `log/pwd/redirect_to`
+ hidden `bu_branded=1`. Register posts JSON to `/auth/register` (no nonce).
