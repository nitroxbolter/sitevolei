// Comunidade do Vôlei - JavaScript Principal

$(document).ready(function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts após 5 segundos
    $('.alert').each(function() {
        var alert = $(this);
        setTimeout(function() {
            alert.fadeOut();
        }, 5000);
    });

    // Confirmar presença em jogo
    $('.confirmar-presenca').on('click', function(e) {
        e.preventDefault();
        var jogoId = $(this).data('jogo-id');
        var status = $(this).data('status');
        
        if (confirm('Tem certeza que deseja ' + (status === 'confirmado' ? 'confirmar' : 'cancelar') + ' sua presença neste jogo?')) {
            $.ajax({
                url: 'ajax/confirmar_presenca.php',
                method: 'POST',
                data: {
                    jogo_id: jogoId,
                    status: status === 'confirmado' ? 'Confirmado' : 'Ausente'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao processar solicitação');
                }
            });
        }
    });

    // Validação de formulários
    $('form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;

        // Validar campos obrigatórios
        form.find('[required]').each(function() {
            var field = $(this);
            if (field.val().trim() === '') {
                field.addClass('is-invalid');
                isValid = false;
            } else {
                field.removeClass('is-invalid');
            }
        });

        // Validar email
        var emailField = form.find('input[type="email"]');
        if (emailField.length && emailField.val()) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.val())) {
                emailField.addClass('is-invalid');
                isValid = false;
            }
        }

        // Validar confirmação de senha
        var senhaField = form.find('input[name="senha"]');
        var confirmarSenhaField = form.find('input[name="confirmar_senha"]');
        if (senhaField.length && confirmarSenhaField.length) {
            if (senhaField.val() !== confirmarSenhaField.val()) {
                confirmarSenhaField.addClass('is-invalid');
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            showAlert('Por favor, corrija os erros no formulário', 'danger');
        }
    });

    // Animações de entrada
    $('.fade-in-up').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
    });

    // Smooth scroll para links internos
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 80
            }, 1000);
        }
    });

    // Atualizar contador de tempo restante
    updateTimeCounters();
    setInterval(updateTimeCounters, 60000); // Atualizar a cada minuto
});

// Função para mostrar alertas
function showAlert(message, type = 'info') {
    var alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.container').first().prepend(alertHtml);
    
    // Auto-hide após 5 segundos
    setTimeout(function() {
        $('.alert').first().fadeOut();
    }, 5000);
}

// Função para atualizar contadores de tempo
function updateTimeCounters() {
    $('.time-counter').each(function() {
        var $this = $(this);
        var dataTime = $this.data('time');
        var timeRemaining = getTimeRemaining(dataTime);
        $this.text(timeRemaining);
    });
}

// Função para calcular tempo restante
function getTimeRemaining(endtime) {
    var t = Date.parse(endtime) - Date.parse(new Date());
    var seconds = Math.floor((t / 1000) % 60);
    var minutes = Math.floor((t / 1000 / 60) % 60);
    var hours = Math.floor((t / (1000 * 60 * 60)) % 24);
    var days = Math.floor(t / (1000 * 60 * 60 * 24));

    if (t < 0) {
        return 'Jogo já aconteceu';
    }

    if (days > 0) {
        return days + ' dia(s) restante(s)';
    } else if (hours > 0) {
        return hours + ' hora(s) restante(s)';
    } else if (minutes > 0) {
        return minutes + ' minuto(s) restante(s)';
    } else {
        return seconds + ' segundo(s) restante(s)';
    }
}

// Função para confirmar presença via AJAX
function confirmarPresenca(jogoId, status) {
    $.ajax({
        url: 'ajax/confirmar_presenca.php',
        method: 'POST',
        data: {
            jogo_id: jogoId,
            status: status
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao processar solicitação', 'danger');
        }
    });
}

// Função para buscar jogos
function buscarJogos(filtros) {
    $.ajax({
        url: 'ajax/buscar_jogos.php',
        method: 'POST',
        data: filtros,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#jogos-container').html(response.html);
            } else {
                showAlert('Erro ao buscar jogos', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao buscar jogos', 'danger');
        }
    });
}

