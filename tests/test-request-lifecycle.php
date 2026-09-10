<?php
require __DIR__ . '/bootstrap.php';
function expect_lifecycle($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_current_user']=7;$_SERVER['REMOTE_ADDR']='127.0.0.9';
$created=Xsofty_MCP_Auth::create_token('Worker test',1,['site_read'],0,['site_info']);
$valid=new WP_REST_Request(['Authorization'=>'Bearer '.$created['token']]);
expect_lifecycle(Xsofty_MCP_Auth::authorize($valid)===true,'Valid token must authorize.');
expect_lifecycle(get_current_user_id()===1 && Xsofty_MCP_Auth::active_token_id()===$created['id'],'Valid request must install temporary actor context.');
Xsofty_MCP_Auth::end_request();
expect_lifecycle(get_current_user_id()===7 && Xsofty_MCP_Auth::active_token_id()==='','Request end must restore previous user and clear token.');
expect_lifecycle(is_wp_error(Xsofty_MCP_Auth::authorize($valid))===false,'Second valid request must authorize.');
$invalid=new WP_REST_Request(['Authorization'=>'Bearer xwmcp_'.str_repeat('f',16).'_'.str_repeat('0',64)]);
$result=Xsofty_MCP_Auth::authorize($invalid);
expect_lifecycle(is_wp_error($result),'Invalid request must fail.');
expect_lifecycle(get_current_user_id()===7 && Xsofty_MCP_Auth::active_token_id()==='' && Xsofty_MCP_Auth::active_token()===null,'A reused worker must clear prior actor and token before validating the next request.');
print("REQUEST_LIFECYCLE_TESTS_PASS\n");
