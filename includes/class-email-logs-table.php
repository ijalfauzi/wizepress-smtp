<?php
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
                $delete_nonce,
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
        global $wpdb;

        $table = modifus_smtp_table();
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

        // Build query
        $where = modifus_smtp_logs_where($_REQUEST);

        // Sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'sent_at';
        $order = isset($_REQUEST['order']) && strtoupper($_REQUEST['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Validate orderby
        $allowed_orderby = ['sent_at', 'to_email', 'subject', 'result'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'sent_at';
        }

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");

        // Get items
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE $where ORDER BY $orderby $order, id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

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
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-email_logs')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            $log_ids = isset($_REQUEST['log_ids']) ? array_map('intval', $_REQUEST['log_ids']) : [];

            if (!empty($log_ids)) {
                global $wpdb;
                $ids_placeholder = implode(',', array_fill(0, count($log_ids), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM " . modifus_smtp_table() . " WHERE id IN ($ids_placeholder)",
                        ...$log_ids
                    )
                );
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

        $status = isset($_REQUEST['status']) ? sanitize_key($_REQUEST['status']) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $date = isset($_REQUEST['m']) ? sanitize_text_field($_REQUEST['m']) : '';
        $has_filter = !empty($status) || !empty($search) || !empty($date);
        ?>
        <div class="alignleft actions">
            <?php $this->render_months_dropdown(); ?>
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
    private function render_months_dropdown() {
        global $wpdb;

        $months = $wpdb->get_results(
            "SELECT DISTINCT YEAR(sent_at) AS year, MONTH(sent_at) AS month
             FROM " . modifus_smtp_table() . "
             ORDER BY year DESC, month DESC"
        );

        if (empty($months)) {
            return;
        }

        $selected = isset($_REQUEST['m']) ? sanitize_text_field($_REQUEST['m']) : '';
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
