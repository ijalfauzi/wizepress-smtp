<?php
/**
 * WizePress SMTP Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Cleans up database tables and options.
 *
 * @package WizePress_SMTP
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete the email logs table
$table_name = $wpdb->prefix . 'wzp_email_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
delete_option('wzp_smtp_settings');
delete_option('wzp_smtp_db_version');

// Delete user meta for screen options
delete_metadata('user', 0, 'wzp_logs_per_page', '', true);
