# Contributing

Thank you for your interest in FormCourier Notifications Pro.

## Development workflow

Recommended branches:

- `main` - stable production code.
- `develop` - ongoing integration.
- `feature/*` - new features.
- `fix/*` - bug fixes.
- `chore/*` - maintenance and repository work.

Example:

```bash
git checkout develop
git pull
git checkout -b feature/slack-provider
```

## Coding rules

- Follow WordPress PHP coding standards where practical.
- Sanitize user input and escape output.
- Check capabilities and nonces for admin actions.
- Never commit Bot Tokens, Chat IDs, API keys, passwords, or local secrets.
- Keep provider-specific logic outside the notification core.
- Preserve backward compatibility with saved settings where possible.

## Testing

Before opening a pull request:

1. Activate the plugin on a clean WordPress installation.
2. Confirm there are no PHP errors or warnings.
3. Test the affected form providers.
4. Verify Telegram delivery and logs when notification code changes.
5. Test upgrade behaviour from the previous stable version.
6. Test retry behaviour when delivery code changes.

## Pull requests

Keep each pull request focused on one feature or fix.

Include:
- what changed;
- why it changed;
- how it was tested;
- backward-compatibility considerations.

## Security

Do not report vulnerabilities in public issues. See `SECURITY.md`.
