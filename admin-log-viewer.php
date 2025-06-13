<?php
function wzp_email_logs_page() {
    global $wpdb;

    //  Ensure the table exists
    $table = WZP_SMTP_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        to_email TEXT NOT NULL,
        subject TEXT NOT NULL,
        headers TEXT,
        attachments TEXT,
        message LONGTEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Fetch logs
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY sent_at DESC LIMIT 100");

    echo '<table class="widefat"><thead><tr><th>Sent At</th><th>To</th><th>Subject</th><th>Action</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime(get_date_from_gmt($log->sent_at)))) . '</td>';
        echo '<td>' . esc_html(implode(", ", (array) maybe_unserialize($log->to_email))) . '</td>';
        echo '<td>' . esc_html($log->subject) . '</td>';
        echo '<td><button class="button view-email" data-id="' . esc_attr($log->id) . '">View</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    ?>

<style>
#email-log-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
}
#email-log-modal {
    background: #fff;
    padding: 20px;
    width: 90%;
    max-width: 900px;
    margin: 5% auto;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 9999;
}
#email-log-modal h2 {
    margin-top: 0;
    font-size: 1.4em;
}
#email-log-modal table {
    margin-bottom: 1em;
}
#email-log-modal th {
    text-align: left;
    padding-right: 10px;
    vertical-align: top;
    width: 20%;
}
#email-log-modal .modal-toolbar {
    margin: 1rem 0;
}
#email-log-modal textarea,
#email-log-modal iframe {
    width: 100%;
    height: 300px;
    display: none;
    border: 1px solid #ccc;
    font-family: monospace;
}
#email-log-modal-close {
    position: absolute;
    top: 10px; right: 10px;
    background: #eee;
    border: none;
    border-radius: 5px;
    font-size: 1.2em;
    padding: 5px 10px;
    cursor: pointer;
}
</style>

<div id="email-log-modal-overlay">
    <div id="email-log-modal">
        <button id="email-log-modal-close" aria-label="Close Modal">&times;</button>
        <h2>Email Content</h2>
        <table class="widefat striped">
            <tr><th>Sent at:</th><td id="log-sent-at"></td></tr>
            <tr><th>To:</th><td id="log-to"></td></tr>
            <tr><th>Subject:</th><td id="log-subject"></td></tr>
        </table>

        <div class="modal-toolbar">
            <button class="button" id="toggle-raw">Raw Email Content</button>
            <button class="button" id="toggle-html">Preview Content as HTML</button>
        </div>

        <textarea id="email-raw" readonly></textarea>
        <iframe id="email-html-preview"></iframe>
    </div>
</div>

<script>
jQuery(document).ready(function ($) {
    $(".view-email").click(function () {
        const id = $(this).data("id");
        $.get(ajaxurl, { action: "wzp_get_email_log", id: id }, function (res) {
            $("#log-sent-at").text(res.sent_at);
            $("#log-to").text(res.to);
            $("#log-subject").text(res.subject);
            $("#email-raw").val(res.message);
            $("#email-html-preview").attr("srcdoc", res.message);
            $("#email-raw").show();
            $("#email-html-preview").hide();
            $("#email-log-modal-overlay").fadeIn();
        });
    });

    $("#toggle-html").click(function () {
        $("#email-html-preview").show();
        $("#email-raw").hide();
    });

    $("#toggle-raw").click(function () {
        $("#email-html-preview").hide();
        $("#email-raw").show();
    });

    $("#email-log-modal-close, #email-log-modal-overlay").click(function (e) {
        if (e.target.id === "email-log-modal-overlay" || e.target.id === "email-log-modal-close") {
            $("#email-log-modal-overlay").fadeOut();
        }
    });
});
</script>

<?php
}
