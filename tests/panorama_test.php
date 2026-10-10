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
 * Tests for the 360 degree panorama image support.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::diagnosis_get_panorama_url
 * @covers     ::diagnosis_save_panorama
 */
final class panorama_test extends \advanced_testcase {
    /**
     * Store a panorama image in an instance's module context.
     *
     * @param \context_module $context the module context
     * @param string $filename the stored file name
     * @return void
     */
    protected function store_panorama(\context_module $context, string $filename = 'pano.jpg'): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_diagnosis',
            'filearea' => 'panorama',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], 'fake-equirectangular-bytes');
    }

    /**
     * With no stored image, the helper reports no panorama.
     */
    public function test_get_panorama_url_null_when_absent(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->assertNull(diagnosis_get_panorama_url($context));
    }

    /**
     * With a stored image, the helper returns a pluginfile URL that points at
     * the panorama area with no itemid segment (as for the activity intro).
     */
    public function test_get_panorama_url_points_at_stored_file(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->store_panorama($context);

        $url = diagnosis_get_panorama_url($context);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $out = $url->out(false);
        $this->assertStringContainsString('/mod_diagnosis/panorama/', $out);
        $this->assertStringContainsString('pano.jpg', $out);
        // The single-file area omits the itemid segment, so the area is followed
        // directly by the filename rather than a "/0/" itemid.
        $this->assertStringNotContainsString('/panorama/0/', $out);
    }

    /**
     * Saving with the toggle off clears any previously stored panorama.
     */
    public function test_save_panorama_toggle_off_clears_existing(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->store_panorama($context);
        $this->assertNotNull(diagnosis_get_panorama_url($context));

        // A draft id is present (the filemanager always submits one) but the
        // toggle is off, so the stored image must be removed.
        $data = (object) ['haspanorama' => 0, 'panorama_image' => 123];
        diagnosis_save_panorama($data, $context);

        $this->assertNull(diagnosis_get_panorama_url($context));
    }
}
