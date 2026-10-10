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
 * Backup task for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/diagnosis/backup/moodle2/backup_diagnosis_stepslib.php');

/**
 * Provides the steps to perform one complete backup of a mod_diagnosis instance.
 */
class backup_diagnosis_activity_task extends backup_activity_task {
    /**
     * Define particular settings this activity can have.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define particular steps this activity can have.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_diagnosis_activity_structure_step('diagnosis_structure', 'diagnosis.xml'));
    }

    /**
     * Code the transformations to perform in the activity in order to get transportable (encoded) links.
     *
     * @param string $content the content to encode
     * @return string the content with the activity's links encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of diagnoses in a course: index.php?id=COURSEID.
        $search = '/(' . $base . '\/mod\/diagnosis\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@DIAGNOSISINDEX*$2@$', $content);

        // Link to one diagnosis: view.php?id=CMID.
        $search = '/(' . $base . '\/mod\/diagnosis\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@DIAGNOSISVIEWBYID*$2@$', $content);

        return $content;
    }
}
