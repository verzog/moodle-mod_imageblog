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
 * Library of interface functions and constants for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declare which optional features this activity supports.
 *
 * @param string $feature one of the FEATURE_xx constants
 * @return mixed true/false for a known feature, null for an unknown one
 */
function diagnosis_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_ADVANCED_GRADING:
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
 * Create a new diagnosis instance.
 *
 * @param stdClass $data submitted form data (with coursemodule set)
 * @param mod_diagnosis_mod_form|null $mform the form instance, if any
 * @return int the id of the newly created instance
 */
function diagnosis_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->completionsubmit = empty($data->completionsubmit) ? 0 : 1;
    $data->id = $DB->insert_record('diagnosis', $data);

    diagnosis_grade_item_update($data);

    $context = context_module::instance($data->coursemodule);
    if (isset($data->casetags)) {
        core_tag_tag::set_item_tags('mod_diagnosis', 'diagnosis', $data->id, $context, $data->casetags);
    }
    diagnosis_save_panorama($data, $context);
    diagnosis_save_model($data, $context);

    return $data->id;
}

/**
 * Update an existing diagnosis instance.
 *
 * @param stdClass $data submitted form data (with instance set)
 * @param mod_diagnosis_mod_form|null $mform the form instance, if any
 * @return bool true on success
 */
function diagnosis_update_instance($data, $mform = null) {
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
    $DB->update_record('diagnosis', $data);

    // Reload the full record: the form data omits fields that are not form
    // elements (such as "revealed").
    $diagnosis = $DB->get_record('diagnosis', ['id' => $data->id], '*', MUST_EXIST);
    diagnosis_grade_item_update($diagnosis);
    diagnosis_update_grades($diagnosis);

    $context = context_module::instance($data->coursemodule);
    if (isset($data->casetags)) {
        core_tag_tag::set_item_tags('mod_diagnosis', 'diagnosis', $data->id, $context, $data->casetags);
    }
    diagnosis_save_panorama($data, $context);
    diagnosis_save_model($data, $context);

    return true;
}

/**
 * Delete an diagnosis instance and its associated data.
 *
 * @param int $id the instance id
 * @return bool true on success
 */
function diagnosis_delete_instance($id) {
    global $DB;

    $diagnosis = $DB->get_record('diagnosis', ['id' => $id]);
    if (!$diagnosis) {
        return false;
    }

    core_tag_tag::remove_all_item_tags('mod_diagnosis', 'diagnosis', $diagnosis->id);

    $DB->delete_records('diagnosis_questions', ['diagnosisid' => $diagnosis->id]);
    $DB->delete_records('diagnosis_submissions', ['diagnosisid' => $diagnosis->id]);
    $DB->delete_records('diagnosis', ['id' => $diagnosis->id]);

    diagnosis_grade_item_delete($diagnosis);

    return true;
}

/**
 * Provide course-module info, including the custom completion rules in use.
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|false the course-module info, or false if the instance is missing
 */
function diagnosis_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit';
    $diagnosis = $DB->get_record('diagnosis', ['id' => $coursemodule->instance], $fields);
    if (!$diagnosis) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $diagnosis->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('diagnosis', $diagnosis, $coursemodule->id, false);
    }

    // Expose the custom completion rule so the completion API treats it as available.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = $diagnosis->completionsubmit;
    }

    return $info;
}

/**
 * Create or update the grade item for an diagnosis instance.
 *
 * @param stdClass $diagnosis the instance record (must include course, id, name, grade)
 * @param array|object|string $grades raw grades to push, or 'reset' to reset the item
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED and friends
 */
function diagnosis_grade_item_update($diagnosis, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($diagnosis->name, PARAM_NOTAGS),
    ];

    if (isset($diagnosis->grade) && $diagnosis->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $diagnosis->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/diagnosis', $diagnosis->course, 'mod', 'diagnosis', $diagnosis->id, 0, $grades, $item);
}

/**
 * Delete the grade item for an diagnosis instance.
 *
 * @param stdClass $diagnosis the instance record
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED and friends
 */
function diagnosis_grade_item_delete($diagnosis) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/diagnosis', $diagnosis->course, 'mod', 'diagnosis', $diagnosis->id, 0, null, ['deleted' => 1]);
}

