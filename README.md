# FormCourier Notifications Pro

Route submissions from popular WordPress form plugins to Telegram with multiple destinations, per-form routing, conditional rules, custom message templates, delivery logs, and automatic retries.

**Version:** 1.6.0  
**Author:** Den Slav  
**Requires WordPress:** 6.0+  
**Tested up to:** WordPress 7.1  
**Requires PHP:** 7.4+  
**License:** GPL v2 or later  

> FormCourier Notifications Pro is an independent WordPress plugin. It is not affiliated with or endorsed by Telegram or the developers of the supported form plugins.

## Overview

FormCourier Notifications Pro receives submissions from supported WordPress form plugins, normalizes the submitted data, applies routing rules, builds the Telegram message, and sends it to one or more configured Telegram destinations.

The plugin is designed for websites where different forms or different form values need to be routed to different Telegram users, groups, channels, departments, or teams.

Example:

```text
Contact Form 7 → Sales Telegram group
Fluent Forms → Support Telegram group
Gravity Forms → Sales + Manager
Job application → HR
```

Conditional Routing can additionally change the destination depending on a submitted field value.

## Supported Form Plugins

FormCourier Notifications Pro currently supports:

- Contact Form 7
- WPForms
- Fluent Forms
- Forminator
- Ninja Forms
- Gravity Forms

The plugin automatically discovers supported forms available on the website.

## Notification Provider

Version 1.6.0 includes:

- Telegram

The plugin architecture separates form integrations, normalized submissions, routing, message generation, notification management, and notification providers.

## Main Features

- Send WordPress form submissions directly to Telegram.
- Support for six popular WordPress form plugins.
- Multiple Telegram destinations.
- Separate Bot Token and Chat ID for each destination.
- Enable or disable individual destinations.
- Select a default Telegram destination.
- Route each WordPress form to one or more Telegram destinations.
- Conditional Routing based on submitted form values.
- Replace or add destinations when a condition matches.
- Automatic form discovery.
- Automatic form-field discovery.
- Human-readable field labels where available.
- Global Telegram message template.
- Custom message template for individual forms.
- Dynamic form-field placeholders.
- Telegram-compatible HTML formatting.
- Automatic splitting of long Telegram messages.
- Delivery logs.
- Destination and attempt information in logs.
- Manual retry for failed deliveries.
- Automatic retry queue for temporary Telegram failures.
- Retry handling for network errors, rate limits, and Telegram 5xx errors.
- Support for Telegram `retry_after`.
- Duplicate-delivery protection where submission IDs are available.
- Encrypted Telegram Bot Tokens when server support is available.
- Optional cleanup of plugin data during uninstall.

## Installation

### WordPress Admin

1. Open **Plugins > Add New Plugin**.
2. Upload the FormCourier Notifications Pro ZIP file.
3. Install and activate the plugin.
4. Open **FormCourier Notifications Pro** in the WordPress admin menu.
5. Configure at least one Telegram destination.
6. Open the **Forms** tab and configure routing.
7. Configure the message template if required.
8. Send a Telegram test message.
9. Submit a real form and check the **Logs** tab.

### Manual Installation

1. Extract the plugin archive.
2. Upload the plugin folder to:

```text
/wp-content/plugins/formcourier-notifications-pro/
```

3. Activate **FormCourier Notifications Pro** in WordPress.
4. Open the plugin settings page.

## Admin Sections

The plugin contains five main sections:

- **Dashboard**
- **Telegram**
- **Forms**
- **Message**
- **Logs**

## Dashboard

The Dashboard provides a quick overview of the integration.

It shows:

- Telegram integration status;
- Bot Token configuration status;
- Chat ID configuration status;
- supported form plugins detected on the website;
- whether each provider is enabled or disabled;
- recent Telegram delivery activity.

Quick links are also available for Telegram settings, message templates, and logs.

## Telegram Setup

### 1. Create a Telegram Bot

Create a Telegram bot using **@BotFather**.

BotFather will provide a Bot Token similar to:

```text
123456789:AAExampleTelegramBotToken
```

Keep this token private.

### 2. Create a Destination

Open:

**FormCourier Notifications Pro > Telegram**

A destination represents one Telegram endpoint.

For example:

```text
Sales
Support
HR
Manager
Website Leads
```

Each destination can have its own:

- destination name;
- Bot Token;
- Chat ID;
- enabled/disabled state.

### 3. Enter the Chat ID

The Chat ID identifies the Telegram chat where notifications should be sent.

A destination may point to a:

