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
 * Course-level Xertify configuration page.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$resetattempts = optional_param('resetattempts', 0, PARAM_BOOL);
$resetissueid = optional_param('resetissueid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/xertify:manage', $context);

$url = new moodle_url('/local/xertify/course_settings.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('courseconfig', 'local_xertify'));
$PAGE->set_heading($course->fullname);

$record = $DB->get_record('local_xertify_course', ['courseid' => $courseid]);
$form = new \local_xertify\form\course_settings_form($url, ['courseid' => $courseid]);

if ($resetattempts) {
    require_sesskey();
    $DB->delete_records('local_xertify_issue', ['courseid' => $courseid]);
    redirect($url, get_string('resetattemptsdone', 'local_xertify'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($resetissueid) {
    require_sesskey();
    $issue = $DB->get_record('local_xertify_issue', ['id' => $resetissueid, 'courseid' => $courseid], '*', MUST_EXIST);
    $DB->delete_records('local_xertify_issue', ['id' => $issue->id]);
    redirect($url, get_string('resetattemptdone', 'local_xertify'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($record) {
    $form->set_data($record);
} else {
    $form->set_data((object)[
        'courseid' => $courseid,
        'enabled' => 0,
        'idtype' => \local_xertify\local\config::get_default_idtype(),
        'emitrule' => 'course_completed',
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

if ($data = $form->get_data()) {
    $now = time();
    $payload = (object)[
        'courseid' => $courseid,
        'enabled' => !empty($data->enabled) ? 1 : 0,
        'templateid' => trim((string)$data->templateid),
        'groupid' => trim((string)$data->groupid),
        'idtype' => trim((string)$data->idtype),
        'apitoken' => trim((string)$data->apitoken),
        'emitrule' => $data->emitrule ?: 'course_completed',
        'dynamicdata' => trim((string)$data->dynamicdata),
        'notification_sms' => !empty($data->notification_sms) ? 1 : 0,
        'notification_email' => !empty($data->notification_email) ? 1 : 0,
        'notification_whatsapp' => !empty($data->notification_whatsapp) ? 1 : 0,
        'timemodified' => $now,
    ];

    if ($record) {
        $payload->id = $record->id;
        $DB->update_record('local_xertify_course', $payload);
    } else {
        $payload->timecreated = $now;
        $DB->insert_record('local_xertify_course', $payload);
    }

    redirect($url, get_string('settingssaved', 'local_xertify'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();

// Select name fields without replacing the issue ID used by reset actions.
$namefields = \core_user\fields::for_name()->get_sql('u')->selects;
$attempts = $DB->get_records_sql(
    "SELECT xi.id,
            xi.userid,
            xi.status,
            xi.responsecode,
            xi.timemodified,
            u.email{$namefields}
       FROM {local_xertify_issue} xi
       JOIN {user} u ON u.id = xi.userid
      WHERE xi.courseid = ?
   ORDER BY xi.timemodified DESC",
    [$courseid],
    0,
    20
);

if ($attempts) {
    echo $OUTPUT->heading(get_string('recentattempts', 'local_xertify'), 3);

    $table = new html_table();
    $table->head = [
        get_string('student', 'local_xertify'),
        get_string('status', 'local_xertify'),
        get_string('responsecode', 'local_xertify'),
        get_string('timemodified', 'local_xertify'),
        get_string('actions'),
    ];

    foreach ($attempts as $attempt) {
        $resetoneurl = new moodle_url('/local/xertify/course_settings.php', [
            'courseid' => $courseid,
            'resetissueid' => $attempt->id,
            'sesskey' => sesskey(),
        ]);
        $resetonebutton = $OUTPUT->single_button(
            $resetoneurl,
            get_string('resetattempt', 'local_xertify'),
            'post'
        );

        $table->data[] = [
            fullname($attempt) . html_writer::empty_tag('br') . s($attempt->email),
            s($attempt->status),
            $attempt->responsecode ? s((string)$attempt->responsecode) : '-',
            userdate($attempt->timemodified),
            $resetonebutton,
        ];
    }

    echo html_writer::table($table);
}

$reseturl = new moodle_url('/local/xertify/course_settings.php', [
    'courseid' => $courseid,
    'resetattempts' => 1,
    'sesskey' => sesskey(),
]);
echo $OUTPUT->single_button(
    $reseturl,
    get_string('resetattempts', 'local_xertify'),
    'post',
    ['class' => 'mt-3', 'tooltip' => get_string('resetattemptsconfirm', 'local_xertify')]
);
echo $OUTPUT->footer();
