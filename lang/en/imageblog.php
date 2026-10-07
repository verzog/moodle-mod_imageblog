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
 * English language strings for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alldiagnoses'] = 'All diagnoses';
$string['bestanswer'] = 'Best answer';
$string['bestanswerlocked'] = 'Another change to the best answer is in progress. Please try again in a moment.';
$string['bestfactor'] = 'Best-answer factor';
$string['bestfactor_help'] = 'Fraction of full marks for the diagnosis the teacher marks as best. It overrides the correct and participation factors for that one submission, then is scaled by the case difficulty and capped at the maximum grade.';
$string['bestupdated'] = 'The best answer has been updated.';
$string['case'] = 'Clinical case';
$string['casealreadyrevealed'] = 'The case outcome was revealed before your diagnosis could be saved, so it was not recorded.';
$string['casedifficulty'] = 'Case difficulty';
$string['casedifficulty_help'] = 'The difficulty level of this case. A higher level applies a larger multiplier from the difficulty scale, so an answer on a harder case is worth proportionally more, up to the maximum grade.';
$string['casequestion'] = 'Case question';
$string['casesettings'] = 'Case';
$string['clearbest'] = 'Clear';
$string['correctdiagnosis'] = 'Expected diagnosis';
$string['correctdiagnosis_help'] = 'The diagnosis treated as correct when scoring. A submission that matches this (ignoring case and surrounding spaces) earns the correct-answer factor; any other submission earns the participation factor. Both are then scaled by the case difficulty.';
$string['correctfactor'] = 'Correct-answer factor';
$string['currentbest'] = 'Current best';
$string['diagnosis'] = 'Your diagnosis';
$string['diagnosissaved'] = 'Your diagnosis has been saved.';
$string['difficultyscale'] = 'Difficulty scale';
$string['difficultyscale_help'] = 'Comma-separated multipliers, one per difficulty level (level 1 first), e.g. "1, 1.5, 2, 3, 5". The multiplier for the selected case difficulty scales the score before the maximum-grade cap.';
$string['factornotnegative'] = 'The factor cannot be negative.';
$string['gradescalesnotsupported'] = 'This activity grades on points only. Choose "Point" and set a maximum grade.';
$string['imageblog:addinstance'] = 'Add a new image blog activity';
$string['imageblog:reveal'] = 'Reveal the case outcome';
$string['imageblog:submit'] = 'Submit a diagnosis';
$string['imageblog:view'] = 'View an image blog activity';
$string['markbest'] = 'Mark as best';
$string['modulename'] = 'Image blog';
$string['modulename_help'] = 'The image blog activity presents a clinical case. Readers submit a diagnosis, and once the outcome is revealed they receive a grade based on their answer.';
$string['modulenameplural'] = 'Image blogs';
$string['nodiagnoses'] = 'No diagnoses have been submitted yet.';
$string['noinstances'] = 'There are no image blog activities in this course.';
$string['outcome'] = 'Outcome';
$string['outcomerevealed'] = 'The case outcome has been revealed and grades have been awarded.';
$string['participationfactor'] = 'Participation factor';
$string['pluginadministration'] = 'Image blog administration';
$string['pluginname'] = 'Image blog';
$string['privacy:metadata:imageblog_diagnoses'] = 'Diagnoses submitted by readers on a case.';
$string['privacy:metadata:imageblog_diagnoses:diagnosis'] = 'The diagnosis text the reader submitted.';
$string['privacy:metadata:imageblog_diagnoses:timecreated'] = 'The time the diagnosis was first submitted.';
$string['privacy:metadata:imageblog_diagnoses:timemodified'] = 'The time the diagnosis was last updated.';
$string['privacy:metadata:imageblog_diagnoses:userid'] = 'The user who submitted the diagnosis.';
$string['revealoutcome'] = 'Reveal outcome';
$string['revealtext'] = 'Outcome and explanation';
$string['submitdiagnosis'] = 'Submit diagnosis';
$string['yourdiagnosis'] = 'Your diagnosis: {$a}';
$string['yourdiagnosisheading'] = 'Your diagnosis';
$string['yourgrade'] = 'Your grade: {$a}';
