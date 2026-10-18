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

- Moodle 4.1 or later.
- PHP version supported by the installed Moodle release.
- Outbound HTTPS access from Moodle to the configured Xertify API endpoint.
- A Xertify account, API token, group ID, and template ID.

## Installation

1. Download the plugin ZIP.
2. In Moodle, go to `Site administration > Plugins > Install plugins`.
3. Upload the ZIP file and complete the installation.
4. Go to `Site administration > Plugins > Local plugins > Xertify`.
5. Configure the Xertify API URL and API token.

The default API endpoint is:

```text
https://api.xertify.co/v4createRequest
```

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

This plugin sends certificate recipient data to the configured Xertify API. It stores local issuing-attempt metadata, including the Xertify generation ID, certificate code, and verification URL, for audit and certificate access. Certificate PDF/Base64 content is not stored permanently in Moodle. See the Moodle privacy metadata declared in `classes/privacy/provider.php`.

## License

GNU GPL v3 or later.


## Shared certificate read contract (unreleased)

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
contract issues, repairs, replaces, updates or persists certificates. There is
no activity-module restriction policy in this phase.

### Contract tests

Run in a disposable Moodle PHPUnit installation, never with production database
configuration. The suite uses `advanced_testcase`, real Moodle records/roles and
a replacement for the external client transport; no Xertify API is contacted.

```sh
vendor/bin/phpunit --testsuite local_xertify_testsuite
phpcs --standard=moodle certificate.php pdf.php lib.php \
  classes/local/certificate_service.php classes/local/certificate_access.php tests
```

The plugin version and minimum Moodle requirement are intentionally unchanged.
Align the documented Moodle 4.1+ target with `version.php` during release planning.

### Course backup and restore safety (unreleased)

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
