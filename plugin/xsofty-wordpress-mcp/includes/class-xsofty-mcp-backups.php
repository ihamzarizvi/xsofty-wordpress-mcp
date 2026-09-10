<?php
if (!defined('ABSPATH')) exit;

final class Xsofty_MCP_Backups {
    private static function canonical_private_path(string $configured):string {
        $path=untrailingslashit(wp_normalize_path($configured));
        if($path===''||$path==='/'||!preg_match('#^(?:[A-Za-z]:/|/)#',$path)||preg_match('#(?:^|/)\.\.(?:/|$)#',$path))throw new RuntimeException('XSOFTY_MCP_PRIVATE_DIR must be an absolute safe path.');
        $probe=$path;$missing=[];while(!file_exists($probe)){array_unshift($missing,basename($probe));$parent=dirname($probe);if($parent===$probe)throw new RuntimeException('Unable to resolve the private storage parent.');$probe=$parent;}
        $cursor=preg_match('#^[A-Za-z]:/#',$probe)?substr($probe,0,3):'/';foreach(array_filter(explode('/',ltrim(substr($probe,strlen($cursor)),'/')),'strlen') as $segment){$cursor=untrailingslashit($cursor).'/'.$segment;if(is_link($cursor))throw new RuntimeException('Private storage paths cannot contain symlinks.');}
        $resolved=realpath($probe);if($resolved===false||is_link($probe))throw new RuntimeException('Unable to canonicalize private storage.');$canonical=untrailingslashit(wp_normalize_path($resolved));foreach($missing as $segment)$canonical.='/'.$segment;
        $public_roots=[];foreach([$_SERVER['DOCUMENT_ROOT']??'',ABSPATH,defined('WP_CONTENT_DIR')?WP_CONTENT_DIR:''] as $candidate){$real=$candidate!==''?realpath($candidate):false;if($real!==false)$public_roots[]=trailingslashit(wp_normalize_path($real));}
        foreach(array_unique($public_roots) as $public)if(str_starts_with($canonical.'/',$public))throw new RuntimeException('XSOFTY_MCP_PRIVATE_DIR must be outside every public WordPress root.');
        return $canonical;
    }

    public static function private_root(): string {
        $public=realpath($_SERVER['DOCUMENT_ROOT']??ABSPATH);if($public===false)$public=realpath(ABSPATH);if($public===false)throw new RuntimeException('Unable to resolve the WordPress public root.');
        $configured=defined('XSOFTY_MCP_PRIVATE_DIR')?(string)XSOFTY_MCP_PRIVATE_DIR:dirname(wp_normalize_path($public)).'/xsofty-mcp-private';
        return self::canonical_private_path($configured);
    }

    private static function namespace(): string {
        return 'site-' . get_current_blog_id() . '-' . substr(hash_hmac('sha256', home_url(), wp_salt('auth')), 0, 12);
    }

    public static function directory(): string {
        return self::private_root() . '/backups/' . self::namespace();
    }

    public static function snapshots_directory(): string {
        return self::private_root() . '/snapshots/' . self::namespace();
    }

    public static function protect_directory(): void {
        $root=self::private_root();
        foreach ([$root, $root.'/backups', $root.'/snapshots', self::directory(), self::snapshots_directory()] as $dir) {
            if(is_link($dir))throw new RuntimeException('Private MCP storage cannot use symlinks.');
            if (!is_dir($dir) && !wp_mkdir_p($dir)) throw new RuntimeException('Unable to create the private MCP storage directory.');
            $real=realpath($dir);if($real===false||wp_normalize_path($real)!==wp_normalize_path($dir)||is_link($dir))throw new RuntimeException('Private MCP storage failed canonical validation.');
            if(!chmod($dir,0700)){throw new RuntimeException('Unable to secure private MCP storage permissions.');}clearstatcache(true,$dir);
            if (!is_writable($dir)||(fileperms($dir)&0777)!==0700) throw new RuntimeException('Private MCP storage permissions are not 0700.');
        }
        self::migrate_legacy_backups();
    }

