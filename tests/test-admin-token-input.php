<?php
require __DIR__ . '/bootstrap.php';
function expect_admin($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$presets=Xsofty_MCP_Admin::scope_presets();
expect_admin($presets['read_only']===['site_read','content_read'],'Read-only preset must be minimal.');
expect_admin(in_array('content_write',$presets['content_manager'],true) && !in_array('plugins',$presets['content_manager'],true),'Content preset must not grant plugin administration.');
expect_admin(in_array('backups',$presets['administrator'],true),'Administrator preset must include backups.');
$now=time();
$data=Xsofty_MCP_Admin::normalize_token_input(['name'=>' <b>SEO Team</b> ','preset'=>'read_only','scopes'=>['plugins','invalid'],'allowed_tools'=>['site_info','not_real'],'expiry_days'=>'30','rate_limit'=>'9999'], $now);
expect_admin($data['name']==='SEO Team','Name must be sanitized.');
expect_admin($data['scopes']===['site_read','content_read'],'Selected preset must define scopes and ignore forged scope fields.');
expect_admin($data['allowed_tools']===Xsofty_MCP_Admin::preset_definitions()['read_only']['tools'],'Named presets must define their own tool allowlist and ignore forged tool fields.');
expect_admin($data['expires_at']===$now+30*86400,'Expiry must be converted to an absolute timestamp.');
expect_admin($data['rate_limit']===600,'Token rate limit must be bounded.');
$custom=Xsofty_MCP_Admin::normalize_token_input(['name'=>'Custom','preset'=>'custom','scopes'=>['site_read','bad','audit'],'allowed_tools'=>['site_info','not_real'],'expiry_days'=>'0'], $now);
expect_admin($custom['scopes']===['site_read','audit'] && $custom['allowed_tools']===['site_info'] && $custom['expires_at']===0,'Custom scopes and tools must be allowlisted and zero expiry must mean no expiry.');
print("ADMIN_TOKEN_INPUT_TESTS_PASS\n");
