# Image blog activity (`mod_imageblog`)

A Moodle **course activity** for graded clinical cases. A teacher poses a case;
readers submit a diagnosis; when the teacher reveals the outcome, each reader
receives a grade in the course gradebook.

This is the companion to the site-wide
[`local_imageblog`](https://github.com/verzog/moodle-local_imageblog) blog: the
local plugin is the public, site-wide showcase, while this activity brings the
clinical-case flow into a course where it can be graded and tracked. The two
share a context-neutral scoring engine.

> **Status: walking skeleton (v0.1.0).** It installs, adds to a course, shows a
> case, accepts a diagnosis and writes a grade on reveal. Taxonomy, panoramas,
> notifications, questions/answers, backup/restore and the full CPD scoring
> rules are not here yet — see the design plan referenced below.

## Requirements

* Moodle 5.0 or later (tested up to Moodle 5.3).
* PHP 8.2, 8.3 or 8.4 (per the requirements of the Moodle version in use).
* PostgreSQL, MySQL or MariaDB.

## What it does today

1. A teacher adds an **Image blog** activity to a course, writing the case
   prompt, the expected diagnosis and the outcome text, and setting the maximum
   grade.
2. Students open the activity and submit a diagnosis (one per student, editable
   until the outcome is revealed).
3. The teacher reveals the outcome. Each student who submitted is graded (see
   **Grading** below) and the grade flows to the gradebook.

## Requirements note

This activity depends on the **`local_imageblog`** plugin, which provides the
shared clinical-case scoring engine (`\local_imageblog\local\scoring`). Install
`local_imageblog` alongside it.

## Grading

Grading uses the standard Moodle Grade API. Each activity instance owns one
point-based grade item whose maximum is set on the settings form. The score is
computed through the shared engine and mapped by
`\mod_imageblog\local\grader::grade_fraction()`:

- A submission matching the expected diagnosis (case- and whitespace-insensitive)
  earns the **correct-answer factor**; any other non-empty submission earns the
  **participation factor**.
- That factor is scaled by the **case difficulty** multiplier (from the
  per-instance difficulty scale) and capped at full marks.
- `fraction × maximum grade` is written to the gradebook on reveal.

All four inputs — difficulty level, difficulty scale, and the two factors — are
per-instance settings on the activity form.

## Roadmap

The design and phased plan live in the local plugin repository at
`doc/mod_imageblog-grading-plan.md`. Next milestones: a teacher-selected
best-answer bonus, questions/answers, backup/restore, and completion rules.

## Licence

GNU GPL v3 or later.
