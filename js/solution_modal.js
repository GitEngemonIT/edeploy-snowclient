/**
 * Gerenciamento da modal de solução para integração com ServiceNow
 */
class SolutionModal {
    constructor() {
        this.modalHtml = null;
        this.initialized = false;
        this.ticketId = null;
        this.originalForm = null;
    }

    /**
     * Inicializa a modal
     */
    async init() {
        console.log('SnowClient Modal: Iniciando inicialização...');
        if (this.initialized) {
            console.log('SnowClient Modal: Já inicializado');
            return;
        }

        try {
            // Carregar template da modal
            console.log('SnowClient Modal: Carregando template...');
            const response = await fetch('../plugins/snowclient/ajax/get_solution_modal.php');
            if (!response.ok) throw new Error('Falha ao carregar template');
            this.modalHtml = await response.text();
            
            // Remover modal anterior se existir
            const oldModal = document.querySelector('#snowclient-solution-modal');
            if (oldModal) {
                console.log('SnowClient Modal: Removendo modal anterior');
                oldModal.remove();
            }
            
            // Adicionar ao DOM
            console.log('SnowClient Modal: Adicionando ao DOM');
            document.body.insertAdjacentHTML('beforeend', this.modalHtml);
            
            // Inicializar eventos
            this.bindEvents();
            
            this.initialized = true;
            console.log('SnowClient Modal: Inicialização completa');
        } catch (error) {
            console.error('SnowClient Modal: Erro na inicialização:', error);
            throw error;
        }
    }

