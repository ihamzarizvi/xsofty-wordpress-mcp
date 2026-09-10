<?php
require __DIR__ . '/bootstrap.php';
function expect_throttle($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_current_user']=7;$_SERVER['REMOTE_ADDR']='198.51.100.23';
$created=Xsofty_MCP_Auth::create_token('Throttle test',1,['site_read']);
$wrong='xwmcp_'.$created['id'].'_'.str_repeat('0',64);$request=new WP_REST_Request(['Authorization'=>'Bearer '.$wrong]);
for($i=1;$i<=10;$i++){$result=Xsofty_MCP_Auth::authorize($request);expect_throttle(is_wp_error($result)&&$result->get_error_code()==='xsofty_mcp_unauthorized','First ten invalid secrets should be rejected without lockout.');}
$start=microtime(true);$blocked=Xsofty_MCP_Auth::authorize($request);$elapsed=microtime(true)-$start;
expect_throttle(is_wp_error($blocked)&&$blocked->get_error_code()==='xsofty_mcp_rate_limited','Eleventh invalid secret must be blocked by the credential bucket.');
expect_throttle($elapsed<0.08,'Locked credential must be rejected before an expensive password hash.');
expect_throttle(Xsofty_MCP_Auth::active_token()===null&&get_current_user_id()===7,'Throttling must not install authentication context.');
print("PREHASH_THROTTLE_TESTS_PASS\n");
