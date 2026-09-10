<?php
require __DIR__ . '/bootstrap.php';
function expect_schema($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$schema=['type'=>'object','properties'=>['ids'=>['type'=>'array','items'=>['type'=>'integer'],'maxItems'=>2],'values'=>['type'=>'object']],'required'=>['ids','values'],'additionalProperties'=>false];
expect_schema(Xsofty_MCP_Server::validate_arguments($schema,['ids'=>[1,2],'values'=>['key'=>'value']])===[],'Valid arrays and objects must pass.');
expect_schema(count(Xsofty_MCP_Server::validate_arguments($schema,['ids'=>'1,2','values'=>[]]))>=1,'Non-array value must fail array schema.');
expect_schema(count(Xsofty_MCP_Server::validate_arguments($schema,['ids'=>[1,'2'],'values'=>[]]))>=1,'Array item types must be validated.');
expect_schema(count(Xsofty_MCP_Server::validate_arguments($schema,['ids'=>[1,2,3],'values'=>[]]))>=1,'maxItems must be enforced.');
expect_schema(count(Xsofty_MCP_Server::validate_arguments($schema,['ids'=>[1],'values'=>['not','an','object']]))>=1,'List values must fail object schema.');
print("RECURSIVE_SCHEMA_VALIDATION_TESTS_PASS\n");
