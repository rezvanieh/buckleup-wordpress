#!/usr/bin/env python3
"""
Deterministically extract the VERIFIED questions (intact text + 4 options + a
printed answer-key letter) from the ICBC Class 4 sample PDF into a PHP data file
consumed by buckleup_quiz_seed_questions().

The provided PDF is a free SAMPLE: only Q1-Q35 have a published answer key
(later answer keys + Q101+ question text are redacted behind a paid upsell). So
this script emits exactly the questions for which the source gives us a
verifiable correct answer — currently Q1-Q35, across two categories.

Usage (host tooling; pure source generation — no WordPress involved):
    pdftotext -layout driving-tests/ICBC-class-4-Knowledge-practice-test-S-.pdf /tmp/icbc-cl4.txt
    python3 scripts/wp/quiz/parse-verified.py /tmp/icbc-cl4.txt \
        wp-content/plugins/buckleup-quiz/includes/data/questions-verified.php
"""
import re
import sys

# Category by question-number range (from the PDF chapter structure). Only the
# ranges that contain verified (answered) questions matter here.
RANGES = [
    (1, 20, "getting-your-licence"),
    (21, 55, "heavy-vehicle-braking"),
    (56, 140, "basic-driving-skills"),
    (141, 155, "fuel-efficient-driving"),
    (156, 165, "trucks-and-trailers"),
    (166, 275, "buses-taxis-limos-ride-hailing"),
    (276, 290, "hours-of-service"),
    (291, 330, "air-brakes"),
    (331, 350, "air-brake-adjustment"),
    (351, 385, "pre-trip-inspections"),
    (386, 405, "signs-signals-and-markings"),
    (406, 410, "industrial-roads"),
]
LETTER_TO_INDEX = {"A": 0, "B": 1, "C": 2, "D": 3}


def category_for(n):
    for lo, hi, slug in RANGES:
        if lo <= n <= hi:
            return slug
    return "uncategorised"


def clean(line):
    # Strip zero-width spaces, form-feeds, and trailing whitespace.
    line = line.replace("​", "").replace("\x0c", "")
    return line.rstrip()


def is_artifact(line):
    s = line.strip()
    if s == "":
        return True
    if "Get Your Full Copy" in s:
        return True
    if s.startswith("Answer Key ("):
        return True
    if re.match(r"^Chapter \d+:", s):
        return True
    # Sub-section heading: "Some Title (N Questions)"
    if re.match(r"^[A-Z].*\(\d+ Questions\)\s*$", s):
        return True
    return False


