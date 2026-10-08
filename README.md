# Image blog activity (`mod_imageblog`)

A Moodle **course activity** for graded clinical cases. A teacher poses a case;
readers submit a diagnosis; when the teacher reveals the outcome, each reader
receives a grade in the course gradebook.

This is the companion to the site-wide
[`local_imageblog`](https://github.com/verzog/moodle-local_imageblog) blog: the
local plugin is the public, site-wide showcase, while this activity brings the
clinical-case flow into a course where it can be graded and tracked. The two
share a context-neutral scoring engine.

> **Status: early alpha (v0.8.0).** It installs, adds to a course, shows a
> case, accepts a diagnosis, writes a CPD-style grade on reveal (with a
> teacher-selected best-answer bonus), supports reader questions with teacher
> answers, tags cases for browsing, tracks activity completion, sends
> notifications on key events, and backs up and restores cleanly. 360°
> panoramas are not here yet — see the design plan referenced below.

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

- A diagnosis the teacher marks as **best** earns the **best-answer factor**,
  overriding the checks below for that submission.
- Otherwise, a submission matching the expected diagnosis (case- and
  whitespace-insensitive) earns the **correct-answer factor**; any other
  non-empty submission earns the **participation factor**.
- The factor is scaled by the **case difficulty** multiplier (from the
  per-instance difficulty scale) and capped at full marks.
- `fraction × maximum grade` is written to the gradebook on reveal.

The difficulty level, difficulty scale, and the three factors
(participation / correct / best) are per-instance settings on the activity form.

After revealing the outcome, a teacher sees every submitted diagnosis and can
mark one as the best (or clear the selection); grades update automatically.

## Questions & answers

Each case carries a question-and-answer thread. Readers (with the
*Ask a question* capability) can post questions about the case; teachers (with
the *Answer a question* capability) can answer them or edit an answer. Answered
questions are visible to everyone who can view the activity, so the thread
doubles as a shared teaching resource. Peers see other readers' questions
anonymously, while the asker and teachers see the asker's name.

## Activity completion

The activity supports Moodle completion tracking. Alongside the standard
*view* condition, it offers a custom rule — **Student must submit a diagnosis**
— which marks the activity complete for a student once they submit a diagnosis.
Enable it on the activity's *Activity completion* settings.

## Case tags

Each case can be tagged with a dedicated **Case tags** field (separate from the
generic activity tags), classifying it by body system, imaging modality,
specialty or any scheme the teacher chooses. Tags appear on the case and link
to Moodle's tag pages, so a reader can follow a tag to other cases that share
it.

## Notifications

The activity sends notifications through Moodle's messaging system on three
events: when the teacher reveals a case outcome, each reader who submitted a
diagnosis is notified; when a reader posts a question, teachers who can answer
are notified; and when a teacher answers, the asker is notified. Each message
links back to the case and respects the recipient's messaging preferences.

## Roadmap

The design and phased plan live in the local plugin repository at
`doc/mod_imageblog-grading-plan.md`. Next milestone: 360° panoramas.

## Licence

GNU GPL v3 or later.
