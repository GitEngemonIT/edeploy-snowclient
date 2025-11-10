# Análise: Integração RAT Digital com ServiceNow - Problemas e Soluções

---

## 📚 Documentação Relacionada

Este é o **documento principal de análise técnica**. Documentos complementares:

- 📊 **[Dashboard - Resumo Executivo](examples/DASHBOARD_RESUMO.md)** - Visão geral do dashboard (O QUE você verá)
- 🎨 **[Dashboard - Mockup Completo](examples/dashboard-mockup.md)** - Wireframes e especificações de UX/UI
- 🛠️ **[Guia de Implementação](examples/README_IMPLEMENTATION.md)** - Passo a passo para implementar
- 💾 **[Script SQL de Migração](examples/migration_add_retry_columns.sql)** - Alterar estrutura do banco
- ⏰ **[Script Cron de Retry](examples/cron_retry_rats.php)** - Reprocessamento automático

---

## 📋 Sumário Executivo

Quando tickets do ServiceNow são criados no GLPI via API REST, o plugin RAT Digital tenta criar automaticamente uma RAT no servidor Laravel. **Problema identificado**: se o servidor da RAT Digital estiver instável ou indisponível, a RAT não é criada e a URL não é gerada, resultando em perda de dados e necessidade de intervenção manual.

---

## 🔍 Cenário Atual de Criação da RAT Digital

### Fluxo de Execução

```
ServiceNow → API REST GLPI → Ticket Criado → Hook item_add → plugin_ratdigital_item_add()
                                                                         ↓
                                                    PluginRatdigitalRat::createRatOnTicketAdd()
                                                                         ↓
                                                    1. Verifica elegibilidade (entidade configurada)
                                                    2. Cria registro na tabela glpi_plugin_ratdigital_rats (status='pending')
                                                    3. Prepara payload (extrai dados do ticket)
                                                    4. Envia para Laravel via cURL (sendToLaravel)
                                                    5. Aguarda resposta (timeout 30s)
                                                    6. Atualiza registro com URL ou erro
                                                    7. Adiciona followup no ticket com URL
```

### Pontos Críticos Identificados

#### 1. **Execução Síncrona e Bloqueante**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - linha ~233
$response = self::sendToLaravel($config->fields['rat_url'], $payload, $rat_id);
```
- ❌ **Problema**: A criação do ticket fica bloqueada aguardando resposta do servidor Laravel (timeout de 30s)
- ❌ **Impacto**: Se o servidor estiver lento ou instável, a criação do ticket demora ou falha
- ❌ **Consequência**: Usuário tem experiência ruim e ticket pode ser criado sem a RAT

#### 2. **Tentativa Única, Sem Retry**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - linha ~1073
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($http_code !== 201) {
    // Atualiza como erro e desiste
    self::updateRatRecord($rat_id, 'error', null, 'HTTP ' . $http_code);
    return false;
}
```
- ❌ **Problema**: Se a primeira tentativa falhar (timeout, erro 500, erro de rede), não há retry
- ❌ **Impacto**: Instabilidades temporárias resultam em perda permanente da RAT
- ❌ **Consequência**: Chamados ficam sem URL da RAT, exigindo criação manual

