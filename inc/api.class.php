<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/engemon/snowclient
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
   @link      https://github.com/engemon/snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginSnowclientApi
 */
class PluginSnowclientApi
{
    private $config;
    private $instance_url;
    private $username;
    private $password;
    private $debug_mode;

    public function __construct()
    {
        $this->config = PluginSnowclientConfig::getInstance();
        $this->instance_url = $this->config->fields['instance_url'];
        $this->username = $this->config->fields['username'];
        
        // Usar método correto para descriptografar senha
        $this->password = $this->config->getDecryptedPassword();
        
        $this->debug_mode = $this->config->fields['debug_mode'];
        
        // Log de debug para verificar se credenciais foram carregadas
        if ($this->debug_mode) {
            error_log("SnowClient DEBUG API: Inicializando API");
            error_log("SnowClient DEBUG API: URL: " . $this->instance_url);
            error_log("SnowClient DEBUG API: Username: " . $this->username);
            error_log("SnowClient DEBUG API: Password carregada: " . (empty($this->password) ? 'NÃO' : 'SIM (' . strlen($this->password) . ' chars)'));
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
            if ($data) {
                Toolbox::logDebug("Request Data: " . json_encode($data));
            }
            Toolbox::logDebug("Response Code: $http_code");
            Toolbox::logDebug("Response: " . substr($response, 0, 500));
        }

        // Log sempre erros de autenticação e outros problemas críticos
        if ($http_code === 401) {
            error_log("SnowClient RETURN: ERRO 401 - Falha de autenticação no ServiceNow. Verifique credenciais.");
            error_log("SnowClient RETURN: URL tentativa: $url");
            error_log("SnowClient RETURN: Username: " . $this->username);
        } elseif ($http_code >= 400) {
            error_log("SnowClient RETURN: ERRO HTTP $http_code - $response");
            error_log("SnowClient RETURN: URL: $url");
        }

        if ($error) {
            error_log("SnowClient RETURN: ERRO cURL - $error");
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
                    'message' => sprintf(__('Conexão bem-sucedida! Encontrados %d registros.', 'snowclient'), count($result['result']))
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Resposta inesperada da API ServiceNow', 'snowclient')
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Erro na conexão: %s', 'snowclient'), $e->getMessage())
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
        $snowId = PluginSnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            if ($this->debug_mode) {
                Toolbox::logDebug("SnowClient: Ticket {$ticket->fields['id']} não tem ID ServiceNow no título");
            }
            return false;
        }
        
        // Remover # do ID se presente
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar o sys_id do incidente usando o número
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                if ($this->debug_mode) {
                    Toolbox::logError("SnowClient: Incidente $cleanSnowId não encontrado no ServiceNow");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // Preparar dados de atualização
            $updateData = [
                'work_notes' => sprintf(__('Ticket atualizado no GLPI por %s em %s', 'snowclient'), 
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
                Toolbox::logDebug("SnowClient: Atualizando incidente $cleanSnowId com dados: " . json_encode($updateData));
            }
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $updateData);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("SnowClient: Incidente $cleanSnowId atualizado com sucesso");
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("SnowClient: Erro ao atualizar incidente $cleanSnowId: " . $e->getMessage());
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
                Toolbox::logError("SnowClient: Ticket {$followup->fields['items_id']} não encontrado para followup");
            }
            return false;
        }
        
