<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/GitEngemonIT/edeploy-snowclient
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
   @link      https://github.com/GitEngemonIT/edeploy-snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginEdeploysnowclientProfile
 */
class PluginEdeploysnowclientProfile extends CommonDBTM
{
    static $rightname = "profile";

    /**
     * @param int $nb
     *
     * @return translated
     */
    static function getTypeName($nb = 0)
    {
        return __('ServiceNow Client', 'edeploysnowclient');
    }

    /**
     * @param \Profile $prof
     */
    static function createFirstAccess(\Profile $prof)
    {
        foreach (self::getAllRights() as $right) {
            self::addDefaultProfileInfos($prof->getID(), [$right['field'] => ALLSTANDARDRIGHT]);
        }
    }

    /**
     * @return array
     */
    static function getAllRights()
    {
        $rights = [
            [
                'itemtype' => 'PluginEdeploysnowclientConfig',
                'label' => __('ServiceNow Configuration', 'edeploysnowclient'),
                'field' => 'plugin_edeploysnowclient_config'
            ],
        ];

        return $rights;
    }

    /**
     * @param $ID
     * @param $options array
     *
     * @return bool
     */
    function showForm($ID, $options = [])
    {
        $profile = new Profile();
        if ($ID && !$profile->getFromDB($ID)) {
            return false;
        }

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        echo "<form method='post' action='" . $profile->getFormURL() . "'>";

        $rights = self::getAllRights();
        if (count($rights)) {
            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='4'>" . __('Rights assignment') . "</th>";
            echo "</tr>";

            foreach ($rights as $info) {
                self::showSummary($profile->fields, $canedit, $info['field'], $info['label']);
            }

            if ($canedit) {
                echo "<tr class='tab_bg_1'>";
                echo "<td colspan='4' class='center'>";
                echo "<input type='hidden' name='id' value='$ID'>";
                echo "<input type='submit' name='update' value='" . _sx('button', 'Save') . "' class='submit'>";
                echo "</td></tr>";
            }

            echo "</table>";
        }

        Html::closeForm();
        return true;
    }

    /**
     * Print the field
     */
    static function showSummary($fields, $canedit, $name, $label)
    {
        $value = isset($fields[$name]) ? $fields[$name] : 0;

        echo "<tr class='tab_bg_2'>";
        echo "<td width='20%'>$label</td>";
        echo "<td>";

        if ($canedit) {
            Profile::dropdownNoneReadWrite($name, $value, 1, 1);
        } else {
            echo Profile::getRightValue($value);
        }

        echo "</td>";
        echo "</tr>";
    }

    static function install(Migration $migration)
    {
        global $DB;

        $rights = self::getAllRights();

        foreach ($rights as $data) {
            if (!$DB->fieldExists('glpi_profiles', $data['field'])) {
                $migration->addField('glpi_profiles', $data['field'], 'char', [
                    'value' => NULL,
                    'update' => 0
                ]);
                $migration->addKey('glpi_profiles', $data['field']);
            }
        }

        // Add right to admin profile
        foreach ($DB->request("SELECT id FROM glpi_profiles") as $prof) {
            foreach ($rights as $data) {
                $DB->updateOrInsert('glpi_profilerights', [
                    'profiles_id' => $prof['id'],
                    'name' => $data['field'],
                    'rights' => ALLSTANDARDRIGHT
                ], [
                    'profiles_id' => $prof['id'],
                    'name' => $data['field']
                ]);
            }
        }
    }

    static function uninstall(Migration $migration)
    {
        global $DB;

        $rights = self::getAllRights();

        foreach ($rights as $data) {
            // Remove field from profiles table
            if ($DB->fieldExists('glpi_profiles', $data['field'])) {
                $migration->dropField('glpi_profiles', $data['field']);
            }

            // Remove from profilerights
            $DB->delete('glpi_profilerights', [
                'name' => $data['field']
            ]);
        }
    }
}
