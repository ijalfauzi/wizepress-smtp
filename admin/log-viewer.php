<?php
function wzp_email_logs_page() {
    global $wpdb;

    $table = WZP_SMTP_TABLE;

    // Create the table if it doesn't exist
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
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
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Fetch logs
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY sent_at DESC LIMIT 100");

    echo '<table class="widefat"><thead><tr><th>Sent At</th><th>To</th><th>Subject</th><th>Message</th><th>Attachments</th><th>IP Address</th><th>Result</th></tr></thead>
            <tbody>';

    foreach ($logs as $log) {
        $to = is_serialized($log->to_email) ? maybe_unserialize($log->to_email) : $log->to_email;
        $to_display = is_array($to) ? implode(', ', $to) : $to;

        $headers = is_serialized($log->headers) ? maybe_unserialize($log->headers) : $log->headers;
        $headers_display = is_array($headers) ? implode("\n", $headers) : $headers;

        $attachments = is_serialized($log->attachments) ? maybe_unserialize($log->attachments) : $log->attachments;
        $attachment_count = is_array($attachments) ? count($attachments) : $attachments;

        $user = $log->user_id ? get_userdata($log->user_id) : null;

        echo '<tr>';
        echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->sent_at))) . '</td>';
        echo '<td>' . esc_html($to_display) . '</td>';
        echo '<td>' . esc_html($log->subject) . '</td>';
        echo '<td><button class="button view-email" data-id="' . esc_attr($log->id) . '">View</button></td>';
        echo '<td>' . esc_html($attachment_count);
        if (!empty($log->attachment_name)) {
            echo '<br><small>' . esc_html($log->attachment_name) . '</small>';
        }
        echo '</td>';
        echo '<td>' . esc_html($log->ip_address ?? '—') . '</td>';
        echo '<td>' . ($log->result ? '✅' : '❌') . '</td>';
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
