<?php

// Last mail args for wp_mail_failed fallback
global $wzp_last_mail_args;
$wzp_last_mail_args = null;

// Skip logging flag for manual operations
global $wzp_skip_hook_logging;
$wzp_skip_hook_logging = false;

function wzp_create_email_log_table() {
    global $wpdb;
    $table = WZP_SMTP_TABLE;
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
}

// Log successful emails
add_action('wp_mail_succeeded', function($mail) {
    global $wzp_last_mail_args, $wzp_skip_hook_logging;

    $wzp_last_mail_args = $mail; // cache for fallback

    if ($wzp_skip_hook_logging) {
        return;
    }

    wzp_insert_log($mail, true, null);
});

// Log failed emails
add_action('wp_mail_failed', function($wp_error) {
    global $wzp_last_mail_args, $wzp_skip_hook_logging;

    if ($wzp_skip_hook_logging) {
        return;
    }

    $mail = method_exists($wp_error, 'get_data') ? $wp_error->get_data() : [];
    $mail = is_array($mail) ? $mail : [];

    // Patch missing data from cached successful call
    foreach (['subject', 'message'] as $key) {
        if (empty($mail[$key]) && !empty($wzp_last_mail_args[$key])) {
            $mail[$key] = $wzp_last_mail_args[$key];
        }
    }

    $error_msg = method_exists($wp_error, 'get_error_message') ? $wp_error->get_error_message() : 'Unknown error';

    wzp_insert_log($mail, false, $error_msg);
});

/**
 * Insert email log entry
 */
function wzp_insert_log($mail, $success = true, $error = null) {
    global $wpdb;

    $to_email_raw    = $mail['to'] ?? '';
    $to_email        = is_array($to_email_raw) ? implode(', ', $to_email_raw) : $to_email_raw;

    $headers_raw     = $mail['headers'] ?? '';
    $headers         = is_array($headers_raw) ? implode("\n", $headers_raw) : $headers_raw;

    $from_email      = wzp_extract_from_header($headers_raw);

    $attachments_raw = $mail['attachments'] ?? [];
    $attachments     = is_array($attachments_raw) ? count($attachments_raw) : 0;
    $attachment_name = is_array($attachments_raw) ? implode(', ', $attachments_raw) : '';

    $subject         = $mail['subject'] ?? '';
    $message         = $mail['message'] ?? '';

    $ip_address      = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';
    $user_id         = get_current_user_id();

    $content_type = (is_string($headers) && stripos($headers, 'Content-Type: text/html') !== false)
        ? 'text/html'
        : 'text/plain';

    $wpdb->insert(WZP_SMTP_TABLE, [
        'to_email'        => maybe_serialize($to_email),
        'from_email'      => sanitize_email($from_email),
        'subject'         => sanitize_text_field($subject),
        'message'         => $message,
        'headers'         => maybe_serialize($headers),
        'attachments'     => maybe_serialize($attachments),
        'attachment_name' => sanitize_text_field($attachment_name),
        'sent_at'         => current_time('mysql'),
        'ip_address'      => $ip_address,
        'result'          => $success ? 1 : 0,
        'error_message'   => $error ? sanitize_text_field($error) : null,
        'user_id'         => $user_id,
        'content_type'    => $content_type
    ]);
}

/**
 * Extract From address from headers
 */
function wzp_extract_from_header($headers) {
    foreach ((array) $headers as $header) {
        if (stripos($header, 'From:') === 0) {
            if (preg_match('/<(.+?)>/', $header, $matches)) {
                return $matches[1];
            }
            return trim(str_ireplace('From:', '', $header));
        }
    }
    return '';
}

// AJAX: Get email log
add_action('wp_ajax_wzp_get_email_log', function () {
    if (!check_ajax_referer('wzp_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid security token.');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized.');
    }

    global $wpdb;
    $id = intval($_GET['id'] ?? 0);

    $log = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM " . WZP_SMTP_TABLE . " WHERE id = %d", $id),
        ARRAY_A
    );

    if (!$log) {
        wp_send_json_error('Log not found.');
    }

    $to      = is_serialized($log['to_email']) ? maybe_unserialize($log['to_email']) : $log['to_email'];
    $headers = is_serialized($log['headers']) ? maybe_unserialize($log['headers']) : $log['headers'];

    wp_send_json_success([
        'to'            => is_array($to) ? implode(', ', $to) : $to,
        'subject'       => $log['subject'] ?? '',
        'message'       => $log['message'] ?? '',
        'headers'       => $headers,
        'sent_at'       => date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime(get_date_from_gmt($log['sent_at']))
        ),
        'result'        => (int) $log['result'],
        'error_message' => $log['error_message'] ?? ''
    ]);
});

// AJAX: Delete email log
add_action('wp_ajax_wzp_delete_email_log', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized.');
    }

    $id = intval($_POST['id'] ?? 0);
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');

    if (!wp_verify_nonce($nonce, 'wzp_delete_log_' . $id)) {
        wp_send_json_error('Invalid security token.');
    }

    global $wpdb;

    $result = $wpdb->delete(
        WZP_SMTP_TABLE,
        ['id' => $id],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error('Failed to delete log.');
    }

    wp_send_json_success('Log deleted successfully.');
});

// AJAX: Resend email
add_action('wp_ajax_wzp_resend_email', function () {
    if (!check_ajax_referer('wzp_ajax_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid security token.');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized.');
    }

    global $wpdb;
    $id = intval($_POST['id'] ?? 0);

    $log = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM " . WZP_SMTP_TABLE . " WHERE id = %d", $id),
        ARRAY_A
    );

    if (!$log) {
        wp_send_json_error('Log not found.');
    }

    $to = is_serialized($log['to_email']) ? maybe_unserialize($log['to_email']) : $log['to_email'];
    $subject = $log['subject'];
    $message = $log['message'];
    $headers = is_serialized($log['headers']) ? maybe_unserialize($log['headers']) : $log['headers'];

    if (is_array($headers)) {
        $headers = implode("\r\n", $headers);
    }

    // Skip auto-logging, we'll log manually with "(Resent)" suffix
    global $wzp_skip_hook_logging;
    $wzp_skip_hook_logging = true;

    $result = wp_mail($to, $subject, $message, $headers);

    $wzp_skip_hook_logging = false;

    $mail_args = [
        'to'          => $to,
        'subject'     => $subject . ' (Resent)',
        'message'     => $message,
        'headers'     => $headers,
        'attachments' => [],
    ];
    wzp_insert_log($mail_args, $result, $result ? null : 'Failed to resend email.');

    if ($result) {
        wp_send_json_success('Email resent successfully!');
    } else {
        wp_send_json_error('Failed to resend email. Please check your SMTP settings.');
    }
});
