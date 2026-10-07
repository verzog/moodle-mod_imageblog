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
 * The activity stores one diagnosis per user per instance; this provider
 * exports and deletes that data.
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
            'timecreated' => 'privacy:metadata:imageblog_diagnoses:timecreated',
            'timemodified' => 'privacy:metadata:imageblog_diagnoses:timemodified',
        ], 'privacy:metadata:imageblog_diagnoses');

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
            $record = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
            if (!$record) {
                continue;
            }
            $bestid = $DB->get_field('imageblog', 'bestdiagnosisid', ['id' => $cm->instance]);
            $data = (object) [
                'diagnosis' => $record->diagnosis,
                'markedbest' => transform::yesno(!empty($bestid) && (int) $bestid === (int) $record->id),
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
            writer::with_context($context)->export_data([], $data);
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
        $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $cm->instance]);
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
            $DB->delete_records('imageblog_diagnoses', ['imageblogid' => $cm->instance, 'userid' => $user->id]);
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
        $DB->delete_records_select('imageblog_diagnoses', "imageblogid = :imageblogid AND userid {$insql}", $params);
        self::clear_dangling_best($cm->instance);
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
