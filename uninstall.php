<?php
/**
 * Modifus SMTP Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Cleans up database tables and options.
 *
 * @package Modifus_SMTP
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete the email logs table
$table_name = $wpdb->prefix . 'modifus_smtp_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
delete_option('modifus_smtp_settings');
delete_option('modifus_smtp_db_version');

// Delete user meta for screen options
delete_metadata('user', 0, 'modifus_smtp_logs_per_page', '', true);
