<?php

/**
 * ServiceNow Configuration Form Handler
 */

include '../../../inc/includes.php';

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('snowclient') || !$plugin->isActivated('snowclient')) {
    Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginSnowclientConfig();

if (isset($_POST['update'])) {
    $config->check($_POST['id'], UPDATE);
    $config->update($_POST);
    Session::addMessageAfterRedirect(__('Configuration updated successfully', 'snowclient'));
    Html::back();
}

Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginSnowclientConfig$1'));
