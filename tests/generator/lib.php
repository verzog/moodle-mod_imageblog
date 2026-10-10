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
 * Test data generator for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates diagnosis activity instances for tests.
 */
class mod_diagnosis_generator extends testing_module_generator {
    /**
     * Create a new diagnosis instance, filling in sensible defaults for the case fields.
     *
     * @param array|stdClass|null $record instance overrides (course is required)
     * @param array|null $options generator options passed through to the parent
     * @return stdClass the created instance record
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'casequestion' => 'What is the most likely diagnosis?',
            'correctdiagnosis' => 'pneumonia',
            'revealtext' => 'The outcome was pneumonia.',
            'revealed' => 0,
            'grade' => 100,
            'completionsubmit' => 0,
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
