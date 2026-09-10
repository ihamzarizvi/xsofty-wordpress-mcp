# Contributing

This is a security-sensitive open-source WordPress integration. Keep changes focused, reviewable and fail-closed.

## Contribution license

By submitting a contribution, you certify that you have the right to submit it and agree that it will be distributed under the repository's GPL-2.0-or-later license. Copyright in your original contribution remains yours unless separately assigned in writing.

Contributions become part of the official Xsofty WordPress MCP Bridge only after owner review and merge. A pull request does not grant permission to present an independent fork as an official Xsofty product. See [TRADEMARKS.md](TRADEMARKS.md).

## Development requirements

- PHP 8.1 or newer
- Node.js for JavaScript syntax validation
- `zip` and `unzip` for release packaging

## Workflow

1. Create a focused branch from `main`.
2. Add or update tests before changing security-sensitive behavior.
3. Keep tool definitions curated; never add generic code, shell, SQL or filesystem execution.
4. Preserve native WordPress capability checks, nonces, sanitization and explicit mutation confirmation.
5. Run the complete local gate:

```bash
bash scripts/test.sh
```

6. Describe security impact and verification evidence in the pull request.

## Commit style

Use concise Conventional Commit messages, for example:

```text
feat(admin): add least-privilege access presets
fix(auth): clear request context after failures
```

## Pull-request checklist

- [ ] Behavior is covered by deterministic tests
- [ ] PHP syntax passes on supported versions
- [ ] JavaScript syntax passes
- [ ] No credentials, backups, tokens or local paths were committed
- [ ] Mutations still require `confirm=true`
- [ ] Capability and object-level checks remain intact
- [ ] Admin changes were reviewed at desktop and mobile widths
- [ ] The release archive contains only installable plugin files

Security-sensitive changes require explicit owner review before merge.
