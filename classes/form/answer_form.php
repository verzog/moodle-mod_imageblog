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

namespace mod_diagnosis\form;

/**
 * Form for a teacher to answer (or edit the answer to) a reader's question.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'answer');
        $mform->setType('answer', PARAM_INT);

        $mform->addElement('textarea', 'answertext', get_string('answer', 'mod_diagnosis'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('answertext', PARAM_TEXT);
        $mform->addRule('answertext', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('saveanswer', 'mod_diagnosis'));
    }

    /**
     * Reject an answer that is empty once surrounding whitespace is removed.
     *
     * @param array $data the submitted values
     * @param array $files the submitted files
     * @return array field name => error string
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['answertext'] ?? '') === '') {
            $errors['answertext'] = get_string('required');
        }
        return $errors;
    }
}
