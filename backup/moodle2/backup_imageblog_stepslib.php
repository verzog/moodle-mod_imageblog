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
            'timecreated', 'timemodified',
        ]);

        $diagnoses = new backup_nested_element('diagnoses');
        $diagnosis = new backup_nested_element('diagnosis', ['id'], [
            'userid', 'diagnosis', 'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $imageblog->add_child($diagnoses);
        $diagnoses->add_child($diagnosis);

        // Define the data sources.
        $imageblog->set_source_table('imageblog', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $diagnosis->set_source_table('imageblog_diagnoses', ['imageblogid' => backup::VAR_PARENTID]);
        }

        // Define id annotations: each diagnosis belongs to a user.
        $diagnosis->annotate_ids('user', 'userid');

        // Define file annotations: the activity intro may embed files.
        $imageblog->annotate_files('mod_imageblog', 'intro', null);

        return $this->prepare_activity_structure($imageblog);
    }
}
