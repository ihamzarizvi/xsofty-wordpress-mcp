<?php
require __DIR__ . '/bootstrap.php';
function expect_revision($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$list=Xsofty_MCP_Tools::definition('list_content_revisions');$restore=Xsofty_MCP_Tools::definition('restore_content_revision');
expect_revision(is_array($list)&&$list['scope']==='content_read'&&!empty($list['annotations']['readOnlyHint']),'Revision listing must be read-scoped and annotated read-only.');
expect_revision(($list['inputSchema']['required']??[])===['id'],'Revision listing must require a parent content ID.');
expect_revision(is_array($restore)&&$restore['scope']==='content_write'&&!empty($restore['annotations']['destructiveHint']),'Revision restoration must be write-scoped and destructive.');
expect_revision(($restore['inputSchema']['required']??[])===['revision_id','confirm'],'Revision restore must require explicit confirmation.');
print("REVISION_TOOL_CONTRACT_TESTS_PASS\n");
