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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/imageblog/lib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Tests for advanced grading (e.g. rubric) support.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::imageblog_grading_active
 * @covers     ::imageblog_get_user_grades
 * @covers     \mod_imageblog\grades\gradeitems
 */
final class rubric_test extends \advanced_testcase {
    /**
     * Create a course, instance and an enrolled student with a diagnosis.
     *
     * @return array [stdClass $imageblog, \context_module $context, int $studentid]
     */
    protected function make_case(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_imageblog_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_imageblog');
        $imageblog = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($imageblog->cmid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();
        $DB->insert_record('imageblog_diagnoses', (object) [
            'imageblogid' => $imageblog->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$imageblog, $context, (int) $student->id];
    }

    /**
     * The activity advertises advanced grading support.
     */
    public function test_supports_advanced_grading(): void {
        $this->assertTrue(imageblog_supports(FEATURE_ADVANCED_GRADING));
    }

    /**
     * The single grade item maps to the advanced-gradable "submissions" area.
     */
    public function test_gradeitems_mapping(): void {
        $this->assertSame(
            [0 => 'submissions'],
            \mod_imageblog\grades\gradeitems::get_itemname_mapping_for_component()
        );
        $this->assertSame(
            ['submissions'],
            \mod_imageblog\grades\gradeitems::get_advancedgrading_itemnames()
        );
    }

    /**
     * No active method means engine scoring, so the helper reports none.
     */
    public function test_grading_active_null_without_method(): void {
        $this->resetAfterTest();

        [$imageblog] = $this->make_case();
        $this->assertNull(imageblog_grading_active($imageblog));
    }

    /**
     * Selecting a method makes the helper return that area's controller.
     */
    public function test_grading_active_returns_controller(): void {
        $this->resetAfterTest();

        [$imageblog, $context] = $this->make_case();
        get_grading_manager($context, 'mod_imageblog', 'submissions')->set_active_method('rubric');

        $controller = imageblog_grading_active($imageblog);
        $this->assertInstanceOf(\gradingform_controller::class, $controller);
    }

    /**
     * Without an active method, grades come from the engine and only once the
     * outcome has been revealed; a stored rubric grade is ignored.
     */
    public function test_get_user_grades_uses_engine_without_method(): void {
        $this->resetAfterTest();

        [$imageblog, , $studentid] = $this->make_case();

        // An unrevealed case has no engine grades yet.
        $this->assertSame([], imageblog_get_user_grades($imageblog));

        // Reveal it: the correct diagnosis now earns full marks from the engine.
        $imageblog->revealed = 1;
        $grades = imageblog_get_user_grades($imageblog, $studentid);
        $this->assertArrayHasKey($studentid, $grades);
        $this->assertEqualsWithDelta(100.0, (float) $grades[$studentid]->rawgrade, 0.001);
    }

    /**
     * With an active method, grades are the stored per-submission points,
     * independent of the reveal, and unmarked submissions are skipped.
     */
    public function test_get_user_grades_uses_rubric_when_active(): void {
        global $DB;
        $this->resetAfterTest();

        [$imageblog, $context, $studentid] = $this->make_case();
        get_grading_manager($context, 'mod_imageblog', 'submissions')->set_active_method('rubric');

        // Unmarked: no grade yet even though the engine would award marks on reveal.
        $imageblog->revealed = 1;
        $this->assertSame([], imageblog_get_user_grades($imageblog));

        // Mark the submission: the stored points are returned verbatim.
        $DB->set_field('imageblog_diagnoses', 'rubricgrade', 42.5, ['imageblogid' => $imageblog->id, 'userid' => $studentid]);
        $grades = imageblog_get_user_grades($imageblog, $studentid);
        $this->assertArrayHasKey($studentid, $grades);
        $this->assertEqualsWithDelta(42.5, (float) $grades[$studentid]->rawgrade, 0.001);
    }
}
