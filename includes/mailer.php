<?php
/**
 * SMTP configuration, From address and password storage.
 *
 * @package Modifus_SMTP
 */

defined('ABSPATH') || exit;

/**
 * Saved settings merged with defaults.
 */
function modifus_smtp_get_settings() {
    $saved = get_option('modifus_smtp_settings', []);
    $saved = is_array($saved) ? $saved : [];

    $defaults = [
        'smtp_host'          => '',
        'smtp_port'          => 587,
        'smtp_secure'        => 'tls',
        // On for new installs. Settings saved before 2.0.0 have no smtp_auth:
        // they authenticated whenever a username was set.
        'smtp_auth'          => empty($saved) || !empty($saved['smtp_user']),
        'smtp_user'          => '',
        'smtp_pass'          => '',
        'from_email'         => '',
        'from_name'          => '',
        'force_from'         => true,
        'log_retention_days' => 0,
    ];

    return array_merge($defaults, $saved);
}

/**
 * Whether the password is set in wp-config.php instead of the database.
 */
function modifus_smtp_password_in_config() {
    return defined('MODIFUS_SMTP_PASSWORD');
}

/**
 * The SMTP password in plain text, or '' if it can't be decrypted.
 */
function modifus_smtp_get_password() {
    if (modifus_smtp_password_in_config()) {
        return (string) MODIFUS_SMTP_PASSWORD;
    }
    $stored = modifus_smtp_get_settings()['smtp_pass'];
    $plain  = modifus_smtp_decrypt($stored);
    return $plain === false ? '' : $plain;
}

/**
 * Encryption key derived from the site's secret keys in wp-config.php.
 */
function modifus_smtp_crypto_key() {
    $key  = defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '';
    $salt = defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : '';
    return hash('sha256', 'modifus-smtp|' . $key . '|' . $salt, true);
}

/**
 * Encrypt a password for storage. Falls back to plain text without OpenSSL.
 */
function modifus_smtp_encrypt($plain) {
    if ($plain === '' || !function_exists('openssl_encrypt')) {
        return $plain;
    }
    $key    = modifus_smtp_crypto_key();
    $iv     = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return $plain;
    }
    $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return 'enc:' . base64_encode($iv . $mac . $cipher);
}

/**
 * Decrypt a stored password. Plain-text values (saved before 2.0.0) are
 * returned as is. Returns false if the value can't be decrypted, e.g.
 * after the secret keys in wp-config.php were changed.
 */
function modifus_smtp_decrypt($stored) {
    if (!is_string($stored) || strpos($stored, 'enc:') !== 0) {
        return (string) $stored;
    }
    if (!function_exists('openssl_decrypt')) {
        return false;
    }
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 49) {
        return false;
    }
    $key    = modifus_smtp_crypto_key();
    $iv     = substr($raw, 0, 16);
    $mac    = substr($raw, 16, 32);
    $cipher = substr($raw, 48);
    if (!hash_equals(hash_hmac('sha256', $iv . $cipher, $key, true), $mac)) {
        return false;
    }
    return openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Default port for an encryption type.
 */
function modifus_smtp_default_port($secure) {
    if ($secure === 'ssl') {
        return 465;
    }
    return $secure === 'tls' ? 587 : 25;
}

// Send through SMTP
add_action('phpmailer_init', function ($phpmailer) {
    $opt = modifus_smtp_get_settings();
    if (empty($opt['smtp_host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $opt['smtp_host'];
    $phpmailer->Port = absint($opt['smtp_port']) ?: modifus_smtp_default_port($opt['smtp_secure']);

    if (!empty($opt['smtp_auth'])) {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $opt['smtp_user'];
        $phpmailer->Password = modifus_smtp_get_password();
    } else {
        $phpmailer->SMTPAuth = false;
    }

    // Handle encryption setting
    if (!empty($opt['smtp_secure'])) {
        $phpmailer->SMTPSecure = $opt['smtp_secure'];
        $phpmailer->SMTPAutoTLS = true;
    } else {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }
});

/**
 * Configured From address: the From Email setting, else the SMTP username
 * if it's an email address.
 */
function modifus_smtp_configured_from_email() {
    $opt = modifus_smtp_get_settings();
    if (empty($opt['smtp_host'])) {
        return '';
    }
    if (!empty($opt['from_email'])) {
        return $opt['from_email'];
    }
    return (!empty($opt['smtp_auth']) && is_email($opt['smtp_user'])) ? $opt['smtp_user'] : '';
}

/**
 * The address wp_mail() uses when nothing else sets one.
 */
function modifus_smtp_wp_default_from() {
    $sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);
    if (is_string($sitename) && strpos($sitename, 'www.') === 0) {
        $sitename = substr($sitename, 4);
    }
    return 'wordpress@' . $sitename;
}

// From address: always when forced, otherwise only replace WordPress's default
add_filter('wp_mail_from', function ($from) {
    $configured = modifus_smtp_configured_from_email();
    if ($configured === '') {
        return $from;
    }
    $force = !empty(modifus_smtp_get_settings()['force_from']);
    return ($force || $from === modifus_smtp_wp_default_from()) ? $configured : $from;
}, 999);

add_filter('wp_mail_from_name', function ($name) {
    $opt = modifus_smtp_get_settings();
    if (empty($opt['smtp_host'])) {
        return $name;
    }
    $configured = !empty($opt['from_name']) ? $opt['from_name'] : wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    return (!empty($opt['force_from']) || $name === 'WordPress') ? $configured : $name;
}, 999);

// What was actually used for the current message, for the log
global $modifus_smtp_current_mail;
$modifus_smtp_current_mail = [];

add_filter('wp_mail', function ($atts) {
    global $modifus_smtp_current_mail;
    $modifus_smtp_current_mail = [];
    return $atts;
}, PHP_INT_MAX);

add_action('phpmailer_init', function ($phpmailer) {
    global $modifus_smtp_current_mail;
    $modifus_smtp_current_mail = [
        'from'         => $phpmailer->From,
        'content_type' => $phpmailer->ContentType,
    ];
}, PHP_INT_MAX);
