-- Migration: adiciona coluna group_mappings à tabela de configuração do plugin eDeploySnowClient
-- Execute apenas uma vez em instalações existentes (novas instalações já incluem a coluna).

ALTER TABLE `glpi_plugin_edeploysnowclient_configs`
    ADD COLUMN IF NOT EXISTS `group_mappings` TEXT DEFAULT NULL COMMENT 'JSON: mapeamento de grupos GLPI → ServiceNow'
    AFTER `debug_mode`;
