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
 * Tests for the 3D model viewer support.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::diagnosis_get_model_url
 * @covers     ::diagnosis_model_format
 * @covers     ::diagnosis_model_file_accepted
 * @covers     ::diagnosis_save_model
 */
final class model_test extends \advanced_testcase {
    /**
     * Store a model file in an instance's module context.
     *
     * @param \context_module $context the module context
     * @param string $filename the stored file name
     * @return void
     */
    protected function store_model(\context_module $context, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_diagnosis',
            'filearea' => 'model',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], 'fake-model-bytes');
    }

    /**
     * The extension maps to the viewer format, with glTF and GLB sharing a loader.
     */
    public function test_model_format_from_extension(): void {
        $this->assertSame('gltf', diagnosis_model_format('scene.glb'));
        $this->assertSame('gltf', diagnosis_model_format('scene.GLTF'));
        $this->assertSame('stl', diagnosis_model_format('part.stl'));
        $this->assertSame('ply', diagnosis_model_format('scan.ply'));
        $this->assertSame('obj', diagnosis_model_format('mesh.obj'));
        $this->assertSame('', diagnosis_model_format('notes.txt'));
        $this->assertSame('', diagnosis_model_format('noextension'));
    }

    /**
     * The accepted-type check allows every model and companion extension
     * (case-insensitively) and rejects anything else.
     */
    public function test_model_file_accepted(): void {
        $accepted = ['scene.glb', 'scene.GLTF', 'part.stl', 'scan.ply', 'mesh.obj',
            'buffer.bin', 'library.mtl', 'texture.png', 'texture.jpg', 'texture.jpeg', 'texture.webp'];
        foreach ($accepted as $name) {
            $this->assertTrue(diagnosis_model_file_accepted($name), $name);
        }
        foreach (['notes.txt', 'archive.zip', 'script.exe', 'noextension'] as $name) {
            $this->assertFalse(diagnosis_model_file_accepted($name), $name);
        }
    }

    /**
     * With no stored model, the helper reports none.
     */
    public function test_get_model_url_null_when_absent(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->assertNull(diagnosis_get_model_url($context));
    }

    /**
     * With a stored model, the helper returns a pluginfile URL that points at the
     * model area with no itemid segment (as for the activity intro).
     */
    public function test_get_model_url_points_at_stored_file(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->store_model($context, 'heart.glb');

        $url = diagnosis_get_model_url($context);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $out = $url->out(false);
        $this->assertStringContainsString('/mod_diagnosis/model/', $out);
        $this->assertStringContainsString('heart.glb', $out);
        $this->assertStringNotContainsString('/model/0/', $out);
    }

    /**
     * With companion files present, the main model is the recognised model file,
     * not a buffer or texture, whatever order they are stored in.
     */
    public function test_get_model_url_picks_main_among_companions(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        // A glTF bundle: the JSON model plus its buffer and a texture.
        $this->store_model($context, 'scene.bin');
        $this->store_model($context, 'texture.png');
        $this->store_model($context, 'scene.gltf');

        $main = diagnosis_get_model_mainfile($context);
        $this->assertNotNull($main);
        $this->assertSame('scene.gltf', $main->get_filename());
        $this->assertSame('gltf', diagnosis_model_format($main->get_filename()));
        $this->assertStringContainsString('scene.gltf', diagnosis_get_model_url($context)->out(false));
    }

    /**
     * Every .mtl companion is discoverable so an OBJ that declares several
     * material libraries gets all of them, while other extensions return none.
     */
    public function test_get_model_companion_urls_finds_all_mtl(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->store_model($context, 'mesh.obj');
        $this->store_model($context, 'materials1.mtl');
        $this->store_model($context, 'materials2.mtl');

        $this->assertSame('obj', diagnosis_model_format(diagnosis_get_model_mainfile($context)->get_filename()));
        $mtls = diagnosis_get_model_companion_urls($context, 'mtl');
        $this->assertCount(2, $mtls);
        $names = array_map(fn($u) => $u->out(false), $mtls);
        $this->assertStringContainsString('materials1.mtl', implode(' ', $names));
        $this->assertStringContainsString('materials2.mtl', implode(' ', $names));
        $this->assertSame([], diagnosis_get_model_companion_urls($context, 'bin'));
    }

    /**
     * Saving with the toggle off clears any previously stored model.
     */
    public function test_save_model_toggle_off_clears_existing(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_diagnosis_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_diagnosis');
        $diagnosis = $generator->create_instance(['course' => $course->id]);
        $context = \context_module::instance($diagnosis->cmid);

        $this->store_model($context, 'part.stl');
        $this->assertNotNull(diagnosis_get_model_url($context));

        $data = (object) ['hasmodel' => 0, 'model_file' => 123];
        diagnosis_save_model($data, $context);

        $this->assertNull(diagnosis_get_model_url($context));
    }
}
