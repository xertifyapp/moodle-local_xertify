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
}
