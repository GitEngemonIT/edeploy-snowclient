/* ServiceNow Client Plugin JavaScript */

// Funções globais do SnowClient
var SnowClient = {
    
    // Configurações
    config: {
        debug: false
    },
    
    // Função de log para debug
    log: function(message) {
        if (this.config.debug && console) {
            console.log('[SnowClient] ' + message);
        }
    },
    
    // Inicializar plugin
    init: function() {
        this.log('SnowClient initialized');
        
        // Verificar se jQuery está disponível
        if (typeof jQuery === 'undefined') {
            console.error('SnowClient requires jQuery');
            return;
        }
        
        // Event handlers globais podem ser adicionados aqui
        this.setupGlobalHandlers();
    },
    
    // Configurar handlers globais
    setupGlobalHandlers: function() {
        // Handlers que se aplicam a todas as páginas
        // podem ser adicionados aqui
    }
};

// Inicializar quando o documento estiver pronto
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        SnowClient.init();
    });
} else {
    // Fallback para quando jQuery não estiver disponível
    document.addEventListener('DOMContentLoaded', function() {
        SnowClient.init();
    });
}
