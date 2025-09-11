```mermaid
sequenceDiagram
    participant MGR as Manager/API/Inventory
    participant GLPI as GLPI Core
    participant HOOK as SnowClient Hook
    participant ENT as Entity Validator
    participant USR as User Resolver
    participant MAP as Data Mapper
    participant API as ServiceNow API
    participant SNOW as ServiceNow

    Note over MGR, SNOW: Fluxo Detalhado de Sincronização de Ativos

    MGR->>GLPI: INSERT/UPDATE/DELETE Asset
    GLPI->>HOOK: Trigger Hook (item_add/update/delete)
    
    HOOK->>ENT: shouldSyncAsset(asset)
    ENT->>ENT: Check entity_id configured
    ENT->>ENT: Check if asset.entities_id matches
    alt Entity doesn't match
        ENT->>ENT: Check hierarchy enabled
        ENT->>ENT: Check if asset in child entity
        alt Not in hierarchy
            ENT-->>HOOK: false (skip sync)
            HOOK-->>GLPI: Skip - not in scope
        else In child entity
            ENT-->>HOOK: true (proceed)
        end
    else Direct match
        ENT-->>HOOK: true (proceed)
    end

    HOOK->>USR: resolveServiceNowUser(asset)
    USR->>USR: Get asset.users_id or entity.registration_number
    USR->>API: Search sys_user table
    API->>SNOW: GET /api/now/table/sys_user?sysparm_query=first_name=X
    SNOW-->>API: User data or empty
    alt User not found by first_name
        API->>SNOW: GET /api/now/table/sys_user?sysparm_query=employee_number=X
        SNOW-->>API: User data or empty
    end
    API-->>USR: ServiceNow user sys_id or null
    USR-->>HOOK: resolved_user_id

    HOOK->>MAP: mapAssetToServiceNow(asset, user_id)
    MAP->>MAP: Transform GLPI asset fields
    MAP->>MAP: Map to ServiceNow CI fields
    MAP->>MAP: Add user assignment
    MAP-->>HOOK: ServiceNow payload

    alt INSERT Action
        HOOK->>API: createAsset(payload)
        API->>SNOW: POST /api/now/table/cmdb_ci_[type]
        SNOW-->>API: Created asset with sys_id
        API-->>HOOK: success + sys_id
        HOOK->>HOOK: saveMapping(glpi_id, snow_sys_id)
    else UPDATE Action  
        HOOK->>HOOK: getSnowSysId(glpi_asset_id)
        HOOK->>API: updateAsset(sys_id, payload)
        API->>SNOW: PATCH /api/now/table/cmdb_ci_[type]/sys_id
        SNOW-->>API: Updated asset data
        API-->>HOOK: success
    else DELETE Action
        HOOK->>HOOK: getSnowSysId(glpi_asset_id)
        HOOK->>API: deleteAsset(sys_id)
        API->>SNOW: DELETE /api/now/table/cmdb_ci_[type]/sys_id
        SNOW-->>API: Deletion confirmation
        API-->>HOOK: success
        HOOK->>HOOK: removeMapping(glpi_asset_id)
    end

    HOOK->>HOOK: logAction(result)
    HOOK-->>GLPI: Hook completed

    Note over HOOK, SNOW: Tabelas ServiceNow por Tipo de Ativo:
    Note over HOOK, SNOW: Computer → cmdb_ci_computer
    Note over HOOK, SNOW: Monitor → cmdb_ci_monitor  
    Note over HOOK, SNOW: Printer → cmdb_ci_printer
    Note over HOOK, SNOW: Phone → cmdb_ci_phone
    Note over HOOK, SNOW: NetworkEquipment → cmdb_ci_network_gear
    Note over HOOK, SNOW: Peripheral → cmdb_ci_peripheral
```

## Detalhes Técnicos da Implementação

### 1. Hooks Necessários no setup.php

