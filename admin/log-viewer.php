<?php
function wzp_email_logs_page() {
    $logs_table = new WZP_Email_Logs_Table();
    $logs_table->prepare_items();
    ?>
    <div class="wzp-email-logs-wrap">
        <form method="get">
            <input type="hidden" name="page" value="wzp-smtp" />
            <input type="hidden" name="tab" value="logs" />
            <?php
            $logs_table->search_box('Search Emails', 'wzp-search');
            $logs_table->display();
            ?>
        </form>
    </div>

    <div id="email-log-modal-overlay">
        <div id="email-log-modal">
            <button id="email-log-modal-close" aria-label="Close Modal">&times;</button>
            <h2>Email Content</h2>
            <table class="widefat striped">
                <tr><th>Sent at</th><td id="log-sent-at"></td></tr>
                <tr><th>To</th><td id="log-to"></td></tr>
                <tr><th>Subject</th><td id="log-subject"></td></tr>
            </table>

            <div class="modal-toolbar">
                <button class="button" id="toggle-raw">Raw Email Content</button>
                <button class="button" id="toggle-html">Preview Content as HTML</button>
            </div>

            <textarea id="email-raw" readonly></textarea>
            <iframe id="email-html-preview" sandbox="allow-same-origin"></iframe>
        </div>
    </div>
<?php
}
