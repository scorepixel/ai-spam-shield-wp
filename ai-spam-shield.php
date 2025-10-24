<?php

/**
 * Plugin Name: AI Spam Shield
 * Plugin URI: https://scorepixel.com
 * Description: Advanced spam filtering for WordPress comments and contact forms using AI-powered analysis.
 * Version: 1.0.0
 * Author: Scorepixel
 * Author URI: https://scorepixel.com
 * License: GPL v2 or later
 * Text Domain: ai-spam-shield
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPAM_FILTER_API_VERSION', '1.0.0');
define('SPAM_FILTER_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPAM_FILTER_API_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once SPAM_FILTER_API_PLUGIN_DIR . 'includes/class-spam-filter-api.php';
require_once SPAM_FILTER_API_PLUGIN_DIR . 'includes/class-spam-filter-settings.php';
require_once SPAM_FILTER_API_PLUGIN_DIR . 'includes/class-spam-filter-admin.php';

// Initialize the plugin
function spam_filter_api_init()
{
    $plugin = new Spam_Filter_API();
    $plugin->init();
}

add_action('plugins_loaded', 'spam_filter_api_init');

// Activation hook
register_activation_hook(__FILE__, 'spam_filter_api_activate');

function spam_filter_api_activate()
{
    // Create options with default values
    add_option('spam_filter_api_url', 'http://localhost:3000/check-spam');
    add_option('spam_filter_api_key', '');
    add_option('spam_filter_api_threshold', 0.6);
    add_option('spam_filter_api_enabled', true);
    add_option('spam_filter_api_check_comments', true);
    add_option('spam_filter_api_check_contact_forms', true);
    add_option('spam_filter_api_log_enabled', true);
    add_option('spam_filter_api_timeout', 5);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'spam_filter_api_deactivate');

function spam_filter_api_deactivate()
{
    // Cleanup if needed
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'spam_filter_api_uninstall');

function spam_filter_api_uninstall()
{
    // Remove options
    delete_option('spam_filter_api_url');
    delete_option('spam_filter_api_key');
    delete_option('spam_filter_api_threshold');
    delete_option('spam_filter_api_enabled');
    delete_option('spam_filter_api_check_comments');
    delete_option('spam_filter_api_check_contact_forms');
    delete_option('spam_filter_api_log_enabled');
    delete_option('spam_filter_api_timeout');

    // Remove logs table
    global $wpdb;
    $table_name = $wpdb->prefix . 'spam_filter_logs';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
