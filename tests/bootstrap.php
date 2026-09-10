<?php
// Minimal deterministic WordPress test environment for authentication-domain tests.
define('ABSPATH', __DIR__ . '/wordpress/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('XSOFTY_MCP_PRIVATE_DIR', dirname(__DIR__) . '/tests-runtime/private');
if(!is_dir(ABSPATH))mkdir(ABSPATH,0700,true);
if(!is_dir(WP_CONTENT_DIR))mkdir(WP_CONTENT_DIR,0700,true);
$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
$GLOBALS['xwmcp_options'] = [];
$GLOBALS['xwmcp_transients'] = [];
$GLOBALS['xwmcp_current_user'] = 1;
$GLOBALS['xwmcp_ext_object_cache'] = false;
$GLOBALS['xwmcp_cache'] = [];
$GLOBALS['xwmcp_cache_incr_fails'] = false;
$GLOBALS['xwmcp_transients_fail_with_ext_cache'] = false;
$GLOBALS['xwmcp_home_url'] = 'https://example.test';
$GLOBALS['xwmcp_environment'] = 'production';
$GLOBALS['xwmcp_ssl'] = true;
$GLOBALS['xwmcp_cron'] = [];
$GLOBALS['xwmcp_schedule_fails'] = false;
$GLOBALS['xwmcp_unschedule_fails'] = false;

function get_option($key, $default = false) { return $GLOBALS['xwmcp_options'][$key] ?? $default; }
function update_option($key, $value, $autoload=false) { $GLOBALS['xwmcp_options'][$key]=$value; return true; }
function add_option($key, $value, $deprecated='', $autoload=false) { if (array_key_exists($key,$GLOBALS['xwmcp_options'])) return false; $GLOBALS['xwmcp_options'][$key]=$value; return true; }
function delete_option($key) { unset($GLOBALS['xwmcp_options'][$key]); return true; }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, is_array($args) ? $args : []); }
function get_current_user_id() { return (int) $GLOBALS['xwmcp_current_user']; }
function user_can($user_id, $capability) { return (int) $user_id === 1 && $capability === 'manage_options'; }
function get_users($args = []) { return [1]; }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function get_current_blog_id() { return 1; }
function wp_using_ext_object_cache() { return (bool)$GLOBALS['xwmcp_ext_object_cache']; }
function wp_cache_add($key,$value,$group='',$ttl=0) { $full=$group.'|'.$key;if(array_key_exists($full,$GLOBALS['xwmcp_cache']))return false;$GLOBALS['xwmcp_cache'][$full]=$value;return true; }
function wp_cache_incr($key,$offset=1,$group='') { if($GLOBALS['xwmcp_cache_incr_fails'])return false;$full=$group.'|'.$key;if(!array_key_exists($full,$GLOBALS['xwmcp_cache']))return false;$GLOBALS['xwmcp_cache'][$full]+=$offset;return $GLOBALS['xwmcp_cache'][$full]; }
function wp_cache_get($key,$group='') { $full=$group.'|'.$key;return $GLOBALS['xwmcp_cache'][$full]??false; }
function get_transient($key) { if($GLOBALS['xwmcp_ext_object_cache']&&$GLOBALS['xwmcp_transients_fail_with_ext_cache'])return false;return $GLOBALS['xwmcp_transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { if($GLOBALS['xwmcp_ext_object_cache']&&$GLOBALS['xwmcp_transients_fail_with_ext_cache'])return false;$GLOBALS['xwmcp_transients'][$key] = $value; return true; }
function home_url() { return $GLOBALS['xwmcp_home_url']; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_get_environment_type() { return $GLOBALS['xwmcp_environment']; }
function is_ssl() { return (bool)$GLOBALS['xwmcp_ssl']; }
function get_user_by($field, $value) { return (int) $value === 1 ? (object) ['ID' => 1] : false; }
function wp_set_current_user($id) { $GLOBALS['xwmcp_current_user'] = (int) $id; }
function wp_normalize_path($path) { return str_replace('\\', '/', (string) $path); }
function untrailingslashit($path) { return rtrim((string) $path, '/\\'); }
function trailingslashit($path) { return untrailingslashit($path) . '/'; }
function wp_salt($scheme='auth') { return 'test-only-salt'; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0700, true); }
function rest_url($path='') { return 'https://example.test/wp-json/' . ltrim($path, '/'); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function xwmcp_cron_key($timestamp,$hook,$args) { return $timestamp.'|'.$hook.'|'.md5(serialize($args)); }
function wp_schedule_event($timestamp,$recurrence,$hook,$args=[],$wp_error=false) { if($GLOBALS['xwmcp_schedule_fails'])return false;$GLOBALS['xwmcp_cron'][xwmcp_cron_key($timestamp,$hook,$args)]=(object)['timestamp'=>(int)$timestamp,'schedule'=>(string)$recurrence,'hook'=>(string)$hook,'args'=>(array)$args];return true; }
function wp_schedule_single_event($timestamp,$hook,$args=[],$wp_error=false) { return wp_schedule_event($timestamp,'',$hook,$args,$wp_error); }
function wp_get_scheduled_event($hook,$args=[],$timestamp=null) { $items=array_values(array_filter($GLOBALS['xwmcp_cron'],static fn($e)=>$e->hook===$hook&&$e->args===$args&&($timestamp===null||$e->timestamp===$timestamp)));usort($items,static fn($a,$b)=>$a->timestamp<=>$b->timestamp);return $items[0]??false; }
function wp_next_scheduled($hook,$args=[]) { $event=wp_get_scheduled_event($hook,$args);return $event?$event->timestamp:false; }
function wp_get_schedule($hook,$args=[]) { $event=wp_get_scheduled_event($hook,$args);return $event?$event->schedule:false; }
function wp_unschedule_event($timestamp,$hook,$args=[],$wp_error=false) { if($GLOBALS['xwmcp_unschedule_fails'])return false;$key=xwmcp_cron_key($timestamp,$hook,$args);if(!isset($GLOBALS['xwmcp_cron'][$key]))return false;unset($GLOBALS['xwmcp_cron'][$key]);return true; }
function wp_clear_scheduled_hook($hook,$args=[],$wp_error=false) { $count=0;foreach($GLOBALS['xwmcp_cron'] as $key=>$event)if($event->hook===$hook&&($args===[]||$event->args===$args)){unset($GLOBALS['xwmcp_cron'][$key]);$count++;}return $count; }

final class WP_Error {
    private string $code; private string $message; private array $data;
    public function __construct($code, $message, $data = []) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }

final class WP_REST_Request {
    private array $headers;
    private array $payload;
    public function __construct(array $headers = [], array $payload = []) { $this->headers=array_change_key_case($headers, CASE_LOWER); $this->payload=$payload; }
    public function get_header($name) { return $this->headers[strtolower($name)] ?? ''; }
    public function get_json_params() { return $this->payload; }
    public function get_body() { return wp_json_encode($this->payload); }
}

final class WP_REST_Response {
    private $data; private int $status; private array $headers;
    public function __construct($data=null, int $status=200, array $headers=[]) { $this->data=$data; $this->status=$status; $this->headers=$headers; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
    public function get_headers() { return $this->headers; }
}
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }

require_once dirname(__DIR__) . '/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-auth.php';
require_once dirname(__DIR__) . '/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-backups.php';
require_once dirname(__DIR__) . '/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-tools.php';
require_once dirname(__DIR__) . '/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-server.php';
require_once dirname(__DIR__) . '/plugin/xsofty-wordpress-mcp/includes/class-xsofty-mcp-admin.php';
