# Guia de Implementação: Sistema de Retry para RAT Digital

## 📋 Visão Geral

Este guia fornece instruções passo a passo para implementar o sistema de retry inteligente no plugin RAT Digital.

## 🎯 Objetivo

Adicionar resiliência à criação de RATs, permitindo retentativas automáticas quando o servidor Laravel está instável ou temporariamente indisponível.

---

## 📦 Arquivos Necessários

```
plugins/ratdigital/
├── inc/
│   └── rat.class.php                    (modificar métodos existentes)
├── scripts/
│   └── cron_retry_rats.php             (criar novo)
└── sql/
    └── migration_add_retry_columns.sql  (executar uma vez)
```

---

## 🚀 Passo 1: Executar Migração SQL

### 1.1. Fazer Backup do Banco de Dados

```bash
# Backup completo
mysqldump -u root -p glpi > /backup/glpi_backup_$(date +%Y%m%d_%H%M%S).sql

# Ou apenas a tabela específica
mysqldump -u root -p glpi glpi_plugin_ratdigital_rats > /backup/ratdigital_rats_backup_$(date +%Y%m%d_%H%M%S).sql
```

### 1.2. Executar Migração

```bash
# Via linha de comando
mysql -u root -p glpi < /var/www/html/glpi/plugins/snowclient/docs/examples/migration_add_retry_columns.sql

# Ou via MySQL Workbench / phpMyAdmin
# Copiar e colar o conteúdo do arquivo
```

### 1.3. Verificar Sucesso

```sql
-- Verificar se as colunas foram criadas
DESCRIBE glpi_plugin_ratdigital_rats;

-- Deve mostrar as novas colunas:
-- retry_count, max_retries, next_retry_at, last_error
```

---

## 🛠️ Passo 2: Modificar Classe RAT

### 2.1. Atualizar Método `updateRatRecord()`

Abrir arquivo: `plugins/ratdigital/inc/rat.class.php`

Localizar método `updateRatRecord()` (linha ~301) e adicionar novos parâmetros:

```php
/**
 * Atualizar registro na tabela rats
 */
static function updateRatRecord(
    $rat_id, 
    $status, 
    $rat_url = null, 
    $error_message = null, 
    $response_data = null, 
    $payload = null,
    $retry_count = null,      // NOVO
    $next_retry_at = null     // NOVO
)
{
    global $DB;
    
    error_log("RAT Digital - UPDATE: Atualizando registro RAT {$rat_id} com status '{$status}'");
    
    if (empty($rat_id) || !is_numeric($rat_id)) {
        error_log("RAT Digital - UPDATE: ID de RAT inválido");
        return false;
    }
    
    $input = [
        'status' => $status,
        'response_at' => $_SESSION['glpi_currenttime']
    ];
    
    if ($rat_url) {
        $input['rat_url'] = $rat_url;
        error_log("RAT Digital - UPDATE: URL da RAT definida: " . $rat_url);
    }
    
    if ($error_message) {
        $input['error_message'] = $error_message;
        $input['last_error'] = $error_message;  // NOVO: duplicar para last_error
        error_log("RAT Digital - UPDATE: Mensagem de erro: " . $error_message);
    }
    
    if ($response_data) {
        $input['response_data'] = is_string($response_data) ? $response_data : json_encode($response_data);
        error_log("RAT Digital - UPDATE: Dados de resposta registrados");
    }
    
    if ($payload) {
        $input['payload_sent'] = is_string($payload) ? $payload : json_encode($payload);
        error_log("RAT Digital - UPDATE: Payload registrado");
    }
    
    // NOVO: Adicionar campos de retry
    if ($retry_count !== null) {
        $input['retry_count'] = (int)$retry_count;
        error_log("RAT Digital - UPDATE: retry_count definido: " . $retry_count);
    }
    
    if ($next_retry_at !== null) {
        $input['next_retry_at'] = $next_retry_at;
        error_log("RAT Digital - UPDATE: next_retry_at definido: " . $next_retry_at);
    }
    
    try {
        $result = $DB->update('glpi_plugin_ratdigital_rats', $input, ['id' => $rat_id]);
        error_log("RAT Digital - UPDATE: Atualização concluída. Resultado: " . ($result ? 'sucesso' : 'falha'));
        return $result;
    } catch (Exception $e) {
        error_log("RAT Digital - UPDATE: Exceção ao atualizar registro: " . $e->getMessage());
        return false;
    }
}
```

