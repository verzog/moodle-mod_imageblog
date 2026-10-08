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

namespace mod_imageblog\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Get the completion state for a defined custom rule.
     *
     * @param string $rule the rule name
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $submitted = $DB->record_exists('imageblog_diagnoses', [
            'imageblogid' => $this->cm->instance,
            'userid' => $this->userid,
        ]);

        return $submitted ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The custom completion rules this activity defines.
     *
     * @return array the rule names
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Human-readable descriptions for the custom rules.
     *
     * @return array rule name => description
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionsubmit' => get_string('completionsubmitdesc', 'mod_imageblog'),
        ];
    }

    /**
     * The order in which rules are shown and evaluated.
     *
     * @return array the ordered rule names
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionsubmit',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
