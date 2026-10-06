<?php
/**
 * Email logs list table.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Modifus_SMTP_Logs_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'email_log',
            'plural'   => 'email_logs',
            'ajax'     => false
        ]);
    }

    /**
     * Get table columns
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'sent_at'     => __('Sent At', 'modifus-smtp'),
            'result'      => __('Status', 'modifus-smtp'),
            'to_email'    => __('To', 'modifus-smtp'),
            'subject'     => __('Subject', 'modifus-smtp'),
            'attachments' => __('Attachments', 'modifus-smtp'),
            'ip_address'  => __('IP Address', 'modifus-smtp')
        ];
    }

    /**
     * Sortable columns
     */
    public function get_sortable_columns() {
        return [
            'sent_at'  => ['sent_at', true],
            'to_email' => ['to_email', false],
            'subject'  => ['subject', false],
            'result'   => ['result', false]
        ];
    }

    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'modifus-smtp')
        ];
    }

    /**
     * Checkbox column
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="log_ids[]" value="%d" />', $item->id);
    }

    /**
     * Sent At column with row actions
     */
    public function column_sent_at($item) {
        // sent_at is stored in site local time
        $date_display = modifus_smtp_format_date($item->sent_at, get_option('date_format'));
        $time_display = modifus_smtp_format_date($item->sent_at, get_option('time_format'));

        $delete_nonce = wp_create_nonce('modifus_smtp_delete_log_' . $item->id);

        $actions = [
            'view'   => sprintf(
                '<a href="#" class="view-email" data-id="%d">%s</a>',
                $item->id,
                esc_html__('View Content', 'modifus-smtp')
            ),
            'resend' => sprintf(
                '<a href="#" class="resend-email" data-id="%d">%s</a>',
                $item->id,
                esc_html__('Resend', 'modifus-smtp')
            ),
            'delete' => sprintf(
                '<a href="#" class="delete-email" data-id="%d" data-nonce="%s">%s</a>',
                $item->id,
                esc_attr($delete_nonce),
                esc_html__('Delete', 'modifus-smtp')
            )
        ];

        return sprintf(
            '%s<br><span class="modifus-smtp-time">@ %s</span><br><span class="modifus-smtp-id">(id:%d)</span>%s',
            esc_html($date_display),
            esc_html($time_display),
            $item->id,
            $this->row_actions($actions)
        );
    }

    /**
     * Result/Status column
     */
    public function column_result($item) {
        $class = $item->result ? 'modifus-smtp-status-success' : 'modifus-smtp-status-failed';
        $label = $item->result ? __('Success', 'modifus-smtp') : __('Failed', 'modifus-smtp');
        return sprintf('<span class="modifus-smtp-status-icon %s" title="%s"></span><span class="screen-reader-text">%s</span>', $class, esc_attr($label), esc_html($label));
    }

    /**
     * To Email column
     */
    public function column_to_email($item) {
        return esc_html(modifus_smtp_log_recipients($item->to_email));
    }

    /**
     * Subject column
     */
    public function column_subject($item) {
        return esc_html($item->subject);
    }

    /**
     * Attachments column
     */
    public function column_attachments($item) {
        $attachments = is_serialized($item->attachments) ? maybe_unserialize($item->attachments) : $item->attachments;
        $count = is_array($attachments) ? count($attachments) : $attachments;

        $output = esc_html($count);
        if (!empty($item->attachment_name)) {
            $output .= '<br><small>' . esc_html($item->attachment_name) . '</small>';
        }
        return $output;
    }

    /**
     * IP Address column
     */
    public function column_ip_address($item) {
        return esc_html($item->ip_address ?? '—');
    }

    /**
     * Default column handler
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }

    /**
     * Message when no items found
     */
    public function no_items() {
        esc_html_e('No email logs found.', 'modifus-smtp');
    }

    /**
     * Prepare items for display
     */
    public function prepare_items() {
        $per_page = $this->get_items_per_page('modifus_smtp_logs_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];

        // Process bulk action
        $this->process_bulk_action();

        $filters = modifus_smtp_logs_filters();

        // Sorting (read-only, like the filters). The column is checked
        // against an allowlist in modifus_smtp_get_logs().
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'sent_at';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash($_GET['order']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $order = $order === 'ASC' ? 'ASC' : 'DESC';

        $total_items = modifus_smtp_count_logs($filters);
        $this->items = modifus_smtp_get_logs($filters, $orderby, $order, $per_page, $offset);

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        if ('delete' === $this->current_action()) {
            // Verify nonce
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-email_logs')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            $log_ids = isset($_REQUEST['log_ids']) ? array_filter(array_map('absint', (array) wp_unslash($_REQUEST['log_ids']))) : [];

            global $wpdb;
            // At most one page of logs
            foreach ($log_ids as $log_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
                $wpdb->delete(modifus_smtp_table(), ['id' => $log_id], ['%d']);
            }
        }
    }

    /**
     * Extra table navigation (filters)
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $filters = modifus_smtp_logs_filters();
        $status = $filters['status'];
        $search = $filters['search'];
        $date = $filters['month'];
        $has_filter = !empty($status) || !empty($search) || !empty($date);
        ?>
        <div class="alignleft actions">
            <?php $this->render_months_dropdown($date); ?>
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'modifus-smtp'); ?></option>
                <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Success', 'modifus-smtp'); ?></option>
                <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'modifus-smtp'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'modifus-smtp'), '', 'filter_action', false); ?>
            <?php if ($has_filter) : ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=modifus-smtp&tab=logs')); ?>" class="button"><?php esc_html_e('Clear', 'modifus-smtp'); ?></a>
            <?php endif; ?>
        </div>
        <div class="alignleft actions modifus-smtp-export-actions">
            <?php
            $base_params = [
                'page'   => 'modifus-smtp',
                'tab'    => 'logs',
                's'      => $search,
                'status' => $status,
                'm'      => $date,
                '_wpnonce' => wp_create_nonce('modifus_smtp_export')
            ];

            $export_csv_url = add_query_arg(array_merge($base_params, ['action' => 'export_csv']), admin_url('options-general.php'));
            $export_excel_url = add_query_arg(array_merge($base_params, ['action' => 'export_excel']), admin_url('options-general.php'));
            $export_print_url = add_query_arg(array_merge($base_params, ['action' => 'export_print']), admin_url('options-general.php'));
            ?>
            <a href="<?php echo esc_url($export_csv_url); ?>" class="button" title="<?php esc_attr_e('Download as CSV', 'modifus-smtp'); ?>">CSV</a>
            <a href="<?php echo esc_url($export_excel_url); ?>" class="button" title="<?php esc_attr_e('Download as Excel', 'modifus-smtp'); ?>">Excel</a>
            <a href="<?php echo esc_url($export_print_url); ?>" class="button" target="_blank" title="<?php esc_attr_e('Print or Save as PDF', 'modifus-smtp'); ?>"><?php esc_html_e('Print/PDF', 'modifus-smtp'); ?></a>
        </div>
        <?php
    }

    /**
     * Display months dropdown for filtering
     */
    private function render_months_dropdown($selected) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own log table.
        $months = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DISTINCT YEAR(sent_at) AS year, MONTH(sent_at) AS month FROM %i ORDER BY year DESC, month DESC',
                modifus_smtp_table()
            )
        );

        if (empty($months)) {
            return;
        }
        ?>
        <select name="m">
            <option value=""><?php esc_html_e('All Dates', 'modifus-smtp'); ?></option>
            <?php foreach ($months as $row) : ?>
                <?php
                $month_value = sprintf('%04d%02d', $row->year, $row->month);
                $month_label = date_i18n('F Y', mktime(0, 0, 0, $row->month, 1, $row->year));
                ?>
                <option value="<?php echo esc_attr($month_value); ?>" <?php selected($selected, $month_value); ?>>
                    <?php echo esc_html($month_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
