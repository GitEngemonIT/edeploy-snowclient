-- ============================================================
-- Migração: Adicionar colunas de retry no RAT Digital
-- Plugin: ratdigital
-- Versão: 2.4.0
-- Data: 2025-10-23
-- Descrição: Adiciona suporte a retry automático com backoff exponencial
-- ============================================================

-- Adicionar colunas de controle de retry
ALTER TABLE `glpi_plugin_ratdigital_rats` 
ADD COLUMN IF NOT EXISTS `retry_count` INT DEFAULT 0 COMMENT 'Número de tentativas realizadas' AFTER `status`,
ADD COLUMN IF NOT EXISTS `max_retries` INT DEFAULT 3 COMMENT 'Número máximo de tentativas permitidas' AFTER `retry_count`,
ADD COLUMN IF NOT EXISTS `next_retry_at` DATETIME DEFAULT NULL COMMENT 'Data/hora da próxima tentativa' AFTER `max_retries`,
ADD COLUMN IF NOT EXISTS `last_error` TEXT DEFAULT NULL COMMENT 'Mensagem do último erro' AFTER `next_retry_at`;

-- Criar índice para melhorar performance de queries de retry
CREATE INDEX IF NOT EXISTS `idx_status_retry` 
ON `glpi_plugin_ratdigital_rats` (`status`, `next_retry_at`);

-- Atualizar RATs com erro existentes para status 'error' (padronização)
UPDATE `glpi_plugin_ratdigital_rats` 
SET `status` = 'error' 
WHERE `status` IN ('failed', 'failure')
  AND `rat_url` IS NULL;

-- Adicionar novo status 'retry' na enum (se for enum)
-- Nota: Se a coluna status for VARCHAR, este passo não é necessário

-- Log da migração
INSERT INTO `glpi_logs` (`items_id`, `itemtype`, `itemtype_link`, `linked_action`, `user_name`, `date_mod`, `id_search_option`, `old_value`, `new_value`)
VALUES (0, 'PluginRatdigitalRat', '', 0, 'SYSTEM', NOW(), 0, '', 'Adicionadas colunas de retry: retry_count, max_retries, next_retry_at, last_error');

-- ============================================================
-- Verificação pós-migração
-- ============================================================

-- Verificar se as colunas foram criadas
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'glpi_plugin_ratdigital_rats'
    AND COLUMN_NAME IN ('retry_count', 'max_retries', 'next_retry_at', 'last_error')
ORDER BY 
    ORDINAL_POSITION;

-- Estatísticas de RATs por status
SELECT 
    `status`,
    COUNT(*) as total,
    COUNT(CASE WHEN `rat_url` IS NOT NULL THEN 1 END) as com_url,
    COUNT(CASE WHEN `rat_url` IS NULL THEN 1 END) as sem_url
FROM 
    `glpi_plugin_ratdigital_rats`
GROUP BY 
    `status`;

-- ============================================================
-- Rollback (em caso de necessidade)
-- ============================================================

-- Para reverter esta migração, execute:
-- ALTER TABLE `glpi_plugin_ratdigital_rats` 
-- DROP COLUMN IF EXISTS `retry_count`,
-- DROP COLUMN IF EXISTS `max_retries`,
-- DROP COLUMN IF EXISTS `next_retry_at`,
-- DROP COLUMN IF EXISTS `last_error`;
-- 
-- DROP INDEX IF EXISTS `idx_status_retry` ON `glpi_plugin_ratdigital_rats`;
