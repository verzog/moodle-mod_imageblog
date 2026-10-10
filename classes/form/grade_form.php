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
 * Form for a teacher to mark one submitted diagnosis, using either simple direct
 * grading (a point value) or the active advanced grading method (e.g. a rubric).
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_form extends \moodleform {
    /**
     * Define the form fields: the grading control plus the identifiers needed
     * to persist the grade against the right submission.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $gradinginstance = $this->_customdata['gradinginstance'] ?? null;
        $maxgrade = $this->_customdata['maxgrade'] ?? 0;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        // The submission's timemodified when the form was rendered, so the save
        // can detect a student edit that happened in the meantime.
        $mform->addElement('hidden', 'submissiontime');
        $mform->setType('submissiontime', PARAM_INT);

        if ($gradinginstance) {
            // Advanced grading: render the method's control (rubric, marking guide).
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
        } else {
            // Simple direct grading: a point value out of the activity maximum.
            $mform->addElement('text', 'grade', get_string('gradeoutof', 'mod_diagnosis', $maxgrade), ['size' => 8]);
            $mform->setType('grade', PARAM_RAW);
        }

        $this->add_action_buttons(true, get_string('savegrade', 'mod_diagnosis'));
    }

    /**
     * Reject a simple grade outside the range 0..maximum.
     *
     * @param array $data the submitted values
     * @param array $files the submitted files
     * @return array field name => error string
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Only the simple-grading branch has a 'grade' text field to validate.
        if (array_key_exists('grade', $data) && trim((string) $data['grade']) !== '') {
            // Strict parse: unformat_float() returns false for malformed input
            // (e.g. "abc", "42oops") only when its strict flag is set; without it
            // such values are silently coerced to a number.
            $value = unformat_float($data['grade'], true);
            $maxgrade = $this->_customdata['maxgrade'] ?? 0;
            if ($value === false) {
                $errors['grade'] = get_string('gradenotnumeric', 'mod_diagnosis');
            } else if ($value < 0 || $value > $maxgrade) {
                $errors['grade'] = get_string('gradeoutofrange', 'mod_diagnosis', $maxgrade);
            }
        }

        return $errors;
    }
}