/**
 * The advanced grading controller active on an instance's "submissions" area,
 * or null when the activity uses simple direct grading.
 *
 * A controller is returned as soon as the teacher selects an advanced grading
 * method (e.g. a rubric), even before the rubric itself is defined; callers that
 * render the marking form must still check the controller's is_form_available().
 *
 * @param stdClass $diagnosis the instance record (needs id and course)
 * @return \gradingform_controller|null the active controller, or null for simple grading
 */
function diagnosis_grading_active($diagnosis) {
    global $CFG;
    require_once($CFG->dirroot . '/grade/grading/lib.php');

    $courseid = $diagnosis->course ?? 0;
    $cm = get_coursemodule_from_instance('diagnosis', $diagnosis->id, $courseid, false, IGNORE_MISSING);
    if (!$cm) {
        return null;
    }
    $context = context_module::instance($cm->id);
    $gradingmanager = get_grading_manager($context, 'mod_diagnosis', 'submissions');
    if ($method = $gradingmanager->get_active_method()) {
        return $gradingmanager->get_controller($method);
    }
    return null;
}

/**
 * Compute the grades a set of users have earned on a diagnosis instance.
 *
 * Grades come from the teacher's marking of each submitted diagnosis (simple
 * direct grading or an advanced method such as a rubric), stored as points on
 * the submission. A submission the teacher has not yet marked has no grade.
 *
 * @param stdClass $diagnosis the instance record
 * @param int $userid a single user to compute for, or 0 for everyone
 * @return array userid => object{userid, rawgrade}
 */
