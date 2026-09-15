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