```php
// Adicionar aos hooks existentes
$PLUGIN_HOOKS['item_add']['snowclient'] = [
    'Computer' => 'plugin_snowclient_item_add',
    'Monitor' => 'plugin_snowclient_item_add', 
    'Printer' => 'plugin_snowclient_item_add',
    'Phone' => 'plugin_snowclient_item_add',
    'NetworkEquipment' => 'plugin_snowclient_item_add',
    'Peripheral' => 'plugin_snowclient_item_add',
    // Manter hooks existentes de Ticket...
];

$PLUGIN_HOOKS['item_update']['snowclient'] = [
    'Computer' => 'plugin_snowclient_item_update',
    'Monitor' => 'plugin_snowclient_item_update',
    'Printer' => 'plugin_snowclient_item_update', 
    'Phone' => 'plugin_snowclient_item_update',
    'NetworkEquipment' => 'plugin_snowclient_item_update',
    'Peripheral' => 'plugin_snowclient_item_update',
    // Manter hooks existentes de Ticket...
];

$PLUGIN_HOOKS['item_delete']['snowclient'] = [
    'Computer' => 'plugin_snowclient_item_delete',
    'Monitor' => 'plugin_snowclient_item_delete',
    'Printer' => 'plugin_snowclient_item_delete',
    'Phone' => 'plugin_snowclient_item_delete', 
    'NetworkEquipment' => 'plugin_snowclient_item_delete',
    'Peripheral' => 'plugin_snowclient_item_delete',
    // Manter hooks existentes de Ticket...
];
```

### 2. Tabela de Mapeamento de Ativos

```sql
CREATE TABLE `glpi_plugin_snowclient_asset_mappings` (
  `id` int unsigned NOT NULL auto_increment,
  `glpi_asset_id` int NOT NULL,
  `glpi_asset_type` varchar(100) NOT NULL,
  `snow_sys_id` varchar(32) NOT NULL,
  `snow_table` varchar(100) NOT NULL,
  `date_creation` timestamp NULL DEFAULT NULL,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_glpi_asset` (`glpi_asset_id`, `glpi_asset_type`),
  KEY `snow_sys_id` (`snow_sys_id`)
);
```

### 3. Mapeamento de Tipos para Tabelas ServiceNow

| GLPI Type | ServiceNow Table | ServiceNow Class |
|-----------|------------------|------------------|
| Computer | cmdb_ci_computer | Computer |
| Monitor | cmdb_ci_monitor | Monitor |
| Printer | cmdb_ci_printer | Printer |
| Phone | cmdb_ci_phone | Phone |
| NetworkEquipment | cmdb_ci_network_gear | Network Gear |
| Peripheral | cmdb_ci_peripheral | Peripheral |

### 4. Campos de Mapeamento Básico

**Computer (cmdb_ci_computer):**
- `name` ← `Computer.name`
- `serial_number` ← `Computer.serial`
- `model` ← `ComputerModel.name`
- `manufacturer` ← `Manufacturer.name`
- `assigned_to` ← resolved user sys_id
- `location` ← `Location.name`
- `install_status` ← mapped from state

**Monitor (cmdb_ci_monitor):**
- `name` ← `Monitor.name`
- `serial_number` ← `Monitor.serial`
- `model` ← `MonitorModel.name`
- `manufacturer` ← `Manufacturer.name`
- `size` ← `Monitor.size`

**Printer (cmdb_ci_printer):**
- `name` ← `Printer.name`
- `serial_number` ← `Printer.serial`
- `model` ← `PrinterModel.name`
- `manufacturer` ← `Manufacturer.name`

### 5. Configurações Adicionais Necessárias

No formulário de configuração adicionar:

- ✅ **Sync Assets**: Habilitar sincronização de ativos
- ✅ **Asset Types**: Quais tipos sincronizar (multi-select)
- ✅ **User Resolution Strategy**: first_name vs employee_number priority
- ✅ **Default Assignment**: Usuário padrão quando não encontrar

### 6. Logs e Debug

- Log detalhado de cada operação de ativo
- Controle de rate limiting para APIs
- Retry automático em caso de falha temporária
- Dashboard de estatísticas de sincronização
