<?php
$path=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-control.php';if(!is_file($path))throw new RuntimeException('Control class must exist.');require __DIR__.'/bootstrap.php';require_once $path;
function expect_control($c,string $m):void{if(!$c)throw new RuntimeException($m);}
$expected=['export_audit_log'=>'audit','submit_approval_request'=>'audit','list_approval_requests'=>'audit','execute_approved_request'=>'audit'];foreach($expected as $n=>$s){$d=Xsofty_MCP_Tools::definition($n);expect_control(is_array($d)&&$d['scope']===$s,$n.' missing or wrong scope.');if(in_array($n,['submit_approval_request','execute_approved_request'],true))expect_control(in_array('confirm',$d['inputSchema']['required']??[],true),$n.' must require confirmation.');}
try{Xsofty_MCP_Control::sanitize_request_arguments(['api_key'=>'secret']);throw new RuntimeException('Sensitive approval argument accepted.');}catch(InvalidArgumentException $e){}
$clean=Xsofty_MCP_Control::sanitize_request_arguments(['id'=>5,'values'=>['title'=>'Safe']]);expect_control($clean['values']['title']==='Safe','Safe nested arguments rejected.');
$source=file_get_contents($path);expect_control(str_contains($source,"!empty(\$result['isError'])"),'Approved MCP errors must transition requests to failed.');
$resources=Xsofty_MCP_Control::resource_definitions();expect_control(count($resources)>=4&&isset($resources[0]['uri'],$resources[0]['mimeType']),'Resource definitions missing.');
print("CONTROL_PLANE_CONTRACT_PASS\n");
