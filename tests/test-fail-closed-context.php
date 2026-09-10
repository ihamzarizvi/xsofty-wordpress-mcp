<?php
require __DIR__ . '/bootstrap.php';
function expect_closed($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$GLOBALS['xwmcp_options']=[Xsofty_MCP_Auth::OPTION_KEY=>array_merge(Xsofty_MCP_Auth::defaults(),['scopes'=>['site_read','content_read']])];
$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_current_user']=1;
Xsofty_MCP_Auth::clear_context();
expect_closed(!Xsofty_MCP_Auth::has_scope('site_read'),'Scope checks must fail closed without authenticated token context.');
expect_closed(!Xsofty_MCP_Auth::tool_allowed('site_info'),'Tool checks must fail closed without authenticated token context.');
try{Xsofty_MCP_Tools::call('site_info',[]);throw new RuntimeException('Direct dispatcher call unexpectedly succeeded.');}
catch(RuntimeException $error){expect_closed(str_contains($error->getMessage(),'not enabled'),'Direct tool dispatch must fail without token context.');}
print("FAIL_CLOSED_CONTEXT_TESTS_PASS\n");
