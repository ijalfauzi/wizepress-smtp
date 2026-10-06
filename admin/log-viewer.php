<?php
/**
 * Email logs page and exports.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

// Handle exports before headers are sent
add_action('admin_init', 'modifus_smtp_handle_export');
function modifus_smtp_handle_export() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'modifus-smtp') {
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

    if (in_array($action, ['export_csv', 'export_excel', 'export_print'], true)) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'modifus_smtp_export')) {
            wp_die(esc_html__('Invalid security token.', 'modifus-smtp'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'modifus-smtp'));
        }

        $filters  = modifus_smtp_logs_filters();
        $logs     = modifus_smtp_get_logs($filters, 'sent_at', 'ASC', PHP_INT_MAX, 0, ARRAY_A);
        $filename = modifus_smtp_get_export_filename($filters);

        switch ($action) {
            case 'export_csv':
                modifus_smtp_export_csv($logs, $filename);
                break;
            case 'export_excel':
                modifus_smtp_export_xlsx($logs, $filename);
                break;
            case 'export_print':
                modifus_smtp_export_print($logs);
                break;
        }
    }
}

function modifus_smtp_logs_page() {
    $logs_table = new Modifus_SMTP_Logs_Table();
    $logs_table->prepare_items();
    ?>
    <div class="modifus-smtp-email-logs-wrap">
        <form method="get">
            <input type="hidden" name="page" value="modifus-smtp" />
            <input type="hidden" name="tab" value="logs" />
            <?php
            $logs_table->search_box(__('Search Emails', 'modifus-smtp'), 'modifus-smtp-search');
            $logs_table->display();
            ?>
        </form>
    </div>

    <div id="email-log-modal-overlay">
        <div id="email-log-modal">
            <button id="email-log-modal-close" aria-label="<?php esc_attr_e('Close Modal', 'modifus-smtp'); ?>">&times;</button>
            <h2><?php esc_html_e('Email Content', 'modifus-smtp'); ?></h2>
            <table class="widefat striped">
                <tr><th><?php esc_html_e('Sent at', 'modifus-smtp'); ?></th><td id="log-sent-at"></td></tr>
                <tr><th><?php esc_html_e('To', 'modifus-smtp'); ?></th><td id="log-to"></td></tr>
                <tr><th><?php esc_html_e('Subject', 'modifus-smtp'); ?></th><td id="log-subject"></td></tr>
                <tr id="log-error-row" style="display:none;"><th><?php esc_html_e('Error', 'modifus-smtp'); ?></th><td id="log-error-message" class="modifus-smtp-error-text"></td></tr>
            </table>

            <div class="modal-toolbar">
                <button class="button" id="toggle-raw"><?php esc_html_e('Raw Email Content', 'modifus-smtp'); ?></button>
                <button class="button" id="toggle-html"><?php esc_html_e('Preview Content as HTML', 'modifus-smtp'); ?></button>
            </div>

            <textarea id="email-raw" readonly></textarea>
            <iframe id="email-html-preview" sandbox="allow-same-origin"></iframe>
        </div>
    </div>
<?php
}

/**
 * Generate descriptive filename for exports
 */
function modifus_smtp_get_export_filename($filters) {
    $parts = ['email-logs'];

    if ($filters['status'] !== '') {
        $parts[] = $filters['status'];
    }

    if ($filters['month'] !== '') {
        $parts[] = substr($filters['month'], 0, 4) . '-' . substr($filters['month'], 4, 2);
    }

    $parts[] = wp_date('Y-m-d');

    return implode('-', $parts);
}

/**
 * Export column headings
 */
function modifus_smtp_export_headings() {
    return [
        __('ID', 'modifus-smtp'),
        __('Sent At', 'modifus-smtp'),
        __('Status', 'modifus-smtp'),
        __('To', 'modifus-smtp'),
        __('Subject', 'modifus-smtp'),
        __('Attachments', 'modifus-smtp'),
        __('IP Address', 'modifus-smtp'),
        __('Error Message', 'modifus-smtp'),
    ];
}

/**
 * Format log row for export
 */
function modifus_smtp_format_log_row($log) {
    return [
        'id'         => (int) $log['id'],
        'sent_at'    => $log['sent_at'],
        'status'     => $log['result'] ? __('Success', 'modifus-smtp') : __('Failed', 'modifus-smtp'),
        'to'         => modifus_smtp_log_recipients($log['to_email']),
        'subject'    => $log['subject'] ?? '',
        'attachments'=> (int) ($log['attachments'] ?? 0),
        'ip_address' => $log['ip_address'] ?? '',
        'error'      => $log['error_message'] ?? ''
    ];
}

