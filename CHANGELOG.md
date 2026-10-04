# Changelog

All notable changes to the Image blog activity (`mod_imageblog`) are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/), and
the project aims to follow [Semantic Versioning](https://semver.org/).

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

[0.2.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.2.0
[0.1.0]: https://github.com/verzog/moodle-mod_imageblog/releases/tag/v0.1.0
