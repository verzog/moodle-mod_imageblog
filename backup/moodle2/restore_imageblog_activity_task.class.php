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
 * Restore task for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/imageblog/backup/moodle2/restore_imageblog_stepslib.php');

/**
 * Provides the steps to perform one complete restore of a mod_imageblog instance.
 */
class restore_imageblog_activity_task extends restore_activity_task {
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
        $this->add_step(new restore_imageblog_activity_structure_step('imageblog_structure', 'imageblog.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array the restore_decode_content items
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('imageblog', ['intro', 'casequestion', 'revealtext'], 'imageblog');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array the restore_decode_rule items
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('IMAGEBLOGVIEWBYID', '/mod/imageblog/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('IMAGEBLOGINDEX', '/mod/imageblog/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the link restore.
     *
     * @return array the restore_log_rule items
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
