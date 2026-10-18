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

/**
 * English language strings for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Xertify';
$string['privacy:export:issuance'] = 'Certificate issuance';
$string['privacy:export:redacted'] = '[Redacted]';
$string['privacy:export:responsebody'] = 'Structured stored data for this issue only. Credentials, embedded documents, unverified recipients, opaque text and unsupported fields are omitted. No external data was fetched.';
$string['xertify:manage'] = 'Manage Xertify certificate settings';
$string['privacy:metadata:xertify_api'] = 'Recipient data is sent to the configured Xertify API to issue digital certificates.';
$string['privacy:metadata:xertify_api:firstname'] = 'The recipient first name.';
$string['privacy:metadata:xertify_api:lastname'] = 'The recipient last name.';
$string['privacy:metadata:xertify_api:email'] = 'The recipient email address.';
$string['privacy:metadata:xertify_api:idtype'] = 'The recipient document type.';
$string['privacy:metadata:xertify_api:idnum'] = 'The recipient document number.';
$string['privacy:metadata:xertify_api:phone'] = 'The recipient phone number, when available.';
$string['privacy:metadata:xertify_api:course'] = 'Course data used in certificate dynamic fields.';
$string['privacy:metadata:xertify_api:profilefields'] = 'Custom profile fields used as certificate dynamic fields.';
$string['privacy:metadata:local_xertify_issue'] = 'Stores Xertify certificate issuing attempts for audit and troubleshooting.';
$string['privacy:metadata:local_xertify_issue:courseid'] = 'The Moodle course related to the issuing attempt.';
$string['privacy:metadata:local_xertify_issue:id'] = 'The local identifier of the user-associated issuance record.';
$string['privacy:metadata:local_xertify_issue:userid'] = 'The Moodle user related to the issuing attempt.';
$string['privacy:metadata:local_xertify_issue:status'] = 'The issuing attempt status.';
$string['privacy:metadata:local_xertify_issue:requesthash'] = 'A hash of the Xertify API request payload.';
$string['privacy:metadata:local_xertify_issue:responsecode'] = 'The HTTP response code returned by the Xertify API.';
$string['privacy:metadata:local_xertify_issue:responsebody'] = 'The response body returned by the Xertify API or the recorded dry-run payload.';
$string['privacy:metadata:local_xertify_issue:timecreated'] = 'The time when the issuing attempt was created.';
$string['privacy:metadata:local_xertify_issue:timemodified'] = 'The time when the issuing attempt was last updated.';
$string['apiurl'] = 'Xertify API URL';
$string['apiurl_desc'] = 'Endpoint that receives certificate requests. Use /v4createRequest for the current Xertify API.';
$string['apitoken'] = 'Xertify API token';
$string['apitoken_desc'] = 'Bearer token from Xertify. A course-level token can override this value.';
$string['defaultidtype'] = 'Default document type';
$string['defaultidtype_desc'] = 'Document type sent to Xertify when the course does not override it.';
$string['dryrun'] = 'Simulate only - do not issue certificates';
$string['dryrun_desc'] = 'Keep this disabled for real tests and production. If enabled, Moodle only records the request locally: it does NOT call the Xertify API and does NOT issue certificates.';
$string['courseconfig'] = 'Xertify certificates';
$string['enabled'] = 'Enable automatic issuing';
$string['templateid'] = 'Xertify template ID';
$string['groupid'] = 'Xertify group ID';
$string['idtype'] = 'Document type';
$string['courseapitoken'] = 'Course API token override';
$string['emitrule'] = 'Issue rule';
$string['emitrule_course_completed'] = 'Course completed';
$string['coursedynamicdata'] = 'Course dynamic data';
$string['coursedynamicdata_help'] = 'Enter one dynamic field per line using key=value. The key should match the Xertify template variable without ${}. Example: ciudad=Bogota';
$string['invaliddynamicdata'] = 'Course dynamic data must use one key=value pair per line. Keys must start with a letter and contain only letters, numbers, and underscores.';
$string['notifications'] = 'Xertify notifications';
$string['notification_email'] = 'Send email notification';
$string['notification_sms'] = 'Send SMS notification';
$string['notification_whatsapp'] = 'Send WhatsApp notification';
$string['savechanges'] = 'Save changes';
$string['settingssaved'] = 'Xertify configuration saved.';
$string['missingconfig'] = 'Xertify course configuration is incomplete.';
$string['resetattempts'] = 'Clear issuing attempts';
$string['resetattemptsconfirm'] = 'This removes the local Xertify issuing-attempt history for this course. Use it only when you need to repeat tests with the same learners.';
$string['resetattemptsdone'] = 'Xertify issuing attempts were cleared for this course.';
$string['resetattempt'] = 'Clear attempt';
$string['resetattemptdone'] = 'The selected Xertify issuing attempt was cleared.';
$string['recentattempts'] = 'Recent issuing attempts';
$string['student'] = 'Student';
$string['status'] = 'Status';
$string['responsecode'] = 'Response code';
$string['timemodified'] = 'Last modified';

$string['mycertificate'] = 'My Xertify certificate';
$string['certificateavailable'] = 'Your certificate is available.';
$string['certificatenotavailable'] = 'This Xertify certificate is not available.';
$string['certificatecode'] = 'Certificate code';
$string['viewcertificate'] = 'View certificate';
$string['downloadpdf'] = 'Download PDF';
$string['verifyxertify'] = 'Verify in Xertify';
$string['pdfserviceerror'] = 'The certificate PDF could not be obtained from Xertify. Please try again later.';
$string['privacy:metadata:local_xertify_issue:generationid'] = 'The Xertify generation identifier associated with the issue.';
$string['privacy:metadata:local_xertify_issue:certificatecode'] = 'The individual Xertify certificate code.';
$string['privacy:metadata:local_xertify_issue:certificateurl'] = 'The public Xertify certificate verification URL.';
