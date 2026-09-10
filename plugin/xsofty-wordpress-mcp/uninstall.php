<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

$xsofty_mcp_jobs=(array)get_option('xsofty_mcp_jobs',[]);
foreach(array_keys($xsofty_mcp_jobs) as $job_id)wp_clear_scheduled_hook('xsofty_mcp_run_backup_job',[(string)$job_id]);

foreach ([
    'xsofty_mcp_settings',
    'xsofty_mcp_audit_log',
    'xsofty_mcp_token_usage',
    'xsofty_mcp_rate_limits',
    'xsofty_mcp_jobs',
    'xsofty_mcp_approval_queue',
    'xsofty_mcp_redirects',
    'xsofty_mcp_redirect_lock',
    'xsofty_mcp_plugin_checkpoints',
    'xsofty_mcp_policy_lock',
    'xwmcp_rate_database_lock',
    'xsofty_mcp_jobs_lock',
    'xsofty_mcp_backup_lock',
    'xsofty_mcp_approval_lock',
    'xsofty_mcp_version',
] as $option) delete_option($option);

wp_clear_scheduled_hook('xsofty_mcp_run_backup_job');
wp_clear_scheduled_hook('xsofty_mcp_scheduled_backup');
unset($xsofty_mcp_jobs);

// Private backup archives, plugin checkpoints and theme restore files are
// intentionally preserved for manual verification and removal.
