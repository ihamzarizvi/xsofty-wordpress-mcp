<?php
require __DIR__ . '/bootstrap.php';
function expect_content_tool($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$expected=['list_taxonomies'=>'content_read','list_terms'=>'content_read','create_term'=>'content_write','update_term'=>'content_write','assign_terms'=>'content_write','get_registered_meta'=>'content_read','update_registered_meta'=>'content_write','list_menus'=>'content_read','update_menu_item'=>'content_write','update_media_metadata'=>'media','set_featured_image'=>'content_write'];
foreach($expected as $name=>$scope){$definition=Xsofty_MCP_Tools::definition($name);expect_content_tool(is_array($definition)&&$definition['scope']===$scope,$name.' must exist with '.$scope.' scope.');if(!str_starts_with($name,'list_')&&!str_starts_with($name,'get_'))expect_content_tool(in_array('confirm',$definition['inputSchema']['required']??[],true),$name.' must require explicit confirmation.');}
expect_content_tool(!empty(Xsofty_MCP_Tools::definition('list_taxonomies')['annotations']['readOnlyHint']),'Taxonomy inventory must be read-only.');
expect_content_tool(!empty(Xsofty_MCP_Tools::definition('update_menu_item')['annotations']['destructiveHint']),'Menu mutation must be marked destructive.');
print("CONTENT_MANAGEMENT_TOOL_CONTRACT_PASS\n");
