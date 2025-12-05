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
        console.log('edeploysnowclient Modal: Iniciando inicialização...');
        if (this.initialized) {
            console.log('edeploysnowclient Modal: Já inicializado');
            return;
        }

        try {
            // Carregar template da modal
            console.log('edeploysnowclient Modal: Carregando template...');
            const response = await fetch('../plugins/edeploysnowclient/ajax/get_solution_modal.php');
            if (!response.ok) throw new Error('Falha ao carregar template');
            this.modalHtml = await response.text();
            
            // Remover modal anterior se existir
            const oldModal = document.querySelector('#edeploysnowclient-solution-modal');
            if (oldModal) {
                console.log('edeploysnowclient Modal: Removendo modal anterior');
                oldModal.remove();
            }
            
            // Adicionar ao DOM
            console.log('edeploysnowclient Modal: Adicionando ao DOM');
            document.body.insertAdjacentHTML('beforeend', this.modalHtml);
            
            // Inicializar eventos
            this.bindEvents();
            
            this.initialized = true;
            console.log('edeploysnowclient Modal: Inicialização completa');
        } catch (error) {
            console.error('edeploysnowclient Modal: Erro na inicialização:', error);
            throw error;
        }
    }

    /**
     * Vincular eventos da modal
     */
    bindEvents() {
        console.log('edeploysnowclient Modal: Vinculando eventos');
        const modal = document.querySelector('#edeploysnowclient-solution-modal');
        const form = modal.querySelector('form');
        const cancelBtn = modal.querySelector('.js-cancel');
        
        // Fechar modal
        cancelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('edeploysnowclient Modal: Botão cancelar clicado');
            this.close();
        });
        
        // Submit do form
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            console.log('edeploysnowclient Modal: Form da modal submetido');
            this.handleSubmit(form);
        });
        
        // Fechar ao clicar fora
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                console.log('edeploysnowclient Modal: Clique fora da modal');
                this.close();
            }
        });
    }

    /**
     * Abrir modal para um ticket específico
     */
    async open(originalForm, ticketId) {
        console.log('edeploysnowclient Modal: Abrindo modal para ticket', ticketId);
        console.log('edeploysnowclient Modal: Formulário recebido:', originalForm);
        
        try {
            this.ticketId = ticketId;
            this.originalForm = originalForm;
            
            // Verificar se a modal já existe
            let modal = document.querySelector('#edeploysnowclient-solution-modal');
            if (!modal) {
                console.log('edeploysnowclient Modal: Modal não encontrada, carregando template...');
                
                // Descobrir o caminho correto baseado no location atual
                const baseUrl = window.location.pathname.includes('/front/') 
                    ? '../plugins/edeploysnowclient/ajax/get_solution_modal.php'
                    : './plugins/edeploysnowclient/ajax/get_solution_modal.php';
                
                console.log('edeploysnowclient Modal: Carregando template de:', baseUrl);
                
                // Carregar template da modal
                try {
                    const response = await $.ajax({
                        url: baseUrl,
                        method: 'GET'
                    });
                    
                    // Adicionar modal ao DOM
                    $('body').append(response);
                    
                    // Referenciar a modal recém-adicionada
                    modal = document.querySelector('#edeploysnowclient-solution-modal');
                    
                    if (!modal) {
                        throw new Error('Modal não encontrada após inserção no DOM');
                    }
                } catch (error) {
                    console.error('edeploysnowclient Modal: Erro ao carregar template:', error);
                    console.error('edeploysnowclient Modal: Status:', error.status);
                    console.error('edeploysnowclient Modal: Response:', error.responseText);
                    throw new Error('Falha ao carregar modal de solução: ' + (error.responseText || error.statusText));
                }
            }
            
            // Atualizar conteúdo da modal
            if (this.originalForm) {
                // Preencher campos readonly com valores mockados do ServiceNow
                const closeTypeInput = modal.querySelector('#snow-close-type');
                if (closeTypeInput) {
                    closeTypeInput.value = 'Remoto';  // Valor mockado para u_bk_tipo_encerramento
                }

                const solutionClass = modal.querySelector('#snow-solution-class');
                if (solutionClass) {
                    solutionClass.value = 'Aplicação (Software)';  // Valor mockado para u_bk_ic_impactado
                }

                console.log('edeploysnowclient Modal: Campos mockados preenchidos com sucesso');
            } else {
                console.warn('edeploysnowclient Modal: Formulário original não encontrado');
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
                    console.log('edeploysnowclient Modal: Abrindo via Bootstrap Modal');
                    const bsModal = new bootstrap.Modal(modal, {
                        backdrop: 'static',
                        keyboard: false
                    });
                    bsModal.show();
                } else {
                    console.log('edeploysnowclient Modal: Bootstrap não disponível, mostrando diretamente');
                    modal.style.display = 'block';
                    modal.classList.add('show');
                }
            } catch (error) {
                console.error('edeploysnowclient Modal: Erro ao abrir modal com Bootstrap:', error);
                // Fallback: mostrar diretamente
                modal.style.display = 'block';
                modal.classList.add('show');
            }
            
            console.log('edeploysnowclient Modal: Modal aberta com sucesso');
            
        } catch (error) {
            console.error('edeploysnowclient Modal: Erro ao abrir modal:', error);
            alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
            throw error;
        }
    }

    /**
     * Inicializar eventos específicos da modal
     */
    bindModalEvents(modal) {
        console.log('edeploysnowclient Modal: Inicializando eventos da modal');
        
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
            
            console.log('edeploysnowclient Modal: Eventos inicializados com sucesso');
            
        } catch (error) {
            console.error('edeploysnowclient Modal: Erro ao inicializar eventos:', error);
            throw new Error('Falha ao inicializar eventos da modal');
        }
    }

    /**
     * Preencher campos somente leitura
     */
    fillReadOnlyFields() {        
        console.log('edeploysnowclient Modal: Preenchendo campos readonly');
        try {
            // Preencher campos mockados
            document.querySelector('#snow-close-type').value = 'Remoto';
            document.querySelector('#snow-solution-class').value = 'Aplicação (Software)';
            
            console.log('edeploysnowclient Modal: Campos preenchidos');
        } catch (error) {
            console.error('edeploysnowclient Modal: Erro ao preencher campos:', error);
        }
    }

    /**
     * Fechar modal
     */
    close() {
        console.log('edeploysnowclient Modal: Fechando modal');
        const modal = document.querySelector('#edeploysnowclient-solution-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Handler para o submit do formulário da modal
     */
    async handleSubmit(form) {
        if (this.isSubmitting) {
            console.log('edeploysnowclient Modal: Já está submetendo, ignorando');
            return;
        }
        
        try {
            this.isSubmitting = true;
            console.log('edeploysnowclient Modal: Processando submit do formulário');

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
                console.error('edeploysnowclient Modal: Formulário original não está conectado ao DOM');
                throw new Error('Formulário original do GLPI não encontrado');
            }

            const submitButton = this.originalForm.querySelector('button[name="add"], input[name="add"]');
            if (!submitButton) {
                console.error('edeploysnowclient Modal: Botão de submit não encontrado no formulário');
                throw new Error('Botão de submit do GLPI não encontrado');
            }

            console.log('edeploysnowclient Modal: Formulário e botão encontrados');

            // 3. Coletar dados do formulário modal
            const ticketId = this.ticketId || 'unknown';
            const closeCode = modal.querySelector('#snow-close-code');
            const closeType = modal.querySelector('#snow-close-type');
            const solutionClass = modal.querySelector('#snow-solution-class');
            const solutionCodeSelect = modal.querySelector('#snow-solution-code');
            
            console.log('edeploysnowclient Modal: ticketId:', ticketId);
            console.log('edeploysnowclient Modal: close_code:', closeCode?.value);
            console.log('edeploysnowclient Modal: u_bk_tipo_encerramento:', closeType?.value);
            console.log('edeploysnowclient Modal: u_bk_ic_impactado:', solutionClass?.value);
            console.log('edeploysnowclient Modal: u_bk_type_of_failure:', solutionCodeSelect?.value);
            
            const formData = {
                ticketId: String(ticketId),
                close_code: closeCode?.value || 'Definitiva',
                u_bk_tipo_encerramento: closeType?.value || 'Remoto',
                u_bk_ic_impactado: solutionClass?.value || 'Aplicação (Software)',
                u_bk_type_of_failure: solutionCodeSelect?.value || '',
                timestamp: Date.now()
            };

            console.log('edeploysnowclient Modal: Dados coletados:', formData);
            console.log('edeploysnowclient Modal: FormData como JSON:', JSON.stringify(formData));

            // 4. Salvar dados na sessão via AJAX
            console.log('edeploysnowclient Modal: Salvando dados na sessão...');
            
            // Descobrir o caminho correto baseado no location atual
            const baseUrl = window.location.pathname.includes('/front/') 
                ? '../plugins/edeploysnowclient/ajax/save_session_data.php'
                : './plugins/edeploysnowclient/ajax/save_session_data.php';
            
            console.log('edeploysnowclient Modal: URL da requisição:', baseUrl);
            
            try {
                const ajaxResponse = await $.ajax({
                    url: baseUrl,
                    method: 'POST',
                    data: { 
                        ticketId: formData.ticketId,
                        close_code: formData.close_code,
                        u_bk_tipo_encerramento: formData.u_bk_tipo_encerramento,
                        u_bk_ic_impactado: formData.u_bk_ic_impactado,
                        u_bk_type_of_failure: formData.u_bk_type_of_failure,
                        timestamp: formData.timestamp
                    },
                    dataType: 'json'
                });
                console.log('edeploysnowclient Modal: Dados salvos na sessão com sucesso:', ajaxResponse);
            } catch (ajaxError) {
                console.error('edeploysnowclient Modal: Erro ao salvar na sessão:', ajaxError);
                console.error('edeploysnowclient Modal: Status:', ajaxError.status);
                console.error('edeploysnowclient Modal: Response:', ajaxError.responseText);
                throw new Error('Falha ao salvar dados na sessão: ' + (ajaxError.responseText || ajaxError.statusText));
            }

            // 5. Fechar e remover modal
            console.log('edeploysnowclient Modal: Fechando modal...');
            const existingModal = document.querySelector('#edeploysnowclient-solution-modal');
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
                    console.warn('edeploysnowclient Modal: Erro ao fechar via Bootstrap, removendo diretamente');
                }
                // Remover do DOM
                existingModal.remove();
            }

            // 6. Aguardar um momento para garantir que a modal fechou
            await new Promise(resolve => setTimeout(resolve, 300));

            // 7. Marcar formulário e submeter
            console.log('edeploysnowclient Modal: Marcando formulário para submit...');
            this.originalForm.dataset.edeploysnowclientSubmitting = 'true';
            
            console.log('edeploysnowclient Modal: Tentando submeter formulário...');
            console.log('edeploysnowclient Modal: Submit button:', submitButton);
            console.log('edeploysnowclient Modal: Submit button type:', submitButton.type);
            console.log('edeploysnowclient Modal: Submit button name:', submitButton.name);
            
            // Tentar múltiplas formas de submeter
            try {
                // Método 1: Click direto no botão
                submitButton.click();
                console.log('edeploysnowclient Modal: Click executado no botão');
            } catch (e) {
                console.error('edeploysnowclient Modal: Erro ao clicar no botão:', e);
                
                // Método 2: Disparar evento de click
                try {
                    const clickEvent = new MouseEvent('click', {
                        bubbles: true,
                        cancelable: true,
                        view: window
                    });
                    submitButton.dispatchEvent(clickEvent);
                    console.log('edeploysnowclient Modal: Evento de click disparado');
                } catch (e2) {
                    console.error('edeploysnowclient Modal: Erro ao disparar evento:', e2);
                    
                    // Método 3: Submit direto no form
                    try {
                        this.originalForm.submit();
                        console.log('edeploysnowclient Modal: Form.submit() executado');
                    } catch (e3) {
                        console.error('edeploysnowclient Modal: Erro ao submeter form:', e3);
                    }
                }
            }
            
            console.log('edeploysnowclient Modal: Submit concluído');
            
        } catch (error) {
            console.error('edeploysnowclient Modal: Erro ao processar submit:', error);
            alert('Erro ao salvar solução: ' + error.message);
            this.isSubmitting = false;
        }
    }
}

