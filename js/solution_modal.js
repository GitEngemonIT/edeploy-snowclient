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
    console.log('SnowClient: Inicializando modal de solução...');
    
    if (!window.SolutionModal) {
        window.SolutionModal = new SolutionModal();
    }
    
    // Observer para detectar quando o botão de solução é carregado
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                // Procurar pelo botão de solução
                const solutionBtn = document.querySelector('[name="add_solution"]');
                if (solutionBtn && !solutionBtn.dataset.snowclientInit) {
                    console.log('SnowClient: Botão de solução detectado, adicionando listener...');
                    
                    solutionBtn.dataset.snowclientInit = 'true';
                    solutionBtn.addEventListener('click', async (e) => {
                        // Aguardar o carregamento do form de solução
                        setTimeout(() => {
                            const solutionForm = document.querySelector('#solution-container form');
                            if (solutionForm && !solutionForm.dataset.snowclientInit) {
                                console.log('SnowClient: Form de solução detectado após clique');
                                
                                solutionForm.dataset.snowclientInit = 'true';
                                solutionForm.addEventListener('submit', async (e) => {
                                    console.log('SnowClient: Form de solução submetido');
                                    e.preventDefault();
                                    
                                    const ticketId = new URLSearchParams(window.location.search).get('id');
                                    try {
                                        console.log('SnowClient: Tentando abrir modal para ticket:', ticketId);
                                        await window.SolutionModal.open(ticketId, solutionForm);
                                    } catch (error) {
                                        console.error('SnowClient: Erro ao abrir modal:', error);
                                        alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
                                    }
                                });
                            }
                        }, 500); // Aguardar 500ms para o form ser carregado
                    });
                }
                
                // Verificar se já existe um form de solução (quando a página é carregada com o form aberto)
                const solutionForm = document.querySelector('#solution-container form');
                if (solutionForm && !solutionForm.dataset.snowclientInit) {
                    console.log('SnowClient: Form de solução já existe na página');
                    
                    solutionForm.dataset.snowclientInit = 'true';
                    solutionForm.addEventListener('submit', async (e) => {
                        console.log('SnowClient: Form de solução existente submetido');
                        e.preventDefault();
                        
                        const ticketId = new URLSearchParams(window.location.search).get('id');
                        try {
                            console.log('SnowClient: Tentando abrir modal para ticket existente:', ticketId);
                            await window.SolutionModal.open(ticketId, solutionForm);
                        } catch (error) {
                            console.error('SnowClient: Erro ao abrir modal:', error);
                            alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
                        }
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

// Verificar dados da sessão ao carregar
window.addEventListener('load', () => {
    const savedData = sessionStorage.getItem('snowclient_solution_data');
    if (savedData) {
        console.log('SnowClient: Dados de solução encontrados na sessão');
        const data = JSON.parse(savedData);
        // Limpar dados após uso
        sessionStorage.removeItem('snowclient_solution_data');
    }
});

// Inicializar quando documento estiver pronto
document.addEventListener('DOMContentLoaded', initSolutionModal);

// Reinicializar após carregamentos AJAX
$(document).ajaxComplete(function() {
    console.log('SnowClient: Ajax completado, verificando necessidade de inicialização...');
    initSolutionModal();
});