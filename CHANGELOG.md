# Changelog

All notable changes to Xsofty WordPress MCP Bridge are documented here.

## [0.3.2] - 2026-09-10

### Added

- Verified connection instructions for Codex, Claude Code, Grok, Hermes Agent and OpenClaw
- Responsive client setup cards in the WordPress control center using the live site endpoint
- Dedicated GitHub guide covering commands, secure token handling, verification, removal and client-specific limitations

## [0.3.1] - 2026-09-10

### Fixed

- Saved named access policies now reopen with their matching preset instead of always showing Custom access
- Full site administrator connections now display all 61 individual tools as selected while retaining the secure empty-allowlist-means-all storage model

## [0.3.0] - 2026-09-10

### Added

- Responsive WordPress control center with overview metrics and progressive technical details
- Six plain-language access presets covering read-only, content, SEO, maintenance, administrator and custom roles
- Multiple named connections with independent scopes, tool allowlists, expiry, rate limits and revocation
- Curated content, media, taxonomy, menu, SEO, redirect, health, maintenance and approval capabilities
- Private plugin checkpoints, verified safe updates and rollback
- Private synchronous/asynchronous backups, schedules, retention and signed downloads
- Five scoped read-only MCP resources
- Deterministic source, security, admin-contract and release-invariant tests
- GPL-2.0-or-later publication with explicit Hamza Rizvi/Xsofty copyright notices, contribution terms and a separate trademark policy

### Security

- Added global and per-connection tool controls
- Added request-context cleanup and pre-hash invalid-credential throttling
- Added recursive runtime schema validation and audit redaction
- Added one-time, token-owned administrator approval execution
- Preserved the prohibition on arbitrary code, shell, SQL, core and unrestricted filesystem access

## [0.2.0]

- Bound credentials to valid WordPress administrators and native capabilities
- Split read, write, theme and audit scopes
- Moved backup and restore storage outside the public document root
- Added transaction-consistent database export, origin validation and throttling

## [0.1.0]

- Initial private pilot
