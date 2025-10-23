<?php
include ('../../../inc/includes.php');

header('Content-Type: application/json');

// Log para debug
error_log('SnowClient: save_session_data.php chamado');
error_log('SnowClient: POST data: ' . print_r($_POST, true));

// Verificar se os dados foram enviados
if (!isset($_POST['data'])) {
    error_log('SnowClient: Erro - No data provided');
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No data provided']));
}

try {
    // Os dados já vêm como string JSON
    $dataString = $_POST['data'];
    error_log('SnowClient: Data string recebida: ' . $dataString);
    error_log('SnowClient: Data string length: ' . strlen($dataString));
    error_log('SnowClient: Data string (hex): ' . bin2hex(substr($dataString, 0, 50)));
    
    // Verificar se a string não está vazia
    if (empty($dataString)) {
        throw new Exception('Empty data string');
    }
    
    // Decodificar os dados
    $data = json_decode($dataString, true);
    
    if ($data === null) {
        $error = json_last_error_msg();
        error_log('SnowClient: Erro ao decodificar JSON: ' . $error);
        error_log('SnowClient: JSON error code: ' . json_last_error());
        throw new Exception('Invalid JSON data: ' . $error . ' (code: ' . json_last_error() . ')');
    }

    error_log('SnowClient: Data decodificada: ' . print_r($data, true));

    // Validar dados essenciais
    if (!isset($data['ticketId']) || !isset($data['solutionCode'])) {
        error_log('SnowClient: Erro - Missing required fields');
        throw new Exception('Missing required fields (ticketId or solutionCode)');
    }

    // Salvar na sessão PHP (salvar como array, não string)
    $_SESSION['snowclient_solution_data'] = $data;
    
    error_log('SnowClient: Dados salvos na sessão com sucesso');
    error_log('SnowClient: Session data: ' . print_r($_SESSION['snowclient_solution_data'], true));

    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
    
} catch (Exception $e) {
    error_log('SnowClient: Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}