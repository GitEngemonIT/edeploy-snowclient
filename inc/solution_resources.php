<?php

/**
 * Add debug logging for solution modal
 * CSS and JS are loaded globally via setup.php
 */
function plugin_edeploysnowclient_add_solution_resources($hook_params = [])
{
    if (!isset($_SESSION['glpiactiveprofile'])) {
        return;
    }

    // Verificar se estamos em um formulário de ticket
    if (!isset($hook_params['item']) || !($hook_params['item'] instanceof Ticket)) {
        return;
    }
    
    error_log("edeployeDeploySnowClient: Verificando ticket " . $hook_params['item']->getID() . " para modal de solução");
    
    // Verificar se é um ticket do ServiceNow
    if (PluginEdeployedeploysnowclientConfig::isTicketFromServiceNow($hook_params['item'])) {
        error_log("edeployeDeploySnowClient: Ticket é do ServiceNow, modal deve ser ativado");
        echo "<script>console.log('edeployeDeploySnowClient: Ticket do ServiceNow detectado (ID: " . $hook_params['item']->getID() . ")');</script>";
    } else {
        error_log("edeployeDeploySnowClient: Ticket não é do ServiceNow");
    }
}
