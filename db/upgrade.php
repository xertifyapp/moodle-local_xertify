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
 * Upgrade steps for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_xertify plugin.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_xertify_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061602) {
        $table = new xmldb_table('local_xertify_course');

        $fields = [
            new xmldb_field('notification_sms', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'emitrule'),
            new xmldb_field('notification_email', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notification_sms'),
            new xmldb_field('notification_whatsapp', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notification_email'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026061602, 'local', 'xertify');
    }

    if ($oldversion < 2026072200) {
        $table = new xmldb_table('local_xertify_course');
        $field = new xmldb_field('dynamicdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'emitrule');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072200, 'local', 'xertify');
    }

    return true;
}
