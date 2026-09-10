<?php
require __DIR__ . '/bootstrap.php';
function expect_migration($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$settings=Xsofty_MCP_Auth::defaults();$settings['token_hash']=password_hash('xwmcp_'.str_repeat('a',64),PASSWORD_DEFAULT);$settings['token_hint']='legacy';$settings['actor_user_id']=1;$settings['download_key']=str_repeat('d',64);
for($i=0;$i<19;$i++){$id=str_pad(dechex($i+1),16,'0',STR_PAD_LEFT);$settings['tokens'][$id]=['id'=>$id,'name'=>'Existing','token_hash'=>'hash','actor_user_id'=>1,'scopes'=>['site_read'],'allowed_tools'=>[],'enabled'=>true];}
$GLOBALS['xwmcp_options']=[Xsofty_MCP_Auth::OPTION_KEY=>$settings];$GLOBALS['xwmcp_transients']=[];
$blocked=false;try{Xsofty_MCP_Auth::create_token('Must not displace legacy',1,['site_read']);}catch(RuntimeException $e){$blocked=true;}
expect_migration($blocked,'Creation must reserve capacity for an unmigrated legacy credential.');
$id=Xsofty_MCP_Auth::migrate_legacy_token();$after=Xsofty_MCP_Auth::settings();
expect_migration($id!==''&&count(Xsofty_MCP_Auth::token_records())===20,'Legacy migration must fill the reserved final slot.');
expect_migration($after['token_hash']===''&&$after['download_key']==='','Legacy secrets must clear only after migration.');
expect_migration(Xsofty_MCP_Auth::migrate_legacy_token()==='','Migration must be idempotent.');
print("LEGACY_CAPACITY_TESTS_PASS\n");
