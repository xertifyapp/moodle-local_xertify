# Changelog

## 1.1.1 - 2026-10-21 (release candidate)

- Meets Moodle Marketplace packaging policy by distributing English strings only.
- Spanish translation source has been preserved for migration to Moodle AMOS; it is not published to AMOS in this release.
- No production functional changes; language distribution and release metadata only.

## 1.1.0 - 2026-10-18 (release candidate)

- Adds a secure shared certificate read service and reusable authorization.
- Integrates with the separately installed Xertify Certificate activity and protects legacy routes when activities restrict access.
- Excludes API tokens and certificate records from course backup; new restored configurations have issuance disabled and an empty token override.
- Provides the complete Moodle Privacy API for local certificate associations, without revoking external certificates.
- Preserves certificate viewing, PDF download and Wallet links; PDF bytes are retrieved on demand, not stored in Moodle.
- Validated on Moodle 4.1–5.2; the minimum requirement is now aligned to Moodle 4.1.
- Respects Moodle full-name formats, including middle, alternate and phonetic names, in recent certificate attempts.
- Keeps the main issuance flow without intentional functional changes.

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
