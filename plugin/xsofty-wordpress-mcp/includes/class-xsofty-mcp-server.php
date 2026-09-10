<?php
if (!defined('ABSPATH')) exit;

final class Xsofty_MCP_Server {
    private const PROTOCOLS = ['2025-06-18','2025-03-26'];

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'routes']);
        add_filter('rest_pre_serve_request', [self::class, 'serve_empty'], 10, 4);
        add_filter('rest_request_after_callbacks', [self::class, 'finish_request'], 10, 3);
    }

    public static function finish_request($response, $handler, WP_REST_Request $request) {
        if (str_starts_with((string)$request->get_route(), '/xsofty-mcp/v1/')) Xsofty_MCP_Auth::end_request();
        return $response;
    }

    public static function routes(): void {
        register_rest_route('xsofty-mcp/v1', '/mcp', [
            ['methods'=>'POST','callback'=>[self::class,'handle'],'permission_callback'=>[self::class,'authorize']],
            ['methods'=>'GET','callback'=>[self::class,'method_not_allowed'],'permission_callback'=>[self::class,'authorize']],
        ]);
        register_rest_route('xsofty-mcp/v1', '/health', ['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>[self::class,'authorize_site_read']]);
        register_rest_route('xsofty-mcp/v1', '/download', ['methods'=>'GET','callback'=>[self::class,'download'],'permission_callback'=>'__return_true']);
    }

    public static function serve_empty(bool $served, $result): bool {
        if ($result instanceof WP_REST_Response && (($result->get_headers()['X-Xsofty-MCP-Empty'] ?? '') === '1')) return true;
        return $served;
    }

    private static function origin_allowed(WP_REST_Request $request): bool {
        $origin = trim((string) $request->get_header('origin'));
        if ($origin === '') return true;
        $settings = Xsofty_MCP_Auth::settings();
        $home = untrailingslashit((string) wp_parse_url(home_url(), PHP_URL_SCHEME) . '://' . (string) wp_parse_url(home_url(), PHP_URL_HOST));
        $port = wp_parse_url(home_url(), PHP_URL_PORT); if ($port) $home .= ':' . $port;
        $allowed = array_map('untrailingslashit', array_filter(array_merge([$home], (array) $settings['allowed_origins'])));
        return in_array(untrailingslashit($origin), $allowed, true);
    }

    public static function authorize(WP_REST_Request $request) {
        Xsofty_MCP_Auth::begin_request();
        if (!self::origin_allowed($request)) return new WP_Error('xsofty_mcp_origin_denied', 'Request Origin is not approved.', ['status'=>403]);
        return Xsofty_MCP_Auth::authorize($request);
    }

    public static function authorize_site_read(WP_REST_Request $request) {
        $authorized=self::authorize($request);if(is_wp_error($authorized))return $authorized;
        return Xsofty_MCP_Auth::require_scope('site_read');
    }

    private static function headers(): array { return ['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']; }

    private static function response($id, $result): WP_REST_Response {
        return new WP_REST_Response(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result], 200, self::headers());
    }

    private static function error($id, int $code, string $message, int $status=400, array $data=[]): WP_REST_Response {
        $error=['code'=>$code,'message'=>$message]; if($data)$error['data']=$data;
        return new WP_REST_Response(['jsonrpc'=>'2.0','id'=>$id,'error'=>$error],$status,self::headers());
    }

    private static function empty_response(int $status=202): WP_REST_Response {
        return new WP_REST_Response(null,$status,array_merge(self::headers(),['X-Xsofty-MCP-Empty'=>'1']));
    }

    public static function method_not_allowed(): WP_REST_Response {
        return new WP_REST_Response(['error'=>'This stateless MCP endpoint does not provide an SSE stream. Use POST.'],405,array_merge(self::headers(),['Allow'=>'POST']));
    }

    public static function health(): WP_REST_Response {
        return new WP_REST_Response(['ok'=>true,'server'=>'xsofty-wordpress-mcp','version'=>XSOFTY_WP_MCP_VERSION,'wordpress'=>get_bloginfo('version'),'tools'=>count(Xsofty_MCP_Tools::exposed_definitions()),'time'=>gmdate('c')],200,self::headers());
    }

    private static function validate_value(array $rule,$value,string $path):array {
        $type=$rule['type']??null;$errors=[];
        $valid=match($type){'string'=>is_string($value),'integer'=>is_int($value),'number'=>is_int($value)||is_float($value),'boolean'=>is_bool($value),'array'=>is_array($value)&&array_is_list($value),'object'=>is_array($value)&&($value===[]||!array_is_list($value)),'null'=>$value===null,default=>true};
        if(!$valid)return ["$path must be $type"];
        if(isset($rule['enum'])&&!in_array($value,(array)$rule['enum'],true))$errors[]="$path is not an allowed value";
        if(($type==='integer'||$type==='number')&&isset($rule['minimum'])&&$value<$rule['minimum'])$errors[]="$path is below minimum";
        if(($type==='integer'||$type==='number')&&isset($rule['maximum'])&&$value>$rule['maximum'])$errors[]="$path exceeds maximum";
        if($type==='string'&&isset($rule['maxLength'])&&strlen($value)>$rule['maxLength'])$errors[]="$path exceeds maximum length";
        if($type==='array'){
            if(isset($rule['maxItems'])&&count($value)>$rule['maxItems'])$errors[]="$path exceeds maximum items";
            if(isset($rule['items'])&&is_array($rule['items']))foreach($value as $index=>$item)$errors=array_merge($errors,self::validate_value($rule['items'],$item,$path.'['.$index.']'));
        }
        if($type==='object'){
            $properties=(array)($rule['properties']??[]);foreach((array)($rule['required']??[]) as $required)if(!array_key_exists($required,$value))$errors[]="$path.$required is required";
            if(($rule['additionalProperties']??true)===false)foreach(array_keys($value) as $field)if(!array_key_exists($field,$properties))$errors[]="$path.$field is not allowed";
            foreach($value as $field=>$child)if(isset($properties[$field])&&is_array($properties[$field]))$errors=array_merge($errors,self::validate_value($properties[$field],$child,$path.'.'.$field));
        }
        return $errors;
    }

    private static function validate_schema(array $schema, array $arguments): array {
        $errors=[];$properties=(array)($schema['properties']??[]);$required=(array)($schema['required']??[]);
        foreach($required as $field)if(!array_key_exists($field,$arguments))$errors[]="$field is required";
        if(($schema['additionalProperties']??true)===false)foreach(array_keys($arguments) as $field)if(!array_key_exists($field,$properties))$errors[]="$field is not allowed";
        foreach($arguments as $field=>$value)if(isset($properties[$field])&&is_array($properties[$field]))$errors=array_merge($errors,self::validate_value($properties[$field],$value,$field));
        return $errors;
    }

    public static function validate_arguments(array $schema,array $arguments):array {return self::validate_schema($schema,$arguments);}

    private static function validate_method_params(string $method,array $params):array {
        $string=static fn(int $max):array=>['type'=>'string','maxLength'=>$max];
        $schemas=[
            'initialize'=>['type'=>'object','properties'=>['protocolVersion'=>$string(32),'capabilities'=>['type'=>'object'],'clientInfo'=>['type'=>'object','properties'=>['name'=>$string(128),'version'=>$string(64),'title'=>$string(128)],'required'=>['name','version'],'additionalProperties'=>false]],'required'=>['protocolVersion','capabilities','clientInfo'],'additionalProperties'=>false],
            'ping'=>['type'=>'object','properties'=>[],'additionalProperties'=>false],
            'tools/list'=>['type'=>'object','properties'=>['cursor'=>$string(512)],'additionalProperties'=>false],
            'tools/call'=>['type'=>'object','properties'=>['name'=>$string(128),'arguments'=>['type'=>'object']],'required'=>['name'],'additionalProperties'=>false],
            'resources/list'=>['type'=>'object','properties'=>['cursor'=>$string(512)],'additionalProperties'=>false],
            'resources/read'=>['type'=>'object','properties'=>['uri'=>$string(512)],'required'=>['uri'],'additionalProperties'=>false],
        ];
        return isset($schemas[$method])?self::validate_schema($schemas[$method],$params):[];
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response {
        if(strlen((string)$request->get_body())>2097152)return self::error(null,-32600,'JSON-RPC request body exceeds 2 MB.',413);
        $payload=$request->get_json_params();
        if(!is_array($payload)||($payload!==[]&&array_is_list($payload))||($payload['jsonrpc']??'')!=='2.0'||empty($payload['method'])||!is_string($payload['method'])||strlen($payload['method'])>128)return self::error(is_array($payload)?($payload['id']??null):null,-32600,'Invalid JSON-RPC request.');
        $has_id=array_key_exists('id',$payload); $id=$payload['id']??null; $method=$payload['method']; $params=$payload['params']??[];
        if($has_id&&!(is_string($id)||is_int($id)||$id===null))return self::error(null,-32600,'JSON-RPC id must be a string, integer or null.');
        if(!is_array($params)||($params!==[]&&array_is_list($params)))return $has_id?self::error($id,-32602,'params must be an object.'):self::empty_response();
        $version=trim((string)$request->get_header('mcp-protocol-version'));
        if($method!=='initialize'&&!in_array($version,self::PROTOCOLS,true))return self::error($id,-32600,'Unsupported or missing MCP-Protocol-Version header.',400);
        $method_validation=self::validate_method_params($method,$params);
        if($method_validation)return $has_id?self::error($id,-32602,'Invalid method parameters.',400,['fields'=>$method_validation]):self::empty_response();
        $tool_name='';$tool_definition=null;$tool_arguments=[];
        if($method==='tools/call'){
            $tool_name=sanitize_key((string)$params['name']);$tool_definition=Xsofty_MCP_Tools::definition($tool_name);
            if(!$tool_definition||!Xsofty_MCP_Auth::has_scope($tool_definition['scope'])||!Xsofty_MCP_Auth::tool_allowed($tool_name))return $has_id?self::error($id,-32601,'Tool is unknown or not enabled.'):self::empty_response();
            $tool_arguments=(array)($params['arguments']??[]);$tool_validation=self::validate_schema($tool_definition['inputSchema'],$tool_arguments);
            if($tool_validation)return $has_id?self::error($id,-32602,'Invalid tool arguments.',400,['fields'=>$tool_validation]):self::empty_response();
        }
        if(!$has_id)return self::empty_response();
        if($method==='initialize'){
            $requested=$params['protocolVersion']??null;$capabilities=$params['capabilities']??null;$client=$params['clientInfo']??null;
            $protocol=in_array($requested,self::PROTOCOLS,true)?$requested:self::PROTOCOLS[0];
            $result=['protocolVersion'=>$protocol,'capabilities'=>['tools'=>['listChanged'=>false],'resources'=>['subscribe'=>false,'listChanged'=>false]],'serverInfo'=>['name'=>'xsofty-wordpress-mcp','version'=>XSOFTY_WP_MCP_VERSION],'instructions'=>'Use tools within the assigned scopes. Every mutation requires explicit confirmation; some requests may require WordPress administrator approval.'];return self::response($id,$result);
        }
        if($method==='ping')return self::response($id,(object)[]);
        if($method==='tools/list')return self::response($id,['tools'=>Xsofty_MCP_Tools::exposed_definitions()]);
        if($method==='resources/list'){$items=[];foreach(Xsofty_MCP_Control::resource_definitions() as $resource)if(Xsofty_MCP_Auth::has_scope(Xsofty_MCP_Control::resource_scope($resource['uri'])))$items[]=$resource;return self::response($id,['resources'=>$items]);}
        if($method==='resources/read'){$uri=$params['uri'];$scope=Xsofty_MCP_Control::resource_scope($uri);if($scope===''||!Xsofty_MCP_Auth::has_scope($scope))return self::error($id,-32602,'Resource is unknown or not enabled.');if(in_array($scope,['audit','backups'],true)&&!current_user_can('manage_options'))return self::error($id,-32603,'Resource access denied.',403);try{$data=Xsofty_MCP_Control::read_resource($uri);return self::response($id,['contents'=>[['uri'=>$uri,'mimeType'=>'application/json','text'=>wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]]]);}catch(Throwable $error){return self::error($id,-32603,$error->getMessage(),403);}}
        if($method==='tools/call'){
            try{$result=Xsofty_MCP_Tools::call($tool_name,$tool_arguments);return self::response($id,['content'=>[['type'=>'text','text'=>wp_json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]],'structuredContent'=>(object)$result,'isError'=>false]);}
            catch(Throwable $error){return self::response($id,['content'=>[['type'=>'text','text'=>$error->getMessage()]],'isError'=>true]);}
        }
        return self::error($id,-32601,'Method not found.');
    }

    public static function download(WP_REST_Request $request) {
        $filename=sanitize_file_name((string)$request->get_param('file')); $expires=(int)$request->get_param('expires'); $signature=sanitize_text_field((string)$request->get_param('signature')); $token_id=sanitize_key((string)$request->get_param('token'));
        if(!Xsofty_MCP_Backups::verify_download($filename,$expires,$signature,$token_id))return new WP_Error('xsofty_mcp_invalid_download','The backup download link is invalid or expired.',['status'=>403]);
        try{$opened=Xsofty_MCP_Backups::open_download($filename);}catch(Throwable $error){return new WP_Error('xsofty_mcp_backup_missing',$error->getMessage(),['status'=>404]);}
        $handle=$opened['handle'];while(ob_get_level())ob_end_clean(); nocache_headers(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"'); header('Content-Length: '.(int)$opened['bytes']); header('X-Content-Type-Options: nosniff'); fpassthru($handle);fclose($handle); exit;
    }
}
