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

namespace mod_imageblog\local;

/**
 * Tests for the grade-fraction mapping that consumes the shared scoring engine.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_imageblog\local\grader
 */
final class grader_test extends \basic_testcase {
    /** @var string A representative difficulty scale. */
    const SCALE = '1, 1.5, 2, 3, 5';

    /**
     * An empty submission scores zero regardless of settings.
     */
    public function test_empty_submission_scores_zero(): void {
        $f = grader::grade_fraction('  ', 'pneumonia', false, 1, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEquals(0.0, $f);
    }

    /**
     * A correct answer on a level-1 case earns the full correct factor.
     */
    public function test_correct_easy_scores_correct_factor(): void {
        $f = grader::grade_fraction('Pneumonia', 'pneumonia', false, 1, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEqualsWithDelta(1.0, $f, 0.0001);
    }

    /**
     * An incorrect but non-empty answer on a level-1 case earns the participation factor.
     */
    public function test_incorrect_easy_scores_participation_factor(): void {
        $f = grader::grade_fraction('Asthma', 'pneumonia', false, 1, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEqualsWithDelta(0.5, $f, 0.0001);
    }

    /**
     * The difficulty multiplier scales the fraction up (0.5 x 2.0 = 1.0 at level 3).
     */
    public function test_difficulty_multiplier_scales_score(): void {
        $f = grader::grade_fraction('Asthma', 'pneumonia', false, 3, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEqualsWithDelta(1.0, $f, 0.0001);
    }

    /**
     * The fraction is capped at full marks even when difficulty would exceed it.
     */
    public function test_fraction_capped_at_one(): void {
        $f = grader::grade_fraction('Pneumonia', 'pneumonia', false, 5, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEqualsWithDelta(1.0, $f, 0.0001);
    }

    /**
     * Matching ignores letter case and surrounding whitespace.
     */
    public function test_match_is_case_and_space_insensitive(): void {
        $f = grader::grade_fraction('  PNEUMONIA ', 'pneumonia', false, 1, self::SCALE, 0.5, 1.0, 1.0);
        $this->assertEqualsWithDelta(1.0, $f, 0.0001);
    }

    /**
     * A best-marked diagnosis earns the best factor, overriding the correctness check.
     */
    public function test_best_answer_uses_best_factor(): void {
        // Wrong against the expected string, but marked best with a 0.8 best factor.
        $f = grader::grade_fraction('Asthma', 'pneumonia', true, 1, self::SCALE, 0.5, 1.0, 0.8);
        $this->assertEqualsWithDelta(0.8, $f, 0.0001);
    }

    /**
     * The best factor is also scaled by difficulty and capped at full marks.
     */
    public function test_best_answer_scaled_and_capped(): void {
        $f = grader::grade_fraction('Asthma', 'pneumonia', true, 3, self::SCALE, 0.5, 1.0, 0.8);
        $this->assertEqualsWithDelta(1.0, $f, 0.0001);
    }
}
