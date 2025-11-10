<?php
/**
 * Script para executar migração manual do plugin SnowClient
 * Execute via browser: /glpi/plugins/snowclient/run_migration_v112.php
 */

require_once '../../../inc/includes.php';

if (!Session::haveRight('config', UPDATE)) {
    die('Acesso negado. É necessário ter permissão de administrador.');
}

echo "<h2>Migração do Plugin SnowClient para v1.1.2</h2>";

// Verificar versão atual do plugin
$plugin = new Plugin();
$plugin_data = $plugin->find(['directory' => 'snowclient']);

if (empty($plugin_data)) {
    die('Plugin SnowClient não encontrado.');
}

$current_plugin = reset($plugin_data);
$current_version = $current_plugin['version'];

echo "<p><strong>Versão atual:</strong> " . $current_version . "</p>";
echo "<p><strong>Versão alvo:</strong> 1.1.2</p>";

// Verificar se precisa migrar
if (version_compare($current_version, '1.1.2', '>=')) {
    echo "<p style='color: green;'>✅ Plugin já está na versão 1.1.2 ou superior. Nenhuma migração necessária.</p>";
} else {
    echo "<p style='color: orange;'>🔄 Migração necessária...</p>";
    
    // Executar migração
    echo "<h3>Executando migração...</h3>";
    
    try {
        // Chamar função de update
        $result = plugin_snowclient_update($current_version);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Migração executada com sucesso!</p>";
            
            // Atualizar versão do plugin na tabela
            global $DB;
            $DB->update('glpi_plugins', [
                'version' => '1.1.2'
            ], [
                'directory' => 'snowclient'
            ]);
            
            echo "<p style='color: green;'>✅ Versão do plugin atualizada para 1.1.2</p>";
        } else {
            echo "<p style='color: red;'>❌ Erro durante a migração</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    }
}

// Verificar se o campo foi criado
echo "<h3>Verificação pós-migração:</h3>";
global $DB;

try {
    $query = "DESCRIBE glpi_plugin_edeploysnowclient_configs";
    $result = $DB->query($query);
    
    $fields = [];
    $return_queue_found = false;
    
    while ($row = $DB->fetchAssoc($result)) {
        $fields[] = $row['Field'];
        if ($row['Field'] === 'return_queue_group') {
            $return_queue_found = true;
        }
    }
    
    echo "<p><strong>Campos na tabela:</strong></p>";
    echo "<ul>";
    foreach ($fields as $field) {
        $highlight = ($field === 'return_queue_group') ? ' style="color: green; font-weight: bold;"' : '';
        echo "<li{$highlight}>" . $field . "</li>";
    }
    echo "</ul>";
    
    if ($return_queue_found) {
        echo "<p style='color: green;'>✅ Campo 'return_queue_group' encontrado na tabela!</p>";
        
        // Testar inserção de valor
        $config = new PluginEdeploysnowclientConfig();
        if ($config->getFromDB(1)) {
            echo "<p><strong>Valor atual do return_queue_group:</strong> " . 
                 (empty($config->fields['return_queue_group']) ? '(vazio)' : htmlspecialchars($config->fields['return_queue_group'])) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Campo 'return_queue_group' NÃO encontrado na tabela!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro ao verificar tabela: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Após a migração, acesse a configuração do plugin para definir o Return Queue Group ID.</em></p>";
echo "<p><a href='/glpi/front/plugin.php'>← Voltar para lista de plugins</a></p>";

?>
