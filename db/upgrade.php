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

    if ($oldversion < 2026091500) {
        $table = new xmldb_table('local_xertify_issue');

        $generationid = new xmldb_field(
            'generationid',
            XMLDB_TYPE_CHAR,
            '64',
            null,
            null,
            null,
            null,
            'responsebody'
        );

        if (!$dbman->field_exists($table, $generationid)) {
            $dbman->add_field($table, $generationid);
        }

        $certificatecode = new xmldb_field(
            'certificatecode',
            XMLDB_TYPE_CHAR,
            '64',
            null,
            null,
            null,
            null,
            'generationid'
        );

        if (!$dbman->field_exists($table, $certificatecode)) {
            $dbman->add_field($table, $certificatecode);
        }

        $certificateurl = new xmldb_field(
            'certificateurl',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'certificatecode'
        );

        if (!$dbman->field_exists($table, $certificateurl)) {
            $dbman->add_field($table, $certificateurl);
        }

        // Recover certificate metadata from responses already stored by
        // previous versions and remove embedded PDF Base64 where present.
        $records = $DB->get_recordset_select(
            'local_xertify_issue',
            'responsebody IS NOT NULL AND responsebody <> ?',
            [''],
            '',
            'id,responsebody'
        );

        foreach ($records as $record) {
            $decoded = json_decode($record->responsebody, true);
            if (!is_array($decoded)) {
                continue;
            }

            $update = (object)['id' => $record->id];
            $changed = false;

            if (!empty($decoded['id']) && is_scalar($decoded['id'])) {
                $update->generationid = substr((string)$decoded['id'], 0, 64);
                $changed = true;
            }

            if (!empty($decoded['certificates']) && is_array($decoded['certificates'])) {
                $first = reset($decoded['certificates']);

                if (is_array($first)) {
                    if (!empty($first['codeCertificate'])) {
                        $update->certificatecode = substr(
                            (string)$first['codeCertificate'],
                            0,
                            64
                        );
                        $changed = true;
                    }

                    if (!empty($first['certificateUrl'])) {
                        $update->certificateurl = (string)$first['certificateUrl'];
                        $changed = true;
                    }
                }

                foreach ($decoded['certificates'] as &$certificate) {
                    if (is_array($certificate)) {
                        unset($certificate['file']);
                    }
                }
                unset($certificate);

                $sanitized = json_encode(
                    $decoded,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );

                if ($sanitized !== false) {
                    $update->responsebody = $sanitized;
                    $changed = true;
                }
            }

            if ($changed) {
                $DB->update_record('local_xertify_issue', $update);
            }
        }

        $records->close();

        upgrade_plugin_savepoint(true, 2026091500, 'local', 'xertify');
    }

    return true;
}
