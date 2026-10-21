# Xertify Certificates for Moodle

Xertify Certificates is a Moodle local plugin that issues Xertify digital certificates automatically when a learner completes a course.

## Features

- Automatic issuing on Moodle course completion.
- Global Xertify API token with optional course-level override.
- Course-level Xertify template and group configuration.
- Configurable email, SMS, and WhatsApp notification flags.
- Dynamic certificate fields from Moodle course data and custom user profile fields.
- Course-level dynamic certificate fields managed by course administrators.
- Optional simulation mode that records requests locally without calling the Xertify API.
- Local audit records for issuing attempts and API responses.
- Course-level issuing-attempt reset action for repeated test runs.
- Course backup and restore support for Xertify course configuration.
- Learner-facing **My Xertify certificate** page after successful issuance.
- Inline certificate PDF viewing and secure PDF download from Moodle.
- Direct certificate verification link to the Xertify Wallet.
- Certificate PDFs are retrieved from Xertify on demand and are not stored permanently in Moodle.

## Requirements

- Moodle 4.1–5.2; validated with the latest stable patches in the compatibility matrix.
- Xertify Certificates 1.1.1, plugin build `2026102100`.
- PHP version supported by the installed Moodle release.
- Outbound HTTPS access from Moodle to the configured Xertify API endpoint.
- A Xertify account, API token, group ID, and template ID.

## Installation and optional activity

1. Install or update **Xertify Certificates (`local_xertify`) 1.1.1** first.
   Upload `local_xertify_1.1.1.zip` in **Site administration > Plugins > Install plugins**
   and complete Moodle's upgrade. For manual installation, extract `xertify/`
   into `local/` before Moodle 5.1 or `public/local/` in Moodle 5.1 and later.
2. To provide a certificate activity, install the separate
   **`mod_xertifycertificate` 1.0.1** package next. Upload
   `mod_xertifycertificate_1.0.1.zip`, or extract `xertifycertificate/` into
   `mod/` before Moodle 5.1 or `public/mod/` in Moodle 5.1 and later,
   then complete the upgrade.
3. Configure the Xertify integration globally and in each intended course.
4. Turn course editing on and select **Add an activity or resource >
   Xertify Certificate**.
5. Optionally put it in a **Certification** section, then set its name,
   description, standard access restrictions and optional view completion.

Token, Template ID, Group ID and issuance settings belong only to the local
plugin. The activity package is installed separately and contains no duplicate
integration settings. Existing courses do not gain activities automatically.

## Upgrading from local_xertify 1.0.3

**Do not uninstall the existing local plugin. Upgrade it in place.**

1. Back up Moodle files, moodledata and the database according to the site's
   administration policy.
2. Replace/update `local_xertify` 1.0.3 with 1.1.1 and complete Moodle's upgrade.
3. Verify the existing course integration and certificate associations.
4. Install `mod_xertifycertificate` 1.0.1 after the local upgrade succeeds.
5. Add the activity to each course where teachers want it available.

The module requires local plugin build `2026102100` or later; local 1.0.3 and 1.1.0 are
insufficient. This upgrade preserves stored configuration and certificate
associations; it does not reissue certificates or create course activities.

## Course Configuration

Open a Moodle course and go to:

```text
More > Xertify certificates
```

Configure:

- Enable automatic issuing.
- Xertify template ID.
- Xertify group ID.
- Document type.
- Optional course API token override.
- Notification flags.
- Course dynamic data as `key=value` lines.

## Recipient Data

The plugin sends these Moodle user fields to Xertify:

- `firstname`
- `lastname`
- `email`
- `idnumber`
- `phone1` or `phone2`, when available

The document number is resolved from the first available value in this order:

1. Moodle native user ID number (`idnumber`).
2. Custom profile field `document`.
3. Custom profile field `documento`.
4. Custom profile field `idnum`.
5. Custom profile field `numdoc`.

## Dynamic Data

The plugin sends `dynamicData` as a flat JSON object. Moodle custom profile fields are included using their short name, and also with a `profile_` prefix for compatibility.
Course administrators can also define course-level dynamic data in the course Xertify settings using one `key=value` pair per line.

Example:

