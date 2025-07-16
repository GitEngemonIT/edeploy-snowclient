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
   @copyright Copyright (c) 2025 ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/engemon/snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * ServiceNow Client Configuration Class
 */
class PluginSnowclientConfig extends CommonDBTM
{
    // ServiceNow ticket types
    public const INCIDENT = 1;
    public const REQUEST = 2;
    public const CHANGE = 3;
    public const PROBLEM = 4;
    
    private static $_instance = null;

    /**
     * PluginSnowclientConfig constructor.
     */
    function __construct()
    {
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    static function canCreate()
    {
        return Session::haveRight('config', UPDATE);
    }

    static function canView()
    {
        return Session::haveRight('config', READ);
    }

    static function canUpdate()
    {
        return Session::haveRight('config', UPDATE);
    }

    /**
     * @param int $nb
     *
     * @return translated
     */
    static function getTypeName($nb = 0)
    {
        return __('Cliente ServiceNow', 'snowclient');
    }

    /**
     * Get search options for the configuration
     */
    static function getSearchOptionsNew()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Características')
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'name',
            'name'               => __('Nome'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'snow_url',
            'name'               => __('URL do ServiceNow', 'snowclient'),
            'datatype'           => 'string',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'snow_username',
            'name'               => __('Usuário ServiceNow', 'snowclient'),
            'datatype'           => 'string',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => __('Entidade'),
            'datatype'           => 'dropdown',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'sync_followups',
            'name'               => __('Sincronizar Acompanhamentos', 'snowclient'),
            'datatype'           => 'bool',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'sync_status',
            'name'               => __('Sincronizar Status', 'snowclient'),
            'datatype'           => 'bool',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'debug_mode',
            'name'               => __('Modo Debug', 'snowclient'),
            'datatype'           => 'bool',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'date_creation',
            'name'               => __('Data de criação'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => 'glpi_plugin_snowclient_configs',
            'field'              => 'date_mod',
            'name'               => __('Última atualização'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        return $tab;
    }

    static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    /**
     * @param bool $update
     *
     * @return PluginSnowclientConfig
     */
    static function getConfig($update = false)
    {
        $config = new self();

        if ($update) {
            $config->getFromDB(1);
        }
        return $config;
    }

    static function showConfigForm()
    {
        $config = new self();
        if (!$config->getFromDB(1)) {
            $config->getEmpty();
        }

        $config->showForm(1, [
            'canedit' => Session::haveRight('config', UPDATE)
        ]);
    }

    function showForm($ID, $options = [])
    {
        if (!Session::haveRight('config', READ)) {
            return false;
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Log para verificar os valores carregados
        Toolbox::logDebug("Valores carregados para o formulário: " . print_r($this->fields, true));

        // Info header explicando o fluxo
        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo "<div style='background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong style='color: #1976d2;'>🔄 " . __('Integration Flow', 'snowclient') . "</strong><br>";
        echo "<span style='color: #424242;'>";
        echo __('1. ServiceNow creates ticket → 2. Plugin replicates to GLPI → 3. Technician updates only in GLPI → 4. Plugin syncs back to ServiceNow', 'snowclient');
        echo "</span>";
        echo "</div>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('URL da Instância ServiceNow', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'instance_url', [
            'placeholder' => 'https://your-instance.service-now.com'
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Usuário', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'username');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Senha', 'snowclient') . "</td>";
        echo "<td>";
        echo "<input type='password' name='password' value='' placeholder='" . __('Digite a senha para alterar', 'snowclient') . "' />";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Grupo de Atribuição Padrão', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'assignment_group');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Entidade para Integração', 'snowclient') . "</td>";
        echo "<td>";
        Entity::dropdown([
            'name' => 'entities_id',
            'value' => $this->fields['entities_id'],
            'comments' => false,
            'toupdate' => false
        ]);
        echo "<br><span class='small'>" . __('Apenas tickets desta entidade e suas filhas serão sincronizados', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Tipo de Solicitação ServiceNow', 'snowclient') . "</td>";
        echo "<td>";
        RequestType::dropdown([
            'name' => 'request_type',
            'value' => $this->fields['request_type'],
            'comments' => false
        ]);
        echo "<br><span class='small'>" . __('Tipo de solicitação usado para identificar tickets vindos do ServiceNow', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Usuário da API ServiceNow', 'snowclient') . "</td>";
        echo "<td>";
        User::dropdown([
            'name' => 'api_user',
            'value' => $this->fields['api_user'],
            'comments' => false
        ]);
        echo "<br><span class='small'>" . __('Usuário usado para operações da API e acompanhamentos', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center tab_bg_2'>";
        echo "<strong>" . __('Opções de Sincronização (ServiceNow → GLPI → ServiceNow)', 'snowclient') . "</strong>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sincronizar Tickets', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_tickets', $this->fields['sync_tickets']);
        echo "<br><span class='small'>" . __('Habilitar sincronização bidirecional de tickets', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sincronizar Acompanhamentos/Atualizações', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_followups', $this->fields['sync_followups']);
        echo "<br><span class='small'>" . __('Enviar atualizações do GLPI de volta para ServiceNow como notas de trabalho', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sincronizar Mudanças de Status', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_status', $this->fields['sync_status']);
        echo "<br><span class='small'>" . __('Atualizar status do ticket ServiceNow quando alterado no GLPI', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sincronizar Documentos', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_documents', $this->fields['sync_documents']);
        echo "<br><span class='small'>" . __('Sincronizar anexos entre GLPI e ServiceNow', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Tipo de Ticket Padrão', 'snowclient') . "</td>";
        echo "<td>";
        $types = [
            self::INCIDENT => __('Incidente', 'snowclient'),
            self::REQUEST => __('Solicitação de Serviço', 'snowclient'),
            self::CHANGE => __('Solicitação de Mudança', 'snowclient'),
            self::PROBLEM => __('Problema', 'snowclient'),
        ];
        Dropdown::showFromArray('default_type', $types, [
            'value' => $this->fields['default_type']
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Habilitar Modo Debug', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('debug_mode', $this->fields['debug_mode']);
        echo "</td>";
        echo "</tr>";
        
        // Adicionar botão de teste de conexão
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='test_connection' value='" . __('Testar Conexão ServiceNow', 'snowclient') . "' class='btn btn-info' style='background-color: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        return true;
    }

    function prepareInputForUpdate($input)
    {
        // Validar URL
        if (isset($input['instance_url']) && !empty($input['instance_url'])) {
            if (!filter_var($input['instance_url'], FILTER_VALIDATE_URL)) {
                Session::addMessageAfterRedirect(__('URL inválida', 'snowclient'), false, ERROR);
                return false;
            }
        }

        // Validar usuário
        if (isset($input['username']) && empty($input['username'])) {
            Session::addMessageAfterRedirect(__('Usuário não pode ser vazio', 'snowclient'), false, ERROR);
            return false;
        }

        // Criptografar senha se fornecida e não estiver vazia
        if (isset($input['password']) && !empty($input['password'])) {
            if (method_exists('Toolbox', 'sodiumEncrypt')) {
                $input['password'] = Toolbox::sodiumEncrypt($input['password']);
            } else {
                $input['password'] = base64_encode($input['password']);
            }
        } else {
            // Se senha não foi fornecida, manter a atual
            unset($input['password']);
        }

        // Garantir que campos booleanos tenham valores padrão
        if (!isset($input['sync_tickets'])) {
            $input['sync_tickets'] = 0;
        }
        if (!isset($input['sync_followups'])) {
            $input['sync_followups'] = 0;
        }
        if (!isset($input['sync_status'])) {
            $input['sync_status'] = 0;
        }
        if (!isset($input['sync_documents'])) {
            $input['sync_documents'] = 0;
        }
        if (!isset($input['debug_mode'])) {
            $input['debug_mode'] = 0;
        }

        Toolbox::logDebug("Dados preparados para atualização: " . print_r($input, true));
        return $input;
    }

    function prepareInputForAdd($input)
    {
        // Validar URL
        if (isset($input['instance_url']) && !empty($input['instance_url'])) {
            if (!filter_var($input['instance_url'], FILTER_VALIDATE_URL)) {
                Session::addMessageAfterRedirect(__('URL inválida', 'snowclient'), false, ERROR);
                return false;
            }
        }

        // Validar usuário
        if (isset($input['username']) && empty($input['username'])) {
            Session::addMessageAfterRedirect(__('Usuário não pode ser vazio', 'snowclient'), false, ERROR);
            return false;
        }

        // Criptografar senha se fornecida
        if (isset($input['password']) && !empty($input['password'])) {
            if (method_exists('Toolbox', 'sodiumEncrypt')) {
                $input['password'] = Toolbox::sodiumEncrypt($input['password']);
            } else {
                $input['password'] = base64_encode($input['password']);
            }
        }

        // Garantir que campos booleanos tenham valores padrão
        if (!isset($input['sync_followups'])) {
            $input['sync_followups'] = 1;
        }
        if (!isset($input['sync_status'])) {
            $input['sync_status'] = 1;
        }
        if (!isset($input['debug_mode'])) {
            $input['debug_mode'] = 0;
        }

        Toolbox::logDebug("Dados preparados para inserção: " . print_r($input, true));
        return $input;
    }

    static function install(Migration $migration)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS $table (
              `id` int {$default_key_sign} NOT NULL auto_increment,
              `instance_url` varchar(255) DEFAULT NULL,
              `username` varchar(255) DEFAULT NULL,
              `password` text DEFAULT NULL,
              `assignment_group` varchar(255) DEFAULT NULL,
              `entities_id` int NOT NULL DEFAULT '0',
              `request_type` int NOT NULL DEFAULT '0',
              `api_user` int NOT NULL DEFAULT '0',
              `sync_tickets` tinyint NOT NULL DEFAULT '1',
              `sync_followups` tinyint NOT NULL DEFAULT '1',
              `sync_status` tinyint NOT NULL DEFAULT '1',
              `sync_documents` tinyint NOT NULL DEFAULT '0',
              `default_type` int NOT NULL DEFAULT '1',
              `debug_mode` tinyint NOT NULL DEFAULT '0',
              `date_mod` timestamp NULL DEFAULT NULL,
              `date_creation` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            try {
                $DB->queryOrDie($query, $DB->error());

                // Insert default configuration
                $DB->insert(
                    $table,
                    [
                        'id' => 1,
                        'instance_url' => '',
                        'username' => '',
                        'password' => '',
                        'assignment_group' => '',
                        'entities_id' => 0,
                        'request_type' => 0,
                        'api_user' => 0,
                        'sync_tickets' => 1,
                        'sync_followups' => 1,
                        'sync_status' => 1,
                        'sync_documents' => 0,
                        'default_type' => self::INCIDENT,
                        'debug_mode' => 0,
                        'date_creation' => $_SESSION['glpi_currenttime'],
                    ]
                );

                Toolbox::logDebug("Tabela $table criada com sucesso e configuração padrão inserida.");
            } catch (Exception $e) {
                Toolbox::logError("Erro ao criar tabela $table: " . $e->getMessage());
            }
        } else {
            // Tabela existe, verificar se precisa de atualizações
            $migration->displayMessage("Updating $table");
            
            // Verificar e adicionar campos faltantes
            if (!$DB->fieldExists($table, 'sync_tickets')) {
                $migration->addField($table, 'sync_tickets', 'tinyint NOT NULL DEFAULT 1');
                $migration->migrationOneTable($table);
            }
            
            if (!$DB->fieldExists($table, 'sync_status')) {
                $migration->addField($table, 'sync_status', 'tinyint NOT NULL DEFAULT 1');
                $migration->migrationOneTable($table);
            }
            
            if (!$DB->fieldExists($table, 'sync_documents')) {
                $migration->addField($table, 'sync_documents', 'tinyint NOT NULL DEFAULT 0');
                $migration->migrationOneTable($table);
            }
            
            // Atualizar registro existente com valores padrão para campos novos
            $existingData = [];
            if (!$DB->fieldExists($table, 'sync_tickets')) {
                $existingData['sync_tickets'] = 1;
            }
            if (!$DB->fieldExists($table, 'sync_status')) {
                $existingData['sync_status'] = 1;
            }
            if (!$DB->fieldExists($table, 'sync_documents')) {
                $existingData['sync_documents'] = 0;
            }
            
            if (!empty($existingData)) {
                $DB->updateOrDie($table, $existingData, ['id' => 1]);
            }
            
            Toolbox::logDebug("Tabela $table atualizada com sucesso.");
        }
    }

    static function uninstall(Migration $migration)
    {
        global $DB;

        $table = self::getTable();
        if ($DB->tableExists($table)) {
            $migration->displayMessage("Uninstalling $table");
            $migration->dropTable($table);
        }
    }

    /**
     * Processar teste de conexão ServiceNow
     */
    private function testConnectionAction()
    {
        try {
            // Recarregar dados atuais do banco
            $this->getFromDB(1);
            
            // Verificar se temos os dados necessários
            if (empty($this->fields['instance_url']) || empty($this->fields['username']) || empty($this->fields['password'])) {
                Session::addMessageAfterRedirect(
                    __('Configure primeiro a URL, usuário e senha do ServiceNow antes de testar a conexão.', 'snowclient'), 
                    false, 
                    ERROR
                );
                return;
            }
            
            $api = new PluginSnowclientApi();
            $result = $api->testConnection();
            
            if ($result['success']) {
                Session::addMessageAfterRedirect($result['message'], false, INFO);
            } else {
                Session::addMessageAfterRedirect($result['message'], false, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect(
                sprintf(__('Erro ao testar conexão: %s', 'snowclient'), $e->getMessage()), 
                false, 
                ERROR
            );
        }
    }

    static function afterTicketAdd($ticket)
    {
        $config = self::getInstance();
        
        // First check if ticket is in the right entity hierarchy
        if (!self::shouldSyncTicket($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("SnowClient: Ticket {$ticket->fields['id']} not in configured entity hierarchy. Skipping.");
            }
            return false;
        }
        
        // Check if ticket is from ServiceNow (save mapping but don't sync back)
        if (self::isTicketFromServiceNow($ticket)) {
            self::logServiceNowTicket($ticket);
            return true;
        }
        
        // Don't create tickets in ServiceNow - tickets are always created there first
        // This hook is only for logging purposes when tickets come from ServiceNow
        return false;
    }

    static function afterTicketUpdate($ticket)
    {
        $config = self::getInstance();
        
        // First check if ticket is in the right entity hierarchy
        if (!self::shouldSyncTicket($ticket)) {
            return false;
        }
        
        // Only sync updates for tickets that came from ServiceNow
        if (!self::isTicketFromServiceNow($ticket)) {
            return false;
        }
        
        if ($config->fields['sync_tickets']) {
            $api = new PluginSnowclientApi();
            $api->updateIncident($ticket);
        }
    }

    static function afterTicketDelete($ticket)
    {
        $config = self::getInstance();
        
        // First check if ticket is in the right entity hierarchy
        if (!self::shouldSyncTicket($ticket)) {
            return false;
        }
        
        // Only sync deletes for tickets that came from ServiceNow
        if (!self::isTicketFromServiceNow($ticket)) {
            return false;
        }
        
        if ($config->fields['sync_tickets']) {
            $api = new PluginSnowclientApi();
            $api->deleteIncident($ticket);
        }
    }

    static function afterTicketFollowUp($followup)
    {
        $config = self::getInstance();
        
        if ($config->fields['sync_followups']) {
            // Get the ticket to check if it came from ServiceNow
            $ticket = new Ticket();
            if ($ticket->getFromDB($followup->fields['items_id'])) {
                // Only sync follow-ups for tickets that came from ServiceNow
                if (!self::isTicketFromServiceNow($ticket)) {
                    return false;
                }
                
                // Skip if followup is from API user to avoid loops
                if ($followup->fields['users_id'] == $config->fields['api_user']) {
                    return false;
                }
                
                $api = new PluginSnowclientApi();
                $api->addWorkNote($followup);
            }
        }
    }

    static function afterDocumentAdd($document)
    {
        $config = self::getInstance();
        if ($config->fields['sync_documents']) {
            // Check if document is attached to a ticket that should be synced
            if (isset($document->fields['items_id']) && $document->fields['itemtype'] == 'Ticket') {
                $ticket = new Ticket();
                if ($ticket->getFromDB($document->fields['items_id']) && self::shouldSyncTicket($ticket)) {
                    $api = new PluginSnowclientApi();
                    $api->attachDocument($document);
                }
            }
        }
    }

    /**
     * Check if a ticket should be synchronized based on entity configuration
     * Checks if ticket is in the configured entity OR in any of its children
     */
    static function shouldSyncTicket($ticket)
    {
        $config = self::getInstance();
        $configEntityId = $config->fields['entities_id'];
        $ticketEntityId = $ticket->fields['entities_id'];
        
        // If no entity is configured, don't sync
        if (empty($configEntityId)) {
            return false;
        }
        
        // If ticket is in the exact configured entity
        if ($ticketEntityId == $configEntityId) {
            return true;
        }
        
        // Check if ticket entity is a child of the configured entity
        // Get all descendants (children) of the configured entity
        $descendants = getSonsOf('glpi_entities', $configEntityId);
        
        // Check if ticket entity is in the descendants list
        return in_array($ticketEntityId, $descendants);
    }

    /**
     * Check if ticket is from ServiceNow based on title containing ServiceNow ID
     */
    static function isTicketFromServiceNow($ticket)
    {
        // Check if title contains ServiceNow ticket number pattern
        return self::extractServiceNowId($ticket) !== false;
    }

    /**
     * Extract ServiceNow ID from ticket title
     * Supports formats: INC0012345, REQ0012345, CHG0012345, PRB0012345 (without #)
     */
    static function extractServiceNowId($ticket)
    {
        $title = $ticket->fields['name'];
        
        // Pattern to match ServiceNow ticket numbers (with or without # prefix)
        if (preg_match('/^#?(INC|REQ|CHG|PRB)\d{7}/', $title, $matches)) {
            // Remove # if present and return clean ID
            return ltrim($matches[0], '#');
        }
        
        return false;
    }

    /**
     * Log ServiceNow ticket information and create mapping
     */
    static function logServiceNowTicket($ticket)
    {
        $config = self::getInstance();
        
        // Extract ServiceNow ID from ticket title
        $snowId = self::extractServiceNowId($ticket);
        
        if ($snowId) {
            // Remove # prefix if present for storage
            $cleanSnowId = ltrim($snowId, '#');
            
            // Save mapping for bidirectional sync
            global $DB;
            
            $mappingTable = 'glpi_plugin_snowclient_mappings';
            
            // Check if mapping already exists
            $existing = $DB->request([
                'FROM' => $mappingTable,
                'WHERE' => ['glpi_ticket_id' => $ticket->fields['id']]
            ]);
            
            if (count($existing) == 0) {
                // Insert new mapping
                $DB->insert($mappingTable, [
                    'glpi_ticket_id' => $ticket->fields['id'],
                    'snow_sys_id' => $cleanSnowId,
                    'snow_type' => self::getSnowTypeFromPrefix($cleanSnowId),
                    'date_creation' => $_SESSION['glpi_currenttime'],
                ]);
                
                if ($config->fields['debug_mode']) {
                    error_log("SnowClient: Mapped GLPI ticket {$ticket->fields['id']} to ServiceNow {$cleanSnowId}");
                }
            } else {
                if ($config->fields['debug_mode']) {
                    error_log("SnowClient: Mapping already exists for GLPI ticket {$ticket->fields['id']}");
                }
            }
        }
    }

    /**
     * Get ServiceNow type from ID prefix
     */
    static function getSnowTypeFromPrefix($snowId)
    {
        if (strpos($snowId, 'INC') === 0) {
            return 'incident';
        } elseif (strpos($snowId, 'REQ') === 0) {
            return 'sc_request';
        } elseif (strpos($snowId, 'CHG') === 0) {
            return 'change_request';
        } elseif (strpos($snowId, 'PRB') === 0) {
            return 'problem';
        }
        return 'incident'; // default
    }
}