// Função de inicialização que será chamada quando necessário
function initSolutionModal() {
    console.log('edeploysnowclient: Inicializando manipulador da modal de solução...');
    
    if (!window.SolutionModal) {
        window.SolutionModal = new SolutionModal();
    }
    
    // Função para interceptar o formulário de solução
    function interceptSolutionForm() {
        // Encontrar o formulário de solução
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Pular se já foi inicializado
            if (form.dataset.edeploysnowclientInit) {
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
                console.log('edeploysnowclient: Formulário de followup detectado, ignorando');
                return;
            }
            
            // 4. NÃO deve ter botão de tarefa (addtask)
            const hasTaskButton = form.querySelector('button[name="addtask"], input[name="addtask"]');
            if (hasTaskButton) {
                console.log('edeploysnowclient: Formulário de tarefa detectado, ignorando');
                return;
            }
            
            // 5. NÃO deve ter campo de validation_answer (validação)
            const hasValidationAnswer = form.querySelector('[name="validation_answer"]');
            if (hasValidationAnswer) {
                console.log('edeploysnowclient: Formulário de validação detectado, ignorando');
                return;
            }
            
            // 6. Deve ter botão "add" para adicionar solução
            const hasAddButton = form.querySelector('button[name="add"], input[name="add"]');
            if (!hasAddButton) {
                return;
            }
            
            console.log('edeploysnowclient: ✅ Form de SOLUÇÃO detectado com sucesso!');
            console.log('edeploysnowclient: - Tem textarea content: sim');
            console.log('edeploysnowclient: - Tem select solutiontypes_id: sim');
            console.log('edeploysnowclient: - Tem botão add: sim');
            console.log('edeploysnowclient: - É followup: não');
            console.log('edeploysnowclient: - É tarefa: não');
            console.log('edeploysnowclient: - É validação: não');
            
            // Marcar formulário como inicializado
            form.dataset.edeploysnowclientInit = 'true';
            
            // Pegar ID do ticket da URL
            const urlParams = new URLSearchParams(window.location.search);
            const ticketId = urlParams.get('id');
            
            if (!ticketId) {
                console.log('edeploysnowclient: ID do ticket não encontrado na URL');
                return;
            }
            
            // Verificar se é um ticket do ServiceNow via Ajax antes de adicionar o interceptador
            $.ajax({
                url: '../plugins/edeploysnowclient/ajax/check_return_button.php',
                method: 'POST',
                data: { ticket_id: ticketId },
                success: function(response) {
                    if (response.success && response.show_button) {
                        console.log('edeploysnowclient: Ticket confirmado como ServiceNow, adicionando interceptador de submit');
                        
                        // Adicionar interceptador de submit
                        form.addEventListener('submit', function(e) {
                            // Verificar se está sendo submetido pela modal
                            if (form.dataset.edeploysnowclientSubmitting === 'true') {
                                console.log('edeploysnowclient: Submit sendo feito pela modal, permitindo');
                                delete form.dataset.edeploysnowclientSubmitting;
                                return true;
                            }
                            
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            console.log('edeploysnowclient: Submit interceptado, abrindo modal');
                            window.SolutionModal.open(form, ticketId).catch(function(error) {
                                console.error('edeploysnowclient: Erro ao abrir modal:', error);
                                alert('Erro ao abrir modal de solução. Por favor, tente novamente.');
                            });
                            
                            return false;
                        }, true);
                        
                        console.log('edeploysnowclient: Interceptador adicionado com sucesso');
                    } else {
                        console.log('edeploysnowclient: Ticket não é do ServiceNow, ignorando');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('edeploysnowclient: Erro ao verificar ticket:', error);
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
        console.log('edeploysnowclient: Documento pronto, inicializando...');
        initSolutionModal();
    });
} else {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('edeploysnowclient: DOM carregado, inicializando...');
        initSolutionModal();
    });
}

// Reinicializar após carregamentos AJAX
$(document).ajaxComplete(function() {
    console.log('edeploysnowclient: Ajax completado, reinicializando...');
    initSolutionModal();
});
