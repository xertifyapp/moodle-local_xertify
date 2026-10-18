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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_xertify\local;

/**
 * Optional activity availability policy for legacy certificate routes.
 *
 * Uses only core APIs: the local plugin never requires module PHP files.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_access {
    /**
     * Find an accessible activity for the current actor, using OR semantics.
     *
     * @param int $courseid Course to inspect after course login.
     * @return \cm_info|false|null Accessible activity, denied, or legacy/admin mode.
     */
    public static function legacy_target(int $courseid) {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (has_capability('local/xertify:manage', \context_course::instance($courseid))) {
            return null;
        }
        $instances = get_fast_modinfo($courseid)->get_instances_of('xertifycertificate');
        if (!$instances) {
            return null;
        }
        foreach ($instances as $cm) {
            if ($cm->uservisible && has_capability('mod/xertifycertificate:view', $cm->context)) {
                return $cm;
            }
        }
        return false;
    }

    /**
     * Prevent direct legacy URLs from bypassing activity availability.
     *
     * @param int $courseid Authorized course.
     */
    public static function require_legacy_access(int $courseid): void {
        if (self::legacy_target($courseid) === false) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }
    }
}