    private static function migrate_legacy_backups(): void {
        $legacy = WP_CONTENT_DIR . '/xsofty-mcp-backups';
        if (!is_dir($legacy) || wp_normalize_path($legacy) === wp_normalize_path(self::directory())) return;
        foreach ((array) glob($legacy . '/xsofty-backup-*.zip') as $file) {
            $target = self::directory() . '/' . basename($file);
            if(is_link($file))continue;if(!file_exists($target)&&@rename($file,$target)){if(!chmod($target,0600)||(fileperms($target)&0777)!==0600)throw new RuntimeException('Unable to secure migrated backup permissions.');}
        }
    }

    private static function excluded(string $path): bool {
        $path = wp_normalize_path($path);
        foreach ([wp_normalize_path(self::private_root()), wp_normalize_path(WP_CONTENT_DIR.'/cache'), wp_normalize_path(WP_CONTENT_DIR.'/ai1wm-backups'), '/.git/', '/node_modules/', '/.DS_Store'] as $part) if (str_contains($path, $part)) return true;
        return false;
    }

    private static function write($handle, string $data): void {
        $length = strlen($data);
        if (fwrite($handle, $data) !== $length) throw new RuntimeException('Database export write failed.');
    }

    private static function sql_value($value): string {
        global $wpdb;
        return $value === null ? 'NULL' : "'" . $wpdb->_real_escape((string) $value) . "'";
    }

    private static function table_allowlist(): array {
        if (is_multisite()) throw new RuntimeException('Backups are disabled on multisite until a network-scoped backup policy is configured.');
        global $wpdb;
        $rows = $wpdb->get_results('SHOW FULL TABLES', ARRAY_N);
        if ($wpdb->last_error) throw new RuntimeException('Unable to enumerate database tables.');
        $tables = [];
        foreach ($rows as $row) {
            if (isset($row[0], $row[1]) && strtoupper((string)$row[1]) === 'BASE TABLE' && str_starts_with((string)$row[0], (string)$wpdb->prefix)) $tables[] = (string)$row[0];
        }
        return array_values(array_unique($tables));
    }