        // Extrair ID do ServiceNow
        $snowId = PluginSnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            if ($this->debug_mode) {
                Toolbox::logDebug("SnowClient: Ticket {$ticket->fields['id']} não tem ID ServiceNow no título");
            }
            return false;
        }
        
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar sys_id do incidente
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                if ($this->debug_mode) {
                    Toolbox::logError("SnowClient: Incidente $cleanSnowId não encontrado no ServiceNow para followup");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
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
                Toolbox::logDebug("SnowClient: Adicionando work note ao incidente $cleanSnowId");
            }
            
            $result = $this->makeRequest("api/now/table/incident/$sysId", 'PATCH', $updateData);
            
            if ($this->debug_mode) {
                Toolbox::logDebug("SnowClient: Work note adicionada com sucesso ao incidente $cleanSnowId");
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            if ($this->debug_mode) {
                Toolbox::logError("SnowClient: Erro ao adicionar nota de trabalho ao incidente $cleanSnowId: " . $e->getMessage());
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
        $snowId = PluginSnowclientConfig::extractServiceNowId($ticket);
        if (!$snowId) {
            return false;
        }
        
        // Remover # do ID se presente
        $cleanSnowId = ltrim($snowId, '#');
        
        try {
            // Buscar o sys_id do incidente usando o número
            $searchResult = $this->makeRequest("api/now/table/incident?sysparm_query=number=$cleanSnowId&sysparm_fields=sys_id");
            
            if (empty($searchResult['result'])) {
                if ($this->debug_mode) {
                    Toolbox::logError("Incidente $cleanSnowId não encontrado no ServiceNow para exclusão");
                }
                return false;
            }
            
            $sysId = $searchResult['result'][0]['sys_id'];
            
            // Cancelar incidente (state = 8 = Canceled)
            $updateData = [
                'state' => 8,
                'work_notes' => sprintf(__('Ticket cancelado no GLPI por %s', 'snowclient'), 
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
        error_log("SnowClient: attachDocument iniciado - documento ID: {$document->fields['id']}, sys_id: $sys_id");
        
        if (!$sys_id) {
            error_log("SnowClient: Erro - sys_id não fornecido");
            return false;
        }

        error_log("SnowClient: Iniciando envio de anexo - documento: {$document->fields['name']}");
        error_log("SnowClient: Filepath original: {$document->fields['filepath']}");

        // Usar caminho correto do GLPI para documentos
        $filepath = GLPI_ROOT . '/../files/' . $document->fields['filepath'];
        
        error_log("SnowClient: Caminho construído: $filepath");
        
        if (!file_exists($filepath)) {
            error_log("SnowClient: Arquivo não encontrado: $filepath");
            return false;
        }

        error_log("SnowClient: Arquivo encontrado: $filepath (tamanho: " . filesize($filepath) . " bytes)");

        try {
            // Passo 1: Upload do arquivo via ServiceNow Attachment API
            $mimeType = $document->fields['mime'] ?? 'application/octet-stream';
            error_log("SnowClient: Iniciando upload - mime: $mimeType");
            
            $attachmentSysId = $this->uploadFileToServiceNow($filepath, $document->fields['name'], $mimeType, $sys_id);
            
            if (!$attachmentSysId) {
                error_log("SnowClient: Falha no upload do arquivo");
                return false;
            }

            error_log("SnowClient: Upload bem-sucedido, attachment sys_id: $attachmentSysId");
            return true;

        } catch (Exception $e) {
            error_log("SnowClient: Exceção ao enviar anexo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload de arquivo para ServiceNow usando a API correta
     */
    private function uploadFileToServiceNow($filepath, $filename, $mimeType = null, $incidentSysId = null)
    {
        error_log("SnowClient: Iniciando upload do arquivo: $filename");
        
        // Verificar se arquivo existe
        if (!file_exists($filepath)) {
            error_log("SnowClient: Arquivo não encontrado: $filepath");
            return false;
        }

        // Ler conteúdo do arquivo
        $fileContent = file_get_contents($filepath);
        if ($fileContent === false) {
            error_log("SnowClient: Erro ao ler arquivo: $filepath");
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
        
        error_log("SnowClient: Fazendo upload para URL: $url");
        
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

        error_log("SnowClient: Enviando request de upload... (tamanho: " . strlen($fileContent) . " bytes)");
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        error_log("SnowClient: Upload response code: $http_code");
        error_log("SnowClient: Upload response: " . substr($response, 0, 1000));

        if ($error) {
            error_log("SnowClient: cURL Error no upload: $error");
            return false;
        }

        if ($http_code >= 400) {
            error_log("SnowClient: HTTP Error $http_code no upload: $response");
            return false;
        }

        $result = json_decode($response, true);
        
        error_log("SnowClient: Upload result parsed: " . json_encode($result));
        
        if (isset($result['result']) && isset($result['result']['sys_id'])) {
            error_log("SnowClient: Upload bem-sucedido, sys_id: " . $result['result']['sys_id']);
            return $result['result']['sys_id'];
        } else {
            error_log("SnowClient: Upload falhou - resposta inválida");
            return false;
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
     * Mapear prioridade do GLPI para ServiceNow
     * GLPI: 1=Muito baixa, 2=Baixa, 3=Média, 4=Alta, 5=Muito alta, 6=Crítica
     * ServiceNow: 1=Crítico, 2=Alto, 3=Moderado, 4=Baixo
     */
    private function mapGlpiPriorityToServiceNow($glpiPriority)
    {
        $mapping = [
            1 => 4,    // Muito baixa -> Baixo
            2 => 4,    // Baixa -> Baixo
            3 => 3,    // Média -> Moderado
            4 => 2,    // Alta -> Alto
            5 => 1,    // Muito alta -> Crítico
            6 => 1     // Crítica -> Crítico
        ];
        
        return $mapping[$glpiPriority] ?? 3; // Default: Moderado
    }

    /**
     * Mapear urgência do GLPI para ServiceNow
     * GLPI: 1=Muito baixa, 2=Baixa, 3=Média, 4=Alta, 5=Muito alta
     * ServiceNow: 1=High, 2=Medium, 3=Low
     */
    private function mapGlpiUrgencyToServiceNow($glpiUrgency)
    {
        $mapping = [
            1 => 3,    // Muito baixa -> Low
            2 => 3,    // Baixa -> Low
            3 => 2,    // Média -> Medium
            4 => 1,    // Alta -> High
            5 => 1     // Muito alta -> High
        ];
        
        return $mapping[$glpiUrgency] ?? 2; // Default: Medium
    }

    /**
     * Mapear impacto do GLPI para ServiceNow
     * GLPI: 1=Muito baixo, 2=Baixo, 3=Médio, 4=Alto, 5=Muito alto
     * ServiceNow: 1=High, 2=Medium, 3=Low
     */
    private function mapGlpiImpactToServiceNow($glpiImpact)
    {
        $mapping = [
            1 => 3,    // Muito baixo -> Low
            2 => 3,    // Baixo -> Low
            3 => 2,    // Médio -> Medium
            4 => 1,    // Alto -> High
            5 => 1     // Muito alto -> High
        ];
        
        return $mapping[$glpiImpact] ?? 2; // Default: Medium
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
     * Devolve um ticket para uma fila específica no ServiceNow sem resolver
     */
    public function returnTicketToQueue($ticket, $reason)
    {
        try {
            // Log inicial
            error_log("SnowClient RETURN: Iniciando devolução do ticket GLPI {$ticket->getID()}");
            
            // Buscar o sys_id do ticket no ServiceNow
            $sysId = PluginSnowclientConfig::getSnowSysIdFromTicket($ticket->getID());
            
            if (!$sysId) {
                error_log("SnowClient RETURN: ERRO - Não foi possível encontrar sys_id para ticket GLPI {$ticket->getID()}");
                return false;
            }
            
            error_log("SnowClient RETURN: sys_id encontrado: $sysId para ticket GLPI {$ticket->getID()}");

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
            error_log("SnowClient RETURN: Fila configurada: " . ($targetQueue ? $targetQueue : '(não configurada)'));
            
            // Se foi configurada uma fila, definir o assignment_group
            if (!empty($targetQueue)) {
                // Se é um sys_id (32 caracteres), usar diretamente
                if (strlen($targetQueue) == 32) {
                    $updateData['assignment_group'] = $targetQueue;
                    error_log("SnowClient RETURN: Usando sys_id configurado: $targetQueue");
                } else {
                    // Tentar buscar por nome
                    $groupSysId = $this->findAssignmentGroupByName($targetQueue);
                    if ($groupSysId) {
                        $updateData['assignment_group'] = $groupSysId;
                        error_log("SnowClient RETURN: Grupo encontrado: $targetQueue -> $groupSysId");
                    } else {
                        // Se não encontrou o grupo, adicionar na work note
                        $updateData['work_notes'] .= "\n\nNota: Fila solicitada '$targetQueue' não foi encontrada automaticamente.";
                        error_log("SnowClient RETURN: AVISO - Grupo '$targetQueue' não encontrado");
                    }
                }
            } else {
                error_log("SnowClient RETURN: AVISO - Nenhuma fila de devolução configurada");
                $updateData['work_notes'] .= "\n\nNota: Nenhuma fila específica configurada para devolução.";
            }
            
            // Definir estado como "In Progress" para garantir que não está resolvido
            $updateData['state'] = '2'; // In Progress
            
            // Limpar assigned_to para forçar redistribuição
            $updateData['assigned_to'] = '';
            
            error_log("SnowClient RETURN: Dados para envio: " . json_encode($updateData));
            
            // Fazer a requisição de atualização
            $endpoint = "/api/now/table/incident/$sysId";
            error_log("SnowClient RETURN: Fazendo requisição PATCH para: " . $this->instance_url . $endpoint);
            
            $response = $this->makeRequest($endpoint, 'PATCH', $updateData);
            
            if ($response && isset($response['result'])) {
                error_log("SnowClient RETURN: SUCESSO - Ticket devolvido com sucesso - sys_id: $sysId");
                return true;
            } else {
                error_log("SnowClient RETURN: ERRO - Resposta da API: " . json_encode($response));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("SnowClient RETURN: EXCEÇÃO - " . $e->getMessage());
            error_log("SnowClient RETURN: Stack trace: " . $e->getTraceAsString());
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
                error_log("SnowClient: Erro ao buscar grupo '$groupName': " . $e->getMessage());
            }
            return null;
        }
    }
}
