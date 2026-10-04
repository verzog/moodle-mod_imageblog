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
 * Unit tests for the context-neutral case scoring engine.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_imageblog\local\case_engine
 */
final class case_engine_test extends \basic_testcase {
    /**
     * An exact match scores full marks.
     */
    public function test_exact_match_scores_full(): void {
        $this->assertEqualsWithDelta(1.0, case_engine::score_fraction('Pneumonia', 'pneumonia'), 0.0001);
    }

    /**
     * Matching ignores surrounding whitespace and letter case.
     */
    public function test_trimmed_case_insensitive_match_scores_full(): void {
        $this->assertEqualsWithDelta(1.0, case_engine::score_fraction('  PNEUMONIA  ', 'pneumonia'), 0.0001);
    }

    /**
     * A non-matching but non-empty submission earns participation credit.
     */
    public function test_nonmatching_submission_scores_participation(): void {
        $this->assertEqualsWithDelta(0.5, case_engine::score_fraction('Asthma', 'pneumonia'), 0.0001);
    }

    /**
     * An empty (or whitespace-only) submission scores zero.
     */
    public function test_empty_submission_scores_zero(): void {
        $this->assertEqualsWithDelta(0.0, case_engine::score_fraction('   ', 'pneumonia'), 0.0001);
    }

    /**
     * With no expected answer configured, any submission earns participation credit.
     */
    public function test_missing_expected_answer_scores_participation(): void {
        $this->assertEqualsWithDelta(0.5, case_engine::score_fraction('Anything', ''), 0.0001);
    }
}
