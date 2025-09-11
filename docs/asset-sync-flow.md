```mermaid
graph TD
    %% Entradas do sistema
    subgraph "TRIGGERS DE AÇÃO NOS ATIVOS"
        A1[🧑‍💼 Manager Manual<br/>Cria/Edita/Deleta Ativo]
        A2[🔌 API Externa<br/>Cria/Edita/Deleta Ativo]
        A3[📊 GLPI Inventory<br/>Descoberta/Atualização Automática]
        A4[🔧 Outras Situações<br/>Importação/Scripts/Plugins]
    end

    %% Verificação de entidade
    subgraph "VERIFICAÇÃO DE ENTIDADE"
        B1{Ativo pertence à<br/>entidade configurada<br/>no plugin?}
        B2{Hierarquia habilitada?<br/>Ativo está em entidade filha?}
    end

    %% Tipos de ativos
    subgraph "TIPOS DE ATIVOS SUPORTADOS"
        C1[💻 Computer]
        C2[🖥️ Monitor]
        C3[🖨️ Printer]
        C4[📞 Phone]
        C5[🌐 NetworkEquipment]
        C6[🖱️ Peripheral]
        C7[📱 Outros Ativos...]
    end

    %% Hooks de sincronização
    subgraph "HOOKS DE SINCRONIZAÇÃO"
        D1[🎯 Hook INSERT<br/>plugin_snowclient_item_add]
        D2[🔄 Hook UPDATE<br/>plugin_snowclient_item_update]
        D3[🗑️ Hook DELETE<br/>plugin_snowclient_item_delete]
    end

    %% Busca do usuário no ServiceNow
    subgraph "BUSCA USUÁRIO SERVICENOW"
        E1[🔍 Buscar em sys_user]
        E2[📝 Campo: first_name]
        E3[🆔 Fallback: employee_number]
        E4[🎯 Equivalente: registration_number<br/>da entidade GLPI]
    end

    %% Mapeamento de dados
    subgraph "MAPEAMENTO DE DADOS"
        F1[📋 Dados do Ativo GLPI]
        F2[🔄 Transformação para<br/>formato ServiceNow]
        F3[📤 Payload ServiceNow API]
    end

    %% API ServiceNow
    subgraph "SERVICENOW API"
        G1[📡 Table API: cmdb_ci_computer]
        G2[📡 Table API: cmdb_ci_printer]
        G3[📡 Table API: cmdb_ci_monitor]
        G4[📡 Table API: cmdb_ci_phone]
        G5[📡 Table API: cmdb_ci_network_gear]
        G6[📡 Table API: cmdb_ci_peripheral]
    end

    %% Resultados
    subgraph "RESULTADOS"
        H1[✅ Sucesso<br/>Ativo sincronizado]
        H2[❌ Erro<br/>Log de erro]
        H3[📝 Mapeamento salvo<br/>GLPI ↔ ServiceNow]
    end

    %% Fluxo principal
    A1 --> B1
    A2 --> B1
    A3 --> B1
    A4 --> B1

    B1 -->|Sim| C1
    B1 -->|Não| B2
    B2 -->|Sim, está em filha| C1
    B2 -->|Não| H2

    C1 --> D1
    C2 --> D1
    C3 --> D1
    C4 --> D1
    C5 --> D1
    C6 --> D1
    C7 --> D1

    C1 --> D2
    C2 --> D2
    C3 --> D2
    C4 --> D2
    C5 --> D2
    C6 --> D2
    C7 --> D2

    C1 --> D3
    C2 --> D3
    C3 --> D3
    C4 --> D3
    C5 --> D3
    C6 --> D3
    C7 --> D3

    D1 --> E1
    D2 --> E1
    D3 --> E1

    E1 --> E2
    E2 -->|Não encontrado| E3
    E3 --> E4

    E2 --> F1
    E4 --> F1

    F1 --> F2
    F2 --> F3

    F3 --> G1
    F3 --> G2
    F3 --> G3
    F3 --> G4
    F3 --> G5
    F3 --> G6

    G1 --> H1
    G2 --> H1
    G3 --> H1
    G4 --> H1
    G5 --> H1
    G6 --> H1

    G1 --> H2
    G2 --> H2
    G3 --> H2
    G4 --> H2
    G5 --> H2
    G6 --> H2

    H1 --> H3

    %% Estilos
    classDef triggerStyle fill:#e1f5fe,stroke:#01579b,stroke-width:2px
    classDef entityStyle fill:#f3e5f5,stroke:#4a148c,stroke-width:2px
    classDef assetStyle fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px
    classDef hookStyle fill:#fff3e0,stroke:#e65100,stroke-width:2px
    classDef searchStyle fill:#fce4ec,stroke:#880e4f,stroke-width:2px
    classDef mappingStyle fill:#f9fbe7,stroke:#33691e,stroke-width:2px
    classDef apiStyle fill:#e0f2f1,stroke:#00695c,stroke-width:2px
    classDef resultStyle fill:#fafafa,stroke:#424242,stroke-width:2px

    class A1,A2,A3,A4 triggerStyle
    class B1,B2 entityStyle
    class C1,C2,C3,C4,C5,C6,C7 assetStyle
    class D1,D2,D3 hookStyle
    class E1,E2,E3,E4 searchStyle
    class F1,F2,F3 mappingStyle
    class G1,G2,G3,G4,G5,G6 apiStyle
    class H1,H2,H3 resultStyle
```

## Fluxo de Sincronização Unilateral de Ativos - ServiceNow Client Plugin

### Cenários de Trigger:

1. **🧑‍💼 Manager Manual**: Administrador/técnico cria, edita ou deleta ativos via interface GLPI
2. **🔌 API Externa**: Sistemas externos fazem operações via API REST do GLPI
3. **📊 GLPI Inventory**: Agent de inventário descobre ou atualiza ativos automaticamente
4. **🔧 Outras Situações**: Importações em lote, scripts personalizados, outros plugins

### Verificações de Entidade:

- ✅ Verifica se o ativo pertence à entidade configurada no plugin
- ✅ Se hierarquia estiver habilitada, inclui entidades filhas
- ❌ Ativos fora do escopo são ignorados

### Tipos de Ativos Suportados:

- **Computer** → `cmdb_ci_computer`
- **Monitor** → `cmdb_ci_monitor` 
- **Printer** → `cmdb_ci_printer`
- **Phone** → `cmdb_ci_phone`
- **NetworkEquipment** → `cmdb_ci_network_gear`
- **Peripheral** → `cmdb_ci_peripheral`

### Mapeamento de Usuário:

1. **Busca em `sys_user`** no ServiceNow
2. **Prioridade**: `first_name` (campo principal)
3. **Fallback**: `employee_number` 
4. **Equivalência**: `registration_number` da entidade GLPI

### Hooks de Sincronização:

- **INSERT**: Novos ativos são criados no ServiceNow
- **UPDATE**: Alterações são sincronizadas
- **DELETE**: Remoções são replicadas

Este fluxo garante sincronização unilateral (GLPI → ServiceNow) respeitando as regras de entidade e hierarquia configuradas no plugin.
