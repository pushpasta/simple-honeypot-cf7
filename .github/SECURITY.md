# Security Policy

## Supported Versions

Only the latest released version is supported.

[![Latest Release](https://img.shields.io/github/v/release/pushpasta/simple-honeypot-cf7)](https://github.com/pushpasta/simple-honeypot-cf7/releases/latest)

If you're using an older version, please update to the latest supported release.

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please **do not** open a public issue.

Instead, report it privately using [GitHub Security Advisories](https://github.com/pushpasta/simple-honeypot-cf7/security/advisories/new).

Please include as much detail as possible to help us understand and reproduce the issue, including:

- A clear description of the vulnerability
- Steps to reproduce
- Potential impact
- Affected versions
- Any suggested mitigation or fix

We will acknowledge receipt of your report and work to address it in a timely manner. Once a fix is available, we will credit reporters appropriately (if desired).

## Security Considerations

This plugin follows secure-by-default practices:

**Input and output**

- Request data is sanitized on read (`sanitize_text_field`, `sanitize_key`, `absint`, …).
- Output is escaped in context (`esc_html`, `esc_attr`, `esc_url`) at render time.

**Authentication and authorization**

- Admin actions are guarded by WordPress nonces and capability checks (`manage_options`).
- REST endpoints declare a `permission_callback` and validate/sanitize every parameter.

**Database**

- Queries use `$wpdb->prepare()`; no raw user input reaches SQL.
- Settings export/import is restricted to a known allow-list of keys.

**Honeypot tokens**

- Tokens are HMAC-signed with `wp_hash()` and compared with `hash_equals()` (timing-safe).
- Payloads are AES-128-CTR encrypted using a key derived from the site's auth salt.
- Tokens are single-use, form-bound, and time-limited, and validate without a database write.
- Optional Proof-of-Work (HTTPS only) and per-IP rate limiting slow automated abuse.

**Privacy and data minimization**

- All checks run locally — no external API calls and no visitor data sharing.
- Honeypot values are stripped from posted data by default and only stored when opted in.
- Retention controls limit stored events, and uninstalling removes all plugin data.

**Hardening**

- Every plugin PHP file exits unless `ABSPATH` is defined; `index.php` stubs block listing.
- Client IP detection is proxy-aware (`wp_get_request_ip`) rather than trusting headers.
