<?php
if (!defined('ABSPATH')) exit;

final class Xsofty_MCP_Auth {
    public const OPTION_KEY = 'xsofty_mcp_settings';
    public const AUDIT_KEY = 'xsofty_mcp_audit_log';
    public const USAGE_KEY = 'xsofty_mcp_token_usage';
    public const RATE_LIMIT_OPTION = 'xsofty_mcp_rate_limits';
    private const MAX_TOKENS = 20;
    private static ?array $active_token = null;
    private static string $active_token_id = '';
    private static ?int $previous_user_id = null;
    private static int $policy_lock_depth = 0;
    private const POLICY_FIELDS = ['tokens','token_hash','token_hint','actor_user_id','download_key'];

    public static function clear_context(): void {
        self::$active_token=null;
        self::$active_token_id='';
    }

    public static function begin_request(): void {
        if(self::$previous_user_id!==null)wp_set_current_user(self::$previous_user_id);
        self::$previous_user_id=null;
        self::clear_context();
    }

    public static function end_request(): void {
        if(self::$previous_user_id!==null)wp_set_current_user(self::$previous_user_id);
        self::$previous_user_id=null;
        self::clear_context();
    }

    public static function valid_scopes(): array {
        return ['site_read','content_read','content_write','media','plugins','theme_read','theme_write','settings','backups','maintenance','audit'];
    }

    public static function defaults(): array {
        return [
            'enabled' => true,
            'tokens' => [],
            // Legacy single-token fields remain readable only for a safe in-place v0.2 upgrade.
            'token_hash' => '',
            'token_hint' => '',
            'actor_user_id' => 0,
            'download_key' => '',
            'allow_http' => false,
            'allow_cron_run' => false,
            'backup_schedule' => 'disabled',
            'backup_retention' => 5,
            'allowed_origins' => [],
            'rate_limit' => 120,
            'scopes' => ['site_read', 'content_read'],
            'disabled_tools' => [],
        ];
    }

    public static function settings(): array {
        return wp_parse_args((array) get_option(self::OPTION_KEY, []), self::defaults());
    }

    private static function persist_settings(array $settings): void { update_option(self::OPTION_KEY,wp_parse_args($settings,self::defaults()),false); }

    public static function save(array $settings): void {
        if(self::$policy_lock_depth>0){self::persist_settings($settings);return;}
        self::update_settings(function(array $fresh)use($settings):array {foreach($settings as $key=>$value)if(!in_array((string)$key,self::POLICY_FIELDS,true))$fresh[$key]=$value;return $fresh;});
    }

    public static function update_settings(callable $callback): array {
        return self::with_policy_lock(function()use($callback):array {$fresh=self::settings();$updated=$callback($fresh);if(!is_array($updated))throw new RuntimeException('Settings mutation must return an array.');self::persist_settings($updated);return self::settings();});
    }

    private static function administrator_id(): int {
        $current = get_current_user_id();
        if ($current && user_can($current, 'manage_options')) return $current;
        $admins = get_users(['role'=>'administrator','number'=>1,'fields'=>'ID','orderby'=>'ID','order'=>'ASC']);
        return isset($admins[0]) ? (int) $admins[0] : 0;
    }

    private static function clean_scopes(array $scopes): array {
        return array_values(array_unique(array_intersect(self::valid_scopes(), array_map('sanitize_key', $scopes))));
    }

    private static function clean_tools(array $tools): array {
        $clean = [];
        foreach ($tools as $tool) {
            $tool = sanitize_key((string) $tool);
            if ($tool !== '' && !in_array($tool, $clean, true)) $clean[] = $tool;
        }
        return array_slice($clean, 0, 100);
    }

