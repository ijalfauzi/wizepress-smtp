<?php
function wzp_smtp_settings_page() {
    $options = get_option('wzp_smtp_settings', []);

    // Handle test email submission
    if (
        isset($_POST['wzp_test_email']) &&
        check_admin_referer('wzp_send_test_email', 'wzp_test_nonce')
    ) {
        $to = sanitize_email($_POST['wzp_test_email']);
        $subject = 'WizePress SMTP Test Email';
        $body = "Hello,\n\nThis is a test email sent from WizePress SMTP plugin.\n\nIf you received this, your SMTP settings are working!";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $mail_args = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $body,
            'headers'     => $headers,
        ];

        if (wp_mail(
            $mail_args['to'],
            $mail_args['subject'],
            $mail_args['message'],
            $mail_args['headers']
        )) {
            echo '<div class="notice notice-success"><p>✅ Test email sent to <strong>' . esc_html($to) . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Failed to send test email. Please check your SMTP settings.</p></div>';
        }
    }

    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('wzp_smtp_settings_group');
        do_settings_sections('wzp-smtp');
        ?>
        <table class="form-table">
            <tr><th>SMTP Host</th>
            <td><input type="text" name="wzp_smtp_settings[smtp_host]" value="<?php echo esc_attr($options['smtp_host'] ?? ''); ?>" class="regular-text" /></td></tr>
            <tr><th>SMTP Port</th>
            <td><input type="number" name="wzp_smtp_settings[smtp_port]" value="<?php echo esc_attr($options['smtp_port'] ?? 465); ?>" class="small-text" /></td></tr>
            <tr><th>Encryption</th>
            <td><select name="wzp_smtp_settings[smtp_secure]">
                <option value="ssl" <?php selected($options['smtp_secure'] ?? '', 'ssl'); ?>>SSL</option>
                <option value="tls" <?php selected($options['smtp_secure'] ?? '', 'tls'); ?>>TLS</option>
            </select></td></tr>
            <tr><th>SMTP Username</th>
            <td><input type="text" name="wzp_smtp_settings[smtp_user]" value="<?php echo esc_attr($options['smtp_user'] ?? ''); ?>" class="regular-text" /></td></tr>
            <tr><th>SMTP Password</th>
            <td><input type="password" name="wzp_smtp_settings[smtp_pass]" value="<?php echo esc_attr($options['smtp_pass'] ?? ''); ?>" class="regular-text" /></td></tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <hr>
    <h2>Send Test Email</h2>
    <form method="post">
        <?php wp_nonce_field('wzp_send_test_email', 'wzp_test_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wzp_test_email">To Email</label></th>
                <td>
                    <input type="email" name="wzp_test_email" required class="regular-text" />
                    <p class="description">Enter an email address to send a test message.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Send Test Email', 'primary', 'wzp_submit_test_email', false); ?>
    </form>
    <?php
}

add_action('admin_init', function () {
    register_setting('wzp_smtp_settings_group', 'wzp_smtp_settings');
});
