<?php
/*
   ------------------------------------------------------------------------
   Plugin eDeploy ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/GitEngemonIT/edeploy-snowclient
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin eDeploy ServiceNow Client project.
   Plugin eDeploy ServiceNow Client is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   ------------------------------------------------------------------------
   @package   Plugin eDeploy ServiceNow Client
   @author    EngemonIT
   @co-author
   @copyright Copyright (c) 2025 eDeploy ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/GitEngemonIT/edeploy-snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

//plugin version
define('PLUGIN_EDEPLOYSNOWCLIENT_VERSION', '1.2.1');
// Minimal GLPI version
define('PLUGIN_EDEPLOYSNOWCLIENT_MIN_GLPI', '9.4');
// Maximum GLPI version
define('PLUGIN_EDEPLOYSNOWCLIENT_MAX_GLPI', '10.1.1');

define('PLUGIN_EDEPLOYSNOWCLIENT_NAME', 'eDeploy ServiceNow Client');

function plugin_version_edeploysnowclient()
{
    return [
        'name' => PLUGIN_EDEPLOYSNOWCLIENT_NAME,
        'version' => PLUGIN_EDEPLOYSNOWCLIENT_VERSION,
        'author' => 'EngemonIT',
        'license' => 'GPL v3+',
        'homepage' => 'https://github.com/GitEngemonIT/edeploy-snowclient',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_EDEPLOYSNOWCLIENT_MIN_GLPI,
                'max' => PLUGIN_EDEPLOYSNOWCLIENT_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_init_edeploysnowclient()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['edeploysnowclient'] = true;

    // Load translations
    $PLUGIN_HOOKS['language']['edeploysnowclient'] = [
        'pt_BR' => __DIR__ . '/locales/pt_BR.php',
        'en_US' => __DIR__ . '/locales/en_US.php',
    ];

    $plugin = new Plugin();

    if ($plugin->isActivated('edeploysnowclient')) {
        $PLUGIN_HOOKS['config_page']['edeploysnowclient'] = 'front/config.php';

        Plugin::registerClass('PluginEdeploysnowclientProfile', ['addtabon' => 'Profile']);
        Plugin::registerClass('PluginEdeploysnowclientConfig', ['addtabon' => 'Config']);
        Plugin::registerClass('PluginEdeploysnowclientApi');

        // Hook pre_item_add para interceptar antes da adição
        $PLUGIN_HOOKS['pre_item_add']['edeploysnowclient'] = [
            'ITILSolution' => 'plugin_edeploysnowclient_pre_item_add',
        ];

        $PLUGIN_HOOKS['item_add']['edeploysnowclient'] = [
            'Ticket' => 'plugin_edeploysnowclient_item_add',
            'ITILFollowup' => 'plugin_edeploysnowclient_item_add',
            'ITILSolution' => 'plugin_edeploysnowclient_item_add',
            'Document' => 'plugin_edeploysnowclient_item_add',
            'Document_Item' => 'plugin_edeploysnowclient_item_add',
        ];

        $PLUGIN_HOOKS['item_update']['edeploysnowclient'] = [
            'Ticket' => 'plugin_edeploysnowclient_item_update',
            'ITILFollowup' => 'plugin_edeploysnowclient_item_update',
            'ITILSolution' => 'plugin_edeploysnowclient_item_update',
        ];

        $PLUGIN_HOOKS['item_delete']['edeploysnowclient'] = [
            'Ticket' => 'plugin_edeploysnowclient_item_delete',
        ];

        // Add menu item
        $PLUGIN_HOOKS['menu_toadd']['edeploysnowclient'] = [
            'plugins' => 'PluginEdeploysnowclientConfig',
        ];

        // Hook para adicionar botões personalizados na tela de ticket
        $PLUGIN_HOOKS['use_massive_action']['edeploysnowclient'] = 1;

        // Hook para adicionar JavaScript e CSS globalmente
        $PLUGIN_HOOKS['add_javascript']['edeploysnowclient'] = ['js/snowclient.js', 'js/solution_modal.js'];

        $PLUGIN_HOOKS['add_css']['edeploysnowclient'] = ['css/snowclient.css', 'css/solution_modal.css'];

        // Hook para debug de modal de solução
        include_once __DIR__ . '/inc/solution_resources.php';
        $PLUGIN_HOOKS['post_item_form']['edeploysnowclient'] = [
            'Ticket' => 'plugin_edeploysnowclient_add_solution_resources',
        ];
    }
}

function plugin_edeploysnowclient_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_EDEPLOYSNOWCLIENT_MIN_GLPI, 'lt') || version_compare(GLPI_VERSION, PLUGIN_EDEPLOYSNOWCLIENT_MAX_GLPI, 'gt')) {
        echo sprintf(__('Este plugin requer uma versão do GLPI entre %s e %s'), PLUGIN_EDEPLOYSNOWCLIENT_MIN_GLPI, PLUGIN_EDEPLOYSNOWCLIENT_MAX_GLPI);
        return false;
    }

    // Check for cURL extension
    if (!extension_loaded('curl')) {
        echo __('Este plugin requer a extensão cURL');
        return false;
    }

    return true;
}

function plugin_edeploysnowclient_check_config()
{
    return true;
}

/**
 * Plugin update function
 */
