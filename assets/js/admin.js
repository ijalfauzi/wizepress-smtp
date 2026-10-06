jQuery(document).ready(function ($) {

    // View email content
    $(document).on("click", ".view-email", function (e) {
        e.preventDefault();
        const id = $(this).data("id");

        $.get(modifusSmtp.ajax_url, {
            action: "modifus_smtp_get_email_log",
            id: id,
            nonce: modifusSmtp.nonce
        }, function (response) {
            if (response.success) {
                const res = response.data;
                $("#log-sent-at").text(res.sent_at);
                $("#log-to").text(res.to);
                $("#log-subject").text(res.subject);
                $("#email-raw").val(res.message);
                $("#email-html-preview").attr("srcdoc", res.message);
                $("#email-raw").show();
                $("#email-html-preview").hide();

                // Show error message if email failed
                if (res.result === 0 && res.error_message) {
                    $("#log-error-message").text(res.error_message);
                    $("#log-error-row").show();
                } else {
                    $("#log-error-row").hide();
                }

                $("#email-log-modal-overlay").css('display', 'flex').hide().fadeIn(200);
            } else {
                alert(response.data || modifusSmtp.i18n.loadFailed);
            }
        });
    });

    // Delete email log
    $(document).on("click", ".delete-email", function (e) {
        e.preventDefault();

        if (!confirm(modifusSmtp.i18n.confirmDelete)) {
            return;
        }

        const $link = $(this);
        const $row = $link.closest("tr");
        const id = $link.data("id");
        const nonce = $link.data("nonce");

        $.post(modifusSmtp.ajax_url, {
            action: "modifus_smtp_delete_email_log",
            id: id,
            nonce: nonce
        }, function (response) {
            if (response.success) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                });
            } else {
                alert(response.data || modifusSmtp.i18n.deleteFailed);
            }
        });
    });

    // Resend email
    $(document).on("click", ".resend-email", function (e) {
        e.preventDefault();

        if (!confirm(modifusSmtp.i18n.confirmResend)) {
            return;
        }

        const $link = $(this);
        const id = $link.data("id");

        $link.text(modifusSmtp.i18n.sending);

        $.post(modifusSmtp.ajax_url, {
            action: "modifus_smtp_resend_email",
            id: id,
            nonce: modifusSmtp.nonce
        }, function (response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert(response.data || modifusSmtp.i18n.resendFailed);
                $link.text(modifusSmtp.i18n.resend);
            }
        }).fail(function () {
            alert(modifusSmtp.i18n.requestFailed);
            $link.text(modifusSmtp.i18n.resend);
        });
    });

    // Toggle HTML preview
    $("#toggle-html").click(function () {
        $("#email-html-preview").show();
        $("#email-raw").hide();
    });

    // Toggle raw content
    $("#toggle-raw").click(function () {
        $("#email-html-preview").hide();
        $("#email-raw").show();
    });

    // Close modal
    $("#email-log-modal-close, #email-log-modal-overlay").click(function (e) {
        if (e.target.id === "email-log-modal-overlay" || e.target.id === "email-log-modal-close") {
            $("#email-log-modal-overlay").fadeOut();
        }
    });
});