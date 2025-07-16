# SnowClient - Plugin de Integração GLPI com ServiceNow

Plugin para integração entre GLPI e ServiceNow, permitindo sincronização automática de tickets, acompanhamentos e documentos.

## Funcionalidades

- ✅ Sincronização automática de tickets do GLPI para ServiceNow
- ✅ Mapeamento inteligente de campos (urgência, impacto, prioridade, status)
- ✅ Sincronização de acompanhamentos como work notes
- ✅ Suporte a documentos anexados
- ✅ Configuração flexível de grupos de atribuição
- ✅ Modo debug para troubleshooting
- ✅ Teste de conectividade integrado
- ✅ Interface de configuração amigável

## Requisitos

- GLPI 9.4 ou superior
- PHP 7.4 ou superior
- Extensão cURL habilitada
- Instância ServiceNow com API REST habilitada
- Credenciais de usuário ServiceNow com permissões adequadas

## Instalação

1. Extraia o plugin na pasta `plugins/snowclient` do GLPI
2. Acesse **Configurar > Plugins** no GLPI
3. Instale e ative o plugin SnowClient
4. Configure as credenciais do ServiceNow em **Configurar > Geral > Aba ServiceNow Client**

## Configuração

### Configurações Básicas

- **URL da Instância ServiceNow**: URL completa da sua instância (ex: https://sua-instancia.service-now.com)
- **Usuário**: Nome de usuário para autenticação
- **Senha**: Senha do usuário (armazenada de forma criptografada)
- **Grupo de Atribuição Padrão**: Grupo no ServiceNow que receberá os tickets

### Opções de Sincronização

- **Sincronizar Tickets**: Habilita/desabilita sincronização de tickets
- **Sincronizar Acompanhamentos**: Sincroniza follow-ups como work notes
- **Sincronizar Documentos**: Anexa documentos aos incidents
- **Tipo de Ticket Padrão**: Define o tipo padrão (Incident, Service Request, etc.)

### Debug

- **Modo Debug**: Habilita logs detalhados em `files/_log/snowclient.log`

## Mapeamento de Campos

### Urgência/Impacto/Prioridade
- GLPI Very Low (1) → ServiceNow Low (3)
- GLPI Low (2) → ServiceNow Medium (2)
- GLPI Medium (3) → ServiceNow Medium (2)
- GLPI High (4) → ServiceNow High (1)
- GLPI Very High (5) → ServiceNow High (1)

### Status
- GLPI New (1) → ServiceNow New (1)
- GLPI Assigned (2) → ServiceNow In Progress (2)
- GLPI Planned (3) → ServiceNow In Progress (2)
- GLPI Pending (4) → ServiceNow In Progress (2)
- GLPI Solved (5) → ServiceNow Resolved (6)
- GLPI Closed (6) → ServiceNow Closed (7)

## API ServiceNow Utilizada

O plugin utiliza a API REST do ServiceNow:
- **Incidents**: `/api/now/table/incident`
- **Users**: `/api/now/table/sys_user`
- **Attachments**: `/api/now/attachment/file`

## Logs e Troubleshooting

Os logs são gravados em `files/_log/snowclient.log` quando o modo debug está habilitado.

Tipos de log:
- `ERROR`: Erros de conexão ou API
- `DEBUG`: Requisições e respostas detalhadas
