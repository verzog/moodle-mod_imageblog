# Changelog

All notable changes to the Image blog activity (`mod_imageblog`) are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/), and
the project aims to follow [Semantic Versioning](https://semver.org/).

## [0.9.0] - 2026-10-08

### Added
- **360° panoramas.** A case can include an optional equirectangular panorama
  image, rendered as an interactive viewer on the case page with the bundled
  Pannellum library (loaded lazily, with a graceful fallback). Adds the
  *Include a 360° panorama* toggle and a file uploader on the activity form, a
  `panorama` module-context file area served through `imageblog_pluginfile`,
  backup/restore of the image, `styles.css`, `thirdparty/pannellum` with
  `thirdpartylibs.xml`, and PHPUnit tests. No schema change (the image lives in
  file storage). This completes the phased build-out in the design plan.

## [0.8.0] - 2026-10-08

### Added
- **Notifications** via the Moodle Message API. Three message providers:
  *outcome revealed* (to each reader who submitted, when the teacher reveals
  the case), *new question posted* (to teachers who can answer, when a reader
  asks), and *question answered* (to the asker, when a teacher answers). Each
  carries a link back to the activity and respects the recipient's messaging
  preferences. Adds `db/messages.php`, the sending helpers, and a PHPUnit test.

## [0.7.0] - 2026-10-08

### Added
- **Case taxonomy (tags).** Cases can be tagged with a dedicated *Case tags*
  field backed by a `mod_imageblog`/`imageblog` core tag area, kept separate
  from the generic activity tags. Tags show on the case view and link to
  Moodle's tag pages, so readers can follow a tag to other cases that share it.
  Adds `db/tag.php`, the form field, display, backup/restore of the tags, and a
  backup/restore test. No schema change (tags use the core tag tables).

## [0.6.0] - 2026-10-08

### Added
- **Activity completion.** The activity now supports completion tracking: a
  custom rule *Student must submit a diagnosis* (`completionsubmit`) that
  completes once the student submits, plus the standard *view* rule. Adds the
  `completionsubmit` instance setting (with an upgrade step), the
  `\mod_imageblog\completion\custom_completion` class, completion marking on
  view and on diagnosis submission, backup of the new setting, and a PHPUnit
  test for the rule state.

## [0.5.0] - 2026-10-07

### Added
- **Questions & answers on a case.** Readers can ask questions about a case
  (new `mod/imageblog:askquestion` capability) and teachers can answer or edit
  an answer (`mod/imageblog:answerquestion`). Answered questions are shown to
  everyone; peers see other readers' questions anonymously, while the asker and
  teachers see the name. Adds the `imageblog_questions` table (with an upgrade
  step), ask/answer forms, backup/restore of questions and answers (remapping
  both asker and answerer), and full privacy export/erasure for questions.

## [0.4.0] - 2026-10-07

### Added
- **Backup and restore** (Moodle 2 format). An image blog activity now backs up
  its case settings, the activity intro files, and — when user data is included
  — every submitted diagnosis. On restore the teacher-selected best answer is
  remapped to follow its diagnosis to the new id, so the best-answer bonus
  survives course copy, import and restore. Adds a test data generator and a
  backup/restore PHPUnit test covering the remap, and flips
  `FEATURE_BACKUP_MOODLE2` on.

## [0.3.0] - 2026-10-04

### Added
- Teacher-selected **best-answer bonus**: once the outcome is revealed, a
  teacher sees all submitted diagnoses and can mark one as the best. That
  diagnosis is graded with a configurable best-answer factor (overriding the
  correct/participation factors for that submission), scaled by difficulty and
  capped at full marks. Adds the per-instance `bestfactor` setting and a
  `bestdiagnosisid` column (with an upgrade step); `grader::grade_fraction()`
  gains the best-answer path. Mark/clear actions post (rather than following a
  link), the regrade reads the persisted instance, the privacy export reports
  whether a diagnosis was marked best, and erasure clears a best-answer
  reference that would otherwise dangle.

## [0.2.0] - 2026-10-04

### Changed
- Grade on the shared CPD-style scoring engine. The activity now depends on
  `local_imageblog` and routes grading through `\local_imageblog\local\scoring`
  via a thin `\mod_imageblog\local\grader` adapter, replacing the standalone
  correctness-only `case_engine`.

### Added
- Per-instance scoring settings: case difficulty level, a difficulty scale, and
  participation / correct-answer factors. A correct answer earns the correct
  factor, any other submission the participation factor, scaled by the case
  difficulty and capped at the maximum grade.
- `db/upgrade.php` adds the new instance columns for existing installs.
- CI installs `local_imageblog` as a dependency so the shared engine resolves.

## [0.1.0] - 2026-10-04

Initial walking skeleton.

### Added
- Course activity `mod_imageblog` that presents a single clinical case:
  readers submit a diagnosis and, once a teacher reveals the outcome, receive a
  gradebook grade.
- Gradebook integration via the Grade API (one point-based grade item per
  instance, maximum configurable on the settings form).
- A context-neutral scoring engine (`\mod_imageblog\local\case_engine`) shared
  with the wider project; the skeleton scores an exact match as full marks and
  any other submission as participation credit.
- Privacy API provider covering the stored diagnoses (export and erasure).
- Capabilities, language strings, a PHPUnit test for the scoring engine, and a
  CI workflow across Moodle 5.0–5.3.

### Not yet included
- Backup/restore, taxonomy, 360° panoramas, notifications, questions/answers,
  difficulty multipliers and the best-answer bonus — see
  `moodle-local_imageblog/doc/mod_imageblog-grading-plan.md`.

[0.9.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.9.0
[0.8.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.8.0
[0.7.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.7.0
[0.6.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.6.0
[0.5.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.5.0
[0.4.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.4.0
[0.3.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.3.0
[0.2.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.2.0
[0.1.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.1.0
