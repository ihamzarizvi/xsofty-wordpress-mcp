<?php
require __DIR__.'/bootstrap.php';
function final_review_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes';
$auth=file_get_contents($root.'/class-xsofty-mcp-auth.php');
$backups=file_get_contents($root.'/class-xsofty-mcp-backups.php');
$server=file_get_contents($root.'/class-xsofty-mcp-server.php');
$tools=file_get_contents($root.'/class-xsofty-mcp-tools.php');
$ops=file_get_contents($root.'/class-xsofty-mcp-operations.php');
$jobs=file_get_contents($root.'/class-xsofty-mcp-jobs.php');
$control=file_get_contents($root.'/class-xsofty-mcp-control.php');

$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_cache']=[];$GLOBALS['xwmcp_current_user']=1;$GLOBALS['xwmcp_ext_object_cache']=true;$GLOBALS['xwmcp_cache_incr_fails']=true;$GLOBALS['xwmcp_transients_fail_with_ext_cache']=true;$_SERVER['REMOTE_ADDR']='198.51.100.77';
$created=Xsofty_MCP_Auth::create_token('External cache outage',1,['site_read']);
$wrong='xwmcp_'.$created['id'].'_'.str_repeat('0',64);$request=new WP_REST_Request(['Authorization'=>'Bearer '.$wrong]);
for($i=0;$i<10;$i++)Xsofty_MCP_Auth::authorize($request);
$blocked=Xsofty_MCP_Auth::authorize($request);
final_review_expect(is_wp_error($blocked)&&$blocked->get_error_code()==='xsofty_mcp_rate_limited','External-cache increment and transient failure must fall back to persistent database rate state.');

$GLOBALS['xwmcp_options']=[];$GLOBALS['xwmcp_transients']=[];$GLOBALS['xwmcp_cache']=[];$GLOBALS['xwmcp_ext_object_cache']=false;$GLOBALS['xwmcp_cache_incr_fails']=false;$GLOBALS['xwmcp_transients_fail_with_ext_cache']=false;$GLOBALS['xwmcp_home_url']='http://public.example.com';$GLOBALS['xwmcp_environment']='development';$GLOBALS['xwmcp_ssl']=false;
$public_http_token=Xsofty_MCP_Auth::create_token('Public HTTP regression',1,['site_read']);Xsofty_MCP_Auth::update_settings(function(array $settings):array{$settings['allow_http']=true;return $settings;});$public_http_request=new WP_REST_Request(['Authorization'=>'Bearer '.$public_http_token['token']]);$public_http=Xsofty_MCP_Auth::authorize($public_http_request);
final_review_expect(is_wp_error($public_http)&&$public_http->get_error_code()==='xsofty_mcp_https_required','Development environment must not authorize plaintext credentials on a public host.');

final_review_expect(str_contains($auth,'RATE_LIMIT_OPTION')&&!str_contains($auth,'try{$value=(int)get_transient($bucket)+1'),'Database fallback must not route through external-cache-backed transients.');
final_review_expect(str_contains($auth,"subject_token_id'])?")&&str_contains($auth,'[A-Za-z]:\\\\'),'Audit indexing must preserve explicit subject tokens and redact Windows paths.');
final_review_expect(str_contains($backups,'open_download')&&str_contains($backups,'fstat($handle)')&&str_contains($server,'fpassthru'),'Downloads must stream from a descriptor revalidated against the canonical path.');
final_review_expect(str_contains($backups,'remove_file')&&str_contains($backups,"Unable to remove the temporary database export"),'Database export cleanup must retry and fail closed before publication.');
final_review_expect(str_contains($server,'authorize_site_read'),'The health endpoint must require site_read scope.');
final_review_expect(str_contains($server,"'tools/call'=>")&&str_contains($server,"'arguments'=>['type'=>'object']"),'tools/call parameters must be strictly validated before notification handling.');
final_review_expect(str_contains($tools,"'expected_sha256'")&&str_contains($tools,'Content changed since it was read'),'Content writes must compare an exact current-state hash.');
final_review_expect(str_contains($tools,'stale-read guard')&&!str_contains($tools,'compare-and-swap'),'Content hash documentation must not claim atomic compare-and-swap semantics.');
final_review_expect(str_contains($tools,"hash_file('sha256',\$path)")&&str_contains($tools,'$parent_stat'),'Theme publication must revalidate content and parent-directory identity.');
final_review_expect(substr_count($ops,"Plugin checkpoint operations are disabled on multisite")>=3,'Checkpoint create/list/restore must fail closed on multisite.');
final_review_expect(str_contains($ops,'Unable to clean plugin checkpoint staging')&&str_contains($ops,'Unable to recover the quarantined plugin'),'Checkpoint cleanup and rollback must be verified.');
final_review_expect(str_contains($ops,'$validated=true')&&str_contains($ops,'if($validated)'),'Validated restored plugins must be preserved if only cleanup fails.');
final_review_expect(str_contains($jobs,"\$old['schedule']!==\$normalized['schedule']")&&str_contains($jobs,'Unable to restore the previous backup schedule'),'Schedule reconciliation and rollback must validate recurrence and restoration.');
final_review_expect(str_contains($jobs,'PENDING_LEASE')&&str_contains($jobs,'Backup job was never scheduled'),'Abandoned pending jobs must become terminal.');
final_review_expect(str_contains($control,'Approval administrator is no longer authorized')&&str_contains($control,"user_can(\$approver,'manage_options')"),'Approval execution must revalidate the deciding administrator.');
final_review_expect(str_contains($backups,'array_slice($items,0,200)')&&str_contains($tools,'$max_files=1000'),'Backup and theme inventories must be bounded.');
print("FINAL_REVIEW_BLOCKERS_PASS\n");
