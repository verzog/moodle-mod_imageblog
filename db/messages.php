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
 * Message providers for mod_diagnosis.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [

    // Sent to readers who submitted a diagnosis when the teacher reveals the outcome.
    'outcomerevealed' => [
        'capability' => 'mod/diagnosis:view',
    ],

    // Sent to teachers who can answer when a reader posts a question.
    'questionposted' => [
        'capability' => 'mod/diagnosis:answerquestion',
    ],

    // Sent to the asker when their question is answered.
    'questionanswered' => [],
];
