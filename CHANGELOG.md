# Changelog

All notable changes to the Image blog activity (`mod_imageblog`) are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/), and
the project aims to follow [Semantic Versioning](https://semver.org/).

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

[0.5.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.5.0
[0.4.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.4.0
[0.3.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.3.0
[0.2.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.2.0
[0.1.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.1.0
