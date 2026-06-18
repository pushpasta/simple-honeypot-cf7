=== Simple Honeypot for Contact Form 7 ===
Contributors: pushpasta
Donate link: https://github.com/pushpasta/simple-honeypot-cf7/?sponsor
Tags: contact form 7, cf7, honeypot, antispam, spam protection, bot protection, proof of work, hashcash
Requires at least: 6.7
Requires PHP: 7.4
Tested up to: 7.0
Requires Plugins: contact-form-7
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lightweight honeypot, timing, proof-of-work, and rule-based spam protection for Contact Form 7.

== Description ==

Hidden honeypot fields, timing checks, proof-of-work, custom rules, and spam reporting for Contact Form 7. Everything runs on your server — no external services, no visitor tracking.

= Features =

* 🪤 Adds a `[honeypot]` form tag to Contact Form 7, supporting multiple fields per form.
* 🔒 Server-side token validation — no database queries during validation.
* 🧩 Dynamic field names that change regularly, cache-friendly and harder for bots to predict.
* ⏱️ Timing checks flag submissions that arrive faster than a human could fill out the form.
* 🧠 Optional Proof-of-Work — browser solves a computational puzzle before submitting. Imperceptible to humans, costly for bots.
* 🛡️ IP and email blocking rules with wildcard and CIDR support.
* 🔐 All checks run locally — no external API calls, no visitor tracking, no data sharing.
* 🔁 Import and export all settings (global + per-form) as a single JSON file.
* 📝 Records blocked spam with form, IP, user agent, and reason details.
* 🧾 Adds spam log reasons to CF7 submissions for record-keeping plugins like Flamingo.

== Installation ==

= Manual Installation =

1. Upload the `simple-honeypot-cf7` folder to `/wp-content/plugins/`.
2. Activate Simple Honeypot for Contact Form 7 from the Plugins screen.
3. Make sure Contact Form 7 is installed and active.
4. Add a `[honeypot]` field to a CF7 form.

== Frequently Asked Questions ==

= How does the honeypot work? =

The plugin adds one or more hidden fields that are invisible to legitimate visitors. Automated bots often fill these fields, allowing spam submissions to be identified and blocked before they are processed. You can add multiple honeypot fields to a single form.

= What is Proof of Work and how does it help? =

Proof of Work requires the visitor's browser to spend a small amount of CPU time computing a hash before the form can be submitted. At the default complexity, this takes roughly 50–100ms — imperceptible to humans — but forces automated spam tools to spend significant resources. It can be enabled or disabled in the settings with configurable difficulty. Requires JavaScript and a secure (HTTPS) connection.

= Does the plugin block submissions that are sent too quickly? =

Yes. The plugin validates the time between page load and form submission. Submissions that arrive faster than the configured minimum time are flagged as spam. Time checks can be inherited from global settings, enabled, or disabled per form.

= What types of spam rules are supported? =

The plugin supports IP addresses (with wildcards and CIDR) and email addresses (with wildcards). For keyword or pattern filtering, use the WordPress Disallowed Comment Keys setting (Settings → Discussion), which Contact Form 7 checks automatically.

= Does the plugin send form data to a third-party service? =

No. All spam checks are performed locally on your website. No form submissions or visitor data are sent to external services.

= Will the honeypot value be stored in record plugins like Flamingo? =

By default, honeypot fields are removed from submitted data before it is stored. You can optionally enable storage of honeypot values in the plugin settings (under Data) for debugging or security analysis.

= Why was a submission marked as spam? =

The Spam Log shows which rule triggered the detection, such as a filled honeypot field, a failed time check, a blocked keyword, or a custom IP or email rule.

= What happens when the plugin is uninstalled? =

All plugin data is removed from the database, including settings, statistics, and per-form configuration. The only exception is spam submissions already recorded in the log, which are preserved.

== Screenshots ==

1. **General Settings:** Configure timing threshold, token lifetime, proof-of-work complexity, and data retention.
2. **Rules:** Create IP or email rules to block specific addresses or patterns.
3. **Reports:** View blocked submission statistics with reason and form breakdowns.
4. **Form Settings:** Override time-check settings on a per-form basis.
5. **Spam Log:** Review detailed records of each blocked submission, including reason, IP, and user agent.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
* Initial release.
