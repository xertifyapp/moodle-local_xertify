# Changelog

## 1.0.3 - 2026-09-15

- Added learner-facing **My Xertify certificate** page inside Moodle courses.
- Added secure inline certificate PDF viewing and PDF download.
- Added direct certificate verification link to the Xertify Wallet.
- Stores Xertify generation ID, certificate code, and verification URL for issued certificates.
- Retrieves certificate PDFs from Xertify on demand using server-to-server authentication.
- Certificate PDF/Base64 content is not stored permanently in Moodle.
- Existing stored API responses are sanitized during upgrade to remove embedded PDF Base64 where present.

## 1.0.2 - 2026-07-30

- Added course backup and restore support for Xertify course configuration.
- Added course issuing-attempt reset action to simplify repeated testing.
- Added missing capability and reset-action language strings.

## 1.0.1 - 2026-07-22

- Added course-level dynamic data fields managed from the Xertify course settings page.
- Course dynamic fields are sent in `dynamicData` as flat key-value entries.
- Added validation for course dynamic data keys.

## 1.0.0-beta - 2026-07-13

- Prepared plugin package for Moodle Plugins Directory submission.
- Added public documentation, changelog, and license.
- Declared Moodle privacy metadata for Xertify API data and local issuing attempts.
- Sends dynamic certificate fields as a flat `dynamicData` object.
- Supports configurable email, SMS, and WhatsApp notification flags.
- Supports global and course-level Xertify API tokens.
- Supports course-level Xertify template and group configuration.
