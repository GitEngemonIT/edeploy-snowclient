#!/usr/bin/env php
<?php
/**
 * ============================================================
 * Cron Job: Reprocessar RATs com falha
 * Plugin: ratdigital
 * Arquivo: /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php
 * ============================================================
 * 
 * DESCRIÇÃO:
 * Este script busca RATs com status 'retry' que estão agendadas
 * para reprocessamento e tenta criar novamente no servidor Laravel.
 * 
 * CONFIGURAÇÃO DO CRON:
 * Adicionar ao crontab (crontab -e):
 * 
 * # Reprocessar RATs falhadas a cada 5 minutos
 * */5 * * * * php /var/www/html/glpi/plugins/ratdigital/scripts/cron_retry_rats.php >> /var/log/glpi/ratdigital_cron.log 2>&1
 * 
 * LOGS:
 * - Logs são gravados via error_log() e vão para o arquivo especificado no cron
 * - Também são registrados no sistema de logs do GLPI
 * 
 * ============================================================
 */

// Verificar se está sendo executado via CLI
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado via linha de comando (CLI)\n");
}

// Definir caminho do GLPI
define('GLPI_ROOT', realpath(__DIR__ . '/../../../..'));

// Verificar se o arquivo includes existe
if (!file_exists(GLPI_ROOT . '/inc/includes.php')) {
    die("ERRO: Arquivo GLPI includes.php não encontrado em: " . GLPI_ROOT . "/inc/includes.php\n");
}

// Incluir dependências do GLPI
include_once GLPI_ROOT . '/inc/includes.php';

// Timestamp de início
$start_time = microtime(true);
$execution_id = date('YmdHis') . '_' . getmypid();

echo "\n";
echo "============================================================\n";
echo "RAT Digital - Cron Job de Retry\n";
echo "Execução ID: {$execution_id}\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

error_log("RAT Digital Cron - Iniciando execução {$execution_id}");

