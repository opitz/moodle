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
 * Contains the class for fetching the important dates in mod_quiz for a given module instance and a user.
 *
 * @package   mod_quiz
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_quiz;

use core\activity_dates;
use mod_quiz\local\quiz_overrides_cache_manager;

/**
 * Class for fetching the important dates in mod_quiz for a given module instance and a user.
 *
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {

    /** @var int|null timeclose the activity closing date */
    private ?int $timeclose;

    /**
     * Returns a list of important dates in mod_quiz
     *
     * @return array
     */
    protected function get_dates(): array {
        $quiz = $this->cm->get_instance_record();
        $timeopen = !empty($quiz->timeopen) ? (int) $quiz->timeopen : null;
        $timeclose = !empty($quiz->timeclose) ? (int) $quiz->timeclose : null;
        $baseopen = $timeopen;
        $baseclose = $timeclose;
        $overrides = quiz_overrides_cache_manager::get_overrides((int) $this->cm->instance, $this->userid);

        foreach ($overrides as $override) {
            $overrideopen = $override->timeopen ?? $baseopen;
            $overrideclose = $override->timeclose ?? $baseclose;

            if (isset($override->timeclose) && empty($override->timeclose)) {
                $timeopen = $overrideopen;
                $timeclose = $override->timeclose;
                break;
            }

            if (!empty($overrideclose) && (empty($timeclose) || $overrideclose > $timeclose)) {
                $timeopen = $overrideopen;
                $timeclose = $overrideclose;
            }
        }

        $this->timeclose = $timeclose ? (int) $timeclose : null;

        $now = time();
        $dates = [];

        if ($timeopen) {
            $openlabelid = $timeopen > $now ? 'activitydate:opens' : 'activitydate:opened';
            $dates[] = [
                'dataid' => 'timeopen',
                'label' => get_string($openlabelid, 'core_course'),
                'timestamp' => (int) $timeopen,
            ];
        }

        if ($timeclose) {
            $closelabelid = $timeclose > $now ? 'activitydate:closes' : 'activitydate:closed';
            $dates[] = [
                'dataid' => 'timeclose',
                'label' => get_string($closelabelid, 'core_course'),
                'timestamp' => (int) $timeclose,
            ];
        }

        return $dates;
    }

    /**
     * Returns the dues date data, if any.
     * @return int|null the close timestamp or null if not set.
     */
    public function get_due_date(): ?int {
        if (!isset($this->timeclose)) {
            $this->get_dates();
        }
        return $this->timeclose;
    }
}
