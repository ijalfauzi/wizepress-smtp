<?php
/**
 * Modifus SMTP Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Cleans up database tables and options on every site.
 *
 * @package Modifus_SMTP
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function modifus_smtp_uninstall_site() {
    global $wpdb;

    // Delete the email logs table
    $table_name = $wpdb->prefix . 'modifus_smtp_logs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own table on uninstall.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table_name));

    // Delete plugin options
    delete_option('modifus_smtp_settings');
    delete_option('modifus_smtp_db_version');

    wp_clear_scheduled_hook('modifus_smtp_cleanup_logs');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $modifus_smtp_site_id) {
        switch_to_blog($modifus_smtp_site_id);
        modifus_smtp_uninstall_site();
        restore_current_blog();
    }
} else {
    modifus_smtp_uninstall_site();
}

// Delete user meta for screen options (shared across the network)
delete_metadata('user', 0, 'modifus_smtp_logs_per_page', '', true);
