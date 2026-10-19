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
 * Regression tests for the actual administrative attempts table.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class course_settings_test extends \advanced_testcase {
    /**
     * Create an issue whose identifier must remain distinct from the user's identifier.
     *
     * @param int $courseid Course identifier.
     * @param int $userid User identifier.
     * @param int $modified Modification timestamp.
     * @return \stdClass Persisted issue.
     */
    private function issue(int $courseid, int $userid, int $modified = 1700000100): \stdClass {
        global $DB;
        $issue = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => 'failed<&>',
            'requesthash' => 'rb01-synthetic',
            'responsecode' => 410,
            'responsebody' => 'RB01_RESPONSE_NOT_FOR_DISPLAY',
            'timecreated' => 1700000000,
            'timemodified' => $modified,
        ];
        $issue->id = $DB->insert_record('local_xertify_issue', $issue);
        return $issue;
    }

    /**
     * Render the controller without submitting the configuration form or reset actions.
     *
     * @param int $courseid Course identifier.
     * @return \DOMXPath Parsed page; raw HTML is not written to disk.
     */
    private function page(int $courseid): \DOMXPath {
        global $CFG, $DB, $USER, $PAGE, $OUTPUT;
        $oldget = $_GET;
        $oldpost = $_POST;
        $oldpage = $PAGE;
        $oldoutput = $OUTPUT;
        $_GET = ['courseid' => $courseid];
        $_POST = [];
        $PAGE = new \moodle_page();
        $OUTPUT = new \bootstrap_renderer();
        ob_start();
        try {
            include($CFG->dirroot . '/local/xertify/course_settings.php');
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = $oldget;
            $_POST = $oldpost;
            $PAGE = $oldpage;
            $OUTPUT = $oldoutput;
        }
        $this->assertStringNotContainsString('RB01_RESPONSE_NOT_FOR_DISPLAY', $html);
        $doc = new \DOMDocument();
        $oldlibxml = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($doc->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($oldlibxml);
        }
        return new \DOMXPath($doc);
    }

    /**
     * The real table must agree with core fullname() using the complete user record.
     *
     * @dataProvider name_format_provider
     * @param string $format Site full-name format.
     */
    public function test_configured_names_and_issue_actions(string $format): void {
        global $CFG, $DB, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user([
            'firstname' => 'Matrix',
            'lastname' => 'student_b',
            'middlename' => 'MIDDLE_NAME_PROBE',
            'alternatename' => 'ALTERNATE_NAME_PROBE',
            'firstnamephonetic' => 'FIRST_PHONETIC',
            'lastnamephonetic' => 'LAST_PHONETIC',
            'email' => 'matrix-rb01@example.invalid',
        ]);
        $issue = $this->issue((int)$course->id, (int)$user->id);
        $this->assertNotEquals($user->id, $issue->id, 'Fixture must detect a user/issue ID collision.');
        $completeuser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $before = $DB->get_records('local_xertify_issue');
        $oldformat = $CFG->fullnamedisplay;
        $hassessionformat = property_exists($SESSION, 'fullnamedisplay');
        $oldsessionformat = $SESSION->fullnamedisplay ?? null;
        try {
            $CFG->fullnamedisplay = $format;
            unset($SESSION->fullnamedisplay);
            $expected = fullname($completeuser);
            $xpath = $this->page((int)$course->id);
            $rows = $xpath->query('//tr[td]');
            $this->assertCount(1, $rows);
            $cells = $rows->item(0)->getElementsByTagName('td');
            $this->assertCount(5, $cells);
            $this->assertSame($expected . $user->email, trim($cells->item(0)->textContent));
            $this->assertSame($issue->status, trim($cells->item(1)->textContent));
            $this->assertSame('410', trim($cells->item(2)->textContent));
            $this->assertSame(userdate($issue->timemodified), trim($cells->item(3)->textContent));
            $form = $xpath->query('.//form', $cells->item(4))->item(0);
            $this->assertNotNull($form);
            $this->assertSame('post', strtolower($form->getAttribute('method')));
            foreach (['courseid' => $course->id, 'resetissueid' => $issue->id] as $name => $value) {
                $input = $xpath->query('.//input[@name="' . $name . '"]', $form)->item(0);
                $this->assertNotNull($input);
                $this->assertSame((string)$value, $input->getAttribute('value'));
            }
            $this->assertCount(1, $xpath->query('.//input[@name="sesskey"]', $form));
            $this->assertEquals($before, $DB->get_records('local_xertify_issue'));
        } finally {
            $CFG->fullnamedisplay = $oldformat;
            if ($hassessionformat) {
                $SESSION->fullnamedisplay = $oldsessionformat;
            } else {
                unset($SESSION->fullnamedisplay);
            }
        }
    }

    /**
     * Every core name field is exercised without duplicating Moodle's name formatter.
     *
     * @return array Formats supported by Moodle 4.1 through 5.2.
     */
    public static function name_format_provider(): array {
        return [
            'standard' => ['firstname lastname'],
            'middle name' => ['firstname middlename lastname'],
            'alternate name' => ['alternatename'],
            'phonetic names' => ['firstnamephonetic lastnamephonetic'],
        ];
    }

    /**
     * Keep the newest twenty issues of this course, with reset actions for those issues.
     */
    public function test_recent_attempts_keep_course_order_and_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $expected = [];
        for ($i = 0; $i < 21; $i++) {
            $user = $generator->create_user(['email' => 'rb01-' . $i . '@example.invalid']);
            $issue = $this->issue((int)$course->id, (int)$user->id, 1700000100 + $i);
            $expected[] = (string)$issue->id;
        }
        $this->issue((int)$othercourse->id, (int)$user->id, 1800000000);
        $before = $DB->get_records('local_xertify_issue');
        $xpath = $this->page((int)$course->id);
        $actual = [];
        foreach ($xpath->query('//tr[td]//input[@name="resetissueid"]') as $input) {
            $actual[] = $input->getAttribute('value');
        }
        $this->assertSame(array_slice(array_reverse($expected), 0, 20), $actual);
        $this->assertCount(20, $xpath->query('//tr[td]'));
        $this->assertEquals($before, $DB->get_records('local_xertify_issue'));
    }

    /**
     * Additional name fields remain behind the existing management capability.
     */
    public function test_student_cannot_view_attempts(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->issue((int)$course->id, (int)$student->id);
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        $this->page((int)$course->id);
    }
}
