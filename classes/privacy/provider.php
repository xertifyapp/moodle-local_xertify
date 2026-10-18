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

namespace local_xertify\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy metadata, export and local deletion for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe data sent to Xertify and stored locally.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('xertify_api', [
            'firstname' => 'privacy:metadata:xertify_api:firstname',
            'lastname' => 'privacy:metadata:xertify_api:lastname',
            'email' => 'privacy:metadata:xertify_api:email',
            'idtype' => 'privacy:metadata:xertify_api:idtype',
            'idnum' => 'privacy:metadata:xertify_api:idnum',
            'phone' => 'privacy:metadata:xertify_api:phone',
            'course' => 'privacy:metadata:xertify_api:course',
            'profilefields' => 'privacy:metadata:xertify_api:profilefields',
        ], 'privacy:metadata:xertify_api');

        $collection->add_database_table('local_xertify_issue', [
            'id' => 'privacy:metadata:local_xertify_issue:id',
            'courseid' => 'privacy:metadata:local_xertify_issue:courseid',
            'userid' => 'privacy:metadata:local_xertify_issue:userid',
            'status' => 'privacy:metadata:local_xertify_issue:status',
            'requesthash' => 'privacy:metadata:local_xertify_issue:requesthash',
            'responsecode' => 'privacy:metadata:local_xertify_issue:responsecode',
            'responsebody' => 'privacy:metadata:local_xertify_issue:responsebody',
            'generationid' => 'privacy:metadata:local_xertify_issue:generationid',
            'certificatecode' => 'privacy:metadata:local_xertify_issue:certificatecode',
            'certificateurl' => 'privacy:metadata:local_xertify_issue:certificateurl',
            'timecreated' => 'privacy:metadata:local_xertify_issue:timecreated',
            'timemodified' => 'privacy:metadata:local_xertify_issue:timemodified',
        ], 'privacy:metadata:local_xertify_issue');

        return $collection;
    }

    /**
     * Find existing course contexts containing the user's issues, without inventing contexts for orphans.
     *
     * @param int $userid Data subject, not necessarily the current session user.
     * @return contextlist Existing course contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contexts = new contextlist();
        $contexts->add_from_sql(
            'SELECT DISTINCT ctx.id
               FROM {local_xertify_issue} i
               JOIN {course} c ON c.id = i.courseid
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :level
              WHERE i.userid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contexts;
    }

    /**
     * Resolve only approved, still-existing course contexts for this component.
     *
     * @param approved_contextlist $contextlist Approved request.
     * @return array Context records keyed by context ID.
     */
    private static function approved_courses(approved_contextlist $contextlist): array {
        global $DB;
        if (
            $contextlist->get_component() !== 'local_xertify' || (int)$contextlist->get_user()->id <= 0
                || !$contextlist->count()
        ) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params['level'] = CONTEXT_COURSE;
        return $DB->get_records_sql(
            "SELECT ctx.id, ctx.instanceid
               FROM {context} ctx
               JOIN {course} c ON c.id = ctx.instanceid
              WHERE ctx.contextlevel = :level AND ctx.id $insql",
            $params
        );
    }

    /**
     * Validate a course context without creating or repairing contexts.
     *
     * @param \context $context Requested context.
     * @return bool Whether the course and context still exist.
     */
    private static function valid_course(\context $context): bool {
        global $DB;
        return $context instanceof \context_course && $DB->record_exists_sql(
            'SELECT ctx.id FROM {context} ctx JOIN {course} c ON c.id = ctx.instanceid
              WHERE ctx.id = :contextid AND ctx.contextlevel = :level AND c.id = :courseid',
            ['contextid' => $context->id, 'level' => CONTEXT_COURSE, 'courseid' => $context->instanceid]
        );
    }

    /**
     * Export every matching record under its own ID, using the approved subject rather than $USER.
     *
     * @param approved_contextlist $contextlist Approved component, subject and contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach (self::approved_courses($contextlist) as $approved) {
            $context = \context::instance_by_id($approved->id, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $records = $DB->get_recordset('local_xertify_issue', [
                'courseid' => $approved->instanceid, 'userid' => $contextlist->get_user()->id,
            ], 'id');
            try {
                foreach ($records as $record) {
                    $decoded = json_decode((string)$record->responsebody, true, 64);
                    $secrets = self::export_secrets((int)$record->courseid, is_array($decoded) ? $decoded : []);
                    $data = new \stdClass();
                    foreach (['id', 'courseid', 'userid', 'responsecode', 'timecreated', 'timemodified'] as $field) {
                        $data->$field = $record->$field === null ? null : (int)$record->$field;
                    }
                    foreach (['status', 'requesthash', 'generationid', 'certificatecode'] as $field) {
                        $data->$field = self::clean_value($record->$field, $secrets);
                    }
                    $data->certificateurl = self::clean_url($record->certificateurl, $secrets);
                    $data->responsebody = self::response_for_export($decoded, $record, $secrets);
                    $data->responsebody_note = get_string('privacy:export:responsebody', 'local_xertify');
                    writer::with_context($context)->export_data([
                        get_string('pluginname', 'local_xertify'),
                        get_string('privacy:export:issuance', 'local_xertify'),
                        (string)$record->id,
                    ], $data);
                }
            } finally {
                $records->close();
            }
        }
    }

    /**
     * Delete only the approved subject's rows in approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved request.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach (self::approved_courses($contextlist) as $context) {
            $DB->delete_records('local_xertify_issue', [
                'courseid' => $context->instanceid, 'userid' => $contextlist->get_user()->id,
            ]);
        }
    }

    /**
     * Delete personal issue rows in the course, preserving its administrative configuration.
     *
     * @param \context $context Approved course context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (self::valid_course($context)) {
            $DB->delete_records('local_xertify_issue', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Discover only users with local issue rows in this course.
     *
     * @param userlist $userlist Component and context being examined.
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_component() !== 'local_xertify' || !self::valid_course($userlist->get_context())) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_xertify_issue} WHERE courseid = :courseid', [
            'courseid' => $userlist->get_context()->instanceid,
        ]);
    }

    /**
     * Delete an approved subset of users in one course; never delete the whole course implicitly.
     *
     * @param approved_userlist $userlist Approved component, context and user IDs.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (
            $userlist->get_component() !== 'local_xertify' || !self::valid_course($userlist->get_context())
                || !$userlist->count()
        ) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $userlist->get_context()->instanceid;
        $DB->delete_records_select('local_xertify_issue', "courseid = :courseid AND userid $insql", $params);
    }

    /**
     * Recognise credential keys independently of casing and punctuation.
     *
     * @param string $key JSON field or URL parameter.
     * @return bool Whether the field carries credentials.
     */
    private static function credential_key(string $key): bool {
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));
        return (bool)preg_match('/token|secret|password|passwd|authorization|apikey|privatekey|credential|cookie|session/', $key);
    }

    /**
     * Collect credential values solely for redaction, never for export or transport.
     *
     * @param int $courseid Course being exported.
     * @param array $body Decoded response.
     * @return array Non-empty secret values.
     */
    private static function export_secrets(int $courseid, array $body): array {
        global $DB, $CFG;
        $secrets = [
            $DB->get_field('local_xertify_course', 'apitoken', ['courseid' => $courseid]),
            get_config('local_xertify', 'apitoken'), $CFG->local_xertify_api_token ?? '', getenv('XERTIFY_API_TOKEN'),
        ];
        $collect = function (array $values, bool $sensitive = false) use (&$collect, &$secrets) {
            foreach ($values as $key => $value) {
                $sensitivefield = $sensitive || self::credential_key((string)$key);
                if (is_array($value)) {
                    $collect($value, $sensitivefield);
                } else if ($sensitivefield && is_scalar($value)) {
                    $secrets[] = (string)$value;
                    if (is_string($value) && preg_match('/^(?:Bearer|Basic)\s+(.+)$/i', $value, $match)) {
                        $secrets[] = $match[1];
                    }
                }
            }
        };
        $collect($body);
        $secrets = array_values(array_unique(array_filter($secrets, function ($value) {
            return is_string($value) && $value !== '';
        })));
        usort($secrets, function ($left, $right) {
            return strlen($right) <=> strlen($left);
        });
        return $secrets;
    }

    /**
     * Clean scalar personal data; do not decode or reconstruct embedded documents.
     *
     * @param mixed $value Stored scalar.
     * @param array $secrets Known credentials.
     * @return mixed Scalar, null, or a translated redaction marker.
     */
    private static function clean_value($value, array $secrets) {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $redacted = get_string('privacy:export:redacted', 'local_xertify');
        if (!is_string($value)) {
            return $redacted;
        }
        if (
            preg_match('/%PDF-|JVBERi0|data:[^\s]*;base64,|(?:Bearer|Basic)\s+\S+/i', $value)
                || preg_match('/^[A-Za-z0-9+\/]{80,}={0,2}$/D', $value)
        ) {
            return $redacted;
        }
        return str_replace($secrets, $redacted, $value);
    }

    /**
     * Exclude URL credentials, sensitive query parameters and non-web payloads.
     *
     * @param mixed $value Stored URL.
     * @param array $secrets Known credentials.
     * @return mixed Safe stored URL, null or redaction marker.
     */
    private static function clean_url($value, array $secrets) {
        if ($value === null || $value === '') {
            return $value;
        }
        $parts = is_string($value) ? parse_url($value) : false;
        $redacted = get_string('privacy:export:redacted', 'local_xertify');
        if (
            !$parts || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        ) {
            return $redacted;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (self::credential_key($key)) {
                return $redacted;
            }
        }
        foreach ($secrets as $secret) {
            if (strpos(rawurldecode($value), $secret) !== false) {
                return $redacted;
            }
        }
        return self::clean_value($value, $secrets);
    }

    /**
     * Project only structured response data attributable to this stored issue.
     * Opaque text/messages and unknown objects cannot safely establish a subject.
     *
     * @param mixed $body Decoded historical response or dry-run payload.
     * @param \stdClass $record Owner and certificate association.
     * @param array $secrets Known credentials.
     * @return \stdClass Safe response projection, with omissions explained separately.
     */
    private static function response_for_export($body, \stdClass $record, array $secrets): \stdClass {
        $result = new \stdClass();
        if (!is_array($body)) {
            return $result;
        }
        if (isset($body['id']) && is_scalar($body['id']) && (string)$body['id'] === (string)$record->generationid) {
            $result->id = self::clean_value($body['id'], $secrets);
        }
        if (
            isset($body['status']) && is_scalar($body['status'])
                && preg_match('/^[a-z0-9_.-]{1,32}$/i', (string)$body['status'])
        ) {
            $result->status = self::clean_value($body['status'], $secrets);
        }
        foreach (is_array($body['certificates'] ?? null) ? $body['certificates'] : [] as $certificate) {
            if (!is_array($certificate) || empty($record->certificatecode)) {
                continue;
            }
            $code = $certificate['codeCertificate'] ?? $certificate['code'] ?? null;
            if (!is_scalar($code) || (string)$code !== (string)$record->certificatecode) {
                continue;
            }
            $safe = ['code' => self::clean_value($code, $secrets)];
            if (isset($certificate['certificateUrl'])) {
                $safe['certificateUrl'] = self::clean_url($certificate['certificateUrl'], $secrets);
            }
            $result->certificates[] = (object)$safe;
        }
        foreach (is_array($body['participants'] ?? null) ? $body['participants'] : [] as $participant) {
            if (!is_array($participant) || !self::owns_participant($participant, $record)) {
                continue;
            }
            $safe = [];
            foreach (['firstname', 'lastname', 'email', 'phone', 'idtype', 'idnum', 'universalKey'] as $key) {
                if (isset($participant[$key]) && is_scalar($participant[$key])) {
                    $safe[$key] = self::clean_value($participant[$key], $secrets);
                }
            }
            foreach (is_array($participant['dynamicData'] ?? null) ? $participant['dynamicData'] : [] as $key => $value) {
                $excluded = '/(?:^|[_-])files?(?:$|[_-])|base64|pdf|encoded|binary|blob|otheruser|recipient|participant/i';
                if (
                    !self::credential_key((string)$key) && !preg_match($excluded, $key)
                        && (is_scalar($value) || $value === null)
                ) {
                    $safe['dynamicData'][$key] = self::clean_value($value, $secrets);
                }
            }
            $result->participants[] = (object)$safe;
        }
        return $result;
    }

    /**
     * Bind a dry-run participant using the key generated by the existing issuer.
     *
     * @param array $participant Stored participant.
     * @param \stdClass $record Issue owner and course.
     * @return bool Whether the participant identifies this same owner and course.
     */
    private static function owns_participant(array $participant, \stdClass $record): bool {
        $key = $participant['universalKey'] ?? null;
        if (
            !is_string($key) || !preg_match('/^MOODLE\.([0-9]+)\.([0-9]+)\.([0-9]+)$/D', $key, $matches)
                || (int)$matches[1] !== (int)$record->courseid || (int)$matches[2] !== (int)$record->userid
        ) {
            return false;
        }
        $dynamic = $participant['dynamicData'] ?? [];
        if (!is_array($dynamic)) {
            return false;
        }
        foreach (['moodleUserId' => $record->userid, 'moodleCourseId' => $record->courseid] as $field => $expected) {
            if (isset($dynamic[$field]) && (!is_scalar($dynamic[$field]) || (string)$dynamic[$field] !== (string)$expected)) {
                return false;
            }
        }
        return true;
    }
}
