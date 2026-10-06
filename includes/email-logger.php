<?php
/**
 * Email logging, log AJAX handlers and log retention.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

// Error from the most recent failed send in this request
global $modifus_smtp_last_error;
$modifus_smtp_last_error = '';

// Log successful emails
add_action('wp_mail_succeeded', function ($mail) {
    modifus_smtp_insert_log($mail, true, null);
});

// Log failed emails
add_action('wp_mail_failed', function ($wp_error) {
    global $modifus_smtp_last_error;

    $mail = $wp_error->get_error_data();
    $mail = is_array($mail) ? $mail : [];

    $error_msg = $wp_error->get_error_message() ?: __('Unknown error', 'modifus-smtp');
    $modifus_smtp_last_error = $error_msg;

    modifus_smtp_insert_log($mail, false, $error_msg);
});

/**
 * Insert email log entry
 */
function modifus_smtp_insert_log($mail, $success = true, $error = null) {
    global $wpdb, $modifus_smtp_current_mail;

    $to_email_raw    = $mail['to'] ?? '';
    $to_email        = is_array($to_email_raw) ? implode(', ', $to_email_raw) : $to_email_raw;

    $headers_raw     = $mail['headers'] ?? '';
    $headers         = is_array($headers_raw) ? implode("\n", $headers_raw) : $headers_raw;

    // Prefer what PHPMailer actually used; fall back to the headers when
    // sending failed before PHPMailer was set up.
    $from_email      = $modifus_smtp_current_mail['from'] ?? modifus_smtp_extract_from_header($headers_raw);

    $attachments_raw = $mail['attachments'] ?? [];
    $attachments_raw = is_array($attachments_raw) ? $attachments_raw : array_filter(explode("\n", str_replace("\r\n", "\n", (string) $attachments_raw)));
    $attachments     = count($attachments_raw);
    $attachment_name = implode(', ', $attachments_raw);

    $subject         = $mail['subject'] ?? '';
    $message         = $mail['message'] ?? '';

    $remote_addr     = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip_address      = filter_var($remote_addr, FILTER_VALIDATE_IP) ?: '';
    $user_id         = get_current_user_id();

    if (!empty($modifus_smtp_current_mail['content_type'])) {
        $content_type = $modifus_smtp_current_mail['content_type'];
    } else {
        $content_type = (is_string($headers) && stripos($headers, 'Content-Type: text/html') !== false)
            ? 'text/html'
            : 'text/plain';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin's own log table.
    $wpdb->insert(modifus_smtp_table(), [
        'to_email'        => $to_email,
        'from_email'      => sanitize_email($from_email),
        'subject'         => sanitize_text_field($subject),
        'message'         => $message,
        'headers'         => $headers,
        'attachments'     => $attachments,
        'attachment_name' => sanitize_text_field($attachment_name),
        'sent_at'         => current_time('mysql'),
        'ip_address'      => $ip_address,
        'result'          => $success ? 1 : 0,
        'error_message'   => $error ? sanitize_text_field($error) : null,
        'user_id'         => $user_id,
        'content_type'    => sanitize_text_field($content_type)
    ]);
}

/**
 * Extract From address from headers
 */
function modifus_smtp_extract_from_header($headers) {
    if (is_string($headers)) {
        $headers = explode("\n", str_replace("\r\n", "\n", $headers));
    }
    foreach ((array) $headers as $header) {
        if (stripos($header, 'From:') === 0) {
            if (preg_match('/<(.+?)>/', $header, $matches)) {
                return $matches[1];
            }
            return trim(substr($header, 5));
        }
    }
    return '';
}

/**
 * Format a stored sent_at value (site local time) for display.
 */
function modifus_smtp_format_date($sent_at, $format = null) {
    $format = $format ?: get_option('date_format') . ' ' . get_option('time_format');
    return mysql2date($format, $sent_at);
}

/**
 * Read a stored to_email value (older versions stored serialized arrays).
 */
function modifus_smtp_log_recipients($to_email) {
    $to = is_serialized($to_email) ? maybe_unserialize($to_email) : $to_email;
    return is_array($to) ? implode(', ', $to) : (string) $to;
}

/**
 * Read a stored headers value (older versions stored serialized arrays).
 */
function modifus_smtp_log_headers($headers) {
    $headers = is_serialized($headers) ? maybe_unserialize($headers) : $headers;
    return is_array($headers) ? implode("\r\n", $headers) : (string) $headers;
}

// AJAX: Get email log
add_action('wp_ajax_modifus_smtp_get_email_log', function () {
    if (!check_ajax_referer('modifus_smtp_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(__('Invalid security token.', 'modifus-smtp'));
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'modifus-smtp'));
    }

    global $wpdb;
    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $log = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM %i WHERE id = %d', modifus_smtp_table(), $id),
        ARRAY_A
    );

    if (!$log) {
        wp_send_json_error(__('Log not found.', 'modifus-smtp'));
    }

    wp_send_json_success([
        'to'            => modifus_smtp_log_recipients($log['to_email']),
        'subject'       => $log['subject'] ?? '',
        'message'       => $log['message'] ?? '',
        'headers'       => modifus_smtp_log_headers($log['headers']),
        'sent_at'       => modifus_smtp_format_date($log['sent_at']),
        'result'        => (int) $log['result'],
        'error_message' => $log['error_message'] ?? ''
    ]);
});