    private static function with_policy_lock(callable $callback) {
        if(self::$policy_lock_depth>0)return $callback();
        global $wpdb;
        $name='xsofty_mcp_policy_'.substr(hash_hmac('sha256',home_url(),wp_salt('auth')),0,32);
        $mysql=is_object($wpdb)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var');
        $owner='';
        if($mysql){
            $acquired=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$name));
            if($acquired!==1)throw new RuntimeException('Unable to acquire the MCP token policy lock.');
        }else{
            $owner=bin2hex(random_bytes(16));$deadline=microtime(true)+3;
            do{
                if(add_option($name,['owner'=>$owner,'expires'=>time()+5],'',false))break;
                $existing=get_option($name,[]);if(is_array($existing)&&((int)($existing['expires']??0))<time())delete_option($name);
                usleep(50000);
            }while(microtime(true)<$deadline);
            $held=get_option($name,[]);if(!is_array($held)||!hash_equals($owner,(string)($held['owner']??'')))throw new RuntimeException('Unable to acquire the MCP token policy lock.');
        }
        self::$policy_lock_depth++;
        try{return $callback();}
        finally{
            self::$policy_lock_depth--;
            if($mysql)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));
            else{$held=get_option($name,[]);if(is_array($held)&&hash_equals($owner,(string)($held['owner']??'')))delete_option($name);}
        }
    }

    public static function token_records(): array {
        $settings = self::settings();
        $usage = (array) get_option(self::USAGE_KEY, []);
        $records = [];
        foreach ((array) ($settings['tokens'] ?? []) as $id => $record) {
            $id = sanitize_key((string) $id);
            if ($id === '' || !is_array($record) || empty($record['token_hash'])) continue;
            $record['id'] = $id;
            $record['name'] = substr(sanitize_text_field((string) ($record['name'] ?? 'Connection')), 0, 80);
            $record['actor_user_id'] = (int) ($record['actor_user_id'] ?? 0);
            $record['scopes'] = self::clean_scopes((array) ($record['scopes'] ?? []));
            $record['allowed_tools'] = self::clean_tools((array) ($record['allowed_tools'] ?? []));
            $record['expires_at'] = max(0, (int) ($record['expires_at'] ?? 0));
            if (isset($usage[$id]['last_used_at'])) $record['last_used_at'] = max(0, (int)$usage[$id]['last_used_at']);
            $record['enabled'] = !array_key_exists('enabled', $record) || (bool) $record['enabled'];
            $records[$id] = $record;
        }
        return $records;
    }

    private static function policy_records_for_storage(array $records):array {foreach($records as &$record)if(is_array($record))unset($record['last_used_at']);unset($record);return $records;}

    public static function create_token(string $name, int $user_id = 0, ?array $scopes = null, int $expires_at = 0, array $allowed_tools = []): array {
        return self::with_policy_lock(function() use($name,$user_id,$scopes,$expires_at,$allowed_tools):array {
            $actor = $user_id ?: self::administrator_id();
            if (!$actor || !user_can($actor, 'manage_options')) throw new RuntimeException('A valid administrator is required to create an MCP token.');
            $records = self::token_records();
            $settings = self::settings();
            $reserved_for_legacy = empty($settings['token_hash']) ? 0 : 1;
            if (count($records) + $reserved_for_legacy >= self::MAX_TOKENS) throw new RuntimeException('The maximum of 20 MCP tokens has been reached. Revoke an unused token first.');
            $safe_name = substr(sanitize_text_field($name), 0, 80);
            if ($safe_name === '') throw new InvalidArgumentException('A connection name is required.');
            $id = bin2hex(random_bytes(8));
            $secret = bin2hex(random_bytes(32));
            $token = 'xwmcp_' . $id . '_' . $secret;
            $record = [
                'id'=>$id,'name'=>$safe_name,'token_hash'=>password_hash($token,PASSWORD_DEFAULT),
                'token_hint'=>'xwmcp_'.substr($id,0,6).'…'.substr($secret,-4),'actor_user_id'=>$actor,
                'download_key'=>bin2hex(random_bytes(32)),'scopes'=>self::clean_scopes($scopes??(array)$settings['scopes']),
                'allowed_tools'=>self::clean_tools($allowed_tools),'expires_at'=>max(0,$expires_at),
                'created_at'=>time(),'enabled'=>true,
            ];
            $records[$id]=$record;$settings['tokens']=self::policy_records_for_storage($records);$settings['enabled']=true;self::save($settings);
            self::audit('token_generated',true,['actor'=>$actor,'token_id'=>$id,'name'=>$safe_name]);
            return ['id'=>$id,'token'=>$token,'hint'=>$record['token_hint'],'expires_at'=>$record['expires_at']];
        });
    }

    public static function generate_token(int $user_id = 0): string {
        $created = self::create_token('Default connection', $user_id);
        return $created['token'];
    }

    public static function update_token(string $id, array $changes): bool {
        $id = sanitize_key($id);
        return self::with_policy_lock(function() use($id,$changes):bool {
            $settings=self::settings();$records=self::token_records();if(!isset($records[$id]))return false;$record=$records[$id];
            if(array_key_exists('name',$changes)){$name=substr(sanitize_text_field((string)$changes['name']),0,80);if($name==='')throw new InvalidArgumentException('A connection name is required.');$record['name']=$name;}
            if(array_key_exists('scopes',$changes))$record['scopes']=self::clean_scopes((array)$changes['scopes']);
            if(array_key_exists('allowed_tools',$changes))$record['allowed_tools']=self::clean_tools((array)$changes['allowed_tools']);
            if(array_key_exists('expires_at',$changes))$record['expires_at']=max(0,(int)$changes['expires_at']);
            if(array_key_exists('enabled',$changes))$record['enabled']=(bool)$changes['enabled'];
            if(array_key_exists('rate_limit',$changes))$record['rate_limit']=max(10,min(600,(int)$changes['rate_limit']));
            $records[$id]=$record;$settings['tokens']=self::policy_records_for_storage($records);self::save($settings);
            if(self::$active_token_id===$id)self::$active_token=$record;
            self::audit('token_updated',true,['token_id'=>$id,'name'=>$record['name']]);return true;
        });
    }

    public static function revoke_token(string $id): bool {
        $id = sanitize_key($id);
        return self::with_policy_lock(function() use($id):bool {
            $settings=self::settings();$records=self::token_records();if(!isset($records[$id]))return false;unset($records[$id]);$settings['tokens']=self::policy_records_for_storage($records);self::save($settings);
            $usage=(array)get_option(self::USAGE_KEY,[]);unset($usage[$id]);update_option(self::USAGE_KEY,$usage,false);
            if(self::$active_token_id===$id)self::clear_context();self::audit('token_revoked',true,['token_id'=>$id]);return true;
        });
    }

    public static function revoke(): void {
        self::with_policy_lock(function():void {$settings=self::settings();$settings['tokens']=[];$settings['token_hash']='';$settings['token_hint']='';$settings['actor_user_id']=0;$settings['download_key']='';self::save($settings);update_option(self::USAGE_KEY,[],false);self::clear_context();self::audit('token_revoked_all',true);});
    }

    public static function migrate_legacy_token(): string {
        return self::with_policy_lock(function():string {
            $settings=self::settings();if(empty($settings['token_hash']))return '';$records=self::token_records();$id='legacy-'.substr(hash('sha256',(string)$settings['token_hash']),0,12);
            if(!isset($records[$id])){if(count($records)>=self::MAX_TOKENS)throw new RuntimeException('Unable to migrate the legacy token because the token limit is full.');$records[$id]=['id'=>$id,'name'=>'Migrated v0.2 connection','token_hash'=>(string)$settings['token_hash'],'token_hint'=>(string)$settings['token_hint'],'actor_user_id'=>(int)$settings['actor_user_id'],'download_key'=>(string)$settings['download_key'],'scopes'=>self::clean_scopes((array)$settings['scopes']),'allowed_tools'=>[],'expires_at'=>0,'created_at'=>time(),'enabled'=>true];}
            $settings['tokens']=self::policy_records_for_storage($records);$settings['token_hash']='';$settings['token_hint']='';$settings['actor_user_id']=0;$settings['download_key']='';self::save($settings);self::audit('legacy_token_migrated',true,['token_id'=>$id]);return $id;
        });
    }

    public static function token_from_request(WP_REST_Request $request): string {
        $header = trim((string) $request->get_header('authorization'));
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) return trim($matches[1]);
        return trim((string) $request->get_header('x-xsofty-mcp-key'));
    }

    private static function token_id_from_secret(string $token): string {
        return preg_match('/^xwmcp_([a-f0-9]{16})_[a-f0-9]{64}$/', $token, $matches) ? $matches[1] : '';
    }

    private static function database_limit_value(string $bucket):int {
        $records=(array)get_option(self::RATE_LIMIT_OPTION,[]);$record=$records[hash('sha256',$bucket)]??null;
        return is_array($record)&&(int)($record['expires']??0)>=time()?(int)($record['value']??0):0;
    }

    private static function increment_database_limit(string $bucket,int $ttl):int {
        $lock='xwmcp_rate_database_lock';$owner=bin2hex(random_bytes(8));$acquired=false;
        for($i=0;$i<5;$i++){if(add_option($lock,['owner'=>$owner,'expires'=>microtime(true)+2],'','no')){$acquired=true;break;}$held=get_option($lock);if(is_array($held)&&(float)($held['expires']??0)<microtime(true))delete_option($lock);usleep(20000);}
        if(!$acquired)return PHP_INT_MAX;
        try{$now=time();$key=hash('sha256',$bucket);$records=(array)get_option(self::RATE_LIMIT_OPTION,[]);foreach($records as $record_key=>$record)if(!is_array($record)||(int)($record['expires']??0)<$now)unset($records[$record_key]);$value=(int)($records[$key]['value']??0)+1;$records[$key]=['value'=>$value,'expires'=>$now+max(1,$ttl)];if(count($records)>2000){uasort($records,static fn($a,$b)=>(int)($b['expires']??0)<=>(int)($a['expires']??0));$records=array_slice($records,0,2000,true);}update_option(self::RATE_LIMIT_OPTION,$records,false);return $value;}
        finally{$held=get_option($lock);if(is_array($held)&&hash_equals($owner,(string)($held['owner']??'')))delete_option($lock);}
    }

    private static function increment_limit(string $bucket, int $ttl): int {
        if (wp_using_ext_object_cache()) {
            wp_cache_add($bucket, 0, 'xsofty_mcp', $ttl);
            $value = wp_cache_incr($bucket, 1, 'xsofty_mcp');
            if($value!==false)return (int)$value;
        }
        if(wp_using_ext_object_cache())return self::increment_database_limit($bucket,$ttl);
        $value=(int)get_transient($bucket)+1;return set_transient($bucket,$value,$ttl)?$value:PHP_INT_MAX;
    }

    private static function limit_value(string $bucket): int {
        if(wp_using_ext_object_cache()){$value=wp_cache_get($bucket,'xsofty_mcp');return max($value===false?0:(int)$value,self::database_limit_value($bucket));}
        return (int)get_transient($bucket);
    }

    private static function update_last_used(string $id, array $record): void {
        $now = time();
        if ($now - (int)($record['last_used_at'] ?? 0) < 300) return;
        self::with_policy_lock(function()use($id,$now):void {$usage=(array)get_option(self::USAGE_KEY,[]);$usage[$id]=['last_used_at'=>$now];update_option(self::USAGE_KEY,$usage,false);});
        self::$active_token['last_used_at']=$now;
    }

    private static function development_http_allowed(array $settings):bool {
        if(empty($settings['allow_http']))return false;
        $host=strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST));
        if($host==='localhost'||$host==='127.0.0.1'||$host==='::1'||str_ends_with($host,'.local'))return true;
        if(filter_var($host,FILTER_VALIDATE_IP))return filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false;
        return false;
    }

    public static function authorize(WP_REST_Request $request) {
        self::begin_request();
        $settings = self::settings();
        if (empty($settings['enabled'])) return new WP_Error('xsofty_mcp_disabled', 'The MCP bridge is disabled.', ['status'=>403]);
        $records = self::token_records();
        if (!$records && !empty($settings['token_hash'])) { self::migrate_legacy_token(); $records=self::token_records(); }
        if (!$records) return new WP_Error('xsofty_mcp_unpaired', 'No MCP connection token has been created.', ['status'=>401]);
        if (!is_ssl() && !self::development_http_allowed($settings)) return new WP_Error('xsofty_mcp_https_required', 'HTTPS is required unless HTTP development mode is explicitly enabled for a local, development or private-network site.', ['status'=>403]);

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $attempt_key = 'xwmcp_attempt_' . substr(hash('sha256', $remote), 0, 24);
        if (self::increment_limit($attempt_key, MINUTE_IN_SECONDS) > 300) return new WP_Error('xsofty_mcp_rate_limited', 'Too many authentication attempts. Retry in one minute.', ['status'=>429]);

        $token = self::token_from_request($request);
        $id = self::token_id_from_secret($token);
        $record = $id !== '' ? ($records[$id] ?? null) : null;
        // A migrated v0.2 token has no embedded id, so check only the single legacy record.
        if (!$record && preg_match('/^xwmcp_[a-f0-9]{64}$/', $token)) {
            foreach ($records as $candidate_id=>$candidate) if (str_starts_with($candidate_id,'legacy-')) { $id=$candidate_id; $record=$candidate; break; }
        }
        $credential_failure_key=$record?'xwmcp_credential_fail_'.substr(hash('sha256',$remote.'|'.$id),0,24):'';
        if($credential_failure_key!==''&&self::limit_value($credential_failure_key)>=10)return new WP_Error('xsofty_mcp_rate_limited','Too many invalid attempts for this connection. Retry in one minute.',['status'=>429]);
        if (!$record || empty($record['enabled']) || !password_verify($token, (string)$record['token_hash'])) {
            if($credential_failure_key!=='')self::increment_limit($credential_failure_key,MINUTE_IN_SECONDS);
            $failure_key = 'xwmcp_fail_' . substr(hash('sha256', $remote), 0, 24);
            if (self::increment_limit($failure_key, MINUTE_IN_SECONDS) > 30) return new WP_Error('xsofty_mcp_rate_limited', 'Too many invalid authentication attempts. Retry in one minute.', ['status'=>429]);
            $audit_key = 'xwmcp_failed_audit_' . substr(hash('sha256', $remote), 0, 16);
            if (!get_transient($audit_key)) { self::audit('authentication_failed', false); set_transient($audit_key, 1, MINUTE_IN_SECONDS); }
            return new WP_Error('xsofty_mcp_unauthorized', 'Invalid MCP credentials.', ['status'=>401]);
        }
        if (!empty($record['expires_at']) && time() >= (int)$record['expires_at']) return new WP_Error('xsofty_mcp_token_expired', 'This MCP connection token has expired.', ['status'=>401]);
        $actor = (int) $record['actor_user_id'];
        if (!$actor || !get_user_by('id', $actor) || !user_can($actor, 'manage_options')) return new WP_Error('xsofty_mcp_actor_invalid', 'The MCP service administrator is no longer valid.', ['status'=>403]);
        $limit = max(10, min(600, (int)($record['rate_limit'] ?? $settings['rate_limit'])));
        $fingerprint = 'xwmcp_ok_' . substr(hash('sha256', $id . '|' . $remote), 0, 24);
        if (self::increment_limit($fingerprint, MINUTE_IN_SECONDS) > $limit) return new WP_Error('xsofty_mcp_rate_limited', 'MCP request limit exceeded. Retry in one minute.', ['status'=>429]);
        self::$previous_user_id=get_current_user_id();
        wp_set_current_user($actor);
        self::$active_token_id=$id; self::$active_token=$record;
        self::update_last_used($id,$record);
        return true;
    }

    public static function active_token_id(): string { return self::$active_token_id; }
    public static function active_token(): ?array { return self::$active_token; }

    public static function has_scope(string $scope): bool {
        return self::$active_token !== null && in_array($scope,(array)self::$active_token['scopes'],true);
    }

    public static function tool_allowed(string $name): bool {
        if (self::$active_token === null) return false;
        $tool=sanitize_key($name); $settings=self::settings();
        if (in_array($tool,(array)($settings['disabled_tools']??[]),true)) return false;
        $allowed=(array)(self::$active_token['allowed_tools']??[]);
        return !$allowed || in_array($tool,$allowed,true);
    }

    public static function record_allows_tool(array $record,string $name):bool {$tool=sanitize_key($name);$settings=self::settings();if(in_array($tool,(array)($settings['disabled_tools']??[]),true))return false;$allowed=(array)($record['allowed_tools']??[]);return !$allowed||in_array($tool,$allowed,true);}

    public static function download_key(): string {
        if (self::$active_token && !empty(self::$active_token['download_key'])) return (string)self::$active_token['download_key'];
        return (string)(self::settings()['download_key'] ?? '');
    }

    public static function require_scope(string $scope) {
        if (self::has_scope($scope)) return true;
        return new WP_Error('xsofty_mcp_scope_denied', sprintf('The %s scope is disabled for this connection.', $scope), ['status'=>403]);
    }

    private static function clean_audit_value(string $key, $value): string {
        if(preg_match('/authorization|token|secret|password|cookie|api_?key|credential/i',$key))return '[REDACTED]';
        $text=(string)$value;
        $text=preg_replace('/xwmcp_(?:[a-f0-9]{16}_)?[a-f0-9]{64}/i','[REDACTED]',$text);
        $text=preg_replace_callback('#https?://[^\s]+#i',static function($match){$parts=wp_parse_url($match[0]);if(!is_array($parts)||empty($parts['scheme'])||empty($parts['host']))return '[URL REDACTED]';$safe=$parts['scheme'].'://'.$parts['host'];if(!empty($parts['port']))$safe.=':'.(int)$parts['port'];$safe.=$parts['path']??'';return $safe;},$text);
        $text=preg_replace('#(?<![:/A-Za-z0-9_])(?:[A-Za-z]:)?/(?!/)[^\s,;]+#','[PATH REDACTED]',$text);
        $text=preg_replace('#(?<![A-Za-z0-9_])(?:[A-Za-z]:\\\\|\\\\\\\\)[^\s,;]+#','[PATH REDACTED]',$text);
        return substr(sanitize_text_field($text),0,240);
    }

    public static function audit(string $action, bool $success, array $context = []): void {
        self::with_policy_lock(function() use($action,$success,$context):void {
            $log=(array)get_option(self::AUDIT_KEY,[]);$clean=[];
            foreach(array_slice($context,0,8,true) as $key=>$value)if(is_scalar($value)||$value===null){$safe_key=sanitize_key((string)$key);$clean[$safe_key]=self::clean_audit_value($safe_key,$value);}
            $remote=(string)($_SERVER['REMOTE_ADDR']??'cli');
            $subject=isset($context['subject_token_id'])?sanitize_key((string)$context['subject_token_id']):(isset($context['token_id'])?sanitize_key((string)$context['token_id']):'');
            $log[]=['time'=>gmdate('c'),'action'=>sanitize_key($action),'success'=>$success,'site'=>(int)get_current_blog_id(),'actor'=>(int)get_current_user_id(),'token_id'=>self::$active_token_id,'subject_token_id'=>$subject,'remote'=>substr(hash_hmac('sha256',$remote,wp_salt('auth')),0,16),'context'=>$clean];
            if(count($log)>500)$log=array_slice($log,-500);update_option(self::AUDIT_KEY,$log,false);
        });
    }
}
