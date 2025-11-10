# Dashboard de Gerenciamento de RATs - Resumo Executivo

## 🎯 Objetivo

Fornecer visibilidade completa e controle sobre o processo de criação de RATs, permitindo identificar e resolver problemas rapidamente quando o servidor Laravel está instável.

---

## 📍 Onde Estará o Dashboard?

### Localização Principal
```
Menu GLPI → Plugins → RAT Digital → Gerenciamento de RATs
```

**URL**: `https://glpi.empresa.com/plugins/ratdigital/front/management.php`

### Localizações Secundárias

1. **Aba no Ticket Individual**
   - Cada ticket mostra status da sua RAT específica
   - Badge visual: 🟢✅ (sucesso), 🟡🔄 (retry), 🔴❌ (erro)

2. **Notificações no Header do GLPI**
   - Bell icon com contador de RATs com problema
   - Clique rápido para ver lista

---

## 🎨 O Que Veremos no Dashboard?

### 1. Resumo Executivo (Topo) - 4 Cards Grandes

```
┌─────────────────┬─────────────────┬─────────────────┬──────────────┐
│  ✅ SUCESSO     │  🔄 RETRY       │  ❌ ERRO        │  ⏱️ PENDENTE │
│                 │                 │                 │              │
│     4,523       │       12        │       3         │       0      │
│   (98.5%)       │    (0.26%)      │    (0.07%)      │    (0%)      │
│                 │                 │                 │              │
│  Últimas 24h    │  Aguardando     │  Requer ação    │  Travado     │
└─────────────────┴─────────────────┴─────────────────┴──────────────┘
```

**O que cada card mostra:**
- **Número total** de RATs naquele status
- **Percentual** em relação ao total
- **Descrição** do que significa
- **Cor**: Verde (sucesso), Amarelo (retry), Vermelho (erro), Cinza (pendente)

**Interatividade:**
- Clicar em um card filtra a tabela abaixo para aquele status
- Cards piscam se houver problemas críticos

---

### 2. Gráfico de Tendência (7 dias)

```
📊 Gráfico de Barras Empilhadas

    ▓▓▓ = Sucesso (verde)
    ░░░ = Retry (amarelo)  
    ▒▒▒ = Erro (vermelho)

  Cada barra = 1 dia
  Hover = mostra números exatos
  Click = filtra tabela para aquele dia
```

**Utilidade:**
- Ver se o problema está piorando ou melhorando
- Identificar horários/dias com mais falhas
- Comparar com semanas anteriores

---

### 3. Tabela de RATs que Precisam de Atenção

**Esta é a parte MAIS IMPORTANTE do dashboard!**

#### Colunas da Tabela

| Coluna | O Que Mostra | Exemplo |
|--------|--------------|---------|
| **ID** | Número interno da RAT | `#789` |
| **Ticket** | Número e título do ticket | `INC001234`<br>`Impressora Canon...` |
| **Status** | Badge colorido | 🔄 Retry<br>❌ Erro<br>⏱️ Pendente |
| **Retry** | Tentativas realizadas | `2/3` (normal)<br>`3/3 ⚠️MAX` (crítico) |
| **Último Erro** | Mensagem resumida | `HTTP 500 - Internal Server Error`<br>`HTTP 422 - Validação entidade` |
| **Próximo Retry** | Countdown ao vivo | `em 3min`<br>`em 2h`<br>`-` (sem retry) |
| **Ações** | Botões de ação | `[▶️] [❌] [🔧] [📋]` |

#### Botões de Ação (O Que Cada Um Faz)

**[▶️] Tentar Agora**
- Força retry imediato, sem esperar
- Mostra loading enquanto processa
- Atualiza linha com resultado em tempo real
- **Uso**: Quando corrigimos o problema e queremos testar agora

**[❌] Cancelar Retry**
- Marca como erro definitivo
- Cancela tentativas futuras
- Pede motivo (opcional)
- **Uso**: Quando sabemos que não vai funcionar automaticamente

**[🔧] Editar Payload**
- Abre modal para ajustar dados
- Permite corrigir erros de validação
- Reenvia após edição
- **Uso**: Quando há erro de dados (entidade errada, campo faltando, etc.)

**[📋] Ver Detalhes**
- Abre modal com histórico completo
- Mostra todas as tentativas
- Exibe payload JSON completo
- Mostra resposta do servidor
- **Uso**: Para investigar e diagnosticar problemas

---

### 4. Exemplo Prático: Como Usar o Dashboard

#### Cenário 1: Servidor Laravel Instável (Manhã)

**8h30 - Você abre o dashboard:**
```
📊 Cards mostram:
✅ Sucesso: 0 (0%)
🔄 Retry: 15 (100%)  ← Muitas em retry!
❌ Erro: 0 (0%)
```

**O que fazer:**
1. Ver tabela: todas as RATs estão em `tentativa 1/3` ou `2/3`
2. Verificar "Último Erro": todos dizem `HTTP 500` ou `Timeout`
3. **Ação**: Aguardar! O sistema vai tentar automaticamente
4. Checar servidor Laravel se o problema persistir