// AJAX: Delete email log
add_action('wp_ajax_modifus_smtp_delete_email_log', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'modifus-smtp'));
    }

    $id    = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'modifus_smtp_delete_log_' . $id)) {
        wp_send_json_error(__('Invalid security token.', 'modifus-smtp'));
    }

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $result = $wpdb->delete(
        modifus_smtp_table(),
        ['id' => $id],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(__('Failed to delete log.', 'modifus-smtp'));
    }

    wp_send_json_success(__('Log deleted successfully.', 'modifus-smtp'));
});

// AJAX: Resend email
add_action('wp_ajax_modifus_smtp_resend_email', function () {
    if (!check_ajax_referer('modifus_smtp_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(__('Invalid security token.', 'modifus-smtp'));
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'modifus-smtp'));
    }

    global $wpdb, $modifus_smtp_last_error;
    $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
    $log = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM %i WHERE id = %d', modifus_smtp_table(), $id),
        ARRAY_A
    );

    if (!$log) {
        wp_send_json_error(__('Log not found.', 'modifus-smtp'));
    }

    // Re-attach files that are still on disk
    $attachments = [];
    $missing     = 0;
    if (!empty($log['attachment_name'])) {
        foreach (explode(', ', $log['attachment_name']) as $path) {
            if (is_readable($path)) {
                $attachments[] = $path;
            } else {
                $missing++;
            }
        }
    }

    // Logged by the wp_mail hooks like any other email
    $modifus_smtp_last_error = '';
    $result = wp_mail(
        modifus_smtp_log_recipients($log['to_email']),
        $log['subject'],
        $log['message'],
        modifus_smtp_log_headers($log['headers']),
        $attachments
    );

    if (!$result) {
        wp_send_json_error(
            /* translators: %s: error message */
            sprintf(__('Failed to resend email: %s', 'modifus-smtp'), $modifus_smtp_last_error ?: __('Unknown error', 'modifus-smtp'))
        );
    }

    $message = __('Email resent successfully!', 'modifus-smtp');
    if ($missing) {
        $message .= ' ' . sprintf(
            /* translators: %d: number of attachments */
            _n('%d attachment no longer exists and was not included.', '%d attachments no longer exist and were not included.', $missing, 'modifus-smtp'),
            $missing
        );
    }
    wp_send_json_success($message);
});

// Log retention: delete logs older than the configured number of days
add_action('modifus_smtp_cleanup_logs', 'modifus_smtp_cleanup_logs');
function modifus_smtp_cleanup_logs() {
    global $wpdb;

    $days = absint(modifus_smtp_get_settings()['log_retention_days']);
    if ($days < 1) {
        return;
    }

    // sent_at is stored in site local time
    $cutoff = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

    // Delete in batches to avoid long table locks
    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
        $deleted = $wpdb->query(
            $wpdb->prepare('DELETE FROM %i WHERE sent_at < %s LIMIT 1000', modifus_smtp_table(), $cutoff)
        );
    } while ($deleted === 1000);
}

/**
 * Log filters from the query string, shared by the log list and exports.
 *
 * They only narrow which logs are shown, so like core's list table filters
 * they are read without a nonce. Exports check their own nonce first.
 */
