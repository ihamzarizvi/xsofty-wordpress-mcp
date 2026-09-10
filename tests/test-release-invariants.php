<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-operations.php';
require_once dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-jobs.php';
require_once dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-control.php';
function release_expect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$definitions=Xsofty_MCP_Tools::definitions();$names=[];$valid_scopes=Xsofty_MCP_Auth::valid_scopes();
foreach($definitions as $definition){$name=$definition['name'];release_expect(!isset($names[$name]),'Duplicate tool: '.$name);$names[$name]=true;release_expect(in_array($definition['scope'],$valid_scopes,true),'Invalid scope: '.$name);$read_only=!empty($definition['annotations']['readOnlyHint']);if(!$read_only){$properties=(array)($definition['inputSchema']['properties']??[]);$required=(array)($definition['inputSchema']['required']??[]);$confirmed=in_array('confirm',$required,true);$dry_run_exception=isset($properties['dry_run'])&&isset($properties['confirm']);release_expect($confirmed||$dry_run_exception,'Mutation lacks confirmation: '.$name);}}
$tools_source=file_get_contents(dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-tools.php');release_expect(str_contains($tools_source,"['outputSchema']")&&str_contains($tools_source,"['title']"),'Rich schema enrichment missing.');
$resources=Xsofty_MCP_Control::resource_definitions();$uris=[];foreach($resources as $resource){release_expect(!isset($uris[$resource['uri']]),'Duplicate resource URI.');$uris[$resource['uri']]=true;release_expect(in_array(Xsofty_MCP_Control::resource_scope($resource['uri']),$valid_scopes,true),'Resource scope missing.');}
$root=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp';$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$credential='/xwmcp_[a-f0-9]{16}_[a-f0-9]{64}/i';$danger='/\b(eval|exec|shell_exec|system|passthru|proc_open|popen)\s*\(/';foreach($iterator as $file){if($file->getExtension()!=='php')continue;$source=file_get_contents($file->getPathname());release_expect(!preg_match($credential,$source),'Embedded bearer credential: '.$file->getFilename());release_expect(!preg_match($danger,$source),'Arbitrary execution primitive: '.$file->getFilename());}
release_expect(count($definitions)===61,'Expected 61 tools.');release_expect(count($resources)===5,'Expected five resources.');
print("RELEASE_INVARIANT_TESTS_PASS tools=61 resources=5\n");
