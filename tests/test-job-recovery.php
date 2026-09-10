<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-jobs.php';
function job_recovery_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
Xsofty_MCP_Auth::update_settings(function(array $settings):array{$settings['backup_schedule']='daily';$settings['backup_retention']=5;return $settings;});
$old=time()+120;job_recovery_expect(wp_schedule_event($old,'daily',Xsofty_MCP_Jobs::SCHEDULE_HOOK),'Unable to seed old schedule.');
$GLOBALS['xwmcp_schedule_fails']=true;$failed=false;try{Xsofty_MCP_Jobs::update_schedule('weekly',7);}catch(RuntimeException $e){$failed=true;}
job_recovery_expect($failed,'A replacement scheduling failure must be reported.');
$settings=Xsofty_MCP_Auth::settings();job_recovery_expect($settings['backup_schedule']==='daily'&&wp_next_scheduled(Xsofty_MCP_Jobs::SCHEDULE_HOOK)===$old,'Failed replacement must preserve old settings and event.');
$GLOBALS['xwmcp_schedule_fails']=false;$updated=Xsofty_MCP_Jobs::update_schedule('weekly',7);job_recovery_expect($updated['schedule']==='weekly'&&$updated['retention']===7&&wp_get_schedule(Xsofty_MCP_Jobs::SCHEDULE_HOOK)==='weekly','Successful replacement must atomically publish new schedule state.');
$stale='stalejob';update_option(Xsofty_MCP_Jobs::JOBS_OPTION,[$stale=>['id'=>$stale,'token_id'=>'system','status'=>'running','progress'=>10,'created_at'=>gmdate('c',time()-90000),'lease_expires_at'=>time()-1,'heartbeat_at'=>time()-90000]],false);
$listed=Xsofty_MCP_Jobs::list_jobs();$record=$listed['items'][0]??[];job_recovery_expect(($record['status']??'')==='failed'&&str_contains((string)($record['message']??''),'interrupted'),'Expired running jobs must transition to failed manual-verification state.');
$id='retryjob';update_option(Xsofty_MCP_Jobs::JOBS_OPTION,[$id=>['id'=>$id,'token_id'=>'system','status'=>'pending','progress'=>0,'label'=>'busy','created_at'=>gmdate('c')]],false);
$backupLock='xwmcp_backup_'.substr(hash_hmac('sha256',home_url(),wp_salt('auth')),0,32);add_option($backupLock,['owner'=>'other','expires'=>time()+DAY_IN_SECONDS],'',false);$GLOBALS['xwmcp_schedule_fails']=true;Xsofty_MCP_Jobs::run_backup_job($id);$jobs=(array)get_option(Xsofty_MCP_Jobs::JOBS_OPTION,[]);job_recovery_expect(($jobs[$id]['status']??'')==='failed'&&($jobs[$id]['message']??'')==='Unable to queue a backup retry.','A failed backup retry schedule must terminally fail the job.');
print("JOB_RECOVERY_AND_SCHEDULE_TESTS_PASS\n");
