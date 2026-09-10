<?php
if (!defined('ABSPATH')) exit;

final class Xsofty_MCP_SEO {
    public const REDIRECT_OPTION='xsofty_mcp_redirects';
    public const REDIRECT_LOCK='xsofty_mcp_redirect_lock';

    private static function mutate_redirects(callable $callback){$token=bin2hex(random_bytes(8));$value=['token'=>$token,'expires'=>time()+30];if(!add_option(self::REDIRECT_LOCK,$value,'','no')){$old=get_option(self::REDIRECT_LOCK);if(!is_array($old)||(int)($old['expires']??0)>=time())throw new RuntimeException('Another redirect update is in progress.');delete_option(self::REDIRECT_LOCK);if(!add_option(self::REDIRECT_LOCK,$value,'','no'))throw new RuntimeException('Another redirect update is in progress.');}try{$items=(array)get_option(self::REDIRECT_OPTION,[]);$result=$callback($items);update_option(self::REDIRECT_OPTION,$items,false);return $result;}finally{$held=get_option(self::REDIRECT_LOCK);if(is_array($held)&&hash_equals((string)($held['token']??''),$token))delete_option(self::REDIRECT_LOCK);}}

    public static function init():void {add_action('template_redirect',[self::class,'maybe_redirect'],0);}

    public static function adapter():string {
        if(defined('WPSEO_VERSION')||class_exists('WPSEO_Options'))return 'yoast';
        if(defined('RANK_MATH_VERSION')||class_exists('RankMath'))return 'rank_math';
        return 'none';
    }

    private static function fields(string $adapter):array {
        if($adapter==='yoast')return ['title'=>'_yoast_wpseo_title','description'=>'_yoast_wpseo_metadesc','canonical'=>'_yoast_wpseo_canonical','noindex'=>'_yoast_wpseo_meta-robots-noindex'];
        if($adapter==='rank_math')return ['title'=>'rank_math_title','description'=>'rank_math_description','canonical'=>'rank_math_canonical_url','noindex'=>'rank_math_robots'];
        return [];
    }

    public static function status():array {
        $adapter=self::adapter();return ['adapter'=>$adapter,'supported'=>$adapter!=='none','core_sitemaps'=>function_exists('wp_sitemaps_get_server')&&(bool)wp_sitemaps_get_server()->sitemaps_enabled(),'sitemap_url'=>home_url('/wp-sitemap.xml')];
    }

    private static function post(int $id){$post=get_post($id);if(!$post)throw new RuntimeException('Content record not found.');$object=get_post_type_object($post->post_type);if(!$object||empty($object->public))throw new InvalidArgumentException('Content type is not public.');return $post;}

    public static function get_metadata(int $id):array {
        $post=self::post($id);if(!current_user_can('read_post',$post->ID))throw new RuntimeException('The MCP service user cannot read this content record.');$adapter=self::adapter();$values=[];foreach(self::fields($adapter) as $field=>$key)$values[$field]=get_post_meta($post->ID,$key,true);return ['id'=>$post->ID,'adapter'=>$adapter,'supported'=>$adapter!=='none','values'=>$values];
    }

