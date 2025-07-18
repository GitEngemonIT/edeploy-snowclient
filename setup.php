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

//plugin version
define('PLUGIN_SNOWCLIENT_VERSION', '1.0.5');
// Minimal GLPI version
define('PLUGIN_SNOWCLIENT_MIN_GLPI', '9.4');
// Maximum GLPI version
define('PLUGIN_SNOWCLIENT_MAX_GLPI', '10.1.1');

define('PLUGIN_SNOWCLIENT_NAME', 'Cliente ServiceNow');

function plugin_version_snowclient()
{
    return [
        'name' => PLUGIN_SNOWCLIENT_NAME,
        'version' => PLUGIN_SNOWCLIENT_VERSION,
        'author' => 'EngemonIT',
        'license' => 'GPL v3+',
        'homepage' => 'https://github.com/engemon/snowclient',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SNOWCLIENT_MIN_GLPI,
                'max' => PLUGIN_SNOWCLIENT_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_init_snowclient()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['snowclient'] = true;
    
    // Load translations
    $PLUGIN_HOOKS['language']['snowclient'] = [
        'pt_BR' => __DIR__ . '/locales/pt_BR.php',
        'en_US' => __DIR__ . '/locales/en_US.php',
    ];

    $plugin = new Plugin();

    if ($plugin->isActivated('snowclient')) {
        $PLUGIN_HOOKS['config_page']['snowclient'] = 'front/config.php';

        Plugin::registerClass('PluginSnowclientProfile', ['addtabon' => 'Profile']);
        Plugin::registerClass('PluginSnowclientConfig', ['addtabon' => 'Config']);
        Plugin::registerClass('PluginSnowclientApi');

        $PLUGIN_HOOKS['item_add']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_add',
            'ITILFollowup' => 'plugin_snowclient_item_add',
            'Document' => 'plugin_snowclient_item_add',
        ];

        $PLUGIN_HOOKS['item_update']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_update',
            'ITILFollowup' => 'plugin_snowclient_item_update',
        ];

        $PLUGIN_HOOKS['item_delete']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_delete',
        ];

        // Add menu item
        $PLUGIN_HOOKS['menu_toadd']['snowclient'] = [
            'plugins' => 'PluginSnowclientConfig'
        ];
    }
}

function plugin_snowclient_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_SNOWCLIENT_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_SNOWCLIENT_MAX_GLPI, 'gt')) {
        echo sprintf(
            __('Este plugin requer uma versão do GLPI entre %s e %s'),
            PLUGIN_SNOWCLIENT_MIN_GLPI,
            PLUGIN_SNOWCLIENT_MAX_GLPI
        );
        return false;
    }

    // Check for cURL extension
    if (!extension_loaded('curl')) {
        echo __('Este plugin requer a extensão cURL');
        return false;
    }

    return true;
}

function plugin_snowclient_check_config()
{
    return true;
}

/**
 * Plugin update function
 */
function plugin_snowclient_update($current_version)
{
    global $DB;
    
    $migration = new Migration(PLUGIN_SNOWCLIENT_VERSION);
    $migration->displayMessage("Atualizando plugin ServiceNow Client");
    
    // Verificar se é necessário atualizar a partir de versões anteriores
    if (version_compare($current_version, '1.0.4', '<')) {
        // Atualizar tabela de configuração
        $table = 'glpi_plugin_snowclient_configs';
        
        if ($DB->tableExists($table)) {
            // Adicionar campos faltantes se não existirem
            if (!$DB->fieldExists($table, 'sync_tickets')) {
                $migration->addField($table, 'sync_tickets', 'tinyint NOT NULL DEFAULT 1');
                $migration->migrationOneTable($table);
            }
            
            if (!$DB->fieldExists($table, 'sync_documents')) {
                $migration->addField($table, 'sync_documents', 'tinyint NOT NULL DEFAULT 0');
                $migration->migrationOneTable($table);
            }
            
            // Atualizar registros existentes com valores padrão
            $DB->updateOrDie($table, [
                'sync_tickets' => 1
            ], [
                'id' => 1,
                'sync_tickets' => null
            ]);
            
            $DB->updateOrDie($table, [
                'sync_documents' => 0
            ], [
                'id' => 1,
                'sync_documents' => null
            ]);
        }
        
        // Criar tabela de mappings se não existir
        $mappingTable = 'glpi_plugin_snowclient_mappings';
        if (!$DB->tableExists($mappingTable)) {
            $migration->displayMessage("Installing $mappingTable");
            $default_charset = DBConnection::getDefaultCharset();
            $default_collation = DBConnection::getDefaultCollation();
            $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

            $query = "CREATE TABLE IF NOT EXISTS $mappingTable (
              `id` int {$default_key_sign} NOT NULL auto_increment,
              `glpi_ticket_id` int NOT NULL,
              `snow_sys_id` varchar(255) NOT NULL,
              `snow_type` varchar(50) NOT NULL DEFAULT 'incident',
              `date_creation` timestamp NULL DEFAULT NULL,
              `date_mod` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `glpi_ticket_id` (`glpi_ticket_id`),
              KEY `snow_sys_id` (`snow_sys_id`),
              KEY `snow_type` (`snow_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());
        }
    }
    
    $migration->executeMigration();
    
    return true;
}

/**
 * Plugin installation function
 */
function plugin_snowclient_install()
{
    $migration = new Migration(PLUGIN_SNOWCLIENT_VERSION);
    
    // Install config table
    PluginSnowclientConfig::install($migration);
    
    // Install API mapping table directly here
    global $DB;
    $mappingTable = 'glpi_plugin_snowclient_mappings';
    if (!$DB->tableExists($mappingTable)) {
        $migration->displayMessage("Installing $mappingTable");
        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $query = "CREATE TABLE IF NOT EXISTS $mappingTable (
          `id` int {$default_key_sign} NOT NULL auto_increment,
          `glpi_ticket_id` int NOT NULL,
          `snow_sys_id` varchar(255) NOT NULL,
          `snow_type` varchar(50) NOT NULL DEFAULT 'incident',
          `date_creation` timestamp NULL DEFAULT NULL,
          `date_mod` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `glpi_ticket_id` (`glpi_ticket_id`),
          KEY `snow_sys_id` (`snow_sys_id`),
          KEY `snow_type` (`snow_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

        $DB->queryOrDie($query, $DB->error());
    }
    
    $migration->executeMigration();
    return true;
}

/**
 * Plugin uninstallation function
 */
function plugin_snowclient_uninstall()
{
    $migration = new Migration(PLUGIN_SNOWCLIENT_VERSION);
    
    // Drop config table
    PluginSnowclientConfig::uninstall($migration);
    
    // Drop mapping table
    global $DB;
    $mappingTable = 'glpi_plugin_snowclient_mappings';
    if ($DB->tableExists($mappingTable)) {
        $migration->displayMessage("Uninstalling $mappingTable");
        $migration->dropTable($mappingTable);
    }
    
    $migration->executeMigration();
    return true;
}
