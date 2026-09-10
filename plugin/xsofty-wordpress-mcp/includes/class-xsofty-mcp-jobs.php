<?php
if(!defined('ABSPATH'))exit;

final class Xsofty_MCP_Jobs {
    public const JOBS_OPTION='xsofty_mcp_jobs';
    public const JOB_HOOK='xsofty_mcp_run_backup_job';
    public const SCHEDULE_HOOK='xsofty_mcp_scheduled_backup';
    private const LOCK_OPTION='xsofty_mcp_jobs_lock';
    private const RUN_LEASE=86400;
    private const PENDING_LEASE=900;

    public static function init():void {add_action(self::JOB_HOOK,[self::class,'run_backup_job'],10,1);add_action(self::SCHEDULE_HOOK,[self::class,'run_scheduled_backup']);}

    public static function normalize_schedule(string $schedule,int $retention):array {$schedule=sanitize_key($schedule);if(!in_array($schedule,['disabled','daily','weekly'],true))$schedule='disabled';return ['schedule'=>$schedule,'retention'=>max(1,min(20,$retention))];}

    private static function lock(string $name,int $ttl=30):string {global $wpdb;$advisory='xwmcp_'.substr(hash_hmac('sha256',$name,wp_salt('auth')),0,32);if(is_object($wpdb)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var')){if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$advisory))!==1)throw new RuntimeException('Another MCP job operation is in progress.');return 'mysql:'.$advisory;}$token=bin2hex(random_bytes(8));$value=['token'=>$token,'expires'=>time()+$ttl];if(add_option($name,$value,'','no'))return $token;$current=get_option($name);if(is_array($current)&&(int)($current['expires']??0)<time()){delete_option($name);if(add_option($name,$value,'','no'))return $token;}throw new RuntimeException('Another MCP job operation is in progress.');}
    private static function unlock(string $name,string $token):void {global $wpdb;if(str_starts_with($token,'mysql:')){if(is_object($wpdb)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var'))$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',substr($token,6)));return;}$value=get_option($name);if(is_array($value)&&hash_equals((string)($value['token']??''),$token))delete_option($name);}
    private static function records():array {return (array)get_option(self::JOBS_OPTION,[]);}
    private static function save_records(array $records):void {uasort($records,static fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));$active=array_filter($records,static fn($job)=>in_array((string)($job['status']??''),['pending','running'],true));$terminal=array_diff_key($records,$active);$records=$active+array_slice($terminal,0,max(0,50-count($active)),true);update_option(self::JOBS_OPTION,$records,false);}
    private static function mutate(callable $callback){$lock=self::lock(self::LOCK_OPTION);try{$records=self::records();$result=$callback($records);self::save_records($records);return $result;}finally{self::unlock(self::LOCK_OPTION,$lock);}}
    private static function public_job(array $job):array {unset($job['token_id'],$job['lease_expires_at'],$job['heartbeat_at']);return $job;}
    private static function visible(array $job):bool {$owner=(string)($job['token_id']??'system');$active=Xsofty_MCP_Auth::active_token_id();return $owner==='system'||($active!==''&&hash_equals($owner,$active));}

    private static function recover_stale_jobs():void {self::mutate(function(array &$records):void {foreach($records as &$job){$status=(string)($job['status']??'');if($status==='pending'){$created=strtotime((string)($job['created_at']??''));$id=sanitize_key((string)($job['id']??''));if($created!==false&&$created+self::PENDING_LEASE<time()&&($id===''||wp_next_scheduled(self::JOB_HOOK,[$id])===false)){$job['status']='failed';$job['progress']=100;$job['message']='Backup job was never scheduled or its event was lost.';$job['finished_at']=gmdate('c');continue;}}if($status==='running'&&(int)($job['lease_expires_at']??0)>0&&(int)$job['lease_expires_at']<time()){$job['status']='failed';$job['progress']=100;$job['message']='Backup worker was interrupted; verify private backup inventory before retrying.';$job['finished_at']=gmdate('c');unset($job['lease_expires_at'],$job['heartbeat_at']);}}unset($job);});}

