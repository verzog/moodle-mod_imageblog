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
 * Display a single image blog activity: the case, the diagnosis form and the outcome.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'imageblog');
$imageblog = $DB->get_record('imageblog', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/imageblog:view', $context);

$pageurl = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($imageblog->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canreveal = has_capability('mod/imageblog:reveal', $context);
$cansubmit = has_capability('mod/imageblog:submit', $context);
$canask = has_capability('mod/imageblog:askquestion', $context);
$cananswer = has_capability('mod/imageblog:answerquestion', $context);

// When an advanced grading method (e.g. a rubric) is active, grading is manual
// and per submission; the built-in engine's reveal-time scoring and best-answer
// bonus do not apply.
$gradingcontroller = imageblog_grading_active($imageblog);

// A case may carry an optional 360 degree panorama. Load the bundled Pannellum
// stylesheet before any output; the viewer script is loaded lazily below.
$panoramaurl = imageblog_get_panorama_url($context);
if ($panoramaurl) {
    $PAGE->requires->css(new moodle_url('/mod/imageblog/thirdparty/pannellum/pannellum.css'));
}

// A case may also carry an optional 3D model (STL/PLY/OBJ/glTF/GLB), possibly
// with companion files (glTF buffers/textures, an OBJ material library). Resolve
// the main model file and viewer format; the Three.js viewer loads lazily below.
$modelmainfile = imageblog_get_model_mainfile($context);
$modelurl = null;
$modelformat = '';
$modelmtlurls = [];
if ($modelmainfile) {
    $modelformat = imageblog_model_format($modelmainfile->get_filename());
    if ($modelformat !== '') {
        $modelurl = imageblog_model_file_url($modelmainfile);
        // An OBJ may ship one or more .mtl material libraries; load them all.
        if ($modelformat === 'obj') {
            $modelmtlurls = imageblog_get_model_companion_urls($context, 'mtl');
        }
    }
}

// Mark the activity viewed for completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Teacher action: reveal the outcome and award grades.
if ($canreveal && empty($imageblog->revealed) && optional_param('reveal', 0, PARAM_BOOL) && confirm_sesskey()) {
    // Serialize the reveal so concurrent or double-submitted requests perform the
    // grade pass and notifications exactly once, from the request that flips the flag.
    $lockfactory = \core\lock\lock_config::get_lock_factory('mod_imageblog_reveal');
    $lock = $lockfactory->get_lock('imageblog_' . $imageblog->id, 10);
    if ($lock) {
        try {
            if (!$DB->get_field('imageblog', 'revealed', ['id' => $imageblog->id])) {
                $DB->set_field('imageblog', 'revealed', 1, ['id' => $imageblog->id]);
                $imageblog = $DB->get_record('imageblog', ['id' => $imageblog->id], '*', MUST_EXIST);
                imageblog_update_grades($imageblog);
                imageblog_notify_outcome_revealed($imageblog, $cm, $context, $USER);
            }
        } finally {
            $lock->release();
        }
    }
    redirect($pageurl, get_string('outcomerevealed', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Teacher action: mark (or clear) the best diagnosis. Only meaningful after reveal.
$setbest = optional_param('setbest', -1, PARAM_INT);
if ($canreveal && !empty($imageblog->revealed) && $setbest >= 0 && confirm_sesskey()) {
    // 0 clears the selection; otherwise the diagnosis must belong to this case.
    $valid = ($setbest === 0)
        || $DB->record_exists('imageblog_diagnoses', ['id' => $setbest, 'imageblogid' => $imageblog->id]);
    if ($valid) {
        // Serialize the write and regrade so two concurrent teacher actions cannot
        // leave the stored selection and the gradebook awarding different bonuses.
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_imageblog_bestanswer');
        $lock = $lockfactory->get_lock('imageblog_' . $imageblog->id, 10);
        if (!$lock) {
            redirect($pageurl, get_string('bestanswerlocked', 'mod_imageblog'), null, \core\output\notification::NOTIFY_WARNING);
        }
        try {
            $DB->set_field('imageblog', 'bestdiagnosisid', $setbest, ['id' => $imageblog->id]);
            // Re-read inside the lock so the regrade runs against the persisted state.
            $imageblog = $DB->get_record('imageblog', ['id' => $imageblog->id], '*', MUST_EXIST);
            imageblog_update_grades($imageblog);
        } finally {
            $lock->release();
        }
        redirect($pageurl, get_string('bestupdated', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Reader action: submit or update a diagnosis (only while the case is open).
$mform = null;
if ($cansubmit && empty($imageblog->revealed)) {
    $mform = new \mod_imageblog\form\diagnosis_form($pageurl->out(false));
    $existing = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $imageblog->id, 'userid' => $USER->id]);
    $mform->set_data([
        'id' => $cm->id,
        'diagnosis' => $existing ? $existing->diagnosis : '',
    ]);

    if ($data = $mform->get_data()) {
        // Re-check the persisted reveal state: a teacher may have revealed (and
        // graded) the case after this form was rendered. Saving now would leave
        // the diagnosis permanently locked but absent from the reveal-time
        // grade pass, so refuse the stale write.
        if ($DB->get_field('imageblog', 'revealed', ['id' => $imageblog->id])) {
            redirect($pageurl, get_string('casealreadyrevealed', 'mod_imageblog'), null, \core\output\notification::NOTIFY_WARNING);
        }
        $now = time();
        if ($existing) {
            // If the teacher had already graded this diagnosis with an advanced
            // grading method and the text now changes, that assessment no longer
            // applies: clear the stored grade and the gradebook entry so the
            // teacher re-marks the new answer.
            $invalidategrade = $existing->rubricgrade !== null && $existing->diagnosis !== $data->diagnosis;
            $existing->diagnosis = $data->diagnosis;
            $existing->timemodified = $now;
            if ($invalidategrade) {
                $existing->rubricgrade = null;
            }
            $DB->update_record('imageblog_diagnoses', $existing);
            if ($invalidategrade) {
                imageblog_update_grades($imageblog, $USER->id);
            }
        } else {
            $record = (object) [
                'imageblogid' => $imageblog->id,
                'userid' => $USER->id,
                'diagnosis' => $data->diagnosis,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('imageblog_diagnoses', $record);
        }
        // Submitting a diagnosis can satisfy the "submit a diagnosis" completion rule.
        if ($completion->is_enabled($cm) && !empty($imageblog->completionsubmit)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
        }
        redirect($pageurl, get_string('diagnosissaved', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Reader action: ask a question about the case.
$questionform = null;
if ($canask) {
    $questionform = new \mod_imageblog\form\question_form($pageurl->out(false));
    $questionform->set_data(['id' => $cm->id]);
    if ($data = $questionform->get_data()) {
        $now = time();
        $DB->insert_record('imageblog_questions', (object) [
            'imageblogid' => $imageblog->id,
            'userid' => $USER->id,
            'question' => $data->question,
            'answer' => null,
            'answeredby' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeanswered' => 0,
        ]);
        imageblog_notify_question_posted($imageblog, $cm, $context, $USER);
        redirect($pageurl, get_string('questionasked', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Teacher action: answer (or edit the answer to) a question.
$answerform = null;
$answerquestion = null;
$answerquestionid = optional_param('answer', 0, PARAM_INT);
if ($cananswer && $answerquestionid) {
    $answerquestion = $DB->get_record(
        'imageblog_questions',
        ['id' => $answerquestionid, 'imageblogid' => $imageblog->id],
        '*',
        MUST_EXIST
    );
    $answerform = new \mod_imageblog\form\answer_form($pageurl->out(false));
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
        $DB->update_record('imageblog_questions', $answerquestion);
        if ($wasunanswered) {
            imageblog_notify_question_answered($imageblog, $cm, $answerquestion, $USER);
        }
        redirect($pageurl, get_string('answersaved', 'mod_imageblog'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($imageblog->name));

if (!empty($imageblog->intro)) {
    echo $OUTPUT->box(format_module_intro('imageblog', $imageblog, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->heading(get_string('case', 'mod_imageblog'), 3);
echo $OUTPUT->box(format_text($imageblog->casequestion, FORMAT_MOODLE), 'generalbox');

if ($panoramaurl) {
    // The viewer initialises this region from the data attributes below. Pannellum
    // is a plain (non-AMD) global, so it is injected on demand and its presence
    // checked before use; any load failure degrades to the CSS fallback message.
    echo html_writer::div('', 'mod-imageblog-panorama mb-3', [
        'data-region' => 'mod-imageblog-panorama',
        'data-fallback' => get_string('panoramaunavailable', 'mod_imageblog'),
        'role' => 'region',
        'aria-label' => get_string('panorama', 'mod_imageblog'),
    ]);
    $pannellumjs = (new moodle_url('/mod/imageblog/thirdparty/pannellum/pannellum.js'))->out(false);
    $config = json_encode([
        'js' => $pannellumjs,
        'img' => $panoramaurl->out(false),
    ]);
    $PAGE->requires->js_amd_inline("
require([], function() {
    var cfg = $config;
    var region = document.querySelector('[data-region=\"mod-imageblog-panorama\"]');
    if (!region || region.dataset.initialised === '1') {
        return;
    }
    var fail = function() {
        region.classList.add('mod-imageblog-panorama-fallback');
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
    echo html_writer::div('', 'mod-imageblog-model mb-3', [
        'data-region' => 'mod-imageblog-model',
        'data-fallback' => get_string('modelunavailable', 'mod_imageblog'),
        'role' => 'region',
        'aria-label' => get_string('model', 'mod_imageblog'),
    ]);
    $loadermap = ['gltf' => 'GLTFLoader', 'stl' => 'STLLoader', 'ply' => 'PLYLoader', 'obj' => 'OBJLoader'];
    $loaderurl = function ($name) {
        return (new moodle_url('/mod/imageblog/thirdparty/three/js/loaders/' . $name . '.js'))->out(false);
    };
    $threejs = (new moodle_url('/mod/imageblog/thirdparty/three/build/three.min.js'))->out(false);
    $controlsjs = (new moodle_url('/mod/imageblog/thirdparty/three/js/controls/OrbitControls.js'))->out(false);
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
    $modelfiles = get_file_storage()->get_area_files($context->id, 'mod_imageblog', 'model', 0, 'filepath, filename', false);
    foreach ($modelfiles as $cfile) {
        if ($cfile->get_pathnamehash() === $modelmainfile->get_pathnamehash()) {
            continue;
        }
        $full = ltrim($cfile->get_filepath(), '/') . $cfile->get_filename();
        $rel = ($mainprefix !== '' && strpos($full, $mainprefix) === 0)
            ? substr($full, strlen($mainprefix)) : $full;
        $companions[$rel] = imageblog_model_file_url($cfile)->out(false);
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
    var region = document.querySelector('[data-region=\"mod-imageblog-model\"]');
    if (!region || region.dataset.initialised === '1') {
        return;
    }
    var fail = function() {
        region.classList.add('mod-imageblog-model-fallback');
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

$casetags = core_tag_tag::get_item_tags('mod_imageblog', 'imageblog', $imageblog->id);
if ($casetags) {
    echo $OUTPUT->tag_list($casetags, get_string('casetags', 'mod_imageblog'), 'imageblog-tags');
}

$mydiagnosis = $DB->get_record('imageblog_diagnoses', ['imageblogid' => $imageblog->id, 'userid' => $USER->id]);

if (!empty($imageblog->revealed)) {
    echo $OUTPUT->heading(get_string('outcome', 'mod_imageblog'), 3);
    echo $OUTPUT->box(format_text($imageblog->revealtext, FORMAT_MOODLE), 'generalbox');
    if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_imageblog', s($mydiagnosis->diagnosis)));
        $grades = imageblog_get_user_grades($imageblog, $USER->id);
        if (isset($grades[$USER->id]) && $grades[$USER->id]->rawgrade !== null) {
            $a = format_float($grades[$USER->id]->rawgrade, 2) . ' / ' . $imageblog->grade;
            echo html_writer::tag('p', get_string('yourgrade', 'mod_imageblog', $a));
        }
    }

    // The best-answer bonus belongs to the built-in scoring engine; with an
    // advanced grading method active the teacher marks each submission instead
    // (rendered below, independent of the reveal).
    if ($canreveal && !$gradingcontroller) {
        echo $OUTPUT->heading(get_string('alldiagnoses', 'mod_imageblog'), 3);
        $alldiagnoses = $DB->get_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id], 'timecreated ASC');
        if (!$alldiagnoses) {
            echo html_writer::tag('p', get_string('nodiagnoses', 'mod_imageblog'));
        } else {
            $allgrades = imageblog_get_user_grades($imageblog);
            $besttable = new html_table();
            $besttable->head = [
                get_string('diagnosis', 'mod_imageblog'),
                get_string('grade'),
                get_string('bestanswer', 'mod_imageblog'),
            ];
            foreach ($alldiagnoses as $diag) {
                $isbestrow = ((int) $imageblog->bestdiagnosisid === (int) $diag->id);
                $gradecell = '-';
                if (isset($allgrades[$diag->userid]) && $allgrades[$diag->userid]->rawgrade !== null) {
                    $gradecell = format_float($allgrades[$diag->userid]->rawgrade, 2) . ' / ' . $imageblog->grade;
                }
                if ($isbestrow) {
                    $params = ['id' => $cm->id, 'setbest' => 0, 'sesskey' => sesskey()];
                    $url = new moodle_url('/mod/imageblog/view.php', $params);
                    $bestcell = html_writer::span(get_string('currentbest', 'mod_imageblog'), 'badge badge-success')
                        . ' ' . $OUTPUT->single_button($url, get_string('clearbest', 'mod_imageblog'));
                } else {
                    $params = ['id' => $cm->id, 'setbest' => $diag->id, 'sesskey' => sesskey()];
                    $url = new moodle_url('/mod/imageblog/view.php', $params);
                    $bestcell = $OUTPUT->single_button($url, get_string('markbest', 'mod_imageblog'));
                }
                $besttable->data[] = [s($diag->diagnosis), $gradecell, $bestcell];
            }
            echo html_writer::table($besttable);
        }
    }
} else {
    if ($mform) {
        echo $OUTPUT->heading(get_string('yourdiagnosisheading', 'mod_imageblog'), 3);
        $mform->display();
    } else if ($mydiagnosis) {
        echo html_writer::tag('p', get_string('yourdiagnosis', 'mod_imageblog', s($mydiagnosis->diagnosis)));
    }
    // With advanced grading the teacher may grade before revealing, so a reader
    // can already have a grade; show it to them as soon as it is awarded.
    if (!$canreveal && $gradingcontroller && $mydiagnosis) {
        $grades = imageblog_get_user_grades($imageblog, $USER->id);
        if (isset($grades[$USER->id]) && $grades[$USER->id]->rawgrade !== null) {
            $a = format_float($grades[$USER->id]->rawgrade, 2) . ' / ' . $imageblog->grade;
            echo html_writer::tag('p', get_string('yourgrade', 'mod_imageblog', $a));
        }
    }
    if ($canreveal) {
        $revealurl = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id, 'reveal' => 1, 'sesskey' => sesskey()]);
        echo $OUTPUT->single_button($revealurl, get_string('revealoutcome', 'mod_imageblog'));
    }
}

// Teacher grading with an advanced grading method (e.g. a rubric). This marking
// is independent of the reveal, so it is shown whenever a method is active.
if ($canreveal && $gradingcontroller) {
    echo $OUTPUT->heading(get_string('gradeheading', 'mod_imageblog'), 3);
    if (!$gradingcontroller->is_form_available()) {
        // The method is selected but the rubric has not been defined yet.
        echo $gradingcontroller->form_unavailable_notification();
    } else {
        $tograde = $DB->get_records('imageblog_diagnoses', ['imageblogid' => $imageblog->id], 'timecreated ASC');
        if (!$tograde) {
            echo html_writer::tag('p', get_string('nodiagnoses', 'mod_imageblog'));
        } else {
            $gradetable = new html_table();
            $gradetable->head = [
                get_string('diagnosis', 'mod_imageblog'),
                get_string('grade'),
                get_string('action'),
            ];
            foreach ($tograde as $diag) {
                if ($diag->rubricgrade !== null) {
                    $gradecell = format_float((float) $diag->rubricgrade, 2) . ' / ' . $imageblog->grade;
                    $label = get_string('editgrade', 'mod_imageblog');
                } else {
                    $gradecell = '-';
                    $label = get_string('gradediagnosis', 'mod_imageblog');
                }
                $gradeurl = new moodle_url('/mod/imageblog/grade.php', ['id' => $cm->id, 'userid' => $diag->userid]);
                $gradetable->data[] = [s($diag->diagnosis), $gradecell, $OUTPUT->single_button($gradeurl, $label, 'get')];
            }
            echo html_writer::table($gradetable);
        }
    }
}

// Questions and answers on the case.
echo $OUTPUT->heading(get_string('questionsheading', 'mod_imageblog'), 3);

if ($answerform) {
    // A teacher is answering a specific question: show it and the answer form.
    echo html_writer::tag('p', format_text($answerquestion->question, FORMAT_PLAIN), ['class' => 'font-italic']);
    $answerform->display();
} else {
    $questions = $DB->get_records('imageblog_questions', ['imageblogid' => $imageblog->id], 'timecreated ASC');
    if (!$questions) {
        echo html_writer::tag('p', get_string('noquestions', 'mod_imageblog'));
    } else {
        foreach ($questions as $question) {
            // Peers see questions anonymously; the asker and teachers see the name.
            if ($cananswer || (int) $question->userid === (int) $USER->id) {
                $asker = \core_user::get_user($question->userid);
                $askername = $asker ? fullname($asker) : get_string('participant', 'mod_imageblog');
            } else {
                $askername = get_string('participant', 'mod_imageblog');
            }

            echo html_writer::start_tag('div', ['class' => 'card mb-3']);
            echo html_writer::start_tag('div', ['class' => 'card-body']);
            echo html_writer::tag('h5', format_text($question->question, FORMAT_PLAIN), ['class' => 'card-title']);
            echo html_writer::tag('p', get_string('askedby', 'mod_imageblog', $askername), ['class' => 'text-muted']);

            if ($question->answer !== null && trim($question->answer) !== '') {
                echo html_writer::tag('div', format_text($question->answer, FORMAT_PLAIN), ['class' => 'alert alert-info mb-0']);
            } else {
                echo html_writer::tag('p', get_string('notanswered', 'mod_imageblog'), ['class' => 'text-muted font-italic']);
            }

            if ($cananswer) {
                $answered = $question->answer !== null && trim($question->answer) !== '';
                $label = $answered
                    ? get_string('editanswer', 'mod_imageblog')
                    : get_string('answerquestion', 'mod_imageblog');
                $url = new moodle_url('/mod/imageblog/view.php', ['id' => $cm->id, 'answer' => $question->id]);
                echo $OUTPUT->single_button($url, $label, 'get');
            }

            echo html_writer::end_tag('div');
            echo html_writer::end_tag('div');
        }
    }

    if ($questionform) {
        echo $OUTPUT->heading(get_string('askquestion', 'mod_imageblog'), 4);
        $questionform->display();
    }
}

echo $OUTPUT->footer();
