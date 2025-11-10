# Dashboard de Gerenciamento de RATs - Mockup e Especificações

## 📍 Localização do Dashboard

### Opção 1: Menu do Plugin RAT Digital (Recomendado)
```
Menu Principal GLPI
└── Plugins
    └── RAT Digital
        ├── Configuração
        ├── Lista de RATs
        └── 🆕 Gerenciamento de RATs ⚠️  <-- NOVO DASHBOARD
```

**URL**: `/plugins/ratdigital/front/management.php`

### Opção 2: Aba no Ticket (Complementar)
```
Ticket #12345
├── [Ticket]
├── [Estatísticas]
├── [Solução]
└── [RAT Digital] ⚠️  <-- Mostra status da RAT específica
```

---

## 🎨 Layout do Dashboard Principal

### Estrutura Visual

```
┌─────────────────────────────────────────────────────────────────────────┐
│  🏠 GLPI > Plugins > RAT Digital > Gerenciamento                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  📊 RESUMO EXECUTIVO                                                     │
│  ┌─────────────────┬─────────────────┬─────────────────┬──────────────┐│
│  │  ✅ Sucesso     │  🔄 Retry       │  ❌ Erro        │  ⏱️ Pendente ││
│  │                 │                 │                 │              ││
│  │     4,523       │       12        │       3         │       0      ││
│  │   (98.5%)       │    (0.26%)      │    (0.07%)      │    (0%)      ││
│  │                 │                 │                 │              ││
│  │  Últimas 24h    │  Aguardando     │  Requer ação    │  Travado     ││
│  └─────────────────┴─────────────────┴─────────────────┴──────────────┘│
│                                                                           │
│  📈 GRÁFICO DE TENDÊNCIA (Últimos 7 dias)                               │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │                                                       ▓▓▓            │ │
│  │                                             ▓▓▓       ███            │ │
│  │                                   ▓▓▓       ███       ███            │ │
│  │                         ▓▓▓       ███       ███       ███            │ │
│  │               ▓▓▓       ███       ███       ███       ███            │ │
│  │     ▓▓▓       ███       ███       ███       ███       ███            │ │
│  │     ███       ███       ███       ███       ███       ███            │ │
│  │ ────┴─────────┴─────────┴─────────┴─────────┴─────────┴──────────  │ │
│  │    17/10     18/10     19/10     20/10     21/10     22/10   23/10  │ │
│  │                                                                       │ │
│  │  ▓▓▓ = Sucesso    ░░░ = Retry    ▒▒▒ = Erro                         │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                           │
│  🔍 FILTROS                                                              │
│  [Status: Todos ▼] [Período: Últimas 24h ▼] [Entidade: Todas ▼]        │
│  [🔄 Atualizar] [📊 Exportar CSV] [🔔 Configurar Alertas]              │
│                                                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ⚠️ RATS QUE PRECISAM DE ATENÇÃO (15)                                   │
│                                                                           │
│  ┌─────┬───────────┬──────────┬────────┬──────────┬──────────┬────────┐│
│  │ ID  │ Ticket    │ Status   │ Retry  │ Último   │ Próximo  │ Ações  ││
│  │     │           │          │        │ Erro     │ Retry    │        ││
│  ├─────┼───────────┼──────────┼────────┼──────────┼──────────┼────────┤│
│  │ 789 │ INC001234 │ 🔄 Retry │ 2/3    │ HTTP 500 │ em 3min  │ [▶️][❌]││
│  │     │ Impressora│          │        │ Internal │          │        ││
│  │     │ Canon...  │          │        │ Server   │          │        ││
│  ├─────┼───────────┼──────────┼────────┼──────────┼──────────┼────────┤│
│  │ 788 │ INC001233 │ 🔄 Retry │ 1/3    │ Timeout  │ em 12min │ [▶️][❌]││
│  │     │ Sistema   │          │        │ 30s      │          │        ││
│  │     │ ERP fora  │          │        │          │          │        ││
│  ├─────┼───────────┼──────────┼────────┼──────────┼──────────┼────────┤│
│  │ 785 │ INC001230 │ ❌ Erro  │ 3/3    │ HTTP 422 │ -        │ [🔧][📋]││
│  │     │ Acesso    │          │ ⚠️MAX  │ Validação│          │        ││
│  │     │ negado    │          │        │ entidade │          │        ││
│  ├─────┼───────────┼──────────┼────────┼──────────┼──────────┼────────┤│
│  │ 783 │ INC001228 │ ❌ Erro  │ 3/3    │ HTTP 404 │ -        │ [🔧][📋]││
│  │     │ VPN não   │          │ ⚠️MAX  │ Endpoint │          │        ││
│  │     │ conecta   │          │        │ not found│          │        ││
│  └─────┴───────────┴──────────┴────────┴──────────┴──────────┴────────┘│
│                                                                           │
│  [◀️ Anterior]  Página 1 de 2  [▶️ Próxima]                             │
│                                                                           │
└─────────────────────────────────────────────────────────────────────────┘

Legenda das Ações:
[▶️] = Tentar Agora (força retry imediato)
[❌] = Cancelar Retry (marca como erro definitivo)
[🔧] = Editar Payload (permite ajustar dados e reenviar)
[📋] = Ver Detalhes (abre modal com histórico completo)
```

