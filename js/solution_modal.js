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
    async open(originalForm, ticketId) {
        console.log('SnowClient Modal: Abrindo modal para ticket', ticketId);
        console.log('SnowClient Modal: Formulário recebido:', originalForm);
        
        try {
            this.ticketId = ticketId;
            this.originalForm = originalForm;
            
            // Verificar se a modal já existe
            let modal = document.querySelector('#snowclient-solution-modal');
            if (!modal) {
                console.log('SnowClient Modal: Modal não encontrada, carregando template...');
                
                // Descobrir o caminho correto baseado no location atual
                const baseUrl = window.location.pathname.includes('/front/') 
                    ? '../plugins/snowclient/ajax/get_solution_modal.php'
                    : './plugins/snowclient/ajax/get_solution_modal.php';
                
                console.log('SnowClient Modal: Carregando template de:', baseUrl);
                
                // Carregar template da modal
                try {
                    const response = await $.ajax({
                        url: baseUrl,
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
                    console.error('SnowClient Modal: Status:', error.status);
                    console.error('SnowClient Modal: Response:', error.responseText);
                    throw new Error('Falha ao carregar modal de solução: ' + (error.responseText || error.statusText));
                }
            }
            
            // Atualizar conteúdo da modal
            if (this.originalForm) {
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
            
            // Mostrar modal usando Bootstrap se disponível
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    console.log('SnowClient Modal: Abrindo via Bootstrap Modal');
                    const bsModal = new bootstrap.Modal(modal, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    bsModal.show();
                } else {
                    console.log('SnowClient Modal: Bootstrap não disponível, mostrando diretamente');
                    modal.style.display = 'block';
                    modal.classList.add('show');
                }
            } catch (error) {
                console.error('SnowClient Modal: Erro ao abrir modal com Bootstrap:', error);
                // Fallback: mostrar diretamente
                modal.style.display = 'block';
                modal.classList.add('show');
            }
            
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
            // Preencher campos mockados
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
        if (this.isSubmitting) {
            console.log('SnowClient Modal: Já está submetendo, ignorando');
            return;
        }
        
        try {
            this.isSubmitting = true;
            console.log('SnowClient Modal: Processando submit do formulário');

            // 1. Validar código de solução
            const solutionCode = form.querySelector('#snow-solution-code');
            if (!solutionCode?.value) {
                solutionCode.classList.add('is-invalid');
                alert('Por favor, selecione um código de solução.');
                this.isSubmitting = false;
                return;
            }

            // 2. Verificar e preparar formulário original
            if (!this.originalForm?.isConnected) {
                console.error('SnowClient Modal: Formulário original não está conectado ao DOM');
                throw new Error('Formulário original do GLPI não encontrado');
            }

            const submitButton = this.originalForm.querySelector('button[name="add"], input[name="add"]');
            if (!submitButton) {
                console.error('SnowClient Modal: Botão de submit não encontrado no formulário');
                throw new Error('Botão de submit do GLPI não encontrado');
            }

            console.log('SnowClient Modal: Formulário e botão encontrados');

            // 3. Coletar dados do formulário
            const ticketId = this.ticketId || 'unknown';
            const solutionCodeValue = solutionCode.value || '';
            
            console.log('SnowClient Modal: ticketId:', ticketId);
            console.log('SnowClient Modal: solutionCode:', solutionCodeValue);
            
            const formData = {
                ticketId: String(ticketId),
                solutionCode: String(solutionCodeValue),
                timestamp: Date.now()
            };

            console.log('SnowClient Modal: Dados coletados:', formData);
            console.log('SnowClient Modal: FormData como JSON:', JSON.stringify(formData));

            // 4. Salvar dados na sessão via AJAX
            console.log('SnowClient Modal: Salvando dados na sessão...');
            
            // Descobrir o caminho correto baseado no location atual
            const baseUrl = window.location.pathname.includes('/front/') 
                ? '../plugins/snowclient/ajax/save_session_data.php'
                : './plugins/snowclient/ajax/save_session_data.php';
            
            console.log('SnowClient Modal: URL da requisição:', baseUrl);
            
            try {
                const ajaxResponse = await $.ajax({
                    url: baseUrl,
                    method: 'POST',
                    data: { 
                        ticketId: formData.ticketId,
                        solutionCode: formData.solutionCode,
                        timestamp: formData.timestamp
                    },
                    dataType: 'json'
                });
                console.log('SnowClient Modal: Dados salvos na sessão com sucesso:', ajaxResponse);
            } catch (ajaxError) {
                console.error('SnowClient Modal: Erro ao salvar na sessão:', ajaxError);
                console.error('SnowClient Modal: Status:', ajaxError.status);
                console.error('SnowClient Modal: Response:', ajaxError.responseText);
                throw new Error('Falha ao salvar dados na sessão: ' + (ajaxError.responseText || ajaxError.statusText));
            }

            // 5. Fechar e remover modal
            console.log('SnowClient Modal: Fechando modal...');
            const existingModal = document.querySelector('#snowclient-solution-modal');
            if (existingModal) {
                try {
                    // Tentar usar Bootstrap se disponível
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const bsModal = bootstrap.Modal.getInstance(existingModal);
                        if (bsModal) {
                            bsModal.hide();
                        }
                    }
                } catch (e) {
                    console.warn('SnowClient Modal: Erro ao fechar via Bootstrap, removendo diretamente');
                }
                // Remover do DOM
                existingModal.remove();
            }

            // 6. Aguardar um momento para garantir que a modal fechou
            await new Promise(resolve => setTimeout(resolve, 300));

            // 7. Marcar formulário e submeter
            console.log('SnowClient Modal: Marcando formulário para submit...');
            this.originalForm.dataset.snowclientSubmitting = 'true';
            
            console.log('SnowClient Modal: Tentando submeter formulário...');
            console.log('SnowClient Modal: Submit button:', submitButton);
            console.log('SnowClient Modal: Submit button type:', submitButton.type);
            console.log('SnowClient Modal: Submit button name:', submitButton.name);
            
            // Tentar múltiplas formas de submeter
            try {
                // Método 1: Click direto no botão
                submitButton.click();
                console.log('SnowClient Modal: Click executado no botão');
            } catch (e) {
                console.error('SnowClient Modal: Erro ao clicar no botão:', e);
                
                // Método 2: Disparar evento de click
                try {
                    const clickEvent = new MouseEvent('click', {
                        bubbles: true,
                        cancelable: true,
                        view: window
                    });
                    submitButton.dispatchEvent(clickEvent);
                    console.log('SnowClient Modal: Evento de click disparado');
                } catch (e2) {
                    console.error('SnowClient Modal: Erro ao disparar evento:', e2);
                    
                    // Método 3: Submit direto no form
                    try {
                        this.originalForm.submit();
                        console.log('SnowClient Modal: Form.submit() executado');
                    } catch (e3) {
                        console.error('SnowClient Modal: Erro ao submeter form:', e3);
                    }
                }
            }
            
            console.log('SnowClient Modal: Submit concluído');
            
        } catch (error) {
            console.error('SnowClient Modal: Erro ao processar submit:', error);
            alert('Erro ao salvar solução: ' + error.message);
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
        // Encontrar o formulário de solução
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Pular se já foi inicializado
            if (form.dataset.snowclientInit) {
                return;
            }
            
            // DETECÇÃO ESPECÍFICA DE FORMULÁRIO DE SOLUÇÃO:
            // 1. Deve ter textarea com name="content"
            const contentField = form.querySelector('textarea[name="content"]');
            if (!contentField) {
                return;
            }
            
            // 2. Deve ter select de tipo de solução (solutiontypes_id)
            const solutionTypeSelect = form.querySelector('select[name="solutiontypes_id"]');
            if (!solutionTypeSelect) {
                return; // Se não tem campo de tipo de solução, não é formulário de solução
            }
            
            // 3. NÃO deve ter botão de followup
            const hasFollowupButton = form.querySelector('button[name="add_followup"], input[name="add_followup"]');
            if (hasFollowupButton) {
                console.log('SnowClient: Formulário de followup detectado, ignorando');
                return;
            }
            
            // 4. NÃO deve ter botão de tarefa (addtask)
            const hasTaskButton = form.querySelector('button[name="addtask"], input[name="addtask"]');
            if (hasTaskButton) {
                console.log('SnowClient: Formulário de tarefa detectado, ignorando');
                return;
            }
            
            // 5. NÃO deve ter campo de validation_answer (validação)
            const hasValidationAnswer = form.querySelector('[name="validation_answer"]');
            if (hasValidationAnswer) {
                console.log('SnowClient: Formulário de validação detectado, ignorando');
                return;
            }
            
            // 6. Deve ter botão "add" para adicionar solução
            const hasAddButton = form.querySelector('button[name="add"], input[name="add"]');
            if (!hasAddButton) {
                return;
            }
            
            console.log('SnowClient: ✅ Form de SOLUÇÃO detectado com sucesso!');
            console.log('SnowClient: - Tem textarea content: sim');
            console.log('SnowClient: - Tem select solutiontypes_id: sim');
            console.log('SnowClient: - Tem botão add: sim');
            console.log('SnowClient: - É followup: não');
            console.log('SnowClient: - É tarefa: não');
            console.log('SnowClient: - É validação: não');
            
            // Marcar formulário como inicializado
            form.dataset.snowclientInit = 'true';
            
            // Pegar ID do ticket da URL
            const urlParams = new URLSearchParams(window.location.search);
            const ticketId = urlParams.get('id');
            
            if (!ticketId) {
                console.log('SnowClient: ID do ticket não encontrado na URL');
                return;
            }
            
            // Verificar se é um ticket do ServiceNow via Ajax antes de adicionar o interceptador
            $.ajax({
                url: '../plugins/snowclient/ajax/check_return_button.php',
                method: 'POST',
                data: { ticket_id: ticketId },
                success: function(response) {
                    if (response.success && response.show_button) {
                        console.log('SnowClient: Ticket confirmado como ServiceNow, adicionando interceptador de submit');
                        
                        // Adicionar interceptador de submit
                        form.addEventListener('submit', function(e) {
                            // Verificar se está sendo submetido pela modal
                            if (form.dataset.snowclientSubmitting === 'true') {
                                console.log('SnowClient: Submit sendo feito pela modal, permitindo');
                                delete form.dataset.snowclientSubmitting;
                                return true;
                            }
                            
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            console.log('SnowClient: Submit interceptado, abrindo modal');
                            window.SolutionModal.open(form, ticketId).catch(function(error) {
                                console.error('SnowClient: Erro ao abrir modal:', error);
                                alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
                            });
                            
                            return false;
                        }, true);
                        
                        console.log('SnowClient: Interceptador adicionado com sucesso');
                    } else {
                        console.log('SnowClient: Ticket não é do ServiceNow, ignorando');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('SnowClient: Erro ao verificar ticket:', error);
                }
            });
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