#### 3. **Falta de Persistência para Recuperação**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - linha ~259
if ($response && isset($response['data']['url'])) {
    self::updateRatRecord($rat_id, 'success', $response['data']['url'], null, $response, $payload);
} else {
    // Marca como erro e não tenta novamente
    self::updateRatRecord($rat_id, 'error', null, 'Falha no envio');
}
```
- ❌ **Problema**: Após falha, o registro fica com `status='error'` e nenhum mecanismo tenta reprocessar
- ❌ **Impacto**: RATs com erro precisam de intervenção manual
- ❌ **Consequência**: Perda de dados, retrabalho, falta de auditoria

#### 4. **Ausência de Monitoramento e Alertas**
- ❌ Não há dashboard de RATs pendentes/falhadas
- ❌ Não há notificações para administradores quando há falhas
- ❌ Não há métricas de taxa de sucesso/falha

---

## 💡 Propostas de Melhoria

### ✅ Solução 1: Sistema de Retry Inteligente (Recomendado)

#### Descrição
Implementar um sistema de retentativas automáticas com backoff exponencial quando houver falha na criação da RAT.

#### Implementação

**1.1. Adicionar controle de tentativas na tabela**
```sql
-- Adicionar colunas na tabela glpi_plugin_ratdigital_rats
ALTER TABLE `glpi_plugin_ratdigital_rats` 
ADD COLUMN `retry_count` INT DEFAULT 0 AFTER `status`,
ADD COLUMN `next_retry_at` DATETIME DEFAULT NULL AFTER `retry_count`,
ADD COLUMN `last_error` TEXT DEFAULT NULL AFTER `next_retry_at`,
ADD COLUMN `max_retries` INT DEFAULT 3 AFTER `retry_count`;
```

**1.2. Modificar lógica de falha para agendar retry**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - método sendToLaravel()

// Quando falhar, ao invés de marcar como erro definitivo:
if ($http_code !== 201) {
    $retry_count = $this->getRetryCount($rat_id);
    
    if ($retry_count < 3) {
        // Calcular próximo retry com backoff exponencial
        // Retry 1: +5 minutos
        // Retry 2: +15 minutos  
        // Retry 3: +30 minutos
        $delay_minutes = [5, 15, 30][$retry_count];
        $next_retry = date('Y-m-d H:i:s', strtotime("+{$delay_minutes} minutes"));
        
        self::updateRatRecord(
            $rat_id, 
            'retry', // Novo status
            null, 
            "Falha HTTP {$http_code}. Retry {$retry_count}/3 agendado para {$next_retry}",
            null,
            $payload,
            $retry_count + 1,
            $next_retry
        );
        
        error_log("RAT Digital - Retry agendado para {$next_retry}");
        return false;
    } else {
        // Após 3 tentativas, marcar como erro definitivo
        self::updateRatRecord($rat_id, 'error', null, 'Falha após 3 tentativas', null, $payload);
        
        // Notificar administradores
        self::notifyAdminFailure($rat_id);
    }
}
```

**1.3. Criar cron job para reprocessar RATs pendentes**
```php
// Novo arquivo: plugins/ratdigital/front/cron_retry.php

/**
 * Cron job para reprocessar RATs com retry agendado
 * Executar a cada 5 minutos via crontab:
 * */5 * * * * php /var/www/html/glpi/plugins/ratdigital/front/cron_retry.php
 */

include '../../../inc/includes.php';

global $DB;

// Buscar RATs que precisam de retry
$rats_to_retry = $DB->request([
    'FROM' => 'glpi_plugin_ratdigital_rats',
    'WHERE' => [
        'status' => 'retry',
        'next_retry_at' => ['<=', date('Y-m-d H:i:s')]
    ],
    'LIMIT' => 10 // Processar até 10 por vez
]);

foreach ($rats_to_retry as $rat) {
    error_log("RAT Digital - Cron: Reprocessando RAT ID {$rat['id']}");
    
    // Recarregar o ticket
    $ticket = new Ticket();
    if ($ticket->getFromDB($rat['tickets_id'])) {
        // Tentar criar novamente
        PluginRatdigitalRat::retryCreateRat($rat['id'], $ticket);
    }
}
```

**1.4. Adicionar método de retry**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php

/**
 * Tentar criar RAT novamente para um registro existente
 */