def main():
    src, out = sys.argv[1], sys.argv[2]
    raw = open(src, encoding="utf-8").read().split("\n")

    # Begin after the INTRODUCTION (skip the table of contents copy).
    start = 0
    for i, l in enumerate(raw):
        if l.strip() == "INTRODUCTION":
            start = i
            break
    lines = [clean(l) for l in raw[start:]]

    # ---- Pass 1: questions (number, text, 4 options) -----------------------
    q_start = re.compile(r"^(\d+)\.\s+(.*)$")
    opt = re.compile(r"^([A-D])\.\s+(.*)$")
    ans_hdr = re.compile(r"^Answer Key \(")

    questions = {}  # n -> {"text":..., "options":[a,b,c,d]}
    i = 0
    n = len(lines)
    in_answer_block = False
    while i < n:
        line = lines[i]
        if ans_hdr.match(line):
            in_answer_block = True
            i += 1
            continue
        m = q_start.match(line)
        # A question starts only outside an answer block and when the text after
        # "N." is NOT just an A-D letter (which would be an answer-key entry).
        if m and not in_answer_block and not re.match(r"^[A-D]$", m.group(2).strip()):
            num = int(m.group(1))
            text_parts = [m.group(2).strip()]
            i += 1
            # Question text continuation (until first option marker).
            while i < n and not opt.match(lines[i]) and not is_artifact(lines[i]) and not q_start.match(lines[i]):
                text_parts.append(lines[i].strip())
                i += 1
            options = []
            # Collect options A-D, each possibly wrapping across lines.
            while i < n and opt.match(lines[i]):
                om = opt.match(lines[i])
                otext = [om.group(2).strip()]
                i += 1
                while i < n and not opt.match(lines[i]) and not is_artifact(lines[i]) and not q_start.match(lines[i]) and not ans_hdr.match(lines[i]):
                    otext.append(lines[i].strip())
                    i += 1
                options.append(" ".join(p for p in otext if p).strip())
            questions[num] = {
                "text": " ".join(p for p in text_parts if p).strip(),
                "options": options,
            }
            continue
        # Leaving an answer block when we hit a non-answer, non-blank line.
        if in_answer_block and line.strip() and not re.match(r"^\d+\.\s*[A-D]\b", line.strip()):
            in_answer_block = False
        i += 1

    # ---- Pass 2: answer keys (number -> letter) ----------------------------
    answers = {}
    ans_entry = re.compile(r"^(\d+)\.\s*([A-D])\b")
    i = 0
    while i < n:
        if ans_hdr.match(lines[i]):
            i += 1
            while i < n:
                s = lines[i].strip()
                if s == "" or "Get Your Full Copy" in s:
                    i += 1
                    continue
                am = ans_entry.match(s)
                if am:
                    answers[int(am.group(1))] = am.group(2)
                    i += 1
                    continue
                break  # next section
            continue
        i += 1

    # ---- Join: emit only questions with intact text + 4 options + an answer -
    rows = []
    skipped = []
    for num in sorted(questions):
        q = questions[num]
        if num not in answers:
            continue  # no published answer (Q36+)
        if "*" in q["text"] or any("*" in o for o in q["options"]):
            skipped.append((num, "masked"))
            continue
        if len(q["options"]) != 4:
            skipped.append((num, f"{len(q['options'])} options"))
            continue
        rows.append((num, category_for(num), q["text"], q["options"], LETTER_TO_INDEX[answers[num]]))

    # ---- Write PHP ---------------------------------------------------------
    def esc(s):
        return s.replace("\\", "\\\\").replace("'", "\\'")

    php = [
        "<?php",
        "/**",
        " * VERIFIED ICBC Class 4 questions extracted from the source sample PDF",
        " * (driving-tests/ICBC-class-4-Knowledge-practice-test-S-.pdf). These are the",
        " * questions for which the PDF publishes an authoritative answer key (Q1-Q35).",
        " *",
        " * GENERATED by scripts/wp/quiz/parse-verified.py — do not hand-edit; re-run the",
        " * parser to regenerate. Consumed by buckleup_quiz_seed_questions().",
        " *",
        " * @package BuckleUp_Quiz",
        " */",
        "",
        "if ( ! defined( 'ABSPATH' ) ) { return array(); }",
        "",
        "return array(",
    ]
    for num, cat, text, opts, ci in rows:
        php.append("\tarray(")
        php.append(f"\t\t'source_ref'    => 'ICBC-CL4-PDF#{num}',")
        php.append(f"\t\t'category'      => '{cat}',")
        php.append(f"\t\t'question'      => '{esc(text)}',")
        php.append(f"\t\t'option_a'      => '{esc(opts[0])}',")
        php.append(f"\t\t'option_b'      => '{esc(opts[1])}',")
        php.append(f"\t\t'option_c'      => '{esc(opts[2])}',")
        php.append(f"\t\t'option_d'      => '{esc(opts[3])}',")
        php.append(f"\t\t'correct_index' => {ci},")
        php.append("\t\t'explanation'   => '',")
        php.append("\t\t'difficulty'    => 2,")
        php.append("\t),")
    php.append(");")
    open(out, "w", encoding="utf-8").write("\n".join(php) + "\n")

    # ---- Report ------------------------------------------------------------
    by_cat = {}
    for _, cat, *_ in rows:
        by_cat[cat] = by_cat.get(cat, 0) + 1
    print(f"Emitted {len(rows)} verified questions -> {out}")
    for cat, c in sorted(by_cat.items()):
        print(f"  {cat}: {c}")
    if skipped:
        print(f"Skipped {len(skipped)}: {skipped[:8]}{'...' if len(skipped) > 8 else ''}")
    print(f"(questions parsed: {len(questions)}, answers parsed: {len(answers)})")


if __name__ == "__main__":
    main()
