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
 * Backup structure step for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete image blog structure for backup, with file and id annotations.
 */
class backup_imageblog_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure of the resulting activity backup.
     *
     * @return backup_nested_element the activity element wrapped in the standard structure
     */
    protected function define_structure() {
        // Diagnoses are per-user data, so only back them up when user info is included.
        $userinfo = $this->get_setting_value('userinfo');

        $imageblog = new backup_nested_element('imageblog', ['id'], [
            'name', 'intro', 'introformat', 'casequestion', 'correctdiagnosis',
            'revealtext', 'revealed', 'grade', 'casedifficulty', 'difficultyscale',
            'participationfactor', 'correctfactor', 'bestfactor', 'bestdiagnosisid',
            'completionsubmit', 'timecreated', 'timemodified',
        ]);

        $diagnoses = new backup_nested_element('diagnoses');
        $diagnosis = new backup_nested_element('diagnosis', ['id'], [
            'userid', 'diagnosis', 'timecreated', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'userid', 'question', 'answer', 'answeredby', 'timecreated', 'timemodified', 'timeanswered',
        ]);

        $tags = new backup_nested_element('casetags');
        $tag = new backup_nested_element('tag', ['id'], ['itemid', 'rawname']);

        // Build the tree.
        $imageblog->add_child($diagnoses);
        $diagnoses->add_child($diagnosis);
        $imageblog->add_child($questions);
        $questions->add_child($question);
        $imageblog->add_child($tags);
        $tags->add_child($tag);

        // Define the data sources.
        $imageblog->set_source_table('imageblog', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $diagnosis->set_source_table('imageblog_diagnoses', ['imageblogid' => backup::VAR_PARENTID]);
            $question->set_source_table('imageblog_questions', ['imageblogid' => backup::VAR_PARENTID]);
        }

        // Case tags are instance content, so back them up regardless of user info.
        if (core_tag_tag::is_enabled('mod_imageblog', 'imageblog')) {
            $tag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                    FROM {tag} t
                                    JOIN {tag_instance} ti ON ti.tagid = t.id
                                   WHERE ti.itemtype = ?
                                     AND ti.component = ?
                                     AND ti.contextid = ?', [
                backup_helper::is_sqlparam('imageblog'),
                backup_helper::is_sqlparam('mod_imageblog'),
                backup::VAR_CONTEXTID,
            ]);
        }

        // Define id annotations: each diagnosis belongs to a user, and a question
        // belongs to its asker and (once answered) the teacher who answered it.
        $diagnosis->annotate_ids('user', 'userid');
        $question->annotate_ids('user', 'userid');
        $question->annotate_ids('user', 'answeredby');

        // Define file annotations: the activity intro may embed files, and the
        // case may carry an optional 360 degree panorama image (itemid 0).
        $imageblog->annotate_files('mod_imageblog', 'intro', null);
        $imageblog->annotate_files('mod_imageblog', 'panorama', null);

        return $this->prepare_activity_structure($imageblog);
    }
}
