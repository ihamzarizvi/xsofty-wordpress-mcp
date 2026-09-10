<?php
require __DIR__.'/bootstrap.php';
function expect_admin_preset_persistence(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
expect_admin_preset_persistence(method_exists(Xsofty_MCP_Admin::class,'preset_for_policy'),'Admin UI must infer a saved named preset from its policy.');
expect_admin_preset_persistence(method_exists(Xsofty_MCP_Admin::class,'display_tools_for_preset'),'Admin UI must expand preset tool selections for display.');
$administrator=Xsofty_MCP_Admin::preset_definitions()['administrator'];
$reordered=array_reverse($administrator['scopes']);
expect_admin_preset_persistence(Xsofty_MCP_Admin::preset_for_policy($reordered,[])==='administrator','A saved full-administrator policy must render as Full site administrator, independent of scope order.');
$display=Xsofty_MCP_Admin::display_tools_for_preset('administrator',[]);
$known=array_column(Xsofty_MCP_Tools::definitions(),'name');
sort($display);sort($known);
expect_admin_preset_persistence($display===$known&&count($display)===61,'Full site administrator must display all 61 individual tools as selected even though an empty stored allowlist means all tools.');
$read=Xsofty_MCP_Admin::preset_definitions()['read_only'];
expect_admin_preset_persistence(Xsofty_MCP_Admin::preset_for_policy(array_reverse($read['scopes']),array_reverse($read['tools']))==='read_only','A saved named preset must be recognized independent of policy ordering.');
expect_admin_preset_persistence(Xsofty_MCP_Admin::preset_for_policy($read['scopes'],['site_info'])==='custom','A manually restricted policy must render as Custom access.');
expect_admin_preset_persistence(Xsofty_MCP_Admin::display_tools_for_preset('custom',['site_info'])===['site_info'],'Custom access must preserve its exact individual-tool display selection.');
print("ADMIN_PRESET_PERSISTENCE_PASS\n");
