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
- ✅ **NOVO**: Botão "Devolver ao ServiceNow" para tickets
- ✅ **NOVO**: Devolução com justificativa e fila específica
- ✅ **NOVO**: Resolução automática no GLPI sem resolver no ServiceNow

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

### Configuração de Devolução

- **ID do Grupo da Fila de Devolução**: sys_id do ServiceNow do grupo que receberá tickets devolvidos

### Debug

- **Modo Debug**: Habilita logs detalhados em `files/_log/snowclient.log`

## Funcionalidade de Devolução de Tickets

### Como Usar

1. **Identificação**: O botão "Devolver ao ServiceNow" aparece automaticamente em tickets que:
   - Foram criados pelo ServiceNow
   - Estão na entidade configurada para sincronização
   - Não estão resolvidos ou fechados

2. **Processo de Devolução**:
   - Clique no botão "Devolver ao ServiceNow" (localizado após o botão Escalar)
   - Preencha o **motivo da devolução** (obrigatório)
   - Opcionalmente, especifique a **fila de destino** no ServiceNow
   - Confirme a devolução

3. **Resultado**:
   - Ticket é **resolvido automaticamente no GLPI**
   - **Acompanhamento** é adicionado com justificativa
   - Ticket é **transferido de volta ao ServiceNow**
   - No ServiceNow: ticket **NÃO é resolvido**, apenas transferido para nova fila
   - Work note é adicionada explicando a devolução

### Casos de Uso

- Tickets que precisam de conhecimento específico do ServiceNow
- Chamados que requerem acesso a sistemas não disponíveis no GLPI
- Transferência para equipes especializadas do ServiceNow
- Devolução por falta de informações técnicas adequadas

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

## Changelog

### v1.1.0 (Setembro 2025)
🚀 **NOVA FUNCIONALIDADE: Devolução de Tickets (VERSÃO MELHORADA)**
- ✅ **NOVO**: Botão "Devolver ao ServiceNow" na tela de tickets
- ✅ **NOVO**: Modal com justificativa obrigatória para devolução
- ✅ **NOVO**: Campo de configuração para fila padrão de devolução (sys_id)
- ✅ **NOVO**: Suporte a sys_id ou nome do grupo de atribuição
- ✅ **NOVO**: Resolução automática do ticket no GLPI
- ✅ **NOVO**: Transferência para ServiceNow SEM resolver o ticket lá
- ✅ **CRÍTICO**: Sistema anti-loop para evitar sincronização durante devolução
- ✅ **CRÍTICO**: Proteção contra hooks de resolução em devoluções
- ✅ **NOVO**: API para busca automática de grupos de atribuição
- ✅ Interface multilíngue (Português/Inglês)
- ✅ CSS e JavaScript dedicados para a funcionalidade

### v1.0.9 (Setembro 2025)
🔒 **CORREÇÕES CRÍTICAS DE SEGURANÇA**
- **CRÍTICO**: Implementada revalidação de entidade em `afterTicketUpdate()`
- **CRÍTICO**: Implementada revalidação de entidade em `afterTicketDelete()`
- **CRÍTICO**: Implementada validação de entidade em `afterDocumentAdd()`
- **CRÍTICO**: Implementada validação de entidade em `afterDocumentItemAdd()`
- ✅ **VULNERABILIDADE CORRIGIDA**: Tickets movidos entre entidades não sincronizam mais indevidamente
- ✅ Adicionado logging detalhado para auditoria de segurança
- ✅ Proteção completa contra vazamento de dados entre entidades

### v1.0.8 
- Melhorias na sincronização de documentos
- Correções de bugs menores

### v1.0.7
- Simplificação no manuseio de sys_id via API
- Melhorias na estabilidade da conexão
