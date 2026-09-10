<?php
require __DIR__ . '/bootstrap.php';
function expect_audit($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_current_user']=1;$_SERVER['REMOTE_ADDR']='203.0.113.77';
$secret='xwmcp_'.str_repeat('a',16).'_'.str_repeat('b',64);
Xsofty_MCP_Auth::audit('privacy_test',false,['authorization'=>'Bearer '.$secret,'error'=>'Failure at /Users/example/private/file.php using https://user:pass@example.test/path?token=secret','scope'=>'site_read']);
$event=end($GLOBALS['xwmcp_options'][Xsofty_MCP_Auth::AUDIT_KEY]);$serialized=json_encode($event);
expect_audit(($event['context']['authorization']??'')==='[REDACTED]','Credential-bearing context keys must be fully redacted.');
expect_audit(!str_contains($serialized,$secret)&&!str_contains($serialized,'/Users/example/')&&!str_contains($serialized,'user:pass')&&!str_contains($serialized,'token=secret'),'Audit values must remove tokens, local paths and URL credentials/query secrets.');
$expected=substr(hash_hmac('sha256','203.0.113.77',wp_salt('auth')),0,16);
expect_audit($event['remote']===$expected,'Remote identifier must be keyed with the site secret.');
print("AUDIT_PRIVACY_TESTS_PASS\n");
