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

namespace mod_imageblog\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_imageblog.
 *
 * The activity stores one diagnosis per user per instance, plus questions
 * readers ask on a case and the teacher answers; this provider exports and
 * deletes that data.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection the collection to add metadata to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('imageblog_diagnoses', [
            'userid' => 'privacy:metadata:imageblog_diagnoses:userid',
            'diagnosis' => 'privacy:metadata:imageblog_diagnoses:diagnosis',
            'rubricgrade' => 'privacy:metadata:imageblog_diagnoses:rubricgrade',
            'timecreated' => 'privacy:metadata:imageblog_diagnoses:timecreated',
            'timemodified' => 'privacy:metadata:imageblog_diagnoses:timemodified',
        ], 'privacy:metadata:imageblog_diagnoses');

        $collection->add_database_table('imageblog_questions', [
            'userid' => 'privacy:metadata:imageblog_questions:userid',
            'question' => 'privacy:metadata:imageblog_questions:question',
            'answer' => 'privacy:metadata:imageblog_questions:answer',
            'answeredby' => 'privacy:metadata:imageblog_questions:answeredby',
            'timecreated' => 'privacy:metadata:imageblog_questions:timecreated',
            'timemodified' => 'privacy:metadata:imageblog_questions:timemodified',
            'timeanswered' => 'privacy:metadata:imageblog_questions:timeanswered',
        ], 'privacy:metadata:imageblog_questions');

        return $collection;
    }

    /**
     * Get the list of module contexts that contain a given user's data.
     *
     * @param int $userid the user to search for
     * @return contextlist the contexts containing the user's data
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {imageblog_diagnoses} d
                  JOIN {imageblog} i ON i.id = d.imageblogid
                  JOIN {course_modules} cm ON cm.instance = i.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE d.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'modname' => 'imageblog',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);

        // Contexts where the user asked or answered a question.
        $sql = "SELECT ctx.id
                  FROM {imageblog_questions} q
                  JOIN {imageblog} i ON i.id = q.imageblogid
                  JOIN {course_modules} cm ON cm.instance = i.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE q.userid = :askerid OR q.answeredby = :answererid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'imageblog',
            'contextlevel' => CONTEXT_MODULE,
            'askerid' => $userid,
            'answererid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data in a given context.
     *
     * @param userlist $userlist the userlist to add users to
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT d.userid
                  FROM {imageblog_diagnoses} d
                  JOIN {imageblog} i ON i.id = d.imageblogid
                  JOIN {course_modules} cm ON cm.instance = i.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'imageblog',
            'cmid' => $context->instanceid,
        ]);

        $questionsql = "SELECT q.userid
                          FROM {imageblog_questions} q
                          JOIN {imageblog} i ON i.id = q.imageblogid
                          JOIN {course_modules} cm ON cm.instance = i.id
                          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                         WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $questionsql, [
            'modname' => 'imageblog',
            'cmid' => $context->instanceid,
        ]);

        $answersql = "SELECT q.answeredby
                        FROM {imageblog_questions} q
                        JOIN {imageblog} i ON i.id = q.imageblogid
                        JOIN {course_modules} cm ON cm.instance = i.id
                        JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       WHERE cm.id = :cmid AND q.answeredby <> 0";
        $userlist->add_from_sql('answeredby', $answersql, [
            'modname' => 'imageblog',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Export all diagnoses for the approved contexts belonging to a user.
     *
     * @param approved_contextlist $contextlist the approved contexts to export
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('imageblog', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // The user's own diagnosis, if any.
            $record = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
            if ($record) {
                $bestid = $DB->get_field('imageblog', 'bestdiagnosisid', ['id' => $cm->instance]);
                $data = [
                    'diagnosis' => $record->diagnosis,
                    'markedbest' => transform::yesno(!empty($bestid) && (int) $bestid === (int) $record->id),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
                if ($record->rubricgrade !== null) {
                    $data['rubricgrade'] = format_float((float) $record->rubricgrade, 2);
                }
                writer::with_context($context)->export_data([], (object) $data);

                // Any advanced grading (e.g. rubric) fill is keyed by the diagnosis id.
                $gradesubcontext = [get_string('gradenoun')];
                \core_grading\privacy\provider::export_item_data($context, (int) $record->id, $gradesubcontext);
            }

            self::export_questions($context, $cm->instance, (int) $user->id);
        }
    }

    /**
     * Export the questions a user asked, and the answers they authored, on one case.
     *
     * @param \context $context the module context being exported
     * @param int $imageblogid the imageblog instance id
     * @param int $userid the user being exported
     * @return void
     */
    protected static function export_questions(\context $context, int $imageblogid, int $userid): void {
        global $DB;

        $folder = get_string('questionsheading', 'mod_imageblog');

        $asked = $DB->get_records(
            'imageblog_questions',
            ['imageblogid' => $imageblogid, 'userid' => $userid],
            'timecreated ASC'
        );
        foreach ($asked as $question) {
            writer::with_context($context)->export_data([$folder, 'asked-' . $question->id], (object) [
                'question' => $question->question,
                'answer' => $question->answer,
                'answered' => transform::yesno(!empty($question->answeredby)),
                'timecreated' => transform::datetime($question->timecreated),
                'timemodified' => transform::datetime($question->timemodified),
            ]);
        }

        $answered = $DB->get_records(
            'imageblog_questions',
            ['imageblogid' => $imageblogid, 'answeredby' => $userid],
            'timeanswered ASC'
        );
        foreach ($answered as $question) {
            if ((int) $question->userid === $userid) {
                // Already exported above as the user's own question.
                continue;
            }
            writer::with_context($context)->export_data([$folder, 'answered-' . $question->id], (object) [
                'question' => $question->question,
                'answer' => $question->answer,
                'timeanswered' => transform::datetime($question->timeanswered),
            ]);
        }
    }

    /**
     * Delete all diagnoses stored in a given context.
     *
     * @param \context $context the context to delete within
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('imageblog', $context->instanceid);
        if (!$cm) {
            return;
        }
        // Remove any advanced grading (e.g. rubric) fills for the whole area first.
        \core_grading\privacy\provider::delete_instance_data($context);
        $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $cm->instance]);
        $DB->delete_records('imageblog_questions', ['imageblogid' => $cm->instance]);
        self::clear_dangling_best($cm->instance);
    }

    /**
     * Delete all diagnoses for a user across the approved contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to delete within
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('imageblog', $context->instanceid);
            if (!$cm) {
                continue;
            }
            // Remove the advanced grading (e.g. rubric) fill for this user's
            // diagnosis, keyed by the diagnosis id, before deleting the row.
            $diagnosisid = $DB->get_field('imageblog_diagnoses', 'id', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
            if ($diagnosisid) {
                \core_grading\privacy\provider::delete_instance_data($context, (int) $diagnosisid);
            }
            $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
            // Remove the questions this user asked, and strip the answers they authored on others' questions.
            $DB->delete_records('imageblog_questions', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
            self::strip_answers($cm->instance, 'answeredby = :answerer', ['answerer' => $user->id]);
            self::clear_dangling_best($cm->instance);
        }
    }

    /**
     * Delete diagnoses for a set of users within a context.
     *
     * @param approved_userlist $userlist the approved users to delete
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('imageblog', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['imageblogid' => $cm->instance], $inparams);

        // Remove the advanced grading (e.g. rubric) fills for these users'
        // diagnoses, keyed by the diagnosis id, before deleting the rows.
        $diagnosisids = $DB->get_fieldset_select(
            'imageblog_diagnoses',
            'id',
            "imageblogid = :imageblogid AND userid {$insql}",
            $params
        );
        if ($diagnosisids) {
            \core_grading\privacy\provider::delete_data_for_instances($context, array_map('intval', $diagnosisids));
        }

        $DB->delete_records_select('imageblog_diagnoses', "imageblogid = :imageblogid AND userid {$insql}", $params);
        $DB->delete_records_select('imageblog_questions', "imageblogid = :imageblogid AND userid {$insql}", $params);
        self::strip_answers($cm->instance, "answeredby {$insql}", $inparams);
        self::clear_dangling_best($cm->instance);
    }

    /**
     * Blank the answers authored by matched users, leaving the questions others asked intact.
     *
     * @param int $imageblogid the imageblog instance id
     * @param string $answerwhere a SQL fragment matching the answeredby column
     * @param array $answerparams named parameters for the fragment
     * @return void
     */
    protected static function strip_answers(int $imageblogid, string $answerwhere, array $answerparams): void {
        global $DB;

        $select = "imageblogid = :imageblogid AND {$answerwhere}";
        $params = array_merge(['imageblogid' => $imageblogid], $answerparams);

        // Clear the content first: the last step blanks answeredby, which the fragment matches on.
        $DB->set_field_select('imageblog_questions', 'answer', null, $select, $params);
        $DB->set_field_select('imageblog_questions', 'timeanswered', 0, $select, $params);
        $DB->set_field_select('imageblog_questions', 'answeredby', 0, $select, $params);
    }

    /**
     * Reset an instance's best-answer reference when the diagnosis it points at no longer exists.
     *
     * @param int $imageblogid the imageblog instance id
     * @return void
     */
    protected static function clear_dangling_best(int $imageblogid): void {
        global $DB;

        $bestid = $DB->get_field('imageblog', 'bestdiagnosisid', ['id' => $imageblogid]);
        if (!empty($bestid) && !$DB->record_exists('imageblog_diagnoses', ['id' => $bestid])) {
            $DB->set_field('imageblog', 'bestdiagnosisid', 0, ['id' => $imageblogid]);
        }
    }
}
