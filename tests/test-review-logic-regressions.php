<?php
if(!defined('XSOFTY_WP_MCP_VERSION'))define('XSOFTY_WP_MCP_VERSION','0.3.0-test');
require __DIR__.'/bootstrap.php';
function review_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$bad_notification=Xsofty_MCP_Server::handle(new WP_REST_Request(['MCP-Protocol-Version'=>'1900-01-01'],['jsonrpc'=>'2.0','method'=>'ping','params'=>[]]));
review_expect($bad_notification->get_status()===400,'Invalid-version notifications must be rejected before 202 handling.');
$good_notification=Xsofty_MCP_Server::handle(new WP_REST_Request(['MCP-Protocol-Version'=>'2025-06-18'],['jsonrpc'=>'2.0','method'=>'ping','params'=>[]]));
review_expect($good_notification->get_status()===202,'Valid notifications must return an empty 202 response.');
$missing=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-06-18']]));
review_expect($missing->get_status()===400,'Initialize must require capabilities and clientInfo.');
$valid=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-06-18','capabilities'=>[],'clientInfo'=>['name'=>'qa','version'=>'1']]]));
review_expect($valid->get_status()===200,'A complete initialize request must succeed.');
$newer=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>3,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25','capabilities'=>[],'clientInfo'=>['name'=>'qa','version'=>'1']]]));review_expect($newer->get_status()===200&&$newer->get_data()['result']['protocolVersion']==='2025-06-18','Newer clients must negotiate down to the newest supported bridge protocol.');
$bad_id=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>true,'method'=>'initialize','params'=>[]]));review_expect($bad_id->get_status()===400,'Boolean JSON-RPC IDs must be rejected.');
$bad_params=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['not-an-object']]));review_expect($bad_params->get_status()===400,'List params must be rejected.');
$large=Xsofty_MCP_Server::handle(new WP_REST_Request([],['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['blob'=>str_repeat('x',2097152)]]));review_expect($large->get_status()===413,'Bodies over 2 MB must be rejected.');
$root=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp';$tools=file_get_contents($root.'/includes/class-xsofty-mcp-tools.php');$seo=file_get_contents($root.'/includes/class-xsofty-mcp-seo.php');$ops=file_get_contents($root.'/includes/class-xsofty-mcp-operations.php');$control=file_get_contents($root.'/includes/class-xsofty-mcp-control.php');$uninstall=file_get_contents($root.'/uninstall.php');
review_expect(str_contains($tools,'Status must be draft, pending, private or publish.'),'Invalid content status must be rejected.');
review_expect(str_contains($seo,"array_diff(\$robots,['noindex'])")&&str_contains($seo,'REDIRECT_LOCK'),'Rank Math robots must be preserved and redirects locked.');
review_expect(str_contains($ops,"'disabled_on_multisite'")&&str_contains($ops,'expected_current_version'),'Health and plugin-update preconditions must be explicit.');
review_expect(str_contains($control,'plan_hash')&&str_contains($control,'executing_at'),'Approvals must bind plans and recover stale execution state.');
review_expect(str_contains($uninstall,"wp_clear_scheduled_hook('xsofty_mcp_run_backup_job',[(string)\$job_id])"),'Uninstall must clear argument-specific job hooks.');
foreach(Xsofty_MCP_Tools::definitions() as $definition)foreach((array)($definition['inputSchema']['properties']??[]) as $property)if(($property['type']??'')==='string')review_expect(isset($property['maxLength']),'Every string property must be bounded: '.$definition['name']);
print("REVIEW_LOGIC_REGRESSIONS_PASS\n");
