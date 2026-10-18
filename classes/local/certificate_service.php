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
 * Read-only certificate contract for Moodle consumers.
 *
 * Consumers must establish course login and, where applicable, module access.
 * Every data/PDF operation additionally checks the current actor's ownership
 * or manage capability. No method emits, repairs or persists a certificate.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_service {
    /** @var string Fields required for authorization and safe presentation only. */
    private const ISSUE_FIELDS = 'id,courseid,userid,status,responsecode,generationid,certificatecode,'
        . 'certificateurl,timecreated,timemodified';

    /**
     * Get safe presentation data for a course and owner.
     *
     * @param int $courseid Expected course ID.
     * @param int|null $userid Owner; defaults to the authenticated user.
     * @param \context|null $context Course or module context.
     * @return array Safe metadata; state=noissue when no association exists.
     */
    public function get_for_user(int $courseid, ?int $userid = null, ?\context $context = null): array {
        global $DB, $USER;

        $userid = $userid ?? (int)$USER->id;
        $context = certificate_access::require_user($courseid, $userid, $context);
        $issue = $DB->get_record('local_xertify_issue', ['courseid' => $courseid, 'userid' => $userid], self::ISSUE_FIELDS);
        return $this->present($issue ?: null, $courseid, $context);
    }

    /**
     * Get an authorized issue by ID; never returns the raw database record.
     *
     * @param int $issueid Persisted issue ID.
     * @param int|null $courseid Expected course, required when known by the caller.
     * @param \context|null $context Course or module context.
     * @return array Safe certificate metadata.
     */
    public function get_by_id(int $issueid, ?int $courseid = null, ?\context $context = null): array {
        global $DB;

        $issue = $DB->get_record('local_xertify_issue', ['id' => $issueid], self::ISSUE_FIELDS, MUST_EXIST);
        $courseid = $courseid ?? (int)$issue->courseid;
        $context = certificate_access::require_issue($issue, $courseid, $context);
        return $this->present($issue, $courseid, $context);
    }

    /**
     * Build a Wallet link without accepting arbitrary external destinations.
     *
     * @param string $certificatecode Stored certificate code.
     * @param string $certificateurl Stored candidate URL.
     * @return string Safe HTTPS URL, or an empty string without a code.
     */
    public static function get_wallet_url(string $certificatecode, string $certificateurl = ''): string {
        if (empty($certificatecode)) {
            return '';
        }
        $candidate = trim($certificateurl);
        $parts = parse_url($candidate);
        if (
            is_array($parts)
            && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && strtolower((string)($parts['host'] ?? '')) === 'wallet.xertify.co'
            && !isset($parts['user']) && !isset($parts['pass'])
            && (!isset($parts['port']) || $parts['port'] === 443)
            && !preg_match('/[\x00-\x20\x7f\\\\]/', $candidate)
        ) {
            return $candidate;
        }
        return 'https://wallet.xertify.co/certificates/' . rawurlencode($certificatecode);
    }

    /**
     * Request an authorized certificate PDF, in memory and on demand.
     *
     * This reloads the issue: caller-supplied presentation data or codes cannot
     * authorize a download. Remote failures never change the local issue.
     *
     * @param int $issueid Persisted issue ID.
     * @param string $language Requested language; unsupported languages use Spanish.
     * @param int|null $courseid Expected course when known.
     * @param \context|null $context Course or module context.
     * @return array ok, state, httpcode, pdf (bytes or null), filename.
     */
    public function get_pdf(
        int $issueid,
        string $language = 'es',
        ?int $courseid = null,
        ?\context $context = null
    ): array {
        $certificate = $this->get_by_id($issueid, $courseid, $context);
        if (!$certificate['canrequestpdf']) {
            throw new \moodle_exception('certificatenotavailable', 'local_xertify');
        }
        $courseconfig = config::get_course_config($certificate['courseid']);
        $apiurl = config::get_api_url();
        $token = config::get_api_token($courseconfig);
        if ($apiurl === '' || $token === '') {
            throw new \moodle_exception('missingconfig', 'local_xertify');
        }
        $language = substr($language, 0, 2);
        if (!in_array($language, ['es', 'en'], true)) {
            $language = 'es';
        }
        $client = $this->create_client($apiurl, $token);
        $response = $client->get_pdf($certificate['certificatecode'], $language);
        $httpcode = (int)($response['code'] ?? 0);
        $pdf = $response['pdf'] ?? null;
        $ok = !empty($response['ok']) && $httpcode >= 200 && $httpcode < 300
            && is_string($pdf) && substr($pdf, 0, 5) === '%PDF-';
        $state = 'error';
        if ($ok) {
            $state = 'available';
        } else if (in_array($httpcode, [404, 410], true)) {
            $state = 'unavailable';
        }
        return [
            'ok' => $ok,
            'state' => $state,
            'httpcode' => $httpcode,
            'pdf' => $ok ? $pdf : null,
            'filename' => clean_filename('xertify-' . $certificate['certificatecode'] . '.pdf'),
        ];
    }

    /**
     * Construct the existing API client; isolated seam for transport tests.
     *
     * @param string $apiurl Resolved API endpoint.
     * @param string $token Resolved server-side token.
     * @return client
     */
    protected function create_client(string $apiurl, string $token): client {
        return new client($apiurl, $token);
    }

    /**
     * Interpret local state and whitelist fields after authorization.
     *
     * @param \stdClass|null $issue Loaded issue or null.
     * @param int $courseid Expected course.
     * @param \context $context Authorized context.
     * @return array Safe metadata; remote availability is deliberately unknown.
     */
    private function present(?\stdClass $issue, int $courseid, \context $context): array {
        $status = $issue ? (string)$issue->status : null;
        $code = $issue ? (string)($issue->certificatecode ?? '') : '';
        $state = 'unknown';
        if (!$issue) {
            $state = 'noissue';
        } else if ($status === 'issued') {
            $state = empty($code) ? 'issuedwithoutcode' : 'issued';
        } else if (in_array($status, ['pending', 'failed', 'dryrun'], true)) {
            $state = $status;
        }
        $data = [
            'issueid' => $issue ? (int)$issue->id : null,
            'courseid' => $courseid,
            'status' => $status,
            'state' => $state,
            'generationid' => $issue ? (string)($issue->generationid ?? '') : '',
            'certificatecode' => $code,
            'walleturl' => self::get_wallet_url($code, $issue ? (string)($issue->certificateurl ?? '') : ''),
            'timecreated' => $issue ? (int)$issue->timecreated : null,
            'timemodified' => $issue ? (int)$issue->timemodified : null,
            'canrequestpdf' => $state === 'issued',
            'pdfavailability' => 'unknown',
        ];
        if (has_capability('local/xertify:manage', $context)) {
            $data['responsecode'] = $issue && $issue->responsecode !== null ? (int)$issue->responsecode : null;
        }
        return $data;
    }
}