// Função para filtrar grupos
function filtrarGrupos(filtros) {
    $.ajax({
        url: 'ajax/filtrar_grupos.php',
        method: 'POST',
        data: filtros,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#grupos-container').html(response.html);
            } else {
                showAlert('Erro ao filtrar grupos', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao filtrar grupos', 'danger');
        }
    });
}

// Função para carregar mais jogos (paginação)
function carregarMaisJogos(pagina) {
    $.ajax({
        url: 'ajax/carregar_jogos.php',
        method: 'POST',
        data: {
            pagina: pagina
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#jogos-container').append(response.html);
                if (response.fim) {
                    $('#carregar-mais').hide();
                }
            }
        },
        error: function() {
            showAlert('Erro ao carregar mais jogos', 'danger');
        }
    });
}

// Função para mostrar/ocultar senha
function togglePassword(fieldId) {
    var field = $('#' + fieldId);
    var icon = $('#' + fieldId + '-toggle');
    
    if (field.attr('type') === 'password') {
        field.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        field.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

// Função para validar força da senha
function validarForcaSenha(senha) {
    var forca = 0;
    var feedback = '';
    
    if (senha.length >= 6) forca++;
    if (senha.match(/[a-z]/)) forca++;
    if (senha.match(/[A-Z]/)) forca++;
    if (senha.match(/[0-9]/)) forca++;
    if (senha.match(/[^a-zA-Z0-9]/)) forca++;
    
    switch (forca) {
        case 0:
        case 1:
            feedback = 'Muito fraca';
            break;
        case 2:
            feedback = 'Fraca';
            break;
        case 3:
            feedback = 'Média';
            break;
        case 4:
            feedback = 'Forte';
            break;
        case 5:
            feedback = 'Muito forte';
            break;
    }
    
    return {
        forca: forca,
        feedback: feedback
    };
}

// Função para mostrar feedback de força da senha
function mostrarFeedbackSenha(senha) {
    var validacao = validarForcaSenha(senha);
    var $feedback = $('#senha-feedback');
    
    if ($feedback.length === 0) {
        $feedback = $('<div id="senha-feedback" class="form-text"></div>');
        $('#senha').after($feedback);
    }
    
    $feedback.text('Força da senha: ' + validacao.feedback);
    $feedback.removeClass('text-danger text-warning text-success');
    
    if (validacao.forca <= 2) {
        $feedback.addClass('text-danger');
    } else if (validacao.forca <= 3) {
        $feedback.addClass('text-warning');
    } else {
        $feedback.addClass('text-success');
    }
}

// Event listener para feedback de senha
$(document).on('input', '#senha', function() {
    mostrarFeedbackSenha($(this).val());
});

// Função para formatar data em tempo real
function formatarDataTempoReal(data) {
    var agora = new Date();
    var dataJogo = new Date(data);
    var diferenca = dataJogo - agora;
    
    if (diferenca < 0) {
        return 'Jogo já aconteceu';
    }
    
    var dias = Math.floor(diferenca / (1000 * 60 * 60 * 24));
    var horas = Math.floor((diferenca % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    var minutos = Math.floor((diferenca % (1000 * 60 * 60)) / (1000 * 60));
    
    if (dias > 0) {
        return dias + ' dia(s) restante(s)';
    } else if (horas > 0) {
        return horas + ' hora(s) restante(s)';
    } else {
        return minutos + ' minuto(s) restante(s)';
    }
}

// Função para inicializar componentes específicos da página
function initPageComponents() {
    // Inicializar datepickers se existirem
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Inicializar timepickers se existirem
    if ($.fn.timepicker) {
        $('.timepicker').timepicker({
            showMeridian: false,
            minuteStep: 15
        });
    }
    
    // Inicializar select2 se existir
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecione uma opção'
        });
    }
}

// Executar inicialização quando o documento estiver pronto
$(document).ready(function() {
    initPageComponents();
});
