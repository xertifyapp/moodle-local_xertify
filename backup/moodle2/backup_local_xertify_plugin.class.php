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
 * Backup support for Xertify course configuration.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backs up course-scoped Xertify configuration.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_xertify_plugin extends backup_local_plugin {
    /**
     * Define course backup structure.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../id');
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $config = new backup_nested_element('courseconfig', ['id'], [
            'enabled',
            'templateid',
            'groupid',
            'idtype',
            'apitoken',
            'emitrule',
            'dynamicdata',
            'notification_sms',
            'notification_email',
            'notification_whatsapp',
            'timecreated',
            'timemodified',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($config);

        $config->set_source_table('local_xertify_course', ['courseid' => backup::VAR_COURSEID]);

        return $plugin;
    }
}
