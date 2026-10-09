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
 * The main settings form for an image blog instance.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/imageblog/lib.php');

/**
 * Instance settings form for the image blog activity.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_imageblog_mod_form extends moodleform_mod {
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

        $mform->addElement('header', 'casehdr', get_string('casesettings', 'mod_imageblog'));

        $mform->addElement('textarea', 'casequestion', get_string('casequestion', 'mod_imageblog'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('casequestion', PARAM_TEXT);
        $mform->addRule('casequestion', null, 'required', null, 'client');

        $mform->addElement('text', 'correctdiagnosis', get_string('correctdiagnosis', 'mod_imageblog'), ['size' => 64]);
        $mform->setType('correctdiagnosis', PARAM_TEXT);
        $mform->addRule('correctdiagnosis', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('correctdiagnosis', 'correctdiagnosis', 'mod_imageblog');

        $mform->addElement('textarea', 'revealtext', get_string('revealtext', 'mod_imageblog'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('revealtext', PARAM_TEXT);

        // Optional 360 degree panorama image, shown as an interactive viewer on the case.
        $mform->addElement('advcheckbox', 'haspanorama', get_string('haspanorama', 'mod_imageblog'));
        $mform->setType('haspanorama', PARAM_BOOL);
        $mform->addHelpButton('haspanorama', 'haspanorama', 'mod_imageblog');

        $mform->addElement(
            'filemanager',
            'panorama_image',
            get_string('panorama', 'mod_imageblog'),
            null,
            imageblog_panorama_filemanager_options()
        );
        $mform->addHelpButton('panorama_image', 'panorama', 'mod_imageblog');
        $mform->hideIf('panorama_image', 'haspanorama', 'notchecked');

        // Optional 3D model, shown as an interactive viewer on the case.
        $mform->addElement('advcheckbox', 'hasmodel', get_string('hasmodel', 'mod_imageblog'));
        $mform->setType('hasmodel', PARAM_BOOL);
        $mform->addHelpButton('hasmodel', 'hasmodel', 'mod_imageblog');

        $mform->addElement(
            'filemanager',
            'model_file',
            get_string('model', 'mod_imageblog'),
            null,
            imageblog_model_filemanager_options()
        );
        $mform->addHelpButton('model_file', 'model', 'mod_imageblog');
        $mform->hideIf('model_file', 'hasmodel', 'notchecked');

        $levels = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
        $mform->addElement('select', 'casedifficulty', get_string('casedifficulty', 'mod_imageblog'), $levels);
        $mform->setType('casedifficulty', PARAM_INT);
        $mform->setDefault('casedifficulty', 1);
        $mform->addHelpButton('casedifficulty', 'casedifficulty', 'mod_imageblog');

        $mform->addElement('text', 'difficultyscale', get_string('difficultyscale', 'mod_imageblog'), ['size' => 32]);
        $mform->setType('difficultyscale', PARAM_TEXT);
        $mform->setDefault('difficultyscale', '1, 1.5, 2, 3, 5');
        $mform->addRule('difficultyscale', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('difficultyscale', 'difficultyscale', 'mod_imageblog');

        $mform->addElement('text', 'correctfactor', get_string('correctfactor', 'mod_imageblog'), ['size' => 8]);
        $mform->setType('correctfactor', PARAM_FLOAT);
        $mform->setDefault('correctfactor', 1.0);

        $mform->addElement('text', 'participationfactor', get_string('participationfactor', 'mod_imageblog'), ['size' => 8]);
        $mform->setType('participationfactor', PARAM_FLOAT);
        $mform->setDefault('participationfactor', 0.5);

        $mform->addElement('text', 'bestfactor', get_string('bestfactor', 'mod_imageblog'), ['size' => 8]);
        $mform->setType('bestfactor', PARAM_FLOAT);
        $mform->setDefault('bestfactor', 1.0);
        $mform->addHelpButton('bestfactor', 'bestfactor', 'mod_imageblog');

        $mform->addElement(
            'tags',
            'casetags',
            get_string('casetags', 'mod_imageblog'),
            ['itemtype' => 'imageblog', 'component' => 'mod_imageblog']
        );
        $mform->addHelpButton('casetags', 'casetags', 'mod_imageblog');

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
            get_string('completionsubmit', 'mod_imageblog')
        );
        $mform->addHelpButton($completionsubmitel, 'completionsubmit', 'mod_imageblog');

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
                'mod_imageblog',
                'imageblog',
                $this->current->id
            );
        }

        // Prime the panorama filemanager from the stored file area and reflect
        // whether an image is present in the toggle that gates the uploader.
        $draftitemid = file_get_submitted_draft_itemid('panorama_image');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_imageblog',
            'panorama',
            0,
            imageblog_panorama_filemanager_options()
        );
        $defaultvalues['panorama_image'] = $draftitemid;

        $haspanorama = 0;
        if (!empty($this->current->id)) {
            $fs = get_file_storage();
            if ($fs->get_area_files($this->context->id, 'mod_imageblog', 'panorama', 0, 'id', false)) {
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
            'mod_imageblog',
            'model',
            0,
            imageblog_model_filemanager_options()
        );
        $defaultvalues['model_file'] = $modeldraftid;

        $hasmodel = 0;
        if (!empty($this->current->id)) {
            $fs = get_file_storage();
            if ($fs->get_area_files($this->context->id, 'mod_imageblog', 'model', 0, 'id', false)) {
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
            $errors['grade'] = get_string('gradescalesnotsupported', 'mod_imageblog');
        }

        // Guard the expected diagnosis against the 255-character column limit
        // server-side (the client rule can be bypassed).
        if (isset($data['correctdiagnosis']) && core_text::strlen($data['correctdiagnosis']) > 255) {
            $errors['correctdiagnosis'] = get_string('maximumchars', '', 255);
        }

        // Apply the same server-side length guard to the difficulty scale.
        if (isset($data['difficultyscale']) && core_text::strlen($data['difficultyscale']) > 255) {
            $errors['difficultyscale'] = get_string('maximumchars', '', 255);
        }

        // Scoring factors are fractions of full marks; a negative factor is
        // meaningless (the engine floors it to zero), so reject it outright.
        foreach (['participationfactor', 'correctfactor', 'bestfactor'] as $factorfield) {
            if (isset($data[$factorfield]) && (float) $data[$factorfield] < 0) {
                $errors[$factorfield] = get_string('factornotnegative', 'mod_imageblog');
            }
        }

        return $errors;
    }
}