    private static function scheduled_event():?array {
        if(function_exists('wp_get_scheduled_event')){$event=wp_get_scheduled_event(self::SCHEDULE_HOOK);if($event)return ['timestamp'=>(int)$event->timestamp,'schedule'=>(string)$event->schedule,'args'=>(array)$event->args];}
        $timestamp=wp_next_scheduled(self::SCHEDULE_HOOK);if($timestamp===false)return null;$schedule=function_exists('wp_get_schedule')?(string)wp_get_schedule(self::SCHEDULE_HOOK):'';return ['timestamp'=>(int)$timestamp,'schedule'=>$schedule,'args'=>[]];
    }

    public static function schedule_settings():array {$settings=Xsofty_MCP_Auth::settings();$normalized=self::normalize_schedule((string)($settings['backup_schedule']??'disabled'),(int)($settings['backup_retention']??5));$next=wp_next_scheduled(self::SCHEDULE_HOOK);return $normalized+['next_run_gmt'=>$next?gmdate('c',$next):null];}

    public static function update_schedule(string $schedule,int $retention):array {
        $normalized=self::normalize_schedule($schedule,$retention);$lock=self::lock(self::LOCK_OPTION);
        try{
            $settings=Xsofty_MCP_Auth::settings();$previous=self::normalize_schedule((string)($settings['backup_schedule']??'disabled'),(int)($settings['backup_retention']??5));$old=self::scheduled_event();$new_timestamp=0;$old_removed=false;
            $event_mismatch=$normalized['schedule']!=='disabled'&&($old===null||$old['schedule']!==$normalized['schedule']);$same=$normalized['schedule']===$previous['schedule']&&!$event_mismatch&&(($normalized['schedule']==='disabled'&&$old===null)||($normalized['schedule']!=='disabled'&&$old!==null));
            if(!$same&&$normalized['schedule']!=='disabled'){$new_timestamp=time()+600;if($old!==null&&$new_timestamp===$old['timestamp'])$new_timestamp++;if(!wp_schedule_event($new_timestamp,$normalized['schedule'],self::SCHEDULE_HOOK))throw new RuntimeException('Unable to schedule the replacement backup event; the previous schedule remains active.');}
            if(!$same&&$old!==null){$removed=wp_unschedule_event($old['timestamp'],self::SCHEDULE_HOOK,$old['args'],true);if(is_wp_error($removed)||$removed===false){$cleaned=$new_timestamp?wp_unschedule_event($new_timestamp,self::SCHEDULE_HOOK,[],true):true;if(is_wp_error($cleaned)||$cleaned===false)throw new RuntimeException('Unable to roll back the replacement backup event. Manual cron repair is required.');throw new RuntimeException('Unable to replace the previous backup event; settings were not changed.');}$old_removed=true;}
            try{Xsofty_MCP_Auth::update_settings(function(array $fresh)use($normalized):array {$fresh['backup_schedule']=$normalized['schedule'];$fresh['backup_retention']=$normalized['retention'];return $fresh;});}
            catch(Throwable $error){$new_removed=true;if($new_timestamp){$removed=wp_unschedule_event($new_timestamp,self::SCHEDULE_HOOK,[],true);$new_removed=!is_wp_error($removed)&&$removed!==false;}$old_restored=true;if($old_removed&&$old!==null&&$old['schedule']!=='')$old_restored=(bool)wp_schedule_event($old['timestamp'],$old['schedule'],self::SCHEDULE_HOOK,$old['args']);if(!$new_removed||!$old_restored)throw new RuntimeException('Unable to restore the previous backup schedule after a settings failure. Manual cron repair is required.',0,$error);throw $error;}
            return self::schedule_settings();
        }finally{self::unlock(self::LOCK_OPTION,$lock);}
    }

    public static function start_backup(string $label='manual'):array {self::recover_stale_jobs();$id=bin2hex(random_bytes(12));$token=Xsofty_MCP_Auth::active_token_id();if($token==='')throw new RuntimeException('An authenticated named token is required.');$job=['id'=>$id,'type'=>'backup','status'=>'pending','progress'=>0,'message'=>'Waiting for WP-Cron.','label'=>substr(sanitize_text_field($label),0,80),'token_id'=>$token,'created_at'=>gmdate('c'),'started_at'=>null,'finished_at'=>null,'result'=>null];self::mutate(function(array &$records)use($id,$job){$active=count(array_filter($records,static fn($item)=>in_array((string)($item['status']??''),['pending','running'],true)));if($active>=10)throw new RuntimeException('Too many active backup jobs.');$records[$id]=$job;});if(!wp_schedule_single_event(time()+1,self::JOB_HOOK,[$id])){self::mutate(function(array &$records)use($id){unset($records[$id]);});throw new RuntimeException('Unable to schedule backup job.');}return self::public_job($job);}

