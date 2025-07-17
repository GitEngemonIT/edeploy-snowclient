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
        
        // Descriptografar senha
        $encryptedPassword = $this->config->fields['password'];
        if (!empty($encryptedPassword)) {
            if (method_exists('Toolbox', 'sodiumDecrypt')) {
                $this->password = Toolbox::sodiumDecrypt($encryptedPassword);
            } else {
                $this->password = base64_decode($encryptedPassword);
            }
        }
        
        $this->debug_mode = $this->config->fields['debug_mode'];
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
            
            // Sincronizar descrição se mudou
            if (isset($ticket->fields['content']) && !empty($ticket->fields['content'])) {
                $updateData['description'] = strip_tags($ticket->fields['content']);
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
            
            // Determinar tipo de followup
            $followupType = '';
            if (isset($followup->fields['is_private']) && $followup->fields['is_private']) {
                $followupType = '[NOTA PRIVADA]';
            } else {
                $followupType = '[ATUALIZAÇÃO PÚBLICA]';
            }
            
            $workNote = sprintf(
                "%s [GLPI - %s em %s]\n%s", 
                $followupType,
                $userName, 
                $timestamp,
                strip_tags($followup->fields['content'])
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
    public function attachDocument($document)
    {
        // Esta é uma implementação placeholder - ServiceNow attachments requerem multipart/form-data
        if ($this->debug_mode) {
            Toolbox::logDebug("Anexo de documento não implementado ainda: " . $document->fields['name']);
        }
        
        // TODO: Implementar upload de anexos via ServiceNow Attachment API
        return false;
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
     * ServiceNow: 1=Critical, 2=High, 3=Moderate, 4=Low, 5=Planning
     */
    private function mapGlpiPriorityToServiceNow($glpiPriority)
    {
        $mapping = [
            1 => 5,    // Muito baixa -> Planning
            2 => 4,    // Baixa -> Low
            3 => 3,    // Média -> Moderate
            4 => 2,    // Alta -> High
            5 => 1,    // Muito alta -> Critical
            6 => 1     // Crítica -> Critical
        ];
        
        return $mapping[$glpiPriority] ?? 3; // Default: Moderate
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
}
