<?php
function wzp_create_email_log_table() {
    global $wpdb;
    $table = WZP_SMTP_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        to_email TEXT NOT NULL,
        subject TEXT NOT NULL,
        headers TEXT,
        attachments TEXT,
        message LONGTEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'wzp_create_email_log_table');

function wzp_log_sent_email($mail) {
    global $wpdb;
    $wpdb->insert(WZP_SMTP_TABLE, [
        'to_email'    => maybe_serialize($mail['to']),
        'subject'     => $mail['subject'],
        'headers'     => maybe_serialize($mail['headers']),
        'attachments' => maybe_serialize($mail['attachments']),
        'message'     => $mail['message']
    ]);
}
add_action('wp_mail_succeeded', 'wzp_log_sent_email');

add_action('wp_ajax_wzp_get_email_log', function () {
    global $wpdb;
    $id = intval($_GET['id']);
    $log = $wpdb->get_row("SELECT * FROM " . WZP_SMTP_TABLE . " WHERE id = $id", ARRAY_A);
    wp_send_json([
        'to' => implode(', ', maybe_unserialize($log['to_email'])),
        'subject' => $log['subject'],
        'message' => $log['message'],
        'sent_at' => date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime(get_date_from_gmt($log['sent_at']))
        )
    ]);
});
