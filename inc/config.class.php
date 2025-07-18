<?php

/**
 * ServiceNow Configuration Class
 * Manages the configuration settings for ServiceNow integration
 */
class PluginSnowclientConfig extends CommonDBTM
{
    static $rightname = 'config';
    static $table = 'glpi_plugin_snowclient_configs';

    // ServiceNow ticket types
    const INCIDENT = 1;
    const REQUEST = 2;
    const CHANGE = 3;
    const PROBLEM = 4;

    static function getTypeName($nb = 0)
    {
        return __('ServiceNow Configuration', 'snowclient');
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

    static function canDelete()
    {
        return Session::haveRight('config', UPDATE);
    }

    static function canPurge()
    {
        return Session::haveRight('config', UPDATE);
    }

    function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        return $tabs;
    }

    static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
            if (!$instance->getFromDB(1)) {
                $instance->getEmpty();
            }
        }
        return $instance;
    }

    function getFromDB($ID)
    {
        $result = parent::getFromDB($ID);
        
        // NÃO descriptografar aqui - será feito apenas quando necessário
        return $result;
    }

    /**
     * Get decrypted password when needed
     */
    function getDecryptedPassword()
    {
        if (empty($this->fields['password'])) {
            return '';
        }

        if (method_exists('Toolbox', 'sodiumDecrypt')) {
            try {
                return Toolbox::sodiumDecrypt($this->fields['password']);
            } catch (Exception $e) {
                return base64_decode($this->fields['password']);
            }
        } else {
            return base64_decode($this->fields['password']);
        }
    }

    function post_updateItem($history = 1)
    {
        parent::post_updateItem($history);
        
        if (isset($_POST['test_connection'])) {
            $this->testConnectionAction();
        }
    }

    function post_addItem()
    {
        parent::post_addItem();
        
        if (isset($_POST['test_connection'])) {
            $this->testConnectionAction();
        }
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

        // Integration flow info
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

        // ServiceNow connection settings
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow Instance URL', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'instance_url', [
            'placeholder' => 'https://your-instance.service-now.com'
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Username', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'username');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Password', 'snowclient') . "</td>";
        echo "<td>";
        echo "<input type='password' name='password' value='' placeholder='" . __('Enter password to change', 'snowclient') . "' />";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Assignment Group', 'snowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'assignment_group');
        echo "</td>";
        echo "</tr>";

        // Entity configuration
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Integration Entity', 'snowclient') . "</td>";
        echo "<td>";
        Entity::dropdown([
            'name' => 'entities_id',
            'value' => $this->fields['entities_id'],
            'comments' => false,
            'entity' => -1,  // Mostrar todas as entidades
            'emptylabel' => __('Select an entity...', 'snowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Only tickets from this entity and its children will be synchronized', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow Request Type', 'snowclient') . "</td>";
        echo "<td>";
        RequestType::dropdown([
            'name' => 'request_type',
            'value' => $this->fields['request_type'],
            'comments' => false,
            'entity' => -1,  // Mostrar todos os tipos de solicitação
            'emptylabel' => __('Select a request type...', 'snowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Request type used to identify tickets from ServiceNow', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow API User', 'snowclient') . "</td>";
        echo "<td>";
        User::dropdown([
            'name' => 'api_user',
            'value' => $this->fields['api_user'],
            'comments' => false,
            'entity' => -1,  // Mostrar usuários de todas as entidades
            'entity_sons' => true,
            'right' => 'all',
            'width' => '80%',
            'emptylabel' => __('Select a user...', 'snowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('User for API operations and follow-ups', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        // Sync options
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center tab_bg_2'>";
        echo "<strong>" . __('Synchronization Options', 'snowclient') . "</strong>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Tickets', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_tickets', $this->fields['sync_tickets']);
        echo "<br><span class='small'>" . __('Enable bidirectional ticket synchronization', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Follow-ups', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_followups', $this->fields['sync_followups']);
        echo "<br><span class='small'>" . __('Send GLPI updates back to ServiceNow as work notes', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Status Changes', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_status', $this->fields['sync_status']);
        echo "<br><span class='small'>" . __('Update ServiceNow ticket status when changed in GLPI', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Documents', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_documents', $this->fields['sync_documents']);
        echo "<br><span class='small'>" . __('Synchronize attachments between GLPI and ServiceNow', 'snowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Ticket Type', 'snowclient') . "</td>";
        echo "<td>";
        $types = [
            self::INCIDENT => __('Incident', 'snowclient'),
            self::REQUEST => __('Service Request', 'snowclient'),
            self::CHANGE => __('Change Request', 'snowclient'),
            self::PROBLEM => __('Problem', 'snowclient'),
        ];
        Dropdown::showFromArray('default_type', $types, [
            'value' => $this->fields['default_type']
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Enable Debug Mode', 'snowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('debug_mode', $this->fields['debug_mode']);
        echo "</td>";
        echo "</tr>";
        
        // Test connection button
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='test_connection' value='" . __('Test ServiceNow Connection', 'snowclient') . "' class='btn btn-info' style='background-color: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>";
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
        return true;
    }

    function prepareInputForUpdate($input)
    {
        // Validate URL
        if (isset($input['instance_url']) && !empty($input['instance_url'])) {
            if (!filter_var($input['instance_url'], FILTER_VALIDATE_URL)) {
                Session::addMessageAfterRedirect(__('Invalid URL', 'snowclient'), false, ERROR);
                return false;
            }
        }

        // Validate username
        if (isset($input['username']) && empty($input['username'])) {
            Session::addMessageAfterRedirect(__('Username cannot be empty', 'snowclient'), false, ERROR);
            return false;
        }

        // Encrypt password if provided
        if (isset($input['password']) && !empty($input['password'])) {
            if (method_exists('Toolbox', 'sodiumEncrypt')) {
                $input['password'] = Toolbox::sodiumEncrypt($input['password']);
            } else {
                $input['password'] = base64_encode($input['password']);
            }
        } else {
            unset($input['password']);
        }

        // Set default values for boolean fields
        $boolFields = ['sync_tickets', 'sync_followups', 'sync_status', 'sync_documents', 'debug_mode'];
        foreach ($boolFields as $field) {
            if (!isset($input[$field])) {
                $input[$field] = 0;
            }
        }

        return $input;
    }

    function prepareInputForAdd($input)
    {
        return $this->prepareInputForUpdate($input);
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

            $DB->queryOrDie($query, $DB->error());

            // Insert default configuration
            $DB->insert($table, [
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
            ]);
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

    private function testConnectionAction()
    {
        try {
            $this->getFromDB(1);
            
            if (empty($this->fields['instance_url']) || empty($this->fields['username']) || empty($this->fields['password'])) {
                Session::addMessageAfterRedirect(
                    __('Please configure ServiceNow URL, username and password before testing connection.', 'snowclient'), 
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
                sprintf(__('Error testing connection: %s', 'snowclient'), $e->getMessage()), 
                false, 
                ERROR
            );
        }
    }

    // Ticket hook methods
    static function afterTicketAdd($ticket)
    {
        $config = self::getInstance();
        
        if (!self::shouldSyncTicket($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("SnowClient: Ticket {$ticket->fields['id']} not in configured entity hierarchy. Skipping.");
            }
            return false;
        }
        
        if (self::isTicketFromServiceNow($ticket)) {
            self::logServiceNowTicket($ticket);
            return true;
        }
        
        return false;
    }

    static function afterTicketUpdate($ticket)
    {
        $config = self::getInstance();
        
        if (!self::shouldSyncTicket($ticket) || !self::isTicketFromServiceNow($ticket)) {
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
        
        if (!self::shouldSyncTicket($ticket) || !self::isTicketFromServiceNow($ticket)) {
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
        
        if (!$config->fields['sync_followups']) {
            return false;
        }
        
        $ticket = new Ticket();
        if ($ticket->getFromDB($followup->fields['items_id'])) {
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

    static function afterDocumentAdd($document)
    {
        $config = self::getInstance();
        
        if (!$config->fields['sync_documents']) {
            if ($config->fields['debug_mode']) {
                error_log("SnowClient: Sincronização de documentos está desabilitada");
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("SnowClient: afterDocumentAdd chamado para documento ID: " . $document->fields['id']);
        }
        
        // Verificar se o documento está associado diretamente a um ticket
        if (isset($document->fields['items_id']) && $document->fields['itemtype'] == 'Ticket') {
            $ticket = new Ticket();
            if ($ticket->getFromDB($document->fields['items_id']) && self::shouldSyncTicket($ticket)) {
                if ($config->fields['debug_mode']) {
                    error_log("SnowClient: Processando anexo direto do ticket {$ticket->fields['id']}");
                }
                
                // Obter sys_id do ServiceNow
                $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                
                if ($snowSysId) {
                    $api = new PluginSnowclientApi();
                    
                    // Obter sys_id real do ServiceNow (pode ser diferente do número)
                    $realSysId = $api->getSysIdFromIncidentNumber($snowSysId);
                    
                    if ($realSysId) {
                        $result = $api->attachDocument($document, $realSysId);
                        if ($config->fields['debug_mode']) {
                            error_log("SnowClient: Resultado do anexo: " . ($result ? 'sucesso' : 'falha'));
                        }
                    } else {
                        if ($config->fields['debug_mode']) {
                            error_log("SnowClient: Não foi possível obter sys_id real para o incidente $snowSysId");
                        }
                    }
                } else {
                    if ($config->fields['debug_mode']) {
                        error_log("SnowClient: Ticket {$ticket->fields['id']} não tem mapeamento com ServiceNow");
                    }
                }
            }
        }
    }

    static function afterDocumentItemAdd($documentItem)
    {
        $config = self::getInstance();
        
        if (!$config->fields['sync_documents']) {
            if ($config->fields['debug_mode']) {
                error_log("SnowClient: Sincronização de documentos está desabilitada");
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("SnowClient: afterDocumentItemAdd chamado - itemtype: {$documentItem->fields['itemtype']}, items_id: {$documentItem->fields['items_id']}");
        }
        
        // Verificar se o anexo é para um ticket diretamente
        if ($documentItem->fields['itemtype'] == 'Ticket') {
            $ticket = new Ticket();
            if ($ticket->getFromDB($documentItem->fields['items_id']) && self::shouldSyncTicket($ticket)) {
                if ($config->fields['debug_mode']) {
                    error_log("SnowClient: Processando anexo direto do ticket {$ticket->fields['id']}");
                }
                
                // Obter sys_id do ServiceNow
                $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                
                if ($snowSysId) {
                    $api = new PluginSnowclientApi();
                    
                    // Obter sys_id real do ServiceNow
                    $realSysId = $api->getSysIdFromIncidentNumber($snowSysId);
                    
                    if ($realSysId) {
                        $document = new Document();
                        if ($document->getFromDB($documentItem->fields['documents_id'])) {
                            $result = $api->attachDocument($document, $realSysId);
                            if ($config->fields['debug_mode']) {
                                error_log("SnowClient: Anexo do ticket {$ticket->fields['id']} enviado para ServiceNow - resultado: " . ($result ? 'sucesso' : 'falha'));
                            }
                        }
                    } else {
                        if ($config->fields['debug_mode']) {
                            error_log("SnowClient: Não foi possível obter sys_id real para o incidente $snowSysId");
                        }
                    }
                } else {
                    if ($config->fields['debug_mode']) {
                        error_log("SnowClient: Ticket {$ticket->fields['id']} não tem mapeamento com ServiceNow");
                    }
                }
            }
        }
        
        // Verificar se o anexo é para um followup
        if ($documentItem->fields['itemtype'] == 'ITILFollowup') {
            $followup = new ITILFollowup();
            if ($followup->getFromDB($documentItem->fields['items_id'])) {
                $ticket = new Ticket();
                if ($ticket->getFromDB($followup->fields['items_id']) && self::shouldSyncTicket($ticket)) {
                    if ($config->fields['debug_mode']) {
                        error_log("SnowClient: Processando anexo do followup {$followup->fields['id']} do ticket {$ticket->fields['id']}");
                    }
                    
                    // Obter sys_id do ServiceNow
                    $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                    
                    if ($snowSysId) {
                        $api = new PluginSnowclientApi();
                        
                        // Obter sys_id real do ServiceNow
                        $realSysId = $api->getSysIdFromIncidentNumber($snowSysId);
                        
                        if ($realSysId) {
                            $document = new Document();
                            if ($document->getFromDB($documentItem->fields['documents_id'])) {
                                $result = $api->attachDocument($document, $realSysId);
                                
                                if ($config->fields['debug_mode']) {
                                    error_log("SnowClient: Anexo do followup {$followup->fields['id']} enviado para ServiceNow - resultado: " . ($result ? 'sucesso' : 'falha'));
                                }
                            }
                        } else {
                            if ($config->fields['debug_mode']) {
                                error_log("SnowClient: Não foi possível obter sys_id real para o incidente $snowSysId");
                            }
                        }
                    } else {
                        if ($config->fields['debug_mode']) {
                            error_log("SnowClient: Ticket {$ticket->fields['id']} não tem mapeamento com ServiceNow");
                        }
                    }
                }
            }
        }
    }

    // Utility methods
    static function shouldSyncTicket($ticket)
    {
        $config = self::getInstance();
        $configEntityId = $config->fields['entities_id'];
        $ticketEntityId = $ticket->fields['entities_id'];
        
        if (empty($configEntityId)) {
            return false;
        }
        
        if ($ticketEntityId == $configEntityId) {
            return true;
        }
        
        $descendants = getSonsOf('glpi_entities', $configEntityId);
        return in_array($ticketEntityId, $descendants);
    }

    static function isTicketFromServiceNow($ticket)
    {
        return self::extractServiceNowId($ticket) !== false;
    }

    static function extractServiceNowId($ticket)
    {
        $title = $ticket->fields['name'];
        
        if (preg_match('/^#?(INC|REQ|CHG|PRB)\d{7}/', $title, $matches)) {
            return ltrim($matches[0], '#');
        }
        
        return false;
    }

    static function logServiceNowTicket($ticket)
    {
        $config = self::getInstance();
        $snowId = self::extractServiceNowId($ticket);
        
        if ($snowId) {
            $cleanSnowId = ltrim($snowId, '#');
            
            global $DB;
            $mappingTable = 'glpi_plugin_snowclient_mappings';
            
            $existing = $DB->request([
                'FROM' => $mappingTable,
                'WHERE' => ['glpi_ticket_id' => $ticket->fields['id']]
            ]);
            
            if (count($existing) == 0) {
                $DB->insert($mappingTable, [
                    'glpi_ticket_id' => $ticket->fields['id'],
                    'snow_sys_id' => $cleanSnowId,
                    'snow_type' => self::getSnowTypeFromPrefix($cleanSnowId),
                    'date_creation' => $_SESSION['glpi_currenttime'],
                ]);
                
                if ($config->fields['debug_mode']) {
                    error_log("SnowClient: Mapped GLPI ticket {$ticket->fields['id']} to ServiceNow {$cleanSnowId}");
                }
            }
        }
    }

    static function getSnowTypeFromPrefix($snowId)
    {
        if (strpos($snowId, 'INC') === 0) return 'incident';
        if (strpos($snowId, 'REQ') === 0) return 'sc_request';
        if (strpos($snowId, 'CHG') === 0) return 'change_request';
        if (strpos($snowId, 'PRB') === 0) return 'problem';
        return 'incident';
    }

    /**
     * Obter sys_id do ServiceNow a partir do ticket GLPI
     */
    static function getSnowSysIdFromTicket($ticket_id)
    {
        global $DB;
        
        $mappingTable = 'glpi_plugin_snowclient_mappings';
        
        $result = $DB->request([
            'FROM' => $mappingTable,
            'WHERE' => ['glpi_ticket_id' => $ticket_id]
        ]);
        
        if (count($result) > 0) {
            $row = $result->current();
            return $row['snow_sys_id'];
        }
        
        return null;
    }
}