function diagnosis_get_user_grades($diagnosis, $userid = 0) {
    global $DB;

    if (empty($diagnosis->grade) || $diagnosis->grade <= 0) {
        return [];
    }

    $params = ['diagnosisid' => $diagnosis->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $grades = [];
    foreach ($DB->get_records('diagnosis_submissions', $params) as $record) {
        if ($record->grade === null) {
            continue;
        }
        $grades[$record->userid] = (object) [
            'userid' => $record->userid,
            'rawgrade' => (float) $record->grade,
        ];
    }

    return $grades;
}

/**
 * Push the current grades for an diagnosis instance into the gradebook.
 *
 * @param stdClass $diagnosis the instance record
 * @param int $userid a single user to update, or 0 for everyone
 * @param bool $nullifnone whether to store a null grade when a named user has none
 * @return void
 */
function diagnosis_update_grades($diagnosis, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (empty($diagnosis->grade) || $diagnosis->grade <= 0) {
        diagnosis_grade_item_update($diagnosis);
        return;
    }

    $grades = diagnosis_get_user_grades($diagnosis, $userid);
    if ($grades) {
        diagnosis_grade_item_update($diagnosis, $grades);
    } else if ($userid && $nullifnone) {
        diagnosis_grade_item_update($diagnosis, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        diagnosis_grade_item_update($diagnosis);
    }
}

/**
 * Serve files from the activity's intro file area.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the module context
 * @param string $filearea the name of the file area
 * @param array $args the remaining path arguments (itemid is implicitly 0 for intro and panorama)
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting file serving
 * @return bool false if the file was not found; otherwise the file is sent and execution stops
 */
function diagnosis_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    require_capability('mod/diagnosis:view', $context);

    // These areas each hold a single file at itemid 0 (the intro, the optional
    // 360 degree panorama and the optional 3D model), so their URLs carry no
    // itemid segment.
    if ($filearea !== 'intro' && $filearea !== 'panorama' && $filearea !== 'model') {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_diagnosis', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Filemanager/draft-area options for the 360 degree panorama image. The limit
 * is generous because equirectangular sources are typically high resolution.
 *
 * @return array the options array for file_prepare_draft_area/file_save_draft_area_files
 */
function diagnosis_panorama_filemanager_options() {
    return [
        'maxbytes' => 20 * 1024 * 1024,
        'accepted_types' => ['.jpg', '.jpeg', '.png'],
        'maxfiles' => 1,
        'subdirs' => 0,
    ];
}

/**
 * URL of the instance's 360 degree panorama image, or null if none is set.
 *
 * The file lives at itemid 0 in the module context, so the URL is built
 * without an itemid segment (as for the activity intro).
 *
 * @param context $context the module context
 * @return moodle_url|null the pluginfile URL, or null when no panorama exists
 */
function diagnosis_get_panorama_url($context) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_diagnosis', 'panorama', 0, 'itemid, filepath, filename', false);
    if (!$files) {
        return null;
    }
    $file = reset($files);
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        null,
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Persist the submitted panorama image for an instance.
 *
 * When the panorama toggle is off the area is cleared, so unchecking it removes
 * a previously uploaded image; otherwise the draft files are saved into the
 * module context at itemid 0.
 *
 * @param stdClass $data submitted form data (with panorama_image draft id and coursemodule set)
 * @param context $context the module context
 * @return void
 */
function diagnosis_save_panorama($data, $context) {
    if (!isset($data->panorama_image)) {
        return;
    }
    if (empty($data->haspanorama)) {
        get_file_storage()->delete_area_files($context->id, 'mod_diagnosis', 'panorama', 0);
        return;
    }
    file_save_draft_area_files(
        $data->panorama_image,
        $context->id,
        'mod_diagnosis',
        'panorama',
        0,
        diagnosis_panorama_filemanager_options()
    );
}

/**
 * The 3D model file extensions the viewer can open as a main model.
 *
 * @return string[] accepted extensions, each with a leading dot
 */
function diagnosis_model_extensions() {
    return ['.glb', '.gltf', '.stl', '.ply', '.obj'];
}

/**
 * Companion file extensions a model may depend on (glTF buffers/textures and
 * OBJ material libraries), uploaded alongside the main model file.
 *
 * @return string[] accepted companion extensions, each with a leading dot
 */
function diagnosis_model_companion_extensions() {
    return ['.bin', '.mtl', '.png', '.jpg', '.jpeg', '.webp'];
}

/**
 * Filemanager/draft-area options for the 3D model files. Several files and
 * subdirectories are allowed so a glTF or OBJ model can be uploaded together
 * with its companion buffers, materials and textures; the per-file size limit
 * is generous because meshes and scanned point clouds are often large.
 *
 * @return array the options array for file_prepare_draft_area/file_save_draft_area_files
 */
function diagnosis_model_filemanager_options() {
    // The model and companion extensions (.glb, .gltf, .stl, .ply, .obj, .bin,
    // .mtl, ...) are not in Moodle's file-type registry, so listing them in
    // accepted_types makes the file picker silently drop the unknown ones and
    // reject the upload. Accept any type here and enforce the real allow-list
    // server-side in the form's validation(), where the match is by extension
    // and does not depend on the registry.
    return [
        'maxbytes' => 50 * 1024 * 1024,
        'accepted_types' => '*',
        'maxfiles' => -1,
        'subdirs' => 1,
    ];
}

/**
 * Whether a file name's extension is an accepted 3D model or companion file.
 *
 * @param string $filename the file name to check
 * @return bool true if the extension is a recognised model or companion type
 */
function diagnosis_model_file_accepted($filename) {
    $allowed = array_map(
        fn($ext) => ltrim($ext, '.'),
        array_merge(diagnosis_model_extensions(), diagnosis_model_companion_extensions())
    );
    $ext = core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true);
}

/**
 * The main model file for an instance: the first stored file (ordered by path
 * then name) whose extension is a recognised model format. Companion files such
 * as .bin buffers or textures are ignored when choosing it.
 *
 * @param context $context the module context
 * @return stored_file|null the main model file, or null when none is stored
 */
function diagnosis_get_model_mainfile($context) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_diagnosis', 'model', 0, 'filepath, filename', false);
    $modelexts = array_map(fn($ext) => ltrim($ext, '.'), diagnosis_model_extensions());
    foreach ($files as $file) {
        $ext = core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (in_array($ext, $modelexts, true)) {
            return $file;
        }
    }
    return null;
}

/**
 * URL of a stored model-area file, built without an itemid segment (as for the
 * activity intro) so companion files resolve relative to the main model URL.
 *
 * @param stored_file $file a file stored in the model area
 * @return moodle_url the pluginfile URL for the file
 */
function diagnosis_model_file_url($file) {
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        null,
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * URL of the instance's main 3D model file, or null if none is set.
 *
 * @param context $context the module context
 * @return moodle_url|null the pluginfile URL, or null when no model exists
 */
function diagnosis_get_model_url($context) {
    $file = diagnosis_get_model_mainfile($context);
    return $file ? diagnosis_model_file_url($file) : null;
}

/**
 * URLs of every companion file with the given extension (e.g. an OBJ's .mtl
 * material libraries), ordered by path then name.
 *
 * @param context $context the module context
 * @param string $extension the companion extension to find, without a leading dot
 * @return moodle_url[] the pluginfile URLs, empty when no such file is stored
 */
function diagnosis_get_model_companion_urls($context, $extension) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_diagnosis', 'model', 0, 'filepath, filename', false);
    $extension = core_text::strtolower($extension);
    $urls = [];
    foreach ($files as $file) {
        if (core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === $extension) {
            $urls[] = diagnosis_model_file_url($file);
        }
    }
    return $urls;
}

/**
 * The viewer format key for a model file name, from its extension.
 *
 * glTF and GLB share the same loader, so both map to 'gltf'; an unknown
 * extension returns the empty string.
 *
 * @param string $filename the stored model file name
 * @return string one of stl, ply, obj, gltf, or '' when unrecognised
 */
function diagnosis_model_format($filename) {
    $ext = core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'glb':
        case 'gltf':
            return 'gltf';
        case 'stl':
            return 'stl';
        case 'ply':
            return 'ply';
        case 'obj':
            return 'obj';
        default:
            return '';
    }
}

