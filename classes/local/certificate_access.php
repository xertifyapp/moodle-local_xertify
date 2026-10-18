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
 * Ownership checks shared by certificate consumers.
 *
 * Callers remain responsible for require_login and any activity restrictions.
 * The context must belong to the expected course; it is never trusted as an
 * alternative source of authority for an issue from a different course.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_access {
    /**
     * Authorize the current actor to read a user's certificate in a course.
     *
     * @param int $courseid Expected course ID.
     * @param int $userid Certificate owner, not the acting user.
     * @param \context|null $context Course or module context; defaults to the course.
     * @return \context Validated context.
     */
    public static function require_user(int $courseid, int $userid, ?\context $context = null): \context {
        global $USER;

        if (!isloggedin() || isguestuser() || $courseid <= 0 || $userid <= 0) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }

        $context = $context ?? \context_course::instance($courseid);
        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }
        $coursecontext = $context->get_course_context();
        if ((int)$coursecontext->instanceid !== $courseid) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }
        if ((int)$USER->id !== $userid) {
            require_capability('local/xertify:manage', $context);
        }
        return $context;
    }

    /**
     * Check an issue loaded by trusted server code against its expected course.
     *
     * @param \stdClass $issue Persisted issue, never a browser-provided object.
     * @param int $courseid Expected course ID.
     * @param \context|null $context Course or module context.
     * @return \context Validated context.
     */
    public static function require_issue(\stdClass $issue, int $courseid, ?\context $context = null): \context {
        if ((int)$issue->courseid !== $courseid) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }
        return self::require_user($courseid, (int)$issue->userid, $context);
    }
}
