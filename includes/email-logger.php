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

    $ip_address      = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';
    $user_id         = get_current_user_id();

    if (!empty($modifus_smtp_current_mail['content_type'])) {
        $content_type = $modifus_smtp_current_mail['content_type'];
    } else {
        $content_type = (is_string($headers) && stripos($headers, 'Content-Type: text/html') !== false)
            ? 'text/html'
            : 'text/plain';
    }

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
    $id = intval($_GET['id'] ?? 0);

    $log = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM " . modifus_smtp_table() . " WHERE id = %d", $id),
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

    $id = intval($_POST['id'] ?? 0);
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');

    if (!wp_verify_nonce($nonce, 'modifus_smtp_delete_log_' . $id)) {
        wp_send_json_error(__('Invalid security token.', 'modifus-smtp'));
    }

    global $wpdb;

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
    $id = intval($_POST['id'] ?? 0);

    $log = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM " . modifus_smtp_table() . " WHERE id = %d", $id),
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
        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM " . modifus_smtp_table() . " WHERE sent_at < %s LIMIT 1000", $cutoff)
        );
    } while ($deleted === 1000);
}

/**
 * Build the WHERE clause for the log list and exports from request filters.
 */
function modifus_smtp_logs_where($request) {
    global $wpdb;

    $where = '1=1';

    $search = isset($request['s']) ? sanitize_text_field(wp_unslash($request['s'])) : '';
    if ($search !== '') {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(
            " AND (to_email LIKE %s OR subject LIKE %s OR from_email LIKE %s)",
            $search_like,
            $search_like,
            $search_like
        );
    }

    $status = isset($request['status']) ? sanitize_key($request['status']) : '';
    if ($status === 'success') {
        $where .= ' AND result = 1';
    } elseif ($status === 'failed') {
        $where .= ' AND result = 0';
    }

    // Month filter as a range so the sent_at index is used
    $month = isset($request['m']) ? sanitize_text_field($request['m']) : '';
    if (preg_match('/^(\d{4})(\d{2})$/', $month, $matches) && (int) $matches[2] >= 1 && (int) $matches[2] <= 12) {
        $start = sprintf('%04d-%02d-01 00:00:00', $matches[1], $matches[2]);
        $end   = (int) $matches[2] === 12
            ? sprintf('%04d-01-01 00:00:00', $matches[1] + 1)
            : sprintf('%04d-%02d-01 00:00:00', $matches[1], $matches[2] + 1);
        $where .= $wpdb->prepare(' AND sent_at >= %s AND sent_at < %s', $start, $end);
    }

    return $where;
}