function plugin_edeploysnowclient_update($current_version)
{
    global $DB;

    $migration = new Migration(PLUGIN_EDEPLOYSNOWCLIENT_VERSION);
    $migration->displayMessage('Atualizando plugin eDeploy ServiceNow Client');

    // Verificar se é necessário atualizar a partir de versões anteriores
    if (version_compare($current_version, '1.0.4', '<')) {
        // Atualizar tabela de configuração
        $table = 'glpi_plugin_edeploysnowclient_configs';

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
            $DB->updateOrDie(
                $table,
                [
                    'sync_tickets' => 1,
                ],
                [
                    'id' => 1,
                    'sync_tickets' => null,
                ],
            );

            $DB->updateOrDie(
                $table,
                [
                    'sync_documents' => 0,
                ],
                [
                    'id' => 1,
                    'sync_documents' => null,
                ],
            );
        }

        // Criar tabela de mappings se não existir
        $mappingTable = 'glpi_plugin_edeploysnowclient_mappings';
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
        $migration->displayMessage('Updating to 1.0.7 - Simplified sys_id handling via API');
        // Não fazemos migração de dados - deixamos como está e usamos sempre a API
    }

    // Migração para versão 1.0.9 - Correções críticas de segurança
    if (version_compare($current_version, '1.0.9', '<')) {
        $migration->displayMessage('Updating to 1.0.9 - Critical security fixes for entity validation');
        // Correções implementadas no código:
        // - Revalidação de entidade em afterTicketUpdate()
        // - Revalidação de entidade em afterTicketDelete()
        // - Validação de entidade em afterDocumentAdd()
        // - Validação de entidade em afterDocumentItemAdd()
        // Nenhuma migração de dados necessária - apenas correções de código
    }

    // Migração para versão 1.1.0 - Nova funcionalidade de devolução de tickets
    if (version_compare($current_version, '1.1.0', '<')) {
        $migration->displayMessage('Updating to 1.1.0 - Added return ticket functionality');

        // Adicionar campo return_queue_group se não existir
        $table = 'glpi_plugin_edeploysnowclient_configs';
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
        $migration->displayMessage('Updating to 1.1.1 - Fix return_queue_group field');

        // Garantir que o campo return_queue_group existe
        $table = 'glpi_plugin_edeploysnowclient_configs';
        if ($DB->tableExists($table)) {
            if (!$DB->fieldExists($table, 'return_queue_group')) {
                $migration->displayMessage('Adding missing return_queue_group field');
                $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                $migration->migrationOneTable($table);
            } else {
                $migration->displayMessage('return_queue_group field already exists');
            }
        }

        // Correções implementadas:
        // - Campo return_queue_group adicionado corretamente
        // - Posicionamento do botão de devolução melhorado
        // - Detecção automática de tickets integrados
    }

    // Migração para versão 1.1.2 - Garantir que o campo return_queue_group existe
    if (version_compare($current_version, '1.1.2', '<')) {
        $migration->displayMessage('Updating to 1.1.2 - Ensure return_queue_group field exists');

        $table = 'glpi_plugin_edeploysnowclient_configs';
        if ($DB->tableExists($table)) {
            if (!$DB->fieldExists($table, 'return_queue_group')) {
                $migration->displayMessage('Adding missing return_queue_group field to existing installation');
                $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                $migration->migrationOneTable($table);
                $migration->displayMessage('return_queue_group field added successfully');
            } else {
                $migration->displayMessage('return_queue_group field already exists - no action needed');
            }
        }

        // Melhorias implementadas:
        // - Verificação robusta da existência do campo return_queue_group
        // - Correção de problema de persistência do campo
        // - Posicionamento melhorado do botão próximo ao menu de ações
    }

    // Migração para versão 1.1.6 - Adicionar campos customizados do ServiceNow
    if (version_compare($current_version, '1.1.6', '<')) {
        $migration->displayMessage('Updating to 1.1.6 - Adding ServiceNow custom fields configuration');

        $table = 'glpi_plugin_edeploysnowclient_configs';
        if ($DB->tableExists($table)) {
            // Adicionar campo para valor padrão de close_code (Solução)
            if (!$DB->fieldExists($table, 'default_close_code')) {
                $migration->addField($table, 'default_close_code', 'varchar(255) DEFAULT "Definitiva"');
            }

            // Adicionar campo para valor padrão do tipo de encerramento
            if (!$DB->fieldExists($table, 'default_close_type')) {
                $migration->addField($table, 'default_close_type', 'varchar(255) DEFAULT "Remoto"');
            }

            // Adicionar campo para valor padrão da classificação da solução
            if (!$DB->fieldExists($table, 'default_solution_class')) {
                $migration->addField($table, 'default_solution_class', 'varchar(255) DEFAULT "Aplicação (Software)"');
            }

            // Adicionar campo para armazenar as opções do código de solução
            if (!$DB->fieldExists($table, 'solution_codes')) {
                $migration->addField($table, 'solution_codes', 'text', [
                    'after' => 'default_solution_class',
                    'value' => json_encode(['Rollback / Plano de Retorno', 'Restart de Serviços', 'Reset de senha', 'Reprocessamento de Loja', 'Reprocessamento de Logs', 'Reprocessamento de Extrato', 'Reprocessamento de Dados', 'Reprocessamento de Arquivo', 'Reiniciada aplicação', 'Processamento de pedidos', 'Parametrização', 'Outros', 'Orientação Operacional', 'Normalizado sem intervenção', 'Manutenção Fornecedor', 'Indexação de itens', 'Duplicado', 'Criação de Perfil', 'Configuração do serviços', 'Configuração de Monitoração', 'Cadastro de API', 'Alteração de dados cadastrais', 'Ajuste de permissão', 'Ajuste de Performace', 'Ajuste de Perfil']),
                ]);
            }

            $migration->migrationOneTable($table);
            $migration->displayMessage('ServiceNow custom fields configuration added successfully');
        }
    }

    $migration->executeMigration();

    return true;
}

