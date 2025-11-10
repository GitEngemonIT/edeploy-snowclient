<?php
include ('../../../inc/includes.php');

header('Content-Type: application/json');

// Log para debug
error_log('eDeployeDeploySnowClient: save_session_data.php chamado');
error_log('eDeployeDeploySnowClient: POST data: ' . print_r($_POST, true));

// Verificar se os dados foram enviados
if (!isset($_POST['ticketId']) || !isset($_POST['solutionCode'])) {
    error_log('eDeployeDeploySnowClient: Erro - Missing required fields');
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields (ticketId or solutionCode)']));
}

try {
    // Coletar dados direto do POST
    $data = [
        'ticketId' => $_POST['ticketId'],
        'solutionCode' => $_POST['solutionCode'],
        'timestamp' => isset($_POST['timestamp']) ? $_POST['timestamp'] : time()
    ];
    
    error_log('eDeployeDeploySnowClient: Data coletada: ' . print_r($data, true));

    // Salvar na sessão PHP
    $_SESSION['edeploysnowclient_solution_data'] = $data;
    
    error_log('eDeployeDeploySnowClient: Dados salvos na sessão com sucesso');
    error_log('eDeployeDeploySnowClient: Session data: ' . print_r($_SESSION['edeploysnowclient_solution_data'], true));

    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
    
} catch (Exception $e) {
    error_log('eDeployeDeploySnowClient: Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
