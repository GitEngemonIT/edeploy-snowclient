<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/engemon/snowclient
   ------------------------------------------------------------------------
 */

// Configurar headers primeiro
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Verificar se é uma requisição válida
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Include GLPI sem inicializar todos os plugins para evitar warnings
define('TU_USER', '_test_user');
define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));

$GLPI_CACHE = GLPI_ROOT . '/files/_cache';
$GLPI_CONFIG_DIR = GLPI_ROOT . '/config';

include GLPI_ROOT . '/inc/includes.php';

// Verificar autenticação de forma simples
if (!isset($_SESSION['glpiID']) || $_SESSION['glpiID'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Rate limiting simples - máximo 10 requisições por minuto por usuário
$userId = $_SESSION['glpiID'];
$rateLimitKey = 'snowclient_rate_limit_' . $userId;
$currentTime = time();

if (isset($_SESSION[$rateLimitKey])) {
    $lastRequests = $_SESSION[$rateLimitKey];
    // Filtrar requisições do último minuto
    $lastRequests = array_filter($lastRequests, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < 60;
    });
    
    if (count($lastRequests) >= 10) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Muitas requisições']);
        exit;
    }
    
    $lastRequests[] = $currentTime;
    $_SESSION[$rateLimitKey] = $lastRequests;
} else {
    $_SESSION[$rateLimitKey] = [$currentTime];
}

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
    // Cache simples para evitar processamento repetitivo
    $cacheKey = 'snowclient_button_check_' . $ticketId;
    if (isset($_SESSION[$cacheKey])) {
        $cached = $_SESSION[$cacheKey];
        // Se foi cachado há menos de 30 segundos, usar cache
        if ((time() - $cached['timestamp']) < 30) {
            echo json_encode($cached['result']);
            exit;
        }
    }
    
    // Carregar o ticket
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
        exit;
    }
    
    // Verificar se deve mostrar o botão
    $shouldShow = PluginEdeploysnowclientConfig::shouldShowReturnButton($ticket);
    
    $result = null;
    if ($shouldShow) {
        $result = [
            'success' => true, 
            'show_button' => true,
            'message' => 'Botão deve ser exibido'
        ];
    } else {
        $result = [
            'success' => true, 
            'show_button' => false,
            'message' => 'Critérios para exibição não atendidos'
        ];
    }
    
    // Cachear resultado
    $_SESSION[$cacheKey] = [
        'timestamp' => time(),
        'result' => $result
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("eDeploySnowClient check_return_button Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
?>
