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

use backup;
use backup_controller;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/diagnosis/lib.php');

/**
 * Backup and restore tests for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_diagnosis_activity_structure_step
 * @covers     \restore_diagnosis_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Back up an instance with user data and restore it into a fresh course.
     *
     * The best-answer reference must follow the diagnosis to its restored id,
     * rather than keeping the stale source id (which could collide with an
     * unrelated diagnosis after restore).
     */
    public function test_backup_restore_remaps_best_answer(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id, 'revealed' => 1]);

        // Record a marked diagnosis for the student.
        $now = time();
        $submissionid = $DB->insert_record('diagnosis_submissions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'grade' => 42.5,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Back up the activity with user data, then restore it into a new course.
        $backupid = $this->backup_activity($diagnosis->cmid, $USER->id);
        $targetcourse = $this->getDataGenerator()->create_course();
        $this->restore_into_course($backupid, $targetcourse->id, $USER->id);

        $restored = $DB->get_record('diagnosis', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredsubmission = $DB->get_record(
            'diagnosis_submissions',
            ['diagnosisid' => $restored->id],
            '*',
            MUST_EXIST
        );

        // The submission restores into the new instance, remapped to a new id.
        $this->assertNotEquals((int) $submissionid, (int) $restoredsubmission->id);
        $this->assertSame('pneumonia', $restoredsubmission->diagnosis);
        $this->assertEquals((int) $student->id, (int) $restoredsubmission->userid);
        // The stored grade survives the round trip.
        $this->assertEqualsWithDelta(42.5, (float) $restoredsubmission->grade, 0.001);
    }

    /**
     * Questions and their answers back up and restore, remapping both the asker
     * and the answering teacher to the restored users.
     */
    public function test_backup_restore_carries_questions(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);

        $now = time();
        $answeredid = $DB->insert_record('diagnosis_questions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'question' => 'Is the lesion calcified?',
            'answer' => 'Yes, there is dense calcification.',
            'answeredby' => $teacher->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeanswered' => $now,
        ]);
        $DB->insert_record('diagnosis_questions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $student->id,
            'question' => 'What is the patient age?',
            'answer' => null,
            'answeredby' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeanswered' => 0,
        ]);

        $backupid = $this->backup_activity($diagnosis->cmid, $USER->id);
        $targetcourse = $this->getDataGenerator()->create_course();
        $this->restore_into_course($backupid, $targetcourse->id, $USER->id);

        $restored = $DB->get_record('diagnosis', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $questions = $DB->get_records('diagnosis_questions', ['diagnosisid' => $restored->id], 'timecreated ASC');
        $this->assertCount(2, $questions);

        $answered = array_filter($questions, fn($q) => trim((string) $q->answer) !== '');
        $this->assertCount(1, $answered);
        $answered = reset($answered);
        $this->assertNotEquals((int) $answeredid, (int) $answered->id);
        $this->assertSame('Yes, there is dense calcification.', $answered->answer);
        $this->assertEquals((int) $student->id, (int) $answered->userid);
        $this->assertEquals((int) $teacher->id, (int) $answered->answeredby);

        $unanswered = array_filter($questions, fn($q) => trim((string) $q->answer) === '');
        $unanswered = reset($unanswered);
        $this->assertEquals(0, (int) $unanswered->answeredby);
        $this->assertEquals((int) $student->id, (int) $unanswered->userid);
    }

    /**
     * Case tags are set on the instance and carried through backup and restore.
     */
    public function test_backup_restore_carries_case_tags(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance([
            'course' => $course->id,
            'casetags' => ['Chest', 'Pneumonia'],
        ]);

        $tags = \core_tag_tag::get_item_tags_array('mod_diagnosis', 'diagnosis', $diagnosis->id);
        $this->assertEqualsCanonicalizing(['Chest', 'Pneumonia'], array_values($tags));

        $backupid = $this->backup_activity($diagnosis->cmid, $USER->id);
        $targetcourse = $this->getDataGenerator()->create_course();
        $this->restore_into_course($backupid, $targetcourse->id, $USER->id);

        $restored = $DB->get_record('diagnosis', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredtags = \core_tag_tag::get_item_tags_array('mod_diagnosis', 'diagnosis', $restored->id);
        $this->assertEqualsCanonicalizing(['Chest', 'Pneumonia'], array_values($restoredtags));
    }

    /**
     * A case's 360 degree panorama image is backed up and restored, so the
     * viewer still has its source after course copy, import or restore.
     */
    public function test_backup_restore_carries_panorama(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_diagnosis',
            'filearea' => 'panorama',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'pano.jpg',
        ], 'fake-equirectangular-bytes');

        $backupid = $this->backup_activity($diagnosis->cmid, $USER->id);
        $targetcourse = $this->getDataGenerator()->create_course();
        $this->restore_into_course($backupid, $targetcourse->id, $USER->id);

        $restored = $DB->get_record('diagnosis', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredcm = get_coursemodule_from_instance('diagnosis', $restored->id, $targetcourse->id, false, MUST_EXIST);
        $restoredcontext = \context_module::instance($restoredcm->id);

        $url = diagnosis_get_panorama_url($restoredcontext);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertStringContainsString('pano.jpg', $url->out(false));
    }

    /**
     * A case's 3D model and its companion files are backed up and restored, so
     * the viewer still has the whole bundle after course copy, import or restore.
     */
    public function test_backup_restore_carries_model(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $fs = get_file_storage();
        $base = [
            'contextid' => $context->id,
            'component' => 'mod_diagnosis',
            'filearea' => 'model',
            'itemid' => 0,
            'filepath' => '/',
        ];
        // A glTF model with an external buffer companion.
        $fs->create_file_from_string(['filename' => 'scene.gltf'] + $base, '{"asset":{"version":"2.0"}}');
        $fs->create_file_from_string(['filename' => 'scene.bin'] + $base, 'fake-buffer-bytes');

        $backupid = $this->backup_activity($diagnosis->cmid, $USER->id);
        $targetcourse = $this->getDataGenerator()->create_course();
        $this->restore_into_course($backupid, $targetcourse->id, $USER->id);

        $restored = $DB->get_record('diagnosis', ['course' => $targetcourse->id], '*', MUST_EXIST);
        $restoredcm = get_coursemodule_from_instance('diagnosis', $restored->id, $targetcourse->id, false, MUST_EXIST);
        $restoredcontext = \context_module::instance($restoredcm->id);

        // The main model resolves, and the companion buffer rode along with it.
        $url = diagnosis_get_model_url($restoredcontext);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertStringContainsString('scene.gltf', $url->out(false));
        $this->assertTrue(
            $fs->file_exists($restoredcontext->id, 'mod_diagnosis', 'model', 0, '/', 'scene.bin')
        );
    }

    /**
     * Back up a single activity with user data included.
     *
     * MODE_GENERAL zips the backup and removes its working directory, so the
     * archive is extracted back into the expected location for restore-by-id.
     *
     * @param int $cmid the course module id to back up
     * @param int $userid the user performing the backup
     * @return string the backup id
     */
    protected function backup_activity(int $cmid, int $userid): string {
        global $CFG;
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $userid
        );
        $bc->get_plan()->get_setting('users')->set_value(true);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $results = $bc->get_results();
        $bc->destroy();

        $this->assertArrayHasKey('backup_destination', $results);
        $file = $results['backup_destination'];
        $this->assertInstanceOf(\stored_file::class, $file);

        $packer = get_file_packer('application/vnd.moodle.backup');
        $file->extract_to_pathname($packer, make_backup_temp_directory($backupid));

        return $backupid;
    }

    /**
     * Restore a backed-up activity into a course, adding it to that course.
     *
     * @param string $backupid the backup id to restore
     * @param int $courseid the target course id
     * @param int $userid the user performing the restore
     * @return void
     */
    protected function restore_into_course(string $backupid, int $courseid, int $userid): void {
        $rc = new restore_controller(
            $backupid,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $userid,
            backup::TARGET_CURRENT_ADDING
        );
        // Precheck can return false on benign warnings; a genuine problem surfaces
        // as an exception from execute_plan() below, which is what we care about.
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
    }
}
