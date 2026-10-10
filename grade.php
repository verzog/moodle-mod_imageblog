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
 * Mark one submitted diagnosis, with simple direct grading or an advanced
 * grading method (e.g. a rubric).
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$id = required_param('id', PARAM_INT);         // Course module id.
$userid = required_param('userid', PARAM_INT); // The user whose diagnosis is graded.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'diagnosis');
$instance = $DB->get_record('diagnosis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
// Marking a submission is a teacher action, gated by the same capability as
// revealing the outcome.
require_capability('mod/diagnosis:reveal', $context);

$pageurl = new moodle_url('/mod/diagnosis/grade.php', ['id' => $cm->id, 'userid' => $userid]);
$returnurl = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Only a submitted diagnosis can be graded; its id is the grading item id.
$submission = $DB->get_record('diagnosis_submissions', ['diagnosisid' => $instance->id, 'userid' => $userid]);
if (!$submission) {
    redirect($returnurl, get_string('nodiagnosistograde', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_WARNING);
}
if (empty($instance->grade) || $instance->grade <= 0) {
    redirect($returnurl, get_string('gradingdisabled', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_WARNING);
}

$gradeduser = \core_user::get_user($userid, '*', MUST_EXIST);

// An advanced grading method (rubric, marking guide) may be active; otherwise
// the teacher enters a simple point grade.
$gradingmanager = get_grading_manager($context, 'mod_diagnosis', 'submissions');
$controller = ($method = $gradingmanager->get_active_method()) ? $gradingmanager->get_controller($method) : null;

// Build the marking form and process a submission before any output so a save
// or cancel can redirect cleanly. With advanced grading, the form is only usable
// once the method's form (e.g. the rubric) has been defined.
$mform = null;
$gradinginstance = null;
$formavailable = true;
if ($controller) {
    $formavailable = $controller->is_form_available();
}
if ($formavailable) {
    $customdata = ['maxgrade' => $instance->grade];
    if ($controller) {
        $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
        $gradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $submission->id);
        $gradinginstance->get_controller()->set_grade_range(make_grades_menu($instance->grade), $instance->grade > 0);
        $customdata['gradinginstance'] = $gradinginstance;
    }

    $mform = new \mod_diagnosis\form\grade_form($pageurl->out(false), $customdata);
    $setdata = ['id' => $cm->id, 'userid' => $userid];
    if ($gradinginstance) {
        $setdata['advancedgradinginstanceid'] = $gradinginstance->get_id();
    } else if ($submission->grade !== null) {
        $setdata['grade'] = format_float($submission->grade, 5, true, true);
    }
    $mform->set_data($setdata);

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        if ($gradinginstance) {
            $submission->grade = $gradinginstance->submit_and_get_grade($data->advancedgrading, $submission->id);
        } else {
            $submission->grade = (trim((string) $data->grade) === '') ? null : unformat_float($data->grade);
        }
        $submission->timemodified = time();
        $DB->update_record('diagnosis_submissions', $submission);
        diagnosis_update_grades($instance, $submission->userid);
        redirect($returnurl, get_string('gradesaved', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradeuser', 'mod_diagnosis', fullname($gradeduser)));

if (!$formavailable) {
    // An advanced method is selected but its form (e.g. the rubric) is not defined yet.
    echo $controller->form_unavailable_notification();
} else {
    // Show the diagnosis being graded, then the marking form.
    echo $OUTPUT->box(s($submission->diagnosis), 'generalbox');
    $mform->display();
}

echo $OUTPUT->footer();
