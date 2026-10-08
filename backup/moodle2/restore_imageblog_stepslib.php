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
 * Restore structure step for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete image blog structure for restore.
 */
class restore_imageblog_activity_structure_step extends restore_activity_structure_step {
    /** @var int Old best-diagnosis id, remapped to the restored diagnosis in after_execute(). */
    protected $bestdiagnosisid = 0;

    /**
     * Define the structure to restore.
     *
     * @return array the restore paths wrapped in the standard activity structure
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('imageblog', '/activity/imageblog');
        if ($userinfo) {
            $paths[] = new restore_path_element('imageblog_diagnosis', '/activity/imageblog/diagnoses/diagnosis');
            $paths[] = new restore_path_element('imageblog_question', '/activity/imageblog/questions/question');
        }
        $paths[] = new restore_path_element('imageblog_tag', '/activity/imageblog/casetags/tag');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore one image blog instance.
     *
     * @param array $data the parsed instance data
     * @return void
     */
    protected function process_imageblog($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // The bestdiagnosisid field references a diagnosis restored later as a
        // child element. Stash the old id and zero it for now; after_execute()
        // remaps it once the diagnoses exist and their new ids are known.
        $this->bestdiagnosisid = empty($data->bestdiagnosisid) ? 0 : (int) $data->bestdiagnosisid;
        $data->bestdiagnosisid = 0;

        $newitemid = $DB->insert_record('imageblog', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one submitted diagnosis.
     *
     * @param array $data the parsed diagnosis data
     * @return void
     */
    protected function process_imageblog_diagnosis($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->imageblogid = $this->get_new_parentid('imageblog');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newid = $DB->insert_record('imageblog_diagnoses', $data);
        $this->set_mapping('imageblog_diagnosis', $oldid, $newid);
    }

    /**
     * Restore one question and its answer.
     *
     * @param array $data the parsed question data
     * @return void
     */
    protected function process_imageblog_question($data) {
        global $DB;

        $data = (object) $data;
        $data->imageblogid = $this->get_new_parentid('imageblog');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // The answeredby field is 0 while unanswered; only remap a real user reference.
        $data->answeredby = empty($data->answeredby) ? 0 : (int) $this->get_mappingid('user', $data->answeredby);

        $DB->insert_record('imageblog_questions', $data);
    }

    /**
     * Restore one case tag by re-tagging the restored instance.
     *
     * @param array $data the parsed tag data
     * @return void
     */
    protected function process_imageblog_tag($data) {
        $data = (object) $data;

        if (!core_tag_tag::is_enabled('mod_imageblog', 'imageblog')) {
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_imageblog', 'imageblog', $this->task->get_activityid(), $context, $data->rawname);
    }

    /**
     * Post-restore actions: remap the best-answer reference and restore intro files.
     *
     * @return void
     */
    protected function after_execute() {
        global $DB;

        // Now the diagnoses exist, point bestdiagnosisid at the restored diagnosis.
        // When user info was not restored there is no mapping, so the reference
        // stays cleared, which is correct.
        if (!empty($this->bestdiagnosisid)) {
            $newbest = $this->get_mappingid('imageblog_diagnosis', $this->bestdiagnosisid);
            if ($newbest) {
                $DB->set_field('imageblog', 'bestdiagnosisid', $newbest, ['id' => $this->task->get_activityid()]);
            }
        }

        // Restore the activity intro file area.
        $this->add_related_files('mod_imageblog', 'intro', null);
    }
}
