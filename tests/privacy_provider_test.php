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
 * Privacy request isolation and safe export regression tests.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xertify;

use local_xertify\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Real Privacy API requests with synthetic records and no external effects.
 *
 * @covers \local_xertify\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /** @var array Synthetic courses. */
    private $courses;
    /** @var array Synthetic users. */
    private $users;
    /** @var array Stored integration configurations before each request. */
    private $configs;
    /** @var array Original HTTP mocks. */
    private $curlmocks;
    /** @var \phpunit_event_sink Captured events. */
    private $events;
    /** @var string A deliberately fictitious credential. */
    private const SECRET = 'TEST_SECRET_PRIVACY_TOKEN_123456';

    /**
     * Create three courses, three users and unchanged enabled configurations.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/filelib.php');
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $property->setAccessible(true);
        $this->curlmocks = $property->getValue();
        $property->setValue(null, ['PRIVACY_HTTP_MUST_NOT_BE_CONSUMED']);
        $this->events = $this->redirectEvents();
        $this->courses = [];
        $this->users = [];
        $g = $this->getDataGenerator();
        for ($i = 0; $i < 3; $i++) {
            $this->courses[] = $g->create_course();
            $this->users[] = $g->create_user();
            $DB->insert_record('local_xertify_course', (object)[
                'courseid' => $this->courses[$i]->id, 'enabled' => 1, 'templateid' => 'TEST-TEMPLATE',
                'groupid' => 'TEST-GROUP', 'apitoken' => self::SECRET,
                'emitrule' => 'course_completed', 'dynamicdata' => 'campus=Test',
                'timecreated' => 1700000000, 'timemodified' => 1700000010,
            ]);
        }
        set_config('apitoken', 'TEST_GLOBAL_PRIVACY_TOKEN', 'local_xertify');
        $CFG->local_xertify_api_token = 'TEST_CFG_PRIVACY_TOKEN';
        $this->configs = $DB->get_records('local_xertify_course', null, 'id');
    }

    /**
     * Every operation must preserve integration settings and make no HTTP/completion event.
     */
    public function tearDown(): void {
        global $DB;
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $property->setAccessible(true);
        $remaining = $property->getValue();
        $property->setValue(null, $this->curlmocks);
        try {
            $this->assertSame(['PRIVACY_HTTP_MUST_NOT_BE_CONSUMED'], $remaining);
            $this->assertEquals($this->configs, $DB->get_records('local_xertify_course', null, 'id'));
            $this->assertSame('TEST_GLOBAL_PRIVACY_TOKEN', get_config('local_xertify', 'apitoken'));
            foreach ($this->events->get_events() as $event) {
                $this->assertNotInstanceOf(\core\event\course_completed::class, $event);
            }
        } finally {
            $this->events->close();
            parent::tearDown();
        }
    }

    /**
     * Insert a synthetic local issue, never through the issuer.
     *
     * @param int $course Course array index.
     * @param int $user User array index.
     * @param array $changes Field overrides.
     * @return \stdClass Stored record.
     */
    private function issue(int $course = 0, int $user = 0, array $changes = []): \stdClass {
        global $DB;
        $record = (object)array_merge([
            'courseid' => $this->courses[$course]->id, 'userid' => $this->users[$user]->id,
            'status' => 'issued', 'requesthash' => hash('sha256', 'TEST-REQUEST-' . $course . '-' . $user),
            'responsecode' => 200, 'generationid' => 'TEST-GENERATION-PRIVACY',
            'certificatecode' => 'TESTCERT-PRIVACY-' . $course . '-' . $user,
            'certificateurl' => 'https://wallet.xertify.co/certificates/TESTCERT-PRIVACY-' . $course . '-' . $user,
            'timecreated' => 1700000000, 'timemodified' => 1700000010,
        ], $changes);
        if (!isset($changes['responsebody'])) {
            $record->responsebody = json_encode(['id' => $record->generationid, 'status' => 'OK', 'certificates' => [
                ['codeCertificate' => $record->certificatecode, 'certificateUrl' => $record->certificateurl],
            ]]);
        }
        $record->id = $DB->insert_record('local_xertify_issue', $record);
        return $DB->get_record('local_xertify_issue', ['id' => $record->id]);
    }

    /**
     * Build an approved request using real course contexts.
     *
     * @param array $courses Course array indices.
     * @param int $user User array index.
     * @param string $component Approved component.
     * @return approved_contextlist Request.
     */
    private function approved(array $courses, int $user = 0, string $component = 'local_xertify'): approved_contextlist {
        $ids = array_map(function ($index) {
            return \context_course::instance($this->courses[$index]->id)->id;
        }, $courses);
        return new approved_contextlist($this->users[$user], $component, $ids);
    }

    /**
     * Read the actual privacy test writer at the unique per-issue path.
     *
     * @param \stdClass $record Stored issue.
     * @return \stdClass Exported data.
     */
    private function exported(\stdClass $record): \stdClass {
        return writer::with_context(\context_course::instance($record->courseid))->get_data([
            get_string('pluginname', 'local_xertify'), get_string('privacy:export:issuance', 'local_xertify'), (string)$record->id,
        ]);
    }

    /**
     * Assert exactly which existing issue IDs remain after a deletion.
     *
     * @param array $records Records that must remain.
     */
    private function remaining(array $records): void {
        global $DB;
        $ids = array_map(function ($record) {
            return (int)$record->id;
        }, $records);
        $actual = array_map('intval', array_keys($DB->get_records('local_xertify_issue', null, 'id')));
        $this->assertEqualsCanonicalizing($ids, $actual);
        foreach ($records as $record) {
            $this->assertEquals($record, $DB->get_record('local_xertify_issue', ['id' => $record->id]));
        }
    }

    /**
     * Declare every issue field, translated descriptions and the existing external transfer.
     */
    public function test_metadata(): void {
        global $DB;
        $collection = provider::get_metadata(new collection('local_xertify'));
        $items = $collection->get_collection();
        $this->assertCount(2, $items);
        $tables = array_filter($items, function ($item) {
            return $item instanceof \core_privacy\local\metadata\types\database_table;
        });
        $this->assertCount(1, $tables);
        $table = reset($tables);
        $this->assertSame('local_xertify_issue', $table->get_name());
        $fields = array_keys($DB->get_columns('local_xertify_issue'));
        $this->assertEqualsCanonicalizing($fields, array_keys($table->get_privacy_fields()));
        $this->assertArrayNotHasKey('apitoken', $table->get_privacy_fields());
        foreach (array_merge(array_values($table->get_privacy_fields()), [$table->get_summary()]) as $identifier) {
            $this->assertTrue(get_string_manager()->string_exists($identifier, 'local_xertify'));
            $this->assertNotEmpty(get_string($identifier, 'local_xertify'));
        }
        $this->assertContains('xertify_api', array_map(function ($item) {
            return $item->get_name();
        }, $items));
    }

    /**
     * Discover zero, one or multiple course contexts without exposing others.
     *
     * @dataProvider context_counts
     * @param int $count Number of courses containing the subject's issue.
     */
    public function test_context_discovery(int $count): void {
        $this->issue(2, 1);
        $expected = [];
        for ($i = 0; $i < $count; $i++) {
            $this->issue($i);
            $expected[] = \context_course::instance($this->courses[$i]->id)->id;
        }
        $contexts = provider::get_contexts_for_userid($this->users[0]->id);
        $this->assertEqualsCanonicalizing($expected, $contexts->get_contextids());
        $this->assertCount($count, $contexts);
        foreach ($contexts->get_contexts() as $context) {
            $this->assertInstanceOf(\context_course::class, $context);
        }
    }

    /**
     * Context discovery sizes.
     *
     * @return array Cases.
     */
    public static function context_counts(): array {
        return [[0], [1], [3]];
    }

    /**
     * Export approved A/C only, ignoring the logged-in actor and other users' issues.
     *
     * @dataProvider export_courses
     * @param array $selected Approved courses.
     */
    public function test_export_scope_and_fields(array $selected): void {
        global $DB;
        $own = [];
        for ($i = 0; $i < 3; $i++) {
            $own[] = $this->issue($i);
            $this->issue($i, 1, ['certificatecode' => 'OTHER-USER-SECRET-' . $i]);
        }
        $before = $DB->get_records('local_xertify_issue', null, 'id');
        $this->setUser($this->users[2]);
        provider::export_user_data($this->approved($selected));
        foreach ($own as $i => $record) {
            if (!in_array($i, $selected, true)) {
                $this->assertFalse(writer::with_context(\context_course::instance($record->courseid))->has_any_data());
                continue;
            }
            $export = $this->exported($record);
            foreach (['id', 'courseid', 'userid', 'responsecode', 'timecreated', 'timemodified'] as $field) {
                $this->assertSame((int)$record->$field, $export->$field);
            }
            foreach (['status', 'requesthash', 'generationid', 'certificatecode', 'certificateurl'] as $field) {
                $this->assertSame($record->$field, $export->$field);
            }
            $this->assertSame($record->generationid, $export->responsebody->id);
            $this->assertSame('OK', $export->responsebody->status);
            $this->assertSame($record->certificatecode, $export->responsebody->certificates[0]->code);
            $all = json_encode(writer::with_context(\context_course::instance($record->courseid))->get_data());
            foreach (['OTHER-USER-SECRET', self::SECRET, 'TEST_GLOBAL_PRIVACY_TOKEN'] as $secret) {
                $this->assertStringNotContainsString($secret, $all);
            }
        }
        $this->assertEquals($before, $DB->get_records('local_xertify_issue', null, 'id'));
    }

    /**
     * Empty, single and disjoint approved contexts, including duplicates.
     *
     * @return array Cases.
     */
    public static function export_courses(): array {
        return [[[]], [[0]], [[0, 2]], [[0, 0, 2]]];
    }

    /**
     * Delete only the approved subject/context intersection.
     */
    public function test_delete_user_in_selected_courses(): void {
        $a = $this->issue(0);
        $b = $this->issue(1);
        $c = $this->issue(2);
        $othera = $this->issue(0, 1);
        $otherb = $this->issue(1, 1);
        $otherc = $this->issue(2, 1);
        provider::delete_data_for_user($this->approved([0, 2]));
        $this->remaining([$b, $othera, $otherb, $otherc]);
    }

    /**
     * Deleting the middle user preserves the first and third user in the same course.
     */
    public function test_delete_middle_user(): void {
        $first = $this->issue(0, 0);
        $this->issue(0, 1);
        $third = $this->issue(0, 2);
        provider::delete_data_for_user($this->approved([0], 1));
        $this->remaining([$first, $third]);
    }

    /**
     * Context deletion removes issues only in that course.
     */
    public function test_delete_all_in_course(): void {
        for ($i = 0; $i < 3; $i++) {
            $this->issue(0, $i);
        }
        $other = $this->issue(1);
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->courses[0]->id));
        $this->remaining([$other]);
    }

    /**
     * Userlist identifies exactly the issue owners, then deletes only an approved subset.
     *
     * @dataProvider approved_subsets
     * @param array $selected User array indices.
     */
    public function test_userlist_and_subset(array $selected): void {
        $rows = [];
        foreach ($this->users as $i => $user) {
            $rows[$i] = $this->issue(0, $i);
        }
        $other = $this->issue(1);
        $context = \context_course::instance($this->courses[0]->id);
        $list = new userlist($context, 'local_xertify');
        provider::get_users_in_context($list);
        $this->assertEqualsCanonicalizing(array_column($this->users, 'id'), $list->get_userids());
        $approved = array_map(function ($i) {
            return $this->users[$i]->id;
        }, $selected);
        provider::delete_data_for_users(new approved_userlist($context, 'local_xertify', $approved));
        $keep = [$other];
        foreach ($rows as $i => $row) {
            if (!in_array($i, $selected, true)) {
                $keep[] = $row;
            }
        }
        $this->remaining($keep);
    }

    /**
     * Include empty and non-contiguous bulk approvals.
     *
     * @return array Cases.
     */
    public static function approved_subsets(): array {
        return [[[]], [[1]], [[0, 2]]];
    }

    /**
     * Course lists must not expose users who only have data elsewhere.
     */
    public function test_userlist_excludes_other_courses(): void {
        $this->issue(0, 0);
        $this->issue(1, 1);
        $list = new userlist(\context_course::instance($this->courses[0]->id), 'local_xertify');
        provider::get_users_in_context($list);
        $this->assertEquals([$this->users[0]->id], $list->get_userids());
    }

    /**
     * Valid but irrelevant contexts cannot export, enumerate or delete course data.
     *
     * @dataProvider irrelevant_contexts
     * @param string $type Context type.
     */
    public function test_irrelevant_contexts(string $type): void {
        $issue = $this->issue();
        if ($type === 'system') {
            $context = \context_system::instance();
        } else if ($type === 'user') {
            $context = \context_user::instance($this->users[0]->id);
        } else if ($type === 'module') {
            $module = $this->getDataGenerator()->create_module('page', ['course' => $this->courses[0]->id]);
            $context = \context_module::instance($module->cmid);
        } else {
            $context = \context_course::instance($this->courses[2]->id);
        }
        $approved = new approved_contextlist($this->users[0], 'local_xertify', [$context->id]);
        provider::export_user_data($approved);
        $this->assertFalse(writer::with_context($context)->has_any_data());
        provider::delete_data_for_user($approved);
        provider::delete_data_for_all_users_in_context($context);
        $list = new userlist($context, 'local_xertify');
        provider::get_users_in_context($list);
        $this->assertCount(0, $list);
        provider::delete_data_for_users(new approved_userlist($context, 'local_xertify', [$this->users[0]->id]));
        $this->remaining([$issue]);
    }

    /**
     * Non-course and empty course contexts.
     *
     * @return array Cases.
     */
    public static function irrelevant_contexts(): array {
        return [['system'], ['user'], ['module'], ['empty-course']];
    }

    /**
     * Wrong components and an unapproved user cannot use the request APIs.
     */
    public function test_wrong_component_and_empty_subject(): void {
        $issue = $this->issue();
        $context = \context_course::instance($issue->courseid);
        $wrong = $this->approved([0], 0, 'mod_xertifycertificate');
        provider::export_user_data($wrong);
        provider::delete_data_for_user($wrong);
        $list = new userlist($context, 'mod_xertifycertificate');
        provider::get_users_in_context($list);
        $this->assertCount(0, $list);
        provider::delete_data_for_users(new approved_userlist($context, 'mod_xertifycertificate', [$issue->userid]));
        $empty = new approved_contextlist((object)['id' => 0], 'local_xertify', [$context->id]);
        provider::export_user_data($empty);
        provider::delete_data_for_user($empty);
        $this->assertFalse(writer::with_context($context)->has_any_data());
        $this->remaining([$issue]);
    }

    /**
     * A user without records produces no exports or deletion of others.
     */
    public function test_user_without_data(): void {
        $issue = $this->issue(0, 1);
        provider::export_user_data($this->approved([0, 1, 2]));
        provider::delete_data_for_user($this->approved([0, 1, 2]));
        foreach ($this->courses as $course) {
            $this->assertFalse(writer::with_context(\context_course::instance($course->id))->has_any_data());
        }
        $this->remaining([$issue]);
    }

    /**
     * Missing course/context records are safely skipped without fabricating contexts.
     *
     * @dataProvider missing_context_cases
     * @param string $missing Missing database object.
     */
    public function test_orphan_records(string $missing): void {
        global $DB;
        $valid = $this->issue(0);
        $orphan = $this->issue(1);
        $context = \context_course::instance($orphan->courseid);
        $approved = $this->approved([0, 1]);
        if ($missing === 'course') {
            $DB->delete_records('course', ['id' => $orphan->courseid]);
        } else {
            $DB->delete_records('context', ['id' => $context->id]);
        }
        $count = $DB->count_records('context');
        $this->assertEquals(
            [\context_course::instance($valid->courseid)->id],
            provider::get_contexts_for_userid($valid->userid)->get_contextids()
        );
        provider::export_user_data($approved);
        $this->assertSame($valid->certificatecode, $this->exported($valid)->certificatecode);
        provider::delete_data_for_user($approved);
        provider::delete_data_for_all_users_in_context($context);
        provider::delete_data_for_users(new approved_userlist($context, 'local_xertify', [$orphan->userid]));
        $list = new userlist($context, 'local_xertify');
        provider::get_users_in_context($list);
        $this->assertCount(0, $list);
        $this->remaining([$orphan]);
        $this->assertSame($count, $DB->count_records('context'));
    }

    /**
     * Inconsistent historical relationships reproducible without schema changes.
     *
     * @return array Cases.
     */
    public static function missing_context_cases(): array {
        return [['course'], ['context']];
    }

    /**
     * A null/invalid body or an unknown local status does not prevent the main record export.
     *
     * @dataProvider opaque_bodies
     * @param string $body Historical body.
     */
    public function test_opaque_response_and_unknown_status(string $body): void {
        $record = $this->issue(0, 0, ['status' => 'historical', 'responsebody' => $body, 'generationid' => null]);
        provider::export_user_data($this->approved([0]));
        $export = $this->exported($record);
        $this->assertSame('historical', $export->status);
        $this->assertNull($export->generationid);
        $this->assertEquals(new \stdClass(), $export->responsebody);
        $this->assertNotEmpty($export->responsebody_note);
        $this->assertStringNotContainsString(self::SECRET, json_encode($export));
        $this->remaining([$record]);
    }

    /**
     * Opaque text, malformed JSON and JSON scalars are never echoed blindly.
     *
     * @return array Cases.
     */
    public static function opaque_bodies(): array {
        return [[''], ['null'], ['"' . self::SECRET . '"'], ['{"invalid":'], ['Error: ' . self::SECRET]];
    }

    /**
     * Malicious legacy payloads cannot export secrets, files or other certificate recipients.
     */
    public function test_response_security_projection(): void {
        $body = [
            'id' => 'TEST-GENERATION-PRIVACY', 'status' => 'OK',
            'message' => 'OTHER-USER-PRIVATE ' . self::SECRET,
            'credentials' => ['aPi-ToKeN' => self::SECRET, 'password' => 'TEST_UNKNOWN_PASSWORD'],
            'authorization' => 'Bearer TEST_HEADER_CREDENTIAL',
            'unrelated' => ['userid' => $this->users[1]->id, 'email' => 'other@example.invalid'],
            'file' => base64_encode('%PDF-1.7 TEST_DOCUMENT'),
            'certificates' => [
                ['codeCertificate' => 'TESTCERT-PRIVACY-0-0', 'certificateUrl' => 'https://example.invalid/cert/' . self::SECRET,
                    'file' => base64_encode('%PDF-1.7 TEST_DOCUMENT'), 'owner' => 'OTHER-USER-PRIVATE'],
                ['code' => 'OTHER-USER-CERT', 'certificateUrl' => 'https://example.invalid/other'],
            ],
        ];
        $record = $this->issue(0, 0, ['responsebody' => json_encode($body)]);
        provider::export_user_data($this->approved([0]));
        $export = $this->exported($record);
        $this->assertSame('OK', $export->responsebody->status);
        $this->assertCount(1, $export->responsebody->certificates);
        $this->assertSame($record->certificatecode, $export->responsebody->certificates[0]->code);
        $all = json_encode($export);
        foreach (
            [self::SECRET, 'TEST_UNKNOWN_PASSWORD', 'TEST_HEADER_CREDENTIAL', 'OTHER-USER',
            'other@example.invalid', base64_encode('%PDF-1.7 TEST_DOCUMENT'), '%PDF-'] as $forbidden
        ) {
            $this->assertStringNotContainsString($forbidden, $all);
        }
        $this->remaining([$record]);
    }

    /**
     * A genuine issuer-shaped dry-run includes the subject's stored profile data only.
     */
    public function test_dryrun_personal_data_and_redaction(): void {
        $participant = [
            'universalKey' => 'MOODLE.' . $this->courses[0]->id . '.' . $this->users[0]->id . '.1700000000',
            'firstname' => 'Test Learner', 'lastname' => 'Privacy', 'email' => 'historical@example.invalid',
            'idtype' => 'CC', 'idnum' => 'TEST-DOCUMENT', 'phone' => 'TEST-PHONE',
            'dynamicData' => ['moodleUserId' => (string)$this->users[0]->id,
                'moodleCourseId' => (string)$this->courses[0]->id, 'studentFullname' => 'Test Learner Privacy',
                'profile_department' => 'Synthetic department', 'Api_Token' => self::SECRET,
                'field_with_echo' => self::SECRET, 'old_global_echo' => 'TEST_GLOBAL_PRIVACY_TOKEN',
                'cfg_echo' => 'TEST_CFG_PRIVACY_TOKEN', 'file' => 'JVBERi0xLjcgVEVTVA==',
                'encodedDocument' => base64_encode('TEST_BINARY_DOCUMENT'),
                'opaque' => base64_encode(str_repeat('SYNTHETIC', 12)),
                'nested_other_user' => ['email' => 'OTHER-USER-PRIVATE']],
        ];
        $other = $participant;
        $other['universalKey'] = 'MOODLE.' . $this->courses[0]->id . '.' . $this->users[1]->id . '.1700000000';
        $other['firstname'] = 'OTHER-USER-PRIVATE';
        $record = $this->issue(0, 0, ['status' => 'dryrun',
            'responsebody' => json_encode(['runNow' => true, 'participants' => [$participant, $other]])]);
        provider::export_user_data($this->approved([0]));
        $export = $this->exported($record);
        $this->assertCount(1, $export->responsebody->participants);
        $actual = $export->responsebody->participants[0];
        $this->assertSame('Test Learner', $actual->firstname);
        $this->assertSame('historical@example.invalid', $actual->email);
        $this->assertSame('TEST-DOCUMENT', $actual->idnum);
        $this->assertSame('Synthetic department', $actual->dynamicData['profile_department']);
        foreach (
            [self::SECRET, 'TEST_GLOBAL_PRIVACY_TOKEN', 'TEST_CFG_PRIVACY_TOKEN', 'OTHER-USER-PRIVATE',
            'JVBERi0xLjcgVEVTVA==', base64_encode('TEST_BINARY_DOCUMENT'),
            base64_encode(str_repeat('SYNTHETIC', 12))] as $forbidden
        ) {
            $this->assertStringNotContainsString($forbidden, json_encode($export));
        }
        $this->remaining([$record]);
    }

    /**
     * Unbound or contradictory participants are excluded rather than guessed from array position.
     *
     * @dataProvider unbound_participants
     * @param string $case Identity contradiction.
     */
    public function test_unbound_participants(string $case): void {
        $participant = ['firstname' => 'UNVERIFIED_PERSON',
            'universalKey' => 'MOODLE.' . $this->courses[0]->id . '.' . $this->users[0]->id . '.1700000000'];
        if ($case === 'missing') {
            unset($participant['universalKey']);
        } else if ($case === 'wrong-course') {
            $participant['universalKey'] = 'MOODLE.' . $this->courses[1]->id . '.' . $this->users[0]->id . '.1700000000';
        } else {
            $participant['dynamicData'] = ['moodleUserId' => (string)$this->users[1]->id];
        }
        $record = $this->issue(0, 0, ['responsebody' => json_encode(['participants' => [$participant]])]);
        provider::export_user_data($this->approved([0]));
        $this->assertStringNotContainsString('UNVERIFIED_PERSON', json_encode($this->exported($record)));
    }

    /**
     * Ownership failures.
     *
     * @return array Cases.
     */
    public static function unbound_participants(): array {
        return [['missing'], ['wrong-course'], ['contradictory-user']];
    }

    /**
     * URL credentials and document payloads cannot leak through canonical fields.
     *
     * @dataProvider unsafe_urls
     * @param string $url Unsafe stored URL.
     */
    public function test_url_redaction(string $url): void {
        $record = $this->issue(0, 0, ['certificateurl' => $url]);
        provider::export_user_data($this->approved([0]));
        $this->assertSame(get_string('privacy:export:redacted', 'local_xertify'), $this->exported($record)->certificateurl);
        $this->assertStringNotContainsString('URL_CREDENTIAL', json_encode($this->exported($record)));
    }

    /**
     * Credential-bearing URL forms.
     *
     * @return array Cases.
     */
    public static function unsafe_urls(): array {
        return [
            ['https://user:URL_CREDENTIAL@example.invalid/cert'],
            ['https://example.invalid/cert?access_token=URL_CREDENTIAL'],
            ['https://example.invalid/cert#URL_CREDENTIAL'],
            ['data:application/pdf;base64,JVBERi0xLjcgVEVTVA=='],
        ];
    }

    /**
     * The provider cannot acquire a client, invoke the issuer or trigger remote deletion.
     */
    public function test_no_external_or_issuance_dependencies(): void {
        $file = __DIR__ . '/../classes/privacy/provider.php';
        foreach (token_get_all(file_get_contents($file)) as $token) {
            if (!is_array($token) || ($token[0] !== T_STRING && strpos(token_name($token[0]), 'T_NAME_') !== 0)) {
                continue;
            }
            foreach (explode('\\', strtolower($token[1])) as $part) {
                $this->assertNotContains($part, ['issuer', 'client', 'curl', 'issue_for_course_completion', 'course_completed']);
            }
        }
    }
}
