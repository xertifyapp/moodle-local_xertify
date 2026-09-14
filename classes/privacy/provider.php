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

namespace local_xertify\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy metadata provider for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe data sent to Xertify and stored locally.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('xertify_api', [
            'firstname' => 'privacy:metadata:xertify_api:firstname',
            'lastname' => 'privacy:metadata:xertify_api:lastname',
            'email' => 'privacy:metadata:xertify_api:email',
            'idtype' => 'privacy:metadata:xertify_api:idtype',
            'idnum' => 'privacy:metadata:xertify_api:idnum',
            'phone' => 'privacy:metadata:xertify_api:phone',
            'course' => 'privacy:metadata:xertify_api:course',
            'profilefields' => 'privacy:metadata:xertify_api:profilefields',
        ], 'privacy:metadata:xertify_api');

        $collection->add_database_table('local_xertify_issue', [
            'courseid' => 'privacy:metadata:local_xertify_issue:courseid',
            'userid' => 'privacy:metadata:local_xertify_issue:userid',
            'status' => 'privacy:metadata:local_xertify_issue:status',
            'requesthash' => 'privacy:metadata:local_xertify_issue:requesthash',
            'responsecode' => 'privacy:metadata:local_xertify_issue:responsecode',
            'responsebody' => 'privacy:metadata:local_xertify_issue:responsebody',
            'timecreated' => 'privacy:metadata:local_xertify_issue:timecreated',
            'timemodified' => 'privacy:metadata:local_xertify_issue:timemodified',
        ], 'privacy:metadata:local_xertify_issue');

        return $collection;
    }
}
