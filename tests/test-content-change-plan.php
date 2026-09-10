<?php
require __DIR__ . '/bootstrap.php';
function expect_plan($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$current=['id'=>42,'title'=>'Old title','content'=>'Original body','excerpt'=>'Old excerpt','status'=>'draft'];
$plan=Xsofty_MCP_Tools::content_update_plan($current,['title'=>'New title','content'=>'Replacement body','status'=>'publish','confirm'=>false,'dry_run'=>true]);
expect_plan($plan['operation']==='update_content'&&$plan['target_id']===42,'Plan must identify operation and target.');
expect_plan($plan['changed_fields']===['title','content','status'],'Plan must preserve deterministic supported-field order.');
expect_plan($plan['changes']['title']['from']==='Old title'&&$plan['changes']['title']['to']==='New title','Short text diff must be explicit.');
expect_plan(!isset($plan['changes']['content']['from'])&&!isset($plan['changes']['content']['to']),'Content bodies must not be duplicated into plan output.');
expect_plan(isset($plan['changes']['content']['from_sha256'],$plan['changes']['content']['to_sha256'],$plan['changes']['content']['from_bytes'],$plan['changes']['content']['to_bytes']),'Content diff must use bounded hashes and byte counts.');
expect_plan($plan['requires_confirmation']===true&&$plan['will_create_revision']===true,'Plan must describe safety gates.');
print("CONTENT_CHANGE_PLAN_TESTS_PASS\n");
