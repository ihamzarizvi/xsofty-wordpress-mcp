<?php
$source=file_get_contents(dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-auth.php');
if(!str_contains($source,'function with_policy_lock'))throw new RuntimeException('Token policy mutations require a shared lock helper.');
foreach(['create_token','update_token','revoke_token','migrate_legacy_token'] as $method){$pattern='/function\\s+'.preg_quote($method,'/').'\\b[\\s\\S]*?with_policy_lock/';if(!preg_match($pattern,$source))throw new RuntimeException($method.' must acquire the token policy lock.');}
if(!str_contains($source,'GET_LOCK')||!str_contains($source,'RELEASE_LOCK'))throw new RuntimeException('Production lock must use a database-backed atomic lock.');
print("TOKEN_POLICY_LOCK_CONTRACT_PASS\n");
