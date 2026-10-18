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
 * Learner certificate page for Xertify.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$issueid = optional_param('issueid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

$service = new \local_xertify\local\certificate_service();
$certificate = $issueid
    ? $service->get_by_id($issueid, $courseid, $context)
    : $service->get_for_user($courseid, null, $context);

if (!$certificate['canrequestpdf']) {
    throw new moodle_exception('certificatenotavailable', 'local_xertify');
}

$pageurl = new moodle_url('/local/xertify/certificate.php', [
    'courseid' => $courseid,
]);

if ($issueid) {
    $pageurl->param('issueid', $issueid);
}

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('mycertificate', 'local_xertify'));
$PAGE->set_heading($course->fullname);

$pdfurl = new moodle_url('/local/xertify/pdf.php', [
    'issueid' => $certificate['issueid'],
]);

$downloadurl = new moodle_url('/local/xertify/pdf.php', [
    'issueid' => $certificate['issueid'],
    'download' => 1,
]);

$verifyurl = $certificate['walleturl'];

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('mycertificate', 'local_xertify'), 2);

echo html_writer::div(
    get_string('certificateavailable', 'local_xertify'),
    'alert alert-success'
);

echo html_writer::tag(
    'p',
    html_writer::tag(
        'strong',
        get_string('certificatecode', 'local_xertify') . ': '
    ) . s($certificate['certificatecode'])
);

$buttons = [];

$buttons[] = html_writer::link(
    $pdfurl,
    get_string('viewcertificate', 'local_xertify'),
    [
        'class' => 'btn btn-primary',
        'target' => '_blank',
        'rel' => 'noopener',
    ]
);

$buttons[] = html_writer::link(
    $downloadurl,
    get_string('downloadpdf', 'local_xertify'),
    ['class' => 'btn btn-secondary']
);

$buttons[] = html_writer::link(
    $verifyurl,
    get_string('verifyxertify', 'local_xertify'),
    [
        'class' => 'btn btn-outline-secondary',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ]
);

echo html_writer::div(implode(' ', $buttons), 'mb-3');

echo html_writer::tag(
    'iframe',
    '',
    [
        'src' => $pdfurl->out(false),
        'title' => get_string('mycertificate', 'local_xertify'),
        'style' => 'width:100%;height:80vh;border:1px solid #ddd;',
        'loading' => 'lazy',
    ]
);

echo $OUTPUT->footer();
