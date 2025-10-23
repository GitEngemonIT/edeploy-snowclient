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
            this.ticketId = ticketId;
            this.originalForm = originalForm;
            
            // Verificar se a modal já existe
            let modal = document.querySelector('#snowclient-solution-modal');
            if (!modal) {
                console.log('SnowClient Modal: Modal não encontrada, carregando template...');
                
                // Carregar template da modal
                try {
                    const response = await $.ajax({
                        url: '../plugins/snowclient/ajax/get_solution_modal.php',
                        method: 'GET'
                    });
                    
                    // Adicionar modal ao DOM
                    $('body').append(response);
                    
                    // Referenciar a modal recém-adicionada
                    modal = document.querySelector('#snowclient-solution-modal');
                    
                    if (!modal) {
                        throw new Error('Modal não encontrada após inserção no DOM');
                    }
                } catch (error) {
                    console.error('SnowClient Modal: Erro ao carregar template:', error);
                    throw new Error('Falha ao carregar modal de solução');
                }
            }
            
            // Atualizar conteúdo da modal
            if (this.originalForm) {
                // Preencher a solução do GLPI
                const content = this.originalForm.querySelector('textarea[name="content"]');
                if (content) {
                    const solutionTextarea = modal.querySelector('#snow-solution');
                    if (solutionTextarea) {
                        solutionTextarea.value = content.value || '';
                    }
                }

                // Preencher campos readonly com valores mockados do ServiceNow
                const closeTypeInput = modal.querySelector('#snow-close-type');
                if (closeTypeInput) {
                    closeTypeInput.value = 'Presencial';  // Valor mockado para u_bk_tipo_encerramento
                }

                const solutionClass = modal.querySelector('#snow-solution-class');
                if (solutionClass) {
                    solutionClass.value = 'Hardware';  // Valor mockado para u_bk_ic_impactado
                }

                console.log('SnowClient Modal: Campos mockados preenchidos com sucesso');
            } else {
                console.warn('SnowClient Modal: Formulário original não encontrado');
            }
            
            // Remover select2 anterior se existir
            const solutionCode = modal.querySelector('#snow-solution-code');
            if (solutionCode) {
                try {
                    $(solutionCode).select2('destroy');
                } catch (e) {
                    // Ignora erro se select2 não existir
                }
            }

            // Limpar seleção anterior
            if (solutionCode) {
                solutionCode.value = '';
                solutionCode.classList.remove('is-valid', 'is-invalid');
            }
            
            // Remover eventos anteriores
            const form = modal.querySelector('#snow-solution-form');
            if (form) {
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
                this.bindModalEvents(modal);
            }
            
            // Reinicializar select2
            if (solutionCode) {
                $(solutionCode).select2({
                    dropdownParent: modal,
                    width: '100%',
                    placeholder: 'Selecione...',
                    minimumResultsForSearch: -1 // Desabilita busca
                }).on('change', () => {
                    if (solutionCode.value) {
                        solutionCode.classList.remove('is-invalid');
                        solutionCode.classList.add('is-valid');
                    } else {
                        solutionCode.classList.remove('is-valid');
                        solutionCode.classList.add('is-invalid');
                    }
                });
            }
            
            // Mostrar modal usando Bootstrap
            const bsModal = new bootstrap.Modal(modal, {
                backdrop: 'static',
                keyboard: false
            });
            
            bsModal.show();
            
            console.log('SnowClient Modal: Modal aberta com sucesso');
            
        } catch (error) {
            console.error('SnowClient Modal: Erro ao abrir modal:', error);
            alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
            throw error;
        }
    }

    /**
     * Inicializar eventos específicos da modal
     */
    bindModalEvents(modal) {
        console.log('SnowClient Modal: Inicializando eventos da modal');
        
        try {
            // Form submit
            const form = modal.querySelector('#snow-solution-form');
            if (form) {
                // Remover eventos anteriores
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
                
                // Adicionar novo evento de submit
                newForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleSubmit(newForm);
                });
                
                // Adicionar validação em tempo real
                const solutionCode = newForm.querySelector('#snow-solution-code');
                if (solutionCode) {
                    solutionCode.addEventListener('change', () => {
                        if (solutionCode.value) {
                            solutionCode.classList.remove('is-invalid');
                            solutionCode.classList.add('is-valid');
                        } else {
                            solutionCode.classList.remove('is-valid');
                            solutionCode.classList.add('is-invalid');
                        }
                    });
                }
            }
            
            // Botões de ação
            const confirmBtn = modal.querySelector('button[type="submit"]');
            const cancelBtn = modal.querySelector('[data-bs-dismiss="modal"]');
            
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => {
                    const form = modal.querySelector('#snow-solution-form');
                    if (form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                });
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        if (confirm('Tem certeza que deseja cancelar? As alterações não salvas serão perdidas.')) {
                            bsModal.hide();
                        }
                    }
                });
            }
            
            console.log('SnowClient Modal: Eventos inicializados com sucesso');
            
        } catch (error) {
            console.error('SnowClient Modal: Erro ao inicializar eventos:', error);
            throw new Error('Falha ao inicializar eventos da modal');
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
     * Handler para o submit do formulário da modal
     */
    async handleSubmit(form) {
        if (this.isSubmitting) return;
        
        try {
            this.isSubmitting = true;
            console.log('SnowClient Modal: Processando submit do formulário');

            // 1. Validar código de solução
            const solutionCode = form.querySelector('#snow-solution-code');
            if (!solutionCode?.value) {
                solutionCode.classList.add('is-invalid');
                alert('Por favor, selecione um código de solução.');
                return;
            }

            // 2. Verificar e preparar formulário original
            if (!this.originalForm?.isConnected) {
                throw new Error('Formulário original do GLPI não encontrado');
            }

            const submitButton = this.originalForm.querySelector('button[name="add"], input[name="add"]');
            if (!submitButton) {
                throw new Error('Botão de submit do GLPI não encontrado');
            }

            // 3. Coletar dados do formulário
            const formData = {
                ticketId: this.ticketId,
                solution: form.querySelector('#snow-solution').value,
                closeType: form.querySelector('#snow-close-type').value,
                solutionClass: form.querySelector('#snow-solution-class').value,
                solutionCode: solutionCode.value,
                timestamp: Date.now()
            };

            // 4. Remover modal existente antes de qualquer ação
            const existingModal = document.querySelector('#snowclient-solution-modal');
            if (existingModal) {
                const bsModal = bootstrap.Modal.getInstance(existingModal);
                if (bsModal) {
                    bsModal.dispose();
                }
                existingModal.remove();
            }

            // 5. Salvar dados na sessão DEPOIS de remover a modal
            console.log('SnowClient Modal: Salvando dados na sessão:', formData);
            // Salvar no sessionStorage do navegador E na sessão do PHP
            sessionStorage.setItem('snowclient_solution_data', JSON.stringify(formData));
            
            // Enviar para a sessão PHP via AJAX
            await $.ajax({
                url: '../plugins/snowclient/ajax/save_session_data.php',
                method: 'POST',
                data: { 
                    data: JSON.stringify(formData)
                }
            });

            // 6. Remover evento de submit do formulário original
            const clonedForm = this.originalForm.cloneNode(true);
            this.originalForm.parentNode.replaceChild(clonedForm, this.originalForm);
            
            // 7. Aguardar um momento para garantir que tudo foi limpo
            await new Promise(resolve => setTimeout(resolve, 100));

            // 8. Pegar o novo botão de submit do formulário clonado
            const newSubmitButton = clonedForm.querySelector('button[name="add"], input[name="add"]');
            if (!newSubmitButton) {
                throw new Error('Botão de submit não encontrado após clonagem');
            }

            // 9. Submeter o formulário clonado
            console.log('SnowClient Modal: Submetendo formulário...');
            newSubmitButton.click();
            
        } catch (error) {
            console.error('SnowClient Modal: Erro ao processar submit:', error);
            alert('Erro ao salvar solução: ' + error.message);
            sessionStorage.removeItem('snowclient_solution_data');
        } finally {
            this.isSubmitting = false;
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
        console.log('SnowClient: Procurando formulário de solução...');
        
        // Pegar ID do ticket da URL
        const urlParams = new URLSearchParams(window.location.search);
        const ticketId = urlParams.get('id');
        
        if (!ticketId) {
            console.log('SnowClient: ID do ticket não encontrado na URL');
            return;
        }

        // Encontrar o botão "Adicionar uma solução"
        const solutionButtons = document.querySelectorAll('[data-bs-toggle="collapse"][title*="Adicionar uma solução"]');
        
        solutionButtons.forEach(button => {
            if (!button.dataset.snowclientInit) {
                console.log('SnowClient: Botão de solução encontrado, adicionando interceptador');
                button.dataset.snowclientInit = 'true';

                // Verificar se é ticket do ServiceNow
                $.ajax({
                    url: '../plugins/snowclient/ajax/check_return_button.php',
                    method: 'POST',
                    data: { ticket_id: ticketId },
                    success: function(response) {
                        if (response.success && response.show_button) {
                            console.log('SnowClient: Ticket é do ServiceNow, interceptando formulário');
                            
                            // Adicionar listener para quando o formulário for carregado
                            const targetId = button.getAttribute('data-bs-target');
                            if (targetId) {
                                const target = document.querySelector(targetId);
                                if (target) {
                                    const observer = new MutationObserver((mutations) => {
                                        mutations.forEach((mutation) => {
                                            if (mutation.addedNodes.length) {
                                                const form = target.querySelector('form');
                                                if (form && !form.dataset.snowclientInit) {
                                                    console.log('SnowClient: Formulário de solução detectado, adicionando evento');
                                                    form.dataset.snowclientInit = 'true';
                                                    
                                                    form.addEventListener('submit', function(e) {
                                                        console.log('SnowClient: Submit do formulário interceptado');
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        window.SolutionModal.open(ticketId, form);
                                                        return false;
                                                    });
                                                }
                                            }
                                        });
                                    });

                                    observer.observe(target, {
                                        childList: true,
                                        subtree: true
                                    });
                                    
                                    console.log('SnowClient: Observer configurado para detectar formulário');
                                }
                            }
                        } else {
                            console.log('SnowClient: Ticket não é do ServiceNow, modal não será ativado');
                        }
                    },
                    error: function(xhr, status, error) {
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
        console.log('SnowClient: Observer do DOM inicializado');
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