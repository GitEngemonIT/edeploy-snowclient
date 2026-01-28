<?php
/*
   -----------------------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/GitEngemonIT/edeploy-snowclient
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin ServiceNow Client project.
   Plugin ServiceNow Client is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   ------------------------------------------------------------------------
   @package   Plugin ServiceNow Client
   @author    EngemonIT
   @co-author
   @copyright Copyright (c) 2025 ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/GitEngemonIT/edeploy-snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginEdeploysnowclientApi
 */
class PluginEdeploysnowclientApi
{
    private $config;
    private $instance_url;
    private $username;
    private $password;
    private $debug_mode;

    public function __construct()
    {
        $this->config = PluginEdeploysnowclientConfig::getInstance();
        $this->instance_url = $this->config->fields['instance_url'];
        $this->username = $this->config->fields['username'];
        
        // Usar método correto para descriptografar senha
        $this->password = $this->config->getDecryptedPassword();
        
        $this->debug_mode = $this->config->fields['debug_mode'];
        
        // Log de debug apenas se modo debug estiver ativo
        if ($this->debug_mode) {
            error_log("eDeploySnowClient API DEBUG: Construtor inicializado");
            error_log("eDeploySnowClient API DEBUG: URL: " . ($this->instance_url ?: 'VAZIA'));
            error_log("eDeploySnowClient API DEBUG: Username: " . ($this->username ?: 'VAZIO'));
            error_log("eDeploySnowClient API DEBUG: Password carregada: " . (empty($this->password) ? 'NÃO' : 'SIM (' . strlen($this->password) . ' chars)'));
        }
    }

    /**
     * Definir modo de debug
     */
    public function setDebugMode($enabled)
    {
        $this->debug_mode = $enabled;
    }

    /**
     * Método público para teste - permite acesso ao makeRequest
     */
    public function testRequest($endpoint, $method = 'GET', $data = null)
    {
        return $this->makeRequest($endpoint, $method, $data);
    }

    /**
     * Fazer uma requisição HTTP para a API do ServiceNow
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = rtrim($this->instance_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Log temporário para debug de autenticação
        if ($this->debug_mode) {
            error_log("eDeploySnowClient API DEBUG: URL: " . $url);
            error_log("eDeploySnowClient API DEBUG: Username: " . $this->username);
            error_log("eDeploySnowClient API DEBUG: Password length: " . strlen($this->password));
            error_log("eDeploySnowClient API DEBUG: Password empty: " . (empty($this->password) ? 'YES' : 'NO'));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->debug_mode) {
            Toolbox::logDebug("ServiceNow API Request: $method $url");
            Toolbox::logDebug("ServiceNow API Username: " . $this->username);
            Toolbox::logDebug("ServiceNow API Password Length: " . strlen($this->password));
            if ($data) {
                Toolbox::logDebug("Request Data: " . json_encode($data));
            }
            Toolbox::logDebug("Response Code: $http_code");
            Toolbox::logDebug("Response: " . substr($response, 0, 500));
        }

        // Log apenas erro de autenticação (sem dados sensíveis)
        if ($http_code === 401) {
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: ERRO 401 - Credenciais inválidas no ServiceNow\n", FILE_APPEND | LOCK_EX);
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: URL: $url\n", FILE_APPEND | LOCK_EX);
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: Verificar credenciais na configuração\n", FILE_APPEND | LOCK_EX);
        } elseif ($http_code >= 400) {
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: ERRO HTTP $http_code\n", FILE_APPEND | LOCK_EX);
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: URL: $url\n", FILE_APPEND | LOCK_EX);
            file_put_contents('/var/www/html/files/_log/php-errors.log', "[" . date('Y-m-d H:i:s') . "] SnowClient DEBUG: Response: " . substr($response, 0, 500) . "\n", FILE_APPEND | LOCK_EX);
        }

        if ($error) {
            error_log("eDeploySnowClient RETURN: ERRO cURL - $error");
            throw new Exception("cURL Error: $error");
        }

        if ($http_code >= 400) {
            throw new Exception("HTTP Error $http_code: $response");
        }

        return json_decode($response, true);
    }

    /**
     * Fazer uma requisição HTTP específica para upload de anexos
     */
    private function makeAttachmentRequest($endpoint, $method = 'POST', $data = null)
    {
        $url = rtrim($this->instance_url, '/') . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout maior para uploads

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->debug_mode) {
            Toolbox::logDebug("ServiceNow Attachment API Request: $method $url");
            if ($data) {
                // Log sem o conteúdo base64 para evitar logs enormes
                $log_data = $data;
                if (isset($log_data['content'])) {
                    $log_data['content'] = '[BASE64_CONTENT_' . strlen($log_data['content']) . '_BYTES]';
                }
                Toolbox::logDebug("Request Data: " . json_encode($log_data));
            }
            Toolbox::logDebug("Response Code: $http_code");
            Toolbox::logDebug("Response: " . substr($response, 0, 500));
        }

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        if ($http_code >= 400) {
            throw new Exception("HTTP Error $http_code: $response");
        }