---

## 📊 Componentes Detalhados

### 1. Cards de Resumo (Topo)

#### Card 1: ✅ Sucesso
```
┌─────────────────┐
│  ✅ Sucesso     │
│                 │
│     4,523       │ ← Total de RATs criadas com sucesso
│   (98.5%)       │ ← Percentual de sucesso
│                 │
│  Últimas 24h    │ ← Período de referência
└─────────────────┘
```

**Ao clicar**: Expande lista de RATs bem-sucedidas

#### Card 2: 🔄 Retry
```
┌─────────────────┐
│  🔄 Retry       │
│                 │
│       12        │ ← RATs aguardando retry
│    (0.26%)      │ ← Percentual
│                 │
│  Aguardando     │ ← Status atual
└─────────────────┘
```

**Cor**: Amarelo/Warning  
**Ao clicar**: Filtra tabela para mostrar apenas RATs em retry

#### Card 3: ❌ Erro
```
┌─────────────────┐
│  ❌ Erro        │
│                 │
│       3         │ ← RATs com erro definitivo
│    (0.07%)      │ ← Percentual
│                 │
│  Requer ação    │ ← Indicação de urgência
└─────────────────┘
```

**Cor**: Vermelho/Danger  
**Badge de Alerta**: Pisca se houver RATs com erro  
**Ao clicar**: Filtra para RATs com erro que precisam intervenção manual

#### Card 4: ⏱️ Pendente
```
┌─────────────────┐
│  ⏱️ Pendente    │
│                 │
│       0         │ ← RATs que não receberam resposta
│    (0%)         │ ← (pendente > 10 minutos)
│                 │
│  Travado        │ ← Possível problema
└─────────────────┘
```

**Cor**: Cinza/Secondary (0), Laranja/Warning (> 0)

---

### 2. Gráfico de Tendência

**Tipo**: Gráfico de barras empilhadas (stacked bar chart)  
**Período**: Últimos 7 dias (configurável)  
**Dados**:
- Cada barra = 1 dia
- Verde: RATs com sucesso
- Amarelo: RATs em retry (snapshot do momento)
- Vermelho: RATs com erro

**Interatividade**:
- Hover: Mostra números exatos
- Click: Filtra tabela para aquele dia específico

---

### 3. Tabela de RATs que Precisam de Atenção

#### Colunas

| Coluna | Conteúdo | Largura | Ordenável |
|--------|----------|---------|-----------|
| **ID** | ID interno da RAT | 60px | ✅ |
| **Ticket** | Número + Título resumido | 200px | ✅ |
| **Status** | Badge colorido com ícone | 100px | ✅ |
| **Retry** | X/Y com indicador visual | 80px | ✅ |
| **Último Erro** | Mensagem resumida | 250px | ❌ |
| **Próximo Retry** | Countdown ou "-" | 120px | ✅ |
| **Ações** | Botões de ação | 100px | ❌ |

