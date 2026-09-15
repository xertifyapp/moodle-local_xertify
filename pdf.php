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

$issue = $DB->get_record(
    'local_xertify_issue',
    ['id' => $issueid],
    '*',
    MUST_EXIST
);

$course = $DB->get_record(
    'course',
    ['id' => $issue->courseid],
    '*',
    MUST_EXIST
);

$context = context_course::instance($course->id);

require_login($course);

if ((int)$issue->userid !== (int)$USER->id) {
    require_capability('local/xertify:manage', $context);
}

if ($issue->status !== 'issued' || empty($issue->certificatecode)) {
    throw new moodle_exception('certificatenotavailable', 'local_xertify');
}

$courseconfig = \local_xertify\local\config::get_course_config($course->id);
$apiurl = \local_xertify\local\config::get_api_url();
$token = \local_xertify\local\config::get_api_token($courseconfig);

if ($apiurl === '' || $token === '') {
    throw new moodle_exception('missingconfig', 'local_xertify');
}

$language = substr(current_language(), 0, 2);
if (!in_array($language, ['es', 'en'], true)) {
    $language = 'es';
}

$client = new \local_xertify\api\client($apiurl, $token);
$response = $client->get_pdf(
    (string)$issue->certificatecode,
    $language
);

if (empty($response['ok']) || empty($response['pdf'])) {
    throw new moodle_exception('pdfserviceerror', 'local_xertify');
}

$pdf = $response['pdf'];

if (
    !is_string($pdf)
    || strlen($pdf) < 5
    || substr($pdf, 0, 5) !== '%PDF-'
) {
    throw new moodle_exception('pdfserviceerror', 'local_xertify');
}

$filename = clean_filename(
    'xertify-' . $issue->certificatecode . '.pdf'
);

// The PDF exists only in memory for this request.
// It is not persisted in Moodle.
send_file(
    $pdf,
    $filename,
    0,
    0,
    true,
    !empty($download),
    'application/pdf'
);
