<?php
/*
   ------------------------------------------------------------------------
   Plugin eDeploy ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/GitEngemonIT/edeploy-snowclient
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin eDeploy ServiceNow Client project.
   Plugin eDeploy ServiceNow Client is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   ------------------------------------------------------------------------
   @package   Plugin eDeploy ServiceNow Client
   @author    EngemonIT
   @co-author
   @copyright Copyright (c) 2025 eDeploy ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/GitEngemonIT/edeploy-snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

// Functions moved to setup.php to avoid duplication

function plugin_edeploysnowclient_pre_item_add($item) 
{
    if ($item::getType() === ITILSolution::getType()) {
        // Verificar se é um ticket do ServiceNow
        $ticket = new Ticket();
        if ($ticket->getFromDB($item->input['items_id'])) {
            if (PluginEdeploysnowclientConfig::isTicketFromServiceNow($ticket)) {
                error_log("eDeployeDeploySnowClient: pre_item_add - Solução sendo adicionada ao ticket ServiceNow: " . $ticket->fields['id']);
                
                // Verificar se tem os dados adicionais do ServiceNow na sessão
                if (isset($_SESSION['edeploysnowclient_solution_data'])) {
                    $snowData = is_array($_SESSION['edeploysnowclient_solution_data']) ? 
                        $_SESSION['edeploysnowclient_solution_data'] : 
                        json_decode($_SESSION['edeploysnowclient_solution_data'], true);
                    
                    error_log("eDeployeDeploySnowClient: pre_item_add - Dados da sessão encontrados: " . print_r($snowData, true));
                    
                    // Verificar se os dados são para este ticket específico
                    if ($snowData && isset($snowData['ticketId']) && $snowData['ticketId'] == $item->input['items_id']) {
                        error_log("eDeployeDeploySnowClient: pre_item_add - Dados validados para o ticket correto");
                    } else {
                        error_log("eDeployeDeploySnowClient: pre_item_add - AVISO: Dados da sessão não correspondem ao ticket atual");
                    }
                } else {
                    error_log("eDeployeDeploySnowClient: pre_item_add - AVISO: Dados adicionais do ServiceNow não encontrados na sessão");
                    // NÃO bloquear - permitir que a solução seja salva de qualquer forma
                    // O hook item_add irá usar dados padrão se necessário
                }
            }
        }
    }
    return true; // Sempre permitir a adição
}

function plugin_edeploysnowclient_item_add($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginEdeploysnowclientConfig::afterTicketAdd($item);
    }

    if ($item::getType() === ITILFollowup::getType()) {
        PluginEdeploysnowclientConfig::afterTicketFollowUp($item);
    }

    if ($item::getType() === ITILSolution::getType()) {
        // Se houver dados do ServiceNow anexados ao input
        PluginEdeploysnowclientConfig::afterTicketSolution($item);
    }

    if ($item::getType() === Document::getType()) {
        PluginEdeploysnowclientConfig::afterDocumentAdd($item);
    }
    
    if ($item::getType() === Document_Item::getType()) {
        PluginEdeploysnowclientConfig::afterDocumentItemAdd($item);
    }
}

function plugin_edeploysnowclient_item_update($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginEdeploysnowclientConfig::afterTicketUpdate($item);
    }

    if ($item::getType() === ITILFollowup::getType()) {
        PluginEdeploysnowclientConfig::afterTicketFollowUp($item);
    }
}

function plugin_edeploysnowclient_item_delete($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginEdeploysnowclientConfig::afterTicketDelete($item);
    }
}

function plugin_edeploysnowclient_item_form($params)
{
    // Simplificado - deixar o JavaScript fazer o trabalho de detectar e adicionar o botão
    // O JavaScript vai verificar se deve mostrar o botão baseado na lógica de negócio
    return true;
}

function plugin_edeploysnowclient_post_item_form($params)
{
    error_log("eDeployeDeploySnowClient: plugin_edeploysnowclient_post_item_form called");
    
    if ($params['item']::getType() === Ticket::getType()) {
        $ticket = $params['item'];
        
        error_log("eDeployeDeploySnowClient: Processing ticket ID: " . $ticket->getID());
        
        // Verificar se deve mostrar o botão
        if (PluginEdeploysnowclientConfig::shouldShowReturnButton($ticket)) {
            error_log("eDeployeDeploySnowClient: Should show button - calling showReturnButton");
            PluginEdeploysnowclientConfig::showReturnButton($ticket, $params);
        } else {
            error_log("eDeployeDeploySnowClient: Should NOT show button");
        }
    }
    
    return true;
}