/**
 * Persist the submitted 3D model files for an instance.
 *
 * When the model toggle is off the area is cleared, so unchecking it removes a
 * previously uploaded model and its companions; otherwise the draft files are
 * saved into the module context at itemid 0.
 *
 * @param stdClass $data submitted form data (with model_file draft id and coursemodule set)
 * @param context $context the module context
 * @return void
 */
function diagnosis_save_model($data, $context) {
    if (!isset($data->model_file)) {
        return;
    }
    if (empty($data->hasmodel)) {
        get_file_storage()->delete_area_files($context->id, 'mod_diagnosis', 'model', 0);
        return;
    }
    file_save_draft_area_files(
        $data->model_file,
        $context->id,
        'mod_diagnosis',
        'model',
        0,
        diagnosis_model_filemanager_options()
    );
}

/**
 * Build the tag index for diagnosis cases carrying a given tag.
 *
 * @param core_tag_tag $tag the tag being viewed
 * @param bool $exclusivemode whether only this component/itemtype is shown
 * @param int $fromcontextid the context the tag page was reached from, or 0
 * @param int $contextid the context to restrict the results to, or 0 for the whole site
 * @param bool $recursivecontext whether to include child contexts of $contextid
 * @param int $page the zero-based page number
 * @return \core_tag\output\tagindex the rendered tag index
 */
