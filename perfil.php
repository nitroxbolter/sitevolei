<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Meu Perfil';
requireLogin();

$usuario = getUserById($pdo, $_SESSION['user_id']);

// Processar atualização do perfil
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $senha_nova = $_POST['senha_nova'] ?? '';
        $senha_confirmar = $_POST['senha_confirmar'] ?? '';

        if (empty($senha_atual) || empty($senha_nova) || empty($senha_confirmar)) {
            $_SESSION['mensagem'] = 'Preencha todos os campos de senha.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header('Location: perfil.php');
            exit();
        }
        if (strlen($senha_nova) < 6) {
            $_SESSION['mensagem'] = 'A nova senha deve ter pelo menos 6 caracteres.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header('Location: perfil.php');
            exit();
        }
        if ($senha_nova !== $senha_confirmar) {
            $_SESSION['mensagem'] = 'A confirmação da senha não confere.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header('Location: perfil.php');
            exit();
        }
        // Validar senha atual
        $usuarioAtual = getUserById($pdo, $_SESSION['user_id']);
        if (!$usuarioAtual || !verificarSenha($senha_atual, $usuarioAtual['senha'])) {
            $_SESSION['mensagem'] = 'Senha atual incorreta.';
            $_SESSION['tipo_mensagem'] = 'danger';
            header('Location: perfil.php');
            exit();
        }
        $hash = hashSenha($senha_nova);
        $sql = "UPDATE usuarios SET senha = ? WHERE id = ?";
        if (executeQuery($pdo, $sql, [$hash, $_SESSION['user_id']])) {
            $_SESSION['mensagem'] = 'Senha alterada com sucesso!';
            $_SESSION['tipo_mensagem'] = 'success';
        } else {
            $_SESSION['mensagem'] = 'Erro ao alterar senha.';
            $_SESSION['tipo_mensagem'] = 'danger';
        }
        header('Location: perfil.php');
        exit();
    }
    // Usar valores atuais como padrão quando não enviados
    $nome = isset($_POST['nome']) ? sanitizar($_POST['nome']) : $usuario['nome'];
    $nivel = $_POST['nivel'] ?? $usuario['nivel'];
    $telefone = isset($_POST['telefone']) ? sanitizar($_POST['telefone']) : ($usuario['telefone'] ?? '');
    $disponibilidade = isset($_POST['disponibilidade']) ? sanitizar($_POST['disponibilidade']) : ($usuario['disponibilidade'] ?? '');
    $avatar_selecionado = isset($_POST['avatar_selecionado']) ? trim($_POST['avatar_selecionado']) : '';
    
    // Foto de perfil (avatar selecionado tem prioridade sobre upload)
    $foto_perfil = $usuario['foto_perfil'];
    if ($avatar_selecionado !== '') {
        $opcoes_validas = ['01','02','03','04','05','06'];
        if (in_array($avatar_selecionado, $opcoes_validas, true)) {
            $foto_perfil = 'assets/arquivos/' . $avatar_selecionado . '.png';
        }
    }
    if ($acao !== 'avatar' && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $nova_foto = uploadFoto($_FILES['foto_perfil']);
        if ($nova_foto) {
            // Remover foto antiga se existir e não for avatar padrão
            if ($foto_perfil && file_exists($foto_perfil) && strpos($foto_perfil, 'assets/arquivos/') !== 0) {
                @unlink($foto_perfil);
            }
            $foto_perfil = $nova_foto;
        }
    }
    
    // Processar foto 3x4 via base64 (cropper)
    $foto34Base64 = $_POST['foto34_cropped'] ?? '';
    if (!empty($foto34Base64) && preg_match('/^data:image\/(png|jpeg);base64,/', $foto34Base64)) {
        $dados = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $foto34Base64);
        $dados = str_replace(' ', '+', $dados);
        $bin = base64_decode($dados);
        if ($bin !== false) {
            $dir = __DIR__ . '/assets/arquivos/logousers';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $arquivo = $dir . '/' . (int)$_SESSION['user_id'] . '.png';
            if (function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($bin);
                if ($src) {
                    $dstW = 300; $dstH = 400; // 3x4
                    $dst = imagecreatetruecolor($dstW, $dstH);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    $w = imagesx($src); $h = imagesy($src);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $w, $h);
                    imagepng($dst, $arquivo);
                    imagedestroy($dst); imagedestroy($src);
                } else {
                    file_put_contents($arquivo, $bin);
                }
            } else {
                file_put_contents($arquivo, $bin);
            }
            $foto_perfil = 'assets/arquivos/logousers/' . (int)$_SESSION['user_id'] . '.png';
        }
    }

    $sql = "UPDATE usuarios SET nome = ?, nivel = ?, telefone = ?, disponibilidade = ?, foto_perfil = ? WHERE id = ?";
    $result = executeQuery($pdo, $sql, [$nome, $nivel, $telefone, $disponibilidade, $foto_perfil, $_SESSION['user_id']]);
    
    if ($result) {
        $_SESSION['mensagem'] = 'Perfil atualizado com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: perfil.php');
        exit();
    } else {
        $erro = 'Erro ao atualizar perfil';
    }
}

