<?php
/*
   ------------------------------------------------------------------------
   Plugin ServiceNow Client
   Copyright (C) 2025 by EngemonIT
   https://github.com/GitEngemonIT/edeploy-snowclient
   ------------------------------------------------------------------------
 */

$LANG['edeploysnowclient'] = [
    // Plugin general
    'ServiceNow Client' => 'ServiceNow Client',
    'ServiceNow Integration' => 'ServiceNow Integration',
    'ServiceNow Configuration' => 'ServiceNow Configuration',
    
    // Configuration fields
    'ServiceNow Instance URL' => 'ServiceNow Instance URL',
    'ServiceNow URL' => 'ServiceNow URL',
    'ServiceNow Username' => 'ServiceNow Username',
    'Username' => 'Username',
    'Password' => 'Password',
    'Default Assignment Group' => 'Default Assignment Group',
    'Entity for Integration' => 'Entity for Integration',
    'ServiceNow Request Type' => 'ServiceNow Request Type',
    'ServiceNow API User' => 'ServiceNow API User',
    'Default Technician for Auto-Assignment' => 'Default Technician for Auto-Assignment',
    'Sync Tickets' => 'Sync Tickets',
    'Sync Follow-ups' => 'Sync Follow-ups',
    'Sync Follow-ups/Updates' => 'Sync Follow-ups/Updates',
    'Sync Status' => 'Sync Status',
    'Sync Status Changes' => 'Sync Status Changes',
    'Sync Documents' => 'Sync Documents',
    'Default Ticket Type' => 'Default Ticket Type',
    'Enable Debug Mode' => 'Enable Debug Mode',
    'Debug Mode' => 'Debug Mode',
    'Integration Flow' => 'Integration Flow',
    'Synchronization Options (ServiceNow → GLPI → ServiceNow)' => 'Synchronization Options (ServiceNow → GLPI → ServiceNow)',
    
    // Ticket types
    'Incident' => 'Incident',
    'Service Request' => 'Service Request',
    'Change Request' => 'Change Request',
    'Problem' => 'Problem',
    
    // Messages
    'Configuration updated successfully' => 'Configuration updated successfully',
    'Connection test successful' => 'Connection test successful',
    'ServiceNow connection test successful!' => 'ServiceNow connection test successful!',
    'ServiceNow connection test failed. Check your credentials and URL.' => 'ServiceNow connection test failed. Check your credentials and URL.',
    'Testar Conexão ServiceNow' => 'Test ServiceNow Connection',
    'Connection test failed' => 'Connection test failed',
    'Ticket synchronized from ServiceNow' => 'Ticket synchronized from ServiceNow',
    'Ticket synchronized to ServiceNow' => 'Ticket synchronized to ServiceNow',
    'Ticket updated in ServiceNow' => 'Ticket updated in ServiceNow',
    'Work note added to ServiceNow' => 'Work note added to ServiceNow',
    'Document attached to ServiceNow' => 'Document attached to ServiceNow',
    '1. ServiceNow creates ticket → 2. Plugin replicates to GLPI → 3. Technician updates only in GLPI → 4. Plugin syncs back to ServiceNow' => '1. ServiceNow creates ticket → 2. Plugin replicates to GLPI → 3. Technician updates only in GLPI → 4. Plugin syncs back to ServiceNow',
    
    // Help texts
    'Only tickets from this entity and its children will be synchronized' => 'Only tickets from this entity and its children will be synchronized',
    'Request type used to identify tickets coming from ServiceNow' => 'Request type used to identify tickets coming from ServiceNow',
    'User used for API operations and followups' => 'User used for API operations and followups',
    'User for API operations and follow-ups' => 'User for API operations and follow-ups',
    'Technician to be assigned automatically when solving tickets without assignment' => 'Technician to be assigned automatically when solving tickets without assignment',
    'Select a user...' => 'Select a user...',
    'Select a technician...' => 'Select a technician...',
    'Send GLPI updates back to ServiceNow as work notes' => 'Send GLPI updates back to ServiceNow as work notes',
    'Update ServiceNow ticket status when changed in GLPI' => 'Update ServiceNow ticket status when changed in GLPI',
    
    // Errors
    'ServiceNow configuration is incomplete' => 'ServiceNow configuration is incomplete',
    'Connection test failed' => 'Connection test failed',
    'Error synchronizing ticket' => 'Error synchronizing ticket',
    'Error updating ticket' => 'Error updating ticket',
    'Error adding work note' => 'Error adding work note',
    'Error attaching document' => 'Error attaching document',
    
    // Return Functionality
    'Return to ServiceNow' => 'Return to ServiceNow',
    'Return Ticket to ServiceNow' => 'Return Ticket to ServiceNow',
    'Return Reason' => 'Return Reason',
    'Destination Queue in ServiceNow' => 'Destination Queue in ServiceNow',
    'Return Queue Group ID' => 'Return Queue Group ID',
    'sys_id of the group for returned tickets' => 'sys_id of the group for returned tickets',
    'ServiceNow sys_id of the group that will receive returned tickets' => 'ServiceNow sys_id of the group that will receive returned tickets',
    'Please provide a return reason' => 'Please provide a return reason',
    'Ticket returned successfully to ServiceNow!' => 'Ticket returned successfully to ServiceNow!',
    'Error returning ticket' => 'Error returning ticket',
    'Error returning ticket, correlation not found' => 'Error returning ticket, correlation not found',
    'Communication error. Please try again.' => 'Communication error. Please try again.',
    'Cancel' => 'Cancel',
    'Return Ticket' => 'Return Ticket',
    'Returning...' => 'Returning...',
    'Describe why this ticket is being returned to ServiceNow...' => 'Describe why this ticket is being returned to ServiceNow...',
    'Ex: Service Desk L1 (optional)' => 'Ex: Service Desk L1 (optional)',
    'This ticket will be resolved in GLPI and transferred back to ServiceNow using the configured return queue, WITHOUT being resolved there.' => 'This ticket will be resolved in GLPI and transferred back to ServiceNow using the configured return queue, WITHOUT being resolved there.',
];
