# Security Policy

## Supported version

Security fixes are currently maintained for the latest `0.3.x` release.

## Production guidance

Before connecting an MCP client to a live WordPress site:

1. Create and verify a complete backup.
2. Use HTTPS. The local-HTTP override is only for private development hosts.
3. Create a dedicated WordPress administrator for the integration.
4. Start with the least-powerful access preset.
5. Give every client its own named connection and expiry.
6. Keep plugin, theme-write, backup, maintenance and audit access disabled unless required.
7. Keep manual WP-Cron execution disabled unless an operator explicitly needs it.
8. Review approvals and audit activity regularly.
9. Revoke the connection immediately if its bearer token may have been exposed.

## Built-in boundaries

- Bearer secrets are shown once; WordPress stores password hashes instead of plaintext tokens.
- Connections are independently scoped, expirable, rate-limited and revocable.
- Authentication is bound to a valid WordPress administrator.
- Native capabilities and object-level permissions are rechecked during execution.
- Every mutating tool requires literal `confirm=true`.
- High-impact work can be routed through an expiring, one-time approval queue.
- Global and per-token tool controls fail closed.
- Invalid-credential attempts are throttled before expensive password verification.
- Browser origins are same-origin or explicitly allowlisted HTTPS origins.
- Audit records redact credential-like fields, URL queries and local paths.
- Private backups and plugin checkpoints are kept outside the public document root.

The plugin does not expose shell execution, arbitrary PHP/JavaScript, raw SQL, WordPress core editing, unrestricted filesystem access, passwords, cookies, secrets or `wp-config.php`.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability.

For this private repository, use GitHub's **Security → Report a vulnerability** flow if private vulnerability reporting is enabled. Otherwise contact the repository owner privately and include:

- A concise description and impact
- Affected version
- Reproduction steps or proof of concept
- Required permissions and environment assumptions
- Any suggested mitigation

Do not include live credentials, private backup archives, customer data or production tokens. Reports will be acknowledged as soon as practical and assessed before public disclosure.
