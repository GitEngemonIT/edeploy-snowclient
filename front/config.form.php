<?php

/**
 * ServiceNow Configuration Form Handler
 */

include '../../../inc/includes.php';

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('edeploysnowclient') || !$plugin->isActivated('edeploysnowclient')) {
    Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginEdeploysnowclientConfig();

if (isset($_POST['update'])) {
    $config->check($_POST['id'], UPDATE);
    $config->update($_POST);
    Session::addMessageAfterRedirect(__('Configuration updated successfully', 'edeploysnowclient'));
    Html::back();
}

Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginEdeploysnowclientConfig$1'));
