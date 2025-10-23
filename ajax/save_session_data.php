<?php
include ('../../../inc/includes.php');

header('Content-Type: application/json');

// Verificar se os dados foram enviados
if (!isset($_POST['data'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No data provided']));
}

try {
    // Decodificar os dados
    $data = json_decode($_POST['data'], true);
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validar dados essenciais
    if (!isset($data['ticketId']) || !isset($data['solutionCode'])) {
        throw new Exception('Missing required fields');
    }

    // Salvar na sessão PHP
    $_SESSION['snowclient_solution_data'] = $_POST['data'];

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}