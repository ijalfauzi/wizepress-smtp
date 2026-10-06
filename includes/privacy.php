<?php
/**
 * Personal data export/erase and privacy policy text.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

/**
 * Log rows sent to an email address, a page at a time.
 */
function modifus_smtp_privacy_rows($email, $page, $per_page = 100) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, to_email, subject, sent_at, result FROM " . modifus_smtp_table() . " WHERE to_email LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
            '%' . $wpdb->esc_like($email) . '%',
            $per_page,
            ($page - 1) * $per_page
        ),
        ARRAY_A
    );
}

add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
    $exporters['modifus-smtp'] = [
        'exporter_friendly_name' => __('Modifus SMTP Email Logs', 'modifus-smtp'),
        'callback'               => 'modifus_smtp_privacy_exporter',
    ];
    return $exporters;
});

function modifus_smtp_privacy_exporter($email, $page = 1) {
    $per_page = 100;
    $rows     = modifus_smtp_privacy_rows($email, (int) $page, $per_page);
    $items    = [];

    foreach ($rows as $row) {
        $items[] = [
            'group_id'    => 'modifus-smtp-logs',
            'group_label' => __('Email Logs', 'modifus-smtp'),
            'item_id'     => 'modifus-smtp-log-' . $row['id'],
            'data'        => [
                ['name' => __('Sent At', 'modifus-smtp'), 'value' => $row['sent_at']],
                ['name' => __('To', 'modifus-smtp'), 'value' => modifus_smtp_log_recipients($row['to_email'])],
                ['name' => __('Subject', 'modifus-smtp'), 'value' => $row['subject']],
                ['name' => __('Status', 'modifus-smtp'), 'value' => $row['result'] ? __('Success', 'modifus-smtp') : __('Failed', 'modifus-smtp')],
            ],
        ];
    }

    return ['data' => $items, 'done' => count($rows) < $per_page];
}

add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
    $erasers['modifus-smtp'] = [
        'eraser_friendly_name' => __('Modifus SMTP Email Logs', 'modifus-smtp'),
        'callback'             => 'modifus_smtp_privacy_eraser',
    ];
    return $erasers;
});

function modifus_smtp_privacy_eraser($email, $page = 1) {
    global $wpdb;

    // Always read the first page: rows are deleted as we go
    $rows = modifus_smtp_privacy_rows($email, 1, 100);
    $ids  = wp_list_pluck($rows, 'id');

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM " . modifus_smtp_table() . " WHERE id IN ($placeholders)", ...$ids));
    }

    return [
        'items_removed'  => !empty($ids),
        'items_retained' => false,
        'messages'       => [],
        'done'           => count($ids) < 100,
    ];
}

add_action('admin_init', function () {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }
    wp_add_privacy_policy_content(
        'Modifus SMTP',
        '<p>' . __('When this site sends an email, a copy is kept in an email log: the recipient address, sender, subject, full message, attachment file names, the IP address and user account that triggered it, and whether it was delivered. Administrators can view these logs.', 'modifus-smtp') . '</p>'
        . '<p>' . __('Logs are kept until an administrator deletes them, or for the number of days set in the plugin\'s log retention setting.', 'modifus-smtp') . '</p>'
    );
});