---

#### Cenário 2: Problema de Configuração (Entidade Errada)

**Dashboard mostra:**
```
❌ Erro: 5 RATs com "3/3 ⚠️MAX"
Último Erro: "HTTP 422 - Campo 'entidade' inválido"
```

**O que fazer:**
1. Clicar em `[🔧] Editar Payload` na primeira RAT
2. Modal abre mostrando campos
3. Corrigir campo "Entidade" para o valor correto
4. Clicar "Salvar e Enviar"
5. RAT é recriada imediatamente
6. Repetir para as outras 4 RATs

---

#### Cenário 3: URL do Laravel Mudou

**Dashboard mostra:**
```
❌ Erro: 50 RATs com "3/3 ⚠️MAX"
Último Erro: "HTTP 404 - Endpoint not found"
```

**O que fazer:**
1. Ir em `Plugins → RAT Digital → Configuração`
2. Atualizar campo "URL do Laravel"
3. Voltar ao dashboard
4. Clicar em `[▶️] Tentar Agora` em cada RAT
5. Ou usar botão "Reprocessar Todas as RATs com Erro" (se implementado)

---

### 5. Modal de Detalhes (Ao Clicar em 📋)

**O que você vê:**

```
┌─────────────────────────────────────────┐
│  📋 Detalhes da RAT #789                │
├─────────────────────────────────────────┤
│                                         │
│  🎫 INFORMAÇÕES DO TICKET               │
│  Ticket:     INC001234                  │
│  Criado em:  23/10/2025 14:32:15        │
│  Entidade:   Empresa ABC Ltda           │
│                                         │
│  📊 STATUS ATUAL                        │
│  Status:        🔄 Retry                │
│  Tentativas:    2/3                     │
│  Próximo retry: em 3 minutos            │
│                                         │
│  📝 HISTÓRICO DE TENTATIVAS             │
│  ⏱️ Tentativa #1 - 14:32:20            │
│     ❌ HTTP 500 - Internal Server Error │
│     Próximo: +5min                      │
│                                         │
│  ⏱️ Tentativa #2 - 14:37:22            │
│     ❌ HTTP 500 - Server unavailable   │
│     Próximo: +15min                     │
│                                         │
│  ⏳ Tentativa #3 - Agendada            │
│     Será executada em 3 minutos         │
│                                         │
│  📦 PAYLOAD ENVIADO (Expandível)        │
│  📤 RESPOSTA COMPLETA (Expandível)      │
│                                         │
│  [Fechar]  [▶️ Tentar Agora]  [🔧]      │
└─────────────────────────────────────────┘
```

**Utilidade:**
- Ver exatamente o que foi enviado
- Entender por que falhou
- Copiar/colar payload para testes manuais
- Diagnóstico completo

---

### 6. Filtros (Topo da Tabela)

```
🔍 FILTROS:
[Status: Todos ▼] [Período: Últimas 24h ▼] [Entidade: Todas ▼]
[🔄 Atualizar] [📊 Exportar CSV] [🔔 Configurar Alertas]
```

**Opções de Filtro:**

**Status:**
- Todos
- Apenas Erro (3/3)
- Apenas Retry (1/3, 2/3)
- Apenas Pendente

**Período:**
- Última hora
- Últimas 24 horas
- Últimos 7 dias
- Últimos 30 dias
- Personalizado (selecionar datas)

**Entidade:**
- Todas
- Empresa A
- Empresa B
- etc.

---

### 7. Sistema de Alertas (🔔 Botão)

**Ao clicar, abre configuração:**

```
🔔 Configurar Alertas

📧 Email:
☑️ Enviar email quando RAT atingir máximo de tentativas
   Para: admin@empresa.com

☑️ Relatório diário de RATs falhadas
   Horário: 09:00
   Para: admin@empresa.com, ti@empresa.com

🔔 Notificações GLPI:
☑️ Notificar quando RAT falhar após 3 tentativas
☑️ Notificar quando mais de 5 RATs em erro nas últimas 1h

📊 Limite:
☑️ Alertar se taxa de erro ultrapassar 5% nas últimas 24h
```

**Exemplo de Email Recebido:**

```
De: GLPI Sistema
Para: admin@empresa.com
Assunto: ⚠️ [GLPI] 3 RATs com erro definitivo

3 RATs falharam e precisam de atenção:

1. RAT #785 - INC001230
   Erro: HTTP 422 - entidade inválida
   
2. RAT #783 - INC001228
   Erro: HTTP 404 - endpoint not found

👉 Acesse: glpi.empresa.com/plugins/ratdigital/...
```

---

## 📱 Versão Mobile

Dashboard funciona em celular/tablet com layout adaptado:

