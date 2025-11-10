<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/engemon/snowclient
   ------------------------------------------------------------------------
 */

include '../../../inc/includes.php';

header('Content-Type: application/json');

Session::checkLoginUser();

if (!isset($_POST['ticket_id']) || !isset($_POST['return_reason'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não informados']);
    exit;
}

$ticketId = intval($_POST['ticket_id']);
$reason = trim($_POST['return_reason']);

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Motivo da devolução é obrigatório']);
    exit;
}

try {
    // Carregar o ticket
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
        exit;
    }
    
    // Verificar permissões
    if (!$ticket->canUpdate()) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para alterar este ticket']);
        exit;
    }
    
    // Verificar se é um ticket do ServiceNow
    if (!PluginEdeploysnowclientConfig::shouldShowReturnButton($ticket)) {
        echo json_encode(['success' => false, 'message' => 'Este ticket não pode ser devolvido ao ServiceNow']);
        exit;
    }
    
    // Processar a devolução
    $result = PluginEdeploysnowclientConfig::returnTicketToServiceNow($ticket, $reason);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Ticket devolvido com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    
} catch (Exception $e) {
    error_log("eDeploySnowClient Return Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
