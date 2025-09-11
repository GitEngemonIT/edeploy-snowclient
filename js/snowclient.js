/* ServiceNow Client Plugin JavaScript */

// Funções globais do SnowClient
var SnowClient = {
    
    // Configurações
    config: {
        debug: true // Ativando debug temporariamente
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
            window.location.href.includes('/Ticket/') ||
            $('body').hasClass('ticket-form')) {
            
            self.log('Página de ticket detectada');
            
            // Esperar um pouco e verificar se deve mostrar o botão baseado na lógica PHP
            setTimeout(function() {
                if (typeof window.snowclient_show_return_button !== 'undefined' && 
                    window.snowclient_show_return_button === true) {
                    self.log('Deve mostrar botão de devolução - ticket integrado do ServiceNow');
                    self.addReturnButton();
                } else {
                    self.log('Ticket não integrado ou não atende critérios - não mostra botão');
                }
            }, 500);
            
            // Tentar novamente após delay maior
            setTimeout(function() {
                if (typeof window.snowclient_show_return_button !== 'undefined' && 
                    window.snowclient_show_return_button === true && 
                    $('#snowclient-return-button').length === 0) {
                    self.log('Segunda tentativa - adicionando botão');
                    self.addReturnButton();
                }
            }, 1500);
            
            // Também tentar quando a página mudar (AJAX)
            $(document).ajaxComplete(function() {
                setTimeout(function() {
                    if (typeof window.snowclient_show_return_button !== 'undefined' && 
                        window.snowclient_show_return_button === true && 
                        $('#snowclient-return-button').length === 0) {
                        self.addReturnButton();
                    }
                }, 300);
            });
        }
    },
    
    // Adicionar botão de devolução
    addReturnButton: function() {
        var self = this;
        
        // Se já existe, não adicionar novamente
        if ($('#snowclient-return-button').length > 0) {
            self.log('Botão já existe');
            return;
        }
        
        // Buscar por diversos seletores possíveis, priorizando menu lateral direito próximo ao Salvar
        var targetElements = [
            // Botões específicos do GLPI
            'input[name="update"]',
            'input[value="Salvar"]',
            'button:contains("Salvar")', 
            'input[type="submit"][value*="Salvar"]',
            '.btn-success:contains("Salvar")',
            // Seletores específicos da interface do GLPI
            '.main-form input[type="submit"]:last',
            '.tab_cadre_fixe input[type="submit"]:last',
            '.center input[type="submit"]:last',
            // Área de botões no final do formulário
            '.form_buttons',
            '.submit',
            // Menu lateral e ações
            '.right-menu .btn:last',
            '.sidebar-right .btn:last',
            '.ticket-actions .btn:last',
            // Rodapé e área de formulário
            '.card-footer .btn-group:last',
            '.card-footer .d-flex:last',
            '.form-buttons .btn:last',
            '.main-form .btn:last',
            // Fallbacks tradicionais
            '.form-buttons .btn-primary:last',
            '.card-footer .btn-primary:last',
            'input[type="submit"]:last',
            // Último recurso - qualquer botão
            '.btn-primary:last',
            '.btn:last'
        ];
        
        var targetFound = false;
        
        for (var i = 0; i < targetElements.length; i++) {
            var $target = $(targetElements[i]);
            if ($target.length > 0) {
                self.log('Encontrado elemento: ' + targetElements[i]);
                
                // Verificar se é um ticket válido para devolução
                var ticketId = this.getTicketId();
                if (ticketId > 0) {
                    var returnButton = '<button type="button" class="btn btn-warning me-2" id="snowclient-return-button" data-ticket-id="' + ticketId + '" style="margin: 5px;">' +
                        '<i class="fas fa-undo"></i> Devolver ao ServiceNow' +
                        '</button>';
                    
                    $target.after(returnButton);
                    self.log('Botão adicionado após: ' + targetElements[i]);
                    targetFound = true;
                    break;
                }
            }
        }
        
        if (!targetFound) {
            self.log('Nenhum elemento alvo encontrado. Tentando inserir em local genérico...');
            
            // Última tentativa - inserir em qualquer local visível
            var ticketId = this.getTicketId();
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
        
        // Primeiro, verificar se temos a variável global definida pelo PHP
        if (typeof window.snowclient_ticket_id !== 'undefined' && window.snowclient_ticket_id > 0) {
            ticketId = parseInt(window.snowclient_ticket_id);
            this.log('Ticket ID obtido da variável global: ' + ticketId);
            return ticketId;
        }
        
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
                                'Este chamado será resolvido no GLPI e transferido de volta ao ServiceNow na fila de retorno configurada, SEM ser resolvido lá.' +
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