/**
 * Stop spreadsheet apps treating a text cell as a formula (CSV injection).
 */
function modifus_smtp_csv_safe($value) {
    if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Export as CSV
 */
function modifus_smtp_export_csv($logs, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, modifus_smtp_export_headings(), ',', '"', '\\');

    foreach ($logs as $log) {
        $row = array_map('modifus_smtp_csv_safe', modifus_smtp_format_log_row($log));
        fputcsv($output, array_values($row), ',', '"', '\\');
    }

    fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a file.
    exit;
}

/**
 * Escape a value for an XML text node, dropping characters XML can't hold.
 */
function modifus_smtp_xml($value) {
    $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value);
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Build one worksheet row. Text is written as inline strings, so it is
 * never evaluated as a formula.
 */
function modifus_smtp_xlsx_row($cells, $row_number) {
    $xml = '<row r="' . $row_number . '">';
    foreach (array_values($cells) as $i => $value) {
        $ref = chr(65 + $i) . $row_number;
        if (is_int($value)) {
            $xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        } else {
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . modifus_smtp_xml($value) . '</t></is></c>';
        }
    }
    return $xml . '</row>';
}

/**
 * Export as Excel (.xlsx). Falls back to CSV if ZipArchive is unavailable.
 */
function modifus_smtp_export_xlsx($logs, $filename) {
    if (!class_exists('ZipArchive')) {
        modifus_smtp_export_csv($logs, $filename);
    }

    $rows = modifus_smtp_xlsx_row(modifus_smtp_export_headings(), 1);
    $n = 2;
    foreach ($logs as $log) {
        $rows .= modifus_smtp_xlsx_row(modifus_smtp_format_log_row($log), $n++);
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Email Logs" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rows . '</sheetData>'
            . '</worksheet>',
    ];

    $tmp = wp_tempnam('modifus-smtp-export.xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        wp_delete_file($tmp);
        modifus_smtp_export_csv($logs, $filename);
    }
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($tmp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the temporary export file to the browser.
    wp_delete_file($tmp);
    exit;
}

/**
 * Export as Print-friendly HTML page
 */
function modifus_smtp_export_print($logs) {
    $site_name = get_bloginfo('name');
    $export_date = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
    $total_logs = count($logs);
    $headings = modifus_smtp_export_headings();

    $url = plugin_dir_url(MODIFUS_SMTP_FILE);
    wp_register_style('modifus-smtp-print', $url . 'assets/css/print.css', [], MODIFUS_SMTP_VERSION);
    wp_register_script('modifus-smtp-print', $url . 'assets/js/print.js', [], MODIFUS_SMTP_VERSION, true);
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php echo esc_html(__('Email Logs', 'modifus-smtp') . ' - ' . $site_name); ?></title>
    <?php wp_print_styles('modifus-smtp-print'); ?>
</head>
<body>
    <div class="no-print toolbar">
        <button type="button" id="modifus-smtp-print">🖨️ <?php esc_html_e('Print / Save as PDF', 'modifus-smtp'); ?></button>
        <button type="button" id="modifus-smtp-close"><?php esc_html_e('Close', 'modifus-smtp'); ?></button>
    </div>
    <h1><?php esc_html_e('Email Logs', 'modifus-smtp'); ?></h1>
    <p class="meta"><?php
        /* translators: 1: site name, 2: export date, 3: number of records */
        echo esc_html(sprintf(__('%1$s • Exported on %2$s • %3$d records', 'modifus-smtp'), $site_name, $export_date, $total_logs));
    ?></p>
    <table>
        <thead>
            <tr>
                <?php foreach ($headings as $heading) : ?>
                    <th><?php echo esc_html($heading); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) :
                $row = modifus_smtp_format_log_row($log);
            ?>
            <tr>
                <td><?php echo esc_html($row['id']); ?></td>
                <td><?php echo esc_html($row['sent_at']); ?></td>
                <td class="status-<?php echo $log['result'] ? 'success' : 'failed'; ?>"><?php echo esc_html($row['status']); ?></td>
                <td><?php echo esc_html($row['to']); ?></td>
                <td><?php echo esc_html($row['subject']); ?></td>
                <td><?php echo esc_html($row['attachments']); ?></td>
                <td><?php echo esc_html($row['ip_address']); ?></td>
                <td><?php echo esc_html($row['error']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php wp_print_scripts('modifus-smtp-print'); ?>
</body>
</html>
<?php
    exit;
}
