<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$issueid = optional_param('issueid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

if ($issueid) {
    $issue = $DB->get_record(
        'local_xertify_issue',
        ['id' => $issueid, 'courseid' => $courseid],
        '*',
        MUST_EXIST
    );

    if ((int)$issue->userid !== (int)$USER->id) {
        require_capability('local/xertify:manage', $context);
    }
} else {
    $issue = $DB->get_record(
        'local_xertify_issue',
        ['courseid' => $courseid, 'userid' => $USER->id],
        '*',
        MUST_EXIST
    );
}

if ($issue->status !== 'issued' || empty($issue->certificatecode)) {
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
    'issueid' => $issue->id,
]);

$downloadurl = new moodle_url('/local/xertify/pdf.php', [
    'issueid' => $issue->id,
    'download' => 1,
]);

$verifyurl = '';
if (!empty($issue->certificateurl)) {
    $candidate = trim((string)$issue->certificateurl);
    $parts = parse_url($candidate);

    if (
        is_array($parts)
        && ($parts['scheme'] ?? '') === 'https'
        && strtolower((string)($parts['host'] ?? '')) === 'wallet.xertify.co'
    ) {
        $verifyurl = $candidate;
    }
}

if ($verifyurl === '') {
    $verifyurl = 'https://wallet.xertify.co/certificates/'
        . rawurlencode((string)$issue->certificatecode);
}

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
    ) . s($issue->certificatecode)
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
