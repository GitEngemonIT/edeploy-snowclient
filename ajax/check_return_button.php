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

if (!isset($_POST['ticket_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ticket_id é obrigatório']);
    exit;
}

$ticketId = intval($_POST['ticket_id']);

if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do ticket inválido']);
    exit;
}

try {
    // Carregar o ticket
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
        exit;
    }
    
    // Verificar se deve mostrar o botão
    $shouldShow = PluginSnowclientConfig::shouldShowReturnButton($ticket);
    
    if ($shouldShow) {
        echo json_encode([
            'success' => true, 
            'show_button' => true,
            'message' => 'Botão deve ser exibido'
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'show_button' => false,
            'message' => 'Critérios para exibição não atendidos'
        ]);
    }
    
} catch (Exception $e) {
    error_log("SnowClient check_return_button Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
?>
