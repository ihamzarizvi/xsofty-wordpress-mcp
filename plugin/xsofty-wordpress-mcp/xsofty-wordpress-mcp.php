<?php
/**
 * Plugin Name: Xsofty WordPress MCP Bridge
 * Plugin URI: https://xsofty.com
 * Description: Secure Model Context Protocol tools for managing WordPress content, media, plugins, themes, maintenance and backups.
 * Version: 0.3.1
 * Author: Hamza Rizvi, Xsofty
 * Author URI: https://xsofty.com
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xsofty-wordpress-mcp
 *
 * Copyright (C) 2026 Hamza Rizvi and Xsofty (Private) Limited.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('XSOFTY_WP_MCP_VERSION', '0.3.1');
define('XSOFTY_WP_MCP_FILE', __FILE__);
define('XSOFTY_WP_MCP_DIR', plugin_dir_path(__FILE__));

require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-auth.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-backups.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-seo.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-operations.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-jobs.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-control.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-tools.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-server.php';
require_once XSOFTY_WP_MCP_DIR . 'includes/class-xsofty-mcp-admin.php';

final class Xsofty_WordPress_MCP {
    public static function activate(): void {
        $settings = get_option(Xsofty_MCP_Auth::OPTION_KEY, []);
        update_option(Xsofty_MCP_Auth::OPTION_KEY, wp_parse_args($settings, Xsofty_MCP_Auth::defaults()), false);
        Xsofty_MCP_Backups::protect_directory();
    }

    public static function boot(): void {
        self::maybe_upgrade();
        Xsofty_MCP_Server::init();
        Xsofty_MCP_SEO::init();
        Xsofty_MCP_Jobs::init();
        Xsofty_MCP_Control::init();
        Xsofty_MCP_Admin::init();
    }

    private static function maybe_upgrade(): void {
        if (get_option('xsofty_mcp_version') === XSOFTY_WP_MCP_VERSION) return;
        $settings = Xsofty_MCP_Auth::settings();
        $legacy = (array) ($settings['scopes'] ?? []);
        if (array_intersect($legacy, ['read','content','theme'])) {
            $mapped = [];
            if (in_array('read',$legacy,true)) $mapped = array_merge($mapped,['site_read','content_read','media','plugins','theme_read','settings','audit']);
            if (in_array('content',$legacy,true)) $mapped[] = 'content_write';
            if (in_array('theme',$legacy,true)) $mapped = array_merge($mapped,['theme_read','theme_write']);
            foreach (['media','plugins','settings','backups','maintenance'] as $scope) if (in_array($scope,$legacy,true)) $mapped[] = $scope;
            $settings['scopes'] = array_values(array_unique($mapped));
            Xsofty_MCP_Auth::save($settings);
        }
        Xsofty_MCP_Auth::migrate_legacy_token();
        try { Xsofty_MCP_Backups::protect_directory(); }
        catch (Throwable $error) { Xsofty_MCP_Auth::audit('private_storage_unavailable', false, ['error'=>substr($error->getMessage(),0,160)]); }
        update_option('xsofty_mcp_version', XSOFTY_WP_MCP_VERSION, false);
    }
}

register_activation_hook(__FILE__, [Xsofty_WordPress_MCP::class, 'activate']);
add_action('plugins_loaded', [Xsofty_WordPress_MCP::class, 'boot']);
