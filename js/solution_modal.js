/**
 * Gerenciamento da modal de solução para integração com ServiceNow
 */
class SolutionModal {
    constructor() {
        this.modalHtml = null;
        this.initialized = false;
        this.ticketId = null;
    }

    /**
     * Inicializa a modal
     */
    async init() {
        if (this.initialized) return;

        // Carregar template da modal
        const response = await fetch('../plugins/snowclient/ajax/get_solution_modal.php');
        this.modalHtml = await response.text();
        
        // Adicionar ao DOM
        document.body.insertAdjacentHTML('beforeend', this.modalHtml);
        
        // Inicializar eventos
        this.bindEvents();
        
        this.initialized = true;
    }

    /**
     * Vincular eventos da modal
     */
    bindEvents() {
        const modal = document.querySelector('#snowclient-solution-modal');
        const form = modal.querySelector('form');
        const cancelBtn = modal.querySelector('.js-cancel');
        
        // Fechar modal
        cancelBtn.addEventListener('click', () => this.close());
        
        // Submit do form
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit(form);
        });
    }

    /**
     * Abrir modal para um ticket específico
     */
    async open(ticketId) {
        if (!this.initialized) {
            await this.init();
        }
        
        this.ticketId = ticketId;
        
        const modal = document.querySelector('#snowclient-solution-modal');
        modal.style.display = 'block';
        
        // Preencher campos readonly
        this.fillReadOnlyFields();
    }

    /**
     * Preencher campos somente leitura
     */
    fillReadOnlyFields() {        
        // Preencher campos readonly
        document.querySelector('#snow-solution').value = 'Definitiva';
        document.querySelector('#snow-close-type').value = 'Presencial';
        document.querySelector('#snow-solution-class').value = 'Hardware';
    }

    /**
     * Fechar modal
     */
    close() {
        const modal = document.querySelector('#snowclient-solution-modal');
        modal.style.display = 'none';
    }

    /**
     * Manipular envio do form
     */
    async handleSubmit(form) {
        const formData = new FormData(form);
        formData.append('ticket_id', this.ticketId);
        
        try {
            const response = await fetch('../plugins/snowclient/ajax/process_solution.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.close();
                // Continuar com o fluxo normal de solução
                document.querySelector('#solution-container form').submit();
            } else {
                alert(result.message || 'Erro ao processar solução');
            }
        } catch (error) {
            console.error('Erro ao enviar solução:', error);
            alert('Erro ao processar solução. Tente novamente.');
        }
    }
}

// Função de inicialização que será chamada quando necessário
function initSolutionModal() {
    console.log('SnowClient: Inicializando modal de solução...');
    
    if (!window.SolutionModal) {
        window.SolutionModal = new SolutionModal();
    }
    
    // Observer para detectar quando o formulário de solução é carregado
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                const solutionForm = document.querySelector('#solution-container form');
                if (solutionForm && !solutionForm.dataset.snowclientInit) {
                    console.log('SnowClient: Form de solução detectado, adicionando listener...');
                    
                    solutionForm.dataset.snowclientInit = 'true';
                    solutionForm.addEventListener('submit', async (e) => {
                        console.log('SnowClient: Form de solução submetido...');
                        e.preventDefault();
                        
                        const ticketId = new URLSearchParams(window.location.search).get('id');
                        console.log('SnowClient: Abrindo modal para ticket:', ticketId);
                        await window.SolutionModal.open(ticketId);
                    });
                }
            }
        });
    });
    
    // Observar o container principal do GLPI
    const container = document.querySelector('#page');
    if (container) {
        console.log('SnowClient: Iniciando observação do container...');
        observer.observe(container, { 
            childList: true, 
            subtree: true 
        });
    }
}

// Inicializar quando documento estiver pronto e quando houver navegação via AJAX
document.addEventListener('DOMContentLoaded', initSolutionModal);

// Tentar capturar eventos de carregamento AJAX do GLPI
if (typeof($) !== 'undefined') {
    $(document).ajaxComplete(function() {
        console.log('SnowClient: Ajax completado, verificando necessidade de inicialização...');
        initSolutionModal();
    });
}