/**
 * Plugin installation function
 */
function plugin_edeploysnowclient_install()
{
    $migration = new Migration(PLUGIN_EDEPLOYSNOWCLIENT_VERSION);

    // Install config table
    PluginEdeploysnowclientConfig::install($migration);

    // Install API mapping table directly here
    global $DB;
    $mappingTable = 'glpi_plugin_edeploysnowclient_mappings';
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
function plugin_edeploysnowclient_uninstall()
{
    $migration = new Migration(PLUGIN_EDEPLOYSNOWCLIENT_VERSION);

    // Drop config table
    PluginEdeploysnowclientConfig::uninstall($migration);

    // Drop mapping table
    global $DB;
    $mappingTable = 'glpi_plugin_edeploysnowclient_mappings';
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
function plugin_edeploysnowclient_upgrade($migrations)
{
    global $DB;
    $migration = new Migration(PLUGIN_EDEPLOYSNOWCLIENT_VERSION);

    $table = 'glpi_plugin_edeploysnowclient_configs';

    foreach ($migrations as $version) {
        switch ($version) {
            case '1.1.1':
            case '1.1.2':
            case '1.1.3':
            case '1.1.4':
                // Garantir que o campo return_queue_group existe para instalações existentes
                if ($DB->tableExists($table)) {
                    if (!$DB->fieldExists($table, 'return_queue_group')) {
                        $migration->displayMessage('Adding missing return_queue_group field to existing installation');
                        $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
                        $migration->displayMessage('return_queue_group field added successfully');
                    }
                }
                break;

            case '1.1.6':
            case '1.2.0':
                // Garantir que os campos customizados do ServiceNow existem
                if ($DB->tableExists($table)) {
                    $migration->displayMessage('Updating to 1.2.0 - Adding eDeploy ServiceNow custom fields');

                    if (!$DB->fieldExists($table, 'default_close_code')) {
                        $migration->addField($table, 'default_close_code', 'varchar(255) DEFAULT "Definitiva"', ['after' => 'debug_mode']);
                        $migration->displayMessage('Added field: default_close_code');
                    }
                    if (!$DB->fieldExists($table, 'default_close_type')) {
                        $migration->addField($table, 'default_close_type', 'varchar(255) DEFAULT "Remoto"', ['after' => 'default_close_code']);
                        $migration->displayMessage('Added field: default_close_type');
                    }
                    if (!$DB->fieldExists($table, 'default_solution_class')) {
                        $migration->addField($table, 'default_solution_class', 'varchar(255) DEFAULT "Aplicação (Software)"', ['after' => 'default_close_type']);
                        $migration->displayMessage('Added field: default_solution_class');
                    }
                    if (!$DB->fieldExists($table, 'solution_codes')) {
                        $migration->addField($table, 'solution_codes', 'text', [
                            'after' => 'default_solution_class',
                        ]);

                        // Atualizar com os valores padrão
                        $DB->updateOrDie(
                            $table,
                            [
                                'solution_codes' => json_encode(['Rollback / Plano de Retorno', 'Restart de Serviços', 'Reset de senha', 'Reprocessamento de Loja', 'Reprocessamento de Logs', 'Reprocessamento de Extrato', 'Reprocessamento de Dados', 'Reprocessamento de Arquivo', 'Reiniciada aplicação', 'Processamento de pedidos', 'Parametrização', 'Outros', 'Orientação Operacional', 'Normalizado sem intervenção', 'Manutenção Fornecedor', 'Indexação de itens', 'Duplicado', 'Criação de Perfil', 'Configuração do serviços', 'Configuração de Monitoração', 'Cadastro de API', 'Alteração de dados cadastrais', 'Ajuste de permissão', 'Ajuste de Performace', 'Ajuste de Perfil']),
                            ],
                            ['id' => 1],
                        );

                        $migration->displayMessage('Added field: solution_codes with default values');
                    }

                    $migration->migrationOneTable($table);
                    $migration->displayMessage('eDeploy ServiceNow custom fields configuration completed');
                }
                break;
            case '1.2.2':
                // Adicionar campo default_technician_id para atribuição automática
                if ($DB->tableExists($table)) {
                    if (!$DB->fieldExists($table, 'default_technician_id')) {
                        $migration->displayMessage('Adding default_technician_id field for auto-assignment');
                        $migration->addField($table, 'default_technician_id', 'int NOT NULL DEFAULT 0', ['after' => 'return_queue_group']);
                        $migration->migrationOneTable($table);
                        $migration->displayMessage('default_technician_id field added successfully');
                    }

                    // Adicionar campos obrigatórios do eDeploy
                    if (!$DB->fieldExists($table, 'default_group_id')) {
                        $migration->displayMessage('Adding default_group_id field');
                        $migration->addField($table, 'default_group_id', 'int NOT NULL DEFAULT 0', ['after' => 'default_technician_id']);
                        $migration->migrationOneTable($table);
                    }

                    if (!$DB->fieldExists($table, 'default_solutiontype_id')) {
                        $migration->displayMessage('Adding default_solutiontype_id field');
                        $migration->addField($table, 'default_solutiontype_id', 'int NOT NULL DEFAULT 0', ['after' => 'default_group_id']);
                        $migration->migrationOneTable($table);
                    }

                    if (!$DB->fieldExists($table, 'default_solutiontemplate_id')) {
                        $migration->displayMessage('Adding default_solutiontemplate_id field');
                        $migration->addField($table, 'default_solutiontemplate_id', 'int NOT NULL DEFAULT 0', ['after' => 'default_solutiontype_id']);
                        $migration->migrationOneTable($table);
                    }

                    if (!$DB->fieldExists($table, 'default_itilcategory_id')) {
                        $migration->displayMessage('Adding default_itilcategory_id field');
                        $migration->addField($table, 'default_itilcategory_id', 'int NOT NULL DEFAULT 0', ['after' => 'default_solutiontemplate_id']);
                        $migration->migrationOneTable($table);
                    }
                }
                break;
        }
    }

    $migration->executeMigration();
    return true;
}
