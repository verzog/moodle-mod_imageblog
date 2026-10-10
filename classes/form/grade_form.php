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
 * Form for a teacher to grade one submitted diagnosis with the active advanced
 * grading method (e.g. a rubric).
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_form extends \moodleform {
    /**
     * Define the form fields: the grading element plus the identifiers needed
     * to persist the grade against the right submission.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $gradinginstance = $this->_customdata['gradinginstance'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement(
            'grading',
            'advancedgrading',
            get_string('gradenoun'),
            ['gradinginstance' => $gradinginstance]
        );

        // Carry the created grading instance id so the submission reuses it
        // rather than orphaning a fresh incomplete instance on each page load.
        $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
        $mform->setType('advancedgradinginstanceid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savegrade', 'mod_imageblog'));
    }
}
