jQuery(document).ready(function ($) {

    // View email content
    $(document).on("click", ".view-email", function (e) {
        e.preventDefault();
        const id = $(this).data("id");

        $.get(wzpAjax.ajax_url, {
            action: "wzp_get_email_log",
            id: id,
            nonce: wzpAjax.nonce
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
                alert(response.data || "Failed to load email log.");
            }
        });
    });

    // Delete email log
    $(document).on("click", ".delete-email", function (e) {
        e.preventDefault();

        if (!confirm("Are you sure you want to delete this email log?")) {
            return;
        }

        const $link = $(this);
        const $row = $link.closest("tr");
        const id = $link.data("id");
        const nonce = $link.data("nonce");

        $.post(wzpAjax.ajax_url, {
            action: "wzp_delete_email_log",
            id: id,
            nonce: nonce
        }, function (response) {
            if (response.success) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                });
            } else {
                alert(response.data || "Failed to delete log.");
            }
        });
    });

    // Resend email
    $(document).on("click", ".resend-email", function (e) {
        e.preventDefault();

        if (!confirm("Are you sure you want to resend this email?")) {
            return;
        }

        const $link = $(this);
        const id = $link.data("id");

        $link.text("Sending...");

        $.post(wzpAjax.ajax_url, {
            action: "wzp_resend_email",
            id: id,
            nonce: wzpAjax.nonce
        }, function (response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert(response.data || "Failed to resend email.");
                $link.text("Resend");
            }
        }).fail(function () {
            alert("Request failed. Please try again.");
            $link.text("Resend");
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