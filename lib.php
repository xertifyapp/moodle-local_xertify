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
 * Navigation callbacks for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the Xertify course settings page to the course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function local_xertify_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (has_capability('local/xertify:manage', $context)) {
        $url = new moodle_url('/local/xertify/course_settings.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('courseconfig', 'local_xertify'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_xertify'
        );
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $service = new \local_xertify\local\certificate_service();
    $certificate = $service->get_for_user((int)$course->id, null, $context);
    if (!$certificate['canrequestpdf']) {
        return;
    }

    $certificateurl = new moodle_url(
        '/local/xertify/certificate.php',
        ['courseid' => $course->id]
    );

    $navigation->add(
        get_string('mycertificate', 'local_xertify'),
        $certificateurl,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_xertify_certificate'
    );
}
