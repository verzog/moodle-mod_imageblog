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
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
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
    $data->completionsubmit = empty($data->completionsubmit) ? 0 : 1;
    $data->id = $DB->insert_record('imageblog', $data);

    imageblog_grade_item_update($data);

    if (isset($data->casetags)) {
        $context = context_module::instance($data->coursemodule);
        core_tag_tag::set_item_tags('mod_imageblog', 'imageblog', $data->id, $context, $data->casetags);
    }

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
    // Only touch the completion rule when the teacher could actually edit it:
    // once a learner has completion data Moodle locks and omits these controls,
    // so an unconditional normalisation would silently disable the stored rule.
    if (!empty($data->completionunlocked)) {
        $data->completionsubmit = empty($data->completionsubmit) ? 0 : 1;
    } else {
        unset($data->completionsubmit);
    }
    $DB->update_record('imageblog', $data);

    // Reload the full record: the form data omits fields that are not form
    // elements (such as "revealed"), and imageblog_update_grades() needs the
    // real reveal state to decide whether grades exist.
    $imageblog = $DB->get_record('imageblog', ['id' => $data->id], '*', MUST_EXIST);
    imageblog_grade_item_update($imageblog);
    imageblog_update_grades($imageblog);

    if (isset($data->casetags)) {
        $context = context_module::instance($data->coursemodule);
        core_tag_tag::set_item_tags('mod_imageblog', 'imageblog', $data->id, $context, $data->casetags);
    }

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

    core_tag_tag::remove_all_item_tags('mod_imageblog', 'imageblog', $imageblog->id);

    $DB->delete_records('imageblog_questions', ['imageblogid' => $imageblog->id]);
    $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id]);
    $DB->delete_records('imageblog', ['id' => $imageblog->id]);

    imageblog_grade_item_delete($imageblog);

    return true;
}

/**
 * Provide course-module info, including the custom completion rules in use.
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|false the course-module info, or false if the instance is missing
 */
function imageblog_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit';
    $imageblog = $DB->get_record('imageblog', ['id' => $coursemodule->instance], $fields);
    if (!$imageblog) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $imageblog->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('imageblog', $imageblog, $coursemodule->id, false);
    }

    // Expose the custom completion rule so the completion API treats it as available.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = $imageblog->completionsubmit;
    }

    return $info;
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

/**
 * Build the tag index for image blog cases carrying a given tag.
 *
 * @param core_tag_tag $tag the tag being viewed
 * @param bool $exclusivemode whether only this component/itemtype is shown
 * @param int $fromcontextid the context the tag page was reached from, or 0
 * @param int $contextid the context to restrict the results to, or 0 for the whole site
 * @param bool $recursivecontext whether to include child contexts of $contextid
 * @param int $page the zero-based page number
 * @return \core_tag\output\tagindex the rendered tag index
 */
function mod_imageblog_get_tagged_cases(
    $tag,
    $exclusivemode = false,
    $fromcontextid = 0,
    $contextid = 0,
    $recursivecontext = true,
    $page = 0
) {
    global $OUTPUT;

    $perpage = $exclusivemode ? 20 : 5;

    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');

    $query = "SELECT i.id, i.name, cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {imageblog} i
                JOIN {modules} m ON m.name = 'imageblog'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = i.id
                JOIN {tag_instance} tt ON tt.itemid = i.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
                JOIN {course} c ON c.id = cm.course
               WHERE tt.itemtype = :itemtype
                 AND tt.tagid = :tagid
                 AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND i.id %ITEMFILTER%
                 AND c.id %COURSEFILTER%";

    $params = [
        'itemtype' => 'imageblog',
        'tagid' => $tag->id,
        'component' => 'mod_imageblog',
        'coursemodulecontextlevel' => CONTEXT_MODULE,
    ];

    if ($contextid) {
        $context = context::instance_by_id($contextid);
        $query .= $recursivecontext ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        if ($recursivecontext) {
            $params['path'] = $context->path . '/%';
        }
    }

    $query .= ' ORDER BY ';
    if ($fromcontextid) {
        $query .= '(CASE WHEN ctx.id = :preferredcontextid THEN 0 ELSE 1 END), ';
        $params['preferredcontextid'] = $fromcontextid;
    }
    $query .= 'c.sortorder, cm.id';

    $builder = new core_tag_index_builder('mod_imageblog', 'imageblog', $query, $params, $page * $perpage, $perpage + 1);

    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        if (!$builder->can_access_course($item->courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($item->courseid);
        $cm = $modinfo->get_cm($item->cmid);
        $builder->set_accessible($item, $cm->uservisible);
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2;
        array_pop($items);
    } else {
        $totalpages = $page + ($items ? 1 : 0);
    }

    $tagfeed = new core_tag\output\tagfeed();
    foreach ($items as $item) {
        context_helper::preload_from_record($item);
        $modinfo = get_fast_modinfo($item->courseid);
        $cm = $modinfo->get_cm($item->cmid);
        $pageurl = new moodle_url('/mod/imageblog/view.php', ['id' => $item->cmid]);
        $pagename = html_writer::link($pageurl, format_string($item->name, true, ['context' => $cm->context]));
        $courseurl = course_get_url($item->courseid, $cm->sectionnum);
        $coursename = html_writer::link($courseurl, format_string($item->fullname, true, ['context' => $cm->context]));
        $icon = html_writer::link($pageurl, $OUTPUT->pix_icon('monologo', '', 'mod_imageblog'));
        $tagfeed->add($icon, $pagename, $coursename);
    }

    $content = $OUTPUT->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($OUTPUT));

    return new core_tag\output\tagindex(
        $tag,
        'mod_imageblog',
        'imageblog',
        $content,
        $exclusivemode,
        $fromcontextid,
        $contextid,
        $recursivecontext,
        $page,
        $totalpages
    );
}