    public static function get_job(string $id):array {self::recover_stale_jobs();$id=sanitize_key($id);$job=self::records()[$id]??null;if(!$job||!self::visible($job))throw new RuntimeException('Job not found.');return self::public_job($job);}
    public static function list_jobs(int $limit=20):array {self::recover_stale_jobs();$limit=max(1,min(50,$limit));$items=[];foreach(self::records() as $job)if(self::visible($job))$items[]=self::public_job($job);$items=array_slice($items,0,$limit);return ['items'=>$items,'count'=>count($items)];}

    public static function cancel(string $id):array {self::recover_stale_jobs();$id=sanitize_key($id);$job=self::records()[$id]??null;if(!$job||!self::visible($job))throw new RuntimeException('Job not found.');$cancelled=self::mutate(function(array &$records)use($id){if(!isset($records[$id])||$records[$id]['status']!=='pending')throw new RuntimeException('Job is no longer pending.');$records[$id]['status']='cancelled';$records[$id]['progress']=0;$records[$id]['message']='Cancelled.';$records[$id]['finished_at']=gmdate('c');return self::public_job($records[$id]);});$next=wp_next_scheduled(self::JOB_HOOK,[$id]);if($next!==false)wp_unschedule_event((int)$next,self::JOB_HOOK,[$id]);return $cancelled;}

    private static function update_job(string $id,array $changes):void {self::mutate(function(array &$records)use($id,$changes){if(!isset($records[$id]))return;foreach($changes as $key=>$value){if($value===null)unset($records[$id][$key]);else $records[$id][$key]=$value;}});}

    public static function run_backup_job(string $id):void {
        $id=sanitize_key($id);$job=self::records()[$id]??null;if(!$job||($job['status']??'')!=='pending')return;
        $claimed=self::mutate(function(array &$records)use($id){if(!isset($records[$id])||($records[$id]['status']??'')!=='pending')return null;$records[$id]['status']='running';$records[$id]['progress']=10;$records[$id]['message']='Creating private backup.';$records[$id]['started_at']=gmdate('c');$records[$id]['heartbeat_at']=time();$records[$id]['lease_expires_at']=time()+self::RUN_LEASE;return $records[$id];});if(!$claimed)return;
        try{$result=Xsofty_MCP_Backups::create((string)($claimed['label']??'job'));self::update_job($id,['status'=>'completed','progress'=>100,'message'=>'Backup completed.','finished_at'=>gmdate('c'),'result'=>$result,'lease_expires_at'=>null,'heartbeat_at'=>null]);}
        catch(Throwable $e){
            if($e->getMessage()==='Another backup creation is already in progress.'){$queued=wp_schedule_single_event(time()+60,self::JOB_HOOK,[$id]);if($queued){self::update_job($id,['status'=>'pending','progress'=>0,'message'=>'Waiting for another backup job.','started_at'=>null,'lease_expires_at'=>null,'heartbeat_at'=>null]);return;}$message='Unable to queue a backup retry.';}
            else $message=substr(sanitize_text_field($e->getMessage()),0,180);
            self::update_job($id,['status'=>'failed','progress'=>100,'message'=>$message,'finished_at'=>gmdate('c'),'lease_expires_at'=>null,'heartbeat_at'=>null]);
        }
    }

    public static function run_scheduled_backup():void {try{Xsofty_MCP_Backups::create('scheduled');self::prune_scheduled();}catch(Throwable $e){Xsofty_MCP_Auth::audit('scheduled_backup',false,['error'=>substr($e->getMessage(),0,160)]);}}

    public static function prune_scheduled():array {$retention=self::schedule_settings()['retention'];$scheduled=array_values(array_filter(Xsofty_MCP_Backups::list(),static fn($item)=>str_ends_with((string)$item['filename'],'-scheduled.zip')));$deleted=[];foreach(array_slice($scheduled,$retention) as $item)if(Xsofty_MCP_Backups::delete($item['filename']))$deleted[]=$item['filename'];return ['retention'=>$retention,'deleted'=>count($deleted)];}
}