#### Detalhes das Colunas

**Ticket**:
```
INC001234
Impressora Canon não...
```
- Link clicável para o ticket
- Tooltip com título completo

**Status**:
```
🔄 Retry    → Badge amarelo
❌ Erro     → Badge vermelho
⏱️ Pendente → Badge cinza
✅ Sucesso  → Badge verde (não aparece nesta tabela)
```

**Retry**:
```
2/3         → Normal (amarelo)
3/3 ⚠️MAX   → Máximo atingido (vermelho)
```

**Próximo Retry**:
```
em 3min     → Countdown ao vivo
em 12min    → Formato legível
em 2h       → Para períodos longos
-           → Sem retry agendado (erro definitivo)
```

---

### 4. Botões de Ação na Tabela

#### [▶️] Tentar Agora
**Funcionalidade**: Força retry imediato, sem esperar o próximo agendamento
**Comportamento**:
1. Mostra loading spinner no botão
2. Executa retry em background via AJAX
3. Atualiza linha da tabela com resultado
4. Mostra toast de sucesso/erro

**Modal de Confirmação**:
```
┌─────────────────────────────────────────┐
│  Confirmar Retry Manual                 │
├─────────────────────────────────────────┤
│                                         │
│  Deseja tentar criar a RAT agora?      │
│                                         │
│  RAT ID: 789                            │
│  Ticket: INC001234                      │
│  Tentativas: 2/3                        │
│                                         │
│  [Cancelar]  [✅ Sim, Tentar Agora]    │
└─────────────────────────────────────────┘
```

#### [❌] Cancelar Retry
**Funcionalidade**: Marca RAT como erro definitivo, cancelando retries futuros
**Uso**: Quando sabemos que o problema não será resolvido automaticamente

**Modal de Confirmação**:
```
┌─────────────────────────────────────────┐
│  ⚠️ Cancelar Retry                      │
├─────────────────────────────────────────┤
│                                         │
│  Isso marcará a RAT como ERRO           │
│  DEFINITIVO e cancelará todas as        │
│  tentativas futuras.                    │
│                                         │
│  Motivo (opcional):                     │
│  [_________________________________]    │
│                                         │
│  [Voltar]  [⚠️ Sim, Cancelar]          │
└─────────────────────────────────────────┘
```

#### [🔧] Editar Payload
**Funcionalidade**: Permite ajustar dados do payload antes de reenviar
**Uso**: Quando há erro de validação (422) ou dados incorretos

**Modal de Edição**:
```
┌────────────────────────────────────────────────────────┐
│  🔧 Editar Payload - RAT #789                          │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Ticket ID: 12345                                      │
│  Status: Novo                                          │
│  Entidade: [Empresa ABC Ltda ▼]                       │
│  Chamado: 12345                                        │
│  Descrição:                                            │
│  ┌──────────────────────────────────────────────────┐ │
│  │ Impressora Canon não imprime documentos PDF.     │ │
│  │ Erro aparece apenas no Windows 10.               │ │
│  │                                                  │ │
│  └──────────────────────────────────────────────────┘ │
│                                                        │
│  Contato: [João Silva                           ]     │
│  Telefone: [(11) 99999-9999                     ]     │
│  Cidade: [São Paulo                             ]     │
│  Estado: [SP ▼]                                       │
│                                                        │
│  ⚠️ Último erro: HTTP 422 - Campo 'entidade' inválido │
│                                                        │
│  [Cancelar]  [📋 Ver JSON Raw]  [✅ Salvar e Enviar] │
└────────────────────────────────────────────────────────┘
```

#### [📋] Ver Detalhes
**Funcionalidade**: Abre modal com histórico completo da RAT

