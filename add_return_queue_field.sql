-- Script para adicionar o campo return_queue_group na tabela de produção
-- Execute este script no banco de dados de produção

-- Primeiro, verificar a estrutura atual da tabela
SELECT 'Estrutura atual da tabela:' as info;
DESCRIBE glpi_plugin_edeploysnowclient_configs;

-- Adicionar o campo return_queue_group se não existir
-- IMPORTANTE: Execute apenas se o campo não existir na estrutura acima
ALTER TABLE glpi_plugin_edeploysnowclient_configs 
ADD COLUMN return_queue_group varchar(255) DEFAULT NULL 
AFTER assignment_group;

-- Verificar se foi adicionado com sucesso
SELECT 'Campo adicionado com sucesso:' as info;
DESCRIBE glpi_plugin_edeploysnowclient_configs;
