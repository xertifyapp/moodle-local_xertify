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

namespace local_xertify;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Course backup, restore and duplication using Moodle's real controllers.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_local_xertify_plugin
 * @covers \restore_local_xertify_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var string Synthetic credential, never a real token. */
    private const SECRET = 'TEST_SECRET_XERTIFY_TOKEN_123456';
    /** @var string Synthetic certificate identifier. */
    private const CODE = 'TEST_CERTIFICATE_DO_NOT_COPY';
    /** @var string Synthetic generation identifier. */
    private const GENERATION = '00000000-0000-0000-0000-000000001234';
    /** @var string Synthetic response marker. */
    private const PAYLOAD = 'TEST_CERTIFICATE_RESPONSE_DO_NOT_COPY';
    /** @var \stdClass Source course. */
    private $course;
    /** @var \stdClass Source integration configuration. */
    private $config;
    /** @var string Embedded PDF fixture. */
    private $base64;

    /**
     * Create only synthetic data in the disposable PHPUnit database.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;
        $CFG->local_xertify_api_url = 'https://example.invalid/v4createRequest';
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->config = (object)[
            'courseid' => $this->course->id,
            'enabled' => 1,
            'templateid' => 'TEST-TEMPLATE',
            'groupid' => 'TEST-GROUP',
            'idtype' => 'TEST-IDTYPE',
            'apitoken' => self::SECRET,
            'emitrule' => 'course_completed',
            'dynamicdata' => "location=Test campus\ncohort=2026",
            'notification_sms' => 1,
            'notification_email' => 0,
            'notification_whatsapp' => 1,
            'timecreated' => 1700000000,
            'timemodified' => 1700000010,
        ];
        $this->config->id = $DB->insert_record('local_xertify_course', $this->config);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $this->base64 = base64_encode('%PDF-1.7 synthetic backup exclusion fixture');
        $DB->insert_record('local_xertify_issue', (object)[
            'courseid' => $this->course->id, 'userid' => $user->id, 'status' => 'issued',
            'requesthash' => 'synthetic-hash', 'responsecode' => 200,
            'generationid' => self::GENERATION, 'certificatecode' => self::CODE,
            'certificateurl' => 'https://wallet.xertify.co/certificates/' . self::CODE,
            'responsebody' => json_encode(['message' => self::PAYLOAD, 'file' => $this->base64]),
            'timecreated' => 1700000000, 'timemodified' => 1700000010,
        ]);
    }

    /**
     * Guard a real operation against HTTP, completion events and issue writes.
     *
     * @param callable $operation Backup/restore/duplicate operation.
     * @return mixed Operation result.
     */
    private function without_emission(callable $operation) {
        global $DB;
        $before = $DB->get_records('local_xertify_issue', null, 'id');
        $sink = $this->redirectEvents();
        // A queued response prevents any accidental first HTTP call from leaving the process.
        // Comparing the queue proves that no curl request consumed it during the operation.
        $property = new \ReflectionProperty(\curl::class, 'mockresponses');
        $property->setAccessible(true);
        $original = $property->getValue();
        \curl::mock_response('UNEXPECTED_HTTP_DURING_BACKUP_RESTORE');
        $expected = $property->getValue();
        try {
            $result = $operation();
            $this->assertSame($expected, $property->getValue(), 'Backup/restore must not perform HTTP requests.');
            $this->assertEquals($before, $DB->get_records('local_xertify_issue', null, 'id'));
            foreach ($sink->get_events() as $event) {
                $this->assertNotInstanceOf(\core\event\course_completed::class, $event);
            }
            return $result;
        } finally {
            $property->setValue(null, $original);
            $sink->close();
        }
    }

    /**
     * Generate an actual MBZ and extract its contents using Moodle's packer.
     *
     * @param bool $users Include standard Moodle user data.
     * @return string Backup directory identifier.
     */
    private function backup_course(bool $users = false): string {
        global $CFG, $USER;
        return $this->without_emission(function () use ($users, $CFG, $USER) {
            $controller = new \backup_controller(
                \backup::TYPE_1COURSE,
                $this->course->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id
            );
            try {
                $controller->get_plan()->get_setting('users')->set_value($users);
                $controller->execute_plan();
                $backupid = $controller->get_backupid();
                $file = $controller->get_results()['backup_destination'];
                $this->assertInstanceOf(\stored_file::class, $file);
                $path = $CFG->tempdir . '/backup/' . $backupid;
                // Re-extract the finished archive: assertions inspect what actually left the backup process.
                fulldelete($path);
                $this->assertNotFalse($file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path));
                return $backupid;
            } finally {
                $controller->destroy();
            }
        });
    }

    /**
     * Run Moodle restore into a new or existing destination.
     *
     * @param string $backupid Extracted backup directory ID.
     * @param int|null $destination Existing course ID or null for a new course.
     * @param int $target Restore target constant.
     * @param bool $overwrite Apply course configuration when restoring over an existing course.
     * @return int Destination course ID.
     */
    private function restore_course(
        string $backupid,
        ?int $destination = null,
        int $target = \backup::TARGET_NEW_COURSE,
        bool $overwrite = true
    ): int {
        global $USER;
        return $this->without_emission(function () use ($backupid, $destination, $target, $overwrite, $USER) {
            if ($destination === null) {
                $destination = \restore_dbops::create_new_course('Restored fixture', 'restored-fixture', $this->course->category);
            }
            $controller = new \restore_controller(
                $backupid,
                $destination,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                $target
            );
            try {
                if ($target !== \backup::TARGET_NEW_COURSE) {
                    $controller->get_plan()->get_setting('overwrite_conf')->set_value($overwrite);
                }
                $this->assertTrue($controller->execute_precheck());
                $controller->execute_plan();
            } finally {
                $controller->destroy();
            }
            return (int)$destination;
        });
    }

    /**
     * Check every portable field and safe defaults, not only the token.
     *
     * @param int $courseid Destination course ID.
     */
    private function assert_safe_config(int $courseid): void {
        global $DB;
        $record = $DB->get_record('local_xertify_course', ['courseid' => $courseid], '*', MUST_EXIST);
        $this->assertSame($courseid, (int)$record->courseid);
        $this->assertNotEquals($this->course->id, $record->courseid);
        $this->assertSame(0, (int)$record->enabled);
        $this->assertSame('', $record->apitoken);
        foreach (['templateid', 'groupid', 'idtype', 'emitrule', 'dynamicdata'] as $field) {
            $this->assertSame($this->config->$field, $record->$field);
        }
        foreach (['notification_sms', 'notification_email', 'notification_whatsapp'] as $field) {
            $this->assertSame((int)$this->config->$field, (int)$record->$field);
        }
        $this->assertSame(0, $DB->count_records('local_xertify_issue', ['courseid' => $courseid]));
        $this->assertEquals($this->config, $DB->get_record('local_xertify_course', ['id' => $this->config->id]));
    }

    /**
     * Finished archives exclude secrets and all certificate payload fields.
     *
     * @dataProvider user_data_provider
     * @param bool $users Include standard Moodle user data.
     */
    public function test_archive_has_only_portable_configuration(bool $users): void {
        global $CFG;
        $backupid = $this->backup_course($users);
        $path = $CFG->tempdir . '/backup/' . $backupid;
        $forbidden = [
            'apitoken', self::SECRET, 'local_xertify_issue', 'certificatecode', 'generationid',
            'certificateurl', 'responsebody', self::CODE, self::GENERATION, self::PAYLOAD, $this->base64,
        ];
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $count++;
            $contents = file_get_contents($file->getPathname());
            foreach ($forbidden as $value) {
                $this->assertStringNotContainsString($value, $contents, $file->getFilename());
            }
        }
        $this->assertGreaterThan(0, $count);
        $xml = simplexml_load_file($path . '/course/course.xml');
        $records = $xml->xpath('//courseconfig');
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertSame('1', (string)$record->enabled);
        foreach (['templateid', 'groupid', 'idtype', 'emitrule', 'dynamicdata'] as $field) {
            $this->assertSame($this->config->$field, (string)$record->$field);
        }
        foreach (['notification_sms', 'notification_email', 'notification_whatsapp', 'timecreated', 'timemodified'] as $field) {
            $this->assertSame((string)$this->config->$field, (string)$record->$field);
        }
    }

    /**
     * Both user-data modes must exclude Xertify student history.
     *
     * @return array
     */
    public static function user_data_provider(): array {
        return [[false], [true]];
    }

    /**
     * New destinations receive portable values, never activation or credentials.
     *
     * @dataProvider user_data_provider
     * @param bool $users Include standard Moodle user data.
     */
    public function test_new_course_restore(bool $users): void {
        $this->assert_safe_config($this->restore_course($this->backup_course($users)));
    }

    /**
     * Add legacy credentials and unrecognized sensitive fields to a real backup XML.
     *
     * @param string $backupid Extracted archive ID.
     */
    private function make_legacy_backup(string $backupid): void {
        global $CFG;
        $path = $CFG->tempdir . '/backup/' . $backupid . '/course/course.xml';
        $xml = simplexml_load_file($path);
        $records = $xml->xpath('//courseconfig');
        $this->assertCount(1, $records);
        $record = $records[0];
        $record->enabled = 1;
        $record->addChild('apitoken', self::SECRET);
        $record->addChild('api_token', 'UNKNOWN_SECRET_FIELD');
        $record->addChild('password', 'UNKNOWN_PASSWORD_FIELD');
        $record->addChild('courseid', (string)$this->course->id);
        $record->addChild('certificatecode', self::CODE);
        $record->addChild('generationid', self::GENERATION);
        $record->addChild('responsebody', self::PAYLOAD);
        $record->addChild('file', $this->base64);
        $this->assertNotFalse($xml->asXML($path));
        $this->assertStringContainsString(self::SECRET, file_get_contents($path));
    }

    /**
     * The actual XML restore parser must ignore old tokens and unknown fields.
     */
    public function test_legacy_backup_is_safe(): void {
        global $DB;
        $backupid = $this->backup_course();
        $this->make_legacy_backup($backupid);
        $courseid = $this->restore_course($backupid);
        $this->assert_safe_config($courseid);
        $record = $DB->get_record('local_xertify_course', ['courseid' => $courseid]);
        foreach ([self::SECRET, 'UNKNOWN_SECRET_FIELD', 'UNKNOWN_PASSWORD_FIELD', self::CODE, self::PAYLOAD] as $value) {
            $this->assertStringNotContainsString($value, json_encode($record));
        }
    }

    /**
     * Preserve every destination field, regardless of its existing activation state.
     *
     * @dataProvider existing_course_provider
     * @param int $enabled Destination activation state.
     * @param int $target Moodle restore target.
     * @param bool $overwrite Apply the source course configuration.
     */
    public function test_existing_configuration_is_preserved(int $enabled, int $target, bool $overwrite): void {
        global $DB;
        $destination = $this->getDataGenerator()->create_course();
        $record = clone $this->config;
        unset($record->id);
        $record->courseid = $destination->id;
        $record->enabled = $enabled;
        $record->apitoken = 'TEST_DESTINATION_TOKEN';
        $record->templateid = 'DEST-TEMPLATE';
        $record->groupid = 'DEST-GROUP';
        $record->dynamicdata = 'destination=keep';
        $record->id = $DB->insert_record('local_xertify_course', $record);
        $before = $DB->get_record('local_xertify_course', ['id' => $record->id]);
        $backupid = $this->backup_course();
        $this->make_legacy_backup($backupid);
        $this->restore_course($backupid, (int)$destination->id, $target, $overwrite);
        $this->assertEquals($before, $DB->get_record('local_xertify_course', ['id' => $record->id]));
        $this->assertSame(1, $DB->count_records('local_xertify_course', ['courseid' => $destination->id]));
    }

    /**
     * Cover Moodle's merge and delete-content targets with both activation states.
     *
     * @return array
     */
    public static function existing_course_provider(): array {
        return [
            'active merge' => [1, \backup::TARGET_EXISTING_ADDING, true],
            'disabled merge' => [0, \backup::TARGET_EXISTING_ADDING, true],
            'active replace content' => [1, \backup::TARGET_EXISTING_DELETING, true],
            'disabled replace content' => [0, \backup::TARGET_EXISTING_DELETING, true],
            'active merge without config' => [1, \backup::TARGET_EXISTING_ADDING, false],
            'disabled merge without config' => [0, \backup::TARGET_EXISTING_ADDING, false],
            'active replace without config' => [1, \backup::TARGET_EXISTING_DELETING, false],
            'disabled replace without config' => [0, \backup::TARGET_EXISTING_DELETING, false],
        ];
    }

    /**
     * Existing courses without an integration receive the same safe defaults.
     */
    public function test_existing_course_without_configuration(): void {
        $destination = $this->getDataGenerator()->create_course();
        $courseid = $this->restore_course(
            $this->backup_course(),
            (int)$destination->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $this->assert_safe_config($courseid);
    }

    /**
     * Exercise Moodle's real duplicate-course entry point, not a simulated copy.
     */
    public function test_duplicate_course_is_safe(): void {
        $result = $this->without_emission(function () {
            return \core_course_external::duplicate_course(
                $this->course->id,
                'Duplicated fixture',
                'duplicated-fixture',
                $this->course->category,
                1,
                [['name' => 'users', 'value' => 0]]
            );
        });
        $this->assert_safe_config((int)$result['id']);
    }

    /**
     * Backups without Xertify configuration must not create an integration.
     */
    public function test_source_without_configuration(): void {
        global $DB;
        $DB->delete_records('local_xertify_course', ['courseid' => $this->course->id]);
        $courseid = $this->restore_course($this->backup_course());
        $this->assertFalse($DB->record_exists('local_xertify_course', ['courseid' => $courseid]));
        $this->assertFalse($DB->record_exists('local_xertify_issue', ['courseid' => $courseid]));
    }

    /**
     * Guard direct issuance dependencies as well as the runtime event/HTTP checks.
     */
    public function test_backup_restore_has_no_issuance_dependency(): void {
        foreach (['backup', 'restore'] as $operation) {
            $file = __DIR__ . '/../backup/moodle2/' . $operation . '_local_xertify_plugin.class.php';
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (!is_array($token)) {
                    continue;
                }
                if ($token[0] === T_STRING || strpos(token_name($token[0]), 'T_NAME_') === 0) {
                    foreach (explode('\\', strtolower($token[1])) as $part) {
                        $this->assertNotContains($part, ['issuer', 'client', 'issue', 'issue_for_course_completion']);
                    }
                }
            }
        }
    }

    /**
     * Existing normal issuance remains functional; only HTTP is simulated.
     */
    public function test_normal_issuance_still_works_outside_restore(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        \curl::mock_response(json_encode([
            'id' => '00000000-0000-0000-0000-000000005678',
            'certificates' => [['codeCertificate' => 'TEST-NORMAL-ISSUE',
                'certificateUrl' => 'https://wallet.xertify.co/certificates/TEST-NORMAL-ISSUE']],
        ]));
        \local_xertify\local\issuer::issue_for_course_completion($this->course->id, $user->id, time());
        $issue = $DB->get_record('local_xertify_issue', ['courseid' => $this->course->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('issued', $issue->status);
        $this->assertSame('TEST-NORMAL-ISSUE', $issue->certificatecode);
        $this->assertSame(self::SECRET, $DB->get_field('local_xertify_course', 'apitoken', ['id' => $this->config->id]));
    }

    /**
     * Moodle skips course plugin configuration when overwrite_conf is false.
     */
    public function test_existing_course_can_skip_configuration(): void {
        global $DB;
        $destination = $this->getDataGenerator()->create_course();
        $this->restore_course(
            $this->backup_course(),
            (int)$destination->id,
            \backup::TARGET_EXISTING_ADDING,
            false
        );
        $this->assertFalse($DB->record_exists('local_xertify_course', ['courseid' => $destination->id]));
        $this->assertFalse($DB->record_exists('local_xertify_issue', ['courseid' => $destination->id]));
    }
}