### 2.2. Modificar Método `sendToLaravel()`

Localizar método `sendToLaravel()` (linha ~1010) e modificar a lógica de erro:

```php
// ANTES (linha ~1148):
if ($http_code !== 201) {
    error_log("RAT Digital - SENDTOLARAVEL: Erro HTTP: " . $http_code);
    $error_msg = __('RAT Digital: Erro HTTP ', 'ratdigital') . $http_code;
    
    // ... código de log de erro ...
    
    if ($rat_id) {
        self::updateRatRecord($rat_id, 'error', null, 'HTTP ' . $http_code . ': ' . $response, null, $payload);
    }
    return false;
}

// DEPOIS (substituir por):
if ($http_code !== 201) {
    error_log("RAT Digital - SENDTOLARAVEL: Erro HTTP: " . $http_code);
    
    // Buscar contador de retry atual
    global $DB;
    $rat = $DB->request([
        'FROM' => 'glpi_plugin_ratdigital_rats',
        'WHERE' => ['id' => $rat_id]
    ])->next();
    
    $retry_count = isset($rat['retry_count']) ? (int)$rat['retry_count'] : 0;
    $max_retries = isset($rat['max_retries']) ? (int)$rat['max_retries'] : 3;
    
    if ($retry_count < $max_retries) {
        // Agendar retry com backoff exponencial
        $delay_minutes = [5, 15, 30][$retry_count] ?? 60;
        $next_retry = date('Y-m-d H:i:s', strtotime("+{$delay_minutes} minutes"));
        
        $error_msg = "HTTP {$http_code}. Retry " . ($retry_count + 1) . "/{$max_retries} agendado para {$next_retry}";
        
        error_log("RAT Digital - SENDTOLARAVEL: {$error_msg}");
        
        if ($rat_id) {
            self::updateRatRecord(
                $rat_id, 
                'retry',              // Novo status
                null, 
                $error_msg,
                null,
                $payload,
                $retry_count + 1,     // Incrementar contador
                $next_retry           // Agendar próxima tentativa
            );
        }
        
        if (class_exists('Session')) {
            Session::addMessageAfterRedirect(
                __('RAT Digital: Falha temporária. Nova tentativa será realizada automaticamente.', 'ratdigital'),
                false,
                WARNING
            );
        }
        
        return false;
        
    } else {
        // Máximo de tentativas atingido - erro definitivo
        error_log("RAT Digital - SENDTOLARAVEL: Máximo de tentativas atingido ({$max_retries})");
        
        $error_msg = "Falha após {$max_retries} tentativas. HTTP {$http_code}: " . $response;
        
        if ($rat_id) {
            self::updateRatRecord($rat_id, 'error', null, $error_msg, null, $payload);
        }
        
        if (class_exists('Session')) {
            Session::addMessageAfterRedirect(
                __('RAT Digital: Falha após múltiplas tentativas. Contate o administrador.', 'ratdigital'),
                false,
                ERROR
            );
        }
        
        // TODO: Notificar administradores
        
        return false;
    }
}
```

### 2.3. Adicionar Método `retryCreateRat()`

Adicionar no final da classe `PluginRatdigitalRat`:

