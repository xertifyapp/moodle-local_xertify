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

/**
 * Architectural guard against connecting the read path to issuance or writes.
 *
 * This supplements runtime service/controller tests; it is not a substitute
 * for ownership, capability, PDF or rendering tests.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class read_only_contract_test extends \basic_testcase {
    /**
     * Read-path source must not acquire issuance or persistence dependencies.
     *
     * @dataProvider read_path_provider
     * @param string $path Relative plugin file.
     */
    public function test_read_path_has_no_issuer_or_writes(string $path): void {
        $source = file_get_contents(__DIR__ . '/../' . $path);
        $forbidden = [
            'issuer', 'issue', 'issue_for_course_completion',
            'insert_record', 'update_record', 'delete_records', 'delete_records_select',
            'set_field', 'set_field_select', 'execute', 'create_file_from_string',
        ];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            // PHP 8 combines qualified names, whereas PHP 7 returns individual parts.
            $parts = explode('\\', strtolower($token[1]));
            if ($token[0] === T_STRING || strpos(token_name($token[0]), 'T_NAME_') === 0) {
                foreach ($parts as $part) {
                    $this->assertNotContains($part, $forbidden, $path . ' must remain read-only.');
                }
            }
        }
    }

    /**
     * Files reachable from the certificate read interface.
     *
     * @return array
     */
    public static function read_path_provider(): array {
        return [
            ['certificate.php'], ['pdf.php'], ['lib.php'],
            ['classes/local/certificate_service.php'], ['classes/local/certificate_access.php'],
        ];
    }
}
