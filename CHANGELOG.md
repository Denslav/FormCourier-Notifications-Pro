# Changelog

All notable changes to **FormCourier Notifications Pro** are documented in this file.

## [1.9.0] - 2026-09-06

### Added
- Optional Advanced Routing section for complex routing while keeping standard form routing as the default experience.
- Multiple conditions per rule with ALL (AND) and ANY (OR) matching.
- Telegram and Slack destinations in the same Advanced Routing rule.
- Add destinations and Replace form destinations actions.
- Optional rule Priority from 0 to 999; higher priorities are evaluated first.
- Optional Stop processing control to skip lower-priority rules after a match.
- Automatic fallback to normal Form Routes/default destinations when no Advanced Routing rule matches.

### Improved
- Automatic form-field discovery is reused for each Advanced Routing condition.
- Overlapping Replace rules resolve deterministically so higher-priority rules take precedence.
- Advanced Routing stays out of the way unless the user explicitly creates rules.

### Compatibility
- Updating from 1.8.0 requires no routing reconfiguration.
- Existing 1.8.0 single-condition Telegram rules remain compatible and are treated as one-condition Advanced Routing rules.
- Existing Telegram and Slack settings, standard Form Routes, templates, logs, manual Retry, automatic Retry, CSV export, pagination, and 30-day log cleanup remain supported.
- Contact Form 7, WPForms, Fluent Forms, Forminator, Ninja Forms, and Gravity Forms remain supported.

## [1.8.0] - 2026-09-05

### Added
- Expandable Delivery details for notification logs.
- HTTP status, provider response, last error, retry state, next retry time, submission ID, and submitted-at diagnostics.
- Log filters for notification channel, form provider, delivery status, and destination.
- Text search and Date from / Date to filtering.
- CSV export for the complete currently filtered log selection.
- Log summary counters for filtered entries, successes, errors, Telegram, and Slack deliveries.
- Pagination with 20, 50, or 100 entries per page.
- Optional automatic deletion of logs older than 30 days using daily WP-Cron cleanup.

### Changed
- Retry diagnostics are refreshed after manual and automatic retries.
- Logs layout, counters, filter controls, and pagination footer were improved for clearer administration.

### Compatibility
- Existing Telegram and Slack settings, routing, templates, logs, manual Retry, and automatic Retry remain fully supported.
- Contact Form 7, WPForms, Fluent Forms, Forminator, Ninja Forms, and Gravity Forms remain supported.

## [1.7.0] - 2026-09-05

### Added
- Slack notification provider using Incoming Webhooks.
- Multiple encrypted Slack destinations.
- Default Slack destination.
- Slack connection testing.
- Per-form Slack destination routing.
- Simultaneous Telegram and Slack delivery.

### Changed
- Delivery logs now support Slack destinations.
- Manual Retry now supports Slack.
- Automatic WP-Cron retry now supports temporary Slack network, rate-limit, and 5xx failures.
- Slack rate limits respect `Retry-After`.
- Existing message templates and placeholders are reused for Slack as clean plain-text messages.

### Compatibility
- Existing Telegram settings and routing remain fully supported.

## [1.6.0] - 2026-09-04

### Added
- Automatic retry queue for temporary Telegram delivery failures.
- Retry delays of 1 minute, 5 minutes, and 15 minutes.
- Retry support for network errors, Telegram rate limits, and Telegram 5xx errors.

### Changed
- Logs show the next scheduled automatic retry and retain manual Retry.
- Telegram `retry_after` values are respected when longer than the default delay.

## [1.5.0]

### Added
- Destination and Attempts columns to delivery logs.
- Manual Retry for failed Telegram deliveries.
- `{destination}`, `{provider}`, and `{submitted_at}` message placeholders.

## [1.4.4]

### Changed
- Default Telegram messages use discovered human-readable field labels where available.
- Fluent Forms compound Name fields use readable labels.
- Contact Form 7 field-type suffixes are removed from Telegram message labels.

## [1.4.3]

### Fixed
- Duplicate combined Name output in Fluent Forms default messages while preserving the legacy `{field:names}` alias.

## [1.4.2]

### Added
- Per-form Telegram message templates.
- Default template fallback for forms without an override.
- Discovered field placeholders for each form.

## [1.3.1]

### Added
- Automatic field discovery for Conditional Routing.
- Field discovery for all six supported form builders.

## [1.3.0]

### Added
- Automatic form discovery.
- Conditional routing by form field values.
- Replace and Add routing modes.

## [1.2.0]

### Added
- Multi-destination routing per form.

## [1.1.0]

### Added
- Multiple Telegram destinations.
- Default Telegram destination.
- Per-form destination routing.
- Destination-aware test messages and logs.

## [1.0.0]

### Added
- Initial Pro architecture.
- Universal Submission core.
- Routing Engine and provider interface.
- Telegram as the first notification provider.
- Integrations with six WordPress form plugins.