```json
{
  "curso": "Course name",
  "dia": "13",
  "mes": "julio",
  "anio": "2026",
  "ciudad": "Bogota",
  "profile_ciudad": "Bogota"
}
```

For Xertify templates, the Moodle custom profile field short name should match the template variable. For example, `${ciudad}` should use a Moodle custom profile field with short name `ciudad`.

Course-level dynamic data is recommended for variables that are the same for all learners in a course, such as `evento`, `intensidad`, `ciudad`, `tipoevento`, or `fechaevento`.

## Repeating Tests

The plugin avoids duplicate Xertify API calls for the same learner and course once an issuing attempt has been recorded. To repeat tests with the same learner, open the course Xertify settings page and use **Clear attempt** in the recent attempts table before completing the course again. Use **Clear issuing attempts** only when you need to clear all local issuing attempts for the course.

## Student Certificate Access

After Xertify successfully issues a certificate, the learner can access **My Xertify certificate** from the Moodle course.

The certificate page allows the learner to:

- View the certificate PDF inside Moodle.
- Download the certificate PDF.
- Open the public certificate verification page in the Xertify Wallet.

Moodle stores the Xertify generation ID, certificate code, and verification URL. The PDF itself is retrieved from Xertify on demand through an authenticated server-to-server request and is not stored permanently in Moodle.

## Privacy

This plugin sends recipient data to the configured Xertify API during normal
issuance. Locally it stores the user/course association, issue status, request
hash, HTTP result, stored response or dry-run payload, generation ID, certificate
code/URL and timestamps. Moodle Privacy API can export and delete these records
within approved course contexts, including an approved subset of users.

Privacy operations are local: they do not contact Xertify, issue a certificate or
revoke/delete an external certificate. Deleting student data preserves the
course's administrative configuration, enabled state and API token. It removes
the local certificate association and duplicate-attempt record; any future normal
course-completion event remains governed by the unchanged issuer.

Exports include the stored issue fields and a safe structured response projection.
Matching certificate entries and dry-run participants identified by the issuer's
course/user key are retained, including their scalar personal/profile data.
Credentials, embedded documents, unverified recipients, opaque messages and
unsupported structures are omitted with an explanatory note. This also protects
against older responses whose PDF/secret cleanup was incomplete. Export never
changes the stored response or fetches additional data. Orphan issues without a
valid course/context are skipped, not assigned a fictitious context or deleted
outside an approved scope; resolving such historical inconsistencies remains an
administrative task.

## License

GNU GPL v3 or later.


## Shared certificate read contract

`local_xertify\local\certificate_service` provides the read-only contract for
certificate consumers. Callers must first establish Moodle course login and,
where applicable, module availability and view capability. The service always
checks the current authenticated actor's ownership or `local/xertify:manage`.
Pass the expected course and context whenever they are known; module contexts
are accepted only when they belong to that course. Never authorize with a code
or a presentation array supplied by a browser.

Public operations:

- `get_for_user(int $courseid, ?int $userid = null, ?\context $context = null): array`
  defaults to the current user; another owner requires management permission.
- `get_by_id(int $issueid, ?int $courseid = null, ?\context $context = null): array`
  reloads and authorizes the stored issue. Omitting the expected course is for
  endpoints that resolve their course from the issue, such as the existing PDF route.
- `get_wallet_url(string $certificatecode, string $certificateurl = ''): string`
  is a pure URL allowlist/fallback operation, not an authorization mechanism.
- `get_pdf(int $issueid, string $language = 'es', ?int $courseid = null, ?\context $context = null): array`
  reauthorizes the persisted association and uses the existing API client. The
  token stays server-side and only decoded, validated PDF bytes are returned.

`certificate_access::require_user(int $courseid, int $userid, ?\context $context = null): \context`
and `certificate_access::require_issue(\stdClass $issue, int $courseid, ?\context $context = null): \context`
centralize ownership and course/context consistency. The latter accepts a trusted
server-loaded record, never an object from the request. Neither replaces Moodle
login or activity availability checks.

Presentation arrays contain `issueid`, `courseid`, `status`, `state`,
`generationid`, `certificatecode`, `walleturl`, `timecreated`, `timemodified`,
`canrequestpdf` and `pdfavailability`. Only managers receive `responsecode`.
They never contain response bodies, request hashes, credentials or PDF/Base64 data.

