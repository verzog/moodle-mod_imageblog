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

namespace mod_diagnosis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/diagnosis/lib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Tests for grading: teacher marking of submissions and the gradebook push.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::diagnosis_grading_active
 * @covers     ::diagnosis_get_user_grades
 * @covers     ::diagnosis_update_grades
 * @covers     \mod_diagnosis\grades\gradeitems
 */
final class grading_test extends \advanced_testcase {
    /**
     * Create a course, instance and an enrolled student with a submission.
     *
     * @return array [stdClass $diagnosis, \context_module $context, int $studentid]
     */
    protected function make_case(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();
        $DB->insert_record('diagnosis_submissions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [$diagnosis, $context, (int) $student->id];
    }

    /**
     * The activity advertises advanced grading support.
     */
    public function test_supports_advanced_grading(): void {
        $this->assertTrue(diagnosis_supports(FEATURE_ADVANCED_GRADING));
    }

    /**
     * The single grade item maps to the advanced-gradable "submissions" area.
     */
    public function test_gradeitems_mapping(): void {
        $this->assertSame(
            [0 => 'submissions'],
            \mod_diagnosis\grades\gradeitems::get_itemname_mapping_for_component()
        );
        $this->assertSame(
            ['submissions'],
            \mod_diagnosis\grades\gradeitems::get_advancedgrading_itemnames()
        );
    }

    /**
     * With no method selected the activity uses simple direct grading, so the
     * helper reports no advanced grading controller.
     */
    public function test_grading_active_null_without_method(): void {
        $this->resetAfterTest();

        [$diagnosis] = $this->make_case();
        $this->assertNull(diagnosis_grading_active($diagnosis));
    }

    /**
     * Selecting a method makes the helper return that area's controller.
     */
    public function test_grading_active_returns_controller(): void {
        $this->resetAfterTest();

        [$diagnosis, $context] = $this->make_case();
        get_grading_manager($context, 'mod_diagnosis', 'submissions')->set_active_method('rubric');

        $controller = diagnosis_grading_active($diagnosis);
        $this->assertInstanceOf(\gradingform_controller::class, $controller);
    }

    /**
     * Grades are the teacher's stored marks: an unmarked submission has none,
     * and the stored points are returned verbatim once marked, independent of
     * the reveal.
     */
    public function test_get_user_grades_returns_stored_marks(): void {
        global $DB;
        $this->resetAfterTest();

        [$diagnosis, , $studentid] = $this->make_case();

        // Unmarked: no grade, whether or not the outcome is revealed.
        $this->assertSame([], diagnosis_get_user_grades($diagnosis));
        $diagnosis->revealed = 1;
        $this->assertSame([], diagnosis_get_user_grades($diagnosis));

        // Marked: the stored points come back.
        $DB->set_field('diagnosis_submissions', 'grade', 42.5, ['diagnosisid' => $diagnosis->id, 'userid' => $studentid]);
        $grades = diagnosis_get_user_grades($diagnosis, $studentid);
        $this->assertArrayHasKey($studentid, $grades);
        $this->assertEqualsWithDelta(42.5, (float) $grades[$studentid]->rawgrade, 0.001);
    }

    /**
     * With grading turned off (maximum grade 0) there are no grades at all.
     */
    public function test_get_user_grades_empty_when_grade_zero(): void {
        global $DB;
        $this->resetAfterTest();

        [$diagnosis, , $studentid] = $this->make_case();
        $DB->set_field('diagnosis_submissions', 'grade', 42.5, ['diagnosisid' => $diagnosis->id, 'userid' => $studentid]);
        $diagnosis->grade = 0;

        $this->assertSame([], diagnosis_get_user_grades($diagnosis));
    }

    /**
     * Pushing grades sends each marked submission's points to the gradebook.
     */
    public function test_update_grades_writes_marks_to_gradebook(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        [$diagnosis, , $studentid] = $this->make_case();
        $DB->set_field('diagnosis_submissions', 'grade', 30.0, ['diagnosisid' => $diagnosis->id, 'userid' => $studentid]);

        diagnosis_update_grades($diagnosis);

        $all = grade_get_grades($diagnosis->course, 'mod', 'diagnosis', $diagnosis->id, [$studentid]);
        $graderow = reset($all->items)->grades;
        $this->assertEqualsWithDelta(30.0, (float) $graderow[$studentid]->grade, 0.001);
    }
}