// Obter estatísticas do usuário
$sql = "SELECT 
            COUNT(DISTINCT j.id) as total_jogos,
            COUNT(DISTINCT CASE WHEN cp.status = 'Confirmado' THEN j.id END) as jogos_confirmados,
            COUNT(DISTINCT CASE WHEN cp.status = 'Ausente' THEN j.id END) as jogos_ausente,
            COUNT(DISTINCT g.id) as total_grupos,
            COUNT(DISTINCT t.id) as total_torneios
        FROM usuarios u
        LEFT JOIN grupo_membros gm ON u.id = gm.usuario_id AND gm.ativo = 1
        LEFT JOIN grupos g ON gm.grupo_id = g.id AND g.ativo = 1
        LEFT JOIN jogos j ON g.id = j.grupo_id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id AND cp.usuario_id = u.id
        LEFT JOIN torneio_participantes tp ON u.id = tp.usuario_id
        LEFT JOIN torneios t ON tp.torneio_id = t.id
        WHERE u.id = ?";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$estatisticas = $stmt ? $stmt->fetch() : ['total_jogos' => 0, 'jogos_confirmados' => 0, 'jogos_ausente' => 0, 'total_grupos' => 0, 'total_torneios' => 0];

// Obter histórico de jogos recentes
$sql = "SELECT j.*, g.nome as grupo_nome, cp.status as confirmacao_status
        FROM jogos j
        JOIN grupos g ON j.grupo_id = g.id
        LEFT JOIN confirmacoes_presenca cp ON j.id = cp.jogo_id AND cp.usuario_id = ?
        WHERE j.data_jogo < NOW() AND j.status = 'Finalizado'
        ORDER BY j.data_jogo DESC
        LIMIT 10";
$stmt = executeQuery($pdo, $sql, [$_SESSION['user_id']]);
$historico_jogos = $stmt ? $stmt->fetchAll() : [];

include 'includes/header.php';
?>

