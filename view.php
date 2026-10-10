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
 * Display a single diagnosis activity: the case, the diagnosis form and the outcome.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'diagnosis');
$diagnosis = $DB->get_record('diagnosis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/diagnosis:view', $context);

$pageurl = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($diagnosis->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canreveal = has_capability('mod/diagnosis:reveal', $context);
$cansubmit = has_capability('mod/diagnosis:submit', $context);
$canask = has_capability('mod/diagnosis:askquestion', $context);
$cananswer = has_capability('mod/diagnosis:answerquestion', $context);

// Grading is always the teacher's manual marking of each submission: a simple
// point grade, or an advanced grading method (e.g. a rubric) when one is active.
$gradingcontroller = diagnosis_grading_active($diagnosis);

// A case may carry an optional 360 degree panorama. Load the bundled Pannellum
// stylesheet before any output; the viewer script is loaded lazily below.
$panoramaurl = diagnosis_get_panorama_url($context);
if ($panoramaurl) {
    $PAGE->requires->css(new moodle_url('/mod/diagnosis/thirdparty/pannellum/pannellum.css'));
}

// A case may also carry an optional 3D model (STL/PLY/OBJ/glTF/GLB), possibly
// with companion files (glTF buffers/textures, an OBJ material library). Resolve
// the main model file and viewer format; the Three.js viewer loads lazily below.
$modelmainfile = diagnosis_get_model_mainfile($context);
$modelurl = null;
$modelformat = '';
$modelmtlurls = [];
if ($modelmainfile) {
    $modelformat = diagnosis_model_format($modelmainfile->get_filename());
    if ($modelformat !== '') {
        $modelurl = diagnosis_model_file_url($modelmainfile);
        // An OBJ may ship one or more .mtl material libraries; load them all.
        if ($modelformat === 'obj') {
            $modelmtlurls = diagnosis_get_model_companion_urls($context, 'mtl');
        }
    }
}

// Mark the activity viewed for completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Teacher action: reveal the outcome. This shows the expected diagnosis and the
// explanation to readers; it does not award grades (the teacher marks separately).
if ($canreveal && empty($diagnosis->revealed) && optional_param('reveal', 0, PARAM_BOOL) && confirm_sesskey()) {
    // Serialize the reveal so concurrent or double-submitted requests send the
    // notifications exactly once, from the request that flips the flag.
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_diagnosis_reveal');
    $lock = $lockfactory->get_lock('diagnosis_' . $diagnosis->id, 10);
    if ($lock) {
        try {
            if (!$DB->get_field('diagnosis', 'revealed', ['id' => $diagnosis->id])) {
                $DB->set_field('diagnosis', 'revealed', 1, ['id' => $diagnosis->id]);
                $diagnosis = $DB->get_record('diagnosis', ['id' => $diagnosis->id], '*', MUST_EXIST);
                diagnosis_notify_outcome_revealed($diagnosis, $cm, $context, $USER);
            }
        } finally {
            $lock->release();
        }
    }
    redirect($pageurl, get_string('outcomerevealed', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Reader action: submit or update a diagnosis (only while the case is open).
$mform = null;
if ($cansubmit && empty($diagnosis->revealed)) {
    $mform = new \mod_diagnosis\form\diagnosis_form($pageurl->out(false));
    $existing = $DB->get_record('diagnosis_submissions', ['diagnosisid' => $diagnosis->id, 'userid' => $USER->id]);
    $mform->set_data([
        'id' => $cm->id,
        'diagnosis' => $existing ? $existing->diagnosis : '',
    ]);

    if ($data = $mform->get_data()) {
        // Re-check the persisted reveal state: a teacher may have revealed the
        // outcome after this form was rendered. Submissions close at reveal, so
        // refuse the stale write rather than record an answer after the fact.
        if ($DB->get_field('diagnosis', 'revealed', ['id' => $diagnosis->id])) {
            redirect($pageurl, get_string('casealreadyrevealed', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_WARNING);
        }
        $now = time();
        if ($existing) {
            // If the teacher had already marked this diagnosis and the text now
            // changes, that assessment no longer applies: clear the stored grade
            // and the gradebook entry so the teacher re-marks the new answer.
            $invalidategrade = $existing->grade !== null && $existing->diagnosis !== $data->diagnosis;
            $existing->diagnosis = $data->diagnosis;
            $existing->timemodified = $now;
            if ($invalidategrade) {
                $existing->grade = null;
            }
            $DB->update_record('diagnosis_submissions', $existing);
            if ($invalidategrade) {
                diagnosis_update_grades($diagnosis, $USER->id);
            }
        } else {
            $record = (object) [
                'diagnosisid' => $diagnosis->id,
                'userid' => $USER->id,
                'diagnosis' => $data->diagnosis,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('diagnosis_submissions', $record);
        }
        // Submitting a diagnosis can satisfy the "submit a diagnosis" completion rule.
        if ($completion->is_enabled($cm) && !empty($diagnosis->completionsubmit)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
        }
        redirect($pageurl, get_string('diagnosissaved', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Reader action: ask a question about the case.
$questionform = null;
if ($canask) {
    $questionform = new \mod_diagnosis\form\question_form($pageurl->out(false));
    $questionform->set_data(['id' => $cm->id]);
    if ($data = $questionform->get_data()) {
        $now = time();
        $DB->insert_record('diagnosis_questions', (object) [
            'diagnosisid' => $diagnosis->id,
            'userid' => $USER->id,
            'question' => $data->question,
            'answer' => null,
            'answeredby' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeanswered' => 0,
        ]);
        diagnosis_notify_question_posted($diagnosis, $cm, $context, $USER);
        redirect($pageurl, get_string('questionasked', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Teacher action: answer (or edit the answer to) a question.
$answerform = null;
$answerquestion = null;
$answerquestionid = optional_param('answer', 0, PARAM_INT);
if ($cananswer && $answerquestionid) {
    $answerquestion = $DB->get_record(
        'diagnosis_questions',
        ['id' => $answerquestionid, 'diagnosisid' => $diagnosis->id],
        '*',
        MUST_EXIST
    );
    $answerform = new \mod_diagnosis\form\answer_form($pageurl->out(false));
    $answerform->set_data([
        'id' => $cm->id,
        'answer' => $answerquestion->id,
        'answertext' => (string) $answerquestion->answer,
    ]);
    if ($answerform->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $answerform->get_data()) {
        // Only the first answer notifies the asker; editing an existing answer does not.
        $wasunanswered = empty($answerquestion->answeredby);
        $now = time();
        $answerquestion->answer = $data->answertext;
        $answerquestion->answeredby = $USER->id;
        $answerquestion->timeanswered = $now;
        $answerquestion->timemodified = $now;
        $DB->update_record('diagnosis_questions', $answerquestion);
        if ($wasunanswered) {
            diagnosis_notify_question_answered($diagnosis, $cm, $answerquestion, $USER);
        }
        redirect($pageurl, get_string('answersaved', 'mod_diagnosis'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($diagnosis->name));

if (!empty($diagnosis->intro)) {
    echo $OUTPUT->box(format_module_intro('diagnosis', $diagnosis, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->heading(get_string('case', 'mod_diagnosis'), 3);
echo $OUTPUT->box(format_text($diagnosis->casequestion, FORMAT_MOODLE), 'generalbox');

if ($panoramaurl) {
    // The viewer initialises this region from the data attributes below. Pannellum
    // is a plain (non-AMD) global, so it is injected on demand and its presence
    // checked before use; any load failure degrades to the CSS fallback message.
    echo html_writer::div('', 'mod-diagnosis-panorama mb-3', [
        'data-region' => 'mod-diagnosis-panorama',
        'data-fallback' => get_string('panoramaunavailable', 'mod_diagnosis'),
        'role' => 'region',
        'aria-label' => get_string('panorama', 'mod_diagnosis'),
    ]);
    $pannellumjs = (new moodle_url('/mod/diagnosis/thirdparty/pannellum/pannellum.js'))->out(false);
    $config = json_encode([
        'js' => $pannellumjs,
        'img' => $panoramaurl->out(false),
    ]);
    $PAGE->requires->js_amd_inline("
require([], function() {
    var cfg = $config;
    var region = document.querySelector('[data-region=\"mod-diagnosis-panorama\"]');
    if (!region || region.dataset.initialised === '1') {
        return;
    }
    var fail = function() {
        region.classList.add('mod-diagnosis-panorama-fallback');
    };
    var start = function() {
        if (!window.pannellum) {
            fail();
            return;
        }
        region.dataset.initialised = '1';
        window.pannellum.viewer(region, {
            type: 'equirectangular',
            panorama: cfg.img,
            autoLoad: true,
            showControls: true,
            hfov: 100
        });
    };
    if (window.pannellum) {
        start();
        return;
    }
    var script = document.createElement('script');
    script.src = cfg.js;
    script.async = true;
    script.onload = start;
    script.onerror = fail;
    document.head.appendChild(script);
});
");
}

if ($modelurl) {
    // The Three.js build and the format's loader are plain (non-AMD) globals, so
    // they are injected in order on demand and checked before use; any load or
    // parse failure degrades to the CSS fallback message.
    echo html_writer::div('', 'mod-diagnosis-model mb-3', [
        'data-region' => 'mod-diagnosis-model',
        'data-fallback' => get_string('modelunavailable', 'mod_diagnosis'),
        'role' => 'region',
        'aria-label' => get_string('model', 'mod_diagnosis'),
    ]);
    $loadermap = ['gltf' => 'GLTFLoader', 'stl' => 'STLLoader', 'ply' => 'PLYLoader', 'obj' => 'OBJLoader'];
    $loaderurl = function ($name) {
        return (new moodle_url('/mod/diagnosis/thirdparty/three/js/loaders/' . $name . '.js'))->out(false);
    };
    $threejs = (new moodle_url('/mod/diagnosis/thirdparty/three/build/three.min.js'))->out(false);
    $controlsjs = (new moodle_url('/mod/diagnosis/thirdparty/three/js/controls/OrbitControls.js'))->out(false);
    // Load the format's loader after the controls; an OBJ with material
    // libraries also needs the MTLLoader, loaded before the OBJLoader.
    $scripts = [$controlsjs];
    if ($modelformat === 'obj' && $modelmtlurls) {
        $scripts[] = $loaderurl('MTLLoader');
    }
    $scripts[] = $loaderurl($loadermap[$modelformat]);
    // Map each companion file to its real pluginfile URL, keyed by the path the
    // model references it at (relative to the main model's folder). The viewer
    // rewrites the loader's companion requests through this map, so companions
    // resolve correctly even when the site has slasharguments disabled (where a
    // query-style pluginfile URL defeats the loader's relative resolution).
    $companions = [];
    $mainprefix = ltrim($modelmainfile->get_filepath(), '/');
    $modelfiles = get_file_storage()->get_area_files($context->id, 'mod_diagnosis', 'model', 0, 'filepath, filename', false);
    foreach ($modelfiles as $cfile) {
        if ($cfile->get_pathnamehash() === $modelmainfile->get_pathnamehash()) {
            continue;
        }
        $full = ltrim($cfile->get_filepath(), '/') . $cfile->get_filename();
        $rel = ($mainprefix !== '' && strpos($full, $mainprefix) === 0)
            ? substr($full, strlen($mainprefix)) : $full;
        $companions[$rel] = diagnosis_model_file_url($cfile)->out(false);
    }
    $mtlurls = [];
    foreach ($modelmtlurls as $mtlurl) {
        $mtlurls[] = $mtlurl->out(false);
    }
    $config = json_encode([
        'three' => $threejs,
        'scripts' => $scripts,
        'model' => $modelurl->out(false),
        'format' => $modelformat,
        'mtls' => $mtlurls,
        'files' => (object) $companions,
    ]);
    $PAGE->requires->js_amd_inline("
require([], function() {
    var cfg = $config;
    var region = document.querySelector('[data-region=\"mod-diagnosis-model\"]');
    if (!region || region.dataset.initialised === '1') {
        return;
    }
    var fail = function() {
        region.classList.add('mod-diagnosis-model-fallback');
    };
    var loadScript = function(src) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.async = false;
            s.onload = resolve;
            s.onerror = function() {
                reject(new Error('script load failed'));
            };
            s.src = src;
            document.head.appendChild(s);
        });
    };
    // Moodle loads RequireJS, so define.amd is truthy and the UMD three.min.js
    // would register as an anonymous AMD module instead of assigning window.THREE.
    // Hide define while that build evaluates so it takes its browser-global path;
    // the legacy controls/loaders read the global and do not touch define.
    var realDefine = window.define;
    var hideAmd = function() {
        try {
            window.define = undefined;
        } catch (e) {
            realDefine = window.define;
        }
    };
    var restoreAmd = function() {
        try {
            window.define = realDefine;
        } catch (e) {
            return;
        }
    };
    hideAmd();
    loadScript(cfg.three).then(function() {
        restoreAmd();
        var rest = Promise.resolve();
        cfg.scripts.forEach(function(src) {
            rest = rest.then(function() {
                return loadScript(src);
            });
        });
        return rest;
    }).then(function() {
        if (!window.THREE) {
            fail();
            return;
        }
        region.dataset.initialised = '1';
        render(window.THREE);
    }).catch(function() {
        restoreAmd();
        fail();
    });

    function render(THREE) {
        // Rewrite companion requests (glTF buffers/textures, OBJ materials and
        // their textures) to their real pluginfile URLs, matched by the path the
        // model references them at. This makes companions load regardless of how
        // the loader resolved the relative URL (e.g. with slasharguments off).
        var files = cfg.files || {};
        var manager = new THREE.LoadingManager();
        manager.setURLModifier(function(url) {
            // Match on a path-segment boundary and prefer the longest (most
            // specific) key, so e.g. 'parts/a.png' wins over 'a.png' and a bare
            // 'scene.png' is never matched by 'ne.png'.
            var best = null;
            for (var rel in files) {
                if (!Object.prototype.hasOwnProperty.call(files, rel) || !rel) {
                    continue;
                }
                if (url === rel || url.slice(-(rel.length + 1)) === '/' + rel) {
                    if (best === null || rel.length > best.length) {
                        best = rel;
                    }
                }
            }
            return best === null ? url : files[best];
        });
        var width = region.clientWidth || 640;
        var height = Math.round(width * 9 / 16);
        var scene = new THREE.Scene();
        scene.background = new THREE.Color(0x1a1a1a);
        var camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 100000);
        var renderer = new THREE.WebGLRenderer({antialias: true});
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(width, height);
        region.appendChild(renderer.domElement);
        scene.add(new THREE.AmbientLight(0xffffff, 0.7));
        var key = new THREE.DirectionalLight(0xffffff, 0.8);
        key.position.set(1, 1, 1);
        scene.add(key);
        var controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        var framereq = null;
        // The render loop starts only once a model is in the scene, so a failed
        // or empty load never spins an empty canvas.
        var begin = function() {
            if (framereq !== null) {
                return;
            }
            var loop = function() {
                framereq = requestAnimationFrame(loop);
                controls.update();
                renderer.render(scene, camera);
            };
            loop();
        };
        // Stop rendering and remove the canvas on an asynchronous load failure,
        // so the CSS fallback message has the region to itself.
        var teardown = function() {
            if (framereq !== null) {
                cancelAnimationFrame(framereq);
                framereq = null;
            }
            if (renderer.domElement && renderer.domElement.parentNode) {
                renderer.domElement.parentNode.removeChild(renderer.domElement);
            }
            renderer.dispose();
            fail();
        };
        var frame = function(object3d) {
            var box = new THREE.Box3().setFromObject(object3d);
            var size = box.getSize(new THREE.Vector3());
            var center = box.getCenter(new THREE.Vector3());
            object3d.position.sub(center);
            var maxdim = Math.max(size.x, size.y, size.z) || 1;
            var dist = maxdim / (2 * Math.tan(Math.PI * camera.fov / 360));
            camera.position.set(0, 0, dist * 1.8);
            camera.near = Math.max(dist / 100, 0.01);
            camera.far = dist * 100;
            camera.updateProjectionMatrix();
            controls.target.set(0, 0, 0);
            controls.update();
        };
        var hasColour = function(geometry) {
            return !!(geometry.getAttribute && geometry.getAttribute('color'));
        };
        var addMesh = function(geometry) {
            geometry.computeVertexNormals();
            var colour = hasColour(geometry);
            var material = new THREE.MeshStandardMaterial({
                color: colour ? 0xffffff : 0xb0b0b0,
                vertexColors: colour,
                metalness: 0.1,
                roughness: 0.8
            });
            var mesh = new THREE.Mesh(geometry, material);
            frame(mesh);
            scene.add(mesh);
            begin();
        };
        var addPoints = function(geometry) {
            var box = new THREE.Box3().setFromBufferAttribute(geometry.getAttribute('position'));
            var span = box.getSize(new THREE.Vector3());
            var maxdim = Math.max(span.x, span.y, span.z) || 1;
            var colour = hasColour(geometry);
            var material = new THREE.PointsMaterial({
                color: colour ? 0xffffff : 0xb0b0b0,
                vertexColors: colour,
                size: maxdim / 350,
                sizeAttenuation: true
            });
            var points = new THREE.Points(geometry, material);
            frame(points);
            scene.add(points);
            begin();
        };
        var addObject = function(object3d) {
            frame(object3d);
            scene.add(object3d);
            begin();
        };
        try {
            if (cfg.format === 'gltf') {
                new THREE.GLTFLoader(manager).load(cfg.model, function(gltf) {
                    addObject(gltf.scene);
                }, undefined, teardown);
            } else if (cfg.format === 'stl') {
                new THREE.STLLoader(manager).load(cfg.model, addMesh, undefined, teardown);
            } else if (cfg.format === 'ply') {
                // A PLY with faces is a mesh; one without (e.g. an Open3D scan) is a point cloud.
                new THREE.PLYLoader(manager).load(cfg.model, function(geometry) {
                    if (geometry.index) {
                        addMesh(geometry);
                    } else {
                        addPoints(geometry);
                    }
                }, undefined, teardown);
            } else if (cfg.format === 'obj') {
                var loadObj = function(materials) {
                    var loader = new THREE.OBJLoader(manager);
                    if (materials) {
                        loader.setMaterials(materials);
                    }
                    loader.load(cfg.model, addObject, undefined, teardown);
                };
                var mtls = cfg.mtls || [];
                if (mtls.length) {
                    // Load every material library the bundle carries and merge them
                    // per material, so an OBJ that declares several mtllib files gets
                    // all of its materials. Each library is parsed with its own
                    // directory as the base, so a library's textures resolve to the
                    // path the companion map is keyed by; the already-canonical .mtl
                    // URLs are fetched with a plain loader so they are never remapped.
                    // A library that fails to load or parse is skipped.
                    var plainLoader = new THREE.FileLoader();
                    var materialsByName = {};
                    var index = 0;
                    var buildMerged = function() {
                        if (!Object.keys(materialsByName).length) {
                            return null;
                        }
                        return {
                            preload: function() {},
                            create: function(name) {
                                return materialsByName[name] || null;
                            }
                        };
                    };
                    var loadNextMtl = function() {
                        if (index >= mtls.length) {
                            loadObj(buildMerged());
                            return;
                        }
                        var url = mtls[index];
                        var dir = url.substring(0, url.lastIndexOf('/') + 1);
                        plainLoader.load(url, function(text) {
                            try {
                                var mc = new THREE.MTLLoader(manager).parse(text, dir);
                                mc.preload();
                                for (var name in mc.materialsInfo) {
                                    if (Object.prototype.hasOwnProperty.call(mc.materialsInfo, name)
                                        && !materialsByName[name]) {
                                        materialsByName[name] = mc.create(name);
                                    }
                                }
                            } catch (err) {
                                // Skip a malformed library rather than failing the model.
                            }
                            index++;
                            loadNextMtl();
                        }, undefined, function() {
                            index++;
                            loadNextMtl();
                        });
                    };
                    loadNextMtl();
                } else {
                    loadObj(null);
                }
            } else {
                teardown();
                return;
            }
        } catch (e) {
            teardown();
            return;
        }
        window.addEventListener('resize', function() {
            var w = region.clientWidth || width;
            var h = Math.round(w * 9 / 16);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        });
    }
});
");
}

$casetags = core_tag_tag::get_item_tags('mod_diagnosis', 'diagnosis', $diagnosis->id);
if ($casetags) {
    echo $OUTPUT->tag_list($casetags, get_string('casetags', 'mod_diagnosis'), 'diagnosis-tags');
}

$mydiagnosis = $DB->get_record('diagnosis_submissions', ['diagnosisid' => $diagnosis->id, 'userid' => $USER->id]);

if (!empty($diagnosis->revealed)) {
    echo $OUTPUT->heading(get_string('outcome', 'mod_diagnosis'), 3);
    if (trim((string) $diagnosis->correctdiagnosis) !== '') {
        echo html_writer::tag('p', get_string('expecteddiagnosis', 'mod_diagnosis', s($diagnosis->correctdiagnosis)));
    }
    echo $OUTPUT->box(format_text($diagnosis->revealtext, FORMAT_MOODLE), 'generalbox');
    if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_diagnosis', s($mydiagnosis->diagnosis)));
    }
} else {
    if ($mform) {
        echo $OUTPUT->heading(get_string('yourdiagnosisheading', 'mod_diagnosis'), 3);
        $mform->display();
    } else if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_diagnosis', s($mydiagnosis->diagnosis)));
    }
    if ($canreveal) {
        $revealurl = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id, 'reveal' => 1, 'sesskey' => sesskey()]);
        echo $OUTPUT->single_button($revealurl, get_string('revealoutcome', 'mod_diagnosis'));
    }
}

// A reader sees their own grade as soon as the teacher has marked it.
if (!$canreveal && $mydiagnosis) {
    $grades = diagnosis_get_user_grades($diagnosis, $USER->id);
    if (isset($grades[$USER->id]) && $grades[$USER->id]->rawgrade !== null) {
        $a = format_float($grades[$USER->id]->rawgrade, 2) . ' / ' . $diagnosis->grade;
        echo html_writer::tag('p', get_string('yourgrade', 'mod_diagnosis', $a));
    }
}

// Teacher marking: every submitted diagnosis, with a button to mark it using
// simple direct grading or the active advanced method (e.g. a rubric).
if ($canreveal) {
    echo $OUTPUT->heading(get_string('gradeheading', 'mod_diagnosis'), 3);
    if ($gradingcontroller && !$gradingcontroller->is_form_available()) {
        // An advanced method is selected but its form (e.g. the rubric) is not defined yet.
        echo $gradingcontroller->form_unavailable_notification();
    } else {
        $tograde = $DB->get_records('diagnosis_submissions', ['diagnosisid' => $diagnosis->id], 'timecreated ASC');
        if (!$tograde) {
            echo html_writer::tag('p', get_string('nodiagnoses', 'mod_diagnosis'));
        } else {
            $gradetable = new html_table();
            $gradetable->head = [
                get_string('diagnosis', 'mod_diagnosis'),
                get_string('grade'),
                get_string('action'),
            ];
            foreach ($tograde as $diag) {
                if ($diag->grade !== null) {
                    $gradecell = format_float((float) $diag->grade, 2) . ' / ' . $diagnosis->grade;
                    $label = get_string('editgrade', 'mod_diagnosis');
                } else {
                    $gradecell = '-';
                    $label = get_string('gradediagnosis', 'mod_diagnosis');
                }
                $gradeurl = new moodle_url('/mod/diagnosis/grade.php', ['id' => $cm->id, 'userid' => $diag->userid]);
                $gradetable->data[] = [s($diag->diagnosis), $gradecell, $OUTPUT->single_button($gradeurl, $label, 'get')];
            }
            echo html_writer::table($gradetable);
        }
    }
}

// Questions and answers on the case.
echo $OUTPUT->heading(get_string('questionsheading', 'mod_diagnosis'), 3);

if ($answerform) {
    // A teacher is answering a specific question: show it and the answer form.
    echo html_writer::tag('p', format_text($answerquestion->question, FORMAT_PLAIN), ['class' => 'font-italic']);
    $answerform->display();
} else {
    $questions = $DB->get_records('diagnosis_questions', ['diagnosisid' => $diagnosis->id], 'timecreated ASC');
    if (!$questions) {
        echo html_writer::tag('p', get_string('noquestions', 'mod_diagnosis'));
    } else {
        foreach ($questions as $question) {
            // Peers see questions anonymously; the asker and teachers see the name.
            if ($cananswer || (int) $question->userid === (int) $USER->id) {
                $asker = \core_user::get_user($question->userid);
                $askername = $asker ? fullname($asker) : get_string('participant', 'mod_diagnosis');
            } else {
                $askername = get_string('participant', 'mod_diagnosis');
            }

            echo html_writer::start_tag('div', ['class' => 'card mb-3']);
            echo html_writer::start_tag('div', ['class' => 'card-body']);
            echo html_writer::tag('h5', format_text($question->question, FORMAT_PLAIN), ['class' => 'card-title']);
            echo html_writer::tag('p', get_string('askedby', 'mod_diagnosis', $askername), ['class' => 'text-muted']);

            if ($question->answer !== null && trim($question->answer) !== '') {
                echo html_writer::tag('div', format_text($question->answer, FORMAT_PLAIN), ['class' => 'alert alert-info mb-0']);
            } else {
                echo html_writer::tag('p', get_string('notanswered', 'mod_diagnosis'), ['class' => 'text-muted font-italic']);
            }

            if ($cananswer) {
                $answered = $question->answer !== null && trim($question->answer) !== '';
                $label = $answered
                    ? get_string('editanswer', 'mod_diagnosis')
                    : get_string('answerquestion', 'mod_diagnosis');
                $url = new moodle_url('/mod/diagnosis/view.php', ['id' => $cm->id, 'answer' => $question->id]);
                echo $OUTPUT->single_button($url, $label, 'get');
            }

            echo html_writer::end_tag('div');
            echo html_writer::end_tag('div');
        }
    }

    if ($questionform) {
        echo $OUTPUT->heading(get_string('askquestion', 'mod_diagnosis'), 4);
        $questionform->display();
    }
}

echo $OUTPUT->footer();
