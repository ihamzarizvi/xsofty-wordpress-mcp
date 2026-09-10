<?php
$path=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-jobs.php';if(!is_file($path))throw new RuntimeException('Jobs class must exist.');require __DIR__.'/bootstrap.php';require_once $path;
function expect_jobs($c,string $m):void{if(!$c)throw new RuntimeException($m);}
$expected=['get_backup_schedule'=>'backups','update_backup_schedule'=>'settings','start_backup_job'=>'backups','get_job_status'=>'backups','list_jobs'=>'backups','cancel_job'=>'backups'];foreach($expected as $n=>$s){$d=Xsofty_MCP_Tools::definition($n);expect_jobs(is_array($d)&&$d['scope']===$s,$n.' missing or wrong scope.');if(in_array($n,['update_backup_schedule','start_backup_job','cancel_job'],true))expect_jobs(in_array('confirm',$d['inputSchema']['required']??[],true),$n.' must require confirmation.');}
expect_jobs(Xsofty_MCP_Jobs::normalize_schedule('daily',8)===['schedule'=>'daily','retention'=>8],'Daily schedule normalization failed.');expect_jobs(Xsofty_MCP_Jobs::normalize_schedule('invalid',100)===['schedule'=>'disabled','retention'=>20],'Schedule bounds failed.');
print("JOB_TOOL_CONTRACT_PASS\n");