    private static function export_database(string $target): array {
        global $wpdb;
        $handle = fopen($target, 'xb');
        if (!$handle) throw new RuntimeException('Unable to create the database export.');
        $exported = 0;$transaction=false;
        try {
            if($wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')===false||$wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT')===false)throw new RuntimeException('Unable to begin a consistent database snapshot.');$transaction=true;
            self::write($handle, "-- Xsofty WordPress MCP database export\n-- Generated " . gmdate('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSTART TRANSACTION WITH CONSISTENT SNAPSHOT;\n\n");
            $available = [];
            $rows = $wpdb->get_results('SHOW FULL TABLES', ARRAY_N);
            if ($wpdb->last_error) throw new RuntimeException('Unable to enumerate database tables.');
            foreach ($rows as $row) if (isset($row[0], $row[1]) && strtoupper((string)$row[1]) === 'BASE TABLE') $available[(string)$row[0]] = true;
            foreach (self::table_allowlist() as $table) {
                if (empty($available[$table])) continue;
                $safe_table = str_replace('`', '``', $table);
                $create = $wpdb->get_row("SHOW CREATE TABLE `{$safe_table}`", ARRAY_N);
                if ($wpdb->last_error || !$create || !isset($create[1])) throw new RuntimeException('Unable to read schema for an approved WordPress table.');
                self::write($handle, "DROP TABLE IF EXISTS `{$safe_table}`;\n{$create[1]};\n\n");
                $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$safe_table}`", ARRAY_A);
                if ($wpdb->last_error || !$columns) throw new RuntimeException('Unable to read table columns.');
                $insert_columns = array_values(array_filter($columns, static fn($column) => !str_contains(strtoupper((string)($column['Extra']??'')), 'GENERATED')));
                $names = array_column($insert_columns, 'Field');
                if (!$names) { $exported++; continue; }
                $primary = $wpdb->get_results("SHOW INDEX FROM `{$safe_table}` WHERE Key_name='PRIMARY'", ARRAY_A);
                if($primary)usort($primary,static fn($a,$b)=>(int)($a['Seq_in_index']??0)<=>(int)($b['Seq_in_index']??0));
                $order_columns=$primary?array_map(static fn($index)=>(string)$index['Column_name'],$primary):[];
                $order = $order_columns ? ' ORDER BY ' . implode(',', array_map(static fn($column) => '`'.str_replace('`','``',(string)$column).'`', $order_columns)) : '';
                $offset = 0;
                do {
                    $selected = implode(',', array_map(static fn($name) => '`'.str_replace('`','``',$name).'`', $names));
                    $batch = $wpdb->get_results("SELECT {$selected} FROM `{$safe_table}`{$order} LIMIT 500 OFFSET {$offset}", ARRAY_A);
                    if ($wpdb->last_error) throw new RuntimeException('Database row export failed.');
                    foreach ($batch as $row) {
                        $quoted_columns = implode(',', array_map(static fn($column) => '`'.str_replace('`','``',$column).'`', array_keys($row)));
                        $values = implode(',', array_map([self::class,'sql_value'], array_values($row)));
                        self::write($handle, "INSERT INTO `{$safe_table}` ({$quoted_columns}) VALUES ({$values});\n");
                    }
                    $offset += 500;
                } while (count($batch) === 500);
                self::write($handle, "\n");
                $exported++;
            }
            self::write($handle, "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");
            if($wpdb->query('COMMIT')===false)throw new RuntimeException('Unable to complete the consistent database snapshot.');$transaction=false;
        } catch (Throwable $error) {
            if($transaction)$wpdb->query('ROLLBACK');
            fclose($handle); @unlink($target); throw $error;
        }
        if (!fflush($handle)) { fclose($handle); @unlink($target); throw new RuntimeException('Database export flush failed.'); }
        fclose($handle);
        return ['tables'=>$exported,'bytes'=>filesize($target)?:0];
    }

    private static function remove_tree(string $path):void {
        for($attempt=0;$attempt<10;$attempt++){
            if(is_link($path)||is_file($path)){@unlink($path);if(!file_exists($path)&&!is_link($path))return;}
            elseif(!is_dir($path))return;
            else{foreach(new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $item)self::remove_tree($item->getPathname());if(@rmdir($path))return;}
            clearstatcache(true,$path);usleep(50000);
        }
    }

    private static function remove_file(string $path):bool {for($attempt=0;$attempt<10;$attempt++){if(!file_exists($path)&&!is_link($path))return true;@unlink($path);clearstatcache(true,$path);if(!file_exists($path)&&!is_link($path))return true;usleep(50000);}return false;}

    public static function snapshot_file(string $source,string $target):void {$before=lstat($source);if($before===false||is_link($source)||(($before['mode']&0170000)!==0100000))throw new RuntimeException('Backup source changed or is not a regular file.');if(!is_dir(dirname($target))&&!wp_mkdir_p(dirname($target)))throw new RuntimeException('Unable to create backup staging directory.');$in=fopen($source,'rb');if(!$in)throw new RuntimeException('Unable to open backup source.');$opened=fstat($in);if($opened===false||$opened['dev']!==$before['dev']||$opened['ino']!==$before['ino']){fclose($in);throw new RuntimeException('Backup source changed while opening.');}$out=fopen($target,'xb');if(!$out){fclose($in);throw new RuntimeException('Unable to stage backup source.');}try{if(stream_copy_to_stream($in,$out)===false||!fflush($out))throw new RuntimeException('Unable to snapshot backup source.');$after=fstat($in);$path_after=lstat($source);if($after===false||$path_after===false||is_link($source)||$after['dev']!==$before['dev']||$after['ino']!==$before['ino']||$path_after['dev']!==$before['dev']||$path_after['ino']!==$before['ino']||$after['size']!==$before['size']||$after['mtime']!==$before['mtime'])throw new RuntimeException('Backup source changed during snapshot.');}finally{fclose($in);fclose($out);}if(!chmod($target,0600)||(fileperms($target)&0777)!==0600){@unlink($target);throw new RuntimeException('Unable to secure staged backup source.');}}

