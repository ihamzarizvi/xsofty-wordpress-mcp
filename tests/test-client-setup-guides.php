<?php
require __DIR__.'/bootstrap.php';
function expect_client_setup(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$root=dirname(__DIR__);$guide=$root.'/docs/client-setup.md';
expect_client_setup(is_file($guide),'GitHub must include docs/client-setup.md.');
$docs=file_get_contents($guide);$admin=file_get_contents($root.'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-admin.php');$readme=file_get_contents($root.'/README.md');$plugin_readme=file_get_contents($root.'/plugin/xsofty-wordpress-mcp/README.md');
$clients=['codex'=>'Codex','claude-code'=>'Claude Code','grok'=>'Grok','hermes-agent'=>'Hermes Agent','openclaw'=>'OpenClaw'];$ui=Xsofty_MCP_Admin::client_setup_definitions('https://example.com/wp-json/xsofty-mcp/v1/mcp');
expect_client_setup(array_keys($ui)===array_keys($clients),'WordPress setup clients must remain complete and ordered.');
foreach($clients as $key=>$client){expect_client_setup(str_contains($docs,'## '.$client),"GitHub setup guide missing $client instructions.");expect_client_setup($ui[$key]['label']===$client&&!empty($ui[$key]['description'])&&!empty($ui[$key]['code'])&&!empty($ui[$key]['verify'])&&str_starts_with($ui[$key]['docs'],'https://'),"WordPress setup definition incomplete for $client.");}
$ui_text=implode("\n",array_column($ui,'code'))."\n".implode("\n",array_column($ui,'description'))."\n".implode("\n",array_column($ui,'verify'));
foreach(['--bearer-token-env-var','--transport http','grok mcp doctor','hermes mcp test','openclaw mcp doctor'] as $marker){expect_client_setup(str_contains($docs,$marker),"Verified setup marker missing from GitHub guide: $marker");expect_client_setup(str_contains($ui_text,$marker),"Verified setup marker missing from WordPress UI: $marker");}
expect_client_setup(str_contains($docs,'Business or Enterprise')&&str_contains($docs,'Grok Business')&&str_contains($ui_text,'Business or Enterprise'),'Grok web connector requirements must be explicit.');
expect_client_setup(substr_count($ui_text,'https://example.com/wp-json/xsofty-mcp/v1/mcp')===5,'Every WordPress client example must use the live endpoint exactly once.');
expect_client_setup(str_contains($admin,'data-client=')&&str_contains($admin,'data-copy-target'),'WordPress setup screen must render accessible client disclosures and copy controls.');
expect_client_setup(str_contains($readme,'docs/client-setup.md')&&str_contains($plugin_readme,'docs/client-setup.md'),'Both repository readmes must link to the client setup guide.');
expect_client_setup(!preg_match('/Bearer\s+xwmcp_[a-f0-9_]+/i',$docs.$ui_text.$admin.$readme.$plugin_readme),'Documentation must never contain a real-looking MCP credential.');
print("CLIENT_SETUP_GUIDE_CONTRACT_PASS clients=5\n");