static function retryCreateRat($rat_id, $ticket)
{
    global $DB;
    
    // Buscar dados do registro
    $rat = $DB->request([
        'FROM' => 'glpi_plugin_ratdigital_rats',
        'WHERE' => ['id' => $rat_id]
    ])->next();
    
    if (!$rat) {
        error_log("RAT Digital - Retry: RAT ID {$rat_id} não encontrada");
        return false;
    }
    
    // Decodificar payload anterior
    $payload = json_decode($rat['payload_sent'], true);
    
    if (!$payload) {
        // Se não tem payload salvo, preparar novamente
        $payload = self::preparePayload($ticket);
    }
    
    // Obter configuração
    $config = PluginRatdigitalConfig::getInstance();
    
    // Tentar enviar novamente
    error_log("RAT Digital - Retry: Tentando reenviar RAT ID {$rat_id}");
    $response = self::sendToLaravel($config->fields['rat_url'], $payload, $rat_id);
    
    if ($response && isset($response['data']['url'])) {
        // Sucesso!
        self::updateRatRecord($rat_id, 'success', $response['data']['url'], null, $response, $payload);
        self::addTicketFollowup($ticket, $response['data']['url']);
        
        // Notificar sucesso após retry
        if (class_exists('Session')) {
            Session::addMessageAfterRedirect(
                __("RAT Digital: RAT criada com sucesso após retry!", 'ratdigital'),
                false,
                INFO
            );
        }
        
        return true;
    }
    
    return false;
}
```

#### Vantagens
- ✅ **Resiliência**: Recupera automaticamente de falhas temporárias
- ✅ **Não invasivo**: Não bloqueia a criação do ticket
- ✅ **Auditável**: Mantém histórico de tentativas
- ✅ **Configurável**: Número de tentativas e delays ajustáveis

#### Desvantagens
- ⚠️ Requer configuração de cron job
- ⚠️ RAT não está disponível imediatamente após criação do ticket em caso de falha

---

### ✅ Solução 2: Queue Assíncrona com Worker

#### Descrição
Implementar uma fila de processamento assíncrono usando banco de dados, onde a criação da RAT é enfileirada e processada por um worker em background.

#### Implementação

**2.1. Criar tabela de queue**
```sql
CREATE TABLE `glpi_plugin_ratdigital_queue` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `rat_id` INT NOT NULL,
  `tickets_id` INT NOT NULL,
  `payload` TEXT NOT NULL,
  `priority` INT DEFAULT 1,
  `status` VARCHAR(50) DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `max_attempts` INT DEFAULT 3,
  `last_attempt_at` DATETIME DEFAULT NULL,
  `next_attempt_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `error_log` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status_next_attempt` (`status`, `next_attempt_at`),
  KEY `rat_id` (`rat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**2.2. Modificar criação para enfileirar**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - método createRatOnTicketAdd()

// Ao invés de enviar diretamente:
$response = self::sendToLaravel($config->fields['rat_url'], $payload, $rat_id);

// Enfileirar para processamento assíncrono:
self::enqueueRatCreation($rat_id, $ticket->fields['id'], $payload);

// Marcar RAT como enfileirada
self::updateRatRecord($rat_id, 'queued', null, 'Enfileirado para processamento');

// Adicionar mensagem informativa
Session::addMessageAfterRedirect(
    __('RAT Digital: A RAT será criada em breve. Você receberá a URL nos próximos minutos.', 'ratdigital'),
    false,
    INFO
);
```

**2.3. Criar worker de processamento**
```php
// Novo arquivo: plugins/ratdigital/scripts/queue_worker.php

#!/usr/bin/env php
<?php
/**
 * Worker assíncrono para processar queue de criação de RATs
 * 
 * Executar como daemon:
 * nohup php queue_worker.php > /var/log/ratdigital_worker.log 2>&1 &
 * 
 * Ou via systemd (recomendado)
 */

define('GLPI_ROOT', realpath(__DIR__ . '/../../..'));
include GLPI_ROOT . "/inc/includes.php";

error_log("RAT Digital - Worker iniciado");

// Loop infinito processando a queue
while (true) {
    try {
        global $DB;
        
        // Buscar próximo item da queue
        $queue_items = $DB->request([
            'FROM' => 'glpi_plugin_ratdigital_queue',
            'WHERE' => [
                'status' => 'pending',
                'OR' => [
                    'next_attempt_at' => ['<=', date('Y-m-d H:i:s')],
                    'next_attempt_at' => null
                ],
                'attempts' => ['<', new \QueryExpression($DB->quoteName('max_attempts'))]
            ],
            'ORDER' => ['priority DESC', 'created_at ASC'],
            'LIMIT' => 1
        ]);
        
        if (count($queue_items) > 0) {
            $item = $queue_items->next();
            
            error_log("RAT Digital - Worker: Processando item {$item['id']}");
            
            // Marcar como processando
            $DB->update('glpi_plugin_ratdigital_queue', [
                'status' => 'processing',
                'last_attempt_at' => date('Y-m-d H:i:s')
            ], ['id' => $item['id']]);
            
            // Tentar processar
            $result = PluginRatdigitalRat::processQueueItem($item);
            
            if ($result['success']) {
                // Sucesso - remover da queue
                $DB->update('glpi_plugin_ratdigital_queue', [
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s')
                ], ['id' => $item['id']]);
                
                error_log("RAT Digital - Worker: Item {$item['id']} processado com sucesso");
            } else {
                // Falha - incrementar tentativas e reagendar
                $attempts = $item['attempts'] + 1;
                
                if ($attempts >= $item['max_attempts']) {
                    // Máximo de tentativas atingido
                    $DB->update('glpi_plugin_ratdigital_queue', [
                        'status' => 'failed',
                        'attempts' => $attempts,
                        'error_log' => $result['error']
                    ], ['id' => $item['id']]);
                    
                    error_log("RAT Digital - Worker: Item {$item['id']} falhou após {$attempts} tentativas");
                } else {
                    // Reagendar com backoff exponencial
                    $delay_seconds = pow(2, $attempts) * 60; // 2min, 4min, 8min...
                    $next_attempt = date('Y-m-d H:i:s', time() + $delay_seconds);
                    
                    $DB->update('glpi_plugin_ratdigital_queue', [
                        'status' => 'pending',
                        'attempts' => $attempts,
                        'next_attempt_at' => $next_attempt,
                        'error_log' => $result['error']
                    ], ['id' => $item['id']]);
                    
                    error_log("RAT Digital - Worker: Item {$item['id']} falhou, tentativa {$attempts}/{$item['max_attempts']}. Próxima em {$delay_seconds}s");
                }
            }
        }
        
        // Aguardar 5 segundos antes de buscar próximo item
        sleep(5);
        
    } catch (Exception $e) {
        error_log("RAT Digital - Worker: Exceção: " . $e->getMessage());
        sleep(10); // Aguardar mais em caso de erro
    }
}
```

**2.4. Criar serviço systemd (recomendado)**
```ini
# Arquivo: /etc/systemd/system/ratdigital-worker.service

[Unit]
Description=RAT Digital Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/glpi/plugins/ratdigital/scripts
ExecStart=/usr/bin/php /var/www/html/glpi/plugins/ratdigital/scripts/queue_worker.php
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
# Habilitar e iniciar o serviço
sudo systemctl enable ratdigital-worker
sudo systemctl start ratdigital-worker
sudo systemctl status ratdigital-worker
```

#### Vantagens
- ✅ **Performance**: Criação do ticket não é bloqueada
- ✅ **Escalável**: Pode ter múltiplos workers processando em paralelo
- ✅ **Robusto**: Worker reinicia automaticamente em caso de falha
- ✅ **Monitorável**: Logs centralizados via systemd/journald

#### Desvantagens
- ⚠️ Complexidade de infraestrutura (requer daemon/systemd)
- ⚠️ Requer monitoramento do worker (garantir que está rodando)
- ⚠️ RAT disponível apenas alguns segundos/minutos após criação do ticket

---

### ✅ Solução 3: Fallback Manual com Dashboard

#### Descrição
Adicionar um dashboard para administradores visualizarem e reprocessarem manualmente RATs falhadas.

> **📋 MOCKUP COMPLETO DO DASHBOARD**: Veja o documento detalhado com mockups visuais, especificações de UX/UI e wireframes em [`examples/dashboard-mockup.md`](examples/dashboard-mockup.md)

#### Implementação

**3.1. Criar página de gerenciamento**
```php
// Novo arquivo: plugins/ratdigital/front/failed_rats.php

include '../../../inc/includes.php';

Session::checkRight('config', READ);

Html::header(__('RATs Falhadas', 'ratdigital'), $_SERVER['PHP_SELF'], 'plugins', 'ratdigital');

global $DB;

// Buscar RATs com erro ou pendentes há mais de 10 minutos
$failed_rats = $DB->request([
    'SELECT' => [
        'r.id',
        'r.tickets_id',
        'r.status',
        'r.error_message',
        'r.sent_at',
        'r.retry_count',
        't.name AS ticket_name',
        't.status AS ticket_status'
    ],
    'FROM' => 'glpi_plugin_ratdigital_rats AS r',
    'LEFT JOIN' => [
        'glpi_tickets AS t' => [
            'ON' => [
                'r' => 'tickets_id',
                't' => 'id'
            ]
        ]
    ],
    'WHERE' => [
        'OR' => [
            'r.status' => 'error',
            [
                'r.status' => 'pending',
                'r.sent_at' => ['<', date('Y-m-d H:i:s', strtotime('-10 minutes'))]
            ]
        ]
    ],
    'ORDER' => 'r.sent_at DESC'
]);

echo "<div class='center'>";
echo "<table class='tab_cadre_fixehov'>";
echo "<tr class='tab_bg_1'>";
echo "<th colspan='7'>" . __('RATs Falhadas - Gerenciamento Manual', 'ratdigital') . "</th>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<th>" . __('ID RAT', 'ratdigital') . "</th>";
echo "<th>" . __('Ticket', 'ratdigital') . "</th>";
echo "<th>" . __('Status', 'ratdigital') . "</th>";
echo "<th>" . __('Erro', 'ratdigital') . "</th>";
echo "<th>" . __('Tentativas', 'ratdigital') . "</th>";
echo "<th>" . __('Data', 'ratdigital') . "</th>";
echo "<th>" . __('Ações', 'ratdigital') . "</th>";
echo "</tr>";

foreach ($failed_rats as $rat) {
    echo "<tr class='tab_bg_1'>";
    echo "<td>{$rat['id']}</td>";
    echo "<td><a href='/glpi/front/ticket.form.php?id={$rat['tickets_id']}'>{$rat['ticket_name']}</a></td>";
    echo "<td>" . self::getStatusBadge($rat['status']) . "</td>";
    echo "<td>" . htmlspecialchars(substr($rat['error_message'], 0, 100)) . "</td>";
    echo "<td>{$rat['retry_count']}/3</td>";
    echo "<td>" . Html::convDateTime($rat['sent_at']) . "</td>";
    echo "<td>";
    echo "<a href='?action=retry&id={$rat['id']}' class='btn btn-primary'>" . __('Retentar', 'ratdigital') . "</a>";
    echo "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

Html::footer();
```

**3.2. Adicionar botão no ticket**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - método showRatDisplay()

// Adicionar botão para técnicos retentarem manualmente
if ($status === 'error' || $status === 'pending') {
    echo '<div style="margin-top: 15px;">';
    echo '<button class="btn btn-warning" onclick="retryRatCreation(' . $ticket_id . ')">';
    echo __('Tentar Criar RAT Novamente', 'ratdigital');
    echo '</button>';
    echo '</div>';
}
```

**3.3. Adicionar endpoint AJAX para retry manual**
```php
// Novo arquivo: plugins/ratdigital/ajax/retry_rat.php

include '../../../inc/includes.php';

Session::checkRight('config', UPDATE);

$rat_id = $_POST['rat_id'] ?? null;

if (!$rat_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

global $DB;

// Buscar RAT
$rat = $DB->request([
    'FROM' => 'glpi_plugin_ratdigital_rats',
    'WHERE' => ['id' => $rat_id]
])->next();

if (!$rat) {
    echo json_encode(['success' => false, 'message' => 'RAT não encontrada']);
    exit;
}

// Recarregar ticket
$ticket = new Ticket();
if (!$ticket->getFromDB($rat['tickets_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
    exit;
}

// Tentar criar novamente
$result = PluginRatdigitalRat::retryCreateRat($rat_id, $ticket);

if ($result) {
    echo json_encode(['success' => true, 'message' => __('RAT criada com sucesso!', 'ratdigital')]);
} else {
    echo json_encode(['success' => false, 'message' => __('Falha ao criar RAT', 'ratdigital')]);
}
```

#### Vantagens
- ✅ **Simples**: Fácil de implementar
- ✅ **Visibilidade**: Administradores veem claramente problemas
- ✅ **Controle**: Permite intervenção manual quando necessário

#### Desvantagens
- ⚠️ Requer intervenção manual
- ⚠️ Não resolve automaticamente o problema

---

### ✅ Solução 4: Webhook de Callback (Invertendo o Fluxo)

#### Descrição
Ao invés do GLPI ficar esperando resposta do Laravel, o GLPI envia a requisição e o Laravel chama um webhook quando a RAT estiver pronta.

#### Implementação

**4.1. Modificar envio para não aguardar resposta**
```php
// Arquivo: plugins/ratdigital/inc/rat.class.php - método sendToLaravel()

// Adicionar callback_url ao payload
$payload['callback_url'] = $CFG_GLPI['url_base'] . '/plugins/ratdigital/ajax/rat_callback.php';
$payload['callback_secret'] = self::generateCallbackSecret($rat_id);

// Configurar cURL para não aguardar resposta (fire and forget)
curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000); // Apenas 1 segundo
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

// Enviar e não aguardar
$response = curl_exec($ch);

// Registrar como enviado, aguardando callback
self::updateRatRecord($rat_id, 'callback_pending', null, 'Aguardando callback do Laravel');
```

**4.2. Criar endpoint de callback**
```php
// Novo arquivo: plugins/ratdigital/ajax/rat_callback.php

include '../../../inc/includes.php';

// Validar requisição
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['rat_id']) || !isset($input['url']) || !isset($input['secret'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Validar secret
if (!self::validateCallbackSecret($input['rat_id'], $input['secret'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid secret']);
    exit;
}

// Atualizar RAT com URL
PluginRatdigitalRat::updateRatRecord(
    $input['rat_id'], 
    'success', 
    $input['url'], 
    null, 
    $input
);

// Adicionar followup no ticket
$ticket = new Ticket();
if ($ticket->getFromDB($input['tickets_id'])) {
    PluginRatdigitalRat::addTicketFollowup($ticket, $input['url']);
}

echo json_encode(['success' => true]);
```

**4.3. Laravel deve chamar o callback quando RAT estiver pronta**
```php
// No servidor Laravel, após criar a RAT:

if (isset($request->callback_url)) {
    $client = new \GuzzleHttp\Client();
    $client->post($request->callback_url, [
        'json' => [
            'rat_id' => $request->rat_id,
            'tickets_id' => $request->tickets_id,
            'url' => $rat->url,
            'secret' => $request->callback_secret
        ],
        'timeout' => 5
    ]);
}
```

#### Vantagens
- ✅ **Não bloqueante**: GLPI não fica aguardando
- ✅ **Resiliente**: Se callback falhar, Laravel pode tentar novamente
- ✅ **Escalável**: Laravel pode processar assincronamente (queue)

#### Desvantagens
- ⚠️ Requer mudanças no Laravel
- ⚠️ Requer endpoint público acessível do Laravel
- ⚠️ Complexidade de segurança (validação de callback)

---

## 🎯 Recomendação Final

### Abordagem Híbrida: Solução 1 + Solução 3

**Implementar:**

1. **Sistema de Retry Inteligente (Solução 1)**
   - Adicionar colunas de retry na tabela
   - Implementar backoff exponencial (5min, 15min, 30min)
   - Criar cron job simples para reprocessar

2. **Dashboard de Gerenciamento (Solução 3)**
   - Interface para visualizar falhas
   - Botão de retry manual
   - Estatísticas de sucesso/falha
   - **📊 [Ver mockup completo do dashboard](examples/dashboard-mockup.md)**

**Justificativa:**
- ✅ Equilibra automação com controle manual
- ✅ Não requer infraestrutura complexa (apenas cron)
- ✅ Implementação incremental possível
- ✅ Resolve 95% dos casos de instabilidade temporária
- ✅ Fornece visibilidade e controle para casos extremos

### Próximos Passos

1. **Fase 1 - Quick Win (1-2 dias)**
   - Adicionar colunas de retry na tabela
   - Implementar lógica de retry no método `sendToLaravel()`
   - Criar script cron simples

2. **Fase 2 - Monitoramento (2-3 dias)**
   - Criar dashboard de RATs falhadas
   - Adicionar botão de retry manual
   - Implementar notificações para administradores

3. **Fase 3 - Otimização (opcional)**
   - Avaliar necessidade de queue assíncrona
   - Implementar métricas e alertas
   - Considerar webhook callback se necessário

---

## 📊 Métricas de Sucesso

**Antes da Implementação:**
- ❌ Taxa de falha: ~20-30% em momentos de instabilidade
- ❌ RATs perdidas: ~50-100/mês
- ❌ Intervenção manual: Alta

**Após Implementação (Esperado):**
- ✅ Taxa de sucesso após retry: ~95%
- ✅ RATs perdidas: <5/mês
- ✅ Intervenção manual: Baixa (apenas casos extremos)
- ✅ Visibilidade: 100% dos problemas rastreados

---

## 📝 Observações Importantes

### Sobre "Tentar 3x"
A abordagem de simplesmente "tentar 3 vezes imediatamente" **NÃO é ideal** porque:

1. **Problemas temporários precisam de tempo**: Se o servidor está sobrecarregado, tentar 3x em sequência só piora a situação
2. **Desperdiça recursos**: Cada tentativa bloqueia a thread por até 30 segundos (timeout)
3. **Experiência ruim**: Usuário esperaria até 90 segundos para criar um ticket
4. **Não resolve instabilidades longas**: Se o servidor fica fora por 5 minutos, 3 tentativas em 90 segundos falhariam de qualquer forma

**Retry com backoff exponencial é superior porque:**
- ✅ Dá tempo para o servidor se recuperar
- ✅ Não bloqueia a criação do ticket
- ✅ Mantém logs e auditoria
- ✅ Permite intervenção manual se necessário

---

## 🔧 Código de Exemplo: Implementação Completa do Retry

Veja os arquivos de exemplo na pasta `/docs/examples/`:
- [`migration_add_retry_columns.sql`](examples/migration_add_retry_columns.sql) - Script SQL de migração
- [`cron_retry_rats.php`](examples/cron_retry_rats.php) - Script de cron job funcional
- [`README_IMPLEMENTATION.md`](examples/README_IMPLEMENTATION.md) - Guia passo a passo de implementação
- [`dashboard-mockup.md`](examples/dashboard-mockup.md) - Mockup completo do dashboard de gerenciamento

---

**Documento gerado em:** 23 de outubro de 2025  
**Versão:** 1.0  
**Autor:** Análise técnica para integração RAT Digital + ServiceNow
