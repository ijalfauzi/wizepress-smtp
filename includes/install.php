<?php
/**
 * Install, upgrade and WizePress SMTP migration.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

/**
 * Run the installer when the stored DB version is behind.
 *
 * Activation hooks don't fire on in-place updates, so this keeps the
 * schema current on every site after the files are replaced.
 */
function modifus_smtp_maybe_upgrade() {
    if (get_option('modifus_smtp_db_version') !== MODIFUS_SMTP_DB_VERSION) {
        modifus_smtp_install();
    }
}

/**
 * Whether the legacy WizePress SMTP plugin is loaded in this request.
 */
function modifus_smtp_legacy_active() {
    return defined('WZP_SMTP_VERSION');
}

/**
 * Migrate WizePress SMTP data, then create or upgrade the log table.
 */
function modifus_smtp_install() {
    global $wpdb;

    // Wait until WizePress SMTP is deactivated: it still writes to its table.
    if (modifus_smtp_legacy_active()) {
        return;
    }

    modifus_smtp_migrate_legacy();

    $table   = modifus_smtp_table();
    $charset = $wpdb->get_charset_collate();

    // dbDelta format: no IF NOT EXISTS, two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE $table (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        to_email TEXT NOT NULL,
        from_email TEXT,
        subject TEXT,
        message LONGTEXT,
        headers TEXT,
        attachments TEXT,
        attachment_name TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        result TINYINT(1) DEFAULT 1,
        error_message TEXT,
        user_id BIGINT(20),
        content_type VARCHAR(50),
        PRIMARY KEY  (id),
        KEY sent_at (sent_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    modifus_smtp_encrypt_stored_password();
    modifus_smtp_schedule_cleanup();

    update_option('modifus_smtp_db_version', MODIFUS_SMTP_DB_VERSION);
}

/**
 * Encrypt a password saved in plain text by an earlier version.
 */
function modifus_smtp_encrypt_stored_password() {
    $settings = get_option('modifus_smtp_settings', null);
    if (!is_array($settings) || empty($settings['smtp_pass']) || strpos($settings['smtp_pass'], 'enc:') === 0) {
        return;
    }
    $settings['smtp_pass'] = modifus_smtp_encrypt($settings['smtp_pass']);
    update_option('modifus_smtp_settings', $settings);
}

/**
 * Schedule the daily log cleanup.
 */
function modifus_smtp_schedule_cleanup() {
    if (!wp_next_scheduled('modifus_smtp_cleanup_logs')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'modifus_smtp_cleanup_logs');
    }
}

/**
 * Deactivation: stop the scheduled cleanup.
 */
function modifus_smtp_deactivate() {
    wp_clear_scheduled_hook('modifus_smtp_cleanup_logs');
}

/**
 * Carry settings, logs and screen options over from WizePress SMTP.
 */
function modifus_smtp_migrate_legacy() {
    global $wpdb;

    // Settings
    $legacy_settings = get_option('wzp_smtp_settings', null);
    if (is_array($legacy_settings)) {
        if (get_option('modifus_smtp_settings', null) === null) {
            add_option('modifus_smtp_settings', $legacy_settings);
        }
        // Don't leave a second copy of the SMTP password behind.
        delete_option('wzp_smtp_settings');
    }

    // Log table
    // phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-time migration of the plugin's own table.
    $legacy_table = $wpdb->prefix . 'wzp_email_logs';
    $legacy_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_table))) === $legacy_table;
    $new_table     = modifus_smtp_table();
    $new_exists    = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($new_table))) === $new_table;
    if ($legacy_exists && !$new_exists) {
        $wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $legacy_table, $new_table));
    }

    // Logs-per-page screen option. No API renames a meta key for all users.
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time update, keyed on meta_key.
    $wpdb->update(
        $wpdb->usermeta,
        ['meta_key' => 'modifus_smtp_logs_per_page'],
        ['meta_key' => 'wzp_logs_per_page']
    );
    // phpcs:enable
}

// Ask for WizePress SMTP to be deactivated while both are active.
add_action('admin_notices', function () {
    if (!modifus_smtp_legacy_active() || !current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-warning"><p><strong>Modifus SMTP</strong> '
        . esc_html__('replaces WizePress SMTP. Deactivate WizePress SMTP and your SMTP settings and email logs will move over automatically. You can delete WizePress SMTP after that.', 'modifus-smtp')
        . '</p></div>';
});
