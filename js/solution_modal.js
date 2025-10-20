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

// Inicializar quando documento estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.SolutionModal = new SolutionModal();
    
    // Interceptar submit do form de solução
    const solutionForm = document.querySelector('#solution-container form');
    if (solutionForm) {
        solutionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const ticketId = new URLSearchParams(window.location.search).get('id');
            await window.SolutionModal.open(ticketId);
        });
    }
});