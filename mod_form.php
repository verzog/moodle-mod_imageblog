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
 * The main settings form for an diagnosis instance.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/diagnosis/lib.php');

/**
 * Instance settings form for the diagnosis activity.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_diagnosis_mod_form extends moodleform_mod {
    /**
     * Define the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'casehdr', get_string('casesettings', 'mod_diagnosis'));

        $mform->addElement('textarea', 'casequestion', get_string('casequestion', 'mod_diagnosis'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('casequestion', PARAM_TEXT);
        $mform->addRule('casequestion', null, 'required', null, 'client');

        $mform->addElement('text', 'correctdiagnosis', get_string('correctdiagnosis', 'mod_diagnosis'), ['size' => 64]);
        $mform->setType('correctdiagnosis', PARAM_TEXT);
        $mform->addRule('correctdiagnosis', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('correctdiagnosis', 'correctdiagnosis', 'mod_diagnosis');

        $mform->addElement('textarea', 'revealtext', get_string('revealtext', 'mod_diagnosis'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('revealtext', PARAM_TEXT);

        // Optional 360 degree panorama image, shown as an interactive viewer on the case.
        $mform->addElement('advcheckbox', 'haspanorama', get_string('haspanorama', 'mod_diagnosis'));
        $mform->setType('haspanorama', PARAM_BOOL);
        $mform->addHelpButton('haspanorama', 'haspanorama', 'mod_diagnosis');

        $mform->addElement(
            'filemanager',
            'panorama_image',
            get_string('panorama', 'mod_diagnosis'),
            null,
            diagnosis_panorama_filemanager_options()
        );
        $mform->addHelpButton('panorama_image', 'panorama', 'mod_diagnosis');
        $mform->hideIf('panorama_image', 'haspanorama', 'notchecked');

        // Optional 3D model, shown as an interactive viewer on the case.
        $mform->addElement('advcheckbox', 'hasmodel', get_string('hasmodel', 'mod_diagnosis'));
        $mform->setType('hasmodel', PARAM_BOOL);
        $mform->addHelpButton('hasmodel', 'hasmodel', 'mod_diagnosis');

        $mform->addElement(
            'filemanager',
            'model_file',
            get_string('model', 'mod_diagnosis'),
            null,
            diagnosis_model_filemanager_options()
        );
        $mform->addHelpButton('model_file', 'model', 'mod_diagnosis');
        $mform->hideIf('model_file', 'hasmodel', 'notchecked');

        $mform->addElement(
            'tags',
            'casetags',
            get_string('casetags', 'mod_diagnosis'),
            ['itemtype' => 'diagnosis', 'component' => 'mod_diagnosis']
        );
        $mform->addHelpButton('casetags', 'casetags', 'mod_diagnosis');

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Add the activity's custom completion rules to the form.
     *
     * @return array the names of the added rule elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $completionsubmitel = 'completionsubmit' . $suffix;

        $mform->addElement(
            'checkbox',
            $completionsubmitel,
            '',
            get_string('completionsubmit', 'mod_diagnosis')
        );
        $mform->addHelpButton($completionsubmitel, 'completionsubmit', 'mod_diagnosis');

        return [$completionsubmitel];
    }

    /**
     * Whether any of this activity's custom completion rules are enabled.
     *
     * @param array $data the submitted form data
     * @return bool true if a custom rule is selected
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionsubmit' . $suffix]);
    }

    /**
     * Load the instance's existing case tags into the form when editing.
     *
     * @param array $defaultvalues the default form values, passed by reference
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        if (!empty($this->current->id)) {
            $defaultvalues['casetags'] = \core_tag_tag::get_item_tags_array(
                'mod_diagnosis',
                'diagnosis',
                $this->current->id
            );
        }

        // Prime the panorama filemanager from the stored file area and reflect
        // whether an image is present in the toggle that gates the uploader.
        $draftitemid = file_get_submitted_draft_itemid('panorama_image');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_diagnosis',
            'panorama',
            0,
            diagnosis_panorama_filemanager_options()
        );
        $defaultvalues['panorama_image'] = $draftitemid;

        $haspanorama = 0;
        if (!empty($this->current->id)) {
            $fs = get_file_storage();
            if ($fs->get_area_files($this->context->id, 'mod_diagnosis', 'panorama', 0, 'id', false)) {
                $haspanorama = 1;
            }
        }
        $defaultvalues['haspanorama'] = $haspanorama;

        // Prime the 3D model filemanager from the stored file area and reflect
        // whether a model is present in the toggle that gates the uploader.
        $modeldraftid = file_get_submitted_draft_itemid('model_file');
        file_prepare_draft_area(
            $modeldraftid,
            $this->context->id,
            'mod_diagnosis',
            'model',
            0,
            diagnosis_model_filemanager_options()
        );
        $defaultvalues['model_file'] = $modeldraftid;

        $hasmodel = 0;
        if (!empty($this->current->id)) {
            $fs = get_file_storage();
            if ($fs->get_area_files($this->context->id, 'mod_diagnosis', 'model', 0, 'id', false)) {
                $hasmodel = 1;
            }
        }
        $defaultvalues['hasmodel'] = $hasmodel;
    }

    /**
     * Server-side validation.
     *
     * @param array $data submitted form data
     * @param array $files submitted files
     * @return array field name => error message for any invalid fields
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // The standard grading element offers scales (stored as a negative
        // grade value). This activity grades on points only, so reject a scale
        // selection rather than silently creating an ungraded activity.
        if (isset($data['grade']) && (int) $data['grade'] < 0) {
            $errors['grade'] = get_string('gradescalesnotsupported', 'mod_diagnosis');
        }

        // Guard the expected diagnosis against the 255-character column limit
        // server-side (the client rule can be bypassed).
        if (isset($data['correctdiagnosis']) && core_text::strlen($data['correctdiagnosis']) > 255) {
            $errors['correctdiagnosis'] = get_string('maximumchars', '', 255);
        }

        // The file picker accepts any type (Moodle cannot restrict to the 3D
        // extensions, which are not in its registry), so enforce the allow-list
        // here: reject anything that is neither a model nor a companion file,
        // and require at least one main model file (companions alone would save
        // but render nothing on the case).
        if (!empty($data['hasmodel']) && !empty($data['model_file'])) {
            $unaccepted = $this->draft_unaccepted_model_files((int) $data['model_file']);
            if ($unaccepted) {
                $errors['model_file'] = get_string('modelunacceptedfile', 'mod_diagnosis', implode(', ', $unaccepted));
            } else if (!$this->draft_has_main_model((int) $data['model_file'])) {
                $errors['model_file'] = get_string('modelnomainfile', 'mod_diagnosis');
            }
        }

        return $errors;
    }

    /**
     * Names of any files in the model draft area whose extension is not an
     * accepted 3D model or companion type.
     *
     * @param int $draftitemid the submitted draft area id
     * @return string[] the rejected file names, empty when all are accepted
     */
    protected function draft_unaccepted_model_files(int $draftitemid): array {
        global $USER;

        if (!$draftitemid) {
            return [];
        }
        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);
        $rejected = [];
        foreach ($draftfiles as $file) {
            if (!diagnosis_model_file_accepted($file->get_filename())) {
                $rejected[] = $file->get_filename();
            }
        }
        return $rejected;
    }

    /**
     * Whether a draft file area holds at least one recognised main-model file.
     *
     * @param int $draftitemid the submitted draft area id
     * @return bool true if a glTF/GLB/STL/PLY/OBJ file is present
     */
    protected function draft_has_main_model(int $draftitemid): bool {
        global $USER;

        if (!$draftitemid) {
            return false;
        }
        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);
        $modelexts = array_map(fn($ext) => ltrim($ext, '.'), diagnosis_model_extensions());
        foreach ($draftfiles as $file) {
            $ext = core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (in_array($ext, $modelexts, true)) {
                return true;
            }
        }
        return false;
    }
}
