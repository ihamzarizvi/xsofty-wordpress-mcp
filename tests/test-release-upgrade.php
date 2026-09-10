<?php
$main=file_get_contents(dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/xsofty-wordpress-mcp.php');
if(!preg_match("/Version:\\s*0\\.3\\.0/",$main))throw new RuntimeException('Plugin header must declare 0.3.0.');
if(!str_contains($main,"define('XSOFTY_WP_MCP_VERSION', '0.3.0')"))throw new RuntimeException('Runtime version must declare 0.3.0.');
if(!str_contains($main,'Xsofty_MCP_Auth::migrate_legacy_token();'))throw new RuntimeException('Upgrade must explicitly migrate the active legacy credential.');
print("RELEASE_UPGRADE_CONTRACT_PASS\n");
