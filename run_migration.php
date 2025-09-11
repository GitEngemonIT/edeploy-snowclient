<?php
// Script para executar migração do campo return_queue_group
// Execute via browser: /glpi/plugins/snowclient/run_migration.php

require_once '../../../inc/includes.php';

echo "<h2>Migração - Campo return_queue_group</h2>";

// Verificar se o plugin está ativo
$plugin = new Plugin();
if (!$plugin->isActivated('snowclient')) {
    die('Plugin SnowClient não está ativo');
}

echo "<h3>Status Atual:</h3>";

global $DB;
$table = 'glpi_plugin_snowclient_configs';

// Verificar se a tabela existe
if (!$DB->tableExists($table)) {
    echo "<p style='color: red;'>❌ Tabela {$table} não existe!</p>";
    exit;
}

// Verificar se o campo existe
$fieldExists = $DB->fieldExists($table, 'return_queue_group');
echo "<p>Campo 'return_queue_group' existe: " . ($fieldExists ? '✅ SIM' : '❌ NÃO') . "</p>";

if (!$fieldExists) {
    echo "<h3>Executando Migração...</h3>";
    
    try {
        // Criar migration
        $migration = new Migration('1.1.1');
        $migration->displayMessage("Adicionando campo return_queue_group");
        
        // Adicionar o campo
        $migration->addField($table, 'return_queue_group', 'varchar(255) DEFAULT NULL', ['after' => 'assignment_group']);
        $migration->migrationOneTable($table);
        
        // Executar migração
        $migration->executeMigration();
        
        echo "<p style='color: green;'>✅ Migração executada com sucesso!</p>";
        
        // Verificar novamente
        $fieldExists = $DB->fieldExists($table, 'return_queue_group');
        echo "<p>Campo existe após migração: " . ($fieldExists ? '✅ SIM' : '❌ NÃO') . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro na migração: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Campo já existe, nenhuma migração necessária</p>";
}

// Mostrar estrutura atual da tabela
echo "<h3>Estrutura Atual da Tabela:</h3>";
try {
    $result = $DB->query("DESCRIBE {$table}");
    if ($result) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $DB->fetchAssoc($result)) {
            $highlight = ($row['Field'] === 'return_queue_group') ? 'style="background-color: #ffffcc;"' : '';
            echo "<tr {$highlight}>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao mostrar estrutura: " . $e->getMessage() . "</p>";
}

// Testar inserção de valor
if ($fieldExists || $DB->fieldExists($table, 'return_queue_group')) {
    echo "<h3>Teste de Persistência:</h3>";
    
    $config = new PluginSnowclientConfig();
    if ($config->getFromDB(1)) {
        $testValue = 'test_group_' . time();
        
        $result = $config->update([
            'id' => 1,
            'return_queue_group' => $testValue
        ]);
        
        if ($result) {
            // Verificar se persistiu
            $config->getFromDB(1);
            if ($config->fields['return_queue_group'] === $testValue) {
                echo "<p style='color: green;'>✅ Teste de persistência: SUCESSO</p>";
                echo "<p>Valor salvo: " . htmlspecialchars($config->fields['return_queue_group']) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Teste de persistência: FALHOU</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Erro ao testar atualização</p>";
        }
    }
}

?>