**Modal Detalhado**:
```
┌───────────────────────────────────────────────────────────────────┐
│  📋 Detalhes da RAT #789                                          │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  🎫 INFORMAÇÕES DO TICKET                                         │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ Ticket:     INC001234 - Impressora Canon não imprime      │ │
│  │ Criado em:  23/10/2025 14:32:15                            │ │
│  │ Entidade:   Empresa ABC Ltda                               │ │
│  │ Solicitante: João Silva                                     │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  📊 STATUS ATUAL                                                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ Status:        🔄 Retry (aguardando)                       │ │
│  │ Tentativas:    2/3                                          │ │
│  │ Próximo retry: 23/10/2025 15:05:00 (em 3 minutos)         │ │
│  │ URL da RAT:    -                                            │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  📝 HISTÓRICO DE TENTATIVAS                                       │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ ⏱️ Tentativa #1 - 23/10/2025 14:32:20                      │ │
│  │    ❌ Falha: HTTP 500 - Internal Server Error              │ │
│  │    Resposta: {"error": "Database connection timeout"}       │ │
│  │    Próximo retry: 14:37:20 (em 5 minutos)                  │ │
│  │                                                             │ │
│  │ ⏱️ Tentativa #2 - 23/10/2025 14:37:22                      │ │
│  │    ❌ Falha: HTTP 500 - Internal Server Error              │ │
│  │    Resposta: {"error": "Service temporarily unavailable"}  │ │
│  │    Próximo retry: 14:52:22 (em 15 minutos)                 │ │
│  │                                                             │ │
│  │ ⏳ Tentativa #3 - Agendada para 15:05:00                   │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  📦 PAYLOAD ENVIADO                                               │
│  [Expandir JSON ▼]                                                │
│                                                                   │
│  📤 RESPOSTA COMPLETA (Última Tentativa)                          │
│  [Expandir JSON ▼]                                                │
│                                                                   │
│  ─────────────────────────────────────────────────────────────   │
│                                                                   │
│  [Fechar]  [▶️ Tentar Agora]  [🔧 Editar Payload]  [❌ Cancelar] │
└───────────────────────────────────────────────────────────────────┘
```

---

## 🔔 Sistema de Notificações/Alertas

### Configuração de Alertas
**Localização**: Botão no topo do dashboard

```
┌────────────────────────────────────────────────────┐
│  🔔 Configurar Alertas                             │
├────────────────────────────────────────────────────┤
│                                                    │
│  📧 Email                                          │
│  ☑️ Enviar email quando RAT atingir máximo de     │
│     tentativas (erro definitivo)                  │
│     Para: [admin@empresa.com              ]       │
│                                                    │
│  ☑️ Enviar relatório diário de RATs falhadas      │
│     Horário: [09:00 ▼]                            │
│     Para: [admin@empresa.com, ti@empresa.com]     │
│                                                    │
│  🔔 Notificações no GLPI                           │
│  ☑️ Notificar no GLPI quando:                     │
│     ☑️ RAT falhar após 3 tentativas               │
│     ☑️ Mais de 5 RATs em erro nas últimas 1h      │
│     ☐ Servidor Laravel ficou indisponível         │
│                                                    │
│  📊 Limite de Taxa de Erro                         │
│  ☑️ Alertar se taxa de erro ultrapassar:          │
│     [5] % nas últimas [24] horas                  │
│                                                    │
│  [Cancelar]  [💾 Salvar Configurações]            │
└────────────────────────────────────────────────────┘
```

### Exemplo de Email de Alerta

