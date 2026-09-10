=== Xsofty WordPress MCP Bridge ===
Contributors: xsofty
Tags: mcp, ai, automation, administration, backup
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure, scoped Model Context Protocol tools and resources for WordPress administration through Hermes and compatible MCP clients.

Copyright 2026 Hamza Rizvi and Xsofty (Private) Limited. Licensed under GPLv2 or later. The GPL covers the code but does not grant rights to use Xsofty branding for unofficial derivatives.

== Description ==

Provides a stateless Streamable HTTP / JSON-RPC 2.0 endpoint with multiple named hashed bearer credentials, per-token scopes/tool allowlists/expiry/revocation, WordPress capability checks, confirmation gates, optional administrator approvals, rate limits, origin validation, audit logging, private verified backups and safe plugin checkpoints.

No arbitrary PHP, JavaScript, shell, SQL, core-file or unrestricted filesystem execution is exposed. Generic theme writes remain limited to non-executable CSS, JSON, TXT and MD assets.

== Installation ==

1. Upload and activate the plugin.
2. Open Settings > Xsofty MCP.
3. Create a named connection with the least-powerful plain-language access preset, or use Custom access for precise scopes and tools.
4. Securely store the one-time token.
5. Configure the MCP endpoint and Authorization bearer header.
6. Use HTTPS on every live site.

== Security ==

New installations default to site/content read scopes. Every mutation requires confirm=true. Tokens are independently scoped, expirable and revocable and are bound to a WordPress administrator. Backups/checkpoints are private. Scheduled backups and manual WP-Cron execution are disabled by default. Sensitive approval fields are rejected and approved requests execute once.

== Frequently Asked Questions ==

= Does it provide shell, PHP, JavaScript, raw SQL or unrestricted file access? =

No.

= Can it modify PHP theme files? =

No. PHP, JavaScript, HTML and SVG mutations are prohibited. Only CSS, JSON, TXT and MD assets can be patched with explicit scope and confirmation.

= Does it work on multisite? =

Read/content operations may work per site, but backup and plugin-management operations are disabled until a network-scoped policy is configured.

= Are backups publicly accessible? =

No. They are outside the document root and use short-lived token-specific signed links. Token rotation or revocation invalidates outstanding links.

= Can it restore the complete site? =

No. Full-site restore is intentionally excluded. It can restore a single plugin from a private checkpoint created before a safe plugin update.

== Changelog ==

= 0.3.2 =
* Add verified setup instructions for Codex, Claude Code, Grok, Hermes Agent and OpenClaw to the WordPress control center and GitHub documentation.

= 0.3.1 =
* Fix saved named access presets incorrectly reopening as Custom access.
* Keep all 61 individual tools visibly selected when editing a Full site administrator connection.

= 0.3.0 =
* Add a redesigned, responsive WordPress control center with overview metrics, plain-language access presets, grouped tools, progressive technical details and safer mobile controls.
* Add multiple named tokens with per-token scopes, tool allowlists, expiry, rate limits and revocation.
* Add global tool controls, safer presets, request-context cleanup, pre-hash throttling and isolated usage telemetry.
* Add dry-run content plans, revisions and confirmed content rollback.
* Add taxonomy, registered-meta, menu, featured-image and media-metadata tools.
* Add curated Yoast/Rank Math adapters, core sitemap status and protected exact-path redirects.
* Add curated Site Health, bounded cron discovery and default-disabled manual cron execution.
* Add private plugin update checkpoints, automatic update rollback and confirmed checkpoint restore.
* Add asynchronous backup jobs, progress, cancellation, scheduling and retention.
* Add filtered audit CSV export, WordPress approval queue and one-time approved execution.
* Add scoped MCP resources plus titles and output schemas for every exposed tool.
* Harden nested schema validation, audit redaction, keyed remote pseudonyms and token-specific download signatures.

= 0.2.0 =
* Bind tokens to a valid WordPress administrator and enforce native capabilities.
* Split read, write, theme and audit scopes; default new installs to read-only.
* Move backup and restore storage outside the public document root.
* Add transaction-consistent database export and verified backup archives.
* Add Origin validation, throttling and MCP runtime schema checks.

= 0.1.0 =
* Initial private pilot.
