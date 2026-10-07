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

use local_imageblog\local\scoring;

/**
 * Maps a diagnosis to a grade fraction using the shared scoring engine.
 *
 * This is the activity's thin adapter over \local_imageblog\local\scoring: it
 * decides the per-reason factor (a correct answer earns the correct factor, any
 * other submission the participation factor) and applies the case difficulty
 * multiplier, reusing the shared primitives rather than duplicating them.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grader {
    /**
     * Compute the grade fraction (0.0-1.0) earned for a diagnosis.
     *
     * An empty submission scores zero. A diagnosis the teacher marked best earns
     * the best-answer factor outright (the teacher's judgement overrides string
     * matching). Otherwise the factor is the correct factor when the submission
     * matches the expected diagnosis (case- and whitespace-insensitive), else the
     * participation factor. The factor is then scaled by the difficulty
     * multiplier and capped at full marks.
     *
     * @param string $diagnosis the reader's submitted diagnosis
     * @param string $correct the expected diagnosis (may be empty)
     * @param bool $isbest whether this diagnosis was marked best by the teacher
     * @param int $difficulty the 1-based case difficulty level
     * @param string $scale the comma-separated difficulty scale
     * @param float $participationfactor fraction for a submitted, incorrect diagnosis
     * @param float $correctfactor fraction for a correct diagnosis
     * @param float $bestfactor fraction for the best diagnosis
     * @return float the grade fraction, between 0.0 and 1.0 inclusive
     */
    public static function grade_fraction(
        string $diagnosis,
        string $correct,
        bool $isbest,
        int $difficulty,
        string $scale,
        float $participationfactor,
        float $correctfactor,
        float $bestfactor
    ): float {
        if (trim($diagnosis) === '') {
            return 0.0;
        }

        if ($isbest) {
            $factor = $bestfactor;
        } else {
            $iscorrect = $correct !== ''
                && \core_text::strtolower(trim($diagnosis)) === \core_text::strtolower(trim($correct));
            $factor = $iscorrect ? $correctfactor : $participationfactor;
        }

        $multiplier = scoring::difficulty_multiplier(scoring::parse_scale($scale), $difficulty);

        return min(1.0, scoring::hours(1.0, $multiplier, $factor));
    }
}
