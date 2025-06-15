<?php

// Store last mail args for fallback in wp_mail_failed
global $wzp_last_mail_args;
$wzp_last_mail_args = null;

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
register_activation_hook(__FILE__, 'wzp_create_email_log_table');

// Register success and failure logging hooks
add_action('wp_mail_succeeded', function($mail) {
    global $wzp_last_mail_args;
    $wzp_last_mail_args = $mail; // cache for fallback
    wzp_insert_log($mail, true, null);
});

add_action('wp_mail_failed', function($wp_error) {
    global $wzp_last_mail_args;

    $mail = method_exists($wp_error, 'get_data') ? $wp_error->get_data() : [];
    $mail = is_array($mail) ? $mail : [];

    // Fallback: patch in data from previous success call
    foreach (['subject', 'message', 'origin'] as $key) {
        if (empty($mail[$key]) && !empty($wzp_last_mail_args[$key])) {
            $mail[$key] = $wzp_last_mail_args[$key];
        }
    }

    $error_msg = method_exists($wp_error, 'get_error_message') ? $wp_error->get_error_message() : 'Unknown error';

    // Skip duplicate if already logged manually (e.g. from settings form)
    if (!empty($mail['origin']) && $mail['origin'] === 'settings') {
        return;
    }

    wzp_insert_log($mail, false, $error_msg);
});

// Main log insert function
function wzp_insert_log($mail, $success = true, $error = null) {
    global $wpdb;

    // Prevent double logging for test emails that already logged manually
    if (!empty($mail['origin']) && $mail['origin'] === 'settings' && $success) {
        return;
    }

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

    $ip_address      = $_SERVER['REMOTE_ADDR'] ?? '';
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
        'error_message'   => $error,
        'user_id'         => $user_id,
        'content_type'    => $content_type
    ]);
}

// Extract the From: address from headers
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

// Handle AJAX for viewing individual email log
add_action('wp_ajax_wzp_get_email_log', function () {
    global $wpdb;
    $id = intval($_GET['id']);
    $log = $wpdb->get_row("SELECT * FROM " . WZP_SMTP_TABLE . " WHERE id = $id", ARRAY_A);

    if (!$log) {
        wp_send_json_error('Log not found.');
    }

    $to      = is_serialized($log['to_email']) ? maybe_unserialize($log['to_email']) : $log['to_email'];
    $headers = is_serialized($log['headers']) ? maybe_unserialize($log['headers']) : $log['headers'];

    wp_send_json([
        'to'       => is_array($to) ? implode(', ', $to) : $to,
        'subject'  => $log['subject'] ?? '',
        'message'  => $log['message'] ?? '',
        'headers'  => $headers,
        'sent_at'  => date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime(get_date_from_gmt($log['sent_at']))
        )
    ]);
});
