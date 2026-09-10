# Xsofty WordPress MCP Bridge

An open-source WordPress plugin exposing a curated administration surface to compatible MCP clients over stateless Streamable HTTP / JSON-RPC 2.0.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- PHP Zip extension for backups/checkpoints
- HTTPS on live sites

HTTP is rejected unless development mode is explicitly enabled. Use that opt-in only on isolated local networks; production requires HTTPS.

## Security model

- Multiple named one-time-display bearer tokens. WordPress stores password hashes, not bearer secrets.
- Per-token administrator binding, scopes, tool allowlists, expiry, rate limits, revocation and backup-signing keys.
- New installations default to `site_read` and `content_read`.
- Every mutation requires literal `confirm=true`; high-risk calls can additionally use the WordPress approval queue.
- Native WordPress capabilities and object-level permissions are checked after authentication.
- Global and per-token tool controls fail closed.
- Invalid-token checks are throttled before expensive hash verification; valid traffic has separate per-token/IP limits.
- Request user/token context is reset before and after every MCP REST request.
- Browser origins must be same-origin or explicitly allowlisted HTTPS origins.
- Audit logs redact credential fields, URLs queries and local paths, and pseudonymize remote addresses with keyed HMAC.
- No arbitrary PHP, JavaScript, shell/process, raw SQL, WordPress-core editing, unrestricted filesystem, password or `wp-config.php` tool exists.

## Installation

1. Upload the release ZIP in **Plugins → Add New → Upload Plugin**, or copy `xsofty-wordpress-mcp` into `wp-content/plugins/`.
2. Activate **Xsofty WordPress MCP Bridge**.
3. Open **Settings → Xsofty MCP**.
4. Create a named connection with the least-powerful suitable access preset.
5. Copy the token immediately; it is shown once.
6. Follow the official repository's [Codex, Claude Code, Grok, Hermes Agent, and OpenClaw setup guide](https://github.com/ihamzarizvi/xsofty-wordpress-mcp/blob/main/docs/client-setup.md).

## Protocol

- MCP POST: `/wp-json/xsofty-mcp/v1/mcp`
- Authenticated health: `/wp-json/xsofty-mcp/v1/health`
- Signed private-backup download: `/wp-json/xsofty-mcp/v1/download`
- Protocols: `2025-06-18`, `2025-03-26`
- Capabilities: tools and resources
- GET/SSE on the stateless MCP endpoint returns `405 Method Not Allowed`

Every exposed tool includes a title, input schema, output schema and MCP annotations. Runtime validation recursively enforces objects, arrays, item types, enums and bounds.

## v0.3 tool surface

Up to 61 tools are exposed according to global settings, token scopes and token allowlists.

- Site and health: site information, curated Site Health, sitemap and SEO adapter status.
- Content: list/get/create/update/delete, dry-run change plans, revisions and confirmed rollback.
- Taxonomy/meta: public taxonomy and term operations; registered REST-visible metadata only.
- Menus/media: classic menu items, Media Library metadata, featured images and bounded HTTPS image sideloading.
- SEO/redirects: curated Yoast/Rank Math fields when supported; exact internal redirect registry with protected-route denial.
- Plugins: official-directory install, activation/deactivation, cached update discovery, private update checkpoints, safe update and confirmed rollback. The bridge cannot modify itself.
- Theme: active-theme read plus atomic patching of non-executable CSS/JSON/TXT/MD files only.
- Settings/maintenance: approved non-secret options, rewrite/cache maintenance, bounded WP-Cron discovery. Manual cron execution is disabled by default.
- Backups/jobs: synchronous and asynchronous full backups, job progress/cancellation, daily/weekly schedule and retention.
- Audit/approvals: filtered audit query, bounded CSV export, token-owned approval submission/list/execution.

## MCP resources

Scoped read-only resources:

- `xsofty://site/health`
- `xsofty://backups/schedule`
- `xsofty://jobs/recent`
- `xsofty://audit/recent`
- `xsofty://approvals/recent`

Resources are listed only when the active token has the required scope.

## Backups and jobs

Single-site backups contain descriptor-stable snapshots of WordPress files, a live repeatable-read consistent-snapshot SQL export of prefixed base tables, and a JSON manifest. Archives are created privately, consistency checked, atomically published and set to mode `0600`; symlinks are never followed. Transactional engines such as InnoDB observe the snapshot; non-transactional tables can still change during export and are not guaranteed to be transaction-consistent.

Default private storage is namespaced outside the document root. Override only with an absolute non-public path:

```php
define('XSOFTY_MCP_PRIVATE_DIR', '/secure/path/xsofty-mcp-private');
```

Scheduled backups are disabled by default. Daily/weekly scheduling retains 1–20 scheduled archives. Async jobs are processed by WP-Cron and retain only 50 bounded status records. Production restore of complete site backups is intentionally not exposed.

## Plugin checkpoints

A safe plugin update is bound to the exact current and advertised target versions and first creates a private verified ZIP checkpoint from descriptor-stable source snapshots. Only installed plugin basenames are accepted; traversal, symlinks, multisite management and bridge self-update are rejected. Failed or wrong-version updates automatically restore the checkpoint. Manual checkpoint rollback remains confirmation-gated.

## Approval queue

A token can submit an enabled mutating tool call for WordPress-admin review. The queue rejects credential-like fields, caps stored JSON at 32 KB, expires requests after 24 hours, and shows canonical sanitized arguments plus their deterministic plan hash to the administrator and owning token. Approved requests are revalidated against that hash, current schema, token scope and tool policy, then execute once; replay is denied and stale execution records fail closed.

## Operations

- Use a dedicated WordPress administrator account for each production integration.
- Keep plugin, theme-write, maintenance, backup and audit scopes disabled unless required.
- Keep manual WP-Cron execution disabled unless an operator explicitly needs it.
- Review the audit and approval panels regularly.
- Rotate or revoke a connection immediately if exposure is suspected; outstanding signed URLs for that token become invalid.
- Backups and plugin checkpoints can contain sensitive code/configuration and must be protected independently.

## Limitations

- Multisite backup and plugin management are disabled pending network-scoped policy.
- No full-site restore over MCP.
- SEO writes require active Yoast or Rank Math capability detection; unsupported sites fail closed.
- Redirects are exact-path, site-relative and internal only.
- Database views, triggers, events, routines and server-level grants are outside the backup format.

## Uninstall

Uninstall removes settings, credentials, usage telemetry, redirects, job/approval state, audit records and scheduled MCP cron events. Private backup archives, plugin checkpoints and theme restore files are intentionally preserved for manual verification/removal.