/**
 * Send one image blog notification.
 *
 * @param string $name the message provider name
 * @param stdClass $userfrom the sending user
 * @param stdClass $userto the receiving user
 * @param stdClass $a the subject/body placeholder data (name, course)
 * @param stdClass $imageblog the instance record
 * @param stdClass $cm the course module record
 * @param moodle_url $url the activity view url
 * @return mixed the message id, or false on failure
 */
function imageblog_send_notification($name, $userfrom, $userto, $a, $imageblog, $cm, moodle_url $url) {
    $message = new \core\message\message();
    $message->component = 'mod_imageblog';
    $message->name = $name;
    $message->userfrom = $userfrom;
    $message->userto = $userto;
    $message->subject = get_string('messagesubject_' . $name, 'mod_imageblog', $a);
    $message->fullmessage = get_string('messagebody_' . $name, 'mod_imageblog', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = html_writer::tag('p', get_string('messagebody_' . $name, 'mod_imageblog', $a));
    $message->smallmessage = get_string('messagebody_' . $name, 'mod_imageblog', $a);
    $message->notification = 1;
    $message->courseid = $cm->course;
    $message->contexturl = $url->out(false);
    $message->contexturlname = format_string($imageblog->name);

    return message_send($message);
}

/**
 * Notify everyone who submitted a diagnosis that the case outcome has been revealed.
 *
 * @param stdClass $imageblog the instance record
 * @param stdClass $cm the course module record
 * @param context $context the module context
 * @param stdClass $userfrom the teacher revealing the outcome
 * @return void
 */
function imageblog_notify_outcome_revealed($imageblog, $cm, $context, $userfrom) {
    global $DB;

    $recipients = $DB->get_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id], '', 'id, userid');
    if (!$recipients) {
        return;
    }

    $a = imageblog_notification_data($imageblog, $cm);
    $url = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
    foreach ($recipients as $recipient) {
        $userto = \core_user::get_user($recipient->userid);
        if (!$userto || $userto->deleted) {
            continue;
        }
        imageblog_send_notification('outcomerevealed', $userfrom, $userto, $a, $imageblog, $cm, $url);
    }
}

/**
 * Notify teachers who can answer that a reader posted a question.
 *
 * @param stdClass $imageblog the instance record
 * @param stdClass $cm the course module record
 * @param context $context the module context
 * @param stdClass $userfrom the reader who asked
 * @return void
 */
function imageblog_notify_question_posted($imageblog, $cm, $context, $userfrom) {
    $recipients = get_enrolled_users($context, 'mod/imageblog:answerquestion');
    if (!$recipients) {
        return;
    }

    $a = imageblog_notification_data($imageblog, $cm);
    $url = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
    foreach ($recipients as $userto) {
        if ((int) $userto->id === (int) $userfrom->id) {
            continue;
        }
        imageblog_send_notification('questionposted', $userfrom, $userto, $a, $imageblog, $cm, $url);
    }
}

/**
 * Notify the asker that their question has been answered.
 *
 * @param stdClass $imageblog the instance record
 * @param stdClass $cm the course module record
 * @param stdClass $question the question record
 * @param stdClass $userfrom the teacher who answered
 * @return void
 */
function imageblog_notify_question_answered($imageblog, $cm, $question, $userfrom) {
    $userto = \core_user::get_user($question->userid);
    if (!$userto || $userto->deleted) {
        return;
    }

    $a = imageblog_notification_data($imageblog, $cm);
    $url = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
    imageblog_send_notification('questionanswered', $userfrom, $userto, $a, $imageblog, $cm, $url);
}

/**
 * Build the placeholder data shared by the notification strings.
 *
 * @param stdClass $imageblog the instance record
 * @param stdClass $cm the course module record
 * @return stdClass an object with name and course
 */
function imageblog_notification_data($imageblog, $cm) {
    $course = get_course($cm->course);
    return (object) [
        'name' => format_string($imageblog->name),
        'course' => format_string($course->fullname),
    ];
}
