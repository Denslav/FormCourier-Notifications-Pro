=== FormCourier Notifications Pro ===
Contributors: denslav
Tags: forms, telegram, slack, notifications, wpforms
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 1.8.0
License: GPLv2 or later

FormCourier Notifications Pro routes WordPress form submissions to notification providers.

== Description ==

Supported form integrations:
* Contact Form 7
* WPForms
* Fluent Forms
* Forminator
* Ninja Forms
* Gravity Forms

Included notification providers:
* Telegram
* Slack (Incoming Webhooks)

The internal architecture separates submission normalization, routing, notification management and notification providers.

== Installation ==

1. Upload and activate FormCourier Notifications Pro.
2. Configure Telegram and/or Slack destinations.
3. Open Forms and choose Telegram and Slack destinations for each form.
4. Optionally add Conditional Routing rules.
5. Send test submissions and review Logs.

== Changelog ==

= 1.8.0 =
* Added expandable Delivery details with HTTP status, provider response, last error, retry state, next retry time, submission ID and submission timestamp.
* Added log filtering by notification channel, form provider, delivery status and destination.
* Added text search and Date from / Date to filtering for delivery logs.
* Added CSV export for the complete currently filtered log selection.
* Added log summary counters for filtered entries, successes, errors, Telegram and Slack deliveries.
* Added pagination with 20, 50 or 100 entries per page.
* Added optional automatic deletion of logs older than 30 days using daily WP-Cron cleanup.
* Improved retry diagnostics so delivery details are refreshed after manual and automatic retries.
* Improved the Logs layout and pagination footer for clearer administration.

= 1.7.0 =
* Added Slack as a notification provider using Incoming Webhooks.
* Added multiple encrypted Slack destinations and a default Slack destination.
* Added Slack connection testing.
* Added per-form Slack routing alongside existing Telegram routing.
* Slack deliveries are recorded in the existing Logs screen with Destination and Attempts.
* Manual and automatic Retry now support temporary Slack network, rate-limit, and 5xx failures.
* Slack reuses existing message templates and placeholders as a clean plain-text message.

= 1.6.0 =
* Added automatic retry queue for temporary Telegram delivery failures.
* Automatic retry delays are 1 minute, 5 minutes, and 15 minutes.
* Network errors, Telegram rate limits, and Telegram 5xx errors are retried automatically.
* Configuration and permission errors are not retried automatically.
* Logs show the next scheduled automatic retry and keep the manual Retry action available.
* Telegram retry_after values are respected when they require a longer delay.

= 1.5.0 =
* Added Destination and Attempts columns to delivery logs.
* Added manual Retry for failed Telegram deliveries.
* Added {destination}, {provider}, and {submitted_at} message placeholders.
* Failed deliveries keep a local retry payload until successfully resent or logs are cleared.

= 1.4.4 =
* Default Telegram messages now use discovered human-readable field labels where available.
* Fluent Forms compound Name fields display labels such as First Name and Last Name instead of technical keys.
* Contact Form 7 field-type suffixes are kept in the admin UI but removed from Telegram message labels.
* Field discovery is limited to the submitted form when building a default message.

= 1.4.3 =
* Fixed duplicate combined Name output in Fluent Forms default messages while preserving the legacy {field:names} alias.

= 1.4.2 =
* Added per-form Telegram message templates.
* Added a default template fallback for forms without an override.
* Added discovered field placeholders for each form.
* Existing global message template remains fully compatible.

= 1.3.1 =
* Added automatic field discovery for Conditional Routing.
* Field list changes automatically when a form is selected.
* Added field discovery for Contact Form 7, WPForms, Fluent Forms, Forminator, Ninja Forms and Gravity Forms.
* Added remembered submission field keys as a fallback when a builder API cannot expose fields.
* Existing manually saved field rules remain compatible.

= 1.3.0 =
* Added automatic form discovery for supported form builders.
* Added conditional routing by form field values.
* Added Replace and Add routing modes.

= 1.2.0 =
* Added multi-destination routing per form.
* A single form submission can be delivered to multiple Telegram destinations.
* Existing single-destination routes from 1.1.0 remain compatible.
* Forms with no explicit destination continue to use the default Telegram destination.

= 1.1.0 =
* Added multiple Telegram destinations.
* Added a default destination.
* Added per-form destination routing.
* Added destination-aware test messages and logs.

= 1.0.0 =
* Initial Pro architecture.
* Added universal Submission core.
* Added Routing Engine and provider interface.
* Added Telegram as the first notification provider.
* Preserved integrations with six WordPress form plugins.