function mod_diagnosis_get_tagged_cases(
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
                FROM {diagnosis} i
                JOIN {modules} m ON m.name = 'diagnosis'
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
        'itemtype' => 'diagnosis',
        'tagid' => $tag->id,
        'component' => 'mod_diagnosis',
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

    $builder = new core_tag_index_builder('mod_diagnosis', 'diagnosis', $query, $params, $page * $perpage, $perpage + 1);

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
        $pageurl = new moodle_url('/mod/diagnosis/view.php', ['id' => $item->cmid]);
        $pagename = html_writer::link($pageurl, format_string($item->name, true, ['context' => $cm->context]));
        $courseurl = course_get_url($item->courseid, $cm->sectionnum);
        $coursename = html_writer::link($courseurl, format_string($item->fullname, true, ['context' => $cm->context]));
        $icon = html_writer::link($pageurl, $OUTPUT->pix_icon('monologo', '', 'mod_diagnosis'));
        $tagfeed->add($icon, $pagename, $coursename);
    }

    $content = $OUTPUT->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($OUTPUT));

    return new core_tag\output\tagindex(
        $tag,
        'mod_diagnosis',
        'diagnosis',
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
 * Send one diagnosis notification.
 *
 * @param string $name the message provider name
 * @param stdClass $userfrom the sending user
 * @param stdClass $userto the receiving user
 * @param stdClass $a the subject/body placeholder data (name, course)
 * @param stdClass $diagnosis the instance record
 * @param stdClass $cm the course module record
 * @param moodle_url $url the activity view url
 * @param string|null $bodykey the body string key, or null to derive it from $name
 * @return mixed the message id, or false on failure
 */
function diagnosis_send_notification($name, $userfrom, $userto, $a, $diagnosis, $cm, moodle_url $url, $bodykey = null) {
    $bodykey = $bodykey ?? ('messagebody_' . $name);

    // Render the strings in the recipient's language: the Message API stores the
    // text as supplied rather than translating it when it is displayed.
    $sm = get_string_manager();
    $subject = $sm->get_string('messagesubject_' . $name, 'mod_diagnosis', $a, $userto->lang);
    $body = $sm->get_string($bodykey, 'mod_diagnosis', $a, $userto->lang);

    $message = new \core\message\message();
    $message->component = 'mod_diagnosis';
    $message->name = $name;
    $message->userfrom = $userfrom;
    $message->userto = $userto;
    $message->subject = $subject;
    $message->fullmessage = $body;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = html_writer::tag('p', $body);
    $message->smallmessage = $body;
    $message->notification = 1;
    $message->courseid = $cm->course;
    $message->contexturl = $url->out(false);
    $message->contexturlname = format_string($diagnosis->name);

    return message_send($message);
}

/**
 * Notify everyone who submitted a diagnosis that the case outcome has been revealed.
 *
 * @param stdClass $diagnosis the instance record
 * @param stdClass $cm the course module record
 * @param context $context the module context
 * @param stdClass $userfrom the teacher revealing the outcome
 * @return void
 */
function diagnosis_notify_outcome_revealed($diagnosis, $cm, $context, $userfrom) {
    global $DB;

    $recipients = $DB->get_records('diagnosis_submissions', ['diagnosisid' => $diagnosis->id], '', 'id, userid');
    if (!$recipients) {
        return;
    }

    $a = diagnosis_notification_data($diagnosis, $cm);
    $url = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id]);
    // The reveal shows the outcome; grades come from the teacher's separate
    // marking, so the message does not claim the submission has been graded.
    foreach ($recipients as $recipient) {
        $userto = \core_user::get_user($recipient->userid);
        if (!$userto || $userto->deleted) {
            continue;
        }
        diagnosis_send_notification('outcomerevealed', $userfrom, $userto, $a, $diagnosis, $cm, $url);
    }
}

/**
 * Notify teachers who can answer that a reader posted a question.
 *
 * @param stdClass $diagnosis the instance record
 * @param stdClass $cm the course module record
 * @param context $context the module context
 * @param stdClass $userfrom the reader who asked
 * @return void
 */
function diagnosis_notify_question_posted($diagnosis, $cm, $context, $userfrom) {
    // The onlyactive flag skips suspended or out-of-date enrolments, which cannot open the activity.
    $recipients = get_enrolled_users($context, 'mod/diagnosis:answerquestion', 0, 'u.*', null, 0, 0, true);
    if (!$recipients) {
        return;
    }

    $a = diagnosis_notification_data($diagnosis, $cm);
    $url = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id]);
    foreach ($recipients as $userto) {
        if ((int) $userto->id === (int) $userfrom->id) {
            continue;
        }
        diagnosis_send_notification('questionposted', $userfrom, $userto, $a, $diagnosis, $cm, $url);
    }
}

/**
 * Notify the asker that their question has been answered.
 *
 * @param stdClass $diagnosis the instance record
 * @param stdClass $cm the course module record
 * @param stdClass $question the question record
 * @param stdClass $userfrom the teacher who answered
 * @return void
 */
function diagnosis_notify_question_answered($diagnosis, $cm, $question, $userfrom) {
    $userto = \core_user::get_user($question->userid);
    if (!$userto || $userto->deleted) {
        return;
    }

    $a = diagnosis_notification_data($diagnosis, $cm);
    $url = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id]);
    diagnosis_send_notification('questionanswered', $userfrom, $userto, $a, $diagnosis, $cm, $url);
}

/**
 * Build the placeholder data shared by the notification strings.
 *
 * @param stdClass $diagnosis the instance record
 * @param stdClass $cm the course module record
 * @return stdClass an object with name and course
 */
function diagnosis_notification_data($diagnosis, $cm) {
    $course = get_course($cm->course);
    return (object) [
        'name' => format_string($diagnosis->name),
        'course' => format_string($course->fullname),
    ];
}
