<?php
/**
 * Teste de Conexão com ServiceNow - Diagnóstico
 * 
 * Execute este arquivo para testar a conectividade com ServiceNow
 */

// Incluir GLPI
include '../../inc/includes.php';

echo "<h2>🔍 Teste de Conexão ServiceNow - Diagnóstico</h2>";

// Carregar configuração
$config = PluginSnowclientConfig::getInstance();

if (!$config || empty($config->fields)) {
    echo "<p style='color: red;'>❌ Configuração não encontrada</p>";
    exit;
}

echo "<h3>📋 Configuração Atual:</h3>";
echo "<ul>";
echo "<li><strong>URL:</strong> " . htmlspecialchars($config->fields['instance_url']) . "</li>";
echo "<li><strong>Username:</strong> " . htmlspecialchars($config->fields['username']) . "</li>";
echo "<li><strong>Senha:</strong> " . (empty($config->fields['password']) ? '❌ NÃO CONFIGURADA' : '✅ Configurada') . "</li>";
echo "<li><strong>Debug Mode:</strong> " . ($config->fields['debug_mode'] ? '✅ Ativo' : '❌ Inativo') . "</li>";
echo "<li><strong>Fila de Devolução:</strong> " . (empty($config->fields['return_queue_group']) ? '❌ Não configurada' : htmlspecialchars($config->fields['return_queue_group'])) . "</li>";
echo "</ul>";

// Testar conexão básica
echo "<h3>🌐 Teste de Conectividade:</h3>";

$api = new PluginSnowclientApi();

try {
    echo "<p>🔄 Testando conexão com ServiceNow...</p>";
    
    // Fazer requisição simples para testar autenticação
    $response = $api->testConnection();
    
    if ($response && isset($response['result'])) {
        echo "<p style='color: green;'>✅ <strong>CONEXÃO OK!</strong> Conseguiu acessar API do ServiceNow</p>";
        echo "<p>📊 Resposta recebida: " . count($response['result']) . " registro(s)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Conexão estabelecida mas resposta inesperada</p>";
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERRO NA CONEXÃO:</strong></p>";
    echo "<p style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
    echo htmlspecialchars($e->getMessage());
    echo "</p>";
    
    // Análise do erro
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, '401') !== false) {
        echo "<h4>🔍 Diagnóstico - Erro 401:</h4>";
        echo "<ul>";
        echo "<li>❌ <strong>Problema de Autenticação</strong></li>";
        echo "<li>🔧 Verifique username e senha nas configurações</li>";
        echo "<li>🔧 Verifique se o usuário tem permissões no ServiceNow</li>";
        echo "<li>🔧 Teste as credenciais diretamente no ServiceNow</li>";
        echo "</ul>";
    } elseif (strpos($errorMsg, '404') !== false) {
        echo "<h4>🔍 Diagnóstico - Erro 404:</h4>";
        echo "<ul>";
        echo "<li>❌ <strong>URL ou Endpoint não encontrado</strong></li>";
        echo "<li>🔧 Verifique a URL da instância ServiceNow</li>";
        echo "<li>🔧 Certifique-se que a API REST está ativa</li>";
        echo "</ul>";
    } elseif (strpos($errorMsg, 'cURL') !== false) {
        echo "<h4>🔍 Diagnóstico - Erro de Rede:</h4>";
        echo "<ul>";
        echo "<li>❌ <strong>Problema de conectividade</strong></li>";
        echo "<li>🔧 Verifique conectividade de rede</li>";
        echo "<li>🔧 Verifique firewall e proxy</li>";
        echo "<li>🔧 Teste ping para o servidor ServiceNow</li>";
        echo "</ul>";
    }
}

echo "<hr>";
echo "<h3>📋 Próximos Passos para Debug:</h3>";
echo "<ol>";
echo "<li><strong>Ative o Debug Mode</strong> na configuração do plugin</li>";
echo "<li><strong>Tente uma devolução</strong> e observe os logs</li>";
echo "<li><strong>Verifique os logs</strong> em <code>/var/www/html/glpi/files/_log/php-errors.log</code></li>";
echo "<li><strong>Procure por</strong>: <code>grep 'SnowClient RETURN' php-errors.log</code></li>";
echo "</ol>";

echo "<p><small>💡 <strong>Dica:</strong> Todos os logs de devolução agora começam com 'SnowClient RETURN:' para facilitar a busca</small></p>";
?>