        return json_decode($response, true);
    }

    /**
     * Testar conexão com ServiceNow
     */
    public function testConnection()
    {
        try {
            $result = $this->makeRequest('api/now/table/incident?sysparm_limit=1');
            
            if (isset($result['result']) && is_array($result['result'])) {
                return [
                    'success' => true,
                    'message' => sprintf(__('Conexão bem-sucedida! Encontrados %d registros.', 'edeploysnowclient'), count($result['result']))
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Resposta inesperada da API ServiceNow', 'edeploysnowclient')
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Erro na conexão: %s', 'edeploysnowclient'), $e->getMessage())
            ];
        }
    }

    /**
     * Buscar incidentes do ServiceNow
     */
    public function getIncidents($filters = [])
    {
        $endpoint = 'api/now/table/incident';
        $params = [];
        
        if (!empty($filters)) {
            if (isset($filters['limit'])) {
                $params['sysparm_limit'] = $filters['limit'];
            }
            if (isset($filters['offset'])) {
                $params['sysparm_offset'] = $filters['offset'];
            }
            if (isset($filters['query'])) {
                $params['sysparm_query'] = $filters['query'];
            }
        }
        
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        try {
            $result = $this->makeRequest($endpoint);
            return $result['result'] ?? [];
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("Erro ao buscar incidentes: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Criar um incidente no ServiceNow
     */
    public function createIncident($data)
    {
        try {
            $result = $this->makeRequest('api/now/table/incident', 'POST', $data);
            return $result['result'] ?? null;
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("Erro ao criar incidente: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Atualizar um incidente no ServiceNow
     */
    public function updateIncident($ticket)
    {
        // Extrair ID do ServiceNow do título do ticket
        $snowId = PluginEdeploysnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Ticket {$ticket->fields['id']} não tem ID ServiceNow no título");
            }
            return false;
        }
        
        // Remover # do ID se presente
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar o sys_id do incidente usando o número
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                // Log como DEBUG - pode ser ticket criado manualmente no GLPI
                if ($this->debug_mode) {
                    Toolbox::logDebug("eDeploySnowClient: Incidente $cleanSnowId não encontrado no ServiceNow (pode ser ticket local do GLPI)");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // VERIFICAÇÃO: Confirmar se este ticket GLPI pode atualizar este incidente ServiceNow
            if (!$this->canUpdateIncident($ticket, $sysId)) {
                if ($this->debug_mode) {
                    Toolbox::logError("eDeploySnowClient: Ticket GLPI {$ticket->fields['id']} não autorizado a atualizar incidente $sysId devido ao correlation_id");
                }
                return false;
            }
            
            // Preparar dados de atualização
            $updateData = [
                'work_notes' => sprintf(__('Ticket atualizado no GLPI por %s em %s', 'edeploysnowclient'), 
                    getUserName(Session::getLoginUserID()), 
                    date('Y-m-d H:i:s'))
            ];
            
            // Mapear status do GLPI para ServiceNow
            if ($this->config->fields['sync_status']) {
                $updateData['state'] = $this->mapGlpiStatusToServiceNow($ticket->fields['status']);
            }
            
            // Mapear prioridade (ServiceNow usa 1-5, GLPI usa 1-6)
            if (isset($ticket->fields['priority'])) {
                $updateData['priority'] = $this->mapGlpiPriorityToServiceNow($ticket->fields['priority']);
            }
            
            // Mapear urgência (ServiceNow usa 1-3, GLPI usa 1-5)
            if (isset($ticket->fields['urgency'])) {
                $updateData['urgency'] = $this->mapGlpiUrgencyToServiceNow($ticket->fields['urgency']);
            }
            
            // Mapear impacto (ServiceNow usa 1-3, GLPI usa 1-5)
            if (isset($ticket->fields['impact'])) {
                $updateData['impact'] = $this->mapGlpiImpactToServiceNow($ticket->fields['impact']);
            }
            
            // Sincronizar título se mudou
            if (isset($ticket->fields['name']) && !empty($ticket->fields['name'])) {
                // Não sobrescrever se o título contém o ID do ServiceNow
                if (!preg_match('/^#?(INC|REQ|CHG|PRB)\d{7}/', $ticket->fields['name'])) {
                    $updateData['short_description'] = $ticket->fields['name'];
                }
            }
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Atualizando incidente $cleanSnowId com dados: " . json_encode($updateData));
            }
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $updateData);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Incidente $cleanSnowId atualizado com sucesso");
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Erro ao atualizar incidente $cleanSnowId: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Adicionar nota de trabalho a um incidente
     */
    public function addWorkNote($followup)
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($followup->fields['items_id'])) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Ticket {$followup->fields['items_id']} não encontrado para followup");
            }
            return false;
        }
        
        // Extrair ID do ServiceNow
        $snowId = PluginEdeploysnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Ticket {$ticket->fields['id']} não tem ID ServiceNow no título");
            }
            return false;
        }
        
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar sys_id do incidente
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                // Log como DEBUG ao invés de ERROR - pode ser ticket criado manualmente no GLPI
                if ($this->debug_mode) {
                    Toolbox::logDebug("eDeploySnowClient: Incidente $cleanSnowId não encontrado no ServiceNow para adicionar followup (pode ser ticket local do GLPI)");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // VERIFICAÇÃO: Confirmar se este ticket GLPI pode atualizar este incidente ServiceNow
            if (!$this->canUpdateIncident($ticket, $sysId)) {
                if ($this->debug_mode) {
                    Toolbox::logError("eDeploySnowClient: Ticket GLPI {$ticket->fields['id']} não autorizado a adicionar followup ao incidente $sysId devido ao correlation_id");
                }
                return false;
            }
            
            // Preparar nota de trabalho
            $userName = getUserName($followup->fields['users_id']);
            $timestamp = date('Y-m-d H:i:s');
            
            // Limpar conteúdo HTML de forma robusta - HOTFIX
            $content = $this->cleanHtmlContent($followup->fields['content']);
            
            // Validação dupla para garantir que tags HTML foram removidas
            if (preg_match('/<[^>]+>/', $content) || preg_match('/style\s*=/', $content)) {
                // Se ainda houver tags HTML, fazer limpeza manual adicional
                $content = strip_tags($content);
                $content = preg_replace('/style\s*=\s*["\'][^"\']*["\']/', '', $content);
                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = trim(preg_replace('/\s+/', ' ', $content));
            }
            
            if (empty($content)) {
                $content = 'Atualização sem conteúdo de texto';
            }
            
            $workNote = sprintf(
                "[GLPI - %s em %s]\n%s", 
                $userName, 
                $timestamp,
                $content
            );
            
            $updateData = [
                'work_notes' => $workNote
            ];
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Adicionando work note ao incidente $cleanSnowId");
            }
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $updateData);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Work note adicionada com sucesso ao incidente $cleanSnowId");
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Erro ao adicionar nota de trabalho ao incidente $cleanSnowId: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Adicionar solução como work note no ServiceNow com campos adicionais
     */
    public function addSolution($solution, $additionalData = [])
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($solution->fields['items_id'])) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Ticket {$solution->fields['items_id']} não encontrado para solução");
            }
            return false;
        }
        
        // Extrair ID do ServiceNow
        $snowId = PluginEdeploysnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Ticket {$ticket->fields['id']} não tem ID ServiceNow no título");
            }
            return false;
        }
        
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar sys_id do incidente
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                // Log como DEBUG - pode ser ticket criado manualmente no GLPI
                if ($this->debug_mode) {
                    Toolbox::logDebug("eDeploySnowClient: Incidente $cleanSnowId não encontrado no ServiceNow para adicionar solução (pode ser ticket local do GLPI)");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // VERIFICAÇÃO: Confirmar se este ticket GLPI pode atualizar este incidente ServiceNow
            if (!$this->canUpdateIncident($ticket, $sysId)) {
                if ($this->debug_mode) {
                    Toolbox::logError("eDeploySnowClient: Ticket GLPI {$ticket->fields['id']} não autorizado a adicionar solução ao incidente $sysId devido ao correlation_id");
                }
                return false;
            }
            
            // Preparar dados da solução
            $userName = getUserName($solution->fields['users_id']);
            $timestamp = date('Y-m-d H:i:s');
            
            // Limpar conteúdo HTML de forma robusta
            $content = $this->cleanHtmlContent($solution->fields['content']);
            
            // Validação dupla para garantir que tags HTML foram removidas
            if (preg_match('/<[^>]+>/', $content) || preg_match('/style\s*=/', $content)) {
                $content = strip_tags($content);
                $content = preg_replace('/style\s*=\s*["\'][^"\']*["\']/', '', $content);
                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = trim(preg_replace('/\s+/', ' ', $content));
            }
            
            if (empty($content)) {
                $content = 'Solução sem conteúdo de texto';
            }
            
            // Obter informações do tipo de solução se disponível
            $solutionTypeInfo = '';
            if (!empty($solution->fields['solutiontypes_id'])) {
                $solutionType = new SolutionType();
                if ($solutionType->getFromDB($solution->fields['solutiontypes_id'])) {
                    $solutionTypeInfo = "\nTipo de Solução: " . $solutionType->fields['name'];
                }
            }
            
            $solutionNote = sprintf(
                "[GLPI - SOLUÇÃO por %s em %s]%s\n\n%s", 
                $userName, 
                $timestamp,
                $solutionTypeInfo,
                $content
            );
            
            // ETAPA 1: Resolver o incidente
            $resolveData = [
                'close_notes' => $solutionNote,
                'state' => 6, // Resolved
                'resolved_by' => $userName,
                'resolved_at' => $timestamp
            ];
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: ETAPA 1 - Resolvendo incidente $cleanSnowId");
                Toolbox::logDebug("eDeploySnowClient: Dados de resolução: " . json_encode($resolveData));
            }
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $resolveData);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: ETAPA 1 - Incidente resolvido com sucesso");
            }
            
            // Aguardar processamento
            sleep(1);
            
            // ETAPA 2: Enviar dados customizados do eDeploy ServiceNow
            // Pegar dados da sessão (salvos pela modal de solução)
            $sessionData = $_SESSION['edeploysnowclient_solution_data'] ?? [];
            
            $customFieldsData = [
                'close_code' => $sessionData['close_code'] ?? 'Definitiva',
                'u_bk_tipo_encerramento' => $sessionData['u_bk_tipo_encerramento'] ?? 'Remoto',
                'u_bk_ic_impactado' => $sessionData['u_bk_ic_impactado'] ?? 'Aplicação (Software)',
                'u_bk_type_of_failure' => $sessionData['u_bk_type_of_failure'] ?? ''
            ];
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: ETAPA 2 - Enviando dados customizados do eDeploy");
                Toolbox::logDebug("eDeploySnowClient: Session data: " . json_encode($sessionData));
                Toolbox::logDebug("eDeploySnowClient: Dados a enviar: " . json_encode($customFieldsData));
            }
            
            try {
                $customResult = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $customFieldsData);
                
                if ($this->debug_mode) {
                    Toolbox::logDebug("eDeploySnowClient: ETAPA 2 - Dados customizados enviados com sucesso");
                }
                
                // Limpar dados da sessão após uso
                unset($_SESSION['edeploysnowclient_solution_data']);
                
            } catch (Exception $e) {
                if ($this->debug_mode) {
                    Toolbox::logError("eDeploySnowClient: ETAPA 2 - Erro ao enviar dados customizados (não crítico): " . $e->getMessage());
                }
            }
            
            // Aguardar processamento
            sleep(1);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("eDeploySnowClient: Solução aplicada com sucesso ao incidente $cleanSnowId");
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Erro ao adicionar solução ao incidente $cleanSnowId: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Deletar um incidente no ServiceNow (marcar como cancelado)
     */
    public function deleteIncident($ticket)
    {
        // Extrair ID do ServiceNow do título do ticket
        $snowId = PluginEdeploysnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            return false;
        }
        
        // Remover # do ID se presente
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar o sys_id do incidente usando o número
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                // Log como DEBUG - pode ser ticket criado manualmente no GLPI
                if ($this->debug_mode) {
                    Toolbox::logDebug("eDeploySnowClient: Incidente $cleanSnowId não encontrado no ServiceNow para exclusão (pode ser ticket local do GLPI)");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // Cancelar incidente (state = 8 = Canceled)
            $updateData = [
                'state' => 8,
                'work_notes' => sprintf(__('Ticket cancelado no GLPI por %s', 'edeploysnowclient'), 
                    getUserName(Session::getLoginUserID()))
            ];
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $updateData);
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("Erro ao cancelar incidente: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Anexar documento a um incidente no ServiceNow
     */
    public function attachDocument($document, $sys_id = null)
    {
        error_log("eDeploySnowClient: attachDocument iniciado - documento ID: {$document->fields['id']}, sys_id: $sys_id");
        
        if (!$sys_id) {
            error_log("eDeploySnowClient: Erro - sys_id não fornecido");
            return false;
        }

        error_log("eDeploySnowClient: Iniciando envio de anexo - documento: {$document->fields['name']}");
        error_log("eDeploySnowClient: Filepath original: {$document->fields['filepath']}");

        // Usar caminho correto do GLPI para documentos
        $filepath = GLPI_ROOT . '/../files/' . $document->fields['filepath'];
        
        error_log("eDeploySnowClient: Caminho construído: $filepath");
        
        if (!file_exists($filepath)) {
            error_log("eDeploySnowClient: Arquivo não encontrado: $filepath");
            return false;
        }

        error_log("eDeploySnowClient: Arquivo encontrado: $filepath (tamanho: " . filesize($filepath) . " bytes)");

        try {
            // Passo 1: Upload do arquivo via ServiceNow Attachment API
            $mimeType = $document->fields['mime'] ?? 'application/octet-stream';
            error_log("eDeploySnowClient: Iniciando upload - mime: $mimeType");
            
            $attachmentSysId = $this->uploadFileToServiceNow($filepath, $document->fields['name'], $mimeType, $sys_id);
            
            if (!$attachmentSysId) {
                error_log("eDeploySnowClient: Falha no upload do arquivo");
                return false;
            }

            error_log("eDeploySnowClient: Upload bem-sucedido, attachment sys_id: $attachmentSysId");
            return true;

        } catch (Exception $e) {
            error_log("eDeploySnowClient: Exceção ao enviar anexo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload de arquivo para ServiceNow usando a API correta
     */
    private function uploadFileToServiceNow($filepath, $filename, $mimeType = null, $incidentSysId = null)
    {
        error_log("eDeploySnowClient: Iniciando upload do arquivo: $filename");
        
        // Verificar se arquivo existe
        if (!file_exists($filepath)) {
            error_log("eDeploySnowClient: Arquivo não encontrado: $filepath");
            return false;
        }

        // Ler conteúdo do arquivo
        $fileContent = file_get_contents($filepath);
        if ($fileContent === false) {
            error_log("eDeploySnowClient: Erro ao ler arquivo: $filepath");
            return false;
        }

        // Usar query parameters conforme documentação ServiceNow
        $params = [
            'table_name' => 'incident',
            'file_name' => $filename
        ];
        
        // Incluir table_sys_id se fornecido
        if ($incidentSysId) {
            $params['table_sys_id'] = $incidentSysId;
        }
        
        $url = rtrim($this->instance_url, '/') . '/api/now/attachment/file?' . http_build_query($params);
        
        error_log("eDeploySnowClient: Fazendo upload para URL: $url");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . ($mimeType ?? 'application/octet-stream'),
            'Accept: application/json'
        ]);
        
        // Enviar conteúdo binário diretamente no body
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);

        error_log("eDeploySnowClient: Enviando request de upload... (tamanho: " . strlen($fileContent) . " bytes)");
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        error_log("eDeploySnowClient: Upload response code: $http_code");
        error_log("eDeploySnowClient: Upload response: " . substr($response, 0, 1000));

        if ($error) {
            error_log("eDeploySnowClient: cURL Error no upload: $error");
            return false;
        }

        if ($http_code >= 400) {
            error_log("eDeploySnowClient: HTTP Error $http_code no upload: $response");
            return false;
        }

        $result = json_decode($response, true);
        
        error_log("eDeploySnowClient: Upload result parsed: " . json_encode($result));
        
        if (isset($result['result']) && isset($result['result']['sys_id'])) {
            error_log("eDeploySnowClient: Upload bem-sucedido, sys_id: " . $result['result']['sys_id']);
            return $result['result']['sys_id'];
        } else {
            error_log("eDeploySnowClient: Upload falhou - resposta inválida");
            return false;
        }
    }

    /**
     * Verificar se o ticket GLPI pode atualizar o incidente ServiceNow
     * Compara o correlation_id do ServiceNow com o ID do ticket GLPI
     */
    public function canUpdateIncident($ticket, $sysId)
    {
        try {
            // Buscar correlation_id do incidente no ServiceNow
            $searchResult = $this->makeRequest("api/now/table/incident/$sysId?sysparm_fields=correlation_id");
            
            if (isset($searchResult['result']['correlation_id'])) {
                $correlationId = $searchResult['result']['correlation_id'];
                
                // Verificar se o correlation_id bate com o ID do ticket GLPI
                if ($correlationId == $ticket->fields['id']) {
                    if ($this->debug_mode) {
                        Toolbox::logDebug("eDeploySnowClient: Ticket GLPI {$ticket->fields['id']} autorizado a atualizar incidente $sysId (correlation_id: $correlationId)");
                    }
                    return true;
                } else {
                    if ($this->debug_mode) {
                        Toolbox::logDebug("eDeploySnowClient: Ticket GLPI {$ticket->fields['id']} NÃO autorizado a atualizar incidente $sysId (correlation_id no ServiceNow: '$correlationId')");
                    }
                    return false;
                }
            } else {
                // Se não houver correlation_id, verificar o contexto da chamada
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
                $callingMethod = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : '';
                
                // Para devolução de tickets, correlation_id é obrigatório
                if ($callingMethod === 'returnTicketToQueue') {
                    if ($this->debug_mode) {
                        Toolbox::logDebug("eDeploySnowClient: Incidente $sysId não possui correlation_id. Negando devolução pois correlation_id é obrigatório para esta operação.");
                    }
                    return false;
                } else {
                    // Para outras operações, permitir atualização (compatibilidade com incidentes antigos)
                    if ($this->debug_mode) {
                        Toolbox::logDebug("eDeploySnowClient: Incidente $sysId não possui correlation_id. Permitindo atualização para compatibilidade.");
                    }
                    return true;
                }
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("eDeploySnowClient: Erro ao verificar correlation_id para incidente $sysId: " . $e->getMessage());
            }
            // Em caso de erro, permitir atualização para não quebrar funcionalidade existente
            return true;
        }
    }

    /**
     * Obter sys_id real do ServiceNow a partir do número do incidente
     */
    public function getSysIdFromIncidentNumber($incident_number)
    {
        try {
            $cleanNumber = ltrim($incident_number, '#');
            $searchResult = $this->makeRequest("api/now/table/incident?number=$cleanNumber&sysparm_fields=sys_id&sysparm_display_value=all");
            
            if (isset($searchResult['result']) && count($searchResult['result']) > 0) {
                // A API retorna sys_id como objeto com display_value e value
                $sysIdData = $searchResult['result'][0]['sys_id'];
                
                if (is_array($sysIdData) && isset($sysIdData['value'])) {
                    return $sysIdData['value'];
                } else if (is_string($sysIdData)) {
                    return $sysIdData;
                }
            }
            
            return null;
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logDebug("Erro ao buscar sys_id para incidente $incident_number: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Mapear status do GLPI para ServiceNow
     */
    private function mapGlpiStatusToServiceNow($glpiStatus)
    {
        $mapping = [
            Ticket::INCOMING => 1,    // New
            Ticket::ASSIGNED => 2,    // In Progress
            Ticket::PLANNED => 2,     // In Progress
            Ticket::WAITING => 3,     // On Hold
            Ticket::SOLVED => 6,      // Resolved
            Ticket::CLOSED => 7       // Closed
        ];
        
        return $mapping[$glpiStatus] ?? 1; // Default: New
    }

    /**
     * Mapear prioridade do GLPI para ServiceNow (eDeploy)
     * GLPI: 2=Baixa, 3=Média, 4=Alta, 5=Muito alta
     * ServiceNow: 1=Crítico, 2=Alta, 3=Média, 4=Baixa
     */
    private function mapGlpiPriorityToServiceNow($glpiPriority)
    {
        $mapping = [
            1 => 4,    // Muito baixa -> Baixa (4)
            2 => 4,    // Baixa -> Baixa (4)
            3 => 3,    // Média -> Média (3)
            4 => 2,    // Alta -> Alta (2)
            5 => 1,    // Muito alta -> Crítico (1)
            6 => 1     // Crítica -> Crítico (1)
        ];
        
        return $mapping[$glpiPriority] ?? 3; // Default: Média
    }

    /**
     * Mapear urgência do GLPI para ServiceNow (eDeploy)
     * GLPI: 2=Baixa, 3=Média, 4=Alta, 5=Muito alta
     * ServiceNow: 1=Crítico, 2=Alta, 3=Média, 4=Baixa
     */
    private function mapGlpiUrgencyToServiceNow($glpiUrgency)
    {
        $mapping = [
            1 => 4,    // Muito baixa -> Baixa (4)
            2 => 4,    // Baixa -> Baixa (4)
            3 => 3,    // Média -> Média (3)
            4 => 2,    // Alta -> Alta (2)
            5 => 1     // Muito alta -> Crítico (1)
        ];
        
        return $mapping[$glpiUrgency] ?? 3; // Default: Média
    }

    /**
     * Mapear impacto do GLPI para ServiceNow (eDeploy)
     * GLPI: 2=Baixo, 3=Médio, 4=Alto, 5=Muito alto
     * ServiceNow: 1=Crítico, 2=Alto, 3=Médio, 4=Baixo
     */
    private function mapGlpiImpactToServiceNow($glpiImpact)
    {
        $mapping = [
            1 => 4,    // Muito baixo -> Baixo (4)
            2 => 4,    // Baixo -> Baixo (4)
            3 => 3,    // Médio -> Médio (3)
            4 => 2,    // Alto -> Alto (2)
            5 => 1     // Muito alto -> Crítico (1)
        ];
        
        return $mapping[$glpiImpact] ?? 3; // Default: Média
    }

    /**
     * Limpar conteúdo HTML de forma robusta
     * Remove tags HTML, estilos inline, entidades HTML e espaços extras
     */
    private function cleanHtmlContent($htmlContent)
    {
        if (empty($htmlContent)) {
            return '';
        }

        // 1. Converter quebras de linha HTML para texto
        $content = str_replace(['<br>', '<br/>', '<br />', '<p>', '</p>', '<div>', '</div>'], "\n", $htmlContent);
        
        // 2. Remover todas as tags HTML
        $content = strip_tags($content);
        
        // 3. Decodificar entidades HTML
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 4. Remover caracteres de controle e espaços desnecessários
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
        
        // 5. Normalizar espaços em branco
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 6. Normalizar quebras de linha múltiplas
        $content = preg_replace('/\n\s*\n/', "\n\n", $content);
        
        // 7. Remover espaços no início e fim
        $content = trim($content);
        
        // 8. Limitar o tamanho se necessário (ServiceNow tem limites)
        if (strlen($content) > 4000) {
            $content = substr($content, 0, 3997) . '...';
        }
        
        return $content;
    }

    /**
     * Mapear tipos de encerramento do GLPI para códigos do ServiceNow
     */
    private function mapGlpiClosureTypeToServiceNow($glpiClosureType)
    {
        $mapping = [
            'Resolved' => 'Solved (Permanently)',
            'Solução de Contorno' => 'Solved (Work Around)',
            'Não Resolvido' => 'Not Solved',
            'Duplicado' => 'Closed/Resolved by Caller',
            'Cancelado' => 'Cancelled',
            'Atualizado' => 'Closed/Resolved by Caller',
            'Resolvido por Erro' => 'Solved (Work Around)',
            'Resolvido por Mudança' => 'Solved (Permanently)',
            'Resolvido por Patch' => 'Solved (Permanently)',
            'Resolvido por Projeto' => 'Solved (Permanently)',
            'Resolvido por Melhoria' => 'Solved (Permanently)',
            'Resolvido por Processo' => 'Solved (Permanently)',
            'Resolvido por Reset/Reboot' => 'Solved (Work Around)',
            'Resolvido por Backup' => 'Solved (Work Around)',
        ];
        
        return $mapping[$glpiClosureType] ?? 'Solved (Permanently)'; // Default se não encontrar mapeamento
    }

    /**
     * Devolve um ticket para uma fila específica no ServiceNow sem resolver
     */
    public function returnTicketToQueue($ticket, $reason)
    {
        try {
            // Log inicial
            error_log("eDeploySnowClient RETURN: Iniciando devolução do ticket GLPI {$ticket->getID()}");
            
            // Buscar o sys_id do ticket no ServiceNow
            $sysId = PluginEdeploysnowclientConfig::getSnowSysIdFromTicket($ticket->getID());
            
            if (!$sysId) {
                error_log("eDeploySnowClient RETURN: ERRO - Não foi possível encontrar sys_id para ticket GLPI {$ticket->getID()}");
                return false;
            }
            
            error_log("eDeploySnowClient RETURN: sys_id encontrado: $sysId para ticket GLPI {$ticket->getID()}");

            // Verificar se o ticket pode atualizar o incidente (validação de correlation_id)
            if (!$this->canUpdateIncident($ticket, $sysId)) {
                error_log("eDeploySnowClient RETURN: ERRO - Correlação não encontrada para ticket GLPI {$ticket->getID()} / ServiceNow sys_id: $sysId");
                return false;
            }
            
            error_log("eDeploySnowClient RETURN: Validação de correlação passou para ticket GLPI {$ticket->getID()}");

            // Preparar dados para atualização
            $updateData = [];
            
            // Adicionar work note explicando a devolução
            $workNote = "CHAMADO DEVOLVIDO DO GLPI\n\n" .
                       "Motivo: " . $reason . "\n\n" .
                       "Este chamado foi devolvido pelo GLPI para tratamento pela equipe do ServiceNow.\n\n" .
                       "Ticket GLPI: " . $ticket->getID() . "\n" .
                       "Usuário: " . getUserName(Session::getLoginUserID()) . "\n" .
                       "Data: " . date('d/m/Y H:i:s');
            
            $updateData['work_notes'] = $workNote;
            
            // Usar fila configurada no plugin
            $targetQueue = $this->config->fields['return_queue_group'];
            error_log("eDeploySnowClient RETURN: Fila configurada: " . ($targetQueue ? $targetQueue : '(não configurada)'));
            
            // Se foi configurada uma fila, definir o assignment_group
            if (!empty($targetQueue)) {
                // Se é um sys_id (32 caracteres), usar diretamente
                if (strlen($targetQueue) == 32) {
                    $updateData['assignment_group'] = $targetQueue;
                    error_log("eDeploySnowClient RETURN: Usando sys_id configurado: $targetQueue");
                } else {
                    // Tentar buscar por nome
                    $groupSysId = $this->findAssignmentGroupByName($targetQueue);
                    if ($groupSysId) {
                        $updateData['assignment_group'] = $groupSysId;
                        error_log("eDeploySnowClient RETURN: Grupo encontrado: $targetQueue -> $groupSysId");
                    } else {
                        // Se não encontrou o grupo, adicionar na work note
                        $updateData['work_notes'] .= "\n\nNota: Fila solicitada '$targetQueue' não foi encontrada automaticamente.";
                        error_log("eDeploySnowClient RETURN: AVISO - Grupo '$targetQueue' não encontrado");
                    }
                }
            } else {
                error_log("eDeploySnowClient RETURN: AVISO - Nenhuma fila de devolução configurada");
                $updateData['work_notes'] .= "\n\nNota: Nenhuma fila específica configurada para devolução.";
            }
            
            // NÃO alterar status no ServiceNow - apenas mudar fila
            // Limpar assigned_to para forçar redistribuição
            $updateData['assigned_to'] = '';
            
            error_log("eDeploySnowClient RETURN: Dados para envio: " . json_encode($updateData));
            
            // Fazer a requisição de atualização
            $endpoint = "/api/now/table/incident/$sysId";
            error_log("eDeploySnowClient RETURN: Fazendo requisição PATCH para: " . $this->instance_url . $endpoint);
            
            $response = $this->makeRequest($endpoint, 'PATCH', $updateData);
            
            if ($response && isset($response['result'])) {
                error_log("eDeploySnowClient RETURN: SUCESSO - Ticket devolvido com sucesso - sys_id: $sysId");
                return true;
            } else {
                error_log("eDeploySnowClient RETURN: ERRO - Resposta da API: " . json_encode($response));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("eDeploySnowClient RETURN: EXCEÇÃO - " . $e->getMessage());
            error_log("eDeploySnowClient RETURN: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Busca um grupo de atribuição pelo nome
     */
    private function findAssignmentGroupByName($groupName)
    {
        try {
            $endpoint = "/api/now/table/sys_user_group?sysparm_query=name=" . urlencode($groupName) . "&sysparm_fields=sys_id,name&sysparm_limit=1";
            $response = $this->makeRequest($endpoint, 'GET');
            
            if ($response && isset($response['result']) && count($response['result']) > 0) {
                return $response['result'][0]['sys_id'];
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("eDeploySnowClient: Erro ao buscar grupo '$groupName': " . $e->getMessage());
            }
            return null;
        }
    }
}
