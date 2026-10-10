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
 * Restore structure step for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete diagnosis structure for restore.
 */
class restore_diagnosis_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure to restore.
     *
     * @return array the restore paths wrapped in the standard activity structure
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('diagnosis', '/activity/diagnosis');
        if ($userinfo) {
            $paths[] = new restore_path_element('diagnosis_submission', '/activity/diagnosis/submissions/submission');
            $paths[] = new restore_path_element('diagnosis_question', '/activity/diagnosis/questions/question');
        }
        $paths[] = new restore_path_element('diagnosis_tag', '/activity/diagnosis/casetags/tag');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore one diagnosis instance.
     *
     * @param array $data the parsed instance data
     * @return void
     */
    protected function process_diagnosis($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('diagnosis', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one submitted diagnosis.
     *
     * @param array $data the parsed submission data
     * @return void
     */
    protected function process_diagnosis_submission($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->diagnosisid = $this->get_new_parentid('diagnosis');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newid = $DB->insert_record('diagnosis_submissions', $data);

        // The submission id is the grading item id for the "submissions" area, so
        // map it for the automatic advanced-grading (e.g. rubric) restore.
        $this->set_mapping(\restore_gradingform_plugin::itemid_mapping('submissions'), $oldid, $newid);
    }

    /**
     * Restore one question and its answer.
     *
     * @param array $data the parsed question data
     * @return void
     */
    protected function process_diagnosis_question($data) {
        global $DB;

        $data = (object) $data;
        $data->diagnosisid = $this->get_new_parentid('diagnosis');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // The answeredby field is 0 while unanswered; only remap a real user reference.
        $data->answeredby = empty($data->answeredby) ? 0 : (int) $this->get_mappingid('user', $data->answeredby);

        $DB->insert_record('diagnosis_questions', $data);
    }

    /**
     * Restore one case tag by re-tagging the restored instance.
     *
     * @param array $data the parsed tag data
     * @return void
     */
    protected function process_diagnosis_tag($data) {
        $data = (object) $data;

        if (!core_tag_tag::is_enabled('mod_diagnosis', 'diagnosis')) {
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_diagnosis', 'diagnosis', $this->task->get_activityid(), $context, $data->rawname);
    }

    /**
     * Post-restore actions: restore the intro, panorama and 3D model file areas.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_diagnosis', 'intro', null);
        $this->add_related_files('mod_diagnosis', 'panorama', null);
        $this->add_related_files('mod_diagnosis', 'model', null);
    }
}
