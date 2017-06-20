<style>
    .address-container:before {
        background-image: url('<?= image_url('cfg_icon_mattermost_padded.png', false, 'mattermost'); ?>');
    }
</style>
<div class="address-settings">
    <p><?= __('The Bug Genie can integrate with %mattermost_icon Mattermost (%link_to_mattermost) to notify about events such as new issues, releases and more.', ['%mattermost_icon' => image_tag('icon_mattermost.png', ['style' => 'display: inline-block; width: 16px; vertical-align: middle; margin-left: 3px;'], false, 'mattermost'), '%link_to_mattermost' => '<a href="https://about.mattermost.com/">https://about.mattermost.com/</a>']); ?></p>
    <p><?= __('These integrations can be configured per-project, here you can set a default URL for the Mattermost incoming webhook that will be used for all projects with Mattermost integration enabled.'); ?></p>
    <form action="<?= make_url('configure_mattermost_settings'); ?>" accept-charset="<?= \thebuggenie\core\framework\Context::getI18n()->getCharset(); ?>" action="<?= make_url('configure_mattermost_settings'); ?>" method="post" onsubmit="return false;" id="mattermost_settings_form" class="<?php if ($webhook_url) echo 'disabled'; ?>">
        <div class="address-container<?php if ($webhook_url) echo ' verified'; ?>" id="mattermost_address_container">
            <img class="verified" src="<?= image_url('icon_ok.png'); ?>">
            <input type="text" id="mattermost_webhook_url_input" value="<?= $webhook_url; ?>" name="webhook_url" <?php if ($webhook_url) echo 'disabled'; ?> placeholder="https://mattermost.example.com/hooks/[...]">
        </div>
        <input type="submit" id="mattermost_form_button" class="button" value="<?= __('Next'); ?>">
        <a href="#" id="mattermost_settings_change_button" class="button button-silver change-button"><?= __('Change'); ?></a>
        <span id="mattermost_form_indicator" style="display: none;" class="indicator"><?= image_tag('spinning_20.gif'); ?></span>
    </form>
    <p><?= __('To communicate with Mattermost, you need to create this incoming webhook for your team. Follow this guide on how to setup incoming webhooks: %link_to_new_webhook', ['%link_to_new_webhook' => link_tag('https://docs.mattermost.com/developer/webhooks-incoming.html#creating-integrations-using-incoming-webhooks', null, ['target' => '_blank'])]); ?></p>
</div>
