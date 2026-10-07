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

use backup;
use backup_controller;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_imageblog_activity_structure_step
 * @covers     \restore_imageblog_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Back up an instance with user data and restore it into the same course.
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

        /** @var \mod_imageblog_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_imageblog');
        $imageblog = $generator->create_instance(['course' => $course->id, 'revealed' => 1]);

        // Record a diagnosis for the student and mark it as the best answer.
        $now = time();
        $diagnosisid = $DB->insert_record('imageblog_diagnoses', (object) [
            'imageblogid' => $imageblog->id,
            'userid' => $student->id,
            'diagnosis' => 'pneumonia',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->set_field('imageblog', 'bestdiagnosisid', $diagnosisid, ['id' => $imageblog->id]);

        // Back up the activity including user data.
        $backupid = $this->backup_activity($imageblog->cmid, $USER->id);

        // Restore it back into the same course as an additional activity.
        $this->restore_into_course($backupid, $course->id, $USER->id);

        // Two instances now exist; the later one is the restored copy.
        $instances = $DB->get_records('imageblog', ['course' => $course->id], 'id ASC');
        $this->assertCount(2, $instances);
        $restored = end($instances);

        $restoreddiagnosis = $DB->get_record(
            'imageblog_diagnoses',
            ['imageblogid' => $restored->id],
            '*',
            MUST_EXIST
        );

        // The best-answer reference points at the restored diagnosis, not the original.
        $this->assertEquals((int) $restoreddiagnosis->id, (int) $restored->bestdiagnosisid);
        $this->assertNotEquals((int) $diagnosisid, (int) $restored->bestdiagnosisid);
        $this->assertSame('pneumonia', $restoreddiagnosis->diagnosis);
        $this->assertEquals((int) $student->id, (int) $restoreddiagnosis->userid);
    }

    /**
     * Back up a single activity with user data included.
     *
     * @param int $cmid the course module id to back up
     * @param int $userid the user performing the backup
     * @return string the backup id
     */
    protected function backup_activity(int $cmid, int $userid): string {
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
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restore a backed-up activity into a course, adding it alongside existing content.
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
