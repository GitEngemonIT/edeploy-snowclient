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
define('PLUGIN_SNOWCLIENT_VERSION', '1.1.14');
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

        // Hook pre_item_add para interceptar antes da adição
        $PLUGIN_HOOKS['pre_item_add']['snowclient'] = [
            'ITILSolution' => 'plugin_snowclient_pre_item_add'
        ];

        $PLUGIN_HOOKS['item_add']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_add',
            'ITILFollowup' => 'plugin_snowclient_item_add',
            'ITILSolution' => 'plugin_snowclient_item_add',
            'Document' => 'plugin_snowclient_item_add',
            'Document_Item' => 'plugin_snowclient_item_add',
        ];

        $PLUGIN_HOOKS['item_update']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_update',
            'ITILFollowup' => 'plugin_snowclient_item_update',
            'ITILSolution' => 'plugin_snowclient_item_update',
        ];

        $PLUGIN_HOOKS['item_delete']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_item_delete',
        ];

        // Add menu item
        $PLUGIN_HOOKS['menu_toadd']['snowclient'] = [
            'plugins' => 'PluginSnowclientConfig'
        ];

        // Hook para adicionar botões personalizados na tela de ticket
        $PLUGIN_HOOKS['use_massive_action']['snowclient'] = 1;
        
        // Hook para adicionar JavaScript e CSS
        $PLUGIN_HOOKS['add_javascript']['snowclient'] = [
            'js/snowclient.js',
            'js/solution_modal.js'
        ];
        
        $PLUGIN_HOOKS['add_css']['snowclient'] = [
            'css/snowclient.css',
            'css/solution_modal.css'
        ];

        // Hook para adicionar recursos do modal de solução
        include_once __DIR__ . '/inc/solution_resources.php';
        $PLUGIN_HOOKS['post_item_form']['snowclient'] = [
            'Ticket' => 'plugin_snowclient_add_solution_resources'
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
              `snow_number` varchar(20) NOT NULL DEFAULT '',
              `snow_type` varchar(50) NOT NULL DEFAULT 'incident',
              `date_creation` timestamp NULL DEFAULT NULL,
              `date_mod` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `glpi_ticket_id` (`glpi_ticket_id`),
              KEY `snow_sys_id` (`snow_sys_id`),
              KEY `snow_number` (`snow_number`),
              KEY `snow_type` (`snow_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());
        }
    }
    
    // Migração para versão 1.0.7 - Simplificação: usar sempre API para buscar sys_id
    if (version_compare($current_version, '1.0.7', '<')) {
        $migration->displayMessage("Updating to 1.0.7 - Simplified sys_id handling via API");
        // Não fazemos migração de dados - deixamos como está e usamos sempre a API
    }
    
    // Migração para versão 1.0.9 - Correções críticas de segurança
    if (version_compare($current_version, '1.0.9', '<')) {
        $migration->displayMessage("Updating to 1.0.9 - Critical security fixes for entity validation");
        // Correções implementadas no código:
        // - Revalidação de entidade em afterTicketUpdate()
        // - Revalidação de entidade em afterTicketDelete() 
        // - Validação de entidade em afterDocumentAdd()
        // - Validação de entidade em afterDocumentItemAdd()
        // Nenhuma migração de dados necessária - apenas correções de código
    }
    
    // Migração para versão 1.1.0 - Nova funcionalidade de devolução de tickets
    if (version_compare($current_version, '1.1.0', '<')) {
        $migration->displayMessage("Updating to 1.1.0 - Added return ticket functionality");
        
        // Adicionar campo return_queue_group se não existir
        $table = 'glpi_plugin_snowclient_configs';
        if ($DB->tableExists($table)) {
            if (!$DB->fieldExists($table, 'return_queue_group')) {
                $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL');
                $migration->migrationOneTable($table);
            }
        }
        
        // Nova funcionalidade implementada:
        // - Botão "Devolver ao ServiceNow" em tickets
        // - Modal de justificativa para devolução
        // - Resolução automática no GLPI
        // - Transferência para fila específica no ServiceNow sem resolver
        // - API para busca de grupos de atribuição
        // - Campo de configuração para fila padrão de devolução
        // - Sistema de flags para evitar loops de sincronização
    }
    
    // Migração para versão 1.1.1 - Correção do campo return_queue_group
    if (version_compare($current_version, '1.1.1', '<')) {
        $migration->displayMessage("Updating to 1.1.1 - Fix return_queue_group field");
        
        // Garantir que o campo return_queue_group existe
        $table = 'glpi_plugin_snowclient_configs';
        if ($DB->tableExists($table)) {
            if (!$DB->fieldExists($table, 'return_queue_group')) {
                $migration->displayMessage("Adding missing return_queue_group field");
                $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                $migration->migrationOneTable($table);
            } else {
                $migration->displayMessage("return_queue_group field already exists");
            }
        }
        
        // Correções implementadas:
        // - Campo return_queue_group adicionado corretamente
        // - Posicionamento do botão de devolução melhorado
        // - Detecção automática de tickets integrados
    }
    
    // Migração para versão 1.1.2 - Garantir que o campo return_queue_group existe
    if (version_compare($current_version, '1.1.2', '<')) {
        $migration->displayMessage("Updating to 1.1.2 - Ensure return_queue_group field exists");
        
        $table = 'glpi_plugin_snowclient_configs';
        if ($DB->tableExists($table)) {
            if (!$DB->fieldExists($table, 'return_queue_group')) {
                $migration->displayMessage("Adding missing return_queue_group field to existing installation");
                $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                $migration->migrationOneTable($table);
                $migration->displayMessage("return_queue_group field added successfully");
            } else {
                $migration->displayMessage("return_queue_group field already exists - no action needed");
            }
        }
        
        // Melhorias implementadas:
        // - Verificação robusta da existência do campo return_queue_group
        // - Correção de problema de persistência do campo
        // - Posicionamento melhorado do botão próximo ao menu de ações
    }

    // Migração para versão 1.1.6 - Adicionar campos customizados do ServiceNow
    if (version_compare($current_version, '1.1.6', '<')) {
        $migration->displayMessage("Updating to 1.1.6 - Adding ServiceNow custom fields configuration");
        
        $table = 'glpi_plugin_snowclient_configs';
        if ($DB->tableExists($table)) {
            // Adicionar campo para valor padrão do tipo de encerramento
            if (!$DB->fieldExists($table, 'default_close_type')) {
                $migration->addField($table, 'default_close_type', 'varchar(255) DEFAULT "Definitiva"');
            }
            
            // Adicionar campo para valor padrão da classificação da solução
            if (!$DB->fieldExists($table, 'default_solution_class')) {
                $migration->addField($table, 'default_solution_class', 'varchar(255) DEFAULT "Presencial/Hardware"');
            }
            
            // Adicionar campo para armazenar as opções do código de solução
            if (!$DB->fieldExists($table, 'solution_codes')) {
                $migration->addField($table, 'solution_codes', 'text', [
                    'after' => 'default_solution_class',
                    'value' => json_encode([
                        'Manutenção de cabeamento',
                        'Manutenção do equipamento',
                        'Normalizado sem intervenção',
                        'Substituição de cabeamento',
                        'Substituição do equipamento'
                    ])
                ]);
            }
            
            $migration->migrationOneTable($table);
            $migration->displayMessage("ServiceNow custom fields configuration added successfully");
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
          `snow_number` varchar(20) NOT NULL DEFAULT '',
          `snow_type` varchar(50) NOT NULL DEFAULT 'incident',
          `date_creation` timestamp NULL DEFAULT NULL,
          `date_mod` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `glpi_ticket_id` (`glpi_ticket_id`),
          KEY `snow_sys_id` (`snow_sys_id`),
          KEY `snow_number` (`snow_number`),
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

/**
 * Plugin upgrade function
 */
function plugin_snowclient_upgrade($migrations)
{
    global $DB;
    $migration = new Migration(PLUGIN_SNOWCLIENT_VERSION);
    
    $table = 'glpi_plugin_snowclient_configs';
    
    foreach ($migrations as $version) {
        switch ($version) {
            case '1.1.1':
            case '1.1.2':
            case '1.1.3':
            case '1.1.4':
                // Garantir que o campo return_queue_group existe para instalações existentes
                if ($DB->tableExists($table)) {
                    if (!$DB->fieldExists($table, 'return_queue_group')) {
                        $migration->displayMessage("Adding missing return_queue_group field to existing installation");
                        $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                        $migration->displayMessage("return_queue_group field added successfully");
                    }
                }
                break;
            
            case '1.1.6':
                // Garantir que os campos customizados do ServiceNow existem
                if ($DB->tableExists($table)) {
                    if (!$DB->fieldExists($table, 'default_close_type')) {
                        $migration->addField($table, 'default_close_type', 'varchar(255) DEFAULT "Definitiva"');
                    }
                    if (!$DB->fieldExists($table, 'default_solution_class')) {
                        $migration->addField($table, 'default_solution_class', 'varchar(255) DEFAULT "Presencial/Hardware"');
                    }
                    if (!$DB->fieldExists($table, 'solution_codes')) {
                        $migration->addField($table, 'solution_codes', 'text', [
                            'after' => 'default_solution_class',
                            'value' => json_encode([
                                'Manutenção de cabeamento',
                                'Manutenção do equipamento',
                                'Normalizado sem intervenção',
                                'Substituição de cabeamento',
                                'Substituição do equipamento'
                            ])
                        ]);
                    }
                    $migration->migrationOneTable($table);
                }
                break;
        }
    }
    
    $migration->executeMigration();
    return true;
}
