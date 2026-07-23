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

namespace local_xertify;

/**
 * Event observer callbacks for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Issue a Xertify certificate when a Moodle course is completed.
     *
     * @param \core\event\course_completed $event Course completed event.
     * @return void
     */
    public static function course_completed(\core\event\course_completed $event): void {
        $userid = $event->relateduserid ?: $event->userid;
        $courseid = $event->courseid;
        if (empty($userid) || empty($courseid)) {
            return;
        }

        \local_xertify\local\issuer::issue_for_course_completion((int)$courseid, (int)$userid, (int)$event->timecreated);
    }
}
