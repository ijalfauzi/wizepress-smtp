<?php
function wzp_email_logs_page() {
    global $wpdb;

    //  Ensure the table exists
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

    // Fetch logs
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY sent_at DESC LIMIT 100");

    echo '<table class="widefat"><thead><tr><th>Sent At</th><th>To</th><th>Subject</th><th>Action</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime(get_date_from_gmt($log->sent_at)))) . '</td>';
        echo '<td>' . esc_html(implode(", ", (array) maybe_unserialize($log->to_email))) . '</td>';
        echo '<td>' . esc_html($log->subject) . '</td>';
        echo '<td><button class="button view-email" data-id="' . esc_attr($log->id) . '">View</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    ?>

    <div id="email-log-modal-overlay">
        <div id="email-log-modal">
            <button id="email-log-modal-close" aria-label="Close Modal">&times;</button>
            <h2>Email Content</h2>
            <table class="widefat striped">
                <tr><th><strong>Sent at</strong></th><td id="log-sent-at"></td></tr>
                <tr><th><strong>To</strong></th><td id="log-to"></td></tr>
                <tr><th><strong>Subject</strong></th><td id="log-subject"></td></tr>
            </table>

            <div class="modal-toolbar">
                <button class="button" id="toggle-raw">Raw Email Content</button>
                <button class="button" id="toggle-html">Preview Content as HTML</button>
            </div>

            <textarea id="email-raw" readonly></textarea>
            <iframe id="email-html-preview"></iframe>
        </div>
    </div>
<?php
}
