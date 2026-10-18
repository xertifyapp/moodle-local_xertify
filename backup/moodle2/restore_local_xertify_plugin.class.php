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

/**
 * Restore support for Xertify course configuration.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores course-scoped Xertify configuration.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_xertify_plugin extends restore_local_plugin {
    /**
     * Define course restore paths.
     *
     * @return array
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element($this->get_namefor('courseconfig'), $this->get_pathfor('/courseconfig')),
        ];
    }

    /**
     * Restore one course configuration record.
     *
     * @param array $data Restored data.
     * @return void
     */
    public function process_local_xertify_courseconfig(array $data): void {
        global $DB;

        $courseid = $this->task->get_courseid();
        // The destination owns its integration, including deliberately disabled configurations.
        // This also protects merge/delete-content restores and repeated plugin records.
        if ($DB->record_exists('local_xertify_course', ['courseid' => $courseid])) {
            return;
        }

        $data = (object)$data;
        $now = time();

        // Explicit allowlist: legacy tokens and unknown fields are never imported.
        // Empty override plus disabled issuing requires administrator review before activation.
        $record = (object)[
            'courseid' => $courseid,
            'enabled' => 0,
            'templateid' => (string)($data->templateid ?? ''),
            'groupid' => (string)($data->groupid ?? ''),
            'idtype' => (string)($data->idtype ?? ''),
            'apitoken' => '',
            'emitrule' => (string)($data->emitrule ?? 'course_completed'),
            'dynamicdata' => (string)($data->dynamicdata ?? ''),
            'notification_sms' => (int)($data->notification_sms ?? 0),
            'notification_email' => (int)($data->notification_email ?? 0),
            'notification_whatsapp' => (int)($data->notification_whatsapp ?? 0),
            'timecreated' => !empty($data->timecreated) ? (int)$data->timecreated : $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('local_xertify_course', $record);
    }
}