```
┌──────────────────────┐
│ ☰ RAT Digital        │
├──────────────────────┤
│ 📊 RESUMO            │
│ ✅ Sucesso: 4,523    │
│ 🔄 Retry: 12         │
│ ❌ Erro: 3           │
├──────────────────────┤
│ ⚠️ ATENÇÃO (3)       │
│                      │
│ ┌──────────────────┐ │
│ │ #785 INC001230   │ │
│ │ ❌ Erro 3/3      │ │
│ │ [▶️] [📋]        │ │
│ └──────────────────┘ │
│                      │
│ [Ver Mais ▼]         │
└──────────────────────┘
```

---

## 🔄 Atualização Automática

**Dashboard se atualiza automaticamente a cada 15 segundos**, mostrando:
- Números dos cards
- Status das RATs na tabela
- Countdown do próximo retry em tempo real
- Toast de notificação se aparecer novo erro

**Você pode:**
- ✅ Pausar auto-refresh
- ✅ Forçar atualização manual (botão 🔄)
- ✅ Ver quando foi a última atualização

---

## 📊 Indicador Visual no Ticket

Quando você abre um ticket, a aba "RAT Digital" tem badge visual:

```
Ticket #12345
├── [Ticket]
├── [Solução]
└── [RAT Digital] 🟡 2/3  ← BADGE AQUI
```

**Cores do Badge:**
- 🟢 ✅ = RAT criada com sucesso
- 🟡 🔄 2/3 = Em retry (mostra tentativas)
- 🔴 ❌ = Erro definitivo
- ⚪ ⏱️ = Pendente

---

## 🎯 Casos de Uso Reais

### 1. Monitoramento Diário (Gestor)

**Rotina:**
1. Abrir dashboard às 9h
2. Ver cards de resumo: "Tudo verde? Ótimo!"
3. Se houver RATs com erro: investigar
4. Exportar relatório semanal (CSV)

---

### 2. Resolução de Problema (Técnico)

**Situação:** Servidor Laravel ficou offline por 30 minutos

1. Dashboard mostra 20 RATs em retry
2. Técnico verifica servidor Laravel
3. Reativa servidor
4. Aguarda 5-15 minutos para retries automáticos
5. Verifica dashboard: todas passaram para "Sucesso" ✅
6. Se alguma ainda falhar: `[▶️] Tentar Agora`

---

### 3. Correção de Configuração (Admin)

**Situação:** Mudou URL do Laravel, esqueceu de atualizar no GLPI

1. Dashboard mostra 50 RATs com "HTTP 404"
2. Admin percebe o erro
3. Vai em Configuração e atualiza URL
4. Volta ao dashboard
5. Clica em "Reprocessar Todas" ou manualmente em cada uma
6. RATs são recriadas com sucesso

---

### 4. Auditoria (Gestor/QA)

**Objetivo:** Verificar qualidade do processo

1. Abrir dashboard
2. Ver gráfico de tendência dos últimos 30 dias
3. Exportar dados para CSV
4. Analisar:
   - Taxa de sucesso média
   - Horários com mais falhas
   - Entidades com mais problemas
5. Tomar ações preventivas

---

## ✅ Benefícios do Dashboard

### Para Técnicos:
- ✅ Ver imediatamente quais RATs têm problema
- ✅ Resolver rapidamente com 1 clique
- ✅ Não precisa verificar ticket por ticket
- ✅ Histórico completo para diagnóstico

### Para Gestores:
- ✅ Visibilidade da saúde do sistema
- ✅ Métricas de performance (taxa de sucesso)
- ✅ Relatórios automáticos por email
- ✅ Identificar padrões e tendências

### Para Administradores:
- ✅ Detectar problemas de configuração
- ✅ Monitorar conectividade com Laravel
- ✅ Auditoria completa de tentativas
- ✅ Ações corretivas rápidas

---

## 🚀 Timeline de Implementação

### Semana 1-2: MVP
- Cards de resumo
- Tabela básica
- Botão de retry manual
- Modal de detalhes

### Semana 3: Melhorias
- Gráfico de tendência
- Filtros avançados
- Edição de payload
- Auto-refresh

### Semana 4: Alertas
- Sistema de notificações
- Emails automáticos
- Configuração de limites

### Semana 5: Finalizações
- Responsividade mobile
- Exportação CSV
- Documentação
- Testes de carga

---

## 💡 Resumo Final

O dashboard é o **centro de controle** para gerenciar RATs:

1. **Ver rapidamente** se há problemas (cards coloridos)
2. **Identificar causa** dos problemas (tabela com erros)
3. **Resolver com 1 clique** (botões de ação)
4. **Ser alertado** automaticamente (emails e notificações)
5. **Auditar e melhorar** (gráficos e métricas)

**Resultado esperado:**
- 95% dos problemas resolvidos automaticamente (retry)
- 5% resolvidos manualmente em poucos cliques (dashboard)
- 0% de RATs perdidas sem visibilidade

---

**Próximo passo:** Ver mockup visual completo em [`dashboard-mockup.md`](dashboard-mockup.md)