    private static function add_tree(ZipArchive $zip, string $root, string $prefix,string $stage, int &$files, int &$source_bytes, array &$skipped): void {
        $root = trailingslashit(wp_normalize_path((string) realpath($root)));
        if ($root === '/' || !is_dir($root)) throw new RuntimeException('Invalid backup source root.');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $path = wp_normalize_path($item->getPathname());
            if (self::excluded($path)) continue;
            $relative = ltrim(substr($path, strlen($root)), '/');
            if ($relative === '') continue;
            $archive = ltrim($prefix . '/' . $relative, '/');
            if ($item->isLink()) { $skipped[] = $archive . ' (symlink)'; continue; }
            if ($item->isDir()) { if (!$zip->addEmptyDir($archive)) throw new RuntimeException('Unable to add a directory to the backup.'); continue; }
            if (!$item->isReadable()) { $skipped[] = $archive; continue; }
            $size=(int)$item->getSize(); $limit=defined('XSOFTY_MCP_MAX_BACKUP_SOURCE_BYTES')?(int)XSOFTY_MCP_MAX_BACKUP_SOURCE_BYTES:5*GB_IN_BYTES;
            if($size>GB_IN_BYTES||$source_bytes+$size>$limit)throw new RuntimeException('Backup source exceeds the configured size limit.');
            $snapshot=$stage.'/'.$archive;self::snapshot_file($path,$snapshot);if(!$zip->addFile($snapshot,$archive))throw new RuntimeException('Unable to add a file to the backup.');
            $files++; $source_bytes+=$size;
        }
    }

    private static int $creation_lock_depth=0;

    private static function with_creation_lock(callable $callback){
        if(self::$creation_lock_depth>0)return $callback();
        global $wpdb;$name='xwmcp_backup_'.substr(hash_hmac('sha256',home_url(),wp_salt('auth')),0,32);$mysql=is_object($wpdb)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var');$owner='';
        if($mysql){if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$name))!==1)throw new RuntimeException('Another backup creation is already in progress.');}
        else{$owner=bin2hex(random_bytes(16));if(!add_option($name,['owner'=>$owner,'expires'=>time()+DAY_IN_SECONDS],'',false))throw new RuntimeException('Another backup creation is already in progress.');}
        self::$creation_lock_depth++;
        try{return $callback();}finally{self::$creation_lock_depth--;if($mysql)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));else{$held=get_option($name,[]);if(is_array($held)&&hash_equals($owner,(string)($held['owner']??'')))delete_option($name);}}
    }

    public static function create(string $label = ''): array { return self::with_creation_lock(static fn()=>self::create_unlocked($label)); }

    private static function create_unlocked(string $label = ''): array {
        if (!class_exists('ZipArchive')) throw new RuntimeException('The PHP Zip extension is required for backups.');
        if (function_exists('set_time_limit')) @set_time_limit(0);
        self::protect_directory();
        $safe_label = $label !== '' ? '-' . sanitize_title($label) : '';
        $filename = 'xsofty-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . $safe_label . '.zip';
        $destination = self::directory() . '/' . $filename;
        $temporary = $destination . '.part';
        $sql = self::directory() . '/database-' . wp_generate_uuid4() . '.sql';
        $stage=self::directory().'/.source-'.wp_generate_uuid4();if(!mkdir($stage,0700)||is_link($stage)||(fileperms($stage)&0777)!==0700)throw new RuntimeException('Unable to create secure backup staging.');
        try{$db=self::export_database($sql);}catch(Throwable $error){self::remove_tree($stage);throw $error;}
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) { @unlink($sql);self::remove_tree($stage);throw new RuntimeException('Unable to create temporary backup archive.'); }
        $files = 0; $source_bytes = 0; $skipped = [];
        try {
            if (!$zip->addFile($sql, 'database.sql')) throw new RuntimeException('Unable to add database.sql to the backup.');
            self::add_tree($zip, ABSPATH, 'wordpress',$stage, $files, $source_bytes, $skipped);
            $content = wp_normalize_path((string) realpath(WP_CONTENT_DIR));
            $root = trailingslashit(wp_normalize_path((string) realpath(ABSPATH)));
            if ($content && !str_starts_with($content.'/', $root)) self::add_tree($zip, $content, 'external/wp-content',$stage, $files, $source_bytes, $skipped);
            $config = file_exists(ABSPATH.'wp-config.php') ? ABSPATH.'wp-config.php' : dirname(untrailingslashit(ABSPATH)).'/wp-config.php';
            if(is_file($config)&&!str_starts_with(wp_normalize_path($config),$root)){self::snapshot_file($config,$stage.'/wordpress/wp-config.php');if(!$zip->addFile($stage.'/wordpress/wp-config.php','wordpress/wp-config.php'))throw new RuntimeException('Unable to add wp-config.php to the backup.');}
            $manifest = ['format'=>2,'plugin'=>'Xsofty WordPress MCP Bridge','plugin_version'=>XSOFTY_WP_MCP_VERSION,'created_at'=>gmdate('c'),'site_url'=>home_url(),'wordpress_version'=>get_bloginfo('version'),'php_version'=>PHP_VERSION,'database_tables'=>$db['tables'],'files'=>$files,'source_bytes'=>$source_bytes,'skipped_files'=>$skipped,'source_paths'=>['wordpress_root'=>wp_normalize_path(ABSPATH),'content_root'=>wp_normalize_path(WP_CONTENT_DIR)]];
            if (!$zip->addFromString('xsofty-backup-manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))) throw new RuntimeException('Unable to add backup manifest.');
            if (!$zip->close()) throw new RuntimeException('Unable to finalize backup archive.');
        } catch (Throwable $error) {
            $zip->close();self::remove_file($temporary);$sql_clean=self::remove_file($sql);self::remove_tree($stage);if(!$sql_clean)throw new RuntimeException('Unable to remove the temporary database export after backup failure.',0,$error);throw $error;
        }
        $sql_removed=self::remove_file($sql);self::remove_tree($stage);if(!$sql_removed){self::remove_file($temporary);throw new RuntimeException('Unable to remove the temporary database export; the archive was not published.');}if(file_exists($stage)||is_link($stage)){self::remove_file($temporary);throw new RuntimeException('Unable to remove private backup staging; the archive was not published.');}
        $check = new ZipArchive();
        if ($check->open($temporary, ZipArchive::CHECKCONS) !== true || $check->locateName('database.sql') === false || $check->locateName('xsofty-backup-manifest.json') === false) { if ($check->status === ZipArchive::ER_OK) $check->close(); @unlink($temporary); throw new RuntimeException('Backup archive integrity verification failed.'); }
        $check->close();
        if (!rename($temporary, $destination)) { @unlink($temporary); throw new RuntimeException('Unable to publish the verified backup archive.'); }
        if(!chmod($destination,0600)){@unlink($destination);throw new RuntimeException('Unable to secure backup archive permissions.');}clearstatcache(true,$destination);if((fileperms($destination)&0777)!==0600){@unlink($destination);throw new RuntimeException('Backup archive permissions are not 0600.');}
        return ['filename'=>$filename,'bytes'=>filesize($destination)?:0,'sha256'=>hash_file('sha256',$destination),'database_tables'=>$db['tables'],'files'=>$files,'skipped_files'=>count($skipped),'created_at'=>gmdate('c')];
    }

    public static function list(): array {
        self::protect_directory(); $items=[];
        foreach ((array) glob(self::directory().'/xsofty-backup-*.zip') as $file) if (is_file($file)&&!is_link($file)) $items[]=['filename'=>basename($file),'bytes'=>filesize($file)?:0,'sha256'=>hash_file('sha256',$file),'created_at'=>gmdate('c',filemtime($file))];
        usort($items, static fn($a,$b)=>strcmp($b['created_at'],$a['created_at'])); return array_slice($items,0,200);
    }

    public static function path(string $filename): string {
        $safe=basename($filename); if($safe!==$filename||!preg_match('/^xsofty-backup-[a-zA-Z0-9._-]+\.zip$/',$safe))throw new RuntimeException('Invalid backup filename.');
        $directory=realpath(self::directory());$path=self::directory().'/'.$safe;if($directory===false||is_link($path)||!is_file($path))throw new RuntimeException('Backup not found.');$resolved=realpath($path);if($resolved===false||dirname(wp_normalize_path($resolved))!==wp_normalize_path($directory))throw new RuntimeException('Backup path failed canonical validation.');return $resolved;
    }

    public static function open_download(string $filename):array {
        $path=self::path($filename);$before=lstat($path);if($before===false||is_link($path)||(($before['mode']&0170000)!==0100000))throw new RuntimeException('Backup is not a regular file.');
        $handle=fopen($path,'rb');if($handle===false)throw new RuntimeException('Backup is unreadable.');
        $opened=fstat($handle);$current=lstat($path);if($opened===false||$current===false||is_link($path)||$opened['dev']!==$before['dev']||$opened['ino']!==$before['ino']||$current['dev']!==$before['dev']||$current['ino']!==$before['ino']||(($opened['mode']&0170000)!==0100000)){fclose($handle);throw new RuntimeException('Backup changed while opening.');}
        return ['handle'=>$handle,'bytes'=>(int)$opened['size']];
    }

    public static function delete(string $filename): bool { return unlink(self::path($filename)); }

    public static function signed_download_url(string $filename, int $ttl=600): string {
        self::path($filename); $token_id=Xsofty_MCP_Auth::active_token_id(); $key=Xsofty_MCP_Auth::download_key();
        if($token_id===''||$key==='')throw new RuntimeException('An authenticated named token is required to generate download links.');
        $expires=time()+max(60,min(3600,$ttl)); $payload=get_current_blog_id().'|'.$token_id.'|'.$filename.'|'.$expires; $signature=hash_hmac('sha256',$payload,$key);
        return add_query_arg(['file'=>$filename,'token'=>$token_id,'expires'=>$expires,'signature'=>$signature],rest_url('xsofty-mcp/v1/download'));
    }

    public static function verify_download(string $filename,int $expires,string $signature,string $token_id=''):bool {
        $token_id=sanitize_key($token_id); $records=Xsofty_MCP_Auth::token_records(); $record=$records[$token_id]??null;
        $actor=(int)($record['actor_user_id']??0);if(!$record||empty($record['enabled'])||empty($record['download_key'])||!in_array('backups',(array)($record['scopes']??[]),true)||!Xsofty_MCP_Auth::record_allows_tool($record,'get_backup_download_url')||!$actor||!get_user_by('id',$actor)||!user_can($actor,'manage_options')||(!empty($record['expires_at'])&&time()>=(int)$record['expires_at'])||$expires<time()||$expires>time()+3605)return false;
        $expected=hash_hmac('sha256',get_current_blog_id().'|'.$token_id.'|'.$filename.'|'.$expires,(string)$record['download_key']); return hash_equals($expected,$signature);
    }
}
