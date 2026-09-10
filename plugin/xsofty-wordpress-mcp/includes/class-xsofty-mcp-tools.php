<?php
if (!defined('ABSPATH')) exit;

final class Xsofty_MCP_Tools {
    private static function schema(array $properties = [], array $required = []): array {
        return ['type' => 'object', 'properties' => (object) $properties, 'required' => $required, 'additionalProperties' => false];
    }

    public static function content_update_plan(array $current,array $proposed):array {
        $changes=[];$order=['title','content','excerpt','status'];
        foreach($order as $field){if(!array_key_exists($field,$proposed))continue;$from=(string)($current[$field]??'');$to=(string)$proposed[$field];if($from===$to)continue;
            if($field==='content')$changes[$field]=['from_sha256'=>hash('sha256',$from),'to_sha256'=>hash('sha256',$to),'from_bytes'=>strlen($from),'to_bytes'=>strlen($to)];
            else $changes[$field]=['from'=>substr($from,0,500),'to'=>substr($to,0,500)];
        }
        return ['operation'=>'update_content','target_id'=>(int)($current['id']??0),'changed_fields'=>array_keys($changes),'changes'=>$changes,'requires_confirmation'=>true,'will_create_revision'=>true,'will_write'=>false];
    }

    public static function definitions(): array {
        $string = static fn(string $description) => ['type' => 'string', 'description' => $description, 'maxLength'=>2097152];
        $integer = static fn(string $description, int $min = 1, int $max = 100) => ['type' => 'integer', 'description' => $description, 'minimum' => $min, 'maximum' => $max];
        $strings = static fn(string $description) => ['type'=>'array','description'=>$description,'items'=>['type'=>'string','maxLength'=>512],'maxItems'=>100];
        $integers = static fn(string $description) => ['type'=>'array','description'=>$description,'items'=>['type'=>'integer'],'maxItems'=>100];
        return [
            ['name'=>'site_info','scope'=>'site_read','description'=>'Return WordPress, PHP, active theme, environment and route information.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'list_content','scope'=>'content_read','description'=>'List posts, pages or public custom post type records with pagination.','inputSchema'=>self::schema(['post_type'=>$string('Post type slug.'),'status'=>$string('Post status, default any readable status.'),'search'=>$string('Optional search phrase.'),'page'=>$integer('Page number.'),'per_page'=>$integer('Items per page.',1,100)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'get_content','scope'=>'content_read','description'=>'Get one WordPress content record including rendered and raw editable fields.','inputSchema'=>self::schema(['id'=>$integer('Post ID.',1,2147483647)],['id']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'create_content','scope'=>'content_write','description'=>'Create a post, page or public custom post type record.','inputSchema'=>self::schema(['post_type'=>$string('Post type slug.'),'title'=>$string('Title.'),'content'=>$string('HTML content.'),'excerpt'=>$string('Excerpt.'),'status'=>$string('draft, pending, private or publish.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['post_type','title','confirm']),'annotations'=>['readOnlyHint'=>false]],
            ['name'=>'update_content','scope'=>'content_write','description'=>'Preview or update selected fields on an existing WordPress content record. Set dry_run=true to return a bounded plan without writing; writes require a best-effort stale-read guard using the current state SHA-256.','inputSchema'=>self::schema(['id'=>$integer('Post ID.',1,2147483647),'title'=>$string('Replacement title.'),'content'=>$string('Replacement HTML content.'),'excerpt'=>$string('Replacement excerpt.'),'status'=>$string('Replacement status.'),'expected_sha256'=>['type'=>'string','description'=>'Best-effort stale-read guard from state_sha256 in get_content or the latest dry-run plan; external writers are not transactionally locked.','maxLength'=>64],'dry_run'=>['type'=>'boolean','description'=>'Return a change plan without writing.'],'confirm'=>['type'=>'boolean','description'=>'Required and must be true unless dry_run is true.']],['id']),'annotations'=>['readOnlyHint'=>false,'idempotentHint'=>true]],
            ['name'=>'list_content_revisions','scope'=>'content_read','description'=>'List bounded revision metadata and content hashes for one readable content record.','inputSchema'=>self::schema(['id'=>$integer('Parent content ID.',1,2147483647),'limit'=>$integer('Maximum revisions.',1,100)],['id']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'restore_content_revision','scope'=>'content_write','description'=>'Restore one WordPress revision after creating a checkpoint of the current content.','inputSchema'=>self::schema(['revision_id'=>$integer('Revision ID.',1,2147483647),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['revision_id','confirm']),'annotations'=>['destructiveHint'=>true,'idempotentHint'=>false]],
            ['name'=>'list_taxonomies','scope'=>'content_read','description'=>'List public taxonomies and supported post types.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'list_terms','scope'=>'content_read','description'=>'List bounded terms in one public taxonomy.','inputSchema'=>self::schema(['taxonomy'=>$string('Public taxonomy slug.'),'search'=>$string('Optional search phrase.'),'limit'=>$integer('Maximum terms.',1,100)],['taxonomy']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'create_term','scope'=>'content_write','description'=>'Create a term in a public taxonomy.','inputSchema'=>self::schema(['taxonomy'=>$string('Taxonomy slug.'),'name'=>$string('Term name.'),'slug'=>$string('Optional term slug.'),'description'=>$string('Optional description.'),'parent'=>$integer('Optional parent term ID.',0,2147483647),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['taxonomy','name','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'update_term','scope'=>'content_write','description'=>'Update an existing public taxonomy term.','inputSchema'=>self::schema(['taxonomy'=>$string('Taxonomy slug.'),'term_id'=>$integer('Term ID.',1,2147483647),'name'=>$string('Replacement name.'),'slug'=>$string('Replacement slug.'),'description'=>$string('Replacement description.'),'parent'=>$integer('Replacement parent term ID.',0,2147483647),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['taxonomy','term_id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'assign_terms','scope'=>'content_write','description'=>'Replace or append term assignments for one content record.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647),'taxonomy'=>$string('Taxonomy slug.'),'term_ids'=>$integers('Term IDs.'),'append'=>['type'=>'boolean','description'=>'Append rather than replace assignments.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','taxonomy','term_ids','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'get_registered_meta','scope'=>'content_read','description'=>'Read allowlisted registered REST-visible metadata for one content record.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647),'keys'=>$strings('Optional registered meta keys.')],['id']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'update_registered_meta','scope'=>'content_write','description'=>'Update registered REST-visible scalar metadata only.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647),'values'=>['type'=>'object','description'=>'Registered meta key/value map.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','values','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_menus','scope'=>'content_read','description'=>'List classic navigation menus and bounded item metadata.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'update_menu_item','scope'=>'content_write','description'=>'Create or update a classic navigation menu item using WordPress APIs.','inputSchema'=>self::schema(['menu_id'=>$integer('Navigation menu ID.',1,2147483647),'item_id'=>$integer('Existing menu item ID; omit to create.',0,2147483647),'title'=>$string('Menu label.'),'url'=>$string('HTTP(S) or site-relative URL for a custom item.'),'object_id'=>$integer('Optional linked post or term ID.',0,2147483647),'object'=>$string('Optional object type, such as page or category.'),'type'=>$string('custom, post_type or taxonomy.'),'parent_id'=>$integer('Optional parent menu item ID.',0,2147483647),'position'=>$integer('Menu position.',0,10000),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['menu_id','title','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'update_media_metadata','scope'=>'media','description'=>'Update editable metadata for one Media Library attachment.','inputSchema'=>self::schema(['id'=>$integer('Attachment ID.',1,2147483647),'title'=>$string('Attachment title.'),'caption'=>$string('Caption.'),'description'=>$string('Description.'),'alt'=>$string('Alternative text.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'set_featured_image','scope'=>'content_write','description'=>'Set or remove the featured image for one editable content record.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647),'media_id'=>$integer('Image attachment ID; use 0 to remove.',0,2147483647),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','media_id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'get_seo_status','scope'=>'site_read','description'=>'Report supported SEO adapter and WordPress sitemap status.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'get_seo_metadata','scope'=>'content_read','description'=>'Read curated SEO metadata for one content record through a supported adapter.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647)],['id']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'update_seo_metadata','scope'=>'content_write','description'=>'Update title, description, canonical or noindex metadata through a supported SEO adapter.','inputSchema'=>self::schema(['id'=>$integer('Content ID.',1,2147483647),'values'=>['type'=>'object','description'=>'SEO fields: title, description, canonical and noindex.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','values','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_redirects','scope'=>'settings','description'=>'List exact-path internal redirects managed by this bridge.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'upsert_redirect','scope'=>'settings','description'=>'Create or update an exact-path internal redirect. Protected WordPress routes are forbidden.','inputSchema'=>self::schema(['source'=>$string('Exact site-relative source path.'),'target'=>$string('Site-relative target path.'),'status'=>$integer('HTTP status: 301, 302, 307 or 308.',301,308),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['source','target','status','confirm']),'annotations'=>['destructiveHint'=>true,'idempotentHint'=>true]],
            ['name'=>'delete_redirect','scope'=>'settings','description'=>'Delete one bridge-managed redirect.','inputSchema'=>self::schema(['id'=>$string('Managed redirect ID.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'get_sitemap_status','scope'=>'site_read','description'=>'Return WordPress core sitemap availability and URL.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'get_site_health','scope'=>'site_read','description'=>'Return curated WordPress, PHP, storage, cron, cache and update health signals without paths or secrets.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'list_cron_events','scope'=>'maintenance','description'=>'List bounded scheduled WP-Cron events with argument hashes, never argument values.','inputSchema'=>self::schema(['limit'=>$integer('Maximum events.',1,200)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'run_cron_event','scope'=>'maintenance','description'=>'Run one existing scheduled WP-Cron event by opaque ID. Globally disabled by default.','inputSchema'=>self::schema(['event_id'=>$string('Opaque event ID from list_cron_events.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['event_id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_plugin_updates','scope'=>'plugins','description'=>'List package-backed updates advertised by the WordPress update API.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true,'openWorldHint'=>true]],
            ['name'=>'update_plugin_safely','scope'=>'plugins','description'=>'Checkpoint and update one installed plugin from its current version to the exact advertised target, automatically restoring on failure. The bridge cannot update itself.','inputSchema'=>self::schema(['plugin'=>$string('Installed plugin basename from list_plugin_updates.'),'expected_current_version'=>$string('Exact current version from list_plugin_updates.'),'expected_new_version'=>$string('Exact target version from list_plugin_updates.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['plugin','expected_current_version','expected_new_version','confirm']),'annotations'=>['destructiveHint'=>true,'openWorldHint'=>true]],
            ['name'=>'list_plugin_checkpoints','scope'=>'plugins','description'=>'List private plugin checkpoints without exposing filesystem paths.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'restore_plugin_checkpoint','scope'=>'plugins','description'=>'Restore one plugin from a private verified checkpoint. The bridge cannot restore itself.','inputSchema'=>self::schema(['id'=>$string('Checkpoint ID from list_plugin_checkpoints.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'get_backup_schedule','scope'=>'backups','description'=>'Read scheduled-backup frequency, retention and next run.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'update_backup_schedule','scope'=>'settings','description'=>'Set disabled, daily or weekly private backups and retention.','inputSchema'=>self::schema(['schedule'=>['type'=>'string','maxLength'=>16,'enum'=>['disabled','daily','weekly']],'retention'=>$integer('Number of scheduled backups to retain.',1,20),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['schedule','retention','confirm']),'annotations'=>['destructiveHint'=>true,'idempotentHint'=>true]],
            ['name'=>'start_backup_job','scope'=>'backups','description'=>'Queue a private full backup for asynchronous WP-Cron processing.','inputSchema'=>self::schema(['label'=>$string('Optional non-sensitive label.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'get_job_status','scope'=>'backups','description'=>'Read one token-owned asynchronous job status.','inputSchema'=>self::schema(['id'=>$string('Job ID.')],['id']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'list_jobs','scope'=>'backups','description'=>'List bounded asynchronous jobs visible to the active token.','inputSchema'=>self::schema(['limit'=>$integer('Maximum jobs.',1,50)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'cancel_job','scope'=>'backups','description'=>'Cancel one token-owned backup job while it is still pending.','inputSchema'=>self::schema(['id'=>$string('Pending job ID.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'delete_content','scope'=>'content_write','description'=>'Trash or permanently delete a public content record.','inputSchema'=>self::schema(['id'=>$integer('Post ID.',1,2147483647),'force'=>['type'=>'boolean','description'=>'Permanently delete instead of trashing.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_media','scope'=>'media','description'=>'List Media Library attachments with file metadata.','inputSchema'=>self::schema(['search'=>$string('Optional search phrase.'),'page'=>$integer('Page number.'),'per_page'=>$integer('Items per page.',1,100)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'upload_media_from_url','scope'=>'media','description'=>'Safely download a bounded image from an HTTPS URL into the Media Library.','inputSchema'=>self::schema(['url'=>$string('Public HTTPS image URL.'),'title'=>$string('Optional attachment title.'),'alt'=>$string('Optional alternative text.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['url','confirm']),'annotations'=>['readOnlyHint'=>false,'openWorldHint'=>true]],
            ['name'=>'list_plugins','scope'=>'plugins','description'=>'List installed plugins and their activation/update state.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'install_plugin','scope'=>'plugins','description'=>'Install a plugin from the official WordPress.org directory. Requires explicit confirmation.','inputSchema'=>self::schema(['slug'=>$string('Official WordPress.org plugin slug.'),'activate'=>['type'=>'boolean','description'=>'Activate after installation.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['slug','confirm']),'annotations'=>['destructiveHint'=>true,'openWorldHint'=>true]],
            ['name'=>'activate_plugin','scope'=>'plugins','description'=>'Activate an installed plugin.','inputSchema'=>self::schema(['plugin'=>$string('Plugin basename such as folder/plugin.php.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['plugin','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'deactivate_plugin','scope'=>'plugins','description'=>'Deactivate an installed plugin.','inputSchema'=>self::schema(['plugin'=>$string('Plugin basename.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['plugin','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_theme_files','scope'=>'theme_read','description'=>'List text files in the active theme.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'read_theme_file','scope'=>'theme_read','description'=>'Read a text file inside the active theme.','inputSchema'=>self::schema(['path'=>$string('Path relative to the active theme root.')],['path']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'patch_theme_file','scope'=>'theme_write','description'=>'Replace exact text in a non-executable active-theme data or style file, creating a private restore copy first. PHP, JavaScript, HTML and SVG writes are prohibited.','inputSchema'=>self::schema(['path'=>$string('Path relative to the active theme root; CSS, JSON, TXT or MD only.'),'old_string'=>$string('Exact text to replace.'),'new_string'=>$string('Replacement text.'),'replace_all'=>['type'=>'boolean','description'=>'Replace every match rather than requiring one unique match.'],'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['path','old_string','new_string','confirm']),'annotations'=>['destructiveHint'=>true,'idempotentHint'=>false]],
            ['name'=>'get_option','scope'=>'settings','description'=>'Read one approved non-secret WordPress option.','inputSchema'=>self::schema(['name'=>$string('Approved option name.')],['name']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'update_option','scope'=>'settings','description'=>'Update one approved non-secret WordPress option.','inputSchema'=>self::schema(['name'=>$string('Approved option name.'),'value'=>$string('New scalar value.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['name','value','confirm']),'annotations'=>['destructiveHint'=>true,'idempotentHint'=>true]],
            ['name'=>'create_backup','scope'=>'backups','description'=>'Create a complete ZIP backup containing WordPress files, a SQL database export and a manifest.','inputSchema'=>self::schema(['label'=>$string('Optional short backup label.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['confirm']),'annotations'=>['readOnlyHint'=>false]],
            ['name'=>'list_backups','scope'=>'backups','description'=>'List backups created by this bridge with size and SHA-256.','inputSchema'=>self::schema(),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'get_backup_download_url','scope'=>'backups','description'=>'Create a short-lived signed download URL for one backup.','inputSchema'=>self::schema(['filename'=>$string('Backup filename.'),'ttl'=>$integer('URL lifetime in seconds.',60,3600)],['filename']),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'delete_backup','scope'=>'backups','description'=>'Permanently delete one bridge backup. Requires explicit confirmation.','inputSchema'=>self::schema(['filename'=>$string('Backup filename.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['filename','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'flush_rewrite_rules','scope'=>'maintenance','description'=>'Flush WordPress permalink rewrite rules.','inputSchema'=>self::schema(['confirm'=>['type'=>'boolean','description'=>'Must be true.']],['confirm']),'annotations'=>['idempotentHint'=>true]],
            ['name'=>'clear_cache','scope'=>'maintenance','description'=>'Clear WordPress object cache and invoke common cache-plugin purge hooks.','inputSchema'=>self::schema(['confirm'=>['type'=>'boolean','description'=>'Must be true.']],['confirm']),'annotations'=>['idempotentHint'=>true]],
            ['name'=>'get_audit_log','scope'=>'audit','description'=>'Filter recent MCP authentication and tool activity.','inputSchema'=>self::schema(['action'=>$string('Exact audit action.'),'token_id'=>$string('Exact token ID.'),'success'=>['type'=>'boolean'],'since'=>$string('ISO-8601 lower time bound.'),'limit'=>$integer('Maximum entries.',1,500)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'export_audit_log','scope'=>'audit','description'=>'Export a bounded filtered audit view as CSV text.','inputSchema'=>self::schema(['action'=>$string('Exact audit action.'),'token_id'=>$string('Exact token ID.'),'success'=>['type'=>'boolean'],'since'=>$string('ISO-8601 lower time bound.'),'limit'=>$integer('Maximum entries.',1,500)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'submit_approval_request','scope'=>'audit','description'=>'Submit one enabled mutating tool call to the WordPress administrator approval queue. Sensitive fields are rejected.','inputSchema'=>self::schema(['tool'=>$string('Enabled mutating tool name.'),'arguments'=>['type'=>'object','description'=>'Target tool arguments, excluding secrets.'],'summary'=>$string('Human-readable reason and expected effect.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['tool','arguments','summary','confirm']),'annotations'=>['destructiveHint'=>true]],
            ['name'=>'list_approval_requests','scope'=>'audit','description'=>'List approval requests owned by the active token.','inputSchema'=>self::schema(['status'=>['type'=>'string','maxLength'=>16,'enum'=>['all','pending','approved','rejected','executing','executed','failed','expired']],'limit'=>$integer('Maximum requests.',1,100)]),'annotations'=>['readOnlyHint'=>true]],
            ['name'=>'execute_approved_request','scope'=>'audit','description'=>'Execute one approved token-owned request exactly once after rechecking current scope and tool policy.','inputSchema'=>self::schema(['id'=>$string('Approval request ID.'),'confirm'=>['type'=>'boolean','description'=>'Must be true.']],['id','confirm']),'annotations'=>['destructiveHint'=>true]],
        ];
    }

    public static function exposed_definitions(): array {
        return array_values(array_map(static function(array $tool): array { unset($tool['scope']);$tool['title']=ucwords(str_replace('_',' ',$tool['name']));$tool['outputSchema']=['type'=>'object','additionalProperties'=>true];return $tool; }, array_filter(self::definitions(), static fn($tool) => Xsofty_MCP_Auth::has_scope($tool['scope']) && Xsofty_MCP_Auth::tool_allowed($tool['name']))));
    }

    public static function definition(string $name): ?array {
        foreach (self::definitions() as $tool) if ($tool['name'] === $name) return $tool;
        return null;
    }

    private static function require_confirm(array $args): void {
        if (($args['confirm'] ?? false) !== true) throw new InvalidArgumentException('This operation requires confirm=true.');
    }

    private static function allowed_post_type(string $post_type): string {
        $types = get_post_types(['show_in_rest' => true, 'public' => true], 'names');
        if (!in_array($post_type, $types, true)) throw new InvalidArgumentException('Post type is not publicly manageable through REST.');
        return $post_type;
    }

    private static function content_status(string $status): string {
        $status=sanitize_key($status);if(!in_array($status,['draft','pending','private','publish'],true))throw new InvalidArgumentException('Status must be draft, pending, private or publish.');return $status;
    }

    private static function content_state_hash(WP_Post $post):string {return hash('sha256',(string)wp_json_encode(['id'=>(int)$post->ID,'post_type'=>(string)$post->post_type,'title'=>(string)$post->post_title,'content'=>(string)$post->post_content,'excerpt'=>(string)$post->post_excerpt,'status'=>(string)$post->post_status,'modified_gmt'=>(string)$post->post_modified_gmt],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}

    private static function post_result(WP_Post $post, bool $full = false): array {
        $result = ['id'=>$post->ID,'post_type'=>$post->post_type,'status'=>$post->post_status,'title'=>get_the_title($post),'slug'=>$post->post_name,'modified_gmt'=>$post->post_modified_gmt,'state_sha256'=>self::content_state_hash($post),'url'=>get_permalink($post)];
        if ($full) {
            $result['content_raw'] = $post->post_content;
            $result['content_rendered'] = apply_filters('the_content', $post->post_content);
            $result['excerpt'] = $post->post_excerpt;
            $result['featured_media'] = get_post_thumbnail_id($post) ?: null;
        }
        return $result;
    }

    private static function list_content(array $args): array {
        $post_type = self::allowed_post_type(sanitize_key($args['post_type'] ?? 'page'));
        $object = get_post_type_object($post_type);
        if (!$object || !current_user_can($object->cap->edit_posts)) throw new RuntimeException('The MCP service user cannot read this post type.');
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 20)));
        $query = new WP_Query(['post_type'=>$post_type,'post_status'=>sanitize_key($args['status'] ?? 'any'),'s'=>sanitize_text_field($args['search'] ?? ''),'paged'=>max(1,(int)($args['page'] ?? 1)),'posts_per_page'=>$per_page,'orderby'=>'modified','order'=>'DESC']);
        return ['items'=>array_map(static fn($post)=>self::post_result($post),$query->posts),'page'=>max(1,(int)($args['page']??1)),'per_page'=>$per_page,'total'=>(int)$query->found_posts,'pages'=>(int)$query->max_num_pages];
    }

    private static function get_content(array $args): array {
        $post = get_post((int) $args['id']);
        if (!$post || !in_array($post->post_type, get_post_types(['show_in_rest'=>true,'public'=>true],'names'), true)) throw new RuntimeException('Content record not found.');
        if (!current_user_can('read_post', $post->ID)) throw new RuntimeException('The MCP service user cannot read this content record.');
        return self::post_result($post, true);
    }

    private static function create_content(array $args): array {
        self::require_confirm($args);
        $post_type = self::allowed_post_type(sanitize_key($args['post_type']));
        $object = get_post_type_object($post_type); $status = self::content_status($args['status']??'draft');
        if (!$object || !current_user_can($object->cap->create_posts)) throw new RuntimeException('The MCP service user cannot create this post type.');
        if ($status === 'publish' && !current_user_can($object->cap->publish_posts)) throw new RuntimeException('The MCP service user cannot publish this post type.');
        $id = wp_insert_post(['post_type'=>$post_type,'post_title'=>sanitize_text_field($args['title']),'post_content'=>wp_kses_post($args['content']??''),'post_excerpt'=>sanitize_textarea_field($args['excerpt']??''),'post_status'=>$status], true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        return self::post_result(get_post($id), true);
    }

    private static function update_content(array $args): array {
        $post = get_post((int)$args['id']);
        if (!$post) throw new RuntimeException('Content record not found.');
        self::allowed_post_type($post->post_type);
        if (!current_user_can('edit_post', $post->ID)) throw new RuntimeException('The MCP service user cannot edit this content record.');
        $object = get_post_type_object($post->post_type);
        $validated_status=array_key_exists('status',$args)?self::content_status((string)$args['status']):null;
        if ($validated_status === 'publish' && (!$object || !current_user_can($object->cap->publish_posts))) throw new RuntimeException('The MCP service user cannot publish this post type.');
        $data=['ID'=>$post->ID];$proposed=[];
        if (array_key_exists('title',$args)) {$data['post_title']=sanitize_text_field($args['title']);$proposed['title']=$data['post_title'];}
        if (array_key_exists('content',$args)) {$data['post_content']=wp_kses_post($args['content']);$proposed['content']=$data['post_content'];}
        if (array_key_exists('excerpt',$args)) {$data['post_excerpt']=sanitize_textarea_field($args['excerpt']);$proposed['excerpt']=$data['post_excerpt'];}
        if ($validated_status!==null) {$data['post_status']=$validated_status;$proposed['status']=$validated_status;}
        $current_hash=self::content_state_hash($post);if(!empty($args['dry_run']))return self::content_update_plan(['id'=>$post->ID,'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'status'=>$post->post_status],$proposed)+['expected_sha256'=>$current_hash];
        self::require_confirm($args);
        $expected=(string)($args['expected_sha256']??'');if(!preg_match('/^[a-f0-9]{64}$/',$expected)||!hash_equals($current_hash,$expected))throw new RuntimeException('Content changed since it was read; refresh the record or dry-run plan.');
        $id=wp_update_post($data,true); if(is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        return self::post_result(get_post($id),true);
    }

    private static function list_content_revisions(array $args):array {
        $post=get_post((int)$args['id']);if(!$post)throw new RuntimeException('Content record not found.');self::allowed_post_type($post->post_type);
        if(!current_user_can('read_post',$post->ID))throw new RuntimeException('The MCP service user cannot read revisions for this content record.');
        $limit=max(1,min(100,(int)($args['limit']??20)));$revisions=wp_get_post_revisions($post->ID,['posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC']);$items=[];
        foreach($revisions as $revision)$items[]=['revision_id'=>(int)$revision->ID,'parent_id'=>(int)$revision->post_parent,'date_gmt'=>(string)$revision->post_date_gmt,'author'=>(int)$revision->post_author,'title'=>(string)$revision->post_title,'content_sha256'=>hash('sha256',(string)$revision->post_content),'content_bytes'=>strlen((string)$revision->post_content)];
        return ['parent_id'=>$post->ID,'items'=>$items,'count'=>count($items)];
    }

    private static function restore_content_revision(array $args):array {
        self::require_confirm($args);$revision_id=(int)$args['revision_id'];$parent_id=(int)wp_is_post_revision($revision_id);if(!$parent_id)throw new RuntimeException('Revision not found.');
        $parent=get_post($parent_id);if(!$parent)throw new RuntimeException('Parent content record not found.');self::allowed_post_type($parent->post_type);
        if(!current_user_can('edit_post',$parent_id))throw new RuntimeException('The MCP service user cannot restore this content record.');
        $checkpoint=wp_save_post_revision($parent_id);$restored=wp_restore_post_revision($revision_id);if(!$restored)throw new RuntimeException('WordPress could not restore the requested revision.');
        return ['restored_revision_id'=>$revision_id,'checkpoint_revision_id'=>is_wp_error($checkpoint)?0:(int)$checkpoint,'content'=>self::post_result(get_post($parent_id),true)];
    }

    private static function public_taxonomy(string $name){$name=sanitize_key($name);$taxonomy=get_taxonomy($name);if(!$taxonomy||empty($taxonomy->public))throw new InvalidArgumentException('Taxonomy is not public or does not exist.');return $taxonomy;}

    private static function list_taxonomies():array {$items=[];foreach(get_taxonomies(['public'=>true],'objects') as $taxonomy)$items[]=['name'=>$taxonomy->name,'label'=>$taxonomy->label,'hierarchical'=>(bool)$taxonomy->hierarchical,'object_types'=>array_values((array)$taxonomy->object_type)];return ['items'=>$items,'count'=>count($items)];}

    private static function list_terms(array $args):array {$taxonomy=self::public_taxonomy($args['taxonomy']);$limit=max(1,min(100,(int)($args['limit']??50)));$terms=get_terms(['taxonomy'=>$taxonomy->name,'hide_empty'=>false,'number'=>$limit,'search'=>sanitize_text_field($args['search']??'')]);if(is_wp_error($terms))throw new RuntimeException($terms->get_error_message());$items=[];foreach($terms as $term)$items[]=['term_id'=>(int)$term->term_id,'name'=>$term->name,'slug'=>$term->slug,'description'=>$term->description,'parent'=>(int)$term->parent,'count'=>(int)$term->count];return ['taxonomy'=>$taxonomy->name,'items'=>$items,'count'=>count($items)];}

    private static function create_term(array $args):array {self::require_confirm($args);$taxonomy=self::public_taxonomy($args['taxonomy']);if(!current_user_can($taxonomy->cap->manage_terms))throw new RuntimeException('The MCP service user cannot create terms in this taxonomy.');$data=[];if(isset($args['slug']))$data['slug']=sanitize_title($args['slug']);if(isset($args['description']))$data['description']=sanitize_textarea_field($args['description']);if(isset($args['parent']))$data['parent']=max(0,(int)$args['parent']);$created=wp_insert_term(sanitize_text_field($args['name']),$taxonomy->name,$data);if(is_wp_error($created))throw new RuntimeException($created->get_error_message());$term=get_term((int)$created['term_id'],$taxonomy->name);return ['term_id'=>(int)$term->term_id,'taxonomy'=>$taxonomy->name,'name'=>$term->name,'slug'=>$term->slug,'parent'=>(int)$term->parent];}

    private static function update_term(array $args):array {self::require_confirm($args);$taxonomy=self::public_taxonomy($args['taxonomy']);if(!current_user_can($taxonomy->cap->edit_terms))throw new RuntimeException('The MCP service user cannot edit terms in this taxonomy.');$term_id=(int)$args['term_id'];$existing=get_term($term_id,$taxonomy->name);if(!$existing||is_wp_error($existing))throw new RuntimeException('Taxonomy term not found.');$data=[];if(isset($args['name']))$data['name']=sanitize_text_field($args['name']);if(isset($args['slug']))$data['slug']=sanitize_title($args['slug']);if(isset($args['description']))$data['description']=sanitize_textarea_field($args['description']);if(isset($args['parent']))$data['parent']=max(0,(int)$args['parent']);if(!$data)throw new InvalidArgumentException('No supported term fields were provided.');$updated=wp_update_term($term_id,$taxonomy->name,$data);if(is_wp_error($updated))throw new RuntimeException($updated->get_error_message());$term=get_term($term_id,$taxonomy->name);return ['term_id'=>$term_id,'taxonomy'=>$taxonomy->name,'name'=>$term->name,'slug'=>$term->slug,'parent'=>(int)$term->parent];}

    private static function assign_terms(array $args):array {self::require_confirm($args);$post=get_post((int)$args['id']);if(!$post)throw new RuntimeException('Content record not found.');self::allowed_post_type($post->post_type);if(!current_user_can('edit_post',$post->ID))throw new RuntimeException('The MCP service user cannot edit this content record.');$taxonomy=self::public_taxonomy($args['taxonomy']);if(!in_array($post->post_type,(array)$taxonomy->object_type,true))throw new InvalidArgumentException('Taxonomy is not registered for this post type.');if(!current_user_can($taxonomy->cap->assign_terms))throw new RuntimeException('The MCP service user cannot assign this taxonomy.');if(!is_array($args['term_ids']))throw new InvalidArgumentException('term_ids must be an array.');$ids=array_values(array_unique(array_filter(array_map('absint',$args['term_ids']))));foreach($ids as $id)if(!term_exists($id,$taxonomy->name))throw new InvalidArgumentException('One or more term IDs do not exist in the taxonomy.');$assigned=wp_set_object_terms($post->ID,$ids,$taxonomy->name,!empty($args['append']));if(is_wp_error($assigned))throw new RuntimeException($assigned->get_error_message());return ['id'=>$post->ID,'taxonomy'=>$taxonomy->name,'term_taxonomy_ids'=>array_map('intval',$assigned),'append'=>!empty($args['append'])];}

    private static function rest_visible_meta(string $post_type):array {$registered=get_registered_meta_keys('post',$post_type);return array_filter($registered,static fn($schema)=>!empty($schema['show_in_rest']));}

    private static function get_registered_meta(array $args):array {$post=get_post((int)$args['id']);if(!$post)throw new RuntimeException('Content record not found.');self::allowed_post_type($post->post_type);if(!current_user_can('read_post',$post->ID))throw new RuntimeException('The MCP service user cannot read this content record.');$allowed=self::rest_visible_meta($post->post_type);$requested=isset($args['keys'])&&is_array($args['keys'])?array_map('sanitize_key',$args['keys']):array_keys($allowed);$values=[];$visible=[];foreach(array_slice($requested,0,100) as $key)if(isset($allowed[$key])&&current_user_can('read_post_meta',$post->ID,$key)){$visible[]=$key;$values[$key]=get_post_meta($post->ID,$key,!empty($allowed[$key]['single']));}return ['id'=>$post->ID,'registered_keys'=>$visible,'values'=>$values];}

    private static function update_registered_meta(array $args):array {self::require_confirm($args);$post=get_post((int)$args['id']);if(!$post)throw new RuntimeException('Content record not found.');self::allowed_post_type($post->post_type);if(!current_user_can('edit_post',$post->ID))throw new RuntimeException('The MCP service user cannot edit this content record.');if(!is_array($args['values'])||count($args['values'])>20)throw new InvalidArgumentException('values must be an object containing at most 20 entries.');$allowed=self::rest_visible_meta($post->post_type);$updated=[];foreach($args['values'] as $raw_key=>$value){$key=sanitize_key((string)$raw_key);if(!isset($allowed[$key]))throw new InvalidArgumentException('Meta key is not registered and REST-visible: '.$key);if(!current_user_can('edit_post_meta',$post->ID,$key))throw new RuntimeException('The MCP service user cannot edit registered meta: '.$key);if(!(is_scalar($value)||$value===null))throw new InvalidArgumentException('Only scalar registered meta values are supported.');$sanitized=sanitize_meta($key,$value,'post',$post->post_type);if(update_post_meta($post->ID,$key,$sanitized)===false&&get_post_meta($post->ID,$key,true)!=$sanitized)throw new RuntimeException('Unable to update registered meta: '.$key);$updated[]=$key;}return ['id'=>$post->ID,'updated_keys'=>$updated];}

    private static function list_menus():array {if(!current_user_can('edit_theme_options'))throw new RuntimeException('The MCP service user cannot access navigation menus.');$items=[];foreach(wp_get_nav_menus() as $menu){$menu_items=wp_get_nav_menu_items($menu->term_id,['post_status'=>'any']);$children=[];foreach(array_slice(is_array($menu_items)?$menu_items:[],0,500) as $item)$children[]=['item_id'=>(int)$item->ID,'title'=>$item->title,'url'=>$item->url,'type'=>$item->type,'object'=>$item->object,'object_id'=>(int)$item->object_id,'parent_id'=>(int)$item->menu_item_parent,'position'=>(int)$item->menu_order];$items[]=['menu_id'=>(int)$menu->term_id,'name'=>$menu->name,'slug'=>$menu->slug,'items'=>$children];}return ['items'=>$items,'count'=>count($items)];}

    private static function update_menu_item(array $args):array {self::require_confirm($args);if(!current_user_can('edit_theme_options'))throw new RuntimeException('The MCP service user cannot edit navigation menus.');$menu_id=(int)$args['menu_id'];if(!wp_get_nav_menu_object($menu_id))throw new RuntimeException('Navigation menu not found.');$type=isset($args['type'])?sanitize_key($args['type']):'custom';if(!in_array($type,['custom','post_type','taxonomy'],true))throw new InvalidArgumentException('Unsupported menu item type.');$data=['menu-item-title'=>sanitize_text_field($args['title']),'menu-item-status'=>'publish','menu-item-type'=>$type,'menu-item-parent-id'=>max(0,(int)($args['parent_id']??0)),'menu-item-position'=>max(0,(int)($args['position']??0))];if($type==='custom'){$url=trim((string)($args['url']??''));if($url===''||(!str_starts_with($url,'/')&&!in_array(strtolower((string)wp_parse_url($url,PHP_URL_SCHEME)),['http','https'],true)))throw new InvalidArgumentException('Custom menu URLs must be site-relative or use HTTP(S).');$data['menu-item-url']=esc_url_raw($url);}else{$object=sanitize_key((string)($args['object']??''));$object_id=max(1,(int)($args['object_id']??0));if($object===''||$object_id<1)throw new InvalidArgumentException('Object and object_id are required for linked menu items.');$data['menu-item-object']=$object;$data['menu-item-object-id']=$object_id;}$item_id=wp_update_nav_menu_item($menu_id,max(0,(int)($args['item_id']??0)),$data);if(is_wp_error($item_id))throw new RuntimeException($item_id->get_error_message());return ['menu_id'=>$menu_id,'item_id'=>(int)$item_id,'created'=>empty($args['item_id'])];}

    private static function update_media_metadata(array $args):array {self::require_confirm($args);$attachment=get_post((int)$args['id']);if(!$attachment||$attachment->post_type!=='attachment')throw new RuntimeException('Media attachment not found.');if(!current_user_can('edit_post',$attachment->ID))throw new RuntimeException('The MCP service user cannot edit this attachment.');$data=['ID'=>$attachment->ID];if(isset($args['title']))$data['post_title']=sanitize_text_field($args['title']);if(isset($args['caption']))$data['post_excerpt']=sanitize_textarea_field($args['caption']);if(isset($args['description']))$data['post_content']=wp_kses_post($args['description']);if(count($data)>1){$result=wp_update_post($data,true);if(is_wp_error($result))throw new RuntimeException($result->get_error_message());}if(array_key_exists('alt',$args))update_post_meta($attachment->ID,'_wp_attachment_image_alt',sanitize_text_field($args['alt']));return ['id'=>$attachment->ID,'title'=>get_the_title($attachment->ID),'caption'=>wp_get_attachment_caption($attachment->ID),'alt'=>get_post_meta($attachment->ID,'_wp_attachment_image_alt',true)];}

    private static function set_featured_image(array $args):array {self::require_confirm($args);$post=get_post((int)$args['id']);if(!$post)throw new RuntimeException('Content record not found.');self::allowed_post_type($post->post_type);if(!current_user_can('edit_post',$post->ID))throw new RuntimeException('The MCP service user cannot edit this content record.');$media_id=(int)$args['media_id'];if($media_id===0){delete_post_thumbnail($post->ID);return ['id'=>$post->ID,'media_id'=>0,'removed'=>true];}$attachment=get_post($media_id);if(!$attachment||$attachment->post_type!=='attachment'||!wp_attachment_is_image($media_id))throw new InvalidArgumentException('media_id must reference an image attachment.');if(!set_post_thumbnail($post->ID,$media_id))throw new RuntimeException('WordPress could not set the featured image.');return ['id'=>$post->ID,'media_id'=>$media_id,'removed'=>false];}

    private static function list_media(array $args): array {
        if (!current_user_can('upload_files')) throw new RuntimeException('The MCP service user cannot access media.');
        $per_page=max(1,min(100,(int)($args['per_page']??20))); $page=max(1,(int)($args['page']??1));
        $q=new WP_Query(['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','s'=>sanitize_text_field($args['search']??''),'paged'=>$page,'posts_per_page'=>$per_page,'orderby'=>'modified','order'=>'DESC']);
        $items=array_map(static function($post){$file=get_attached_file($post->ID);return['id'=>$post->ID,'title'=>get_the_title($post),'url'=>wp_get_attachment_url($post->ID),'mime'=>$post->post_mime_type,'alt'=>get_post_meta($post->ID,'_wp_attachment_image_alt',true),'bytes'=>$file&&is_file($file)?filesize($file):null];},$q->posts);
        return ['items'=>$items,'page'=>$page,'per_page'=>$per_page,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages];
    }

    private static function upload_media(array $args): array {
        self::require_confirm($args);
        if (!current_user_can('upload_files')) throw new RuntimeException('The MCP service user cannot upload media.');
        $url=esc_url_raw($args['url']);
        if (strtolower((string)wp_parse_url($url,PHP_URL_SCHEME))!=='https') throw new InvalidArgumentException('Media URL must use HTTPS.');
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
        $tmp=wp_tempnam('xsofty-mcp-media');
        if($tmp===false)throw new RuntimeException('Unable to allocate a temporary media file.');
        $response=wp_safe_remote_get($url,['timeout'=>30,'redirection'=>3,'stream'=>true,'filename'=>$tmp,'limit_response_size'=>20*MB_IN_BYTES]);
        if(is_wp_error($response)){@unlink($tmp);throw new RuntimeException($response->get_error_message());}
        $code=(int)wp_remote_retrieve_response_code($response); $type=strtolower((string)wp_remote_retrieve_header($response,'content-type'));
        if($code<200||$code>=300||!str_starts_with($type,'image/')){@unlink($tmp);throw new RuntimeException('Remote media must be a successful image response.');}
        if(!is_file($tmp)||filesize($tmp)===0||filesize($tmp)>20*MB_IN_BYTES){@unlink($tmp);throw new RuntimeException('Remote image is empty or exceeds the 20 MB limit.');}
        $name=basename((string)wp_parse_url($url,PHP_URL_PATH))?:'remote-image';
        $id=media_handle_sideload(['name'=>sanitize_file_name($name),'tmp_name'=>$tmp],0,sanitize_text_field($args['title']??''));
        if(is_wp_error($id)){@unlink($tmp);throw new RuntimeException($id->get_error_message());}
        if(isset($args['alt'])) update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($args['alt']));
        return ['id'=>$id,'url'=>wp_get_attachment_url($id),'mime'=>get_post_mime_type($id),'title'=>get_the_title($id)];
    }

    private static function list_plugins(): array {
        if (is_multisite() || !current_user_can('activate_plugins')) throw new RuntimeException('Plugin operations require a single-site administrator.');
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        $updates=get_site_transient('update_plugins'); $items=[];
        foreach(get_plugins() as $file=>$data)$items[]=['plugin'=>$file,'name'=>$data['Name'],'version'=>$data['Version'],'active'=>is_plugin_active($file),'update_available'=>isset($updates->response[$file])?$updates->response[$file]->new_version:null];
        return ['items'=>$items];
    }

    private static function install_plugin(array $args): array {
        self::require_confirm($args); if(is_multisite()||!current_user_can('install_plugins'))throw new RuntimeException('Plugin installation requires a single-site administrator.'); $slug=sanitize_key($args['slug']); if($slug==='')throw new InvalidArgumentException('Plugin slug is required.');
        require_once ABSPATH.'wp-admin/includes/plugin-install.php'; require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
        $api=plugins_api('plugin_information',['slug'=>$slug,'fields'=>['sections'=>false]]); if(is_wp_error($api))throw new RuntimeException($api->get_error_message());
        $skin=new Automatic_Upgrader_Skin(); $upgrader=new Plugin_Upgrader($skin); $result=$upgrader->install($api->download_link);
        if(is_wp_error($result))throw new RuntimeException($result->get_error_message()); if(!$result)throw new RuntimeException('Plugin installation failed.');
        $plugin=$upgrader->plugin_info(); $activated=false;
        if(!empty($args['activate'])&&$plugin){$a=activate_plugin($plugin);if(is_wp_error($a))return ['plugin'=>$plugin,'installed'=>true,'activated'=>false,'activation_error'=>$a->get_error_message()];$activated=is_plugin_active($plugin);}
        return ['plugin'=>$plugin,'installed'=>true,'activated'=>$activated];
    }

    private static function active_theme_file(string $relative): string {
        $relative=ltrim(wp_normalize_path($relative),'/');
        if($relative===''||str_contains($relative,'..')||!preg_match('/\.(php|css|js|json|svg|html|txt|md)$/i',$relative))throw new InvalidArgumentException('Invalid or unsupported theme path.');
        $root_real=realpath(get_stylesheet_directory());if($root_real===false||is_link(get_stylesheet_directory())||!is_dir($root_real))throw new RuntimeException('Unable to resolve the active theme root.');$root=trailingslashit(wp_normalize_path($root_real)); $path=wp_normalize_path($root.$relative);
        if(!str_starts_with($path,$root))throw new InvalidArgumentException('Theme path escapes the active theme.');
        $cursor=rtrim($root,'/');
        foreach(explode('/',$relative) as $segment){$cursor.='/'.$segment;if(is_link($cursor))throw new InvalidArgumentException('Symlinks are not permitted in MCP theme paths.');}
        if(file_exists($path)){
            $resolved=wp_normalize_path((string)realpath($path));
            if($resolved===''||!str_starts_with($resolved,$root))throw new InvalidArgumentException('Theme path resolves outside the active theme.');
            return $resolved;
        }
        return $path;
    }

    private static function secure_theme_read(string $path,int $limit):array {$before=lstat($path);if($before===false||is_link($path)||(($before['mode']&0170000)!==0100000))throw new RuntimeException('Theme file is not a regular file.');$handle=fopen($path,'rb');if(!$handle)throw new RuntimeException('Theme file is unreadable.');try{$opened=fstat($handle);if($opened===false||$opened['dev']!==$before['dev']||$opened['ino']!==$before['ino'])throw new RuntimeException('Theme file changed while opening.');$content=stream_get_contents($handle,$limit+1);if($content===false||strlen($content)>$limit)throw new RuntimeException('Theme file exceeds the read limit.');$after=fstat($handle);$path_after=lstat($path);if($after===false||$path_after===false||is_link($path)||$after['dev']!==$before['dev']||$after['ino']!==$before['ino']||$path_after['dev']!==$before['dev']||$path_after['ino']!==$before['ino']||$after['size']!==$before['size']||$after['mtime']!==$before['mtime'])throw new RuntimeException('Theme file changed during read.');return ['content'=>$content,'stat'=>$after];}finally{fclose($handle);}}

    private static function list_theme_files(): array {
        $root_real=realpath(get_stylesheet_directory());if($root_real===false||is_link(get_stylesheet_directory())||!is_dir($root_real))throw new RuntimeException('Unable to resolve the active theme root.');$root=wp_normalize_path($root_real);$items=[];$max_files=1000;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){if(count($items)>=$max_files)break;if(!$file->isFile()||$file->isLink())continue;$path=wp_normalize_path($file->getPathname());$relative=ltrim(substr($path,strlen($root)),'/');if(preg_match('/\.(php|css|js|json|svg|html|txt|md)$/i',$relative))$items[]=['path'=>$relative,'bytes'=>$file->getSize(),'modified_at'=>gmdate('c',$file->getMTime())];}
        usort($items,static fn($a,$b)=>strcmp($a['path'],$b['path'])); return ['theme'=>get_stylesheet(),'files'=>$items];
    }

    private static function read_theme_file(array $args): array {
        if(!current_user_can('edit_themes'))throw new RuntimeException('The MCP service user cannot read theme source.');
        $path=self::active_theme_file($args['path']);$read=self::secure_theme_read($path,2*MB_IN_BYTES);
        return ['path'=>$args['path'],'content'=>$read['content'],'bytes'=>strlen($read['content']),'sha256'=>hash('sha256',$read['content'])];
    }

    private static function patch_theme_file(array $args): array {
        self::require_confirm($args); if(!current_user_can('edit_themes')||!current_user_can('edit_theme_options'))throw new RuntimeException('The MCP service user cannot modify theme assets under the current WordPress file-edit policy.'); $path=self::active_theme_file($args['path']); if(!is_file($path)||!is_writable($path))throw new RuntimeException('Theme file not found or not writable.');
        if(!preg_match('/\.(css|json|txt|md)$/i',$path))throw new InvalidArgumentException('Only non-executable CSS, JSON, TXT and MD files can be modified through MCP.');
        $old=(string)$args['old_string'];if($old==='')throw new InvalidArgumentException('old_string cannot be empty.');$read=self::secure_theme_read($path,2*MB_IN_BYTES);$content=$read['content'];$original_stat=$read['stat'];$original_hash=hash('sha256',$content);$parent_path=dirname($path);$parent_stat=lstat($parent_path);if($parent_stat===false||is_link($parent_path)||(($parent_stat['mode']&0170000)!==0040000))throw new RuntimeException('Theme parent directory is unsafe.');$count=substr_count($content,$old);
        if($count===0)throw new RuntimeException('old_string was not found.'); if(empty($args['replace_all'])&&$count!==1)throw new RuntimeException('old_string is not unique; use a larger match or replace_all=true.');
        $replacement=(string)$args['new_string']; $updated=empty($args['replace_all'])?preg_replace('/'.preg_quote($old,'/').'/',str_replace(['\\','$'],['\\\\','\\$'],$replacement),$content,1):str_replace($old,$replacement,$content);
        if(strlen($updated)>2*MB_IN_BYTES)throw new RuntimeException('Updated file exceeds the 2 MB write limit.');
        if(strtolower(pathinfo($path,PATHINFO_EXTENSION))==='json'){json_decode($updated,true);if(json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException('Updated JSON is invalid: '.json_last_error_msg());}
        Xsofty_MCP_Backups::protect_directory();
        $snapshot=Xsofty_MCP_Backups::snapshots_directory().'/'.gmdate('Ymd-His').'-'.wp_generate_uuid4().'/'.ltrim((string)$args['path'],'/');
        $snapshot_dir=dirname($snapshot);if(!wp_mkdir_p($snapshot_dir)||is_link($snapshot_dir)||!chmod($snapshot_dir,0700)||(fileperms($snapshot_dir)&0777)!==0700||file_put_contents($snapshot,$content,LOCK_EX)!==strlen($content)||hash_file('sha256',$snapshot)!==hash('sha256',$content))throw new RuntimeException('Unable to create a verified private restore copy.');
        if(!chmod($snapshot,0600)||(fileperms($snapshot)&0777)!==0600)throw new RuntimeException('Unable to secure private restore copy.');$mode=$original_stat['mode']&0777;$temporary=tempnam(dirname($path),'.xwmcp-');
        if($temporary===false)throw new RuntimeException('Unable to create an atomic theme write.');
        $bytes=file_put_contents($temporary,$updated,LOCK_EX); if($bytes===false){@unlink($temporary);throw new RuntimeException('Unable to stage the updated theme file.');}
        if(is_link($temporary)||!chmod($temporary,$mode)){@unlink($temporary);throw new RuntimeException('Unable to secure staged theme update.');}clearstatcache(true,$path);self::active_theme_file($args['path']);$current=lstat($path);$current_parent=lstat($parent_path);$current_hash=is_file($path)?hash_file('sha256',$path):false;if($current===false||$current_parent===false||is_link($parent_path)||$current_parent['dev']!==$parent_stat['dev']||$current_parent['ino']!==$parent_stat['ino']||$current['dev']!==$original_stat['dev']||$current['ino']!==$original_stat['ino']||$current['size']!==$original_stat['size']||$current['mtime']!==$original_stat['mtime']||!is_string($current_hash)||!hash_equals($original_hash,$current_hash)){@unlink($temporary);throw new RuntimeException('Theme file changed before publish.');}
        if(!rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('Unable to publish the atomic theme update.');}
        return ['path'=>$args['path'],'replacements'=>empty($args['replace_all'])?1:$count,'bytes'=>$bytes,'sha256'=>hash_file('sha256',$path),'restore_copy_created'=>true];
    }

    private static function option_names(): array { return ['blogname','blogdescription','timezone_string','date_format','time_format','start_of_week','posts_per_page','permalink_structure','show_on_front','page_on_front','page_for_posts']; }

    private static function option_value(string $name, $value) {
        if(in_array($name,['blogname','blogdescription'],true))return sanitize_text_field((string)$value);
        if($name==='timezone_string'){$value=sanitize_text_field((string)$value);if($value!==''&&!in_array($value,timezone_identifiers_list(),true))throw new InvalidArgumentException('Invalid timezone identifier.');return $value;}
        if(in_array($name,['date_format','time_format'],true)){$value=sanitize_text_field((string)$value);if(strlen($value)>80||str_contains($value,'<'))throw new InvalidArgumentException('Invalid date or time format.');return $value;}
        if($name==='start_of_week')return max(0,min(6,(int)$value));
        if($name==='posts_per_page')return max(1,min(100,(int)$value));
        if($name==='show_on_front')return in_array($value,['posts','page'],true)?$value:'posts';
        if(in_array($name,['page_on_front','page_for_posts'],true)){$id=max(0,(int)$value);if($id&&!get_post($id))throw new InvalidArgumentException('Selected page does not exist.');return $id;}
        if($name==='permalink_structure'){$value=sanitize_text_field((string)$value);if($value!==''&&(!str_starts_with($value,'/')||!str_ends_with($value,'/')))throw new InvalidArgumentException('Permalink structure must begin and end with a slash.');return $value;}
        throw new InvalidArgumentException('Option is not approved for MCP access.');
    }

    public static function call(string $name, array $args): array {
        $definition=self::definition($name); if(!$definition)throw new InvalidArgumentException('Unknown MCP tool.');
        if(!Xsofty_MCP_Auth::tool_allowed($name))throw new RuntimeException('This MCP tool is not enabled for the active connection.');
        $scope=Xsofty_MCP_Auth::require_scope($definition['scope']); if(is_wp_error($scope))throw new RuntimeException($scope->get_error_message());
        try {
            switch($name){
                case 'site_info': $active=Xsofty_MCP_Auth::active_token(); $result=['name'=>get_bloginfo('name'),'url'=>home_url(),'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'environment'=>wp_get_environment_type(),'multisite'=>is_multisite(),'active_theme'=>['name'=>wp_get_theme()->get('Name'),'stylesheet'=>get_stylesheet(),'version'=>wp_get_theme()->get('Version')],'rest_url'=>rest_url(),'mcp_endpoint'=>rest_url('xsofty-mcp/v1/mcp'),'scopes'=>(array)($active['scopes']??[])]; break;
                case 'list_content': $result=self::list_content($args); break;
                case 'get_content': $result=self::get_content($args); break;
                case 'create_content': $result=self::create_content($args); break;
                case 'update_content': $result=self::update_content($args); break;
                case 'list_content_revisions': $result=self::list_content_revisions($args); break;
                case 'restore_content_revision': $result=self::restore_content_revision($args); break;
                case 'list_taxonomies': $result=self::list_taxonomies(); break;
                case 'list_terms': $result=self::list_terms($args); break;
                case 'create_term': $result=self::create_term($args); break;
                case 'update_term': $result=self::update_term($args); break;
                case 'assign_terms': $result=self::assign_terms($args); break;
                case 'get_registered_meta': $result=self::get_registered_meta($args); break;
                case 'update_registered_meta': $result=self::update_registered_meta($args); break;
                case 'list_menus': $result=self::list_menus(); break;
                case 'update_menu_item': $result=self::update_menu_item($args); break;
                case 'update_media_metadata': $result=self::update_media_metadata($args); break;
                case 'set_featured_image': $result=self::set_featured_image($args); break;
                case 'get_seo_status': $result=Xsofty_MCP_SEO::status(); break;
                case 'get_seo_metadata': $result=Xsofty_MCP_SEO::get_metadata((int)$args['id']); break;
                case 'update_seo_metadata': self::require_confirm($args); $result=Xsofty_MCP_SEO::update_metadata((int)$args['id'],(array)$args['values']); break;
                case 'list_redirects': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot manage redirects.'); $result=Xsofty_MCP_SEO::list_redirects(); break;
                case 'upsert_redirect': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot manage redirects.'); $result=Xsofty_MCP_SEO::upsert_redirect($args['source'],$args['target'],(int)$args['status']); break;
                case 'delete_redirect': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot manage redirects.'); $result=Xsofty_MCP_SEO::delete_redirect($args['id']); break;
                case 'get_sitemap_status': $status=Xsofty_MCP_SEO::status(); $result=['enabled'=>$status['core_sitemaps'],'url'=>$status['sitemap_url']]; break;
                case 'get_site_health': $result=Xsofty_MCP_Operations::health(); break;
                case 'list_cron_events': $result=Xsofty_MCP_Operations::cron_events((int)($args['limit']??100)); break;
                case 'run_cron_event': self::require_confirm($args); $result=Xsofty_MCP_Operations::run_cron($args['event_id']); break;
                case 'list_plugin_updates': if(!current_user_can('update_plugins'))throw new RuntimeException('The MCP service user cannot inspect plugin updates.'); $result=Xsofty_MCP_Operations::plugin_updates(); break;
                case 'update_plugin_safely': self::require_confirm($args); $result=Xsofty_MCP_Operations::update_plugin($args['plugin'],$args['expected_current_version'],$args['expected_new_version']); break;
                case 'list_plugin_checkpoints': if(!current_user_can('update_plugins'))throw new RuntimeException('The MCP service user cannot inspect plugin checkpoints.'); $result=Xsofty_MCP_Operations::list_checkpoints(); break;
                case 'restore_plugin_checkpoint': self::require_confirm($args); if(!current_user_can('update_plugins'))throw new RuntimeException('The MCP service user cannot restore plugin checkpoints.'); $result=Xsofty_MCP_Operations::restore_checkpoint($args['id']); break;
                case 'get_backup_schedule': $result=Xsofty_MCP_Jobs::schedule_settings(); break;
                case 'update_backup_schedule': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot change backup scheduling.'); $result=Xsofty_MCP_Jobs::update_schedule($args['schedule'],(int)$args['retention']); break;
                case 'start_backup_job': self::require_confirm($args); if(!current_user_can('export'))throw new RuntimeException('The MCP service user cannot create backups.'); $result=Xsofty_MCP_Jobs::start_backup((string)($args['label']??'manual')); break;
                case 'get_job_status': $result=Xsofty_MCP_Jobs::get_job($args['id']); break;
                case 'list_jobs': $result=Xsofty_MCP_Jobs::list_jobs((int)($args['limit']??20)); break;
                case 'cancel_job': self::require_confirm($args); $result=Xsofty_MCP_Jobs::cancel($args['id']); break;
                case 'delete_content': self::require_confirm($args); $target=get_post((int)$args['id']); if(!$target)throw new RuntimeException('Content record not found.'); self::allowed_post_type($target->post_type); if(!current_user_can('delete_post',$target->ID))throw new RuntimeException('The MCP service user cannot delete this content record.'); $deleted=wp_delete_post((int)$args['id'],!empty($args['force'])); if(!$deleted)throw new RuntimeException('Content record could not be deleted.'); $result=['id'=>(int)$args['id'],'deleted'=>true,'permanent'=>!empty($args['force'])]; break;
                case 'list_media': $result=self::list_media($args); break;
                case 'upload_media_from_url': $result=self::upload_media($args); break;
                case 'list_plugins': $result=self::list_plugins(); break;
                case 'install_plugin': $result=self::install_plugin($args); break;
                case 'activate_plugin': self::require_confirm($args); if(is_multisite()||!current_user_can('activate_plugins'))throw new RuntimeException('Plugin activation requires a single-site administrator.'); require_once ABSPATH.'wp-admin/includes/plugin.php'; $plugin=plugin_basename($args['plugin']); if($plugin===plugin_basename(XSOFTY_WP_MCP_FILE))throw new InvalidArgumentException('The MCP bridge manages its own activation state outside MCP.'); if(!isset(get_plugins()[$plugin]))throw new RuntimeException('Plugin is not installed.'); $e=activate_plugin($plugin); if(is_wp_error($e))throw new RuntimeException($e->get_error_message()); $result=['plugin'=>$plugin,'active'=>is_plugin_active($plugin)]; break;
                case 'deactivate_plugin': self::require_confirm($args); if(is_multisite()||!current_user_can('activate_plugins'))throw new RuntimeException('Plugin deactivation requires a single-site administrator.'); require_once ABSPATH.'wp-admin/includes/plugin.php'; $plugin=plugin_basename($args['plugin']); if($plugin===plugin_basename(XSOFTY_WP_MCP_FILE))throw new InvalidArgumentException('The MCP bridge cannot deactivate itself through MCP.'); if(!isset(get_plugins()[$plugin]))throw new RuntimeException('Plugin is not installed.'); deactivate_plugins($plugin); $result=['plugin'=>$plugin,'active'=>is_plugin_active($plugin)]; break;
                case 'list_theme_files': if(!current_user_can('edit_themes'))throw new RuntimeException('The MCP service user cannot read theme source.'); $result=self::list_theme_files(); break;
                case 'read_theme_file': $result=self::read_theme_file($args); break;
                case 'patch_theme_file': $result=self::patch_theme_file($args); break;
                case 'get_option': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot read settings.'); $option=sanitize_key($args['name']); if(!in_array($option,self::option_names(),true))throw new InvalidArgumentException('Option is not approved for MCP access.'); $result=['name'=>$option,'value'=>get_option($option)]; break;
                case 'update_option': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot update settings.'); $option=sanitize_key($args['name']); if(!in_array($option,self::option_names(),true))throw new InvalidArgumentException('Option is not approved for MCP access.'); $value=self::option_value($option,$args['value']); update_option($option,$value); if($option==='permalink_structure')flush_rewrite_rules(false); $result=['name'=>$option,'value'=>get_option($option)]; break;
                case 'create_backup': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot create backups.'); $result=Xsofty_MCP_Backups::create(sanitize_text_field($args['label']??'')); break;
                case 'list_backups': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot list backups.'); $result=['items'=>Xsofty_MCP_Backups::list()]; break;
                case 'get_backup_download_url': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot download backups.'); $filename=sanitize_file_name($args['filename']); $result=['filename'=>$filename,'url'=>Xsofty_MCP_Backups::signed_download_url($filename,(int)($args['ttl']??600)),'expires_in'=>max(60,min(3600,(int)($args['ttl']??600)))]; break;
                case 'delete_backup': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot delete backups.'); $result=['filename'=>sanitize_file_name($args['filename']),'deleted'=>Xsofty_MCP_Backups::delete(sanitize_file_name($args['filename']))]; break;
                case 'flush_rewrite_rules': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot flush rewrite rules.'); flush_rewrite_rules(false); $result=['flushed'=>true]; break;
                case 'clear_cache': self::require_confirm($args); if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot clear caches.'); wp_cache_flush(); do_action('w3tc_flush_all'); do_action('litespeed_purge_all'); if(function_exists('rocket_clean_domain'))rocket_clean_domain(); $result=['cleared'=>true]; break;
                case 'get_audit_log': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot read audit logs.'); $result=Xsofty_MCP_Control::query_audit($args); break;
                case 'export_audit_log': if(!current_user_can('manage_options'))throw new RuntimeException('The MCP service user cannot export audit logs.'); $result=Xsofty_MCP_Control::export_audit($args); break;
                case 'submit_approval_request': self::require_confirm($args); $result=Xsofty_MCP_Control::submit($args['tool'],(array)$args['arguments'],$args['summary']); break;
                case 'list_approval_requests': $result=Xsofty_MCP_Control::list_approvals((string)($args['status']??'all'),(int)($args['limit']??50)); break;
                case 'execute_approved_request': self::require_confirm($args); $result=Xsofty_MCP_Control::execute($args['id']); break;
                default: throw new InvalidArgumentException('Unsupported MCP tool.');
            }
            Xsofty_MCP_Auth::audit('tool_'.$name,true,['scope'=>$definition['scope']]);
            return $result;
        } catch(Throwable $error){Xsofty_MCP_Auth::audit('tool_'.$name,false,['scope'=>$definition['scope'],'error'=>substr($error->getMessage(),0,160)]);throw $error;}
    }
}
