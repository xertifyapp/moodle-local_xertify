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

namespace local_xertify\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * HTTP client for the Xertify certificate issuing API.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string Xertify API URL. */
    private string $apiurl;

    /** @var string Xertify API bearer token. */
    private string $token;

    /**
     * Constructor.
     *
     * @param string $apiurl Xertify API URL.
     * @param string $token Xertify API bearer token.
     */
    public function __construct(string $apiurl, string $token) {
        $this->apiurl = $apiurl;
        $this->token = $token;
    }

    /**
     * Send a certificate issuing payload to Xertify.
     *
     * @param array $payload Request payload.
     * @return array Response information.
     */
    public function issue(array $payload): array {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ]);

        $body = json_encode($payload);
        $response = $curl->post($this->apiurl, $body, [
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        $info = $curl->get_info();
        $code = isset($info['http_code']) ? (int)$info['http_code'] : 0;

        return [
            'code' => $code,
            'body' => is_string($response) ? $response : json_encode($response),
            'ok' => $code >= 200 && $code < 300,
        ];
    }

    /**
     * Retrieve an issued certificate PDF from Xertify.
     *
     * @param string $certificatecode Xertify certificate code.
     * @param string $language Preferred language.
     * @return array Response information and decoded PDF bytes.
     */
    public function get_pdf(string $certificatecode, string $language = 'es'): array {
        $pdfurl = $this->build_endpoint_url('getPdf');
        if ($pdfurl === '') {
            return [
                'ok' => false,
                'code' => 0,
                'pdf' => null,
            ];
        }

        $payload = [
            'codeCertificate' => $certificatecode,
            'language' => $language,
            'type' => 'pdf',
            'stored' => true,
            'viewMode' => 'regular',
        ];

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = $curl->post($pdfurl, json_encode($payload), [
            'CURLOPT_TIMEOUT' => 90,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        $info = $curl->get_info();
        $code = isset($info['http_code']) ? (int)$info['http_code'] : 0;

        if ($code < 200 || $code >= 300 || !is_string($response)) {
            return [
                'ok' => false,
                'code' => $code,
                'pdf' => null,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['file']) || !is_string($decoded['file'])) {
            return [
                'ok' => false,
                'code' => $code,
                'pdf' => null,
            ];
        }

        $pdf = base64_decode($decoded['file'], true);
        if ($pdf === false || strlen($pdf) < 5 || substr($pdf, 0, 5) !== '%PDF-') {
            return [
                'ok' => false,
                'code' => $code,
                'pdf' => null,
            ];
        }

        return [
            'ok' => true,
            'code' => $code,
            'pdf' => $pdf,
        ];
    }

    /**
     * Build another Xertify API endpoint from the configured API URL.
     *
     * Preserves an optional API Gateway path prefix and replaces only
     * the final endpoint segment.
     *
     * @param string $endpoint Endpoint name.
     * @return string
     */
    private function build_endpoint_url(string $endpoint): string {
        $parts = parse_url($this->apiurl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            return '';
        }

        $path = isset($parts['path']) ? (string)$parts['path'] : '';
        $basepath = preg_replace('#/[^/]*$#', '', $path);
        if ($basepath === null) {
            return '';
        }

        $url = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $url .= ':' . (int)$parts['port'];
        }

        return $url . rtrim($basepath, '/') . '/' . ltrim($endpoint, '/');
    }

}