```php
/**
 * Tentar criar RAT novamente para um registro existente (usado pelo cron)
 */
static function retryCreateRat($rat_id, $ticket)
{
    global $DB;
    
    error_log("RAT Digital - RETRY: Iniciando retry para RAT ID {$rat_id}");
    
    // Buscar dados do registro
    $rat = $DB->request([
        'FROM' => 'glpi_plugin_ratdigital_rats',
        'WHERE' => ['id' => $rat_id]
    ])->next();
    
    if (!$rat) {
        error_log("RAT Digital - RETRY: RAT ID {$rat_id} não encontrada");
        return false;
    }
    
    // Decodificar payload anterior
    $payload = json_decode($rat['payload_sent'], true);
    
    if (!$payload) {
        // Se não tem payload salvo, preparar novamente
        error_log("RAT Digital - RETRY: Payload não encontrado, preparando novo");
        $payload = self::preparePayload($ticket);
    }
    
    // Obter configuração
    $config = PluginRatdigitalConfig::getInstance();
    
    if (empty($config->fields['rat_url'])) {
        error_log("RAT Digital - RETRY: URL do Laravel não configurada");
        return false;
    }
    
    // Tentar enviar novamente
    error_log("RAT Digital - RETRY: Enviando requisição ao Laravel");
    $response = self::sendToLaravel($config->fields['rat_url'], $payload, $rat_id);
    
    if ($response && isset($response['data']['url'])) {
        // Sucesso!
        $rat_url = $response['data']['url'];
        
        error_log("RAT Digital - RETRY: Sucesso! URL: {$rat_url}");
        
        self::updateRatRecord($rat_id, 'success', $rat_url, null, $response, $payload);
        self::addTicketFollowup($ticket, $rat_url);
        
        return true;
    }
    
    error_log("RAT Digital - RETRY: Falha no retry");
    return false;
}
```

---

## ⏰ Passo 3: Configurar Cron Job

### 3.1. Copiar Script de Cron

```bash
# Criar diretório scripts se não existir
mkdir -p /var/www/html/glpi/plugins/ratdigital/scripts

# Copiar arquivo de exemplo
cp /var/www/html/glpi/plugins/snowclient/docs/examples/cron_retry_rats.php \
   /var/www/html/glpi/plugins/ratdigital/scripts/

# Dar permissão de execução
chmod +x /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php

# Ajustar proprietário
chown www-data:www-data /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php
```

### 3.2. Criar Diretório de Logs

```bash
mkdir -p /var/log/glpi
chown www-data:www-data /var/log/glpi
chmod 755 /var/log/glpi
```

### 3.3. Adicionar ao Crontab

```bash
# Editar crontab do usuário www-data (ou root)
sudo crontab -u www-data -e

# Adicionar linha:
# Reprocessar RATs falhadas a cada 5 minutos
*/5 * * * * /usr/bin/php /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php >> /var/log/glpi/ratdigital_cron.log 2>&1
```

### 3.4. Testar Manualmente

```bash
# Executar manualmente para testar
sudo -u www-data php /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php

# Verificar logs
tail -f /var/log/glpi/ratdigital_cron.log
```

---

## ✅ Passo 4: Validação

### 4.1. Criar Ticket de Teste

1. Criar ticket via ServiceNow API
2. Verificar na tabela `glpi_plugin_ratdigital_rats`:

```sql
SELECT id, tickets_id, status, retry_count, next_retry_at, rat_url 
FROM glpi_plugin_ratdigital_rats 
ORDER BY id DESC LIMIT 5;
```

### 4.2. Simular Falha

Para testar o retry, pode-se temporariamente:

1. **Opção 1**: Desativar servidor Laravel
```bash
# Parar servidor Laravel temporariamente
systemctl stop laravel-rat-server
```

2. **Opção 2**: Alterar URL para inválida
```sql
-- Temporariamente alterar URL de configuração
UPDATE glpi_plugin_ratdigital_configs 
SET rat_url = 'https://servidor-invalido.exemplo.com/api/rats';
```

3. Criar ticket no GLPI
4. Verificar que RAT fica com `status='retry'`
5. Aguardar 5 minutos (ou executar cron manualmente)
6. Reativar servidor Laravel
7. Verificar que RAT foi criada com sucesso

### 4.3. Monitorar Logs

```bash
# Logs do GLPI
tail -f /var/www/html/glpi/files/_log/php-errors.log | grep "RAT Digital"

# Logs do cron
tail -f /var/log/glpi/ratdigital_cron.log

# Logs do sistema
journalctl -u cron -f | grep ratdigital
```