Interpreted states are `noissue`, `pending`, `issued`, `issuedwithoutcode`,
`failed`, `dryrun` and `unknown`. `canrequestpdf` means locally issued with a
code, not remote availability. `pdfavailability` remains `unknown` until an
explicit PDF request. The PDF result has `ok`, `state`, `httpcode`, `pdf` and
`filename`: state is `available` for a validated PDF, `unavailable` for HTTP
404/410, otherwise `error`. It does not infer why a remote certificate is missing.
Authorization, missing-issue, missing-configuration and missing-code conditions
raise Moodle exceptions. Consumers render/stream results; the service does not.

The existing pages retain their interface, iframe and inline/download behaviour.
Navigation makes one issue query and no external request. No method in this
contract issues, repairs, replaces, updates or persists certificates. Activity-module restrictions also protect the legacy routes as described below.

### Contract tests

Run in a disposable Moodle PHPUnit installation, never with production database
configuration. The suite uses `advanced_testcase`, real Moodle records/roles and
a replacement for the external client transport; no Xertify API is contacted.

```sh
vendor/bin/phpunit --testsuite local_xertify_testsuite
phpcs --standard=moodle certificate.php pdf.php lib.php \
  classes/local/certificate_service.php classes/local/certificate_access.php tests
```

Release metadata declares Moodle 4.1 as the minimum and a supported branch range of 4.1–5.2.

### Course backup and restore safety

Course backups include only portable Xertify configuration: original enabled
state, template/group IDs, document type, rule, dynamic data, notification flags
and timestamps. They exclude the API token and all issue/certificate records.
Dynamic data is administrator-authored certificate content; do not put credentials
in that free-text field or in other course content.

When the destination has no Xertify configuration, restore always sets
`enabled = 0` and `apitoken = ''`, including for old backups containing a token.
An administrator must review Template ID, Group ID, token source, rule and
notifications before enabling issuance. An empty course token uses the existing
global/environment fallback; disabled issuance prevents automatic use before review.
Course duplication follows the same policy and does not inherit certificates.

If a destination configuration already exists, restore preserves **all** its
fields, including activation and token, instead of overwriting it. This applies
to enabled and disabled integrations, including merge and replace-content restores.
If Moodle's "Overwrite course configuration" option is disabled, core skips the
course plugin configuration entirely; a destination without an integration stays
without one. Backup/restore does not call the issuer or the Xertify API.

## Optional activity access policy

The optional activity is the separate `mod_xertifycertificate` 1.0.1 package,
installed independently at `mod/xertifycertificate`. The local plugin has no
dependency on the activity and continues to support courses without it.

`activity_access` detects instances through core `get_fast_modinfo()`. Courses
without instances retain their old behavior. With instances, legacy certificate
and PDF routes require at least one user-visible instance with the view
capability; navigation links to that instance. Multiple restrictions use OR.
`local/xertify:manage` retains administrative legacy access, consistent with the
existing issue-inspection permission. No remote calls are made by this policy.
Module routes enforce each activity's own availability and current-user access.

Activity view completion is optional and does not mean certificate issuance.
Do not make the certificate activity a course-completion prerequisite when its
availability depends on completing that same course. Core completion can still
trigger the existing course-completed observer under administrator configuration.

## Partial restore and Moodle App scope

If a partial restore omits an activity referenced by an access restriction,
Moodle may retain an unresolved condition. In the tested Moodle 4.1–5.2 patches,
this becomes `cm = 0` and students remain blocked. Teachers must review the
restriction; the plugin does not invent a replacement prerequisite.

The initial release supports Moodle's responsive web experience. Dedicated
native Moodle App integration is outside this release's scope.

## Language distribution

This release distributes English strings only for Moodle Marketplace compliance.
Spanish translation source has been preserved for migration to Moodle AMOS.
It has not been published to AMOS as part of this release. When a plugin-specific
translation is not installed, Moodle falls back to the bundled English strings.
Certificate issuance, authorization, PDF, backup/restore and Privacy behavior are
unchanged by this packaging update.
