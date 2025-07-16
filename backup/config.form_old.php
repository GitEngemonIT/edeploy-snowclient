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

include '../../../inc/includes.php';

// Check if plugin is activated...
$plugin = new Plugin();
if (!$plugin->isInstalled('snowclient') || !$plugin->isActivated('snowclient')) {
    Html::displayNotFoundError();
}

// Include plugin files
include_once(GLPI_ROOT . '/plugins/snowclient/inc/config.class.php');
include_once(GLPI_ROOT . '/plugins/snowclient/inc/api.class.php');

Session::checkRight('config', UPDATE);

$config = new PluginSnowclientConfig();

if (isset($_POST['update'])) {
    $config->check($_POST['id'], UPDATE);
    
    $input = [
        'id' => $_POST['id'],
        'instance_url' => $_POST['instance_url'],
        'username' => $_POST['username'],
        'assignment_group' => $_POST['assignment_group'],
        'entities_id' => $_POST['entities_id'],
        'request_type' => $_POST['request_type'],
        'api_user' => $_POST['api_user'],
        'sync_tickets' => isset($_POST['sync_tickets']) ? $_POST['sync_tickets'] : 0,
        'sync_followups' => isset($_POST['sync_followups']) ? $_POST['sync_followups'] : 0,
        'sync_status' => isset($_POST['sync_status']) ? $_POST['sync_status'] : 0,
        'sync_documents' => isset($_POST['sync_documents']) ? $_POST['sync_documents'] : 0,
        'default_type' => $_POST['default_type'],
        'debug_mode' => isset($_POST['debug_mode']) ? $_POST['debug_mode'] : 0,
    ];
    
    // Only update password if provided
    if (!empty($_POST['password'])) {
        $input['password'] = $_POST['password'];
    }
    
    $config->update($input);
    
    Session::addMessageAfterRedirect(__('Configuration updated successfully', 'snowclient'));
    Html::back();
}

if (isset($_POST['test_connection'])) {
    // Debug: verificar todos os dados POST recebidos
    error_log("POST data received: " . print_r($_POST, true));
    
    // Buscar dados salvos no banco se campos estiverem vazios
    $config = new PluginSnowclientConfig();
    $config->getFromDB(1);
    
    // Preparar dados para teste - usar dados salvos se não fornecidos no formulário
    $tempData = [
        'instance_url' => !empty($_POST['instance_url']) ? $_POST['instance_url'] : $config->fields['instance_url'],
        'username' => !empty($_POST['username']) ? $_POST['username'] : $config->fields['username'],
        'password' => !empty($_POST['password']) ? $_POST['password'] : null // Null se não fornecida
    ];
    
    // Se senha não foi fornecida no formulário, usar a salva no banco (descriptografada)
    if (empty($tempData['password']) && !empty($config->fields['password'])) {
        // Usar base64_decode já que nossa implementação usa base64
        $tempData['password'] = base64_decode($config->fields['password']);
        error_log("Password loaded from DB - encoded length: " . strlen($config->fields['password']) . ", decoded length: " . strlen($tempData['password']));
    }
    
    // Debug: verificar dados preparados (sem mostrar senha completa)
    $debugData = $tempData;
    if (!empty($debugData['password'])) {
        $debugData['password'] = '[***' . substr($debugData['password'], -4) . ']';
    }
    error_log("Temp data prepared: " . print_r($debugData, true));
    
    // Validar dados básicos
    if (empty($tempData['instance_url']) || empty($tempData['username']) || empty($tempData['password'])) {
        $missing = [];
        if (empty($tempData['instance_url'])) $missing[] = 'URL';
        if (empty($tempData['username'])) $missing[] = 'Usuário';
        if (empty($tempData['password'])) $missing[] = 'Senha';
        
        error_log("Missing fields: " . implode(', ', $missing));
        Session::addMessageAfterRedirect(__('Erro: campos obrigatórios não encontrados nem no formulário nem na configuração salva', 'snowclient') . ' (' . implode(', ', $missing) . ')', false, ERROR);
        Html::back();
    }
    
    // Validar URL
    if (!filter_var($tempData['instance_url'], FILTER_VALIDATE_URL)) {
        Session::addMessageAfterRedirect(__('URL inválida', 'snowclient'), false, ERROR);
        Html::back();
    }
    
    // Fazer teste direto via cURL
    $url = rtrim($tempData['instance_url'], '/') . '/api/now/table/incident?sysparm_limit=1';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $tempData['username'] . ':' . $tempData['password'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log detalhes do teste
    error_log("cURL test - URL: $url");
    error_log("cURL test - HTTP Code: $httpCode");
    error_log("cURL test - Error: $error");
    error_log("cURL test - Response: " . substr($response, 0, 500));
    
    if ($error) {
        Session::addMessageAfterRedirect(__('Erro de conexão: ', 'snowclient') . $error, false, ERROR);
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        Session::addMessageAfterRedirect(__('Teste de conexão realizado com sucesso!', 'snowclient'), false, INFO);
    } else {
        $responseData = json_decode($response, true);
        $errorMsg = isset($responseData['error']['message']) ? $responseData['error']['message'] : "HTTP $httpCode";
        Session::addMessageAfterRedirect(__('Falha no teste de conexão: ', 'snowclient') . $errorMsg, false, ERROR);
    }
    
    Html::back();
}

Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginSnowclientConfig$1'));