<div class="row">
    <!-- Sidebar do Perfil -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>Meu Perfil
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="position-relative d-inline-block mb-3">
                    <?php if ($usuario['foto_perfil']): ?>
                        <img src="<?php echo $usuario['foto_perfil']; ?>" class="rounded-circle" width="150" height="150" alt="Foto do perfil">
                    <?php else: ?>
                        <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                            <i class="fas fa-user fa-4x text-white"></i>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-light position-absolute bottom-0 end-0 translate-middle p-2 bg-primary text-white rounded-circle" title="Trocar foto" data-bs-toggle="modal" data-bs-target="#avatarModal">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                
                <h4><?php echo htmlspecialchars($usuario['nome']); ?></h4>
                <p class="text-muted"><?php echo $usuario['email']; ?></p>
                
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="badge bg-<?php echo $usuario['nivel'] === 'Profissional' ? 'danger' : ($usuario['nivel'] === 'Avançado' ? 'warning' : ($usuario['nivel'] === 'Intermediário' ? 'info' : 'secondary')); ?> me-2">
                        <?php echo $usuario['nivel']; ?>
                    </span>
                    <?php if ($usuario['is_premium']): ?>
                        <span class="badge bg-warning text-dark me-2">
                            <i class="fas fa-crown me-1"></i>Premium
                        </span>
                    <?php endif; ?>
                    <?php if ($usuario['is_admin']): ?>
                        <span class="badge bg-danger me-2">
                            <i class="fas fa-shield-alt me-1"></i>Admin
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php 
                    $rep = (int)($usuario['reputacao'] ?? 0);
                    $repBadgeClass = 'bg-danger'; // 0-25 vermelho
                    $repStyle = '';
                    if ($rep > 75) {
                        $repBadgeClass = 'bg-success'; // 76-100 verde
                    } elseif ($rep > 50) {
                        $repBadgeClass = 'bg-warning text-dark'; // 51-75 amarelo
                    } elseif ($rep > 25) {
                        // 26-50 laranja
                        $repBadgeClass = 'text-dark';
                        $repStyle = 'background-color:#fd7e14;';
                    }
                ?>
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <span class="badge fs-6 <?php echo $repBadgeClass; ?>" style="<?php echo $repStyle; ?>">
                        <i class="fas fa-star me-1"></i><?php echo $rep; ?> pts
                    </span>
                </div>
                
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editarPerfilModal">
                    <i class="fas fa-edit me-2"></i>Editar Perfil
                </button>
                <button class="btn btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#alterarSenhaModal">
                    <i class="fas fa-key me-2"></i>Trocar Senha
                </button>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Estatísticas
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h5 class="text-primary"><?php echo $estatisticas['total_jogos']; ?></h5>
                        <small class="text-muted">Jogos</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h5 class="text-success"><?php echo $estatisticas['jogos_confirmados']; ?></h5>
                        <small class="text-muted">Confirmados</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h5 class="text-warning"><?php echo $estatisticas['total_grupos']; ?></h5>
                        <small class="text-muted">Grupos</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h5 class="text-info"><?php echo $estatisticas['total_torneios']; ?></h5>
                        <small class="text-muted">Torneios</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="col-md-8">
        <!-- Informações Pessoais -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Informações Pessoais
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Nome:</strong>
                        <p><?php echo htmlspecialchars($usuario['nome']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong>
                        <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Telefone:</strong>
                        <p><?php echo $usuario['telefone'] ? htmlspecialchars($usuario['telefone']) : 'Não informado'; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Nível:</strong>
                        <p><span class="badge bg-<?php echo $usuario['nivel'] === 'Profissional' ? 'danger' : ($usuario['nivel'] === 'Avançado' ? 'warning' : ($usuario['nivel'] === 'Intermediário' ? 'info' : 'secondary')); ?>"><?php echo $usuario['nivel']; ?></span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Gênero:</strong>
                        <p>
                            <?php if (!empty($usuario['genero'])): ?>
                                <i class="fas fa-venus-mars me-1"></i><?php echo htmlspecialchars($usuario['genero']); ?>
                            <?php else: ?>
                                <span class="text-muted">Não informado</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong>
                        <p>
                            <?php if ($usuario['is_premium']): ?>
                                <span class="badge bg-warning text-dark me-1">
                                    <i class="fas fa-crown me-1"></i>Premium
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary me-1">Usuário Comum</span>
                            <?php endif; ?>
                            <?php if ($usuario['is_admin']): ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-shield-alt me-1"></i>Administrador
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Disponibilidade:</strong>
                        <p><?php echo $usuario['disponibilidade'] ? htmlspecialchars($usuario['disponibilidade']) : 'Não informado'; ?></p>
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Membro desde:</strong>
                        <p><?php echo formatarData($usuario['data_cadastro'], 'd/m/Y'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Histórico de Jogos -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Histórico de Jogos
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($historico_jogos)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum jogo no histórico ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Grupo</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico_jogos as $jogo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($jogo['titulo']); ?></td>
                                        <td><?php echo htmlspecialchars($jogo['grupo_nome']); ?></td>
                                        <td><?php echo formatarData($jogo['data_jogo']); ?></td>
                                        <td>
                                            <?php if ($jogo['confirmacao_status']): ?>
                                                <span class="badge bg-<?php echo $jogo['confirmacao_status'] === 'Confirmado' ? 'success' : 'danger'; ?>">
                                                    <?php echo $jogo['confirmacao_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Não participou</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Perfil -->
<div class="modal fade" id="editarPerfilModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Editar Perfil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php if (isset($erro)): ?>
                        <?php echo exibirMensagem('erro', $erro); ?>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="foto_perfil" class="form-label">Foto do Perfil</label>
                            <input type="file" class="form-control" id="foto_perfil" name="foto_perfil" 
                                   accept="image/*">
                            <div class="form-text">Formatos aceitos: JPG, PNG, GIF (máx. 2MB)</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nivel" class="form-label">Nível *</label>
                            <select class="form-select" id="nivel" name="nivel" required>
                                <option value="Iniciante" <?php echo $usuario['nivel'] === 'Iniciante' ? 'selected' : ''; ?>>Iniciante</option>
                                <option value="Intermediário" <?php echo $usuario['nivel'] === 'Intermediário' ? 'selected' : ''; ?>>Intermediário</option>
                                <option value="Avançado" <?php echo $usuario['nivel'] === 'Avançado' ? 'selected' : ''; ?>>Avançado</option>
                                <option value="Profissional" <?php echo $usuario['nivel'] === 'Profissional' ? 'selected' : ''; ?>>Profissional</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" 
                                   value="<?php echo htmlspecialchars($usuario['telefone']); ?>" 
                                   placeholder="(00) 00000-0000">
                        </div>
                    </div>

                    
                    
                    

                    <div class="mb-3">
                        <label for="disponibilidade" class="form-label">Disponibilidade</label>
                        <textarea class="form-control" id="disponibilidade" name="disponibilidade" rows="3" 
                                  placeholder="Ex: Finais de semana, manhãs, noites..."><?php echo htmlspecialchars($usuario['disponibilidade']); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarPerfil">
                        <i class="fas fa-save me-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Alterar Senha -->
<div class="modal fade" id="alterarSenhaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Alterar Senha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="alterar_senha">
                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                    </div>
                    <div class="mb-3">
                        <label for="senha_nova" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="senha_nova" name="senha_nova" minlength="6" required>
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label for="senha_confirmar" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="senha_confirmar" name="senha_confirmar" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Selecionar Avatar / Trocar Foto -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-image me-2"></i>Trocar Foto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="avatar">
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <?php 
                        $avatars = ['01','02','03','04','05','06'];
                        foreach ($avatars as $av) { 
                            $path = 'assets/arquivos/' . $av . '.png';
                            $checked = ($usuario['foto_perfil'] === $path) ? 'checked' : '';
                        ?>
                        <label class="d-inline-block text-center" style="cursor:pointer;">
                            <input type="radio" name="avatar_selecionado" value="<?php echo $av; ?>" class="form-check-input" <?php echo $checked; ?>>
                            <img src="<?php echo $path; ?>" alt="Avatar <?php echo $av; ?>" class="rounded-circle d-block border" style="width:64px;height:64px; object-fit:cover;">
                        </label>
                        <?php } ?>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <label class="form-label">Ou envie uma Foto 3x4</label>
                        <input type="file" class="form-control" id="foto34_avatar_input" accept="image/*">
                        <input type="hidden" name="foto34_cropped" id="foto34_avatar_cropped">
                        <div class="mt-2" style="width:150px; height:200px; overflow:hidden; border:1px dashed #ccc; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <img id="foto34_avatar_preview" style="width:150px; height:200px; display:none; object-fit:cover;" alt="Prévia 3x4">
                        </div>
                        <small class="text-muted">Recorte fixo 3x4 (300x400 pixels ao salvar)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarAvatar">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    </div>

<?php include 'includes/footer.php'; ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
var cropper34Avatar = null;
// Cropper para modal Trocar Foto (avatar)
document.getElementById('foto34_avatar_input')?.addEventListener('change', function(e){
    var file = e.target.files && e.target.files[0];
    if(!file) return;
    var url = URL.createObjectURL(file);
    var img = document.getElementById('foto34_avatar_preview');
    img.src = url;
    img.style.display = 'block';
    if (cropper34Avatar) { cropper34Avatar.destroy(); cropper34Avatar = null; }
    cropper34Avatar = new Cropper(img, {
        aspectRatio: 3/4,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        background: false,
        responsive: false,
        minContainerWidth: 150,
        minContainerHeight: 200,
    });
});
document.getElementById('btnSalvarAvatar')?.addEventListener('click', function(e){
    if (cropper34Avatar) {
        e.preventDefault();
        var canvas = cropper34Avatar.getCroppedCanvas({ width: 300, height: 400, imageSmoothingQuality: 'high' });
        if (canvas) {
            document.getElementById('foto34_avatar_cropped').value = canvas.toDataURL('image/png');
        }
        this.closest('form').submit();
    }
});
</script>
