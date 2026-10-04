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

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
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

        return $errors;
    }
}
