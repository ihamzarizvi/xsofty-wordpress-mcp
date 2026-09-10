<?php
$root=dirname(__DIR__).'/plugin/xsofty-wordpress-mcp/includes';
function hardening_expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$admin=file_get_contents($root.'/class-xsofty-mcp-admin.php');$auth=file_get_contents($root.'/class-xsofty-mcp-auth.php');$backups=file_get_contents($root.'/class-xsofty-mcp-backups.php');$tools=file_get_contents($root.'/class-xsofty-mcp-tools.php');
hardening_expect(!str_contains($admin,"set_transient('xsofty_mcp_token_"),'Plaintext tokens must not be stored in transients.');
hardening_expect(str_contains($auth,"empty(\$settings['allow_http'])"),'HTTP must require explicit opt-in.');
hardening_expect(!str_contains($auth,'!self::is_local_request()'),'Local hostname must not bypass HTTP opt-in.');
hardening_expect(str_contains($backups,'realpath')&&str_contains($backups,'is_link')&&str_contains($backups,'fileperms'),'Private storage must be canonical, reject symlinks and verify permissions.');
hardening_expect(str_contains($backups,'user_can($actor')&&str_contains($backups,"'manage_options'")&&str_contains($backups,"'backups'")&&str_contains($backups,'record_allows_tool'),'Downloads must revalidate actor, scope and tool policy.');
hardening_expect(str_contains($tools,"current_user_can('read_post_meta'")&&str_contains($tools,"current_user_can('edit_post_meta'"),'Registered meta callbacks must be enforced.');
hardening_expect(str_contains($tools,'Unable to resolve the active theme root.'),'Theme root must fail closed.');
hardening_expect(!str_contains($tools,"'restore_copy'=>wp_normalize_path(\$snapshot)"),'Theme snapshot path must not be returned.');
print("REVIEW_SECURITY_BOUNDARY_CONTRACT_PASS\n");
