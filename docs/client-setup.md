# Connect an MCP Client

Xsofty WordPress MCP Bridge exposes a remote **Streamable HTTP** MCP endpoint. Installing the plugin alone does not connect an AI client; create a named connection in WordPress and configure one compatible client below.

## Before you connect

1. Install and activate the plugin on WordPress 6.5+ with PHP 8.1+.
2. On a live website, confirm the site uses HTTPS.
3. Open **WordPress Admin → Settings → Xsofty MCP**.
4. Create a separate named connection for this client using the least-powerful suitable preset.
5. Copy the one-time token immediately. It cannot be retrieved later.
6. Note the MCP endpoint shown by the plugin. It normally has this shape:

```text
https://example.com/wp-json/xsofty-mcp/v1/mcp
```

Use a different connection/token for every client. Never add a real token to a repository, screenshot, support message, URL query, or shared configuration file.

## Codex

Codex CLI and the Codex IDE extension share the same MCP configuration on a Codex host. Current Codex supports Streamable HTTP and can read the bearer token from an environment variable.

```bash
export WORDPRESS_MCP_TOKEN='PASTE_YOUR_ONE_TIME_TOKEN_HERE'

codex mcp add wordpress-site \
  --url https://example.com/wp-json/xsofty-mcp/v1/mcp \
  --bearer-token-env-var WORDPRESS_MCP_TOKEN

codex mcp list
```

Open Codex and use `/mcp` to inspect the connected server. Ensure `WORDPRESS_MCP_TOKEN` is available to the process that launches Codex; IDE applications may need the variable configured in their launch environment.

Remove the connection with:

```bash
codex mcp remove wordpress-site
```

Official reference: [OpenAI Codex MCP documentation](https://developers.openai.com/codex/mcp/)

## Claude Code

Claude Code supports remote HTTP MCP servers with authorization headers. Use **user scope** so the credential is not written to a project repository.

```bash
export WORDPRESS_MCP_TOKEN='PASTE_YOUR_ONE_TIME_TOKEN_HERE'

claude mcp add \
  --scope user \
  --transport http \
  wordpress-site \
  https://example.com/wp-json/xsofty-mcp/v1/mcp \
  --header "Authorization: Bearer $WORDPRESS_MCP_TOKEN"

claude mcp list
```

Start Claude Code and use `/mcp` to inspect server status and tools. This command expands the token into Claude's user-level configuration; protect that configuration as a credential and never use project scope for a real token. Advanced deployments can use Claude Code's `headersHelper` feature to obtain headers from a password manager or protected local helper.

Remove it with:

```bash
claude mcp remove wordpress-site --scope user
```

Official reference: [Anthropic Claude Code MCP documentation](https://code.claude.com/docs/en/mcp)

## Grok

### Grok CLI

Grok CLI supports remote HTTP MCP servers and expands environment variables in `~/.grok/config.toml` headers.

```bash
export WORDPRESS_MCP_TOKEN='PASTE_YOUR_ONE_TIME_TOKEN_HERE'
```

Add this user-level configuration:

```toml
[mcp_servers.wordpress-site]
url = "https://example.com/wp-json/xsofty-mcp/v1/mcp"
headers = { Authorization = "Bearer ${WORDPRESS_MCP_TOKEN}" }
```

Then verify it:

```bash
grok mcp doctor wordpress-site
grok mcp list
```

In the Grok TUI, `/mcps` opens MCP server management.

### Grok on the web

Custom MCP connectors on grok.com require a **Grok Business or Enterprise** team and a team administrator:

1. Sign in to [console.x.ai](https://console.x.ai/), select the team, and open **Grok Business → Connectors**.
2. Select **Add Connector → Other**.
3. Enter the public HTTPS WordPress MCP endpoint.
4. Complete the authentication options offered for the connector using the dedicated WordPress connection credential.
5. Team members can then enable the approved connector from [grok.com/connectors](https://grok.com/connectors).

Grok's cloud must reach the endpoint over the public internet; it rejects localhost and private-network addresses. Do not place the token in the URL. If the tenant does not offer a compatible static authorization-header option, use Grok CLI instead of weakening plugin authentication.

Official references: [Grok CLI MCP servers](https://docs.x.ai/build/features/mcp-servers), [Grok connector management](https://docs.x.ai/grok/connector-management), and [Custom MCP Tunneling](https://docs.x.ai/grok/connectors/custom-mcp-tunneling)

## Hermes Agent

Store the token in Hermes' protected environment file, not directly in `config.yaml`.

```bash
hermes config env-path
```

Add the token to that file:

```bash
WORDPRESS_MCP_TOKEN=PASTE_YOUR_ONE_TIME_TOKEN_HERE
```

Open the Hermes configuration:

```bash
hermes config edit
```

Add:

```yaml
mcp_servers:
  wordpress-site:
    url: "https://example.com/wp-json/xsofty-mcp/v1/mcp"
    headers:
      Authorization: "Bearer ${WORDPRESS_MCP_TOKEN}"
    connect_timeout: 30
    enabled: true
```

Reload MCP servers or restart Hermes, then verify connection and tool discovery:

```bash
hermes mcp test wordpress-site
```

Inside a running Hermes session, use `/reload-mcp` after changing MCP configuration.

Official reference: [Hermes Agent MCP documentation](https://hermes-agent.nousresearch.com/docs/user-guide/features/mcp)

## OpenClaw

OpenClaw supports Streamable HTTP MCP servers through **Control UI → Settings → MCP** and through its `mcp.servers` configuration.

1. Make the token available to the OpenClaw Gateway process:

```bash
export WORDPRESS_MCP_TOKEN='PASTE_YOUR_ONE_TIME_TOKEN_HERE'
```

2. In the scoped MCP configuration editor, add:

```json5
{
  mcp: {
    servers: {
      "wordpress-site": {
        url: "https://example.com/wp-json/xsofty-mcp/v1/mcp",
        transport: "streamable-http",
        enabled: true,
        headers: {
          Authorization: "Bearer ${WORDPRESS_MCP_TOKEN}",
        },
      },
    },
  },
}
```

3. Verify the saved definition and live connection:

```bash
openclaw mcp doctor wordpress-site --probe
openclaw mcp status --verbose
```

If the Gateway is already running and does not pick up the change, use `openclaw mcp reload` in the owning process or restart the Gateway. Keep the token out of literal shared configuration and ensure the Gateway service receives the environment variable.

Official reference: [OpenClaw MCP documentation](https://docs.openclaw.ai/tools/mcp)

## Verify safely

After connecting any client:

1. Confirm the discovered tool count matches the connection's assigned policy.
2. Start with `site_info`, `list_content`, or another harmless read tool.
3. Confirm a read-only connection cannot call a mutation.
4. Before the first production mutation, create and verify a backup.
5. Review the WordPress activity log after testing.
6. Revoke the connection immediately if its token may have been exposed.

A client connection never bypasses the plugin's scopes, tool allowlist, global disables, WordPress capabilities, mutation confirmations, approval requirements, expiry, revocation, or rate limits.
