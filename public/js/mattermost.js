require(['domReady', 'thebuggenie/tbg', 'jquery'], function (domReady, tbgjs, $) {
    domReady(function () {
        var submitSetupStep = function(e) {
            var form_id        = 'mattermost_settings_form',
                $form          = $('#' + form_id),
                $indicator     = $('#' + form_id + '_indicator'),
                $submit_button = $('#' + form_id + '_button'),
                url            = $form.attr("action");

            $indicator.show();
            e.preventDefault();

            var submitStep = function () {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        type: 'POST',
                        data: $form.serialize(),
                        url: url,
                        success: resolve,
                        error: function (details) {
                            $indicator.hide();
                            $submit_button.attr('disabled', false);
                            reject(details);
                        }
                    });
                });
            };

            submitStep()
                .then(function (result) {
                    $indicator.hide();
                    $form.addClass('disabled');
                    $('#mattermost_address_container').addClass('verified');
                    $('#mattermost_webhook_url_input').attr('disabled', true);
                }, function (details) {
                    tbgjs.Main.Helpers.Message.error(details.responseJSON.error);
                });
        };

        $('#mattermost_settings_form').submit(submitSetupStep);

        $('#mattermost_settings_change_button').click(function (e) {
            e.preventDefault();

            $('#mattermost_settings_form').removeClass('disabled');
            $('#mattermost_address_container').removeClass('verified');
            $('#mattermost_webhook_url_input').attr('disabled', false);
        })
    });
});
