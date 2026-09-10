<?php
require __DIR__ . '/bootstrap.php';

function expect_true($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function reset_state(): void {
    $GLOBALS['xwmcp_options'] = [];
    $GLOBALS['xwmcp_transients'] = [];
    $GLOBALS['xwmcp_current_user'] = 1;
}

reset_state();
$created = Xsofty_MCP_Auth::create_token('Content Team', 1, ['site_read', 'content_read'], time() + 3600, ['site_info', 'list_content']);
expect_true(isset($created['id'], $created['token']), 'create_token must return a token id and one-time secret.');
expect_true(str_starts_with($created['token'], 'xwmcp_'), 'Token must use the expected high-entropy prefix.');
$records = Xsofty_MCP_Auth::token_records();
expect_true(count($records) === 1, 'One token record must be stored.');
$record = array_values($records)[0];
expect_true($record['name'] === 'Content Team', 'Token name must be stored.');
expect_true($record['scopes'] === ['site_read', 'content_read'], 'Token scopes must be independent.');
expect_true($record['allowed_tools'] === ['site_info', 'list_content'], 'Tool allowlist must be stored.');
expect_true(!isset($record['token']), 'Plain token must never be persisted.');
expect_true(password_verify($created['token'], $record['token_hash']), 'Stored token hash must verify the one-time secret.');

$second = Xsofty_MCP_Auth::create_token('SEO', 1, ['site_read'], 0, []);
expect_true(count(Xsofty_MCP_Auth::token_records()) === 2, 'Creating a token must not rotate or delete another token.');
Xsofty_MCP_Auth::revoke_token($created['id']);
expect_true(count(Xsofty_MCP_Auth::token_records()) === 1, 'Revoking one token must preserve other tokens.');
expect_true(isset(Xsofty_MCP_Auth::token_records()[$second['id']]), 'Unrelated token must remain after targeted revocation.');

$expired = Xsofty_MCP_Auth::create_token('Expired', 1, ['site_read'], time() - 1, []);
$request = new WP_REST_Request(['Authorization' => 'Bearer ' . $expired['token']]);
$result = Xsofty_MCP_Auth::authorize($request);
expect_true(is_wp_error($result) && $result->get_error_code() === 'xsofty_mcp_token_expired', 'Expired tokens must fail with an explicit error.');

$valid = new WP_REST_Request(['Authorization' => 'Bearer ' . $second['token']]);
expect_true(Xsofty_MCP_Auth::authorize($valid) === true, 'Valid named token must authorize.');
expect_true(Xsofty_MCP_Auth::active_token_id() === $second['id'], 'Authorized token must become request context.');
expect_true(Xsofty_MCP_Auth::has_scope('site_read'), 'Scope checks must use the active token.');
expect_true(!Xsofty_MCP_Auth::has_scope('content_write'), 'Scopes not granted to the active token must be denied.');
expect_true(Xsofty_MCP_Auth::tool_allowed('site_info'), 'Empty tool allowlist must permit scoped tools.');

print("AUTH_DOMAIN_TESTS_PASS\n");
