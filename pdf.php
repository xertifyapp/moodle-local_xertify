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
 * Secure PDF delivery endpoint for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$issueid = required_param('issueid', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

require_login();

$service = new \local_xertify\local\certificate_service();
$certificate = $service->get_by_id($issueid);
$course = $DB->get_record('course', ['id' => $certificate['courseid']], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);

$response = $service->get_pdf($issueid, current_language(), (int)$course->id, $context);
if (!$response['ok']) {
    throw new moodle_exception('pdfserviceerror', 'local_xertify');
}

// The PDF exists only in memory for this request. It is not persisted in Moodle.
send_file(
    $response['pdf'],
    $response['filename'],
    0,
    0,
    true,
    !empty($download),
    'application/pdf'
);
