# Draft: revised structure + copy for /services/class-7-driving-lessons/

Page #630 on dev, **#612 on prod** (`page`, Elementor, 22 widgets before this work).

**Status: APPLIED to dev and prod on 2026-08-19** via
`scripts/wp/optimize-class-7-page.php`. This file is kept as the rationale record
for the copy; the script is the thing that actually builds the sections. If the
copy needs to change, edit the script — editing this draft changes nothing.

## Why this page and not a new one

The GLP is the licence *system*; the commercial thing people actually buy at the
start of it is a beginner lesson. A separate "GLP course" page would rank for the
query and then fail the visitor, because BuckleUp is not an authorised
ICBC-approved GLP course provider. The site's own GLP post already says so:

> "Regular driving lessons are not automatically considered an ICBC-approved GLP
> course. A school must be specifically authorized to offer an approved GLP course."

So this page absorbs the GLP intent honestly: it maps its lessons onto the L to N
to full path, and answers the approved-course question head on instead of dodging
it. Answering it is what stops the bounce.

**Resolved with the client 2026-08-16: BuckleUp is NOT an authorised
ICBC-approved GLP course provider.** The copy below assumes that and says it
plainly. If that ever changes, this page and the GLP blog post both need
revisiting, and the "Is this an ICBC-approved GLP course?" FAQ becomes wrong.

---

## Current structure (what is there today)

| # | Section | Verdict |
|---|---|---|
| 1 | Hero: eyebrow, H1, dek | Keep, revise dek |
| 2 | Intro paragraph | Keep, revise |
| 3 | "one of our lessons and packages" line | Keep |
| 4 | H2 What these lessons cover + icon list | Keep, split by stage |
| 5 | H2 Common questions (3 Q) | Keep, add 3 |
| 6 | H2 Other lessons we offer | Keep |
| 7 | "Also useful" links | Keep |
| 8 | H2 Ready to book a lesson? + 2 buttons | **Keep untouched** |

Gaps: nothing about the GLP anywhere, the 7L and 7N audiences are merged into one
undifferentiated pitch, `rank_math_focus_keyword` is **empty**, and the SEO title
targets Coquitlam while the body never names a city.

## Proposed structure

Two new sections (**2a**, **4a**), three new FAQs, one new local block, and a
focus keyword. Everything else keeps its current copy.

---

### 1. Hero (revise the dek only)

H1 stays **Class 7 Driving Lessons for New Drivers**.

Revised dek:

> Just passed your knowledge test, or driving on an N? We'll take you from your
> first time behind the wheel through to your Class 7 road test, at whatever pace
> suits you. Every lesson is one-on-one with an ICBC-certified instructor.

### 2a. NEW: Where Class 7 fits in BC's Graduated Licensing Program

The intent-capture section. Placed high, right after the intro, because someone
who searched "GLP course" needs to orient before they will read a pitch.

> **Where Class 7 fits in BC's Graduated Licensing Program**
>
> Almost every new driver in BC goes through the Graduated Licensing Program, or
> GLP. It has three stages, and Class 7 covers the first two of them.
>
> **Stage 1: Class 7L, your learner's licence.** You pass the ICBC knowledge test,
> then spend at least a year practising with a supervisor before you are eligible
> for the Class 7 road test. This is the stage most of our beginner lessons are
> for. We start on quiet residential streets and move to busier roads once the
> basics stop taking up all your attention.
>
> **Stage 2: Class 7N, your novice licence.** Passing the Class 7 road test gets
> you here. You drive on your own, with restrictions on passengers and on your
> blood alcohol level. Lessons at this stage are usually about the roads people
> avoided as a learner: highway merging, driving at night, and parking somewhere
> genuinely tight.
>
> **Stage 3: Class 5, a full licence.** After the novice period you take one more
> road test, and passing it clears the last of the restrictions.
>
> **A note on ICBC-approved GLP courses.** You may have read that finishing an
> ICBC-approved GLP course shortens the novice stage. That is a specific course a
> school has to be separately authorised by ICBC to deliver, and it is not the
> same thing as regular driving lessons. Our lessons are built to get you ready to
> pass, not to shorten the waiting period. If the shorter novice stage is what you
> are after, look for a school advertising the approved course by name.