---

## 📊 Passo 5: Monitoramento

### 5.1. Query de Estatísticas

```sql
-- Estatísticas de RATs por status
SELECT 
    status,
    COUNT(*) as total,
    AVG(retry_count) as avg_retries,
    MAX(retry_count) as max_retries
FROM 
    glpi_plugin_ratdigital_rats
WHERE 
    sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY 
    status;

-- RATs que precisam de atenção
SELECT 
    r.id,
    r.tickets_id,
    t.name as ticket_name,
    r.status,
    r.retry_count,
    r.last_error,
    r.sent_at,
    r.next_retry_at
FROM 
    glpi_plugin_ratdigital_rats r
    JOIN glpi_tickets t ON r.tickets_id = t.id
WHERE 
    r.status IN ('retry', 'error')
    AND r.sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY 
    r.sent_at DESC;
```

### 5.2. Dashboard Recomendado

Criar view no GLPI ou Grafana com:
- Taxa de sucesso/falha por dia
- Número de retries por RAT
- Tempo médio até sucesso
- RATs com erro que precisam de intervenção manual

---

## 🔧 Troubleshooting

### Problema: Cron não está executando

```bash
# Verificar se o cron está rodando
systemctl status cron

# Verificar logs do cron
grep CRON /var/log/syslog

# Verificar se o usuário tem permissão
ls -la /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php

# Testar execução manual
sudo -u www-data php /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php
```

### Problema: RATs não estão sendo retentadas

```bash
# Verificar se há RATs pendentes
mysql -u root -p glpi -e "SELECT * FROM glpi_plugin_ratdigital_rats WHERE status='retry' AND next_retry_at <= NOW();"

# Verificar logs
tail -100 /var/log/glpi/ratdigital_cron.log

# Executar cron com mais verbosidade
php /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php 2>&1 | tee /tmp/ratdigital_debug.log
```

### Problema: Muitas RATs com erro definitivo

1. Verificar conectividade com servidor Laravel:
```bash
curl -X POST https://seu-servidor-laravel.com/api/rats \
  -H "Content-Type: application/json" \
  -d '{"test": "connection"}'
```

2. Verificar configuração:
```sql
SELECT * FROM glpi_plugin_ratdigital_configs;
```

3. Resetar contador de retry (se necessário):
```sql
-- CUIDADO: Apenas para casos específicos
UPDATE glpi_plugin_ratdigital_rats 
SET status = 'retry', retry_count = 0, next_retry_at = NOW()
WHERE id = 123 AND status = 'error';
```

---

## 📝 Notas Importantes

1. **Backup**: Sempre fazer backup antes de aplicar alterações
2. **Testes**: Testar em ambiente de homologação primeiro
3. **Monitoramento**: Configurar alertas para RATs com erro
4. **Performance**: O cron processa até 10 RATs por execução para evitar sobrecarga
5. **Logs**: Manter logs por pelo menos 30 dias para análise

---

## 🚨 Rollback

Se precisar reverter as alterações:

1. **Remover cron**:
```bash
sudo crontab -u www-data -e
# Remover linha do ratdigital
```

2. **Reverter alterações no código**:
```bash
cd /var/www/html/glpi/plugins/ratdigital
git checkout inc/rat.class.php  # Se estiver usando git
# Ou restaurar de backup
```

3. **Reverter banco de dados** (opcional):
```sql
ALTER TABLE glpi_plugin_ratdigital_rats 
DROP COLUMN IF EXISTS retry_count,
DROP COLUMN IF EXISTS max_retries,
DROP COLUMN IF EXISTS next_retry_at,
DROP COLUMN IF EXISTS last_error;

DROP INDEX IF EXISTS idx_status_retry ON glpi_plugin_ratdigital_rats;
```

---

## 📞 Suporte

Em caso de dúvidas ou problemas:
- Verificar logs em `/var/log/glpi/ratdigital_cron.log`
- Consultar documentação completa em `docs/ratdigital-integration-analysis.md`
- Abrir issue no repositório do projeto
