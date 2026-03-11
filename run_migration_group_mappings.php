<?php
/**
 * Migration: adiciona coluna group_mappings ao plugin eDeploySnowClient
 *
 * Execute este script uma única vez em instalações existentes.
 * Novas instalações já incluem a coluna automaticamente via install().
 *
 * Uso:
 *   php run_migration_group_mappings.php
 */

// Bootstrap GLPI
// O plugin está em <glpi_root>/plugins/edeploysnowclient/, portanto 2 níveis acima
$glpiPath = dirname(__DIR__, 2);
if (!file_exists($glpiPath . '/inc/includes.php')) {
    // Fallback: tentar caminhos comuns
    foreach (['/var/www/html/glpi', '/var/www/glpi', '/var/www/html'] as $candidate) {
        if (file_exists($candidate . '/inc/includes.php')) {
            $glpiPath = $candidate;
            break;
        }
    }
}
if (!file_exists($glpiPath . '/inc/includes.php')) {
    echo "ERRO: Não foi possível localizar o GLPI. Edite o caminho \$glpiPath direto no script.\n";
    exit(1);
}
include($glpiPath . '/inc/includes.php');

Session::checkRight('config', UPDATE);

global $DB;

$table = 'glpi_plugin_edeploysnowclient_configs';

if (!$DB->tableExists($table)) {
    echo "Tabela $table não encontrada. O plugin está instalado?\n";
    exit(1);
}

if ($DB->fieldExists($table, 'group_mappings')) {
    echo "Coluna 'group_mappings' já existe. Nenhuma ação necessária.\n";
    exit(0);
}

$result = $DB->query(
    "ALTER TABLE `$table` ADD COLUMN `group_mappings` TEXT DEFAULT NULL AFTER `debug_mode`"
);

if ($result) {
    echo "Coluna 'group_mappings' adicionada com sucesso!\n";
} else {
    echo "Erro ao adicionar coluna: " . $DB->error() . "\n";
    exit(1);
}
