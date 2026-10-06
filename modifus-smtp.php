<?php
/**
 * Plugin Name: Modifus SMTP
 * Plugin URI: https://modifus.com/plugins/modifus-smtp
 * Description: SMTP & Email Log — make sure your WordPress emails reach the inbox, and see every one that's sent.
 * Version: 2.0.0
 * Author: Modifus
 * Author URI: https://modifus.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: modifus-smtp
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package Modifus_SMTP
 */


defined('ABSPATH') || exit;

define('MODIFUS_SMTP_VERSION', '2.0.0');
define('MODIFUS_SMTP_DB_VERSION', '2.0.0');
define('MODIFUS_SMTP_TABLE', $GLOBALS['wpdb']->prefix . 'modifus_smtp_logs');

require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/log-viewer.php';
require_once plugin_dir_path(__FILE__) . 'includes/email-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-email-logs-table.php';
require_once plugin_dir_path(__FILE__) . 'includes/install.php';

// Create/upgrade the log table on activation and after in-place updates
register_activation_hook(__FILE__, 'modifus_smtp_install');
add_action('plugins_loaded', 'modifus_smtp_maybe_upgrade');

add_action('admin_menu', function () {
    $hook = add_options_page('Modifus SMTP', 'Modifus SMTP', 'manage_options', 'modifus-smtp', 'modifus_smtp_render_tabs');
    add_action("load-$hook", 'modifus_smtp_add_screen_options');
});

function modifus_smtp_add_screen_options() {
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'logs';
    if ($tab === 'logs') {
        add_screen_option('per_page', [
            'label'   => 'Logs per page',
            'default' => 20,
            'option'  => 'modifus_smtp_logs_per_page'
        ]);
    }
}

add_filter('set-screen-option', function ($status, $option, $value) {
    if ($option === 'modifus_smtp_logs_per_page') {
        return absint($value);
    }
    return $status;
}, 10, 3);

function modifus_smtp_render_tabs() {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'logs';
    $active_tab = in_array($active_tab, ['settings', 'logs'], true) ? $active_tab : 'logs';

    echo '<div class="wrap"><h1>Modifus SMTP</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?page=modifus-smtp&tab=logs" class="nav-tab ' . ($active_tab === 'logs' ? 'nav-tab-active' : '') . '">Email Logs</a>';
    echo '<a href="?page=modifus-smtp&tab=settings" class="nav-tab ' . ($active_tab === 'settings' ? 'nav-tab-active' : '') . '">SMTP Settings</a>';
    echo '</h2>';
    if ($active_tab === 'logs') {
        modifus_smtp_logs_page();
    } else {
        modifus_smtp_settings_page();
    }
    echo '</div>';
}

add_action('phpmailer_init', function ($phpmailer) {
    $opt = get_option('modifus_smtp_settings', []);
    if (!empty($opt['smtp_host']) && !empty($opt['smtp_user']) && !empty($opt['smtp_pass'])) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $opt['smtp_host'];
        $phpmailer->Port = $opt['smtp_port'] ?? 465;
        $phpmailer->Username = $opt['smtp_user'];
        $phpmailer->Password = $opt['smtp_pass'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->From = $opt['smtp_user'];
        $phpmailer->FromName = get_bloginfo('name');

        // Handle encryption setting
        $secure = $opt['smtp_secure'] ?? '';
        if (!empty($secure)) {
            $phpmailer->SMTPSecure = $secure;
            $phpmailer->SMTPAutoTLS = true;
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }
    }
});

add_filter('admin_footer_text', 'modifus_smtp_custom_footer_credit', 11);
function modifus_smtp_custom_footer_credit($footer_text) {
    $screen = get_current_screen();

    if ($screen && strpos($screen->base, 'modifus-smtp') !== false) {
        $custom_credit = sprintf(
            '<span style="font-style: italic;">You\'re using <a href="https://modifus.com/plugins/modifus-smtp" target="_blank" style="text-decoration:underline;">Modifus SMTP</a> v%s by <a href="https://modifus.com" target="_blank" style="text-decoration:underline;">Modifus</a></span><br>',
            MODIFUS_SMTP_VERSION
        );
        return $custom_credit . $footer_text;
    }

    return $footer_text;
}

function modifus_smtp_enqueue_admin_assets($hook) {
    if (strpos($hook, 'modifus-smtp') === false) {
        return;
    }
    wp_enqueue_style('modifus-smtp-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], MODIFUS_SMTP_VERSION);
    wp_enqueue_script('modifus-smtp-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], MODIFUS_SMTP_VERSION, true);
    wp_localize_script('modifus-smtp-admin-script', 'modifusSmtp', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('modifus_smtp_ajax_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'modifus_smtp_enqueue_admin_assets');

// Add settings link on plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=modifus-smtp&tab=settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});