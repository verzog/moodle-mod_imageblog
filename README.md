# Diagnosis activity (`mod_diagnosis`)

A Moodle **course activity** for clinical cases. A teacher poses a case; readers
submit a diagnosis; the teacher marks each submission with normal Moodle grading
and can reveal the expected outcome and an explanation.

It is a self-contained activity module with no external plugin dependencies.

> **Status: early alpha (v0.1.0).** It installs, adds to a course, shows a case,
> accepts a diagnosis, lets the teacher mark each submission (simple grade or a
> rubric/marking guide) with the grade flowing to the gradebook, reveals the
> expected diagnosis and explanation, supports reader questions with teacher
> answers, tags cases for browsing, tracks activity completion, sends
> notifications on key events, shows an optional interactive 360° panorama and
> an optional interactive 3D model on the case, and backs up and restores
> cleanly.

## Requirements

* Moodle 5.0 or later (tested up to Moodle 5.3).
* PHP 8.2, 8.3 or 8.4 (per the requirements of the Moodle version in use).
* PostgreSQL, MySQL or MariaDB.

## What it does today

1. A teacher adds a **Diagnosis** activity to a course, writing the case prompt,
   the expected diagnosis and the outcome explanation, and setting the maximum
   grade and grading method.
2. Students open the activity and submit a diagnosis (one per student, editable
   until the outcome is revealed).
3. The teacher marks each submitted diagnosis (see **Grading** below); the grade
   flows to the gradebook. The teacher can also reveal the expected diagnosis and
   explanation to readers.
4. Around that core flow, a case can also carry a question-and-answer thread,
   case tags, activity-completion tracking, event notifications, and optional
   interactive **360° panorama** and **3D model** media — each described in its
   own section below. The activity backs up and restores with the course.

## Grading

Grading uses the standard Moodle Grade API — the same **manual marking** as the
Assignment module. Each activity instance owns one point-based grade item whose
maximum is set on the settings form, and the teacher marks each submitted
diagnosis from the activity page (**Grade submissions**), choosing per instance
between:

- **Simple direct grading** — enter a point value out of the maximum grade.
- **An advanced grading method** — a **rubric** or **marking guide**, selected
  via the standard **Grading method** control on the settings form and defined
  under the activity's **Advanced grading** settings.

Either way, the mark is stored on the submission and written to the gradebook as
soon as it is saved, and the student sees their grade on the activity once it is
awarded. Marking is independent of the reveal. If a student edits their diagnosis
after it was marked, the stored grade is cleared so the teacher re-marks the new
answer. Rubric fills are included in activity backup, restore and the privacy
(export/delete) API alongside the submissions.

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

## 360° panoramas

A case can carry an optional **360° panorama**: an equirectangular (2:1) image
rendered as an interactive viewer on the case page, using the bundled
[Pannellum](https://github.com/mpetroff/pannellum) library. Enable it on the
activity form with *Include a 360° panorama* and upload the image (JPEG or PNG,
up to 20 MB); readers can then drag to look around and scroll or pinch to zoom.
The viewer loads lazily and degrades to a short message if it cannot start, so
the rest of the case is unaffected. The image backs up and restores with the
activity.

## 3D models

A case can also carry an optional **3D model**, rendered as an interactive
viewer on the case page with the bundled [three.js](https://github.com/mrdoob/three.js)
library. Enable it on the activity form with *Include a 3D model* and upload the
main model file (up to 50 MB each) — and, when the format needs them, its
companion files alongside it (subfolders are allowed):

| Format | Extensions | Notes |
| --- | --- | --- |
| glTF / GLB | `.gltf`, `.glb` | A binary `.glb` is self-contained. A `.gltf` may reference an external `.bin` buffer and texture images — upload those alongside it at the paths the glTF expects. Materials and textures render. |
| STL | `.stl` | Geometry only (ASCII or binary); a neutral material is applied. |
| PLY | `.ply` | Mesh, or a point cloud (e.g. an Open3D scan) when the file has no faces; vertex colours render. |
| OBJ | `.obj` | Upload the companion `.mtl` material libraries (one or more, with their textures) to render materials; all uploaded libraries are merged, so an OBJ that declares several `mtllib` files is covered. Without any, the geometry renders with a neutral material. |

Companion files (`.bin`, `.mtl`, `.png`, `.jpg`, `.jpeg`, `.webp`) are served
from the activity's file area so the loader resolves them relative to the main
model, and the first recognised model file in the upload is used as the main
model. Readers drag to rotate, and scroll or pinch to zoom; the model is
auto-centred and framed. The viewer and the chosen format's loader load lazily
and degrade to a short message if they cannot start. The model and its
companion files back up and restore with the activity.

## Licence

GNU GPL v3 or later.
