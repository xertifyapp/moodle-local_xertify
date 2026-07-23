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
 * Site administration settings for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_xertify', get_string('pluginname', 'local_xertify'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_xertify/apiurl',
        get_string('apiurl', 'local_xertify'),
        get_string('apiurl_desc', 'local_xertify'),
        'https://api.xertify.co/v4createRequest',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_xertify/apitoken',
        get_string('apitoken', 'local_xertify'),
        get_string('apitoken_desc', 'local_xertify'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_xertify/defaultidtype',
        get_string('defaultidtype', 'local_xertify'),
        get_string('defaultidtype_desc', 'local_xertify'),
        '1',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_xertify/dryrun',
        get_string('dryrun', 'local_xertify'),
        get_string('dryrun_desc', 'local_xertify'),
        1
    ));
}
