<?php
require __DIR__ . '/bootstrap.php';
function expect_tool($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['xwmcp_options']=[]; $GLOBALS['xwmcp_transients']=[]; $GLOBALS['xwmcp_current_user']=1;
$created=Xsofty_MCP_Auth::create_token('Restricted reader',1,['site_read','content_read'],time()+3600,['site_info']);
$result=Xsofty_MCP_Auth::authorize(new WP_REST_Request(['Authorization'=>'Bearer '.$created['token']]));
expect_tool($result===true,'Restricted token must authorize.');
$names=array_column(Xsofty_MCP_Tools::exposed_definitions(),'name');
expect_tool($names===['site_info'],'Discovery must expose only tools explicitly allowed to the active token.');
try { Xsofty_MCP_Tools::call('list_content',[]); throw new RuntimeException('Disallowed tool unexpectedly executed.'); }
catch (RuntimeException $error) { expect_tool(str_contains($error->getMessage(),'not enabled'),'Execution must reject a tool outside the token allowlist.'); }
print("TOOL_ALLOWLIST_TESTS_PASS\n");
