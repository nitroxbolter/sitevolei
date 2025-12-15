<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

$titulo = 'Gerenciar Torneio';

if (!isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

$torneio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($torneio_id <= 0) {
    $_SESSION['mensagem'] = 'Torneio inválido.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Carregar torneio
$sql = "SELECT t.*, g.nome AS grupo_nome, u.nome AS criado_por_nome
        FROM torneios t
        LEFT JOIN grupos g ON g.id = t.grupo_id
        LEFT JOIN usuarios u ON u.id = t.criado_por
        WHERE t.id = ?";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$torneio = $stmt ? $stmt->fetch() : false;

// Verificar se a coluna inscricoes_abertas existe e obter o valor
$inscricoes_abertas = 0;
try {
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneios LIKE 'inscricoes_abertas'");
    $coluna_existe = $columnsQuery && $columnsQuery->rowCount() > 0;
    if ($coluna_existe && isset($torneio['inscricoes_abertas'])) {
        $inscricoes_abertas = (int)$torneio['inscricoes_abertas'];
    }
} catch (Exception $e) {
    // Coluna não existe ainda
    $inscricoes_abertas = 0;
}

if (!$torneio) {
    $_SESSION['mensagem'] = 'Torneio não encontrado.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Verificar permissão
$sou_criador = ((int)$torneio['criado_por'] === (int)$_SESSION['user_id']);
$sou_admin_grupo = false;
if ($torneio['grupo_id']) {
    $sql = "SELECT administrador_id FROM grupos WHERE id = ?";
    $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
    $grupo = $stmt ? $stmt->fetch() : false;
    $sou_admin_grupo = $grupo && ((int)$grupo['administrador_id'] === (int)$_SESSION['user_id']);
}

if (!$sou_criador && !$sou_admin_grupo && !isAdmin($pdo, $_SESSION['user_id'])) {
    $_SESSION['mensagem'] = 'Você não tem permissão para gerenciar este torneio.';
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: ../torneios.php');
    exit();
}

// Buscar participantes com informações do usuário
// Verificar quais colunas existem
$columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
$columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
$tem_ordem = in_array('ordem', $columns);
$tem_nome_avulso = in_array('nome_avulso', $columns);

// Debug
error_log("Colunas torneio_participantes: " . implode(', ', $columns));
error_log("Buscando participantes do torneio ID: " . $torneio_id);

// Query básica - funciona mesmo sem nome_avulso
$sql = "SELECT tp.id, tp.torneio_id, tp.usuario_id, tp.data_inscricao";
if ($tem_nome_avulso) {
    $sql .= ", tp.nome_avulso";
}
if ($tem_ordem) {
    $sql .= ", tp.ordem";
}
$sql .= ", u.nome AS usuario_nome, u.foto_perfil AS usuario_foto
        FROM torneio_participantes tp
        LEFT JOIN usuarios u ON u.id = tp.usuario_id
        WHERE tp.torneio_id = ?";

// Adicionar ORDER BY baseado nas colunas disponíveis
if ($tem_ordem && $tem_nome_avulso) {
    $sql .= " ORDER BY tp.ordem, tp.nome_avulso, u.nome";
} elseif ($tem_ordem) {
    $sql .= " ORDER BY tp.ordem, u.nome";
} elseif ($tem_nome_avulso) {
    $sql .= " ORDER BY tp.nome_avulso, u.nome";
} else {
    $sql .= " ORDER BY u.nome, tp.id";
}

try {
    $stmt = executeQuery($pdo, $sql, [$torneio_id]);
    $participantes = $stmt ? $stmt->fetchAll() : [];
    
    // Debug
    error_log("SQL executado: " . $sql);
    error_log("Total de participantes encontrados: " . count($participantes));
    if (count($participantes) > 0) {
        error_log("Primeiro participante: " . print_r($participantes[0], true));
    } else {
        // Verificar se há participantes na tabela
        $sql_check = "SELECT COUNT(*) as total FROM torneio_participantes WHERE torneio_id = ?";
        $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id]);
        $check_result = $stmt_check ? $stmt_check->fetch() : false;
        error_log("Total de participantes na tabela para este torneio: " . ($check_result['total'] ?? 0));
    }
} catch (Exception $e) {
    error_log("Erro ao buscar participantes: " . $e->getMessage());
    $participantes = [];
}

// Buscar membros do grupo (se for torneio do grupo)
$membros_grupo = [];
$tipoTorneio = $torneio['tipo'] ?? ($torneio['grupo_id'] ? 'grupo' : 'avulso');
if ($tipoTorneio === 'grupo' && $torneio['grupo_id']) {
    $sql = "SELECT u.id, u.nome, u.foto_perfil
            FROM grupo_membros gm
            JOIN usuarios u ON u.id = gm.usuario_id
            WHERE gm.grupo_id = ? AND gm.ativo = 1
            ORDER BY u.nome";
    $stmt = executeQuery($pdo, $sql, [$torneio['grupo_id']]);
    $membros_grupo = $stmt ? $stmt->fetchAll() : [];
}

// Buscar times ordenados por ordem - usar DISTINCT para evitar duplicatas na query
$sql = "SELECT DISTINCT id, torneio_id, nome, cor, ordem, data_criacao FROM torneio_times WHERE torneio_id = ? ORDER BY ordem ASC, id ASC";
$stmt = executeQuery($pdo, $sql, [$torneio_id]);
$times_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Remover duplicatas baseado em ID (manter apenas um de cada ID)
$times_unicos = [];
$ids_vistos = [];
foreach ($times_raw as $time) {
    $id = (int)($time['id'] ?? 0);
    
    if ($id > 0 && !in_array($id, $ids_vistos)) {
        $times_unicos[] = $time;
        $ids_vistos[] = $id;
    }
}
$times = $times_unicos;

// Contar quantos times existem no banco de dados
$quantidade_times_db = count($times);

// Buscar integrantes dos times
foreach ($times as &$time) {
    $sql = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
            FROM torneio_time_integrantes tti
            JOIN torneio_participantes tp ON tp.id = tti.participante_id
            LEFT JOIN usuarios u ON u.id = tp.usuario_id
            WHERE tti.time_id = ?";
    
    // Verificar se a coluna 'ordem' existe
    if (!isset($tem_ordem)) {
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_participantes");
        $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
        $tem_ordem = in_array('ordem', $columns);
    }
    
    if ($tem_ordem) {
        $sql .= " ORDER BY tp.ordem";
    } else {
        $sql .= " ORDER BY tp.nome_avulso, u.nome";
    }
    $stmt = executeQuery($pdo, $sql, [$time['id']]);
    $time['integrantes'] = $stmt ? $stmt->fetchAll() : [];
}
unset($time); // Importante: remover referência após o loop

// Inicializar variável $pode_encerrar (será definida mais tarde quando necessário)
$pode_encerrar = false;

include '../../includes/header.php';
?>

<!-- Script inline para definir funções ANTES do HTML ser renderizado -->
<script>
// Definir funções completas aqui para garantir disponibilidade imediata
let valoresOriginaisFormulario = {};

// Disparar submissão do formulário de edição (para o botão Salvar)
window.salvarInformacoesTorneio = function() {
    try {
        console.log('salvarInformacoesTorneio chamada');
        const form = document.getElementById('formEditarInformacoesTorneio');
        if (!form) {
            console.error('Formulário de edição não encontrado.');
            showAlert('Erro: Formulário não encontrado.', 'danger');
            return;
        }
        
        // Chamar diretamente a função de submit (se disponível)
        if (typeof window.submitEditarTorneio === 'function') {
            console.log('Chamando submitEditarTorneio diretamente');
            window.submitEditarTorneio(new Event('submit'));
        } else {
            // Se a função não estiver disponível ainda, executar o código diretamente
            console.log('submitEditarTorneio não encontrado, executando código diretamente');
            const torneioId = parseInt(
                (form.querySelector('input[name="torneio_id"]')?.value ||
                 new URLSearchParams(window.location.search).get('id') || 0), 10
            ) || 0;
            
            if (!torneioId) {
                showAlert('Torneio inválido (ID ausente).', 'danger');
                return;
            }
            
            const payloadObj = {
                torneio_id: torneioId,
                nome: form.querySelector('#edit_nome_torneio_inline')?.value || '',
                data_torneio: form.querySelector('#edit_data_torneio_inline')?.value || '',
                descricao: form.querySelector('#edit_descricao_torneio_inline')?.value || '',
                max_participantes: form.querySelector('#edit_max_participantes_inline')?.value || ''
            };
            
            const btnSubmit = form.querySelector('button[type="button"].btn-primary');
            const originalText = btnSubmit ? btnSubmit.innerHTML : '';
            
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';
            }
            
            console.log('Enviando payload:', payloadObj);
            
            if (window.jQuery) {
                $.ajax({
                    url: '../ajax/editar_torneio.php',
                    method: 'POST',
                    data: payloadObj,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message || 'Salvo com sucesso!', 'success');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            showAlert(response.message || 'Erro ao salvar.', 'danger');
                            if (btnSubmit) {
                                btnSubmit.disabled = false;
                                btnSubmit.innerHTML = originalText;
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro AJAX:', error, xhr.responseText);
                        showAlert('Erro ao salvar informações do torneio.', 'danger');
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.innerHTML = originalText;
                        }
                    }
                });
            } else {
                fetch('../ajax/editar_torneio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(payloadObj).toString()
                })
                .then(r => r.json())
                .then(response => {
                    if (response.success) {
                        showAlert(response.message || 'Salvo com sucesso!', 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showAlert(response.message || 'Erro ao salvar.', 'danger');
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.innerHTML = originalText;
                        }
                    }
                })
                .catch(err => {
                    console.error('Erro fetch:', err);
                    showAlert('Erro ao salvar informações do torneio.', 'danger');
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = originalText;
                    }
                });
            }
        }
    } catch (e) {
        console.error('Erro ao acionar salvarInformacoesTorneio:', e);
        showAlert('Erro ao salvar: ' + e.message, 'danger');
    }
};

window.editarInformacoesTorneio = function() {
    try {
        const display = document.getElementById('displayInformacoesTorneio');
        const form = document.getElementById('formEditarInformacoesTorneio');
        const btnEditar = document.getElementById('btnEditarInformacoes');

        if (display && form && btnEditar) {
            valoresOriginaisFormulario = {
                nome: form.querySelector('#edit_nome_torneio_inline').value,
                data_torneio: form.querySelector('#edit_data_torneio_inline').value,
                descricao: form.querySelector('#edit_descricao_torneio_inline').value,
                max_participantes: form.querySelector('#edit_max_participantes_inline').value
            };

            display.style.display = 'none';
            form.style.display = 'block';
            btnEditar.style.display = 'none';
        } else {
            console.error('Elementos não encontrados para edição inline:', { display, form, btnEditar });
        }
    } catch (error) {
        console.error('Erro ao ativar edição (inline topo):', error);
    }
};

// Função para calcular quantidade de times automaticamente (versão inline no topo)
window.calcularParticipantesNecessarios = function() {
    const tipoTime = document.getElementById('tipo_time');
    const quantidadeTimesInput = document.getElementById('quantidade_times');
    const integrantesInput = document.getElementById('integrantes_por_time');
    const maxParticipantesInput = document.getElementById('max_participantes');
    const infoQuantidadeTimes = document.getElementById('info_quantidade_times_auto');
    
    if (!tipoTime || !quantidadeTimesInput || !integrantesInput || !maxParticipantesInput) {
        return;
    }
    
    if (tipoTime.value) {
        const selectedOption = tipoTime.options[tipoTime.selectedIndex];
        const integrantes = parseInt(selectedOption.getAttribute('data-integrantes')) || 0;
        
        integrantesInput.value = integrantes;
        
        const maxParticipantes = parseInt(maxParticipantesInput.value) || 0;
        
        if (maxParticipantes > 0 && integrantes > 0) {
            const quantidadeTimes = Math.floor(maxParticipantes / integrantes);
            quantidadeTimesInput.value = quantidadeTimes;
            
            if (infoQuantidadeTimes) {
                infoQuantidadeTimes.style.display = 'block';
                infoQuantidadeTimes.innerHTML = `<i class="fas fa-info-circle me-1"></i>Calculado automaticamente: ${maxParticipantes} participantes ÷ ${integrantes} integrantes = ${quantidadeTimes} times`;
            }
        } else {
            quantidadeTimesInput.value = 0;
            if (infoQuantidadeTimes) {
                infoQuantidadeTimes.style.display = 'none';
            }
        }
    } else {
        integrantesInput.value = '';
        quantidadeTimesInput.value = 0;
        if (infoQuantidadeTimes) {
            infoQuantidadeTimes.style.display = 'none';
        }
    }
};

// Variável global para estado da lista de participantes
window.listaParticipantesExpandida = false;

// Função para toggle da lista de participantes (versão inline no topo)
window.toggleListaParticipantes = function() {
    const corpo = document.getElementById('corpoListaParticipantes');
    const icone = document.getElementById('iconeParticipantes');
    
    if (corpo && icone) {
        if (window.listaParticipantesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            window.listaParticipantesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            window.listaParticipantesExpandida = true;
        }
    }
};

window.cancelarEdicaoInformacoes = function() {
    try {
        const display = document.getElementById('displayInformacoesTorneio');
        const form = document.getElementById('formEditarInformacoesTorneio');
        const btnEditar = document.getElementById('btnEditarInformacoes');

        if (display && form && btnEditar) {
            if (valoresOriginaisFormulario) {
                form.querySelector('#edit_nome_torneio_inline').value = valoresOriginaisFormulario.nome || '';
                form.querySelector('#edit_data_torneio_inline').value = valoresOriginaisFormulario.data_torneio || '';
                form.querySelector('#edit_descricao_torneio_inline').value = valoresOriginaisFormulario.descricao || '';
                form.querySelector('#edit_max_participantes_inline').value = valoresOriginaisFormulario.max_participantes || '';
            }

            if (window.jQuery) {
                $(form).find('.is-invalid').removeClass('is-invalid');
                $(form).find('.invalid-feedback').remove();
                $(form).removeClass('was-validated');
                $(form).find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Salvar');
            }

            form.style.display = 'none';
            display.style.display = 'block';
            btnEditar.style.display = 'inline-block';
        }
    } catch (error) {
        console.error('Erro ao cancelar edição (inline topo):', error);
    }
};

// Função excluir torneio já completa no topo para evitar ReferenceError
window.excluirTorneio = function(torneioId) {
    const idForcado = torneioId || new URLSearchParams(window.location.search).get('id') || 0;
    if (!confirm('Tem certeza que deseja excluir este torneio?\n\nEsta ação não pode ser desfeita e excluirá todos os participantes e times associados.')) return;

    $.ajax({
        url: '../ajax/excluir_torneio.php',
        method: 'POST',
        data: { torneio_id: idForcado },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = '../torneios.php';
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro ao excluir torneio.';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao excluir torneio', 'danger');
        }
    });
};
</script>

<style>
/* Remover spinners dos campos de número */
.pontos-input[type="number"]::-webkit-inner-spin-button,
.pontos-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pontos-input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}

/* Remover setas de todos os inputs number na seção de semi-final */
input[type="number"].pontos-input::-webkit-inner-spin-button,
input[type="number"].pontos-input::-webkit-outer-spin-button {
    -webkit-appearance: none !important;
    margin: 0;
}

input[type="number"].pontos-input {
    -moz-appearance: textfield !important;
    appearance: textfield !important;
}

/* Garantir que inputs number não mostrem spinners */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Remover setas (spinners) de input number para chaves */
.pontos-chave-input[type="number"]::-webkit-inner-spin-button,
.pontos-chave-input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pontos-chave-input[type="number"] {
    -moz-appearance: textfield;
}
</style>

<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<div class="row mb-3" style="margin-top: 10px;">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-trophy me-2"></i>Gerenciar Torneio: <?php echo htmlspecialchars($torneio['nome']); ?>
        </h2>
        <div>
            <a href="../torneios.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
</div>

<!-- Informações do Torneio -->
<?php
// Definir modalidade antes de usar
$modalidade = $torneio['modalidade'] ?? null;
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoInformacoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoInformacoes"></i>
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Torneio</h5>
                </div>
                <?php if ($sou_criador): ?>
                    <div class="d-flex gap-2">
                        <?php if ($torneio['status'] !== 'Finalizado' && $pode_encerrar): ?>
                            <button class="btn btn-sm btn-danger" onclick="encerrarTorneio()" id="btnEncerrarTorneio">
                                <i class="fas fa-flag-checkered me-1"></i>Encerrar Torneio
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" id="btnEditarInformacoes" onclick="event.stopPropagation(); editarInformacoesTorneio();">
                            <i class="fas fa-edit me-1"></i>Editar
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body" id="corpoSecaoInformacoes">
                <?php
                // Calcular informações adicionais
                // Total de jogos (1ª fase + 2ª fase)
                $sql_total_jogos = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
                $stmt_total_jogos = executeQuery($pdo, $sql_total_jogos, [$torneio_id]);
                $total_jogos_geral = $stmt_total_jogos ? (int)$stmt_total_jogos->fetch()['total'] : 0;
                
                // Jogos por time (calcular baseado na modalidade)
                $jogos_por_time = 0;
                if ($modalidade === 'todos_contra_todos' && $quantidade_times_db > 0) {
                    $jogos_por_time = $quantidade_times_db - 1;
                } elseif (($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') && $quantidade_times_db > 0) {
                    // Buscar quantidade de grupos
                    $sql_grupos_count = "SELECT COUNT(*) as total FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%'";
                    $stmt_grupos_count = executeQuery($pdo, $sql_grupos_count, [$torneio_id]);
                    $total_grupos = $stmt_grupos_count ? (int)$stmt_grupos_count->fetch()['total'] : 0;
                    if ($total_grupos > 0) {
                        $times_por_grupo = ceil($quantidade_times_db / $total_grupos);
                        $jogos_por_time = $times_por_grupo - 1;
                    }
                }
                
                // Quantidade de participantes
                $total_participantes = count($participantes);
                
                // Quantidade de times
                $total_times = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                
                // Quantidade de chaves (grupos da 1ª fase)
                $sql_chaves_count = "SELECT COUNT(*) as total FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%'";
                $stmt_chaves_count = executeQuery($pdo, $sql_chaves_count, [$torneio_id]);
                $total_chaves = $stmt_chaves_count ? (int)$stmt_chaves_count->fetch()['total'] : 0;
                
                // Quantidade de times por chave
                $times_por_chave = 0;
                if ($total_chaves > 0 && $total_times > 0) {
                    $times_por_chave = ceil($total_times / $total_chaves);
                }
                ?>
                <!-- Display das informações (modo visualização) -->
                <div id="displayInformacoesTorneio">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <strong>Nome do Torneio:</strong><br>
                        <?php echo htmlspecialchars($torneio['nome']); ?>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Data:</strong><br>
                        <?php 
                        $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                        echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'N/A';
                        ?>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Tipo:</strong><br>
                        <?php 
                        if (isset($torneio['tipo'])) {
                            echo $torneio['tipo'] === 'grupo' ? 'Torneio do Grupo' : 'Torneio Avulso';
                        } else {
                            echo $torneio['grupo_id'] ? 'Torneio do Grupo' : 'Torneio Avulso';
                        }
                        ?>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Status:</strong><br>
                        <?php 
                        $status_class = '';
                        $status_icon = '';
                        switch($torneio['status']) {
                            case 'Finalizado':
                                $status_class = 'success';
                                $status_icon = 'fa-flag-checkered';
                                break;
                            case 'Em Andamento':
                                $status_class = 'warning';
                                $status_icon = 'fa-play-circle';
                                break;
                            case 'Inscrições Abertas':
                                $status_class = 'info';
                                $status_icon = 'fa-user-plus';
                                break;
                            default:
                                $status_class = 'secondary';
                                $status_icon = 'fa-clock';
                        }
                        ?>
                        <span class="badge bg-<?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($torneio['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Quantidade de Jogos Totais:</strong><br>
                        <span class="badge bg-primary"><?php echo $total_jogos_geral; ?> jogo(s)</span>
                        <?php 
                        $total_1fase = isset($partidas) ? count($partidas) : 0;
                        $total_2fase = isset($partidas_2fase) ? count($partidas_2fase) : 0;
                        if ($total_2fase > 0): 
                        ?>
                            <small class="text-muted d-block">
                                (<?php echo $total_1fase; ?> na 1ª fase + <?php echo $total_2fase; ?> na 2ª fase)
                            </small>
                        <?php endif; ?>
                </div>
                    <div class="col-md-3 mb-3">
                        <strong>Jogos por Time:</strong><br>
                        <span class="badge bg-info"><?php echo $jogos_por_time > 0 ? $jogos_por_time : 'N/A'; ?></span>
            </div>
                    <div class="col-md-3 mb-3">
                        <strong>Quantidade de Participantes:</strong><br>
                        <?php 
                        // Calcular quantidade máxima baseado em times × integrantes
                        $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                        $integrantesPorTime = (int)($torneio['integrantes_por_time'] ?? 0);
                        $maxParticipantesCalculado = ($quantidadeTimes > 0 && $integrantesPorTime > 0) ? ($quantidadeTimes * $integrantesPorTime) : 0;
                        $maxParticipantes = $maxParticipantesCalculado > 0 ? $maxParticipantesCalculado : ($torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0);
                        ?>
                        <span class="badge bg-secondary"><?php echo $total_participantes; ?></span>
                        <?php if ($maxParticipantes > 0): ?>
                            <small class="text-muted">/ <?php echo (int)$maxParticipantes; ?> máximo</small>
                        <?php endif; ?>
        </div>
                    <div class="col-md-3 mb-3">
                        <strong>Quantidade de Times:</strong><br>
                        <span class="badge bg-success"><?php echo $total_times; ?> time(s)</span>
                    </div>
                    <?php if ($total_chaves > 0): ?>
                    <div class="col-md-3 mb-3">
                        <strong>Quantidade de Grupos:</strong><br>
                        <span class="badge bg-warning"><?php echo $total_chaves; ?> grupo(s)</span>
                    </div>
                    <div class="col-md-3 mb-3">
                        <strong>Times por Grupo:</strong><br>
                        <span class="badge bg-info"><?php echo $times_por_chave; ?> time(s)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
                
                <!-- Formulário de edição inline -->
                <form id="formEditarInformacoesTorneio" style="display: none;" onsubmit="return false;">
                    <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_nome_torneio_inline" class="form-label"><strong>Nome do Torneio:</strong></label>
                            <input type="text" class="form-control" id="edit_nome_torneio_inline" name="nome" 
                                   value="<?php echo htmlspecialchars($torneio['nome']); ?>" required>
        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_data_torneio_inline" class="form-label"><strong>Data do Torneio:</strong></label>
                            <input type="date" class="form-control" id="edit_data_torneio_inline" name="data_torneio" 
                                   value="<?php 
                                   $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                                   echo $dataTorneio ? date('Y-m-d', strtotime($dataTorneio)) : '';
                                   ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_descricao_torneio_inline" class="form-label"><strong>Descrição:</strong></label>
                            <textarea class="form-control" id="edit_descricao_torneio_inline" name="descricao" rows="3"><?php echo htmlspecialchars($torneio['descricao'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_max_participantes_inline" class="form-label"><strong>Quantidade Máxima de Participantes:</strong></label>
                            <?php 
                            $quantidadeTimes = (int)($torneio['quantidade_times'] ?? 0);
                            $integrantesPorTime = (int)($torneio['integrantes_por_time'] ?? 0);
                            $maxParticipantesCalculado = ($quantidadeTimes > 0 && $integrantesPorTime > 0) ? ($quantidadeTimes * $integrantesPorTime) : 0;
                            $maxParticipantesInicial = $maxParticipantesCalculado > 0 ? $maxParticipantesCalculado : (int)($torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0);
                            ?>
                            <input type="number" class="form-control" id="edit_max_participantes_inline" name="max_participantes" 
                                   min="1" value="<?php echo $maxParticipantesInicial; ?>" required>
                            <small class="text-muted">Atual: <?php echo count($participantes); ?> participantes</small>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary btn-sm" onclick="salvarInformacoesTorneio();">
                                <i class="fas fa-save me-1"></i>Salvar
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarEdicaoInformacoes()">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="excluirTorneio(<?php echo $torneio_id; ?>);">
                                <i class="fas fa-trash me-1"></i>Excluir Torneio
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Configurações do Torneio -->
<script>
// Definir função ANTES do HTML do botão para garantir que esteja disponível
window.processarSalvarConfiguracoes = function() {
    console.log('=== PROCESSAR SALVAR CONFIGURAÇÕES CHAMADO ===');
    
    // Verificar se jQuery está disponível
    if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
        console.error('ERRO: jQuery não está disponível!');
        alert('Erro: jQuery não está disponível. Recarregue a página.');
        return;
    }
    
    const $ = window.jQuery || window.$;
    
    // Verificar se o formulário existe
    const form = $('#formConfigTorneio');
    if (form.length === 0) {
        console.error('ERRO: Formulário #formConfigTorneio não encontrado!');
        if (typeof showAlert === 'function') {
            showAlert('Erro: Formulário não encontrado. Recarregue a página.', 'danger');
        } else {
            alert('Erro: Formulário não encontrado. Recarregue a página.');
        }
        return;
    }
    
    // Mensagem visual confirmando que o botão foi clicado
    if (typeof showAlert === 'function') {
        showAlert('Botão "Salvar Configurações" foi clicado! Processando...', 'info');
    }
    
    // Garantir que integrantes_por_time está atualizado antes de enviar
    if (typeof calcularParticipantesNecessarios === 'function') {
        calcularParticipantesNecessarios();
    }
    
    // Garantir que integrantes_por_time está preenchido
    const tipoTime = $('#tipo_time').val();
    const integrantesPorTime = $('#integrantes_por_time').val();
    const quantidadeTimes = $('#quantidade_times').val();
    
    if (!tipoTime) {
        if (typeof showAlert === 'function') {
            showAlert('Selecione o tipo de time', 'danger');
        } else {
            alert('Selecione o tipo de time');
        }
        return;
    }
    
    if (!integrantesPorTime || integrantesPorTime <= 0) {
        if (typeof showAlert === 'function') {
            showAlert('Erro: Tipo de time não configurado corretamente. Recarregue a página e tente novamente.', 'danger');
        } else {
            alert('Erro: Tipo de time não configurado corretamente.');
        }
        return;
    }
    
    if (!quantidadeTimes || quantidadeTimes < 2) {
        if (typeof showAlert === 'function') {
            showAlert('Informe a quantidade de times (mínimo 2)', 'danger');
        } else {
            alert('Informe a quantidade de times (mínimo 2)');
        }
        return;
    }
    
    // Montar payload explicitamente
    let torneioId = 0;
    let torneioIdFromForm = form.find('input[name="torneio_id"]').val();
    if (!torneioIdFromForm || torneioIdFromForm === '') {
        torneioIdFromForm = $('#torneio_id_hidden').val();
    }
    if (!torneioIdFromForm || torneioIdFromForm === '') {
        torneioIdFromForm = new URLSearchParams(window.location.search).get('id');
    }
    if (!torneioIdFromForm || torneioIdFromForm === '') {
        torneioIdFromForm = <?php echo $torneio_id; ?>;
    }
    
    torneioId = parseInt(torneioIdFromForm, 10) || 0;
    
    console.log('torneioId final:', torneioId);
    
    if (!torneioId || torneioId <= 0) {
        if (typeof showAlert === 'function') {
            showAlert('Torneio inválido (ID ausente). Verifique o console para mais detalhes.', 'danger');
        } else {
            alert('Torneio inválido (ID ausente).');
        }
        console.error('ERRO: Não foi possível obter o ID do torneio');
        return;
    }
    
    const payloadObj = {
        torneio_id: torneioId,
        max_participantes: form.find('#max_participantes').val() || '',
        quantidade_times: form.find('#quantidade_times').val() || '',
        integrantes_por_time: form.find('#integrantes_por_time').val() || ''
    };
    
    console.log('Enviando configurações do torneio:', payloadObj);
    
    // Desabilitar botão durante o envio
    const btnSubmit = $('#btnSalvarConfig');
    const originalText = btnSubmit.html();
    btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Salvando...');
    
    $.ajax({
        url: '../ajax/configurar_torneio.php',
        method: 'POST',
        data: payloadObj,
        dataType: 'json',
        success: function(response) {
            btnSubmit.prop('disabled', false).html(originalText);
            console.log('Resposta recebida:', response);
            
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'success');
                }
                
                const torneioId = <?php echo $torneio_id; ?>;
                const quantidadeTimes = parseInt($('#quantidade_times').val()) || 0;
                
                if (quantidadeTimes > 0) {
                    setTimeout(function() {
                        $.ajax({
                            url: '../ajax/criar_times_torneio.php',
                            method: 'POST',
                            data: { torneio_id: torneioId },
                            dataType: 'json',
                            success: function(responseTimes) {
                                if (responseTimes.success && typeof showAlert === 'function') {
                                    showAlert('Configurações salvas e ' + quantidadeTimes + ' times criados com sucesso!', 'success');
                                }
                                $('.config-field').prop('disabled', true);
                                $('#btnSalvarConfig').hide();
                                $('#btnEditarConfig').show();
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            },
                            error: function() {
                                if (typeof showAlert === 'function') {
                                    showAlert('Configurações salvas, mas houve um erro ao criar os times.', 'warning');
                                }
                                $('.config-field').prop('disabled', true);
                                $('#btnSalvarConfig').hide();
                                $('#btnEditarConfig').show();
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        });
                    }, 500);
                } else {
                    $('.config-field').prop('disabled', true);
                    $('#btnSalvarConfig').hide();
                    $('#btnEditarConfig').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            } else {
                const errorMsg = response.message || 'Erro ao salvar configurações.';
                console.error('Erro ao salvar configurações:', response);
                if (typeof showAlert === 'function') {
                    showAlert(errorMsg, 'danger');
                } else {
                    alert(errorMsg);
                }
            }
        },
        error: function(xhr, status, error) {
            btnSubmit.prop('disabled', false).html(originalText);
            console.error('Erro AJAX ao salvar configurações:', { status, error, statusCode: xhr.status });
            const errorMsg = xhr.status === 404 ? 'Arquivo não encontrado.' : 'Erro ao salvar configurações.';
            if (typeof showAlert === 'function') {
                showAlert(errorMsg, 'danger');
            } else {
                alert(errorMsg);
            }
        }
    });
};

// Alias para compatibilidade
function processarSalvarConfiguracoes() {
    if (typeof window.processarSalvarConfiguracoes === 'function') {
        return window.processarSalvarConfiguracoes();
    }
}

// Definir função abrirModalAdicionarParticipante ANTES do HTML do botão
window.abrirModalAdicionarParticipante = function() {
    console.log('=== ABRIR MODAL ADICIONAR PARTICIPANTE CHAMADO ===');
    
    const modalElement = document.getElementById('modalAdicionarParticipante');
    if (!modalElement) {
        console.error('ERRO: Modal modalAdicionarParticipante não encontrado!');
        alert('Erro: Modal não encontrado. Verifique se o modal existe na página.');
        return;
    }
    
    try {
        // Verificar se Bootstrap está disponível
        if (typeof bootstrap === 'undefined') {
            console.error('ERRO: Bootstrap não está disponível!');
            alert('Erro: Bootstrap não está disponível. Recarregue a página.');
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Modal aberto com sucesso');
        
        // Atualizar contador ao abrir modal
        if (typeof atualizarContadorVagas === 'function') {
            atualizarContadorVagas();
        } else {
            console.warn('Função atualizarContadorVagas não está disponível');
        }
    } catch (error) {
        console.error('Erro ao abrir modal:', error);
        alert('Erro ao abrir modal. Verifique o console para mais detalhes.');
    }
};

// Alias para compatibilidade
function abrirModalAdicionarParticipante() {
    if (typeof window.abrirModalAdicionarParticipante === 'function') {
        return window.abrirModalAdicionarParticipante();
    } else {
        console.error('ERRO: window.abrirModalAdicionarParticipante não está disponível!');
    }
}

// Função para editar times - tornar global
window.editarTimes = function() {
    console.log('=== EDITAR TIMES CHAMADO ===');
    if (typeof jQuery !== 'undefined') {
        jQuery('.times-action-btn').prop('disabled', false);
        jQuery('#btnEditarTimes').hide();
        console.log('Botões de ação dos times habilitados');
    } else {
        console.error('jQuery não está disponível!');
    }
};

// Alias para compatibilidade
function editarTimes() {
    if (typeof window.editarTimes === 'function') {
        return window.editarTimes();
    } else {
        console.error('ERRO: window.editarTimes não está disponível!');
    }
}

// Função para limpar todos os participantes - tornar global
window.limparTodosParticipantes = function() {
    console.log('=== LIMPAR TODOS PARTICIPANTES CHAMADO ===');
    if (!confirm('ATENÇÃO: Isso removerá TODOS os participantes do torneio. Esta ação não pode ser desfeita. Deseja continuar?')) {
        console.log('Operação cancelada pelo usuário');
        return;
    }
    
    const torneioId = <?php echo $torneio_id; ?>;
    console.log('Enviando requisição para limpar participantes do torneio:', torneioId);
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/limpar_todos_participantes_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId },
            dataType: 'json',
            success: function(response) {
                console.log('Resposta recebida:', response);
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    let errorMsg = response.message || 'Erro ao limpar participantes';
                    console.error('Erro ao limpar participantes:', response);
                    if (response.debug) {
                        console.error('Erro detalhado:', response.debug);
                        errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                    }
                    if (typeof showAlert === 'function') {
                        showAlert(errorMsg, 'danger');
                    } else {
                        alert(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX ao limpar participantes:', { status, error, statusCode: xhr.status });
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao limpar participantes. Tente novamente.', 'danger');
                } else {
                    alert('Erro ao limpar participantes. Tente novamente.');
                }
            }
        });
    } else {
        console.error('jQuery não está disponível!');
        alert('Erro: jQuery não está disponível.');
    }
};

// Alias para compatibilidade
function limparTodosParticipantes() {
    if (typeof window.limparTodosParticipantes === 'function') {
        return window.limparTodosParticipantes();
    } else {
        console.error('ERRO: window.limparTodosParticipantes não está disponível!');
    }
}

// Função para remover participante individual - tornar global
window.removerParticipante = function(participanteId) {
    console.log('=== REMOVER PARTICIPANTE CHAMADO ===', participanteId);
    
    if (!confirm('Remover este participante do torneio?')) {
        console.log('Operação cancelada pelo usuário');
        return;
    }
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/remover_participante_torneio.php',
            method: 'POST',
            data: { participante_id: participanteId },
            dataType: 'json',
            success: function(response) {
                console.log('Resposta recebida:', response);
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    let errorMsg = response.message || 'Erro ao remover participante';
                    console.error('Erro ao remover participante:', response);
                    if (response.debug) {
                        console.error('Erro detalhado:', response.debug);
                        errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                    }
                    if (typeof showAlert === 'function') {
                        showAlert(errorMsg, 'danger');
                    } else {
                        alert(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX ao remover participante:', { status, error, statusCode: xhr.status });
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao remover participante. Tente novamente.', 'danger');
                } else {
                    alert('Erro ao remover participante. Tente novamente.');
                }
            }
        });
    } else {
        console.error('jQuery não está disponível!');
        alert('Erro: jQuery não está disponível.');
    }
};

// Alias para compatibilidade
function removerParticipante(participanteId) {
    if (typeof window.removerParticipante === 'function') {
        return window.removerParticipante(participanteId);
    } else {
        console.error('ERRO: window.removerParticipante não está disponível!');
    }
}

// Função para editar nome do time - tornar global
window.editarNomeTime = function(timeId) {
    console.log('=== EDITAR NOME TIME CHAMADO ===', timeId);
    const display = document.getElementById('nome-time-display-' + timeId);
    const input = document.getElementById('nome-time-input-' + timeId);
    
    if (display && input) {
        display.classList.add('d-none');
        input.classList.remove('d-none');
        input.focus();
        input.select();
        console.log('Input de edição do nome do time ativado');
    } else {
        console.error('Elementos não encontrados:', { display: !!display, input: !!input });
    }
};

// Alias para compatibilidade
function editarNomeTime(timeId) {
    if (typeof window.editarNomeTime === 'function') {
        return window.editarNomeTime(timeId);
    } else {
        console.error('ERRO: window.editarNomeTime não está disponível!');
    }
}

// Função para salvar nome do time - tornar global
window.salvarNomeTime = function(timeId) {
    console.log('=== SALVAR NOME TIME CHAMADO ===', timeId);
    const display = document.getElementById('nome-time-display-' + timeId);
    const input = document.getElementById('nome-time-input-' + timeId);
    
    if (!input || !display) {
        console.error('Elementos não encontrados:', { display: !!display, input: !!input });
        return;
    }
    
    const novoNome = input.value.trim();
    
    if (novoNome === '') {
        if (typeof showAlert === 'function') {
            showAlert('O nome do time não pode estar vazio.', 'warning');
        } else {
            alert('O nome do time não pode estar vazio.');
        }
        input.classList.add('d-none');
        display.classList.remove('d-none');
        return;
    }
    
    // Se o nome não mudou, apenas esconder o input
    const nomeAnterior = display.querySelector('strong').textContent.trim();
    if (novoNome === nomeAnterior) {
        input.classList.add('d-none');
        display.classList.remove('d-none');
        console.log('Nome não alterado, apenas escondendo input');
        return;
    }
    
    console.log('Enviando novo nome:', novoNome);
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/atualizar_nome_time.php',
            method: 'POST',
            data: {
                time_id: timeId,
                novo_nome: novoNome
            },
            dataType: 'json',
            success: function(response) {
                console.log('Resposta recebida:', response);
                if (response.success) {
                    // Atualizar o texto exibido
                    display.querySelector('strong').textContent = response.novo_nome || novoNome;
                    input.value = response.novo_nome || novoNome;
                    input.classList.add('d-none');
                    display.classList.remove('d-none');
                    if (typeof showAlert === 'function') {
                        showAlert('Nome do time atualizado com sucesso!', 'success');
                    } else {
                        alert('Nome do time atualizado com sucesso!');
                    }
                } else {
                    const errorMsg = response.message || 'Erro ao atualizar nome do time.';
                    console.error('Erro ao atualizar nome do time:', response);
                    if (typeof showAlert === 'function') {
                        showAlert(errorMsg, 'danger');
                    } else {
                        alert(errorMsg);
                    }
                    input.classList.add('d-none');
                    display.classList.remove('d-none');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX ao atualizar nome do time:', { status, error, statusCode: xhr.status });
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao atualizar nome do time. Tente novamente.', 'danger');
                } else {
                    alert('Erro ao atualizar nome do time. Tente novamente.');
                }
                input.classList.add('d-none');
                display.classList.remove('d-none');
            }
        });
    } else {
        console.error('jQuery não está disponível!');
        alert('Erro: jQuery não está disponível.');
    }
};

// Alias para compatibilidade
function salvarNomeTime(timeId) {
    if (typeof window.salvarNomeTime === 'function') {
        return window.salvarNomeTime(timeId);
    } else {
        console.error('ERRO: window.salvarNomeTime não está disponível!');
    }
}

// ============================================
// FUNÇÕES PARA DEFINIR GRUPOS MANUALMENTE
// ============================================

// Função para abrir modal de definir grupos
window.abrirModalDefinirGrupos = function() {
    console.log('=== ABRIR MODAL DEFINIR GRUPOS CHAMADO ===');
    
    const modalElement = document.getElementById('modalDefinirGrupos');
    if (!modalElement) {
        console.error('ERRO: Modal modalDefinirGrupos não encontrado!');
        alert('Erro: Modal não encontrado.');
        return;
    }
    
    try {
        if (typeof bootstrap === 'undefined') {
            console.error('ERRO: Bootstrap não está disponível!');
            alert('Erro: Bootstrap não está disponível.');
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Modal aberto com sucesso');
        
        // Carregar times e grupos
        carregarTimesParaGrupos();
    } catch (error) {
        console.error('Erro ao abrir modal:', error);
        alert('Erro ao abrir modal. Verifique o console.');
    }
};

// Função para carregar times e grupos
function carregarTimesParaGrupos() {
    const torneioId = <?php echo $torneio_id; ?>;
    const quantidadeGrupos = <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
    
    if (quantidadeGrupos <= 0) {
        if (typeof showAlert === 'function') {
            showAlert('Configure a quantidade de grupos primeiro.', 'warning');
        } else {
            alert('Configure a quantidade de grupos primeiro.');
        }
        return;
    }
    
    console.log('Carregando times para grupos...', { torneioId, quantidadeGrupos });
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/buscar_times_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderizarGrupos(response.times, quantidadeGrupos);
                } else {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message || 'Erro ao carregar times.', 'danger');
                    } else {
                        alert(response.message || 'Erro ao carregar times.');
                    }
                }
            },
            error: function() {
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao carregar times.', 'danger');
                } else {
                    alert('Erro ao carregar times.');
                }
            }
        });
    } else {
        // Fallback sem jQuery
        fetch('../ajax/buscar_times_torneio.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'torneio_id=' + torneioId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderizarGrupos(data.times, quantidadeGrupos);
            } else {
                alert(data.message || 'Erro ao carregar times.');
            }
        })
        .catch(e => {
            console.error('Erro:', e);
            alert('Erro ao carregar times.');
        });
    }
}

// Função para renderizar grupos e times
function renderizarGrupos(times, quantidadeGrupos) {
    const container = document.getElementById('containerGrupos');
    if (!container) return;
    
    // Buscar grupos já definidos (se houver)
    const torneioId = <?php echo $torneio_id; ?>;
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/buscar_grupos_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId },
            dataType: 'json',
            success: function(responseGrupos) {
                const gruposExistentes = responseGrupos.success ? responseGrupos.grupos : [];
                criarInterfaceGrupos(times, quantidadeGrupos, gruposExistentes);
            },
            error: function() {
                criarInterfaceGrupos(times, quantidadeGrupos, []);
            }
        });
    } else {
        criarInterfaceGrupos(times, quantidadeGrupos, []);
    }
}

// Função para criar interface de grupos
function criarInterfaceGrupos(times, quantidadeGrupos, gruposExistentes) {
    const container = document.getElementById('containerGrupos');
    if (!container) return;
    
    container.innerHTML = '';
    
    // Criar colunas para cada grupo
    const colSize = quantidadeGrupos <= 2 ? 6 : quantidadeGrupos <= 4 ? 4 : 3;
    
    for (let g = 1; g <= quantidadeGrupos; g++) {
        const letraGrupo = String.fromCharCode(64 + g); // A, B, C, etc.
        const grupoExistente = gruposExistentes.find(gr => gr.ordem === g);
        const grupoId = grupoExistente?.id || null;
        const timesGrupo = grupoExistente?.times || [];
        
        const grupoHtml = `
            <div class="col-md-${colSize} mb-3">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <strong>Grupo ${letraGrupo}</strong>
                    </div>
                    <div class="card-body" style="min-height: 200px; max-height: 400px; overflow-y: auto;" 
                         data-grupo-ordem="${g}" data-grupo-id="${grupoId || ''}">
                        <div class="list-group" id="grupo-${g}-times">
                            ${timesGrupo.map(time => `
                                <div class="list-group-item d-flex justify-content-between align-items-center" data-time-id="${time.id}">
                                    <span>${(time.nome || 'Time sem nome').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                                    <button class="btn btn-sm btn-outline-danger" onclick="removerTimeDoGrupo(${g}, ${time.id})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">${timesGrupo.length} time(s)</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += grupoHtml;
    }
    
    // Criar área de times disponíveis
    const timesDistribuidos = gruposExistentes.flatMap(gr => gr.times.map(t => t.id));
    const timesDisponiveis = times.filter(time => !timesDistribuidos.includes(time.id));
    
    const disponiveisHtml = `
        <div class="col-12 mt-3">
            <div class="card border-secondary">
                <div class="card-header bg-secondary text-white">
                    <strong>Times Disponíveis</strong>
                </div>
                <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                    <div class="list-group" id="times-disponiveis">
                        ${timesDisponiveis.map(time => {
                            const timeNome = (time.nome || 'Time sem nome').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, "\\'");
                            const botoesGrupos = Array.from({length: quantidadeGrupos}, (_, i) => i + 1).map(g => {
                                const letra = String.fromCharCode(64 + g);
                                return `<button class="btn btn-outline-primary btn-sm" onclick="adicionarTimeAoGrupo(${g}, ${time.id}, '${timeNome}')" title="Adicionar ao Grupo ${letra}">${letra}</button>`;
                            }).join('');
                            return `
                                <div class="list-group-item d-flex justify-content-between align-items-center" data-time-id="${time.id}">
                                    <span>${timeNome}</span>
                                    <div class="btn-group btn-group-sm">
                                        ${botoesGrupos}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
    container.innerHTML += disponiveisHtml;
}

// Função para adicionar time ao grupo
function adicionarTimeAoGrupo(grupoOrdem, timeId, timeNome) {
    const grupoContainer = document.querySelector(`[data-grupo-ordem="${grupoOrdem}"] .list-group`);
    const timeDisponivel = document.querySelector(`#times-disponiveis [data-time-id="${timeId}"]`);
    
    if (!grupoContainer || !timeDisponivel) return;
    
    // Verificar se o time já está em algum grupo
    const timeJaNoGrupo = document.querySelector(`[data-grupo-ordem] .list-group-item[data-time-id="${timeId}"]`);
    if (timeJaNoGrupo) {
        if (typeof showAlert === 'function') {
            showAlert('Este time já está em um grupo. Remova-o primeiro.', 'warning');
        } else {
            alert('Este time já está em um grupo. Remova-o primeiro.');
        }
        return;
    }
    
    // Adicionar ao grupo
    const letraGrupo = String.fromCharCode(64 + grupoOrdem);
    const itemHtml = `
        <div class="list-group-item d-flex justify-content-between align-items-center" data-time-id="${timeId}">
            <span>${timeNome}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="removerTimeDoGrupo(${grupoOrdem}, ${timeId})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    grupoContainer.insertAdjacentHTML('beforeend', itemHtml);
    
    // Remover dos disponíveis
    timeDisponivel.remove();
    
    // Atualizar contador
    atualizarContadorGrupo(grupoOrdem);
}

// Função para remover time do grupo
function removerTimeDoGrupo(grupoOrdem, timeId) {
    const grupoContainer = document.querySelector(`[data-grupo-ordem="${grupoOrdem}"] .list-group`);
    const timeItem = grupoContainer?.querySelector(`[data-time-id="${timeId}"]`);
    
    if (!timeItem) return;
    
    // Buscar nome do time
    const timeNome = timeItem.querySelector('span').textContent;
    
    // Remover do grupo
    timeItem.remove();
    
    // Adicionar aos disponíveis
    const disponiveisContainer = document.getElementById('times-disponiveis');
    if (disponiveisContainer) {
        const quantidadeGrupos = <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
        const botoesGrupos = Array.from({length: quantidadeGrupos}, (_, i) => i + 1).map(g => {
            const letra = String.fromCharCode(64 + g);
            return `<button class="btn btn-outline-primary btn-sm" onclick="adicionarTimeAoGrupo(${g}, ${timeId}, '${timeNome.replace(/'/g, "\\'")}')" title="Adicionar ao Grupo ${letra}">${letra}</button>`;
        }).join('');
        
        const itemHtml = `
            <div class="list-group-item d-flex justify-content-between align-items-center" data-time-id="${timeId}">
                <span>${timeNome}</span>
                <div class="btn-group btn-group-sm">
                    ${botoesGrupos}
                </div>
            </div>
        `;
        disponiveisContainer.insertAdjacentHTML('beforeend', itemHtml);
    }
    
    // Atualizar contador
    atualizarContadorGrupo(grupoOrdem);
}

// Função para atualizar contador do grupo
function atualizarContadorGrupo(grupoOrdem) {
    const grupoContainer = document.querySelector(`[data-grupo-ordem="${grupoOrdem}"]`);
    if (!grupoContainer) return;
    
    const timesNoGrupo = grupoContainer.querySelectorAll('.list-group-item').length;
    const contador = grupoContainer.querySelector('small');
    if (contador) {
        contador.textContent = `${timesNoGrupo} time(s)`;
    }
}

// Função para sortear grupos automaticamente
function sortearGruposAutomaticamente() {
    if (!confirm('Isso irá sortear todos os times nos grupos automaticamente. Deseja continuar?')) return;
    
    const torneioId = <?php echo $torneio_id; ?>;
    const quantidadeGrupos = <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/sortear_grupos_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId, quantidade_grupos: quantidadeGrupos },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert('Times sorteados automaticamente!', 'success');
                    } else {
                        alert('Times sorteados automaticamente!');
                    }
                    carregarTimesParaGrupos(); // Recarregar interface
                } else {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message || 'Erro ao sortear grupos.', 'danger');
                    } else {
                        alert(response.message || 'Erro ao sortear grupos.');
                    }
                }
            },
            error: function() {
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao sortear grupos.', 'danger');
                } else {
                    alert('Erro ao sortear grupos.');
                }
            }
        });
    }
}

// Função para limpar distribuição
function limparDistribuicaoGrupos() {
    if (!confirm('Isso irá remover todos os times dos grupos. Deseja continuar?')) return;
    
    const torneioId = <?php echo $torneio_id; ?>;
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/limpar_grupos_torneio.php',
            method: 'POST',
            data: { torneio_id: torneioId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert('Grupos limpos com sucesso!', 'success');
                    } else {
                        alert('Grupos limpos com sucesso!');
                    }
                    carregarTimesParaGrupos(); // Recarregar interface
                } else {
                    if (typeof showAlert === 'function') {
                        showAlert(response.message || 'Erro ao limpar grupos.', 'danger');
                    } else {
                        alert(response.message || 'Erro ao limpar grupos.');
                    }
                }
            },
            error: function() {
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao limpar grupos.', 'danger');
                } else {
                    alert('Erro ao limpar grupos.');
                }
            }
        });
    }
}

// Função para salvar distribuição de grupos
function salvarDistribuicaoGrupos() {
    console.log('=== SALVAR DISTRIBUIÇÃO GRUPOS CHAMADO ===');
    
    const container = document.getElementById('containerGrupos');
    if (!container) {
        console.error('Container de grupos não encontrado!');
        alert('Erro: Container de grupos não encontrado.');
        return;
    }
    
    const torneioId = <?php echo $torneio_id; ?>;
    const quantidadeGrupos = <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
    
    console.log('Torneio ID:', torneioId, 'Quantidade Grupos:', quantidadeGrupos);
    
    // Coletar distribuição
    const distribuicao = {};
    for (let g = 1; g <= quantidadeGrupos; g++) {
        const grupoContainer = document.querySelector(`[data-grupo-ordem="${g}"] .list-group`);
        if (grupoContainer) {
            const timesIds = Array.from(grupoContainer.querySelectorAll('[data-time-id]')).map(item => 
                parseInt(item.getAttribute('data-time-id'))
            );
            distribuicao[g] = timesIds;
            console.log(`Grupo ${g}:`, timesIds);
        } else {
            distribuicao[g] = [];
            console.log(`Grupo ${g}: vazio (container não encontrado)`);
        }
    }
    
    console.log('Distribuição coletada:', distribuicao);
    
    // Validar que há pelo menos alguns times distribuídos
    const timesDistribuidos = Object.values(distribuicao).flat();
    console.log('Total de times distribuídos:', timesDistribuidos.length);
    
    if (timesDistribuidos.length === 0) {
        if (typeof showAlert === 'function') {
            showAlert('Nenhum time foi distribuído. Adicione times aos grupos antes de salvar.', 'warning');
        } else {
            alert('Nenhum time foi distribuído. Adicione times aos grupos antes de salvar.');
        }
        return;
    }
    
    // Verificar se há times duplicados
    const timesDuplicados = timesDistribuidos.filter((id, index) => timesDistribuidos.indexOf(id) !== index);
    if (timesDuplicados.length > 0) {
        console.error('Times duplicados encontrados:', timesDuplicados);
        if (typeof showAlert === 'function') {
            showAlert('Erro: Alguns times estão duplicados na distribuição.', 'danger');
        } else {
            alert('Erro: Alguns times estão duplicados na distribuição.');
        }
        return;
    }
    
    // Desabilitar botão durante o envio
    const btnSalvar = document.getElementById('btnSalvarGrupos');
    if (!btnSalvar) {
        console.error('Botão salvar não encontrado!');
        alert('Erro: Botão salvar não encontrado.');
        return;
    }
    
    const originalText = btnSalvar.innerHTML;
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';
    
    console.log('Enviando dados para servidor...');
    
    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: '../ajax/salvar_distribuicao_grupos.php',
            method: 'POST',
            data: {
                torneio_id: torneioId,
                quantidade_grupos: quantidadeGrupos,
                distribuicao: JSON.stringify(distribuicao)
            },
            dataType: 'json',
            success: function(response) {
                console.log('Resposta do servidor:', response);
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = originalText;
                
                if (response.success) {
                    if (typeof showAlert === 'function') {
                        showAlert('Distribuição de grupos salva com sucesso!', 'success');
                    } else {
                        alert('Distribuição de grupos salva com sucesso!');
                    }
                    const modalElement = document.getElementById('modalDefinirGrupos');
                    if (modalElement) {
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    setTimeout(() => location.reload(), 1000);
                } else {
                    console.error('Erro na resposta:', response);
                    if (typeof showAlert === 'function') {
                        showAlert(response.message || 'Erro ao salvar distribuição.', 'danger');
                    } else {
                        alert(response.message || 'Erro ao salvar distribuição.');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', {xhr, status, error});
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = originalText;
                if (typeof showAlert === 'function') {
                    showAlert('Erro ao salvar distribuição de grupos. Verifique o console (F12) para mais detalhes.', 'danger');
                } else {
                    alert('Erro ao salvar distribuição de grupos. Verifique o console (F12) para mais detalhes.');
                }
            }
        });
    } else {
        console.error('jQuery não está disponível!');
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = originalText;
        alert('Erro: jQuery não está disponível.');
    }
}

// Função global para processar adição de participante (chamada diretamente pelo botão)
window.processarAdicionarParticipante = function() {
    console.log('=== PROCESSAR ADICIONAR PARTICIPANTE CHAMADO ===');
    
    const form = $('#formAdicionarParticipante');
    if (form.length === 0) {
        console.error('ERRO: Formulário não encontrado!');
        alert('Erro: Formulário não encontrado.');
        return;
    }
    
    // Executar a lógica diretamente em vez de apenas disparar submit
    console.log('=== FORMULÁRIO ADICIONAR PARTICIPANTE SUBMETIDO (via função global) ===');
    
    // Verificar torneio_id
    let torneioId = form.find('input[name="torneio_id"]').val();
    if (!torneioId || torneioId === '') {
        torneioId = $('#torneio_id_participante').val();
    }
    if (!torneioId || torneioId === '') {
        torneioId = new URLSearchParams(window.location.search).get('id');
    }
    if (!torneioId || torneioId === '') {
        torneioId = <?php echo $torneio_id; ?>;
    }
    
    console.log('torneio_id capturado:', torneioId);
    
    if (!torneioId || torneioId <= 0) {
        console.error('ERRO: torneio_id inválido ou ausente');
        if (typeof showAlert === 'function') {
            showAlert('Erro: ID do torneio não encontrado. Recarregue a página.', 'danger');
        } else {
            alert('Erro: ID do torneio não encontrado. Recarregue a página.');
        }
        return;
    }
    
    <?php if ($tipoTorneio === 'grupo'): ?>
    var participantes = $('input[name="participantes[]"]:checked');
    if (participantes.length === 0) {
        if (typeof showAlert === 'function') {
            showAlert('Selecione pelo menos um participante.', 'warning');
        } else {
            alert('Selecione pelo menos um participante.');
        }
        return;
    }
    
    // Validar quantidade máxima
    if (typeof maxParticipantesTorneio !== 'undefined' && maxParticipantesTorneio > 0) {
        if (typeof totalParticipantesAtual !== 'undefined') {
            var totalAposAdicao = totalParticipantesAtual + participantes.length;
            if (totalAposAdicao > maxParticipantesTorneio) {
                if (typeof showAlert === 'function') {
                    showAlert('O limite máximo de participantes (' + maxParticipantesTorneio + ') será excedido. Selecione menos participantes.', 'danger');
                } else {
                    alert('O limite máximo de participantes (' + maxParticipantesTorneio + ') será excedido. Selecione menos participantes.');
                }
                return;
            }
        }
    }
    <?php endif; ?>
    
    // Montar payload explicitamente
    const formData = form.serialize();
    console.log('Dados sendo enviados:', formData);
    
    // Desabilitar botão de submit durante o envio
    const btnSubmit = $('#btnAdicionarParticipante');
    const originalText = btnSubmit.html();
    if (btnSubmit.length > 0) {
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Adicionando...');
    }
    
    $.ajax({
        url: '../ajax/adicionar_participante_torneio.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            // Reabilitar botão
            if (btnSubmit.length > 0) {
                btnSubmit.prop('disabled', false).html(originalText);
            }
            
            console.log('Resposta recebida:', response);
            
            if (response.success) {
                if (typeof showAlert === 'function') {
                    showAlert(response.message, 'success');
                } else {
                    alert(response.message);
                }
                var modalElement = document.getElementById('modalAdicionarParticipante');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) modalInstance.hide();
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro ao adicionar participante';
                console.error('Erro ao adicionar participante:', response);
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                if (typeof showAlert === 'function') {
                    showAlert(errorMsg, 'danger');
                } else {
                    alert(errorMsg);
                }
            }
        },
        error: function(xhr, status, error) {
            // Reabilitar botão
            if (btnSubmit.length > 0) {
                btnSubmit.prop('disabled', false).html(originalText);
            }
            
            console.error('Erro AJAX ao adicionar participante:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText
            });
            
            let errorMsg = 'Erro ao adicionar participante. Tente novamente.';
            try {
                const jsonResponse = JSON.parse(xhr.responseText);
                if (jsonResponse.message) {
                    errorMsg = jsonResponse.message;
                }
            } catch (e) {
                if (xhr.status === 404) {
                    errorMsg = 'Arquivo não encontrado. Verifique o caminho do script.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Erro interno do servidor. Verifique os logs.';
                }
            }
            
            if (typeof showAlert === 'function') {
                showAlert(errorMsg, 'danger');
            } else {
                alert(errorMsg);
            }
        }
    });
};
</script>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoConfiguracoes()">
                    <i class="fas fa-chevron-down" id="iconeSecaoConfiguracoes"></i>
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Configurações do Torneio</h5>
                </div>
                <?php 
                $configSalva = ($torneio['quantidade_times'] ?? 0) > 0 && ($torneio['integrantes_por_time'] ?? 0) > 0;
                if ($configSalva): ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editarConfiguracoes()" id="btnEditarConfig">
                        <i class="fas fa-edit me-1"></i>Editar
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="corpoSecaoConfiguracoes">
                <form id="formConfigTorneio" onsubmit="return false;" action="javascript:void(0);" method="post">
                    <input type="hidden" name="torneio_id" id="torneio_id_hidden" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="max_participantes" class="form-label">Quantidade Máxima de Participantes</label>
                            <?php 
                            // Calcular valor inicial baseado em times × integrantes se já estiver configurado
                            $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                            $integrantesPorTime = (int)($torneio['integrantes_por_time'] ?? 0);
                            $maxParticipantesCalculado = ($quantidadeTimes > 0 && $integrantesPorTime > 0) ? ($quantidadeTimes * $integrantesPorTime) : 0;
                            $maxParticipantesInicial = $maxParticipantesCalculado > 0 ? $maxParticipantesCalculado : (int)($torneio['max_participantes'] ?? $torneio['quantidade_participantes'] ?? 0);
                            ?>
                            <input type="number" class="form-control config-field" id="max_participantes" name="max_participantes" 
                                   min="1" value="<?php echo $maxParticipantesInicial; ?>"
                                   onchange="calcularParticipantesNecessarios()" oninput="calcularParticipantesNecessarios()"
                                   <?php echo $configSalva ? 'disabled' : ''; ?>>
                            <small class="text-muted">Atual: <?php echo count($participantes); ?> participantes</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="tipo_time" class="form-label">Tipo de Time</label>
                            <?php 
                            $integrantesAtual = (int)($torneio['integrantes_por_time'] ?? 0);
                            $tipoAtual = '';
                            if ($integrantesAtual === 2) $tipoAtual = 'dupla';
                            elseif ($integrantesAtual === 3) $tipoAtual = 'trio';
                            elseif ($integrantesAtual === 4) $tipoAtual = 'quarteto';
                            elseif ($integrantesAtual === 5) $tipoAtual = 'quinteto';
                            ?>
                            <select class="form-control config-field" id="tipo_time" name="tipo_time" onchange="calcularParticipantesNecessarios()" <?php echo $configSalva ? 'disabled' : ''; ?>>
                                <option value="">Selecione...</option>
                                <option value="dupla" data-integrantes="2" <?php echo $tipoAtual === 'dupla' ? 'selected' : ''; ?>>Dupla</option>
                                <option value="trio" data-integrantes="3" <?php echo $tipoAtual === 'trio' ? 'selected' : ''; ?>>Trio</option>
                                <option value="quarteto" data-integrantes="4" <?php echo $tipoAtual === 'quarteto' ? 'selected' : ''; ?>>Quarteto</option>
                                <option value="quinteto" data-integrantes="5" <?php echo $tipoAtual === 'quinteto' ? 'selected' : ''; ?>>Quinteto</option>
                            </select>
                            <input type="hidden" id="integrantes_por_time" name="integrantes_por_time" value="<?php echo $integrantesAtual; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantidade_times" class="form-label">Quantidade de Times</label>
                            <input type="number" class="form-control config-field" id="quantidade_times" name="quantidade_times" 
                                   min="2" value="<?php echo (int)($torneio['quantidade_times'] ?? 0); ?>"
                                   readonly style="background-color: #e9ecef; cursor: not-allowed;"
                                   <?php echo $configSalva ? 'disabled' : ''; ?>>
                            <small class="text-info d-block mt-1" id="info_quantidade_times_auto">
                                <i class="fas fa-info-circle me-1"></i>Calculado automaticamente
                            </small>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" id="btnSalvarConfig" onclick="console.log('=== CLIQUE NO BOTÃO SALVAR CONFIGURAÇÕES ==='); console.log('Verificando função...'); if(typeof window.processarSalvarConfiguracoes === 'function') { console.log('Função encontrada, chamando...'); window.processarSalvarConfiguracoes(); } else if(typeof processarSalvarConfiguracoes === 'function') { console.log('Função encontrada (sem window), chamando...'); processarSalvarConfiguracoes(); } else { console.error('ERRO: Função processarSalvarConfiguracoes não encontrada!'); alert('Erro: Função não encontrada. Verifique o console (F12) para mais detalhes.'); } return false;" <?php echo $configSalva ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-save me-1"></i>Salvar Configurações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Participantes -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="if(typeof window.toggleListaParticipantes === 'function') { window.toggleListaParticipantes(); } else { console.error('ERRO: window.toggleListaParticipantes não encontrada!'); } return false;">
                    <i class="fas fa-chevron-right" id="iconeParticipantes"></i>
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Participantes
                        <span class="badge bg-secondary ms-2"><?php echo count($participantes); ?></span>
                    </h5>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if (!empty($participantes)): ?>
                        <span class="text-muted small me-2"><?php echo count($participantes); ?> participante(s)</span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary" onclick="console.log('=== BOTÃO ADICIONAR PARTICIPANTE CLICADO ==='); console.log('Verificando função...'); if(typeof window.abrirModalAdicionarParticipante === 'function') { console.log('Função encontrada, chamando...'); window.abrirModalAdicionarParticipante(); } else if(typeof abrirModalAdicionarParticipante === 'function') { console.log('Função encontrada (sem window), chamando...'); abrirModalAdicionarParticipante(); } else { console.error('ERRO: Função abrirModalAdicionarParticipante não encontrada!'); alert('Erro: Função não encontrada. Verifique o console (F12) para mais detalhes.'); } return false;" title="Adicionar Participante">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm <?php echo $inscricoes_abertas ? 'btn-success' : 'btn-outline-success'; ?>" 
                            onclick="toggleInscricoes()" 
                            id="btnToggleInscricoes"
                            title="<?php echo $inscricoes_abertas ? 'Inscrições Abertas - Clique para fechar' : 'Inscrições Fechadas - Clique para abrir'; ?>">
                        <i class="fas <?php echo $inscricoes_abertas ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                    </button>
                    <?php if (!empty($participantes)): ?>
                        <button class="btn btn-sm btn-danger" onclick="limparTodosParticipantes()" title="Remover todos os participantes">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="corpoListaParticipantes" style="display: none;">
                <div id="listaParticipantes">
                    <?php 
                    if (empty($participantes)): 
                    ?>
                        <p class="text-muted mb-0">Nenhum participante adicionado ainda.</p>
                        
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($participantes as $p): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php 
                                        // Verificar se é usuário ou participante avulso
                                        $temUsuario = isset($p['usuario_id']) && !empty($p['usuario_id']) && (int)$p['usuario_id'] > 0;
                                        $temNomeAvulso = isset($p['nome_avulso']) && !empty($p['nome_avulso']);
                                        
                                        // Debug
                                        error_log("Participante ID: " . $p['id'] . ", usuario_id: " . ($p['usuario_id'] ?? 'NULL') . ", nome_avulso: " . ($p['nome_avulso'] ?? 'NULL'));
                                        
                                        if ($temUsuario): 
                                            $avatar = $p['usuario_foto'] ?: '../../assets/arquivos/logo.png';
                                            // Corrigir caminho da foto de perfil
                                            if (!empty($avatar)) {
                                                // Se já começa com http ou /, usar como está
                                                if (strpos($avatar, 'http') === 0 || strpos(trim($avatar), '/') === 0) {
                                                    // Já é um caminho absoluto ou começa com /
                                                } elseif (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                    // Se já tem assets/, garantir que comece com ../../
                                                    if (strpos($avatar, '../../') !== 0) {
                                                        if (strpos($avatar, '../') === 0) {
                                                        $avatar = '../' . ltrim($avatar, '/');
                                                        } else {
                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                        }
                                                    }
                                                } else {
                                                    // Se não tem caminho, adicionar caminho completo
                                                    $avatar = '../../assets/arquivos/' . ltrim($avatar, '/');
                                                }
                                            } else {
                                                $avatar = '../../assets/arquivos/logo.png';
                                            }
                                            $nome = isset($p['usuario_nome']) && !empty($p['usuario_nome']) ? $p['usuario_nome'] : 'Usuário não encontrado';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="24" height="24" style="object-fit:cover;">
                                            <span><?php echo htmlspecialchars($nome); ?></span>
                                        <?php elseif ($temNomeAvulso): ?>
                                            <i class="fas fa-user"></i>
                                            <span><?php echo htmlspecialchars($p['nome_avulso']); ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                            <span>
                                                <?php 
                                                if ($temNomeAvulso) {
                                                    echo htmlspecialchars($p['nome_avulso']);
                                                } else {
                                                    echo 'Participante #' . $p['id'];
                                                    if (isset($p['usuario_id'])) {
                                                        echo ' (Usuario ID: ' . $p['usuario_id'] . ')';
                                                    }
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger" onclick="removerParticipante(<?php echo $p['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Solicitações de Participação -->
    <?php 
    // Verificar se há solicitações pendentes, mesmo que inscrições estejam fechadas
    $sql_check_solicitacoes = "SELECT COUNT(*) AS total FROM torneio_solicitacoes WHERE torneio_id = ? AND status = 'Pendente'";
    $stmt_check_solicitacoes = executeQuery($pdo, $sql_check_solicitacoes, [$torneio_id]);
    $tem_solicitacoes_pendentes = false;
    if ($stmt_check_solicitacoes) {
        $result = $stmt_check_solicitacoes->fetch();
        $tem_solicitacoes_pendentes = ($result && (int)$result['total'] > 0);
    }
    // Mostrar seção se inscrições estão abertas OU se há solicitações pendentes
    if ($inscricoes_abertas || $tem_solicitacoes_pendentes): 
    ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>Solicitações de Participação
                    <span class="badge bg-warning ms-2" id="badgeSolicitacoes">0</span>
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="carregarSolicitacoes()" title="Atualizar">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="listaSolicitacoes">
                    <p class="text-muted text-center mb-0">
                        <i class="fas fa-spinner fa-spin me-2"></i>Carregando solicitações...
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Times do Torneio -->
<?php 
$quantidadeTimes = $torneio['quantidade_times'] ?? 0;
// Usar a quantidade real do banco se houver times, senão usar a configurada
$quantidadeTimesExibicao = $quantidade_times_db > 0 ? $quantidade_times_db : $quantidadeTimes;

if ($quantidadeTimes > 0 || $quantidade_times_db > 0): 
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2 mb-2" style="cursor: pointer;" onclick="toggleSecaoTimes()">
                    <i class="fas fa-chevron-right" id="iconeSecaoTimes"></i>
                <div>
                    <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                    <small class="text-muted">
                        <?php if ($quantidade_times_db > 0): ?>
                            <?php echo $quantidade_times_db; ?> time(s) criado(s) no banco de dados
                            <?php if ($quantidade_times_db != $quantidadeTimes): ?>
                                <span class="badge bg-warning text-dark ms-2">Configuração: <?php echo $quantidadeTimes; ?> time(s)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Configuração: <?php echo $quantidadeTimes; ?> time(s) - Nenhum time criado ainda
                        <?php endif; ?>
                    </small>
                </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php 
                    $timesSalvos = $quantidade_times_db > 0;
                    if ($timesSalvos): ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editarTimes()" id="btnEditarTimes">
                            <i class="fas fa-edit me-1"></i>Editar
                        </button>
                    <?php endif; ?>
                    <?php if ($quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-warning times-action-btn" onclick="sortearTimes()" id="btnSortearTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-random me-1"></i>Sortear
                        </button>
                        <button class="btn btn-sm btn-success times-action-btn" onclick="salvarTimes()" id="btnSalvarTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-save me-1"></i>Salvar Times
                        </button>
                    <?php endif; ?>
                    <?php 
                    $integrantesPorTime = $torneio['integrantes_por_time'] ?? null;
                    if ($quantidadeTimes > 0 && !empty($participantes) && $integrantesPorTime): 
                    ?>
                        <button class="btn btn-sm btn-primary times-action-btn" onclick="criarTimes(this)" id="btnCriarTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-magic me-1"></i>Criar Times
                        </button>
                    <?php endif; ?>
                    <?php if ($quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-danger times-action-btn" onclick="limparTimes()" id="btnLimparTimes" <?php echo $timesSalvos ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash me-1"></i>Limpar Times
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($times) && $quantidade_times_db > 0): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirTimes()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="corpoSecaoTimes" style="display: none;">
                <?php if ($quantidade_times_db == 0 && $quantidadeTimes > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum time foi criado ainda. Clique em "Criar Times" para criar <?php echo $quantidadeTimes; ?> time(s) baseado na configuração.
                    </div>
                <?php endif; ?>
                <div class="row" id="containerTimes">
                    <?php 
                    // APENAS usar times do banco de dados - não criar estrutura vazia
                    // Isso evita mostrar times "fantasma" quando não há nada no banco
                    $timesExistentes = [];
                    if (!empty($times)) {
                        // REMOVER DUPLICATAS POR ID antes de usar (segunda verificação de segurança)
                        $times_unicos_por_id = [];
                        $ids_ja_adicionados = [];
                        
                        foreach ($times as $time) {
                            $id = (int)($time['id'] ?? 0);
                            if ($id > 0 && !in_array($id, $ids_ja_adicionados)) {
                                $times_unicos_por_id[] = $time;
                                $ids_ja_adicionados[] = $id;
                            }
                        }
                        
                        $timesExistentes = $times_unicos_por_id;
                        
                        // Ordenar times por ordem antes de exibir
                        usort($timesExistentes, function($a, $b) {
                            $ordemA = isset($a['ordem']) ? (int)$a['ordem'] : 999;
                            $ordemB = isset($b['ordem']) ? (int)$b['ordem'] : 999;
                            if ($ordemA == $ordemB) {
                                $idA = isset($a['id']) ? (int)$a['id'] : 0;
                                $idB = isset($b['id']) ? (int)$b['id'] : 0;
                                return $idA - $idB;
                            }
                            return $ordemA - $ordemB;
                        });
                    }
                    ?>
                    <?php if (empty($timesExistentes)): ?>
                        <div class="col-12">
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                Nenhum time criado. Clique em "Criar Times" para criar os times baseado na configuração.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($timesExistentes as $idx => $time): ?>
                            <div class="col-md-6 col-lg-4 mb-3" data-time-id="<?php echo (int)($time['id'] ?? 0); ?>" data-time-ordem="<?php echo $time['ordem'] ?? 0; ?>">
                            <div class="card h-100" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>20;">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor'] ?? '#007bff'); ?>; border-radius: 4px;"></div>
                                        <?php 
                                        // Permitir renomear times em qualquer modalidade, desde que o time tenha ID
                                        $pode_renomear = true; // Sempre permitir renomear
                                        ?>
                                        <?php if ($pode_renomear && $time['id']): ?>
                                            <span class="nome-time-display" id="nome-time-display-<?php echo $time['id']; ?>" style="cursor: pointer;" onclick="editarNomeTime(<?php echo $time['id']; ?>)">
                                        <strong><?php echo htmlspecialchars($time['nome'] ?? 'Time sem nome'); ?></strong>
                                                <i class="fas fa-edit ms-1 text-muted" style="font-size: 0.8em;"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control form-control-sm nome-time-input d-none" 
                                                   id="nome-time-input-<?php echo $time['id']; ?>" 
                                                   value="<?php echo htmlspecialchars($time['nome'] ?? 'Time sem nome'); ?>"
                                                   style="max-width: 200px;"
                                                   onkeypress="if(event.key === 'Enter') salvarNomeTime(<?php echo $time['id']; ?>);"
                                                   onblur="salvarNomeTime(<?php echo $time['id']; ?>)">
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($time['nome'] ?? 'Time sem nome'); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="adicionarParticipanteAoTime(<?php echo $time['id'] ?? 'null'; ?>, <?php echo $time['ordem']; ?>)"
                                                title="Adicionar Participante">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    <?php if ($time['id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="excluirTime(<?php echo $time['id']; ?>)" title="Excluir Time">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                                    <div id="time-<?php echo $time['id'] ?? 'novo_' . $time['ordem']; ?>" class="time-participantes" 
                                         data-time-numero="<?php echo $time['ordem']; ?>"
                                         style="min-height: 150px;">
                                        <?php if (!empty($time['integrantes'])): ?>
                                            <?php foreach ($time['integrantes'] as $integ): ?>
                                                <div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" 
                                                     data-participante-id="<?php echo $integ['participante_id']; ?>"
                                                     onclick="event.stopPropagation(); selecionarParticipante(this, event)"
                                                     style="cursor: pointer; user-select: none; -webkit-user-select: none;"
                                                     oncontextmenu="return false;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($integ['usuario_id']): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="24" height="24" style="object-fit:cover;">
                                                            <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                        <?php else: ?>
                                                            <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removerParticipanteDoTime(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Times (versão antiga - manter para compatibilidade) -->
<?php if (!empty($times) && $quantidadeTimes == 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Times do Torneio</h5>
                <div>
                    <?php if (!empty($times)): ?>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="imprimirTimes()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                <button class="btn btn-sm btn-warning" onclick="sortearTimes()">
                    <i class="fas fa-random me-1"></i>Sortear Times
                </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="tabela-times">
                    <?php foreach ($times as $time): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card" style="border-left: 4px solid <?php echo htmlspecialchars($time['cor']); ?>;">
                                <div class="card-header d-flex justify-content-between align-items-center" 
                                     style="background-color: <?php echo htmlspecialchars($time['cor']); ?>20;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($time['cor']); ?>; border-radius: 4px;"></div>
                                        <strong><?php echo htmlspecialchars($time['nome']); ?></strong>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="adicionarIntegranteTime(<?php echo $time['id']; ?>)"
                                                title="Adicionar Integrante">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="excluirTime(<?php echo $time['id']; ?>)" title="Excluir Time">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="integrantes-time-<?php echo $time['id']; ?>">
                                        <?php if (empty($time['integrantes'])): ?>
                                            <p class="text-muted mb-2"><small>Nenhum integrante</small></p>
                                        <?php else: ?>
                                            <?php foreach ($time['integrantes'] as $integ): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($integ['usuario_id']): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            // Corrigir caminho da foto de perfil
                                                            if (!empty($avatar)) {
                                                                // Se já começa com http ou /, usar como está
                                                                if (strpos($avatar, 'http') === 0 || strpos(trim($avatar), '/') === 0) {
                                                                    // Já é um caminho absoluto ou começa com /
                                                                } elseif (strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    // Se já tem assets/, garantir que comece com ../
                                                                    if (strpos($avatar, '../') !== 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    }
                                                                } else {
                                                                    // Se não tem caminho, adicionar caminho completo
                                                                    $avatar = '../assets/arquivos/' . ltrim($avatar, '/');
                                                                }
                                                            } else {
                                                                $avatar = '../assets/arquivos/logo.png';
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;">
                                                            <small><?php echo htmlspecialchars($integ['usuario_nome']); ?></small>
                                                        <?php else: ?>
                                                            <small><?php echo htmlspecialchars($integ['nome_avulso']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removerIntegrante(<?php echo $time['id']; ?>, <?php echo $integ['participante_id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formato de Campeonato -->
<?php 
$timesSalvos = $quantidade_times_db > 0;
if ($timesSalvos): 
    // Verificar se já tem modalidade configurada
    $modalidade = $torneio['modalidade'] ?? null;
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2" style="cursor: pointer;" onclick="toggleSecaoFormato()">
                    <i class="fas fa-chevron-right" id="iconeSecaoFormato"></i>
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Formato de Campeonato</h5>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" form="formModalidadeTorneio" class="btn btn-sm btn-primary" id="btnSalvarModalidade">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" id="corpoSecaoFormato" style="display: none;">
                <form id="formModalidadeTorneio">
                    <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Formato *</label>
                            <div class="form-check mb-2">
                                <?php 
                                // Se não pode criar chaves e estava selecionado todos_chaves, forçar todos_contra_todos
                                $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                                $podeCriarChaves = $quantidadeTimes > 0 && $quantidadeTimes % 2 === 0 && $quantidadeTimes >= 4;
                                if (!$podeCriarChaves && $modalidade === 'todos_chaves') {
                                    $modalidade = 'todos_contra_todos';
                                }
                                ?>
                                <input class="form-check-input" type="radio" name="modalidade" id="modalidade_todos_contra_todos" value="todos_contra_todos" <?php echo ($modalidade === 'todos_contra_todos' || (!$podeCriarChaves && $modalidade === 'todos_chaves' && $modalidade !== 'torneio_pro')) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="modalidade_todos_contra_todos">
                                    <strong>Todos contra Todos</strong>
                                    <p class="text-muted small mb-0">Classificação por pontuação. Em caso de empate de vitórias, será considerado o average (diferença de pontos).</p>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <?php 
                                $checkedTorneioPro = ($modalidade === 'torneio_pro') ? 'checked' : '';
                                ?>
                                <input class="form-check-input" type="radio" name="modalidade" id="modalidade_torneio_pro" value="torneio_pro" <?php echo $checkedTorneioPro; ?> onchange="toggleQuantidadeGrupos()">
                                <label class="form-check-label" for="modalidade_torneio_pro">
                                    <strong>Torneio Pro</strong>
                                    <p class="text-muted small mb-0">Times divididos em grupos na 1ª fase. Os 4 melhores de cada grupo avançam para a 2ª fase (todos contra todos).</p>
                                </label>
                            </div>
                            <div class="form-check">
                                <?php 
                                $quantidadeTimes = $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0);
                                $podeCriarChaves = $quantidadeTimes > 0 && $quantidadeTimes % 2 === 0 && $quantidadeTimes >= 4;
                                $disabledChaves = !$podeCriarChaves ? 'disabled' : '';
                                // Se não pode criar chaves mas estava selecionado, desmarcar e forçar todos_contra_todos
                                $checkedChaves = ($modalidade === 'todos_chaves' && $podeCriarChaves) ? 'checked' : '';
                                if (!$podeCriarChaves && $modalidade === 'todos_chaves') {
                                    // Forçar todos_contra_todos se não pode criar chaves
                                    $checkedChaves = '';
                                    $modalidade = 'todos_contra_todos'; // Atualizar para garantir que o outro radio fique marcado
                                }
                                ?>
                                <input class="form-check-input" type="radio" name="modalidade" id="modalidade_todos_chaves" value="todos_chaves" <?php echo $checkedChaves; ?> <?php echo $disabledChaves; ?> onchange="toggleQuantidadeGrupos()" style="<?php echo !$podeCriarChaves ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>">
                                <label class="form-check-label <?php echo !$podeCriarChaves ? 'text-danger' : ''; ?>" for="modalidade_todos_chaves" style="<?php echo !$podeCriarChaves ? 'cursor: not-allowed;' : ''; ?>">
                                    <strong>Todos contra Todos + Chaves</strong>
                                    <p class="text-muted small mb-0">Os times serão divididos em grupos. Dentro de cada grupo, todos se enfrentam. Os melhores de cada grupo avançam para as eliminatórias.</p>
                                </label>
                            </div>
                            <?php if (!$podeCriarChaves && $quantidadeTimes > 0): ?>
                                <div class="alert alert-danger mt-2 mb-0" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Atenção:</strong> A quantidade de times (<?php echo $quantidadeTimes; ?>) não é suficiente para gerar grupos. É necessário um número par de times (mínimo 4) para criar grupos.
                                </div>
                            <?php endif; ?>
                            <div id="divQuantidadeGrupos" style="display: <?php echo (($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') && $podeCriarChaves) ? 'block' : 'none'; ?>;" class="mt-3">
                                <label class="form-label">Selecione a quantidade de grupos *</label>
                                <div id="opcoesChaves">
                                    <?php
                                    if ($podeCriarChaves) {
                                        // Calcular divisores válidos (que resultam em pelo menos 2 times por chave)
                                        $divisores = [];
                                        for ($i = 2; $i <= $quantidadeTimes / 2; $i++) {
                                            if ($quantidadeTimes % $i === 0) {
                                                $timesPorChave = $quantidadeTimes / $i;
                                                if ($timesPorChave >= 2) {
                                                    $divisores[] = $i;
                                                }
                                            }
                                        }
                                        
                                        // Adicionar opção de 1 chave (todos contra todos sem divisão)
                                        // Mas na verdade, isso seria igual a todos_contra_todos, então não vamos incluir
                                        
                                        if (empty($divisores)) {
                                            echo '<p class="text-muted">Nenhuma opção de chaves disponível para ' . $quantidadeTimes . ' times.</p>';
                                        } else {
                                            $quantidadeGruposAtual = (int)($torneio['quantidade_grupos'] ?? 0);
                                            foreach ($divisores as $divisor) {
                                                $timesPorChave = $quantidadeTimes / $divisor;
                                                $checked = ($quantidadeGruposAtual === $divisor) ? 'checked' : '';
                                                echo '<div class="form-check mb-2">';
                                                $letra_grupo = chr(64 + $divisor); // 65 = 'A', 66 = 'B', etc.
                                                echo '<input class="form-check-input" type="radio" name="quantidade_grupos" id="grupos_' . $divisor . '" value="' . $divisor . '" ' . $checked . ' required>';
                                                echo '<label class="form-check-label" for="grupos_' . $divisor . '">';
                                                echo '<strong>' . $divisor . ' grupos</strong> - ' . $timesPorChave . ' time(s) em cada grupo';
                                                echo '</label>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <small class="text-muted d-block mt-2">Os times serão divididos igualmente entre os grupos selecionados.</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="quantidade_quadras" class="form-label">Quantidade de Quadras Disponíveis</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="quantidade_quadras" 
                                   name="quantidade_quadras" 
                                   min="1" 
                                   value="<?php echo (int)($torneio['quantidade_quadras'] ?? 1); ?>"
                                   placeholder="Digite a quantidade de quadras">
                            <small class="text-muted d-block mt-2">Se houver mais de 1 quadra, os jogos serão distribuídos entre elas.</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Jogos de Enfrentamento -->
<?php 
if ($timesSalvos && $modalidade): 
    // Buscar partidas do torneio
    // Primeiro verificar se há partidas no banco para este torneio
    $sql_check_partidas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
    $stmt_check = executeQuery($pdo, $sql_check_partidas, [$torneio_id]);
    $total_partidas_db = $stmt_check ? (int)$stmt_check->fetch()['total'] : 0;
    
    $partidas = [];
    $partidas_2fase = [];
    if ($total_partidas_db > 0) {
    $sql_partidas = "SELECT tp.*, 
                            t1.nome AS time1_nome, t1.cor AS time1_cor,
                            t2.nome AS time2_nome, t2.cor AS time2_cor,
                                tv.nome AS vencedor_nome, tp.vencedor_id,
                            tg.nome AS grupo_nome, tg.id AS grupo_id
                     FROM torneio_partidas tp
                         LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                     LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                     LEFT JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                     WHERE tp.torneio_id = ? AND tp.fase = 'Grupos'
                     ORDER BY COALESCE(tp.grupo_id, 0) ASC, tp.rodada ASC, COALESCE(tp.quadra, 0) ASC, tp.id ASC";
    
    // Verificar se a coluna grupo_id existe (para compatibilidade)
    static $grupo_id_exists = null;
    if ($grupo_id_exists === null) {
        try {
            $check_col = $pdo->query("SHOW COLUMNS FROM torneio_partidas LIKE 'grupo_id'");
            $grupo_id_exists = $check_col && $check_col->rowCount() > 0;
        } catch (Exception $e) {
            $grupo_id_exists = false;
        }
    }
    
    if (!$grupo_id_exists) {
        // Se não existe, remover referência a grupo_id na query
        $sql_partidas = "SELECT tp.*, 
                                t1.nome AS time1_nome, t1.cor AS time1_cor,
                                t2.nome AS time2_nome, t2.cor AS time2_cor,
                                tv.nome AS vencedor_nome,
                                NULL AS grupo_nome, NULL AS grupo_id
                         FROM torneio_partidas tp
                         LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                         LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                         WHERE tp.torneio_id = ? AND tp.fase = 'Grupos'
                         ORDER BY tp.rodada ASC, COALESCE(tp.quadra, 0) ASC, tp.id ASC";
    }
    $stmt_partidas = executeQuery($pdo, $sql_partidas, [$torneio_id]);
    $partidas = $stmt_partidas ? $stmt_partidas->fetchAll() : [];
        
        // Se não encontrou com fase 'Grupos', buscar sem filtro de fase para debug
        if (empty($partidas)) {
            $sql_partidas_sem_fase = "SELECT tp.*, 
                                            t1.nome AS time1_nome, t1.cor AS time1_cor,
                                            t2.nome AS time2_nome, t2.cor AS time2_cor,
                                            tv.nome AS vencedor_nome,
                                            tg.nome AS grupo_nome, tg.id AS grupo_id
                                     FROM torneio_partidas tp
                                     LEFT JOIN torneio_times t1 ON t1.id = tp.time1_id AND t1.torneio_id = tp.torneio_id
                                     LEFT JOIN torneio_times t2 ON t2.id = tp.time2_id AND t2.torneio_id = tp.torneio_id
                                     LEFT JOIN torneio_times tv ON tv.id = tp.vencedor_id
                                     LEFT JOIN torneio_grupos tg ON tg.id = tp.grupo_id
                                     WHERE tp.torneio_id = ?
                                     ORDER BY COALESCE(tp.grupo_id, 0) ASC, tp.rodada ASC, COALESCE(tp.quadra, 0) ASC, tp.id ASC";
            $stmt_partidas_sem_fase = executeQuery($pdo, $sql_partidas_sem_fase, [$torneio_id]);
            $partidas_sem_fase = $stmt_partidas_sem_fase ? $stmt_partidas_sem_fase->fetchAll() : [];
            
            // Se encontrou partidas sem o filtro de fase, usar essas (pode ser que a fase não esteja sendo salva corretamente)
            if (!empty($partidas_sem_fase)) {
                $partidas = $partidas_sem_fase;
            }
        }
        
        // Reorganizar partidas para evitar que o mesmo time jogue consecutivamente
        if (!empty($partidas)) {
            // Agrupar partidas por grupo e rodada
            $partidas_por_grupo_rodada = [];
            foreach ($partidas as $partida) {
                $grupo_id = $partida['grupo_id'] ?? 0;
                $rodada = $partida['rodada'] ?? 1;
                $chave = $grupo_id . '_' . $rodada;
                
                if (!isset($partidas_por_grupo_rodada[$chave])) {
                    $partidas_por_grupo_rodada[$chave] = [];
                }
                $partidas_por_grupo_rodada[$chave][] = $partida;
            }
            
            // Reorganizar cada grupo/rodada
            $partidas_finais = [];
            foreach ($partidas_por_grupo_rodada as $chave => $partidas_grupo) {
                $partidas_ordenadas = [];
                $partidas_restantes = array_values($partidas_grupo); // Reindexar array
                $ultimos_times = []; // Rastrear os últimos times para evitar repetição
                
                // Algoritmo melhorado para distribuir partidas evitando repetições
                while (!empty($partidas_restantes)) {
                    $melhor_idx = null;
                    $melhor_score = -1;
                    
                    // Tentar encontrar a melhor partida que evite repetição
                    foreach ($partidas_restantes as $idx => $partida) {
                        $time1_id = $partida['time1_id'];
                        $time2_id = $partida['time2_id'];
                        $score = 0;
                        
                        // Verificar se algum dos times já foi usado recentemente
                        if (empty($ultimos_times)) {
                            // Primeira partida - qualquer uma serve
                            $score = 100;
                        } else {
                            // Verificar quantas vezes cada time aparece nos últimos
                            $count_time1 = count(array_filter($ultimos_times, function($t) use ($time1_id) { return $t == $time1_id; }));
                            $count_time2 = count(array_filter($ultimos_times, function($t) use ($time2_id) { return $t == $time2_id; }));
                            
                            // Penalizar se algum time apareceu recentemente
                            $score = 100 - ($count_time1 * 30) - ($count_time2 * 30);
                            
                            // Bonus se nenhum time apareceu recentemente
                            if ($count_time1 == 0 && $count_time2 == 0) {
                                $score = 100;
                            }
                        }
                        
                        if ($score > $melhor_score) {
                            $melhor_score = $score;
                            $melhor_idx = $idx;
                        }
                    }
                    
                    // Adicionar a melhor partida encontrada
                    if ($melhor_idx !== null) {
                        $partida = $partidas_restantes[$melhor_idx];
                        $partidas_ordenadas[] = $partida;
                        
                        // Atualizar últimos times (manter apenas os últimos 3 times para melhor distribuição)
                        $ultimos_times[] = $partida['time1_id'];
                        $ultimos_times[] = $partida['time2_id'];
                        if (count($ultimos_times) > 6) {
                            $ultimos_times = array_slice($ultimos_times, -6);
                        }
                        
                        unset($partidas_restantes[$melhor_idx]);
                        $partidas_restantes = array_values($partidas_restantes); // Reindexar
                    } else {
                        // Fallback: pegar a primeira disponível
                        $partida = reset($partidas_restantes);
                        $partidas_ordenadas[] = $partida;
                        $ultimos_times[] = $partida['time1_id'];
                        $ultimos_times[] = $partida['time2_id'];
                        if (count($ultimos_times) > 6) {
                            $ultimos_times = array_slice($ultimos_times, -6);
                        }
                        unset($partidas_restantes[key($partidas_restantes)]);
                        $partidas_restantes = array_values($partidas_restantes);
                    }
                }
                
                $partidas_finais = array_merge($partidas_finais, $partidas_ordenadas);
            }
            
            // Se conseguiu reorganizar, usar as partidas reorganizadas
            if (!empty($partidas_finais)) {
                $partidas = $partidas_finais;
            }
        }
    }
    
    // Buscar partidas da 2ª fase (Torneio Pro)
    if ($modalidade === 'torneio_pro') {
        $partidas_2fase = [];
        
        // Buscar jogos todos contra todos da 2ª fase (nova tabela partidas_2fase_torneio)
        $sql_partidas_2fase_todos = "SELECT p.id, p.torneio_id, p.time1_id, p.time2_id, p.grupo_id, 
                                        p.rodada, p.quadra, p.pontos_time1, p.pontos_time2, p.vencedor_id,
                                        p.status, p.data_partida, p.data_criacao,
                                        t1.nome AS time1_nome, t1.cor AS time1_cor,
                                        t2.nome AS time2_nome, t2.cor AS time2_cor,
                                        tv.nome AS vencedor_nome,
                                        tg.nome AS grupo_nome,
                                        'Todos Contra Todos' AS tipo_fase
                                 FROM partidas_2fase_torneio p
                                 LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                                 LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                                 LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                                 LEFT JOIN torneio_grupos tg ON tg.id = p.grupo_id
                                 WHERE p.torneio_id = ?
                                 ORDER BY p.grupo_id ASC, p.rodada ASC, COALESCE(p.quadra, 0) ASC, p.id ASC";
        
        $stmt_partidas_2fase_todos = executeQuery($pdo, $sql_partidas_2fase_todos, [$torneio_id]);
        $partidas_todos = $stmt_partidas_2fase_todos ? $stmt_partidas_2fase_todos->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Buscar jogos eliminatórios da 2ª fase (nova tabela partidas_2fase_eliminatorias)
        $sql_partidas_2fase_elim = "SELECT p.id, p.torneio_id, p.time1_id, p.time2_id, NULL AS grupo_id,
                                        p.rodada, p.quadra, p.pontos_time1, p.pontos_time2, p.vencedor_id,
                                        p.status, p.data_partida, p.data_criacao,
                                        t1.nome AS time1_nome, t1.cor AS time1_cor,
                                        t2.nome AS time2_nome, t2.cor AS time2_cor,
                                        tv.nome AS vencedor_nome,
                                        CONCAT('2ª Fase - Ouro - Chaves') AS grupo_nome,
                                        p.tipo_eliminatoria AS tipo_fase
                                 FROM partidas_2fase_eliminatorias p
                                 LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                                 LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                                 LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                                 WHERE p.torneio_id = ?
                                 ORDER BY p.rodada ASC, COALESCE(p.quadra, 0) ASC, p.id ASC";
        
        $stmt_partidas_2fase_elim = executeQuery($pdo, $sql_partidas_2fase_elim, [$torneio_id]);
        $partidas_elim = $stmt_partidas_2fase_elim ? $stmt_partidas_2fase_elim->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Combinar resultados das duas tabelas
        $partidas_2fase = array_merge($partidas_todos, $partidas_elim);
        
        // Ordenar por grupo_id, rodada, quadra e id
        usort($partidas_2fase, function($a, $b) {
            $grupo_a = $a['grupo_id'] ?? 0;
            $grupo_b = $b['grupo_id'] ?? 0;
            if ($grupo_a != $grupo_b) {
                return $grupo_a - $grupo_b;
            }
            if ($a['rodada'] != $b['rodada']) {
                return $a['rodada'] - $b['rodada'];
            }
            $quadra_a = $a['quadra'] ?? 0;
            $quadra_b = $b['quadra'] ?? 0;
            if ($quadra_a != $quadra_b) {
                return $quadra_a - $quadra_b;
            }
            return $a['id'] - $b['id'];
        });
        
        // Debug: verificar se há partidas retornadas
        error_log("DEBUG - Total de partidas 2ª fase encontradas: " . count($partidas_2fase) . " (Todos: " . count($partidas_todos) . ", Eliminatórias: " . count($partidas_elim) . ")");
    }
    
    // Verificar se há eliminatórias geradas (antes de usar nas partidas)
    $tem_eliminatorias = false;
    if ($modalidade === 'todos_chaves') {
        $sql_chaves_check = "SELECT COUNT(*) as total FROM torneio_chaves_times WHERE torneio_id = ?";
        $stmt_chaves_check = executeQuery($pdo, $sql_chaves_check, [$torneio_id]);
        $chaves_check = $stmt_chaves_check ? $stmt_chaves_check->fetch() : ['total' => 0];
        $tem_eliminatorias = (int)$chaves_check['total'] > 0;
    }
    
    // Verificar se final e 3º lugar estão finalizadas (para mostrar botão encerrar)
    $pode_encerrar = false;
    $final_finalizada_check = false;
    $terceiro_finalizado_check = false;
    
    // Buscar grupos se for modalidade todos_chaves
    $grupos = [];
    if ($modalidade === 'todos_chaves') {
        $sql_grupos = "SELECT * FROM torneio_grupos WHERE torneio_id = ? ORDER BY ordem ASC";
        $stmt_grupos = executeQuery($pdo, $sql_grupos, [$torneio_id]);
        $grupos = $stmt_grupos ? $stmt_grupos->fetchAll() : [];
    }
    
    // Buscar integrantes dos times para cada partida
    $integrantes_por_time = [];
    foreach ($partidas as $partida) {
        $time1_id = $partida['time1_id'];
        $time2_id = $partida['time2_id'];
        
        // Buscar integrantes do time 1 se ainda não buscados
        if (!isset($integrantes_por_time[$time1_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time1_id]);
            $integrantes_por_time[$time1_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
        
        // Buscar integrantes do time 2 se ainda não buscados
        if (!isset($integrantes_por_time[$time2_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time2_id]);
            $integrantes_por_time[$time2_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
    }
    
    // Buscar classificação por grupo se for torneio_pro (para exibir após cada chave)
    $classificacao_por_grupo_jogos = [];
    $classificacao_geral_torneio_pro = [];
    if ($modalidade === 'torneio_pro') {
        // Verificar se a coluna grupo_id existe na tabela torneio_classificacao
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
        $tem_grupo_id_classificacao = $columnsQuery && $columnsQuery->rowCount() > 0;
        
        if ($tem_grupo_id_classificacao) {
            // Buscar grupos/chaves da 1ª fase apenas (excluir grupos da 2ª fase)
            $sql_grupos = "SELECT id, nome, ordem FROM torneio_grupos WHERE torneio_id = ? AND nome NOT LIKE '2ª Fase%' ORDER BY ordem ASC";
            $stmt_grupos = executeQuery($pdo, $sql_grupos, [$torneio_id]);
            $grupos_classificacao = $stmt_grupos ? $stmt_grupos->fetchAll() : [];
            
            foreach ($grupos_classificacao as $grupo) {
                // Buscar TODOS os times do grupo primeiro
                $sql_times_grupo = "SELECT tt.id AS time_id, tt.nome AS time_nome, tt.cor AS time_cor
                                   FROM torneio_times tt
                                   JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                   WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                                   ORDER BY tt.id ASC";
                $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo['id'], $torneio_id]);
                $times_do_grupo = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
                
                // Buscar classificação de cada time do grupo
                $classificacao_grupo = [];
                foreach ($times_do_grupo as $time_grupo) {
                    $time_id = (int)$time_grupo['time_id'];
                    
                    $sql_classificacao = "SELECT tc.*
                                         FROM torneio_classificacao tc
                                         WHERE tc.torneio_id = ? AND tc.time_id = ? AND tc.grupo_id = ?
                                         LIMIT 1";
                    $stmt_classificacao = executeQuery($pdo, $sql_classificacao, [$torneio_id, $time_id, $grupo['id']]);
                    $class_time = $stmt_classificacao ? $stmt_classificacao->fetch(PDO::FETCH_ASSOC) : null;
                    
                    if ($class_time) {
                        $class_time['time_nome'] = $time_grupo['time_nome'];
                        $class_time['time_cor'] = $time_grupo['time_cor'];
                        $classificacao_grupo[] = $class_time;
                    } else {
                        // Se não tem classificação, criar entrada zerada
                        $classificacao_grupo[] = [
                            'time_id' => $time_id,
                            'time_nome' => $time_grupo['time_nome'],
                            'time_cor' => $time_grupo['time_cor'],
                            'vitorias' => 0,
                            'derrotas' => 0,
                            'empates' => 0,
                            'pontos_pro' => 0,
                            'pontos_contra' => 0,
                            'saldo_pontos' => 0,
                            'average' => 0.00,
                            'pontos_total' => 0,
                            'grupo_id' => $grupo['id']
                        ];
                    }
                }
                
                // Ordenar por pontos_total, vitorias, average, saldo_pontos
                usort($classificacao_grupo, function($a, $b) {
                    if ($b['pontos_total'] != $a['pontos_total']) {
                        return $b['pontos_total'] - $a['pontos_total'];
                    }
                    if ($b['vitorias'] != $a['vitorias']) {
                        return $b['vitorias'] - $a['vitorias'];
                    }
                    if ($b['average'] != $a['average']) {
                        return $b['average'] > $a['average'] ? 1 : -1;
                    }
                    return $b['saldo_pontos'] - $a['saldo_pontos'];
                });
                
                $classificacao_por_grupo_jogos[$grupo['id']] = [
                    'grupo' => $grupo,
                    'classificacao' => $classificacao_grupo
                ];
            }
            
            // Buscar classificação geral (somando pontos da 1ª e 2ª fase)
            // Primeiro, buscar classificação da 1ª fase (grupos)
            $sql_classificacao_1fase = "SELECT tc.time_id, 
                                               SUM(tc.vitorias) as vitorias_1fase,
                                               SUM(tc.derrotas) as derrotas_1fase,
                                               SUM(tc.empates) as empates_1fase,
                                               SUM(tc.pontos_pro) as pontos_pro_1fase,
                                               SUM(tc.pontos_contra) as pontos_contra_1fase,
                                               SUM(tc.saldo_pontos) as saldo_pontos_1fase,
                                               AVG(tc.average) as average_1fase,
                                               SUM(tc.pontos_total) as pontos_total_1fase
                                       FROM torneio_classificacao tc
                                       JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                                       WHERE tc.torneio_id = ? AND tg.nome NOT LIKE '2ª Fase%'
                                       GROUP BY tc.time_id";
            $stmt_classificacao_1fase = executeQuery($pdo, $sql_classificacao_1fase, [$torneio_id]);
            $classificacao_1fase = $stmt_classificacao_1fase ? $stmt_classificacao_1fase->fetchAll(PDO::FETCH_ASSOC) : [];
            
            // Buscar classificação da 2ª fase (grupos Ouro A e Ouro B)
            $sql_classificacao_2fase = "SELECT tc.time_id, 
                                               SUM(tc.vitorias) as vitorias_2fase,
                                               SUM(tc.derrotas) as derrotas_2fase,
                                               SUM(tc.empates) as empates_2fase,
                                               SUM(tc.pontos_pro) as pontos_pro_2fase,
                                               SUM(tc.pontos_contra) as pontos_contra_2fase,
                                               SUM(tc.saldo_pontos) as saldo_pontos_2fase,
                                               AVG(tc.average) as average_2fase,
                                               SUM(tc.pontos_total) as pontos_total_2fase
                                       FROM torneio_classificacao tc
                                       JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                                       WHERE tc.torneio_id = ? AND tg.nome LIKE '2ª Fase%'
                                       GROUP BY tc.time_id";
            $stmt_classificacao_2fase = executeQuery($pdo, $sql_classificacao_2fase, [$torneio_id]);
            $classificacao_2fase = $stmt_classificacao_2fase ? $stmt_classificacao_2fase->fetchAll(PDO::FETCH_ASSOC) : [];
            
            // Combinar classificações da 1ª e 2ª fase
            $classificacao_combinada = [];
            foreach ($classificacao_1fase as $class_1fase) {
                $time_id = $class_1fase['time_id'];
                $class_2fase = null;
                foreach ($classificacao_2fase as $c2) {
                    if ($c2['time_id'] == $time_id) {
                        $class_2fase = $c2;
                        break;
                    }
                }
                
                $vitorias_total = (int)$class_1fase['vitorias_1fase'] + (int)($class_2fase['vitorias_2fase'] ?? 0);
                $derrotas_total = (int)$class_1fase['derrotas_1fase'] + (int)($class_2fase['derrotas_2fase'] ?? 0);
                $empates_total = (int)$class_1fase['empates_1fase'] + (int)($class_2fase['empates_2fase'] ?? 0);
                $pontos_pro_total = (int)$class_1fase['pontos_pro_1fase'] + (int)($class_2fase['pontos_pro_2fase'] ?? 0);
                $pontos_contra_total = (int)$class_1fase['pontos_contra_1fase'] + (int)($class_2fase['pontos_contra_2fase'] ?? 0);
                $saldo_pontos_total = (int)$class_1fase['saldo_pontos_1fase'] + (int)($class_2fase['saldo_pontos_2fase'] ?? 0);
                $pontos_total = (int)$class_1fase['pontos_total_1fase'] + (int)($class_2fase['pontos_total_2fase'] ?? 0);
                
                $average_total = 0;
                if ($pontos_contra_total > 0) {
                    $average_total = $pontos_pro_total / $pontos_contra_total;
                }
                
                $classificacao_combinada[$time_id] = [
                    'time_id' => $time_id,
                    'vitorias' => $vitorias_total,
                    'derrotas' => $derrotas_total,
                    'empates' => $empates_total,
                    'pontos_pro' => $pontos_pro_total,
                    'pontos_contra' => $pontos_contra_total,
                    'saldo_pontos' => $saldo_pontos_total,
                    'average' => $average_total,
                    'pontos_total' => $pontos_total
                ];
            }
            
            // Adicionar times que só estão na 2ª fase
            foreach ($classificacao_2fase as $class_2fase) {
                $time_id = $class_2fase['time_id'];
                if (!isset($classificacao_combinada[$time_id])) {
                    $classificacao_combinada[$time_id] = [
                        'time_id' => $time_id,
                        'vitorias' => (int)$class_2fase['vitorias_2fase'],
                        'derrotas' => (int)$class_2fase['derrotas_2fase'],
                        'empates' => (int)$class_2fase['empates_2fase'],
                        'pontos_pro' => (int)$class_2fase['pontos_pro_2fase'],
                        'pontos_contra' => (int)$class_2fase['pontos_contra_2fase'],
                        'saldo_pontos' => (int)$class_2fase['saldo_pontos_2fase'],
                        'average' => (float)$class_2fase['average_2fase'],
                        'pontos_total' => (int)$class_2fase['pontos_total_2fase']
                    ];
                }
            }
            
            // Buscar TODOS os times do torneio e combinar com classificações
            $sql_todos_times = "SELECT id, nome AS time_nome, cor AS time_cor FROM torneio_times WHERE torneio_id = ?";
            $stmt_todos_times = executeQuery($pdo, $sql_todos_times, [$torneio_id]);
            $todos_times = $stmt_todos_times ? $stmt_todos_times->fetchAll(PDO::FETCH_ASSOC) : [];
            
            $classificacao_geral_torneio_pro = [];
            foreach ($todos_times as $time) {
                $time_id = (int)$time['id'];
                
                // Se o time tem classificação combinada, usar ela
                if (isset($classificacao_combinada[$time_id])) {
                    $class = $classificacao_combinada[$time_id];
                    $class['time_nome'] = $time['time_nome'];
                    $class['time_cor'] = $time['time_cor'];
                    $classificacao_geral_torneio_pro[] = $class;
                } else {
                    // Se não tem classificação, criar entrada zerada
                    $classificacao_geral_torneio_pro[] = [
                        'time_id' => $time_id,
                        'time_nome' => $time['time_nome'],
                        'time_cor' => $time['time_cor'],
                        'vitorias' => 0,
                        'derrotas' => 0,
                        'empates' => 0,
                        'pontos_pro' => 0,
                        'pontos_contra' => 0,
                        'saldo_pontos' => 0,
                        'average' => 0.00,
                        'pontos_total' => 0
                    ];
                }
            }
            
            // Ordenar por pontos_total, vitorias, average, saldo_pontos
            usort($classificacao_geral_torneio_pro, function($a, $b) {
                if ($b['pontos_total'] != $a['pontos_total']) {
                    return $b['pontos_total'] - $a['pontos_total'];
                }
                if ($b['vitorias'] != $a['vitorias']) {
                    return $b['vitorias'] - $a['vitorias'];
                }
                if ($b['average'] != $a['average']) {
                    return $b['average'] > $a['average'] ? 1 : -1;
                }
                return $b['saldo_pontos'] - $a['saldo_pontos'];
            });
            
            // Buscar integrantes para classificação
            foreach ($classificacao_geral_torneio_pro as $class) {
                $time_id = $class['time_id'];
                if (!isset($integrantes_por_time[$time_id])) {
                    $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                       FROM torneio_time_integrantes tti
                                       JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                       LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                       WHERE tti.time_id = ?
                                       ORDER BY tp.nome_avulso, u.nome
                                       LIMIT 1";
                    $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
                    $integrantes_por_time[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
                }
            }
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-futbol me-2"></i>Jogos de Enfrentamento - 1ª Fase</h5>
                <div class="d-flex gap-2">
                    <?php 
                    // Verificar se há partidas (válidas ou não) para mostrar o botão
                    $sql_count_todas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ?";
                    $stmt_count_todas = executeQuery($pdo, $sql_count_todas, [$torneio_id]);
                    $total_partidas = $stmt_count_todas ? (int)$stmt_count_todas->fetch()['total'] : 0;
                    
                    // Verificar quantas partidas não estão finalizadas
                    $sql_count_nao_finalizadas = "SELECT COUNT(*) as total FROM torneio_partidas WHERE torneio_id = ? AND status != 'Finalizada'";
                    $stmt_count_nao_finalizadas = executeQuery($pdo, $sql_count_nao_finalizadas, [$torneio_id]);
                    $total_nao_finalizadas = $stmt_count_nao_finalizadas ? (int)$stmt_count_nao_finalizadas->fetch()['total'] : 0;
                    ?>
                    <?php 
                    // Verificar se é modalidade que usa grupos
                    $usa_grupos = in_array($modalidade, ['todos_chaves', 'torneio_pro']);
                    $quantidade_grupos_config = $torneio['quantidade_grupos'] ?? 0;
                    ?>
                    <?php if ($usa_grupos && $quantidade_grupos_config > 0 && $torneio['status'] !== 'Finalizado' && empty($partidas)): ?>
                        <button type="button" class="btn btn-sm btn-info" id="btnDefinirGrupos" onclick="abrirModalDefinirGrupos()">
                            <i class="fas fa-layer-group me-1"></i>Definir Grupos
                        </button>
                    <?php endif; ?>
                    <?php if ($modalidade && $torneio['status'] !== 'Finalizado' && empty($partidas)): ?>
                        <button type="button" class="btn btn-sm btn-success" id="btnIniciarJogos" onclick="iniciarJogos()">
                            <i class="fas fa-play me-1"></i>Iniciar Jogos
                        </button>
                    <?php elseif ($torneio['status'] !== 'Finalizado' && empty($partidas)): ?>
                        <button type="button" class="btn btn-sm btn-success" id="btnIniciarJogos" onclick="iniciarJogos()" style="display: none;">
                            <i class="fas fa-play me-1"></i>Iniciar Jogos
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($partidas) && $total_nao_finalizadas > 0 && $torneio['status'] !== 'Finalizado'): ?>
                        <button class="btn btn-sm btn-warning" onclick="simularResultados()" id="btnSimularResultados">
                            <i class="fas fa-dice me-1"></i>Simular Resultados
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($partidas)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirEnfrentamentos()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                    <?php if (($total_partidas > 0 || !empty($partidas)) && $torneio['status'] !== 'Finalizado'): ?>
                        <button class="btn btn-sm btn-danger" onclick="limparJogos()">
                            <i class="fas fa-trash me-1"></i>Limpar Jogos
                        </button>
                    <?php endif; ?>
                    <?php if ($torneio['status'] === 'Finalizado'): ?>
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-flag-checkered me-1"></i>Torneio Finalizado
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($partidas)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum jogo gerado ainda. Configure o formato do campeonato e clique em "Iniciar Jogos" no cabeçalho acima para gerar os confrontos.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" id="tabela-enfrentamentos">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rodada_atual = 0;
                                $grupo_atual = null;
                                $grupo_anterior = null;
                                $indice_partida = 0;
                                $total_partidas = count($partidas);
                                foreach ($partidas as $partida): 
                                    $indice_partida++;
                                    $mudou_grupo = ($modalidade === 'todos_chaves' || $modalidade === 'torneio_pro') && $partida['grupo_id'] != $grupo_atual;
                                    
                                    // Se mudou de grupo e não é a primeira partida, fechar o container do grupo anterior
                                    if ($mudou_grupo && $grupo_atual !== null):
                                        // Fechar o tbody do grupo anterior se existir
                                        if ($modalidade === 'torneio_pro' && isset($classificacao_por_grupo_jogos[$grupo_atual])):
                                            $grupo_data = $classificacao_por_grupo_jogos[$grupo_atual];
                                            $class_grupo = $grupo_data['classificacao'];
                                            if (!empty($class_grupo)):
                                ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Classificação do grupo -->
                            <div class="card m-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-trophy me-2"></i>Classificação - <?php echo htmlspecialchars($grupo_data['grupo']['nome']); ?>
                                    </h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th width="50">Pos</th>
                                                    <th>Time</th>
                                                    <th class="text-center">Jogos</th>
                                                    <th class="text-center">V</th>
                                                    <th class="text-center">D</th>
                                                    <th class="text-center">Pontos</th>
                                                    <th class="text-center">PF</th>
                                                    <th class="text-center">PS</th>
                                                    <th class="text-center">Saldo</th>
                                                    <th class="text-center">Average</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $posicao = 1;
                                                foreach ($class_grupo as $class): 
                                                ?>
                                                    <tr <?php echo $posicao <= 4 ? 'class="table-success"' : ''; ?>>
                                                        <td><strong><?php echo $posicao++; ?>º</strong></td>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor']); ?>; border-radius: 3px;"></div>
                                                                <strong><?php echo htmlspecialchars($class['time_nome']); ?></strong>
                                                                <?php if (!empty($integrantes_por_time[$class['time_id']])): ?>
                                                                    <?php 
                                                                    $primeiro_integ = $integrantes_por_time[$class['time_id']][0] ?? null;
                                                                    if ($primeiro_integ && $primeiro_integ['usuario_id']):
                                                                        $avatar = $primeiro_integ['foto_perfil'] ?? '';
                                                                        $tem_foto_valida = false;
                                                                        if ($avatar && $avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                                if (strpos($avatar, '../../') !== 0) {
                                                                                    if (strpos($avatar, '../') === 0) {
                                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                                    } else {
                                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                                    }
                                                                                }
                                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                                            }
                                                                            $tem_foto_valida = true;
                                                                        endif;
                                                                    ?>
                                                                        <?php if ($tem_foto_valida): ?>
                                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php 
                                                            $total_jogos_jogados = (int)$class['vitorias'] + (int)$class['derrotas'] + (int)($class['empates'] ?? 0);
                                                            ?>
                                                            <span class="badge bg-info"><?php echo $total_jogos_jogados; ?></span>
                                                        </td>
                                                        <td class="text-center"><span class="badge bg-success"><?php echo $class['vitorias']; ?></span></td>
                                                        <td class="text-center"><span class="badge bg-danger"><?php echo $class['derrotas']; ?></span></td>
                                                        <td class="text-center"><strong><?php echo $class['pontos_total']; ?></strong></td>
                                                        <td class="text-center"><?php echo $class['pontos_pro']; ?></td>
                                                        <td class="text-center"><?php echo $class['pontos_contra']; ?></td>
                                                        <td class="text-center <?php echo $class['saldo_pontos'] > 0 ? 'text-success' : ($class['saldo_pontos'] < 0 ? 'text-danger' : ''); ?>">
                                                            <?php echo $class['saldo_pontos'] > 0 ? '+' : ''; ?><?php echo $class['saldo_pontos']; ?>
                                                        </td>
                                                        <td class="text-center"><?php echo number_format($class['average'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                                <?php 
                                            endif;
                                        endif;
                                    endif;
                                    
                                    // Mostrar cabeçalho de grupo se mudou
                                    if ($mudou_grupo):
                                        $grupo_anterior = $grupo_atual;
                                        $grupo_atual = $partida['grupo_id'];
                                        $quadra_chave = !empty($partida['quadra']) ? (int)$partida['quadra'] : null;
                                ?>
                                    <tr class="table-info">
                                        <td colspan="6" class="p-0">
                                            <div class="card m-2">
                                                <div class="card-header bg-info text-white" style="cursor: pointer;" onclick="toggleJogosChave(<?php echo $grupo_atual; ?>)">
                                                    <h6 class="mb-0 d-flex align-items-center justify-content-between">
                                                        <span>
                                                            <i class="fas fa-chevron-right me-2" id="icon_jogos_chave_<?php echo $grupo_atual; ?>"></i>
                                                            <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($partida['grupo_nome'] ?? 'Grupo'); ?>
                                                            <?php if ($quadra_chave && $quadra_chave > 0): ?>
                                                                <span class="badge bg-light text-dark ms-2">
                                                                    <i class="fas fa-map-marker-alt me-1"></i>Quadra <?php echo $quadra_chave; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </h6>
                                                </div>
                                                <div id="conteudo_chave_<?php echo $grupo_atual; ?>" style="display: none;">
                                                    <div class="card-body p-0">
                                                        <table class="table table-hover mb-0">
                                                            <tbody>
                                <?php endif; ?>
                                    <?php if ($partida['rodada'] != $rodada_atual):
                                        $rodada_atual = $partida['rodada'];
                                ?>
                                    <tr class="table-secondary">
                                        <td colspan="6"><strong>Rodada <?php echo $rodada_atual; ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                    <tr id="partida_row_<?php echo $partida['id']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($partida['time1_nome']); ?></strong>
                                                <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time1_id']): ?>
                                                    <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 justify-content-center">
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time1_<?php echo $partida['id']; ?>" 
                                                       value="<?php echo $partida['pontos_time1']; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $partida['id']; ?>"
                                                       readonly
                                                       disabled>
                                                <span class="mx-1">x</span>
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time2_<?php echo $partida['id']; ?>" 
                                                       value="<?php echo $partida['pontos_time2']; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $partida['id']; ?>"
                                                       readonly
                                                       disabled>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($partida['time2_nome']); ?></strong>
                                                <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time2_id']): ?>
                                                    <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $partida['status'] === 'Finalizada' ? 'success' : ($partida['status'] === 'Em Andamento' ? 'warning' : 'secondary'); ?>">
                                                <?php echo $partida['status']; ?>
                                            </span>
                                            <select class="form-select form-select-sm status-select d-none" 
                                                    id="status_<?php echo $partida['id']; ?>" 
                                                    data-partida-id="<?php echo $partida['id']; ?>"
                                                    disabled>
                                                <option value="Agendada" <?php echo $partida['status'] === 'Agendada' ? 'selected' : ''; ?>>Agendada</option>
                                                <option value="Em Andamento" <?php echo $partida['status'] === 'Em Andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                                <option value="Finalizada" <?php echo $partida['status'] === 'Finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                            </select>
                                        </td>
                                        <td>
                                            <?php if ($torneio['status'] !== 'Finalizado' && !$tem_eliminatorias): ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-success btn-salvar-partida" 
                                                        id="btn_salvar_<?php echo $partida['id']; ?>" 
                                                        onclick="salvarResultadoPartidaInline(<?php echo $partida['id']; ?>)"
                                                        style="display: none;">
                                                    <i class="fas fa-save"></i> Salvar
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary btn-editar-partida" 
                                                        id="btn_editar_<?php echo $partida['id']; ?>" 
                                                        onclick="habilitarEdicaoPartida(<?php echo $partida['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                            </div>
                                            <?php elseif ($tem_eliminatorias): ?>
                                                <span class="text-muted"><i class="fas fa-lock"></i> Eliminatórias geradas</span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-lock"></i> Torneio Finalizado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
                                
                                <?php 
                                // Mostrar classificação da chave após todas as partidas (se for torneio_pro)
                                if ($modalidade === 'torneio_pro' && $grupo_atual !== null && isset($classificacao_por_grupo_jogos[$grupo_atual])):
                                    $grupo_data = $classificacao_por_grupo_jogos[$grupo_atual];
                                    $class_grupo = $grupo_data['classificacao'];
                                    if (!empty($class_grupo)):
                                ?>
                                                <!-- Classificação do grupo -->
                                                <div class="card m-3">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-trophy me-2"></i>Classificação - <?php echo htmlspecialchars($grupo_data['grupo']['nome']); ?>
                                                        </h6>
                                                    </div>
                                                    <div class="card-body p-2">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th width="50">Pos</th>
                                                                    <th>Time</th>
                                                                    <th class="text-center">Jogos</th>
                                                                    <th class="text-center">V</th>
                                                                    <th class="text-center">D</th>
                                                                    <th class="text-center">Pontos</th>
                                                                    <th class="text-center">PF</th>
                                                                    <th class="text-center">PS</th>
                                                                    <th class="text-center">Saldo</th>
                                                                    <th class="text-center">Average</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $posicao = 1;
                                                                foreach ($class_grupo as $class): 
                                                                ?>
                                                                    <tr <?php echo $posicao <= 4 ? 'class="table-success"' : ''; ?>>
                                                                        <td><strong><?php echo $posicao++; ?>º</strong></td>
                                                                        <td>
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor']); ?>; border-radius: 3px;"></div>
                                                                                <strong><?php echo htmlspecialchars($class['time_nome']); ?></strong>
                                                                                <?php if (!empty($integrantes_por_time[$class['time_id']])): ?>
                                                                                    <?php 
                                                                                    $primeiro_integ = $integrantes_por_time[$class['time_id']][0] ?? null;
                                                                                    if ($primeiro_integ && $primeiro_integ['usuario_id']):
                                                                                        $avatar = $primeiro_integ['foto_perfil'] ?? '';
                                                                                        $tem_foto_valida = false;
                                                                                        if ($avatar && $avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                                                if (strpos($avatar, '../../') !== 0) {
                                                                                                    if (strpos($avatar, '../') === 0) {
                                                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                                                    } else {
                                                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                                                    }
                                                                                                }
                                                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                                                            }
                                                                                            $tem_foto_valida = true;
                                                                                        endif;
                                                                                    ?>
                                                                                        <?php if ($tem_foto_valida): ?>
                                                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                                                        <?php endif; ?>
                                                                                    <?php endif; ?>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <?php 
                                                                            $total_jogos_jogados = (int)$class['vitorias'] + (int)$class['derrotas'] + (int)($class['empates'] ?? 0);
                                                                            ?>
                                                                            <span class="badge bg-info"><?php echo $total_jogos_jogados; ?></span>
                                                                        </td>
                                                                        <td class="text-center"><span class="badge bg-success"><?php echo $class['vitorias']; ?></span></td>
                                                                        <td class="text-center"><span class="badge bg-danger"><?php echo $class['derrotas']; ?></span></td>
                                                                        <td class="text-center"><strong><?php echo $class['pontos_total']; ?></strong></td>
                                                                        <td class="text-center"><?php echo $class['pontos_pro']; ?></td>
                                                                        <td class="text-center"><?php echo $class['pontos_contra']; ?></td>
                                                                        <td class="text-center <?php echo $class['saldo_pontos'] > 0 ? 'text-success' : ($class['saldo_pontos'] < 0 ? 'text-danger' : ''); ?>">
                                                                            <?php echo $class['saldo_pontos'] > 0 ? '+' : ''; ?><?php echo $class['saldo_pontos']; ?>
                                                                        </td>
                                                                        <td class="text-center"><?php echo number_format($class['average'], 2); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    </div>
                                                </div>
                                <?php 
                                    endif;
                                endif;
                                ?>
                                
                                <?php 
                                // Mostrar classificação geral no final (apenas para torneio_pro)
                                if ($modalidade === 'torneio_pro' && !empty($classificacao_geral_torneio_pro)):
                                ?>
                                    <tr>
                                        <td colspan="6" class="p-0">
                                            <div class="card m-3 border-primary">
                                                <div class="card-header bg-primary text-white" style="cursor: pointer;" onclick="toggleClassificacaoGeral1Fase()">
                                                    <h5 class="mb-0 d-flex align-items-center justify-content-between">
                                                        <span>
                                                            <i class="fas fa-chevron-right me-2" id="icon_classificacao_geral_1fase"></i>
                                                            <i class="fas fa-trophy me-2"></i>Classificação Geral - 1ª Fase
                                                        </span>
                                                    </h5>
                                                </div>
                                                <div class="card-body p-2" id="classificacao_geral_1fase" style="display: none;">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th width="50">Pos</th>
                                                                    <th>Time</th>
                                                                    <th class="text-center">Jogos</th>
                                                                    <th class="text-center">V</th>
                                                                    <th class="text-center">D</th>
                                                                    <th class="text-center">Pontos</th>
                                                                    <th class="text-center">PF</th>
                                                                    <th class="text-center">PS</th>
                                                                    <th class="text-center">Saldo</th>
                                                                    <th class="text-center">Average</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $posicao_geral = 1;
                                                                foreach ($classificacao_geral_torneio_pro as $class): 
                                                                ?>
                                                                    <tr <?php echo $posicao_geral <= 4 ? 'class="table-success"' : ''; ?>>
                                                                        <td><strong><?php echo $posicao_geral++; ?>º</strong></td>
                                                                        <td>
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor']); ?>; border-radius: 3px;"></div>
                                                                                <strong><?php echo htmlspecialchars($class['time_nome']); ?></strong>
                                                                                <?php if (!empty($integrantes_por_time[$class['time_id']])): ?>
                                                                                    <?php 
                                                                                    $primeiro_integ = $integrantes_por_time[$class['time_id']][0] ?? null;
                                                                                    if ($primeiro_integ && $primeiro_integ['usuario_id']):
                                                                                        $avatar = $primeiro_integ['foto_perfil'] ?? '';
                                                                                        $tem_foto_valida = false;
                                                                                        if ($avatar && $avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                                                if (strpos($avatar, '../../') !== 0) {
                                                                                                    if (strpos($avatar, '../') === 0) {
                                                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                                                    } else {
                                                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                                                    }
                                                                                                }
                                                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                                                            }
                                                                                            $tem_foto_valida = true;
                                                                                        endif;
                                                                                    ?>
                                                                                        <?php if ($tem_foto_valida): ?>
                                                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                                                        <?php endif; ?>
                                                                                    <?php endif; ?>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <?php 
                                                                            $total_jogos_jogados = (int)$class['vitorias'] + (int)$class['derrotas'] + (int)($class['empates'] ?? 0);
                                                                            ?>
                                                                            <span class="badge bg-info"><?php echo $total_jogos_jogados; ?></span>
                                                                        </td>
                                                                        <td class="text-center"><span class="badge bg-success"><?php echo $class['vitorias']; ?></span></td>
                                                                        <td class="text-center"><span class="badge bg-danger"><?php echo $class['derrotas']; ?></span></td>
                                                                        <td class="text-center"><strong><?php echo $class['pontos_total']; ?></strong></td>
                                                                        <td class="text-center"><?php echo $class['pontos_pro']; ?></td>
                                                                        <td class="text-center"><?php echo $class['pontos_contra']; ?></td>
                                                                        <td class="text-center <?php echo $class['saldo_pontos'] > 0 ? 'text-success' : ($class['saldo_pontos'] < 0 ? 'text-danger' : ''); ?>">
                                                                            <?php echo $class['saldo_pontos'] > 0 ? '+' : ''; ?><?php echo $class['saldo_pontos']; ?>
                                                                        </td>
                                                                        <td class="text-center"><?php echo number_format($class['average'], 2); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Botão para Gerar 2ª Fase (Torneio Pro) -->
<?php if ($modalidade === 'torneio_pro'): 
    // Verificar se todas as partidas da 1ª fase estão finalizadas
    $sql_partidas_1fase = "SELECT COUNT(*) as total, 
                          SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                          FROM torneio_partidas 
                          WHERE torneio_id = ? AND (fase = 'Grupos' OR fase IS NULL OR fase = '')";
    $stmt_partidas_1fase = executeQuery($pdo, $sql_partidas_1fase, [$torneio_id]);
    $info_1fase = $stmt_partidas_1fase ? $stmt_partidas_1fase->fetch() : ['total' => 0, 'finalizadas' => 0];
    $todas_1fase_finalizadas = $info_1fase['total'] > 0 && $info_1fase['finalizadas'] == $info_1fase['total'];
    
    // Verificar se os grupos da 2ª fase já existem
    $sql_grupos_2fase = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' ORDER BY ordem ASC";
    $stmt_grupos_2fase = executeQuery($pdo, $sql_grupos_2fase, [$torneio_id]);
    $grupos_2fase_existentes = $stmt_grupos_2fase ? $stmt_grupos_2fase->fetchAll() : [];
    $tem_grupos_2fase = !empty($grupos_2fase_existentes);
    
    // Verificar se há jogos da 2ª fase (nova tabela partidas_2fase_torneio)
    $sql_jogos_2fase = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ?";
    $stmt_jogos_2fase = executeQuery($pdo, $sql_jogos_2fase, [$torneio_id]);
    $tem_jogos_2fase = $stmt_jogos_2fase ? (int)$stmt_jogos_2fase->fetch()['total'] > 0 : false;
    
    // Verificar se há jogos não finalizados da 2ª fase
    $tem_jogos_2fase_nao_finalizados = false;
    if ($tem_jogos_2fase) {
        $sql_jogos_nao_finalizados = "SELECT COUNT(*) as total FROM partidas_2fase_torneio WHERE torneio_id = ? AND status != 'Finalizada'";
        $stmt_jogos_nao_finalizados = executeQuery($pdo, $sql_jogos_nao_finalizados, [$torneio_id]);
        $tem_jogos_2fase_nao_finalizados = $stmt_jogos_nao_finalizados ? (int)$stmt_jogos_nao_finalizados->fetch()['total'] > 0 : false;
    }
    
    // Verificar se todas as partidas todos contra todos estão finalizadas
    $todas_partidas_2fase_finalizadas = false;
    $tem_semifinais = false;
    if ($tem_jogos_2fase) {
        // Verificar se há semi-finais (nova tabela partidas_2fase_eliminatorias)
        $sql_check_semifinais = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias WHERE torneio_id = ? AND tipo_eliminatoria = 'Semi-Final'";
        $stmt_check_semifinais = executeQuery($pdo, $sql_check_semifinais, [$torneio_id]);
        $tem_semifinais = $stmt_check_semifinais ? (int)$stmt_check_semifinais->fetch()['total'] > 0 : false;
        
        if (!$tem_semifinais) {
            // Verificar se todas as partidas todos contra todos estão finalizadas (nova tabela partidas_2fase_torneio)
            $sql_partidas_todos_contra_todos = "SELECT COUNT(*) as total, 
                                               SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                               FROM partidas_2fase_torneio 
                                               WHERE torneio_id = ?";
            $stmt_partidas_todos_contra_todos = executeQuery($pdo, $sql_partidas_todos_contra_todos, [$torneio_id]);
            $info_partidas_todos_contra_todos = $stmt_partidas_todos_contra_todos ? $stmt_partidas_todos_contra_todos->fetch() : ['total' => 0, 'finalizadas' => 0];
            $todas_partidas_2fase_finalizadas = $info_partidas_todos_contra_todos['total'] > 0 && 
                                               $info_partidas_todos_contra_todos['finalizadas'] == $info_partidas_todos_contra_todos['total'];
        }
    }
    
    // DEBUG: Sempre permitir gerar 2ª fase se todas as partidas da 1ª fase estiverem finalizadas
    $pode_gerar_2fase = $todas_1fase_finalizadas && $torneio['status'] !== 'Finalizado';
    $pode_gerar_jogos_2fase = $tem_grupos_2fase && !$tem_jogos_2fase && $torneio['status'] !== 'Finalizado';
    
    // Verificar se pode gerar semi-finais específicas do Ouro
    $pode_gerar_semifinais_ouro = false;
    $tem_semifinais_ouro = false;
    if ($tem_grupos_2fase) {
        // Verificar se existem grupos Ouro A e Ouro B
        $sql_check_ouro_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro A'";
        $sql_check_ouro_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Ouro B'";
        $stmt_ouro_a = executeQuery($pdo, $sql_check_ouro_a, [$torneio_id]);
        $stmt_ouro_b = executeQuery($pdo, $sql_check_ouro_b, [$torneio_id]);
        $tem_ouro_a = $stmt_ouro_a ? $stmt_ouro_a->fetch() : null;
        $tem_ouro_b = $stmt_ouro_b ? $stmt_ouro_b->fetch() : null;
        
        if ($tem_ouro_a && $tem_ouro_b) {
            // Verificar se já existem semi-finais do Ouro (nova tabela partidas_2fase_eliminatorias)
            $sql_check_semifinais_ouro = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                                         WHERE torneio_id = ? AND tipo_eliminatoria = 'Semi-Final'
                                         AND serie IN ('Ouro A', 'Ouro B')";
            $stmt_check_semifinais_ouro = executeQuery($pdo, $sql_check_semifinais_ouro, [$torneio_id]);
            $tem_semifinais_ouro = $stmt_check_semifinais_ouro ? (int)$stmt_check_semifinais_ouro->fetch()['total'] > 0 : false;
            
            if (!$tem_semifinais_ouro && $tem_jogos_2fase) {
                // Verificar se todas as partidas de Ouro A e Ouro B estão finalizadas
                $grupo_ouro_a_id = (int)$tem_ouro_a['id'];
                $grupo_ouro_b_id = (int)$tem_ouro_b['id'];
                
                $sql_partidas_ouro_a = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?
                                       AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
                $stmt_partidas_ouro_a = executeQuery($pdo, $sql_partidas_ouro_a, [$torneio_id, $grupo_ouro_a_id]);
                $info_partidas_ouro_a = $stmt_partidas_ouro_a ? $stmt_partidas_ouro_a->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $sql_partidas_ouro_b = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?";
                $stmt_partidas_ouro_b = executeQuery($pdo, $sql_partidas_ouro_b, [$torneio_id, $grupo_ouro_b_id]);
                $info_partidas_ouro_b = $stmt_partidas_ouro_b ? $stmt_partidas_ouro_b->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $todas_partidas_ouro_finalizadas = 
                    ($info_partidas_ouro_a['total'] == 0 || $info_partidas_ouro_a['finalizadas'] == $info_partidas_ouro_a['total']) &&
                    ($info_partidas_ouro_b['total'] == 0 || $info_partidas_ouro_b['finalizadas'] == $info_partidas_ouro_b['total']);
                
                $pode_gerar_semifinais_ouro = $todas_partidas_ouro_finalizadas && $torneio['status'] !== 'Finalizado';
            }
        }
    }
    
    // Verificar se pode gerar semi-finais específicas da Prata
    $pode_gerar_semifinais_prata = false;
    $tem_semifinais_prata = false;
    if ($tem_grupos_2fase) {
        // Verificar se existem grupos Prata A e Prata B
        $sql_check_prata_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Prata A'";
        $sql_check_prata_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Prata B'";
        $stmt_prata_a = executeQuery($pdo, $sql_check_prata_a, [$torneio_id]);
        $stmt_prata_b = executeQuery($pdo, $sql_check_prata_b, [$torneio_id]);
        $tem_prata_a = $stmt_prata_a ? $stmt_prata_a->fetch() : null;
        $tem_prata_b = $stmt_prata_b ? $stmt_prata_b->fetch() : null;
        
        if ($tem_prata_a && $tem_prata_b) {
            // Verificar se já existem semi-finais da Prata (nova tabela partidas_2fase_eliminatorias)
            $sql_check_semifinais_prata = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                                         WHERE torneio_id = ? AND tipo_eliminatoria = 'Semi-Final'
                                         AND serie IN ('Prata A', 'Prata B')";
            $stmt_check_semifinais_prata = executeQuery($pdo, $sql_check_semifinais_prata, [$torneio_id]);
            $tem_semifinais_prata = $stmt_check_semifinais_prata ? (int)$stmt_check_semifinais_prata->fetch()['total'] > 0 : false;
            
            if (!$tem_semifinais_prata && $tem_jogos_2fase) {
                // Verificar se todas as partidas de Prata A e Prata B estão finalizadas
                $grupo_prata_a_id = (int)$tem_prata_a['id'];
                $grupo_prata_b_id = (int)$tem_prata_b['id'];
                
                $sql_partidas_prata_a = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?
                                       AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
                $stmt_partidas_prata_a = executeQuery($pdo, $sql_partidas_prata_a, [$torneio_id, $grupo_prata_a_id]);
                $info_partidas_prata_a = $stmt_partidas_prata_a ? $stmt_partidas_prata_a->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $sql_partidas_prata_b = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?";
                $stmt_partidas_prata_b = executeQuery($pdo, $sql_partidas_prata_b, [$torneio_id, $grupo_prata_b_id]);
                $info_partidas_prata_b = $stmt_partidas_prata_b ? $stmt_partidas_prata_b->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $todas_partidas_prata_finalizadas = 
                    ($info_partidas_prata_a['total'] == 0 || $info_partidas_prata_a['finalizadas'] == $info_partidas_prata_a['total']) &&
                    ($info_partidas_prata_b['total'] == 0 || $info_partidas_prata_b['finalizadas'] == $info_partidas_prata_b['total']);
                
                $pode_gerar_semifinais_prata = $todas_partidas_prata_finalizadas && $torneio['status'] !== 'Finalizado';
            }
        }
    }
    
    // Verificar se pode gerar semi-finais específicas do Bronze
    $pode_gerar_semifinais_bronze = false;
    $tem_semifinais_bronze = false;
    if ($tem_grupos_2fase) {
        // Verificar se existem grupos Bronze A e Bronze B
        $sql_check_bronze_a = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Bronze A'";
        $sql_check_bronze_b = "SELECT id FROM torneio_grupos WHERE torneio_id = ? AND nome = '2ª Fase - Bronze B'";
        $stmt_bronze_a = executeQuery($pdo, $sql_check_bronze_a, [$torneio_id]);
        $stmt_bronze_b = executeQuery($pdo, $sql_check_bronze_b, [$torneio_id]);
        $tem_bronze_a = $stmt_bronze_a ? $stmt_bronze_a->fetch() : null;
        $tem_bronze_b = $stmt_bronze_b ? $stmt_bronze_b->fetch() : null;
        
        if ($tem_bronze_a && $tem_bronze_b) {
            // Verificar se já existem semi-finais do Bronze (nova tabela partidas_2fase_eliminatorias)
            $sql_check_semifinais_bronze = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                                         WHERE torneio_id = ? AND tipo_eliminatoria = 'Semi-Final'
                                         AND serie IN ('Bronze A', 'Bronze B')";
            $stmt_check_semifinais_bronze = executeQuery($pdo, $sql_check_semifinais_bronze, [$torneio_id]);
            $tem_semifinais_bronze = $stmt_check_semifinais_bronze ? (int)$stmt_check_semifinais_bronze->fetch()['total'] > 0 : false;
            
            if (!$tem_semifinais_bronze && $tem_jogos_2fase) {
                // Verificar se todas as partidas de Bronze A e Bronze B estão finalizadas
                $grupo_bronze_a_id = (int)$tem_bronze_a['id'];
                $grupo_bronze_b_id = (int)$tem_bronze_b['id'];
                
                $sql_partidas_bronze_a = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?
                                       AND (tipo_fase IS NULL OR tipo_fase = 'Todos Contra Todos' OR tipo_fase = '')";
                $stmt_partidas_bronze_a = executeQuery($pdo, $sql_partidas_bronze_a, [$torneio_id, $grupo_bronze_a_id]);
                $info_partidas_bronze_a = $stmt_partidas_bronze_a ? $stmt_partidas_bronze_a->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $sql_partidas_bronze_b = "SELECT COUNT(*) as total, 
                                       SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                       FROM partidas_2fase_torneio 
                                       WHERE torneio_id = ? AND grupo_id = ?";
                $stmt_partidas_bronze_b = executeQuery($pdo, $sql_partidas_bronze_b, [$torneio_id, $grupo_bronze_b_id]);
                $info_partidas_bronze_b = $stmt_partidas_bronze_b ? $stmt_partidas_bronze_b->fetch() : ['total' => 0, 'finalizadas' => 0];
                
                $todas_partidas_bronze_finalizadas = 
                    ($info_partidas_bronze_a['total'] == 0 || $info_partidas_bronze_a['finalizadas'] == $info_partidas_bronze_a['total']) &&
                    ($info_partidas_bronze_b['total'] == 0 || $info_partidas_bronze_b['finalizadas'] == $info_partidas_bronze_b['total']);
                
                $pode_gerar_semifinais_bronze = $todas_partidas_bronze_finalizadas && $torneio['status'] !== 'Finalizado';
            }
        }
    }
    
    // Sempre mostrar a seção se pode gerar 2ª fase ou se já tem grupos
    if ($pode_gerar_2fase || $pode_gerar_jogos_2fase || $tem_grupos_2fase):
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Tabela Classificatória da 2ª Fase</h5>
                <div>
                    <?php if ($pode_gerar_2fase): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarSegundaFaseTorneioPro()" id="btnGerar2Fase">
                            <i class="fas fa-play me-1"></i>Gerar 2ª Fase
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_jogos_2fase): ?>
                        <button class="btn btn-sm btn-primary" onclick="gerarJogosSegundaFaseTorneioPro()">
                            <i class="fas fa-futbol me-1"></i>Gerar Jogos da 2ª Fase
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_semifinais_ouro): ?>
                        <button class="btn btn-sm btn-warning" onclick="gerarSemifinaisOuro()" title="Gerar 2 jogos eliminatórios entre os 2 melhores times de Ouro A e os 2 melhores de Ouro B">
                            <i class="fas fa-medal me-1"></i>Gerar Semi-Finais Ouro
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_semifinais_prata): ?>
                        <button class="btn btn-sm btn-secondary" onclick="gerarSemifinaisPrata()" title="Gerar 2 jogos eliminatórios entre os 2 melhores times de Prata A e os 2 melhores de Prata B">
                            <i class="fas fa-medal me-1"></i>Gerar Semi-Finais Prata
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_semifinais_bronze): ?>
                        <button class="btn btn-sm" style="background-color: #8B4513; color: white;" onclick="gerarSemifinaisBronze()" title="Gerar 2 jogos eliminatórios entre os 2 melhores times de Bronze A e os 2 melhores de Bronze B">
                            <i class="fas fa-medal me-1"></i>Gerar Semi-Finais Bronze
                        </button>
                    <?php endif; ?>
                    <?php 
                    // Função auxiliar para verificar se pode gerar final de uma série
                    function verificarPodeGerarFinal($pdo, $torneio_id, $serie, $status_torneio) {
                        $pode_gerar = false;
                        $tem_final = false;
                        
                        // Buscar semi-finais da série
                        $sql_semifinais = "SELECT COUNT(*) as total, 
                                         SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                         FROM partidas_2fase_eliminatorias 
                                         WHERE torneio_id = ? AND tipo_eliminatoria = 'Semi-Final'
                                         AND (serie = ? OR serie = ? OR serie = ? OR serie IS NULL OR serie = '')";
                        $stmt_semifinais = executeQuery($pdo, $sql_semifinais, [
                            $torneio_id,
                            $serie,
                            $serie . ' A',
                            $serie . ' B'
                        ]);
                        $info_semifinais = $stmt_semifinais ? $stmt_semifinais->fetch() : ['total' => 0, 'finalizadas' => 0];
                        
                        // Verificar se há final da série
                        $sql_check_final = "SELECT COUNT(*) as total FROM partidas_2fase_eliminatorias 
                                           WHERE torneio_id = ? AND tipo_eliminatoria = 'Final'
                                           AND (serie = ? OR serie = ? OR serie = ? OR serie IS NULL OR serie = '')";
                        $stmt_check_final = executeQuery($pdo, $sql_check_final, [
                            $torneio_id,
                            $serie,
                            $serie . ' A',
                            $serie . ' B'
                        ]);
                        $tem_final = $stmt_check_final ? (int)$stmt_check_final->fetch()['total'] > 0 : false;
                        
                        $pode_gerar = $info_semifinais['total'] == 2 && 
                                     $info_semifinais['finalizadas'] == 2 && 
                                     !$tem_final && 
                                     $status_torneio !== 'Finalizado';
                        
                        return $pode_gerar;
                    }
                    
                    $pode_gerar_final_ouro = verificarPodeGerarFinal($pdo, $torneio_id, 'Ouro', $torneio['status']);
                    $pode_gerar_final_prata = verificarPodeGerarFinal($pdo, $torneio_id, 'Prata', $torneio['status']);
                    $pode_gerar_final_bronze = verificarPodeGerarFinal($pdo, $torneio_id, 'Bronze', $torneio['status']);
                    ?>
                    <?php if ($pode_gerar_final_ouro): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarFinalOuro()" title="Gerar a final do Ouro com os 2 vencedores das semi-finais">
                            <i class="fas fa-trophy me-1"></i>Gerar Final Ouro
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_final_prata): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarFinal('Prata')" title="Gerar a final da Prata com os 2 vencedores das semi-finais">
                            <i class="fas fa-trophy me-1"></i>Gerar Final Prata
                        </button>
                    <?php endif; ?>
                    <?php if ($pode_gerar_final_bronze): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarFinal('Bronze')" title="Gerar a final do Bronze com os 2 vencedores das semi-finais">
                            <i class="fas fa-trophy me-1"></i>Gerar Final Bronze
                        </button>
                    <?php endif; ?>
                    <?php if ($tem_grupos_2fase): ?>
                        <?php if ($tem_jogos_2fase): ?>
                            <!-- Mostrar botão Limpar quando há jogos -->
                            <button class="btn btn-sm btn-danger" onclick="limparSegundaFaseTorneioPro()" id="btnLimpar2Fase">
                                <i class="fas fa-trash me-1"></i>Limpar Jogos da 2ª Fase
                            </button>
                            <?php if ($tem_jogos_2fase_nao_finalizados): ?>
                                <!-- Mostrar botão Simular quando há jogos não finalizados -->
                                <button class="btn btn-sm btn-warning" onclick="simularResultados2Fase()" id="btnSimularResultados2Fase">
                                    <i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Mostrar botão Simular quando não há jogos -->
                            <button class="btn btn-sm btn-warning" onclick="simularResultados2Fase()" id="btnSimularResultados2Fase">
                                <i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($pode_gerar_2fase): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Todas as partidas da 1ª fase foram finalizadas. Clique em "Gerar 2ª Fase" para criar os grupos Ouro A e Ouro B conforme a classificação dos grupos.
                    </div>
                <?php endif; ?>
                
                <?php if ($tem_grupos_2fase): ?>
                    <?php 
                    // Buscar classificação da 2ª fase para exibir (apenas Pos, Time, Origem)
                    $classificacao_2fase_por_grupo = [];
                    $columnsQuery_2fase = $pdo->query("SHOW COLUMNS FROM torneio_classificacao LIKE 'grupo_id'");
                    $tem_grupo_id_classificacao_2fase = $columnsQuery_2fase && $columnsQuery_2fase->rowCount() > 0;
                    
                    if ($tem_grupo_id_classificacao_2fase) {
                        foreach ($grupos_2fase_existentes as $grupo_2fase) {
                            $grupo_id = (int)$grupo_2fase['id'];
                            $grupo_nome = str_replace('2ª Fase - ', '', $grupo_2fase['nome']);
                            
                            // Ignorar grupos de classificação e chaves
                            if (strpos($grupo_nome, 'Classificação') !== false || strpos($grupo_nome, 'Chaves') !== false) {
                                continue;
                            }
                            
                            // Buscar TODOS os times do grupo da 2ª fase
                            $sql_times_grupo_2fase = "SELECT tt.id AS time_id, tt.nome AS time_nome, tt.cor AS time_cor
                                                     FROM torneio_times tt
                                                     JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                                     WHERE tgt.grupo_id = ? AND tt.torneio_id = ?
                                                     ORDER BY tt.id ASC";
                            $stmt_times_grupo_2fase = executeQuery($pdo, $sql_times_grupo_2fase, [$grupo_id, $torneio_id]);
                            $times_do_grupo_2fase = $stmt_times_grupo_2fase ? $stmt_times_grupo_2fase->fetchAll(PDO::FETCH_ASSOC) : [];
                            
                            // Buscar classificação de cada time do grupo e origem da 1ª fase
                            $classificacao_grupo_2fase = [];
                            foreach ($times_do_grupo_2fase as $time_grupo_2fase) {
                                $time_id = (int)$time_grupo_2fase['time_id'];
                                
                                // Buscar origem do time (chave e posição da 1ª fase)
                                $origem_time = null;
                                $sql_origem = "SELECT tg.nome AS chave_origem, 
                                                      (SELECT COUNT(*) + 1 
                                                       FROM torneio_classificacao tc2 
                                                       WHERE tc2.grupo_id = tc.grupo_id 
                                                       AND tc2.torneio_id = tc.torneio_id
                                                       AND (tc2.pontos_total > tc.pontos_total 
                                                            OR (tc2.pontos_total = tc.pontos_total AND tc2.vitorias > tc.vitorias)
                                                            OR (tc2.pontos_total = tc.pontos_total AND tc2.vitorias = tc.vitorias AND tc2.average > tc.average)
                                                            OR (tc2.pontos_total = tc.pontos_total AND tc2.vitorias = tc.vitorias AND tc2.average = tc.average AND tc2.saldo_pontos > tc.saldo_pontos))) AS posicao_origem
                                               FROM torneio_classificacao tc
                                               JOIN torneio_grupos tg ON tg.id = tc.grupo_id
                                               WHERE tc.torneio_id = ? AND tc.time_id = ? AND tg.nome NOT LIKE '2ª Fase%'
                                               ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC
                                               LIMIT 1";
                                $stmt_origem = executeQuery($pdo, $sql_origem, [$torneio_id, $time_id]);
                                $origem_time = $stmt_origem ? $stmt_origem->fetch(PDO::FETCH_ASSOC) : null;
                                
                                    $classificacao_grupo_2fase[] = [
                                        'time_id' => $time_id,
                                        'time_nome' => $time_grupo_2fase['time_nome'],
                                        'time_cor' => $time_grupo_2fase['time_cor'],
                                        'chave_origem' => $origem_time ? $origem_time['chave_origem'] : null,
                                        'posicao_origem' => $origem_time ? (int)$origem_time['posicao_origem'] : null
                                    ];
                            }
                            
                            $classificacao_2fase_por_grupo[$grupo_id] = [
                                'grupo_nome' => $grupo_nome,
                                'classificacao' => $classificacao_grupo_2fase
                            ];
                        }
                    }
                    
                    // Exibir classificação se houver grupos
                    if (!empty($classificacao_2fase_por_grupo)):
                        // Ordenar grupos: Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B
                        $ordem_grupos = ['Ouro A' => 1, 'Ouro B' => 2, 'Prata A' => 3, 'Prata B' => 4, 'Bronze A' => 5, 'Bronze B' => 6];
                        $grupos_ordenados = [];
                        foreach ($classificacao_2fase_por_grupo as $grupo_id => $dados) {
                            $ordem = $ordem_grupos[$dados['grupo_nome']] ?? 999;
                            $grupos_ordenados[$ordem] = ['grupo_id' => $grupo_id, 'dados' => $dados];
                        }
                        ksort($grupos_ordenados);
                ?>
                        <div class="row mb-3">
                            <?php foreach ($grupos_ordenados as $ordem => $grupo_dados): 
                                $grupo_id_class = $grupo_dados['grupo_id'];
                                $grupo_nome_class = $grupo_dados['dados']['grupo_nome'];
                                $class_grupo_2fase = $grupo_dados['dados']['classificacao'];
                                
                                    // Definir estilos baseado no tipo de grupo
                                    $card_class = 'border-warning';
                                    $header_class = 'bg-warning text-dark';
                                $body_bg_class = 'bg-warning-subtle';
                                    if (strpos($grupo_nome_class, 'Prata') !== false) {
                                        $card_class = 'border-secondary';
                                        $header_class = 'bg-secondary text-white';
                                    $body_bg_class = 'bg-secondary-subtle';
                                    } elseif (strpos($grupo_nome_class, 'Bronze') !== false) {
                                        $card_class = '';
                                        $header_class = 'text-white';
                                    $body_bg_class = 'bg-body-secondary';
                                        $header_style = 'background-color: #8B4513; border-color: #8B4513;';
                                        $card_style = 'border-color: #8B4513;';
                                    } else {
                                        $header_style = '';
                                        $card_style = '';
                                    }
                                    ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card <?php echo $card_class; ?>" <?php echo !empty($card_style) ? 'style="' . $card_style . '"' : ''; ?>>
                                        <div class="card-header <?php echo $header_class; ?>" style="<?php echo $header_style; ?>">
                                            <h6 class="mb-0">
                                                    <i class="fas fa-trophy me-2"></i>Classificação - <?php echo htmlspecialchars($grupo_nome_class); ?>
                                            </h6>
                                        </div>
                                        <div class="card-body p-2 <?php echo $body_bg_class; ?>">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th width="50">Pos</th>
                                                            <th>Time</th>
                                                            <th class="text-center">Origem</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $posicao_2fase = 1;
                                                        foreach ($class_grupo_2fase as $class_2fase): 
                                                        ?>
                                                            <tr>
                                                                <td><strong><?php echo $posicao_2fase++; ?>º</strong></td>
                                                                <td>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class_2fase['time_cor']); ?>; border-radius: 3px;"></div>
                                                                        <strong><?php echo htmlspecialchars($class_2fase['time_nome']); ?></strong>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php if (!empty($class_2fase['chave_origem']) && !empty($class_2fase['posicao_origem'])): 
                                                                        $tooltip_text = $class_2fase['posicao_origem'] . 'º lugar da ' . $class_2fase['chave_origem'];
                                                                    ?>
                                                                        <small class="text-muted" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="<?php echo htmlspecialchars($tooltip_text); ?>">
                                                                            <?php echo htmlspecialchars($class_2fase['chave_origem']); ?><br>
                                                                            <span class="badge bg-secondary"><?php echo $class_2fase['posicao_origem']; ?>º</span>
                                                                        </small>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php 
    endif;
endif; 
?>

<!-- Jogos da 2ª Fase (Torneio Pro) -->
<?php if ($modalidade === 'torneio_pro' && !empty($partidas_2fase)): 
    // Buscar integrantes dos times para a 2ª fase
    $integrantes_2fase = [];
    foreach ($partidas_2fase as $partida) {
        $time1_id = $partida['time1_id'];
        $time2_id = $partida['time2_id'];
        
        if (!isset($integrantes_2fase[$time1_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time1_id]);
            $integrantes_2fase[$time1_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
        
        if (!isset($integrantes_2fase[$time2_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time2_id]);
            $integrantes_2fase[$time2_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
        }
    }
    
    // Agrupar partidas por grupo (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B, e grupos de chaves)
    $partidas_por_grupo_2fase = [];
    
    foreach ($partidas_2fase as $partida) {
        $grupo_id = $partida['grupo_id'] ?? null;
        $grupo_nome = $partida['grupo_nome'] ?? 'Sem Grupo';
        
        // Incluir grupos de chaves do Ouro (Semi-Final Ouro)
        // Ignorar apenas outros grupos de chaves que não sejam do Ouro
        if (strpos($grupo_nome, 'Chaves') !== false) {
            // Incluir se for do Ouro
            if (strpos($grupo_nome, 'Ouro') !== false) {
                // Incluir grupo Ouro - Chaves
            } else {
                // Ignorar outros grupos de chaves (Prata, Bronze, etc.)
                continue;
            }
        }
        
        if (!isset($partidas_por_grupo_2fase[$grupo_id])) {
            $partidas_por_grupo_2fase[$grupo_id] = [
                'grupo_nome' => $grupo_nome,
                'partidas' => []
            ];
        }
        $partidas_por_grupo_2fase[$grupo_id]['partidas'][] = $partida;
    }
    
    // Ordenar grupos: Ouro A, Ouro B, Ouro - Chaves (Semi-Final), Prata A, Prata B, Bronze A, Bronze B
    $ordem_grupos_jogos = ['Ouro A' => 1, 'Ouro B' => 2, 'Ouro - Chaves' => 3, 'Prata A' => 4, 'Prata B' => 5, 'Bronze A' => 6, 'Bronze B' => 7];
    uksort($partidas_por_grupo_2fase, function($a, $b) use ($partidas_por_grupo_2fase, $ordem_grupos_jogos) {
        $nome_a = str_replace('2ª Fase - ', '', $partidas_por_grupo_2fase[$a]['grupo_nome']);
        $nome_b = str_replace('2ª Fase - ', '', $partidas_por_grupo_2fase[$b]['grupo_nome']);
        $ordem_a = $ordem_grupos_jogos[$nome_a] ?? 999;
        $ordem_b = $ordem_grupos_jogos[$nome_b] ?? 999;
        return $ordem_a - $ordem_b;
    });
    
    // Ordenar partidas dentro de cada grupo por tipo e rodada
    foreach ($partidas_por_grupo_2fase as $grupo_id => &$dados_grupo) {
        usort($dados_grupo['partidas'], function($a, $b) {
            // Ordenar por tipo_fase primeiro (Todos Contra Todos, Semi-Final, Final, 3º Lugar)
            $tipo_a = $a['tipo_fase'] ?? '';
            $tipo_b = $b['tipo_fase'] ?? '';
            
            // Se ambos são vazios ou "Todos Contra Todos", considerar como "Todos Contra Todos"
            if (empty($tipo_a) || $tipo_a === 'Todos Contra Todos') {
                $tipo_a = 'Todos Contra Todos';
            }
            if (empty($tipo_b) || $tipo_b === 'Todos Contra Todos') {
                $tipo_b = 'Todos Contra Todos';
            }
            
            // Ordem: Todos Contra Todos (1), Semi-Final (2), Final (3), 3º Lugar (4)
            $ordem_tipo = [
                'Todos Contra Todos' => 1,
                'Semi-Final' => 2,
                'Final' => 3,
                '3º Lugar' => 4
            ];
            
            $ordem_a = $ordem_tipo[$tipo_a] ?? 999;
            $ordem_b = $ordem_tipo[$tipo_b] ?? 999;
            
            if ($ordem_a != $ordem_b) {
                return $ordem_a - $ordem_b;
            }
            
            // Depois por rodada
            if ($a['rodada'] != $b['rodada']) {
                return $a['rodada'] - $b['rodada'];
            }
            
            return 0;
        });
    }
    
    // Buscar grupos da 2ª fase (Ouro A, Ouro B, Prata A, Prata B, Bronze A, Bronze B)
    $grupos_2fase_originais = [];
    if ($tem_grupos_2fase) {
        $sql_grupos_2fase_originais = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome LIKE '2ª Fase%' AND nome NOT LIKE '%Chaves%' ORDER BY ordem ASC";
        $stmt_grupos_2fase_originais = executeQuery($pdo, $sql_grupos_2fase_originais, [$torneio_id]);
        $grupos_2fase_originais = $stmt_grupos_2fase_originais ? $stmt_grupos_2fase_originais->fetchAll() : [];
    }
    
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card <?php echo $tem_jogos_2fase ? 'border-warning' : 'border-primary'; ?>">
            <div class="card-header <?php echo $tem_jogos_2fase ? 'bg-warning text-dark' : 'bg-primary text-white'; ?> d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i><?php echo $tem_jogos_2fase ? 'Jogos da 2ª Fase' : 'Tabela Classificatória da 2ª Fase'; ?></h5>
                <div>
                    <button class="btn btn-sm btn-outline-dark" onclick="imprimirEnfrentamentos2Fase()">
                        <i class="fas fa-print me-1"></i>Imprimir
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($tem_jogos_2fase): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Jogos da 2ª fase gerados! Visualize os jogos e classificações abaixo.
                    </div>
                <?php endif; ?>
                
                        <?php 
                // Agrupar jogos por série (Ouro, Prata, Bronze)
                $jogos_por_serie = ['Ouro' => [], 'Prata' => [], 'Bronze' => []];
                foreach ($partidas_por_grupo_2fase as $grupo_id_jogos => $dados_grupo_jogos):
                    $grupo_nome_jogos = $dados_grupo_jogos['grupo_nome'];
                    $nome_grupo_limpo_jogos = str_replace('2ª Fase - ', '', $grupo_nome_jogos);
                    
                    // Identificar série
                    $serie = null;
                    if (strpos($nome_grupo_limpo_jogos, 'Ouro') !== false) {
                        $serie = 'Ouro';
                    } elseif (strpos($nome_grupo_limpo_jogos, 'Prata') !== false) {
                        $serie = 'Prata';
                    } elseif (strpos($nome_grupo_limpo_jogos, 'Bronze') !== false) {
                        $serie = 'Bronze';
                    }
                    
                    if ($serie) {
                        $jogos_por_serie[$serie][] = [
                            'grupo_id' => $grupo_id_jogos,
                            'grupo_nome' => $grupo_nome_jogos,
                            'nome_limpo' => $nome_grupo_limpo_jogos,
                            'partidas' => $dados_grupo_jogos['partidas']
                        ];
                    }
                endforeach;
                
                // Buscar semi-finais e finais da tabela partidas_2fase_eliminatorias e organizar por série
                $semifinais_por_serie_pre = ['Ouro' => [], 'Prata' => [], 'Bronze' => []];
                $finais_por_serie_pre = ['Ouro' => null, 'Prata' => null, 'Bronze' => null];
                
                if ($modalidade === 'torneio_pro') {
                    // Buscar todas as partidas eliminatórias organizadas por série
                    $series_busca = ['Ouro', 'Prata', 'Bronze'];
                    foreach ($series_busca as $serie_busca) {
                        // Buscar semi-finais da série
                        $sql_semifinais = "SELECT p.*, 
                                          t1.nome AS time1_nome, t1.cor AS time1_cor,
                                          t2.nome AS time2_nome, t2.cor AS time2_cor,
                                          tv.nome AS vencedor_nome
                                          FROM partidas_2fase_eliminatorias p
                                          LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                                          LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                                          LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                                          WHERE p.torneio_id = ? AND p.tipo_eliminatoria = 'Semi-Final' AND p.serie = ?
                                          ORDER BY p.rodada ASC, p.id ASC";
                        $stmt_semifinais = executeQuery($pdo, $sql_semifinais, [$torneio_id, $serie_busca]);
                        $semifinais_por_serie_pre[$serie_busca] = $stmt_semifinais ? $stmt_semifinais->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        // Buscar final da série
                        $sql_final = "SELECT p.*, 
                                     t1.nome AS time1_nome, t1.cor AS time1_cor,
                                     t2.nome AS time2_nome, t2.cor AS time2_cor,
                                     tv.nome AS vencedor_nome
                                     FROM partidas_2fase_eliminatorias p
                                     LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                                     LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                                     LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                                     WHERE p.torneio_id = ? AND p.tipo_eliminatoria = 'Final' AND p.serie = ?
                                     ORDER BY p.rodada ASC, p.id ASC
                                     LIMIT 1";
                        $stmt_final = executeQuery($pdo, $sql_final, [$torneio_id, $serie_busca]);
                        $final_result = $stmt_final ? $stmt_final->fetch(PDO::FETCH_ASSOC) : null;
                        $finais_por_serie_pre[$serie_busca] = $final_result ? $final_result : null;
                    }
                }
                
                // Exibir jogos e classificação por série
                $series_ordenadas = ['Ouro', 'Prata', 'Bronze'];
                foreach ($series_ordenadas as $serie_nome):
                    $jogos_serie = $jogos_por_serie[$serie_nome] ?? [];
                    
                    if (empty($jogos_serie)) {
                        continue;
                    }
                    
                    // Exibir jogos da série
                    foreach ($jogos_serie as $dados_grupo_jogos):
                        $grupo_id_jogos = $dados_grupo_jogos['grupo_id'];
                        $grupo_nome_jogos = $dados_grupo_jogos['grupo_nome'];
                        $partidas_grupo_jogos = $dados_grupo_jogos['partidas'];
                        $nome_grupo_limpo_jogos = $dados_grupo_jogos['nome_limpo'];
                    
                    if (empty($partidas_grupo_jogos)) {
                        continue;
                    }
                    
                    // Para série Ouro: pular grupo "Ouro - Chaves" (semi-finais) pois será exibido após as tabelas de classificação
                    if ($serie_nome === 'Ouro' && strpos($nome_grupo_limpo_jogos, 'Chaves') !== false) {
                        continue;
                    }
                    
                    // Definir cores e estilos por série
                    $cor_grupo_jogos = 'warning';
                    $bg_grupo_jogos = 'bg-warning';
                    $text_grupo_jogos = 'text-dark';
                        if ($serie_nome === 'Prata') {
                        $cor_grupo_jogos = 'secondary';
                        $bg_grupo_jogos = 'bg-secondary';
                        $text_grupo_jogos = 'text-white';
                        } elseif ($serie_nome === 'Bronze') {
                        $cor_grupo_jogos = '';
                        $bg_grupo_jogos = '';
                        $text_grupo_jogos = 'text-white';
                    }
                ?>
                <?php 
                // Verificar se é Prata A, Prata B, Bronze A ou Bronze B para adicionar collapse
                $eh_prata_ou_bronze = (strpos($nome_grupo_limpo_jogos, 'Prata A') !== false || 
                                       strpos($nome_grupo_limpo_jogos, 'Prata B') !== false || 
                                       strpos($nome_grupo_limpo_jogos, 'Bronze A') !== false || 
                                       strpos($nome_grupo_limpo_jogos, 'Bronze B') !== false);
                $collapse_id = $eh_prata_ou_bronze ? 'collapse_jogos_' . $grupo_id_jogos : '';
                ?>
                <div class="card mb-4 <?php echo strpos($nome_grupo_limpo_jogos, 'Bronze') !== false ? 'border' : ''; ?>" <?php echo strpos($nome_grupo_limpo_jogos, 'Bronze') !== false ? 'style="border-color: #8B4513 !important;"' : ''; ?>>
                    <div class="card-header <?php echo $bg_grupo_jogos; ?> <?php echo $text_grupo_jogos; ?> d-flex justify-content-between align-items-center" <?php echo strpos($nome_grupo_limpo_jogos, 'Bronze') !== false ? 'style="background-color: #8B4513 !important; border-color: #8B4513 !important;"' : ''; ?>>
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>
                            <?php 
                            // Mostrar título especial para Semi-Final Ouro ou Final Ouro
                            if (strpos($nome_grupo_limpo_jogos, 'Ouro') !== false && strpos($nome_grupo_limpo_jogos, 'Chaves') !== false) {
                                // Verificar se há partidas de final neste grupo
                                $tem_final = false;
                                foreach ($partidas_grupo_jogos as $p) {
                                    if (($p['tipo_fase'] ?? '') === 'Final') {
                                        $tem_final = true;
                                        break;
                                    }
                                }
                                if ($tem_final) {
                                    echo 'Final Ouro';
                                } else {
                                    echo 'Semi-Final Ouro';
                                }
                            } else {
                                echo 'Jogos - ' . htmlspecialchars($nome_grupo_limpo_jogos);
                            }
                            ?>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($eh_prata_ou_bronze): ?>
                            <button class="btn btn-sm <?php echo $text_grupo_jogos; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="false" aria-controls="<?php echo $collapse_id; ?>" style="background: transparent; border: none; padding: 0.25rem 0.5rem;">
                                <i class="fas fa-chevron-down" id="icon_<?php echo $collapse_id; ?>" style="transition: transform 0.3s;"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body <?php echo $eh_prata_ou_bronze ? 'collapse' : ''; ?>" id="<?php echo $eh_prata_ou_bronze ? $collapse_id : ''; ?>">
                                        <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rodada_atual_2fase = 0;
                                    $tipo_fase_atual = '';
                                    foreach ($partidas_grupo_jogos as $partida): 
                                        // Mostrar tipo de fase se mudou (Semi-Final, 3º Lugar)
                                        $tipo_fase_partida = $partida['tipo_fase'] ?? '';
                                        if ($tipo_fase_partida != $tipo_fase_atual):
                                            $tipo_fase_atual = $tipo_fase_partida;
                                            $label_tipo = '';
                                            if ($tipo_fase_partida === 'Semi-Final') {
                                                // Verificar se é do grupo Ouro - Chaves para mostrar "Semi-Final Ouro"
                                                $grupo_nome_partida = $dados_grupo_jogos['grupo_nome'] ?? '';
                                                if (strpos($grupo_nome_partida, 'Ouro') !== false && strpos($grupo_nome_partida, 'Chaves') !== false) {
                                                    $label_tipo = 'Semi-Final Ouro';
                                                } else {
                                                    $label_tipo = 'Semi-Final';
                                                }
                                            } elseif ($tipo_fase_partida === 'Final') {
                                                // Verificar se é do grupo Ouro - Chaves para mostrar "Final Ouro"
                                                $grupo_nome_partida = $dados_grupo_jogos['grupo_nome'] ?? '';
                                                if (strpos($grupo_nome_partida, 'Ouro') !== false && strpos($grupo_nome_partida, 'Chaves') !== false) {
                                                    $label_tipo = 'Final Ouro';
                                                } else {
                                                    $label_tipo = 'Final';
                                                }
                                            } elseif ($tipo_fase_partida === '3º Lugar') {
                                                $label_tipo = '3º Lugar';
                                            }
                                            if ($label_tipo):
                                    ?>
                                        <tr class="table-info">
                                    <td colspan="5" class="text-center">
                                                <strong><i class="fas fa-trophy me-2"></i><?php echo $label_tipo; ?></strong>
                                    </td>
                                </tr>
                                    <?php 
                                            endif;
                                        endif;
                                        
                                        // Mostrar rodada se mudou
                                        if ($partida['rodada'] != $rodada_atual_2fase):
                                    $rodada_atual_2fase = $partida['rodada'];
                            ?>
                                <tr class="table-secondary">
                                    <td colspan="5"><strong>Rodada <?php echo $rodada_atual_2fase; ?></strong></td>
                                </tr>
                            <?php endif; ?>
                                <tr id="partida_row_<?php echo $partida['id']; ?>" data-tipo-fase="<?php echo htmlspecialchars($partida['tipo_fase'] ?? ''); ?>" data-grupo-nome="<?php echo htmlspecialchars($dados_grupo_jogos['grupo_nome'] ?? ''); ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time1_cor']); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time1_nome']); ?></strong>
                                            <?php if (!empty($integrantes_2fase[$partida['time1_id']])): ?>
                                                <?php 
                                                $primeiro_integ = $integrantes_2fase[$partida['time1_id']][0] ?? null;
                                                if ($primeiro_integ):
                                                    $avatar = $primeiro_integ['foto_perfil'] ?? '';
                                                    $tem_foto_valida = false;
                                                    if ($primeiro_integ['usuario_id'] && !empty($avatar)):
                                                        if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            $tem_foto_valida = true;
                                                        endif;
                                                    endif;
                                                    
                                                    if ($tem_foto_valida):
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                            $primeiro_nome = explode(' ', trim($nome))[0];
                                                            echo htmlspecialchars($primeiro_nome);
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time1_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 justify-content-center">
                                            <input type="number" 
                                                   class="form-control form-control-sm pontos-input" 
                                                   id="pontos_time1_<?php echo $partida['id']; ?>" 
                                                   value="<?php echo $partida['pontos_time1']; ?>" 
                                                   min="0" 
                                                   style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                   data-partida-id="<?php echo $partida['id']; ?>"
                                                   readonly
                                                   disabled>
                                            <span class="mx-1">x</span>
                                            <input type="number" 
                                                   class="form-control form-control-sm pontos-input" 
                                                   id="pontos_time2_<?php echo $partida['id']; ?>" 
                                                   value="<?php echo $partida['pontos_time2']; ?>" 
                                                   min="0" 
                                                   style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                   data-partida-id="<?php echo $partida['id']; ?>"
                                                   readonly
                                                   disabled>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($partida['time2_cor']); ?>; border-radius: 3px;"></div>
                                            <strong><?php echo htmlspecialchars($partida['time2_nome']); ?></strong>
                                            <?php if (!empty($integrantes_2fase[$partida['time2_id']])): ?>
                                                <?php 
                                                $primeiro_integ = $integrantes_2fase[$partida['time2_id']][0] ?? null;
                                                if ($primeiro_integ):
                                                    $avatar = $primeiro_integ['foto_perfil'] ?? '';
                                                    $tem_foto_valida = false;
                                                    if ($primeiro_integ['usuario_id'] && !empty($avatar)):
                                                        if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            $tem_foto_valida = true;
                                                        endif;
                                                    endif;
                                                    
                                                    if ($tem_foto_valida):
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                            $primeiro_nome = explode(' ', trim($nome))[0];
                                                            echo htmlspecialchars($primeiro_nome);
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($partida['status'] === 'Finalizada' && $partida['vencedor_id'] == $partida['time2_id']): ?>
                                                <i class="fas fa-crown text-warning" title="Vencedor"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $partida['status'] === 'Finalizada' ? 'success' : ($partida['status'] === 'Em Andamento' ? 'warning' : 'secondary'); ?>">
                                            <?php echo $partida['status']; ?>
                                        </span>
                                        <select class="form-select form-select-sm status-select d-none" 
                                                id="status_<?php echo $partida['id']; ?>" 
                                                data-partida-id="<?php echo $partida['id']; ?>"
                                                disabled>
                                            <option value="Agendada" <?php echo $partida['status'] === 'Agendada' ? 'selected' : ''; ?>>Agendada</option>
                                            <option value="Em Andamento" <?php echo $partida['status'] === 'Em Andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                            <option value="Finalizada" <?php echo $partida['status'] === 'Finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($torneio['status'] !== 'Finalizado'): ?>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-success btn-salvar-partida" 
                                                    id="btn_salvar_<?php echo $partida['id']; ?>" 
                                                    onclick="salvarResultadoPartidaInline(<?php echo $partida['id']; ?>)"
                                                    style="display: none;">
                                                <i class="fas fa-save"></i> Salvar
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary btn-editar-partida" 
                                                    id="btn_editar_<?php echo $partida['id']; ?>" 
                                                    onclick="habilitarEdicaoPartida(<?php echo $partida['id']; ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                        </div>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-lock"></i> Torneio Finalizado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                    <?php endforeach; // partidas do grupo ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                    <?php endforeach; // grupos de jogos da série ?>
                
                    <?php 
                    // Após exibir os jogos da série, exibir as classificações lado a lado e depois semi-finais/finais
                    // Buscar grupos A e B da série
                    $sql_grupo_a = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
                    $sql_grupo_b = "SELECT id, nome FROM torneio_grupos WHERE torneio_id = ? AND nome = ? LIMIT 1";
                    $stmt_grupo_a = executeQuery($pdo, $sql_grupo_a, [$torneio_id, "2ª Fase - $serie_nome A"]);
                    $stmt_grupo_b = executeQuery($pdo, $sql_grupo_b, [$torneio_id, "2ª Fase - $serie_nome B"]);
                    $grupo_a = $stmt_grupo_a ? $stmt_grupo_a->fetch(PDO::FETCH_ASSOC) : null;
                    $grupo_b = $stmt_grupo_b ? $stmt_grupo_b->fetch(PDO::FETCH_ASSOC) : null;
                    
                    // Buscar classificações se os grupos existirem
                    $classificacao_a = [];
                    $classificacao_b = [];
                    
                    if ($grupo_a):
                        $grupo_a_id = (int)$grupo_a['id'];
                        
                        // Garantir que todos os times do grupo tenham registro
                        $sql_times_grupo = "SELECT DISTINCT tt.id AS time_id
                                           FROM torneio_times tt
                                           JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                           WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
                        $stmt_times_grupo = executeQuery($pdo, $sql_times_grupo, [$grupo_a_id, $torneio_id]);
                        $times_grupo = $stmt_times_grupo ? $stmt_times_grupo->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        foreach ($times_grupo as $time_grupo) {
                            $time_id_grupo = (int)$time_grupo['time_id'];
                            $sql_check_existe = "SELECT id FROM partidas_2fase_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                            $stmt_check_existe = executeQuery($pdo, $sql_check_existe, [$torneio_id, $time_id_grupo, $grupo_a_id]);
                            $existe_registro = $stmt_check_existe ? $stmt_check_existe->fetch() : null;
                            
                            if (!$existe_registro) {
                                $sql_insert_inicial = "INSERT INTO partidas_2fase_classificacao 
                                                      (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                                       pontos_pro, pontos_contra, saldo_pontos, average, pontos_total, posicao)
                                                      VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0, NULL)";
                                executeQuery($pdo, $sql_insert_inicial, [$torneio_id, $time_id_grupo, $grupo_a_id]);
                            }
                        }
                        
                        // Atualizar posições
                        $sql_update_posicoes = "UPDATE partidas_2fase_classificacao tc1
                                                  SET posicao = (
                                                      SELECT COUNT(*) + 1
                                                      FROM partidas_2fase_classificacao tc2
                                                      WHERE tc2.torneio_id = tc1.torneio_id 
                                                      AND tc2.grupo_id = tc1.grupo_id
                                                      AND (
                                                          tc2.pontos_total > tc1.pontos_total
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias > tc1.vitorias)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average > tc1.average)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos > tc1.saldo_pontos)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos = tc1.saldo_pontos AND tc2.time_id < tc1.time_id)
                                                      )
                                                  )
                                                  WHERE tc1.torneio_id = ? AND tc1.grupo_id = ?";
                        executeQuery($pdo, $sql_update_posicoes, [$torneio_id, $grupo_a_id]);
                        
                        // Buscar classificação
                        $sql_class_a = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                                         FROM partidas_2fase_classificacao tc
                                         JOIN torneio_times tt ON tt.id = tc.time_id
                                         WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
                        $stmt_class_a = executeQuery($pdo, $sql_class_a, [$torneio_id, $grupo_a_id]);
                        $classificacao_a = $stmt_class_a ? $stmt_class_a->fetchAll(PDO::FETCH_ASSOC) : [];
                    endif;
                    
                    if ($grupo_b):
                        $grupo_b_id = (int)$grupo_b['id'];
                        
                        // Garantir que todos os times do grupo tenham registro
                        $sql_times_grupo_b = "SELECT DISTINCT tt.id AS time_id
                                           FROM torneio_times tt
                                           JOIN torneio_grupo_times tgt ON tgt.time_id = tt.id
                                           WHERE tgt.grupo_id = ? AND tt.torneio_id = ?";
                        $stmt_times_grupo_b = executeQuery($pdo, $sql_times_grupo_b, [$grupo_b_id, $torneio_id]);
                        $times_grupo_b = $stmt_times_grupo_b ? $stmt_times_grupo_b->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        foreach ($times_grupo_b as $time_grupo_b) {
                            $time_id_grupo_b = (int)$time_grupo_b['time_id'];
                            $sql_check_existe_b = "SELECT id FROM partidas_2fase_classificacao WHERE torneio_id = ? AND time_id = ? AND grupo_id = ?";
                            $stmt_check_existe_b = executeQuery($pdo, $sql_check_existe_b, [$torneio_id, $time_id_grupo_b, $grupo_b_id]);
                            $existe_registro_b = $stmt_check_existe_b ? $stmt_check_existe_b->fetch() : null;
                            
                            if (!$existe_registro_b) {
                                $sql_insert_inicial_b = "INSERT INTO partidas_2fase_classificacao 
                                                          (torneio_id, time_id, grupo_id, vitorias, derrotas, empates, 
                                                           pontos_pro, pontos_contra, saldo_pontos, average, pontos_total, posicao)
                                                          VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0.00, 0, NULL)";
                                executeQuery($pdo, $sql_insert_inicial_b, [$torneio_id, $time_id_grupo_b, $grupo_b_id]);
                            }
                        }
                        
                        // Atualizar posições
                        $sql_update_posicoes_b = "UPDATE partidas_2fase_classificacao tc1
                                                  SET posicao = (
                                                      SELECT COUNT(*) + 1
                                                      FROM partidas_2fase_classificacao tc2
                                                      WHERE tc2.torneio_id = tc1.torneio_id 
                                                      AND tc2.grupo_id = tc1.grupo_id
                                                      AND (
                                                          tc2.pontos_total > tc1.pontos_total
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias > tc1.vitorias)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average > tc1.average)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos > tc1.saldo_pontos)
                                                          OR (tc2.pontos_total = tc1.pontos_total AND tc2.vitorias = tc1.vitorias AND tc2.average = tc1.average AND tc2.saldo_pontos = tc1.saldo_pontos AND tc2.time_id < tc1.time_id)
                                                      )
                                                  )
                                                  WHERE tc1.torneio_id = ? AND tc1.grupo_id = ?";
                        executeQuery($pdo, $sql_update_posicoes_b, [$torneio_id, $grupo_b_id]);
                        
                        // Buscar classificação
                        $sql_class_b = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                                         FROM partidas_2fase_classificacao tc
                                         JOIN torneio_times tt ON tt.id = tc.time_id
                                         WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
                        $stmt_class_b = executeQuery($pdo, $sql_class_b, [$torneio_id, $grupo_b_id]);
                        $classificacao_b = $stmt_class_b ? $stmt_class_b->fetchAll(PDO::FETCH_ASSOC) : [];
                    endif;
                    
                    // Definir cores e estilos por série
                    $card_class_serie = 'border-warning';
                    $header_class_serie = 'bg-warning text-dark';
                    $body_bg_class_serie = 'bg-warning-subtle';
                    if ($serie_nome === 'Prata') {
                        $card_class_serie = 'border-secondary';
                        $header_class_serie = 'bg-secondary text-white';
                        $body_bg_class_serie = 'bg-secondary-subtle';
                    } elseif ($serie_nome === 'Bronze') {
                        $card_class_serie = '';
                        $header_class_serie = 'text-white';
                        $body_bg_class_serie = 'bg-body-secondary';
                    }
                    
                    // Exibir classificações lado a lado se houver dados
                    if (!empty($classificacao_a) || !empty($classificacao_b)):
                ?>
                <div class="row mb-3">
                    <?php if (!empty($classificacao_a)): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card <?php echo $card_class_serie; ?>" <?php echo $serie_nome === 'Bronze' ? 'style="border-color: #8B4513 !important;"' : ''; ?>>
                            <div class="card-header <?php echo $header_class_serie; ?>" <?php echo $serie_nome === 'Bronze' ? 'style="background-color: #8B4513; border-color: #8B4513;"' : ''; ?>>
                                <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - <?php echo $serie_nome; ?> A</h6>
                            </div>
                            <div class="card-body p-2 <?php echo $body_bg_class_serie; ?>">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="40">Pos</th>
                                                <th>Time</th>
                                                <th class="text-center" width="40">J</th>
                                                <th class="text-center" width="35">V</th>
                                                <th class="text-center" width="35">D</th>
                                                <th class="text-center" width="50">Pts</th>
                                                <th class="text-center" width="45">PF</th>
                                                <th class="text-center" width="45">PS</th>
                                                <th class="text-center" width="50">Saldo</th>
                                                <th class="text-center" width="55">Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $pos_a = 1;
                                            foreach ($classificacao_a as $class_a):
                                                $jogos_a = (int)($class_a['vitorias'] ?? 0) + (int)($class_a['derrotas'] ?? 0) + (int)($class_a['empates'] ?? 0);
                                                $vitorias_a = (int)($class_a['vitorias'] ?? 0);
                                                $derrotas_a = (int)($class_a['derrotas'] ?? 0);
                                                $pontos_pro_a = (int)($class_a['pontos_pro'] ?? 0);
                                                $pontos_contra_a = (int)($class_a['pontos_contra'] ?? 0);
                                                $saldo_a = (int)($class_a['saldo_pontos'] ?? 0);
                                                $average_a = (float)($class_a['average'] ?? 0.00);
                                                $pontos_total_a = (int)($class_a['pontos_total'] ?? 0);
                                            ?>
                                            <tr>
                                                <td class="text-center"><strong><?php echo $pos_a++; ?></strong></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="width: 12px; height: 12px; background-color: <?php echo htmlspecialchars($class_a['time_cor']); ?>; border-radius: 2px;"></div>
                                                        <?php echo htmlspecialchars($class_a['time_nome']); ?>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?php echo $jogos_a; ?></td>
                                                <td class="text-center"><?php echo $vitorias_a; ?></td>
                                                <td class="text-center"><?php echo $derrotas_a; ?></td>
                                                <td class="text-center"><strong><?php echo $pontos_total_a; ?></strong></td>
                                                <td class="text-center"><?php echo $pontos_pro_a; ?></td>
                                                <td class="text-center"><?php echo $pontos_contra_a; ?></td>
                                                <td class="text-center"><?php echo $saldo_a; ?></td>
                                                <td class="text-center"><?php echo number_format($average_a, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($classificacao_b)): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card <?php echo $card_class_serie; ?>" <?php echo $serie_nome === 'Bronze' ? 'style="border-color: #8B4513 !important;"' : ''; ?>>
                            <div class="card-header <?php echo $header_class_serie; ?>" <?php echo $serie_nome === 'Bronze' ? 'style="background-color: #8B4513; border-color: #8B4513;"' : ''; ?>>
                                <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - <?php echo $serie_nome; ?> B</h6>
                            </div>
                            <div class="card-body p-2 <?php echo $body_bg_class_serie; ?>">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="40">Pos</th>
                                                <th>Time</th>
                                                <th class="text-center" width="40">J</th>
                                                <th class="text-center" width="35">V</th>
                                                <th class="text-center" width="35">D</th>
                                                <th class="text-center" width="50">Pts</th>
                                                <th class="text-center" width="45">PF</th>
                                                <th class="text-center" width="45">PS</th>
                                                <th class="text-center" width="50">Saldo</th>
                                                <th class="text-center" width="55">Avg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $pos_b = 1;
                                            foreach ($classificacao_b as $class_b):
                                                $jogos_b = (int)($class_b['vitorias'] ?? 0) + (int)($class_b['derrotas'] ?? 0) + (int)($class_b['empates'] ?? 0);
                                                $vitorias_b = (int)($class_b['vitorias'] ?? 0);
                                                $derrotas_b = (int)($class_b['derrotas'] ?? 0);
                                                $pontos_pro_b = (int)($class_b['pontos_pro'] ?? 0);
                                                $pontos_contra_b = (int)($class_b['pontos_contra'] ?? 0);
                                                $saldo_b = (int)($class_b['saldo_pontos'] ?? 0);
                                                $average_b = (float)($class_b['average'] ?? 0.00);
                                                $pontos_total_b = (int)($class_b['pontos_total'] ?? 0);
                                            ?>
                                            <tr>
                                                <td class="text-center"><strong><?php echo $pos_b++; ?></strong></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="width: 12px; height: 12px; background-color: <?php echo htmlspecialchars($class_b['time_cor']); ?>; border-radius: 2px;"></div>
                                                        <?php echo htmlspecialchars($class_b['time_nome']); ?>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?php echo $jogos_b; ?></td>
                                                <td class="text-center"><?php echo $vitorias_b; ?></td>
                                                <td class="text-center"><?php echo $derrotas_b; ?></td>
                                                <td class="text-center"><strong><?php echo $pontos_total_b; ?></strong></td>
                                                <td class="text-center"><?php echo $pontos_pro_b; ?></td>
                                                <td class="text-center"><?php echo $pontos_contra_b; ?></td>
                                                <td class="text-center"><?php echo $saldo_b; ?></td>
                                                <td class="text-center"><?php echo number_format($average_b, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php
                // Exibir semi-finais e finais abaixo das classificações lado a lado
                $semifinais_serie_grupo = $semifinais_por_serie_pre[$serie_nome] ?? [];
                $final_serie_grupo = $finais_por_serie_pre[$serie_nome] ?? null;
                
                if (!empty($semifinais_serie_grupo) || $final_serie_grupo):
                    // Verificar se pode gerar final
                    $pode_gerar_final_serie_grupo = false;
                    if (!empty($semifinais_serie_grupo) && count($semifinais_serie_grupo) === 2) {
                        $todas_semi_finalizadas_serie = true;
                        foreach ($semifinais_serie_grupo as $semi) {
                            if ($semi['status'] !== 'Finalizada') {
                                $todas_semi_finalizadas_serie = false;
                                break;
                            }
                        }
                        $onclick_final_serie = $serie_nome === 'Ouro' ? 'gerarFinalOuro()' : "gerarFinal('$serie_nome')";
                        $pode_gerar_final_serie_grupo = $todas_semi_finalizadas_serie && !$final_serie_grupo && $torneio['status'] !== 'Finalizado';
                    }
                ?>
                <?php if (!empty($semifinais_serie_grupo)): ?>
                <div class="card <?php echo $card_class_serie; ?> mb-2 mt-2" <?php echo $serie_nome === 'Bronze' ? 'style="border-color: #8B4513 !important;"' : ''; ?>>
                    <div class="card-header <?php echo $header_class_serie; ?> d-flex justify-content-between align-items-center" <?php echo $serie_nome === 'Bronze' ? 'style="background-color: #8B4513 !important; border-color: #8B4513 !important;"' : ''; ?>>
                        <h6 class="mb-0"><i class="fas fa-medal me-2"></i>Semi-Final <?php echo $serie_nome; ?></h6>
                        <?php if ($pode_gerar_final_serie_grupo): ?>
                        <button class="btn btn-sm btn-success" onclick="<?php echo $onclick_final_serie; ?>" title="Gerar a final da <?php echo $serie_nome; ?>">
                            <i class="fas fa-trophy me-1"></i>Gerar Final
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-2 <?php echo $body_bg_class_serie; ?>">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="50" class="text-center">Rod</th>
                                        <th>Time 1</th>
                                        <th class="text-center" width="80">Placar</th>
                                        <th>Time 2</th>
                                        <th class="text-center" width="80">Status</th>
                                        <th class="text-center" width="80">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semifinais_serie_grupo as $semi): 
                                        $pontos_time1 = (int)($semi['pontos_time1'] ?? 0);
                                        $pontos_time2 = (int)($semi['pontos_time2'] ?? 0);
                                        $status_semi = $semi['status'] ?? 'Agendada';
                                        $eh_finalizada = $status_semi === 'Finalizada';
                                    ?>
                                    <tr id="partida_row_<?php echo $semi['id']; ?>" data-tipo-fase="Semi-Final">
                                        <td class="text-center"><strong><?php echo $semi['rodada']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <div style="width: 10px; height: 10px; background-color: <?php echo htmlspecialchars($semi['time1_cor'] ?? '#000000'); ?>; border-radius: 2px;"></div>
                                                <small><?php echo htmlspecialchars($semi['time1_nome'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-1 justify-content-center">
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time1_<?php echo $semi['id']; ?>" 
                                                       value="<?php echo $pontos_time1; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $semi['id']; ?>"
                                                       readonly
                                                       disabled>
                                                <span class="mx-1">x</span>
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time2_<?php echo $semi['id']; ?>" 
                                                       value="<?php echo $pontos_time2; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $semi['id']; ?>"
                                                       readonly
                                                       disabled>
                                            </div>
                                            <input type="hidden" id="status_<?php echo $semi['id']; ?>" value="<?php echo $status_semi; ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <div style="width: 10px; height: 10px; background-color: <?php echo htmlspecialchars($semi['time2_cor'] ?? '#000000'); ?>; border-radius: 2px;"></div>
                                                <small><?php echo htmlspecialchars($semi['time2_nome'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($eh_finalizada): ?>
                                                <span class="badge bg-success badge-sm">Finalizada</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-sm">Agendada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($torneio['status'] !== 'Finalizado'): ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-success btn-salvar-partida" 
                                                        id="btn_salvar_<?php echo $semi['id']; ?>" 
                                                        onclick="salvarResultadoPartidaInline(<?php echo $semi['id']; ?>)"
                                                        style="display: none;">
                                                    <i class="fas fa-save"></i> Salvar
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary btn-editar-partida" 
                                                        id="btn_editar_<?php echo $semi['id']; ?>" 
                                                        onclick="habilitarEdicaoPartida(<?php echo $semi['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($final_serie_grupo): ?>
                <div class="card <?php echo $card_class_serie; ?> mt-2" <?php echo $serie_nome === 'Bronze' ? 'style="border-color: #8B4513 !important;"' : ''; ?>>
                    <div class="card-header <?php echo $header_class_serie; ?>" <?php echo $serie_nome === 'Bronze' ? 'style="background-color: #8B4513 !important; border-color: #8B4513 !important;"' : ''; ?>>
                        <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Final <?php echo $serie_nome; ?></h6>
                    </div>
                    <div class="card-body p-2 <?php echo $body_bg_class_serie; ?>">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="50" class="text-center">Rod</th>
                                        <th>Time 1</th>
                                        <th class="text-center" width="80">Placar</th>
                                        <th>Time 2</th>
                                        <th class="text-center" width="80">Status</th>
                                        <th class="text-center" width="80">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pontos_time1_final = (int)($final_serie_grupo['pontos_time1'] ?? 0);
                                    $pontos_time2_final = (int)($final_serie_grupo['pontos_time2'] ?? 0);
                                    $status_final = $final_serie_grupo['status'] ?? 'Agendada';
                                    $eh_finalizada_final = $status_final === 'Finalizada';
                                    ?>
                                    <tr id="partida_row_<?php echo $final_serie_grupo['id']; ?>" data-tipo-fase="Final">
                                        <td class="text-center"><strong><?php echo $final_serie_grupo['rodada']; ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <div style="width: 10px; height: 10px; background-color: <?php echo htmlspecialchars($final_serie_grupo['time1_cor'] ?? '#000000'); ?>; border-radius: 2px;"></div>
                                                <small><?php echo htmlspecialchars($final_serie_grupo['time1_nome'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-1 justify-content-center">
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time1_<?php echo $final_serie_grupo['id']; ?>" 
                                                       value="<?php echo $pontos_time1_final; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $final_serie_grupo['id']; ?>"
                                                       readonly
                                                       disabled>
                                                <span class="mx-1">x</span>
                                                <input type="number" 
                                                       class="form-control form-control-sm pontos-input" 
                                                       id="pontos_time2_<?php echo $final_serie_grupo['id']; ?>" 
                                                       value="<?php echo $pontos_time2_final; ?>" 
                                                       min="0" 
                                                       style="width: 60px; height: 28px; text-align: center; padding: 2px 4px;"
                                                       data-partida-id="<?php echo $final_serie_grupo['id']; ?>"
                                                       readonly
                                                       disabled>
                                            </div>
                                            <input type="hidden" id="status_<?php echo $final_serie_grupo['id']; ?>" value="<?php echo $status_final; ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <div style="width: 10px; height: 10px; background-color: <?php echo htmlspecialchars($final_serie_grupo['time2_cor'] ?? '#000000'); ?>; border-radius: 2px;"></div>
                                                <small><?php echo htmlspecialchars($final_serie_grupo['time2_nome'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($eh_finalizada_final): ?>
                                                <span class="badge bg-success badge-sm">Finalizada</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-sm">Agendada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($torneio['status'] !== 'Finalizado'): ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-success btn-salvar-partida" 
                                                        id="btn_salvar_<?php echo $final_serie_grupo['id']; ?>" 
                                                        onclick="salvarResultadoPartidaInline(<?php echo $final_serie_grupo['id']; ?>)"
                                                        style="display: none;">
                                                    <i class="fas fa-save"></i> Salvar
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary btn-editar-partida" 
                                                        id="btn_editar_<?php echo $final_serie_grupo['id']; ?>" 
                                                        onclick="habilitarEdicaoPartida(<?php echo $final_serie_grupo['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php endforeach; // séries (Ouro, Prata, Bronze) ?>
                
                <?php 
                // Código antigo removido - agora as classificações e semi-finais/finais são exibidas dentro do loop de séries acima
                // Todo o código antigo foi movido para dentro do loop de séries para seguir a ordem correta:
                // 1. Jogos da série
                // 2. Classificações lado a lado (A e B)
                // 3. Semi-finais e finais da série
                // Isso se repete para cada série (Ouro, Prata, Bronze)
                ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Chaves Eliminatórias -->
<?php if ($modalidade === 'todos_chaves'): 
    // Buscar chaves do torneio
    $sql_chaves = "SELECT tc.*, 
                          t1.nome AS time1_nome, t1.cor AS time1_cor,
                          t2.nome AS time2_nome, t2.cor AS time2_cor,
                          tv.nome AS vencedor_nome
                   FROM torneio_chaves_times tc
                   LEFT JOIN torneio_times t1 ON t1.id = tc.time1_id
                   LEFT JOIN torneio_times t2 ON t2.id = tc.time2_id
                   LEFT JOIN torneio_times tv ON tv.id = tc.vencedor_id
                   WHERE tc.torneio_id = ?
                   ORDER BY FIELD(tc.fase, 'Quartas', 'Semi', 'Final', '3º Lugar'), tc.chave_numero ASC";
    $stmt_chaves = executeQuery($pdo, $sql_chaves, [$torneio_id]);
    $chaves = $stmt_chaves ? $stmt_chaves->fetchAll() : [];
    $tem_eliminatorias = !empty($chaves);
    
    // Buscar integrantes dos times para as chaves eliminatórias
    $integrantes_por_time_chaves = [];
    if (!empty($chaves)) {
        foreach ($chaves as $chave) {
            if ($chave['time1_id'] && !isset($integrantes_por_time_chaves[$chave['time1_id']])) {
                $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                   FROM torneio_time_integrantes tti
                                   JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                   LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                   WHERE tti.time_id = ?
                                   ORDER BY tp.nome_avulso, u.nome";
                $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$chave['time1_id']]);
                $integrantes_por_time_chaves[$chave['time1_id']] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
            }
            if ($chave['time2_id'] && !isset($integrantes_por_time_chaves[$chave['time2_id']])) {
                $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                   FROM torneio_time_integrantes tti
                                   JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                   LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                   WHERE tti.time_id = ?
                                   ORDER BY tp.nome_avulso, u.nome";
                $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$chave['time2_id']]);
                $integrantes_por_time_chaves[$chave['time2_id']] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
            }
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Chaves Eliminatórias</h5>
                <div>
                    <?php 
                    // Verificar se todas as partidas da fase de grupos estão finalizadas
                    $sql_partidas_grupos = "SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                                           FROM torneio_partidas 
                                           WHERE torneio_id = ? AND fase = 'Grupos'";
                    $stmt_partidas_grupos = executeQuery($pdo, $sql_partidas_grupos, [$torneio_id]);
                    $info_partidas = $stmt_partidas_grupos ? $stmt_partidas_grupos->fetch() : ['total' => 0, 'finalizadas' => 0];
                    $todas_finalizadas = $info_partidas['total'] > 0 && $info_partidas['finalizadas'] == $info_partidas['total'];
                    $pode_gerar = empty($chaves) && $todas_finalizadas && $torneio['status'] !== 'Finalizado';
                    ?>
                    <?php if ($pode_gerar): ?>
                        <button class="btn btn-sm btn-success" onclick="gerarEliminatorias()">
                            <i class="fas fa-play me-1"></i>Gerar Eliminatórias
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($chaves)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($todas_finalizadas): ?>
                            Clique em "Gerar Eliminatórias" para criar as chaves semi-final e final com os 2 melhores times de cada chave.
                        <?php else: ?>
                            Finalize todos os jogos da fase de grupos para gerar as chaves eliminatórias.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php 
                    $fases = ['Quartas', 'Semi', 'Final', '3º Lugar'];
                    foreach ($fases as $fase):
                        $chaves_fase = array_filter($chaves, function($c) use ($fase) { return $c['fase'] === $fase; });
                        if (!empty($chaves_fase)):
                    ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-center">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div class="rounded border border-primary border-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 100px; height: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 8px rgba(0,0,0,0.2); border-radius: 8px !important;">
                                                    <div class="text-center text-white">
                                                        <strong style="font-size: 12px; font-weight: bold;"><?php 
                                                            if ($fase === 'Semi') {
                                                                echo 'Semi-Final';
                                                            } elseif ($fase === 'Final') {
                                                                echo 'Final';
                                                            } elseif ($fase === '3º Lugar') {
                                                                echo '3º Lugar';
                                                            } else {
                                                                echo $fase;
                                                            }
                                                        ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chaves_fase as $chave): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time1_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                    <strong><?php echo htmlspecialchars($chave['time1_nome'] ?? 'Aguardando'); ?></strong>
                                                    <?php if ($chave['time1_id'] && !empty($integrantes_por_time_chaves[$chave['time1_id']])): ?>
                                                        <?php 
                                                        $primeiro_integ = $integrantes_por_time_chaves[$chave['time1_id']][0];
                                                        $tem_foto_valida = false;
                                                        $avatar = '';
                                                        
                                                        if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                            $avatar = $primeiro_integ['foto_perfil'];
                                                            // Verificar se não é a logo padrão
                                                            if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    if (strpos($avatar, '../../') !== 0) {
                                                                        if (strpos($avatar, '../') === 0) {
                                                                            $avatar = '../' . ltrim($avatar, '/');
                                                                        } else {
                                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                                        }
                                                                    }
                                                                } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                    $avatar = '../../assets/arquivos/' . $avatar;
                                                                }
                                                                $tem_foto_valida = true;
                                                            endif;
                                                        endif;
                                                        
                                                        if ($tem_foto_valida):
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                <?php 
                                                                // Mostrar apenas o primeiro nome do primeiro integrante
                                                                $primeiro_integ = $integrantes_por_time_chaves[$chave['time1_id']][0];
                                                                $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                                $primeiro_nome = explode(' ', trim($nome))[0];
                                                                echo htmlspecialchars($primeiro_nome);
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($chave['status'] === 'Finalizada' && $chave['vencedor_id'] == $chave['time1_id']): ?>
                                                        <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1 justify-content-center">
                                                    <?php if ($chave['time1_id'] && $chave['time2_id']): ?>
                                                        <input type="number" 
                                                               class="form-control form-control-sm pontos-chave-input" 
                                                               id="pontos_time1_chave_<?php echo $chave['id']; ?>" 
                                                               value="<?php echo $chave['pontos_time1']; ?>" 
                                                               min="0" 
                                                               style="width: 60px; height: 30px; text-align: center; padding: 2px 4px;"
                                                               data-chave-id="<?php echo $chave['id']; ?>"
                                                               readonly
                                                               disabled>
                                                        <span class="mx-1">x</span>
                                                        <input type="number" 
                                                               class="form-control form-control-sm pontos-chave-input" 
                                                               id="pontos_time2_chave_<?php echo $chave['id']; ?>" 
                                                               value="<?php echo $chave['pontos_time2']; ?>" 
                                                               min="0" 
                                                               style="width: 60px; height: 30px; text-align: center; padding: 2px 4px;"
                                                               data-chave-id="<?php echo $chave['id']; ?>"
                                                               readonly
                                                               disabled>
                                                <?php else: ?>
                                                        <span class="text-muted">Aguardando</span>
                                                <?php endif; ?>
                                            </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($chave['time2_cor'] ?? '#ccc'); ?>; border-radius: 3px;"></div>
                                                    <strong><?php echo htmlspecialchars($chave['time2_nome'] ?? 'Aguardando'); ?></strong>
                                                    <?php if ($chave['time2_id'] && !empty($integrantes_por_time_chaves[$chave['time2_id']])): ?>
                                                        <?php 
                                                        $primeiro_integ = $integrantes_por_time_chaves[$chave['time2_id']][0];
                                                        $tem_foto_valida = false;
                                                        $avatar = '';
                                                        
                                                        if ($primeiro_integ['usuario_id'] && !empty($primeiro_integ['foto_perfil'])):
                                                            $avatar = $primeiro_integ['foto_perfil'];
                                                            // Verificar se não é a logo padrão
                                                            if ($avatar !== '../../assets/arquivos/logo.png' && $avatar !== '../assets/arquivos/logo.png' && $avatar !== 'assets/arquivos/logo.png' && $avatar !== 'logo.png'):
                                                                if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                    if (strpos($avatar, '../../') !== 0) {
                                                                        if (strpos($avatar, '../') === 0) {
                                                                            $avatar = '../' . ltrim($avatar, '/');
                                                                        } else {
                                                                            $avatar = '../../' . ltrim($avatar, '/');
                                                                        }
                                                                    }
                                                                } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                    $avatar = '../../assets/arquivos/' . $avatar;
                                                                }
                                                                $tem_foto_valida = true;
                                                            endif;
                                                        endif;
                                                        
                                                        if ($tem_foto_valida):
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                <?php 
                                                                // Mostrar apenas o primeiro nome do primeiro integrante
                                                                $primeiro_integ = $integrantes_por_time_chaves[$chave['time2_id']][0];
                                                                $nome = $primeiro_integ['usuario_nome'] ?? $primeiro_integ['nome_avulso'] ?? 'Sem nome';
                                                                $primeiro_nome = explode(' ', trim($nome))[0];
                                                                echo htmlspecialchars($primeiro_nome);
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($chave['status'] === 'Finalizada' && $chave['vencedor_id'] == $chave['time2_id']): ?>
                                                        <i class="fas fa-trophy text-warning" title="Vencedor"></i>
                                                    <?php endif; ?>
                                            </div>
                                            </td>
                                            <td></td>
                                            <td>
                                                <span class="badge bg-<?php echo $chave['status'] === 'Finalizada' ? 'success' : ($chave['status'] === 'Em Andamento' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo $chave['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($chave['time1_id'] && $chave['time2_id'] && $torneio['status'] !== 'Finalizado'): ?>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-success btn-salvar-chave" 
                                                                id="btn_salvar_chave_<?php echo $chave['id']; ?>" 
                                                                onclick="salvarResultadoChave(<?php echo $chave['id']; ?>)"
                                                                style="display: none;">
                                                            <i class="fas fa-save"></i> Salvar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary btn-editar-chave" 
                                                                id="btn_editar_chave_<?php echo $chave['id']; ?>" 
                                                                onclick="habilitarEdicaoChave(<?php echo $chave['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Editar
                                                        </button>
                                                </div>
                                            <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    
                    // Verificar se final e 3º lugar estão finalizadas para mostrar pódio
                    $final_finalizada = false;
                    $terceiro_finalizado = false;
                    $vencedor_final = null;
                    $segundo_lugar = null;
                    $terceiro_lugar = null;
                    
                    foreach ($chaves as $chave) {
                        // Verificar Final
                        if (!empty($chave['fase']) && trim($chave['fase']) === 'Final' && 
                            !empty($chave['status']) && trim($chave['status']) === 'Finalizada' && 
                            !empty($chave['vencedor_id']) && (int)$chave['vencedor_id'] > 0) {
                            $final_finalizada = true;
                            // Buscar dados do vencedor
                            $sql_vencedor = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                            $stmt_vencedor = executeQuery($pdo, $sql_vencedor, [(int)$chave['vencedor_id']]);
                            if ($stmt_vencedor) {
                                $vencedor_final = $stmt_vencedor->fetch(PDO::FETCH_ASSOC);
                            }
                            
                            // Buscar o perdedor (2º lugar) - o time que perdeu a final
                            if (!empty($chave['time1_id']) && !empty($chave['time2_id']) && 
                                (int)$chave['time1_id'] > 0 && (int)$chave['time2_id'] > 0) {
                                $perdedor_id = ((int)$chave['time1_id'] == (int)$chave['vencedor_id']) ? (int)$chave['time2_id'] : (int)$chave['time1_id'];
                                $sql_segundo = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                                $stmt_segundo = executeQuery($pdo, $sql_segundo, [$perdedor_id]);
                                if ($stmt_segundo) {
                                    $segundo_lugar = $stmt_segundo->fetch(PDO::FETCH_ASSOC);
                                }
                            }
                        }
                        // Verificar 3º Lugar
                        if (!empty($chave['fase']) && trim($chave['fase']) === '3º Lugar' && 
                            !empty($chave['status']) && trim($chave['status']) === 'Finalizada' && 
                            !empty($chave['vencedor_id']) && (int)$chave['vencedor_id'] > 0) {
                            $terceiro_finalizado = true;
                            // Buscar dados do 3º lugar
                            $sql_terceiro = "SELECT id, nome, cor FROM torneio_times WHERE id = ?";
                            $stmt_terceiro = executeQuery($pdo, $sql_terceiro, [(int)$chave['vencedor_id']]);
                            if ($stmt_terceiro) {
                                $terceiro_lugar = $stmt_terceiro->fetch(PDO::FETCH_ASSOC);
                            }
                        }
                    }
                    
                    // Verificar se todas as condições estão atendidas para exibir o pódio
                    
                    if ($final_finalizada && $terceiro_finalizado && !empty($vencedor_final) && !empty($segundo_lugar) && !empty($terceiro_lugar)):
                        // Buscar integrantes dos times do pódio
                        $integrantes_podio = [];
                        $times_podio = [$vencedor_final['id'], $segundo_lugar['id'], $terceiro_lugar['id']];
                        foreach ($times_podio as $time_id) {
                            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                               FROM torneio_time_integrantes tti
                                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                               WHERE tti.time_id = ?
                                               ORDER BY tp.nome_avulso, u.nome";
                            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
                            $integrantes_podio[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
                        }
                ?>
                    <div class="mt-4">
                        <h5 class="mb-3"><i class="fas fa-trophy me-2"></i>Pódio</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 80px;">Posição</th>
                                        <th>Time</th>
                                        <th>Participantes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background-color: #ffd700;">
                                        <td class="text-center">
                                            <i class="fas fa-trophy text-warning" style="font-size: 24px;"></i>
                                            <br><strong>1º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($vencedor_final['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($vencedor_final['nome']); ?></strong>
                                    </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$vencedor_final['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                </div>
                                        </td>
                                    </tr>
                                    <tr style="background-color: #c0c0c0;">
                                        <td class="text-center">
                                            <i class="fas fa-medal" style="font-size: 24px; color: #808080;"></i>
                                            <br><strong>2º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($segundo_lugar['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($segundo_lugar['nome']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$segundo_lugar['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                                                            <?php
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                                        </td>
                                    </tr>
                                    <tr style="background-color: #cd7f32;">
                                        <td class="text-center">
                                            <i class="fas fa-medal" style="font-size: 24px; color: #d4a574 !important;"></i>
                                            <br><strong>3º</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($terceiro_lugar['cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($terceiro_lugar['nome']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php foreach ($integrantes_podio[$terceiro_lugar['id']] as $integ): ?>
                                                    <?php if ($integ['usuario_id']): ?>
                                                        <?php if (!empty($integ['foto_perfil'])): ?>
                    <?php 
                                                            $avatar = $integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                            if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                                if (strpos($avatar, '../../') !== 0) {
                                                                    if (strpos($avatar, '../') === 0) {
                                                                        $avatar = '../' . ltrim($avatar, '/');
                                                                    } else {
                                                                        $avatar = '../../' . ltrim($avatar, '/');
                                                                    }
                                                                }
                                                            } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                                $avatar = '../../assets/arquivos/' . $avatar;
                                                            }
                                                            ?>
                                                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                                 class="rounded-circle" width="32" height="32" 
                                                                 style="object-fit:cover;" 
                                                                 alt="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>" 
                                                                 title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['usuario_nome'] ?? $integ['nome_avulso']); ?>">
                                                                <?php echo htmlspecialchars(mb_substr($integ['usuario_nome'] ?? $integ['nome_avulso'], 0, 1)); ?>
                                                            </span>
                <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="<?php echo htmlspecialchars($integ['nome_avulso']); ?>">
                                                            <?php echo htmlspecialchars(mb_substr($integ['nome_avulso'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classificação dos Times -->
<?php if ($timesSalvos && $modalidade && $modalidade !== 'torneio_pro'): 
    // Buscar classificação geral (para formatos que não sejam torneio_pro)
    $sql_classificacao = "SELECT tc.*, tt.nome AS time_nome, tt.cor AS time_cor
                         FROM torneio_classificacao tc
                         JOIN torneio_times tt ON tt.id = tc.time_id
                         WHERE tc.torneio_id = ?
                         ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
    $stmt_classificacao = executeQuery($pdo, $sql_classificacao, [$torneio_id]);
    $classificacao = $stmt_classificacao ? $stmt_classificacao->fetchAll() : [];
    
    // Buscar integrantes dos times para a classificação
    $integrantes_classificacao = [];
    foreach ($classificacao as $class) {
        $time_id = $class['time_id'];
        if (!isset($integrantes_classificacao[$time_id])) {
            $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                               FROM torneio_time_integrantes tti
                               JOIN torneio_participantes tp ON tp.id = tti.participante_id
                               LEFT JOIN usuarios u ON u.id = tp.usuario_id
                               WHERE tti.time_id = ?
                               ORDER BY tp.nome_avulso, u.nome
                               LIMIT 1";
            $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
            $integrantes_classificacao[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetch() : null;
        }
    }
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação dos Times</h5>
                <div>
                    <?php if (!empty($classificacao)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="imprimirClassificacao()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($classificacao)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        A classificação será atualizada automaticamente conforme os jogos são finalizados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" id="tabela-classificacao">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">Pos</th>
                                    <th>Time</th>
                                    <th class="text-center">Jogos</th>
                                    <th class="text-center">V</th>
                                    <th class="text-center">D</th>
                                        <th class="text-center">Pontos</th>
                                    <th class="text-center">PF</th>
                                    <th class="text-center">PS</th>
                                    <th class="text-center">Saldo</th>
                                    <th class="text-center">Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $posicao = 1;
                                foreach ($classificacao as $class): 
                                ?>
                                    <tr>
                                        <td><strong><?php echo $posicao++; ?>º</strong></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 16px; height: 16px; background-color: <?php echo htmlspecialchars($class['time_cor']); ?>; border-radius: 3px;"></div>
                                                <strong><?php echo htmlspecialchars($class['time_nome']); ?></strong>
                                                <?php if (!empty($integrantes_classificacao[$class['time_id']])): ?>
                                                    <?php 
                                                    $primeiro_integ = $integrantes_classificacao[$class['time_id']];
                                                    if ($primeiro_integ && $primeiro_integ['usuario_id']):
                                                        $avatar = $primeiro_integ['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                        if (strpos($avatar, '../../assets/') === 0 || strpos($avatar, '../assets/') === 0 || strpos($avatar, 'assets/') === 0) {
                                                            if (strpos($avatar, '../../') !== 0) {
                                                                if (strpos($avatar, '../') === 0) {
                                                                    $avatar = '../' . ltrim($avatar, '/');
                                                                } else {
                                                                    $avatar = '../../' . ltrim($avatar, '/');
                                                                }
                                                            }
                                                        } elseif (strpos($avatar, 'http') !== 0 && strpos($avatar, '/') !== 0) {
                                                            $avatar = '../../assets/arquivos/' . $avatar;
                                                        }
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle" width="20" height="20" style="object-fit:cover;" alt="<?php echo htmlspecialchars($primeiro_integ['usuario_nome']); ?>" title="<?php echo htmlspecialchars($primeiro_integ['usuario_nome']); ?>">
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                                $total_jogos_jogados = (int)$class['vitorias'] + (int)$class['derrotas'] + (int)($class['empates'] ?? 0);
                                            ?>
                                            <span class="badge bg-info"><?php echo $total_jogos_jogados; ?></span>
                                        </td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $class['vitorias']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?php echo $class['derrotas']; ?></span></td>
                                            <td class="text-center"><strong><?php echo $class['pontos_total']; ?></strong></td>
                                        <td class="text-center"><?php echo $class['pontos_pro']; ?></td>
                                        <td class="text-center"><?php echo $class['pontos_contra']; ?></td>
                                        <td class="text-center <?php echo $class['saldo_pontos'] > 0 ? 'text-success' : ($class['saldo_pontos'] < 0 ? 'text-danger' : ''); ?>">
                                            <?php echo $class['saldo_pontos'] > 0 ? '+' : ''; ?><?php echo $class['saldo_pontos']; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($class['average'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Adicionar Participante -->
<div class="modal fade" id="modalAdicionarParticipante" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Adicionar Participante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAdicionarParticipante" action="javascript:void(0);" method="post">
                <input type="hidden" name="torneio_id" id="torneio_id_participante" value="<?php echo $torneio_id; ?>">
                <div class="modal-body">
                    <?php if ($tipoTorneio === 'grupo'): ?>
                        <?php
                        $maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
                        $totalAtual = count($participantes);
                        $vagasDisponiveis = $maxParticipantes > 0 ? ($maxParticipantes - $totalAtual) : 999;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="form-label mb-0 fw-bold">Selecione os membros do grupo:</div>
                                <?php if ($maxParticipantes > 0): ?>
                                    <small class="text-muted">Vagas disponíveis: <?php echo max(0, $vagasDisponiveis); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($membros_grupo)): ?>
                                    <p class="text-muted mb-0">Nenhum membro no grupo.</p>
                                <?php else: ?>
                                    <?php
                                    $membrosDisponiveis = [];
                                    foreach ($membros_grupo as $membro) {
                                        $jaParticipa = false;
                                        foreach ($participantes as $p) {
                                            if ($p['usuario_id'] == $membro['id']) {
                                                $jaParticipa = true;
                                                break;
                                            }
                                        }
                                        if (!$jaParticipa) {
                                            $membrosDisponiveis[] = $membro;
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($membrosDisponiveis)): ?>
                                        <div class="form-check mb-2 pb-2 border-bottom">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="marcarTodos" 
                                                   onchange="marcarTodosParticipantes(this)">
                                            <label class="form-check-label fw-bold" for="marcarTodos">
                                                Marcar Todos
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($membrosDisponiveis as $membro): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input participante-checkbox" type="checkbox" 
                                                   name="participantes[]" value="<?php echo (int)$membro['id']; ?>" 
                                                   id="membro_<?php echo (int)$membro['id']; ?>">
                                            <label class="form-check-label d-flex align-items-center gap-2" 
                                                   for="membro_<?php echo (int)$membro['id']; ?>">
                                                <?php 
                                                $avatar = $membro['foto_perfil'] ?: '../../assets/arquivos/logo.png';
                                                // Corrigir caminho da foto de perfil
                                                if (!empty($membro['foto_perfil'])) {
                                                    // Se já começa com http ou /, usar como está
                                                    if (strpos($membro['foto_perfil'], 'http') === 0 || strpos(trim($membro['foto_perfil']), '/') === 0) {
                                                        $avatar = $membro['foto_perfil'];
                                                    } elseif (strpos($membro['foto_perfil'], '../../assets/') === 0 || strpos($membro['foto_perfil'], '../assets/') === 0 || strpos($membro['foto_perfil'], 'assets/') === 0) {
                                                        // Se já tem assets/, garantir que comece com ../../
                                                        if (strpos($membro['foto_perfil'], '../../') !== 0) {
                                                            if (strpos($membro['foto_perfil'], '../') === 0) {
                                                                $avatar = '../' . ltrim($membro['foto_perfil'], '/');
                                                            } else {
                                                                $avatar = '../../' . ltrim($membro['foto_perfil'], '/');
                                                        }
                                                    } else {
                                                            $avatar = $membro['foto_perfil'];
                                                    }
                                                } else {
                                                        // Se não tem caminho, adicionar caminho completo
                                                        $avatar = '../../assets/arquivos/' . ltrim($membro['foto_perfil'], '/');
                                                    }
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                                     class="rounded-circle" width="24" height="24" 
                                                     style="object-fit:cover;" alt="Avatar">
                                                <span><?php echo htmlspecialchars($membro['nome']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($membrosDisponiveis)): ?>
                                        <p class="text-muted mb-0">Todos os membros do grupo já estão inscritos.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="nome_avulso" class="form-label">Nome do Participante *</label>
                            <input type="text" class="form-control" id="nome_avulso" name="nome_avulso" 
                                   placeholder="Ex: João ou João, Marcos, Josué" required>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Você pode adicionar múltiplos participantes de uma vez separando os nomes por vírgula. 
                                Exemplo: <strong>João, Marcos, Josué</strong>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnAdicionarParticipante" onclick="console.log('=== BOTÃO ADICIONAR PARTICIPANTE (MODAL) CLICADO ==='); if(typeof window.processarAdicionarParticipante === 'function') { console.log('Função encontrada, chamando...'); window.processarAdicionarParticipante(); } else if(typeof jQuery !== 'undefined') { const form = jQuery('#formAdicionarParticipante'); if(form.length > 0) { console.log('Formulário encontrado, disparando submit...'); console.log('Dados do formulário:', form.serialize()); form.trigger('submit'); } else { console.error('Formulário não encontrado!'); alert('Erro: Formulário não encontrado.'); } } else { console.error('jQuery não está disponível!'); alert('Erro: jQuery não está disponível.'); } return false;">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Torneio -->
<div class="modal fade" id="modalEditarTorneio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Editar Torneio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTorneio">
                <input type="hidden" name="torneio_id" value="<?php echo $torneio_id; ?>">
                <div class="modal-body">
                    <!-- Informações do Torneio (somente leitura) -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Data:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                                echo $dataTorneio ? date('d/m/Y', strtotime($dataTorneio)) : 'N/A';
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Tipo:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                if (isset($torneio['tipo'])) {
                                    echo $torneio['tipo'] === 'grupo' ? 'Torneio do Grupo' : 'Torneio Avulso';
                                } else {
                                    echo $torneio['grupo_id'] ? 'Torneio do Grupo' : 'Torneio Avulso';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Grupo:</strong></label>
                            <div class="form-control-plaintext">
                                <?php echo $torneio['grupo_nome'] ? htmlspecialchars($torneio['grupo_nome']) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Participantes:</strong></label>
                            <div class="form-control-plaintext">
                                <?php 
                                $maxParticipantes = $torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0;
                                echo count($participantes); ?> / <?php echo (int)$maxParticipantes; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <!-- Campos editáveis -->
                    <div class="mb-3">
                        <label for="edit_nome_torneio" class="form-label">Nome do Torneio *</label>
                        <input type="text" class="form-control" id="edit_nome_torneio" name="nome" 
                               value="<?php echo htmlspecialchars($torneio['nome']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_data_torneio" class="form-label">Data do Torneio *</label>
                        <input type="date" class="form-control" id="edit_data_torneio" name="data_torneio" 
                               value="<?php 
                               $dataTorneio = $torneio['data_torneio'] ?? $torneio['data_inicio'] ?? '';
                               echo $dataTorneio ? date('Y-m-d', strtotime($dataTorneio)) : '';
                               ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_descricao_torneio" class="form-label">Descrição</label>
                        <textarea class="form-control" id="edit_descricao_torneio" name="descricao" rows="3"><?php echo htmlspecialchars($torneio['descricao'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="excluirTorneio(<?php echo $torneio_id; ?>); bootstrap.Modal.getInstance(document.getElementById('modalEditarTorneio')).hide();">
                        <i class="fas fa-trash me-1"></i>Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Definir Grupos -->
<div class="modal fade" id="modalDefinirGrupos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-layer-group me-2"></i>Definir Grupos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><strong>Escolha como deseja distribuir os times:</strong></label>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-primary" onclick="sortearGruposAutomaticamente()">
                            <i class="fas fa-random me-1"></i>Sortear Automaticamente
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="limparDistribuicaoGrupos()">
                            <i class="fas fa-undo me-1"></i>Limpar Distribuição
                        </button>
                    </div>
                </div>
                <div id="containerGrupos" class="row">
                    <!-- Grupos serão carregados aqui via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarGrupos" onclick="salvarDistribuicaoGrupos()">
                    <i class="fas fa-save me-1"></i>Salvar Distribuição
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Perfil do Usuário -->
<div class="modal fade" id="modalVerPerfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Perfil do Participante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudoPerfil">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Integrante ao Time -->
<div class="modal fade" id="modalAdicionarIntegrante" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Integrante ao Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="listaParticipantesDisponiveis">
                    <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// SISTEMA DE DEBUG
// ============================================
(function() {
    'use strict';
    
    // Configuração do debug
    const DEBUG_ENABLED = true; // Mude para false para desabilitar
    const DEBUG_MAX_LOGS = 100; // Máximo de logs a manter
    
    // Container de logs
    let debugLogs = [];
    let debugWindow = null;
    let debugButton = null;
    
    // Criar botão flutuante de debug
    function createDebugButton() {
        if (!DEBUG_ENABLED) return;
        
        debugButton = document.createElement('button');
        debugButton.id = 'debug-toggle-btn';
        debugButton.innerHTML = '<i class="fas fa-bug"></i> Debug';
        debugButton.className = 'btn btn-warning btn-sm position-fixed';
        debugButton.style.cssText = 'bottom: 20px; right: 20px; z-index: 10000; border-radius: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
        debugButton.onclick = toggleDebugWindow;
        document.body.appendChild(debugButton);
    }
    
    // Criar janela de debug
    function createDebugWindow() {
        if (debugWindow) return;
        
        debugWindow = document.createElement('div');
        debugWindow.id = 'debug-window';
        debugWindow.className = 'position-fixed';
        debugWindow.style.cssText = 'bottom: 70px; right: 20px; width: 500px; max-width: 90vw; height: 400px; max-height: 70vh; z-index: 10001; background: white; border: 2px solid #ffc107; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.3); display: none; flex-direction: column;';
        
        debugWindow.innerHTML = `
            <div class="bg-warning text-dark p-2 d-flex justify-content-between align-items-center" style="border-radius: 6px 6px 0 0;">
                <strong><i class="fas fa-bug me-2"></i>Console de Debug</strong>
                <div>
                    <button class="btn btn-sm btn-outline-dark" onclick="window.clearDebugLogs()" title="Limpar logs">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-dark" onclick="window.toggleDebugWindow()" title="Fechar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="debug-content" style="flex: 1; overflow-y: auto; padding: 10px; font-family: monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4;">
                <div class="text-muted">Nenhum log ainda...</div>
            </div>
            <div class="bg-dark text-light p-2" style="border-top: 1px solid #444;">
                <small>Total de logs: <span id="debug-count">0</span></small>
            </div>
        `;
        
        document.body.appendChild(debugWindow);
    }
    
    // Adicionar log ao debug
    window.addDebugLog = function(message, type = 'info', data = null) {
        if (!DEBUG_ENABLED) return;
        
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = {
            timestamp: timestamp,
            message: message,
            type: type, // 'info', 'error', 'warning', 'success'
            data: data
        };
        
        debugLogs.push(logEntry);
        
        // Limitar quantidade de logs
        if (debugLogs.length > DEBUG_MAX_LOGS) {
            debugLogs.shift();
        }
        
        // Atualizar interface se a janela estiver visível
        if (debugWindow && debugWindow.style.display !== 'none') {
            updateDebugDisplay();
        }
        
        // Também logar no console do navegador
        const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
        console[consoleMethod](`[DEBUG ${timestamp}]`, message, data || '');
    };
    
    // Atualizar display do debug
    function updateDebugDisplay() {
        if (!debugWindow) return;
        
        const content = debugWindow.querySelector('#debug-content');
        const count = debugWindow.querySelector('#debug-count');
        
        if (!content || !count) return;
        
        count.textContent = debugLogs.length;
        
        if (debugLogs.length === 0) {
            content.innerHTML = '<div class="text-muted">Nenhum log ainda...</div>';
            return;
        }
        
        let html = '';
        debugLogs.forEach((log, index) => {
            const typeClass = {
                'error': 'text-danger',
                'warning': 'text-warning',
                'success': 'text-success',
                'info': 'text-info'
            }[log.type] || 'text-light';
            
            // Limitar tamanho dos dados para evitar erro "Invalid string length"
            let dataStr = '';
            if (log.data) {
                try {
                    const dataStringified = JSON.stringify(log.data, null, 2);
                    // Limitar a 10000 caracteres para evitar erro
                    if (dataStringified.length > 10000) {
                        dataStr = escapeHtml(dataStringified.substring(0, 10000) + '... (truncado)');
                    } else {
                        dataStr = escapeHtml(dataStringified);
                    }
                } catch (e) {
                    dataStr = escapeHtml(String(log.data).substring(0, 1000));
                }
            }
            
            html += `<div class="mb-2 p-2 border-bottom border-secondary" style="border-bottom-width: 1px !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <span class="text-muted" style="font-size: 10px;">[${log.timestamp}]</span>
                        <span class="badge bg-${log.type === 'error' ? 'danger' : log.type === 'warning' ? 'warning' : log.type === 'success' ? 'success' : 'info'} ms-2" style="font-size: 9px;">${log.type.toUpperCase()}</span>
                        <div class="${typeClass} mt-1">${escapeHtml(log.message)}</div>
                        ${dataStr ? `<pre class="text-muted mt-1 mb-0" style="font-size: 10px; max-height: 100px; overflow-y: auto;">${dataStr}</pre>` : ''}
                    </div>
                </div>
            </div>`;
        });
        
        content.innerHTML = html;
        content.scrollTop = content.scrollHeight;
    }
    
    // Escapar HTML para segurança
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Toggle da janela de debug
    window.toggleDebugWindow = function() {
        if (!debugWindow) createDebugWindow();
        
        if (debugWindow.style.display === 'none') {
            debugWindow.style.display = 'flex';
            updateDebugDisplay();
        } else {
            debugWindow.style.display = 'none';
        }
    };
    
    // Limpar logs
    window.clearDebugLogs = function() {
        debugLogs = [];
        updateDebugDisplay();
        window.addDebugLog('Logs limpos', 'info');
    };
    
    // Capturar erros JavaScript
    window.addEventListener('error', function(event) {
        window.addDebugLog(
            `Erro JavaScript: ${event.message}`,
            'error',
            {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error ? event.error.toString() : null,
                stack: event.error ? event.error.stack : null
            }
        );
    });
    
    // Capturar promessas rejeitadas
    window.addEventListener('unhandledrejection', function(event) {
        window.addDebugLog(
            `Promise rejeitada: ${event.reason}`,
            'error',
            {
                reason: event.reason,
                promise: event.promise
            }
        );
    });
    
    // Interceptar console.error
    const originalConsoleError = console.error;
    console.error = function(...args) {
        originalConsoleError.apply(console, args);
        try {
            const argsStr = args.map(a => {
                if (typeof a === 'object') {
                    try {
                        const str = JSON.stringify(a);
                        return str.length > 500 ? str.substring(0, 500) + '... (truncado)' : str;
                    } catch (e) {
                        return String(a).substring(0, 500);
                    }
                }
                const str = String(a);
                return str.length > 500 ? str.substring(0, 500) + '... (truncado)' : str;
            }).join(' ');
            window.addDebugLog('Console.error: ' + argsStr, 'error', args.length > 0 ? args[0] : null);
        } catch (e) {
            // Ignorar erro se não conseguir processar
        }
    };
    
    // Interceptar console.warn
    const originalConsoleWarn = console.warn;
    console.warn = function(...args) {
        originalConsoleWarn.apply(console, args);
        try {
            const argsStr = args.map(a => {
                if (typeof a === 'object') {
                    try {
                        const str = JSON.stringify(a);
                        return str.length > 500 ? str.substring(0, 500) + '... (truncado)' : str;
                    } catch (e) {
                        return String(a).substring(0, 500);
                    }
                }
                const str = String(a);
                return str.length > 500 ? str.substring(0, 500) + '... (truncado)' : str;
            }).join(' ');
            window.addDebugLog('Console.warn: ' + argsStr, 'warning', args.length > 0 ? args[0] : null);
        } catch (e) {
            // Ignorar erro se não conseguir processar
        }
    };
    
    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            createDebugButton();
            createDebugWindow();
            window.addDebugLog('Sistema de debug inicializado', 'success');
        });
    } else {
        createDebugButton();
        createDebugWindow();
        window.addDebugLog('Sistema de debug inicializado', 'success');
    }
})();

// Fallback para showAlert
if (typeof showAlert === 'undefined') {
    function showAlert(message, type) {
        type = type || 'info';
        var alertClass = 'alert-' + type;
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                       message +
                       '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                       '</div>';
        var alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alert-container';
            alertContainer.className = 'position-fixed top-0 end-0 p-3';
            alertContainer.style.zIndex = '9999';
            document.body.appendChild(alertContainer);
        }
        alertContainer.innerHTML = alertHtml;
        setTimeout(function() {
            var alert = alertContainer.querySelector('.alert');
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
        
        // Adicionar ao debug
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog(`Alert: ${message}`, type);
        }
    }
}

// ============================================
// FUNÇÕES DE EDIÇÃO - DEFINIDAS GLOBALMENTE
// ============================================
// IMPORTANTE: Estas funções devem estar disponíveis quando o HTML for renderizado

// Variável para armazenar valores originais - já declarada no topo (linha 191)
// Removida declaração duplicada para evitar erro de sintaxe

// Handler unificado para salvar as informações do torneio (funciona com ou sem jQuery)
window.submitEditarTorneio = function(event) {
    if (event && event.preventDefault) event.preventDefault();

    const form = document.getElementById('formEditarInformacoesTorneio');
    if (!form) {
        console.error('Formulário de edição não encontrado.');
        return false;
    }

    const btnSubmit = form.querySelector('button[type="submit"], button.btn-primary');
    const originalText = btnSubmit ? btnSubmit.innerHTML : '';

    // Coletar campos
    const torneioId = parseInt(
        (form.querySelector('input[name="torneio_id"]')?.value ||
         new URLSearchParams(window.location.search).get('id') || 0), 10
    ) || 0;
    const payloadObj = {
        torneio_id: torneioId,
        nome: form.querySelector('#edit_nome_torneio_inline')?.value || '',
        data_torneio: form.querySelector('#edit_data_torneio_inline')?.value || '',
        descricao: form.querySelector('#edit_descricao_torneio_inline')?.value || '',
        max_participantes: form.querySelector('#edit_max_participantes_inline')?.value || ''
    };

    // Debug: confirmar clique no botão Salvar (após payloadObj ser declarado)
    console.log('submitEditarTorneio chamada', payloadObj);

    if (!payloadObj.torneio_id) {
        showAlert('Torneio inválido (ID ausente).', 'danger');
        return false;
    }

    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';
    }

    // Debug
    if (typeof window.addDebugLog === 'function') {
        window.addDebugLog('Salvar torneio - payload', 'info', payloadObj);
    } else {
        console.log('Salvar torneio - payload', payloadObj);
    }

    // Função de sucesso comum
    const onSuccess = (response) => {
        if (response.success) {
            showAlert(response.message || 'Salvo com sucesso!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            let msg = response.message || 'Erro ao salvar informações do torneio.';
            if (response.debug) {
                msg += ' (Ver console para detalhes)';
                console.error('Debug salvar torneio:', response.debug);
            } else {
                console.error('Erro salvar torneio:', response);
            }
            showAlert(msg, 'danger');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText || '<i class="fas fa-save me-1"></i>Salvar';
            }
        }
    };

    const onError = (err) => {
        console.error('Erro AJAX salvar torneio', err);
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog('Erro AJAX salvar torneio', 'error', err);
        }
        showAlert('Erro ao salvar informações do torneio. Tente novamente.', 'danger');
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText || '<i class="fas fa-save me-1"></i>Salvar';
        }
    };

    // Se jQuery disponível, usar $.ajax
    if (window.jQuery) {
        $.ajax({
            url: '../ajax/editar_torneio.php',
            method: 'POST',
            data: payloadObj,
            dataType: 'json',
            success: onSuccess,
            error: function(xhr, status, error) {
                onError({ status, error, responseText: xhr.responseText });
            }
        });
    } else {
        // Fallback fetch
        fetch('../ajax/editar_torneio.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payloadObj).toString()
        })
        .then(r => r.json())
        .then(onSuccess)
        .catch(onError);
    }

    return false;
}
window.salvarInformacoesTorneio = submitEditarTorneio;

// Função para habilitar edição inline
window.editarInformacoesTorneio = function() {
    try {
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog('editarInformacoesTorneio chamada', 'info');
        }
        
        const display = document.getElementById('displayInformacoesTorneio');
        const form = document.getElementById('formEditarInformacoesTorneio');
        const btnEditar = document.getElementById('btnEditarInformacoes');

        if (display && form && btnEditar) {
            // Armazenar valores originais
            valoresOriginaisFormulario = {
                nome: form.querySelector('#edit_nome_torneio_inline').value,
                data_torneio: form.querySelector('#edit_data_torneio_inline').value,
                descricao: form.querySelector('#edit_descricao_torneio_inline').value,
                max_participantes: form.querySelector('#edit_max_participantes_inline').value
            };

            display.style.display = 'none';
            form.style.display = 'block';
            btnEditar.style.display = 'none';

            if (typeof window.addDebugLog === 'function') {
                window.addDebugLog('Edição ativada com sucesso', 'success', valoresOriginaisFormulario);
            }
        } else {
            const erroMsg = 'Elementos não encontrados: display=' + !!display + ', form=' + !!form + ', btnEditar=' + !!btnEditar;
            if (typeof window.addDebugLog === 'function') {
                window.addDebugLog(erroMsg, 'error', { display: display, form: form, btnEditar: btnEditar });
            }
            console.error(erroMsg);
        }
    } catch (error) {
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog('Erro ao ativar edição: ' + error.message, 'error', { error: error, stack: error.stack });
        }
        console.error('Erro ao ativar edição:', error);
    }
};

// Função para cancelar edição
window.cancelarEdicaoInformacoes = function() {
    try {
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog('cancelarEdicaoInformacoes chamada', 'info');
        }
        
        const display = document.getElementById('displayInformacoesTorneio');
        const form = document.getElementById('formEditarInformacoesTorneio');
        const btnEditar = document.getElementById('btnEditarInformacoes');

        if (display && form && btnEditar) {
            // Restaurar valores originais
            if (valoresOriginaisFormulario) {
                form.querySelector('#edit_nome_torneio_inline').value = valoresOriginaisFormulario.nome || '';
                form.querySelector('#edit_data_torneio_inline').value = valoresOriginaisFormulario.data_torneio || '';
                form.querySelector('#edit_descricao_torneio_inline').value = valoresOriginaisFormulario.descricao || '';
                form.querySelector('#edit_max_participantes_inline').value = valoresOriginaisFormulario.max_participantes || '';
            }

            // Limpar validações
            $(form).find('.is-invalid').removeClass('is-invalid');
            $(form).find('.invalid-feedback').remove();
            $(form).removeClass('was-validated');
            $(form).find('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Salvar');

            form.style.display = 'none';
            display.style.display = 'block';
            btnEditar.style.display = 'inline-block';

            if (typeof window.addDebugLog === 'function') {
                window.addDebugLog('Edição cancelada', 'success');
            }
        }
    } catch (error) {
        if (typeof window.addDebugLog === 'function') {
            window.addDebugLog('Erro ao cancelar edição: ' + error.message, 'error', { error: error });
        }
        console.error('Erro ao cancelar edição:', error);
    }
};

let timeAtualId = null;
let timeAtualNumero = null;
let maxParticipantesTorneio = <?php echo (int)($torneio['quantidade_participantes'] ?? $torneio['max_participantes'] ?? 0); ?>;
let totalParticipantesAtual = <?php echo count($participantes); ?>;
// listaParticipantesExpandida já está definida como window.listaParticipantesExpandida no topo (linha 370)
// Removida declaração local para evitar conflito
let secaoTimesExpandida = false; // Começar recolhida
// Variáveis globais para controle de seções
let secaoInformacoesExpandida = true; // Começar expandida
let secaoConfiguracoesExpandida = true; // Começar expandida
let secaoFormatoExpandida = false; // Começar recolhida

// Garantir que as funções toggle estejam disponíveis globalmente desde o início
// (serão definidas mais abaixo, mas isso garante que não haverá erro se chamadas antes)
if (typeof window.toggleSecaoConfiguracoes === 'undefined') {
    window.toggleSecaoConfiguracoes = function() {
        console.warn('toggleSecaoConfiguracoes chamada antes de ser definida');
    };
}
if (typeof window.toggleSecaoInformacoes === 'undefined') {
    window.toggleSecaoInformacoes = function() {
        console.warn('toggleSecaoInformacoes chamada antes de ser definida');
    };
}
if (typeof window.toggleSecaoTimes === 'undefined') {
    window.toggleSecaoTimes = function() {
        console.warn('toggleSecaoTimes chamada antes de ser definida');
    };
}
if (typeof window.toggleSecaoFormato === 'undefined') {
    window.toggleSecaoFormato = function() {
        console.warn('toggleSecaoFormato chamada antes de ser definida');
    };
}

// Estilos CSS para drag and drop
const style = document.createElement('style');
style.textContent = `
    .time-participantes.drag-over {
        background-color: #e3f2fd !important;
        border: 2px dashed #2196F3 !important;
        border-radius: 4px;
    }
    .participante-item {
        cursor: move;
        transition: opacity 0.2s;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
    .participante-item:hover {
        background-color: #f8f9fa;
    }
    .participante-item[draggable="true"]:active {
        cursor: grabbing;
    }
    .participante-item.selecionado {
        background-color: #cfe2ff !important;
        border: 2px solid #0d6efd !important;
        transform: scale(1.02);
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .participante-item:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
    }
    .time-participantes {
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }
`;
document.head.appendChild(style);

// Função já definida no topo como window.toggleListaParticipantes (linha 373)
// Esta é apenas uma referência para compatibilidade - NÃO redefinir para evitar loop infinito
// A função toggleListaParticipantes() já está disponível globalmente via window.toggleListaParticipantes

// Garantir que todas as funções toggle sejam globais
window.toggleSecaoTimes = function() {
    const corpo = document.getElementById('corpoSecaoTimes');
    const icone = document.getElementById('iconeSecaoTimes');
    
    if (corpo && icone) {
        if (secaoTimesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoTimesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoTimesExpandida = true;
        }
    }
};

// Alias para compatibilidade
function toggleSecaoTimes() {
    return window.toggleSecaoTimes();
}

// Garantir que todas as funções toggle sejam globais
window.toggleSecaoInformacoes = function() {
    const corpo = document.getElementById('corpoSecaoInformacoes');
    const icone = document.getElementById('iconeSecaoInformacoes');
    
    if (corpo && icone) {
        if (secaoInformacoesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoInformacoesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoInformacoesExpandida = true;
        }
    }
};

// Alias para compatibilidade
function toggleSecaoInformacoes() {
    return window.toggleSecaoInformacoes();
}

// Garantir que a função seja global
window.toggleSecaoConfiguracoes = function() {
    const corpo = document.getElementById('corpoSecaoConfiguracoes');
    const icone = document.getElementById('iconeSecaoConfiguracoes');
    
    if (corpo && icone) {
        if (secaoConfiguracoesExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoConfiguracoesExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoConfiguracoesExpandida = true;
        }
    }
};

// Alias para compatibilidade
function toggleSecaoConfiguracoes() {
    return window.toggleSecaoConfiguracoes();
}

// Garantir que todas as funções toggle sejam globais
window.toggleSecaoFormato = function() {
    const corpo = document.getElementById('corpoSecaoFormato');
    const icone = document.getElementById('iconeSecaoFormato');
    
    if (corpo && icone) {
        if (secaoFormatoExpandida) {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
            secaoFormatoExpandida = false;
        } else {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
            secaoFormatoExpandida = true;
        }
    }
};

// Alias para compatibilidade
function toggleSecaoFormato() {
    return window.toggleSecaoFormato();
}

function toggleJogosChave(grupoId) {
    const conteudo = document.getElementById('conteudo_chave_' + grupoId);
    const icone = document.getElementById('icon_jogos_chave_' + grupoId);
    
    if (conteudo && icone) {
        // Verificar se está visível - usar offsetHeight para detectar se está visível
        const isVisible = conteudo.offsetHeight > 0 && conteudo.style.display !== 'none';
        
        if (!isVisible || conteudo.style.display === 'none') {
            conteudo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
        } else {
            conteudo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
        }
    } else {
        console.error('Elementos não encontrados:', { conteudo, icone, grupoId });
    }
}

function toggleClassificacaoGeral1Fase() {
    const corpo = document.getElementById('classificacao_geral_1fase');
    const icone = document.getElementById('icon_classificacao_geral_1fase');
    
    if (corpo && icone) {
        // Verificar se está visível - usar offsetHeight para detectar se está visível
        const isVisible = corpo.offsetHeight > 0 && corpo.style.display !== 'none';
        
        if (!isVisible || corpo.style.display === 'none') {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
        } else {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
        }
    } else {
        console.error('Elementos não encontrados:', { corpo, icone });
    }
}

function toggleClassificacao2Fase(grupoId) {
    const corpo = document.getElementById('classificacao_2fase_' + grupoId);
    const icone = document.getElementById('icon_2fase_' + grupoId);
    
    if (corpo && icone) {
        // Verificar se está visível - usar offsetHeight para detectar se está visível
        const isVisible = corpo.offsetHeight > 0 && corpo.style.display !== 'none';
        
        if (!isVisible || corpo.style.display === 'none') {
            corpo.style.display = 'block';
            icone.classList.remove('fa-chevron-right');
            icone.classList.add('fa-chevron-down');
        } else {
            corpo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
        }
    } else {
        console.error('Elementos não encontrados:', { corpo, icone, grupoId });
    }
}

function recolherSecoesTorneio() {
    // Recolher Informações do Torneio
    if (secaoInformacoesExpandida) {
        toggleSecaoInformacoes();
    }
    
    // Recolher Configurações do Torneio
    if (secaoConfiguracoesExpandida) {
        toggleSecaoConfiguracoes();
    }
    
    // Recolher Formato de Campeonato
    if (secaoFormatoExpandida) {
        toggleSecaoFormato();
    }
}

// Inicializar estado: lista começa recolhida
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    const corpo = document.getElementById('corpoListaParticipantes');
    const icone = document.getElementById('iconeParticipantes');
    
    if (corpo && icone) {
        corpo.style.display = 'none';
        icone.classList.remove('fa-chevron-down');
        icone.classList.add('fa-chevron-right');
        listaParticipantesExpandida = false;
    }
    
    // Seção de times começa recolhida
    const corpoSecaoTimes = document.getElementById('corpoSecaoTimes');
    const iconeSecaoTimes = document.getElementById('iconeSecaoTimes');
    if (corpoSecaoTimes && iconeSecaoTimes) {
        corpoSecaoTimes.style.display = 'none';
        iconeSecaoTimes.classList.remove('fa-chevron-down');
        iconeSecaoTimes.classList.add('fa-chevron-right');
        secaoTimesExpandida = false;
    }
    
    // Seção de formato começa recolhida
    const corpoSecaoFormato = document.getElementById('corpoSecaoFormato');
    const iconeSecaoFormato = document.getElementById('iconeSecaoFormato');
    if (corpoSecaoFormato && iconeSecaoFormato) {
        corpoSecaoFormato.style.display = 'none';
        iconeSecaoFormato.classList.remove('fa-chevron-down');
        iconeSecaoFormato.classList.add('fa-chevron-right');
        secaoFormatoExpandida = false;
    }
    
    // Bind do formulário de edição de informações (seguro, sem depender de jQuery)
    const formEditar = document.getElementById('formEditarInformacoesTorneio');
    if (formEditar) {
        formEditar.addEventListener('submit', submitEditarTorneio);
    }
    if (window.jQuery) {
        $('#formEditarInformacoesTorneio').off('submit').on('submit', submitEditarTorneio);
    }
    
    // Classificação Geral - 1ª Fase começa recolhida
    const corpoClassificacaoGeral1Fase = document.getElementById('classificacao_geral_1fase');
    const iconeClassificacaoGeral1Fase = document.getElementById('icon_classificacao_geral_1fase');
    if (corpoClassificacaoGeral1Fase && iconeClassificacaoGeral1Fase) {
        corpoClassificacaoGeral1Fase.style.display = 'none';
        iconeClassificacaoGeral1Fase.classList.remove('fa-chevron-down');
        iconeClassificacaoGeral1Fase.classList.add('fa-chevron-right');
    }
    
    // Recolher todas as chaves por padrão
    const chaves = document.querySelectorAll('[id^="conteudo_chave_"]');
    chaves.forEach(function(conteudo) {
        const grupoId = conteudo.id.replace('conteudo_chave_', '');
        const icone = document.getElementById('icon_jogos_chave_' + grupoId);
        if (conteudo && icone) {
            conteudo.style.display = 'none';
            icone.classList.remove('fa-chevron-down');
            icone.classList.add('fa-chevron-right');
        }
    });
    
    // Configurar collapse para jogos de Prata e Bronze (recolhidos por padrão)
    const collapsesJogos = document.querySelectorAll('[id^="collapse_jogos_"]');
    collapsesJogos.forEach(function(collapse) {
        const collapseId = collapse.id;
        const icone = document.getElementById('icon_' + collapseId);
        
        if (icone) {
            // Iniciar com seta para baixo (recolhido) - já está no HTML, mas garantimos aqui
            icone.classList.remove('fa-chevron-up');
            icone.classList.add('fa-chevron-down');
        }
        
        // Adicionar event listeners para mudar ícone quando expandir/recolher
        collapse.addEventListener('show.bs.collapse', function() {
            const icon = document.getElementById('icon_' + collapseId);
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
        
        collapse.addEventListener('hide.bs.collapse', function() {
            const icon = document.getElementById('icon_' + collapseId);
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
        
        // Também adicionar listener no botão para garantir funcionamento
        const botao = collapse.previousElementSibling?.querySelector('[data-bs-toggle="collapse"]');
        if (botao) {
            botao.addEventListener('click', function() {
                // O Bootstrap já cuida do collapse, só precisamos atualizar o ícone
                setTimeout(function() {
                    const icon = document.getElementById('icon_' + collapseId);
                    if (icon && collapse.classList.contains('show')) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    } else if (icon) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    }
                }, 100);
            });
        }
    });
});

// Formulário editar informações do torneio (inline)
$(document).ready(function() {
    $('#formEditarInformacoesTorneio').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btnSubmit = form.find('button[type="submit"]');
        const originalText = btnSubmit.html();
        
        // Desabilitar botão durante o envio
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Salvando...');
        
    const torneioId = parseInt(form.find('input[name="torneio_id"]').val() || new URLSearchParams(window.location.search).get('id') || 0, 10) || 0;
    if (!torneioId || torneioId <= 0) {
        showAlert('Torneio inválido (ID ausente).', 'danger');
        btnSubmit.prop('disabled', false).html(originalText);
        return;
    }

    // Montar payload explicitamente para evitar falhas de serialize
    const payloadObj = {
        torneio_id: torneioId,
        nome: $('#edit_nome_torneio_inline').val() || '',
        data_torneio: $('#edit_data_torneio_inline').val() || '',
        descricao: $('#edit_descricao_torneio_inline').val() || '',
        max_participantes: $('#edit_max_participantes_inline').val() || ''
    };

    // Debug
    if (typeof window.addDebugLog === 'function') {
        window.addDebugLog('Salvar torneio - payload', 'info', payloadObj);
    } else {
        console.log('Salvar torneio - payload', payloadObj);
    }

    $.ajax({
        url: '../ajax/editar_torneio.php',
        method: 'POST',
        data: payloadObj,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Recarregar a página para mostrar os dados atualizados
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof window.addDebugLog === 'function') {
                        window.addDebugLog('Erro salvar torneio', 'error', response);
                    } else {
                        console.error('Erro salvar torneio', response);
                    }
                    let msg = response.message || 'Erro ao salvar informações do torneio.';
                    if (response.debug) {
                        msg += ' (Ver console para detalhes)';
                        console.error('Debug salvar torneio:', response.debug);
                    } else {
                        console.error('Resposta salvar torneio:', response);
                    }
                    showAlert(msg, 'danger');
                    btnSubmit.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao editar torneio:', error);
                if (typeof window.addDebugLog === 'function') {
                    window.addDebugLog('Erro AJAX salvar torneio', 'error', { status, error });
                } else {
                    console.error('Erro AJAX salvar torneio', { status, error, responseText: xhr.responseText });
                }
                showAlert('Erro ao salvar informações do torneio. Tente novamente.', 'danger');
                btnSubmit.prop('disabled', false).html(originalText);
            }
        });
    });
});

// Tornar função global
window.abrirModalAdicionarParticipante = function() {
    const modal = new bootstrap.Modal(document.getElementById('modalAdicionarParticipante'));
    modal.show();
    // Atualizar contador ao abrir modal
    if (typeof atualizarContadorVagas === 'function') {
        atualizarContadorVagas();
    }
};

// Alias para compatibilidade
function abrirModalAdicionarParticipante() {
    if (typeof window.abrirModalAdicionarParticipante === 'function') {
        return window.abrirModalAdicionarParticipante();
    }
}

function marcarTodosParticipantes(checkbox) {
    const checkboxes = Array.from(document.querySelectorAll('.participante-checkbox'));
    
    if (checkbox.checked) {
        // Marcar todos, respeitando o limite
        if (maxParticipantesTorneio > 0) {
            const vagasDisponiveis = maxParticipantesTorneio - totalParticipantesAtual;
            const quantidadeMarcar = Math.min(vagasDisponiveis, checkboxes.length);
            
            for (let i = 0; i < quantidadeMarcar; i++) {
                checkboxes[i].checked = true;
            }
            
            if (checkboxes.length > vagasDisponiveis) {
                showAlert('Apenas ' + quantidadeMarcar + ' participante(s) foram marcados devido ao limite de ' + maxParticipantesTorneio + ' participantes.', 'info');
            }
        } else {
            // Sem limite, marcar todos
            checkboxes.forEach(function(cb) {
                cb.checked = true;
            });
        }
    } else {
        // Desmarcar todos
        checkboxes.forEach(function(cb) {
            cb.checked = false;
        });
    }
    
    validarQuantidadeMaxima();
}

function validarQuantidadeMaxima() {
    if (maxParticipantesTorneio <= 0) {
        atualizarContadorVagas();
        return; // Sem limite
    }
    
    const selecionados = document.querySelectorAll('.participante-checkbox:checked').length;
    const total = totalParticipantesAtual + selecionados;
    
    if (total > maxParticipantesTorneio) {
        const excedente = total - maxParticipantesTorneio;
        showAlert('Você selecionou ' + excedente + ' participante(s) a mais do que o limite permitido (' + maxParticipantesTorneio + '). Alguns participantes foram desmarcados automaticamente.', 'warning');
        
        // Desmarcar os últimos checkboxes até respeitar o limite
        const checkboxes = Array.from(document.querySelectorAll('.participante-checkbox:checked'));
        const quantidadeManter = selecionados - excedente;
        for (let i = quantidadeManter; i < checkboxes.length; i++) {
            if (checkboxes[i]) {
                checkboxes[i].checked = false;
            }
        }
        
        // Atualizar checkbox "marcar todos" se necessário
        const checkboxMarcarTodos = document.getElementById('marcarTodos');
        if (checkboxMarcarTodos) {
            const todosMarcados = document.querySelectorAll('.participante-checkbox:checked').length === document.querySelectorAll('.participante-checkbox').length;
            checkboxMarcarTodos.checked = todosMarcados;
        }
    }
    
    atualizarContadorVagas();
}

function atualizarContadorVagas() {
    if (maxParticipantesTorneio > 0) {
        const selecionados = document.querySelectorAll('.participante-checkbox:checked').length;
        const vagasRestantes = maxParticipantesTorneio - totalParticipantesAtual - selecionados;
        const labelVagas = document.querySelector('.modal-body .text-muted');
        if (labelVagas) {
            labelVagas.textContent = 'Vagas disponíveis: ' + Math.max(0, vagasRestantes);
            if (vagasRestantes < 0) {
                labelVagas.classList.remove('text-muted');
                labelVagas.classList.add('text-danger');
            } else {
                labelVagas.classList.remove('text-danger');
                labelVagas.classList.add('text-muted');
            }
        }
    }
}

function adicionarIntegranteTime(timeId) {
    timeAtualId = timeId;
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível para este time.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarIntegranteAoTime(' + timeId + ', ' + p.id + '); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            // Corrigir caminho da foto
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    // Já tem assets/, garantir que comece com ../../
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    // Apenas nome do arquivo, adicionar caminho completo
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('modalAdicionarIntegrante'));
                modal.show();
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarParticipanteAoTime(timeId, timeNumero) {
    timeAtualId = timeId;
    timeAtualNumero = timeNumero;
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeId || 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarParticipanteAoTimeSelecionado(' + p.id + ', \'' + p.nome.replace(/'/g, "\\'") + '\', \'' + (p.foto_perfil || '') + '\'); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    // Já tem assets/, garantir que comece com ../../
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                    fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    // Apenas nome do arquivo, adicionar caminho completo
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('modalAdicionarIntegrante'));
                modal.show();
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao carregar participantes', 'danger');
        }
    });
}

function adicionarParticipanteAoTimeSelecionado(participanteId, nome, foto) {
    const timeContainerId = timeAtualId ? '#time-' + timeAtualId : '#time-novo_' + timeAtualNumero;
    const timeContainer = document.querySelector(timeContainerId);
    
    if (!timeContainer) {
        showAlert('Time não encontrado', 'danger');
        return;
    }
    
    // Verificar se já está no time
    const jaExiste = timeContainer.querySelector('[data-participante-id="' + participanteId + '"]');
    if (jaExiste) {
        showAlert('Este participante já está neste time.', 'warning');
        return;
    }
    
    // Verificar limite de integrantes por time
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    const totalIntegrantes = timeContainer.querySelectorAll('.participante-item').length;
    if (integrantesPorTime > 0 && totalIntegrantes >= integrantesPorTime) {
        showAlert('Limite de integrantes por time atingido (' + integrantesPorTime + ').', 'warning');
        return;
    }
    
    // Obter informações do time
    const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    const timeNumero = timeContainer.getAttribute('data-time-numero');
    
    // Se o time já existe no banco (tem timeId válido), salvar imediatamente
    if (timeId && !timeId.toString().startsWith('novo_')) {
        $.ajax({
            url: '../ajax/adicionar_integrante_time.php',
            method: 'POST',
            data: {
                time_id: parseInt(timeId),
                participante_id: parseInt(participanteId)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Adicionar participante ao DOM
                    const participanteHtml = criarHtmlParticipante(participanteId, nome, foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    
                    // Atualizar visibilidade do botão
                    if (timeNumero) {
                        atualizarVisibilidadeBotaoAdicionar(timeNumero);
                    }
                    
                    // Recarregar lista de participantes disponíveis no modal
                    recarregarListaParticipantesDisponiveis();
                    
                    showAlert('Participante adicionado ao time!', 'success');
                } else {
                    showAlert(response.message || 'Erro ao adicionar participante.', 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao adicionar participante ao time.', 'danger');
            }
        });
    } else {
        // Se o time ainda não existe, criar o time primeiro e depois adicionar o participante
        $.ajax({
            url: '../ajax/adicionar_integrante_time.php',
            method: 'POST',
            data: {
                time_id: 0,
                participante_id: parseInt(participanteId),
                torneio_id: <?php echo $torneio_id; ?>,
                time_numero: parseInt(timeNumero)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Atualizar o data-time-id do card com o ID retornado
                    if (response.time_id && timeCard) {
                        timeCard.setAttribute('data-time-id', response.time_id);
                        // Atualizar o ID do container também
                        timeContainer.id = 'time-' + response.time_id;
                    }
                    
                    // Adicionar participante ao DOM
                    const participanteHtml = criarHtmlParticipante(participanteId, nome, foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    
                    // Atualizar visibilidade do botão
                    if (timeNumero) {
                        atualizarVisibilidadeBotaoAdicionar(timeNumero);
                    }
                    
                    // Recarregar lista de participantes disponíveis no modal
                    recarregarListaParticipantesDisponiveis();
                    
                    showAlert('Participante adicionado ao time!', 'success');
                } else {
                    showAlert(response.message || 'Erro ao adicionar participante.', 'danger');
                }
            },
            error: function() {
                showAlert('Erro ao adicionar participante ao time.', 'danger');
            }
        });
    }
}

function recarregarListaParticipantesDisponiveis() {
    // Recarregar lista de participantes disponíveis no modal se estiver aberto
    const modalElement = document.getElementById('modalAdicionarIntegrante');
    if (!modalElement) return;
    
    // Verificar se o modal está visível
    const modalInstance = bootstrap.Modal.getInstance(modalElement);
    if (!modalInstance) return;
    
    // Verificar se o modal está aberto (Bootstrap 5)
    const isShown = modalElement.classList.contains('show') || modalElement.style.display === 'block';
    if (!isShown) return;
    
    // Recarregar a lista via AJAX
    $.ajax({
        url: '../ajax/listar_participantes_disponiveis_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            time_id: timeAtualId || 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.participantes.length === 0) {
                    html = '<p class="text-muted">Nenhum participante disponível.</p>';
                } else {
                    html = '<div class="list-group">';
                    response.participantes.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action" onclick="adicionarParticipanteAoTimeSelecionado(' + p.id + ', \'' + p.nome.replace(/'/g, "\\'") + '\', \'' + (p.foto_perfil || '') + '\'); return false;">';
                        if (p.usuario_id) {
                            html += '<div class="d-flex align-items-center gap-2">';
                            var fotoPerfil = p.foto_perfil || '../../assets/arquivos/logo.png';
                            if (fotoPerfil && fotoPerfil.indexOf('http') !== 0 && fotoPerfil.indexOf('/') !== 0) {
                                if (fotoPerfil.indexOf('../../assets/') === 0 || fotoPerfil.indexOf('../assets/') === 0 || fotoPerfil.indexOf('assets/') === 0) {
                                    if (fotoPerfil.indexOf('../../') !== 0) {
                                        if (fotoPerfil.indexOf('../') === 0) {
                                            fotoPerfil = '../' + fotoPerfil;
                                        } else {
                                            fotoPerfil = '../../' + fotoPerfil;
                                        }
                                    }
                                } else {
                                    fotoPerfil = '../../assets/arquivos/' + fotoPerfil;
                                }
                            }
                            html += '<img src="' + fotoPerfil + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">';
                            html += '<span>' + p.nome + '</span>';
                            html += '</div>';
                        } else {
                            html += '<i class="fas fa-user me-2"></i>' + p.nome_avulso;
                        }
                        html += '</a>';
                    });
                    html += '</div>';
                }
                document.getElementById('listaParticipantesDisponiveis').innerHTML = html;
            }
        },
        error: function() {
            // Silenciar erro, não mostrar alerta
        }
    });
}

function adicionarIntegranteAoTime(timeId, participanteId) {
    $.ajax({
        url: '../ajax/adicionar_integrante_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            participante_id: participanteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                var modalElement = document.getElementById('modalAdicionarIntegrante');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) modalInstance.hide();
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao adicionar integrante', 'danger');
        }
    });
}

function removerIntegrante(timeId, participanteId) {
    if (!confirm('Remover este integrante do time?')) return;
    
    $.ajax({
        url: '../ajax/remover_integrante_time.php',
        method: 'POST',
        data: {
            time_id: timeId,
            participante_id: participanteId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao remover integrante', 'danger');
        }
    });
}

// Função removerParticipante movida para o script inline no topo (linha ~1050)
// Esta função foi movida para garantir disponibilidade imediata quando o botão é clicado

// Função para alternar inscrições
function toggleInscricoes() {
    var btn = document.getElementById('btnToggleInscricoes');
    var inscricoesAtuais = btn.classList.contains('btn-success');
    var novaSituacao = inscricoesAtuais ? 0 : 1;
    
    $.ajax({
        url: '../ajax/toggle_inscricoes_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            inscricoes_abertas: novaSituacao
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Atualizar botão
                if (response.inscricoes_abertas == 1) {
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-success');
                    btn.querySelector('i').classList.remove('fa-lock');
                    btn.querySelector('i').classList.add('fa-unlock');
                    btn.title = 'Inscrições Abertas - Clique para fechar';
                } else {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-success');
                    btn.querySelector('i').classList.remove('fa-unlock');
                    btn.querySelector('i').classList.add('fa-lock');
                    btn.title = 'Inscrições Fechadas - Clique para abrir';
                }
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao atualizar inscrições', 'danger');
        }
    });
}

// Função para carregar solicitações pendentes
function carregarSolicitacoes() {
    var container = document.getElementById('listaSolicitacoes');
    if (!container) {
        console.error('Container listaSolicitacoes não encontrado');
        return;
    }
    
    $.ajax({
        url: '../ajax/listar_solicitacoes_torneio.php',
        method: 'GET',
        data: {
            torneio_id: <?php echo $torneio_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            console.log('Resposta do servidor:', response);
            if (response.success) {
                var solicitacoes = response.solicitacoes || [];
                var badge = document.getElementById('badgeSolicitacoes');
                
                if (badge) {
                    badge.textContent = solicitacoes.length;
                }
                
                if (solicitacoes.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center mb-0">Nenhuma solicitação pendente.</p>';
                } else {
                    var html = '<div class="list-group">';
                    solicitacoes.forEach(function(sol) {
                        var avatar = sol.foto_perfil || '';
                        // Ajustar caminho da imagem
                        if (avatar && avatar.trim() !== '') {
                            if (avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
                                if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
                                    // Já tem caminho relativo, garantir que seja ../../assets/
                                    if (avatar.indexOf('../../assets/') !== 0) {
                                        if (avatar.indexOf('../assets/') === 0) {
                                            avatar = '../' + avatar;
                                        } else if (avatar.indexOf('assets/') === 0) {
                                            avatar = '../../' + avatar;
                                        }
                                    }
                                } else {
                                    avatar = '../../assets/arquivos/' + avatar;
                                }
                            }
                        } else {
                            avatar = '../../assets/arquivos/logo.png';
                        }
                        
                        var inicialNome = sol.usuario_nome ? sol.usuario_nome.charAt(0).toUpperCase() : '?';
                        
                        html += '<div class="list-group-item">';
                        html += '<div class="d-flex justify-content-between align-items-center">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<div style="position:relative;width:40px;height:40px;">';
                        html += '<img src="' + avatar + '" class="rounded-circle" width="40" height="40" style="object-fit:cover;position:absolute;top:0;left:0;" alt="' + (sol.usuario_nome || '') + '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                        html += '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:40px;height:40px;font-weight:bold;position:absolute;top:0;left:0;display:none;" title="' + (sol.usuario_nome || '') + '">' + inicialNome + '</div>';
                        html += '</div>';
                        html += '<div>';
                        html += '<strong>' + (sol.usuario_nome || 'Usuário') + '</strong><br>';
                        html += '<small class="text-muted">' + (sol.email || '') + '</small><br>';
                        html += '<small class="text-muted"><i class="fas fa-clock me-1"></i>' + new Date(sol.data_solicitacao).toLocaleString('pt-BR') + '</small>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="d-flex gap-2">';
                        html += '<button class="btn btn-sm btn-info" onclick="verPerfilUsuario(' + sol.usuario_id + ')" title="Ver Perfil">';
                        html += '<i class="fas fa-user"></i>';
                        html += '</button>';
                        html += '<button class="btn btn-sm btn-success" onclick="responderSolicitacao(' + sol.id + ', \'aprovar\')" title="Aprovar">';
                        html += '<i class="fas fa-check"></i>';
                        html += '</button>';
                        html += '<button class="btn btn-sm btn-danger" onclick="responderSolicitacao(' + sol.id + ', \'rejeitar\')" title="Rejeitar">';
                        html += '<i class="fas fa-times"></i>';
                        html += '</button>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                }
            } else {
                console.error('Erro na resposta:', response.message || 'Erro desconhecido');
                container.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar solicitações: ' + (response.message || 'Erro desconhecido') + '</p>';
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX:', status, error, xhr.responseText);
            container.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar solicitações. Verifique o console para mais detalhes.</p>';
        }
    });
}

// Função para responder solicitação
function responderSolicitacao(solicitacaoId, acao) {
    var acaoTexto = acao === 'aprovar' ? 'aprovar' : 'rejeitar';
    if (!confirm('Tem certeza que deseja ' + acaoTexto + ' esta solicitação?')) return;
    
    $.ajax({
        url: '../ajax/responder_solicitacao_torneio.php',
        method: 'POST',
        data: {
            solicitacao_id: solicitacaoId,
            acao: acao
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                carregarSolicitacoes();
                // Recarregar página após 1 segundo para atualizar lista de participantes
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao responder solicitação', 'danger');
        }
    });
}

// Função limparTodosParticipantes movida para o script inline no topo (linha ~1000)
// Esta função foi movida para garantir disponibilidade imediata quando o botão é clicado

function criarTimes(btnElement) {
    if (!confirm('Isso criará os times baseado na configuração. Times existentes serão removidos. Deseja continuar?')) return;
    
    // Desabilitar botão para evitar duplo clique
    let btn = btnElement;
    let originalText = '';
    if (!btn && event && event.target) {
        btn = event.target;
    }
    if (btn) {
        originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';
    }
    
    $.ajax({
        url: '../ajax/criar_times_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message, 'danger');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }
        },
        error: function() {
            showAlert('Erro ao criar times', 'danger');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    });
}

function sortearTimes() {
    if (!confirm('Isso irá sortear todos os participantes nos times. Deseja continuar?')) return;
    
    // Obter todos os participantes
    const participantes = [];
    <?php foreach ($participantes as $p): ?>
    <?php
    // Preparar foto do participante
    $fotoParticipante = '';
    if (!empty($p['usuario_foto'])) {
        $fotoParticipante = $p['usuario_foto'];
        // Corrigir caminho se necessário
        if (strpos($fotoParticipante, '../assets/') !== 0 && strpos($fotoParticipante, 'assets/') === 0) {
            $fotoParticipante = '../' . $fotoParticipante;
        } elseif (strpos($fotoParticipante, 'http') !== 0 && strpos($fotoParticipante, '/') !== 0 && strpos($fotoParticipante, '../assets/') !== 0) {
            $fotoParticipante = '../assets/arquivos/' . $fotoParticipante;
        }
    }
    $nomeParticipante = $p['usuario_nome'] ?? $p['nome_avulso'] ?? 'Participante #' . $p['id'];
    ?>
    participantes.push({
        id: <?php echo $p['id']; ?>,
        nome: '<?php echo addslashes($nomeParticipante); ?>',
        foto: '<?php echo addslashes($fotoParticipante); ?>'
    });
    <?php endforeach; ?>
    
    if (participantes.length === 0) {
        showAlert('Não há participantes para sortear.', 'warning');
        return;
    }
    
    // Embaralhar participantes
    for (let i = participantes.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [participantes[i], participantes[j]] = [participantes[j], participantes[i]];
    }
    
    // Distribuir nos times
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    if (integrantesPorTime === 0) {
        showAlert('Configure a quantidade de integrantes por time primeiro.', 'warning');
        return;
    }
    
    // Verificar se há times suficientes
    const totalVagas = quantidadeTimes * integrantesPorTime;
    if (participantes.length > totalVagas) {
        showAlert('Há mais participantes (' + participantes.length + ') do que vagas disponíveis (' + totalVagas + ').', 'warning');
        return;
    }
    
    // Limpar todos os times
    document.querySelectorAll('.time-participantes').forEach(function(container) {
        container.innerHTML = '';
    });
    
    // Distribuir participantes de forma equilibrada
    let participanteIndex = 0;
    
    // Função auxiliar para encontrar o container do time
    function encontrarTimeContainer(timeNum) {
        // Tentar pelo data-time-numero primeiro (mais confiável)
        let container = document.querySelector('[data-time-numero="' + timeNum + '"]');
        if (container) return container;
        
        // Tentar pelos IDs
        container = document.querySelector('#time-novo_' + timeNum);
        if (container) return container;
        
        container = document.querySelector('#time-' + timeNum);
        if (container) return container;
        
        // Tentar encontrar qualquer time com o número
        const allContainers = document.querySelectorAll('.time-participantes');
        for (let i = 0; i < allContainers.length; i++) {
            const num = parseInt(allContainers[i].getAttribute('data-time-numero'));
            if (num === timeNum) {
                return allContainers[i];
            }
        }
        
        return null;
    }
    
    // Primeiro, distribuir até preencher todos os times com a quantidade mínima
    for (let timeNum = 1; timeNum <= quantidadeTimes && participanteIndex < participantes.length; timeNum++) {
        const timeContainer = encontrarTimeContainer(timeNum);
        if (!timeContainer) continue;
        
        // Distribuir integrantesPorTime participantes neste time
        for (let i = 0; i < integrantesPorTime && participanteIndex < participantes.length; i++) {
            const p = participantes[participanteIndex];
            const participanteHtml = criarHtmlParticipante(p.id, p.nome, p.foto);
            timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
            participanteIndex++;
        }
    }
    
    // Se ainda sobraram participantes, distribuir de forma rotativa
    if (participanteIndex < participantes.length) {
        let timeNum = 1;
        let tentativas = 0;
        const maxTentativas = quantidadeTimes * integrantesPorTime * 2; // Evitar loop infinito
        
        while (participanteIndex < participantes.length && tentativas < maxTentativas) {
            const timeContainer = encontrarTimeContainer(timeNum);
            
            if (timeContainer) {
                // Verificar quantos participantes já tem neste time
                const participantesNoTime = timeContainer.querySelectorAll('.participante-item').length;
                
                // Se ainda pode adicionar mais um
                if (participantesNoTime < integrantesPorTime) {
                    const p = participantes[participanteIndex];
                    const participanteHtml = criarHtmlParticipante(p.id, p.nome, p.foto);
                    timeContainer.insertAdjacentHTML('beforeend', participanteHtml);
                    participanteIndex++;
                }
            }
            
            timeNum++;
            if (timeNum > quantidadeTimes) timeNum = 1;
            tentativas++;
        }
    }
    
    // Salvar automaticamente no banco de dados
    const torneioId = <?php echo $torneio_id; ?>;
    
    $.ajax({
        url: '../ajax/sortear_times_torneio.php',
        method: 'POST',
        data: {
            torneio_id: torneioId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message || 'Participantes sorteados e salvos com sucesso!', 'success');
                // Recarregar página após 1 segundo para atualizar a interface
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert(response.message || 'Erro ao salvar sorteio', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar sorteio no banco de dados', 'danger');
        }
    });
}

function criarHtmlParticipante(participanteId, nome, foto) {
    // Corrigir caminho da foto
    let avatar = foto && foto !== '' ? foto : '../../assets/arquivos/logo.png';
    
    // Se não começa com http ou /, ajustar caminho
    if (avatar && avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
        if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
            // Já tem assets/, garantir que comece com ../../
            if (avatar.indexOf('../../') !== 0) {
                if (avatar.indexOf('../') === 0) {
            avatar = '../' + avatar;
                } else {
                    avatar = '../../' + avatar;
                }
            }
        } else {
            // Apenas nome do arquivo, adicionar caminho completo
            avatar = '../../assets/arquivos/' + avatar;
        }
    }
    
    return '<div class="participante-item mb-2 p-2 border rounded d-flex justify-content-between align-items-center" ' +
           'data-participante-id="' + participanteId + '" onclick="event.stopPropagation(); selecionarParticipante(this, event)" ' +
           'style="cursor: pointer; user-select: none; -webkit-user-select: none;" ' +
           'oncontextmenu="return false;">' +
           '<div class="d-flex align-items-center gap-2">' +
           '<img src="' + avatar + '" class="rounded-circle" width="24" height="24" style="object-fit:cover;">' +
           '<small>' + nome + '</small>' +
           '</div>' +
           '<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removerParticipanteDoTime(this)">' +
           '<i class="fas fa-times"></i>' +
           '</button>' +
           '</div>';
}

function limparTimes() {
    if (!confirm('Tem certeza que deseja excluir TODOS os times e seus integrantes? Esta ação não pode ser desfeita!')) {
        return;
    }
    
    $.ajax({
        url: '../ajax/limpar_times_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao limpar times', 'danger');
        }
    });
}

function salvarTimes() {
    const quantidadeTimes = <?php echo (int)($torneio['quantidade_times'] ?? 0); ?>;
    if (quantidadeTimes === 0) {
        showAlert('Configure a quantidade de times primeiro.', 'warning');
        return;
    }
    
    // Coletar dados de todos os times
    const timesData = [];
    
    for (let timeNum = 1; timeNum <= quantidadeTimes; timeNum++) {
        let timeContainer = document.querySelector('#time-novo_' + timeNum + ', #time-' + timeNum);
        if (!timeContainer) {
            // Tentar encontrar pelo data-time-numero
            timeContainer = document.querySelector('[data-time-numero="' + timeNum + '"]');
        }
        
        if (!timeContainer) continue;
        
        const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
        const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
        
        const participantes = [];
        timeContainer.querySelectorAll('.participante-item').forEach(function(item) {
            const participanteId = item.getAttribute('data-participante-id');
            if (participanteId) {
                participantes.push(parseInt(participanteId));
            }
        });
        
        timesData.push({
            time_id: timeId && timeId !== 'novo_' + timeNum ? parseInt(timeId) : null,
            numero: timeNum,
            participantes: participantes
        });
    }
    
    $.ajax({
        url: '../ajax/salvar_times_torneio.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            times_data: JSON.stringify(timesData)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Bloquear botões após salvar
                $('.times-action-btn').prop('disabled', true);
                $('#btnEditarTimes').show();
                // Scroll para o topo e recarregar após salvar
                setTimeout(function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar times', 'danger');
        }
    });
}

function atualizarVisibilidadeBotaoAdicionar(timeNumero) {
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    const timeContainer = document.querySelector('[data-time-numero="' + timeNumero + '"]');
    
    if (!timeContainer) return;
    
    const totalIntegrantes = timeContainer.querySelectorAll('.participante-item').length;
    const timeCard = timeContainer.closest('.col-md-6, .col-lg-4');
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    const btnId = timeId ? 'btn-adicionar-' + timeId : 'btn-adicionar-novo_' + timeNumero;
    const btn = document.getElementById(btnId);
    
    if (!btn) return;
    
    // Se não há limite configurado (0), sempre mostrar o botão
    // Se há limite, mostrar apenas se não atingiu o limite
    if (integrantesPorTime === 0) {
        btn.style.display = 'block';
    } else {
        if (totalIntegrantes >= integrantesPorTime) {
            btn.style.display = 'none';
        } else {
            btn.style.display = 'block';
        }
    }
}

function removerParticipanteDoTime(button) {
    if (!confirm('Remover este participante do time?')) return;
    
    const item = button.closest('.participante-item');
    if (!item) return;
    
    const participanteId = item.getAttribute('data-participante-id');
    const timeContainer = item.closest('.time-participantes');
    const timeNumero = timeContainer ? timeContainer.getAttribute('data-time-numero') : null;
    const timeCard = timeContainer ? timeContainer.closest('.col-md-6, .col-lg-4') : null;
    const timeId = timeCard ? (timeCard.getAttribute('data-time-id') || null) : null;
    
    // Se não tem timeId válido, apenas remover do DOM (será salvo quando clicar em Salvar Times)
    if (!timeId || timeId.toString().startsWith('novo_')) {
        item.remove();
        if (timeNumero) {
            atualizarVisibilidadeBotaoAdicionar(timeNumero);
        }
        return;
    }
    
    // Se tem timeId válido, remover do banco imediatamente
    $.ajax({
        url: '../ajax/remover_integrante_time.php',
        method: 'POST',
        data: {
            time_id: parseInt(timeId),
            participante_id: parseInt(participanteId)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                item.remove();
                if (timeNumero) {
                    atualizarVisibilidadeBotaoAdicionar(timeNumero);
                }
                showAlert('Participante removido do time.', 'success');
            } else {
                showAlert(response.message || 'Erro ao remover participante.', 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao remover participante do time.', 'danger');
        }
    });
}

// Sistema de seleção e troca por clique
let participanteSelecionado = null;
let timeOrigemSelecionado = null;

function selecionarParticipante(elemento, evt) {
    // Prevenir propagação do evento
    if (evt) {
        evt.stopPropagation();
        // Se clicou no botão de remover, não fazer nada
        if (evt.target.closest('button')) {
            return;
        }
    }
    
    const participante = elemento.closest('.participante-item');
    if (!participante) return;
    
    const timeAtual = participante.closest('.time-participantes');
    
    // Se já tem um participante selecionado
    if (participanteSelecionado) {
        // Se clicou no mesmo participante, deselecionar
        if (participanteSelecionado === participante) {
            participanteSelecionado.classList.remove('selecionado');
            participanteSelecionado = null;
            timeOrigemSelecionado = null;
            return;
        }
        
        // Se clicou em outro participante, fazer troca
        const timeDestino = participante.closest('.time-participantes');
        const timeOrigem = participanteSelecionado.closest('.time-participantes');
        
        // Se está no mesmo time, apenas trocar a ordem
        if (timeOrigem === timeDestino) {
            // Trocar posição exata no mesmo time
            const participante1 = participanteSelecionado;
            const participante2 = participante;
            const parent = participante1.parentNode;
            
            // Criar marcadores invisíveis para preservar posições exatas
            const marcador1 = document.createElement('span');
            marcador1.style.display = 'none';
            marcador1.style.visibility = 'hidden';
            marcador1.style.position = 'absolute';
            
            const marcador2 = document.createElement('span');
            marcador2.style.display = 'none';
            marcador2.style.visibility = 'hidden';
            marcador2.style.position = 'absolute';
            
            // Inserir marcadores ANTES de remover os participantes
            const proximo1 = participante1.nextSibling;
            const proximo2 = participante2.nextSibling;
            
            parent.insertBefore(marcador1, proximo1);
            parent.insertBefore(marcador2, proximo2);
            
            // Remover participantes
            participante1.remove();
            participante2.remove();
            
            // Inserir na posição trocada usando os marcadores
            parent.insertBefore(participante2, marcador1);
            parent.insertBefore(participante1, marcador2);
            
            // Remover marcadores
            marcador1.remove();
            marcador2.remove();
        } else {
            // Trocar entre times diferentes
            trocarParticipantesEntreTimes(participanteSelecionado, participante, timeOrigem, timeDestino);
        }
        
        // Deselecionar
        participanteSelecionado.classList.remove('selecionado');
        participanteSelecionado = null;
        timeOrigemSelecionado = null;
    } else {
        // Selecionar o participante
        participanteSelecionado = participante;
        timeOrigemSelecionado = timeAtual;
        participante.classList.add('selecionado');
    }
}

function trocarParticipantesEntreTimes(participante1, participante2, timeOrigem, timeDestino) {
    const integrantesPorTime = <?php echo (int)($torneio['integrantes_por_time'] ?? 0); ?>;
    
    // Verificar se o time de destino está cheio
    const totalIntegrantesDestino = timeDestino.querySelectorAll('.participante-item').length;
    const totalIntegrantesOrigem = timeOrigem.querySelectorAll('.participante-item').length;
    
    // Obter a posição exata do participante2 no time de destino
    const proximoParticipante2 = participante2.nextSibling;
    
    // Se o time de destino está cheio e não é o mesmo time, fazer troca
    if (integrantesPorTime > 0 && totalIntegrantesDestino >= integrantesPorTime && timeOrigem !== timeDestino) {
        // Obter a posição exata do participante1 no time de origem
        const proximoParticipante1 = participante1.nextSibling;
        
        // Remover ambos
        participante1.remove();
        participante2.remove();
        
        // Inserir na posição exata do outro
        if (proximoParticipante2) {
            timeDestino.insertBefore(participante1, proximoParticipante2);
        } else {
            timeDestino.appendChild(participante1);
        }
        
        if (proximoParticipante1) {
            timeOrigem.insertBefore(participante2, proximoParticipante1);
        } else {
            timeOrigem.appendChild(participante2);
        }
    } else if (timeOrigem !== timeDestino) {
        // Se não está cheio, apenas mover para a posição exata
        participante1.remove();
        
        if (proximoParticipante2) {
            timeDestino.insertBefore(participante1, proximoParticipante2);
        } else {
            timeDestino.appendChild(participante1);
        }
    }
}

// Deselecionar ao clicar fora
document.addEventListener('click', function(event) {
    if (participanteSelecionado && !event.target.closest('.participante-item')) {
        participanteSelecionado.classList.remove('selecionado');
        participanteSelecionado = null;
        timeOrigemSelecionado = null;
    }
});
// Funções editarNomeTime e salvarNomeTime movidas para o script inline no topo (linha ~1150)
// Estas funções foram movidas para garantir disponibilidade imediata quando o HTML é renderizado

function excluirTime(timeId) {
    if (!confirm('Tem certeza que deseja excluir este time?')) return;
    
    $.ajax({
        url: '../ajax/excluir_time_torneio.php',
        method: 'POST',
        data: { time_id: timeId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao excluir time', 'danger');
        }
    });
}

function excluirTorneio(torneioId) {
    if (!confirm('Tem certeza que deseja excluir este torneio?\n\nEsta ação não pode ser desfeita e excluirá todos os participantes e times associados.')) return;
    
    const idForcado = torneioId || new URLSearchParams(window.location.search).get('id') || 0;
    
    $.ajax({
        url: '../ajax/excluir_torneio.php',
        method: 'POST',
        data: { torneio_id: idForcado },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    window.location.href = '../torneios.php';
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao excluir torneio', 'danger');
        }
    });
}

// Garantir que a função esteja no escopo global (caso algum bundler/minificação remova)
window.excluirTorneio = excluirTorneio;

// Formulário adicionar participante - garantir que o handler seja anexado
$(document).ready(function() {
    // Remover handlers anteriores
    $('#formAdicionarParticipante').off('submit');
    
    // Adicionar handler
    $('#formAdicionarParticipante').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('=== FORMULÁRIO ADICIONAR PARTICIPANTE SUBMETIDO ===');
    
    const form = $(this);
    
    // Verificar torneio_id
    let torneioId = form.find('input[name="torneio_id"]').val();
    if (!torneioId || torneioId === '') {
        torneioId = $('#torneio_id_participante').val();
    }
    if (!torneioId || torneioId === '') {
        torneioId = new URLSearchParams(window.location.search).get('id');
    }
    if (!torneioId || torneioId === '') {
        torneioId = <?php echo $torneio_id; ?>;
    }
    
    console.log('torneio_id capturado:', torneioId);
    
    if (!torneioId || torneioId <= 0) {
        console.error('ERRO: torneio_id inválido ou ausente');
        showAlert('Erro: ID do torneio não encontrado. Recarregue a página.', 'danger');
        return false;
    }
    
    <?php if ($tipoTorneio === 'grupo'): ?>
    var participantes = $('input[name="participantes[]"]:checked');
    if (participantes.length === 0) {
        showAlert('Selecione pelo menos um participante.', 'warning');
        return false;
    }
    
    // Validar quantidade máxima
    if (maxParticipantesTorneio > 0) {
        var totalAposAdicao = totalParticipantesAtual + participantes.length;
        if (totalAposAdicao > maxParticipantesTorneio) {
            showAlert('O limite máximo de participantes (' + maxParticipantesTorneio + ') será excedido. Selecione menos participantes.', 'danger');
            return false;
        }
    }
    <?php endif; ?>
    
    // Montar payload explicitamente
    const formData = form.serialize();
    console.log('Dados sendo enviados:', formData);
    
    // Desabilitar botão de submit durante o envio
    const btnSubmit = $('#btnAdicionarParticipante');
    const originalText = btnSubmit.html();
    if (btnSubmit.length > 0) {
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Adicionando...');
    }
    
    $.ajax({
        url: '../ajax/adicionar_participante_torneio.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            // Reabilitar botão
            if (btnSubmit.length > 0) {
                btnSubmit.prop('disabled', false).html(originalText);
            }
            
            console.log('Resposta recebida:', response);
            
            if (response.success) {
                showAlert(response.message, 'success');
                var modalElement = document.getElementById('modalAdicionarParticipante');
                if (modalElement) {
                    var modalInstance = bootstrap.Modal.getInstance(modalElement);
                    if (modalInstance) modalInstance.hide();
                }
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro ao adicionar participante';
                console.error('Erro ao adicionar participante:', response);
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            // Reabilitar botão
            if (btnSubmit.length > 0) {
                btnSubmit.prop('disabled', false).html(originalText);
            }
            
            console.error('Erro AJAX ao adicionar participante:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText
            });
            
            let errorMsg = 'Erro ao adicionar participante. Tente novamente.';
            try {
                const jsonResponse = JSON.parse(xhr.responseText);
                if (jsonResponse.message) {
                    errorMsg = jsonResponse.message;
                }
            } catch (e) {
                if (xhr.status === 404) {
                    errorMsg = 'Arquivo não encontrado. Verifique o caminho do script.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Erro interno do servidor. Verifique os logs.';
                }
            }
            
            showAlert(errorMsg, 'danger');
        }
    });
    });
});

// Atualizar checkbox "marcar todos" quando checkboxes individuais mudarem
$(document).on('change', '.participante-checkbox', function() {
    validarQuantidadeMaxima();
    
    // Atualizar estado do checkbox "marcar todos"
    const checkboxMarcarTodos = document.getElementById('marcarTodos');
    if (checkboxMarcarTodos) {
        const totalCheckboxes = document.querySelectorAll('.participante-checkbox').length;
        const totalMarcados = document.querySelectorAll('.participante-checkbox:checked').length;
        checkboxMarcarTodos.checked = (totalMarcados === totalCheckboxes && totalCheckboxes > 0);
        checkboxMarcarTodos.indeterminate = (totalMarcados > 0 && totalMarcados < totalCheckboxes);
    }
});

// Função para editar configurações
function editarConfiguracoes() {
    $('.config-field').prop('disabled', false);
    $('#btnSalvarConfig').show();
    $('#btnEditarConfig').hide();
}

// Função editarTimes movida para o script inline no topo (linha ~980)
// Esta função foi movida para garantir disponibilidade imediata quando o botão é clicado

// Função para mostrar/ocultar campo de quantidade de grupos
function toggleQuantidadeGrupos() {
    if (($('#modalidade_todos_chaves').is(':checked') || $('#modalidade_torneio_pro').is(':checked')) && 
        (!$('#modalidade_todos_chaves').prop('disabled') || !$('#modalidade_torneio_pro').prop('disabled'))) {
        $('#divQuantidadeGrupos').show();
        $('input[name="quantidade_grupos"]').prop('required', true);
    } else {
        $('#divQuantidadeGrupos').hide();
        $('input[name="quantidade_grupos"]').prop('required', false);
    }
}

// Função para gerar eliminatórias
function gerarEliminatorias() {
    if (!confirm('Tem certeza que deseja gerar as chaves eliminatórias?\n\nApós gerar, não será mais possível editar os resultados da fase de grupos.')) return;
    
    $.ajax({
        url: '../ajax/gerar_eliminatorias.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar eliminatórias', 'danger');
        }
    });
}

// Inicializar ao carregar página
// Função para ver perfil do usuário
function verPerfilUsuario(usuarioId) {
    const modal = new bootstrap.Modal(document.getElementById('modalVerPerfil'));
    const conteudo = document.getElementById('conteudoPerfil');
    
    // Mostrar loading
    conteudo.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
    modal.show();
    
    // Buscar detalhes do usuário
    $.ajax({
        url: '../ajax/obter_detalhes_usuario.php',
        method: 'GET',
        data: { usuario_id: usuarioId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.usuario) {
                const u = response.usuario;
                
                // Ajustar caminho da foto
                let avatar = u.foto_perfil || '../../assets/arquivos/logo.png';
                if (avatar && avatar.trim() !== '') {
                    if (avatar.indexOf('http') !== 0 && avatar.indexOf('/') !== 0) {
                        if (avatar.indexOf('../../assets/') === 0 || avatar.indexOf('../assets/') === 0 || avatar.indexOf('assets/') === 0) {
                            if (avatar.indexOf('../../assets/') !== 0) {
                                if (avatar.indexOf('../assets/') === 0) {
                                    avatar = '../' + avatar;
                                } else if (avatar.indexOf('assets/') === 0) {
                                    avatar = '../../' + avatar;
                                }
                            }
                        } else {
                            avatar = '../../assets/arquivos/' + avatar;
                        }
                    }
                } else {
                    avatar = '../../assets/arquivos/logo.png';
                }
                
                const inicialNome = u.nome ? u.nome.charAt(0).toUpperCase() : '?';
                const dataNasc = u.data_aniversario ? new Date(u.data_aniversario).toLocaleDateString('pt-BR') : 'Não informado';
                const dataCadastro = u.data_cadastro ? new Date(u.data_cadastro).toLocaleDateString('pt-BR') : 'Não informado';
                
                let html = '<div class="text-center mb-4">';
                html += '<div style="position:relative;display:inline-block;">';
                html += '<img src="' + avatar + '" class="rounded-circle" width="100" height="100" style="object-fit:cover;border:3px solid #007bff;" alt="' + (u.nome || '') + '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                html += '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:100px;height:100px;font-weight:bold;font-size:2.5rem;border:3px solid #007bff;display:none;position:absolute;top:0;left:50%;transform:translateX(-50%);" title="' + (u.nome || '') + '">' + inicialNome + '</div>';
                html += '</div>';
                html += '<h4 class="mt-3 mb-0">' + (u.nome || 'Usuário') + '</h4>';
                html += '</div>';
                
                html += '<div class="list-group">';
                html += '<div class="list-group-item"><strong><i class="fas fa-envelope me-2"></i>E-mail:</strong><br>' + (u.email || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-phone me-2"></i>Telefone:</strong><br>' + (u.telefone || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-star me-2"></i>Nível:</strong><br>' + (u.nivel || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-venus-mars me-2"></i>Gênero:</strong><br>' + (u.genero || 'Não informado') + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-birthday-cake me-2"></i>Data de Nascimento:</strong><br>' + dataNasc + '</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-trophy me-2"></i>Reputação:</strong><br>' + (u.reputacao || 0) + ' pontos</div>';
                html += '<div class="list-group-item"><strong><i class="fas fa-calendar me-2"></i>Data de Cadastro:</strong><br>' + dataCadastro + '</div>';
                html += '</div>';
                
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do usuário.</div>';
            }
        },
        error: function() {
            conteudo.innerHTML = '<div class="alert alert-danger">Erro ao buscar dados do usuário.</div>';
        }
    });
}

$(document).ready(function() {
    toggleQuantidadeGrupos();
    <?php if ($inscricoes_abertas || $tem_solicitacoes_pendentes): ?>
    carregarSolicitacoes();
    <?php endif; ?>
});

// Formulário modalidade do torneio
$('#formModalidadeTorneio').on('submit', function(e) {
    e.preventDefault();
    
    // Validar quantidade de grupos se for modalidade todos_chaves ou torneio_pro
    if ($('#modalidade_todos_chaves').is(':checked') || $('#modalidade_torneio_pro').is(':checked')) {
        const quantidadeGrupos = $('input[name="quantidade_grupos"]:checked').val();
        if (!quantidadeGrupos) {
            showAlert('Selecione a quantidade de chaves', 'danger');
            return;
        }
        
        // Verificar se o radio está desabilitado (número ímpar de times)
        if ($('#modalidade_todos_chaves').prop('disabled') && $('#modalidade_torneio_pro').prop('disabled')) {
            showAlert('Não é possível criar chaves com número ímpar de times', 'danger');
            return;
        }
    }
    
    $.ajax({
        url: '../ajax/configurar_modalidade_torneio.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                // Mostrar botão Iniciar Jogos após salvar
                $('#btnIniciarJogos').show();
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar formato', 'danger');
        }
    });
});

// Função para iniciar jogos (gerar todos contra todos)
function iniciarJogos() {
    if (!confirm('Isso gerará todos os jogos de enfrentamento entre os times. Deseja continuar?')) return;
    
    // Recolher as seções de Informações, Configurações e Formato
    recolherSecoesTorneio();
    
    // Verificar se é modalidade todos_chaves e validar quantidade de times
    const modalidade = '<?php echo $torneio['modalidade'] ?? ''; ?>';
    const quantidadeGrupos = $('input[name="quantidade_grupos"]:checked').val() || <?php echo (int)($torneio['quantidade_grupos'] ?? 0); ?>;
    const quantidadeTimes = <?php echo $quantidade_times_db > 0 ? $quantidade_times_db : (int)($torneio['quantidade_times'] ?? 0); ?>;
    
    if (modalidade === 'todos_chaves' && quantidadeGrupos > 0) {
        // Verificar se o radio está desabilitado (número ímpar de times)
        if ($('#modalidade_todos_chaves').prop('disabled')) {
            showAlert('Não é possível criar chaves com número ímpar de times', 'danger');
            return;
        }
        
        // Verificar se a quantidade de times é divisível pela quantidade de chaves
        if (quantidadeTimes % quantidadeGrupos !== 0) {
            showAlert(
                'A quantidade total de times (' + quantidadeTimes + ') não é divisível pela quantidade de chaves (' + quantidadeGrupos + ').\n\n' +
                'Para criar chaves, a quantidade de times deve ser divisível pela quantidade de chaves.',
                'danger'
            );
            return;
        }
    }
    
    $.ajax({
        url: '../ajax/iniciar_jogos_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao iniciar jogos', 'danger');
        }
    });
}

// Função para habilitar edição da partida
function habilitarEdicaoPartida(partidaId) {
    // Verificar se é uma semi-final ou final (partidas eliminatórias podem ser editadas)
    const linhaPartida = $('#pontos_time1_' + partidaId).closest('tr');
    const tipoFase = linhaPartida.attr('data-tipo-fase') || '';
    const ehEliminatoria = tipoFase && (tipoFase.toLowerCase().includes('semi') || tipoFase.toLowerCase().includes('final') || tipoFase.toLowerCase().includes('3º lugar'));
    
    // Verificar se eliminatórias foram geradas (apenas para partidas da 1ª fase)
    if (!ehEliminatoria) {
        var temEliminatorias = <?php echo isset($tem_eliminatorias) && $tem_eliminatorias ? 'true' : 'false'; ?>;
        if (temEliminatorias) {
            showAlert('Não é possível editar partidas após gerar as eliminatórias.', 'warning');
            return;
        }
    }
    
    // Habilitar apenas campos de pontos (status será sempre Finalizada ao salvar)
    $('#pontos_time1_' + partidaId).prop('disabled', false).prop('readonly', false);
    $('#pontos_time2_' + partidaId).prop('disabled', false).prop('readonly', false);
    
    // Status sempre será Finalizada, então não precisa habilitar o campo
    // Mas vamos definir o valor como Finalizada no select oculto
    $('#status_' + partidaId).val('Finalizada');
    
    // Mostrar botão salvar e esconder editar
    $('#btn_salvar_' + partidaId).show();
    $('#btn_editar_' + partidaId).hide();
}

// Função para salvar resultado inline
function salvarResultadoPartidaInline(partidaId) {
    const pontosTime1 = parseInt($('#pontos_time1_' + partidaId).val()) || 0;
    const pontosTime2 = parseInt($('#pontos_time2_' + partidaId).val()) || 0;
    // Sempre finalizar ao salvar
    const status = 'Finalizada';
    
    if (pontosTime1 < 0 || pontosTime2 < 0) {
        showAlert('Os pontos não podem ser negativos', 'danger');
        return;
    }
    
    // Verificar se é uma semi-final usando o atributo data-tipo-fase
    const linhaPartida = $('#pontos_time1_' + partidaId).closest('tr');
    const tipoFase = linhaPartida.attr('data-tipo-fase') || '';
    const grupoNome = linhaPartida.attr('data-grupo-nome') || '';
    const ehSemifinal = tipoFase && (tipoFase.toLowerCase() === 'semi-final' || tipoFase.toLowerCase().includes('semi'));
    const ehOuro = grupoNome && (grupoNome.includes('Ouro') && grupoNome.includes('Chaves'));
    
    // Verificação de semi-final mantida para lógica, mas sem debug
    
    // Atualizar o select para mostrar "Finalizada"
    $('#status_' + partidaId).val('Finalizada');
    
    $.ajax({
        url: '../ajax/salvar_resultado_partida.php',
        method: 'POST',
        data: {
            partida_id: partidaId,
            pontos_time1: pontosTime1,
            pontos_time2: pontosTime2,
            status: status
        },
        dataType: 'json',
        success: function(response) {
            // Verificar se response é válido e se success é true
            if (!response) {
                showAlert('Erro: Resposta inválida do servidor', 'danger');
                return;
            }
            
            // Verificar success como boolean ou string 'true'
            const isSuccess = response.success === true || response.success === 'true' || response.success === 1;
            
            if (isSuccess) {
                // Mostrar mensagem de sucesso
                const mensagem = response.message || 'Resultado salvo com sucesso!';
                showAlert(mensagem, 'success');
                
                // Bloquear campos novamente (disabled e readonly)
                $('#pontos_time1_' + partidaId).prop('disabled', true).prop('readonly', true);
                $('#pontos_time2_' + partidaId).prop('disabled', true).prop('readonly', true);
                $('#status_' + partidaId).prop('disabled', true);
                
                // Esconder botão salvar e mostrar editar
                $('#btn_salvar_' + partidaId).hide();
                $('#btn_editar_' + partidaId).show();
                
                // Recarregar após 1.5 segundos para atualizar classificação
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao salvar resultado';
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao salvar partida:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'Sem resposta'
            });
            
            // Verificar se a resposta é HTML (erro PHP)
            if (xhr.responseText && xhr.responseText.trim().startsWith('<!')) {
                showAlert('Erro no servidor: O servidor retornou uma página de erro HTML. Verifique os logs do PHP. Abra o console (F12) para mais detalhes.', 'danger');
                console.error('Resposta HTML completa:', xhr.responseText);
                return;
            }
            
            // Tentar fazer parse do JSON mesmo em caso de erro HTTP
            try {
                if (xhr.responseText) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success === true || response.success === 'true') {
                        // Se o servidor retornou success=true, tratar como sucesso
                        showAlert(response.message || 'Resultado salvo com sucesso!', 'success');
                        $('#pontos_time1_' + partidaId).prop('disabled', true).prop('readonly', true);
                        $('#pontos_time2_' + partidaId).prop('disabled', true).prop('readonly', true);
                        $('#btn_salvar_' + partidaId).hide();
                        $('#btn_editar_' + partidaId).show();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                        return;
                    } else if (response.message) {
                        showAlert(response.message, 'danger');
                        return;
                    }
                }
            } catch (e) {
                console.error('Erro ao fazer parse do JSON:', e);
                console.error('Resposta recebida:', xhr.responseText ? xhr.responseText.substring(0, 500) : 'Sem resposta');
            }
            
            // Mensagem de erro genérico
            let errorMsg = 'Erro ao salvar resultado';
            if (xhr.status === 0) {
                errorMsg += ': Sem conexão com o servidor';
            } else if (xhr.status === 500) {
                errorMsg += ': Erro interno do servidor (500)';
            } else if (xhr.status === 404) {
                errorMsg += ': Arquivo não encontrado (404)';
            } else {
                errorMsg += ': ' + (error || 'Erro de comunicação');
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

// Função para habilitar edição da chave eliminatória
function habilitarEdicaoChave(chaveId) {
    $('#pontos_time1_chave_' + chaveId).prop('disabled', false).prop('readonly', false);
    $('#pontos_time2_chave_' + chaveId).prop('disabled', false).prop('readonly', false);
    
    // Mostrar botão salvar e esconder editar
    $('#btn_salvar_chave_' + chaveId).show();
    $('#btn_editar_chave_' + chaveId).hide();
}

// Função para salvar resultado da chave eliminatória
function salvarResultadoChave(chaveId) {
    const pontosTime1 = parseInt($('#pontos_time1_chave_' + chaveId).val()) || 0;
    const pontosTime2 = parseInt($('#pontos_time2_chave_' + chaveId).val()) || 0;
    const status = 'Finalizada';
    
    if (pontosTime1 < 0 || pontosTime2 < 0) {
        showAlert('Os pontos não podem ser negativos', 'danger');
        return;
    }
    
    $.ajax({
        url: '../ajax/salvar_resultado_chave.php',
        method: 'POST',
        data: {
            chave_id: chaveId,
            pontos_time1: pontosTime1,
            pontos_time2: pontosTime2,
            status: status
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                
                // Desabilitar campos novamente
                $('#pontos_time1_chave_' + chaveId).prop('disabled', true).prop('readonly', true);
                $('#pontos_time2_chave_' + chaveId).prop('disabled', true).prop('readonly', true);
                
                // Esconder botão salvar e mostrar editar
                $('#btn_salvar_chave_' + chaveId).hide();
                $('#btn_editar_chave_' + chaveId).show();
                
                // Recarregar após 1 segundo para atualizar final e 3º lugar
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao salvar resultado', 'danger');
        }
    });
}

// Função antiga mantida para compatibilidade (redireciona para nova)
function editarChave(chaveId) {
    habilitarEdicaoChave(chaveId);
}

// Função para imprimir enfrentamentos
function imprimirEnfrentamentos() {
    try {
        var elemento = document.getElementById('tabela-enfrentamentos');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        
        // Criar uma cópia do elemento para modificar sem afetar o original
        var clone = elemento.cloneNode(true);
        
        // Remover botões e elementos de ação
        var elementosRemover = clone.querySelectorAll('.btn, button, .badge, select, .form-select, .no-print');
        elementosRemover.forEach(function(el) {
            el.remove();
        });
        
        // Remover ícones (FontAwesome e imagens)
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        // Remover divs de cor dos times (quadrados coloridos)
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        // Remover cores de fundo e bordas coloridas de outros elementos
        var elementosComCor = clone.querySelectorAll('[style*="border-left"], [style*="background-color"]');
        elementosComCor.forEach(function(el) {
            var style = el.getAttribute('style') || '';
            style = style.replace(/border-left[^;]*;?/gi, '');
            style = style.replace(/background-color[^;]*;?/gi, '');
            if (style.trim()) {
                el.setAttribute('style', style);
            } else {
                el.removeAttribute('style');
            }
        });
        
        // Processar inputs de placar: deixar em branco na impressão
        var containersPlacar = clone.querySelectorAll('.d-flex.align-items-center.gap-1.justify-content-center');
        containersPlacar.forEach(function(container) {
            var inputs = container.querySelectorAll('input[type="number"]');
            if (inputs.length === 2) {
                // Deixar placar em branco
                container.innerHTML = '&nbsp;&nbsp;&nbsp; x &nbsp;&nbsp;&nbsp;';
            }
        });
        
        // Remover inputs restantes que não foram processados
        var inputsRestantes = clone.querySelectorAll('input[type="number"]');
        inputsRestantes.forEach(function(input) {
            input.remove();
        });
        
        // Remover colunas Status e Ações - encontrar índices primeiro
        var headers = clone.querySelectorAll('thead th');
        var indicesRemover = [];
        headers.forEach(function(th, index) {
            var texto = th.textContent.trim().toLowerCase();
            if (texto === 'status' || texto === 'ações' || texto === 'acoes') {
                indicesRemover.push(index);
                th.remove();
            }
        });
        
        // Remover células correspondentes em ordem reversa para manter índices corretos
        if (indicesRemover.length > 0) {
            indicesRemover.sort(function(a, b) { return b - a; }); // Ordenar decrescente
            var linhas = clone.querySelectorAll('tbody tr, thead tr');
            linhas.forEach(function(linha) {
                var celulas = linha.querySelectorAll('td, th');
                indicesRemover.forEach(function(indice) {
                    if (celulas[indice]) {
                        celulas[indice].remove();
                    }
                });
            });
        }
        
        // Limpar espaços em branco e elementos vazios
        var elementosVazios = clone.querySelectorAll('div:empty, span:empty');
        elementosVazios.forEach(function(el) {
            if (!el.textContent.trim()) {
                el.remove();
            }
        });
        
        // Reorganizar estrutura para chaves lado a lado
        var linhas = clone.querySelectorAll('tbody tr');
        var chaves = {};
        var chaveAtual = null;
        var rodadaAtual = null;
        
        linhas.forEach(function(linha) {
            var classes = linha.className;
            var texto = linha.textContent.trim();
            
            // Verificar se é cabeçalho de chave
            if (classes.includes('table-info')) {
                var matchChave = texto.match(/chave\s*(\d+)/i);
                if (matchChave) {
                    chaveAtual = 'Chave ' + matchChave[1];
                } else {
                    chaveAtual = texto.replace(/[^\w\s]/g, '').trim() || 'Chave 1';
                }
                if (!chaves[chaveAtual]) {
                    chaves[chaveAtual] = {};
                }
                rodadaAtual = null;
            }
            // Verificar se é cabeçalho de rodada
            else if (classes.includes('table-secondary')) {
                var matchRodada = texto.match(/rodada\s*(\d+)/i);
                if (matchRodada) {
                    rodadaAtual = 'Rodada ' + matchRodada[1];
                } else {
                    rodadaAtual = texto.replace(/[^\w\s]/g, '').trim() || 'Rodada 1';
                }
                if (chaveAtual && !chaves[chaveAtual][rodadaAtual]) {
                    chaves[chaveAtual][rodadaAtual] = [];
                }
            }
            // É uma linha de partida
            else if (chaveAtual && rodadaAtual) {
                if (!chaves[chaveAtual][rodadaAtual]) {
                    chaves[chaveAtual][rodadaAtual] = [];
                }
                chaves[chaveAtual][rodadaAtual].push(linha.outerHTML);
            }
        });
        
        // Se não encontrou chaves, usar estrutura original
        var conteudo = '';
        if (Object.keys(chaves).length === 0) {
            conteudo = clone.innerHTML;
        } else {
            // Criar nova estrutura com chaves lado a lado
            var chavesArray = Object.keys(chaves);
            var maxRodadas = 0;
            
            // Encontrar o máximo de rodadas em qualquer chave
            chavesArray.forEach(function(chave) {
                var rodadas = Object.keys(chaves[chave]);
                maxRodadas = Math.max(maxRodadas, rodadas.length);
            });
            
            // Criar tabela com colunas para cada chave
            conteudo = '<table style="width: 100%; border-collapse: collapse;">';
            
            // Cabeçalho com nomes das chaves
            conteudo += '<thead><tr>';
            chavesArray.forEach(function(chave) {
                conteudo += '<th style="width: ' + (100 / chavesArray.length) + '%; border: 1px solid #ddd; padding: 8px; text-align: center; background-color: #f2f2f2;"><strong>' + chave + '</strong></th>';
            });
            conteudo += '</tr></thead>';
            
            // Corpo com rodadas alinhadas
            conteudo += '<tbody>';
            for (var r = 1; r <= maxRodadas; r++) {
                var rodadaNome = 'Rodada ' + r;
                var temRodada = false;
                
                // Verificar se alguma chave tem esta rodada
                chavesArray.forEach(function(chave) {
                    if (chaves[chave][rodadaNome]) {
                        temRodada = true;
                    }
                });
                
                if (temRodada) {
                    // Linha de cabeçalho da rodada
                    conteudo += '<tr>';
                    chavesArray.forEach(function(chave) {
                        if (chaves[chave][rodadaNome]) {
                            conteudo += '<td style="border: 1px solid #ddd; padding: 6px; text-align: center; background-color: #e9ecef;"><strong>' + rodadaNome + '</strong></td>';
                        } else {
                            conteudo += '<td style="border: 1px solid #ddd; padding: 6px;"></td>';
                        }
                    });
                    conteudo += '</tr>';
                    
                    // Linhas de partidas - encontrar o máximo de partidas nesta rodada
                    var maxPartidas = 0;
                    chavesArray.forEach(function(chave) {
                        if (chaves[chave][rodadaNome]) {
                            maxPartidas = Math.max(maxPartidas, chaves[chave][rodadaNome].length);
                        }
                    });
                    
                    for (var p = 0; p < maxPartidas; p++) {
                        conteudo += '<tr>';
                        chavesArray.forEach(function(chave) {
                            if (chaves[chave][rodadaNome] && chaves[chave][rodadaNome][p]) {
                                // Extrair apenas o conteúdo das células (sem a tag tr)
                                var linhaHTML = chaves[chave][rodadaNome][p];
                                
                                // Processar HTML como string para extrair texto das células
                                var time1 = '';
                                var placar = 'x';
                                var time2 = '';
                                
                                // Extrair texto de cada célula usando regex
                                var matchCelulas = linhaHTML.match(/<td[^>]*>([\s\S]*?)<\/td>/gi);
                                if (matchCelulas) {
                                    matchCelulas.forEach(function(celulaHTML, idx) {
                                        // Remover tags HTML e extrair apenas texto
                                        var texto = celulaHTML.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                                        
                                        if (idx === 0 && texto) {
                                            // Primeira célula: Time 1
                                            time1 = texto;
                                        } else if (idx === 1 && texto) {
                                            // Segunda célula: Placar (já processado como "x")
                                            placar = texto.includes('x') ? 'x' : texto;
                                        } else if (idx === 2 && texto) {
                                            // Terceira célula: Time 2
                                            time2 = texto;
                                        }
                                    });
                                }
                                
                                // Criar estrutura com Time 1 à esquerda, x no meio, Time 2 à direita
                                var textoCompleto = '<table style="width: 100%; border-collapse: collapse;"><tr>' +
                                    '<td style="text-align: left; width: 40%; padding: 0;">' + time1 + '</td>' +
                                    '<td style="text-align: center; width: 20%; padding: 0;">&nbsp; x &nbsp;</td>' +
                                    '<td style="text-align: right; width: 40%; padding: 0;">' + time2 + '</td>' +
                                    '</tr></table>';
                                
                                // Criar uma única célula com todo o conteúdo
                                conteudo += '<td style="border: 1px solid #ddd; padding: 6px; white-space: nowrap;">' + textoCompleto + '</td>';
                            } else {
                                // Célula vazia
                                conteudo += '<td style="border: 1px solid #ddd; padding: 6px;"></td>';
                            }
                        });
                        conteudo += '</tr>';
                    }
                }
            }
            conteudo += '</tbody></table>';
        }
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Jogos de Enfrentamento';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 11px; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; font-size: 16px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }');
        janela.document.write('th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; }');
        janela.document.write('td { background-color: #fff !important; }');
        janela.document.write('.table-info { background-color: #f2f2f2 !important; }');
        janela.document.write('.table-info td { text-align: center !important; }');
        janela.document.write('.table-secondary { background-color: #f2f2f2 !important; }');
        janela.document.write('.table-secondary td { text-align: center !important; }');
        janela.document.write('td { white-space: nowrap; }');
        janela.document.write('td div { display: inline !important; margin: 0 3px !important; }');
        janela.document.write('td strong { display: inline !important; }');
        janela.document.write('td span { display: inline !important; }');
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write(conteudo);
        janela.document.write('</body></html>');
        janela.document.close();
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir enfrentamentos:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para imprimir times
function imprimirTimes() {
    try {
        var container = document.getElementById('containerTimes');
        if (!container) {
            alert('Elemento não encontrado!');
            return;
        }
        
        // Buscar todos os cards de times
        var cards = container.querySelectorAll('.col-md-6.col-lg-4');
        if (cards.length === 0) {
            alert('Nenhum time encontrado para imprimir!');
            return;
        }
        
        var totalTimes = cards.length;
        var colunasPorLinha = totalTimes >= 6 ? 3 : 2; // 3 colunas se 6+ times, senão 2
        var larguraColuna = colunasPorLinha === 3 ? '33.33%' : '50%';
        
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Times do Torneio';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 11px; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 15px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; font-size: 18px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; page-break-inside: avoid; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; padding: 8px; border: 1px solid #ddd; text-align: left; }');
        janela.document.write('td { padding: 6px 8px; border: 1px solid #ddd; }');
        janela.document.write('.time-header { background-color: #e9ecef; font-weight: bold; }');
        janela.document.write('.integrante-name { padding-left: 20px; }');
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write('<table>');
        
        // Processar times conforme o layout definido
        for (var i = 0; i < cards.length; i += colunasPorLinha) {
            janela.document.write('<tr>');
            
            // Processar até colunasPorLinha times por linha
            for (var col = 0; col < colunasPorLinha; col++) {
                var idx = i + col;
                
                if (idx < cards.length) {
                    var card = cards[idx];
                    var timeHeader = card.querySelector('.card-header strong');
                    var timeName = timeHeader ? timeHeader.textContent.trim() : 'Time ' + (idx + 1);
                    var integrantes = card.querySelectorAll('.participante-item small, .participante-item .d-flex small');
                    
                    janela.document.write('<td style="width: ' + larguraColuna + '; vertical-align: top;">');
                    janela.document.write('<table style="width: 100%;">');
                    janela.document.write('<tr><th class="time-header">' + timeName + '</th></tr>');
                    if (integrantes.length > 0) {
                        for (var j = 0; j < integrantes.length; j++) {
                            var nome = integrantes[j].textContent.trim();
                            if (nome) {
                                janela.document.write('<tr><td class="integrante-name">' + nome + '</td></tr>');
                            }
                        }
                    } else {
                        janela.document.write('<tr><td class="integrante-name">Nenhum integrante</td></tr>');
                    }
                    janela.document.write('</table>');
                    janela.document.write('</td>');
                } else {
                    // Se não houver time nesta posição, deixar célula vazia
                    janela.document.write('<td style="width: ' + larguraColuna + ';"></td>');
                }
            }
            
            janela.document.write('</tr>');
        }
        
        janela.document.write('</table>');
        janela.document.write('</body></html>');
        janela.document.close();
        
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir times:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para gerar 2ª fase do Torneio Pro
function gerarSegundaFaseTorneioPro() {
    if (!confirm('Isso gerará a 2ª fase com os 4 melhores times de cada chave. Deseja continuar?')) {
        return;
    }
    
    $.ajax({
        url: '../ajax/gerar_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            // Remover tabelas anteriores se existirem
            $('#tabelas_2fase').remove();
            $('#debug_2fase').remove();
            
            if (response.success) {
                showAlert(response.message, 'success');
                
                // Recarregar página após 1.5 segundos para atualizar os botões
                setTimeout(function() {
                    location.reload();
                }, 1500);
                
                return; // Não criar tabelas dinamicamente, deixar o PHP fazer isso após reload
                
                // Criar tabelas de classificação da 2ª fase (não é debug, são tabelas reais)
                let tabelasHtml = '<div id="tabelas_2fase" class="mt-3"><div class="row mb-3">';
                
                if (response.debug) {
                    // Exibir Ouro A
                    if (response.debug.ouro_a && response.debug.ouro_a.times && response.debug.ouro_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-warning"><div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Ouro A</h6></div><div class="card-body p-2 bg-warning-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.ouro_a.times.forEach(function(time) {
                            // Determinar texto do tooltip baseado na chave de origem e posição
                            let tooltipText = '';
                            if (time.chave_origem && time.chave_origem.includes('Chave 1')) {
                                tooltipText = '1° LUGAR A';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 3')) {
                                tooltipText = '1° LUGAR C';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 5')) {
                                tooltipText = '1° LUGAR E';
                            } else if (time.posicao_origem && time.posicao_origem.includes('2º lugar (Melhor)')) {
                                tooltipText = '1° MELHOR 2° LUGAR';
                            }
                            
                            let tooltipAttr = '';
                            if (tooltipText) {
                                tooltipAttr = ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltipText + '"';
                            }
                            let totalJogos = (parseInt(time.vitorias) || 0) + (parseInt(time.derrotas) || 0) + (parseInt(time.empates) || 0);
                            tabelasHtml += '<tr><td><strong' + tooltipAttr + '>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">' + totalJogos + '</span></td><td class="text-center"><span class="badge bg-success">' + (time.vitorias || 0) + '</span></td><td class="text-center"><span class="badge bg-danger">' + (time.derrotas || 0) + '</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Ouro B
                    if (response.debug.ouro_b && response.debug.ouro_b.times && response.debug.ouro_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-warning"><div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Ouro B</h6></div><div class="card-body p-2 bg-warning-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.ouro_b.times.forEach(function(time) {
                            // Determinar texto do tooltip baseado na chave de origem e posição
                            let tooltipText = '';
                            if (time.chave_origem && time.chave_origem.includes('Chave 2')) {
                                tooltipText = '1° LUGAR B';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 4')) {
                                tooltipText = '1° LUGAR D';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 6')) {
                                tooltipText = '1° LUGAR F';
                            } else if (time.posicao_origem && time.posicao_origem.includes('2º lugar (2º Melhor)')) {
                                tooltipText = '2° MELHOR 2° LUGAR';
                            }
                            
                            let tooltipAttr = '';
                            if (tooltipText) {
                                tooltipAttr = ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltipText + '"';
                            }
                            let totalJogos = (parseInt(time.vitorias) || 0) + (parseInt(time.derrotas) || 0) + (parseInt(time.empates) || 0);
                            tabelasHtml += '<tr><td><strong' + tooltipAttr + '>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">' + totalJogos + '</span></td><td class="text-center"><span class="badge bg-success">' + (time.vitorias || 0) + '</span></td><td class="text-center"><span class="badge bg-danger">' + (time.derrotas || 0) + '</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Prata A
                    if (response.debug.prata_a && response.debug.prata_a.times && response.debug.prata_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-secondary"><div class="card-header bg-secondary text-white"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Prata A</h6></div><div class="card-body p-2 bg-secondary-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.prata_a.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Prata B
                    if (response.debug.prata_b && response.debug.prata_b.times && response.debug.prata_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-secondary"><div class="card-header bg-secondary text-white"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Prata B</h6></div><div class="card-body p-2 bg-secondary-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.prata_b.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Bronze A
                    if (response.debug.bronze_a && response.debug.bronze_a.times && response.debug.bronze_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card" style="border-color: #8B4513;"><div class="card-header text-white" style="background-color: #8B4513;"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Bronze A</h6></div><div class="card-body p-2 bg-body-secondary"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.bronze_a.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Bronze B
                    if (response.debug.bronze_b && response.debug.bronze_b.times && response.debug.bronze_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card" style="border-color: #8B4513;"><div class="card-header text-white" style="background-color: #8B4513;"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Bronze B</h6></div><div class="card-body p-2 bg-body-secondary"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.bronze_b.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                }
                
                tabelasHtml += '</div></div>';
                
                // Inserir após o card da 2ª fase
                $('.card.border-warning').last().after(tabelasHtml);
                
                // Inicializar tooltips do Bootstrap
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
                
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                
                // Exibir erro de debug
                let debugHtml = '<div id="debug_2fase" class="mt-3">';
                debugHtml += '<div class="card border-danger">';
                debugHtml += '<div class="card-header bg-danger text-white">';
                debugHtml += '<h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Erro no Debug</h6>';
                debugHtml += '</div>';
                debugHtml += '<div class="card-body">';
                debugHtml += '<div class="alert alert-danger">';
                debugHtml += '<strong>Erro:</strong> ' + errorMsg;
                debugHtml += '</div>';
                
                if (response.debug) {
                    debugHtml += '<h6>Detalhes do Debug:</h6>';
                    debugHtml += '<pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
                    debugHtml += JSON.stringify(response.debug, null, 2);
                    debugHtml += '</pre>';
                }
                
                debugHtml += '</div></div></div>';
                
                // Inserir após o card da 2ª fase
                $('.card.border-warning').last().after(debugHtml);
                
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText,
                responseHeaders: xhr.getAllResponseHeaders()
            });
            
            // Remover debug anterior
            $('#debug_2fase').remove();
            
            // Exibir erro de debug
            let debugHtml = '<div id="debug_2fase" class="mt-3">';
            debugHtml += '<div class="card border-danger">';
            debugHtml += '<div class="card-header bg-danger text-white">';
            debugHtml += '<h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Erro AJAX</h6>';
            debugHtml += '</div>';
            debugHtml += '<div class="card-body">';
            debugHtml += '<div class="alert alert-danger">';
            debugHtml += '<strong>Erro de Comunicação:</strong> ' + error;
            debugHtml += '</div>';
            debugHtml += '<h6>Resposta do Servidor:</h6>';
            debugHtml += '<pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
            debugHtml += xhr.responseText.substring(0, 1000);
            debugHtml += '</pre>';
            debugHtml += '</div></div></div>';
            
            // Inserir após o card da 2ª fase
            $('.card.border-warning').last().after(debugHtml);
            
            let errorMsg = 'Erro ao gerar 2ª fase';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMsg = response.message;
                }
            } catch (e) {
                errorMsg += ': ' + xhr.responseText.substring(0, 200);
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

function gerarJogosSegundaFaseTorneioPro() {
    if (!confirm('Isso gerará os jogos/confrontos da 2ª fase. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/gerar_jogos_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar jogos da 2ª fase', 'danger');
        }
    });
}

// Função para limpar 2ª fase (grupos e jogos)
function limparSegundaFaseTorneioPro() {
    if (!confirm('Isso removerá TODOS os grupos e jogos da 2ª fase. Esta ação não pode ser desfeita. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/limpar_jogos_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao limpar 2ª fase';
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao limpar 2ª fase', 'danger');
        }
    });
}

// Função para popular tabela de classificação da 2ª fase
function popularClassificacao2Fase() {
    if (!confirm('Isso irá popular/atualizar a tabela de classificação da 2ª fase com os dados existentes da tabela torneio_classificacao. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/popular_classificacao_2fase.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let mensagem = response.message || 'Tabela populada com sucesso!';
                if (response.inseridos !== undefined && response.atualizados !== undefined) {
                    mensagem += `\nInseridos: ${response.inseridos}, Atualizados: ${response.atualizados}`;
                }
                showAlert(mensagem, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message || 'Erro ao popular tabela', 'danger');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Erro ao popular tabela de classificação';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch(e) {
                    errorMsg += ': ' + xhr.responseText.substring(0, 200);
                }
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

// Função para gerar final do Ouro
function gerarFinalOuro() {
    if (!confirm('Isso irá criar a final do Ouro com os 2 vencedores das semi-finais. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/gerar_final_ouro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar final';
                if (response.debug) {
                    errorMsg += '\n\nDebug:\n' + response.debug;
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Erro ao gerar final do Ouro';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                    if (response.debug) {
                        errorMsg += '\n\nDebug:\n' + response.debug;
                    }
                } catch(e) {
                    errorMsg += ': ' + xhr.responseText.substring(0, 200);
                }
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

// Função para imprimir enfrentamentos da 2ª fase
function imprimirEnfrentamentos2Fase() {
    try {
        var elemento = document.getElementById('tabela-enfrentamentos-2fase');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        var clone = elemento.cloneNode(true);
        
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        var janela = window.open('', '_blank');
        janela.document.write('<html><head><title>2ª Fase - Enfrentamentos</title>');
        janela.document.write('<style>body { font-family: Arial, sans-serif; padding: 20px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>');
        janela.document.write('</head><body>');
        janela.document.write('<h2>2ª Fase - Todos contra Todos</h2>');
        janela.document.write(clone.outerHTML);
        janela.document.write('</body></html>');
        janela.document.close();
        
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir 2ª fase:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para imprimir classificação
// Função para gerar 2ª fase do Torneio Pro
function gerarSegundaFaseTorneioPro() {
    if (!confirm('Isso gerará a 2ª fase com os 4 melhores times de cada chave. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/gerar_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            // Remover tabelas anteriores se existirem
            $('#tabelas_2fase').remove();
            $('#debug_2fase').remove();
            
            if (response.success) {
                showAlert(response.message, 'success');
                
                // Recarregar página após 1.5 segundos para atualizar os botões
                setTimeout(function() {
                    location.reload();
                }, 1500);
                
                return; // Não criar tabelas dinamicamente, deixar o PHP fazer isso após reload
                
                // Criar tabelas de classificação da 2ª fase (não é debug, são tabelas reais)
                let tabelasHtml = '<div id="tabelas_2fase" class="mt-3"><div class="row mb-3">';
                
                if (response.debug) {
                    // Exibir Ouro A
                    if (response.debug.ouro_a && response.debug.ouro_a.times && response.debug.ouro_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-warning"><div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Ouro A</h6></div><div class="card-body p-2 bg-warning-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.ouro_a.times.forEach(function(time) {
                            // Determinar texto do tooltip baseado na chave de origem e posição
                            let tooltipText = '';
                            if (time.chave_origem && time.chave_origem.includes('Chave 1')) {
                                tooltipText = '1° LUGAR A';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 3')) {
                                tooltipText = '1° LUGAR C';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 5')) {
                                tooltipText = '1° LUGAR E';
                            } else if (time.posicao_origem && time.posicao_origem.includes('2º lugar (Melhor)')) {
                                tooltipText = '1° MELHOR 2° LUGAR';
                            }
                            
                            let tooltipAttr = '';
                            if (tooltipText) {
                                tooltipAttr = ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltipText + '"';
                            }
                            let totalJogos = (parseInt(time.vitorias) || 0) + (parseInt(time.derrotas) || 0) + (parseInt(time.empates) || 0);
                            tabelasHtml += '<tr><td><strong' + tooltipAttr + '>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">' + totalJogos + '</span></td><td class="text-center"><span class="badge bg-success">' + (time.vitorias || 0) + '</span></td><td class="text-center"><span class="badge bg-danger">' + (time.derrotas || 0) + '</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Ouro B
                    if (response.debug.ouro_b && response.debug.ouro_b.times && response.debug.ouro_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-warning"><div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Ouro B</h6></div><div class="card-body p-2 bg-warning-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.ouro_b.times.forEach(function(time) {
                            // Determinar texto do tooltip baseado na chave de origem e posição
                            let tooltipText = '';
                            if (time.chave_origem && time.chave_origem.includes('Chave 2')) {
                                tooltipText = '1° LUGAR B';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 4')) {
                                tooltipText = '1° LUGAR D';
                            } else if (time.chave_origem && time.chave_origem.includes('Chave 6')) {
                                tooltipText = '1° LUGAR F';
                            } else if (time.posicao_origem && time.posicao_origem.includes('2º lugar (2º Melhor)')) {
                                tooltipText = '2° MELHOR 2° LUGAR';
                            }
                            
                            let tooltipAttr = '';
                            if (tooltipText) {
                                tooltipAttr = ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + tooltipText + '"';
                            }
                            let totalJogos = (parseInt(time.vitorias) || 0) + (parseInt(time.derrotas) || 0) + (parseInt(time.empates) || 0);
                            tabelasHtml += '<tr><td><strong' + tooltipAttr + '>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">' + totalJogos + '</span></td><td class="text-center"><span class="badge bg-success">' + (time.vitorias || 0) + '</span></td><td class="text-center"><span class="badge bg-danger">' + (time.derrotas || 0) + '</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Prata A
                    if (response.debug.prata_a && response.debug.prata_a.times && response.debug.prata_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-secondary"><div class="card-header bg-secondary text-white"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Prata A</h6></div><div class="card-body p-2 bg-secondary-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.prata_a.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Prata B
                    if (response.debug.prata_b && response.debug.prata_b.times && response.debug.prata_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card border-secondary"><div class="card-header bg-secondary text-white"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Prata B</h6></div><div class="card-body p-2 bg-secondary-subtle"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.prata_b.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Bronze A
                    if (response.debug.bronze_a && response.debug.bronze_a.times && response.debug.bronze_a.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card" style="border-color: #8B4513;"><div class="card-header text-white" style="background-color: #8B4513;"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Bronze A</h6></div><div class="card-body p-2 bg-body-secondary"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.bronze_a.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                    
                    // Exibir Bronze B
                    if (response.debug.bronze_b && response.debug.bronze_b.times && response.debug.bronze_b.times.length > 0) {
                        tabelasHtml += '<div class="col-md-6 mb-3"><div class="card" style="border-color: #8B4513;"><div class="card-header text-white" style="background-color: #8B4513;"><h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Classificação - Bronze B</h6></div><div class="card-body p-2 bg-body-secondary"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th width="50">Pos</th><th>Time</th><th class="text-center">Jogos</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Pontos</th><th class="text-center">PF</th><th class="text-center">PS</th><th class="text-center">Saldo</th><th class="text-center">Average</th></tr></thead><tbody>';
                        response.debug.bronze_b.times.forEach(function(time) {
                            tabelasHtml += '<tr><td><strong>' + time.posicao + 'º</strong></td><td><strong>' + time.time_nome + '</strong></td><td class="text-center"><span class="badge bg-info">0</span></td><td class="text-center"><span class="badge bg-success">' + time.vitorias + '</span></td><td class="text-center"><span class="badge bg-danger">0</span></td><td class="text-center"><strong>' + time.pontos_total + '</strong></td><td class="text-center">' + (time.pontos_pro || 0) + '</td><td class="text-center">' + (time.pontos_contra || 0) + '</td><td class="text-center">' + (time.saldo_pontos >= 0 ? '+' : '') + time.saldo_pontos + '</td><td class="text-center">' + time.average.toFixed(2) + '</td></tr>';
                        });
                        tabelasHtml += '</tbody></table></div></div></div></div>';
                    }
                }
                
                tabelasHtml += '</div></div>';
                
                // Inserir após o card da 2ª fase
                $('.card.border-warning').last().after(tabelasHtml);
                
                // Inicializar tooltips do Bootstrap
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
                
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                
                // Exibir erro de debug
                let debugHtml = '<div id="debug_2fase" class="mt-3">';
                debugHtml += '<div class="card border-danger">';
                debugHtml += '<div class="card-header bg-danger text-white">';
                debugHtml += '<h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Erro no Debug</h6>';
                debugHtml += '</div>';
                debugHtml += '<div class="card-body">';
                debugHtml += '<div class="alert alert-danger">';
                debugHtml += '<strong>Erro:</strong> ' + errorMsg;
                debugHtml += '</div>';
                
                if (response.debug) {
                    debugHtml += '<h6>Detalhes do Debug:</h6>';
                    debugHtml += '<pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
                    debugHtml += JSON.stringify(response.debug, null, 2);
                    debugHtml += '</pre>';
                }
                
                debugHtml += '</div></div></div>';
                
                // Inserir após o card da 2ª fase
                $('.card.border-warning').last().after(debugHtml);
                
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                responseText: xhr.responseText,
                responseHeaders: xhr.getAllResponseHeaders()
            });
            
            // Remover debug anterior
            $('#debug_2fase').remove();
            
            // Exibir erro de debug
            let debugHtml = '<div id="debug_2fase" class="mt-3">';
            debugHtml += '<div class="card border-danger">';
            debugHtml += '<div class="card-header bg-danger text-white">';
            debugHtml += '<h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Erro AJAX</h6>';
            debugHtml += '</div>';
            debugHtml += '<div class="card-body">';
            debugHtml += '<div class="alert alert-danger">';
            debugHtml += '<strong>Erro de Comunicação:</strong> ' + error;
            debugHtml += '</div>';
            debugHtml += '<h6>Resposta do Servidor:</h6>';
            debugHtml += '<pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
            debugHtml += xhr.responseText.substring(0, 1000);
            debugHtml += '</pre>';
            debugHtml += '</div></div></div>';
            
            // Inserir após o card da 2ª fase
            $('.card.border-warning').last().after(debugHtml);
            
            let errorMsg = 'Erro ao gerar 2ª fase';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMsg = response.message;
                }
            } catch (e) {
                errorMsg += ': ' + xhr.responseText.substring(0, 200);
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

function gerarJogosSegundaFaseTorneioPro() {
    if (!confirm('Isso gerará os jogos/confrontos da 2ª fase. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/gerar_jogos_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar jogos da 2ª fase', 'danger');
        }
    });
}

// Função para limpar 2ª fase (grupos e jogos)
function limparSegundaFaseTorneioPro() {
    if (!confirm('Isso removerá TODOS os grupos e jogos da 2ª fase. Esta ação não pode ser desfeita. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/limpar_jogos_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao limpar 2ª fase';
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao limpar 2ª fase', 'danger');
        }
    });
}

// Função para popular tabela de classificação da 2ª fase
function popularClassificacao2Fase() {
    if (!confirm('Isso irá popular/atualizar a tabela de classificação da 2ª fase com os dados existentes da tabela torneio_classificacao. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/popular_classificacao_2fase.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let mensagem = response.message || 'Tabela populada com sucesso!';
                if (response.inseridos !== undefined && response.atualizados !== undefined) {
                    mensagem += `\nInseridos: ${response.inseridos}, Atualizados: ${response.atualizados}`;
                }
                showAlert(mensagem, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message || 'Erro ao popular tabela', 'danger');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Erro ao popular tabela de classificação';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch(e) {
                    errorMsg += ': ' + xhr.responseText.substring(0, 200);
                }
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

// Função para gerar final do Ouro
function gerarFinalOuro() {
    if (!confirm('Isso irá criar a final do Ouro com os 2 vencedores das semi-finais. Deseja continuar?')) return;
    
    $.ajax({
        url: '../ajax/gerar_final_ouro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar final';
                if (response.debug) {
                    errorMsg += '\n\nDebug:\n' + response.debug;
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Erro ao gerar final do Ouro';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                    if (response.debug) {
                        errorMsg += '\n\nDebug:\n' + response.debug;
                    }
                } catch(e) {
                    errorMsg += ': ' + xhr.responseText.substring(0, 200);
                }
            }
            showAlert(errorMsg, 'danger');
        }
    });
}

// Função para imprimir enfrentamentos da 2ª fase
function imprimirEnfrentamentos2Fase() {
    try {
        var elemento = document.getElementById('tabela-enfrentamentos-2fase');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        var clone = elemento.cloneNode(true);
        
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        var janela = window.open('', '_blank');
        janela.document.write('<html><head><title>2ª Fase - Enfrentamentos</title>');
        janela.document.write('<style>body { font-family: Arial, sans-serif; padding: 20px; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>');
        janela.document.write('</head><body>');
        janela.document.write('<h2>2ª Fase - Todos contra Todos</h2>');
        janela.document.write(clone.outerHTML);
        janela.document.write('</body></html>');
        janela.document.close();
        
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir 2ª fase:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

function imprimirClassificacao() {
    try {
        var elemento = document.getElementById('tabela-classificacao');
        if (!elemento) {
            alert('Elemento não encontrado!');
            return;
        }
        // Criar uma cópia do elemento para modificar sem afetar o original
        var clone = elemento.cloneNode(true);
        
        // Remover ícones (FontAwesome e imagens)
        var icones = clone.querySelectorAll('i.fa, i.fas, img.rounded-circle, img');
        icones.forEach(function(icon) {
            icon.remove();
        });
        
        // Remover divs de cor dos times (quadrados coloridos)
        var divsCor = clone.querySelectorAll('div[style*="background-color"]');
        divsCor.forEach(function(div) {
            var style = div.getAttribute('style') || '';
            if (style.includes('background-color') && (style.includes('width: 16px') || style.includes('width: 20px'))) {
                div.remove();
            }
        });
        
        // Remover cores de fundo e bordas coloridas de outros elementos
        var elementosComCor = clone.querySelectorAll('[style*="border-left"], [style*="background-color"]');
        elementosComCor.forEach(function(el) {
            var style = el.getAttribute('style') || '';
            style = style.replace(/border-left[^;]*;?/gi, '');
            style = style.replace(/background-color[^;]*;?/gi, '');
            if (style.trim()) {
                el.setAttribute('style', style);
            } else {
                el.removeAttribute('style');
            }
        });
        
        var conteudo = clone.innerHTML;
        var titulo = '<?php echo addslashes(htmlspecialchars($torneio['nome'])); ?> - Classificação Geral';
        var janela = window.open('', '_blank', 'width=1,height=1');
        if (!janela) {
            alert('Por favor, permita pop-ups para esta funcionalidade.');
            return;
        }
        janela.document.open();
        janela.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + titulo + '</title>');
        janela.document.write('<style>');
        janela.document.write('@media print { @page { margin: 1cm; } body { font-family: Arial, sans-serif; font-size: 12px; } .no-print { display: none !important; } }');
        janela.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
        janela.document.write('h1 { text-align: center; margin-bottom: 20px; }');
        janela.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }');
        janela.document.write('th, td { border: 1px solid #ddd; padding: 8px; }');
        janela.document.write('th { background-color: #f2f2f2; font-weight: bold; text-align: center; }');
        janela.document.write('td { background-color: #fff !important; text-align: center; }');
        janela.document.write('td:first-child { text-align: left; }'); // Primeira coluna (Posição) alinhada à esquerda
        janela.document.write('td:nth-child(2) { text-align: left; }'); // Segunda coluna (Time) alinhada à esquerda
        janela.document.write('</style></head><body>');
        janela.document.write('<h1>' + titulo + '</h1>');
        janela.document.write(conteudo);
        janela.document.write('</body></html>');
        janela.document.close();
        // Abrir diálogo de impressão imediatamente após carregar
        setTimeout(function() {
            if (janela && !janela.closed) {
                janela.print();
            }
        }, 100);
    } catch (e) {
        console.error('Erro ao imprimir classificação:', e);
        alert('Erro ao gerar impressão. Verifique o console para mais detalhes.');
    }
}

// Função para limpar todos os jogos do torneio
function simularResultados() {
    if (!confirm('Isso irá gerar resultados aleatórios para todos os jogos não finalizados.\n\nDeseja continuar?')) return;
    
    const btnSimular = document.getElementById('btnSimularResultados');
    if (btnSimular) {
        btnSimular.disabled = true;
        btnSimular.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Simulando...';
    }
    
    // Buscar todas as partidas não finalizadas da 1ª fase
    $.ajax({
        url: '../ajax/listar_partidas_nao_finalizadas.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            fase: 'Grupos' // Filtrar apenas partidas da 1ª fase (Grupos)
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success || !response.partidas || response.partidas.length === 0) {
                showAlert('Nenhuma partida não finalizada encontrada na 1ª fase.', 'info');
                if (btnSimular) {
                    btnSimular.disabled = false;
                    btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Resultados';
                }
                return;
            }
            
            // Filtrar apenas partidas da 1ª fase (Grupos ou fase vazia/null)
            const partidas = response.partidas.filter(function(p) {
                return !p.fase || p.fase === 'Grupos' || p.fase === '' || p.fase === null;
            });
            
            if (partidas.length === 0) {
                showAlert('Nenhuma partida não finalizada encontrada na 1ª fase.', 'info');
                if (btnSimular) {
                    btnSimular.disabled = false;
                    btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Resultados';
                }
                return;
            }
            let processadas = 0;
            let erros = 0;
            
            // Função para processar cada partida
            function processarPartida(index) {
                if (index >= partidas.length) {
                    // Todas as partidas foram processadas
                    if (btnSimular) {
                        btnSimular.disabled = false;
                        btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Resultados';
                    }
                    
                    if (erros > 0) {
                        showAlert(`Simulação concluída! ${processadas} partidas processadas com sucesso, ${erros} erros.`, 'warning');
                    } else {
                        showAlert(`Simulação concluída! ${processadas} partidas processadas com sucesso.`, 'success');
                    }
                    
                    // Recarregar página após 1 segundo
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                    return;
                }
                
                const partida = partidas[index];
                
                // Gerar resultados aleatórios
                // Pontos entre 5 e 25, garantindo que um time ganhe
                const pontos1 = Math.floor(Math.random() * 21) + 5; // 5 a 25
                const pontos2 = Math.floor(Math.random() * 21) + 5; // 5 a 25
                
                // Garantir que não seja empate (ajustar se necessário)
                let pontosTime1 = pontos1;
                let pontosTime2 = pontos2;
                
                if (pontosTime1 === pontosTime2) {
                    // Se empate, adicionar 1 ponto a um dos times aleatoriamente
                    if (Math.random() > 0.5) {
                        pontosTime1++;
                    } else {
                        pontosTime2++;
                    }
                }
                
                // Salvar resultado da partida
                console.log('Salvando partida ' + partida.id + ': Time1=' + pontosTime1 + ', Time2=' + pontosTime2);
                $.ajax({
                    url: '../ajax/salvar_resultado_partida.php',
                    method: 'POST',
                    data: {
                        partida_id: partida.id,
                        pontos_time1: pontosTime1,
                        pontos_time2: pontosTime2,
                        status: 'Finalizada'
                    },
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            processadas++;
                            console.log('✓ Partida ' + partida.id + ' salva com sucesso');
                        } else {
                            erros++;
                            console.error('✗ Erro ao simular partida ' + partida.id + ':', result.message);
                            console.error('Resposta completa:', result);
                        }
                        
                        // Processar próxima partida com pequeno delay para não sobrecarregar
                        setTimeout(function() {
                            processarPartida(index + 1);
                        }, 100);
                    },
                    error: function(xhr, status, error) {
                        erros++;
                        console.error('✗ Erro HTTP ao simular partida ' + partida.id + ':', error);
                        console.error('Status:', status);
                        console.error('Resposta:', xhr.responseText);
                        // Continuar processando mesmo com erro
                        setTimeout(function() {
                            processarPartida(index + 1);
                        }, 100);
                    }
                });
            }
            
            // Iniciar processamento
            showAlert(`Iniciando simulação de ${partidas.length} partidas...`, 'info');
            processarPartida(0);
        },
        error: function() {
            showAlert('Erro ao buscar partidas para simulação.', 'danger');
            if (btnSimular) {
                btnSimular.disabled = false;
                btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Resultados';
            }
        }
    });
}

function simularResultados2Fase() {
    if (!confirm('Isso irá gerar resultados aleatórios para todos os jogos não finalizados da 2ª fase.\n\nDeseja continuar?')) return;
    
    const btnSimular = document.getElementById('btnSimularResultados2Fase');
    if (btnSimular) {
        btnSimular.disabled = true;
        btnSimular.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Simulando...';
    }
    
    // Buscar todas as partidas não finalizadas da 2ª fase
    $.ajax({
        url: '../ajax/listar_partidas_nao_finalizadas.php',
        method: 'POST',
        data: {
            torneio_id: <?php echo $torneio_id; ?>,
            fase: '2ª Fase'
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success || !response.partidas || response.partidas.length === 0) {
                showAlert('Nenhuma partida não finalizada encontrada na 2ª fase.', 'info');
                if (btnSimular) {
                    btnSimular.disabled = false;
                    btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase';
                }
                return;
            }
            
            // Filtrar apenas partidas da 2ª fase (garantir)
            const partidas2Fase = response.partidas.filter(function(p) {
                return p.fase === '2ª Fase';
            });
            
            if (partidas2Fase.length === 0) {
                showAlert('Nenhuma partida não finalizada encontrada na 2ª fase.', 'info');
                if (btnSimular) {
                    btnSimular.disabled = false;
                    btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase';
                }
                return;
            }
            
            let processadas = 0;
            let erros = 0;
            
            // Função para processar cada partida
            function processarPartida(index) {
                if (index >= partidas2Fase.length) {
                    if (btnSimular) {
                        btnSimular.disabled = false;
                        btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase';
                    }
                    
                    if (erros > 0) {
                        showAlert(`Simulação concluída! ${processadas} partidas processadas com sucesso, ${erros} erros.`, 'warning');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                    } else {
                        showAlert(`Simulação concluída! ${processadas} partidas da 2ª fase processadas com sucesso. Verificando se é possível gerar semi-finais...`, 'success');
                        
                        // Verificar se todas as partidas todos contra todos estão finalizadas e gerar semi-finais automaticamente
                        setTimeout(function() {
                            verificarEGerarSemifinais();
                        }, 1000);
                    }
                    return;
                }
                
                const partida = partidas2Fase[index];
                
                // Gerar pontuação aleatória (entre 10 e 25 pontos)
                const pontos1 = Math.floor(Math.random() * 16) + 10;
                const pontos2 = Math.floor(Math.random() * 16) + 10;
                
                // Salvar resultado da partida
                $.ajax({
                    url: '../ajax/salvar_resultado_partida.php',
                    method: 'POST',
                    data: {
                        partida_id: partida.id,
                        pontos_time1: pontos1,
                        pontos_time2: pontos2,
                        status: 'Finalizada'
                    },
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            processadas++;
                        } else {
                            erros++;
                            console.error('Erro ao simular partida ' + partida.id + ':', result.message);
                        }
                        
                        // Processar próxima partida com pequeno delay para não sobrecarregar
                        setTimeout(function() {
                            processarPartida(index + 1);
                        }, 100);
                    },
                    error: function() {
                        erros++;
                        console.error('Erro ao simular partida ' + partida.id);
                        // Continuar processando mesmo com erro
                        setTimeout(function() {
                            processarPartida(index + 1);
                        }, 100);
                    }
                });
            }
            
            // Iniciar processamento
            processarPartida(0);
        },
        error: function() {
            showAlert('Erro ao buscar partidas da 2ª fase', 'danger');
            if (btnSimular) {
                btnSimular.disabled = false;
                btnSimular.innerHTML = '<i class="fas fa-dice me-1"></i>Simular Jogos da 2ª Fase';
            }
        }
    });
}

// Função para verificar se todas as partidas todos contra todos estão finalizadas e gerar semi-finais
function verificarEGerarSemifinais() {
    $.ajax({
        url: '../ajax/verificar_partidas_finalizadas_2fase.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.todas_finalizadas && !response.tem_semifinais) {
                // Todas as partidas todos contra todos estão finalizadas e não há semi-finais ainda
                // Gerar semi-finais automaticamente
                gerarSemifinaisSegundaFaseTorneioPro(true);
            } else {
                // Recarregar página normalmente
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        },
        error: function() {
            // Em caso de erro, apenas recarregar
            setTimeout(function() {
                location.reload();
            }, 1000);
        }
    });
}

// Função para gerar semi-finais da 2ª fase
function gerarSemifinaisSegundaFaseTorneioPro(autoGerar) {
    if (!autoGerar && !confirm('Isso gerará as semi-finais da 2ª fase. Deseja continuar?')) {
        return;
    }
    
    $.ajax({
        url: '../ajax/gerar_semifinais_segunda_fase_torneio_pro.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (autoGerar) {
                    showAlert('Semi-finais geradas automaticamente!', 'success');
                } else {
                    showAlert(response.message, 'success');
                }
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar semi-finais';
                showAlert(errorMsg, 'danger');
                // Recarregar mesmo com erro para atualizar a página
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        },
        error: function() {
            showAlert('Erro ao gerar semi-finais', 'danger');
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    });
}

function limparJogos() {
    if (!confirm('Tem certeza que deseja limpar todos os jogos?\n\nEsta ação não pode ser desfeita e excluirá todos os jogos, resultados e classificação do torneio.')) return;
    
    $.ajax({
        url: '../ajax/limpar_jogos_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao limpar jogos', 'danger');
        }
    });
}

// Função para encerrar torneio
function encerrarTorneio() {
    if (!confirm('Tem certeza que deseja encerrar este torneio?\n\nApós encerrado, o torneio ficará apenas para visualização e não poderá mais ser editado (exceto pelo botão Editar Torneio).')) return;
    
    $.ajax({
        url: '../ajax/encerrar_torneio.php',
        method: 'POST',
        data: { torneio_id: <?php echo $torneio_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                let errorMsg = response.message || 'Erro desconhecido ao gerar 2ª fase';
                if (response.debug) {
                    console.error('Erro detalhado:', response.debug);
                    errorMsg += '\n\nVerifique o console (F12) para mais detalhes.';
                }
                showAlert(errorMsg, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao encerrar torneio', 'danger');
        }
    });
}

// Função para calcular quantidade de times automaticamente
window.calcularParticipantesNecessarios = function() {
    const tipoTime = document.getElementById('tipo_time');
    const quantidadeTimesInput = document.getElementById('quantidade_times');
    const integrantesInput = document.getElementById('integrantes_por_time');
    const maxParticipantesInput = document.getElementById('max_participantes');
    const infoQuantidadeTimes = document.getElementById('info_quantidade_times_auto');
    
    if (!tipoTime || !quantidadeTimesInput || !integrantesInput || !maxParticipantesInput) {
        console.error('Elementos não encontrados para calcularParticipantesNecessarios');
        return;
    }
    
    if (tipoTime.value) {
        const selectedOption = tipoTime.options[tipoTime.selectedIndex];
        const integrantes = parseInt(selectedOption.getAttribute('data-integrantes')) || 0;
        
        // Atualizar campo oculto de integrantes por time
            integrantesInput.value = integrantes;
        
        // Calcular quantidade de times baseado em: max_participantes / integrantes_por_time
        const maxParticipantes = parseInt(maxParticipantesInput.value) || 0;
        
        if (maxParticipantes > 0 && integrantes > 0) {
            const quantidadeTimes = Math.floor(maxParticipantes / integrantes);
            
            // Atualizar campo de quantidade de times (readonly, calculado automaticamente)
            quantidadeTimesInput.value = quantidadeTimes;
            
            // Mostrar mensagem informativa
            if (infoQuantidadeTimes) {
                infoQuantidadeTimes.style.display = 'block';
                infoQuantidadeTimes.innerHTML = `<i class="fas fa-info-circle me-1"></i>Calculado automaticamente: ${maxParticipantes} participantes ÷ ${integrantes} integrantes = ${quantidadeTimes} times`;
            }
        } else {
            quantidadeTimesInput.value = 0;
            if (infoQuantidadeTimes) {
                infoQuantidadeTimes.style.display = 'none';
            }
        }
    } else {
        // Se não há tipo de time selecionado, limpar campos
            integrantesInput.value = '';
        quantidadeTimesInput.value = 0;
        if (infoQuantidadeTimes) {
            infoQuantidadeTimes.style.display = 'none';
        }
    }
};

// Função duplicada removida - já está definida no script inline antes do HTML (linha ~736)
// Esta função foi movida para garantir disponibilidade imediata quando o botão é clicado

// Alias para compatibilidade
function processarSalvarConfiguracoes() {
    return window.processarSalvarConfiguracoes();
}

// Calcular ao carregar a página
$(document).ready(function() {
    calcularParticipantesNecessarios();
    
    // Prevenir submit tradicional do formulário
    $('#formConfigTorneio').off('submit').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        console.log('Formulário tentou submeter - bloqueado');
        return false;
    });
    
    // Handler no botão - chamar função diretamente
    // Usar delegação de eventos para garantir que funcione mesmo se o botão estiver oculto
    $(document).off('click', '#btnSalvarConfig').on('click', '#btnSalvarConfig', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('=== BOTÃO SALVAR CONFIGURAÇÕES CLICADO ===');
        console.log('Botão encontrado:', $(this).length > 0);
        console.log('ID do botão:', $(this).attr('id'));
        
        // Verificar se o botão existe
        const btn = $('#btnSalvarConfig');
        if (btn.length === 0) {
            console.error('ERRO: Botão #btnSalvarConfig não encontrado!');
            showAlert('Erro: Botão não encontrado. Recarregue a página.', 'danger');
            return false;
        }
        
        // Verificar se o botão está visível
        if (!btn.is(':visible')) {
            console.warn('AVISO: Botão está oculto, mas processando mesmo assim...');
        }
        
        processarSalvarConfiguracoes();
        
        return false;
    });
    
    // Verificar se o botão foi encontrado
    setTimeout(function() {
        const btn = $('#btnSalvarConfig');
        console.log('Verificação pós-carregamento - Botão encontrado:', btn.length > 0);
        console.log('Botão visível:', btn.is(':visible'));
        console.log('Botão disabled:', btn.prop('disabled'));
    }, 500);
});

// Handler antigo removido - agora usamos processarSalvarConfiguracoes() chamada diretamente pelo botão
// Este código foi movido para a função processarSalvarConfiguracoes() acima
</script>

<script>
// Função genérica para gerar semi-finais de qualquer série
function gerarSemifinais(serie) {
    const serieNome = serie === 'Ouro' ? 'Ouro' : (serie === 'Prata' ? 'Prata' : 'Bronze');
    if (!confirm(`Deseja gerar as semi-finais da série ${serieNome}? Isso criará 2 jogos eliminatórios:\n- 1º ${serieNome} A vs 1º ${serieNome} B\n- 2º ${serieNome} A vs 2º ${serieNome} B\n\nOs 2 vencedores irão para a final.`)) {
        return;
    }
    
    $.ajax({
        url: '../ajax/gerar_semifinais_ouro.php',
        method: 'POST',
        data: { 
            torneio_id: <?php echo $torneio_id; ?>,
            serie: serie
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar semi-finais da série ' + serieNome, 'danger');
        }
    });
}

// Funções específicas para cada série (mantidas para compatibilidade)
function gerarSemifinaisOuro() {
    gerarSemifinais('Ouro');
}

function gerarSemifinaisPrata() {
    gerarSemifinais('Prata');
}

function gerarSemifinaisBronze() {
    gerarSemifinais('Bronze');
}

// Função genérica para gerar final de qualquer série
function gerarFinal(serie) {
    const serieNome = serie === 'Ouro' ? 'Ouro' : (serie === 'Prata' ? 'Prata' : 'Bronze');
    if (!confirm(`Deseja gerar a final da série ${serieNome}? Isso criará 1 jogo entre os 2 vencedores das semi-finais.`)) {
        return;
    }
    
    $.ajax({
        url: '../ajax/gerar_final_ouro.php',
        method: 'POST',
        data: { 
            torneio_id: <?php echo $torneio_id; ?>,
            serie: serie
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Erro ao gerar final da série ' + serieNome, 'danger');
        }
    });
}

// Função específica para Ouro (mantida para compatibilidade)
function gerarFinalOuro() {
    gerarFinal('Ouro');
}
</script>

<?php endif; ?>

<!-- Pódio -->
<?php if ($modalidade === 'torneio_pro'): 
    // Buscar todas as finais finalizadas para criar o pódio
    $sql_podio = "SELECT p.*, 
                  t1.id AS time1_id, t1.nome AS time1_nome, t1.cor AS time1_cor,
                  t2.id AS time2_id, t2.nome AS time2_nome, t2.cor AS time2_cor,
                  tv.id AS vencedor_id, tv.nome AS vencedor_nome, tv.cor AS vencedor_cor,
                  p.serie
                  FROM partidas_2fase_eliminatorias p
                  LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                  LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                  LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                  WHERE p.torneio_id = ? AND p.tipo_eliminatoria = 'Final' AND p.status = 'Finalizada'
                  ORDER BY FIELD(p.serie, 'Ouro', 'Prata', 'Bronze')";
    $stmt_podio = executeQuery($pdo, $sql_podio, [$torneio_id]);
    $finais_finalizadas = $stmt_podio ? $stmt_podio->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Buscar partidas de 3º lugar se existirem
    $sql_terceiro = "SELECT p.*, 
                     t1.id AS time1_id, t1.nome AS time1_nome, t1.cor AS time1_cor,
                     t2.id AS time2_id, t2.nome AS time2_nome, t2.cor AS time2_cor,
                     tv.id AS vencedor_id, tv.nome AS vencedor_nome, tv.cor AS vencedor_cor,
                     p.serie
                     FROM partidas_2fase_eliminatorias p
                     LEFT JOIN torneio_times t1 ON t1.id = p.time1_id AND t1.torneio_id = p.torneio_id
                     LEFT JOIN torneio_times t2 ON t2.id = p.time2_id AND t2.torneio_id = p.torneio_id
                     LEFT JOIN torneio_times tv ON tv.id = p.vencedor_id
                     WHERE p.torneio_id = ? AND p.tipo_eliminatoria = '3º Lugar' AND p.status = 'Finalizada'
                     ORDER BY FIELD(p.serie, 'Ouro', 'Prata', 'Bronze')";
    $stmt_terceiro = executeQuery($pdo, $sql_terceiro, [$torneio_id]);
    $terceiros_lugares = $stmt_terceiro ? $stmt_terceiro->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Organizar terceiros lugares por série
    $terceiros_por_serie = [];
    foreach ($terceiros_lugares as $terceiro) {
        $serie_terceiro = $terceiro['serie'] ?? 'Ouro';
        $terceiros_por_serie[$serie_terceiro] = $terceiro;
    }
    
    if (!empty($finais_finalizadas)):
        // Buscar integrantes dos times para o pódio
        $integrantes_podio = [];
        foreach ($finais_finalizadas as $final) {
            $times_final = [$final['time1_id'], $final['time2_id']];
            foreach ($times_final as $time_id) {
                if ($time_id && !isset($integrantes_podio[$time_id])) {
                    $sql_integrantes = "SELECT tp.id AS participante_id, tp.*, u.nome AS usuario_nome, u.foto_perfil
                                       FROM torneio_time_integrantes tti
                                       JOIN torneio_participantes tp ON tp.id = tti.participante_id
                                       LEFT JOIN usuarios u ON u.id = tp.usuario_id
                                       WHERE tti.time_id = ?
                                       ORDER BY tp.nome_avulso, u.nome";
                    $stmt_integrantes = executeQuery($pdo, $sql_integrantes, [$time_id]);
                    $integrantes_podio[$time_id] = $stmt_integrantes ? $stmt_integrantes->fetchAll() : [];
                }
            }
        }
?>
<div class="row mb-4 mt-5">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark text-center">
                <h4 class="mb-0"><i class="fas fa-trophy me-2"></i>Pódio</h4>
            </div>
            <div class="card-body bg-warning-subtle">
                <?php 
                // Organizar vencedores por série
                $vencedores_por_serie = [];
                foreach ($finais_finalizadas as $final) {
                    $serie_final = $final['serie'] ?? 'Ouro';
                    $vencedor_id = $final['vencedor_id'];
                    $vencedores_por_serie[$serie_final] = [
                        'id' => $vencedor_id,
                        'nome' => $final['vencedor_nome'] ?? 'N/A',
                        'cor' => $final['vencedor_cor'] ?? '#000000'
                    ];
                }
                
                // Garantir ordem: Ouro, Prata, Bronze
                $series_ordenadas = ['Ouro', 'Prata', 'Bronze'];
                $podium_data = [];
                foreach ($series_ordenadas as $serie) {
                    if (isset($vencedores_por_serie[$serie])) {
                        $podium_data[] = [
                            'serie' => $serie,
                            'vencedor' => $vencedores_por_serie[$serie]
                        ];
                    }
                }
                
                if (!empty($podium_data)):
                ?>
                <!-- Layout de Pódio: Prata à esquerda, Ouro no centro (mais alto), Bronze à direita -->
                <div class="d-flex align-items-end justify-content-center gap-3" style="min-height: 250px; padding: 20px;">
                    <?php 
                    // Ordenar para exibir: Prata (2º), Ouro (1º), Bronze (3º)
                    $ordem_podio = [];
                    foreach ($podium_data as $item) {
                        if ($item['serie'] === 'Ouro') {
                            $ordem_podio[1] = $item; // Centro - mais alto
                        } elseif ($item['serie'] === 'Prata') {
                            $ordem_podio[0] = $item; // Esquerda
                        } elseif ($item['serie'] === 'Bronze') {
                            $ordem_podio[2] = $item; // Direita
                        }
                    }
                    ksort($ordem_podio);
                    ?>
                    
                    <!-- Prata (Esquerda) -->
                    <?php if (isset($ordem_podio[0])): 
                        $item = $ordem_podio[0];
                        $vencedor = $item['vencedor'];
                    ?>
                    <div class="text-center" style="flex: 1; max-width: 200px;">
                        <div class="p-4 mb-2" style="background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%); border-radius: 12px; min-height: 150px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                            <i class="fas fa-medal" style="font-size: 48px; color: #C0C0C0; text-shadow: 2px 2px 4px rgba(0,0,0,0.4); margin-bottom: 12px;"></i>
                            <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($vencedor['cor']); ?>; border-radius: 4px; display: inline-block; margin-bottom: 8px;"></div>
                            <strong style="color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.6); font-size: 18px; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($vencedor['nome']); ?></strong>
                            <small style="color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.6); font-size: 14px; font-weight: bold;">Série Prata</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Ouro (Centro - Mais Alto) -->
                    <?php if (isset($ordem_podio[1])): 
                        $item = $ordem_podio[1];
                        $vencedor = $item['vencedor'];
                    ?>
                    <div class="text-center" style="flex: 1; max-width: 240px;">
                        <div class="p-4 mb-2" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); border-radius: 12px; min-height: 200px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 6px 16px rgba(0,0,0,0.4);">
                            <i class="fas fa-trophy" style="font-size: 56px; color: #FFD700; text-shadow: 2px 2px 4px rgba(0,0,0,0.4); margin-bottom: 12px;"></i>
                            <div style="width: 22px; height: 22px; background-color: <?php echo htmlspecialchars($vencedor['cor']); ?>; border-radius: 4px; display: inline-block; margin-bottom: 8px;"></div>
                            <strong style="color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.6); font-size: 20px; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($vencedor['nome']); ?></strong>
                            <small style="color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.6); font-size: 15px; font-weight: bold;">Série Ouro</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Bronze (Direita) -->
                    <?php if (isset($ordem_podio[2])): 
                        $item = $ordem_podio[2];
                        $vencedor = $item['vencedor'];
                    ?>
                    <div class="text-center" style="flex: 1; max-width: 200px;">
                        <div class="p-4 mb-2" style="background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%); border-radius: 12px; min-height: 120px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                            <i class="fas fa-medal" style="font-size: 40px; color: #CD7F32; text-shadow: 2px 2px 4px rgba(0,0,0,0.4); margin-bottom: 12px;"></i>
                            <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($vencedor['cor']); ?>; border-radius: 4px; display: inline-block; margin-bottom: 8px;"></div>
                            <strong style="color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.6); font-size: 18px; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($vencedor['nome']); ?></strong>
                            <small style="color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.6); font-size: 14px; font-weight: bold;">Série Bronze</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