```
Assunto: ⚠️ [GLPI RAT Digital] 3 RATs com erro definitivo

De: GLPI Sistema <noreply@glpi.empresa.com>
Para: admin@empresa.com

────────────────────────────────────────────────────
  ⚠️ ALERTA: RATs que Precisam de Atenção
────────────────────────────────────────────────────

Olá,

3 RATs falharam após múltiplas tentativas e precisam de 
intervenção manual:

1. RAT #785 - Ticket INC001230 (Acesso negado)
   Erro: HTTP 422 - Validation failed: entidade inválida
   Tentativas: 3/3 (máximo atingido)
   
2. RAT #783 - Ticket INC001228 (VPN não conecta)
   Erro: HTTP 404 - Endpoint not found
   Tentativas: 3/3 (máximo atingido)
   
3. RAT #780 - Ticket INC001225 (Sistema lento)
   Erro: Timeout após 30s
   Tentativas: 3/3 (máximo atingido)

────────────────────────────────────────────────────

🔧 AÇÕES RECOMENDADAS:

1. Verificar conectividade com servidor Laravel
2. Validar configuração da URL da RAT Digital
3. Revisar payloads para erros de validação

────────────────────────────────────────────────────

📊 Estatísticas das Últimas 24h:
- Total de RATs criadas: 287
- Sucesso: 284 (98.96%)
- Em retry: 0 (0%)
- Erro: 3 (1.04%)

────────────────────────────────────────────────────

👉 Acesse o dashboard para mais detalhes:
https://glpi.empresa.com/plugins/ratdigital/front/management.php

────────────────────────────────────────────────────
Sistema GLPI - RAT Digital Plugin
```

---

## 🎯 Indicador Visual na Aba do Ticket

Quando visualizar um ticket específico, a aba "RAT Digital" deve ter indicador visual:

```
Ticket #12345
├── [Ticket]
├── [Estatísticas]
├── [Solução]
└── [RAT Digital] 🔴 2/3  ← Badge com status
```

**Badges possíveis**:
- 🟢 ✅ (Verde) = RAT criada com sucesso
- 🟡 🔄 X/Y (Amarelo) = Em retry, mostra contagem
- 🔴 ❌ (Vermelho) = Erro definitivo
- ⚪ ⏱️ (Cinza) = Pendente/aguardando

### Conteúdo da Aba no Ticket

```
┌──────────────────────────────────────────────────────────┐
│  📋 RAT Digital - Status                                 │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  🔄 Status: Em Retry (Aguardando tentativa #3)           │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ Tentativas:    2/3                                 │ │
│  │ Próximo retry: 23/10/2025 15:05:00 (em 3 minutos) │ │
│  │ Último erro:   HTTP 500 - Internal Server Error    │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  📝 Histórico:                                           │
│  • 14:32:20 - Falha (HTTP 500)                          │
│  • 14:37:22 - Falha (HTTP 500)                          │
│  • 15:05:00 - Agendada                                   │
│                                                          │
│  [▶️ Tentar Criar Agora]  [📋 Ver Detalhes Completos]   │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 📱 Versão Mobile/Responsiva

O dashboard deve ser responsivo e funcional em tablets/celulares:

### Mobile Layout
```
┌─────────────────────────────┐
│ ☰ RAT Digital - Dashboard  │
├─────────────────────────────┤
│                             │
│ 📊 RESUMO                   │
│ ┌─────────────────────────┐ │
│ │ ✅ Sucesso:  4,523      │ │
│ │ 🔄 Retry:    12         │ │
│ │ ❌ Erro:     3          │ │
│ └─────────────────────────┘ │
│                             │
│ ⚠️ ATENÇÃO NECESSÁRIA (3)   │
│                             │
│ ┌─────────────────────────┐ │
│ │ #785 INC001230          │ │
│ │ ❌ Erro 3/3             │ │
│ │ [▶️] [📋]               │ │
│ └─────────────────────────┘ │
│                             │
│ ┌─────────────────────────┐ │
│ │ #783 INC001228          │ │
│ │ ❌ Erro 3/3             │ │
│ │ [▶️] [📋]               │ │
│ └─────────────────────────┘ │
│                             │
│ [Ver Mais ▼]                │
└─────────────────────────────┘
```

---

## 🎨 Paleta de Cores e Ícones

### Cores (Bootstrap)
- **Sucesso**: `#28a745` (Verde)
- **Retry**: `#ffc107` (Amarelo)
- **Erro**: `#dc3545` (Vermelho)
- **Pendente**: `#6c757d` (Cinza)
- **Info**: `#17a2b8` (Azul claro)

