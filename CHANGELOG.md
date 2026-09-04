# Changelog

All notable changes to **FormCourier Notifications Pro** are documented in this file.

## [1.6.0] - 2026-09-04

### Added
- Automatic retry queue for temporary Telegram delivery failures.
- WP-Cron based retry scheduling.
- Retry delays of 1 minute, 5 minutes, and 15 minutes.
- Telegram `429` handling with support for `retry_after`.
- Manual Retry remains available after automatic retries are exhausted.

### Changed
- Permanent configuration errors such as an empty Bot Token or Chat ID are not automatically retried.
- Retry processing is performed only when a queued event is due.

## [1.5.0] - 2026-09-04

### Added
- Destination column in delivery logs.
- Attempts counter.
- Manual Retry action for failed deliveries.
- System placeholders: `{destination}`, `{provider}`, `{submitted_at}`, `{form_name}`, `{form_id}`.
- Separate log entry for every destination.

## [1.4.4] - 2026-09-01

### Added
- Human-readable form field labels in default Telegram messages.

## [1.4.3] - 2026-09-01

### Fixed
- Removed duplicate combined `names` output from Fluent Forms default messages while retaining `{field:names}` as a compatibility alias.

## [1.4.2] - 2026-09-01

### Fixed
- Removed duplicate WPForms field aliases from the Message UI.
- Removed duplicate Ninja Forms field aliases from the Message UI.
- Hidden non-user fields and submit controls are no longer shown as message placeholders.

## [1.4.1] - 2026-09-01

### Fixed
- Fluent Forms field discovery.
- Nested Fluent Forms name fields such as `names[first_name]` and `names[last_name]`.
- Placeholder normalization for compound Fluent Forms fields.

## [1.4.0] - 2026-09-01

### Added
- Default message template.
- Per-form custom message templates.
- Real form field placeholders in the Message UI.

## [1.3.1] - 2026-09-01

### Added
- Dynamic field selection for Conditional Routing.
- Automatic field discovery for supported form builders.
- Fallback field discovery from real submissions.

## [1.3.0] - 2026-09-01

### Added
- Automatic form discovery.
- Conditional Routing.
- Conditional operators such as equals, contains, greater than, less than, empty, and not empty.
- `Replace form destinations` and `Add destinations` actions.

## [1.2.0] - 2026-08-31

### Added
- Multiple destinations per form.
- Separate Telegram delivery for every selected destination.
- Separate logging per destination.

## [1.1.0] - 2026-08-27

### Added
- Multiple Telegram destinations.
- Default destination.
- Per-form destination routing.
- Destination-specific connection tests.

## [1.0.0] - 2026-08-27

### Added
- Initial FormCourier Notifications Pro architecture.
- Universal submission and notification core.
- Telegram provider.
- Support for Contact Form 7, WPForms, Fluent Forms, Forminator, Ninja Forms, and Gravity Forms.
