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

$LANG['snowclient'] = [
    // Plugin general
    'ServiceNow Client' => 'Cliente ServiceNow',
    'ServiceNow Integration' => 'Integração ServiceNow',
    'ServiceNow Configuration' => 'Configuração ServiceNow',
    
    // Configuration fields
    'ServiceNow Instance URL' => 'URL da Instância ServiceNow',
    'ServiceNow URL' => 'URL do ServiceNow',
    'ServiceNow Username' => 'Usuário ServiceNow',
    'Username' => 'Usuário',
    'Password' => 'Senha',
    'Default Assignment Group' => 'Grupo de Atribuição Padrão',
    'Entity for Integration' => 'Entidade para Integração',
    'ServiceNow Request Type' => 'Tipo de Solicitação ServiceNow',
    'ServiceNow API User' => 'Usuário da API ServiceNow',
    'Sync Tickets' => 'Sincronizar Chamados',
    'Sync Follow-ups' => 'Sincronizar Acompanhamentos',
    'Sync Follow-ups/Updates' => 'Sincronizar Acompanhamentos/Atualizações',
    'Sync Status' => 'Sincronizar Status',
    'Sync Status Changes' => 'Sincronizar Mudanças de Status',
    'Sync Documents' => 'Sincronizar Documentos',
    'Default Ticket Type' => 'Tipo de Ticket Padrão',
    'Enable Debug Mode' => 'Habilitar Modo Debug',
    'Debug Mode' => 'Modo Debug',
    'Integration Flow' => 'Fluxo de Integração',
    
    // New error messages and validation
    'URL inválida' => 'URL inválida',
    'Usuário não pode ser vazio' => 'Usuário não pode ser vazio',
    'Digite a senha para alterar' => 'Digite a senha para alterar',
    'Preencha todos os campos obrigatórios para testar a conexão' => 'Preencha todos os campos obrigatórios para testar a conexão',
    'Teste de conexão realizado com sucesso!' => 'Teste de conexão realizado com sucesso!',
    'Falha no teste de conexão. Verifique as credenciais e a URL.' => 'Falha no teste de conexão. Verifique as credenciais e a URL.',
    'Configuration updated successfully' => 'Configuração atualizada com sucesso',
    'Connection test successful' => 'Teste de conexão realizado com sucesso',
    'ServiceNow connection test successful!' => 'Teste de conexão ServiceNow realizado com sucesso!',
    'ServiceNow connection test failed. Check your credentials and URL.' => 'Teste de conexão ServiceNow falhou. Verifique suas credenciais e URL.',
    'Testar Conexão ServiceNow' => 'Testar Conexão ServiceNow',
    'Connection test failed' => 'Teste de conexão falhou',
    'Ticket synchronized from ServiceNow' => 'Chamado sincronizado do ServiceNow',
    'Ticket synchronized to ServiceNow' => 'Chamado sincronizado para o ServiceNow',
    'Ticket updated in ServiceNow' => 'Chamado atualizado no ServiceNow',
    'Work note added to ServiceNow' => 'Nota de trabalho adicionada ao ServiceNow',
    'Document attached to ServiceNow' => 'Documento anexado ao ServiceNow',
    '1. ServiceNow creates ticket → 2. Plugin replicates to GLPI → 3. Technician updates only in GLPI → 4. Plugin syncs back to ServiceNow' => '1. ServiceNow cria ticket → 2. Plugin replica para GLPI → 3. Técnico atualiza apenas no GLPI → 4. Plugin sincroniza de volta para ServiceNow',
    
    // Help texts
    'Only tickets from this entity and its children will be synchronized' => 'Apenas tickets desta entidade e suas filhas serão sincronizados',
    'Request type used to identify tickets coming from ServiceNow' => 'Tipo de solicitação usado para identificar tickets vindos do ServiceNow',
    'User used for API operations and followups' => 'Usuário usado para operações da API e acompanhamentos',
    'Send GLPI updates back to ServiceNow as work notes' => 'Enviar atualizações do GLPI de volta para ServiceNow como notas de trabalho',
    'Update ServiceNow ticket status when changed in GLPI' => 'Atualizar status do ticket ServiceNow quando alterado no GLPI',
    
    // Errors
    'ServiceNow configuration is incomplete' => 'Configuração do ServiceNow está incompleta',
    'Connection test failed' => 'Teste de conexão falhou',
    'Error synchronizing ticket' => 'Erro ao sincronizar chamado',
    'Error updating ticket' => 'Erro ao atualizar chamado',
    'Error adding work note' => 'Erro ao adicionar nota de trabalho',
    'Error attaching document' => 'Erro ao anexar documento',
    
    // Funcionalidade de Devolução
    'Return to ServiceNow' => 'Devolver ao ServiceNow',
    'Return Ticket to ServiceNow' => 'Devolver Chamado ao ServiceNow',
    'Return Reason' => 'Motivo da Devolução',
    'Destination Queue in ServiceNow' => 'Fila de Destino no ServiceNow',
    'Return Queue Group ID' => 'ID do Grupo da Fila de Devolução',
    'sys_id of the group for returned tickets' => 'sys_id do grupo para tickets devolvidos',
    'ServiceNow sys_id of the group that will receive returned tickets' => 'sys_id do ServiceNow do grupo que receberá tickets devolvidos',
    'Please provide a return reason' => 'Por favor, informe o motivo da devolução',
    'Ticket returned successfully to ServiceNow!' => 'Chamado devolvido com sucesso ao ServiceNow!',
    'Error returning ticket' => 'Erro ao devolver chamado',
    'Communication error. Please try again.' => 'Erro de comunicação. Tente novamente.',
    'Cancel' => 'Cancelar',
    'Return Ticket' => 'Devolver Chamado',
    'Returning...' => 'Devolvendo...',
    'Describe why this ticket is being returned to ServiceNow...' => 'Descreva o motivo pelo qual este chamado está sendo devolvido ao ServiceNow...',
    'Ex: Service Desk L1 (optional)' => 'Ex: Service Desk L1 (opcional)',
    'This ticket will be resolved in GLPI and transferred back to ServiceNow in the specified queue, WITHOUT being resolved there.' => 'Este chamado será resolvido no GLPI e transferido de volta ao ServiceNow na fila especificada, SEM ser resolvido lá.',
];
