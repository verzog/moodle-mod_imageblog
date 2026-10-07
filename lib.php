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
 * Library of interface functions and constants for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declare which optional features this activity supports.
 *
 * @param string $feature one of the FEATURE_xx constants
 * @return mixed true/false for a known feature, null for an unknown one
 */
function imageblog_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            // Backup/restore is a later milestone; the skeleton does not ship it yet.
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Create a new image blog instance.
 *
 * @param stdClass $data submitted form data (with coursemodule set)
 * @param mod_imageblog_mod_form|null $mform the form instance, if any
 * @return int the id of the newly created instance
 */
function imageblog_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('imageblog', $data);

    imageblog_grade_item_update($data);

    return $data->id;
}

/**
 * Update an existing image blog instance.
 *
 * @param stdClass $data submitted form data (with instance set)
 * @param mod_imageblog_mod_form|null $mform the form instance, if any
 * @return bool true on success
 */
function imageblog_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $DB->update_record('imageblog', $data);

    // Reload the full record: the form data omits fields that are not form
    // elements (such as "revealed"), and imageblog_update_grades() needs the
    // real reveal state to decide whether grades exist.
    $imageblog = $DB->get_record('imageblog', ['id' => $data->id], '*', MUST_EXIST);
    imageblog_grade_item_update($imageblog);
    imageblog_update_grades($imageblog);

    return true;
}

/**
 * Delete an image blog instance and its associated data.
 *
 * @param int $id the instance id
 * @return bool true on success
 */
function imageblog_delete_instance($id) {
    global $DB;

    $imageblog = $DB->get_record('imageblog', ['id' => $id]);
    if (!$imageblog) {
        return false;
    }

    $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id]);
    $DB->delete_records('imageblog', ['id' => $imageblog->id]);

    imageblog_grade_item_delete($imageblog);

    return true;
}

/**
 * Create or update the grade item for an image blog instance.
 *
 * @param stdClass $imageblog the instance record (must include course, id, name, grade)
 * @param array|object|string $grades raw grades to push, or 'reset' to reset the item
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED and friends
 */
function imageblog_grade_item_update($imageblog, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($imageblog->name, PARAM_NOTAGS),
    ];

    if (isset($imageblog->grade) && $imageblog->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $imageblog->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/imageblog', $imageblog->course, 'mod', 'imageblog', $imageblog->id, 0, $grades, $item);
}

/**
 * Delete the grade item for an image blog instance.
 *
 * @param stdClass $imageblog the instance record
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED and friends
 */
function imageblog_grade_item_delete($imageblog) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/imageblog', $imageblog->course, 'mod', 'imageblog', $imageblog->id, 0, null, ['deleted' => 1]);
}

/**
 * Compute the grades a set of users have earned on an image blog instance.
 *
 * Grades only exist once the case outcome has been revealed. The grade is the
 * scoring engine's fraction (0..1) scaled by the configured maximum.
 *
 * @param stdClass $imageblog the instance record
 * @param int $userid a single user to compute for, or 0 for everyone
 * @return array userid => object{userid, rawgrade}
 */
function imageblog_get_user_grades($imageblog, $userid = 0) {
    global $DB;

    if (empty($imageblog->revealed) || empty($imageblog->grade) || $imageblog->grade <= 0) {
        return [];
    }

    $params = ['imageblogid' => $imageblog->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $grades = [];
    foreach ($DB->get_records('imageblog_diagnoses', $params) as $record) {
        $isbest = !empty($imageblog->bestdiagnosisid)
            && (int) $record->id === (int) $imageblog->bestdiagnosisid;
        $fraction = \mod_imageblog\local\grader::grade_fraction(
            (string) $record->diagnosis,
            (string) $imageblog->correctdiagnosis,
            $isbest,
            (int) $imageblog->casedifficulty,
            (string) $imageblog->difficultyscale,
            (float) $imageblog->participationfactor,
            (float) $imageblog->correctfactor,
            (float) $imageblog->bestfactor
        );
        $grades[$record->userid] = (object) [
            'userid' => $record->userid,
            'rawgrade' => $fraction * $imageblog->grade,
        ];
    }

    return $grades;
}

/**
 * Push the current grades for an image blog instance into the gradebook.
 *
 * @param stdClass $imageblog the instance record
 * @param int $userid a single user to update, or 0 for everyone
 * @param bool $nullifnone whether to store a null grade when a named user has none
 * @return void
 */
function imageblog_update_grades($imageblog, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (empty($imageblog->grade) || $imageblog->grade <= 0) {
        imageblog_grade_item_update($imageblog);
        return;
    }

    $grades = imageblog_get_user_grades($imageblog, $userid);
    if ($grades) {
        imageblog_grade_item_update($imageblog, $grades);
    } else if ($userid && $nullifnone) {
        imageblog_grade_item_update($imageblog, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        imageblog_grade_item_update($imageblog);
    }
}

/**
 * Serve files from the activity's intro file area.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the module context
 * @param string $filearea the name of the file area
 * @param array $args the remaining path arguments (itemid is implicitly 0 for intro)
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting file serving
 * @return bool false if the file was not found; otherwise the file is sent and execution stops
 */
function imageblog_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    require_capability('mod/imageblog:view', $context);

    if ($filearea !== 'intro') {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_imageblog', 'intro', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
