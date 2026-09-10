<?php
$path=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-seo.php';
if(!is_file($path))throw new RuntimeException('SEO adapter class must exist.');
require __DIR__.'/bootstrap.php';require_once $path;
function expect_seo($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$expected=['get_seo_status'=>'site_read','get_seo_metadata'=>'content_read','update_seo_metadata'=>'content_write','list_redirects'=>'settings','upsert_redirect'=>'settings','delete_redirect'=>'settings','get_sitemap_status'=>'site_read'];
foreach($expected as $name=>$scope){$d=Xsofty_MCP_Tools::definition($name);expect_seo(is_array($d)&&$d['scope']===$scope,$name.' must exist with '.$scope.' scope.');if(in_array($name,['update_seo_metadata','upsert_redirect','delete_redirect'],true))expect_seo(in_array('confirm',$d['inputSchema']['required']??[],true),$name.' must require confirmation.');}
expect_seo(Xsofty_MCP_SEO::valid_redirect('/old/','/new/',301)['source']==='/old/','Site-relative redirect must validate.');
foreach([['/wp-admin/','/new/',301],['/same/','/same/',301],['/old/','javascript:alert(1)',301],['/old/','/new/',305]] as $bad){try{Xsofty_MCP_SEO::valid_redirect(...$bad);throw new RuntimeException('Unsafe redirect was accepted.');}catch(InvalidArgumentException $e){}}
print("SEO_REDIRECT_TOOL_CONTRACT_PASS\n");
