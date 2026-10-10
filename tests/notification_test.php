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

/**
 * Tests for the activity's message notifications.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \diagnosis_notify_outcome_revealed
 * @covers     \diagnosis_notify_question_answered
 */
final class notification_test extends \advanced_testcase {
    /**
     * Revealing the outcome notifies each reader who submitted a diagnosis.
     */
    public function test_outcome_revealed_notifies_submitters(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('diagnosis', $diagnosis->id);
        $context = \context_module::instance($cm->id);

        $now = time();
        $DB->insert_record('diagnosis_submissions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sink = $this->redirectMessages();
        diagnosis_notify_outcome_revealed($diagnosis, $cm, $context, $teacher);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals('outcomerevealed', $messages[0]->eventtype);
        $this->assertEquals((int) $student->id, (int) $messages[0]->useridto);
        $this->assertEquals('mod_diagnosis', $messages[0]->component);
    }

    /**
     * Answering a question notifies the reader who asked it.
     */
    public function test_question_answered_notifies_asker(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('diagnosis', $diagnosis->id);

        $now = time();
        $questionid = $DB->insert_record('diagnosis_questions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'question' => 'Is it calcified?',
            'answer' => 'Yes.',
            'answeredby' => $teacher->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeanswered' => $now,
        ]);
        $question = $DB->get_record('diagnosis_questions', ['id' => $questionid], '*', MUST_EXIST);

        $sink = $this->redirectMessages();
        diagnosis_notify_question_answered($diagnosis, $cm, $question, $teacher);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals('questionanswered', $messages[0]->eventtype);
        $this->assertEquals((int) $student->id, (int) $messages[0]->useridto);
    }
}
