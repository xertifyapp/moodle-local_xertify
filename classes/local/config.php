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
 * Configuration helpers for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /**
     * Get Xertify configuration for a course.
     *
     * @param int $courseid Moodle course ID.
     * @return \stdClass|null Course configuration record.
     */
    public static function get_course_config(int $courseid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_xertify_course', ['courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Get the configured Xertify API URL.
     *
     * @return string
     */
    public static function get_api_url(): string {
        global $CFG;
        $env = getenv('XERTIFY_API_URL');
        if (!empty($env)) {
            return $env;
        }
        if (!empty($CFG->local_xertify_api_url)) {
            return $CFG->local_xertify_api_url;
        }
        return (string)get_config('local_xertify', 'apiurl');
    }

    /**
     * Get the configured Xertify API token.
     *
     * @param \stdClass|null $courseconfig Course configuration record.
     * @return string
     */
    public static function get_api_token(?\stdClass $courseconfig = null): string {
        global $CFG;
        if ($courseconfig && !empty($courseconfig->apitoken)) {
            return $courseconfig->apitoken;
        }
        $env = getenv('XERTIFY_API_TOKEN');
        if (!empty($env)) {
            return $env;
        }
        if (!empty($CFG->local_xertify_api_token)) {
            return $CFG->local_xertify_api_token;
        }
        return (string)get_config('local_xertify', 'apitoken');
    }

    /**
     * Get the default document type.
     *
     * @return string
     */
    public static function get_default_idtype(): string {
        $value = (string)get_config('local_xertify', 'defaultidtype');
        return $value !== '' ? $value : '1';
    }

    /**
     * Check whether dry-run mode is enabled.
     *
     * @return bool
     */
    public static function is_dry_run(): bool {
        $env = getenv('XERTIFY_DRY_RUN');
        if ($env !== false && $env !== '') {
            return in_array(strtolower($env), ['1', 'true', 'yes'], true);
        }
        return (bool)get_config('local_xertify', 'dryrun');
    }
}
