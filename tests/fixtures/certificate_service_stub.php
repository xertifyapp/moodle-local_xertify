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
 * Replace only the transport while exercising the real service and access rules.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_service_stub extends certificate_service {
    /** @var client Test transport. */
    public $client;
    /** @var string Endpoint passed to the client factory. */
    public $apiurl;
    /** @var string Synthetic token passed to the client factory. */
    public $token;
    /** @var int Number of client constructions. */
    public $calls = 0;

    /**
     * Capture configuration without making a network request.
     *
     * @param string $apiurl Endpoint.
     * @param string $token Synthetic test token.
     * @return client
     */
    protected function create_client(string $apiurl, string $token): client {
        $this->calls++;
        $this->apiurl = $apiurl;
        $this->token = $token;
        return $this->client;
    }
}
