<?php
require __DIR__ . '/bootstrap.php';
function expect_dispatch($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['xwmcp_options']=[]; $GLOBALS['xwmcp_transients']=[]; $GLOBALS['xwmcp_current_user']=1;
$created=Xsofty_MCP_Auth::create_token('Restricted',1,['site_read','content_read'],time()+3600,['site_info']);
$headers=['Authorization'=>'Bearer '.$created['token'],'MCP-Protocol-Version'=>'2025-06-18'];
expect_dispatch(Xsofty_MCP_Auth::authorize(new WP_REST_Request($headers))===true,'Token must authorize.');
$request=new WP_REST_Request($headers,['jsonrpc'=>'2.0','id'=>9,'method'=>'tools/call','params'=>['name'=>'list_content','arguments'=>[]]]);
$response=Xsofty_MCP_Server::handle($request);
$data=$response->get_data();
expect_dispatch(isset($data['error']) && $data['error']['code']===-32601,'A tool outside the active token allowlist must be unknown at dispatch.');
print("DISPATCH_ALLOWLIST_TESTS_PASS\n");
