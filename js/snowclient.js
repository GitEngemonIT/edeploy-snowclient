/* ServiceNow Client Plugin JavaScript */

// Funções globais do SnowClient
var SnowClient = {
    
    // Configurações
    config: {
        debug: false // Desabilitando debug temporariamente
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
        
        // Verificar se estamos na página de ticket
        this.checkTicketPage();
    },
    
    // Verificar se estamos em uma página de ticket
    checkTicketPage: function() {
        var self = this;
        
        // Verificar pela URL
        if (window.location.href.includes('ticket.form.php') || 
            window.location.href.includes('/Ticket/')) {
            
            self.log('Página de ticket detectada');
            
            // Flag para evitar múltiplas verificações
            if (self.buttonCheckInProgress) {
                self.log('Verificação já em andamento, ignorando...');
                return;
            }
            
            // Fazer verificação via AJAX para saber se deve mostrar o botão
            setTimeout(function() {
                self.checkIfShouldShowButton();
            }, 1000);
        }
    },

    // Verificar via AJAX se deve mostrar o botão
    checkIfShouldShowButton: function() {
        var self = this;
        var ticketId = this.getTicketId();
        
        if (ticketId <= 0) {
            self.log('ID do ticket inválido: ' + ticketId);
            return;
        }
        
        // Verificar se já foi processado para este ticket
        if (self.processedTickets && self.processedTickets.includes(ticketId)) {
            self.log('Ticket ' + ticketId + ' já foi processado, ignorando...');
            return;
        }
        
        // Marcar como em progresso
        self.buttonCheckInProgress = true;
        
        self.log('Verificando se deve mostrar botão para ticket: ' + ticketId);
        
        // Fazer requisição AJAX para verificar
        $.ajax({
            url: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '') + '/plugins/snowclient/ajax/check_return_button.php',
            method: 'POST',
            data: {
                ticket_id: ticketId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.show_button) {
                    self.log('Deve mostrar botão - adicionando...');
                    window.snowclient_show_return_button = true;
                    window.snowclient_ticket_id = ticketId;
                    self.addReturnButton();
                } else {
                    self.log('Não deve mostrar botão: ' + (response.message || 'critérios não atendidos'));
                }
                
                // Marcar ticket como processado
                if (!self.processedTickets) {
                    self.processedTickets = [];
                }
                self.processedTickets.push(ticketId);
            },
            error: function(xhr, status, error) {
                self.log('Erro ao verificar botão: ' + error);
                // Em caso de erro, não mostrar o botão por segurança
            },
            complete: function() {
                // Marcar como não está mais em progresso
                self.buttonCheckInProgress = false;
            }
        });
    },
    
    // Adicionar botão de devolução
    addReturnButton: function() {
        var self = this;
        
        var ticketId = window.snowclient_ticket_id || this.getTicketId();
        if (ticketId <= 0) {
            self.log('ID do ticket inválido');
            return;
        }
        
        // Se já existe, não adicionar novamente
        if ($('#snowclient-return-button').length > 0) {
            self.log('Botão já existe');
            return;
        }
        
        // Buscar por diversos seletores possíveis
        var targetElements = [
            '.form-buttons .btn-primary:last',
            '.card-footer .btn-primary:last',
            'input[name="update"]',
            'input[type="submit"]:last',
            '.main-form .btn:last'
        ];
        
        var targetFound = false;
        
        for (var i = 0; i < targetElements.length; i++) {
            var $target = $(targetElements[i]);
            if ($target.length > 0) {
                self.log('Encontrado elemento: ' + targetElements[i]);
                
                // Usar o ticket ID já validado
                var returnButton = '<button type="button" class="btn btn-warning ms-2 me-2" id="snowclient-return-button" data-ticket-id="' + ticketId + '">' +
                    '<i class="fas fa-undo"></i> Devolver ao ServiceNow' +
                    '</button>';
                
                $target.after(returnButton);
                self.log('Botão adicionado após: ' + targetElements[i]);
                targetFound = true;
                break;
            }
        }
        
        if (!targetFound) {
            self.log('Nenhum elemento alvo encontrado. Tentando inserir em local genérico...');
            
            // Última tentativa - inserir em qualquer local visível
            if (ticketId > 0) {
                var genericLocation = $('.card-header, .page-header, .header-title').first();
                if (genericLocation.length > 0) {
                    var returnButton = '<div style="margin: 10px 0;"><button type="button" class="btn btn-warning" id="snowclient-return-button" data-ticket-id="' + ticketId + '">' +
                        '<i class="fas fa-undo"></i> Devolver ao ServiceNow' +
                        '</button></div>';
                    
                    genericLocation.after(returnButton);
                    self.log('Botão inserido em local genérico');
                }
            }
        }
        
        // Adicionar event handler se o botão foi criado
        if ($('#snowclient-return-button').length > 0) {
            this.setupReturnButtonHandler();
        }
    },
    
    // Obter ID do ticket da URL ou página
    getTicketId: function() {
        var ticketId = 0;
        
        // Tentar extrair da URL
        var urlMatch = window.location.href.match(/id=(\d+)/);
        if (urlMatch) {
            ticketId = parseInt(urlMatch[1]);
        }
        
        // Tentar extrair de input hidden
        var hiddenInput = $('input[name="id"]').val();
        if (hiddenInput && hiddenInput > 0) {
            ticketId = parseInt(hiddenInput);
        }
        
        this.log('Ticket ID encontrado: ' + ticketId);
        return ticketId;
    },
    
    // Configurar handler do botão
    setupReturnButtonHandler: function() {
        var self = this;
        
        $(document).off('click', '#snowclient-return-button').on('click', '#snowclient-return-button', function(e) {
            e.preventDefault();
            var ticketId = $(this).data('ticket-id');
            self.log('Botão clicado para ticket: ' + ticketId);
            self.showReturnModal(ticketId);
        });
    },
    
    // Mostrar modal de devolução
    showReturnModal: function(ticketId) {
        var self = this;
        var modalHtml = '<div class="modal fade" id="snowclientReturnModal" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
                '<div class="modal-content">' +
                    '<div class="modal-header bg-warning text-white">' +
                        '<h5 class="modal-title">Devolver Chamado ao ServiceNow</h5>' +
                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<form id="snowclient-return-form">' +
                            '<input type="hidden" name="ticket_id" value="' + ticketId + '">' +
                            '<div class="mb-3">' +
                                '<label for="return_reason" class="form-label">Motivo da Devolução *</label>' +
                                '<textarea class="form-control" id="return_reason" name="return_reason" rows="4" required placeholder="Descreva o motivo pelo qual este chamado está sendo devolvido ao ServiceNow..."></textarea>' +
                            '</div>' +
                            '<div class="alert alert-info">' +
                                '<i class="fas fa-info-circle"></i> ' +
                                'Este chamado será resolvido no GLPI e transferido de volta ao ServiceNow na fila configurada, SEM ser resolvido lá.' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>' +
                        '<button type="button" class="btn btn-warning" id="confirm-return">Devolver Chamado</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        // Remover modal existente se houver
        $('#snowclientReturnModal').remove();
        
        // Adicionar modal ao body
        $('body').append(modalHtml);
        
        // Mostrar modal
        $('#snowclientReturnModal').modal('show');
        
        // Handler para confirmação
        $('#confirm-return').click(function() {
            var reason = $('#return_reason').val().trim();
            if (!reason) {
                alert('Por favor, informe o motivo da devolução.');
                return;
            }
            
            $(this).prop('disabled', true).text('Devolvendo...');
            
            // AJAX para processar a devolução
            $.ajax({
                url: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '') + '/plugins/snowclient/ajax/return_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    return_reason: reason
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Chamado devolvido com sucesso ao ServiceNow!');
                        $('#snowclientReturnModal').modal('hide');
                        location.reload(); // Recarregar página para mostrar status atualizado
                    } else {
                        alert('Erro ao devolver chamado: ' + response.message);
                        $('#confirm-return').prop('disabled', false).text('Devolver Chamado');
                    }
                },
                error: function() {
                    alert('Erro de comunicação. Tente novamente.');
                    $('#confirm-return').prop('disabled', false).text('Devolver Chamado');
                }
            });
        });
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
