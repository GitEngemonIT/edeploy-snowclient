<?php

/**
 * eDeploy ServiceNow Configuration Class
 * Manages the configuration settings for ServiceNow integration
 */
class PluginEdeploysnowclientConfig extends CommonDBTM
{
    static $rightname = 'config';
    static $table = 'glpi_plugin_edeploysnowclient_configs';

    // ServiceNow ticket types
    const INCIDENT = 1;
    const REQUEST = 2;
    const CHANGE = 3;
    const PROBLEM = 4;

    static function getTypeName($nb = 0)
    {
        return __('ServiceNow Configuration', 'edeploysnowclient');
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

        // Variável estática para controlar se deve pular os hooks de sincronização
    private static $skipSyncHooks = false;
    

    
    /**
     * Define se deve ignorar hooks de sincronização temporariamente
     */
    public static function setSkipSyncHooks($skip = true)
    {
        self::$skipSyncHooks = $skip;
    }
    
    /**
     * Verifica se deve ignorar hooks de sincronização
     */
    public static function shouldSkipSyncHooks()
    {
        return self::$skipSyncHooks;
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
                $decrypted = Toolbox::sodiumDecrypt($this->fields['password']);
                return $decrypted;
            } catch (Exception $e) {
                if (isset($this->fields['debug_mode']) && $this->fields['debug_mode']) {
                    error_log("eDeploySnowClient DEBUG: Falha na descriptografia Sodium, usando fallback base64");
                }
                return base64_decode($this->fields['password']);
            }
        } else {
            if (isset($this->fields['debug_mode']) && $this->fields['debug_mode']) {
                error_log("eDeploySnowClient DEBUG: Sodium não disponível, usando base64");
            }
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
        echo "<strong style='color: #1976d2;'>🔄 " . __('Integration Flow', 'edeploysnowclient') . "</strong><br>";
        echo "<span style='color: #424242;'>";
        echo __('1. ServiceNow creates ticket → 2. Plugin replicates to GLPI → 3. Technician updates only in GLPI → 4. Plugin syncs back to ServiceNow', 'edeploysnowclient');
        echo "</span>";
        echo "</div>";
        echo "</td>";
        echo "</tr>";

        // ServiceNow connection settings
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow Instance URL', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'instance_url', [
            'placeholder' => 'https://your-instance.service-now.com'
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Username', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'username');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Password', 'edeploysnowclient') . "</td>";
        echo "<td>";
        echo "<input type='password' name='password' value='' placeholder='" . __('Enter password to change', 'edeploysnowclient') . "' />";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Assignment Group', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'assignment_group');
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Return Queue Group ID', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'return_queue_group', [
            'placeholder' => __('sys_id of the group for returned tickets', 'edeploysnowclient')
        ]);
        echo "<br><span class='small'>" . __('ServiceNow sys_id of the group that will receive returned tickets', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        // Entity configuration
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Integration Entity', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Entity::dropdown([
            'name' => 'entities_id',
            'value' => $this->fields['entities_id'],
            'comments' => false,
            'entity' => -1,  // Mostrar todas as entidades
            'emptylabel' => __('Select an entity...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Only tickets from this entity and its children will be synchronized', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow Request Type', 'edeploysnowclient') . "</td>";
        echo "<td>";
        RequestType::dropdown([
            'name' => 'request_type',
            'value' => $this->fields['request_type'],
            'comments' => false,
            'entity' => -1,  // Mostrar todos os tipos de solicitação
            'emptylabel' => __('Select a request type...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Request type used to identify tickets from ServiceNow', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('ServiceNow API User', 'edeploysnowclient') . "</td>";
        echo "<td>";
        User::dropdown([
            'name' => 'api_user',
            'value' => $this->fields['api_user'],
            'comments' => false,
            'entity' => -1,  // Mostrar usuários de todas as entidades
            'entity_sons' => true,
            'right' => 'all',
            'width' => '80%',
            'emptylabel' => __('Select a user...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('User for API operations and follow-ups', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Technician for Auto-Assignment', 'edeploysnowclient') . "</td>";
        echo "<td>";
        User::dropdown([
            'name' => 'default_technician_id',
            'value' => $this->fields['default_technician_id'],
            'comments' => false,
            'entity' => -1,
            'entity_sons' => true,
            'right' => 'all',
            'width' => '80%',
            'emptylabel' => __('Select a technician...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Technician to be assigned automatically when solving tickets without assignment', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Group for Auto-Assignment', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Group::dropdown([
            'name' => 'default_group_id',
            'value' => $this->fields['default_group_id'],
            'comments' => false,
            'entity' => -1,
            'entity_sons' => true,
            'width' => '80%',
            'emptylabel' => __('Select a group...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Group to be assigned automatically when solving tickets without group assignment', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Solution Type', 'edeploysnowclient') . "</td>";
        echo "<td>";
        SolutionType::dropdown([
            'name' => 'default_solutiontype_id',
            'value' => $this->fields['default_solutiontype_id'],
            'comments' => false,
            'entity' => -1,
            'entity_sons' => true,
            'width' => '80%',
            'emptylabel' => __('Select a solution type...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Solution type to be used when solving tickets', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Solution Template', 'edeploysnowclient') . "</td>";
        echo "<td>";
        SolutionTemplate::dropdown([
            'name' => 'default_solutiontemplate_id',
            'value' => $this->fields['default_solutiontemplate_id'],
            'comments' => false,
            'entity' => -1,
            'entity_sons' => true,
            'width' => '80%',
            'emptylabel' => __('Select a solution template...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Solution template to be used when solving tickets', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Ticket Category', 'edeploysnowclient') . "</td>";
        echo "<td>";
        ITILCategory::dropdown([
            'name' => 'default_itilcategory_id',
            'value' => $this->fields['default_itilcategory_id'],
            'comments' => false,
            'entity' => $this->fields['entities_id'],
            'entity_sons' => true,
            'width' => '80%',
            'emptylabel' => __('Select a category...', 'edeploysnowclient'),
            'display_emptychoice' => true
        ]);
        echo "<br><span class='small'>" . __('Category to be assigned automatically when solving tickets without category', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        // Sync options
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center tab_bg_2'>";
        echo "<strong>" . __('Synchronization Options', 'edeploysnowclient') . "</strong>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Tickets', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_tickets', $this->fields['sync_tickets']);
        echo "<br><span class='small'>" . __('Enable bidirectional ticket synchronization', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Follow-ups', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_followups', $this->fields['sync_followups']);
        echo "<br><span class='small'>" . __('Send GLPI updates back to ServiceNow as work notes', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Status Changes', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_status', $this->fields['sync_status']);
        echo "<br><span class='small'>" . __('Update ServiceNow ticket status when changed in GLPI', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Sync Documents', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('sync_documents', $this->fields['sync_documents']);
        echo "<br><span class='small'>" . __('Synchronize attachments between GLPI and ServiceNow', 'edeploysnowclient') . "</span>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Default Ticket Type', 'edeploysnowclient') . "</td>";
        echo "<td>";
        $types = [
            self::INCIDENT => __('Incident', 'edeploysnowclient'),
            self::REQUEST => __('Service Request', 'edeploysnowclient'),
            self::CHANGE => __('Change Request', 'edeploysnowclient'),
            self::PROBLEM => __('Problem', 'edeploysnowclient'),
        ];
        Dropdown::showFromArray('default_type', $types, [
            'value' => $this->fields['default_type']
        ]);
        echo "</td>";
        echo "</tr>";

        // ---- Seção de Mapeamento de Grupos ----
        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='2' class='center'>";
        echo "<strong>🗂️ " . __('Group Mapping (GLPI → ServiceNow)', 'edeploysnowclient') . "</strong>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2'>";
        echo "<div style='background: #f0f4ff; border: 1px solid #c0cfe8; border-radius: 6px; padding: 14px; margin: 4px 0;'>";
        echo "<p style='margin: 0 0 10px; color: #555; font-size: 13px;'>";
        echo __('When a group is assigned to a ticket in GLPI, the corresponding assignment group in ServiceNow will be updated automatically.', 'edeploysnowclient');
        echo "</p>";

        $currentGroupMappings = self::getGroupMappings();

        // Grid header
        echo "<div style='display:grid; grid-template-columns:2fr 2fr 1fr; gap:8px; align-items:center; margin-bottom:6px; font-weight:bold; font-size:12px; color:#555;'>";
        echo "<div>" . __('GLPI Group', 'edeploysnowclient') . "</div>";
        echo "<div>" . __('ServiceNow Group (name or sys_id)', 'edeploysnowclient') . "</div>";
        echo "<div></div>";
        echo "</div>";

        echo "<div id='group-mappings-list'>";
        foreach ($currentGroupMappings as $idx => $gm) {
            $glpiGroupId   = (int)($gm['glpi_group_id'] ?? 0);
            $glpiGroupName = htmlspecialchars($gm['glpi_group_name'] ?? '');
            $snowGroup     = htmlspecialchars($gm['snow_group'] ?? '');

            echo "<div class='group-mapping-row' data-idx='$idx' style='display:grid; grid-template-columns:2fr 2fr 1fr; gap:8px; align-items:center; margin-bottom:8px; background:#fff; padding:8px; border-radius:5px; border:1px solid #d5dde8;'>";

            // GLPI group dropdown (static, pre-selected)
            echo "<div>";
            Group::dropdown([
                'name'                => "group_mappings[{$idx}][glpi_group_id]",
                'value'               => $glpiGroupId,
                'comments'            => false,
                'entity'              => -1,
                'entity_sons'         => true,
                'width'               => '100%',
                'emptylabel'          => __('Select a group...', 'edeploysnowclient'),
                'display_emptychoice' => true,
            ]);
            echo "<input type='hidden' name='group_mappings[{$idx}][glpi_group_name]' value='$glpiGroupName' class='glpi-group-name-cache' />";
            echo "</div>";

            // ServiceNow group field
            echo "<div>";
            echo "<input type='text' name='group_mappings[{$idx}][snow_group]' value='$snowGroup' class='form-control' placeholder='" . __('e.g. Service Desk or sys_id', 'edeploysnowclient') . "' style='width:100%;' />";
            echo "</div>";

            // Remove button
            echo "<div style='text-align:center;'>";
            echo "<button type='button' class='btn btn-danger remove-group-mapping-btn' onclick='edsncRemoveGroupRow(this)' style='background:#dc3545;color:#fff;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;'>";
            echo "🗑️ " . __('Remove', 'edeploysnowclient');
            echo "</button>";
            echo "</div>";

            echo "</div>"; // .group-mapping-row
        }
        echo "</div>"; // #group-mappings-list

        // "Add mapping" button row
        echo "<div style='display:grid; grid-template-columns:2fr 2fr 1fr; gap:8px; align-items:center; margin-top:10px; background:#e9ecef; padding:8px; border-radius:5px;'>";
        echo "<div>";
        Group::dropdown([
            'name'                => 'new_group_mapping_glpi_id',
            'id'                  => 'new_group_mapping_glpi_id',
            'value'               => 0,
            'comments'            => false,
            'entity'              => -1,
            'entity_sons'         => true,
            'width'               => '100%',
            'emptylabel'          => __('Select a group to add...', 'edeploysnowclient'),
            'display_emptychoice' => true,
        ]);
        echo "</div>";
        echo "<div><input type='text' id='new_group_mapping_snow' class='form-control' placeholder='" . __('ServiceNow group name or sys_id', 'edeploysnowclient') . "' style='width:100%;' /></div>";
        echo "<div style='text-align:center;'><button type='button' id='add-group-mapping-btn' style='background:#28a745;color:#fff;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;width:100%;'>";
        echo "➕ " . __('Add', 'edeploysnowclient');
        echo "</button></div>";
        echo "</div>";

        echo "</div>"; // inner container
        echo "</td>";
        echo "</tr>";

        // Group mapping JavaScript
        $nextIdxJs = count($currentGroupMappings);
        echo "<script type='text/javascript'>";
        echo "(function(){
            var gIdx = $nextIdxJs;

            function getSelectedText(sel) {
                if (!sel) return '';
                var opt = sel.options[sel.selectedIndex];
                return opt ? opt.text : '';
            }

            document.getElementById('add-group-mapping-btn').addEventListener('click', function() {
                var glpiSel  = document.getElementById('new_group_mapping_glpi_id');
                var snowInp  = document.getElementById('new_group_mapping_snow');
                if (!glpiSel || !glpiSel.value || glpiSel.value == '0') {
                    alert('" . addslashes(__('Please select a GLPI group.', 'edeploysnowclient')) . "');
                    return;
                }
                if (!snowInp || !snowInp.value.trim()) {
                    alert('" . addslashes(__('Please enter the ServiceNow group name or sys_id.', 'edeploysnowclient')) . "');
                    return;
                }
                var glpiGroupId   = glpiSel.value;
                var glpiGroupName = getSelectedText(glpiSel);
                var snowGroup     = snowInp.value.trim();

                var list = document.getElementById('group-mappings-list');
                var row  = document.createElement('div');
                row.className = 'group-mapping-row';
                row.setAttribute('data-idx', gIdx);
                row.style.cssText = 'display:grid;grid-template-columns:2fr 2fr 1fr;gap:8px;align-items:center;margin-bottom:8px;background:#fff;padding:8px;border-radius:5px;border:1px solid #d5dde8;';
                row.innerHTML =
                    '<div>' +
                        '<div class=\"b\" style=\"font-weight:500;\">' + edsncEsc(glpiGroupName) + '</div>' +
                        '<input type=\"hidden\" name=\"group_mappings[' + gIdx + '][glpi_group_id]\" value=\"' + edsncEsc(glpiGroupId) + '\" />' +
                        '<input type=\"hidden\" name=\"group_mappings[' + gIdx + '][glpi_group_name]\" value=\"' + edsncEsc(glpiGroupName) + '\" class=\"glpi-group-name-cache\" />' +
                    '</div>' +
                    '<div><input type=\"text\" name=\"group_mappings[' + gIdx + '][snow_group]\" value=\"' + edsncEsc(snowGroup) + '\" class=\"form-control\" style=\"width:100%;\" /></div>' +
                    '<div style=\"text-align:center;\"><button type=\"button\" class=\"btn btn-danger remove-group-mapping-btn\" onclick=\"edsncRemoveGroupRow(this)\" style=\"background:#dc3545;color:#fff;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;\">" . addslashes("🗑️ " . __('Remove', 'edeploysnowclient')) . "</button></div>';
                list.appendChild(row);
                gIdx++;
                glpiSel.value = '';
                snowInp.value = '';
            });

            function edsncEsc(s) {
                return String(s).replace(/[&<>\"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',\"'\":'&#039;'}[c];
                });
            }
        })();

        function edsncRemoveGroupRow(btn) {
            if (confirm('" . addslashes(__('Remove this group mapping?', 'edeploysnowclient')) . "')) {
                btn.closest('.group-mapping-row').remove();
            }
        }
        ";
        echo "</script>";

        // ---- Fim Mapeamento de Grupos ----

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Enable Debug Mode', 'edeploysnowclient') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('debug_mode', $this->fields['debug_mode']);
        echo "</td>";
        echo "</tr>";
        
        // Test connection button
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='test_connection' value='" . __('Test ServiceNow Connection', 'edeploysnowclient') . "' class='btn btn-info' style='background-color: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>";
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
                Session::addMessageAfterRedirect(__('Invalid URL', 'edeploysnowclient'), false, ERROR);
                return false;
            }
        }

        // Don't validate username on update - allow partial updates
        // Username validation only happens on initial creation via prepareInputForAdd

        // Decrypt HTML entities BEFORE encrypting password
        if (isset($input['password']) && !empty($input['password'])) {
            // FIX: Decode HTML entities que podem ter sido aplicadas pelo frontend
            $input['password'] = html_entity_decode($input['password'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            if (method_exists('Toolbox', 'sodiumEncrypt')) {
                $input['password'] = Toolbox::sodiumEncrypt($input['password']);
            } else {
                $input['password'] = base64_encode($input['password']);
            }
        } else {
            unset($input['password']);
        }

        // Process group mappings
        if (isset($input['group_mappings']) && is_array($input['group_mappings'])) {
            $groupMappings = [];
            foreach ($input['group_mappings'] as $gm) {
                $glpiGroupId = isset($gm['glpi_group_id']) ? (int)$gm['glpi_group_id'] : 0;
                $snowGroup   = isset($gm['snow_group']) ? trim($gm['snow_group']) : '';
                if ($glpiGroupId <= 0 || $snowGroup === '') {
                    continue;
                }
                // Resolve group name from DB to keep it up to date
                $group = new Group();
                $glpiGroupName = ($group->getFromDB($glpiGroupId)) ? $group->getName() : ($gm['glpi_group_name'] ?? '');
                $groupMappings[] = [
                    'glpi_group_id'   => $glpiGroupId,
                    'glpi_group_name' => $glpiGroupName,
                    'snow_group'      => $snowGroup,
                ];
            }
            $input['group_mappings'] = json_encode($groupMappings);
        } else {
            $input['group_mappings'] = json_encode([]);
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
        // Validate username is required on creation
        if (!isset($input['username']) || empty($input['username'])) {
            Session::addMessageAfterRedirect(__('Username cannot be empty', 'edeploysnowclient'), false, ERROR);
            return false;
        }
        
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
              `return_queue_group` varchar(255) DEFAULT NULL,
              `default_technician_id` int NOT NULL DEFAULT '0',
              `default_group_id` int NOT NULL DEFAULT '0',
              `default_solutiontype_id` int NOT NULL DEFAULT '0',
              `default_solutiontemplate_id` int NOT NULL DEFAULT '0',
              `default_itilcategory_id` int NOT NULL DEFAULT '0',
              `entities_id` int NOT NULL DEFAULT '0',
              `request_type` int NOT NULL DEFAULT '0',
              `api_user` int NOT NULL DEFAULT '0',
              `sync_tickets` tinyint NOT NULL DEFAULT '1',
              `sync_followups` tinyint NOT NULL DEFAULT '1',
              `sync_status` tinyint NOT NULL DEFAULT '1',
              `sync_documents` tinyint NOT NULL DEFAULT '0',
              `default_type` int NOT NULL DEFAULT '1',
              `debug_mode` tinyint NOT NULL DEFAULT '0',
              `group_mappings` text DEFAULT NULL,
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
                'return_queue_group' => '',
                'default_technician_id' => 0,
                'default_group_id' => 0,
                'default_solutiontype_id' => 0,
                'default_solutiontemplate_id' => 0,
                'default_itilcategory_id' => 0,
                'entities_id' => 0,
                'request_type' => 0,
                'api_user' => 0,
                'sync_tickets' => 1,
                'sync_followups' => 1,
                'sync_status' => 1,
                'sync_documents' => 0,
                'default_type' => self::INCIDENT,
                'debug_mode' => 0,
                'group_mappings' => json_encode([]),
                'date_creation' => $_SESSION['glpi_currenttime'],
            ]);
        } else {
            // Migration: add group_mappings column to existing installations
            if (!$DB->fieldExists($table, 'group_mappings')) {
                $migration->addField($table, 'group_mappings', 'text', ['after' => 'debug_mode', 'value' => json_encode([])]);
            }
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
                    __('Please configure ServiceNow URL, username and password before testing connection.', 'edeploysnowclient'), 
                    false, 
                    ERROR
                );
                return;
            }
            
            $api = new PluginEdeploysnowclientApi();
            $result = $api->testConnection();
            
            if ($result['success']) {
                Session::addMessageAfterRedirect($result['message'], false, INFO);
            } else {
                Session::addMessageAfterRedirect($result['message'], false, ERROR);
            }
        } catch (Exception $e) {
            Session::addMessageAfterRedirect(
                sprintf(__('Error testing connection: %s', 'edeploysnowclient'), $e->getMessage()), 
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
                error_log("eDeploySnowClient: Ticket {$ticket->fields['id']} not in configured entity hierarchy. Skipping.");
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
        
        // Verificar se deve pular sincronização (ex: devolução)
        if (self::shouldSkipSyncHooks()) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Pulando sincronização de afterTicketUpdate (flag ativa) para ticket ID: " . $ticket->fields['id']);
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("eDeploySnowClient: afterTicketUpdate chamado para ticket ID: " . $ticket->fields['id']);
        }
        
        // VERIFICAÇÃO CRÍTICA: Revalida se ticket ainda está na entidade configurada
        if (!self::shouldSyncTicket($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Ticket não está na entidade configurada (entidade atual: {$ticket->fields['entities_id']}). Ignorando atualização.");
            }
            return false;
        }
        
        if (!self::isTicketFromServiceNow($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Ticket não é do ServiceNow, ignorando atualização");
            }
            return false;
        }
        
        // PREVENÇÃO DE LOOP: Evitar reenvio quando a alteração de status veio do próprio ServiceNow
        // Isso ocorre quando o usuário da API (usado para sincronizar do ServiceNow) fez a alteração
        $currentUser = Session::getLoginUserID();
        $apiUser = $config->fields['api_user'];
        
        if (($ticket->fields['status'] == Ticket::SOLVED || $ticket->fields['status'] == Ticket::CLOSED) && 
            $config->fields['sync_status'] && 
            ($currentUser == $apiUser || !$currentUser)) {
            
            $statusName = ($ticket->fields['status'] == Ticket::SOLVED) ? 'SOLVED' : 'CLOSED';
            
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Ticket {$ticket->fields['id']} alterado para {$statusName} pelo usuário da API ({$currentUser}). Assumindo que veio do ServiceNow, ignorando sincronização para evitar loop.");
            }
            
            return false;
        }
        
        if ($config->fields['sync_tickets']) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Enviando atualização do ticket para ServiceNow...");
            }
            
            $api = new PluginEdeploysnowclientApi();
            $result = $api->updateIncident($ticket);
            
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Resultado da atualização: " . ($result ? 'sucesso' : 'falha'));
            }
            
            return $result;
        }
        
        return false;
    }

    static function afterTicketDelete($ticket)
    {
        $config = self::getInstance();
        
        // Verificar se deve pular sincronização (ex: devolução)
        if (self::shouldSkipSyncHooks()) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Pulando sincronização de afterTicketDelete (flag ativa) para ticket ID: " . $ticket->fields['id']);
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("eDeploySnowClient: afterTicketDelete chamado para ticket ID: " . $ticket->fields['id']);
        }
        
        // VERIFICAÇÃO CRÍTICA: Revalida se ticket ainda está na entidade configurada
        if (!self::shouldSyncTicket($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Ticket não está na entidade configurada (entidade atual: {$ticket->fields['entities_id']}). Ignorando exclusão.");
            }
            return false;
        }
        
        if (!self::isTicketFromServiceNow($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Ticket não é do ServiceNow, ignorando exclusão");
            }
            return false;
        }
        
        if ($config->fields['sync_tickets']) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Enviando exclusão do ticket para ServiceNow...");
            }
            
            $api = new PluginEdeploysnowclientApi();
            $result = $api->deleteIncident($ticket);
            
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Resultado da exclusão: " . ($result ? 'sucesso' : 'falha'));
            }
            
            return $result;
        }
        
        return false;
    }

    static function afterTicketFollowUp($followup)
    {
        $config = self::getInstance();
        
        error_log("eDeploySnowClient: afterTicketFollowUp chamado para followup ID: " . $followup->fields['id']);
        
        if (!$config->fields['sync_followups']) {
            error_log("eDeploySnowClient: Sincronização de followups está desabilitada");
            return false;
        }
        
        $ticket = new Ticket();
        if ($ticket->getFromDB($followup->fields['items_id'])) {
            error_log("eDeploySnowClient: Ticket carregado: " . $ticket->fields['id'] . " - " . $ticket->fields['name']);
            
            // VERIFICAÇÃO CRÍTICA: Revalida se ticket ainda está na entidade configurada
            if (!self::shouldSyncTicket($ticket)) {
                error_log("eDeploySnowClient: Ticket não está mais na entidade configurada (entidade atual: {$ticket->fields['entities_id']}). Ignorando followup.");
                return false;
            }
            
            if (!self::isTicketFromServiceNow($ticket)) {
                error_log("eDeploySnowClient: Ticket não é do ServiceNow, ignorando followup");
                return false;
            }
            
            // Skip if followup is from API user to avoid loops
            if ($followup->fields['users_id'] == $config->fields['api_user']) {
                error_log("eDeploySnowClient: Followup é do usuário API, ignorando para evitar loop");
                return false;
            }
            
            error_log("eDeploySnowClient: Enviando followup para ServiceNow...");
            
            $api = new PluginEdeploysnowclientApi();
            $result = $api->addWorkNote($followup);
            
            if ($result) {
                error_log("eDeploySnowClient: Followup enviado com sucesso para ServiceNow");
                
                // Sincronizar anexos do followup
                error_log("eDeploySnowClient: Verificando anexos do followup...");
                self::syncFollowupAttachments($followup, $ticket);
            } else {
                error_log("eDeploySnowClient: ERRO - Falha ao enviar followup para ServiceNow");
            }
            
            return $result;
        } else {
            error_log("eDeploySnowClient: ERRO - Não foi possível carregar ticket ID: " . $followup->fields['items_id']);
        }
        
        return false;
    }

    static function afterDocumentAdd($document)
    {
        $config = self::getInstance();
        
        if (!$config->fields['sync_documents']) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: Sincronização de documentos está desabilitada");
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("eDeploySnowClient: afterDocumentAdd chamado para documento ID: " . $document->fields['id']);
        }
        
        // Verificar se o documento está associado diretamente a um ticket
        if (isset($document->fields['items_id']) && $document->fields['itemtype'] == 'Ticket') {
            $ticket = new Ticket();
            if ($ticket->getFromDB($document->fields['items_id'])) {
                if (self::shouldSyncTicket($ticket) && self::isTicketFromServiceNow($ticket)) {
                    // Obter sys_id do ServiceNow (busca via API automaticamente)
                    $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                    
                    if ($config->fields['debug_mode']) {
                        error_log("eDeploySnowClient: Snow sys_id obtido: " . ($snowSysId ?? 'null'));
                    }
                    
                    if ($snowSysId) {
                        $api = new PluginEdeploysnowclientApi();
                        $result = $api->attachDocument($document, $snowSysId);
                        
                        if ($config->fields['debug_mode']) {
                            error_log("eDeploySnowClient: Resultado do anexo: " . ($result ? 'sucesso' : 'falha'));
                        }
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
                error_log("eDeploySnowClient: Sincronização de documentos está desabilitada");
            }
            return false;
        }
        
        if ($config->fields['debug_mode']) {
            error_log("eDeploySnowClient: afterDocumentItemAdd chamado - itemtype: {$documentItem->fields['itemtype']}, items_id: {$documentItem->fields['items_id']}, documents_id: {$documentItem->fields['documents_id']}");
        }
        
        // Verificar se o anexo é para um ticket diretamente
        if ($documentItem->fields['itemtype'] == 'Ticket') {
            $ticket = new Ticket();
            if ($ticket->getFromDB($documentItem->fields['items_id'])) {
                if (self::shouldSyncTicket($ticket) && self::isTicketFromServiceNow($ticket)) {
                    // Obter sys_id do ServiceNow (busca via API automaticamente)
                    $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                    
                    if ($config->fields['debug_mode']) {
                        error_log("eDeploySnowClient: Snow sys_id obtido: " . ($snowSysId ?? 'null'));
                    }
                    
                    if ($snowSysId) {
                        $document = new Document();
                        if ($document->getFromDB($documentItem->fields['documents_id'])) {
                            $api = new PluginEdeploysnowclientApi();
                            $result = $api->attachDocument($document, $snowSysId);
                            
                            if ($config->fields['debug_mode']) {
                                error_log("eDeploySnowClient: Resultado do envio: " . ($result ? 'sucesso' : 'falha'));
                            }
                        }
                    }
                }
            }
        }
        
        // Verificar se o anexo é para um followup
        if ($documentItem->fields['itemtype'] == 'ITILFollowup') {
            $followup = new ITILFollowup();
            if ($followup->getFromDB($documentItem->fields['items_id'])) {
                $ticket = new Ticket();
                if ($ticket->getFromDB($followup->fields['items_id'])) {
                    // VERIFICAÇÃO CRÍTICA: Revalida se ticket ainda está na entidade configurada
                    if (!self::shouldSyncTicket($ticket)) {
                        if ($config->fields['debug_mode']) {
                            error_log("eDeploySnowClient: Ticket não está mais na entidade configurada (entidade atual: {$ticket->fields['entities_id']}). Ignorando documento de followup.");
                        }
                        return false;
                    }
                    
                    if (self::isTicketFromServiceNow($ticket)) {
                        // Obter sys_id do ServiceNow (busca via API automaticamente)
                        $snowSysId = self::getSnowSysIdFromTicket($ticket->fields['id']);
                        
                        if ($snowSysId) {
                            $document = new Document();
                            if ($document->getFromDB($documentItem->fields['documents_id'])) {
                                $api = new PluginEdeploysnowclientApi();
                                $result = $api->attachDocument($document, $snowSysId);
                                
                                if ($config->fields['debug_mode']) {
                                    error_log("eDeploySnowClient: Anexo do followup enviado - resultado: " . ($result ? 'sucesso' : 'falha'));
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    static function afterTicketSolution($solution)
    {
        $config = self::getInstance();
        
        error_log("eDeploySnowClient: afterTicketSolution chamado para solution ID: " . $solution->fields['id']);
        
        // Verificar se deve pular sincronização (ex: devolução)
        if (self::shouldSkipSyncHooks()) {
            error_log("eDeploySnowClient: Pulando sincronização de solução (flag ativa) - Solução NÃO será enviada ao ServiceNow");
            return false;
        }
        
        if (!$config->fields['sync_followups']) {
            error_log("eDeploySnowClient: Sincronização de followups está desabilitada (soluções usam a mesma configuração)");
            return false;
        }
        
        $ticket = new Ticket();
        if ($ticket->getFromDB($solution->fields['items_id'])) {
            error_log("eDeploySnowClient: Ticket carregado: " . $ticket->fields['id'] . " - " . $ticket->fields['name']);
            
            // VERIFICAÇÃO CRÍTICA: Revalida se ticket ainda está na entidade configurada
            if (!self::shouldSyncTicket($ticket)) {
                error_log("eDeploySnowClient: Ticket não está mais na entidade configurada (entidade atual: {$ticket->fields['entities_id']}). Ignorando solução.");
                return false;
            }
            
            if (!self::isTicketFromServiceNow($ticket)) {
                error_log("eDeploySnowClient: Ticket não é do ServiceNow, ignorando solução");
                return false;
            }
            
            // Skip if solution is from API user to avoid loops
            if ($solution->fields['users_id'] == $config->fields['api_user']) {
                error_log("eDeploySnowClient: Solução é do usuário API, ignorando para evitar loop");
                return false;
            }
            
            error_log("eDeploySnowClient: Enviando solução para ServiceNow...");
            
            // Recuperar dados adicionais da sessão se disponíveis
            $additionalData = [];
            if (isset($_SESSION['snowclient_solution_data'])) {
                error_log("eDeploySnowClient: Dados encontrados na sessão");
                
                $sessionData = $_SESSION['snowclient_solution_data'];
                
                // Se for string JSON, decodificar
                if (is_string($sessionData)) {
                    $sessionData = json_decode($sessionData, true);
                    error_log("eDeploySnowClient: Dados decodificados de JSON");
                }
                
                if (is_array($sessionData) && isset($sessionData['solutionCode'])) {
                    $additionalData['solutionCode'] = $sessionData['solutionCode'];
                    error_log("eDeploySnowClient: Dados adicionais recuperados da sessão: " . json_encode($additionalData));
                } else {
                    error_log("eDeploySnowClient: AVISO - Dados da sessão não contêm solutionCode: " . print_r($sessionData, true));
                }
                
                // Limpar dados da sessão após uso
                unset($_SESSION['snowclient_solution_data']);
                error_log("eDeploySnowClient: Dados removidos da sessão");
            } else {
                error_log("eDeploySnowClient: AVISO - Nenhum dado adicional encontrado na sessão");
            }
            
            $api = new PluginEdeploysnowclientApi();
            $result = $api->addSolution($solution, $additionalData);
            
            if ($result) {
                error_log("eDeploySnowClient: Solução enviada com sucesso para ServiceNow");
                
                // Sincronizar anexos da solução
                error_log("eDeploySnowClient: Verificando anexos da solução...");
                self::syncSolutionAttachments($solution, $ticket);
            } else {
                error_log("eDeploySnowClient: ERRO - Falha ao enviar solução para ServiceNow");
            }
            
            return $result;
        } else {
            error_log("eDeploySnowClient: ERRO - Não foi possível carregar ticket ID: " . $solution->fields['items_id']);
        }
        
        return false;
    }

    // Utility methods
    static function shouldSyncTicket($ticket)
    {
        $config = self::getInstance();
        $configEntityId = $config->fields['entities_id'];
        $ticketEntityId = $ticket->fields['entities_id'];
        
        error_log("eDeploySnowClient: shouldSyncTicket - config entity: $configEntityId");
        error_log("eDeploySnowClient: shouldSyncTicket - ticket entity: $ticketEntityId");
        
        if (empty($configEntityId)) {
            error_log("eDeploySnowClient: shouldSyncTicket - config entity is empty");
            return false;
        }
        
        if ($ticketEntityId == $configEntityId) {
            error_log("eDeploySnowClient: shouldSyncTicket - exact match, returning true");
            return true;
        }
        
        $descendants = getSonsOf('glpi_entities', $configEntityId);
        $inDescendants = in_array($ticketEntityId, $descendants);
        error_log("eDeploySnowClient: shouldSyncTicket - checking descendants: " . ($inDescendants ? 'true' : 'false'));
        
        return $inDescendants;
    }

    /**
     * Check if ticket has an assigned technician (individual user only, not groups)
     *
     * @param int $ticketId Ticket ID
     * @return bool True if ticket has at least one assigned user
     */
    static function hasAssignedTechnician($ticketId)
    {
        global $DB;
        
        // Verifica apenas usuários individuais atribuídos (glpi_tickets_users)
        // NÃO considera grupos (glpi_groups_tickets)
        $result = $DB->request([
            'FROM' => 'glpi_tickets_users',
            'WHERE' => [
                'tickets_id' => $ticketId,
                'type' => CommonITILActor::ASSIGN
            ]
        ]);
        
        $hasAssigned = count($result) > 0;
        error_log("eDeploySnowClient: hasAssignedTechnician - ticket $ticketId has assigned: " . ($hasAssigned ? 'yes' : 'no'));
        
        return $hasAssigned;
    }

    /**
     * Check if ticket has an assigned group
     *
     * @param int $ticketId Ticket ID
     * @return bool True if ticket has at least one assigned group
     */
    static function hasAssignedGroup($ticketId)
    {
        global $DB;
        
        $result = $DB->request([
            'FROM' => 'glpi_groups_tickets',
            'WHERE' => [
                'tickets_id' => $ticketId,
                'type' => CommonITILActor::ASSIGN
            ]
        ]);
        
        $hasAssigned = count($result) > 0;
        error_log("eDeploySnowClient: hasAssignedGroup - ticket $ticketId has assigned: " . ($hasAssigned ? 'yes' : 'no'));
        
        return $hasAssigned;
    }

    static function isTicketFromServiceNow($ticket)
    {
        $snowId = self::extractServiceNowId($ticket);
        $isFromSnow = $snowId !== false;
        error_log("eDeploySnowClient: isTicketFromServiceNow - ticket " . $ticket->getID() . " snow_id: " . ($snowId ? $snowId : 'none') . " - result: " . ($isFromSnow ? 'true' : 'false'));
        return $isFromSnow;
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
            $mappingTable = 'glpi_plugin_edeploysnowclient_mappings';
            
            $existing = $DB->request([
                'FROM' => $mappingTable,
                'WHERE' => ['glpi_ticket_id' => $ticket->fields['id']]
            ]);
            
            if (count($existing) == 0) {
                $DB->insert($mappingTable, [
                    'glpi_ticket_id' => $ticket->fields['id'],
                    'snow_sys_id' => $cleanSnowId, // Armazenar o número (será convertido via API quando necessário)
                    'snow_type' => self::getSnowTypeFromPrefix($cleanSnowId),
                    'date_creation' => $_SESSION['glpi_currenttime'],
                ]);
                
                if ($config->fields['debug_mode']) {
                    error_log("eDeploySnowClient: Mapped GLPI ticket {$ticket->fields['id']} to ServiceNow {$cleanSnowId}");
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
     * Sempre busca via API usando o número armazenado em snow_sys_id
     */
    static function getSnowSysIdFromTicket($ticket_id)
    {
        global $DB;
        
        $mappingTable = 'glpi_plugin_edeploysnowclient_mappings';
        
        $result = $DB->request([
            'FROM' => $mappingTable,
            'WHERE' => ['glpi_ticket_id' => $ticket_id]
        ]);
        
        if (count($result) > 0) {
            $row = $result->current();
            $storedValue = $row['snow_sys_id'];
            
            // Se o valor armazenado é um número de incidente (INC1234567), buscar o sys_id real via API
            if (preg_match('/^(INC|REQ|CHG|PRB)\d{7}$/', $storedValue)) {
                $api = new PluginEdeploysnowclientApi();
                $realSysId = $api->getSysIdFromIncidentNumber($storedValue);
                
                $config = self::getInstance();
                if ($config->fields['debug_mode']) {
                    error_log("eDeploySnowClient: Convertendo {$storedValue} para sys_id: " . ($realSysId ?? 'null'));
                }
                
                return $realSysId;
            }
            
            // Se já é um sys_id (32 caracteres), retornar diretamente
            if (strlen($storedValue) == 32) {
                return $storedValue;
            }
        }
        
        return null;
    }

    /**
     * Verifica se deve mostrar o botão "Devolver" para um ticket
     */
    static function shouldShowReturnButton($ticket)
    {
        $config = self::getInstance();
        
        error_log("eDeploySnowClient: shouldShowReturnButton - ticket ID: " . $ticket->getID());
        error_log("eDeploySnowClient: shouldShowReturnButton - ticket entity: " . $ticket->fields['entities_id']);
        error_log("eDeploySnowClient: shouldShowReturnButton - config entity: " . $config->fields['entities_id']);
        error_log("eDeploySnowClient: shouldShowReturnButton - ticket status: " . $ticket->fields['status']);
        
        // Verificar se o ticket é do ServiceNow e está na entidade configurada
        if (!self::shouldSyncTicket($ticket)) {
            error_log("eDeploySnowClient: shouldShowReturnButton - shouldSyncTicket returned false");
            return false;
        }
        
        if (!self::isTicketFromServiceNow($ticket)) {
            error_log("eDeploySnowClient: shouldShowReturnButton - isTicketFromServiceNow returned false");
            return false;
        }
        
        // Verificar se o ticket não está resolvido/fechado
        if (in_array($ticket->fields['status'], [Ticket::SOLVED, Ticket::CLOSED])) {
            error_log("eDeploySnowClient: shouldShowReturnButton - ticket is solved/closed");
            return false;
        }
        
        error_log("eDeploySnowClient: shouldShowReturnButton - returning true");
        return true;
    }

    /**
     * Mostra o botão "Devolver" no formulário do ticket
     */
    static function showReturnButton($ticket, $options = [])
    {
        global $CFG_GLPI;
        
        if (!$ticket->canUpdate()) {
            error_log("eDeploySnowClient: showReturnButton - user cannot update ticket");
            return;
        }
        
        error_log("eDeploySnowClient: showReturnButton - adding JavaScript for ticket " . $ticket->getID());
        
        // Apenas injetar um sinal para o JavaScript saber que deve mostrar o botão
        echo "<script type='text/javascript'>
        console.log('eDeploySnowClient: Definindo variáveis para ticket {$ticket->getID()}');
        
        // Adicionar uma variável global indicando que o botão deve ser mostrado
        window.snowclient_show_return_button = true;
        window.snowclient_ticket_id = {$ticket->getID()};
        
        console.log('eDeploySnowClient: Variáveis definidas - show_button:', window.snowclient_show_return_button, 'ticket_id:', window.snowclient_ticket_id);
        
        $(document).ready(function() {
            console.log('eDeploySnowClient: Document ready - Return button should be shown for ticket {$ticket->getID()}');
        });
        </script>";
    }

    /**
     * Processa a devolução de um ticket ao ServiceNow
     */
    static function returnTicketToServiceNow($ticket, $reason)
    {
        global $DB;
        
        $config = self::getInstance();
        
        try {
            // CRÍTICO: Ativar flag para evitar hooks de sincronização
            self::setSkipSyncHooks(true);
            
            // 1. VERIFICAR E ATRIBUIR CAMPOS OBRIGATÓRIOS (antes de resolver)
            
            // 1.1. Verificar e atribuir técnico
            $hasAssignedTechnician = self::hasAssignedTechnician($ticket->getID());
            
            if (!$hasAssignedTechnician && !empty($config->fields['default_technician_id'])) {
                error_log("eDeploySnowClient RETURN: Ticket sem técnico atribuído. Atribuindo técnico padrão ID: " . $config->fields['default_technician_id']);
                
                $ticketUser = new Ticket_User();
                $assigned = $ticketUser->add([
                    'tickets_id' => $ticket->getID(),
                    'users_id' => $config->fields['default_technician_id'],
                    'type' => CommonITILActor::ASSIGN
                ]);
                
                if ($assigned) {
                    error_log("eDeploySnowClient RETURN: Técnico padrão atribuído com sucesso ao ticket " . $ticket->getID());
                } else {
                    error_log("eDeploySnowClient RETURN: ERRO ao atribuir técnico padrão ao ticket " . $ticket->getID());
                }
            } elseif (!$hasAssignedTechnician) {
                error_log("eDeploySnowClient RETURN: AVISO - Ticket sem técnico atribuído e nenhum técnico padrão configurado");
            } else {
                error_log("eDeploySnowClient RETURN: Ticket já possui técnico atribuído");
            }
            
            // 1.2. Verificar e atribuir grupo
            $hasAssignedGroup = self::hasAssignedGroup($ticket->getID());
            
            if (!$hasAssignedGroup && !empty($config->fields['default_group_id'])) {
                error_log("eDeploySnowClient RETURN: Ticket sem grupo atribuído. Atribuindo grupo padrão ID: " . $config->fields['default_group_id']);
                
                $groupTicket = new Group_Ticket();
                $assigned = $groupTicket->add([
                    'tickets_id' => $ticket->getID(),
                    'groups_id' => $config->fields['default_group_id'],
                    'type' => CommonITILActor::ASSIGN
                ]);
                
                if ($assigned) {
                    error_log("eDeploySnowClient RETURN: Grupo padrão atribuído com sucesso ao ticket " . $ticket->getID());
                } else {
                    error_log("eDeploySnowClient RETURN: ERRO ao atribuir grupo padrão ao ticket " . $ticket->getID());
                }
            } elseif (!$hasAssignedGroup) {
                error_log("eDeploySnowClient RETURN: AVISO - Ticket sem grupo atribuído e nenhum grupo padrão configurado");
            } else {
                error_log("eDeploySnowClient RETURN: Ticket já possui grupo atribuído");
            }
            
            // 1.3. Verificar e atribuir categoria
            if (empty($ticket->fields['itilcategories_id']) && !empty($config->fields['default_itilcategory_id'])) {
                error_log("eDeploySnowClient RETURN: Ticket sem categoria. Atribuindo categoria padrão ID: " . $config->fields['default_itilcategory_id']);
                
                $ticket->update([
                    'id' => $ticket->getID(),
                    'itilcategories_id' => $config->fields['default_itilcategory_id']
                ]);
                
                error_log("eDeploySnowClient RETURN: Categoria padrão atribuída ao ticket " . $ticket->getID());
            } elseif (empty($ticket->fields['itilcategories_id'])) {
                error_log("eDeploySnowClient RETURN: AVISO - Ticket sem categoria e nenhuma categoria padrão configurada");
            } else {
                error_log("eDeploySnowClient RETURN: Ticket já possui categoria");
            }
            
            // Desativar flag temporariamente para permitir sincronização do followup
            self::setSkipSyncHooks(false);
            
            // 2. Adicionar followup explicando a devolução (SERÁ sincronizado com ServiceNow)
            $followup = new ITILFollowup();
            $followupData = [
                'items_id' => $ticket->getID(),
                'itemtype' => 'Ticket',
                'users_id' => Session::getLoginUserID(),
                'is_private' => 0,
                'content' => "**CHAMADO DEVOLVIDO AO SERVICENOW**\n\n" . 
                            "**Motivo:** " . $reason . "\n\n" . 
                            "Este chamado foi devolvido ao ServiceNow para tratamento adequado pela equipe responsável.",
                'date' => $_SESSION['glpi_currenttime']
            ];
            
            $followupId = $followup->add($followupData);
            if (!$followupId) {
                self::setSkipSyncHooks(false);
                throw new Exception('Erro ao adicionar acompanhamento de devolução');
            }
            
            // Aguardar um momento para garantir que o followup foi sincronizado
            sleep(1);
            
            // Reativar flag para evitar sincronização da resolução
            self::setSkipSyncHooks(true);
            
            // 3. Criar solução no ticket com tipo e template configurados
            $solution = new ITILSolution();
            $solutionData = [
                'itemtype' => 'Ticket',
                'items_id' => $ticket->getID(),
                'users_id' => Session::getLoginUserID(),
                'content' => "Chamado devolvido ao ServiceNow.\n\nMotivo: " . $reason,
                'status' => CommonITILValidation::ACCEPTED,
                'date_creation' => $_SESSION['glpi_currenttime'],
                'date_mod' => $_SESSION['glpi_currenttime']
            ];
            
            // Adicionar tipo de solução se configurado
            if (!empty($config->fields['default_solutiontype_id'])) {
                $solutionData['solutiontypes_id'] = $config->fields['default_solutiontype_id'];
                error_log("eDeploySnowClient RETURN: Usando tipo de solução ID: " . $config->fields['default_solutiontype_id']);
            } else {
                error_log("eDeploySnowClient RETURN: AVISO - Nenhum tipo de solução padrão configurado");
            }
            
            // Adicionar template de solução se configurado
            if (!empty($config->fields['default_solutiontemplate_id'])) {
                $solutionData['solutiontemplate_id'] = $config->fields['default_solutiontemplate_id'];
                error_log("eDeploySnowClient RETURN: Usando template de solução ID: " . $config->fields['default_solutiontemplate_id']);
            }
            
            $solutionId = $solution->add($solutionData);
            if (!$solutionId) {
                self::setSkipSyncHooks(false);
                throw new Exception('Erro ao adicionar solução ao ticket');
            }
            
            error_log("eDeploySnowClient RETURN: Solução adicionada com sucesso ao ticket " . $ticket->getID());
            
            // MANTER flag ativa para evitar sincronização de status para o ServiceNow
            // Apenas a mudança de fila será enviada pela API
            
            // 4. Enviar alteração para o ServiceNow (APENAS mudança de fila, SEM status)
            $api = new PluginEdeploysnowclientApi();
            error_log("eDeploySnowClient RETURN: Chamando API para devolver ticket {$ticket->getID()} ao ServiceNow");
            
            $snowResult = $api->returnTicketToQueue($ticket, $reason);
            
            if (!$snowResult) {
                // Verificar se o erro foi de correlação
                $sysId = self::getSnowSysIdFromTicket($ticket->getID());
                $correlationError = false;
                
                if ($sysId) {
                    // Verificar se a correlação existe
                    if (!$api->canUpdateIncident($ticket, $sysId)) {
                        $correlationError = true;
                        error_log("eDeploySnowClient RETURN: ERRO - Correlação não encontrada para ticket {$ticket->getID()}");
                    }
                }
                
                if ($correlationError) {
                    // Desfazer a resolução do ticket no GLPI
                    self::setSkipSyncHooks(true);
                    $ticket->update([
                        'id' => $ticket->getID(),
                        'status' => $ticket->oldvalues['status'] ?? Ticket::ASSIGNED
                    ]);
                    self::setSkipSyncHooks(false);
                    
                    return [
                        'success' => false,
                        'message' => 'Erro ao devolver chamado, correlação não encontrada'
                    ];
                }
                
                // Log do erro mas não falha a operação local se não foi erro de correlação
                error_log("eDeploySnowClient RETURN: FALHA - Erro ao enviar devolução para ServiceNow - Ticket ID: " . $ticket->getID());
                
                // Reativar flag para adicionar followup de erro
                self::setSkipSyncHooks(true);
                
                // Adicionar nota sobre o problema
                $errorFollowup = new ITILFollowup();
                $errorFollowup->add([
                    'items_id' => $ticket->getID(),
                    'itemtype' => 'Ticket',
                    'users_id' => Session::getLoginUserID(),
                    'is_private' => 1,
                    'content' => "**AVISO:** Ticket foi resolvido no GLPI, mas houve erro ao enviar devolução para ServiceNow. Verifique manualmente.",
                    'date' => $_SESSION['glpi_currenttime']
                ]);
                
                // Desativar flag novamente
                self::setSkipSyncHooks(false);
            } else {
                error_log("eDeploySnowClient RETURN: SUCESSO - Ticket devolvido com sucesso ao ServiceNow");
            }
            
            // Desativar flag após concluir todo o processo
            self::setSkipSyncHooks(false);
            
            return [
                'success' => true, 
                'message' => 'Ticket devolvido com sucesso',
                'snow_sync' => $snowResult
            ];
            
        } catch (Exception $e) {
            // Garantir que a flag seja desativada em caso de erro
            self::setSkipSyncHooks(false);
            
            error_log("eDeploySnowClient returnTicketToServiceNow Error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Erro ao processar devolução: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sincroniza anexos de um followup específico com o ServiceNow
     */
    static function syncFollowupAttachments($followup, $ticket)
    {
        global $DB;
        
        $config = self::getInstance();
        
        if (!$config->fields['sync_documents']) {
            error_log("eDeploySnowClient: Sincronização de documentos está desabilitada");
            return false;
        }
        
        $followup_id = $followup->getID();
        error_log("eDeploySnowClient: Buscando anexos do followup #$followup_id");
        
        // Buscar documentos vinculados ao followup
        $docs = $DB->request([
            'SELECT' => ['glpi_documents.*'],
            'FROM' => 'glpi_documents_items',
            'INNER JOIN' => [
                'glpi_documents' => [
                    'FKEY' => [
                        'glpi_documents_items' => 'documents_id',
                        'glpi_documents' => 'id'
                    ]
                ]
            ],
            'WHERE' => [
                'glpi_documents_items.items_id' => $followup_id,
                'glpi_documents_items.itemtype' => 'ITILFollowup'
            ]
        ]);
        
        $synced = 0;
        $total = 0;
        
        foreach ($docs as $doc) {
            $total++;
            
            // Obter sys_id do ServiceNow
            $snowSysId = self::getSnowSysIdFromTicket($ticket->getID());
            
            if (!$snowSysId) {
                error_log("eDeploySnowClient: ERRO - sys_id não encontrado para ticket " . $ticket->getID());
                continue;
            }
            
            $document = new Document();
            if ($document->getFromDB($doc['id'])) {
                $api = new PluginEdeploysnowclientApi();
                $result = $api->attachDocument($document, $snowSysId);
                
                if ($result) {
                    $synced++;
                    error_log("eDeploySnowClient: ✅ Anexo do followup '{$doc['filename']}' sincronizado");
                } else {
                    error_log("eDeploySnowClient: ❌ ERRO ao sincronizar anexo do followup '{$doc['filename']}'");
                }
            }
        }
        
        error_log("eDeploySnowClient: Total de anexos do followup sincronizados: $synced de $total");
        return $synced > 0;
    }

    /**
     * Sincroniza anexos de uma solução específica com o ServiceNow
     */
    static function syncSolutionAttachments($solution, $ticket)
    {
        global $DB;
        
        $config = self::getInstance();
        
        if (!$config->fields['sync_documents']) {
            error_log("eDeploySnowClient: Sincronização de documentos está desabilitada");
            return false;
        }
        
        $solution_id = $solution->getID();
        error_log("eDeploySnowClient: Buscando anexos da solução #$solution_id");
        
        // Buscar documentos vinculados à solução
        $docs = $DB->request([
            'SELECT' => ['glpi_documents.*'],
            'FROM' => 'glpi_documents_items',
            'INNER JOIN' => [
                'glpi_documents' => [
                    'FKEY' => [
                        'glpi_documents_items' => 'documents_id',
                        'glpi_documents' => 'id'
                    ]
                ]
            ],
            'WHERE' => [
                'glpi_documents_items.items_id' => $solution_id,
                'glpi_documents_items.itemtype' => 'ITILSolution'
            ]
        ]);
        
        $synced = 0;
        $total = 0;
        
        foreach ($docs as $doc) {
            $total++;
            
            // Obter sys_id do ServiceNow
            $snowSysId = self::getSnowSysIdFromTicket($ticket->getID());
            
            if (!$snowSysId) {
                error_log("eDeploySnowClient: ERRO - sys_id não encontrado para ticket " . $ticket->getID());
                continue;
            }
            
            $document = new Document();
            if ($document->getFromDB($doc['id'])) {
                $api = new PluginEdeploysnowclientApi();
                $result = $api->attachDocument($document, $snowSysId);
                
                if ($result) {
                    $synced++;
                    error_log("eDeploySnowClient: ✅ Anexo da solução '{$doc['filename']}' sincronizado");
                } else {
                    error_log("eDeploySnowClient: ❌ ERRO ao sincronizar anexo da solução '{$doc['filename']}'");
                }
            }
        }
        
        error_log("eDeploySnowClient: Total de anexos da solução sincronizados: $synced de $total");
        return $synced > 0;
    }

    // -----------------------------------------------------------------------
    // Group Mapping helpers
    // -----------------------------------------------------------------------

    /**
     * Retorna o array de mapeamentos GLPI-group → ServiceNow-group
     * @return array [['glpi_group_id'=>X, 'glpi_group_name'=>'...', 'snow_group'=>'...'], ...]
     */
    static function getGroupMappings()
    {
        $config = self::getInstance();
        if (empty($config->fields['group_mappings'])) {
            return [];
        }
        $mappings = json_decode($config->fields['group_mappings'], true);
        return is_array($mappings) ? $mappings : [];
    }

    /**
     * Dado o ID de um grupo GLPI retorna o nome/sys_id do grupo correspondente
     * no ServiceNow, ou null se não houver mapeamento.
     */
    static function getSnowGroupForGlpiGroup($glpiGroupId)
    {
        foreach (self::getGroupMappings() as $mapping) {
            if ((int)$mapping['glpi_group_id'] === (int)$glpiGroupId) {
                return $mapping['snow_group'];
            }
        }
        return null;
    }

    /**
     * Hook chamado quando um grupo é atribuído a um ticket (Group_Ticket add/update).
     * Sincroniza o assignment_group no ServiceNow se houver mapeamento configurado.
     */
    static function afterGroupAssigned($groupTicket)
    {
        $config = self::getInstance();

        if (self::shouldSkipSyncHooks()) {
            return false;
        }

        // Somente grupos de atribuição
        if ((int)$groupTicket->fields['type'] !== (int)CommonITILActor::ASSIGN) {
            return false;
        }

        $ticket = new Ticket();
        if (!$ticket->getFromDB($groupTicket->fields['tickets_id'])) {
            return false;
        }

        if (!self::shouldSyncTicket($ticket)) {
            return false;
        }

        if (!self::isTicketFromServiceNow($ticket)) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: afterGroupAssigned - ticket não é do ServiceNow, ignorando");
            }
            return false;
        }

        $glpiGroupId = (int)$groupTicket->fields['groups_id'];
        $snowGroup   = self::getSnowGroupForGlpiGroup($glpiGroupId);

        if ($snowGroup === null) {
            if ($config->fields['debug_mode']) {
                error_log("eDeploySnowClient: afterGroupAssigned - nenhum mapeamento para grupo GLPI ID $glpiGroupId");
            }
            return false;
        }

        if ($config->fields['debug_mode']) {
            error_log("eDeploySnowClient: afterGroupAssigned - mapeando grupo GLPI $glpiGroupId → ServiceNow '$snowGroup'");
        }

        $api = new PluginEdeploysnowclientApi();
        return $api->updateAssignmentGroup($ticket, $snowGroup);
    }
}
