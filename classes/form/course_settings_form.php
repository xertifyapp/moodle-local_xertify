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

namespace local_xertify\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Course settings form for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_xertify'));
        $mform->setType('enabled', PARAM_BOOL);

        $mform->addElement('text', 'templateid', get_string('templateid', 'local_xertify'), ['size' => 48]);
        $mform->setType('templateid', PARAM_ALPHANUMEXT);
        $mform->hideIf('templateid', 'enabled', 'notchecked');

        $mform->addElement('text', 'groupid', get_string('groupid', 'local_xertify'), ['size' => 48]);
        $mform->setType('groupid', PARAM_ALPHANUMEXT);
        $mform->hideIf('groupid', 'enabled', 'notchecked');

        $mform->addElement('text', 'idtype', get_string('idtype', 'local_xertify'), ['size' => 12]);
        $mform->setType('idtype', PARAM_ALPHANUMEXT);
        $mform->hideIf('idtype', 'enabled', 'notchecked');

        $mform->addElement('passwordunmask', 'apitoken', get_string('courseapitoken', 'local_xertify'), ['size' => 64]);
        $mform->setType('apitoken', PARAM_RAW_TRIMMED);
        $mform->hideIf('apitoken', 'enabled', 'notchecked');

        $rules = [
            'course_completed' => get_string('emitrule_course_completed', 'local_xertify'),
        ];
        $mform->addElement('select', 'emitrule', get_string('emitrule', 'local_xertify'), $rules);
        $mform->setDefault('emitrule', 'course_completed');
        $mform->hideIf('emitrule', 'enabled', 'notchecked');

        $mform->addElement('textarea', 'dynamicdata', get_string('coursedynamicdata', 'local_xertify'), [
            'cols' => 64,
            'rows' => 8,
        ]);
        $mform->setType('dynamicdata', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('dynamicdata', 'coursedynamicdata', 'local_xertify');
        $mform->hideIf('dynamicdata', 'enabled', 'notchecked');

        $mform->addElement('header', 'notificationsheader', get_string('notifications', 'local_xertify'));
        $mform->hideIf('notificationsheader', 'enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'notification_email', get_string('notification_email', 'local_xertify'));
        $mform->setType('notification_email', PARAM_BOOL);
        $mform->hideIf('notification_email', 'enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'notification_sms', get_string('notification_sms', 'local_xertify'));
        $mform->setType('notification_sms', PARAM_BOOL);
        $mform->hideIf('notification_sms', 'enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'notification_whatsapp', get_string('notification_whatsapp', 'local_xertify'));
        $mform->setType('notification_whatsapp', PARAM_BOOL);
        $mform->hideIf('notification_whatsapp', 'enabled', 'notchecked');

        $this->add_action_buttons(true, get_string('savechanges', 'local_xertify'));
    }

    /**
     * Validate course settings.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['enabled'])) {
            if (empty($data['templateid'])) {
                $errors['templateid'] = get_string('required');
            }
            if (empty($data['groupid'])) {
                $errors['groupid'] = get_string('required');
            }
            if (!empty($data['dynamicdata'])) {
                foreach (preg_split('/\r\n|\r|\n/', $data['dynamicdata']) as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) {
                        continue;
                    }
                    if (strpos($line, '=') === false) {
                        $errors['dynamicdata'] = get_string('invaliddynamicdata', 'local_xertify');
                        break;
                    }
                    [$key] = explode('=', $line, 2);
                    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', trim($key))) {
                        $errors['dynamicdata'] = get_string('invaliddynamicdata', 'local_xertify');
                        break;
                    }
                }
            }
        }
        return $errors;
    }
}
