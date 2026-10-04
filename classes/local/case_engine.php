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
 * Context-neutral scoring for clinical cases.
 *
 * This is the shared engine the plan calls for: it takes plain values and
 * returns a score fraction, with no dependency on course, module or gradebook
 * state. Both this activity and (later) the site-wide local_imageblog blog can
 * call it. The walking skeleton keeps the rule deliberately simple; difficulty
 * multipliers and a best-answer bonus will layer on top of this fraction.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_engine {
    /** @var float Fraction of full marks awarded for a submitted but non-matching diagnosis. */
    const PARTICIPATION_FRACTION = 0.5;

    /**
     * Score a diagnosis against the expected answer as a fraction of full marks.
     *
     * An exact (case-insensitive, trimmed) match scores full marks; any other
     * non-empty submission earns participation credit; an empty submission
     * scores zero.
     *
     * @param string $diagnosis the reader's submitted diagnosis
     * @param string $correct the expected diagnosis (may be empty when unset)
     * @return float a fraction between 0.0 and 1.0 inclusive
     */
    public static function score_fraction(string $diagnosis, string $correct): float {
        $diagnosis = trim($diagnosis);
        if ($diagnosis === '') {
            return 0.0;
        }

        $correct = trim($correct);
        if ($correct !== '' && \core_text::strtolower($diagnosis) === \core_text::strtolower($correct)) {
            return 1.0;
        }

        return self::PARTICIPATION_FRACTION;
    }
}