- private chat;
- group;
- supergroup;
- channel.

Telegram group and channel IDs may be negative.

The bot must have permission to send messages to the selected destination.

### 4. Enable Telegram Notifications

Enable:

```text
Enable Telegram notifications
```

and save the settings.

### 5. Select a Default Destination

One destination can be used as the default.

Forms that do not have an explicit destination route use the default Telegram destination.

### 6. Send a Test Message

Use the test-message action for a configured destination.

If the credentials and permissions are correct, Telegram should receive the test notification.

## Multiple Telegram Destinations

FormCourier Notifications Pro can maintain multiple Telegram destinations.

Example:

| Destination | Purpose |
|---|---|
| Sales | New sales inquiries |
| Support | Support requests |
| HR | Job applications |
| Manager | Important or high-value leads |

Each destination is configured independently.

This makes it possible to use different bots, different Chat IDs, or both.

## Form Routing

Open:

**FormCourier Notifications Pro > Forms**

The plugin automatically discovers forms from supported form builders.

You can assign one or more Telegram destinations to each form.

Example:

```text
Contact Form → Sales
Support Form → Support
Job Application → HR
Request a Quote → Sales + Manager
```

If no explicit destination is configured for a form, the default Telegram destination is used.

## Multi-Destination Routing

A single form can send the same submission to multiple Telegram destinations.

Example:

```text
Request a Quote
├── Sales
└── Manager
```

This is useful when several teams need to receive the same lead immediately.

## Conditional Routing

Conditional Routing allows the final destination to depend on submitted form data.

A rule contains:

- form;
- field;
- operator;
- expected value;
- routing mode;
- one or more destinations.

The plugin automatically discovers fields for supported forms where possible.

### Available Operators

Conditional rules support:

- `equals`
- `not_equals`
- `contains`
- `not_contains`
- `greater_than`
- `less_than`
- `is_empty`
- `is_not_empty`

Text comparisons are case-insensitive where appropriate.

Numeric comparison operators require numeric values.

### Replace Mode

**Replace** replaces the normal form destinations with the destinations from the matching rule.

Example:

```text
Form destination: Sales

Condition:
Country equals Germany

Mode: Replace
Destination: Germany Sales
```

A matching submission is sent to:

```text
Germany Sales
```

instead of the normal Sales destination.

### Add Mode

**Add** keeps the normal form destinations and adds the destinations from the matching rule.

Example:

```text
Form destination: Sales

Condition:
Budget greater than 5000

Mode: Add
Destination: Manager
```

A matching submission is sent to:

```text
Sales
Manager
```

## Automatic Field Discovery

FormCourier Notifications Pro attempts to discover available form fields directly from the supported form builder.

This makes it easier to configure:

- conditional routing;
- form-specific message placeholders.

If a builder cannot expose its current field structure, the plugin can use field identifiers remembered from real submissions as a fallback.

## Message Templates

Open:

**FormCourier Notifications Pro > Message**

The plugin supports:

1. a default message template;
2. an optional custom template for each discovered form.

If a custom template is disabled for a form, the default message template is used automatically.

### Default Template

```html
🆕 <b>New form submission</b>

<b>Form:</b> {form_name}

{all_fields}
```

## Message Placeholders

The message builder supports general placeholders including:

| Placeholder | Value |
|---|---|
| `{form_provider}` | Human-readable form provider name |
| `{provider}` | Human-readable form provider name |
| `{form_id}` | Form ID |
| `{form_name}` | Form name |
| `{destination}` | Destination name |
| `{all_fields}` | All submitted form fields |
| `{page_url}` | Source page URL when available |
| `{site_name}` | WordPress site name |
| `{site_url}` | WordPress site URL |
| `{date}` | Submission date |
| `{time}` | Submission time |
| `{submitted_at}` | Submission timestamp |

You can also use individual submitted fields.

## Field Placeholders

Use:

```text
{field:FIELD_KEY}
```

Example:

```text
<b>Name:</b> {field:name}
<b>Email:</b> {field:email}
<b>Phone:</b> {field:phone}
```

The available field keys depend on the selected form and form builder.

FormCourier Notifications Pro displays discovered placeholders for supported forms in the admin interface.

## Human-Readable Field Labels

For `{all_fields}`, the plugin attempts to use human-readable labels from the form builder instead of technical field keys.

For example:

```text
First Name: John
Last Name: Smith
Email: john@example.com
```

instead of:

```text
names[first_name]: John
names[last_name]: Smith
email_1: john@example.com
```

