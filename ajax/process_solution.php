<?php
<?php
/**
 * Process solution modal form submission
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json');

try {
    // Validar dados recebidos
    $requiredFields = [
        'ticket_id',
        'u_bk_solucao',
        'u_bk_tipo_encerramento',
        'u_bk_ic_impactado',
        'u_bk_type_of_failure'
    ];

    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception(sprintf(
                __('Campo obrigatório não fornecido: %s', 'snowclient'),
                $field
            ));
        }
    }
    
    // Validar ticket_id
    $ticket = new Ticket();
    if (!$ticket->getFromDB($_POST['ticket_id'])) {
        throw new Exception(__('Ticket não encontrado', 'snowclient'));
    }

    // Verificar se é um ticket do ServiceNow
    if (!PluginSnowclientConfig::isTicketFromServiceNow($ticket)) {
        throw new Exception(__('Este ticket não é originado do ServiceNow', 'snowclient'));
    }

    // Validar código de solução contra lista permitida
    $config = PluginSnowclientConfig::getInstance();
    $allowedCodes = json_decode($config->fields['solution_codes'] ?? '[]', true);
    
    if (!in_array($_POST['u_bk_type_of_failure'], $allowedCodes)) {
        throw new Exception(__('Código de solução inválido', 'snowclient'));
    }

    // Armazenar dados na sessão para uso posterior
    $_SESSION['snowclient_solution_data'] = [
        'u_bk_solucao' => $_POST['u_bk_solucao'],
        'u_bk_tipo_encerramento' => $_POST['u_bk_tipo_encerramento'],
        'u_bk_ic_impactado' => $_POST['u_bk_ic_impactado'],
        'u_bk_type_of_failure' => $_POST['u_bk_type_of_failure']
    ];

    // Log dos dados para debug
    if (isset($CFG_GLPI["use_log_in_files"]) && $CFG_GLPI["use_log_in_files"]) {
        error_log(sprintf(
            "[SnowClient] Dados de solução recebidos para ticket %d: %s",
            $_POST['ticket_id'],
            json_encode($_SESSION['snowclient_solution_data'])
        ));
    }

    echo json_encode([
        'success' => true,
        'message' => __('Dados de solução registrados com sucesso', 'snowclient')
    ]);

} catch (Exception $e) {
    error_log("[SnowClient] Erro ao processar dados de solução: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}