### Ícones (Font Awesome)
- ✅ Sucesso: `fa-check-circle`
- 🔄 Retry: `fa-sync-alt` (animado)
- ❌ Erro: `fa-times-circle`
- ⏱️ Pendente: `fa-clock`
- 🔔 Alerta: `fa-bell`
- 📊 Estatística: `fa-chart-bar`
- 🔧 Editar: `fa-wrench`
- 📋 Detalhes: `fa-list-alt`
- ▶️ Play: `fa-play`

---

## 🔄 Atualização em Tempo Real

### Opção 1: Auto-refresh
- Atualiza página automaticamente a cada 30 segundos
- Mostra countdown: "Atualizando em 25s..."
- Botão para pausar auto-refresh

### Opção 2: AJAX Polling (Recomendado)
- Faz polling via AJAX a cada 15 segundos
- Atualiza apenas números e tabela, sem reload
- Mais eficiente e melhor UX

### Opção 3: WebSocket (Futuro)
- Push em tempo real quando status muda
- Requer configuração adicional de servidor

**Implementação Recomendada**: Opção 2 (AJAX Polling)

```javascript
// Auto-refresh via AJAX
setInterval(function() {
    $.ajax({
        url: '/plugins/ratdigital/ajax/dashboard_update.php',
        success: function(data) {
            // Atualizar cards de resumo
            $('#success-count').text(data.success);
            $('#retry-count').text(data.retry);
            $('#error-count').text(data.error);
            
            // Atualizar tabela (apenas linhas modificadas)
            updateTableRows(data.rats);
            
            // Mostrar toast se houver novas RATs com erro
            if (data.new_errors > 0) {
                showToast('⚠️ ' + data.new_errors + ' novas RATs com erro!');
            }
        }
    });
}, 15000); // 15 segundos
```

---

## 🎯 Permissões de Acesso

### Níveis de Acesso

| Perfil | Visualizar | Retry Manual | Editar Payload | Configurar Alertas |
|--------|------------|--------------|----------------|-------------------|
| **Admin** | ✅ | ✅ | ✅ | ✅ |
| **Técnico** | ✅ | ✅ | ❌ | ❌ |
| **Supervisor** | ✅ | ✅ | ✅ | ✅ |
| **Observador** | ✅ | ❌ | ❌ | ❌ |

---

## 📝 Arquivos a Criar

Para implementar o dashboard completo:

```
plugins/ratdigital/
├── front/
│   ├── management.php              (Dashboard principal)
│   ├── rat_details.php             (Modal de detalhes)
│   └── stats.php                   (Página de estatísticas)
├── ajax/
│   ├── dashboard_update.php        (Update via AJAX)
│   ├── retry_rat.php               (Forçar retry)
│   ├── cancel_retry.php            (Cancelar retry)
│   ├── edit_payload.php            (Editar e reenviar)
│   └── configure_alerts.php        (Salvar configurações)
├── css/
│   └── dashboard.css               (Estilos do dashboard)
└── js/
    └── dashboard.js                (Lógica do dashboard)
```

---

## 🚀 Roadmap de Implementação

### Fase 1 - MVP (1 semana)
- ✅ Resumo executivo (cards)
- ✅ Tabela básica de RATs com erro/retry
- ✅ Botão de retry manual
- ✅ Modal de detalhes

### Fase 2 - Melhorias (1 semana)
- ✅ Gráfico de tendência
- ✅ Filtros avançados
- ✅ Edição de payload
- ✅ Auto-refresh via AJAX

### Fase 3 - Alertas (3-5 dias)
- ✅ Sistema de notificações
- ✅ Email de alertas
- ✅ Configuração de limites

### Fase 4 - Otimizações (1 semana)
- ✅ Responsividade mobile
- ✅ Exportação CSV
- ✅ Performance em larga escala
- ✅ Websocket (opcional)

---

Esse dashboard fornecerá **visibilidade completa** e **controle total** sobre o processo de criação de RATs, permitindo identificar e resolver problemas rapidamente!
