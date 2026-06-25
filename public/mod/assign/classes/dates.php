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
 * Contains the class for fetching the important dates in mod_assign for a given module instance and a user.
 *
 * @package   mod_assign
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_assign;

use core\activity_dates;

/**
 * Class for fetching the important dates in mod_assign for a given module instance and a user.
 *
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {

    /** @var int|null $timedue the activity due date */
    private ?int $timedue;

    /**
     * Returns a list of important dates in mod_assign
     *
     * @return array
     */
    protected function get_dates(): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $this->timedue = null;

        $course = get_course((int) $this->cm->course);
        $context = \context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, $course);

        $instance = $assign->get_instance($this->userid);
        $timeopen = $instance->allowsubmissionsfromdate ?? null;
        $timedue = $instance->duedate ?? null;
        $baseopen = $timeopen;
        $basedue = $timedue;

        $cache = \cache::make('mod_assign', 'overrides');
        $useroverride = $cache->get("{$this->cm->instance}_u_{$this->userid}");
        $overrides = $useroverride ? [$useroverride] : [];

        $groups = groups_get_user_groups((int) $this->cm->course, $this->userid);
        if (!empty($groups[0])) {
            foreach ($groups[0] as $groupid) {
                $groupoverride = $cache->get("{$this->cm->instance}_g_{$groupid}");
                if ($groupoverride) {
                    $overrides[] = $groupoverride;
                }
            }
        }
        usort($overrides, static function(\stdClass $a, \stdClass $b) use ($baseopen, $basedue): int {
            $aunlimited = isset($a->duedate) && empty($a->duedate);
            $bunlimited = isset($b->duedate) && empty($b->duedate);
            if ($aunlimited !== $bunlimited) {
                return $aunlimited ? -1 : 1;
            }

            $adue = $a->duedate ?? $basedue;
            $bdue = $b->duedate ?? $basedue;
            $duecompare = ((int) ($bdue ?? 0)) <=> ((int) ($adue ?? 0));
            if ($duecompare) {
                return $duecompare;
            }

            $aopen = $a->allowsubmissionsfromdate ?? $baseopen;
            $bopen = $b->allowsubmissionsfromdate ?? $baseopen;
            $opencompare = ((int) ($aopen ?? 0)) <=> ((int) ($bopen ?? 0));
            if ($opencompare) {
                return $opencompare;
            }

            return ((int) ($a->id ?? 0)) <=> ((int) ($b->id ?? 0));
        });

        foreach ($overrides as $override) {
            $overrideopen = $override->allowsubmissionsfromdate ?? $baseopen;
            $overridedue = $override->duedate ?? $basedue;

            if (isset($override->duedate) && empty($override->duedate)) {
                $timeopen = $overrideopen;
                $timedue = $override->duedate;
                break;
            }

            if (!empty($overridedue) && (empty($timedue) || $overridedue > $timedue)) {
                $timeopen = $overrideopen;
                $timedue = $overridedue;
                continue;
            }

            if ($overridedue == $timedue && $overrideopen !== null && ($timeopen === null || $overrideopen < $timeopen)) {
                $timeopen = $overrideopen;
            }
        }

        $userflags = $assign->get_user_flags($this->userid, false);
        if (!empty($userflags->extensionduedate)) {
            $timedue = empty($timedue) ? $userflags->extensionduedate : max($timedue, $userflags->extensionduedate);
        }

        $now = \core\di::get(\core\clock::class)->time();
        $dates = [];

        if ($timeopen) {
            $openlabelid = $timeopen > $now ? 'activitydate:submissionsopen' : 'activitydate:submissionsopened';
            $date = [
                'dataid' => 'allowsubmissionsfromdate',
                'label' => get_string($openlabelid, 'mod_assign'),
                'timestamp' => (int) $timeopen,
            ];
            if ($course->relativedatesmode && $assign->can_view_grades()) {
                $date['relativeto'] = $course->startdate;
            }
            $dates[] = $date;
        }

        if ($timedue) {
            $this->timedue = (int) $timedue;
            $date = [
                'dataid' => 'duedate',
                'label' => get_string('activitydate:submissionsdue', 'mod_assign'),
                'timestamp' => $this->timedue,
            ];
            if ($course->relativedatesmode && $assign->can_view_grades()) {
                $date['relativeto'] = $course->startdate;
            }
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * Returns the dues date data, if any.
     * @return int|null the due date timestamp or null if not set.
     */
    public function get_due_date(): ?int {
        if (!isset($this->timedue)) {
            $this->get_dates();
        }
        return $this->timedue;
    }
}