    /**
     * Vincular eventos da modal
     */
    bindEvents() {
        console.log('SnowClient Modal: Vinculando eventos');
        const modal = document.querySelector('#snowclient-solution-modal');
        const form = modal.querySelector('form');
        const cancelBtn = modal.querySelector('.js-cancel');
        
        // Fechar modal
        cancelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('SnowClient Modal: Botão cancelar clicado');
            this.close();
        });
        
        // Submit do form
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            console.log('SnowClient Modal: Form da modal submetido');
            this.handleSubmit(form);
        });
        
        // Fechar ao clicar fora
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                console.log('SnowClient Modal: Clique fora da modal');
                this.close();
            }
        });
    }

    /**
     * Abrir modal para um ticket específico
     */
    async open(ticketId, originalForm) {
        console.log('SnowClient Modal: Abrindo modal para ticket', ticketId);
        try {
            if (!this.initialized) {
                await this.init();
            }
            
            this.ticketId = ticketId;
            this.originalForm = originalForm;
            
            const modal = document.querySelector('#snowclient-solution-modal');
            if (!modal) {
                throw new Error('Modal não encontrada no DOM');
            }
            
            // Mostrar modal
            modal.style.display = 'block';
            
            // Preencher campos readonly
            this.fillReadOnlyFields();
            
            // Focar no primeiro campo editável
            const firstInput = modal.querySelector('select, input:not([readonly]), textarea:not([readonly])');
            if (firstInput) firstInput.focus();
            
            console.log('SnowClient Modal: Modal aberta com sucesso');
        } catch (error) {
            console.error('SnowClient Modal: Erro ao abrir modal:', error);
            throw error;
        }
    }

    /**
     * Preencher campos somente leitura
     */
    fillReadOnlyFields() {        
        console.log('SnowClient Modal: Preenchendo campos readonly');
        try {
            // Obter texto da solução do formulário original
            const solutionText = this.originalForm ? 
                this.originalForm.querySelector('[name="content"]').value : '';
            
            // Preencher campos
            document.querySelector('#snow-solution').value = solutionText || 'Definitiva';
            document.querySelector('#snow-close-type').value = 'Presencial';
            document.querySelector('#snow-solution-class').value = 'Hardware';
            
            console.log('SnowClient Modal: Campos preenchidos');
        } catch (error) {
            console.error('SnowClient Modal: Erro ao preencher campos:', error);
        }
    }

    /**
     * Fechar modal
     */
    close() {
        console.log('SnowClient Modal: Fechando modal');
        const modal = document.querySelector('#snowclient-solution-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Manipular envio do form
     */
    async handleSubmit(form) {
        console.log('SnowClient Modal: Processando submissão do form');
        const formData = new FormData(form);
        formData.append('ticket_id', this.ticketId);
        
        try {
            const response = await fetch('../plugins/snowclient/ajax/process_solution.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('Erro na resposta do servidor');
            
            const result = await response.json();
            console.log('SnowClient Modal: Resposta do servidor:', result);
            
            if (result.success) {
                // Armazenar dados na sessão
                sessionStorage.setItem('snowclient_solution_data', JSON.stringify({
                    ticketId: this.ticketId,
                    formData: Object.fromEntries(formData)
                }));
                
                this.close();
                
                // Continuar com o fluxo normal de solução
                if (this.originalForm) {
                    console.log('SnowClient Modal: Submetendo formulário original');
                    this.originalForm.submit();
                }
            } else {
                alert(result.message || 'Erro ao processar solução');
            }
        } catch (error) {
            console.error('SnowClient Modal: Erro ao processar submissão:', error);
            alert('Erro ao processar solução. Tente novamente.');
        }
    }
}

// Função de inicialização que será chamada quando necessário
function initSolutionModal() {
    console.log('SnowClient: Inicializando manipulador da modal de solução...');
    
    if (!window.SolutionModal) {
        window.SolutionModal = new SolutionModal();
    }
    
    // Função para interceptar o formulário de solução
    function interceptSolutionForm() {
        // Encontrar o formulário de solução
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Verificar se é um formulário de solução
            if (form.querySelector('textarea[name="content"]') && 
                !form.dataset.snowclientInit) {
                
                console.log('SnowClient: Form de solução encontrado, adicionando interceptador');
                form.dataset.snowclientInit = 'true';
                
                // Interceptar o submit
                form.addEventListener('submit', async function(e) {
                    // Verificar se é um ticket do ServiceNow
                    const ticketId = new URLSearchParams(window.location.search).get('id');
                    if (!ticketId) return; // Deixar continuar normalmente se não for um ticket
                    
                    // Fazer uma verificação AJAX para confirmar se é um ticket do ServiceNow
                    try {
                        const response = await fetch('../plugins/snowclient/ajax/check_return_button.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'ticket_id=' + ticketId
                        });
                        
                        const data = await response.json();
                        if (data.success && data.show_button) { // Se é um ticket do ServiceNow
                            console.log('SnowClient: Ticket do ServiceNow detectado, interceptando submit');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            try {
                                await window.SolutionModal.open(ticketId, form);
                            } catch (error) {
                                console.error('SnowClient: Erro ao abrir modal:', error);
                                alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
                            }
                        } else {
                            console.log('SnowClient: Não é um ticket do ServiceNow, continuando normalmente');
                        }
                    } catch (error) {
                        console.error('SnowClient: Erro ao verificar ticket:', error);
                    }
                });
            }
        });
    }
    
    // Observar mudanças no DOM para detectar quando o formulário é adicionado
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                interceptSolutionForm();
            }
        });
    });
    
    // Iniciar observação
    const container = document.querySelector('#page');
    if (container) {
        observer.observe(container, { 
            childList: true, 
            subtree: true 
        });
    }
    
    // Verificar formulários existentes
    interceptSolutionForm();
}

// Inicializar quando o documento estiver pronto
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        console.log('SnowClient: Documento pronto, inicializando...');
        initSolutionModal();
    });
} else {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('SnowClient: DOM carregado, inicializando...');
        initSolutionModal();
    });
}

// Reinicializar após carregamentos AJAX
$(document).ajaxComplete(function() {
    console.log('SnowClient: Ajax completado, reinicializando...');
    initSolutionModal();
});