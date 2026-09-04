=== FormCourier Notifications Pro ===
Contributors: denslav
Tags: forms, telegram, notifications, contact form 7, wpforms
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 1.6.0
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

Included notification provider:
* Telegram

The internal architecture separates submission normalization, routing, notification management and notification providers.

== Installation ==

1. Upload and activate FormCourier Notifications Pro.
2. Open FormCourier Notifications Pro > Telegram and configure one or more destinations.
3. Open Forms and choose destinations for each form.
4. Optionally add Conditional Routing rules.
5. Send test submissions and review Logs.

== Changelog ==

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