The plugin contains additional handling for compound fields such as Fluent Forms Name fields and Gravity Forms multi-input fields.

## Telegram HTML Formatting

Telegram messages are sent using HTML parse mode.

Templates can contain supported formatting such as:

```html
<b>Bold</b>
<strong>Bold</strong>
<i>Italic</i>
<em>Italic</em>
<u>Underline</u>
<s>Strikethrough</s>
<code>Code</code>
<pre>Preformatted text</pre>
<a href="https://example.com">Link</a>
```

Submitted field values are escaped before they are inserted into the Telegram message.

## Long Telegram Messages

Telegram limits the length of an individual message.

FormCourier Notifications Pro automatically splits oversized messages into multiple parts instead of silently discarding the remaining form data.

The parts are sent sequentially.

## Delivery Logs

Open:

**FormCourier Notifications Pro > Logs**

The latest 100 Telegram delivery attempts are stored locally in WordPress.

The log table includes:

- date;
- notification channel;
- form provider;
- form;
- Telegram destination;
- status;
- number of attempts;
- delivery details;
- retry actions.

Logs can be cleared manually.

## Manual Retry

Failed Telegram deliveries can retain a local retry payload.

When a retry is available, the **Retry** action appears in the Logs tab.

A manual retry attempts to resend the original normalized submission to the same Telegram destination.

A manual retry also supersedes a pending automatic retry for that same delivery.

## Automatic Retry Queue

Version 1.6.0 adds automatic retries for temporary Telegram delivery failures.

Default automatic retry delays are:

```text
1 minute
5 minutes
15 minutes
```

Automatic retries are used for temporary problems such as:

- network errors;
- Telegram HTTP 429 rate limits;
- Telegram 5xx server errors.

The plugin does not automatically retry permanent configuration or permission errors.

Examples include:

- invalid Bot Token;
- chat not found;
- bot without permission to send to the destination.

If Telegram provides a `retry_after` value that requires a longer wait, FormCourier Notifications Pro respects that delay.

The Logs tab shows the next scheduled retry.

When all automatic retries are exhausted, manual Retry can still remain available.

## Telegram Error Handling

The plugin converts common Telegram API responses into clearer administrator-facing messages.

Examples include:

```text
Telegram authentication failed. Check the Bot Token.
Telegram chat not found. Check the Chat ID and make sure the bot has access to the chat.
Telegram access denied. The bot may be blocked or may not have permission to post in this chat.
Telegram rate limit reached.
Telegram API is temporarily unavailable.
```

## Data Flow

The normal delivery flow is:

```text
WordPress form
      ↓
FormCourier Notifications Pro
      ↓
Submission normalization
      ↓
Form routing
      ↓
Conditional Routing
      ↓
Message template
      ↓
Telegram Bot API
      ↓
Telegram destination
```

## Data and Privacy

FormCourier Notifications Pro sends the form data included in the generated notification message to Telegram.

Administrators are responsible for determining which submitted data should be included in Telegram notifications and for ensuring that their site's privacy policy and data-processing practices comply with applicable requirements.

The plugin stores configuration data and recent delivery logs in the WordPress database.

For failed messages that can be retried, a local retry payload may be retained until the message is successfully delivered or the related logs are cleared.

## Bot Token Security

Telegram Bot Tokens are sensitive credentials.

FormCourier Notifications Pro uses its encryption component when storing and reading Bot Tokens.

Administrators should also:

- restrict WordPress administrator access;
- keep WordPress, plugins, themes, PHP, and server software updated;
- never publish Bot Tokens in screenshots, documentation, repositories, or support tickets;
- regenerate a Telegram Bot Token if it is exposed.

## Uninstall

The Telegram settings include an option to delete plugin settings and logs when the plugin is uninstalled.

Enable this option if you want FormCourier Notifications Pro data to be removed during uninstall.

Otherwise plugin data can be preserved.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- At least one supported WordPress form plugin
- A Telegram bot
- A valid Telegram Chat ID
- Permission for the bot to send messages to the selected destination
- Outbound HTTPS access from the WordPress server to the Telegram Bot API

## Frequently Asked Questions

### Can one form send notifications to multiple Telegram groups?

Yes. Assign multiple destinations to the same form.

### Can different forms use different Telegram bots?

Yes. Every Telegram destination can have its own Bot Token and Chat ID.

### What happens if no destination is selected for a form?

The plugin falls back to the default enabled Telegram destination.

### Can I route a submission based on a field value?

Yes. Use Conditional Routing.

### Can a condition add a destination without replacing the normal route?