*Fact check before publishing:* the stage durations here must match the existing
GLP post word for word, or the two pages contradict each other. Pull the numbers
from that post rather than restating them from memory.

### 4a. NEW: Which stage are you at?

Two short paths so each audience self-selects instead of reading past the other.
Sits right after "What these lessons cover".

> **I have my 7L and I have never really driven.** Start with a beginner lesson.
> Dual-control car, no assumed knowledge, and an honest read on where you are
> after the first session. Most learners run lessons alongside practice hours with
> a parent or friend.
>
> **I have my 7L and my road test is coming up.** Lessons shift to test
> conditions: the manoeuvres that get marked, the mistakes that fail people, and
> the roads around the test centre. See [Class 7 road test preparation](/class-7-road-test-preparation-bc/).
>
> **I am driving on a 7N.** You already have the basics. Lessons here target
> highway, night, and confidence driving, and get you ready for the Class 5 test.
> Worth reading: [the rules that apply on a 7N](/class-7n-novice-restrictions-bc/)
> and [going from your N to a full Class 5](/class-7n-to-class-5-bc/).

### 5. Common questions (keep 3, add 3)

Existing three stay exactly as written. Add:

> **Is this an ICBC-approved GLP course?**
> No. We teach ICBC-certified driving lessons, which is not the same as the
> ICBC-approved GLP course that a school has to be separately authorised to run.
> Our lessons prepare you to pass your road tests; they do not shorten the novice
> stage. We would rather tell you that up front than have you find out later.

> **Will lessons shorten how long I have to wait between stages?**
> No. The waiting periods in the Graduated Licensing Program are set by ICBC and
> are the same whether or not you take lessons. What lessons change is how ready
> you are when the wait is over, which matters because a failed road test means
> booking again and waiting for the next appointment.

> **I'm nervous and I've never driven at all. Is that a problem?**
> Not remotely, and it is a lot of our students. First lessons for complete
> beginners start in a quiet area at a pace you set, in a car with a second brake
> on the instructor's side. There is nothing you can do that the instructor cannot
> undo.

These three plus the existing three all render through the theme accordion and
feed the existing FAQPage schema, so no schema work is needed.

### 6a. NEW: short local block

The SEO title promises Coquitlam and the body currently never delivers it.

> **Where we teach.** Lessons run across the Tri-Cities and the North Shore,
> including [Coquitlam](/locations/coquitlam/),
> [Port Coquitlam](/locations/port-coquitlam/),
> [Port Moody](/locations/port-moody/) and
> [North Vancouver](/locations/north-vancouver/). Your instructor picks up the
> lesson from an agreed meeting point.

*Check:* confirm the meeting-point wording against the no-pickup cleanup already
done site-wide. If it reads as a pickup offer at all, cut the last sentence.

### 8. CTA: leave exactly as is, per instruction.

---

## Metadata

| Field | Now | Proposed |
|---|---|---|
| `rank_math_focus_keyword` | *(empty)* | `class 7 driving lessons` |
| `rank_math_title` | Class 7 Driving Lessons in Coquitlam \| BuckleUp | unchanged |
| `rank_math_description` | *(current)* | unchanged |

## Terms this picks up

`class 7 driving lessons`, `GLP course BC`, `graduated licensing program lessons`,
`ICBC approved driving school`, `driving lessons for new drivers`,
`beginner driving lessons Coquitlam`, `7L lessons`, `7N lessons`.

The GLP terms are captured by genuinely answering them, which is the only version
of this that survives contact with a visitor.

## If approved

Applied as an idempotent Elementor script following the `fix-remaining-hub-links.php`
pattern: raw `mysqli` read, decode, edit the array, re-encode, `wp_slash`, then
delete `_elementor_element_cache`. Verify locally, then ship with the token-PHP
helper.
