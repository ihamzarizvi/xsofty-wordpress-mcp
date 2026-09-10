<?php
require __DIR__ . '/bootstrap.php';
function expect_update($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['xwmcp_options']=[]; $GLOBALS['xwmcp_transients']=[]; $GLOBALS['xwmcp_current_user']=1;
$created=Xsofty_MCP_Auth::create_token('Original',1,['site_read'],0,[]);
$before=Xsofty_MCP_Auth::token_records()[$created['id']];
expect_update(Xsofty_MCP_Auth::update_token($created['id'],['name'=>'Editors','scopes'=>['content_read','content_write','invalid'],'allowed_tools'=>['list_content','get_content','../bad'],'expires_at'=>time()+7200,'enabled'=>false]),'Existing token must update.');
$after=Xsofty_MCP_Auth::token_records()[$created['id']];
expect_update($after['name']==='Editors','Name must update.');
expect_update($after['scopes']===['content_read','content_write'],'Scopes must be validated.');
expect_update($after['allowed_tools']===['list_content','get_content','bad'],'Tool names must be sanitized and deduplicated.');
expect_update($after['enabled']===false,'Enabled state must update.');
expect_update($after['token_hash']===$before['token_hash'] && $after['download_key']===$before['download_key'],'Editing metadata must not rotate credential material.');
expect_update(!Xsofty_MCP_Auth::update_token('missing',['name'=>'No']), 'Unknown token update must fail.');
$result=Xsofty_MCP_Auth::authorize(new WP_REST_Request(['Authorization'=>'Bearer '.$created['token']]));
expect_update(is_wp_error($result) && $result->get_error_code()==='xsofty_mcp_unauthorized','Disabled token must not authorize.');
print("TOKEN_UPDATE_TESTS_PASS\n");
