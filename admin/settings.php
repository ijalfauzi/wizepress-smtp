<?php
/**
 * SMTP settings page and test email.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

function modifus_smtp_settings_page() {
    global $modifus_smtp_last_error;

    $options = modifus_smtp_get_settings();

    // Handle test email submission
    if (
        isset($_POST['modifus_smtp_test_email']) &&
        check_admin_referer('modifus_smtp_send_test_email', 'modifus_smtp_test_nonce')
    ) {
        $to = sanitize_email(wp_unslash($_POST['modifus_smtp_test_email']));
        $subject = __('Modifus SMTP Test Email', 'modifus-smtp');
        $body = __("Hello,\n\nThis is a test email sent from the Modifus SMTP plugin.\n\nIf you received this, your SMTP settings are working!", 'modifus-smtp');

        // Logged by the wp_mail hooks like any other email
        $modifus_smtp_last_error = '';
        $result = wp_mail($to, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);

        if ($result) {
            echo '<div class="notice notice-success"><p><span class="modifus-smtp-status-icon modifus-smtp-status-success"></span> '
                /* translators: %s: email address */
                . sprintf(esc_html__('Test email sent to %s', 'modifus-smtp'), '<strong>' . esc_html($to) . '</strong>')
                . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p><span class="modifus-smtp-status-icon modifus-smtp-status-failed"></span> '
                . esc_html__('Failed to send test email. Please check your SMTP settings.', 'modifus-smtp');
            if ($modifus_smtp_last_error) {
                echo '<br><code>' . esc_html($modifus_smtp_last_error) . '</code>';
            }
            echo '</p></div>';
        }
    }

    $password_in_config = modifus_smtp_password_in_config();
    $has_password       = !$password_in_config && $options['smtp_pass'] !== '';
    $password_broken    = $has_password && modifus_smtp_decrypt($options['smtp_pass']) === false;

    if ($password_broken) {
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('The saved SMTP password can\'t be read, probably because the secret keys in wp-config.php changed. Please enter it again.', 'modifus-smtp')
            . '</p></div>';
    }
    ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('modifus_smtp_settings_group');
        do_settings_sections('modifus-smtp');
        ?>
        <input type="hidden" name="modifus_smtp_settings[_form]" value="1" />
        <h2><?php esc_html_e('SMTP Server', 'modifus-smtp'); ?></h2>
        <table class="form-table">
            <tr><th><label for="modifus_smtp_host"><?php esc_html_e('SMTP Host', 'modifus-smtp'); ?></label></th>
            <td><input type="text" id="modifus_smtp_host" name="modifus_smtp_settings[smtp_host]" value="<?php echo esc_attr($options['smtp_host']); ?>" class="regular-text" placeholder="smtp.example.com" />
            <p class="description"><?php esc_html_e('Leave empty to use the server\'s default mail function.', 'modifus-smtp'); ?></p></td></tr>
            <tr><th><label for="modifus_smtp_secure"><?php esc_html_e('Encryption', 'modifus-smtp'); ?></label></th>
            <td><select id="modifus_smtp_secure" name="modifus_smtp_settings[smtp_secure]">
                <option value="" <?php selected($options['smtp_secure'], ''); ?>><?php esc_html_e('None', 'modifus-smtp'); ?></option>
                <option value="ssl" <?php selected($options['smtp_secure'], 'ssl'); ?>>SSL</option>
                <option value="tls" <?php selected($options['smtp_secure'], 'tls'); ?>>TLS</option>
            </select></td></tr>
            <tr><th><label for="modifus_smtp_port"><?php esc_html_e('SMTP Port', 'modifus-smtp'); ?></label></th>
            <td><input type="number" id="modifus_smtp_port" name="modifus_smtp_settings[smtp_port]" value="<?php echo esc_attr($options['smtp_port']); ?>" class="small-text" min="1" max="65535" />
            <p class="description"><?php esc_html_e('Usually 465 for SSL, 587 for TLS, 25 for none.', 'modifus-smtp'); ?></p></td></tr>
            <tr><th><?php esc_html_e('Authentication', 'modifus-smtp'); ?></th>
            <td><label><input type="checkbox" name="modifus_smtp_settings[smtp_auth]" value="1" <?php checked(!empty($options['smtp_auth'])); ?> />
            <?php esc_html_e('Log in to the SMTP server with a username and password', 'modifus-smtp'); ?></label></td></tr>
            <tr><th><label for="modifus_smtp_user"><?php esc_html_e('SMTP Username', 'modifus-smtp'); ?></label></th>
            <td><input type="text" id="modifus_smtp_user" name="modifus_smtp_settings[smtp_user]" value="<?php echo esc_attr($options['smtp_user']); ?>" class="regular-text" autocomplete="off" /></td></tr>
            <tr><th><label for="modifus_smtp_pass"><?php esc_html_e('SMTP Password', 'modifus-smtp'); ?></label></th>
            <td>
                <?php if ($password_in_config) : ?>
                    <input type="password" id="modifus_smtp_pass" class="regular-text" disabled placeholder="<?php esc_attr_e('Set in wp-config.php', 'modifus-smtp'); ?>" />
                    <p class="description"><?php esc_html_e('The password is set by the MODIFUS_SMTP_PASSWORD constant in wp-config.php.', 'modifus-smtp'); ?></p>
                <?php else : ?>
                    <input type="password" id="modifus_smtp_pass" name="modifus_smtp_settings[smtp_pass]" value="" class="regular-text" autocomplete="new-password"
                        placeholder="<?php echo $has_password && !$password_broken ? esc_attr__('Saved — leave blank to keep', 'modifus-smtp') : ''; ?>" />
                    <p class="description"><?php
                        /* translators: %s: PHP constant definition */
                        printf(esc_html__('Stored encrypted. To keep it out of the database entirely, add %s to wp-config.php.', 'modifus-smtp'), '<code>define(\'MODIFUS_SMTP_PASSWORD\', \'...\');</code>');
                    ?></p>
                <?php endif; ?>
            </td></tr>
        </table>

        <h2><?php esc_html_e('Sender', 'modifus-smtp'); ?></h2>
        <table class="form-table">
            <tr><th><label for="modifus_smtp_from_email"><?php esc_html_e('From Email', 'modifus-smtp'); ?></label></th>
            <td><input type="email" id="modifus_smtp_from_email" name="modifus_smtp_settings[from_email]" value="<?php echo esc_attr($options['from_email']); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e('Defaults to the SMTP username. Many SMTP providers only accept addresses you\'re authorised to send from.', 'modifus-smtp'); ?></p></td></tr>
            <tr><th><label for="modifus_smtp_from_name"><?php esc_html_e('From Name', 'modifus-smtp'); ?></label></th>
            <td><input type="text" id="modifus_smtp_from_name" name="modifus_smtp_settings[from_name]" value="<?php echo esc_attr($options['from_name']); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
            <p class="description"><?php esc_html_e('Defaults to the site title.', 'modifus-smtp'); ?></p></td></tr>
            <tr><th><?php esc_html_e('Force From', 'modifus-smtp'); ?></th>
            <td><label><input type="checkbox" name="modifus_smtp_settings[force_from]" value="1" <?php checked(!empty($options['force_from'])); ?> />
            <?php esc_html_e('Use this From email and name for every email, even when another plugin sets its own', 'modifus-smtp'); ?></label>
            <p class="description"><?php esc_html_e('When unticked, they only replace WordPress\'s default (wordpress@yoursite).', 'modifus-smtp'); ?></p></td></tr>
        </table>

        <h2><?php esc_html_e('Email Log', 'modifus-smtp'); ?></h2>
        <table class="form-table">
            <tr><th><label for="modifus_smtp_retention"><?php esc_html_e('Keep Logs For', 'modifus-smtp'); ?></label></th>
            <td><input type="number" id="modifus_smtp_retention" name="modifus_smtp_settings[log_retention_days]" value="<?php echo esc_attr($options['log_retention_days']); ?>" class="small-text" min="0" /> <?php esc_html_e('days', 'modifus-smtp'); ?>
            <p class="description"><?php esc_html_e('Older logs are deleted once a day. 0 keeps logs forever.', 'modifus-smtp'); ?></p></td></tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <hr>
    <h2><?php esc_html_e('Send Test Email', 'modifus-smtp'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('modifus_smtp_send_test_email', 'modifus_smtp_test_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="modifus_smtp_test_email"><?php esc_html_e('To Email', 'modifus-smtp'); ?></label></th>
                <td>
                    <input type="email" name="modifus_smtp_test_email" id="modifus_smtp_test_email" required class="regular-text" />
                    <p class="description"><?php esc_html_e('Enter an email address to send a test message.', 'modifus-smtp'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Send Test Email', 'modifus-smtp'), 'primary', 'modifus_smtp_submit_test_email', false); ?>
    </form>
    <?php
}

add_action('admin_init', function () {
    register_setting('modifus_smtp_settings_group', 'modifus_smtp_settings', [
        'sanitize_callback' => 'modifus_smtp_sanitize_settings'
    ]);
});

/**
 * Sanitize SMTP settings before saving.
 *
 * Only form submissions (marked with _form) are processed; values saved by
 * the plugin itself pass through unchanged. This also keeps the password
 * from being encrypted twice when WordPress runs the callback twice on the
 * first save.
 */
function modifus_smtp_sanitize_settings($input) {
    if (!is_array($input) || empty($input['_form'])) {
        return $input;
    }

    $existing = get_option('modifus_smtp_settings', []);
    $existing = is_array($existing) ? $existing : [];

    $sanitized = [];

    $sanitized['smtp_host'] = isset($input['smtp_host'])
        ? sanitize_text_field($input['smtp_host'])
        : '';

    $sanitized['smtp_secure'] = isset($input['smtp_secure']) && in_array($input['smtp_secure'], ['ssl', 'tls', ''], true)
        ? $input['smtp_secure']
        : 'tls';

    $port = isset($input['smtp_port']) ? absint($input['smtp_port']) : 0;
    $sanitized['smtp_port'] = ($port >= 1 && $port <= 65535) ? $port : modifus_smtp_default_port($sanitized['smtp_secure']);

    $sanitized['smtp_auth'] = !empty($input['smtp_auth']);

    $sanitized['smtp_user'] = isset($input['smtp_user'])
        ? sanitize_text_field($input['smtp_user'])
        : '';

    // Password: keep existing if empty, otherwise encrypt the new value
    $sanitized['smtp_pass'] = (!modifus_smtp_password_in_config() && isset($input['smtp_pass']) && $input['smtp_pass'] !== '')
        ? modifus_smtp_encrypt($input['smtp_pass'])
        : ($existing['smtp_pass'] ?? '');

    $sanitized['from_email'] = isset($input['from_email'])
        ? sanitize_email($input['from_email'])
        : '';

    $sanitized['from_name'] = isset($input['from_name'])
        ? sanitize_text_field($input['from_name'])
        : '';

    $sanitized['force_from'] = !empty($input['force_from']);

    $sanitized['log_retention_days'] = isset($input['log_retention_days'])
        ? absint($input['log_retention_days'])
        : 0;

    return $sanitized;
}
