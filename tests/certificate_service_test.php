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

use local_xertify\api\client;
use local_xertify\local\certificate_access;
use local_xertify\local\certificate_service;
use local_xertify\local\certificate_service_stub;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/xertify/lib.php');
require_once(__DIR__ . '/fixtures/certificate_service_stub.php');

/**
 * Real Moodle database and capability tests for the shared read contract.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_xertify\local\certificate_service
 * @covers \local_xertify\local\certificate_access
 */
final class certificate_service_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private $course;
    /** @var \stdClass Certificate owner. */
    private $owner;
    /** @var \stdClass Other user. */
    private $other;
    /** @var \context_course Test course context. */
    private $context;
    /** @var certificate_service Real service. */
    private $service;

    /**
     * Set up isolated Moodle fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->owner = $generator->create_user();
        $this->other = $generator->create_user();
        $generator->enrol_user($this->owner->id, $this->course->id, 'student');
        $generator->enrol_user($this->other->id, $this->course->id, 'student');
        $this->context = \context_course::instance($this->course->id);
        $this->service = new certificate_service();
        $this->setUser($this->owner);
    }

    /**
     * Store synthetic issue metadata in the PHPUnit database only.
     *
     * @param array $overrides Fixture field overrides.
     * @return \stdClass Persisted issue.
     */
    private function issue(array $overrides = []): \stdClass {
        global $DB;
        $record = (object)array_merge([
            'courseid' => $this->course->id,
            'userid' => $this->owner->id,
            'status' => 'issued',
            'requesthash' => 'synthetic-hash',
            'responsecode' => 200,
            'responsebody' => '{"file":"U1lOVEhFVElD","apitoken":"never-return-this"}',
            'generationid' => '00000000-0000-0000-0000-000000000001',
            'certificatecode' => 'TEST0001',
            'certificateurl' => 'https://wallet.xertify.co/certificates/TEST0001',
            'timecreated' => 1700000000,
            'timemodified' => 1700000010,
        ], $overrides);
        $record->id = $DB->insert_record('local_xertify_issue', $record);
        return $record;
    }

    /**
     * Missing associations have a stable state, without a network call.
     */
    public function test_no_issue(): void {
        $service = new certificate_service_stub();
        $data = $service->get_for_user($this->course->id);
        $this->assertSame('noissue', $data['state']);
        $this->assertNull($data['issueid']);
        $this->assertFalse($data['canrequestpdf']);
        $this->assertSame('', $data['walleturl']);
        $this->assertSame(0, $service->calls);
    }

    /**
     * Interpret every supported state without claiming remote availability.
     *
     * @dataProvider state_provider
     * @param string $status Stored state.
     * @param string|null $code Stored code.
     * @param string $expected Interpreted state.
     */
    public function test_states(string $status, ?string $code, string $expected): void {
        $this->issue(['status' => $status, 'certificatecode' => $code]);
        $data = $this->service->get_for_user($this->course->id);
        $this->assertSame($status, $data['status']);
        $this->assertSame($expected, $data['state']);
        $this->assertSame($expected === 'issued', $data['canrequestpdf']);
        $this->assertSame('unknown', $data['pdfavailability']);
    }

    /**
     * Local state examples, including legacy null codes.
     *
     * @return array
     */
    public static function state_provider(): array {
        return [
            'pending' => ['pending', '', 'pending'],
            'failed' => ['failed', '', 'failed'],
            'dryrun' => ['dryrun', '', 'dryrun'],
            'issued' => ['issued', 'TEST0001', 'issued'],
            'empty code' => ['issued', '', 'issuedwithoutcode'],
            'null code' => ['issued', null, 'issuedwithoutcode'],
            'unknown' => ['unexpected', '', 'unknown'],
        ];
    }

    /**
     * Return a whitelist, never the response body, token or embedded Base64.
     */
    public function test_metadata_is_whitelisted(): void {
        $issue = $this->issue();
        $data = $this->service->get_by_id($issue->id, $this->course->id, $this->context);
        $this->assertSame((int)$issue->id, $data['issueid']);
        $this->assertSame((int)$this->course->id, $data['courseid']);
        $this->assertSame($issue->generationid, $data['generationid']);
        $this->assertSame($issue->certificatecode, $data['certificatecode']);
        $this->assertSame($issue->certificateurl, $data['walleturl']);
        $this->assertSame($issue->timecreated, $data['timecreated']);
        $this->assertSame($issue->timemodified, $data['timemodified']);
        $this->assertSame([
            'issueid', 'courseid', 'status', 'state', 'generationid', 'certificatecode',
            'walleturl', 'timecreated', 'timemodified', 'canrequestpdf', 'pdfavailability',
        ], array_keys($data));
        $this->assertStringNotContainsString('never-return-this', json_encode($data));
        $this->assertStringNotContainsString('U1lOVEhFVElD', json_encode($data));
    }

    /**
     * Validate URLs using the real URL policy.
     *
     * @dataProvider wallet_provider
     * @param string $code Certificate code.
     * @param string $url Stored URL.
     * @param string $expected Safe result.
     */
    public function test_wallet(string $code, string $url, string $expected): void {
        $this->assertSame($expected, certificate_service::get_wallet_url($code, $url));
    }

    /**
     * Wallet URL allowlist and canonical fallback cases.
     *
     * @return array
     */
    public static function wallet_provider(): array {
        $fallback = 'https://wallet.xertify.co/certificates/TEST0001';
        return [
            'valid stored' => ['TEST0001', $fallback . '?language=en', $fallback . '?language=en'],
            'external' => ['TEST0001', 'https://example.org/certificate', $fallback],
            'suffix attack' => ['TEST0001', 'https://wallet.xertify.co.example.org/', $fallback],
            'http' => ['TEST0001', 'http://wallet.xertify.co/certificates/TEST0001', $fallback],
            'protocol relative' => ['TEST0001', '//wallet.xertify.co/certificates/TEST0001', $fallback],
            'userinfo attack' => ['TEST0001', 'https://wallet.xertify.co@example.org/', $fallback],
            'userinfo rejected' => ['TEST0001', 'https://user:password@wallet.xertify.co/', $fallback],
            'nonstandard port' => ['TEST0001', 'https://wallet.xertify.co:8443/', $fallback],
            'javascript' => ['TEST0001', 'javascript:alert(1)', $fallback],
            'backslash' => ['TEST0001', 'https://wallet.xertify.co/\\example.org', $fallback],
            'no stored URL' => ['TEST0001', '', $fallback],
            'encoded code' => ['A/B?C', '', 'https://wallet.xertify.co/certificates/A%2FB%3FC'],
            'empty code' => ['', $fallback, ''],
        ];
    }

    /**
     * Owners can use either lookup without selecting another user's data.
     */
    public function test_owner_access(): void {
        $issue = $this->issue();
        $this->assertEquals(
            $this->service->get_for_user($this->course->id),
            $this->service->get_by_id($issue->id)
        );
    }

    /**
     * A manipulated issue ID cannot bypass ownership.
     */
    public function test_other_owner_issue_is_denied(): void {
        $issue = $this->issue(['userid' => $this->other->id]);
        $this->expectException(\required_capability_exception::class);
        $this->service->get_by_id($issue->id);
    }

    /**
     * A manipulated owner ID cannot bypass ownership, even without a record.
     */
    public function test_other_user_lookup_is_denied(): void {
        $this->expectException(\required_capability_exception::class);
        $this->service->get_for_user($this->course->id, $this->other->id);
    }

    /**
     * A course-scoped manager can inspect other users and diagnostics.
     */
    public function test_course_manager_access(): void {
        $issue = $this->issue(['userid' => $this->other->id]);
        $this->getDataGenerator()->enrol_user($this->owner->id, $this->course->id, 'manager');
        $data = $this->service->get_by_id($issue->id, $this->course->id);
        $this->assertSame(200, $data['responsecode']);
        $this->assertSame((int)$issue->id, $data['issueid']);
    }

    /**
     * Expected-course validation applies even to administrators.
     */
    public function test_wrong_course_is_denied(): void {
        $issue = $this->issue();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        $this->service->get_by_id($issue->id, $othercourse->id);
    }

    /**
     * A capability in another course cannot authorize the selected issue.
     */
    public function test_wrong_context_is_denied(): void {
        $issue = $this->issue(['userid' => $this->other->id]);
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->owner->id, $othercourse->id, 'manager');
        $context = \context_course::instance($othercourse->id);
        $this->expectException(\moodle_exception::class);
        $this->service->get_by_id($issue->id, $this->course->id, $context);
    }

    /**
     * A module context can use the contract after its own access checks.
     */
    public function test_module_context_is_supported(): void {
        $issue = $this->issue();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $context = \context_module::instance($page->cmid);
        $this->assertSame($context, certificate_access::require_issue($issue, $this->course->id, $context));
        $data = $this->service->get_by_id($issue->id, $this->course->id, $context);
        $this->assertSame((int)$issue->id, $data['issueid']);
    }

    /**
     * System contexts cannot be substituted for a course or module context.
     */
    public function test_system_context_is_rejected(): void {
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        $this->service->get_for_user($this->course->id, $this->owner->id, \context_system::instance());
    }

    /**
     * Guests cannot read certificate metadata.
     */
    public function test_guest_access_is_denied(): void {
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        $this->service->get_for_user($this->course->id);
    }

    /**
     * Logged-out callers cannot read certificate metadata.
     */
    public function test_anonymous_access_is_denied(): void {
        $this->setUser(null);
        $this->expectException(\moodle_exception::class);
        $this->service->get_for_user($this->course->id);
    }

    /**
     * Test the full PDF orchestration; only the external transport is replaced.
     *
     * @dataProvider pdf_provider
     * @param int $httpcode Remote HTTP status.
     * @param bool $remoteok Client success flag.
     * @param string|null $pdf Client-decoded bytes.
     * @param string $expected Expected availability state.
     */
    public function test_pdf(int $httpcode, bool $remoteok, ?string $pdf, string $expected): void {
        global $CFG, $DB;
        $issue = $this->issue();
        $before = $DB->get_record('local_xertify_issue', ['id' => $issue->id]);
        $CFG->local_xertify_api_url = 'https://example.invalid/v4createRequest';
        $DB->insert_record('local_xertify_course', (object)[
            'courseid' => $this->course->id, 'enabled' => 0, 'templateid' => 'test',
            'groupid' => 'test', 'apitoken' => 'synthetic-test-token',
        ]);
        $transport = $this->getMockBuilder(client::class)->disableOriginalConstructor()->getMock();
        $transport->expects($this->never())->method('issue');
        $transport->expects($this->once())->method('get_pdf')->with('TEST0001', 'en')->willReturn([
            'ok' => $remoteok, 'code' => $httpcode, 'pdf' => $pdf,
            'body' => 'SECRET-RESPONSE', 'token' => 'DO-NOT-RETURN',
        ]);
        $service = new certificate_service_stub();
        $service->client = $transport;
        $filecount = $DB->count_records('files');
        $result = $service->get_pdf($issue->id, 'en_us', $this->course->id, $this->context);
        $this->assertSame('synthetic-test-token', $service->token);
        $this->assertSame('https://example.invalid/v4createRequest', $service->apiurl);
        $this->assertSame($expected, $result['state']);
        $this->assertSame($expected === 'available', $result['ok']);
        $this->assertSame($httpcode, $result['httpcode']);
        $this->assertSame($result['ok'] ? $pdf : null, $result['pdf']);
        $this->assertSame('xertify-TEST0001.pdf', $result['filename']);
        $this->assertSame(['ok', 'state', 'httpcode', 'pdf', 'filename'], array_keys($result));
        $this->assertEquals($before, $DB->get_record('local_xertify_issue', ['id' => $issue->id]));
        $this->assertSame(1, $DB->count_records('local_xertify_issue'));
        $this->assertSame($filecount, $DB->count_records('files'));
        $this->assertSame('issued', $service->get_by_id($issue->id)['state']);
    }

    /**
     * Remote failures never become a local issuance-state change.
     *
     * @return array
     */
    public static function pdf_provider(): array {
        return [
            'valid' => [200, true, '%PDF-1.7 synthetic', 'available'],
            'not PDF' => [200, true, '<html>error</html>', 'error'],
            'base64 not bytes' => [200, true, 'JVBERi0xLjc=', 'error'],
            'missing bytes' => [200, true, null, 'error'],
            '401' => [401, false, null, 'error'],
            '403' => [403, false, null, 'error'],
            '404' => [404, false, null, 'unavailable'],
            '410' => [410, false, null, 'unavailable'],
            '429' => [429, false, null, 'error'],
            '500' => [500, false, null, 'error'],
            '503' => [503, false, null, 'error'],
            'network' => [0, false, null, 'error'],
            'inconsistent failure' => [500, true, '%PDF-1.7', 'error'],
        ];
    }

    /**
     * Deny another owner's PDF before creating a client or resolving a token.
     */
    public function test_pdf_rechecks_ownership(): void {
        $issue = $this->issue(['userid' => $this->other->id]);
        $service = new certificate_service_stub();
        try {
            $service->get_pdf($issue->id);
            $this->fail('Another owner must not be accessible.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame(0, $service->calls);
        }
    }

    /**
     * Missing codes cannot cause a PDF request or automatic repair.
     */
    public function test_pdf_without_code_is_denied(): void {
        $issue = $this->issue(['certificatecode' => null]);
        $service = new certificate_service_stub();
        try {
            $service->get_pdf($issue->id);
            $this->fail('Missing code must not call the API.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('certificatenotavailable', $exception->errorcode);
            $this->assertSame(0, $service->calls);
        }
    }

    /**
     * Unsupported languages retain the existing Spanish fallback.
     */
    public function test_pdf_language_fallback(): void {
        global $CFG, $DB;
        $issue = $this->issue();
        $CFG->local_xertify_api_url = 'https://example.invalid/v4createRequest';
        $DB->insert_record('local_xertify_course', (object)[
            'courseid' => $this->course->id, 'templateid' => 'test',
            'groupid' => 'test', 'apitoken' => 'synthetic-test-token',
        ]);
        $service = new certificate_service_stub();
        $service->client = $this->getMockBuilder(client::class)->disableOriginalConstructor()->getMock();
        $service->client->expects($this->never())->method('issue');
        $service->client->expects($this->once())->method('get_pdf')->with('TEST0001', 'es')->willReturn([
            'ok' => true, 'code' => 200, 'pdf' => '%PDF-1.7',
        ]);
        $this->assertTrue($service->get_pdf($issue->id, 'fr')['ok']);
    }

    /**
     * The real navigation callback preserves the original visibility rule.
     *
     * @dataProvider navigation_provider
     * @param string|null $status Stored state, or no issue.
     * @param string $code Stored code.
     * @param bool $visible Expected link visibility.
     */
    public function test_navigation(?string $status, string $code, bool $visible): void {
        if ($status !== null) {
            $this->issue(['status' => $status, 'certificatecode' => $code]);
        }
        $navigation = new \navigation_node(['text' => 'Course']);
        local_xertify_extend_navigation_course($navigation, $this->course, $this->context);
        $node = $navigation->get('local_xertify_certificate', \navigation_node::TYPE_CUSTOM);
        $this->assertSame($visible, $node !== false);
        if ($visible) {
            $expected = new \moodle_url('/local/xertify/certificate.php', ['courseid' => $this->course->id]);
            $this->assertSame($expected->out(false), $node->action->out(false));
        }
    }

    /**
     * Original navigation cases.
     *
     * @return array
     */
    public static function navigation_provider(): array {
        return [
            [null, '', false], ['pending', '', false], ['failed', '', false],
            ['dryrun', '', false], ['issued', '', false], ['issued', 'TEST0001', true],
        ];
    }

    /**
     * Execute the real certificate controller for the owner and an authorized manager.
     *
     * @dataProvider viewer_provider
     * @param bool $manager Whether to inspect another user's issue.
     */
    public function test_certificate_controller(bool $manager): void {
        global $CFG, $DB, $USER, $PAGE, $OUTPUT;
        $issue = $this->issue(['userid' => $manager ? $this->other->id : $this->owner->id]);
        if ($manager) {
            $this->getDataGenerator()->enrol_user($this->owner->id, $this->course->id, 'manager');
        }
        $_GET = ['courseid' => $this->course->id];
        if ($manager) {
            $_GET['issueid'] = $issue->id;
        }
        $PAGE = new \moodle_page();
        $OUTPUT = new \bootstrap_renderer();
        ob_start();
        try {
            include($CFG->dirroot . '/local/xertify/certificate.php');
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = [];
        }
        $this->assertStringContainsString('TEST0001', $html);
        $this->assertStringContainsString('pdf.php?issueid=' . $issue->id, $html);
        $this->assertStringContainsString('download=1', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('https://wallet.xertify.co/certificates/TEST0001', $html);
        $this->assertStringNotContainsString('never-return-this', $html);
    }

    /**
     * Existing viewer roles.
     *
     * @return array
     */
    public static function viewer_provider(): array {
        return [[false], [true]];
    }

    /**
     * Exercise the unchanged client and its Base64 decoding using Moodle's HTTP fixture.
     *
     * @dataProvider client_response_provider
     * @param string $body Simulated HTTP body.
     * @param bool $expected Whether a valid PDF is expected.
     */
    public function test_pdf_with_real_client(string $body, bool $expected): void {
        global $CFG, $DB;
        $issue = $this->issue();
        $CFG->local_xertify_api_url = 'https://example.invalid/v4createRequest';
        $DB->insert_record('local_xertify_course', (object)[
            'courseid' => $this->course->id, 'templateid' => 'test',
            'groupid' => 'test', 'apitoken' => 'synthetic-test-token',
        ]);
        $before = $DB->get_record('local_xertify_issue', ['id' => $issue->id]);
        \curl::mock_response($body);
        $result = $this->service->get_pdf($issue->id, 'es', $this->course->id);
        $this->assertSame($expected, $result['ok']);
        $this->assertSame($expected ? '%PDF-1.7 synthetic' : null, $result['pdf']);
        $this->assertEquals($before, $DB->get_record('local_xertify_issue', ['id' => $issue->id]));
    }

    /**
     * Client boundary examples; no real HTTP requests are made.
     *
     * @return array
     */
    public static function client_response_provider(): array {
        return [
            [json_encode(['file' => base64_encode('%PDF-1.7 synthetic')]), true],
            [json_encode(['file' => base64_encode('<html>Error</html>')]), false],
            [json_encode(['file' => '**invalid base64**']), false],
            ['<html>Not JSON</html>', false],
        ];
    }
}
