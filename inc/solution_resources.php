<?php

/**
 * Add necessary CSS and JavaScript files for solution modal
 */
function plugin_snowclient_add_solution_resources($hook_params = [])
{
    if (!isset($_SESSION['glpiactiveprofile'])) {
        return;
    }

    // Verificar se estamos em um formulário de ticket
    if (!isset($hook_params['item']) || !($hook_params['item'] instanceof Ticket)) {
        return;
    }
    
    error_log("SnowClient: Adicionando recursos da modal de solução para ticket " . $hook_params['item']->getID());
    
    // Injetar recursos necessários
    echo "<link rel='stylesheet' type='text/css' href='" . Plugin::getWebDir('snowclient') . "/css/solution_modal.css'>";
    echo "<script type='text/javascript' src='" . Plugin::getWebDir('snowclient') . "/js/solution_modal.js'></script>";
    
    // Verificar se é um ticket do ServiceNow
    if (PluginSnowclientConfig::isTicketFromServiceNow($hook_params['item'])) {
        error_log("SnowClient: Ticket é do ServiceNow, ativando modal de solução");
        echo "<script>console.log('SnowClient: Ticket do ServiceNow detectado, modal será ativado');</script>";
    } else {
        error_log("SnowClient: Ticket não é do ServiceNow, modal não será ativado");
    }
}