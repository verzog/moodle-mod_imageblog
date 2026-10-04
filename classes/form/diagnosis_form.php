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

namespace mod_imageblog\form;

/**
 * Form for a reader to submit or update their diagnosis on a case.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnosis_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('textarea', 'diagnosis', get_string('diagnosis', 'mod_imageblog'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('diagnosis', PARAM_TEXT);
        $mform->addRule('diagnosis', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('submitdiagnosis', 'mod_imageblog'));
    }
}