Yes. Use **Add** mode.

### Can a condition completely change the destination?

Yes. Use **Replace** mode.

### Can I create a different Telegram message for every form?

Yes. Version 1.4.2 and later supports per-form message templates.

### Can I insert individual form fields into a message?

Yes. Use `{field:FIELD_KEY}` placeholders.

### What happens when Telegram is temporarily unavailable?

Temporary network errors, Telegram rate limits, and Telegram 5xx responses can be retried automatically.

### How many automatic retry attempts are made?

The retry queue schedules retry delays at approximately 1, 5, and 15 minutes.

### Can I retry a failed message manually?

Yes, when a retry payload is available in the log.

### Are very long submissions truncated?

The plugin automatically splits long Telegram messages into multiple parts.

### How many logs are stored?

The latest 100 Telegram delivery attempts are stored locally.

## Troubleshooting

### Test Message Fails

Check:

- Bot Token;
- Chat ID;
- whether the destination is enabled;
- whether the bot has access to the chat;
- whether the bot is allowed to post in the group or channel;
- whether the WordPress server can make outbound HTTPS requests.

### Form Submission Does Not Reach Telegram

Check:

1. Telegram notifications are enabled.
2. The required form provider is enabled in the **Forms** tab.
3. The form was discovered correctly.
4. At least one destination is enabled.
5. Form routing is configured correctly.
6. Conditional Routing is not redirecting the submission unexpectedly.
7. The **Logs** tab for the delivery result.

### Conditional Routing Does Not Match

Check:

- the correct form is selected;
- the correct field key is selected;
- the expected value matches the submitted value;
- the correct operator is used;
- the rule is enabled;
- at least one destination is selected.

### Telegram Reports Too Many Requests

Telegram may return HTTP 429 together with `retry_after`.

FormCourier Notifications Pro treats this as a temporary error and schedules a retry, respecting Telegram's requested delay when necessary.

## Changelog

### 1.6.0

- Added automatic retry queue for temporary Telegram delivery failures.
- Automatic retry delays are 1 minute, 5 minutes, and 15 minutes.
- Network errors, Telegram rate limits, and Telegram 5xx errors are retried automatically.
- Configuration and permission errors are not retried automatically.
- Logs show the next scheduled automatic retry and keep the manual Retry action available.
- Telegram `retry_after` values are respected when they require a longer delay.

### 1.5.0

- Added Destination and Attempts columns to delivery logs.
- Added manual Retry for failed Telegram deliveries.
- Added `{destination}`, `{provider}`, and `{submitted_at}` message placeholders.
- Failed deliveries keep a local retry payload until successfully resent or logs are cleared.

### 1.4.4

- Default Telegram messages now use discovered human-readable field labels where available.
- Fluent Forms compound Name fields display labels such as First Name and Last Name instead of technical keys.
- Contact Form 7 field-type suffixes are kept in the admin UI but removed from Telegram message labels.
- Field discovery is limited to the submitted form when building a default message.

### 1.4.3

- Fixed duplicate combined Name output in Fluent Forms default messages while preserving the legacy `{field:names}` alias.

### 1.4.2

- Added per-form Telegram message templates.
- Added a default template fallback for forms without an override.
- Added discovered field placeholders for each form.
- Existing global message template remains fully compatible.

### 1.3.1

- Added automatic field discovery for Conditional Routing.
- Field list changes automatically when a form is selected.
- Added field discovery for Contact Form 7, WPForms, Fluent Forms, Forminator, Ninja Forms, and Gravity Forms.
- Added remembered submission field keys as a fallback when a builder API cannot expose fields.
- Existing manually saved field rules remain compatible.

### 1.3.0

- Added automatic form discovery for supported form builders.
- Added conditional routing by form field values.
- Added Replace and Add routing modes.

### 1.2.0

- Added multi-destination routing per form.
- A single form submission can be delivered to multiple Telegram destinations.
- Existing single-destination routes from 1.1.0 remain compatible.
- Forms with no explicit destination continue to use the default Telegram destination.

### 1.1.0

- Added multiple Telegram destinations.
- Added a default destination.
- Added per-form destination routing.
- Added destination-aware test messages and logs.

### 1.0.0

- Initial Pro architecture.
- Added universal Submission core.
- Added Routing Engine and provider interface.
- Added Telegram as the first notification provider.
- Preserved integrations with six WordPress form plugins.

## License

FormCourier Notifications Pro is licensed under the GNU General Public License v2 or later.

https://www.gnu.org/licenses/gpl-2.0.html
