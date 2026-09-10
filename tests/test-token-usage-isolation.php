<?php
require __DIR__ . '/bootstrap.php';
function expect_usage($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_current_user']=7;$_SERVER['REMOTE_ADDR']='127.0.0.11';
$created=Xsofty_MCP_Auth::create_token('Telemetry test',1,['site_read'],0,['site_info']);
$before=$GLOBALS['xwmcp_options'][Xsofty_MCP_Auth::OPTION_KEY]['tokens'][$created['id']];
expect_usage(Xsofty_MCP_Auth::authorize(new WP_REST_Request(['Authorization'=>'Bearer '.$created['token']]))===true,'Token must authorize.');
$policy=$GLOBALS['xwmcp_options'][Xsofty_MCP_Auth::OPTION_KEY]['tokens'][$created['id']];
$usage=$GLOBALS['xwmcp_options'][Xsofty_MCP_Auth::USAGE_KEY][$created['id']]??null;
expect_usage(($policy['last_used_at']??0)===($before['last_used_at']??0),'Authorization must not rewrite token security policy for telemetry.');
expect_usage(is_array($usage) && ($usage['last_used_at']??0)>0,'Last-used telemetry must be stored separately.');
Xsofty_MCP_Auth::end_request();
print("TOKEN_USAGE_ISOLATION_TESTS_PASS\n");
