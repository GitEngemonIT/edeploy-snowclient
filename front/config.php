<?php

/**
 * ServiceNow Configuration Frontend
 */

include ('../../../inc/includes.php');

if (empty($_GET["id"])) {
   $_GET["id"] = 1;
}

$config = new PluginEdeploysnowclientConfig();

if (isset($_POST["add"])) {
   $config->check(-1, CREATE, $_POST);
   if ($config->add($_POST)) {
      Event::log($_POST['id'], "snowclientconfig", 4, "setup",
                 sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
   }
   Html::back();

} else if (isset($_POST["update"])) {
   if (!isset($_POST['id']) || empty($_POST['id'])) {
      Session::addMessageAfterRedirect(__('Configuration ID missing. Could not save.', 'snowclient'), false, ERROR);
      Html::back();
      exit;
   }

   // Don't overwrite password if not provided
   if (isset($_POST['password']) && $_POST['password'] === '') {
      unset($_POST['password']);
   }

   $result = $config->update($_POST);

   if ($result) {
      Session::addMessageAfterRedirect(__('Configuration updated successfully!', 'snowclient'), false, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Error updating configuration. Check logs.', 'snowclient'), false, ERROR);
   }
   Event::log($_POST['id'], "snowclientconfig", 4, "setup",
              sprintf(__('%1$s updates the item %2$s'), $_SESSION["glpiname"],
                      $config->fields["name"]));
   Html::back();

} else if (isset($_POST["test_connection"])) {
   if (!empty($_POST['id'])) {
      $config->getFromDB($_POST['id']);
   }
   
   try {
      $api = new PluginEdeploysnowclientApi();
      $result = $api->testConnection();
      
      if ($result['success']) {
         Session::addMessageAfterRedirect("✅ " . $result['message'], false, INFO);
      } else {
         Session::addMessageAfterRedirect("❌ " . $result['message'], false, ERROR);
      }
   } catch (Exception $e) {
      Session::addMessageAfterRedirect(
         "❌ " . sprintf(__('Error testing connection: %s', 'snowclient'), $e->getMessage()), 
         false, 
         ERROR
      );
   }
   Html::back();

} else {
   Html::header(PluginEdeploysnowclientConfig::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], "config", "plugins");
   $config->display($_GET);
   Html::footer();
}
