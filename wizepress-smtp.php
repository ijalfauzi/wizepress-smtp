<?php
/**
 * Plugin Name: WizePress SMTP
 * Plugin URI: https://wizepress.id/plugin/wizepress-smtp
 * Description: Ensure your emails reach the inbox — simple SMTP setup and smart logging for WordPress.
 * Version: 1.1.0
 * Author: Ijal Fauzi
 * Author URI: https://ijalfauzi.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wizepress-smtp
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package WizePress_SMTP
 */


defined('ABSPATH') || exit;

define('WZP_SMTP_TABLE', $GLOBALS['wpdb']->prefix . 'wzp_email_logs');

require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/log-viewer.php';
require_once plugin_dir_path(__FILE__) . 'includes/email-logger.php';

add_action('admin_menu', function () {
    add_options_page('WizePress SMTP', 'WizePress SMTP', 'manage_options', 'wzp-smtp', 'wzp_render_tabs');
});

function wzp_render_tabs() {
    $active_tab = $_GET['tab'] ?? 'settings';
    echo '<div class="wrap"><h1>WizePress SMTP</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?page=wzp-smtp&tab=settings" class="nav-tab ' . ($active_tab === 'settings' ? 'nav-tab-active' : '') . '">SMTP Settings</a>';
    echo '<a href="?page=wzp-smtp&tab=logs" class="nav-tab ' . ($active_tab === 'logs' ? 'nav-tab-active' : '') . '">Email Logs</a>';
    echo '</h2>';
    if ($active_tab === 'logs') {
        wzp_email_logs_page();
    } else {
        wzp_smtp_settings_page();
    }
    echo '</div>';
}

add_action('phpmailer_init', function ($phpmailer) {
    $opt = get_option('wzp_smtp_settings', []);
    if (!empty($opt['smtp_host']) && !empty($opt['smtp_user']) && !empty($opt['smtp_pass'])) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $opt['smtp_host'];
        $phpmailer->Port = $opt['smtp_port'] ?? 465;
        $phpmailer->Username = $opt['smtp_user'];
        $phpmailer->Password = $opt['smtp_pass'];
        $phpmailer->SMTPSecure = $opt['smtp_secure'] ?? 'ssl';
        $phpmailer->SMTPAuth = true;
        $phpmailer->SMTPAutoTLS = true;
        $phpmailer->From = $opt['smtp_user'];
        $phpmailer->FromName = get_bloginfo('name');
    }
});

add_filter('admin_footer_text', 'wzp_custom_footer_credit', 11);
function wzp_custom_footer_credit($footer_text) {
    $screen = get_current_screen();
    
    if ($screen && strpos($screen->base, 'wzp-smtp') !== false) {
        $custom_credit = '<span style="font-style: italic;">You\'re using <a href="https://wizepress.id/plugin/wizepress-smtp" target="_blank" style="text-decoration:underline;">WizePress SMTP</a> v1.1.0 by <a href="https://ijalfauzi.com" target="_blank" style="text-decoration:underline;">Ijal Fauzi</a></span><br>';
        return $custom_credit . $footer_text;
    }

    return $footer_text;
}

function wzp_enqueue_admin_assets($hook) {
    if (strpos($hook, 'wzp-smtp') === false) {
        return;
    }
    wp_enqueue_style( 'wzp-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], '1.0.0' );
    wp_enqueue_script( 'wzp-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], '1.0.0', true );
    wp_localize_script( 'wzp-admin-script', 'wzpAjax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}
add_action( 'admin_enqueue_scripts', 'wzp_enqueue_admin_assets' );