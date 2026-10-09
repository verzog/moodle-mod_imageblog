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
$string['answer'] = 'Answer';
$string['answerquestion'] = 'Answer';
$string['answersaved'] = 'The answer has been saved.';
$string['askedby'] = 'Asked by {$a}';
$string['askquestion'] = 'Ask a question';
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
$string['casetags'] = 'Case tags';
$string['casetags_help'] = 'Tags that classify this case, for example by body system, imaging modality or specialty. Readers can follow a tag to find other cases that share it.';
$string['clearbest'] = 'Clear';
$string['completionsubmit'] = 'Student must submit a diagnosis';
$string['completionsubmit_help'] = 'When enabled, the activity is marked complete once the student has submitted a diagnosis.';
$string['completionsubmitdesc'] = 'Submit a diagnosis';
$string['correctdiagnosis'] = 'Expected diagnosis';
$string['correctdiagnosis_help'] = 'The diagnosis treated as correct when scoring. A submission that matches this (ignoring case and surrounding spaces) earns the correct-answer factor; any other submission earns the participation factor. Both are then scaled by the case difficulty.';
$string['correctfactor'] = 'Correct-answer factor';
$string['currentbest'] = 'Current best';
$string['diagnosis'] = 'Your diagnosis';
$string['diagnosissaved'] = 'Your diagnosis has been saved.';
$string['difficultyscale'] = 'Difficulty scale';
$string['difficultyscale_help'] = 'Comma-separated multipliers, one per difficulty level (level 1 first), e.g. "1, 1.5, 2, 3, 5". The multiplier for the selected case difficulty scales the score before the maximum-grade cap.';
$string['editanswer'] = 'Edit answer';
$string['factornotnegative'] = 'The factor cannot be negative.';
$string['gradescalesnotsupported'] = 'This activity grades on points only. Choose "Point" and set a maximum grade.';
$string['hasmodel'] = 'Include a 3D model';
$string['hasmodel_help'] = 'Tick to add an interactive 3D model to the case. Leave it unticked to keep the model uploader hidden and to remove any model already uploaded.';
$string['haspanorama'] = 'Include a 360° panorama';
$string['haspanorama_help'] = 'Tick to add an interactive 360° panorama to the case. Leave it unticked to keep the panorama uploader hidden and to remove any image already uploaded.';
$string['imageblog:addinstance'] = 'Add a new image blog activity';
$string['imageblog:answerquestion'] = 'Answer a question on a case';
$string['imageblog:askquestion'] = 'Ask a question about a case';
$string['imageblog:reveal'] = 'Reveal the case outcome';
$string['imageblog:submit'] = 'Submit a diagnosis';
$string['imageblog:view'] = 'View an image blog activity';
$string['markbest'] = 'Mark as best';
$string['messagebody_outcomerevealed'] = 'The outcome of the case "{$a->name}" in {$a->course} has been revealed, and your diagnosis has been graded.';
$string['messagebody_outcomerevealed_nograde'] = 'The outcome of the case "{$a->name}" in {$a->course} has been revealed.';
$string['messagebody_questionanswered'] = 'Your question on the case "{$a->name}" in {$a->course} has been answered.';
$string['messagebody_questionposted'] = 'A reader asked a question on the case "{$a->name}" in {$a->course}.';
$string['messageprovider:outcomerevealed'] = 'Case outcome revealed';
$string['messageprovider:questionanswered'] = 'Your question was answered';
$string['messageprovider:questionposted'] = 'New question on a case';
$string['messagesubject_outcomerevealed'] = 'Case outcome revealed: {$a->name}';
$string['messagesubject_questionanswered'] = 'Your question was answered';
$string['messagesubject_questionposted'] = 'New question on {$a->name}';
$string['model'] = '3D model files';
$string['model_help'] = 'Optional 3D model shown as an interactive viewer on the case: drag to rotate, scroll or pinch to zoom. Upload a main model file (glTF/GLB, STL, PLY or OBJ, up to 50 MB each) and, when needed, its companion files alongside it: a glTF may reference a .bin buffer and texture images, and an OBJ may ship a .mtl material library with textures. Keep each companion at the relative path the model expects (subfolders are allowed); a binary GLB needs none. The first recognised model file is used as the main model.';
$string['modelunavailable'] = 'The 3D model could not be loaded.';
$string['modulename'] = 'Image blog';
$string['modulename_help'] = 'The image blog activity presents a clinical case. Readers submit a diagnosis, and once the outcome is revealed they receive a grade based on their answer.';
$string['modulenameplural'] = 'Image blogs';
$string['nodiagnoses'] = 'No diagnoses have been submitted yet.';
$string['noinstances'] = 'There are no image blog activities in this course.';
$string['noquestions'] = 'No questions have been asked yet.';
$string['notanswered'] = 'Awaiting an answer.';
$string['outcome'] = 'Outcome';
$string['outcomerevealed'] = 'The case outcome has been revealed and grades have been awarded.';
$string['panorama'] = '360° panorama image';
$string['panorama_help'] = 'Optional equirectangular (2:1) image rendered as an interactive 360° viewer on the case. Drag to look around; pinch or scroll to zoom. JPEG or PNG up to 20 MB.';
$string['panoramaunavailable'] = 'The 360° panorama could not be loaded.';
$string['participant'] = 'A participant';
$string['participationfactor'] = 'Participation factor';
$string['pluginadministration'] = 'Image blog administration';
$string['pluginname'] = 'Image blog';
$string['privacy:metadata:imageblog_diagnoses'] = 'Diagnoses submitted by readers on a case.';
$string['privacy:metadata:imageblog_diagnoses:diagnosis'] = 'The diagnosis text the reader submitted.';
$string['privacy:metadata:imageblog_diagnoses:timecreated'] = 'The time the diagnosis was first submitted.';
$string['privacy:metadata:imageblog_diagnoses:timemodified'] = 'The time the diagnosis was last updated.';
$string['privacy:metadata:imageblog_diagnoses:userid'] = 'The user who submitted the diagnosis.';
$string['privacy:metadata:imageblog_questions'] = 'Questions readers asked on a case, and the teacher answers.';
$string['privacy:metadata:imageblog_questions:answer'] = 'The answer text, once the question has been answered.';
$string['privacy:metadata:imageblog_questions:answeredby'] = 'The user who answered the question.';
$string['privacy:metadata:imageblog_questions:question'] = 'The question text the reader asked.';
$string['privacy:metadata:imageblog_questions:timeanswered'] = 'The time the question was answered.';
$string['privacy:metadata:imageblog_questions:timecreated'] = 'The time the question was asked.';
$string['privacy:metadata:imageblog_questions:timemodified'] = 'The time the question or answer was last changed.';
$string['privacy:metadata:imageblog_questions:userid'] = 'The user who asked the question.';
$string['question'] = 'Your question';
$string['questionasked'] = 'Your question has been posted.';
$string['questionsheading'] = 'Questions & answers';
$string['revealoutcome'] = 'Reveal outcome';
$string['revealtext'] = 'Outcome and explanation';
$string['saveanswer'] = 'Save answer';
$string['submitdiagnosis'] = 'Submit diagnosis';
$string['tagarea_imageblog'] = 'Image blog cases';
$string['yourdiagnosis'] = 'Your diagnosis: {$a}';
$string['yourdiagnosisheading'] = 'Your diagnosis';
$string['yourgrade'] = 'Your grade: {$a}';