function modifus_smtp_logs_filters() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
    $month  = isset($_GET['m']) ? sanitize_text_field(wp_unslash($_GET['m'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if (!in_array($status, ['success', 'failed'], true)) {
        $status = '';
    }
    if (!preg_match('/^(\d{4})(\d{2})$/', $month, $matches) || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
        $month = '';
    }

    return ['search' => $search, 'status' => $status, 'month' => $month];
}

/**
 * Values for the filter placeholders in the log queries:
 *
 *   (%s = '' OR to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)  like x4
 *   AND (%d < 0 OR result = %d)                                             result x2
 *   AND (%d = 0 OR (sent_at >= %s AND sent_at < %s))                        has_month, start, end
 *
 * A filter that isn't set turns its condition into a constant true, which
 * MySQL drops, so the month range can still use the sent_at index.
 */
function modifus_smtp_logs_filter_args($filters) {
    global $wpdb;

    $like = $filters['search'] === '' ? '' : '%' . $wpdb->esc_like($filters['search']) . '%';

    $result = -1;
    if ($filters['status'] === 'success') {
        $result = 1;
    } elseif ($filters['status'] === 'failed') {
        $result = 0;
    }

    // Month filter as a range so the sent_at index is used
    $start = '1000-01-01 00:00:00';
    $end   = '9999-12-31 23:59:59';
    if ($filters['month'] !== '') {
        $year  = (int) substr($filters['month'], 0, 4);
        $month = (int) substr($filters['month'], 4, 2);
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end   = $month === 12
            ? sprintf('%04d-01-01 00:00:00', $year + 1)
            : sprintf('%04d-%02d-01 00:00:00', $year, $month + 1);
    }

    return [
        'like'      => $like,
        'result'    => $result,
        'has_month' => $filters['month'] !== '' ? 1 : 0,
        'start'     => $start,
        'end'       => $end,
    ];
}

/**
 * Number of logs matching the filters.
 */
function modifus_smtp_count_logs($filters) {
    global $wpdb;

    $f = modifus_smtp_logs_filter_args($filters);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table; changes with every email sent.
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i
             WHERE (%s = '' OR to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)
             AND (%d < 0 OR result = %d)
             AND (%d = 0 OR (sent_at >= %s AND sent_at < %s))",
            modifus_smtp_table(),
            $f['like'], $f['like'], $f['like'], $f['like'],
            $f['result'], $f['result'],
            $f['has_month'], $f['start'], $f['end']
        )
    );
}

/**
 * Logs matching the filters.
 *
 * @param array  $filters From modifus_smtp_logs_filters().
 * @param string $orderby Column to sort by: sent_at, to_email, subject or result.
 * @param string $order   ASC or DESC.
 * @param int    $limit   Maximum number of logs.
 * @param int    $offset  Number of logs to skip.
 * @param string $output  OBJECT or ARRAY_A.
 */
function modifus_smtp_get_logs($filters, $orderby = 'sent_at', $order = 'DESC', $limit = PHP_INT_MAX, $offset = 0, $output = OBJECT) {
    global $wpdb;

    if (!in_array($orderby, ['sent_at', 'to_email', 'subject', 'result'], true)) {
        $orderby = 'sent_at';
    }

    $table = modifus_smtp_table();
    $f     = modifus_smtp_logs_filter_args($filters);

    if ($order === 'ASC') {
        $sql = $wpdb->prepare(
            "SELECT * FROM %i
             WHERE (%s = '' OR to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)
             AND (%d < 0 OR result = %d)
             AND (%d = 0 OR (sent_at >= %s AND sent_at < %s))
             ORDER BY %i ASC, id ASC LIMIT %d OFFSET %d",
            $table,
            $f['like'], $f['like'], $f['like'], $f['like'],
            $f['result'], $f['result'],
            $f['has_month'], $f['start'], $f['end'],
            $orderby, $limit, $offset
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT * FROM %i
             WHERE (%s = '' OR to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)
             AND (%d < 0 OR result = %d)
             AND (%d = 0 OR (sent_at >= %s AND sent_at < %s))
             ORDER BY %i DESC, id DESC LIMIT %d OFFSET %d",
            $table,
            $f['like'], $f['like'], $f['like'], $f['like'],
            $f['result'], $f['result'],
            $f['has_month'], $f['start'], $f['end'],
            $orderby, $limit, $offset
        );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Plugin's own log table; $sql is prepared just above.
    return $wpdb->get_results($sql, $output);
}
