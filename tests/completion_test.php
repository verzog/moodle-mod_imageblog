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

namespace mod_imageblog;

use cm_info;
use mod_imageblog\completion\custom_completion;

/**
 * Tests for the "submit a diagnosis" custom completion rule.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_imageblog\completion\custom_completion
 */
final class completion_test extends \advanced_testcase {
    /**
     * The completionsubmit rule is incomplete until a diagnosis exists, then complete.
     */
    public function test_completionsubmit_state_tracks_diagnosis(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        /** @var \mod_imageblog_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_imageblog');
        $imageblog = $generator->create_instance([
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
        ]);

        $cm = cm_info::create(get_coursemodule_from_instance('imageblog', $imageblog->id));

        // No diagnosis yet: the rule is incomplete.
        $completion = new custom_completion($cm, (int) $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));

        // Record a diagnosis for the student.
        $now = time();
        $DB->insert_record('imageblog_diagnoses', (object) [
            'imageblogid' => $imageblog->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // The rule is now complete for that student.
        $completion = new custom_completion($cm, (int) $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionsubmit'));
    }

    /**
     * The rule is reported among the activity's defined custom rules.
     */
    public function test_rule_is_defined(): void {
        $this->assertContains('completionsubmit', custom_completion::get_defined_custom_rules());
    }
}
