<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Grade one submitted diagnosis with the active advanced grading method.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$id = required_param('id', PARAM_INT);         // Course module id.
$userid = required_param('userid', PARAM_INT); // The user whose diagnosis is graded.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'imageblog');
$imageblog = $DB->get_record('imageblog', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
// Grading a submission is a case-management action, gated by the same teacher
// capability as revealing the outcome and marking the best answer.
require_capability('mod/imageblog:reveal', $context);

$pageurl = new moodle_url('/mod/imageblog/grade.php', ['id' => $cm->id, 'userid' => $userid]);
$returnurl = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($imageblog->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Only a submitted diagnosis can be graded; its id is the grading item id.
$diagnosis = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $imageblog->id, 'userid' => $userid]);
if (!$diagnosis) {
    redirect($returnurl, get_string('nodiagnosistograde', 'mod_imageblog'), null, \core\output\notification::NOTIFY_WARNING);
}

$gradeduser = \core_user::get_user($userid, '*', MUST_EXIST);

$gradingmanager = get_grading_manager($context, 'mod_imageblog', 'submissions');
$controller = ($method = $gradingmanager->get_active_method()) ? $gradingmanager->get_controller($method) : null;
if (!$controller) {
    // No advanced grading method is selected, so there is nothing to grade here.
    redirect($returnurl, get_string('gradingnotactive', 'mod_imageblog'), null, \core\output\notification::NOTIFY_WARNING);
}

// Build the marking form and process a submission before any output, so a save
// or cancel can redirect cleanly. The form is only usable once the rubric has
// been defined (is_form_available()).
$mform = null;
$formavailable = $controller->is_form_available();
if ($formavailable) {
    // Build (or fetch) the grading instance for this submission and scale it to
    // the activity's point range.
    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
    $gradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $diagnosis->id);
    $gradinginstance->get_controller()->set_grade_range(make_grades_menu($imageblog->grade), $imageblog->grade > 0);

    $mform = new \mod_imageblog\form\grade_form($pageurl->out(false), ['gradinginstance' => $gradinginstance]);
    $mform->set_data([
        'id' => $cm->id,
        'userid' => $userid,
        'advancedgradinginstanceid' => $gradinginstance->get_id(),
    ]);

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        $diagnosis->rubricgrade = $gradinginstance->submit_and_get_grade($data->advancedgrading, $diagnosis->id);
        $diagnosis->timemodified = time();
        $DB->update_record('imageblog_diagnoses', $diagnosis);
        imageblog_update_grades($imageblog, $diagnosis->userid);
        redirect($returnurl, get_string('gradesaved', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradeuser', 'mod_imageblog', fullname($gradeduser)));

if (!$formavailable) {
    // The method is selected but the rubric has not been defined yet.
    echo $controller->form_unavailable_notification();
} else {
    // Show the diagnosis being graded, then the grading form.
    echo $OUTPUT->box(s($diagnosis->diagnosis), 'generalbox');
    $mform->display();
}

echo $OUTPUT->footer();
