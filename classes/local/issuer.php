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

namespace local_xertify\local;

use local_xertify\api\client;

/**
 * Certificate issuing service for Xertify.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer {
    /**
     * Issue a certificate for a completed course.
     *
     * @param int $courseid Moodle course ID.
     * @param int $userid Moodle user ID.
     * @param int $eventtime Event timestamp.
     * @return void
     */
    public static function issue_for_course_completion(int $courseid, int $userid, int $eventtime): void {
        global $DB;

        $courseconfig = config::get_course_config($courseid);
        if (!$courseconfig || empty($courseconfig->enabled)) {
            return;
        }

        if ($courseconfig->emitrule !== 'course_completed') {
            return;
        }

        if (empty($courseconfig->templateid) || empty($courseconfig->groupid)) {
            self::record_attempt($courseid, $userid, 'failed', '', 0, get_string('missingconfig', 'local_xertify'));
            return;
        }

        $existing = $DB->get_record('local_xertify_issue', ['courseid' => $courseid, 'userid' => $userid]);
        if ($existing && in_array($existing->status, ['issued', 'pending', 'dryrun'], true)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $participant = self::build_participant($courseconfig, $course, $user, $eventtime);
        $payload = [
            'runNow' => true,
            'participants' => [$participant],
        ];

        $requesthash = hash('sha256', json_encode($payload));

        if (config::is_dry_run()) {
            self::record_attempt($courseid, $userid, 'dryrun', $requesthash, 0, json_encode($payload));
            return;
        }

        $apiurl = config::get_api_url();
        $token = config::get_api_token($courseconfig);
        if ($apiurl === '' || $token === '') {
            self::record_attempt($courseid, $userid, 'failed', $requesthash, 0, get_string('missingconfig', 'local_xertify'));
            return;
        }

        $client = new client($apiurl, $token);
        $response = $client->issue($payload);
        self::record_attempt(
            $courseid,
            $userid,
            $response['ok'] ? 'issued' : 'failed',
            $requesthash,
            $response['code'],
            $response['body']
        );
    }

    /**
     * Build a Xertify participant payload.
     *
     * @param \stdClass $courseconfig Xertify course configuration.
     * @param \stdClass $course Moodle course record.
     * @param \stdClass $user Moodle user record.
     * @param int $eventtime Event timestamp.
     * @return array
     */
    private static function build_participant(\stdClass $courseconfig, \stdClass $course, \stdClass $user, int $eventtime): array {
        $profilefields = self::get_profile_fields($user);
        $idnumber = trim((string)($user->idnumber ?? ''));
        if ($idnumber === '' && !empty($profilefields['document'])) {
            $idnumber = (string)$profilefields['document'];
        }
        if ($idnumber === '' && !empty($profilefields['documento'])) {
            $idnumber = (string)$profilefields['documento'];
        }
        if ($idnumber === '' && !empty($profilefields['idnum'])) {
            $idnumber = (string)$profilefields['idnum'];
        }
        if ($idnumber === '' && !empty($profilefields['numdoc'])) {
            $idnumber = (string)$profilefields['numdoc'];
        }
        if ($idnumber === '') {
            $idnumber = (string)$user->id;
        }

        $idtype = !empty($courseconfig->idtype) ? $courseconfig->idtype : config::get_default_idtype();
        if (!empty($profilefields['idtype'])) {
            $idtype = self::normalize_idtype($profilefields['idtype'], $idtype);
        }

        return [
            'automatic' => true,
            'profile' => 'p',
            'branchId' => '',
            'groupId' => $courseconfig->groupid,
            'templateId' => $courseconfig->templateid,
            'universalKey' => self::build_universal_key($course, $user, $eventtime),
            'notifications' => [
                'sms' => !empty($courseconfig->notification_sms) ? 1 : 0,
                'email' => !empty($courseconfig->notification_email) ? 1 : 0,
                'whatsapp' => !empty($courseconfig->notification_whatsapp) ? 1 : 0,
            ],
            'idtype' => $idtype,
            'idnum' => $idnumber,
            'email' => \core_text::strtolower(trim((string)$user->email)),
            'firstname' => trim((string)$user->firstname),
            'lastname' => trim((string)$user->lastname),
            'phone' => trim((string)($user->phone1 ?: $user->phone2)),
            'comments' => 'Moodle course completion user ' . $user->id . ' course ' . $course->id,
            'dynamicData' => self::build_dynamic_data($courseconfig, $course, $user, $eventtime, $profilefields),
        ];
    }

    /**
     * Build dynamic data for the certificate template.
     *
     * @param \stdClass $courseconfig Xertify course configuration.
     * @param \stdClass $course Moodle course record.
     * @param \stdClass $user Moodle user record.
     * @param int $eventtime Event timestamp.
     * @param array $profilefields Custom user profile fields.
     * @return array
     */
    private static function build_dynamic_data(
        \stdClass $courseconfig,
        \stdClass $course,
        \stdClass $user,
        int $eventtime,
        array $profilefields
    ): array {
        $monthnames = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $data = [
            'curso' => $course->fullname,
            'dia' => userdate($eventtime, '%d'),
            'mes' => $monthnames[(int)userdate($eventtime, '%m')] ?? userdate($eventtime, '%B'),
            'anio' => userdate($eventtime, '%Y'),
            'moodleCourseId' => (string)$course->id,
            'moodleCourseShortname' => $course->shortname,
            'moodleUserId' => (string)$user->id,
            'studentFullname' => fullname($user),
            'studentEmail' => $user->email,
            'completionDate' => userdate($eventtime, get_string('strftimedatefullshort')),
            'completionTimestamp' => (string)$eventtime,
        ];

        foreach ($profilefields as $key => $value) {
            if ($value !== '') {
                $data[$key] = $value;
                $data['profile_' . $key] = $value;
            }
        }

        foreach (self::parse_course_dynamic_data((string)($courseconfig->dynamicdata ?? '')) as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Parse course-level dynamic data from key=value lines.
     *
     * @param string $rawdata Raw key-value text.
     * @return array
     */
    private static function parse_course_dynamic_data(string $rawdata): array {
        $data = [];
        foreach (preg_split('/\r\n|\r|\n/', $rawdata) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $data[$key] = self::clean_profile_value($value);
        }

        return $data;
    }

    /**
     * Normalize Moodle profile document type values to Xertify IDs.
     *
     * @param string $value Source document type value.
     * @param string $fallback Fallback document type.
     * @return string
     */
    private static function normalize_idtype(string $value, string $fallback): string {
        $normalized = \core_text::strtolower(trim($value));
        $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);

        $aliases = [
            '1' => '1',
            'cc' => '1',
            'cedula' => '1',
            'cedula de ciudadania' => '1',
            'cédula de ciudadanía' => '1',
        ];

        return $aliases[$normalized] ?? $fallback;
    }

    /**
     * Build an idempotency key for Xertify.
     *
     * @param \stdClass $course Moodle course record.
     * @param \stdClass $user Moodle user record.
     * @param int $eventtime Event timestamp.
     * @return string
     */
    private static function build_universal_key(\stdClass $course, \stdClass $user, int $eventtime): string {
        return sprintf('MOODLE.%s.%s.%s', $course->id, $user->id, $eventtime);
    }

    /**
     * Load custom profile fields for a Moodle user.
     *
     * @param \stdClass $user Moodle user record.
     * @return array
     */
    private static function get_profile_fields(\stdClass $user): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        profile_load_data($user);
        $fields = [];
        foreach ((array)$user as $key => $value) {
            if (strpos($key, 'profile_field_') === 0 && is_scalar($value)) {
                $fields[substr($key, 14)] = self::clean_profile_value((string)$value);
            }
        }
        return $fields;
    }

    /**
     * Convert profile field content into plain text.
     *
     * @param string $value Raw profile field value.
     * @return string
     */
    private static function clean_profile_value(string $value): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Store or update a local issuing attempt.
     *
     * @param int $courseid Moodle course ID.
     * @param int $userid Moodle user ID.
     * @param string $status Attempt status.
     * @param string $requesthash Request hash.
     * @param int $responsecode HTTP response code.
     * @param string $responsebody API response body.
     * @return void
     */
    private static function record_attempt(
        int $courseid,
        int $userid,
        string $status,
        string $requesthash,
        int $responsecode,
        string $responsebody
    ): void {
        global $DB;

        $now = time();
        $record = $DB->get_record('local_xertify_issue', ['courseid' => $courseid, 'userid' => $userid]);
        if ($record) {
            $record->status = $status;
            $record->requesthash = $requesthash;
            $record->responsecode = $responsecode ?: null;
            $record->responsebody = $responsebody;
            $record->timemodified = $now;
            $DB->update_record('local_xertify_issue', $record);
            return;
        }

        $record = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => $status,
            'requesthash' => $requesthash,
            'responsecode' => $responsecode ?: null,
            'responsebody' => $responsebody,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_xertify_issue', $record);
    }
}
