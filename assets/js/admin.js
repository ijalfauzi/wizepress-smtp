jQuery(document).ready(function ($) {

    $(document).on("click", ".view-email", function () {
        const id = $(this).data("id");

        $.get(wzpAjax.ajax_url, {
            action: "wzp_get_email_log",
            id: id
        }, function (res) {
            $("#log-sent-at").text(res.sent_at);
            $("#log-to").text(res.to);
            $("#log-subject").text(res.subject);
            $("#email-raw").val(res.message);
            $("#email-html-preview").attr("srcdoc", res.message);
            $("#email-raw").show();
            $("#email-html-preview").hide();
            $("#email-log-modal-overlay").fadeIn(200);
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