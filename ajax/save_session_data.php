<?php
include ('../../../inc/includes.php');

header('Content-Type: application/json');

// Log para debug
error_log('eDeploySnowClient: save_session_data.php chamado');
error_log('eDeploySnowClient: POST data: ' . print_r($_POST, true));

// Verificar se os dados foram enviados
if (!isset($_POST['ticketId']) || !isset($_POST['u_bk_type_of_failure'])) {
    error_log('eDeploySnowClient: Erro - Missing required fields');
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields (ticketId or u_bk_type_of_failure)']));
}

try {
    // Coletar todos os campos necessários para o ServiceNow
    $data = [
        'ticketId' => $_POST['ticketId'],
        'close_code' => $_POST['close_code'] ?? 'Definitiva',
        'u_bk_tipo_encerramento' => $_POST['u_bk_tipo_encerramento'] ?? 'Remoto',
        'u_bk_ic_impactado' => $_POST['u_bk_ic_impactado'] ?? 'Aplicação (Software)',
        'u_bk_type_of_failure' => $_POST['u_bk_type_of_failure'],
        'timestamp' => isset($_POST['timestamp']) ? $_POST['timestamp'] : time()
    ];
    
    error_log('eDeploySnowClient: Data coletada: ' . print_r($data, true));

    // Salvar na sessão PHP
    $_SESSION['edeploysnowclient_solution_data'] = $data;
    
    error_log('eDeploySnowClient: Dados salvos na sessão com sucesso');
    error_log('eDeploySnowClient: Session data: ' . print_r($_SESSION['edeploysnowclient_solution_data'], true));

    echo json_encode(['success' => true, 'message' => 'Data saved successfully', 'data' => $data]);
    
} catch (Exception $e) {
    error_log('eDeploySnowClient: Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
