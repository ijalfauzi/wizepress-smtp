<?php
// Handle exports before headers are sent
add_action('admin_init', 'wzp_handle_export');
function wzp_handle_export() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'wzp-smtp') {
        return;
    }
    
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    
    if (in_array($action, ['export_csv', 'export_excel', 'export_print'], true)) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wzp_export')) {
            wp_die('Invalid security token.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized.');
        }
        
        $logs = wzp_get_export_logs();
        $filename = wzp_get_export_filename();
        
        switch ($action) {
            case 'export_csv':
                wzp_export_csv($logs, $filename);
                break;
            case 'export_excel':
                wzp_export_excel($logs, $filename);
                break;
            case 'export_print':
                wzp_export_print($logs);
                break;
        }
    }
}

function wzp_email_logs_page() {
    $logs_table = new WZP_Email_Logs_Table();
    $logs_table->prepare_items();
    ?>
    <div class="wzp-email-logs-wrap">
        <form method="get">
            <input type="hidden" name="page" value="wzp-smtp" />
            <input type="hidden" name="tab" value="logs" />
            <?php
            $logs_table->search_box('Search Emails', 'wzp-search');
            $logs_table->display();
            ?>
        </form>
    </div>

    <div id="email-log-modal-overlay">
        <div id="email-log-modal">
            <button id="email-log-modal-close" aria-label="Close Modal">&times;</button>
            <h2>Email Content</h2>
            <table class="widefat striped">
                <tr><th>Sent at</th><td id="log-sent-at"></td></tr>
                <tr><th>To</th><td id="log-to"></td></tr>
                <tr><th>Subject</th><td id="log-subject"></td></tr>
                <tr id="log-error-row" style="display:none;"><th>Error</th><td id="log-error-message" class="wzp-error-text"></td></tr>
            </table>

            <div class="modal-toolbar">
                <button class="button" id="toggle-raw">Raw Email Content</button>
                <button class="button" id="toggle-html">Preview Content as HTML</button>
            </div>

            <textarea id="email-raw" readonly></textarea>
            <iframe id="email-html-preview" sandbox="allow-same-origin"></iframe>
        </div>
    </div>
<?php
}

/**
 * Get logs for export with current filters
 */
function wzp_get_export_logs() {
    global $wpdb;
    $table = WZP_SMTP_TABLE;

    $where = '1=1';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(
            " AND (to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)",
            $search_like,
            $search_like,
            $search_like
        );
    }

    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    if ($status_filter === 'success') {
        $where .= ' AND result = 1';
    } elseif ($status_filter === 'failed') {
        $where .= ' AND result = 0';
    }

    $date_filter = isset($_GET['m']) ? sanitize_text_field($_GET['m']) : '';
    if (!empty($date_filter) && preg_match('/^(\d{4})(\d{2})$/', $date_filter, $matches)) {
        $where .= $wpdb->prepare(
            " AND YEAR(sent_at) = %d AND MONTH(sent_at) = %d",
            $matches[1],
            $matches[2]
        );
    }

    return $wpdb->get_results(
        "SELECT * FROM $table WHERE $where ORDER BY sent_at ASC, id ASC",
        ARRAY_A
    );
}

/**
 * Generate descriptive filename for exports
 */
function wzp_get_export_filename() {
    $parts = ['email-logs'];
    
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    if (!empty($status)) {
        $parts[] = $status;
    }
    
    $date = isset($_GET['m']) ? sanitize_text_field($_GET['m']) : '';
    if (!empty($date) && preg_match('/^(\d{4})(\d{2})$/', $date, $m)) {
        $parts[] = $m[1] . '-' . $m[2];
    }
    
    $parts[] = date('Y-m-d');
    
    return implode('-', $parts);
}

/**
 * Format log row for export
 */
function wzp_format_log_row($log) {
    $to = is_serialized($log['to_email']) ? maybe_unserialize($log['to_email']) : $log['to_email'];
    $to = is_array($to) ? implode(', ', $to) : $to;
    
    return [
        'id'         => $log['id'],
        'sent_at'    => $log['sent_at'],
        'status'     => $log['result'] ? 'Success' : 'Failed',
        'to'         => $to,
        'subject'    => $log['subject'] ?? '',
        'attachments'=> $log['attachments'] ?? 0,
        'ip_address' => $log['ip_address'] ?? '',
        'error'      => $log['error_message'] ?? ''
    ];
}

/**
 * Export as CSV
 */
function wzp_export_csv($logs, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['ID', 'Sent At', 'Status', 'To', 'Subject', 'Attachments', 'IP Address', 'Error Message'], ',', '"', '\\');

    foreach ($logs as $log) {
        $row = wzp_format_log_row($log);
        fputcsv($output, array_values($row), ',', '"', '\\');
    }

    fclose($output);
    exit;
}

/**
 * Export as Excel (HTML table format)
 */
function wzp_export_excel($logs, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body><table border="1">';
    echo '<tr><th>ID</th><th>Sent At</th><th>Status</th><th>To</th><th>Subject</th><th>Attachments</th><th>IP Address</th><th>Error Message</th></tr>';

    foreach ($logs as $log) {
        $row = wzp_format_log_row($log);
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . esc_html($cell) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

/**
 * Export as Print-friendly HTML page
 */
function wzp_export_print($logs) {
    $site_name = get_bloginfo('name');
    $export_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
    $total_logs = count($logs);
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Logs - <?php echo esc_html($site_name); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; color: #1d2327; }
        h1 { font-size: 24px; margin-bottom: 5px; }
        .meta { color: #646970; font-size: 13px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #c3c4c7; padding: 8px; text-align: left; }
        th { background: #f0f0f1; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-success { color: #00a32a; }
        .status-failed { color: #d63638; }
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" style="padding:8px 16px;font-size:14px;cursor:pointer;">🖨️ Print / Save as PDF</button>
        <button onclick="window.close()" style="padding:8px 16px;font-size:14px;cursor:pointer;margin-left:10px;">Close</button>
    </div>
    <h1>Email Logs</h1>
    <p class="meta"><?php echo esc_html($site_name); ?> • Exported on <?php echo esc_html($export_date); ?> • <?php echo $total_logs; ?> records</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Sent At</th>
                <th>Status</th>
                <th>To</th>
                <th>Subject</th>
                <th>Attachments</th>
                <th>IP Address</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) : 
                $row = wzp_format_log_row($log);
            ?>
            <tr>
                <td><?php echo esc_html($row['id']); ?></td>
                <td><?php echo esc_html($row['sent_at']); ?></td>
                <td class="status-<?php echo $row['status'] === 'Success' ? 'success' : 'failed'; ?>"><?php echo esc_html($row['status']); ?></td>
                <td><?php echo esc_html($row['to']); ?></td>
                <td><?php echo esc_html($row['subject']); ?></td>
                <td><?php echo esc_html($row['attachments']); ?></td>
                <td><?php echo esc_html($row['ip_address']); ?></td>
                <td><?php echo esc_html($row['error']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
    exit;
}