try {
    global $DB;
    
    // Verificar conexão com o banco
    if (!$DB || !$DB->connected) {
        throw new Exception("Não foi possível conectar ao banco de dados");
    }
    
    echo "[INFO] Conexão com banco de dados estabelecida\n";
    
    // Buscar RATs que precisam de retry
    $current_datetime = date('Y-m-d H:i:s');
    
    $query = [
        'FROM' => 'glpi_plugin_ratdigital_rats',
        'WHERE' => [
            'status' => 'retry',
            'next_retry_at' => ['<=', $current_datetime],
            'retry_count' => ['<', new \QueryExpression($DB->quoteName('max_retries'))]
        ],
        'ORDER' => 'next_retry_at ASC',
        'LIMIT' => 10 // Processar até 10 por execução
    ];
    
    $rats_to_retry = $DB->request($query);
    $total_found = count($rats_to_retry);
    
    echo "[INFO] RATs encontradas para retry: {$total_found}\n\n";
    error_log("RAT Digital Cron - {$total_found} RATs encontradas para retry");
    
    if ($total_found === 0) {
        echo "[INFO] Nenhuma RAT para processar neste momento\n";
        echo "============================================================\n";
        exit(0);
    }
    
    // Estatísticas da execução
    $stats = [
        'total' => $total_found,
        'success' => 0,
        'failed' => 0,
        'max_retries_reached' => 0,
        'errors' => []
    ];
    
    // Processar cada RAT
    foreach ($rats_to_retry as $rat) {
        $rat_id = $rat['id'];
        $tickets_id = $rat['tickets_id'];
        $retry_count = $rat['retry_count'];
        $max_retries = $rat['max_retries'];
        
        echo "----------------------------------------\n";
        echo "[PROCESSING] RAT ID: {$rat_id} | Ticket: {$tickets_id}\n";
        echo "             Tentativa: " . ($retry_count + 1) . "/{$max_retries}\n";
        
        error_log("RAT Digital Cron - Processando RAT {$rat_id} (tentativa " . ($retry_count + 1) . "/{$max_retries})");
        
        try {
            // Carregar o ticket
            $ticket = new Ticket();
            if (!$ticket->getFromDB($tickets_id)) {
                throw new Exception("Ticket {$tickets_id} não encontrado");
            }
            
            echo "             Ticket carregado: {$ticket->fields['name']}\n";
            
            // Verificar se a classe PluginRatdigitalRat existe
            if (!class_exists('PluginRatdigitalRat')) {
                throw new Exception("Classe PluginRatdigitalRat não encontrada. Verifique se o plugin está instalado.");
            }
            
            // Tentar criar a RAT novamente
            echo "             Enviando requisição ao servidor Laravel...\n";
            
            // Preparar payload
            $payload = json_decode($rat['payload_sent'], true);
            
            if (!$payload) {
                // Se não tem payload salvo, preparar novamente
                echo "             [WARN] Payload não encontrado, preparando novo payload\n";
                $payload = PluginRatdigitalRat::preparePayload($ticket);
            }
            
            // Obter configuração
            $config = PluginRatdigitalConfig::getInstance();
            
            if (empty($config->fields['rat_url'])) {
                throw new Exception("URL do Laravel não configurada");
            }
            
            // Enviar para Laravel
            $response = PluginRatdigitalRat::sendToLaravel(
                $config->fields['rat_url'], 
                $payload, 
                $rat_id
            );
            
            // Verificar resposta
            if ($response && isset($response['data']['url'])) {
                // Sucesso!
                $rat_url = $response['data']['url'];
                
                echo "             [SUCCESS] RAT criada: {$rat_url}\n";
                error_log("RAT Digital Cron - RAT {$rat_id} criada com sucesso: {$rat_url}");
                
                // Atualizar registro
                PluginRatdigitalRat::updateRatRecord(
                    $rat_id, 
                    'success', 
                    $rat_url, 
                    null, 
                    $response, 
                    $payload
                );
                
                // Adicionar followup no ticket
                PluginRatdigitalRat::addTicketFollowup($ticket, $rat_url);
                
                $stats['success']++;
                
            } else {
                // Falha na criação
                $retry_count++;
                
                if ($retry_count >= $max_retries) {
                    // Máximo de tentativas atingido
                    echo "             [ERROR] Máximo de tentativas atingido ({$max_retries})\n";
                    echo "             Marcando como erro definitivo\n";
                    
                    error_log("RAT Digital Cron - RAT {$rat_id} falhou após {$max_retries} tentativas");
                    
                    $error_message = "Falha após {$max_retries} tentativas. Última resposta: " . 
                                   json_encode($response);
                    
                    PluginRatdigitalRat::updateRatRecord(
                        $rat_id, 
                        'error', 
                        null, 
                        $error_message, 
                        $response, 
                        $payload
                    );
                    
                    $stats['max_retries_reached']++;
                    
                    // TODO: Notificar administradores via email/notificação
                    
                } else {
                    // Agendar próximo retry com backoff exponencial
                    $delay_minutes = [5, 15, 30][$retry_count - 1] ?? 60;
                    $next_retry = date('Y-m-d H:i:s', strtotime("+{$delay_minutes} minutes"));
                    
                    echo "             [RETRY] Falha na tentativa {$retry_count}/{$max_retries}\n";
                    echo "             Próxima tentativa agendada para: {$next_retry}\n";
                    
                    error_log("RAT Digital Cron - RAT {$rat_id} agendada para retry em {$delay_minutes} minutos");
                    
                    $error_message = "Tentativa {$retry_count} falhou. Resposta: " . 
                                   json_encode($response);
                    
                    // Atualizar registro
                    $DB->update(
                        'glpi_plugin_ratdigital_rats',
                        [
                            'retry_count' => $retry_count,
                            'next_retry_at' => $next_retry,
                            'last_error' => $error_message,
                            'response_data' => json_encode($response)
                        ],
                        ['id' => $rat_id]
                    );
                    
                    $stats['failed']++;
                }
            }
            
        } catch (Exception $e) {
            echo "             [EXCEPTION] " . $e->getMessage() . "\n";
            error_log("RAT Digital Cron - Exceção ao processar RAT {$rat_id}: " . $e->getMessage());
            
            $stats['errors'][] = [
                'rat_id' => $rat_id,
                'error' => $e->getMessage()
            ];
            
            // Incrementar contador de retry mesmo em caso de exceção
            $retry_count++;
            
            if ($retry_count >= $max_retries) {
                $DB->update(
                    'glpi_plugin_ratdigital_rats',
                    [
                        'status' => 'error',
                        'retry_count' => $retry_count,
                        'last_error' => $e->getMessage()
                    ],
                    ['id' => $rat_id]
                );
                $stats['max_retries_reached']++;
            } else {
                $delay_minutes = [5, 15, 30][$retry_count - 1] ?? 60;
                $next_retry = date('Y-m-d H:i:s', strtotime("+{$delay_minutes} minutes"));
                
                $DB->update(
                    'glpi_plugin_ratdigital_rats',
                    [
                        'retry_count' => $retry_count,
                        'next_retry_at' => $next_retry,
                        'last_error' => $e->getMessage()
                    ],
                    ['id' => $rat_id]
                );
                $stats['failed']++;
            }
        }
        
        // Pequeno delay entre requisições para não sobrecarregar o servidor
        usleep(500000); // 0.5 segundos
    }
    
    // Estatísticas finais
    $duration = round(microtime(true) - $start_time, 2);
    
    echo "\n";
    echo "============================================================\n";
    echo "ESTATÍSTICAS DA EXECUÇÃO\n";
    echo "============================================================\n";
    echo "Total processadas:          {$stats['total']}\n";
    echo "Sucesso:                    {$stats['success']}\n";
    echo "Falhas (retry agendado):    {$stats['failed']}\n";
    echo "Máximo de tentativas:       {$stats['max_retries_reached']}\n";
    echo "Exceções:                   " . count($stats['errors']) . "\n";
    echo "Tempo de execução:          {$duration}s\n";
    echo "============================================================\n\n";
    
    error_log("RAT Digital Cron - Execução {$execution_id} concluída: " . 
              "{$stats['success']} sucessos, {$stats['failed']} falhas, " . 
              "{$stats['max_retries_reached']} esgotadas, " . 
              count($stats['errors']) . " exceções em {$duration}s");
    
    // Exit code baseado no resultado
    if (count($stats['errors']) > 0) {
        exit(1); // Houve exceções
    } else {
        exit(0); // Sucesso
    }
    
} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    
    error_log("RAT Digital Cron - ERRO FATAL: " . $e->getMessage());
    error_log("RAT Digital Cron - Stack trace: " . $e->getTraceAsString());
    
    exit(2); // Erro fatal
}
