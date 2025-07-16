<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/engemon/snowclient
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin ServiceNow Client project.
   Plugin ServiceNow Client is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   ------------------------------------------------------------------------
   @package   Plugin ServiceNow Client
   @author    EngemonIT
   @co-author
   @copyright Copyright (c) 2025 ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/engemon/snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

//Session::checkRight("plugin_snowclient_config", READ);

if (empty($_GET["id"])) {
   $_GET["id"] = 1;
}

$config = new PluginSnowclientConfig();

if (isset($_POST["add"])) {
   $config->check(-1, CREATE, $_POST);
   if ($config->add($_POST)) {
      Event::log($_POST['id'], "snowclientconfig", 4, "setup",
                 sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
   }
   Html::back();

} else if (isset($_POST["delete"])) {
   $config->check($_POST['id'], DELETE);
   $config->delete($_POST);
   Event::log($_POST['id'], "snowclientconfig", 4, "setup",
              sprintf(__('%1$s deletes the item %2$s'), $_SESSION["glpiname"],
                      $config->fields["name"]));
   $config->redirectToList();

} else if (isset($_POST["restore"])) {
   $config->check($_POST['id'], DELETE);
   $config->restore($_POST);
   Event::log($_POST['id'], "snowclientconfig", 4, "setup",
              sprintf(__('%1$s restores the item %2$s'), $_SESSION["glpiname"],
                      $config->fields["name"]));
   $config->redirectToList();

} else if (isset($_POST["purge"])) {
   $config->check($_POST['id'], PURGE);
   $config->delete($_POST, 1);
   Event::log($_POST['id'], "snowclientconfig", 4, "setup",
              sprintf(__('%1$s purges the item %2$s'), $_SESSION["glpiname"],
                      $config->fields["name"]));
   $config->redirectToList();

} else if (isset($_POST["update"])) {
   if (!isset($_POST['id']) || empty($_POST['id'])) {
      Session::addMessageAfterRedirect(__('ID de configuração ausente. Não foi possível salvar.', 'snowclient'), false, ERROR);
      Html::back();
      exit;
   }

   // Não sobrescrever senha se não informada
   if (isset($_POST['password']) && $_POST['password'] === '') {
      unset($_POST['password']);
   }

   Toolbox::logDebug("Dados recebidos para atualização: " . print_r($_POST, true));
   $result = $config->update($_POST);
   Toolbox::logDebug("Resultado da atualização: " . ($result ? "sucesso" : "falha"));

   if ($result) {
      Session::addMessageAfterRedirect(__('Configuração atualizada com sucesso!', 'snowclient'), false, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Erro ao atualizar configuração. Verifique os logs.', 'snowclient'), false, ERROR);
   }
   Event::log($_POST['id'], "snowclientconfig", 4, "setup",
              sprintf(__('%1$s updates the item %2$s'), $_SESSION["glpiname"],
                      $config->fields["name"]));
   Html::back();

} else if (isset($_POST["test_connection"])) {
   // Test ServiceNow connection using saved configuration (no form validation)
   if (!empty($_POST['id'])) {
      $config->getFromDB($_POST['id']);
   }
   
   try {
      // Test ServiceNow connection using current saved configuration
      $api = new PluginSnowclientApi();
      $result = $api->testConnection();
      
      if ($result['success']) {
         Session::addMessageAfterRedirect("✅ " . $result['message'], false, INFO);
      } else {
         Session::addMessageAfterRedirect("❌ " . $result['message'], false, ERROR);
      }
   } catch (Exception $e) {
      Session::addMessageAfterRedirect(
         "❌ " . sprintf(__('Erro ao testar conexão: %s', 'snowclient'), $e->getMessage()), 
         false, 
         ERROR
      );
   }
   Html::back();

} else {
   Html::header(PluginSnowclientConfig::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], "config", "plugins");
   $config->display($_GET);
   Html::footer();
}
