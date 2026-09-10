<?php
$path=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-operations.php';
if(!is_file($path))throw new RuntimeException('Operations class must exist.');
require __DIR__.'/bootstrap.php';require_once $path;
function expect_ops($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$expected=['get_site_health'=>'site_read','list_cron_events'=>'maintenance','run_cron_event'=>'maintenance','list_plugin_updates'=>'plugins','update_plugin_safely'=>'plugins','list_plugin_checkpoints'=>'plugins','restore_plugin_checkpoint'=>'plugins'];
foreach($expected as $name=>$scope){$d=Xsofty_MCP_Tools::definition($name);expect_ops(is_array($d)&&$d['scope']===$scope,$name.' missing or wrong scope.');if(in_array($name,['run_cron_event','update_plugin_safely','restore_plugin_checkpoint'],true))expect_ops(in_array('confirm',$d['inputSchema']['required']??[],true),$name.' must require confirmation.');}
expect_ops(Xsofty_MCP_Operations::validate_plugin_basename('akismet/akismet.php')==='akismet/akismet.php','Valid plugin basename rejected.');
foreach(['../evil.php','/absolute.php','xsofty-wordpress-mcp/xsofty-wordpress-mcp.php','folder/../../evil.php'] as $bad){try{Xsofty_MCP_Operations::validate_plugin_basename($bad);throw new RuntimeException('Unsafe plugin basename accepted.');}catch(InvalidArgumentException $e){}}
$a=Xsofty_MCP_Operations::cron_event_id(123,'safe_hook','hourly',['secret'=>'do-not-return']);$b=Xsofty_MCP_Operations::cron_event_id(123,'safe_hook','hourly',['secret'=>'do-not-return']);expect_ops($a===$b&&strlen($a)===24,'Cron identifiers must be stable bounded hashes.');
print("OPERATIONS_TOOL_CONTRACT_PASS\n");
