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
 * Display a single image blog activity: the case, the diagnosis form and the outcome.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/formslib.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'imageblog');
$imageblog = $DB->get_record('imageblog', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/imageblog:view', $context);

$pageurl = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($imageblog->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canreveal = has_capability('mod/imageblog:reveal', $context);
$cansubmit = has_capability('mod/imageblog:submit', $context);

// Teacher action: reveal the outcome and award grades.
if ($canreveal && empty($imageblog->revealed) && optional_param('reveal', 0, PARAM_BOOL) && confirm_sesskey()) {
    $DB->set_field('imageblog', 'revealed', 1, ['id' => $imageblog->id]);
    $imageblog->revealed = 1;
    imageblog_update_grades($imageblog);
    redirect($pageurl, get_string('outcomerevealed', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Teacher action: mark (or clear) the best diagnosis. Only meaningful after reveal.
$setbest = optional_param('setbest', -1, PARAM_INT);
if ($canreveal && !empty($imageblog->revealed) && $setbest >= 0 && confirm_sesskey()) {
    // 0 clears the selection; otherwise the diagnosis must belong to this case.
    $valid = ($setbest === 0)
        || $DB->record_exists('imageblog_diagnoses', ['id' => $setbest, 'imageblogid' => $imageblog->id]);
    if ($valid) {
        $DB->set_field('imageblog', 'bestdiagnosisid', $setbest, ['id' => $imageblog->id]);
        $imageblog->bestdiagnosisid = $setbest;
        imageblog_update_grades($imageblog);
        redirect($pageurl, get_string('bestupdated', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Reader action: submit or update a diagnosis (only while the case is open).
$mform = null;
if ($cansubmit && empty($imageblog->revealed)) {
    $mform = new \mod_imageblog\form\diagnosis_form($pageurl->out(false));
    $existing = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $imageblog->id, 'userid' => $USER->id]);
    $mform->set_data([
        'id' => $cm->id,
        'diagnosis' => $existing ? $existing->diagnosis : '',
    ]);

    if ($data = $mform->get_data()) {
        // Re-check the persisted reveal state: a teacher may have revealed (and
        // graded) the case after this form was rendered. Saving now would leave
        // the diagnosis permanently locked but absent from the reveal-time
        // grade pass, so refuse the stale write.
        if ($DB->get_field('imageblog', 'revealed', ['id' => $imageblog->id])) {
            redirect($pageurl, get_string('casealreadyrevealed', 'mod_imageblog'), null, \core\output\notification::NOTIFY_WARNING);
        }
        $now = time();
        if ($existing) {
            $existing->diagnosis = $data->diagnosis;
            $existing->timemodified = $now;
            $DB->update_record('imageblog_diagnoses', $existing);
        } else {
            $record = (object) [
                'imageblogid' => $imageblog->id,
                'userid' => $USER->id,
                'diagnosis' => $data->diagnosis,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('imageblog_diagnoses', $record);
        }
        redirect($pageurl, get_string('diagnosissaved', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($imageblog->name));

if (!empty($imageblog->intro)) {
    echo $OUTPUT->box(format_module_intro('imageblog', $imageblog, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->heading(get_string('case', 'mod_imageblog'), 3);
echo $OUTPUT->box(format_text($imageblog->casequestion, FORMAT_MOODLE), 'generalbox');

$mydiagnosis = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $imageblog->id, 'userid' => $USER->id]);

if (!empty($imageblog->revealed)) {
    echo $OUTPUT->heading(get_string('outcome', 'mod_imageblog'), 3);
    echo $OUTPUT->box(format_text($imageblog->revealtext, FORMAT_MOODLE), 'generalbox');
    if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_imageblog', s($mydiagnosis->diagnosis)));
        $grades = imageblog_get_user_grades($imageblog, $USER->id);
        if (isset($grades[$USER->id]) && $grades[$USER->id]->rawgrade !== null) {
            $a = format_float($grades[$USER->id]->rawgrade, 2) . ' / ' . $imageblog->grade;
            echo html_writer::tag('p', get_string('yourgrade', 'mod_imageblog', $a));
        }
    }

    if ($canreveal) {
        echo $OUTPUT->heading(get_string('alldiagnoses', 'mod_imageblog'), 3);
        $alldiagnoses = $DB->get_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id], 'timecreated ASC');
        if (!$alldiagnoses) {
            echo html_writer::tag('p', get_string('nodiagnoses', 'mod_imageblog'));
        } else {
            $allgrades = imageblog_get_user_grades($imageblog);
            $besttable = new html_table();
            $besttable->head = [
                get_string('diagnosis', 'mod_imageblog'),
                get_string('grade'),
                get_string('bestanswer', 'mod_imageblog'),
            ];
            foreach ($alldiagnoses as $diag) {
                $isbestrow = ((int) $imageblog->bestdiagnosisid === (int) $diag->id);
                $gradecell = '-';
                if (isset($allgrades[$diag->userid]) && $allgrades[$diag->userid]->rawgrade !== null) {
                    $gradecell = format_float($allgrades[$diag->userid]->rawgrade, 2) . ' / ' . $imageblog->grade;
                }
                if ($isbestrow) {
                    $params = ['id' => $cm->id, 'setbest' => 0, 'sesskey' => sesskey()];
                    $url = new moodle_url('/mod/imageblog/view.php', $params);
                    $bestcell = html_writer::span(get_string('currentbest', 'mod_imageblog'), 'badge badge-success')
                        . ' ' . html_writer::link($url, get_string('clearbest', 'mod_imageblog'));
                } else {
                    $params = ['id' => $cm->id, 'setbest' => $diag->id, 'sesskey' => sesskey()];
                    $url = new moodle_url('/mod/imageblog/view.php', $params);
                    $bestcell = html_writer::link($url, get_string('markbest', 'mod_imageblog'));
                }
                $besttable->data[] = [s($diag->diagnosis), $gradecell, $bestcell];
            }
            echo html_writer::table($besttable);
        }
    }
} else {
    if ($mform) {
        echo $OUTPUT->heading(get_string('yourdiagnosisheading', 'mod_imageblog'), 3);
        $mform->display();
    } else if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_imageblog', s($mydiagnosis->diagnosis)));
    }
    if ($canreveal) {
        $revealurl = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id, 'reveal' => 1, 'sesskey' => sesskey()]);
        echo $OUTPUT->single_button($revealurl, get_string('revealoutcome', 'mod_imageblog'));
    }
}

echo $OUTPUT->footer();
