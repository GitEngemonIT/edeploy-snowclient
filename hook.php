<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/engemon/snowclient
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin ServiceNow Client project.
   Plugin ServiceNow Client is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.
   ------------------------------------------------------------------------
   @package   Plugin ServiceNow Client
   @author    EngemonIT
   @co-author
   @copyright Copyright (c) 2025 ServiceNow Client Plugin Development team
   @license   GPL v3 or later
   @link      https://github.com/engemon/snowclient
   @since     2025
   ------------------------------------------------------------------------
 */

// Functions moved to setup.php to avoid duplication

function plugin_snowclient_pre_item_add($item) 
{
    if ($item::getType() === ITILSolution::getType()) {
        // Verificar se é um ticket do ServiceNow
        $ticket = new Ticket();
        if ($ticket->getFromDB($item->input['items_id'])) {
            if (PluginSnowclientConfig::isTicketFromServiceNow($ticket)) {
                // Verificar se tem os dados adicionais do ServiceNow
                $snowData = isset($_SESSION['snowclient_solution_data']) ? 
                    json_decode($_SESSION['snowclient_solution_data'], true) : null;

                // Verificar se os dados são para este ticket específico
                if (!$snowData || $snowData['ticketId'] != $item->input['items_id']) {
                    Session::addMessageAfterRedirect(
                        __('É necessário preencher os dados adicionais de solução para o ServiceNow', 'snowclient'),
                        true,
                        ERROR
                    );
                    return false;
                }

                // Anexar dados adicionais ao input da solução
                $item->input['snow_data'] = $snowData;
                unset($_SESSION['snowclient_solution_data']); // Limpar dados da sessão após uso
            }
        }
    }
    return true;
}

function plugin_snowclient_item_add($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginSnowclientConfig::afterTicketAdd($item);
    }

    if ($item::getType() === ITILFollowup::getType()) {
        PluginSnowclientConfig::afterTicketFollowUp($item);
    }

    if ($item::getType() === ITILSolution::getType()) {
        // Se houver dados do ServiceNow anexados ao input
        PluginSnowclientConfig::afterTicketSolution($item);
    }

    if ($item::getType() === Document::getType()) {
        PluginSnowclientConfig::afterDocumentAdd($item);
    }
    
    if ($item::getType() === Document_Item::getType()) {
        PluginSnowclientConfig::afterDocumentItemAdd($item);
    }
}

function plugin_snowclient_item_update($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginSnowclientConfig::afterTicketUpdate($item);
    }

    if ($item::getType() === ITILFollowup::getType()) {
        PluginSnowclientConfig::afterTicketFollowUp($item);
    }
}

function plugin_snowclient_item_delete($item)
{
    if ($item::getType() === Ticket::getType()) {
        PluginSnowclientConfig::afterTicketDelete($item);
    }
}

function plugin_snowclient_item_form($params)
{
    // Simplificado - deixar o JavaScript fazer o trabalho de detectar e adicionar o botão
    // O JavaScript vai verificar se deve mostrar o botão baseado na lógica de negócio
    return true;
}

function plugin_snowclient_post_item_form($params)
{
    error_log("SnowClient: plugin_snowclient_post_item_form called");
    
    if ($params['item']::getType() === Ticket::getType()) {
        $ticket = $params['item'];
        
        error_log("SnowClient: Processing ticket ID: " . $ticket->getID());
        
        // Verificar se deve mostrar o botão
        if (PluginSnowclientConfig::shouldShowReturnButton($ticket)) {
            error_log("SnowClient: Should show button - calling showReturnButton");
            PluginSnowclientConfig::showReturnButton($ticket, $params);
        } else {
            error_log("SnowClient: Should NOT show button");
        }
    }
    
    return true;
}
