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

namespace mod_diagnosis\grades;

use core_grades\local\gradeitem\itemnumber_mapping;
use core_grades\local\gradeitem\advancedgrading_mapping;

/**
 * Grade item mappings for the diagnosis activity.
 *
 * The activity has one grade item (itemnumber 0), the "submissions" area, which
 * may be marked with an advanced grading method such as a rubric. Declaring the
 * area here lets core offer the per-activity grading-method selector and route
 * advanced grading through the "submissions" area.
 *
 * @package    mod_diagnosis
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeitems implements advancedgrading_mapping, itemnumber_mapping {
    /**
     * Map the single grade itemnumber to its area name.
     *
     * @return array itemnumber => area name
     */
    public static function get_itemname_mapping_for_component(): array {
        return [
            0 => 'submissions',
        ];
    }

    /**
     * The areas that support an advanced grading method.
     *
     * @return array the advanced-grading area names
     */
    public static function get_advancedgrading_itemnames(): array {
        return [
            'submissions',
        ];
    }
}