    public static function update_metadata(int $id,array $values):array {
        $post=self::post($id);if(!current_user_can('edit_post',$post->ID))throw new RuntimeException('The MCP service user cannot edit this content record.');$adapter=self::adapter();$map=self::fields($adapter);if(!$map)throw new RuntimeException('No supported SEO plugin is active.');if(!$values||count($values)>4)throw new InvalidArgumentException('Provide one or more supported SEO fields.');$updated=[];
        foreach($values as $field=>$value){$field=sanitize_key((string)$field);if(!isset($map[$field]))throw new InvalidArgumentException('Unsupported SEO field: '.$field);if(!is_scalar($value)&&$value!==null)throw new InvalidArgumentException('SEO values must be scalar.');$value=(string)$value;if($field==='canonical'&&$value!==''&&!str_starts_with($value,'/')&&!in_array(strtolower((string)wp_parse_url($value,PHP_URL_SCHEME)),['http','https'],true))throw new InvalidArgumentException('Canonical must be empty, site-relative or HTTP(S).');if($field==='noindex'){$enabled=in_array(strtolower($value),['1','true','yes','noindex'],true);if($adapter==='rank_math'){$current=get_post_meta($post->ID,$map[$field],true);$robots=is_array($current)?array_values(array_unique(array_filter(array_map('sanitize_key',$current)))):[];if($enabled&&!in_array('noindex',$robots,true))$robots[]='noindex';if(!$enabled)$robots=array_values(array_diff($robots,['noindex']));$value=$robots;}else $value=$enabled?'1':'';}else $value=sanitize_text_field($value);update_post_meta($post->ID,$map[$field],$value);$updated[]=$field;}
        return ['id'=>$post->ID,'adapter'=>$adapter,'updated_fields'=>$updated];
    }

    public static function valid_redirect(string $source,string $target,int $status):array {
        $source=trim($source);$target=trim($target);if(!str_starts_with($source,'/')||str_contains($source,'?')||str_contains($source,'#'))throw new InvalidArgumentException('Redirect source must be an exact site-relative path without query or fragment.');$source='/'.ltrim(preg_replace('#/+#','/',$source),'/');if(!str_ends_with($source,'/')&&!str_contains(basename($source),'.'))$source.='/';
        foreach(['/wp-admin','/wp-login.php','/wp-json','/xsofty-mcp'] as $protected)if(str_starts_with($source,$protected))throw new InvalidArgumentException('Protected WordPress and MCP routes cannot be redirected.');
        if(!str_starts_with($target,'/')||str_starts_with($target,'//')||str_contains($target,"\r")||str_contains($target,"\n"))throw new InvalidArgumentException('Redirect target must be a site-relative path.');$target='/'.ltrim($target,'/');if($source===$target)throw new InvalidArgumentException('Redirect source and target must differ.');if(!in_array($status,[301,302,307,308],true))throw new InvalidArgumentException('Redirect status must be 301, 302, 307 or 308.');return ['source'=>$source,'target'=>$target,'status'=>$status];
    }

    public static function list_redirects():array {$items=array_values((array)get_option(self::REDIRECT_OPTION,[]));usort($items,static fn($a,$b)=>strcmp($a['source'],$b['source']));return ['items'=>$items,'count'=>count($items)];}

    public static function upsert_redirect(string $source,string $target,int $status):array {$item=self::valid_redirect($source,$target,$status);return self::mutate_redirects(function(array &$items)use($item){$id=substr(hash('sha256',$item['source']),0,16);$item['id']=$id;$item['updated_at']=gmdate('c');if(!isset($items[$id])&&count($items)>=500)throw new RuntimeException('The maximum of 500 managed redirects has been reached.');$items[$id]=$item;return $item;});}

    public static function delete_redirect(string $id):array {$id=sanitize_key($id);return self::mutate_redirects(function(array &$items)use($id){if(!isset($items[$id]))throw new RuntimeException('Managed redirect not found.');unset($items[$id]);return ['id'=>$id,'deleted'=>true];});}

    public static function maybe_redirect():void {
        if(is_admin()||wp_doing_ajax())return;$path=(string)wp_parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH);$path='/'.ltrim(preg_replace('#/+#','/',$path),'/');if(!str_ends_with($path,'/')&&!str_contains(basename($path),'.'))$path.='/';$id=substr(hash('sha256',$path),0,16);$item=((array)get_option(self::REDIRECT_OPTION,[]))[$id]??null;if(!$item||($item['source']??'')!==$path)return;wp_safe_redirect(home_url($item['target']),(int)$item['status'],'Xsofty WordPress MCP');exit;
    }
}
